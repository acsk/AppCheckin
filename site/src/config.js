const env = import.meta.env;

export const API_URL = (env.VITE_API_URL || (env.PROD ? 'https://apiv2.appcheckin.com.br/v2' : 'http://localhost:9090/v2')).replace(/\/$/, '');

export const LINKS = {
  entrar: env.VITE_LINK_ENTRAR || '#contato',
  demonstracao: env.VITE_LINK_DEMONSTRACAO || '#contato',
  whatsapp: env.VITE_LINK_WHATSAPP || '#contato',
  termos: env.VITE_LINK_TERMOS || '#',
  privacidade: env.VITE_LINK_PRIVACIDADE || '#',
};

export function brl(valor) {
  return 'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
