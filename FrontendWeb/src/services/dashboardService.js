import api from './api';

/**
 * Service para gerenciar dados do dashboard
 */

/**
 * Busca todos os contadores principais do dashboard
 * @returns {Promise} Dados do dashboard (alunos, turmas, professores, receita, etc)
 */
export const buscarDashboard = async () => {
  try {
    const response = await api.get('/admin/dashboard');
    return response.data;
  } catch (error) {
    console.error('Erro ao buscar dashboard:', error);
    throw error;
  }
};

/**
 * Busca quantidade de turmas agrupadas por modalidade
 * @returns {Promise} Array de objetos {id, nome, total}
 */
export const buscarTurmasPorModalidade = async () => {
  try {
    const response = await api.get('/admin/dashboard/turmas-por-modalidade');
    return response.data;
  } catch (error) {
    console.error('Erro ao buscar turmas por modalidade:', error);
    throw error;
  }
};

/**
 * Busca quantidade de alunos agrupados por modalidade
 * @returns {Promise} Array de objetos {id, nome, total}
 */
export const buscarAlunosPorModalidade = async () => {
  try {
    const response = await api.get('/admin/dashboard/alunos-por-modalidade');
    return response.data;
  } catch (error) {
    console.error('Erro ao buscar alunos por modalidade:', error);
    throw error;
  }
};

/**
 * Busca quantidade de check-ins dos últimos 7 dias
 * @returns {Promise} Array de objetos {data, total}
 */
export const buscarCheckinsUltimos7Dias = async () => {
  try {
    const response = await api.get('/admin/dashboard/checkins-últimos-7-dias');
    return response.data;
  } catch (error) {
    console.error('Erro ao buscar check-ins:', error);
    throw error;
  }
};

export default {
  buscarDashboard,
  buscarTurmasPorModalidade,
  buscarAlunosPorModalidade,
  buscarCheckinsUltimos7Dias,
};
