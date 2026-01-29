/**
 * Tipos relacionados aos check-ins e calendário semanal
 */

export interface ModalidadeInfo {
  id: number;
  nome: string;
  cor?: string;
  icone?: string;
}

export interface DiaCheckin {
  data: string;
  modalidade: ModalidadeInfo;
}

export interface CheckinSemanal {
  data: string;
  modalidade_id: number;
  modalidade_nome: string;
  modalidade_cor?: string;
  total: number;
}

export interface SemanaInfo {
  inicio: string;
  fim: string;
}

export interface CheckinsPorModalidade {
  semana_inicio: string;
  semana_fim: string;
  total: number;
  dias: DiaCheckin[];
  modalidades: (ModalidadeInfo & { total: number })[];
}
