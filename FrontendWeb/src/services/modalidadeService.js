import api from './api';

const modalidadeService = {
  /**
   * Listar todas as modalidades
   */
  async listar(apenasAtivas = false) {
    try {
      const response = await api.get('/admin/modalidades', {
        params: { apenas_ativas: apenasAtivas ? 'true' : 'false' }
      });
      return response.data.modalidades || [];
    } catch (error) {
      console.error('❌ Erro ao listar modalidades:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar modalidades' };
    }
  },

  /**
   * Buscar modalidade por ID
   */
  async buscar(id) {
    try {
      const response = await api.get(`/admin/modalidades/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar modalidade:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar modalidade' };
    }
  },

  /**
   * Criar nova modalidade
   */
  async criar(dados) {
    try {
      const response = await api.post('/admin/modalidades', dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar modalidade:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar modalidade' };
    }
  },

  /**
   * Atualizar modalidade
   */
  async atualizar(id, dados) {
    try {
      const response = await api.put(`/admin/modalidades/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar modalidade:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar modalidade' };
    }
  },

  /**
   * Excluir modalidade
   */
  async excluir(id) {
    try {
      const response = await api.delete(`/admin/modalidades/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao excluir modalidade:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao excluir modalidade' };
    }
  },
};

export default modalidadeService;
