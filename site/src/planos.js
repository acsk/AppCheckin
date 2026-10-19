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

export const gratis = (p) => p.tipo === 'gratuito';

/** "AppCheckin Completo" vira "Completo" nos botões e títulos. */
export const nomeCurto = (p) => p.nome.replace(/^AppCheckin\s+/i, '') || p.nome;

/** "Grátis até 100 alunos ativos, sem cartão" — respeita prazo e limite do plano grátis. */
export function resumoFree(p) {
  const alunos = p.maxAlunos ? `até ${num(p.maxAlunos)} alunos ativos` : 'sem limite de alunos';
  return p.prazo ? `Grátis por ${plural(p.prazo, 'dia', 'dias')}, ${alunos}, sem cartão` : `Grátis ${alunos}, sem cartão`;
}

/** Valor de um ciclo para a quantidade de alunos informada. */
export function valorCiclo(p, alunos) {
  if (p.tipo === 'por_aluno') return Math.min(alunos, p.maxAlunos || alunos) * p.porAluno;
  return p.tipo === 'gratuito' ? 0 : p.valor;
}

export function assinarHref(p) {
  return p ? `/assinar?plano=${p.id}` : '/assinar';
}

const inicial = { planos: snapshot.map(normalizar), formas: null, carregado: false };
let pedido = null;

function carregar() {
  pedido ??= fetch(`${API_URL}/site/planos`)
    .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
    .then((d) => ({
      planos: Array.isArray(d.planos) && d.planos.length ? d.planos.map(normalizar) : inicial.planos,
      formas: Array.isArray(d.formas_pagamento) ? d.formas_pagamento : [],
      carregado: true,
    }))
    .catch(() => {
      pedido = null;
      return { ...inicial, formas: [], carregado: true };
    });
  return pedido;
}

/**
 * Planos marcados para o site no painel e as formas de pagamento ativas no Asaas do AppCheckin.
 * Começa com o retrato do build e atualiza pela API (uma requisição para a página toda).
 */
export function useCatalogo() {
  const [catalogo, setCatalogo] = useState(inicial);

  useEffect(() => {
    let vivo = true;
    carregar().then((c) => vivo && setCatalogo(c));
    return () => {
      vivo = false;
    };
  }, []);

  return catalogo;
}

export function usePlanos() {
  return useCatalogo().planos;
}

/** Plano grátis e plano pago em destaque, na ordem do painel. */
export function usePlanosPrincipais() {
  const planos = usePlanos();
  return { planos, free: planos.find(gratis) || null, pago: planos.find((p) => !gratis(p)) || null };
}
