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
};

export default financeiroService;
