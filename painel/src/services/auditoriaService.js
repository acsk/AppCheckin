import api from './api';
import { prepararErro } from '../utils/errorHandler';

export const auditoriaService = {
  async pagamentosDuplicados() {
    try {
      const response = await api.get('/admin/auditoria/pagamentos-duplicados');
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar pagamentos duplicados:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async pagamentosDuplicadosDetalhe(filtros = {}) {
    try {
      const params = {};
      if (filtros.aluno_id) params.aluno_id = filtros.aluno_id;
      if (filtros.matricula_id) params.matricula_id = filtros.matricula_id;
      if (filtros.ano) params.ano = filtros.ano;
      if (filtros.mes) params.mes = filtros.mes;

      const response = await api.get('/admin/auditoria/pagamentos-duplicados/detalhe', { params });
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar detalhe de duplicados:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async anomaliasDatas() {
    try {
      const response = await api.get('/admin/auditoria/anomalias-datas');
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar anomalias de datas:', error);
      throw prepararErro(error.response?.data || error);
    }
  },
};
