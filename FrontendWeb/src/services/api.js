import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_BASE_URL } from '../config/api';

// Event emitter simples para notificar logout
let onUnauthorizedCallback = null;

export const setOnUnauthorized = (callback) => {
  onUnauthorizedCallback = callback;
};

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor para adicionar token em todas as requisições
api.interceptors.request.use(
  async (config) => {
    const token = await AsyncStorage.getItem('@appcheckin:token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
      console.log('🔑 Token adicionado ao header');
    } else {
      console.warn('⚠️ Nenhum token encontrado');
    }
    console.log(`📡 ${config.method.toUpperCase()} ${config.baseURL}${config.url}`);
    return config;
  },
  (error) => {
    console.error('❌ Erro no interceptor de request:', error);
    return Promise.reject(error);
  }
);

// Interceptor para tratar erros de autenticação
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      console.warn('🚫 Token inválido ou expirado - redirecionando para login...');
      await AsyncStorage.removeItem('@appcheckin:token');
      await AsyncStorage.removeItem('@appcheckin:user');
      
      // Notificar o app para redirecionar
      if (onUnauthorizedCallback) {
        onUnauthorizedCallback();
      }
    }
    return Promise.reject(error);
  }
);

/**
 * Faz uma chamada de API genérica
 * @param {string} method - GET, POST, PUT, DELETE, PATCH
 * @param {string} url - URL relativa (ex: /admin/wods)
 * @param {object} data - Dados a enviar (para POST, PUT, PATCH)
 */
export const apiCall = async (method, url, data = null) => {
  try {
    const config = {
      method,
      url,
    };

    if (data) {
      config.data = data;
    }

    const response = await api(config);
    return response.data;
  } catch (error) {
    // Retornar objeto de erro padronizado
    if (error.response?.data) {
      return error.response.data;
    }
    
    return {
      type: 'error',
      message: error.message || 'Erro na requisição',
      error,
    };
  }
};

export default api;
