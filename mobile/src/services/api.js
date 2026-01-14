import { API_URL } from '@/src/utils/apiConfig';
import AsyncStorage from '@/src/utils/storage';

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
    return this.request('GET', endpoint, null, config);
  },

  /**
   * Faz uma requisição POST
   */
  async post(endpoint, data = null, config = {}) {
    return this.request('POST', endpoint, data, config);
  },

  /**
   * Faz uma requisição PUT
   */
  async put(endpoint, data = null, config = {}) {
    return this.request('PUT', endpoint, data, config);
  },

  /**
   * Faz uma requisição DELETE
   */
  async delete(endpoint, config = {}) {
    return this.request('DELETE', endpoint, null, config);
  },

  /**
   * Método base para todas as requisições
   */
  async request(method, endpoint, data = null, config = {}) {
    try {
      // Buscar token do storage
      const token = await AsyncStorage.getItem('@appcheckin:token');
      
      // Montar headers
      const headers = {
        'Content-Type': 'application/json',
        ...config.headers,
      };
      
      // Adicionar token se existir
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
        console.log('🔑 Token adicionado ao header');
      } else {
        console.warn('⚠️ Nenhum token encontrado');
      }
      
      // Log da requisição
      console.log(`📡 ${method} ${API_URL}${endpoint}`);
      
      // Configurar requisição
      const fetchConfig = {
        method,
        headers,
      };
      
      // Adicionar body se houver dados
      if (data) {
        fetchConfig.body = JSON.stringify(data);
      }
      
      // Fazer requisição
      const response = await fetch(`${API_URL}${endpoint}`, fetchConfig);
      
      // Parsear resposta
      let responseData;
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        responseData = await response.json();
      } else {
        responseData = await response.text();
      }
      
      // Tratar erros de autenticação
      if (response.status === 401) {
        console.warn('🚫 Token inválido ou expirado - redirecionando para login...');
        await AsyncStorage.removeItem('@appcheckin:token');
        await AsyncStorage.removeItem('@appcheckin:user');
        
        // Notificar o app para redirecionar
        if (onUnauthorizedCallback) {
          onUnauthorizedCallback();
        }
        
        throw {
          response: {
            status: 401,
            data: responseData,
          },
        };
      }
      
      // Se não for sucesso, lançar erro
      if (!response.ok) {
        throw {
          response: {
            status: response.status,
            data: responseData,
          },
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
      console.error('❌ Erro na requisição:', error.message);
      throw {
        message: error.message || 'Erro de conexão',
        isNetworkError: true,
      };
    }
  },
};

export const API_BASE_URL = API_URL;
export default api;
