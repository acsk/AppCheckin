import AsyncStorage from "@/src/utils/storage";
import api from "./api";

/**
 * Serviço de autenticação
 * Gerencia login, logout, seleção de tenant e armazenamento de tokens
 */
export const authService = {
  _getTenantIdFromData(tenantLike) {
    return tenantLike?.tenant?.id ?? tenantLike?.id ?? null;
  },

  _getTenantRoles(tenantsList, tenantId) {
    if (!tenantId || !Array.isArray(tenantsList)) return [];
    const match = tenantsList.find(
      (t) => (t?.tenant?.id ?? t?.id) === tenantId,
    );
    const roles = match?.papeis || match?.roles || [];
    return Array.isArray(roles) ? roles : [];
  },

  _applyTenantRolesToUser(user, roles) {
    if (!user) return user;
    if (!Array.isArray(roles) || roles.length === 0) return user;
    const unique = [];
    const seen = new Set();
    roles.forEach((r) => {
      const id = r?.id ?? r?.papel_id;
      if (!id || seen.has(id)) return;
      seen.add(id);
      unique.push(r);
    });
    return {
      ...user,
      papeis: unique,
      papel_id: user?.papel_id ?? unique[0]?.id ?? unique[0]?.papel_id ?? null,
    };
  },
  /**
   * Realiza login com email e senha
   */
  async login(email, senha, recaptchaToken = null) {
    try {
      const payload = { email, senha };
      if (recaptchaToken) {
        payload.recaptcha_token = recaptchaToken;
      }

      const response = await api.post("/auth/login", payload);

      console.log("🔐 LOGIN RESPONSE COMPLETO:", JSON.stringify(response.data, null, 2));
      console.log("🔐 USER no login:", response.data.user);
      console.log("🔐 TENANTS no login:", response.data.tenants);
      if (response.data.tenants?.[0]) {
        console.log("🔐 PRIMEIRO TENANT DETALHES:", JSON.stringify(response.data.tenants[0], null, 2));
      }

      // Se tem token, já salva (usuário tem apenas 1 tenant)
      if (response.data.token) {
        let userToSave = response.data.user;
        await AsyncStorage.setItem("@appcheckin:token", response.data.token);

        // Salvar tenants disponíveis (para trocar depois)
        if (response.data.tenants) {
          await AsyncStorage.setItem(
            "@appcheckin:tenants",
            JSON.stringify(response.data.tenants),
          );
          
          // Salvar o tenant atual (primeiro da lista)
          if (response.data.tenants.length > 0) {
            const firstTenantData = response.data.tenants[0];
            const tenantToSave = firstTenantData.tenant || firstTenantData;
            const tenantId = this._getTenantIdFromData(tenantToSave);
            const roles = this._getTenantRoles(
              response.data.tenants,
              tenantId,
            );
            userToSave = this._applyTenantRolesToUser(userToSave, roles);
            
            await AsyncStorage.setItem(
              "@appcheckin:current_tenant",
              JSON.stringify(tenantToSave),
            );
            
            if (tenantToSave?.id) {
              await AsyncStorage.setItem("@appcheckin:tenant_id", String(tenantToSave.id));
            }
            if (tenantToSave?.slug) {
              await AsyncStorage.setItem("@appcheckin:tenant_slug", tenantToSave.slug);
            }
            if (tenantToSave?.nome) {
              await AsyncStorage.setItem("@appcheckin:tenant_nome", tenantToSave.nome);
            }
          }
        }

        await AsyncStorage.setItem(
          "@appcheckin:user",
          JSON.stringify(userToSave),
        );

        return response.data;
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
   * Lista tenants públicos ativos para cadastro
   */
  async getPublicTenants() {
    try {
      const response = await api.get("/auth/tenants-public", {
        skipAuth: true,
      });

      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Cadastro público para mobile (cria usuário aluno e retorna token)
   */
  async registerMobile(payload) {
    try {
      const response = await api.post("/auth/register-mobile", payload);

      if (response.data?.token) {
        await AsyncStorage.setItem("@appcheckin:token", response.data.token);
        if (response.data.user) {
          await AsyncStorage.setItem(
            "@appcheckin:user",
            JSON.stringify(response.data.user),
          );
        }

        const tenantId = response.data.tenant_id || response.data.tenant?.id;
        if (tenantId) {
          await AsyncStorage.setItem("@appcheckin:tenant_id", String(tenantId));
          await AsyncStorage.setItem(
            "@appcheckin:current_tenant",
            JSON.stringify(response.data.tenant || { id: tenantId }),
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
   * Seleção inicial de tenant durante login (endpoint público)
   * Usada quando o login retorna múltiplos tenants sem token
   */
  async selectTenantPublic(userId, email, tenantId) {
    try {
      const response = await api.post("/auth/select-tenant-public", {
        user_id: userId,
        email: email,
        tenant_id: tenantId,
      });

      if (response.data.token) {
        let userToSave = response.data.user;
        console.log("🔐 SELECT-TENANT-INITIAL RESPONSE:", JSON.stringify(response.data, null, 2));
        console.log("🔐 USER após select-tenant:", response.data.user);
        console.log("🔐 USER.papel_id após select-tenant:", response.data.user?.papel_id);
        
        await AsyncStorage.setItem("@appcheckin:token", response.data.token);

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
          const t = response.data.tenant;
          let roles = this._getTenantRoles(
            response.data.tenants,
            this._getTenantIdFromData(t),
          );
          if (!roles.length) {
            try {
              const storedTenantsJson = await AsyncStorage.getItem(
                "@appcheckin:tenants",
              );
              const storedTenants = storedTenantsJson
                ? JSON.parse(storedTenantsJson)
                : [];
              roles = this._getTenantRoles(
                storedTenants,
                this._getTenantIdFromData(t),
              );
            } catch {
              roles = [];
            }
          }
          userToSave = this._applyTenantRolesToUser(userToSave, roles);
          if (t?.id) {
            await AsyncStorage.setItem("@appcheckin:tenant_id", String(t.id));
          }
          if (t?.slug) {
            await AsyncStorage.setItem("@appcheckin:tenant_slug", t.slug);
          }
          if (t?.nome) {
            await AsyncStorage.setItem("@appcheckin:tenant_nome", t.nome);
          }
        }

        await AsyncStorage.setItem(
          "@appcheckin:user",
          JSON.stringify(userToSave),
        );
      }

      return response.data;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
  },

  /**
   * Compat: manter assinatura antiga caso alguma tela use
   */
  async selectTenantInitial(userId, email, tenantId) {
    return this.selectTenantPublic(userId, email, tenantId);
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
        let userToSave = response.data.user;
        await AsyncStorage.setItem("@appcheckin:token", response.data.token);

        // Atualizar tenant atual
        if (response.data.tenant) {
          await AsyncStorage.setItem(
            "@appcheckin:current_tenant",
            JSON.stringify(response.data.tenant),
          );
          const t = response.data.tenant;
          let roles = this._getTenantRoles(
            response.data.tenants,
            this._getTenantIdFromData(t),
          );
          if (!roles.length) {
            try {
              const storedTenantsJson = await AsyncStorage.getItem(
                "@appcheckin:tenants",
              );
              const storedTenants = storedTenantsJson
                ? JSON.parse(storedTenantsJson)
                : [];
              roles = this._getTenantRoles(
                storedTenants,
                this._getTenantIdFromData(t),
              );
            } catch {
              roles = [];
            }
          }
          userToSave = this._applyTenantRolesToUser(userToSave, roles);
          if (t?.id) {
            await AsyncStorage.setItem("@appcheckin:tenant_id", String(t.id));
          }
          if (t?.slug) {
            await AsyncStorage.setItem("@appcheckin:tenant_slug", t.slug);
          }
          if (t?.nome) {
            await AsyncStorage.setItem("@appcheckin:tenant_nome", t.nome);
          }
        }

        await AsyncStorage.setItem(
          "@appcheckin:user",
          JSON.stringify(userToSave),
        );
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
    try {
      const response = await api.get("/auth/tenants");
      const list = response?.data?.tenants || response?.data || [];
      if (Array.isArray(list)) {
        await AsyncStorage.setItem(
          "@appcheckin:tenants",
          JSON.stringify(list),
        );
      }
      return list;
    } catch (error) {
      const errorData = error.response?.data || error;
      throw errorData;
    }
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
