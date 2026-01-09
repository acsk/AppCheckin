import api from './api';

export const turmaService = {
  // Listar turmas - com suporte a filtro por data
  async listar(data = null, apenasAtivas = true) {
    try {
      const params = {
        apenas_ativas: apenasAtivas
      };
      
      // Se uma data foi fornecida, usar ela (formato: YYYY-MM-DD)
      if (data) {
        params.data = data;
      }
      
      const response = await api.get('/admin/turmas', { params });
      return response.data.turmas || [];
    } catch (error) {
      console.error('Erro ao listar turmas:', error);
      throw error;
    }
  },

  // Listar turmas por dia (para mobile)
  async listarPorDia(diaId) {
    try {
      const response = await api.get(`/turmas/dia/${diaId}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao listar turmas do dia:', error);
      throw error;
    }
  },

  // Listar turmas de um professor
  async listarPorProfessor(professorId) {
    try {
      const response = await api.get(`/admin/professores/${professorId}/turmas`);
      return response.data.turmas || [];
    } catch (error) {
      console.error('Erro ao listar turmas do professor:', error);
      throw error;
    }
  },

  // Buscar turma por ID
  async buscarPorId(id) {
    try {
      const response = await api.get(`/admin/turmas/${id}`);
      return response.data.turma;
    } catch (error) {
      console.error('Erro ao buscar turma:', error);
      throw error;
    }
  },

  // Criar nova turma
  async criar(data) {
    try {
      const response = await api.post('/admin/turmas', data);
      return response.data.turma;
    } catch (error) {
      console.error('Erro ao criar turma:', error);
      throw error;
    }
  },

  // Atualizar turma
  async atualizar(id, data) {
    try {
      const response = await api.put(`/admin/turmas/${id}`, data);
      return response.data.turma;
    } catch (error) {
      console.error('Erro ao atualizar turma:', error);
      throw error;
    }
  },

  // Deletar turma
  async deletar(id) {
    try {
      const response = await api.delete(`/admin/turmas/${id}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao deletar turma:', error);
      throw error;
    }
  },

  // Verificar vagas disponíveis
  async verificarVagas(id) {
    try {
      const response = await api.get(`/admin/turmas/${id}/vagas`);
      return response.data;
    } catch (error) {
      console.error('Erro ao verificar vagas:', error);
      throw error;
    }
  }
};
