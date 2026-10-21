import api from './api';
import { prepararErro } from '../utils/errorHandler';

const mercadoPagoService = {
  async reprocessarPagamento(paymentId) {
    try {
      const response = await api.post(`/api/webhooks/mercadopago/payment/${paymentId}/reprocess`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao reprocessar pagamento MP:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  async consultarCobrancas(externalReference) {
    try {
      const response = await api.get('/api/webhooks/mercadopago/cobrancas', {
        params: { external_reference: externalReference }
      });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao consultar cobranças MP:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },
};

export default mercadoPagoService;
