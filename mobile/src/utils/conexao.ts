import { getApiUrlRuntime } from "@/src/config/urls";

/**
 * Teste de comunicação com a API (GET /ping, público, sem cache). Serve para separar
 * "sem internet / API fora" de uma resposta de negócio antes de mostrar um bloqueio ao aluno.
 */
export async function apiAlcancavel(timeoutMs = 5000): Promise<boolean> {
  if (typeof navigator !== "undefined" && (navigator as { onLine?: boolean }).onLine === false) return false;
  const controle = new AbortController();
  const timer = setTimeout(() => controle.abort(), timeoutMs);
  try {
    const base = String(getApiUrlRuntime() || "").replace(/\/$/, "");
    const r = await fetch(`${base}/ping`, { method: "GET", cache: "no-store", signal: controle.signal, headers: { Accept: "application/json" } });
    return r.ok;
  } catch {
    return false;
  } finally {
    clearTimeout(timer);
  }
}

export const esperar = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));
