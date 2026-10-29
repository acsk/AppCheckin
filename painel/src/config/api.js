const isProd = process.env.NODE_ENV === 'production';

/** API Slim (legado — módulos ainda não portados). */
export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ||
  (isProd ? 'https://api.appcheckin.com.br' : 'http://localhost:8080');

/** App web do aluno (mobile exportado para web): "Ver app como aluno". */
export const MOBILE_WEB_URL =
  process.env.EXPO_PUBLIC_MOBILE_WEB_URL ||
  (isProd ? 'https://mobile.appcheckin.com.br' : 'http://localhost:8081');

/** API v2 (Laravel). */
export const API_V2_BASE_URL =
  process.env.EXPO_PUBLIC_API_V2_URL ||
  (isProd ? 'https://apiv2.appcheckin.com.br/v2' : 'http://localhost:9090/v2');
