import Breakdown, { type BreakdownRow } from "@/src/components/ds/Breakdown";
import ParcelasCartaoPicker from "@/src/components/ds/ParcelasCartaoPicker";
import PaySheet from "@/src/components/ds/PaySheet";
import PaymentMethodOptions from "@/src/components/ds/PaymentMethodOptions";
import PixPaymentSheet from "@/src/components/ds/PixPaymentSheet";
import { ds, space, type } from "@/src/components/ds/tokens";
import { useGatewayPicker } from "@/src/hooks/useGatewayPicker";
import { authService } from "@/src/services/authService";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { copyToClipboard } from "@/src/utils/clipboard";
import { nomeProvedorPagamento, type MetodoPagamentoOpcao } from "@/src/utils/metodosPagamento";
import { postComRetryGateway, usuarioPodeVerSandboxPagamento } from "@/src/utils/pagamentoPix";
import AsyncStorage from "@/src/utils/storage";
import { linhasSomenteTaxa, simularTaxaCartao, type TaxaCartaoSimulacao } from "@/src/utils/taxaCartao";
import React, { useCallback, useEffect, useRef, useState } from "react";
import { ActivityIndicator, Linking, StyleSheet, Text, View } from "react-native";

/**
 * Pagamento de pacote pelo app (contratar, pagar o pendente ou renovar).
 * Tudo na tela: a forma de pagamento é escolhida na revisão; PIX gera o QR Code aqui mesmo (nunca abre
 * o site do provedor) e cartão mostra parcelas e taxa simuladas antes de seguir para o checkout seguro.
 * O valor é o do pacote inteiro; quem paga é o pagante e a academia associa os demais participantes.
 */

export type AlvoPacote = {
  titulo: string;
  valor: number;
  /** Linha de apoio na revisão (ex.: "2 pessoas · Mensal"). */
  detalhe?: string | null;
  /** Contrato já existente (pagamento pendente ou renovação). */
  contratoId?: number | null;
  /** Pacote da vitrine: contrata antes de cobrar. */
  pacoteId?: number | null;
  renovar?: boolean;
};

type Pix = {
  contratoId: number;
  matriculaId: number;
  valor: number;
  code: string | null;
  qrBase64: string | null;
  ticketUrl: string | null;
  expiresAt: string | null;
};

const BRL = (v: number) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(Number(v) || 0);

