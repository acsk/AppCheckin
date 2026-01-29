import api from './api';

const alunoService = {
  /**
   * Listar alunos do tenant
   * @param {Object} params
   * @param {boolean} params.apenasAtivos
   * @param {string} params.busca
   * @param {number} params.pagina
   * @param {number} params.porPagina
   */
  async listar({ apenasAtivos = false, busca = '', pagina, porPagina } = {}) {
    try {
      const query = new URLSearchParams();
      if (apenasAtivos) query.append('apenas_ativos', 'true');
      if (busca) query.append('busca', busca);
      if (pagina) query.append('pagina', String(pagina));
      if (porPagina) query.append('por_pagina', String(porPagina));

      const qs = query.toString();
      const response = await api.get(`/admin/alunos${qs ? `?${qs}` : ''}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar alunos:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar alunos' };
    }
  },

  /**
   * Listar alunos básico (para selects)
   */
  async listarBasico() {
    try {
      const response = await api.get('/admin/alunos/basico');
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao listar alunos (básico):', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao listar alunos' };
    }
  },

  /**
   * Buscar aluno por ID
   */
  async buscar(id) {
    try {
      const response = await api.get(`/admin/alunos/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar aluno:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar aluno' };
    }
  },

  /**
   * Buscar aluno por CPF (global, cross-tenant)
   * @param {string} cpf - CPF do aluno (apenas números)
   * @returns {Promise<Object>} { found, aluno, tenants, ja_associado, pode_associar }
   */
  async buscarPorCpf(cpf) {
    try {
      const cpfLimpo = cpf.replace(/\D/g, '');
      const response = await api.get(`/admin/alunos/buscar-cpf/${cpfLimpo}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao buscar aluno por CPF:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao buscar aluno por CPF' };
    }
  },

  /**
   * Associar aluno existente ao tenant atual
   * @param {number} alunoId - ID do aluno a ser associado
   * @returns {Promise<Object>} { success, message, aluno }
   */
  async associar(alunoId) {
    try {
      const response = await api.post('/admin/alunos/associar', { aluno_id: alunoId });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao associar aluno:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao associar aluno' };
    }
  },

  /**
   * Criar aluno
   */
  async criar(dados) {
    try {
      const response = await api.post('/admin/alunos', dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao criar aluno:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao criar aluno' };
    }
  },

  /**
   * Atualizar aluno
   */
  async atualizar(id, dados) {
    try {
      const response = await api.put(`/admin/alunos/${id}`, dados);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao atualizar aluno:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao atualizar aluno' };
    }
  },

  /**
   * Desativar aluno (soft delete)
   */
  async desativar(id) {
    try {
      const response = await api.delete(`/admin/alunos/${id}`);
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao desativar aluno:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao desativar aluno' };
    }
  },

  /**
   * Ativar/Desativar aluno (toggle status)
   */
  async toggleStatus(id, ativo) {
    try {
      const response = await api.put(`/admin/alunos/${id}`, { ativo: ativo ? 1 : 0 });
      return response.data;
    } catch (error) {
      console.error('❌ Erro ao alterar status do aluno:', error.response?.data || error.message);
      throw error.response?.data || { error: 'Erro ao alterar status do aluno' };
    }
  },

  /**
   * Histórico de planos do aluno
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
};

export default alunoService;
