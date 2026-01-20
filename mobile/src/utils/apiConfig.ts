/**
 * Configuração de API com suporte para web e ambientes
 * Usa URLs centralizadas do arquivo src/config/urls.ts
 */

import CONFIG from "@/src/config/urls";
import { Platform } from "react-native";

const isWeb = Platform.OS === "web";

// Detectar URL base da API baseado no ambiente
const getApiUrl = (): string => {
  // 1️⃣ Primeiro, tentar usar variável de ambiente (para override)
  const envUrl =
    process.env.EXPO_PUBLIC_API_URL ||
    process.env.REACT_APP_API_URL ||
    process.env.VITE_API_URL;

  if (envUrl) {
    console.log("📡 API URL (from env):", envUrl);
    return envUrl;
  }

  // 2️⃣ Se não houver env, usar URLs centralizadas de config
  const appEnv = process.env.EXPO_PUBLIC_APP_ENV || "development";
  const configUrl = CONFIG.api[appEnv as keyof typeof CONFIG.api];

  if (configUrl) {
    console.log(`📡 API URL (${appEnv}):`, configUrl);
    return configUrl;
  }

  // 3️⃣ Em web (produção): tentar usar o host atual ou fallback
  if (isWeb) {
    // Se está em produção web, usar API de produção por padrão
    console.log("📡 API URL (web):", CONFIG.api.production);
    return CONFIG.api.production;
  }

  // 4️⃣ Mobile: usar config de produção
  console.log("📡 API URL (mobile):", CONFIG.api.production);
  return CONFIG.api.production;
};

// Export como função também para poder recalcular em runtime
export const getApiUrlRuntime = (): string => {
  // Em tempo de execução, se for web, retornar a URL configurada
  if (typeof window !== "undefined") {
    // Temos acesso ao window, estamos em execução web
    const appEnv = (window as any).__APP_ENV__ || "production";
    const url = CONFIG.api[appEnv as keyof typeof CONFIG.api];

    if (url) {
      console.log(`📡 API URL (runtime ${appEnv}):`, url);
      return url;
    }
  }

  return getApiUrl();
};

export const API_URL = getApiUrl();
export const APP_ENV = process.env.EXPO_PUBLIC_APP_ENV || "development";
export const DEBUG_LOGS = process.env.EXPO_PUBLIC_DEBUG_LOGS === "true";

export default {
  getApiUrl,
  getApiUrlRuntime,
  API_URL,
  APP_ENV,
  DEBUG_LOGS,
  isWeb,
  CONFIG,
};
