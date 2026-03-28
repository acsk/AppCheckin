import api from './api';
import { prepararErro } from '../utils/errorHandler';

export const creditoService = {
  async listar(alunoId) {
    try {
      const response = await api.get(`/admin/alunos/${alunoId}/creditos`);
      return response.data;
    } catch (error) {
      console.error('Erro ao listar créditos:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async consultarSaldo(alunoId) {
    try {
      const response = await api.get(`/admin/alunos/${alunoId}/creditos/saldo`);
      return response.data;
    } catch (error) {
      console.error('Erro ao consultar saldo de créditos:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async criar(alunoId, dados) {
    try {
      const response = await api.post(`/admin/alunos/${alunoId}/creditos`, dados);
      return response.data;
    } catch (error) {
      console.error('Erro ao criar crédito:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async cancelar(creditoId) {
    try {
      const response = await api.delete(`/admin/creditos/${creditoId}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao cancelar crédito:', error);
      throw prepararErro(error.response?.data || error);
    }
  },
};

export default creditoService;
