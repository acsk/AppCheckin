import api from './api';

const erro = (error, fallback) => {
  const d = error?.response?.data || {};
  return { message: d.error || d.message || fallback, status: error?.response?.status, campos: d.campos };
};

// Super Admin: configuração remota do app (rascunho → publicação → rollback)
const appConfigService = {
  async estado() {
    try {
      return (await api.get('/superadmin/app-config')).data;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar as configurações do app');
    }
  },

  /** valor null = volta a herdar (geral → padrão; academia → geral). */
  async definir(chave, valor, tenantId = null) {
    try {
      return (await api.put('/superadmin/app-config/valor', { chave, valor, tenant_id: tenantId })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar');
    }
  },

  async publicar(nota) {
    try {
      return (await api.post('/superadmin/app-config/publicar', { nota })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível publicar');
    }
  },

  async descartar() {
    try {
      return (await api.post('/superadmin/app-config/descartar')).data;
    } catch (error) {
      throw erro(error, 'Não foi possível descartar');
    }
  },

  async bloquear(funcionalidade, bloquear, nota) {
    try {
      return (await api.post(`/superadmin/app-config/bloqueio/${funcionalidade}`, { bloquear, nota })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível alterar o bloqueio');
    }
  },

  async historico() {
    try {
      return (await api.get('/superadmin/app-config/historico')).data;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar o histórico');
    }
  },

  async restaurar(versao) {
    try {
      return (await api.post(`/superadmin/app-config/publicacoes/${versao}/restaurar`)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível restaurar');
    }
  },

  async efetiva({ tenantId, plataforma, versao }) {
    try {
      const params = new URLSearchParams({ plataforma });
      if (tenantId) params.set('tenant_id', tenantId);
      if (versao) params.set('versao', versao);
      return (await api.get(`/superadmin/app-config/efetiva?${params}`)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível calcular a configuração');
    }
  },
};

export default appConfigService;
