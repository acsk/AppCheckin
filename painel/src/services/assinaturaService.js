import api from './api';
import { prepararErro } from '../utils/errorHandler';

const assinaturaService = {
  /**
   * Listar assinaturas do tenant (Admin)
   */
  async listar(filtros = {}) {
    try {
      const params = {};
      if (filtros.status) params.status = filtros.status;
      if (filtros.tipo_cobranca) params.tipo_cobranca = filtros.tipo_cobranca;
      if (filtros.busca) params.busca = filtros.busca;
      if (filtros.page) params.page = filtros.page;
      if (filtros.per_page) params.per_page = filtros.per_page;
      const response = await api.get('/admin/assinaturas', { params });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar assinaturas:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Listar todas as assinaturas de todas as academias (SuperAdmin)
   */
  async listarTodas(tenantId = null, filtros = {}) {
    try {
      const params = {};
      if (filtros.status) params.status = filtros.status;
      if (filtros.tipo_cobranca) params.tipo_cobranca = filtros.tipo_cobranca;
      if (filtros.busca) params.busca = filtros.busca;
      if (filtros.page) params.page = filtros.page;
      if (filtros.per_page) params.per_page = filtros.per_page;
      if (tenantId) params.tenant_id = tenantId;

      const response = await api.get('/superadmin/assinaturas', { params });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar assinaturas (SuperAdmin):', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Buscar assinatura por ID
   */
  async buscar(id) {
    try {
      const response = await api.get(`/admin/assinaturas/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar assinatura:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar assinatura' };
    }
  },

  /* Integrada com matrículas - cria assinatura junto com matrícula
   * 
   * @param {Object} dados - {aluno_id, plano_id, data_inicio, forma_pagamento, renovacoes}
   * @param {boolean} criarMatricula - Se true, cria matrícula junto com assinatura (padrão: true)
   */
  async criar(dados, criarMatricula = true) {
    try {
      const payload = {
        ...dados,
        criar_matricula: criarMatricula
      };
      const response = await api.post('/admin/assinaturas', payload);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar assinatura:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Criar assinatura a partir de uma matrícula existente
   * Liga uma matrícula já existente a uma assinatura
   */
  async criarDasMatricula(matriculaId, dados = {}) {
    try {
      const response = await api.post(`/admin/matriculas/${matriculaId}/assinatura`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar assinatura da matrícula:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error)
      console.error('❌ Erro ao criar assinatura:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar assinatura' };
    }
  },

  /**
   * Atualizar assinatura
   */
  async atualizar(id, dados) {
    try {
      const response = await api.put(`/admin/assinaturas/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar assinatura:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar assinatura' };
    }
  },

  /**
   * Renovar assinatura (estender período)
   */
  async renovar(id, dados) {
    try {
      const response = await api.post(`/admin/assinaturas/${id}/renovar`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao renovar assinatura:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao renovar assinatura' };
    }
  },

  /**
   * Suspender assinatura
   */
  async suspender(id, motivo = '') {
    try {
      const response = await api.post(`/admin/assinaturas/${id}/suspender`, { motivo });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao suspender assinatura:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao suspender assinatura' };
    }
  },

  /**
   * Cancelar assinatura
   */
  async cancelar(id, motivo = '') {
    try {
      const response = await api.post(`/admin/assinaturas/${id}/cancelar`, { motivo });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao cancelar assinatura:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao cancelar assinatura' };
    }
  },

  /**
   * Reativar assinatura suspensa
   */
  async reativar(id) {
    try {
      const response = await api.post(`/admin/assinaturas/${id}/reativar`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao reativar assinatura:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao reativar assinatura' };
    }
  },

  /**
   * Listar assinaturas que vencerão em breve
   */
  async listarProximasVencer(dias = 30) {
    try {
      const response = await api.get('/admin/assinaturas/proximas-vencer', {
        params: { dias }
      });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar assinaturas próximas de vencer:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar assinaturas próximas de vencer' };
    }
  },

  /**
   * Listar histórico de assinaturas de um aluno
   */
  async listarHistoricoAluno(alunoId) {
    try {
      const response = await api.get(`/admin/alunos/${alunoId}/assinaturas`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar histórico de assinaturas:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Sincronizar assinatura com matrícula
   * Atualiza status da assinatura baseado no status da matrícula
   */
  async sincronizarComMatricula(assinaturaId) {
    try {
      const response = await api.post(`/admin/assinaturas/${assinaturaId}/sincronizar-matricula`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao sincronizar:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Obter status de sincronização entre assinatura e matrícula
   */
  async obterStatusSincronizacao(assinaturaId) {
    try {
      const response = await api.get(`/admin/assinaturas/${assinaturaId}/status-sincronizacao`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao obter status:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Listar assinaturas que não têm matrícula associada
   */
  async listarSemMatricula(filtros = {}) {
    try {
      const response = await api.get('/admin/assinaturas/sem-matricula', {
        params: filtros
      });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar sem matrícula:', error.response?.data || error.message);
      throw prepararErro(error.response?.data || error);
    }
  },

  /**
   * Relatório de assinaturas (receita, churn, etc)
   */
  async relatorio(filtros = {}) {
    try {
      const response = await api.get('/admin/assinaturas/relatorio', {
        params: filtros
      });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao gerar relatório:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao gerar relatório' };
    }
  }
};

export default assinaturaService;
