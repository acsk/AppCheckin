/* Regras de apresentação do módulo Prospecção → Recuperação de Alunos (sem React, testáveis com node:test). */

export const VARIAVEIS_MODELO = ['nome', 'modalidade', 'plano_anterior', 'estabelecimento'];

export const TOM_STATUS = {
  nao_contatado: 'neutral', contatado: 'info', interessado: 'warning', em_negociacao: 'warning',
  retorno_agendado: 'info', recuperado: 'success', sem_interesse: 'outline', nao_contatar: 'danger',
};

export const POTENCIAL = { alto: ['success', 'Alto potencial'], medio: ['warning', 'Médio potencial'], baixo: ['neutral', 'Baixo potencial'] };

export const erroApi = (err, fallback) => err?.error || err?.message || fallback;

const titulo = (v) => String(v || '').trim().toLowerCase().replace(/(^|\s)\S/g, (c) => c.toUpperCase());
export const primeiroNome = (nome) => titulo(String(nome || '').trim().split(/\s+/)[0]);

/**
 * Preenche {nome}, {modalidade}, {plano_anterior}, {estabelecimento}. Variável sem valor fica vazia e é
 * listada em `faltando` — o texto nunca inventa dado do aluno.
 * @returns {{ texto: string, faltando: string[] }}
 */
export function aplicarModelo(conteudo, vars = {}) {
  const valores = {
    nome: primeiroNome(vars.nome),
    modalidade: String(vars.modalidade || '').trim(),
    plano_anterior: String(vars.plano_anterior || '').trim(),
    estabelecimento: String(vars.estabelecimento || '').trim(),
  };
  const faltando = new Set();
  const texto = String(conteudo || '').replace(/\{([a-z_]+)\}/g, (inteiro, chave) => {
    if (!(chave in valores)) return inteiro;
    if (!valores[chave]) faltando.add(chave);
    return valores[chave];
  });
  return { texto: texto.replace(/[ \t]+([,.!?])/g, '$1').replace(/,([.!?])/g, '$1').replace(/[ \t]{2,}/g, ' '), faltando: [...faltando] };
}

export function dataBR(iso) {
  if (!iso) return '—';
  const [a, m, d] = String(iso).slice(0, 10).split('-');
  return d && m && a ? `${d}/${m}/${a}` : '—';
}

export function dataHoraBR(iso) {
  if (!iso) return '—';
  const hora = String(iso).replace('T', ' ').slice(11, 16);
  return dataBR(iso) + (hora ? ` às ${hora}` : '');
}

/** "12 dias", "1 mês", "7 meses", "2 anos e 1 mês". */
export function tempoInativo(dias) {
  const n = Math.max(0, Number(dias) || 0);
  if (n < 60) return n === 1 ? '1 dia' : `${n} dias`;
  const meses = Math.floor(n / 30.4);
  if (meses < 24) return `${meses} meses`;
  const anos = Math.floor(meses / 12), resto = meses % 12;
  return `${anos} anos${resto ? ` e ${resto} ${resto === 1 ? 'mês' : 'meses'}` : ''}`;
}

/** Converte 'AAAA-MM-DDTHH:mm' (input datetime-local) para o formato da API. */
export const paraApiDataHora = (local) => (local ? `${String(local).replace('T', ' ').slice(0, 16)}:00` : '');

export function fotoUrl(caminho, base) {
  if (!caminho) return undefined;
  if (/^https?:\/\//.test(caminho)) return caminho;
  return `${String(base || '').replace(/\/$/, '')}/${String(caminho).replace(/^\//, '')}`;
}

export const moeda = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v) || 0);
export const numero = (v) => new Intl.NumberFormat('pt-BR').format(Number(v) || 0);
export const mesCurto = (yyyyMm) => {
  const [a, m] = String(yyyyMm).split('-');
  return `${['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][Number(m) - 1] || m}/${String(a).slice(2)}`;
};
