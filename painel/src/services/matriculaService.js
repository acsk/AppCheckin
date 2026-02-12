import api from './api';
import { prepararErro } from '../utils/errorHandler';

export const matriculaService = {
  async listar(filtros = {}) {
    try {
      const params = new URLSearchParams();
      if (filtros.pagina) params.append('pagina', filtros.pagina);
      if (filtros.por_pagina) params.append('por_pagina', filtros.por_pagina);
      if (filtros.status) params.append('status', filtros.status);
      if (filtros.aluno_id) params.append('aluno_id', filtros.aluno_id);
      if (filtros.incluir_inativos !== undefined) {
        params.append('incluir_inativos', filtros.incluir_inativos ? 'true' : 'false');
      }
      if (filtros.busca) params.append('busca', filtros.busca);

      const query = params.toString();
      const response = await api.get(`/admin/matriculas${query ? `?${query}` : ''}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao listar matrículas:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async buscar(id) {
    try {
      const response = await api.get(`/admin/matriculas/${id}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async deletePreview(id) {
    try {
      const response = await api.get(`/admin/matriculas/${id}/delete-preview`);
      return response.data;
    } catch (error) {
      console.error('Erro ao carregar prévia de exclusão:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async criar(data) {
    try {
      const response = await api.post('/admin/matriculas', data);
      return response.data;
    } catch (error) {
      console.error('Erro ao criar matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async cancelar(id) {
    try {
      const response = await api.post(`/admin/matriculas/${id}/cancelar`);
      return response.data;
    } catch (error) {
      console.error('Erro ao cancelar matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async deletar(id) {
    try {
      const response = await api.delete(`/admin/matriculas/${id}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao excluir matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async buscarPagamentos(id) {
    try {
      const response = await api.get(`/admin/matriculas/${id}/pagamentos`);
      return response.data;
    } catch (error) {
      console.error('Erro ao buscar pagamentos:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async confirmarPagamento(matriculaId, pagamentoId, dados = {}) {
    try {
      const response = await api.post(
        `/admin/matriculas/${matriculaId}/pagamentos/${pagamentoId}/confirmar`,
        dados
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao confirmar pagamento:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async atualizarProximaDataVencimento(matriculaId, proximaDataVencimento) {
    try {
      const response = await api.put(
        `/admin/matriculas/${matriculaId}/proxima-data-vencimento`,
        { proxima_data_vencimento: proximaDataVencimento }
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao atualizar data de vencimento:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async listarVencimentosHoje() {
    try {
      const response = await api.get('/admin/matriculas/vencimentos/hoje');
      return response.data;
    } catch (error) {
      console.error('Erro ao listar vencimentos de hoje:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  async listarProximosVencimentos(dias = 7) {
    try {
      const response = await api.get(`/admin/matriculas/vencimentos/proximos?dias=${dias}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao listar próximos vencimentos:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Criar assinatura para matrícula existente
   * @param {number} matriculaId - ID da matrícula
   * @param {object} dados - Dados da assinatura (renovacoes, etc)
   * @returns {Promise} Assinatura criada
   */
  async criarAssinatura(matriculaId, dados = {}) {
    try {
      const response = await api.post(
        `/admin/matriculas/${matriculaId}/assinatura`,
        dados
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao criar assinatura para matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Obter assinatura associada à matrícula
   * @param {number} matriculaId - ID da matrícula
   * @returns {Promise} Dados da assinatura
   */
  async obterAssinatura(matriculaId) {
    try {
      const response = await api.get(`/admin/matriculas/${matriculaId}/assinatura`);
      return response.data;
    } catch (error) {
      console.error('Erro ao obter assinatura:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Suspender matrícula e sua assinatura associada
   * @param {number} id - ID da matrícula
   * @param {string} motivo - Motivo da suspensão
   * @returns {Promise} Resultado da suspensão
   */
  async suspender(id, motivo = '') {
    try {
      const response = await api.post(`/admin/matriculas/${id}/suspender`, {
        motivo
      });
      return response.data;
    } catch (error) {
      console.error('Erro ao suspender matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Reativar matrícula e sua assinatura associada
   * @param {number} id - ID da matrícula
   * @returns {Promise} Resultado da reativação
   */
  async reativar(id) {
    try {
      const response = await api.post(`/admin/matriculas/${id}/reativar`);
      return response.data;
    } catch (error) {
      console.error('Erro ao reativar matrícula:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Listar matrículas com suas assinaturas associadas
   * @param {object} filtros - Filtros opcionais
   * @returns {Promise} Lista de matrículas com assinaturas
   */
  async listarComAssinaturas(filtros = {}) {
    try {
      const params = new URLSearchParams({
        incluir_assinaturas: true,
        ...filtros
      });
      const response = await api.get(`/admin/matriculas?${params}`);
      return response.data;
    } catch (error) {
      console.error('Erro ao listar matrículas com assinaturas:', error);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Sincronizar status de matrícula com sua assinatura
   * @param {number} matriculaId - ID da matrícula
   * @returns {Promise} Resultado da sincronização
   */
  async sincronizarAssinatura(matriculaId) {
    try {
      const response = await api.post(
        `/admin/matriculas/${matriculaId}/sincronizar-assinatura`
      );
      return response.data;
    } catch (error) {
      console.error('Erro ao sincronizar assinatura:', error);
      throw prepararErro(error.response?.data || error);
    }
  }

};
