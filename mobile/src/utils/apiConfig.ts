/**
 * Configuração de API com suporte para web e ambientes
 * Usa URLs centralizadas do arquivo src/config/urls.ts
 */

import { CONFIG } from "@/src/config/urls";
import { APP_ENV, IS_SIMULATOR } from "@/src/config/runtimeEnvironment";
import { Platform } from "react-native";
import Constants from "expo-constants";

export { APP_ENV } from "@/src/config/runtimeEnvironment";

const isWeb = Platform.OS === "web";
const isAndroid = Platform.OS === "android";
const getEnvUrl = (): string | undefined => {
  const fromProcess =
    process.env.EXPO_PUBLIC_API_URL ||
    process.env.REACT_APP_API_URL ||
    process.env.VITE_API_URL;
  if (fromProcess) return fromProcess;

  const extra =
    (Constants.expoConfig as any)?.extra ||
    (Constants.manifest as any)?.extra ||
    (Constants as any)?.manifest2?.extra ||
    {};

  return (
    extra.EXPO_PUBLIC_API_URL ||
    extra.REACT_APP_API_URL ||
    extra.VITE_API_URL
  );
};

const normalizeLocalhostForAndroid = (url: string): string => {
  if (!isAndroid) return url;
  if (!url) return url;
  // Android emulator não resolve localhost do host; usar 10.0.2.2
  return url
    .replace("http://localhost", "http://10.0.2.2")
    .replace("http://127.0.0.1", "http://10.0.2.2")
    .replace("https://localhost", "https://10.0.2.2")
    .replace("https://127.0.0.1", "https://10.0.2.2");
};

const getHostFromExpo = (): string | null => {
  const rawHostUri =
    (Constants.expoConfig as any)?.hostUri ||
    (Constants.expoGoConfig as any)?.hostUri ||
    (Constants.expoGoConfig as any)?.debuggerHost ||
    (Constants.manifest as any)?.debuggerHost ||
    (Constants.manifest as any)?.hostUri ||
    (Constants as any)?.manifest2?.extra?.expoGo?.developer?.hostUri ||
    (Constants as any)?.linkingUri;

  if (!rawHostUri) return null;

  // rawHostUri pode vir como "192.168.1.100:8081" ou "exp://192.168.1.100:8081"
  const cleaned = String(rawHostUri).replace(/^[a-zA-Z]+:\/\//, "");
  const host = cleaned.split(":")[0];
  if (!host || host === "localhost") return null;
  return host;
};

const normalizeLocalhostForDevice = (url: string): string => {
  if (!url) return url;
  if (!url.includes("localhost") && !url.includes("127.0.0.1")) {
    return url;
  }

  const host = getHostFromExpo();
  if (!host) return url;
  return url
    .replace("http://localhost", `http://${host}`)
    .replace("http://127.0.0.1", `http://${host}`)
    .replace("https://localhost", `https://${host}`)
    .replace("https://127.0.0.1", `https://${host}`);
};

// Detectar URL base da API baseado no ambiente
const getApiUrl = (): string => {
  if (IS_SIMULATOR) return CONFIG.api.production;

  // 1️⃣ Primeiro, tentar usar variável de ambiente (para override)
  const envUrl = getEnvUrl();

  if (envUrl) {
    const normalized = normalizeLocalhostForAndroid(envUrl);
    return normalizeLocalhostForDevice(normalized);
  }

  // 2️⃣ Se não houver env, usar URLs centralizadas de config
  const appEnv = APP_ENV;
  const configUrl = CONFIG.api[appEnv as keyof typeof CONFIG.api];

  if (configUrl) {
    const normalized = normalizeLocalhostForAndroid(configUrl);
    return normalizeLocalhostForDevice(normalized);
  }

  // 3️⃣ Em web (produção): tentar usar o host atual ou fallback
  if (isWeb) {
    // Se está em produção web, usar API de produção por padrão
    return CONFIG.api.production;
  }

  // 4️⃣ Mobile: usar config de produção
  return CONFIG.api.production;
};

// Cache para evitar múltiplas chamadas
let cachedApiUrl: string | null = null;

// Export como função também para poder recalcular em runtime
export const getApiUrlRuntime = (): string => {
  if (IS_SIMULATOR) return CONFIG.api.production;

  // Retornar valor em cache se disponível
  if (cachedApiUrl) {
    // Se cache ainda está em localhost, tentar corrigir usando host do Expo
    if (
      (cachedApiUrl.includes("localhost") ||
        cachedApiUrl.includes("127.0.0.1")) &&
      getHostFromExpo()
    ) {
      cachedApiUrl = normalizeLocalhostForDevice(cachedApiUrl);
    }
    return cachedApiUrl;
  }

  // Em tempo de execução, se for web, retornar a URL configurada
  if (typeof window !== "undefined") {
    // Temos acesso ao window, estamos em execução web
    // Tentar: window.__APP_ENV__, EXPO_PUBLIC_APP_ENV ou usar "production" como fallback
    const appEnv =
      (window as any).__APP_ENV__ ||
      process.env.EXPO_PUBLIC_APP_ENV ||
      "production";

    const url = CONFIG.api[appEnv as keyof typeof CONFIG.api];

    if (url) {
      cachedApiUrl = url;
      return url;
    }
  }

  const url = getApiUrl();
  cachedApiUrl = url;
  if (__DEV__) {
  }
  return url;
};

// Para carregar imagens/assets
export const getAssetsUrl = (): string => {
  const appEnv = APP_ENV;
  const assetsConfig = (CONFIG as any).assets || {};
  return (
    assetsConfig[appEnv] ||
    CONFIG.api[appEnv as keyof typeof CONFIG.api] ||
    CONFIG.api.production
  );
};

export const getAssetsUrlRuntime = (): string => {
  const appEnv = APP_ENV;
  const assetsConfig = (CONFIG as any).assets || {};
  return (
    assetsConfig[appEnv] ||
    CONFIG.api[appEnv as keyof typeof CONFIG.api] ||
    CONFIG.api.production
  );
};

export const API_URL = getApiUrl();
export const ASSETS_URL = getAssetsUrl();
export const DEBUG_LOGS = process.env.EXPO_PUBLIC_DEBUG_LOGS === "true";

export default {
  getApiUrl,
  getApiUrlRuntime,
  getAssetsUrl,
  getAssetsUrlRuntime,
  API_URL,
  ASSETS_URL,
  APP_ENV,
  DEBUG_LOGS,
  isWeb,
  CONFIG,
};
