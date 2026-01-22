import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import AsyncStorage from "@/src/utils/storage";

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
      const API_URL = getApiUrlRuntime();

      // Buscar token do storage
      const token = await AsyncStorage.getItem("@appcheckin:token");

      // Debug: listar todas as chaves do storage
      const allKeys = await AsyncStorage.getAllKeys?.();
      console.log("🔍 Chaves no storage:", allKeys);

      // Montar headers
      const headers = {
        ...config.headers,
      };

      // Adicionar Content-Type apenas se não for FormData
      if (!(data instanceof FormData)) {
        headers["Content-Type"] = "application/json";
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
      }

      // Log da requisição
      console.log(`📡 ${method} ${API_URL}${endpoint}`);
      console.log("📋 Headers:", JSON.stringify(headers, null, 2));

      // Configurar requisição
      const fetchConfig = {
        method,
        headers,
      };

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
