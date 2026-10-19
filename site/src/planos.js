import { useEffect, useState } from 'react';
import snapshot from './data/planos.json';
import { API_URL, brl } from './config';

const num = (v) => Number(v).toLocaleString('pt-BR');
const plural = (n, one, many) => `${num(n)} ${n === 1 ? one : many}`;

export function normalizar(p) {
  return {
    id: p.id,
    nome: p.nome,
    descricao: p.descricao || '',
    tipo: p.tipo_cobranca || (Number(p.valor) > 0 ? 'fixo' : 'gratuito'),
    valor: Number(p.valor || 0),
    porAluno: Number(p.valor_por_aluno || 0),
    ciclo: Number(p.duracao_dias || 30),
    prazo: p.prazo_dias ? Number(p.prazo_dias) : null,
    maxAlunos: p.max_alunos ? Number(p.max_alunos) : null,
    maxAdmins: p.max_admins ? Number(p.max_admins) : null,
  };
}

export function cicloLabel(dias) {
  if (dias === 30) return 'mês';
  if (dias === 365) return 'ano';
  if (dias === 7) return 'semana';
  return `${dias} dias`;
}

export function precoTexto(p) {
  if (p.tipo === 'gratuito') return { valor: 'Grátis', sufixo: p.prazo ? `por ${plural(p.prazo, 'dia', 'dias')}` : 'sem prazo' };
  if (p.tipo === 'por_aluno') return { valor: brl(p.porAluno), sufixo: `por aluno ativo/${cicloLabel(p.ciclo)}` };
  return { valor: brl(p.valor), sufixo: `/${cicloLabel(p.ciclo)}` };
}

export function limitesTexto(p) {
  const out = [p.maxAlunos ? `Até ${plural(p.maxAlunos, 'aluno ativo', 'alunos ativos')}` : 'Alunos ilimitados'];
  out.push(p.maxAdmins ? plural(p.maxAdmins, 'administrador', 'administradores') : 'Administradores ilimitados');
  if (p.prazo && p.tipo !== 'gratuito') out.push(`Contrato de ${plural(p.prazo, 'dia', 'dias')}`);
  return out;
}

/** Planos marcados para o site no painel. Começa com o retrato do build e atualiza pela API. */
export function usePlanos() {
  const [planos, setPlanos] = useState(() => snapshot.map(normalizar));

  useEffect(() => {
    const ctrl = new AbortController();
    fetch(`${API_URL}/site/planos`, { signal: ctrl.signal })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
      .then((d) => {
        if (Array.isArray(d.planos) && d.planos.length) setPlanos(d.planos.map(normalizar));
      })
      .catch(() => {});
    return () => ctrl.abort();
  }, []);

  return planos;
}
