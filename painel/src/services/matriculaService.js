import api from './api';
import { prepararErro } from '../utils/errorHandler';

export const matriculaService = {
  async listar() {
    try {
      const response = await api.get('/admin/matriculas');
      return response.data;
    } catch (error) {
      console.error('Erro ao listar matrículas:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async buscar(id) {
    try {
      const response = await api.get(`/admin/matriculas/${id}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async criar(data) {
    try {
      const response = await api.post('/admin/matriculas', data);
      return response.data;
    } catch (error) {
      console.error('Erro ao criar matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async cancelar(id) {
    try {
      const response = await api.post(`/admin/matriculas/${id}/cancelar`);
      return response.data;
    } catch (error) {
      console.error('Erro ao cancelar matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async buscarPagamentos(id) {
    try {
      const response = await api.get(`/admin/matriculas/${id}/pagamentos`);
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar pagamentos:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async confirmarPagamento(matriculaId, pagamentoId, dados = {}) {
    try {
      const response = await api.post(
        `/admin/matriculas/${matriculaId}/pagamentos/${pagamentoId}/confirmar`,
        dados
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao confirmar pagamento:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async atualizarProximaDataVencimento(matriculaId, proximaDataVencimento) {
    try {
      const response = await api.put(
        `/admin/matriculas/${matriculaId}/proxima-data-vencimento`,
        { proxima_data_vencimento: proximaDataVencimento }
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao atualizar data de vencimento:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async listarVencimentosHoje() {
    try {
      const response = await api.get('/admin/matriculas/vencimentos/hoje');
      return response.data;
    } catch (error) {
      console.error('Erro ao listar vencimentos de hoje:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async listarProximosVencimentos(dias = 7) {
    try {
      const response = await api.get(`/admin/matriculas/vencimentos/proximos?dias=${dias}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao listar próximos vencimentos:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

};
