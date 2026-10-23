const env = import.meta.env;

export const API_URL = (env.VITE_API_URL || (env.PROD ? 'https://apiv2.appcheckin.com.br/v2' : 'http://localhost:9090/v2')).replace(/\/$/, '');

export const PAINEL_URL = (env.VITE_PAINEL_URL || 'https://painel.appcheckin.com.br').replace(/\/$/, '');

export const LINKS = {
  // Sem .env o padrão era '#contato': no fim do cadastro a âncora não existe e o link não fazia nada
  entrar: env.VITE_LINK_ENTRAR || `${PAINEL_URL}/login`,
  demonstracao: env.VITE_LINK_DEMONSTRACAO || '#contato',
  whatsapp: env.VITE_LINK_WHATSAPP || '#contato',
  termos: env.VITE_LINK_TERMOS || '#',
  privacidade: env.VITE_LINK_PRIVACIDADE || '#',
};

export function brl(valor) {
  return 'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
