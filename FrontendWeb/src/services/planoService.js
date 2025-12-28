import api from './api';

const planoService = {
  /**
   * Listar todos os planos
   */
  async listar(apenasAtivos = false) {
    try {
      const response = await api.get('/planos', {
        params: { ativos: apenasAtivos }
      });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar planos:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar planos' };
    }
  },

  /**
   * Buscar plano por ID
   */
  async buscar(id) {
    try {
      const response = await api.get(`/planos/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar plano' };
    }
  },

  /**
   * Criar novo plano
   */
  async criar(dados) {
    try {
      const response = await api.post('/admin/planos', dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar plano' };
    }
  },

  /**
   * Atualizar plano
   */
  async atualizar(id, dados) {
    try {
      const response = await api.put(`/admin/planos/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar plano' };
    }
  },

  /**
   * Desativar plano
   */
  async desativar(id) {
    try {
      const response = await api.delete(`/admin/planos/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao desativar plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao desativar plano' };
    }
  },
};

export default planoService;
