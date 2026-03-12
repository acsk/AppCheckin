import api from './api';
import { prepararErro } from '../utils/errorHandler';

export const pagamentoPlanoService = {
  async buscar(id) {
    try {
      const response = await api.get(`/admin/pagamentos-plano/${id}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar pagamento:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async atualizar(id, dados) {
    try {
      const response = await api.put(`/admin/pagamentos-plano/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('Erro ao atualizar pagamento:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async excluir(id) {
    try {
      const response = await api.delete(`/admin/pagamentos-plano/${id}/excluir`);
      return response.data;
    } catch (error) {
      console.error('Erro ao excluir pagamento:', error);
      throw prepararErro(error.response?.data || error);
    }
  },
};
