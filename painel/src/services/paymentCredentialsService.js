import api from './api';

class PaymentCredentialsService {
  /**
   * Obter credenciais de pagamento do tenant
   */
  async obterCredenciais() {
    try {
      const response = await api.get('/admin/payment-credentials');
      return response.data;
    } catch (error) {
      console.error('Erro ao obter credenciais:', error);
      throw error;
    }
  }

  /**
   * Salvar ou atualizar credenciais
   */
  async salvarCredenciais(dados) {
    try {
      const response = await api.post('/admin/payment-credentials', dados);
      return response.data;
    } catch (error) {
      console.error('Erro ao salvar credenciais:', error);
      throw error;
    }
  }

  /**
   * Testar conexão com Mercado Pago
   */
  async testarConexao() {
    try {
      const response = await api.post('/admin/payment-credentials/test');
      return response.data;
    } catch (error) {
      console.error('Erro ao testar conexão:', error);
      throw error;
    }
  }
}

export default new PaymentCredentialsService();
