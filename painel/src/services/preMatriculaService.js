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
};

export default preMatriculaService;
