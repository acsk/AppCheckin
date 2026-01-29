import { DashboardCardsData } from './types';

/**
 * Valores default para os cards do dashboard
 */
export const DASHBOARD_CARDS_DEFAULT: DashboardCardsData = {
  total_alunos: {
    total: 0,
    ativos: 0,
    inativos: 0,
  },
  receita_mensal: {
    valor: 0,
    valor_formatado: 'R$ 0,00',
    contas_pendentes: 0,
  },
  checkins_hoje: {
    hoje: 0,
    no_mes: 0,
  },
  planos_vencendo: {
    vencendo: 0,
    novos_este_mes: 0,
  },
};
