import { Platform } from "react-native";
import api from "@/src/services/api";
import AsyncStorage from "@/src/utils/storage";
import { APP_VERSION } from "@/src/config/appVersion";

/**
 * Configuração remota do app (Super Admin → Configurações do app).
 *
 * Único ponto de acesso às funcionalidades, parâmetros e situação de atualização vindos da API
 * (GET /mobile/app-config com login, /app-config antes). Guarda a última configuração válida por
 * academia, respeita o intervalo (cache_ttl_segundos), usa ETag e junta chamadas simultâneas.
 * Sem API e sem cache valem os PADROES abaixo, que são o comportamento do app antes da
 * configuração remota. O servidor também bloqueia as ações desligadas: isto aqui é só a interface.
 */

/** Maior versão de contrato que este build entende. Contrato maior = API nova demais: ignora. */
const VERSAO_CONTRATO = 1;
const STORAGE_PREFIX = "@appcheckin:remote_config:";

export type Funcionalidade =
  | "checkin"
  | "matriculas"
  | "pagamentos"
  | "cancelamento"
  | "ranking"
  | "recordes"
  | "comunicacao"
  | "grupos_whatsapp";

export type Parametros = {
  checkin_exigir_matricula_ativa: boolean;
  ranking_limite_home: number;
  aviso_home: string;
};

export type Atualizacao = {
  obrigatoria: boolean;
  mensagem?: string;
  /** Só nativo */
  disponivel?: boolean;
  versao_minima?: string | null;
  versao_recomendada?: string | null;
  url?: string | null;
};

export type ConfigRemota = {
  versao_contrato: number;
  versao_config: number;
  cache_ttl_segundos: number;
  funcionalidades: Record<Funcionalidade, boolean>;
  parametros: Parametros;
  atualizacao: Atualizacao;
};

export const PADROES: ConfigRemota = {
  versao_contrato: VERSAO_CONTRATO,
  versao_config: 0,
  cache_ttl_segundos: 300,
  funcionalidades: {
    checkin: true,
    matriculas: true,
    pagamentos: true,
    cancelamento: true,
    ranking: true,
    recordes: true,
    comunicacao: true,
    grupos_whatsapp: true,
  },
  parametros: { checkin_exigir_matricula_ativa: true, ranking_limite_home: 15, aviso_home: "" },
  atualizacao: { obrigatoria: false },
};

type Salvo = { config: ConfigRemota; etag: string | null; buscadoEm: number };

let atual: ConfigRemota = PADROES;
let etag: string | null = null;
let buscadoEm = 0;
let contexto: string | null = null;
let emAndamento: Promise<ConfigRemota> | null = null;
const ouvintes = new Set<() => void>();

const plataforma = (): string => (Platform.OS === "android" || Platform.OS === "ios" ? Platform.OS : "web");

async function contextoAtual(): Promise<{ chave: string; logado: boolean }> {
  const token = (await AsyncStorage.getItem("@appcheckin:token")) || (await AsyncStorage.getItem("auth_token"));
  const tenant = await AsyncStorage.getItem("@appcheckin:tenant_id");
  return token ? { chave: `t${tenant || "0"}`, logado: true } : { chave: "publico", logado: false };
}

/** Aceita só o contrato conhecido; o que faltar vem dos padrões (chaves novas da API são ignoradas). */
export function normalizar(bruto: unknown): ConfigRemota | null {
  const r = bruto as Partial<ConfigRemota> | null;
  if (!r || typeof r !== "object" || typeof r.versao_contrato !== "number" || r.versao_contrato > VERSAO_CONTRATO) return null;
  if (typeof r.funcionalidades !== "object" || r.funcionalidades === null) return null;

  const funcionalidades = { ...PADROES.funcionalidades };
  (Object.keys(funcionalidades) as Funcionalidade[]).forEach((k) => {
    if (typeof r.funcionalidades?.[k] === "boolean") funcionalidades[k] = r.funcionalidades[k];
  });
  const p = (r.parametros || {}) as Partial<Parametros>;
  const limite = Number(p.ranking_limite_home);
  const ttl = Number(r.cache_ttl_segundos);

  return {
    versao_contrato: r.versao_contrato,
    versao_config: Number(r.versao_config) || 0,
    cache_ttl_segundos: Number.isFinite(ttl) && ttl >= 60 ? ttl : PADROES.cache_ttl_segundos,
    funcionalidades,
    parametros: {
      checkin_exigir_matricula_ativa: typeof p.checkin_exigir_matricula_ativa === 'boolean' ? p.checkin_exigir_matricula_ativa : true,
      ranking_limite_home: Number.isInteger(limite) && limite >= 1 && limite <= 100 ? limite : PADROES.parametros.ranking_limite_home,
      aviso_home: typeof p.aviso_home === "string" ? p.aviso_home : "",
    },
    atualizacao: r.atualizacao && typeof r.atualizacao === "object" ? { ...PADROES.atualizacao, ...r.atualizacao } : PADROES.atualizacao,
  };
}

