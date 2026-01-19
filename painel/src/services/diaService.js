import api from './api';

export const diaService = {
  // Listar todos os dias
  async listar() {
    try {
      const response = await api.get('/admin/dias');
      return response.data.dias || [];
    } catch (error) {
      console.error('Erro ao listar dias:', error);
      throw error;
    }
  },

  // Buscar dia por ID
  async buscarPorId(id) {
    try {
      const response = await api.get(`/admin/dias/${id}`);
      return response.data.dia;
    } catch (error) {
      console.error('Erro ao buscar dia:', error);
      throw error;
    }
  },

  // Criar novo dia
  async criar(data) {
    try {
      const response = await api.post('/admin/dias', data);
      return response.data.dia;
    } catch (error) {
      console.error('Erro ao criar dia:', error);
      throw error;
    }
  },

  // Atualizar dia
  async atualizar(id, data) {
    try {
      const response = await api.put(`/admin/dias/${id}`, data);
      return response.data.dia;
    } catch (error) {
      console.error('Erro ao atualizar dia:', error);
      throw error;
    }
  },

  // Deletar dia
  async deletar(id) {
    try {
      const response = await api.delete(`/admin/dias/${id}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao deletar dia:', error);
      throw error;
    }
  },

  // Desativar dias (feriados, bloqueios)
  async desativar(diaId, periodo = 'apenas_este', diasSemana = null, mes = null) {
    try {
      const payload = {
        dia_id: diaId,
        periodo: periodo,
      };

      if (diasSemana && diasSemana.length > 0) {
        payload.dias_semana = diasSemana;
      }

      if ((periodo === 'mes_todo' || periodo === 'custom') && mes) {
        payload.mes = mes;
      }

      const response = await api.post('/admin/dias/desativar', payload);
      return response.data;
    } catch (error) {
      console.error('Erro ao desativar dia:', error);
      throw error;
    }
  }
};
