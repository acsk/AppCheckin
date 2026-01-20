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

  // 3️⃣ Fallback: se está em desenvolvimento local
  if (isWeb && process.env.NODE_ENV === "development") {
    console.log("📡 API URL (fallback dev):", CONFIG.api.development);
    return CONFIG.api.development;
  }

  // 4️⃣ Produção web: usar config de produção
  if (isWeb) {
    console.log("📡 API URL (fallback prod):", CONFIG.api.production);
    return CONFIG.api.production;
  }

  // 5️⃣ Mobile: usar config
  console.log("📡 API URL (mobile):", CONFIG.api.production);
  return CONFIG.api.production;
};

export const API_URL = getApiUrl();
export const APP_ENV = process.env.EXPO_PUBLIC_APP_ENV || "development";
export const DEBUG_LOGS = process.env.EXPO_PUBLIC_DEBUG_LOGS === "true";

export default {
  getApiUrl,
  API_URL,
  APP_ENV,
  DEBUG_LOGS,
  isWeb,
  CONFIG,
};
