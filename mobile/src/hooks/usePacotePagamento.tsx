import Modal from "@/src/components/ds/Modal";
import PaymentMethodOptions from "@/src/components/ds/PaymentMethodOptions";
import PixPaymentSheet from "@/src/components/ds/PixPaymentSheet";
import { useGatewayPicker } from "@/src/hooks/useGatewayPicker";
import { authService } from "@/src/services/authService";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import { copyToClipboard } from "@/src/utils/clipboard";
import type { MetodoPagamentoOpcao } from "@/src/utils/metodosPagamento";
import { postComRetryGateway, usuarioPodeVerSandboxPagamento } from "@/src/utils/pagamentoPix";
import AsyncStorage from "@/src/utils/storage";
import React, { useCallback, useEffect, useRef, useState } from "react";
import { Linking, StyleSheet, Text, View } from "react-native";
import { ds, space, type } from "@/src/components/ds/tokens";

/**
 * Pagamento de pacote pelo app (contratar, pagar o pendente ou renovar): escolha PIX/cartão, folha do
 * PIX e checkout do cartão. O valor é o do pacote inteiro; quem paga é o pagante e a academia associa
 * os demais participantes depois.
 */

export type AlvoPacote = {
  titulo: string;
  valor: number;
  /** Contrato já existente (pagamento pendente ou renovação). */
  contratoId?: number | null;
  /** Pacote da vitrine: contrata antes de cobrar. */
  pacoteId?: number | null;
  renovar?: boolean;
};

type Pix = { matriculaId: number; valor: number; code: string | null; qrBase64: string | null; ticketUrl: string | null; expiresAt: string | null };

const BRL = (v: number) => `R$ ${v.toFixed(2).replace(".", ",")}`;

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
  const [metodo, setMetodo] = useState<MetodoPagamentoOpcao | null>("pix");
  const [processando, setProcessando] = useState(false);
  const [pix, setPix] = useState<Pix | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [conferindo, setConferindo] = useState(false);
  const podeSandbox = useRef(false);

  useEffect(() => {
    authService.getCurrentUser().then((u) => {
      podeSandbox.current = usuarioPodeVerSandboxPagamento(u);
    }).catch(() => {});
  }, []);

  const iniciar = useCallback((a: AlvoPacote) => {
    setMetodo("pix");
    setAviso(null);
    setAlvo(a);
  }, []);

  const pagar = async () => {
    if (!alvo || !metodo) return;
    setProcessando(true);
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) throw new Error("Sessão expirada. Entre de novo.");
      const api = getApiUrlRuntime();

      let contratoId = alvo.contratoId ?? null;
      if (!contratoId && alvo.pacoteId) {
        contratoId = Number((await chamar("POST", `${api}/mobile/pacotes/${alvo.pacoteId}/contratar`, token)).data?.contrato_id);
      }
      if (!contratoId) throw new Error("Não foi possível identificar o pacote.");
      if (alvo.renovar) {
        await chamar("POST", `${api}/mobile/pacotes/contratos/${contratoId}/renovar`, token);
      }

      const r = await postComRetryGateway({
        url: `${api}/mobile/pacotes/contratos/${contratoId}/cobranca`,
        token,
        body: { metodo_pagamento: metodo },
        escolherGateway,
        podeSandbox: podeSandbox.current,
      });
      if (!r.ok) {
        if ((r as any).cancelled) return;
        throw new Error(r.json?.message || r.json?.error || "Não foi possível gerar o pagamento.");
      }
      const d = r.json?.data ?? {};
      setAlvo(null);
      if (d.pix) {
        setPix({
          matriculaId: Number(d.matricula_id),
          valor: Number(d.valor || alvo.valor),
          code: d.pix.qr_code ?? null,
          qrBase64: d.pix.qr_code_base64 ?? null,
          ticketUrl: d.pix.ticket_url ?? null,
          expiresAt: d.pix.expires_at ?? null,
        });
        setAviso(d.aviso ?? null);
      } else if (d.payment_url) {
        await Linking.openURL(String(d.payment_url));
        onPago?.();
      }
    } catch (e: any) {
      onErro?.(e?.message || "Não foi possível gerar o pagamento.");
    } finally {
      setProcessando(false);
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

  const elemento = (
    <>
      <Modal
        visible={!!alvo}
        variant="dialog"
        icon={alvo?.renovar ? "refresh-cw" : "package"}
        title={alvo?.renovar ? `Renovar ${alvo?.titulo ?? ""}` : alvo?.titulo ?? "Pacote"}
        description={alvo ? `Você paga ${BRL(alvo.valor)} pelo pacote inteiro. ${alvo.renovar ? "A renovação vale para todo o grupo." : "Depois do pagamento, a academia associa os outros participantes."}` : undefined}
        primary={{ label: metodo === "cartao" ? "Pagar com cartão" : "Gerar PIX", icon: metodo === "cartao" ? "credit-card" : "zap", onPress: () => void pagar(), loading: processando }}
        onClose={() => setAlvo(null)}
      >
        <View style={styles.opcoes}>
          <Text style={styles.label}>Forma de pagamento</Text>
          <PaymentMethodOptions opcoes={["pix", "cartao"]} value={metodo} onChange={setMetodo} compact />
        </View>
      </Modal>
      <PixPaymentSheet
        visible={!!pix}
        amount={BRL(pix?.valor ?? 0)}
        description="Pacote"
        code={pix?.code}
        qrBase64={pix?.qrBase64}
        expiresAt={pix?.expiresAt}
        ticketUrl={pix?.ticketUrl}
        status={conferindo ? "checking" : "waiting"}
        notice={aviso}
        onCopy={copyToClipboard}
        onCheck={() => void conferir()}
        onOpenBank={() => {
          if (pix?.ticketUrl) void Linking.openURL(pix.ticketUrl);
        }}
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
  opcoes: { gap: space[2], marginTop: space[2] },
  label: { ...type.label, color: ds.inkMuted },
});
