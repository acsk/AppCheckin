import api from './api';

const pagamentoContratoService = {
  /**
   * Listar todos os pagamentos de contrato
   */
  async listar(filtros = {}) {
    try {
      const response = await api.get('/superadmin/pagamentos-contrato', {
        params: filtros
      });
      return response.data;
    } catch (error) {
      console.error('Erro ao listar pagamentos de contrato:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Obter resumo dos pagamentos
   */
  async obterResumo() {
    try {
      const response = await api.get('/superadmin/pagamentos-contrato/resumo');
      return response.data;
    } catch (error) {
      console.error('Erro ao obter resumo de pagamentos:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Listar pagamentos de um contrato específico
   * @param {number} contratoId - ID do contrato
   */
  async listarPorContrato(contratoId) {
    try {
      const response = await api.get(`/superadmin/contratos/${contratoId}/pagamentos-contrato`);
      return response.data;
    } catch (error) {
      console.error('Erro ao listar pagamentos do contrato:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Criar novo pagamento de contrato
   * @param {number} contratoId - ID do contrato
   * @param {object} dados - Dados do pagamento
   */
  async criar(contratoId, dados) {
    try {
      const response = await api.post(
        `/superadmin/contratos/${contratoId}/pagamentos-contrato`,
        dados
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao criar pagamento:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Confirmar/Baixar pagamento
   * @param {number} pagamentoId - ID do pagamento
   * @param {object} dados - Dados da confirmação
   */
  async confirmar(pagamentoId, dados) {
    try {
      const response = await api.post(
        `/superadmin/pagamentos-contrato/${pagamentoId}/confirmar`,
        dados
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao confirmar pagamento:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Cancelar pagamento
   * @param {number} pagamentoId - ID do pagamento
   */
  async cancelar(pagamentoId) {
    try {
      const response = await api.delete(
        `/superadmin/pagamentos-contrato/${pagamentoId}`
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao cancelar pagamento:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Marcar pagamentos como atrasados
   */
  async marcarAtrasados() {
    try {
      const response = await api.post(
        '/superadmin/pagamentos-contrato/marcar-atrasados'
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao marcar pagamentos como atrasados:', error);
      throw error.response?.data || error;
    }
  }
};

export default pagamentoContratoService;
