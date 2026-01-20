export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ||
  (process.env.NODE_ENV === 'production'
    ? 'https://api.appcheckin.com.br'
    : 'http://localhost:8080');
