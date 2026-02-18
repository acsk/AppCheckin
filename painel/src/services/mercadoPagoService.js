import api from './api';
import { prepararErro } from '../utils/errorHandler';

const mercadoPagoService = {
  async listarWebhooks() {
    try {
      const response = await api.get('/api/webhooks/mercadopago/list');
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar webhooks MP:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  async buscarWebhook(id) {
    try {
      const response = await api.get(`/api/webhooks/mercadopago/show/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar webhook MP:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  async consultarPagamento(paymentId) {
    try {
      const response = await api.get(`/api/webhooks/mercadopago/payment/${paymentId}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao consultar pagamento MP:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  async reprocessarPagamento(paymentId) {
    try {
      const response = await api.post(`/api/webhooks/mercadopago/payment/${paymentId}/reprocess`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao reprocessar pagamento MP:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },
};

export default mercadoPagoService;