function definir(config: ConfigRemota) {
  const mudou = JSON.stringify(config) !== JSON.stringify(atual);
  atual = config;
  if (mudou) ouvintes.forEach((fn) => fn());
}

async function carregarDoCache(chave: string) {
  try {
    const salvo = JSON.parse((await AsyncStorage.getItem(STORAGE_PREFIX + chave)) || "null") as Salvo | null;
    const config = salvo ? normalizar(salvo.config) : null;
    etag = config ? salvo!.etag : null;
    buscadoEm = config ? salvo!.buscadoEm : 0;
    definir(config ?? PADROES);
  } catch {
    etag = null;
    buscadoEm = 0;
    definir(PADROES);
  }
}

async function buscar(forcar: boolean): Promise<ConfigRemota> {
  const ctx = await contextoAtual();
  if (ctx.chave !== contexto) {
    // Outra academia (ou login/logout): nunca reaproveita a configuração de outro contexto
    contexto = ctx.chave;
    await carregarDoCache(ctx.chave);
  }
  if (!forcar && buscadoEm && Date.now() - buscadoEm < atual.cache_ttl_segundos * 1000) return atual;

  try {
    const headers: Record<string, string> = { "X-App-Platform": plataforma(), "X-App-Version": APP_VERSION };
    if (etag) headers["If-None-Match"] = etag;
    const resp: any = await api.get(ctx.logado ? "/mobile/app-config" : "/app-config", { headers, skipAuth: !ctx.logado });
    if (contexto !== ctx.chave) return atual; // trocou de academia durante a requisição

    if (resp.status !== 304) {
      const config = normalizar(resp.data);
      if (!config) {
        console.warn("[remoteConfig] contrato inválido ou não suportado; mantendo a última configuração");
        return atual;
      }
      etag = resp.headers?.get?.("etag") ?? null;
      definir(config);
    }
    buscadoEm = Date.now();
    await AsyncStorage.setItem(STORAGE_PREFIX + ctx.chave, JSON.stringify({ config: atual, etag, buscadoEm } satisfies Salvo));
  } catch (e: any) {
    // Sem rede ou API fora: segue com o cache/padrões e tenta de novo na próxima chamada
    console.warn("[remoteConfig] falha ao buscar:", e?.message || e);
  }
  return atual;
}

/**
 * Busca a configuração se o intervalo venceu (ou sempre, com forcar). Chamadas simultâneas
 * compartilham a mesma requisição.
 */
export function atualizarConfigRemota(opcoes: { forcar?: boolean } = {}): Promise<ConfigRemota> {
  if (!emAndamento) {
    emAndamento = buscar(!!opcoes.forcar).finally(() => {
      emAndamento = null;
    });
  }
  return emAndamento;
}

/** Login, logout ou troca de academia: carrega o cache do novo contexto e busca na hora. */
export function trocarContextoConfigRemota(): Promise<ConfigRemota> {
  return (emAndamento ?? Promise.resolve()).then(() => atualizarConfigRemota({ forcar: true }));
}

export const obterConfigRemota = (): ConfigRemota => atual;

export function assinarConfigRemota(fn: () => void): () => void {
  ouvintes.add(fn);
  return () => {
    ouvintes.delete(fn);
  };
}

export const estaFuncionalidadeAtiva = (f: Funcionalidade): boolean => atual.funcionalidades[f] !== false;

export const obterParametro = <K extends keyof Parametros>(k: K): Parametros[K] => atual.parametros[k];

export const obterAtualizacao = (): Atualizacao => atual.atualizacao;

/**
 * Nativo: a versão instalada está abaixo da mínima. Na web "obrigatoria" é a política para versões
 * novas (quem compara o build é o ForceUpdateGate), então aqui é sempre false.
 */
export const verificarAtualizacaoObrigatoria = (): boolean => plataforma() !== "web" && !!atual.atualizacao.obrigatoria;
