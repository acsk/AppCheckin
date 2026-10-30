/* AppCheckin — blocos financeiros do Dashboard e do Relatório financeiro.
   Estilos: src/theme/ck-financeiro.css. Dados: parcelas de pagamentos_plano. */
import React from 'react';
import { Icon } from './index';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const BRL0 = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
export const money = (v) => BRL.format(Number(v) || 0);
export const moneyShort = (v) => {
  const n = Number(v) || 0;
  if (Math.abs(n) >= 1000) return `R$ ${(n / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil`;
  return BRL0.format(n);
};
export const pct = (part, total) => (total > 0 ? Math.round((part / total) * 100) : 0);

export const isoDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
export const fdate = (iso) => (iso ? String(iso).slice(0, 10).split('-').reverse().join('/') : '—');
export const monthStart = (d, offset = 0) => new Date(d.getFullYear(), d.getMonth() + offset, 1);
export const monthEnd = (d, offset = 0) => new Date(d.getFullYear(), d.getMonth() + offset + 1, 0);
const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
export const monthLabel = (key) => { const [y, m] = key.split('-'); return `${MONTHS[Number(m) - 1]}/${y.slice(2)}`; };

/* Status de uma parcela: aguardando com vencimento passado conta como atrasada. */
export function statusOf(p, todayISO) {
  const id = Number(p.status_pagamento_id);
  if (id === 2) return 'pago';
  if (id === 4) return 'cancelado';
  if (id === 5) return 'estornado';
  if (id === 3) return 'atrasado';
  return String(p.data_vencimento || '').slice(0, 10) < todayISO ? 'atrasado' : 'aguardando';
}
export const STATUS = {
  pago: { tone: 'success', label: 'Pago', plural: 'Pagos' },
  aguardando: { tone: 'warning', label: 'Aguardando', plural: 'Aguardando' },
  atrasado: { tone: 'danger', label: 'Atrasado', plural: 'Atrasados' },
  cancelado: { tone: 'neutral', label: 'Cancelado', plural: 'Cancelados' },
  estornado: { tone: 'neutral', label: 'Estornado', plural: 'Estornados' },
};
/** Fora dos totais a receber/recebidos da lista (o estorno aparece no BI, pela data da devolução) */
export const foraDoTotal = (st) => st === 'cancelado' || st === 'estornado';

export function summarize(payments, todayISO) {
  const s = { pago: 0, aguardando: 0, atrasado: 0, cancelado: 0, estornado: 0, count: { pago: 0, aguardando: 0, atrasado: 0, cancelado: 0, estornado: 0 } };
  payments.forEach((p) => {
    const st = statusOf(p, todayISO);
    s[st] += Number(p.valor) || 0;
    s.count[st] += 1;
  });
  s.total = s.pago + s.aguardando + s.atrasado;
  s.totalCount = s.count.pago + s.count.aguardando + s.count.atrasado;
  return s;
}

export function byMonth(payments, todayISO, fromDate, toDate) {
  const keys = [];
  for (let d = monthStart(fromDate); d <= toDate; d = monthStart(d, 1)) keys.push(isoDate(d).slice(0, 7));
  const map = Object.fromEntries(keys.map((k) => [k, { key: k, pago: 0, aguardando: 0, atrasado: 0 }]));
  payments.forEach((p) => {
    const k = String(p.data_vencimento || '').slice(0, 7);
    const st = statusOf(p, todayISO);
    if (map[k] && !foraDoTotal(st)) map[k][st] += Number(p.valor) || 0;
  });
  return keys.map((k) => map[k]);
}

export function groupSum(payments, keyFn, filter) {
  const map = new Map();
  payments.forEach((p) => {
    if (filter && !filter(p)) return;
    const k = keyFn(p) || 'Não informado';
    const cur = map.get(k) || { label: k, value: 0, count: 0 };
    cur.value += Number(p.valor) || 0;
    cur.count += 1;
    map.set(k, cur);
  });
  return [...map.values()].sort((a, b) => b.value - a.value);
}

/* ---------- Componentes ---------- */
export function KpiCard({ icon, label, value, hint, tone = 'neutral', trend, onClick, emphasis }) {
  const Tag = onClick ? 'button' : 'div';
  return (
    <Tag type={onClick ? 'button' : undefined} className={`ck-kpi ck-kpi--${tone}${emphasis ? ' ck-kpi--emphasis' : ''}${onClick ? ' is-link' : ''}`} onClick={onClick}>
      <span className="ck-kpi__top">
        <span className="ck-kpi__icon" aria-hidden><Icon name={icon} size={18} strokeWidth={2} /></span>
        <span className="ck-kpi__label">{label}</span>
        {onClick ? <Icon name="arrow-right" size={16} className="ck-kpi__go" /> : null}
      </span>
      <span className="ck-kpi__value">{value}</span>
      {hint || trend ? (
        <span className="ck-kpi__hint">
          {trend ? (
            <span className={`ck-kpi__trend ck-kpi__trend--${trend.direction}`}>
              <Icon name={trend.direction === 'down' ? 'trending-down' : 'trending-up'} size={14} strokeWidth={2.25} />{trend.label}
            </span>
          ) : null}
          {hint}
        </span>
      ) : null}
    </Tag>
  );
}

