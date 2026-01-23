import AsyncStorage from "@/src/utils/storage";
import api from "./api";

/**
 * Serviço de autenticação
 * Gerencia login, logout, seleção de tenant e armazenamento de tokens
 */
export const authService = {
  /**
   * Realiza login com email e senha
   * Se houver múltiplos tenants, seleciona automaticamente o primeiro
   */
  async login(email, senha) {
    try {
      const response = await api.post("/auth/login", { email, senha });

      // Se tem token, já salva (usuário tem apenas 1 tenant)
      if (response.data.token) {
        await AsyncStorage.setItem("@appcheckin:token", response.data.token);
        await AsyncStorage.setItem(
          "@appcheckin:user",
          JSON.stringify(response.data.user),
        );

        // Salvar tenants disponíveis (para trocar depois)
        if (response.data.tenants) {
          await AsyncStorage.setItem(
            "@appcheckin:tenants",
            JSON.stringify(response.data.tenants),
          );
        }

        return response.data;
      }

      // Se não tem token mas tem múltiplos tenants, selecionar o primeiro automaticamente
      if (
        response.data.requires_tenant_selection &&
        response.data.tenants?.length > 0
      ) {
        const firstTenant = response.data.tenants[0];
        const tenantId = firstTenant.tenant?.id || firstTenant.id;

        // Fazer seleção inicial de tenant (endpoint público)
        const selectResponse = await this.selectTenantInitial(
          response.data.user.id,
          email,
          tenantId,
        );

        return selectResponse;
      }

      return response.data;
    } catch (error) {
      console.error("❌ ERRO NO LOGIN:", {
        status: error.status,
        statusCode: error.response?.status,
        errorData: error.response?.data,
        message: error.message,
        isNetworkError: error.isNetworkError,
      });

      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Seleção inicial de tenant durante login (endpoint público)
   * Usada quando o login retorna múltiplos tenants sem token
   */
  async selectTenantInitial(userId, email, tenantId) {
    try {
      const response = await api.post("/auth/select-tenant-initial", {
        user_id: userId,
        email: email,
        tenant_id: tenantId,
      });

      if (response.data.token) {
        await AsyncStorage.setItem("@appcheckin:token", response.data.token);
        await AsyncStorage.setItem(
          "@appcheckin:user",
          JSON.stringify(response.data.user),
        );

        // Salvar tenants disponíveis (para trocar depois)
        if (response.data.tenants) {
          await AsyncStorage.setItem(
            "@appcheckin:tenants",
            JSON.stringify(response.data.tenants),
          );
        }

        // Salvar tenant atual
        if (response.data.tenant) {
          await AsyncStorage.setItem(
            "@appcheckin:current_tenant",
            JSON.stringify(response.data.tenant),
          );
        }
      }

      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Seleciona um tenant após login (para usuários já autenticados)
   */
  async selectTenant(tenantId) {
    try {
      const response = await api.post("/auth/select-tenant", {
        tenant_id: tenantId,
      });

      if (response.data.token) {
        await AsyncStorage.setItem("@appcheckin:token", response.data.token);
        await AsyncStorage.setItem(
          "@appcheckin:user",
          JSON.stringify(response.data.user),
        );

        // Atualizar tenant atual
        if (response.data.tenant) {
          await AsyncStorage.setItem(
            "@appcheckin:current_tenant",
            JSON.stringify(response.data.tenant),
          );
        }
      }

      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Realiza logout, removendo todos os dados salvos
   */
  async logout() {
    await AsyncStorage.removeItem("@appcheckin:token");
    await AsyncStorage.removeItem("@appcheckin:user");
    await AsyncStorage.removeItem("@appcheckin:tenants");
    await AsyncStorage.removeItem("@appcheckin:current_tenant");
  },

  /**
   * Retorna o usuário logado atualmente
   */
  async getCurrentUser() {
    const userJson = await AsyncStorage.getItem("@appcheckin:user");
    return userJson ? JSON.parse(userJson) : null;
  },

  /**
   * Retorna o token atual
   */
  async getToken() {
    return await AsyncStorage.getItem("@appcheckin:token");
  },

  /**
   * Verifica se o usuário está autenticado
   */
  async isAuthenticated() {
    const token = await this.getToken();
    return !!token;
  },

  /**
   * Retorna os tenants disponíveis do usuário
   */
  async getTenants() {
    const tenantsJson = await AsyncStorage.getItem("@appcheckin:tenants");
    return tenantsJson ? JSON.parse(tenantsJson) : null;
  },

  /**
   * Retorna o tenant atual selecionado
   */
  async getCurrentTenant() {
    const tenantJson = await AsyncStorage.getItem("@appcheckin:current_tenant");
    return tenantJson ? JSON.parse(tenantJson) : null;
  },

  /**
   * Solicita recuperação de senha enviando email com token
   * @param {string} email - Email do usuário
   * @returns {Promise<{message: string}>}
   */
  async requestPasswordRecovery(email) {
    try {
      const response = await api.post("/auth/password-recovery/request", {
        email,
      });
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Valida o token de recuperação de senha
   * @param {string} token - Token de recuperação
   * @returns {Promise<{message: string, user: object}>}
   */
  async validatePasswordToken(token) {
    try {
      const response = await api.post(
        "/auth/password-recovery/validate-token",
        {
          token,
        },
      );
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Reseta a senha usando um token válido
   * @param {string} token - Token de recuperação
   * @param {string} nova_senha - Nova senha
   * @param {string} confirmacao_senha - Confirmação da senha
   * @returns {Promise<{message: string}>}
   */
  async resetPassword(token, nova_senha, confirmacao_senha) {
    try {
      const response = await api.post("/auth/password-recovery/reset", {
        token,
        nova_senha,
        confirmacao_senha,
      });
      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },
};

export default authService;
