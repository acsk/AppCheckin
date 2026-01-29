/**
 * Tipos relacionados ao perfil do usuário
 */

export interface UserProfile {
  id?: number;
  nome: string;
  email: string;
  cpf?: string;
  telefone?: string;
  data_nascimento?: string;
  foto_base64?: string;
  foto_caminho?: string;
  membro_desde?: string;
  tenant?: { nome: string };
  tenants?: { id: string; nome: string; email?: string; telefone?: string }[];
  estatisticas?: {
    total_checkins: number;
    checkins_mes: number;
    sequencia_dias: number;
    ultimo_checkin?: { data: string; hora: string };
  };
  ranking_modalidades?: {
    modalidade_id: number;
    modalidade_nome: string;
    modalidade_icone?: string;
    modalidade_cor?: string;
    posicao: number;
    total_checkins: number;
    total_participantes: number;
  }[];
}
