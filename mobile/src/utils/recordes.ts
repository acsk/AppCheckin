// Recordes do app: lê o nome da prova ("50m Livre" → 50 · m · Livre) e formata marcas.

export type Direcao = "menor_melhor" | "maior_melhor";

export type RecordeDefinicao = {
  id: number;
  nome: string;
  categoria: string;
  modalidade_id: number | null;
  modalidade_nome: string | null;
  ordem: number;
  metrica_nome: string;
  tipo_valor: "tempo_ms" | "inteiro" | "decimal" | string;
  unidade: string | null;
  direcao: Direcao;
};

export type MinhaProva = {
  definicao_id: number;
  valor: number | null;
  data: string | null;
  anterior: number | null;
  historico: number[];
  posicao: number | null;
  total: number;
};

export type Recordista = {
  definicao_id: number;
  aluno_id: number | null;
  nome: string;
  valor: number;
  data: string;
  self: boolean;
};

export type RecordesResumo = {
  aluno: { id: number; nome: string; sexo: string | null } | null;
  definicoes: RecordeDefinicao[];
  meus: MinhaProva[];
  escola: { geral: Recordista[]; masculino: Recordista[]; feminino: Recordista[] };
};

export type RankingItem = { posicao: number; aluno_id: number; nome: string; valor: number; data: string; self: boolean };

export type Prova = {
  def: RecordeDefinicao;
  /** Distância lida do nome ("50"), ou null quando o nome não começa por ela. */
  distancia: string | null;
  unidadeDist: string;
  /** Estilo ("Livre") ou, sem distância, o próprio nome. */
  estilo: string;
  /** Grupo dos filtros e das seções: o estilo, ou a modalidade quando o nome não traz distância. */
  grupo: string;
  /** Rótulo curto: "50 m livre". */
  rotulo: string;
};

const cap = (s: string) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);

export function lerProva(def: RecordeDefinicao): Prova {
  const nome = String(def.nome || "").trim();
  const m = /^(\d+(?:[.,]\d+)?)\s*(m|km|metros?)\b\.?\s*[-–·]?\s*(.*)$/i.exec(nome);
  if (m) {
    const unidade = /^km$/i.test(m[2]) ? "km" : "m";
    const estilo = cap(m[3].trim()) || "Prova";
    return { def, distancia: m[1].replace(".", ","), unidadeDist: unidade, estilo, grupo: estilo, rotulo: `${m[1]} ${unidade} ${estilo.toLowerCase()}`.trim() };
  }
  return { def, distancia: null, unidadeDist: "", estilo: nome, grupo: def.modalidade_nome || "Outras", rotulo: nome };
}

const pad = (n: number) => (n < 10 ? "0" : "") + n;

/** Tempo em ms → "38,42" · "1:26,90" · "1:02:05". */
export function fmtTempo(ms: number): string {
  const cs = Math.round(Math.abs(ms) / 10);
  const h = Math.floor(cs / 360000);
  const min = Math.floor((cs % 360000) / 6000);
  const s = Math.floor((cs % 6000) / 100);
  const c = cs % 100;
  if (h) return `${h}:${pad(min)}:${pad(s)}`;
  if (min) return `${min}:${pad(s)},${pad(c)}`;
  return `${s},${pad(c)}`;
}

const fmtNum = (v: number, casas?: number) =>
  v.toLocaleString("pt-BR", { minimumFractionDigits: casas ?? 0, maximumFractionDigits: casas ?? 2 });

/** Marca formatada pela métrica principal. */
export function fmtMarca(valor: number | null | undefined, def: RecordeDefinicao): string {
  if (valor == null || !Number.isFinite(valor)) return "—";
  if (def.tipo_valor === "tempo_ms") return fmtTempo(valor);
  const unidade = def.unidade ? ` ${def.unidade}` : "";
  return fmtNum(valor, def.tipo_valor === "inteiro" ? 0 : undefined) + unidade;
}

/** Diferença entre duas marcas, sem sinal: "1,08 s", "1:02,30", "5 kg". */
export function fmtDiferenca(a: number, b: number, def: RecordeDefinicao): string {
  const d = Math.abs(a - b);
  if (def.tipo_valor === "tempo_ms") return d >= 60000 ? fmtTempo(d) : `${fmtTempo(d)} s`;
  return fmtMarca(d, def);
}

/** "mais rápido" para tempo; "a mais"/"a menos" para o resto. */
export function textoMelhora(def: RecordeDefinicao): string {
  if (def.tipo_valor === "tempo_ms") return def.direcao === "menor_melhor" ? "mais rápido" : "a mais";
  return def.direcao === "menor_melhor" ? "a menos" : "a mais";
}

/** "2026-10-12" → "12/10/2026". */
export function fmtData(iso: string | null | undefined): string {
  if (!iso) return "";
  const [y, m, d] = String(iso).slice(0, 10).split("-");
  return d && m && y ? `${d}/${m}/${y}` : String(iso);
}

/** Dias desde a data (para o selo "Novo"). */
export function diasDesde(iso: string | null | undefined): number {
  if (!iso) return Infinity;
  const [y, m, d] = String(iso).slice(0, 10).split("-").map(Number);
  const dt = new Date(y, (m || 1) - 1, d || 1);
  return Math.floor((Date.now() - dt.getTime()) / 86400000);
}
