import api from './api';
import type { DashboardCardsData, DashboardCardsResponse } from '../models';

/**
 * Service para gerenciar dados do dashboard
 */

/**
 * Busca todos os contadores principais do dashboard
 * @returns Dados do dashboard (alunos, turmas, professores, receita, etc)
 */
export const buscarDashboard = async (): Promise<any> => {
  try {
    const response = await api.get('/admin/dashboard');
    return response.data;
  } catch (error) {
    console.error('Erro ao buscar dashboard:', error);
    throw error;
  }
};

/**
 * Busca dados dos cards do dashboard
 * @returns Objeto com total_alunos, receita_mensal, checkins_hoje, planos_vencendo
 */
export const buscarDashboardCards = async (): Promise<DashboardCardsResponse | null> => {
  try {
    const response = await api.get<DashboardCardsResponse>('/admin/dashboard/cards');
    return response.data;
  } catch (error: any) {
    if (error.response?.status === 404) {
      console.warn('⚠️ Endpoint /admin/dashboard/cards não implementado no backend');
      return null;
    }
    console.error('Erro ao buscar cards do dashboard:', error);
    throw error;
  }
};

export default {
  buscarDashboard,
  buscarDashboardCards,
};
