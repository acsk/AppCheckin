export const PRECO_POR_ALUNO = 3.5;
export const LIMITE_ALUNOS = 100;

const env = import.meta.env;

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
