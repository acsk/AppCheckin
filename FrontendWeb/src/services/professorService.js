import api from './api';

export const professorService = {
  // Listar todos os professores
  async listar(apenasAtivos = true) {
    try {
      const response = await api.get('/admin/professores', {
        params: {
          apenas_ativos: apenasAtivos
        }
      });
      return response.data.professores || [];
    } catch (error) {
      console.error('Erro ao listar professores:', error);
      throw error;
    }
  },

  // Buscar professor por ID
  async buscarPorId(id) {
    try {
      const response = await api.get(`/admin/professores/${id}`);
      return response.data.professor;
    } catch (error) {
      console.error('Erro ao buscar professor:', error);
      throw error;
    }
  },

  // Criar novo professor
  async criar(data) {
    try {
      const response = await api.post('/admin/professores', data);
      return response.data.professor;
    } catch (error) {
      console.error('Erro ao criar professor:', error);
      throw error;
    }
  },

  // Atualizar professor
  async atualizar(id, data) {
    try {
      const response = await api.put(`/admin/professores/${id}`, data);
      return response.data.professor;
    } catch (error) {
      console.error('Erro ao atualizar professor:', error);
      throw error;
    }
  },

  // Deletar professor
  async deletar(id) {
    try {
      const response = await api.delete(`/admin/professores/${id}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao deletar professor:', error);
      throw error;
    }
  }
};
