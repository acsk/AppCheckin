import api from "./api";

/**
 * Serviço para endpoints do App Mobile
 * Comunica com o MobileController do backend
 */
export const mobileService = {
  /**
   * Busca o perfil completo do usuário com estatísticas
   */
  async getPerfil() {
    try {
      const response = await api.get("/mobile/perfil");
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Lista os tenants/academias do usuário
   */
  async getTenants() {
    try {
      const response = await api.get("/mobile/tenants");
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Busca os contratos/planos ativos do tenant
   */
  async getContratos() {
    try {
      const response = await api.get("/mobile/contratos");
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Busca todos os planos/contratos do tenant (ativo, vencido, pendente, etc)
   * Permite visualizar múltiplos planos contratados
   */
  async getPlanos() {
    try {
      const response = await api.get("/mobile/planos");
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Registra um check-in
   */
  async registrarCheckin(data = {}) {
    try {
      const response = await api.post("/mobile/checkin", data);
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Busca o histórico de check-ins
   */
  async getCheckins(limit = 30, offset = 0) {
    try {
      const response = await api.get("/mobile/checkins", {
        params: { limit, offset },
      });
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Lista o histórico de check-ins
   * @param {number} limit - Limite de registros (padrão 30, máximo 100)
   * @param {number} offset - Offset para paginação
   */
  async getHistoricoCheckins(limit = 30, offset = 0) {
    try {
      const response = await api.get("/mobile/checkins", {
        params: { limit, offset },
      });
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Busca os WODs do dia
   */
  async getWodsHoje() {
    try {
      const response = await api.get("/mobile/wods/hoje");
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Busca o WOD de uma modalidade específica
   * @param {number} modalidadeId - ID da modalidade
   * @param {string} data - Data do WOD (formato YYYY-MM-DD)
   */
  async getWodPorModalidade(modalidadeId, data) {
    try {
      const response = await api.get("/mobile/wod/hoje", {
        params: { data, modalidade_id: modalidadeId },
      });
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Busca as modalidades com WOD disponível hoje
   */
  async getModalidadesComWodHoje() {
    try {
      const response = await api.get("/mobile/wods/hoje");
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Atualiza a foto de perfil do usuário
   * @param {FormData} formData - FormData contendo a foto com chave 'foto'
   * @returns {Object} {success, data: {caminho_url}}
   */
  async atualizarFoto(formData) {
    try {
      const response = await api.post("/mobile/perfil/foto", formData);
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Obtém a foto de perfil do usuário
   * @returns {Blob} Blob da imagem
   */
  async obterFoto() {
    try {
      const response = await api.get("/mobile/perfil/foto", {
        responseType: "blob",
      });
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },
};

export default mobileService;
