import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_BASE_URL, API_V2_BASE_URL } from '../config/api';
import apiRouting from '../config/apiRouting';

const { shouldUseApiV2 } = apiRouting;

let onUnauthorizedCallback = null;
export const setOnUnauthorized = (callback) => {
  onUnauthorizedCallback = callback;
};

const decodeJwtPayload = (token) => {
  try {
    const parts = token.split('.');
    if (parts.length !== 3) return null;
    const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
    return JSON.parse(atob(base64));
  } catch {
    return null;
  }
};

const isTokenExpired = (token) => {
  const payload = decodeJwtPayload(token);
  if (!payload || !payload.exp) return false;
  return payload.exp <= Math.floor(Date.now() / 1000);
};

const api = axios.create({
  baseURL: API_V2_BASE_URL,
  headers: { 'Content-Type': 'application/json' },
});

api.interceptors.request.use(
  async (config) => {
    const token = await AsyncStorage.getItem('@appcheckin:token');
    if (token) {
      if (isTokenExpired(token)) {
        console.warn('🚫 Token expirado');
        await AsyncStorage.removeItem('@appcheckin:token');
        await AsyncStorage.removeItem('@appcheckin:user');
        if (onUnauthorizedCallback) onUnauthorizedCallback();
        return Promise.reject(new Error('Sessão expirada'));
      }
      config.headers.Authorization = `Bearer ${token}`;
    }
    const useV2 = shouldUseApiV2(config.url, config.method, config.data);
    config.baseURL = useV2 ? API_V2_BASE_URL : API_BASE_URL;
    console.log(`📡 ${config.method.toUpperCase()} ${config.baseURL}${config.url}${useV2 ? ' [v2]' : ' [slim]'}`);
    return config;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      await AsyncStorage.removeItem('@appcheckin:token');
      await AsyncStorage.removeItem('@appcheckin:user');
      if (onUnauthorizedCallback) onUnauthorizedCallback();
    }
    return Promise.reject(error);
  }
);

export const apiCall = async (method, url, data = null) => {
  try {
    const response = await api({ method, url, ...(data ? { data } : {}) });
    return response.data;
  } catch (error) {
    if (error.response?.data) return error.response.data;
    return { type: 'error', message: error.message || 'Erro na requisição', error };
  }
};

export default api;
