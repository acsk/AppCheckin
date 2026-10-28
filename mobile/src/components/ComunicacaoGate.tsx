import ComunicacaoTexto from "@/src/components/ComunicacaoTexto";
import EnquetePerguntas, { corpoRespostas, perguntasFaltando, respostasIniciais, type Pergunta, type Resposta, type Respostas } from "@/src/components/EnquetePerguntas";
import Modal from "@/src/components/ds/Modal";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { useFuncionalidade } from "@/src/hooks/useConfigRemota";
import {
  atualizarResumoComunicacoes,
  chamarComunicacoes,
  limparResumoComunicacoes,
  textoPendencias,
  useResumoComunicacoes,
} from "@/src/services/comunicacoes";
import { Feather } from "@expo/vector-icons";
import { useRouter, useSegments } from "expo-router";
import React, { useEffect, useRef, useState } from "react";
import { AppState, StyleSheet, Text, View } from "react-native";

/**
 * Comunicação em destaque ao abrir o app (e depois de um check-in recusado por pendência). Obrigatória:
 * contador, conteúdo e "Li e estou ciente"; enquete mostra as perguntas com as escolhas e "Enviar respostas".
 * Fechar só adia: a pendência
 * continua e o check-in segue travado pela API. O aluno navega normalmente pelo resto do app.
 */

type Detalhe = {
  id: number; tipo: string; titulo: string; descricao: string | null; conteudo: string | null; obrigatoria: boolean; concluida: boolean;
  inicio_em: string | null; termino_em: string | null; perguntas?: Pergunta[]; minhas_respostas?: Record<string, Resposta>; pode_responder?: boolean;
};

const dataCurta = (v: string | null) => (v ? v.slice(5, 10).split("-").reverse().join("/") : "agora");

