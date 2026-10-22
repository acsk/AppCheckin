import api from './api';

const erro = (error, fallback) => {
  const d = error?.response?.data || {};
  return { message: d.message || d.error || fallback, status: error?.response?.status, code: d.code };
};

// Equipe e permissões da academia (admin principal ou quem tem equipe.gerenciar)
const equipeService = {
  async listar() {
    try {
      return (await api.get('/admin/equipe')).data;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar a equipe');
    }
  },

  async buscar({ cpf, email }) {
    try {
      return (await api.get('/admin/equipe/buscar', { params: { cpf: cpf || undefined, email: email || undefined } })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível procurar a pessoa');
    }
  },

  async adicionar(dados) {
    try {
      return (await api.post('/admin/equipe', dados)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível adicionar a pessoa');
    }
  },

  async atualizar(usuarioId, dados) {
    try {
      return (await api.put(`/admin/equipe/${usuarioId}`, dados)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar');
    }
  },

  async remover(usuarioId) {
    try {
      return (await api.delete(`/admin/equipe/${usuarioId}`)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível remover da equipe');
    }
  },
};

export default equipeService;
