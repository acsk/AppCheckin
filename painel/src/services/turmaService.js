import api from './api';

export const turmaService = {
  // Listar turmas - com suporte a filtro por data
  async listar(data = null, apenasAtivas = false) {
    try {
      const params = {
        apenas_ativas: apenasAtivas
      };
      
      // Se uma data foi fornecida, usar ela (formato: YYYY-MM-DD)
      if (data) {
        params.data = data;
      }
      
      const response = await api.get('/admin/turmas', { params });
      // Retorna objeto com dia e turmas
      return {
        dia: response.data.dia || null,
        turmas: response.data.turmas || []
      };
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

  // Deletar todas as turmas de um dia
  async deletarHorariosDia(diaId) {
    try {
      const response = await api.delete(`/admin/dias/${diaId}/horarios`);
      return response.data;
    } catch (error) {
      console.error('Erro ao deletar horários do dia:', error);
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
  },

  // Replicar turmas para diferentes períodos
  async replicar(diaId, periodo = 'custom', diasSemana = [], mes = null, modalidadeId = null) {
    try {
      const payload = {
        dia_id: diaId,
        periodo: periodo,
      };

      // Adicionar dias_semana apenas se for customizado
      if (periodo === 'custom') {
        payload.dias_semana = diasSemana;
      }

      // Adicionar mês se for mes_todo ou custom
      if ((periodo === 'mes_todo' || periodo === 'custom') && mes) {
        payload.mes = mes;
      }

      // Adicionar modalidade_id se fornecido
      if (modalidadeId) {
        payload.modalidade_id = parseInt(modalidadeId);
      }

      const response = await api.post('/admin/turmas/replicar', payload);
      return response.data;
    } catch (error) {
      console.error('Erro ao replicar turmas:', error);
      throw error;
    }
  },

  // Desativar turmas
  async desativar(turmaId, periodo = 'apenas_esta', mes = null) {
    try {
      const payload = {
        turma_id: turmaId,
        periodo: periodo,
      };

      if ((periodo === 'mes_todo' || periodo === 'custom') && mes) {
        payload.mes = mes;
      }

      const response = await api.post('/admin/turmas/desativar', payload);
      return response.data;
    } catch (error) {
      console.error('Erro ao desativar turma:', error);
      throw error;
    }
  }
};
