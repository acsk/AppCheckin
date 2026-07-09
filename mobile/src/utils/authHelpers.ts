import AsyncStorage from './storage';
import { notifySessionExpired } from './sessionExpired';

const AUTH_STORAGE_KEYS = [
  '@appcheckin:token',
  '@appcheckin:user',
  '@appcheckin:tenants',
  '@appcheckin:tenant',
  '@appcheckin:current_tenant',
  '@appcheckin:tenant_id',
  '@appcheckin:tenant_slug',
];

/**
 * Limpa dados de autenticação do storage.
 */
export const clearAuthStorage = async (): Promise<void> => {
  await Promise.all(
    AUTH_STORAGE_KEYS.map((key) => AsyncStorage.removeItem(key)),
  );
};

/**
 * Trata erros de autenticação (401): limpa storage e abre a modal global.
 * Não redireciona — o usuário confirma na modal e vai para o login.
 */
export const handleAuthError = async (message?: string): Promise<void> => {
  console.log('🔑 Tratando erro de autenticação - Token inválido/expirado');
  await clearAuthStorage();
  notifySessionExpired(message);
};

/**
 * Se a Response for 401, abre a modal de sessão expirada.
 * Retorna true quando tratou (caller deve dar return).
 */
export const handleUnauthorizedResponse = async (
  response: { status: number },
  message?: string,
): Promise<boolean> => {
  if (response?.status !== 401) {
    return false;
  }
  await handleAuthError(message);
  return true;
};

/**
 * Verifica se um erro é um erro de autenticação (401)
 */
export const isAuthError = (error: any): boolean => {
  return (
    error?.response?.status === 401 ||
    error?.status === 401 ||
    (typeof error?.message === 'string' && error.message.includes('401'))
  );
};

/**
 * Extrai mensagem de erro de diferentes formatos de resposta
 */
export const extractErrorMessage = (error: any): string => {
  if (error?.response?.data?.error) {
    return error.response.data.error;
  }

  if (error?.response?.data?.message) {
    return error.response.data.message;
  }

  if (error?.data?.error) {
    return error.data.error;
  }

  if (error?.data?.message) {
    return error.data.message;
  }

  if (error?.message) {
    return error.message;
  }

  return 'Erro desconhecido';
};

/**
 * Trata erros de requisição de forma padronizada
 */
export const handleRequestError = async (
  error: any,
  defaultMessage: string = 'Erro ao processar requisição',
): Promise<string> => {
  console.error('🔴 Erro na requisição:', error);

  if (isAuthError(error)) {
    await handleAuthError();
    return 'Sessão expirada';
  }

  const errorMessage = extractErrorMessage(error);
  return errorMessage || defaultMessage;
};

/**
 * Decodifica um JWT e retorna o payload.
 * Suporta Base64 URL-safe.
 */
export const decodeJwtPayload = (token?: string): any | null => {
  if (!token) return null;
  try {
    const parts = token.split('.');
    if (parts.length < 2) return null;
    const base64Url = parts[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(
      atob(base64)
        .split('')
        .map((c) => `%${('00' + c.charCodeAt(0).toString(16)).slice(-2)}`)
        .join(''),
    );
    return JSON.parse(jsonPayload);
  } catch (e) {
    console.warn('⚠️ Falha ao decodificar JWT:', e);
    return null;
  }
};

/**
 * Obtém o payload do token armazenado.
 */
export const getTokenPayload = async (): Promise<any | null> => {
  const token = await AsyncStorage.getItem('@appcheckin:token');
  return decodeJwtPayload(token || undefined);
};

/**
 * Obtém o tenant_id presente no token atual.
 */
export const getTokenTenantId = async (): Promise<number | null> => {
  const payload = await getTokenPayload();
  const tid = payload?.tenant_id ?? payload?.tenantId;
  return typeof tid === 'number' ? tid : tid ? Number(tid) : null;
};
