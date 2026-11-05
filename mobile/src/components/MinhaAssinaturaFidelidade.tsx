import { chamarRecorrencia, type Oferta } from "@/src/services/recorrencia";
import { SubscriptionAction, SubscriptionOverviewCard, PastSubscriptions } from "@/src/components/ds/SubscriptionOverview";
import { diasAteAssinatura, assinaturaEncerrada, type ProximaCobrancaAssinatura } from "@/src/utils/assinaturasLayout";
import Button from "@/src/components/ds/Button";
import Modal from "@/src/components/ds/Modal";
import PixPaymentSheet from "@/src/components/ds/PixPaymentSheet";
import TextField from "@/src/components/ds/TextField";
import { ds, radius, space, type } from "@/src/components/ds/tokens";
import { copyToClipboard } from "@/src/utils/clipboard";
import { Feather } from "@expo/vector-icons";
import React, { useCallback, useEffect, useState } from "react";
import { Linking, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";

/**
 * "Minha assinatura" (planos com fidelidade): valor, próximo vencimento, fidelização restante, pagamentos,
 * renovação e termos aceitos. Cancelar mostra antes a simulação da regularização; o pedido nunca depende
 * de pagar antes.
 */

type Contrato = {
  id: number;
  plano_nome: string;
  modalidade_nome: string | null;
  status: string;
  status_nome: string;
  valor_mensal: string;
  taxas?: string;
  taxa_cartao?: string;
  valor_mensal_contratado: string;
  valor_mensal_referencia: string;
  metodo: string;
  inicio_em: string;
  proxima_cobranca_em: string | null;
  acesso_ate: string | null;
  fidelizacao_meses: number;
  em_fidelizacao: boolean;
  fidelizacao_restante: number;
  fim_fidelizacao_em: string;
  condicoes_renovacao: string | null;
  termos_aceitos: string;
  termos_versao: number;
  aceite_em: string;
  pagamentos: { numero: number; vencimento: string; valor: string; status: string; pago_em: string | null }[];
  tem_pagamento_aberto: boolean;
  pode_cancelar: boolean;
  cancelamento: { status: string; data_efetiva: string; valor_final: string } | null;
  nova_fidelizacao: Oferta | null;
};

type Simulacao = {
  meses_usufruidos: number;
  fidelizacao_meses: number;
  desconto_acumulado: string;
  valor_regularizacao: string;
  valor_em_aberto: string;
  total_a_pagar: string;
  data_efetiva: string;
  dentro_arrependimento: boolean;
  politica_cobra: boolean;
  memoria: string[];
};

const brl = (v: string | number | null | undefined) => `R$ ${Number(v || 0).toFixed(2).replace(".", ",")}`;
const dataBr = (v: string | null | undefined) => (v ? v.slice(0, 10).split("-").reverse().join("/") : "—");
const TOM: Record<string, "success" | "warning" | "danger" | "info" | "neutral"> = {
  ativo: "success", pendente_ativacao: "warning", pagamento_pendente: "warning", inadimplente: "danger",
  cancelamento_solicitado: "warning", cancelamento_agendado: "info", encerrado: "neutral", cancelado: "neutral",
};
const PAG: Record<string, string> = { paga: "Paga", pendente: "Em aberto", vencida: "Vencida", estornada: "Estornada", cancelada: "Cancelada" };

export type AssinaturasFidelidadeLayout = { alerts: React.ReactNode; active: React.ReactNode; past: React.ReactNode; pastCount: number; count: number; loading: boolean; error: boolean; charges: ProximaCobrancaAssinatura[] };
export default function MinhaAssinaturaFidelidade({ versao = 0, onQuantidade, renderContent }: { versao?: number; onQuantidade?: (n: number) => void; renderContent?: (layout: AssinaturasFidelidadeLayout) => React.ReactNode }) {
  const [loading, setLoading] = useState(true);
  const [readError, setReadError] = useState("");
  const [contratos, setContratos] = useState<Contrato[]>([]);
  const [aberto, setAberto] = useState<number | null>(null);
  const [cancelando, setCancelando] = useState<{ c: Contrato; sim: Simulacao | null } | null>(null);
  const [motivo, setMotivo] = useState("");
  const [renovar, setRenovar] = useState<{ c: Contrato; aceite: boolean } | null>(null);
  const [busy, setBusy] = useState(false);
  const [aviso, setAviso] = useState<{ ok: boolean; texto: string } | null>(null);
  const [pix, setPix] = useState<{ valor: string; code: string | null; qr: string | null; url: string | null } | null>(null);

  const carregar = useCallback(async () => {
    setLoading(true); setReadError("");
    try {
      const { ok, json } = await chamarRecorrencia("GET", "/contratos");
      if (!ok) throw new Error(json?.message || "Não foi possível carregar suas assinaturas.");
      const lista: Contrato[] = json?.data?.contratos || [];
      setContratos(lista);
      onQuantidade?.(lista.length);
    } catch {
      setReadError("Não foi possível carregar os contratos com fidelidade. Atualize para tentar novamente.");
    } finally { setLoading(false); }
  }, [onQuantidade]);
  useEffect(() => { void carregar(); }, [carregar, versao]);

  async function pagar(c: Contrato) {
    setBusy(true);
    try {
      const { ok, json } = await chamarRecorrencia("GET", `/contratos/${c.id}/pagamento`);
      const p = json?.data?.pagamento;
      if (!ok || !p) {
        setAviso({ ok: true, texto: "Nenhuma mensalidade em aberto." });
        await carregar();
      } else if (p.pix?.qr_code) {
        setPix({ valor: p.valor, code: p.pix.qr_code, qr: p.pix.qr_code_base64, url: p.url });
      } else if (p.url) {
        // Fatura do Asaas: pagar ou usar outro cartão nas próximas mensalidades
        await Linking.openURL(p.url);
      }
    } finally {
      setBusy(false);
    }
  }

  async function abrirCancelamento(c: Contrato) {
    setMotivo("");
    setCancelando({ c, sim: null });
    const { ok, json } = await chamarRecorrencia("GET", `/contratos/${c.id}/simular-cancelamento`);
    if (ok) setCancelando({ c, sim: json?.data?.simulacao });
    else {
      setCancelando(null);
      setAviso({ ok: false, texto: json?.message || "Não foi possível calcular o cancelamento agora." });
    }
  }

  async function confirmarCancelamento() {
    if (!cancelando) return;
    setBusy(true);
    try {
      const { ok, json } = await chamarRecorrencia("POST", `/contratos/${cancelando.c.id}/cancelar`, { motivo: motivo.trim() || null });
      setAviso({ ok, texto: json?.message || json?.data?.message || (ok ? "Pedido registrado." : "Não foi possível cancelar agora.") });
      if (ok) {
        setCancelando(null);
        await carregar();
      }
    } finally {
      setBusy(false);
    }
  }

  async function aceitarRenovacao() {
    if (!renovar?.c.nova_fidelizacao) return;
    if (!renovar.aceite) {
      setAviso({ ok: false, texto: "Marque que leu e aceita os novos termos." });
      return;
    }
    setBusy(true);
    try {
      const { ok, json } = await chamarRecorrencia("POST", `/contratos/${renovar.c.id}/aceitar-renovacao`, { termos_hash: renovar.c.nova_fidelizacao.termos_hash });
      setAviso({ ok, texto: json?.message || json?.data?.message || (ok ? "Nova fidelização aceita." : "Não foi possível aceitar agora.") });
      if (ok) {
        setRenovar(null);
        await carregar();
      }
    } finally {
      setBusy(false);
    }
  }

  const renderContrato = (c: Contrato) => {
        const expandido = aberto === c.id;
        const pagas = c.pagamentos.filter((p) => p.status === "paga").length;
        return (
          <SubscriptionOverviewCard key={c.id} title={c.plano_nome} price={brl(c.valor_mensal)} unit="/mês"
            subtitle={[c.fidelizacao_meses ? "Mensal com fidelidade" : "Mensal", c.proxima_cobranca_em ? `renova ${dataBr(c.proxima_cobranca_em)}` : c.acesso_ate ? `até ${dataBr(c.acesso_ate)}` : null, c.metodo === "cartao" ? "cartão" : c.metodo === "pix" ? "PIX mensal" : "boleto", c.status_nome].filter(Boolean).join(" · ")}
            days={diasAteAssinatura(c.acesso_ate || c.proxima_cobranca_em)}
            fidelity={c.fidelizacao_meses > 0 ? {paid: Math.min(pagas,c.fidelizacao_meses),total:c.fidelizacao_meses,until:dataBr(c.fim_fidelizacao_em)} : undefined}
            expanded={expandido} onToggle={() => setAberto(expandido ? null : c.id)}>
            {Number(c.taxas ?? c.taxa_cartao) > 0 ? <Text style={styles.meta}>+ taxas {brl(c.taxas ?? c.taxa_cartao)}/mês</Text> : null}
            <View style={styles.grade}>
              <Info k="Próximo vencimento" v={dataBr(c.proxima_cobranca_em)} />
              <Info k="Forma de pagamento" v={c.metodo === "cartao" ? "Cartão (automático)" : c.metodo === "pix" ? "PIX mensal" : "Boleto"} />
              <Info k="Início" v={dataBr(c.inicio_em)} />
              <Info k="Acesso até" v={dataBr(c.acesso_ate)} />
            </View>
            {!c.em_fidelizacao ? <Text style={styles.meta}>{c.fidelizacao_meses ? "Fidelidade cumprida" : "Sem fidelidade"}</Text> : null}
            {c.condicoes_renovacao ? <Text style={styles.meta}>{c.condicoes_renovacao}</Text> : null}
            {c.cancelamento && ["cancelamento_solicitado", "cancelamento_agendado", "cancelado"].includes(c.status) ? (
              <Text style={styles.meta}>
                {c.cancelamento.status === "solicitado" ? "Pedido de cancelamento em análise pela academia. " : "Cancelamento confirmado. "}
                Acesso até {dataBr(c.cancelamento.data_efetiva)}{Number(c.cancelamento.valor_final) > 0 ? ` · regularização ${brl(c.cancelamento.valor_final)}` : ""}.
              </Text>
            ) : null}

            {c.nova_fidelizacao ? (
              <View style={styles.oferta}>
                <Text style={styles.ofertaTitulo}>Nova fidelidade disponível</Text>
                <Text style={styles.meta}>{brl(c.nova_fidelizacao.valor_mensal)}/mês por {c.nova_fidelizacao.fidelizacao_meses} meses. Só começa se você aceitar.</Text>
                <Button size="sm" variant="secondary" icon="repeat" onPress={() => setRenovar({ c, aceite: false })}>Ver e aceitar</Button>
              </View>
            ) : null}

            <View style={styles.acoes}>
              {c.tem_pagamento_aberto ? <Button size="sm" icon={c.metodo === "pix" ? "zap" : "credit-card"} loading={busy} onPress={() => void pagar(c)}>
                {c.metodo === "cartao" ? "Pagar / trocar cartão" : "Pagar mensalidade"}
              </Button> : null}
              {c.pode_cancelar ? <Button size="sm" variant="danger" icon="x-circle" onPress={() => void abrirCancelamento(c)}>
                {c.status === "pendente_ativacao" ? "Cancelar pedido" : "Solicitar cancelamento"}
              </Button> : null}

            </View>

            {expandido ? (
              <View style={{ gap: space[3] }}>
                <View style={styles.lista}>
                  {c.pagamentos.length ? c.pagamentos.map((p) => (
                    <View key={p.numero} style={styles.linha}>
                      <Text style={styles.linhaN}>{p.numero}ª</Text>
                      <Text style={styles.linhaD}>{dataBr(p.vencimento)}</Text>
                      <Text style={styles.linhaV}>{brl(p.valor)}</Text>
                      <Text style={[styles.linhaS, p.status === "paga" ? { color: ds.success } : p.status === "vencida" ? { color: ds.danger } : null]}>{PAG[p.status] || p.status}</Text>
                    </View>
                  )) : <Text style={styles.meta}>Nenhuma mensalidade gerada ainda.</Text>}
                </View>
                <Text style={styles.label}>Termos aceitos em {dataBr(c.aceite_em)} (versão {c.termos_versao})</Text>
                <Text style={styles.termos}>{c.termos_aceitos}</Text>
              </View>
            ) : null}
          </SubscriptionOverviewCard>
        );
  };
  const active = contratos.filter(c => !assinaturaEncerrada(c.status));
  const past = contratos.filter(c => assinaturaEncerrada(c.status));
  const alerts = <View style={{gap:space[3]}}>
    {readError ? <SubscriptionAction title="Não foi possível carregar as assinaturas" text={readError} tone="danger" action={{label:"Atualizar",onPress:()=>void carregar(),loading}} /> : null}
    {aviso ? <View style={[styles.aviso,aviso.ok?styles.avisoOk:styles.avisoErro]} accessibilityLiveRegion="polite"><Text style={styles.avisoTexto}>{aviso.texto}</Text></View> : null}
    {contratos.filter(c=>c.tem_pagamento_aberto).map(c=><SubscriptionAction key={c.id} title={`${c.plano_nome} · pagamento pendente`} text={`${brl(c.valor_mensal)} · ${c.status_nome}`} tone={c.status === "inadimplente" ? "danger" : "warning"} action={{label:c.metodo === "cartao"?"Pagar / cartão":"Pagar",loading:busy,onPress:()=>void pagar(c)}} />)}
  </View>;
  const content: AssinaturasFidelidadeLayout = { alerts, active:active.map(renderContrato),past:past.map(renderContrato),pastCount:past.length,count:contratos.length,loading,error:!!readError,
    charges:active.filter(c=>c.proxima_cobranca_em && c.status === "ativo" && !c.cancelamento).map(c=>({date:c.proxima_cobranca_em!,amount:Number(c.valor_mensal)+Number(c.taxas ?? c.taxa_cartao ?? 0)})) };
  return <>
    {renderContent ? renderContent(content) : <View style={styles.secao}>{alerts}{content.active}<PastSubscriptions count={past.length}>{content.past}</PastSubscriptions></View>}


      <Modal
        visible={!!cancelando}
        variant="sheet"
        icon="x-circle"
        eyebrow="Cancelar assinatura"
        title={cancelando?.c.plano_nome || ""}
        primary={{ label: "Solicitar cancelamento", icon: "x-circle", onPress: () => void confirmarCancelamento(), loading: busy }}
        onClose={() => !busy && setCancelando(null)}
      >
        {!cancelando?.sim ? <Text style={styles.meta}>Calculando…</Text> : (
          <>
            <View style={styles.grade}>
              <Info k="Mensalidades usadas" v={`${cancelando.sim.meses_usufruidos} de ${cancelando.sim.fidelizacao_meses}`} />
              <Info k="Desconto recebido" v={brl(cancelando.sim.desconto_acumulado)} />
              <Info k="Regularização" v={brl(cancelando.sim.valor_regularizacao)} />
              <Info k="Em aberto" v={brl(cancelando.sim.valor_em_aberto)} />
            </View>
            <View style={styles.memoria}>
              {cancelando.sim.memoria.map((l, i) => <Text key={i} style={styles.meta}>• {l}</Text>)}
            </View>
            <Text style={styles.meta}>
              Acesso até {dataBr(cancelando.sim.data_efetiva)}. As próximas cobranças são canceladas na hora do pedido.
              {Number(cancelando.sim.valor_regularizacao) > 0 ? " A academia confirma o valor da regularização (pode isentar)." : ""}
            </Text>
            <TextField label="Motivo (opcional)" value={motivo} onChangeText={setMotivo} maxLength={500} />
          </>
        )}
      </Modal>

      <Modal
        visible={!!renovar}
        variant="sheet"
        icon="repeat"
        eyebrow="Nova fidelidade"
        title={renovar?.c.plano_nome || ""}
        primary={{ label: "Aceitar nova fidelidade", icon: "check", onPress: () => void aceitarRenovacao(), loading: busy }}
        onClose={() => !busy && setRenovar(null)}
      >
        {renovar?.c.nova_fidelizacao ? (
          <>
            <ScrollView style={styles.termosBox} nestedScrollEnabled><Text style={styles.termos}>{renovar.c.nova_fidelizacao.termos}</Text></ScrollView>
            <Pressable style={styles.aceite} onPress={() => setRenovar({ ...renovar, aceite: !renovar.aceite })} accessibilityRole="checkbox" accessibilityState={{ checked: renovar.aceite }}>
              <Feather name={renovar.aceite ? "check-square" : "square"} size={22} color={renovar.aceite ? ds.brandInk : ds.inkMuted} />
              <Text style={styles.aceiteTexto}>Li e aceito os novos termos</Text>
            </Pressable>
          </>
        ) : null}
      </Modal>

      <PixPaymentSheet
        visible={!!pix}
        amount={brl(pix?.valor)}
        description="Mensalidade"
        code={pix?.code}
        qrBase64={pix?.qr}
        expiresAt={null}
        ticketUrl={pix?.url}
        status="waiting"
        notice={null}
        onCopy={copyToClipboard}
        onCheck={() => { setPix(null); void carregar(); }}
        onOpenBank={() => { if (pix?.url) void Linking.openURL(pix.url); }}
        onClose={() => { setPix(null); void carregar(); }}
      />
    </>;
}

function Info({ k, v }: { k: string; v: string }) {
  return (
    <View style={styles.info}>
      <Text style={styles.infoK}>{k}</Text>
      <Text style={styles.infoV}>{v}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  secao: { gap: space[3], marginBottom: space[4] },
  cab: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  cabTitulo: { ...type.label, color: ds.inkMuted },
  cabAside: { ...type.caption, color: ds.inkMuted },
  card: { gap: space[3], padding: space[4], borderRadius: radius.lg, borderWidth: 1, borderColor: ds.line, backgroundColor: ds.surface },
  topo: { flexDirection: "row", alignItems: "flex-start", gap: space[2] },
  nome: { ...type.heading, color: ds.ink },
  meta: { ...type.caption, color: ds.inkMuted },
  valor: { fontSize: 26, lineHeight: 32, fontWeight: "900", color: ds.ink, fontVariant: ["tabular-nums"] },
  porMes: { fontSize: 15, fontWeight: "700", color: ds.inkMuted },
  grade: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
  info: { flexGrow: 1, flexBasis: "45%", padding: space[2], borderRadius: radius.md, backgroundColor: ds.surfaceSunken },
  infoK: { fontSize: 11, lineHeight: 15, fontWeight: "700", color: ds.inkMuted },
  infoV: { ...type.caption, fontSize: 14, color: ds.ink, fontVariant: ["tabular-nums"] },
  fid: { gap: space[1] },
  trilho: { height: 8, borderRadius: 4, backgroundColor: ds.surfaceSunken, overflow: "hidden" },
  barra: { height: 8, borderRadius: 4, backgroundColor: ds.success },
  oferta: { gap: space[2], padding: space[3], borderRadius: radius.md, backgroundColor: ds.brandSoft },
  ofertaTitulo: { ...type.caption, fontWeight: "800", color: ds.brandInk },
  acoes: { flexDirection: "row", flexWrap: "wrap", gap: space[2] },
  lista: { gap: 2 },
  linha: { flexDirection: "row", alignItems: "center", gap: space[2], paddingVertical: 6, borderBottomWidth: 1, borderBottomColor: ds.line },
  linhaN: { width: 28, ...type.caption, color: ds.inkMuted },
  linhaD: { flex: 1, ...type.caption, color: ds.ink },
  linhaV: { ...type.caption, color: ds.ink, fontVariant: ["tabular-nums"] },
  linhaS: { width: 76, textAlign: "right", ...type.caption, color: ds.inkMuted },
  label: { ...type.label, color: ds.inkMuted },
  termos: { ...type.caption, fontWeight: "500", color: ds.ink },
  termosBox: { maxHeight: 220, padding: space[3], borderRadius: radius.md, backgroundColor: ds.surfaceSunken },
  memoria: { gap: 4, padding: space[3], borderRadius: radius.md, backgroundColor: ds.surfaceSunken },
  aceite: { flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 44 },
  aceiteTexto: { flex: 1, ...type.body, color: ds.ink },
  aviso: { flexDirection: "row", gap: space[2], padding: space[3], borderRadius: radius.md },
  avisoOk: { backgroundColor: ds.successSoft },
  avisoErro: { backgroundColor: ds.dangerSoft },
  avisoTexto: { flex: 1, ...type.caption, color: ds.ink },
});
