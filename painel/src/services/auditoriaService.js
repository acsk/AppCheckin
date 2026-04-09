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

  async repararProximaDataVencimento(dryRun = false) {
    try {
      const params = dryRun ? { 'dry-run': '1' } : {};
      const response = await api.post('/admin/auditoria/reparar-proxima-data-vencimento', null, { params });
      return response.data;
    } catch (error) {
      console.error('Erro ao reparar proxima_data_vencimento:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async checkinsAcimaDoLimite(filtros = {}) {
    try {
      const params = {};
      if (filtros.ano) params.ano = filtros.ano;
      if (filtros.mes) params.mes = filtros.mes;
      const response = await api.get('/admin/auditoria/checkins-acima-do-limite', { params });
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar checkins acima do limite:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async checkinsMultiplosNoDia(filtros = {}) {
    try {
      const params = {};
      if (filtros.data_inicio) params.data_inicio = filtros.data_inicio;
      if (filtros.data_fim) params.data_fim = filtros.data_fim;
      if (filtros.aluno_id) params.aluno_id = filtros.aluno_id;
      if (filtros.modalidade_id) params.modalidade_id = filtros.modalidade_id;
      if (filtros.mesma_modalidade !== undefined) params.mesma_modalidade = filtros.mesma_modalidade ? '1' : '0';
      const response = await api.get('/admin/auditoria/checkins-multiplos-no-dia', { params });
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar checkins múltiplos no dia:', error);
      throw prepararErro(error.response?.data || error);
    }
  },
};
