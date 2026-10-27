import { useSyncExternalStore } from "react";
import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import AsyncStorage from "@/src/utils/storage";
import { estaFuncionalidadeAtiva } from "@/src/services/remoteConfig";

/**
 * Central de Comunicação do aluno: resumo compartilhado pelo sino, pelo aviso obrigatório e pelo check-in.
 * A trava do check-in é da API (409 COMUNICACOES_OBRIGATORIAS_PENDENTES); aqui só mostramos e resolvemos.
 */

export type Pendencia = { id: number; tipo: string; titulo: string; acao_exigida: "confirmar_leitura" | "responder" };

export type ResumoComunicacoes = {
  carregado: boolean;
  nao_lidas: number;
  total_pendencias: number;
  pendencias: Pendencia[];
  /** Ids para abrir em destaque (obrigatórias pendentes primeiro). */
  destaques: number[];
  /** Pedido explícito para abrir o fluxo de pendências (ex.: 409 no check-in). */
  abrirPendencias: number;
};

export const CODIGO_PENDENCIAS = "COMUNICACOES_OBRIGATORIAS_PENDENTES";

let atual: ResumoComunicacoes = { carregado: false, nao_lidas: 0, total_pendencias: 0, pendencias: [], destaques: [], abrirPendencias: 0 };
const ouvintes = new Set<() => void>();
const publicar = (prox: Partial<ResumoComunicacoes>) => {
  atual = { ...atual, ...prox };
  ouvintes.forEach((o) => o());
};

export function useResumoComunicacoes(): ResumoComunicacoes {
  return useSyncExternalStore(
    (o) => {
      ouvintes.add(o);
      return () => ouvintes.delete(o);
    },
    () => atual,
    () => atual,
  );
}

export async function chamarComunicacoes(metodo: "GET" | "POST", caminho: string, body?: unknown): Promise<any> {
  const token = await AsyncStorage.getItem("@appcheckin:token");
  if (!token) throw new Error("Sessão expirada. Entre de novo.");
  const response = await fetch(`${getApiUrlRuntime()}/mobile/comunicacoes${caminho}`, {
    method: metodo,
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json: any = await response.json().catch(() => ({}));
  if (!response.ok || json?.success === false) {
    throw new Error(json?.message || json?.error || "Não foi possível concluir. Tente de novo.");
  }
  return json?.data ?? json;
}

let emAndamento: Promise<void> | null = null;

/** Busca o resumo (badge, pendências, destaques). Sem a Central ligada, zera. */
export function atualizarResumoComunicacoes(): Promise<void> {
  if (!estaFuncionalidadeAtiva("comunicacao")) {
    publicar({ carregado: true, nao_lidas: 0, total_pendencias: 0, pendencias: [], destaques: [] });
    return Promise.resolve();
  }
  emAndamento ??= chamarComunicacoes("GET", "/resumo")
    .then((d) => publicar({
      carregado: true,
      nao_lidas: Number(d?.nao_lidas || 0),
      total_pendencias: Number(d?.total_pendencias || 0),
      pendencias: Array.isArray(d?.pendencias) ? d.pendencias : [],
      destaques: Array.isArray(d?.destaques) ? d.destaques.map(Number) : [],
    }))
    .catch(() => {})
    .finally(() => {
      emAndamento = null;
    });
  return emAndamento;
}

/** Chamado pelas telas de check-in ao receber o 409: mostra as pendências na hora. */
export function tratarBloqueioCheckin(json: any): boolean {
  if ((json?.codigo || json?.code) !== CODIGO_PENDENCIAS) return false;
  const pendencias: Pendencia[] = Array.isArray(json?.pendencias) ? json.pendencias : [];
  publicar({
    total_pendencias: Number(json?.total_pendencias || pendencias.length),
    pendencias,
    destaques: [...pendencias.map((p) => Number(p.id)), ...atual.destaques.filter((id) => !pendencias.some((p) => Number(p.id) === id))],
    abrirPendencias: atual.abrirPendencias + 1,
  });
  void atualizarResumoComunicacoes();
  return true;
}

export function abrirPendencias() {
  publicar({ abrirPendencias: atual.abrirPendencias + 1 });
}

export function limparResumoComunicacoes() {
  publicar({ carregado: false, nao_lidas: 0, total_pendencias: 0, pendencias: [], destaques: [] });
}

export const textoPendencias = (n: number) =>
  n === 1 ? "Você possui 1 comunicação obrigatória pendente." : `Você possui ${n} comunicações obrigatórias pendentes.`;
