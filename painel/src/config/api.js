const isProd = process.env.NODE_ENV === 'production';

/** Única base da API do painel: Laravel apiV2 (prefixo /v2). */
export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ||
  (isProd ? 'https://apiv2.appcheckin.com.br/v2' : 'http://localhost:9090/v2');

/** Alias histórico — mesmo valor que API_BASE_URL. */
export const API_V2_BASE_URL = API_BASE_URL;
