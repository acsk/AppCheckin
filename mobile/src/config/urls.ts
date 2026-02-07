/**
 * Configuração Centralizada de URLs
 * Mude aqui uma única vez para todos os ambientes
 */

export const CONFIG = {
  // URLs da API por ambiente
  api: {
    development: "http://localhost:8080",
    // localhost com porta customizada (ex: 3000, 5000, etc)
    // development: 'http://localhost:3000',

    production: "https://api.appcheckin.com.br",
  },

  // URLs para servir imagens/assets
  assets: {
    development: "http://localhost:8080",
    production: "https://mobile.appcheckin.com.br",
  },

  // Apps/Serviços opcionais
  services: {
    // Adicionar conforme necessário
    // uploadUrl: 'https://upload.appcheckin.com',
    // analyticsUrl: 'https://analytics.appcheckin.com',
  },

  // Configurações do reCAPTCHA
  recaptcha: {
    siteKey: "6Lc4QI8sAAAAAH-aVJ28-3pG93k3wy2Kl7Eh8Xv9",
    // A secret key é usada apenas no backend
    // secretKey: "6Lc4QI8sAAAAI2zP1WqSTf8WqWFHO7dY6EvQd4-"
  },

  // Configurações de timeout
  timeouts: {
    request: 30000, // 30s
    connection: 15000, // 15s
  },

  // Configurações de retry
  retry: {
    maxAttempts: 3,
    delayMs: 1000,
  },

  // Endpoints que não precisam de autenticação
  publicEndpoints: [
    "/auth/login",
    "/auth/register",
    "/auth/register-mobile",
    "/auth/select-tenant",
  ],

  // Headers padrão
  defaultHeaders: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
};

// Função para obter a URL da API em tempo de execução
export function getApiUrlRuntime(): string {
  // Determina o ambiente baseado na URL atual
  const isProduction =
    typeof window !== "undefined" &&
    window.location?.hostname &&
    !window.location.hostname.includes("localhost");

  return isProduction ? CONFIG.api.production : CONFIG.api.development;
}

export default CONFIG;
