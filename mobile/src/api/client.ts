import { getApiUrlRuntime } from "@/src/utils/apiConfig";
import AsyncStorage from "@/src/utils/storage";
import axios, { AxiosError, AxiosInstance, AxiosResponse, InternalAxiosRequestConfig } from "axios";

// Optional: notify app on 401 to redirect to login
let onUnauthorizedCallback: (() => void) | null = null;
export const setOnUnauthorized = (cb: () => void) => {
  onUnauthorizedCallback = cb;
};

const TOKEN_KEY = "@appcheckin:token";
const CURRENT_TENANT_KEY = "@appcheckin:current_tenant";
const TENANT_ID_KEY = "@appcheckin:tenant_id";
const TENANT_SLUG_KEY = "@appcheckin:tenant_slug";

async function getAuthHeaders(): Promise<Record<string, string>> {
  const headers: Record<string, string> = {};

  const token = await AsyncStorage.getItem(TOKEN_KEY);
  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  // Prefer persisted tenant_id; fallback to current_tenant JSON; last-resort tenant_slug
  let tenantId = await AsyncStorage.getItem(TENANT_ID_KEY);
  if (!tenantId) {
    try {
      const tenantJson = await AsyncStorage.getItem(CURRENT_TENANT_KEY);
      const tenant = tenantJson ? JSON.parse(tenantJson) : null;
      const id = tenant?.tenant?.id ?? tenant?.id;
      if (id) tenantId = String(id);
      const slug = tenant?.tenant?.slug ?? tenant?.slug;
      if (!tenantId && slug) headers["X-Tenant-Slug"] = String(slug);
    } catch {
      // ignore
    }
  }
  if (tenantId) headers["X-Tenant-Id"] = String(tenantId);

  return headers;
}

function isAuthEndpoint(url?: string): boolean {
  if (!url) return false;
  return (
    url.includes("/auth/login") ||
    url.includes("/auth/register-mobile") ||
    url.includes("/auth/select-tenant") ||
    url.includes("/auth/select-tenant-public")
  );
}

function normalizeError(error: AxiosError) {
  const status = error.response?.status ?? 0;
  const data: any = error.response?.data;
  const code = data?.code;
  const message = data?.message || data?.error || error.message;
  const normalized = {
    response: { status, data },
    code,
    message,
    isNetworkError: !error.response,
  };
  return normalized;
}

export const client: AxiosInstance = axios.create({
  baseURL: getApiUrlRuntime(),
  timeout: 20000,
});

client.interceptors.request.use(
  async (config: InternalAxiosRequestConfig) => {
    const dynamicHeaders = await getAuthHeaders();
    const token = await AsyncStorage.getItem(TOKEN_KEY);
    const shouldSkipAuth = Boolean((config as any)?.skipAuth);
    const headers = {
      ...(config.headers || {}),
      ...dynamicHeaders,
    } as any;
    config.headers = headers;

    if (!token && !isAuthEndpoint(config.url) && !shouldSkipAuth) {
      if (onUnauthorizedCallback) onUnauthorizedCallback();
      return Promise.reject({
        response: {
          status: 401,
          data: { code: "TOKEN_MISSING", message: "Token não encontrado" },
        },
        code: "TOKEN_MISSING",
        message: "Token não encontrado",
        isNetworkError: false,
      });
    }

    if (typeof window !== "undefined") {
      headers["Cache-Control"] = "no-cache, no-store, must-revalidate";
      headers.Pragma = "no-cache";
      headers.Expires = "0";
    }

    // Ensure Content-Type only for requests with body (avoid adding on GET)
    const method = (config.method || "GET").toUpperCase();
    const hasBody = ["POST", "PUT", "PATCH", "DELETE"].includes(method);
    const isFormData = hasBody && config.data && (typeof FormData !== "undefined") && config.data instanceof FormData;
    if (hasBody && !isFormData) {
      headers["Content-Type"] = headers["Content-Type"] || "application/json";
    }

    return config;
  },
  (error) => Promise.reject(error),
);

client.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error: AxiosError) => {
    const status = error.response?.status;
    const data: any = error.response?.data;
    const code = data?.code;

    if (status === 401) {
      // Clear token and notify app to redirect to login
      await AsyncStorage.removeItem(TOKEN_KEY);
      await AsyncStorage.removeItem("@appcheckin:user");
      if (onUnauthorizedCallback) onUnauthorizedCallback();
      return Promise.reject(normalizeError(error));
    }

    if (status === 400 && code === "MISSING_TENANT") {
      // Suggest tenant selection flow
      return Promise.reject(normalizeError(error));
    }

    if (status === 403 && (code === "TENANT_ACCESS_DENIED" || code === "NO_ACTIVE_CONTRACT")) {
      // Block access for this tenant with friendly UI
      return Promise.reject(normalizeError(error));
    }

    // Other errors
    return Promise.reject(normalizeError(error));
  },
);

// Helpers to persist auth/tenant (use SecureStorage/Keychain if needed)
export async function setAuthToken(token: string) {
  await AsyncStorage.setItem(TOKEN_KEY, token);
}

export async function setCurrentTenant(tenant: any) {
  await AsyncStorage.setItem(CURRENT_TENANT_KEY, JSON.stringify(tenant));
  const id = tenant?.id ?? tenant?.tenant?.id;
  const slug = tenant?.slug ?? tenant?.tenant?.slug;
  if (id) await AsyncStorage.setItem(TENANT_ID_KEY, String(id));
  if (slug) await AsyncStorage.setItem(TENANT_SLUG_KEY, String(slug));
}

export async function getCurrentTenantId(): Promise<string | null> {
  const id = await AsyncStorage.getItem(TENANT_ID_KEY);
  if (id) return String(id);
  try {
    const tenantJson = await AsyncStorage.getItem(CURRENT_TENANT_KEY);
    const tenant = tenantJson ? JSON.parse(tenantJson) : null;
    const tid = tenant?.id ?? tenant?.tenant?.id;
    return tid ? String(tid) : null;
  } catch {
    return null;
  }
}

export default client;