export function StatusSplit({ summary, showCancelled }) {
  const rows = ['pago', 'aguardando', 'atrasado'].concat(showCancelled ? ['cancelado'] : []);
  const total = summary.total || 0;
  return (
    <div className="ck-fin-split">
      <div className="ck-mt-bar" role="img" aria-label={rows.map((k) => `${STATUS[k].label} ${money(summary[k])}`).join(', ')}>
        {['pago', 'aguardando', 'atrasado'].map((k) => (summary[k] > 0 ? <span key={k} className={`ck-mt-bar__seg ck-mt-bar__seg--${STATUS[k].tone}`} style={{ width: `${(summary[k] / (total || 1)) * 100}%` }} /> : null))}
      </div>
      <ul className="ck-mt-legend">
        {rows.map((k) => (
          <li key={k} className={summary[k] === 0 ? 'is-zero' : null}>
            <span className={`ck-mt-legend__key ck-mt-legend__key--${STATUS[k].tone === 'neutral' ? 'neutral' : STATUS[k].tone}`} aria-hidden />
            <span className="ck-mt-legend__label">{STATUS[k].label}<span className="ck-fin-split__count">{summary.count[k]}</span></span>
            <span className="ck-mt-legend__value">{money(summary[k])}</span>
          </li>
        ))}
        <li className="ck-mt-legend__total">
          <span className="ck-mt-legend__label">Total previsto</span>
          <span className="ck-mt-legend__value">{money(total)}</span>
        </li>
      </ul>
    </div>
  );
}

export function MonthlyChart({ months, currentKey, height = 200 }) {
  const max = Math.max(1, ...months.map((m) => m.pago + m.aguardando + m.atrasado));
  return (
    <div className="ck-fin-chart">
      <div className="ck-fin-chart__plot" style={{ height }} role="img" aria-label={months.map((m) => `${monthLabel(m.key)}: recebido ${money(m.pago)}, em aberto ${money(m.aguardando + m.atrasado)}`).join('; ')}>
        {months.map((m) => {
          const total = m.pago + m.aguardando + m.atrasado;
          return (
            <div key={m.key} className={`ck-fin-chart__col${m.key === currentKey ? ' is-current' : ''}`} title={`${monthLabel(m.key)}\nRecebido: ${money(m.pago)}\nAguardando: ${money(m.aguardando)}\nAtrasado: ${money(m.atrasado)}`}>
              <span className="ck-fin-chart__total">{total ? moneyShort(total) : ''}</span>
              <div className="ck-fin-chart__bar" style={{ height: total ? Math.max((total / max) * (height - 28), 4) : 0 }}>
                {m.atrasado > 0 ? <span className="ck-fin-chart__seg ck-fin-chart__seg--late" style={{ flexGrow: m.atrasado }} /> : null}
                {m.aguardando > 0 ? <span className="ck-fin-chart__seg ck-fin-chart__seg--wait" style={{ flexGrow: m.aguardando }} /> : null}
                {m.pago > 0 ? <span className="ck-fin-chart__seg ck-fin-chart__seg--paid" style={{ flexGrow: m.pago }} /> : null}
              </div>
            </div>
          );
        })}
      </div>
      <div className="ck-fin-chart__axis">
        {months.map((m) => <span key={m.key} className={m.key === currentKey ? 'is-current' : null}>{monthLabel(m.key)}</span>)}
      </div>
      <ul className="ck-fin-chart__legend">
        <li><span className="ck-fin-chart__key ck-fin-chart__seg--paid" />Recebido</li>
        <li><span className="ck-fin-chart__key ck-fin-chart__seg--wait" />Aguardando</li>
        <li><span className="ck-fin-chart__key ck-fin-chart__seg--late" />Atrasado</li>
      </ul>
    </div>
  );
}

export function BarList({ items, total, empty = 'Nenhum dado no período.', max = 6, unit = ['parcela', 'parcelas'] }) {
  if (!items.length) return <p className="ck-fin-empty">{empty}</p>;
  const top = items.slice(0, max);
  const rest = items.slice(max);
  const list = rest.length ? [...top, { label: `Outros (${rest.length})`, value: rest.reduce((a, i) => a + i.value, 0), count: rest.reduce((a, i) => a + i.count, 0) }] : top;
  const peak = Math.max(1, ...list.map((i) => i.value));
  return (
    <ul className="ck-fin-bars">
      {list.map((i) => (
        <li key={i.label}>
          <span className="ck-fin-bars__label">{i.label}<span>{i.count} {i.count === 1 ? unit[0] : unit[1]}</span></span>
          <span className="ck-fin-bars__value">{money(i.value)}<span>{pct(i.value, total)}%</span></span>
          <span className="ck-fin-bars__track"><span className="ck-fin-bars__fill" style={{ width: `${(i.value / peak) * 100}%` }} /></span>
        </li>
      ))}
    </ul>
  );
}
