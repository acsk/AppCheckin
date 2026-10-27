// Rótulos e conversões da Central de Comunicação (painel).

export const TIPOS = [
  { value: 'notificacao', label: 'Notificação', icon: 'bell' },
  { value: 'comunicado', label: 'Comunicado', icon: 'bullhorn' },
  { value: 'mensagem', label: 'Mensagem', icon: 'message-text' },
  { value: 'enquete', label: 'Enquete', icon: 'poll' },
];
export const TIPO = Object.fromEntries(TIPOS.map(t => [t.value, t]));

export const STATUS = {
  rascunho: ['outline', 'Rascunho'],
  agendada: ['info', 'Agendada'],
  ativa: ['success', 'Ativa'],
  encerrada: ['neutral', 'Encerrada'],
  cancelada: ['danger', 'Cancelada'],
};

export const CLASSIFICACOES = [
  { value: 'info', label: 'Informação' },
  { value: 'alerta', label: 'Alerta' },
  { value: 'sucesso', label: 'Sucesso' },
  { value: 'urgente', label: 'Urgente' },
];

export const PRIORIDADES = [
  { value: '0', label: 'Baixa' },
  { value: '1', label: 'Normal' },
  { value: '2', label: 'Alta' },
  { value: '3', label: 'Urgente' },
];

export const PUBLICOS = [
  { value: 'todos', label: 'Todos os alunos' },
  { value: 'ativos', label: 'Matrícula ativa' },
  { value: 'segmento', label: 'Segmento' },
  { value: 'alunos', label: 'Alunos escolhidos' },
];

export const TIPOS_RESPOSTA = [
  { value: 'unica', label: 'Escolha única' },
  { value: 'multipla', label: 'Múltipla escolha' },
  { value: 'sim_nao', label: 'Sim ou não' },
  { value: 'avaliacao', label: 'Avaliação de 1 a 5' },
  { value: 'aberta', label: 'Resposta aberta' },
];

export const msg = (err, fallback) => err?.mensagemLimpa || err?.error || err?.message || fallback;

/** "2026-11-01 08:00:00" → "01/11/2026 08:00" */
export function fdatetime(v) {
  if (!v) return '—';
  const [d, t = ''] = String(v).replace('T', ' ').split(' ');
  const [y, m, dd] = d.split('-');
  return `${dd}/${m}/${y}${t ? ' ' + t.slice(0, 5) : ''}`;
}

/** API ("Y-m-d H:i:s") ↔ <input type="datetime-local"> ("Y-m-dTH:i") */
export const toInput = v => (v ? String(v).replace(' ', 'T').slice(0, 16) : '');
export const fromInput = v => (v ? v.replace('T', ' ') : null);

export function publicoResumo(p) {
  if (!p || p.tipo === 'todos') return 'Todos os alunos';
  if (p.tipo === 'ativos') return 'Matrícula ativa';
  if (p.tipo === 'alunos') {
    const n = (p.alunos || []).length + (p.matriculas || []).length;
    return n === 1 ? '1 aluno escolhido' : `${n} alunos escolhidos`;
  }
  const partes = [];
  if (p.modalidades?.length) partes.push(p.modalidades.length === 1 ? '1 modalidade' : `${p.modalidades.length} modalidades`);
  if (p.planos?.length) partes.push(p.planos.length === 1 ? '1 plano' : `${p.planos.length} planos`);
  if (p.horarios?.length) partes.push(p.horarios.length === 1 ? '1 turma' : `${p.horarios.length} turmas`);
  if (p.inadimplentes) partes.push('inadimplentes');
  return partes.join(' · ') || 'Segmento';
}

export const vazia = () => ({
  id: null,
  status: 'rascunho',
  tipo: 'comunicado',
  titulo: '',
  descricao: '',
  conteudo: '',
  icone: '',
  classificacao: 'info',
  prioridade: '1',
  obrigatoria: false,
  exibir_modal: false,
  manter_historico: true,
  inicio_em: '',
  termino_em: '',
  publico: { tipo: 'todos', modalidades: [], planos: [], horarios: [], dias_checkin: 30, inadimplentes: false, alunos: [], matriculas: [] },
  configuracoes: { links: [], anexos: [], imagem_url: '', enquete: { anonima: false, permitir_alterar: false, exibir_resultados: false, exibir_participantes: false } },
  perguntas: [],
});

/** Resposta da API → estado do formulário. */
export function paraForm(c) {
  const base = vazia();
  return {
    ...base,
    ...c,
    prioridade: String(c.prioridade ?? 1),
    descricao: c.descricao || '',
    conteudo: c.conteudo || '',
    icone: c.icone || '',
    inicio_em: toInput(c.inicio_em),
    termino_em: toInput(c.termino_em),
    publico: { ...base.publico, ...(c.publico || {}) },
    configuracoes: {
      ...base.configuracoes,
      ...(c.configuracoes || {}),
      imagem_url: c.configuracoes?.imagem_url || '',
      enquete: { ...base.configuracoes.enquete, ...(c.configuracoes?.enquete || {}) },
    },
    perguntas: (c.perguntas || []).map(p => ({ ...p, alternativas: (p.alternativas || []).map(a => a.texto) })),
  };
}

/** Estado do formulário → corpo da API. */
export function paraApi(f) {
  return {
    tipo: f.tipo,
    titulo: f.titulo.trim(),
    descricao: f.descricao.trim() || null,
    conteudo: f.conteudo.trim() || null,
    icone: f.icone || null,
    classificacao: f.classificacao,
    prioridade: +f.prioridade,
    obrigatoria: f.obrigatoria,
    exibir_modal: f.exibir_modal,
    manter_historico: f.manter_historico,
    inicio_em: fromInput(f.inicio_em),
    termino_em: fromInput(f.termino_em),
    publico: f.publico,
    configuracoes: { ...f.configuracoes, imagem_url: f.configuracoes.imagem_url || null },
    perguntas: f.tipo === 'enquete' ? f.perguntas.map(p => ({
      pergunta: p.pergunta,
      tipo_resposta: p.tipo_resposta,
      obrigatoria: p.obrigatoria !== false,
      alternativas: ['unica', 'multipla'].includes(p.tipo_resposta) ? p.alternativas.filter(a => a.trim()) : [],
    })) : [],
  };
}
