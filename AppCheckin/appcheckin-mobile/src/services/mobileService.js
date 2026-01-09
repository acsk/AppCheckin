import api from './api';

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
      const response = await api.get('/mobile/perfil');
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
      const response = await api.get('/mobile/tenants');
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Registra um check-in
   */
  async registrarCheckin() {
    try {
      const response = await api.post('/mobile/checkin');
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
      const response = await api.get('/mobile/checkins', {
        params: { limit, offset }
      });
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },
};

export default mobileService;
