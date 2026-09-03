import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_BASE_URL, API_V2_BASE_URL } from '../config/api';
import apiRouting from '../config/apiRouting';

const { shouldUseApiV2 } = apiRouting;

// Event emitter simples para notificar logout
let onUnauthorizedCallback = null;

export const setOnUnauthorized = (callback) => {
  onUnauthorizedCallback = callback;
};

const decodeJwtPayload = (token) => {
  try {
    const parts = token.split('.');
    if (parts.length !== 3) return null;
    const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
    const json = atob(base64);
    return JSON.parse(json);
  } catch (error) {
    return null;
  }
};

const isTokenExpired = (token) => {
  const payload = decodeJwtPayload(token);
  if (!payload || !payload.exp) return false;
  const now = Math.floor(Date.now() / 1000);
  return payload.exp <= now;
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
      if (isTokenExpired(token)) {
        console.warn('🚫 Token expirado - redirecionando para login...');
        await AsyncStorage.removeItem('@appcheckin:token');
        await AsyncStorage.removeItem('@appcheckin:user');
        if (onUnauthorizedCallback) {
          onUnauthorizedCallback();
        }
        return Promise.reject(new Error('Sessão expirada'));
      }
      config.headers.Authorization = `Bearer ${token}`;
      console.log('🔑 Token adicionado ao header');
    } else {
      console.warn('⚠️ Nenhum token encontrado');
    }
    const useV2 = shouldUseApiV2(config.url, config.method, config.data);
    config.baseURL = useV2 ? API_V2_BASE_URL : API_BASE_URL;
    console.log(`📡 ${config.method.toUpperCase()} ${config.baseURL}${config.url}${useV2 ? ' [v2]' : ' [slim]'}`);
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
