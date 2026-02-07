import api from './api';

const planoService = {
  /**
   * Listar todos os planos (para Admin do tenant)
   */
  async listar(apenasAtivos = false) {
    try {
      const response = await api.get('/planos', {
        params: { ativos: apenasAtivos }
      });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar planos:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar planos' };
    }
  },

  /**
   * Listar planos de todas as academias (para SuperAdmin)
   */
  async listarTodos(tenantId = null, apenasAtivos = false) {
    try {
      const params = {};
      if (tenantId) params.tenant_id = tenantId;
      if (apenasAtivos) params.ativos = 'true';
      
      const response = await api.get('/superadmin/planos', { params });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar planos (SuperAdmin):', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar planos' };
    }
  },

  /**
   * Buscar plano por ID
   */
  async buscar(id) {
    try {
      const response = await api.get(`/admin/planos/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar plano' };
    }
  },

  /**
   * Criar novo plano
   */
  async criar(dados) {
    try {
      const response = await api.post('/admin/planos', dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar plano' };
    }
  },

  /**
   * Atualizar plano
   */
  async atualizar(id, dados) {
    try {
      const response = await api.put(`/admin/planos/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar plano' };
    }
  },

  /**
   * Desativar plano
   */
  async desativar(id) {
    try {
      const response = await api.delete(`/admin/planos/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao desativar plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao desativar plano' };
    }
  },

  /**
   * Tipos de ciclo
   */
  async listarTiposCiclo() {
    try {
      const response = await api.get('/admin/tipos-ciclo');
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar tipos de ciclo:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar tipos de ciclo' };
    }
  },

  /**
   * Ciclos do plano
   */
  async listarCiclos(planoId) {
    try {
      const response = await api.get(`/admin/planos/${planoId}/ciclos`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar ciclos do plano:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar ciclos do plano' };
    }
  },

  async criarCiclo(planoId, dados) {
    try {
      const response = await api.post(`/admin/planos/${planoId}/ciclos`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar ciclo:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar ciclo' };
    }
  },

  async atualizarCiclo(planoId, cicloId, dados) {
    try {
      const response = await api.put(`/admin/planos/${planoId}/ciclos/${cicloId}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar ciclo:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar ciclo' };
    }
  },

  async excluirCiclo(planoId, cicloId) {
    try {
      const response = await api.delete(`/admin/planos/${planoId}/ciclos/${cicloId}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao excluir ciclo:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao excluir ciclo' };
    }
  },

  async gerarCiclos(planoId, dados = null) {
    try {
      const response = await api.post(`/admin/planos/${planoId}/ciclos/gerar`, dados || {});
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao gerar ciclos:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao gerar ciclos' };
    }
  },
};

export default planoService;
