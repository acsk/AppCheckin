import api from './api';

export const horarioService = {
  // Listar horários de um dia específico
  async listarPorDia(diaId) {
    try {
      const response = await api.get(`/admin/turmas/horarios/${diaId}`);
      return response.data.horarios || [];
    } catch (error) {
      console.error('Erro ao listar horários:', error);
      throw error;
    }
  },

  // Listar horários de uma data específica
  async listarPorData(data) {
    try {
      const response = await api.get('/admin/horarios', {
        params: { data }
      });
      return response.data.horarios || [];
    } catch (error) {
      console.error('Erro ao listar horários por data:', error);
      throw error;
    }
  },
};
