import api from './api';

const assinaturaService = {
  /**
   * Listar todas as assinaturas ativas da academia
   */
  async listar(filtros = {}) {
    try {
      const params = {
        status: filtros.status || 'ativa',
        ...filtros
      };
      const response = await api.get('/admin/assinaturas', { params });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar assinaturas:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar assinaturas' };
    }
  },

  /**
   * Listar todas as assinaturas de todas as academias (SuperAdmin)
   */
  async listarTodas(tenantId = null, filtros = {}) {
    try {
      const params = {
        status: filtros.status || 'ativa',
        ...filtros
      };
      if (tenantId) params.tenant_id = tenantId;

      const response = await api.get('/superadmin/assinaturas', { params });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar assinaturas (SuperAdmin):', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar assinaturas' };
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

  /**
   * Criar nova assinatura
   */
  async criar(dados) {
    try {
      const response = await api.post('/admin/assinaturas', dados);
      return response.data;
    } catch (error) {
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
      throw error.response?.data || { error: 'Erro ao listar histórico de assinaturas' };
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
