import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import AsyncStorage from "@/src/utils/storage";

/** Planos com fidelização (assinatura mensal): chamadas e tipos compartilhados pelas telas do app. */

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
  termos_versao?: number;
  politica_renovacao?: string;
  /** Formas aceitas na assinatura: cartão sempre; PIX se a academia ligou nas Configurações de pagamento */
  metodos?: ("cartao" | "pix")[];
};

export type PagamentoAberto = {
  numero: number;
  valor: string;
  url: string | null;
  metodo: string;
  pix: { qr_code: string | null; qr_code_base64: string | null } | null;
} | null;

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

export const brl = (v: string | number | null | undefined) => `R$ ${Number(v || 0).toFixed(2).replace(".", ",")}`;
