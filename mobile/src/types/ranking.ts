/**
 * Tipos relacionados ao ranking de check-ins
 */

export interface RankingUsuario {
  id: number;
  nome: string;
  foto_caminho?: string;
}

export interface RankingItem {
  posicao: number;
  usuario?: RankingUsuario;
  aluno?: RankingUsuario;
  total_checkins: number;
}

export type RankingPeriodoTipo = "mensal" | "anual";

export interface RankingModalidade {
  id: number;
  nome: string;
  icone?: string;
  cor?: string;
}
