import api from './api';
import { prepararErro } from '../utils/errorHandler';

export const financeiroService = {
  /** Parcelas de matrícula (pagamentos_plano) filtradas pela data de vencimento. */
  async listarPagamentos({ dataInicio, dataFim, statusId } = {}) {
    try {
      const response = await api.get('/admin/pagamentos-plano', {
        params: {
          data_inicio: dataInicio || undefined,
          data_fim: dataFim || undefined,
          status_pagamento_id: statusId || undefined,
        },
      });
      return response.data?.pagamentos || [];
    } catch (error) {
      console.error('Erro ao listar pagamentos:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Parcelas pagas com data de pagamento no período (regime de caixa). É o "Recebido" de todas as
   * telas — Dashboard, Relatório financeiro e Assinaturas usam este mesmo critério.
   */
  async listarRecebidos({ dataInicio, dataFim } = {}) {
    try {
      const response = await api.get('/admin/pagamentos-plano', {
        params: { periodo_por: 'pagamento', data_inicio: dataInicio || undefined, data_fim: dataFim || undefined },
      });
      return response.data?.pagamentos || [];
    } catch (error) {
      throw prepararErro(error.response?.data || error);
    }
  },

  /** Conciliação bancária: pagamentos do Asaas/Mercado Pago no período casados com as baixas do Check-in. */
  async conciliacao({ dataInicio, dataFim } = {}) {
    try {
      const response = await api.get('/admin/financeiro/conciliacao', {
        params: { data_inicio: dataInicio || undefined, data_fim: dataFim || undefined },
      });
      return response.data;
    } catch (error) {
      throw prepararErro(error.response?.data || error);
    }
  },

  /** Reaplica no Check-in um pagamento do Asaas (baixa que faltou ou líquido/taxa da parcela). */
  async reprocessarConciliacao(paymentId) {
    try {
      const response = await api.post(`/admin/financeiro/conciliacao/${encodeURIComponent(paymentId)}/reprocessar`);
      return response.data;
    } catch (error) {
      throw prepararErro(error.response?.data || error);
    }
  },
};

export default financeiroService;
