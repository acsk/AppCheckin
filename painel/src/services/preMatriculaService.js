import api from './api';

const mensagem = (error, fallback) => {
  const data = error?.response?.data || {};
  const detalhes = Object.values(data.errors || {}).flat().join(' ');
  return detalhes || data.message || data.error || error?.message || fallback;
};

const preMatriculaService = {
  async listar(tenantId) {
    try {
      return (await api.get('/superadmin/pre-matriculas', { params: { tenant_id: tenantId } })).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível carregar as pré-matrículas.'));
    }
  },

  async salvarConfiguracao(dados) {
    try {
      return (await api.put('/superadmin/pre-matriculas/configuracao', dados)).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível salvar a configuração.'));
    }
  },

  async excluirConfiguracao(tenantId) {
    try {
      return (await api.delete('/superadmin/pre-matriculas/configuracao', { params: { tenant_id: tenantId } })).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível remover a configuração.'));
    }
  },

  async avaliar(id, tenantId, decisao, observacao) {
    try {
      return (await api.post(`/superadmin/pre-matriculas/${id}/${decisao}`, {
        tenant_id: tenantId,
        observacao,
      })).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível registrar a decisão.'));
    }
  },

  async atualizar(id, tenantId, dados) {
    try {
      return (await api.put(`/superadmin/pre-matriculas/${id}`, { ...dados, tenant_id: tenantId })).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível atualizar a pré-matrícula.'));
    }
  },

  async excluir(id, tenantId) {
    try {
      return (await api.delete(`/superadmin/pre-matriculas/${id}`, { params: { tenant_id: tenantId } })).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível excluir a pré-matrícula.'));
    }
  },

  async listarAcademia() {
    try {
      return (await api.get('/admin/pre-matriculas')).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível carregar as pré-matrículas.'));
    }
  },

  async atualizarAcademia(id, dados) {
    try {
      return (await api.put(`/admin/pre-matriculas/${id}`, dados)).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível ajustar a pré-matrícula.'));
    }
  },

  async excluirAcademia(id) {
    try {
      return (await api.delete(`/admin/pre-matriculas/${id}`)).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível excluir a pré-matrícula.'));
    }
  },

  async avaliarAcademia(id, decisao, observacao) {
    try {
      return (await api.post(`/admin/pre-matriculas/${id}/${decisao}`, { observacao })).data;
    } catch (error) {
      throw new Error(mensagem(error, 'Não foi possível registrar a decisão.'));
    }
  },
};

export default preMatriculaService;
