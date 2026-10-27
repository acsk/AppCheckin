// Rótulos da tela Financeiro → Recorrências.

export const STATUS = {
  pendente_ativacao: ['outline', 'Pendente de ativação'],
  ativo: ['success', 'Ativo'],
  pagamento_pendente: ['warning', 'Pagamento pendente'],
  inadimplente: ['danger', 'Inadimplente'],
  cancelamento_solicitado: ['warning', 'Cancelamento solicitado'],
  cancelamento_agendado: ['info', 'Cancelamento agendado'],
  encerrado: ['neutral', 'Encerrado'],
  cancelado: ['neutral', 'Cancelado'],
};

export const COBRANCA = {
  paga: ['success', 'Paga'],
  pendente: ['outline', 'Em aberto'],
  vencida: ['danger', 'Vencida'],
  estornada: ['warning', 'Estornada'],
  cancelada: ['neutral', 'Cancelada'],
  prevista: ['neutral', 'Prevista'],
};

export const RENOVACAO = {
  encerrar: 'a assinatura se encerra.',
  mensal: 'continua mensal pelo valor de referência, sem prazo mínimo.',
  promocional: 'continua pelo mesmo valor, sem prazo mínimo.',
  nova_fidelizacao: 'oferece nova fidelização; só começa com o aceite do aluno.',
};

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
export const brl = (v) => (v == null || v === '' ? '—' : BRL.format(Number(v) || 0));
export const dataBr = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—');
export const msg = (err, fallback) => err?.mensagemLimpa || err?.error || err?.message || fallback;
