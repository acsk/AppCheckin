import api from './api';

const usuarioService = {
  /**
   * Listar todos os usuários do tenant
   */
  async listar(apenasAtivos = false) {
    try {
      const params = apenasAtivos ? '?ativos=true' : '';
      const response = await api.get(`/tenant/usuarios${params}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar usuários:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar usuários' };
    }
  },

  /**
   * Buscar usuário por ID
   * Usa a rota SuperAdmin se o usuário for SuperAdmin
   */
  async buscar(id, isSuperAdmin = false) {
    try {
      const endpoint = isSuperAdmin 
        ? `/superadmin/usuarios/${id}` 
        : `/tenant/usuarios/${id}`;
      const response = await api.get(endpoint);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar usuário:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar usuário' };
    }
  },

  /**
   * Criar novo usuário
   */
  async criar(dados) {
    try {
      const response = await api.post('/tenant/usuarios', dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar usuário:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar usuário' };
    }
  },

  /**
   * Atualizar usuário
   * Usa a rota SuperAdmin se o usuário for SuperAdmin
   */
  async atualizar(id, dados, isSuperAdmin = false) {
    try {
      const endpoint = isSuperAdmin 
        ? `/superadmin/usuarios/${id}` 
        : `/tenant/usuarios/${id}`;
      const response = await api.put(endpoint, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar usuário:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar usuário' };
    }
  },

  /**
   * Desativar/Excluir usuário
   * Usa a rota apropriada dependendo se o usuário é SuperAdmin
   */
  async desativar(id, isSuperAdmin = false) {
    try {
      const endpoint = isSuperAdmin 
        ? `/superadmin/usuarios/${id}` 
        : `/tenant/usuarios/${id}`;
      const response = await api.delete(endpoint);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao desativar usuário:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao desativar usuário' };
    }
  },

  /**
   * Buscar histórico de planos do usuário (rota admin)
   */
  async historico(id) {
    try {
      const response = await api.get(`/admin/alunos/${id}/historico-planos`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar histórico:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar histórico' };
    }
  },

  /**
   * Listar usuários básico (rota admin - mantida para compatibilidade)
   */
  async listarBasico() {
    try {
      const response = await api.get('/admin/alunos/basico');
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar usuários:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar usuários' };
    }
  }
};

export default usuarioService;
