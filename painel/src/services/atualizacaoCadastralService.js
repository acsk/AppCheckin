import api from './api';

const erro = (error, fallback) => {
  const d = error?.response?.data || {};
  return { message: d.error || d.message || fallback, status: error?.response?.status };
};

// Super Admin: campanhas de atualização cadastral do app (campos, e-mail, senha)
const atualizacaoCadastralService = {
  async listar() {
    try {
      return (await api.get('/superadmin/atualizacoes-cadastrais')).data;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar as atualizações cadastrais');
    }
  },

  async salvar(id, dados) {
    try {
      const { data } = id
        ? await api.put(`/superadmin/atualizacoes-cadastrais/${id}`, dados)
        : await api.post('/superadmin/atualizacoes-cadastrais', dados);
      return data.campanha;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar');
    }
  },

  async pendentes(id) {
    try {
      return (await api.get(`/superadmin/atualizacoes-cadastrais/${id}/pendentes`)).data.pendentes;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar quem falta atualizar');
    }
  },

  async encerrar(id) {
    try {
      return (await api.post(`/superadmin/atualizacoes-cadastrais/${id}/encerrar`)).data.campanha;
    } catch (error) {
      throw erro(error, 'Não foi possível encerrar');
    }
  },

  async excluir(id) {
    try {
      return (await api.delete(`/superadmin/atualizacoes-cadastrais/${id}`)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível excluir a atualização cadastral');
    }
  },
};

export default atualizacaoCadastralService;
