import api from './api';

const erro = (error, fallback) => {
  const d = error?.response?.data || {};
  return { message: d.message || d.error || fallback, status: error?.response?.status, code: d.code };
};

// Super Admin: política de CNPJ duplicado, exceções e gestor cadastral
const cnpjService = {
  // Por padrão só CNPJs em uso por alguma academia; semAcademia inclui os que ficaram sem vínculo
  async listar({ busca = '', compartilhados = false, semAcademia = false } = {}) {
    try {
      return (await api.get('/superadmin/cnpj', { params: { busca: busca || undefined, compartilhados: compartilhados ? 1 : undefined, sem_academia: semAcademia ? 1 : undefined } })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar os CNPJs');
    }
  },

  async definirPolitica(politica) {
    try {
      return (await api.put('/superadmin/cnpj/politica', { politica })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar a política');
    }
  },

  async detalhe(cnpj) {
    try {
      return (await api.get(`/superadmin/cnpj/${cnpj}`)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível carregar o CNPJ');
    }
  },

  async salvarDados(cnpj, dados) {
    try {
      return (await api.put(`/superadmin/cnpj/${cnpj}`, dados)).data;
    } catch (error) {
      throw erro(error, 'Não foi possível salvar os dados do CNPJ');
    }
  },

  async autorizar(cnpj, justificativa) {
    try {
      return (await api.post(`/superadmin/cnpj/${cnpj}/excecoes`, { justificativa })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível autorizar o compartilhamento');
    }
  },

  async revogar(excecaoId, motivo) {
    try {
      return (await api.post(`/superadmin/cnpj/excecoes/${excecaoId}/revogar`, { motivo })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível revogar a exceção');
    }
  },

  async definirGestor(cnpj, tenantId, justificativa) {
    try {
      return (await api.post(`/superadmin/cnpj/${cnpj}/gestor`, { tenant_id: tenantId, justificativa })).data;
    } catch (error) {
      throw erro(error, 'Não foi possível definir o gestor');
    }
  },
};

export default cnpjService;
