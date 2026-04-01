import api from './api';

const recordeService = {
  // ==================== DEFINIÇÕES ====================

  async listarDefinicoes(params = {}) {
    try {
      const response = await api.get('/admin/recordes/definicoes', { params });
      return response.data.definicoes || [];
    } catch (error) {
      console.error('❌ Erro ao listar definições de recordes:', error.response?.data || error.message);
      throw error;
    }
  },

  async buscarDefinicao(id) {
    try {
      const response = await api.get(`/admin/recordes/definicoes/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar definição:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar definição' };
    }
  },

  async criarDefinicao(dados) {
    try {
      const response = await api.post('/admin/recordes/definicoes', dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar definição:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar definição' };
    }
  },

  async atualizarDefinicao(id, dados) {
    try {
      const response = await api.put(`/admin/recordes/definicoes/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar definição:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar definição' };
    }
  },

  async desativarDefinicao(id) {
    try {
      const response = await api.delete(`/admin/recordes/definicoes/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao desativar definição:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao desativar definição' };
    }
  },

  // ==================== RECORDES ====================

  async listarRecordes(params = {}) {
    try {
      const response = await api.get('/admin/recordes', { params });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar recordes:', error.response?.data || error.message);
      throw error;
    }
  },

  async buscarRecorde(id) {
    try {
      const response = await api.get(`/admin/recordes/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar recorde:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar recorde' };
    }
  },

  async criarRecorde(dados) {
    try {
      const response = await api.post('/admin/recordes', dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar recorde:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar recorde' };
    }
  },

  async atualizarRecorde(id, dados) {
    try {
      const response = await api.put(`/admin/recordes/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar recorde:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar recorde' };
    }
  },

  async excluirRecorde(id) {
    try {
      const response = await api.delete(`/admin/recordes/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao excluir recorde:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao excluir recorde' };
    }
  },

  // ==================== RANKING ====================

  async ranking(definicaoId, params = {}) {
    try {
      const response = await api.get(`/admin/recordes/ranking/${definicaoId}`, { params });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar ranking:', error.response?.data || error.message);
      throw error;
    }
  },
};

export default recordeService;
