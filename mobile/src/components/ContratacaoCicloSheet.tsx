import Modal from "@/src/components/ds/Modal";
import PaymentMethodOptions from "@/src/components/ds/PaymentMethodOptions";
import PixPaymentSheet from "@/src/components/ds/PixPaymentSheet";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { brl, chamarRecorrencia, type Oferta, type PagamentoAberto } from "@/src/services/recorrencia";
import { copyToClipboard } from "@/src/utils/clipboard";
import type { MetodoPagamentoOpcao } from "@/src/utils/metodosPagamento";
import { Feather } from "@expo/vector-icons";
import React, { useEffect, useState } from "react";
import { ActivityIndicator, Linking, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";

/**
 * Contratação de um ciclo que também é vendido como assinatura com fidelidade, em etapas:
 *   1. valor: à vista (o ciclo de uma vez) ou assinatura (mensal com fidelidade);
 *   2. informações contratuais: termos e aceite (só na assinatura);
 *   3. forma de pagamento.
 * À vista devolve a forma escolhida para a tela, que segue o fluxo de pagamento de sempre (inclusive troca
 * de plano). A assinatura contrata aqui mesmo: cada mês é uma cobrança separada, não um parcelamento.
 */

type Etapa = "valor" | "termos" | "pagamento";
type Modo = "avista" | "assinatura";

export type CicloContratacao = {
  id: number;
  nome: string;
  meses: number;
  valor_formatado: string;
  valor_mensal_formatado: string;
  fidelizacao_meses?: number | null;
};

type Props = {
  visible: boolean;
  planoId: number;
  ciclo: CicloContratacao;
  /** Data (dd/mm/aaaa) em que a assinatura fica disponível, quando o plano atual ainda vale. */
  assinaturaDisponivelEm: string | null;
  metodosAvista: MetodoPagamentoOpcao[];
  rotuloAvista: (metodo: MetodoPagamentoOpcao) => string;
  onPagarAvista: (metodo: MetodoPagamentoOpcao) => void;
  onClose: () => void;
  onAssinado: (contratoId: number) => void;
};

export default function ContratacaoCicloSheet({ visible, planoId, ciclo, assinaturaDisponivelEm, metodosAvista, rotuloAvista, onPagarAvista, onClose, onAssinado }: Props) {
  const [etapa, setEtapa] = useState<Etapa>("valor");
  const [modo, setModo] = useState<Modo>("avista");
  const [oferta, setOferta] = useState<Oferta | null>(null);
  const [aceite, setAceite] = useState(false);
  const [metodo, setMetodo] = useState<MetodoPagamentoOpcao | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  const [pix, setPix] = useState<{ contratoId: number; pagamento: NonNullable<PagamentoAberto> } | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [conferindo, setConferindo] = useState(false);

  useEffect(() => {
    if (!visible) return;
    setEtapa("valor"); setModo("avista"); setAceite(false); setErro(null); setAviso(null); setOferta(null);
    chamarRecorrencia("GET", `/ofertas?plano_id=${planoId}`)
      .then(({ ok, json }) => {
        const o = ok ? (json?.data?.ofertas || []).find((x: Oferta) => Number(x.plano_ciclo_id) === Number(ciclo.id)) : null;
        setOferta(o || null);
      })
      .catch(() => setOferta(null));
  }, [visible, planoId, ciclo.id]);

  const fidelidade = oferta?.fidelizacao_meses || ciclo.fidelizacao_meses || ciclo.meses;
  const assinaturaTravada = !!assinaturaDisponivelEm;
  const total = modo === "avista" ? 2 : 3;
  const passo = etapa === "valor" ? 1 : etapa === "termos" ? 2 : total;

  function avancar() {
    setErro(null);
    if (etapa === "valor") {
      if (modo === "assinatura" && !oferta) {
        setErro("A assinatura deste ciclo não está disponível agora.");
        return;
      }
      setMetodo(modo === "avista" ? metodosAvista[0] ?? null : "cartao");
      setEtapa(modo === "avista" ? "pagamento" : "termos");
      return;
    }
    if (etapa === "termos") {
      if (!aceite) {
        setErro("Marque que leu e aceita os termos para continuar.");
        return;
      }
      setEtapa("pagamento");
      return;
    }
    if (!metodo) {
      setErro("Escolha a forma de pagamento.");
      return;
    }
    if (modo === "avista") {
      onClose();
      // Fecha antes: o fluxo de pagamento da tela abre os próprios modais (PIX, troca de plano)
      setTimeout(() => onPagarAvista(metodo), 300);
      return;
    }
    void assinar();
  }

  function voltar() {
    setErro(null);
    setEtapa(etapa === "pagamento" && modo === "assinatura" ? "termos" : "valor");
  }

  async function assinar() {
    if (!oferta || !metodo) return;
    setEnviando(true);
    try {
      const { ok, json } = await chamarRecorrencia("POST", "/contratar", { plano_ciclo_id: oferta.plano_ciclo_id, metodo, termos_hash: oferta.termos_hash });
      if (!ok) {
        if (json?.code === "TERMOS_DESATUALIZADOS" && json?.oferta) {
          setOferta(json.oferta); setAceite(false); setEtapa("termos");
        }
        setErro(json?.message || json?.error || "Não foi possível assinar agora.");
        return;
      }
      const contratoId = Number(json?.data?.contrato_id);
      const pagamento: PagamentoAberto = json?.data?.pagamento ?? null;
      if (pagamento?.pix?.qr_code) {
        setPix({ contratoId, pagamento });
        return;
      }
      if (pagamento?.url) await Linking.openURL(pagamento.url);
      onAssinado(contratoId);
    } catch {
      setErro("Sem conexão. Tente de novo.");
    } finally {
      setEnviando(false);
    }
  }

  async function conferir() {
    if (!pix) return;
    setConferindo(true);
    try {
      const { json } = await chamarRecorrencia("GET", `/contratos/${pix.contratoId}/pagamento`);
      if (!json?.data?.pagamento) {
        const id = pix.contratoId;
        setPix(null);
        onAssinado(id);
      } else {
        setAviso("Ainda não recebemos a confirmação. Se já pagou, aguarde alguns instantes.");
      }
    } finally {
      setConferindo(false);
    }
  }

  const titulo = etapa === "valor" ? `Como você quer pagar o ${ciclo.nome}?` : etapa === "termos" ? "Informações contratuais" : "Forma de pagamento";
  const rotulo = etapa !== "pagamento" ? "Continuar"
    : modo === "avista" ? (metodo ? rotuloAvista(metodo) : "Escolha a forma")
      : `Assinar por ${oferta ? brl(oferta.valor_mensal) : ciclo.valor_mensal_formatado}/mês`;

  return (
    <>
      <Modal
        visible={visible && !pix}
        variant="sheet"
        icon={etapa === "valor" ? "tag" : etapa === "termos" ? "file-text" : "credit-card"}
        eyebrow={`${ciclo.nome} · passo ${passo} de ${total}`}
        title={titulo}
        primary={{ label: rotulo, icon: etapa === "pagamento" ? "check" : "arrow-right", onPress: avancar, loading: enviando }}
        onClose={onClose}
      >
        {etapa === "valor" ? (
          <View style={{ gap: space[3] }} accessibilityRole="radiogroup">
            <Opcao on={modo === "avista"} onPress={() => setModo("avista")} titulo="À vista" preco={ciclo.valor_formatado}
              linhas={[`${ciclo.meses} meses de acesso`, "Pagamento único, por PIX, cartão ou boleto"]} />
            <Opcao on={modo === "assinatura"} onPress={() => setModo("assinatura")} titulo="Assinatura mensal" preco={`${ciclo.valor_mensal_formatado}/mês`}
              desabilitada={assinaturaTravada}
              linhas={assinaturaTravada
                ? [`Disponível quando seu plano atual vencer (${assinaturaDisponivelEm})`]
                : [`Fidelidade de ${fidelidade} meses`, "Uma cobrança por mês, não é parcelamento"]} />
          </View>
        ) : null}

        {etapa === "termos" && oferta ? (
          <>
            <View style={styles.fatos}>
              <Fato icon="calendar" texto={`${brl(oferta.valor_mensal)} por mês durante ${oferta.fidelizacao_meses} meses (sem fidelidade: ${brl(oferta.valor_mensal_referencia)}/mês). O total não fica preso no limite do cartão.`} />
              <Fato icon="lock" texto={`Desistência sem custo em até ${oferta.arrependimento_dias} dias após o aceite.`} />
              <Fato icon="x-circle" texto={oferta.regularizacao_ativa ? `Saindo antes do fim: paga só a diferença do desconto já recebido (${brl(oferta.desconto_mensal)} por mês pago), nunca de meses futuros.` : "Pode cancelar quando quiser, sem multa."} />
            </View>
            <ScrollView style={styles.termos} nestedScrollEnabled>
              <Text style={styles.termosTexto}>{oferta.termos}</Text>
            </ScrollView>
            <Pressable style={styles.aceite} onPress={() => { setAceite(!aceite); setErro(null); }} accessibilityRole="checkbox" accessibilityState={{ checked: aceite }}>
              <Feather name={aceite ? "check-square" : "square"} size={22} color={aceite ? ds.brandInk : ds.inkMuted} />
              <Text style={styles.aceiteTexto}>Li e aceito os termos desta assinatura</Text>
            </Pressable>
          </>
        ) : null}
        {etapa === "termos" && !oferta ? <ActivityIndicator color={ds.brand} /> : null}

        {etapa === "pagamento" ? (
          <>
            <View style={styles.resumo}>
              <Text style={styles.resumoTitulo}>{modo === "avista" ? `${ciclo.nome} à vista · ${ciclo.valor_formatado}` : `${ciclo.nome} · ${ciclo.valor_mensal_formatado}/mês`}</Text>
              <Text style={styles.meta}>{modo === "avista" ? `${ciclo.meses} meses de acesso, pagamento único` : `Fidelidade de ${fidelidade} meses · termos aceitos`}</Text>
            </View>
            <PaymentMethodOptions opcoes={modo === "avista" ? metodosAvista : ["cartao", "pix"]} value={metodo} onChange={setMetodo} />
            {modo === "assinatura" ? (
              <Text style={styles.meta}>{metodo === "cartao"
                ? "Você paga a 1ª mensalidade em seguida; as próximas são debitadas no mesmo cartão, uma por mês."
                : "Todo mês você recebe um PIX para pagar a mensalidade. A 1ª aparece em seguida."}</Text>
            ) : null}
          </>
        ) : null}

        {etapa !== "valor" ? (
          <Pressable style={styles.voltar} onPress={voltar} accessibilityRole="button">
            <Feather name="arrow-left" size={16} color={ds.inkMuted} />
            <Text style={styles.voltarTexto}>Voltar</Text>
          </Pressable>
        ) : null}
        {erro ? <Text style={styles.erro}>{erro}</Text> : null}
      </Modal>
      <PixPaymentSheet
        visible={!!pix}
        amount={brl(pix?.pagamento.valor ?? 0)}
        description="1ª mensalidade"
        code={pix?.pagamento.pix?.qr_code}
        qrBase64={pix?.pagamento.pix?.qr_code_base64}
        expiresAt={null}
        ticketUrl={pix?.pagamento.url}
        status={conferindo ? "checking" : "waiting"}
        notice={aviso}
        onCopy={copyToClipboard}
        onCheck={() => void conferir()}
        onOpenBank={() => { if (pix?.pagamento.url) void Linking.openURL(pix.pagamento.url); }}
        onClose={() => { const id = pix?.contratoId; setPix(null); if (id) onAssinado(id); }}
      />
    </>
  );
}

function Opcao({ on, onPress, titulo, preco, linhas, desabilitada }: { on: boolean; onPress: () => void; titulo: string; preco: string; linhas: string[]; desabilitada?: boolean }) {
  return (
    <Pressable style={[styles.opcao, on && styles.opcaoOn, desabilitada && styles.opcaoOff]} disabled={desabilitada} onPress={onPress}
      accessibilityRole="radio" accessibilityState={{ checked: on, disabled: desabilitada }} accessibilityLabel={`${titulo}, ${preco}. ${linhas.join(". ")}`}>
      <View style={[styles.radio, on && styles.radioOn]}>{on ? <Feather name="check" size={13} color={ds.onBrand} /> : null}</View>
      <View style={{ flex: 1, gap: 2 }}>
        <Text style={styles.opcaoTitulo}>{titulo}</Text>
        {linhas.map((l) => <Text key={l} style={styles.meta}>{l}</Text>)}
      </View>
      <Text style={[styles.opcaoPreco, on && { color: ds.brandInk }]}>{preco}</Text>
    </Pressable>
  );
}

function Fato({ icon, texto }: { icon: React.ComponentProps<typeof Feather>["name"]; texto: string }) {
  return (
    <View style={styles.fato}>
      <Feather name={icon} size={16} color={ds.brandInk} />
      <Text style={styles.fatoTexto}>{texto}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  opcao: { flexDirection: "row", alignItems: "center", gap: space[3], padding: space[4], borderRadius: radius.lg, borderWidth: 1, borderColor: ds.line, backgroundColor: ds.surface },
  opcaoOn: { borderWidth: 2, borderColor: ds.brand, backgroundColor: ds.brandSoft },
  opcaoOff: { opacity: 0.5 },
  radio: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, borderColor: ds.lineStrong, alignItems: "center", justifyContent: "center" },
  radioOn: { borderColor: ds.brand, backgroundColor: ds.brand },
  opcaoTitulo: { ...type.heading, color: ds.ink },
  opcaoPreco: { fontSize: 18, lineHeight: 22, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  meta: { ...type.caption, color: ds.inkMuted },
  fatos: { gap: space[2], padding: space[3], borderRadius: radius.md, backgroundColor: ds.brandSoft },
  fato: { flexDirection: "row", gap: space[2], alignItems: "flex-start" },
  fatoTexto: { flex: 1, ...type.caption, color: ds.ink },
  termos: { maxHeight: 200, padding: space[3], borderRadius: radius.md, borderWidth: 1, borderColor: ds.line, backgroundColor: ds.surfaceSunken },
  termosTexto: { ...type.caption, fontWeight: "500", color: ds.ink },
  aceite: { flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 44 },
  aceiteTexto: { flex: 1, ...type.body, color: ds.ink },
  resumo: { gap: 2, padding: space[3], borderRadius: radius.md, backgroundColor: ds.brandSoft },
  resumoTitulo: { ...type.heading, fontSize: 15, color: ds.ink },
  voltar: { flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 44, alignSelf: "flex-start" },
  voltarTexto: { ...type.caption, color: ds.inkMuted },
  erro: { ...type.caption, color: ds.danger },
});
