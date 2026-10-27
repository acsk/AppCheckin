import ComunicacaoTexto from "@/src/components/ComunicacaoTexto";
import EnquetePerguntas, { corpoRespostas, perguntasFaltando, respostasIniciais, type Pergunta, type Resposta, type Respostas } from "@/src/components/EnquetePerguntas";
import AppHeader from "@/src/components/ds/AppHeader";
import Badge from "@/src/components/ds/Badge";
import Button from "@/src/components/ds/Button";
import Section from "@/src/components/ds/Section";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { atualizarResumoComunicacoes, chamarComunicacoes, textoPendencias } from "@/src/services/comunicacoes";
import { Feather } from "@expo/vector-icons";
import { useLocalSearchParams, useRouter } from "expo-router";
import React, { useCallback, useEffect, useState } from "react";
import { ActivityIndicator, Image, Linking, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

/**
 * Comunicação aberta: conteúdo completo, "Li e estou ciente" ou a enquete. Abrir marca como visualizada;
 * a confirmação e o envio é que liberam o check-in (quando obrigatória).
 */

type Resultado = { id: number; pergunta: string; tipo_resposta: string; respondentes: number; opcoes?: { id: number | string; rotulo: string; total: number }[]; media?: number | null };
type Comunicacao = {
  id: number;
  tipo: string;
  tipo_nome: string;
  titulo: string;
  descricao: string | null;
  conteudo: string | null;
  classificacao: string;
  obrigatoria: boolean;
  status: string;
  inicio_em: string | null;
  termino_em: string | null;
  imagem_url: string | null;
  concluida: boolean;
  confirmada_em: string | null;
  concluida_em: string | null;
  pendente: boolean;
  configuracoes: { links?: { rotulo: string; url: string }[]; anexos?: { nome: string; url: string; mime?: string | null }[]; enquete?: { anonima?: boolean; permitir_alterar?: boolean } };
  perguntas?: Pergunta[];
  minhas_respostas?: Record<string, Resposta>;
  pode_responder?: boolean;
  resultados?: { participantes: number; destinatarios: number; perguntas: Resultado[] };
  participantes?: number;
};

const dataBr = (v: string | null) => (v ? v.slice(0, 16).replace(/^(\d{4})-(\d{2})-(\d{2})/, "$3/$2/$1") : "");
const TOM: Record<string, "info" | "warning" | "success" | "danger"> = { info: "info", alerta: "warning", sucesso: "success", urgente: "danger" };

export default function ComunicacaoDetalhe() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [c, setC] = useState<Comunicacao | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [respostas, setRespostas] = useState<Respostas>({});
  const [enviando, setEnviando] = useState(false);
  const [aviso, setAviso] = useState<{ ok: boolean; texto: string } | null>(null);

  const carregar = useCallback(async () => {
    setErro(null);
    try {
      const d = await chamarComunicacoes("GET", `/${id}`);
      const com: Comunicacao = d.comunicacao;
      setC(com);
      setRespostas(respostasIniciais(com.minhas_respostas));
      void atualizarResumoComunicacoes();
    } catch (e: any) {
      setErro(e?.message || "Não foi possível abrir.");
    }
  }, [id]);
  useEffect(() => { void carregar(); }, [carregar]);

  const voltar = () => (router.canGoBack() ? router.back() : router.replace("/comunicacoes"));

  async function concluir(caminho: string, body?: unknown) {
    setEnviando(true);
    setAviso(null);
    try {
      const d = await chamarComunicacoes("POST", caminho, body);
      await atualizarResumoComunicacoes();
      const restantes = Number(d?.total_pendencias || 0);
      setAviso({ ok: true, texto: restantes ? `${d?.message || "Pronto."} ${textoPendencias(restantes)}` : `${d?.message || "Pronto."}${c?.obrigatoria ? " Check-in liberado." : ""}` });
      await carregar();
    } catch (e: any) {
      setAviso({ ok: false, texto: e?.message || "Não foi possível enviar." });
    } finally {
      setEnviando(false);
    }
  }

  const faltando = perguntasFaltando(c?.perguntas || [], respostas);
  const enviar = () => concluir(`/${id}/responder`, corpoRespostas(c?.perguntas || [], respostas));

  const podeResponder = c?.tipo === "enquete" && c.pode_responder;
  const tom = TOM[c?.classificacao || "info"] || "info";

  return (
    <SafeAreaView style={styles.container} edges={["left", "right"]}>
      <AppHeader title={c?.tipo_nome || "Comunicação"} eyebrow="Central de Comunicação" onBack={voltar} />
      {!c && !erro ? (
        <ActivityIndicator style={{ marginTop: space[8] }} color={ds.brand} />
      ) : erro ? (
        <View style={styles.vazio}>
          <Feather name="alert-circle" size={22} color={ds.inkMuted} />
          <Text style={styles.vazioTexto}>{erro}</Text>
          <Button variant="secondary" onPress={() => void carregar()}>Tentar de novo</Button>
        </View>
      ) : c ? (
        <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
          <View style={styles.card}>
            <View style={styles.badges}>
              <Badge tone={tom}>{c.tipo_nome}</Badge>
              {c.obrigatoria ? <Badge tone={c.concluida ? "success" : "warning"} icon={c.concluida ? "check" : "lock"}>{c.concluida ? "Concluída" : "Obrigatória"}</Badge> : null}
              {c.status === "encerrada" ? <Badge tone="neutral">Encerrada</Badge> : null}
            </View>
            <Text style={styles.titulo} accessibilityRole="header">{c.titulo}</Text>
            {c.termino_em ? <Text style={styles.meta}>Vigência: {dataBr(c.inicio_em) || "agora"} a {dataBr(c.termino_em)}</Text> : null}
            {c.imagem_url ? <Image source={{ uri: c.imagem_url }} style={styles.imagem} resizeMode="cover" accessibilityIgnoresInvertColors /> : null}
            {c.descricao ? <Text style={styles.descricao}>{c.descricao}</Text> : null}
            <ComunicacaoTexto>{c.conteudo}</ComunicacaoTexto>
            {[...(c.configuracoes.links || []).map((l) => ({ ...l, icon: "link" as const })), ...(c.configuracoes.anexos || []).map((a) => ({ rotulo: a.nome, url: a.url, icon: "paperclip" as const }))].map((l, i) => (
              <Pressable key={i} style={styles.link} accessibilityRole="link" onPress={() => void Linking.openURL(l.url)}>
                <Feather name={l.icon} size={16} color={ds.brandInk} />
                <Text style={styles.linkTexto} numberOfLines={1}>{l.rotulo}</Text>
                <Feather name="external-link" size={14} color={ds.inkMuted} />
              </Pressable>
            ))}
          </View>

          {c.obrigatoria && c.pendente ? (
            <View style={styles.trava}>
              <Feather name="lock" size={16} color={ds.warning} />
              <Text style={styles.travaTexto}>Enquanto não {c.tipo === "enquete" ? "enviar as respostas" : "confirmar a leitura"}, o check-in fica indisponível. O resto do app funciona normalmente.</Text>
            </View>
          ) : null}

          {c.tipo === "enquete" ? (
            <Section title={c.configuracoes.enquete?.anonima ? "Perguntas · respostas anônimas" : "Perguntas"} bare>
              <EnquetePerguntas perguntas={c.perguntas || []} respostas={respostas} disabled={!podeResponder || enviando} onChange={setRespostas} />
            </Section>
          ) : null}

          {c.resultados ? (
            <Section title={`Resultados · ${c.resultados.participantes} participantes`} bare>
              <View style={{ gap: space[3] }}>
                {c.resultados.perguntas.filter((r) => r.opcoes).map((r) => (
                  <View key={r.id} style={styles.card}>
                    <Text style={styles.pergunta}>{r.pergunta}</Text>
                    {r.opcoes!.map((o) => {
                      const pct = r.respondentes ? Math.round((o.total / r.respondentes) * 100) : 0;
                      return (
                        <View key={String(o.id)} style={{ gap: 4 }} accessibilityLabel={`${o.rotulo}: ${pct}%`}>
                          <View style={styles.resLinha}><Text style={styles.resRotulo}>{o.rotulo}</Text><Text style={styles.resPct}>{pct}%</Text></View>
                          <View style={styles.resTrilho}><View style={[styles.resBarra, { width: `${pct}%` }]} /></View>
                        </View>
                      );
                    })}
                  </View>
                ))}
              </View>
            </Section>
          ) : c.participantes != null ? <Text style={styles.meta}>{c.participantes} {c.participantes === 1 ? "pessoa já respondeu" : "pessoas já responderam"}</Text> : null}

          {aviso ? (
            <View style={[styles.aviso, aviso.ok ? styles.avisoOk : styles.avisoErro]} accessibilityLiveRegion="polite">
              <Feather name={aviso.ok ? "check-circle" : "alert-circle"} size={16} color={aviso.ok ? ds.success : ds.danger} />
              <Text style={styles.avisoTexto}>{aviso.texto}</Text>
            </View>
          ) : null}

          {/* Notificação opcional já conta como lida ao abrir; o resto pede a confirmação */}
          {c.tipo !== "enquete" && !c.concluida ? (
            <Button block size="lg" icon="check" loading={enviando} onPress={() => void concluir(`/${id}/confirmar`)}>Li e estou ciente</Button>
          ) : null}
          {c.tipo !== "enquete" && c.concluida && c.confirmada_em && c.tipo !== "notificacao" ? (
            <Text style={styles.meta}>Leitura confirmada em {dataBr(c.confirmada_em)}</Text>
          ) : null}
          {podeResponder ? (
            <>
              {faltando.length ? <Text style={styles.meta}>Falta responder: {faltando.join(", ")}</Text> : null}
              <Button block size="lg" icon="send" loading={enviando} disabled={faltando.length > 0} onPress={() => void enviar()}>
                {c.concluida ? "Alterar respostas" : "Enviar respostas"}
              </Button>
            </>
          ) : c.tipo === "enquete" && c.concluida ? <Text style={styles.meta}>Respostas enviadas em {dataBr(c.concluida_em)}</Text> : null}
        </ScrollView>
      ) : null}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: ds.ground },
  scroll: { padding: space[4], gap: space[4], paddingBottom: space[8] },
  card: { backgroundColor: ds.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: ds.line, padding: space[4], gap: space[3] },
  badges: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
  titulo: { ...type.title, color: ds.ink },
  meta: { ...type.caption, color: ds.inkMuted },
  descricao: { ...type.body, fontWeight: "700", color: ds.inkMuted },
  imagem: { width: "100%", aspectRatio: 16 / 9, borderRadius: radius.md, backgroundColor: ds.surfaceSunken },
  link: { flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 44, paddingHorizontal: space[3], borderRadius: radius.md, backgroundColor: ds.brandSoft },
  linkTexto: { flex: 1, ...type.caption, fontSize: 14, color: ds.brandInk, fontWeight: "800" },
  trava: { flexDirection: "row", gap: space[2], padding: space[3], borderRadius: radius.md, backgroundColor: ds.warningSoft },
  travaTexto: { flex: 1, ...type.caption, color: ds.warning },
  pergunta: { ...type.heading, color: ds.ink },
  resLinha: { flexDirection: "row", justifyContent: "space-between", gap: space[2] },
  resRotulo: { flex: 1, ...type.caption, color: ds.ink },
  resPct: { ...type.caption, color: ds.inkMuted, fontVariant: ["tabular-nums"] },
  resTrilho: { height: 8, borderRadius: 4, backgroundColor: ds.surfaceSunken, overflow: "hidden" },
  resBarra: { height: 8, borderRadius: 4, backgroundColor: ds.brand },
  aviso: { flexDirection: "row", gap: space[2], padding: space[3], borderRadius: radius.md },
  avisoOk: { backgroundColor: ds.successSoft },
  avisoErro: { backgroundColor: ds.dangerSoft },
  avisoTexto: { flex: 1, ...type.caption, color: ds.ink },
  vazio: { alignItems: "center", gap: space[3], padding: space[8] },
  vazioTexto: { ...type.body, color: ds.inkMuted, textAlign: "center" },
});
