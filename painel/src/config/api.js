const isProd = process.env.NODE_ENV === 'production';

/** API Slim (legado). Módulos ainda não portados continuam aqui. */
export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ||
  (isProd ? 'https://api.appcheckin.com.br' : 'http://localhost:8080');

/** API v2 (Laravel). Inclui o prefixo /v2. */
export const API_V2_BASE_URL =
  process.env.EXPO_PUBLIC_API_V2_URL ||
  (isProd ? 'https://apiv2.appcheckin.com.br/v2' : 'http://localhost:9090/v2');
