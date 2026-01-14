/**
 * Configuração Centralizada de URLs
 * Mude aqui uma única vez para todos os ambientes
 */

export const CONFIG = {
  // URLs da API por ambiente
  api: {
    development: 'http://localhost:8080',
    // localhost com porta customizada (ex: 3000, 5000, etc)
    // development: 'http://localhost:3000',
    
    production: 'https://api.appcheckin.com',
  },

  // Apps/Serviços opcionais
  services: {
    // Adicionar conforme necessário
    // uploadUrl: 'https://upload.appcheckin.com',
    // analyticsUrl: 'https://analytics.appcheckin.com',
  },

  // Configurações de timeout
  timeouts: {
    request: 30000,    // 30s
    connection: 15000, // 15s
  },

  // Configurações de retry
  retry: {
    maxAttempts: 3,
    delayMs: 1000,
  },

  // Endpoints que não precisam de autenticação
  publicEndpoints: [
    '/auth/login',
    '/auth/register',
    '/auth/select-tenant',
  ],

  // Headers padrão
  defaultHeaders: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
};

export default CONFIG;