async function chamar(metodo: "GET" | "POST", url: string, token: string, body?: unknown) {
  const response = await fetch(url, {
    method: metodo,
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json: any = await response.json().catch(() => ({}));
  if (!response.ok || json?.success === false) {
    throw new Error(json?.message || json?.error || "Não foi possível concluir. Tente de novo.");
  }
  return json;
}

export function usePacotePagamento({ onPago, onErro }: { onPago?: () => void; onErro?: (mensagem: string) => void }) {
  const { escolherGateway } = useGatewayPicker();
  const [alvo, setAlvo] = useState<AlvoPacote | null>(null);
  const [metodo, setMetodo] = useState<MetodoPagamentoOpcao>("pix");
  const [taxa, setTaxa] = useState<TaxaCartaoSimulacao | null>(null);
  const [taxaGateway, setTaxaGateway] = useState<string | undefined>();
  const [simulando, setSimulando] = useState(false);
  const [erroTaxa, setErroTaxa] = useState<string | null>(null);
  const [processando, setProcessando] = useState(false);
  const [pix, setPix] = useState<Pix | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [conferindo, setConferindo] = useState(false);
  const podeSandbox = useRef(false);
  // Contrato criado nesta revisão: tentar de novo não contrata outra vez
  const contratoRef = useRef<number | null>(null);

  useEffect(() => {
    authService.getCurrentUser().then((u) => {
      podeSandbox.current = usuarioPodeVerSandboxPagamento(u);
    }).catch(() => {});
  }, []);

  const iniciar = useCallback((a: AlvoPacote) => {
    contratoRef.current = a.contratoId ?? null;
    setMetodo("pix");
    setTaxa(null);
    setTaxaGateway(undefined);
    setErroTaxa(null);
    setAviso(null);
    setAlvo(a);
  }, []);

  // Cartão escolhido: simula as parcelas e a taxa aqui, antes de qualquer checkout
  const simularCartao = useCallback(async (valor: number) => {
    setSimulando(true);
    setErroTaxa(null);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Sessão expirada. Entre de novo.");
      const r = await simularTaxaCartao({ apiUrl: getApiUrlRuntime(), token, valor, escolherGateway, podeSandbox: podeSandbox.current });
      if ((r as any).cancelled) {
        setMetodo("pix");
        return;
      }
      if (!r.ok || !r.json?.data) {
        throw new Error(r.json?.message || r.json?.error || "Não foi possível simular o parcelamento.");
      }
      const sim = r.json.data as TaxaCartaoSimulacao;
      setTaxa(sim);
      setTaxaGateway(r.gateway || sim.provider);
    } catch (e: any) {
      setErroTaxa(e?.message || "Não foi possível simular o parcelamento.");
    } finally {
      setSimulando(false);
    }
  }, [escolherGateway]);

  const escolherMetodo = (m: MetodoPagamentoOpcao) => {
    setMetodo(m);
    if (m === "cartao" && alvo && !taxa && !simulando) void simularCartao(alvo.valor);
  };

  /** Contrata (se for da vitrine), renova (se pedido) e devolve o contrato a cobrar. */
  const garantirContrato = async (token: string, api: string, a: AlvoPacote): Promise<number> => {
    if (contratoRef.current) return contratoRef.current;
    let contratoId = a.contratoId ?? null;
    if (!contratoId && a.pacoteId) {
      contratoId = Number((await chamar("POST", `${api}/mobile/pacotes/${a.pacoteId}/contratar`, token)).data?.contrato_id);
    }
    if (!contratoId) throw new Error("Não foi possível identificar o pacote.");
    if (a.renovar) {
      await chamar("POST", `${api}/mobile/pacotes/contratos/${contratoId}/renovar`, token);
    }
    contratoRef.current = contratoId;
    return contratoId;
  };

  const cobrar = async (token: string, api: string, contratoId: number, m: MetodoPagamentoOpcao) => {
    const r = await postComRetryGateway({
      url: `${api}/mobile/pacotes/contratos/${contratoId}/cobranca`,
      token,
      body: {
        metodo_pagamento: m,
        ...(m === "cartao" && taxaGateway ? { gateway: taxaGateway } : {}),
        ...(m === "cartao" ? { installment_count: taxa?.parcela_count ?? 1 } : {}),
      },
      escolherGateway,
      podeSandbox: podeSandbox.current,
    });
    if ((r as any).cancelled) return null;
    if (!r.ok) throw new Error(r.json?.message || r.json?.error || "Não foi possível gerar o pagamento.");
    return r.json?.data ?? {};
  };

  const pagar = async () => {
    if (!alvo) return;
    setProcessando(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Sessão expirada. Entre de novo.");
      const api = getApiUrlRuntime();
      const contratoId = await garantirContrato(token, api, alvo);
      const d = await cobrar(token, api, contratoId, metodo);
      if (!d) return;

      if (metodo === "pix") {
        // PIX fica no app: QR e copia e cola. Sem código ainda, a folha oferece "tentar de novo".
        const p = d.pix ?? {};
        setAlvo(null);
        setPix({
          contratoId,
          matriculaId: Number(d.matricula_id),
          valor: Number(d.valor || alvo.valor),
          code: p.qr_code ?? null,
          qrBase64: p.qr_code_base64 ?? null,
          ticketUrl: p.ticket_url ?? null,
          expiresAt: p.expires_at ?? null,
        });
        setAviso(d.aviso ?? null);
        return;
      }

      // Cartão: parcelas e taxa já conferidas na revisão; só agora vai ao checkout seguro
      if (!d.payment_url) throw new Error("O link de pagamento não foi gerado. Tente de novo.");
      setAlvo(null);
      await Linking.openURL(String(d.payment_url));
      onPago?.();
    } catch (e: any) {
      onErro?.(e?.message || "Não foi possível gerar o pagamento.");
    } finally {
      setProcessando(false);
    }
  };

  const gerarPixNovamente = async () => {
    if (!pix) return;
    setAviso(null);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) return;
      const d = await cobrar(token, getApiUrlRuntime(), pix.contratoId, "pix");
      if (!d) return;
      const p = d.pix ?? {};
      if (!p.qr_code && !p.qr_code_base64) {
        setAviso("O provedor de pagamento ainda não devolveu o código. Tente de novo em instantes.");
        return;
      }
      setPix((atual) => atual && {
        ...atual,
        matriculaId: Number(d.matricula_id || atual.matriculaId),
        valor: Number(d.valor || atual.valor),
        code: p.qr_code ?? null,
        qrBase64: p.qr_code_base64 ?? null,
        ticketUrl: p.ticket_url ?? null,
        expiresAt: p.expires_at ?? null,
      });
    } catch (e: any) {
      setAviso(e?.message || "Não foi possível gerar o PIX agora.");
    }
  };

  const conferir = async () => {
    if (!pix) return;
    setConferindo(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) return;
      const json = await chamar("GET", `${getApiUrlRuntime()}/mobile/assinaturas/aprovadas-hoje?matricula_id=${pix.matriculaId}`, token);
      const status = String(json?.data?.status_gateway || json?.data?.status_codigo || "").toLowerCase();
      if (json?.approved === true || status === "approved" || status === "paga") {
        setPix(null);
        onPago?.();
      } else {
        setAviso("Ainda não recebemos a confirmação. Se já pagou, aguarde alguns instantes.");
      }
    } catch (e: any) {
      setAviso(e?.message || "Não foi possível conferir agora.");
    } finally {
      setConferindo(false);
    }
  };

  const cartao = metodo === "cartao";
  const comTaxa = cartao && taxa ? taxa : null;
  const total = comTaxa ? comTaxa.valor_cobrar : alvo?.valor ?? 0;
  const n = comTaxa?.parcela_count ?? 1;
  const provedor = taxa?.provider ? nomeProvedorPagamento(taxa.provider) : "provedor";
  const linhas: BreakdownRow[] = alvo
    ? [
        { label: alvo.renovar ? "Renovação do pacote" : "Pacote", hint: [alvo.titulo, alvo.detalhe].filter(Boolean).join(" · "), value: BRL(alvo.valor) },
        ...(comTaxa
          ? linhasSomenteTaxa(comTaxa, podeSandbox.current).map((l) => ({ label: l.label, hint: l.hint ?? null, value: l.valor }))
          : []),
      ]
    : [];
  const totalHint = !cartao ? "no PIX, sem taxa" : comTaxa ? (n > 1 ? comTaxa.rotulo_parcela : "à vista no cartão") : "no cartão";
  const primaryLabel = cartao
    ? simulando
      ? "Calculando parcelas…"
      : `Pagar ${n > 1 && comTaxa ? comTaxa.rotulo_parcela : BRL(total)} no ${provedor}`
    : `Gerar PIX de ${BRL(total)}`;

  const elemento = (
    <>
      <PaySheet
        visible={!!alvo}
        icon={alvo?.renovar ? "refresh-cw" : "package"}
        eyebrow={alvo?.renovar ? "Renovação · pacote" : "Pacote · pagamento"}
        title="Revise e pague"
        description={
          alvo?.renovar
            ? "A renovação vale para todo o grupo."
            : "Você paga o pacote inteiro. Depois do pagamento, a academia associa os outros participantes."
        }
        secureNote={
          cartao
            ? `Você conclui no site seguro do ${provedor} com as parcelas escolhidas aqui. Os dados do cartão ficam com ele.`
            : "O QR Code e o copia e cola aparecem aqui mesmo, logo depois de gerar."
        }
        primary={{
          label: primaryLabel,
          icon: cartao ? undefined : "zap",
          iconRight: cartao ? "external-link" : undefined,
          onPress: () => void pagar(),
          loading: processando,
          disabled: processando || (cartao && (simulando || !taxa)),
        }}
        onClose={() => {
          if (!processando) setAlvo(null);
        }}
      >
        <View style={styles.bloco}>
          <Text style={styles.label}>Como você quer pagar?</Text>
          <PaymentMethodOptions opcoes={["pix", "cartao"]} value={metodo} onChange={escolherMetodo} compact />
        </View>
        {cartao && simulando ? (
          <View style={styles.carregando}>
            <ActivityIndicator color={ds.brand} />
            <Text style={styles.carregandoText}>Simulando parcelas e taxa do cartão…</Text>
          </View>
        ) : null}
        {cartao && erroTaxa ? (
          <Text style={styles.erro} onPress={() => alvo && void simularCartao(alvo.valor)}>
            {erroTaxa} Toque para tentar de novo.
          </Text>
        ) : null}
        {comTaxa ? <ParcelasCartaoPicker simulacao={comTaxa} onChange={setTaxa} /> : null}
        {alvo ? <Breakdown rows={linhas} total={{ label: "Você paga agora", hint: totalHint, value: BRL(total) }} /> : null}
      </PaySheet>
      <PixPaymentSheet
        visible={!!pix}
        amount={BRL(pix?.valor ?? 0)}
        description="Pacote"
        code={pix?.code}
        qrBase64={pix?.qrBase64}
        expiresAt={pix?.expiresAt}
        status={conferindo ? "checking" : "waiting"}
        notice={aviso}
        onCopy={copyToClipboard}
        onCheck={() => void conferir()}
        onRetry={gerarPixNovamente}
        onClose={() => {
          setPix(null);
          onPago?.();
        }}
      />
    </>
  );

  return { iniciar, elemento };
}

const styles = StyleSheet.create({
  bloco: { gap: space[2] },
  label: { ...type.label, color: ds.inkMuted },
  carregando: { flexDirection: "row", alignItems: "center", gap: space[2], paddingVertical: space[2] },
  carregandoText: { ...type.caption, color: ds.inkMuted },
  erro: { ...type.caption, color: ds.danger },
});
