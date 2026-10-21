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

export type RankingItem = { posicao: number; aluno_id: number; nome: string; foto_caminho?: string | null; valor: number; data: string; self: boolean };

export function fotoRecordeUrl(caminho: string | null | undefined, apiUrl: string): string | null {
  const foto = caminho?.trim();
  if (!foto) return null;
  if (/^https?:\/\//i.test(foto)) return foto;
  return `${apiUrl.replace(/\/$/, "")}/${foto.replace(/^\/+/, "")}`;
}

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

/** Tempo em ms; preserva milissegundos quando a marca tem precisao subcentesimal. */
export function fmtTempo(ms: number): string {
  const value = Math.round(Math.abs(ms));
  const h = Math.floor(value / 3600000);
  const min = Math.floor((value % 3600000) / 60000);
  const s = Math.floor((value % 60000) / 1000);
  const fraction = value % 1000;
  const c = fraction % 10 === 0 ? pad(fraction / 10) : String(fraction).padStart(3, "0");
  if (h) return `${h}:${pad(min)}:${pad(s)},${c}`;
  if (min) return `${min}:${pad(s)},${c}`;
  return `${s},${c}`;
}

const fmtNum = (v: number, casas?: number) =>
  v.toLocaleString("pt-BR", { minimumFractionDigits: casas ?? 0, maximumFractionDigits: casas ?? 3 });

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

export type RecordeMetrica = {
  id: number;
  nome: string;
  tipo_valor: "tempo_ms" | "inteiro" | "decimal" | string;
  unidade: string | null;
  direcao: Direcao;
  obrigatoria: boolean;
};

export type Marca = {
  id: number;
  data: string;
  /** Valor da métrica principal. */
  valor: number;
  /** metrica_id → valor */
  valores: Record<string, number>;
  observacoes: string | null;
  origem: "aluno" | "academia" | string;
  editavel: boolean;
};

export type ProvaDetalhe = {
  definicao: RecordeDefinicao & { metrica_principal_id: number };
  metricas: RecordeMetrica[];
  pode_registrar: boolean;
  marcas: Marca[];
  melhor_id: number | null;
};

export type MarcaSalva = { id: number; valor: number | null; melhor_anterior: number | null; novo_recorde: boolean };

/** Valor de uma métrica formatado ("38,42", "80 kg"). */
export function fmtValorMetrica(valor: number | null | undefined, m: Pick<RecordeMetrica, "tipo_valor" | "unidade">): string {
  if (valor == null || !Number.isFinite(valor)) return "—";
  if (m.tipo_valor === "tempo_ms") return fmtTempo(valor);
  return fmtNum(valor, m.tipo_valor === "inteiro" ? 0 : undefined) + (m.unidade ? ` ${m.unidade}` : "");
}

export type TempoPartes = { m: string; s: string; c: string };

/** ms → { m, s, c } para o campo de tempo. */
export function tempoEmPartes(ms: number | null | undefined): TempoPartes {
  if (ms == null || !Number.isFinite(ms)) return { m: "", s: "", c: "" };
  const cs = Math.round(ms / 10);
  const m = Math.floor(cs / 6000);
  return { m: m ? String(m) : "", s: pad(Math.floor((cs % 6000) / 100)), c: pad(cs % 100) };
}

/** { m, s, c } → ms; null se faltar segundo/centésimo ou se segundos ≥ 60. */
export function partesEmTempo(p: TempoPartes): number | null {
  if (!/^\d{0,7}$/.test(p.m) || !/^\d{1,2}$/.test(p.s) || !/^\d{1,2}$/.test(p.c)) return null;
  const m = Number(p.m || 0), s = Number(p.s), c = Number(p.c.padEnd(2, "0"));
  if (![m, s, c].every(Number.isFinite) || s >= 60) return null;
  const ms = (m * 60 + s) * 1000 + c * 10;
  return ms > 0 ? ms : null;
}

/** "12/10/2026" → "2026-10-12"; null se não for uma data real. */
export function dataIso(br: string): string | null {
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(br.trim());
  if (!m) return null;
  const [d, mo, y] = [Number(m[1]), Number(m[2]), Number(m[3])];
  const dt = new Date(y, mo - 1, d);
  if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return null;
  return `${m[3]}-${m[2]}-${m[1]}`;
}

/** Máscara DD/MM/AAAA enquanto digita. */
export function mascaraData(v: string): string {
  const d = v.replace(/\D/g, "").slice(0, 8);
  return d.replace(/(\d{2})(\d)/, "$1/$2").replace(/(\d{2})(\d)/, "$1/$2");
}

/** Hoje em ISO, no fuso do aparelho. */
export function hojeIso(): string {
  const d = new Date();
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** a é melhor que b na direção da métrica. */
export const melhorQue = (a: number, b: number, direcao: Direcao) => (direcao === "menor_melhor" ? a < b : a > b);

export const MESES = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];

export function recordeErro(error: unknown): string {
  if (error && typeof error === "object") {
    if ("error" in error && typeof error.error === "string") return error.error;
    if ("message" in error && typeof error.message === "string") return error.message;
  }
  return "Não foi possível concluir a operação. Tente novamente.";
}

export type ValorMarca = { metrica_id: number; valor: number };
export type MarcaPayload = { definicao_id: number; data_recorde: string; observacoes: string | null; valores: ValorMarca[] };
export type MetricaInput = { texto: string; tempo: TempoPartes };

export function inputsMarca(metricas: RecordeMetrica[], marca?: Marca): Record<string, MetricaInput> {
  return Object.fromEntries(metricas.map(m => {
    const valor = marca?.valores[String(m.id)];
    return [String(m.id), { texto: valor == null ? "" : String(valor).replace(".", ","), tempo: tempoEmPartes(valor) }];
  }));
}

export function valorInput(input: MetricaInput | undefined, metrica: RecordeMetrica, original?: number): number | null {
  if (!input) return null;
  if (metrica.tipo_valor === "tempo_ms") {
    if (original != null && JSON.stringify(input.tempo) === JSON.stringify(tempoEmPartes(original))) return original;
    return partesEmTempo(input.tempo);
  }
  if (!/^\d+(?:[.,]\d{1,3})?$/.test(input.texto.trim())) return null;
  const valor = Number(input.texto.trim().replace(",", "."));
  if (!Number.isFinite(valor) || valor <= 0) return null;
  if (metrica.tipo_valor === "inteiro") return Number.isSafeInteger(valor) ? valor : null;
  return valor <= 999999999.999 ? valor : null;
}

export function payloadMarca(detalhe: ProvaDetalhe, inputs: Record<string, MetricaInput>, data: string, observacoes: string, marca?: Marca): MarcaPayload {
  const iso = dataIso(data);
  if (!iso) throw new Error("Informe uma data válida no formato DD/MM/AAAA.");
  if (iso > hojeIso()) throw new Error("A data não pode ser no futuro.");
  if (observacoes.trim().length > 500) throw new Error("A observação pode ter até 500 caracteres.");
  const valores: ValorMarca[] = [];
  for (const metrica of detalhe.metricas) {
    const input = inputs[String(metrica.id)];
    const vazio = !input || (metrica.tipo_valor === "tempo_ms" ? !input.tempo.m && !input.tempo.s && !input.tempo.c : !input.texto.trim());
    if (vazio && !metrica.obrigatoria && metrica.id !== detalhe.definicao.metrica_principal_id) continue;
    const valor = valorInput(input, metrica, marca?.valores[String(metrica.id)]);
    if (valor == null) throw new Error(`Informe ${metrica.nome.toLowerCase()} válido${metrica.tipo_valor === "inteiro" ? " (número inteiro)" : ""}.`);
    valores.push({ metrica_id: metrica.id, valor });
  }
  return { definicao_id: detalhe.definicao.id, data_recorde: iso, observacoes: observacoes.trim() || null, valores };
}

export function marcasNoPeriodo(marcas: Marca[], periodo: "6m" | "tudo", hoje = new Date()): Marca[] {
  const inicio = new Date(hoje.getFullYear(), hoje.getMonth() - 6, 1);
  inicio.setDate(Math.min(hoje.getDate(), new Date(inicio.getFullYear(), inicio.getMonth() + 1, 0).getDate()));
  const iso = `${inicio.getFullYear()}-${pad(inicio.getMonth() + 1)}-${pad(inicio.getDate())}`;
  return marcas.filter(m => periodo === "tudo" || m.data >= iso).sort((a, b) => a.data.localeCompare(b.data) || a.id - b.id);
}
