import api from './api';

const erro = (error, fallback) => {
  const d = error?.response?.data || {};
  return { message: d.message || d.error || fallback, status: error?.response?.status };
};

// Super Admin: versão publicada do app mobile e se a atualização é obrigatória
const appVersaoService = {
  async buscar() {
    try {
      return (await api.get('/superadmin/app-versao')).data.mobile;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar a versão do app');
    }
  },

  async buscarRankingCheckins() {
    try {
      return (await api.get('/superadmin/app-versao/ranking-checkins')).data;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar o limite do ranking');
    }
  },

  async definirLimiteRankingCheckins(limite) {
    try {
      return (await api.put('/superadmin/app-versao/ranking-checkins', { limite })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar o limite do ranking');
    }
  },

  async definirObrigatoria(obrigatoria) {
    try {
      return (await api.put('/superadmin/app-versao', { obrigatoria })).data.mobile;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar');
    }
  },
};

export default appVersaoService;
