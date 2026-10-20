import React, { useEffect, useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import { financeiroService } from '../../services/financeiroService';
import LayoutBase from '../../components/LayoutBase';
import { Avatar, Badge, Button, Callout, Icon, IconButton, PageHeader, SegmentedControl, TextField } from '../../components/ck';
import {
  BarList, KpiCard, MonthlyChart, STATUS, StatusSplit, byMonth, fdate, groupSum, isoDate, money, monthEnd, monthLabel, monthStart, pct, statusOf, summarize,
} from '../../components/ck/financeiro';
import { abrirRelatorioHtml, relatorioHead } from '../../utils/relatorioPlanos';
import { showError } from '../../utils/toast';

const PAGE_SIZE = 25;
const PERIODS = [
  { value: 'mes', label: 'Este mês' },
  { value: 'mes-passado', label: 'Mês passado' },
  { value: '3m', label: '3 meses' },
  { value: '6m', label: '6 meses' },
  { value: 'ano', label: 'Este ano' },
  { value: 'custom', label: 'Personalizado' },
];
const FILTERS = [['todos', 'Todos'], ['recebido', 'Recebidos no período'], ['pago', 'Pagos'], ['aguardando', 'Aguardando'], ['atrasado', 'Atrasados'], ['cancelado', 'Cancelados']];

const titleCase = (n) => String(n || '').toLowerCase().replace(/(^|\s)(\p{L})/gu, (m, s, c) => s + c.toUpperCase()).replace(/\s(Da|De|Do|Das|Dos|E)\s/g, (m) => m.toLowerCase());
const norm = (s) => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function rangeOf(period, now) {
  switch (period) {
    case 'mes-passado': return [monthStart(now, -1), monthEnd(now, -1)];
    case '3m': return [monthStart(now, -2), monthEnd(now)];
    case '6m': return [monthStart(now, -5), monthEnd(now)];
    case 'ano': return [new Date(now.getFullYear(), 0, 1), new Date(now.getFullYear(), 11, 31)];
    default: return [monthStart(now), monthEnd(now)];
  }
}

function downloadCsv(filename, rows) {
  const csv = rows.map((r) => r.map((v) => {
    const s = String(v ?? '');
    return /[";\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  }).join(';')).join('\n');
  const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export default function RelatorioFinanceiroScreen() {
  const router = useRouter();
  const params = useLocalSearchParams();
  const now = new Date();
  const today = isoDate(now);

  const initialPeriod = PERIODS.some((p) => p.value === params.periodo) ? params.periodo : 'mes';
  const [period, setPeriod] = useState(initialPeriod);
  const [custom, setCustom] = useState(() => {
    const [a, b] = rangeOf('mes', now);
    return { from: isoDate(a), to: isoDate(b) };
  });
  const [filter, setFilter] = useState(FILTERS.some((f) => f[0] === params.status) ? params.status : 'todos');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [payments, setPayments] = useState([]);
  const [recebidos, setRecebidos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [user, setUser] = useState(null);

  const [fromDate, toDate] = useMemo(() => {
    if (period === 'custom') return [new Date(`${custom.from}T00:00:00`), new Date(`${custom.to}T00:00:00`)];
    return rangeOf(period, now);
  }, [period, custom.from, custom.to]);
  const from = isoDate(fromDate);
  const to = isoDate(toDate);
  const invalidRange = period === 'custom' && (!custom.from || !custom.to || custom.from > custom.to);

  useEffect(() => { authService.getCurrentUser().then(setUser).catch(() => {}); }, []);

  useEffect(() => {
    if (invalidRange) return undefined;
    let active = true;
    setLoading(true);
    setError('');
    Promise.all([
      financeiroService.listarPagamentos({ dataInicio: from, dataFim: to }),
      financeiroService.listarRecebidos({ dataInicio: from, dataFim: to }),
    ])
      .then(([list, pagos]) => { if (active) { setPayments(list); setRecebidos(pagos); } })
      .catch((err) => { if (active) { setPayments([]); setRecebidos([]); setError(err?.mensagemLimpa || err?.error || 'Não foi possível carregar as parcelas.'); } })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [from, to, invalidRange]);

  useEffect(() => { setPage(1); }, [from, to, filter, query]);

  const summary = useMemo(() => summarize(payments, today), [payments]);
  // Recebido = pagas com data de pagamento no período (mesmo número do Dashboard e de Assinaturas)
  const received = useMemo(() => recebidos.reduce((a, p) => a + (Number(p.valor) || 0), 0), [recebidos]);
  const matches = (p, q) => norm(`${p.aluno_nome} ${p.plano_nome} ${p.forma_pagamento_nome} ${p.matricula_id}`).includes(q);
  const searched = useMemo(() => {
    const q = norm(query.trim());
    return q ? payments.filter((p) => matches(p, q)) : payments;
  }, [payments, query]);
  const searchedReceived = useMemo(() => {
    const q = norm(query.trim());
    return q ? recebidos.filter((p) => matches(p, q)) : recebidos;
  }, [recebidos, query]);
  const counts = useMemo(() => {
    const c = { todos: searched.length, recebido: searchedReceived.length, pago: 0, aguardando: 0, atrasado: 0, cancelado: 0 };
    searched.forEach((p) => { c[statusOf(p, today)] += 1; });
    return c;
  }, [searched, searchedReceived]);
  const byPayment = filter === 'recebido';
  const rows = useMemo(() => (byPayment
    ? [...searchedReceived].sort((a, b) => String(b.data_pagamento).localeCompare(String(a.data_pagamento)) || String(a.aluno_nome).localeCompare(String(b.aluno_nome), 'pt-BR'))
    : searched
      .filter((p) => filter === 'todos' || statusOf(p, today) === filter)
      .sort((a, b) => String(a.data_vencimento).localeCompare(String(b.data_vencimento)) || String(a.aluno_nome).localeCompare(String(b.aluno_nome), 'pt-BR'))),
  [searched, searchedReceived, filter]);
  const rowsTotal = rows.reduce((a, p) => a + (statusOf(p, today) === 'cancelado' ? 0 : Number(p.valor) || 0), 0);
  const pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
  const pageRows = rows.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  const months = useMemo(() => byMonth(payments, today, fromDate, toDate), [payments, from, to]);
  const byMethod = useMemo(() => groupSum(recebidos, (p) => p.forma_pagamento_nome), [recebidos]);
  const byPlan = useMemo(() => groupSum(payments, (p) => p.plano_nome, (p) => statusOf(p, today) !== 'cancelado'), [payments]);

  const periodLabel = from.slice(0, 7) === to.slice(0, 7) && from.endsWith('-01') && to === isoDate(monthEnd(fromDate))
    ? fromDate.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
    : `${fdate(from)} a ${fdate(to)}`;
  const ticket = recebidos.length ? received / recebidos.length : 0;
  const tenantName = user?.tenant?.nome || user?.tenant_nome || user?.academia_nome || '';

  const print = () => {
    const tr = (p) => {
      const st = STATUS[statusOf(p, today)];
      const cls = { success: 'b-ok', warning: 'b-warn', danger: 'b-bad' }[st.tone] || '';
      return `<tr><td>${esc(titleCase(p.aluno_nome))}<div class="muted">Matrícula ${esc(p.matricula_id)}</div></td><td>${esc(p.plano_nome)}</td><td>${fdate(p.data_vencimento)}</td><td>${p.data_pagamento ? fdate(p.data_pagamento) : '—'}</td><td>${esc(p.forma_pagamento_nome || '—')}</td><td><span class="badge ${cls}">${st.label}</span></td><td class="num">${money(p.valor)}</td></tr>`;
    };
    const breakdown = (title, items) => `
      <section class="card"><h2>${esc(title)}</h2><table><thead><tr><th>Item</th><th class="num">Parcelas</th><th class="num">Valor</th></tr></thead><tbody>
      ${items.length ? items.map((i) => `<tr><td>${esc(i.label)}</td><td class="num">${i.count}</td><td class="num">${money(i.value)}</td></tr>`).join('') : '<tr><td colspan="3" class="muted">Nenhum dado no período.</td></tr>'}
      </tbody></table></section>`;
    const body = `
      ${relatorioHead('Relatório financeiro', `${byPayment ? 'Parcelas pagas' : 'Parcelas com vencimento'} de ${fdate(from)} a ${fdate(to)}${filter !== 'todos' && !byPayment ? ` · ${STATUS[filter].plural}` : ''}${query ? ` · busca "${query}"` : ''}`, tenantName)}
      <section class="kpis">
        <div class="kpi ok"><span>Recebido</span><strong>${money(received)}</strong><span>${recebidos.length} parcelas pagas no período</span></div>
        <div class="kpi warn"><span>A receber</span><strong>${money(summary.aguardando)}</strong><span>${summary.count.aguardando} parcelas</span></div>
        <div class="kpi bad"><span>Em atraso</span><strong>${money(summary.atrasado)}</strong><span>${summary.count.atrasado} parcelas</span></div>
        <div class="kpi"><span>Parcelas do período pagas</span><strong>${pct(summary.pago, summary.total)}%</strong><span>Ticket médio ${money(ticket)}</span></div>
      </section>
      ${months.length > 1 ? `<section class="card"><h2>Por mês de vencimento</h2><table><thead><tr><th>Mês</th><th class="num">Recebido</th><th class="num">Aguardando</th><th class="num">Atrasado</th><th class="num">Total</th></tr></thead><tbody>
        ${months.map((m) => `<tr><td>${monthLabel(m.key)}</td><td class="num">${money(m.pago)}</td><td class="num">${money(m.aguardando)}</td><td class="num">${money(m.atrasado)}</td><td class="num">${money(m.pago + m.aguardando + m.atrasado)}</td></tr>`).join('')}
      </tbody></table></section>` : ''}
      ${breakdown('Recebido por forma de pagamento', byMethod)}
      ${breakdown('Previsto por plano', byPlan)}
      <section class="card"><h2>Parcelas (${rows.length})</h2><table><thead><tr><th>Aluno</th><th>Plano</th><th>Vencimento</th><th>Pagamento</th><th>Forma</th><th>Status</th><th class="num">Valor</th></tr></thead><tbody>
        ${rows.length ? rows.map(tr).join('') : '<tr><td colspan="7" class="muted">Nenhuma parcela neste filtro.</td></tr>'}
        <tr><td colspan="6"><strong>Total (sem cancelados)</strong></td><td class="num"><strong>${money(rowsTotal)}</strong></td></tr>
      </tbody></table></section>
      <p class="foot">Recebido: parcelas pagas no período, pela data do pagamento. A receber, em atraso e o mês a mês: pela data de vencimento. Parcelas atrasadas incluem as que estão aguardando com vencimento anterior a ${fdate(today)}.</p>`;
    try {
      abrirRelatorioHtml('Relatório financeiro', body);
    } catch (err) {
      showError(err?.error || 'Não foi possível abrir o relatório.');
    }
  };

  const exportCsv = () => {
    downloadCsv(`relatorio-financeiro_${from}_${to}.csv`, [
      ['Matrícula', 'Aluno', 'Plano', 'Vencimento', 'Pagamento', 'Forma de pagamento', 'Status', 'Valor'],
      ...rows.map((p) => [p.matricula_id, titleCase(p.aluno_nome), p.plano_nome, fdate(p.data_vencimento), p.data_pagamento ? fdate(p.data_pagamento) : '', p.forma_pagamento_nome || '', STATUS[statusOf(p, today)].label, (Number(p.valor) || 0).toFixed(2).replace('.', ',')]),
    ]);
  };

  return (
    <LayoutBase title="Relatório financeiro" pageHeader={false}>
      <div className="ck-fin">
        <PageHeader
          title="Relatório financeiro"
          description="Recebido pela data do pagamento; a receber e atrasos pela data de vencimento."
          actions={(
            <>
              <Button icon="download" disabled={loading || !rows.length} onClick={exportCsv}>Exportar CSV</Button>
              <Button variant="primary" icon="printer" disabled={loading} onClick={print}>Imprimir</Button>
            </>
          )}
        />

        <section className="ck-card ck-fin__period" aria-label="Período">
          <SegmentedControl label="Período" options={PERIODS} value={period} onChange={setPeriod} />
          {period === 'custom' ? (
            <div className="ck-fin__dates">
              <TextField label="De" type="date" value={custom.from} max={custom.to || undefined} onChange={(e) => setCustom((c) => ({ ...c, from: e.target.value }))} />
              <TextField label="Até" type="date" value={custom.to} min={custom.from || undefined} onChange={(e) => setCustom((c) => ({ ...c, to: e.target.value }))} error={invalidRange ? 'A data final precisa ser igual ou posterior à inicial.' : undefined} />
            </div>
          ) : null}
          <p className="ck-fin__range"><Icon name="calendar" size={15} />Período: <strong>{periodLabel}</strong></p>
        </section>

        {error ? <Callout tone="danger" title="Não foi possível carregar o relatório">{error}</Callout> : null}

        <div className="ck-kpis">
          <KpiCard emphasis tone="success" icon="check-circle" label="Recebido" value={loading ? '—' : money(received)} hint={`${recebidos.length} ${recebidos.length === 1 ? 'parcela paga' : 'parcelas pagas'} no período`} onClick={() => setFilter('recebido')} />
          <KpiCard tone="warning" icon="clock" label="A receber" value={loading ? '—' : money(summary.aguardando)} hint={`${summary.count.aguardando} ${summary.count.aguardando === 1 ? 'parcela no prazo' : 'parcelas no prazo'}`} onClick={() => setFilter('aguardando')} />
          <KpiCard tone="danger" icon="alert-triangle" label="Em atraso" value={loading ? '—' : money(summary.atrasado)} hint={`${summary.count.atrasado} ${summary.count.atrasado === 1 ? 'parcela vencida' : 'parcelas vencidas'}`} onClick={() => setFilter('atrasado')} />
          <KpiCard tone="info" icon="trending-up" label="Parcelas do período pagas" value={loading ? '—' : `${pct(summary.pago, summary.total)}%`} hint={`Pelo vencimento · ticket médio ${money(ticket)}`} />
        </div>

        <div className="ck-dash__row">
          <section className="ck-card ck-dash__panel" aria-label="Distribuição">
            <header className="ck-dash__head">
              <div>
                <h2 className="ck-mt-box__title">{months.length > 1 ? 'Por mês de vencimento' : 'Situação das parcelas'}</h2>
                <p className="ck-dash__sub">{months.length > 1 ? 'Recebido, aguardando e atrasado em cada mês' : `Total previsto ${money(summary.total)}`}</p>
              </div>
            </header>
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p>
              : months.length > 1 ? <MonthlyChart months={months} currentKey={today.slice(0, 7)} />
                : <StatusSplit summary={summary} showCancelled={summary.count.cancelado > 0} />}
          </section>
          <section className="ck-card ck-dash__panel" aria-label="Recebido por forma de pagamento">
            <header className="ck-dash__head">
              <div>
                <h2 className="ck-mt-box__title">Formas de pagamento</h2>
                <p className="ck-dash__sub">O recebido no período, pela data do pagamento</p>
              </div>
            </header>
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : <BarList items={byMethod} total={received} empty="Nenhum pagamento recebido no período." />}
          </section>
        </div>

        <section className="ck-card ck-dash__panel" aria-label="Previsto por plano">
          <header className="ck-dash__head">
            <div>
              <h2 className="ck-mt-box__title">Por plano</h2>
              <p className="ck-dash__sub">Valor previsto no período, sem parcelas canceladas</p>
            </div>
          </header>
          {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : <BarList items={byPlan} total={summary.total} max={8} />}
        </section>

        <section className="ck-card ck-ml__panel" aria-label="Parcelas">
          <div className="ck-ml__toolbar">
            <div className="ck-control ck-ml__search">
              <Icon name="search" size={18} className="ck-control__icon" />
              <input type="search" className="ck-control__input" value={query} placeholder="Buscar por aluno, plano, forma de pagamento ou matrícula" aria-label="Buscar parcelas" onChange={(e) => setQuery(e.target.value)} />
              {query ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setQuery('')} /> : null}
            </div>
            <div className="ck-ml__filters" role="radiogroup" aria-label="Filtrar por status">
              {FILTERS.map(([key, label]) => (
                <button key={key} type="button" role="radio" aria-checked={filter === key} className="ck-ml__chip" onClick={() => setFilter(key)}>
                  {STATUS[key] ? <span className={`ck-ml__chipdot ck-ml__chipdot--${key === 'cancelado' ? 'outline' : STATUS[key].tone}`} aria-hidden /> : null}
                  {label}<span className="ck-ml__count">{counts[key]}</span>
                </button>
              ))}
            </div>
          </div>

          {loading ? <p role="status" className="ck-enrollment-state">Carregando parcelas…</p>
            : pageRows.length ? (
              <div className="ck-mt-table-wrap">
                <table className="ck-mt-table ck-ml__table">
                  <thead>
                    <tr>
                      <th scope="col">Aluno</th>
                      <th scope="col">Plano</th>
                      <th scope="col">Vencimento</th>
                      <th scope="col">Pagamento</th>
                      <th scope="col">Status</th>
                      <th scope="col" className="is-num">Valor</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pageRows.map((p) => {
                      const st = statusOf(p, today);
                      const href = `/matriculas/detalhe?id=${p.matricula_id}`;
                      return (
                        <tr key={p.id} className="ck-ml__row" onClick={(e) => { if (!e.target.closest('a, button')) router.push(href); }}>
                          <td>
                            <div className="ck-ml__who">
                              <Avatar name={titleCase(p.aluno_nome)} size="sm" decorative />
                              <span style={{ minWidth: 0 }}>
                                <a href={href} className="ck-ml__name" onClick={(e) => { e.preventDefault(); router.push(href); }}>{titleCase(p.aluno_nome)}</a>
                                <span className="ck-mt-cell__sub">Matrícula {p.matricula_id}</span>
                              </span>
                            </div>
                          </td>
                          <td><span className="ck-mt-cell__main">{p.plano_nome || '—'}</span></td>
                          <td><span className="ck-mt-cell__main">{fdate(p.data_vencimento)}</span></td>
                          <td>
                            <span className="ck-mt-cell__main">{p.data_pagamento ? fdate(p.data_pagamento) : '—'}</span>
                            {p.forma_pagamento_nome ? <span className="ck-mt-cell__sub">{p.forma_pagamento_nome}</span> : null}
                          </td>
                          <td><Badge tone={STATUS[st].tone} dot>{STATUS[st].label}</Badge></td>
                          <td className="is-num"><span className="ck-mt-cell__main">{money(p.valor)}</span></td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="ck-ml__empty">
                <span className="ck-mt-empty__icon" aria-hidden><Icon name="search" size={20} /></span>
                <strong>{query ? `Nenhuma parcela encontrada para "${query}"` : 'Nenhuma parcela neste filtro'}</strong>
                <p>{query ? 'Confira a grafia ou busque pelo número da matrícula.' : 'Escolha outro status ou outro período.'}</p>
              </div>
            )}

          {!loading && rows.length ? (
            <div className="ck-ml__foot">
              <span>{rows.length} {rows.length === 1 ? 'parcela' : 'parcelas'} · {money(rowsTotal)}{filter === 'todos' || filter === 'cancelado' ? ' sem cancelados' : ''} · {byPayment ? 'pela data do pagamento' : 'pela data de vencimento'}</span>
              {pages > 1 ? (
                <div className="ck-ml__pager">
                  <IconButton icon="chevron-left" label="Página anterior" size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((v) => v - 1)} />
                  <span className="ck-ml__pageinfo">{page} de {pages}</span>
                  <IconButton icon="chevron-right" label="Próxima página" size="sm" variant="secondary" disabled={page >= pages} onClick={() => setPage((v) => v + 1)} />
                </div>
              ) : null}
            </div>
          ) : null}
        </section>
      </div>
    </LayoutBase>
  );
}
