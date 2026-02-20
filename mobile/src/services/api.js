import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import AsyncStorage from "@/src/utils/storage";
import Constants from "expo-constants";

// Callback para notificar logout quando token é inválido
let onUnauthorizedCallback = null;

export const setOnUnauthorized = (callback) => {
  onUnauthorizedCallback = callback;
};

/**
 * Cliente HTTP customizado para fazer requisições à API
 * Similar ao axios mas usando fetch nativo
 */
const api = {
  /**
   * Faz uma requisição GET
   */
  async get(endpoint, config = {}) {
    return this.request("GET", endpoint, null, config);
  },

  /**
   * Faz uma requisição POST
   */
  async post(endpoint, data = null, config = {}) {
    return this.request("POST", endpoint, data, config);
  },

  /**
   * Faz uma requisição PUT
   */
  async put(endpoint, data = null, config = {}) {
    return this.request("PUT", endpoint, data, config);
  },

  /**
   * Faz uma requisição DELETE
   */
  async delete(endpoint, config = {}) {
    return this.request("DELETE", endpoint, null, config);
  },

  /**
   * Método base para todas as requisições
   */
  async request(method, endpoint, data = null, config = {}) {
    try {
      // Obter URL da API em tempo de execução
      let API_URL = getApiUrlRuntime();
      // Fallback: em device físico, substituir localhost pelo host do Expo
      if (API_URL.includes("localhost") || API_URL.includes("127.0.0.1")) {
        const rawHostUri =
          Constants.expoConfig?.hostUri ||
          Constants.expoGoConfig?.hostUri ||
          Constants.expoGoConfig?.debuggerHost ||
          Constants.manifest?.debuggerHost ||
          Constants.manifest?.hostUri ||
          Constants.manifest2?.extra?.expoGo?.developer?.hostUri ||
          Constants.linkingUri;
        if (rawHostUri) {
          const cleaned = String(rawHostUri).replace(/^[a-zA-Z]+:\/\//, "");
          const host = cleaned.split(":")[0];
          if (host && host !== "localhost") {
            API_URL = API_URL
              .replace("http://localhost", `http://${host}`)
              .replace("http://127.0.0.1", `http://${host}`)
              .replace("https://localhost", `https://${host}`)
              .replace("https://127.0.0.1", `https://${host}`);
          }
        }
      }
      if (__DEV__) {
        console.log("🌐 API_URL (api.js):", API_URL);
      }

      // Buscar token do storage
      const token = await AsyncStorage.getItem("@appcheckin:token");
      const shouldSkipAuth = Boolean(config?.skipAuth);
      const isAuthEndpoint =
        endpoint === "/auth/login" ||
        endpoint === "/auth/register-mobile" ||
        endpoint === "/auth/select-tenant" ||
        endpoint === "/auth/select-tenant-public";

      // Debug: listar todas as chaves do storage
      const allKeys = await AsyncStorage.getAllKeys?.();
      console.log("🔍 Chaves no storage:", allKeys);

      // Montar headers base
      // Atenção: não injeta X-Tenant-* por padrão.
      // O backend usa o tenant do JWT. Caso alguma rota específica exija,
      // passe explicitamente via config.headers.
      const headers = {
        ...config.headers,
      };

      if (typeof window !== "undefined") {
        headers["Cache-Control"] = "no-cache, no-store, must-revalidate";
        headers.Pragma = "no-cache";
        headers.Expires = "0";
      }

      // Content-Type:
      // - Não enviar em GET/DELETE para evitar preflight CORS
      // - Enviar apenas em métodos com corpo (POST/PUT/PATCH) quando não for FormData
      const methodHasBody = ["POST", "PUT", "PATCH"].includes(method);
      const isFormData = data instanceof FormData;
      if (methodHasBody && !isFormData) {
        // Só define se o chamador não definiu manualmente
        if (!headers["Content-Type"]) {
          headers["Content-Type"] = "application/json";
        }
      } else {
        // Garante remoção de Content-Type em GET/DELETE
        if (headers["Content-Type"]) {
          delete headers["Content-Type"];
        }
      }

      // Adicionar token se existir
      if (token) {
        headers["Authorization"] = `Bearer ${token}`;
        console.log(
          "🔑 Token adicionado ao header:",
          token.substring(0, 20) + "...",
        );
      } else {
        console.warn(
          "⚠️ Nenhum token encontrado em storage (@appcheckin:token)",
        );
        console.warn("⚠️ Você precisa fazer login primeiro!");
        if (!isAuthEndpoint && !shouldSkipAuth && onUnauthorizedCallback) {
          onUnauthorizedCallback();
        }
        if (!isAuthEndpoint && !shouldSkipAuth) {
          throw {
            response: {
              status: 401,
              data: { code: "TOKEN_MISSING", message: "Token não encontrado" },
            },
            message: "Token não encontrado",
            code: "TOKEN_MISSING",
          };
        }
      }

      // Log da requisição
      console.log(`📡 ${method} ${API_URL}${endpoint}`);
      console.log("📋 Headers:", JSON.stringify(headers, null, 2));

      // Configurar requisição
      const fetchConfig = {
        method,
        headers,
      };

      if (typeof window !== "undefined") {
        fetchConfig.cache = "no-store";
      }

      // Adicionar body se houver dados
      if (data) {
        if (data instanceof FormData) {
          // Para FormData, não fazer JSON.stringify
          fetchConfig.body = data;
        } else {
          // Para outros dados, fazer JSON.stringify
          fetchConfig.body = JSON.stringify(data);
        }
      }

      // Fazer requisição
      const response = await fetch(`${API_URL}${endpoint}`, fetchConfig);

      // Parsear resposta
      let responseData;
      const contentType = response.headers.get("content-type");
      if (contentType && contentType.includes("application/json")) {
        let responseText = await response.text();

        // Limpar warnings/notices do PHP que podem vir antes do JSON
        // Procura por { para encontrar o início do JSON
        const jsonStart = responseText.indexOf("{");
        if (jsonStart > 0) {
          responseText = responseText.substring(jsonStart);
        }

        responseData = JSON.parse(responseText);
      } else {
        responseData = await response.text();
      }

      // Tratar erros de autenticação
      if (response.status === 401) {
        // Extrair mensagem de erro do backend se disponível
        const errorMessage =
          responseData?.message ||
          responseData?.error ||
          "Acesso não autorizado";
        const errorCode = responseData?.code;

        // Verificar se é erro de login (endpoint /auth/login)
        // Se for, não chamar o callback, deixar a tela de login tratar
        const isLoginEndpoint = endpoint === "/auth/login";

        if (!isLoginEndpoint) {
          // Para outros endpoints, limpar storage e notificar
          console.warn(
            "🚫 Token inválido ou expirado - redirecionando para login...",
          );
          await AsyncStorage.removeItem("@appcheckin:token");
          await AsyncStorage.removeItem("@appcheckin:user");

          if (onUnauthorizedCallback) {
            onUnauthorizedCallback();
          }
        } else {
          // Para login, apenas remover dados se existirem
          await AsyncStorage.removeItem("@appcheckin:token");
          await AsyncStorage.removeItem("@appcheckin:user");
        }

        throw {
          response: {
            status: 401,
            data: responseData,
          },
          message: errorMessage,
          code: errorCode,
        };
      }

      // Tratar erros de contrato inativo (403)
      if (response.status === 403) {
        const errorMessage =
          responseData?.message || responseData?.error || "Acesso negado";
        const errorCode = responseData?.code;
        throw {
          response: {
            status: 403,
            data: responseData,
          },
          message: errorMessage,
          code: errorCode,
        };
      }

      // Se não for sucesso, lançar erro
      if (!response.ok) {
        const errorMessage =
          responseData?.message ||
          responseData?.error ||
          `Erro HTTP ${response.status}`;
        const errorCode = responseData?.code;

        throw {
          response: {
            status: response.status,
            data: responseData,
          },
          message: errorMessage,
          code: errorCode,
        };
      }

      // Retornar no formato esperado (similar ao axios)
      return {
        data: responseData,
        status: response.status,
        ok: response.ok,
      };
    } catch (error) {
      // Se já é um erro formatado, re-lançar
      if (error.response) {
        throw error;
      }

      // Erro de rede ou outro
      console.error("❌ Erro na requisição:", error.message);
      throw {
        message: error.message || "Erro de conexão",
        isNetworkError: true,
      };
    }
  },
};

export const API_BASE_URL = getApiUrlRuntime();
export default api;