export default function ComunicacaoGate({ enabled }: { enabled: boolean }) {
  const ligada = useFuncionalidade("comunicacao");
  const ativo = enabled && ligada;
  const resumo = useResumoComunicacoes();
  const router = useRouter();
  const segments = useSegments();
  const adiadas = useRef(new Set<number>());
  const [atual, setAtual] = useState<Detalhe | null>(null);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [, redesenhar] = useState(0);
  const [respostas, setRespostas] = useState<Respostas>({});

  // Carrega ao entrar e ao voltar para o app
  useEffect(() => {
    if (!ativo) {
      limparResumoComunicacoes();
      adiadas.current.clear();
      return undefined;
    }
    void atualizarResumoComunicacoes();
    const sub = AppState.addEventListener("change", (s) => { if (s === "active") void atualizarResumoComunicacoes(); });
    return () => sub.remove();
  }, [ativo]);

  // Pedido para mostrar as pendências (ex.: 409 no check-in): o que foi adiado volta a aparecer
  useEffect(() => {
    if (!resumo.abrirPendencias) return;
    resumo.pendencias.forEach((p) => adiadas.current.delete(Number(p.id)));
    redesenhar((n) => n + 1);
  }, [resumo.abrirPendencias]);

  const naTelaDaComunicacao = segments[0] === "comunicacao" || segments[0] === "comunicacoes" || segments[0] === "(auth)";
  const proximo = ativo && !naTelaDaComunicacao ? resumo.destaques.find((id) => !adiadas.current.has(id)) : undefined;

  useEffect(() => {
    if (!proximo || atual?.id === proximo) return;
    let vivo = true;
    chamarComunicacoes("GET", `/${proximo}`)
      .then((d) => {
        if (!vivo) return;
        setAtual(d.comunicacao);
        setRespostas(respostasIniciais(d.comunicacao?.minhas_respostas));
      })
      .catch(() => {
        adiadas.current.add(proximo);
        redesenhar((n) => n + 1);
      });
    return () => { vivo = false; };
  }, [proximo, resumo.abrirPendencias]);

  const fechar = () => {
    if (atual) adiadas.current.add(atual.id);
    setAtual(null);
    setErro(null);
    void atualizarResumoComunicacoes();
  };

  const posicao = atual && atual.obrigatoria ? resumo.pendencias.findIndex((p) => Number(p.id) === atual.id) + 1 : 0;
  const total = resumo.total_pendencias;
  const enquete = atual?.tipo === "enquete";

  async function primaria() {
    if (!atual) return;
    if (enquete && !atual.pode_responder) {
      // Já respondida (ou sem perguntas carregadas): abre a tela completa
      const id = atual.id;
      fechar();
      router.push(`/comunicacao/${id}`);
      return;
    }
    if (enquete) {
      const faltam = perguntasFaltando(atual.perguntas || [], respostas);
      if (faltam.length) {
        setErro(`Responda a${faltam.length > 1 ? "s perguntas" : " pergunta"} ${faltam.join(", ")} para enviar.`);
        return;
      }
    }
    if (!atual.obrigatoria && atual.concluida) {
      fechar();
      return;
    }
    setEnviando(true);
    setErro(null);
    try {
      if (enquete) await chamarComunicacoes("POST", `/${atual.id}/responder`, corpoRespostas(atual.perguntas || [], respostas));
      else await chamarComunicacoes("POST", `/${atual.id}/confirmar`);
      adiadas.current.add(atual.id);
      setAtual(null);
      await atualizarResumoComunicacoes();
    } catch (e: any) {
      setErro(e?.message || "Não foi possível confirmar. Tente de novo.");
    } finally {
      setEnviando(false);
    }
  }

  if (!atual || !proximo) return null;

  return (
    <Modal
      visible
      variant="dialog"
      avoidKeyboard={enquete}
      icon={enquete ? "bar-chart-2" : atual.obrigatoria ? "alert-octagon" : "bell"}
      eyebrow={atual.obrigatoria ? (total > 1 && posicao ? `Obrigatória ${posicao} de ${total}` : "Comunicado importante") : "Novidade da academia"}
      title={atual.titulo}
      primary={{
        label: enquete ? (atual.pode_responder ? "Enviar respostas" : "Ver enquete") : atual.obrigatoria || !atual.concluida ? "Li e estou ciente" : "Entendi",
        icon: enquete ? "send" : "check",
        onPress: () => void primaria(),
        loading: enviando,
      }}
      onClose={fechar}
      exigirLeitura
      semFechar
      larga
    >
      {atual.obrigatoria && total > 1 ? <Text style={styles.contador}>{textoPendencias(total)}</Text> : null}
      {atual.descricao ? <Text style={styles.descricao}>{atual.descricao}</Text> : null}
      <ComunicacaoTexto>{atual.conteudo}</ComunicacaoTexto>
      {enquete && atual.pode_responder && atual.perguntas?.length ? (
        <EnquetePerguntas perguntas={atual.perguntas} respostas={respostas} disabled={enviando} cartoes={false}
          onChange={(r) => { setRespostas(r); setErro(null); }} />
      ) : null}
      {atual.termino_em ? <Text style={styles.meta}>Vigência: {dataCurta(atual.inicio_em)} a {dataCurta(atual.termino_em)}</Text> : null}
      {atual.obrigatoria ? (
        <View style={styles.trava}>
          <Feather name="lock" size={15} color={ds.warning} />
          <Text style={styles.travaTexto}>
            {enquete ? "Envie as respostas para liberar o check-in." : "Enquanto não confirmar, o botão de check-in permanece indisponível."} Você pode fechar e continuar usando o app.
          </Text>
        </View>
      ) : null}
      {erro ? <Text style={styles.erro}>{erro}</Text> : null}
    </Modal>
  );
}

const styles = StyleSheet.create({
  contador: { ...type.caption, color: ds.warning, fontWeight: "800" },
  descricao: { ...type.body, fontWeight: "700", color: ds.inkMuted },
  meta: { ...type.caption, color: ds.inkMuted },
  trava: { flexDirection: "row", gap: space[2], padding: space[3], borderRadius: radius.md, backgroundColor: ds.warningSoft },
  travaTexto: { flex: 1, ...type.caption, color: ds.warning },
  erro: { ...type.caption, color: ds.danger },
});
