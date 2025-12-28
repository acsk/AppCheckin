import api from './api';

export const superAdminService = {
  // Listar todas as academias
  async listarAcademias() {
    try {
      console.log('🌐 Fazendo requisição GET /superadmin/academias');
      const response = await api.get('/superadmin/academias');
      console.log('✅ Status:', response.status);
      console.log('📦 Data:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro na requisição:', error);
      console.error('📄 Response error:', error.response);
      throw error.response?.data || error;
    }
  },

  // Criar nova academia
  async criarAcademia(dados) {
    try {
      const response = await api.post('/superadmin/academias', dados);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Criar admin para uma academia
  async criarAdminAcademia(tenantId, dados) {
    try {
      const response = await api.post(`/superadmin/academias/${tenantId}/admin`, dados);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Buscar academia por ID
  async buscarAcademia(id) {
    try {
      console.log(`🔍 Buscando academia ${id}`);
      const response = await api.get(`/superadmin/academias/${id}`);
      console.log('✅ Academia encontrada:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar academia:', error);
      throw error.response?.data || error;
    }
  },

  // Atualizar academia
  async atualizarAcademia(id, dados) {
    try {
      console.log(`✏️ Atualizando academia ${id}`, dados);
      const response = await api.put(`/superadmin/academias/${id}`, dados);
      console.log('✅ Academia atualizada:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar academia:', error);
      throw error.response?.data || error;
    }
  },

  // Excluir academia (soft delete)
  async excluirAcademia(id) {
    try {
      console.log(`🗑️ Excluindo academia ${id}`);
      const response = await api.delete(`/superadmin/academias/${id}`);
      console.log('✅ Academia excluída:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao excluir academia:', error);
      throw error.response?.data || error;
    }
  },

  // ========================================
  // Gestão de Usuários (SuperAdmin)
  // ========================================

  /**
   * Listar todos os usuários de todos os tenants
   * @returns {Promise<Object>} Objeto com total e array de usuários
   */
  async listarTodosUsuarios() {
    try {
      console.log('👥 Fazendo requisição GET /superadmin/usuarios');
      const response = await api.get('/superadmin/usuarios');
      console.log('✅ Usuários carregados:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar usuários:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar usuários' };
    }
  }
};

export default superAdminService;