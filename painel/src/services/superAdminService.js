import api from './api';

export const superAdminService = {
  // Listar todas as academias
  async listarAcademias(busca = '') {
    try {
      const params = busca ? `?busca=${encodeURIComponent(busca)}` : '';
      console.log(`🌐 Fazendo requisição GET /superadmin/academias${params}`);
      const response = await api.get(`/superadmin/academias${params}`);
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

  // Ativar/Desativar academia
  async toggleAtivoAcademia(id, ativo) {
    try {
      console.log(`🔄 ${ativo ? 'Ativando' : 'Desativando'} academia ${id}`);
      const response = await api.put(`/superadmin/academias/${id}`, { ativo });
      console.log('✅ Status alterado:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao alterar status da academia:', error);
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
  },

  // ========================================
  // Gestão de Papéis
  // ========================================

  /**
   * Listar papéis disponíveis
   */
  async listarPapeis() {
    try {
      console.log('📋 Listando papéis disponíveis');
      try {
        const response = await api.get('/superadmin/papeis');
        console.log('✅ Papéis carregados:', response.data);
        return response.data;
      } catch (error1) {
        try {
          const response = await api.get('/papeis');
          console.log('✅ Papéis carregados:', response.data);
          return response.data;
        } catch (error2) {
          // Fallback: retornar papéis padrão hardcoded
          console.warn('⚠️ Usando papéis padrão (API não disponível)');
          return {
            papeis: [
              {
                id: 1,
                nome: 'Aluno',
                descricao: 'Pode acessar o app mobile e fazer check-in'
              },
              {
                id: 2,
                nome: 'Professor',
                descricao: 'Pode marcar presença e gerenciar turmas'
              },
              {
                id: 3,
                nome: 'Admin',
                descricao: 'Pode acessar o painel administrativo'
              }
            ]
          };
        }
      }
    } catch (error) {
      console.error('❌ Erro ao listar papéis:', error);
      throw error.response?.data || error;
    }
  },

  // ========================================
  // Gestão de Admins da Academia
  // ========================================

  /**
   * Listar admins de uma academia
   */
  async listarAdmins(tenantId) {
    try {
      console.log(`👥 Listando admins da academia ${tenantId}`);
      const response = await api.get(`/superadmin/academias/${tenantId}/admins`);
      console.log('✅ Admins carregados:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar admins:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Criar admin para uma academia
   */
  async criarAdmin(tenantId, dados) {
    try {
      console.log(`➕ Criando admin para academia ${tenantId}`, dados);
      const response = await api.post(`/superadmin/academias/${tenantId}/admin`, dados);
      console.log('✅ Admin criado:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar admin:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Atualizar admin de uma academia
   */
  async atualizarAdmin(tenantId, adminId, dados) {
    try {
      console.log(`✏️ Atualizando admin ${adminId} da academia ${tenantId}`, dados);
      const response = await api.put(`/superadmin/academias/${tenantId}/admins/${adminId}`, dados);
      console.log('✅ Admin atualizado:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar admin:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Desativar admin de uma academia
   */
  async desativarAdmin(tenantId, adminId, dados = undefined) {
    try {
      console.log(`🚫 Desativando admin ${adminId} da academia ${tenantId}`, dados);
      const response = await api.delete(`/superadmin/academias/${tenantId}/admins/${adminId}`, {
        data: dados
      });
      console.log('✅ Admin desativado:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao desativar admin:', error);
      throw error.response?.data || error;
    }
  },

  /**
   * Reativar admin de uma academia
   */
  async reativarAdmin(tenantId, adminId) {
    try {
      console.log(`✅ Reativando admin ${adminId} da academia ${tenantId}`);
      const response = await api.post(`/superadmin/academias/${tenantId}/admins/${adminId}/reativar`);
      console.log('✅ Admin reativado:', response.data);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao reativar admin:', error);
      throw error.response?.data || error;
    }
  },

  // Admin principal (titular) da academia: só o super admin define
  async definirAdminPrincipal(tenantId, adminId) {
    const response = await api.post(`/superadmin/academias/${tenantId}/admins/${adminId}/principal`);
    return response.data;
  },

  // Unificar dois cadastros da mesma pessoa (origem some, destino fica)
  async previaUnificacao(origem, destino) {
    const response = await api.get('/superadmin/usuarios-unificar/previa', { params: { origem, destino } });
    return response.data;
  },

  async unificarUsuarios(origem, destino) {
    const response = await api.post('/superadmin/usuarios-unificar', { origem, destino });
    return response.data;
  },

  // Motivos do "Acessar como academia" (motivos_acesso_suporte)
  async listarMotivosAcesso() {
    const response = await api.get('/superadmin/academias/acesso-motivos');
    return response.data?.motivos || [];
  },
};

export default superAdminService;