import Modal from "@/src/components/ds/Modal";
import PaymentMethodOptions from "@/src/components/ds/PaymentMethodOptions";
import PixPaymentSheet from "@/src/components/ds/PixPaymentSheet";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { copyToClipboard } from "@/src/utils/clipboard";
import type { MetodoPagamentoOpcao } from "@/src/utils/metodosPagamento";
import AsyncStorage from "@/src/utils/storage";
import { Feather } from "@expo/vector-icons";
import React, { useEffect, useState } from "react";
import { ActivityIndicator, Linking, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";

/**
 * Assinatura com fidelidade: o aluno lê os termos exatos, aceita e escolhe como pagar. Cada mês é uma
 * cobrança separada (não é parcelamento). No cartão, a 1ª é paga pela fatura e as seguintes debitam sozinhas.
 */

export type Oferta = {
  plano_ciclo_id: number;
  plano_nome: string;
  ciclo_nome: string;
  valor_mensal: string;
  valor_mensal_referencia: string;
  desconto_mensal: string;
  fidelizacao_meses: number;
  arrependimento_dias: number;
  regularizacao_ativa: boolean;
  termos: string;
  termos_hash: string;
};

type Pagamento = { numero: number; valor: string; url: string | null; metodo: string; pix: { qr_code: string | null; qr_code_base64: string | null } | null } | null;

const brl = (v: string | number) => `R$ ${Number(v).toFixed(2).replace(".", ",")}`;

export async function chamarRecorrencia(metodo: "GET" | "POST", caminho: string, body?: unknown): Promise<{ ok: boolean; status: number; json: any }> {
  const token = await AsyncStorage.getItem("@appcheckin:token");
  const response = await fetch(`${getApiUrlRuntime()}/mobile/recorrencia${caminho}`, {
    method: metodo,
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json: any = await response.json().catch(() => ({}));
  return { ok: response.ok && json?.success !== false, status: response.status, json };
}

type Props = {
  visible: boolean;
  planoId: number;
  cicloId: number;
  onClose: () => void;
  /** Contrato criado (pagamento aberto ou já pago). */
  onAssinado: (contratoId: number) => void;
};

export default function FidelidadeSheet({ visible, planoId, cicloId, onClose, onAssinado }: Props) {
  const [oferta, setOferta] = useState<Oferta | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [aceite, setAceite] = useState(false);
  const [metodo, setMetodo] = useState<MetodoPagamentoOpcao | null>("cartao");
  const [enviando, setEnviando] = useState(false);
  const [pix, setPix] = useState<{ contratoId: number; pagamento: NonNullable<Pagamento> } | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [conferindo, setConferindo] = useState(false);

  useEffect(() => {
    if (!visible) return;
    setOferta(null); setErro(null); setAceite(false); setMetodo("cartao");
    chamarRecorrencia("GET", `/ofertas?plano_id=${planoId}`).then(({ ok, json }) => {
      const o = (json?.data?.ofertas || []).find((x: Oferta) => Number(x.plano_ciclo_id) === Number(cicloId));
      if (!ok || !o) setErro(json?.message || "Esta opção não está mais disponível.");
      else setOferta(o);
    }).catch(() => setErro("Não foi possível carregar os termos."));
  }, [visible, planoId, cicloId]);

  async function assinar() {
    if (!oferta || !metodo) return;
    if (!aceite) {
      setErro("Marque que leu e aceita os termos para assinar.");
      return;
    }
    setEnviando(true); setErro(null);
    try {
      const { ok, json } = await chamarRecorrencia("POST", "/contratar", { plano_ciclo_id: oferta.plano_ciclo_id, metodo, termos_hash: oferta.termos_hash });
      if (!ok) {
        if (json?.code === "TERMOS_DESATUALIZADOS" && json?.oferta) {
          setOferta(json.oferta); setAceite(false);
        }
        setErro(json?.message || json?.error || "Não foi possível assinar agora.");
        return;
      }
      const contratoId = Number(json?.data?.contrato_id);
      const pagamento: Pagamento = json?.data?.pagamento ?? null;
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

  return (
    <>
      <Modal
        visible={visible && !pix}
        variant="sheet"
        icon="repeat"
        eyebrow="Assinatura com fidelidade"
        title={oferta ? `${oferta.plano_nome} · ${oferta.ciclo_nome}` : "Assinatura"}
        primary={{ label: aceite ? "Aceitar e assinar" : "Leia e aceite os termos", icon: "check", onPress: () => void assinar(), loading: enviando }}
        onClose={onClose}
      >
        {!oferta && !erro ? <ActivityIndicator color={ds.brand} /> : null}
        {oferta ? (
          <>
            <View style={styles.preco}>
              <Text style={styles.valor}>{brl(oferta.valor_mensal)}<Text style={styles.porMes}>/mês</Text></Text>
              <Text style={styles.meta}>por {oferta.fidelizacao_meses} meses · sem fidelidade seria {brl(oferta.valor_mensal_referencia)}/mês</Text>
            </View>
            <View style={styles.fatos}>
              <Fato icon="calendar" texto={`Uma cobrança de ${brl(oferta.valor_mensal)} por mês. Não é parcelamento: o total não fica preso no limite do cartão.`} />
              <Fato icon="lock" texto={`Compromisso de ${oferta.fidelizacao_meses} mensalidades. Desistência sem custo em até ${oferta.arrependimento_dias} dias.`} />
              <Fato icon="x-circle" texto={oferta.regularizacao_ativa ? `Saindo antes: paga só a diferença do desconto já recebido (${brl(oferta.desconto_mensal)} por mês pago).` : "Pode cancelar quando quiser, sem multa."} />
            </View>
            <Text style={styles.label}>Termos</Text>
            <ScrollView style={styles.termos} nestedScrollEnabled>
              <Text style={styles.termosTexto}>{oferta.termos}</Text>
            </ScrollView>
            <Pressable style={styles.aceite} onPress={() => { setAceite(!aceite); setErro(null); }} accessibilityRole="checkbox" accessibilityState={{ checked: aceite }}>
              <Feather name={aceite ? "check-square" : "square"} size={22} color={aceite ? ds.brandInk : ds.inkMuted} />
              <Text style={styles.aceiteTexto}>Li e aceito os termos desta assinatura</Text>
            </Pressable>
            <Text style={styles.label}>Forma de pagamento</Text>
            <PaymentMethodOptions opcoes={["cartao", "pix"]} value={metodo} onChange={setMetodo} compact />
            <Text style={styles.meta}>{metodo === "cartao" ? "Pague a 1ª pela fatura; as próximas são debitadas no mesmo cartão." : "Todo mês você recebe um PIX para pagar a mensalidade."}</Text>
          </>
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

function Fato({ icon, texto }: { icon: React.ComponentProps<typeof Feather>["name"]; texto: string }) {
  return (
    <View style={styles.fato}>
      <Feather name={icon} size={16} color={ds.brandInk} />
      <Text style={styles.fatoTexto}>{texto}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  preco: { gap: 2 },
  valor: { fontSize: 30, lineHeight: 36, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  porMes: { fontSize: 16, fontWeight: "700", color: ds.inkMuted },
  meta: { ...type.caption, color: ds.inkMuted },
  fatos: { gap: space[2], padding: space[3], borderRadius: radius.md, backgroundColor: ds.brandSoft },
  fato: { flexDirection: "row", gap: space[2], alignItems: "flex-start" },
  fatoTexto: { flex: 1, ...type.caption, color: ds.ink },
  label: { ...type.label, color: ds.inkMuted, marginTop: space[2] },
  termos: { maxHeight: 180, padding: space[3], borderRadius: radius.md, borderWidth: 1, borderColor: ds.line, backgroundColor: ds.surfaceSunken },
  termosTexto: { ...type.caption, fontWeight: "500", color: ds.ink },
  aceite: { flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 44 },
  aceiteTexto: { flex: 1, ...type.body, color: ds.ink },
  erro: { ...type.caption, color: ds.danger },
});
