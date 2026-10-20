import React, { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { financeiroService } from '../../services/financeiroService';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, Icon, IconButton, PageHeader, SegmentedControl, TextField } from '../../components/ck';
import { BarList, KpiCard, fdate, isoDate, money, monthEnd, monthStart, pct } from '../../components/ck/financeiro';
import { showError, showSuccess } from '../../utils/toast';

const PAGE_SIZE = 25;
const PERIODS = [
  { value: 'mes', label: 'Este mês' },
  { value: 'mes-passado', label: 'Mês passado' },
  { value: '3m', label: '3 meses' },
  { value: 'custom', label: 'Personalizado' },
];
const MAX_DIAS = 186;

// Situação de cada linha (mesmos códigos do backend)
const SITUACAO = {
  conciliado: { label: 'Conciliado', tone: 'success' },
  divergente: { label: 'Divergente', tone: 'danger' },
  sem_baixa: { label: 'Sem baixa', tone: 'danger' },
  sem_recebimento: { label: 'Sem recebimento', tone: 'warning' },
  sem_vinculo: { label: 'Sem vínculo', tone: 'outline' },
  manual: { label: 'Fora do provedor', tone: 'info' },
};
const PENDENTES = ['divergente', 'sem_baixa', 'sem_recebimento'];
const FILTERS = [
  ['todos', 'Todos'],
  ['pendencias', 'Pendências'],
  ['conciliado', 'Conciliados'],
  ['sem_baixa', 'Sem baixa'],
  ['divergente', 'Divergentes'],
  ['sem_recebimento', 'Sem recebimento'],
  ['sem_vinculo', 'Sem vínculo'],
  ['manual', 'Fora do provedor'],
];
const DOT = { success: 'success', danger: 'danger', warning: 'warning', info: 'info', outline: 'outline' };

const titleCase = (n) => String(n || '').toLowerCase().replace(/(^|\s)(\p{L})/gu, (m, s, c) => s + c.toUpperCase()).replace(/\s(Da|De|Do|Das|Dos|E)\s/g, (m) => m.toLowerCase());
const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const num = (v) => (v == null ? '' : (Number(v) || 0).toFixed(2).replace('.', ','));

function rangeOf(period, now) {
  switch (period) {
    case 'mes-passado': return [monthStart(now, -1), monthEnd(now, -1)];
    case '3m': return [monthStart(now, -2), monthEnd(now)];
    default: return [monthStart(now), monthEnd(now)];
  }
}

function downloadCsv(filename, rows) {
  const csv = rows.map((r) => r.map((v) => {
    const s = String(v ?? '');
    return /[";\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  }).join(';')).join('\n');
  const blob = new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

const quem = (l) => (l.aluno_nome ? titleCase(l.aluno_nome) : (l.descricao || (l.origem === 'provedor' ? 'Sem aluno ligado' : '—')));

export default function ConciliacaoBancariaScreen() {
  const router = useRouter();
  const now = new Date();

  const [period, setPeriod] = useState('mes');
  const [custom, setCustom] = useState(() => {
    const [a, b] = rangeOf('mes', now);
    return { from: isoDate(a), to: isoDate(b) };
  });
  const [filter, setFilter] = useState('todos');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reload, setReload] = useState(0);
  const [reprocessando, setReprocessando] = useState(null);

  const [fromDate, toDate] = useMemo(() => {
    if (period === 'custom') return [new Date(`${custom.from}T00:00:00`), new Date(`${custom.to}T00:00:00`)];
    return rangeOf(period, now);
  }, [period, custom.from, custom.to]);
  const from = isoDate(fromDate);
  const to = isoDate(toDate);
  const longRange = period === 'custom' && custom.from && custom.to && (toDate - fromDate) / 86400000 > MAX_DIAS;
  const invalidRange = period === 'custom' && (!custom.from || !custom.to || custom.from > custom.to || longRange);

  useEffect(() => {
    if (invalidRange) return undefined;
    let active = true;
    setLoading(true);
    setError('');
    financeiroService.conciliacao({ dataInicio: from, dataFim: to })
      .then((res) => { if (active) setData(res); })
      .catch((err) => { if (active) { setData(null); setError(err?.mensagemLimpa || err?.error || 'Não foi possível carregar a conciliação.'); } })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [from, to, invalidRange, reload]);

  useEffect(() => { setPage(1); }, [from, to, filter, query]);

  const linhas = data?.linhas || [];
  const resumo = data?.resumo;
  const prov = resumo?.provedor || { quantidade: 0, bruto: 0, taxas: 0, liquido: 0, a_receber: 0 };
  const provedores = data?.provedores || [];

  const searched = useMemo(() => {
    const q = norm(query.trim());
    if (!q) return linhas;
    return linhas.filter((l) => norm(`${l.aluno_nome} ${l.descricao} ${l.payment_id} ${l.matricula_id} ${l.plano_nome} ${l.forma}`).includes(q));
  }, [linhas, query]);
  const counts = useMemo(() => {
    const c = { todos: searched.length, pendencias: 0 };
    Object.keys(SITUACAO).forEach((k) => { c[k] = 0; });
    searched.forEach((l) => { c[l.status] += 1; if (PENDENTES.includes(l.status)) c.pendencias += 1; });
    return c;
  }, [searched]);
  const rows = useMemo(() => searched.filter((l) => filter === 'todos' || (filter === 'pendencias' ? PENDENTES.includes(l.status) : l.status === filter)), [searched, filter]);
  const pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
  const pageRows = rows.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  const porForma = useMemo(() => (resumo?.por_forma || []).filter((f) => f.taxas > 0).map((f) => ({ label: f.forma, value: f.taxas, count: f.quantidade })), [resumo]);

  const periodLabel = from.slice(0, 7) === to.slice(0, 7) && from.endsWith('-01') && to === isoDate(monthEnd(fromDate))
    ? fromDate.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
    : `${fdate(from)} a ${fdate(to)}`;

  const reprocessar = async (linha) => {
    setReprocessando(linha.id);
    try {
      const r = await financeiroService.reprocessarConciliacao(linha.payment_id);
      showSuccess(r?.message || 'Pagamento aplicado');
      setReload((v) => v + 1);
    } catch (err) {
      showError(err?.mensagemLimpa || err?.error || 'Não foi possível reprocessar o pagamento.');
    } finally {
      setReprocessando(null);
    }
  };

  const exportCsv = () => {
    downloadCsv(`conciliacao_${from}_${to}.csv`, [
      ['Data', 'Crédito', 'Provedor', 'ID no provedor', 'Forma', 'Aluno', 'Matrícula', 'Bruto', 'Taxa', 'Líquido', 'Parcela Check-in', 'Valor no Check-in', 'Situação', 'Detalhe'],
      ...rows.map((l) => [
        fdate(l.data), l.data_credito ? fdate(l.data_credito) : '', l.provedor_nome || 'Check-in', l.payment_id || '', l.forma || '', quem(l), l.matricula_id || '',
        num(l.origem === 'provedor' ? l.valor : null), num(l.taxa), num(l.liquido), l.parcela?.id || '', num(l.parcela?.valor), SITUACAO[l.status]?.label || l.status, l.motivo || l.nota || '',
      ]),
    ]);
  };

  const semProvedor = !loading && data && provedores.length === 0;

  return (
    <LayoutBase title="Conciliação bancária" pageHeader={false}>
      <div className="ck-fin ck-cb">
        <PageHeader
          title="Conciliação bancária"
          description="O que entrou no provedor de pagamento, com taxas e líquido, conferido com as baixas do Check-in."
          actions={(
            <>
              <Button icon="download" disabled={loading || !rows.length} onClick={exportCsv}>Exportar CSV</Button>
              <Button variant="primary" icon="refresh-cw" loading={loading} onClick={() => setReload((v) => v + 1)}>Atualizar</Button>
            </>
          )}
        />

        <section className="ck-card ck-fin__period" aria-label="Período">
          <SegmentedControl label="Período" options={PERIODS} value={period} onChange={setPeriod} />
          {period === 'custom' ? (
            <div className="ck-fin__dates">
              <TextField label="De" type="date" value={custom.from} max={custom.to || undefined} onChange={(e) => setCustom((c) => ({ ...c, from: e.target.value }))} />
              <TextField
                label="Até"
                type="date"
                value={custom.to}
                min={custom.from || undefined}
                onChange={(e) => setCustom((c) => ({ ...c, to: e.target.value }))}
                error={invalidRange ? (longRange ? 'Escolha um período de até 6 meses.' : 'A data final precisa ser igual ou posterior à inicial.') : undefined}
              />
            </div>
          ) : null}
          <p className="ck-fin__range">
            <Icon name="calendar" size={15} />
            <span>
              Pagamentos de <strong>{periodLabel}</strong>
              {provedores.length ? ` · ${provedores.map((p) => p.nome).join(' e ')}` : null}
            </span>
          </p>
        </section>

        {error ? <Callout tone="danger" title="Não foi possível carregar a conciliação">{error}</Callout> : null}
        {(data?.erros || []).map((e) => (
          <Callout key={e.provedor} tone="danger" title={`O ${e.provedor_nome} não respondeu`}>
            {`Os pagamentos dele ficaram de fora desta conciliação. ${e.erro}`}
          </Callout>
        ))}
        {data?.truncado ? <Callout tone="warning" title="Período com muitos pagamentos">Mostramos os primeiros 1.000 pagamentos de cada provedor. Escolha um período menor para ver tudo.</Callout> : null}
        {semProvedor ? (
          <Callout tone="info" title="Nenhum provedor de pagamento ativo" action={<Button size="sm" onClick={() => router.push('/configuracoes-pagamento')}>Configurar</Button>}>
            A conciliação compara o Asaas ou o Mercado Pago com o Check-in. Ative um provedor para começar.
          </Callout>
        ) : null}
        {!loading && resumo && !semProvedor ? (
          resumo.pendencias > 0 ? (
            <Callout
              tone="warning"
              title={`${resumo.pendencias} ${resumo.pendencias === 1 ? 'pendência para conferir' : 'pendências para conferir'}`}
              action={filter !== 'pendencias' ? <Button size="sm" onClick={() => setFilter('pendencias')}>Ver pendências</Button> : null}
            >
              {[
                resumo.contagem.sem_baixa ? `${resumo.contagem.sem_baixa} sem baixa no Check-in` : null,
                resumo.contagem.divergente ? `${resumo.contagem.divergente} com valor ou líquido diferente` : null,
                resumo.contagem.sem_recebimento ? `${resumo.contagem.sem_recebimento} baixa${resumo.contagem.sem_recebimento === 1 ? '' : 's'} sem recebimento no provedor` : null,
              ].filter(Boolean).join(' · ')}
            </Callout>
          ) : (
            <Callout tone="success" title="Tudo conciliado">
              {`Os ${prov.quantidade} pagamentos do período batem com as baixas do Check-in.`}
            </Callout>
          )
        ) : null}

        <div className="ck-kpis">
          <KpiCard emphasis tone="success" icon="landmark" label="Recebido (bruto)" value={loading ? '—' : money(prov.bruto)} hint={`${prov.quantidade} ${prov.quantidade === 1 ? 'pagamento' : 'pagamentos'} no provedor`} />
          <KpiCard tone="danger" icon="percent" label="Taxas" value={loading ? '—' : money(prov.taxas)} hint={prov.bruto ? `${(prov.taxas / prov.bruto * 100).toFixed(2).replace('.', ',')}% do bruto` : 'Sem pagamentos'} />
          <KpiCard tone="info" icon="wallet" label="Líquido" value={loading ? '—' : money(prov.liquido)} hint={prov.sem_liquido ? `${prov.sem_liquido} sem líquido informado` : 'Bruto menos taxas'} />
          <KpiCard tone="warning" icon="clock" label="A receber" value={loading ? '—' : money(prov.a_receber)} hint="Cartões confirmados com crédito futuro" />
        </div>

        <div className="ck-dash__row ck-dash__row--even">
          <section className="ck-card ck-dash__panel" aria-label="Provedor e Check-in">
            <header className="ck-dash__head">
              <div>
                <h2 className="ck-mt-box__title">Provedor e Check-in</h2>
                <p className="ck-dash__sub">O que o provedor recebeu e o que o Check-in baixou</p>
              </div>
            </header>
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : (
              <dl className="ck-cb-facts">
                {(resumo?.por_provedor || []).map((p) => (
                  <div key={p.provedor}>
                    <dt>{p.provedor_nome}<span>{p.quantidade} {p.quantidade === 1 ? 'pagamento' : 'pagamentos'}</span></dt>
                    <dd>{money(p.bruto)}<span>{`taxas ${money(p.taxas)} · líquido ${money(p.liquido)}`}</span></dd>
                  </div>
                ))}
                <div>
                  <dt>Baixas automáticas no Check-in<span>Parcelas pagas pela integração</span></dt>
                  <dd>{money(resumo?.checkin?.integracao)}</dd>
                </div>
                <div>
                  <dt>Recebido fora do provedor<span>{`${resumo?.checkin?.manual_quantidade || 0} ${resumo?.checkin?.manual_quantidade === 1 ? 'parcela' : 'parcelas'} em dinheiro, maquininha etc.`}</span></dt>
                  <dd>{money(resumo?.checkin?.manual)}</dd>
                </div>
              </dl>
            )}
          </section>
          <section className="ck-card ck-dash__panel" aria-label="Taxas por forma de pagamento">
            <header className="ck-dash__head">
              <div>
                <h2 className="ck-mt-box__title">Taxas por forma de pagamento</h2>
                <p className="ck-dash__sub">Quanto o provedor descontou em cada forma</p>
              </div>
            </header>
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p>
              : <BarList items={porForma} total={prov.taxas} unit={['pagamento', 'pagamentos']} empty="Nenhuma taxa no período." />}
          </section>
        </div>

        <section className="ck-card ck-ml__panel" aria-label="Pagamentos">
          <div className="ck-ml__toolbar">
            <div className="ck-control ck-ml__search">
              <Icon name="search" size={18} className="ck-control__icon" />
              <input type="search" className="ck-control__input" value={query} placeholder="Buscar por aluno, matrícula ou ID no provedor" aria-label="Buscar pagamentos" onChange={(e) => setQuery(e.target.value)} />
              {query ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setQuery('')} /> : null}
            </div>
            <div className="ck-ml__filters" role="radiogroup" aria-label="Filtrar por situação">
              {FILTERS.map(([key, label]) => (key === 'todos' || key === 'pendencias' || counts[key] ? (
                <button key={key} type="button" role="radio" aria-checked={filter === key} className="ck-ml__chip" onClick={() => setFilter(key)}>
                  {SITUACAO[key] ? <span className={`ck-ml__chipdot ck-ml__chipdot--${DOT[SITUACAO[key].tone]}`} aria-hidden /> : null}
                  {label}<span className="ck-ml__count">{counts[key] || 0}</span>
                </button>
              ) : null))}
            </div>
          </div>

          {loading ? <p role="status" className="ck-enrollment-state">Consultando o provedor…</p>
            : pageRows.length ? (
              <div className="ck-mt-table-wrap">
                <table className="ck-mt-table ck-ml__table ck-cb-table">
                  <thead>
                    <tr>
                      <th scope="col">Data</th>
                      <th scope="col">Aluno</th>
                      <th scope="col">Provedor</th>
                      <th scope="col" className="is-num">Bruto</th>
                      <th scope="col" className="is-num">Taxa</th>
                      <th scope="col" className="is-num">Líquido</th>
                      <th scope="col">Check-in</th>
                      <th scope="col">Situação</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pageRows.map((l) => {
                      const st = SITUACAO[l.status] || { label: l.status, tone: 'outline' };
                      const href = l.matricula_id ? `/matriculas/detalhe?id=${l.matricula_id}` : null;
                      const doProvedor = l.origem === 'provedor';
                      return (
                        <tr key={l.id} className={href ? 'ck-ml__row' : undefined} onClick={(e) => { if (href && !e.target.closest('a, button')) router.push(href); }}>
                          <td>
                            <span className="ck-mt-cell__main">{fdate(l.data)}</span>
                            {l.data_credito && doProvedor ? <span className="ck-mt-cell__sub">{l.a_receber ? `a receber ${fdate(l.data_credito)}` : `crédito ${fdate(l.data_credito)}`}</span> : null}
                          </td>
                          <td>
                            {href ? <a href={href} className="ck-ml__name" onClick={(e) => { e.preventDefault(); router.push(href); }}>{quem(l)}</a> : <span className="ck-mt-cell__main">{quem(l)}</span>}
                            <span className="ck-mt-cell__sub">{[l.matricula_id ? `Matrícula ${l.matricula_id}` : null, l.plano_nome].filter(Boolean).join(' · ') || '—'}</span>
                          </td>
                          <td>
                            <span className="ck-mt-cell__main ck-cb__prov">
                              {l.provedor_nome || 'Check-in'}
                              {l.invoice_url ? <IconButton icon="open-in-new" size="sm" label={`Abrir no ${l.provedor_nome}`} onClick={() => window.open(l.invoice_url, '_blank', 'noopener')} /> : null}
                            </span>
                            <span className="ck-mt-cell__sub">{[l.forma, l.antecipado ? 'antecipado' : null].filter(Boolean).join(' · ') || '—'}</span>
                          </td>
                          <td className="is-num"><span className="ck-mt-cell__main">{doProvedor ? money(l.valor) : '—'}</span></td>
                          <td className="is-num"><span className={`ck-mt-cell__main${l.taxa ? ' ck-cb__fee' : ''}`}>{l.taxa ? `−${money(l.taxa)}` : '—'}</span></td>
                          <td className="is-num"><span className="ck-mt-cell__main">{l.liquido != null ? money(l.liquido) : '—'}</span></td>
                          <td>
                            {l.parcela ? (
                              <>
                                <span className="ck-mt-cell__main">{`#${l.parcela.id} · ${money(l.parcela.valor)}`}</span>
                                <span className="ck-mt-cell__sub">{l.parcela.status_pagamento_id === 2 ? `pago ${fdate(l.parcela.data_pagamento)}` : 'em aberto'}</span>
                              </>
                            ) : <span className="ck-mt-cell__sub">Sem parcela</span>}
                          </td>
                          <td className="ck-cb__sit">
                            <Badge tone={st.tone} dot>{st.label}</Badge>
                            {l.motivo || l.nota ? <span className="ck-cb__why">{l.motivo || l.nota}</span> : null}
                            {l.pode_reprocessar ? (
                              <span className="ck-cb__acts">
                                <Button size="sm" variant={l.status === 'conciliado' ? 'ghost' : 'secondary'} icon="refresh-cw" loading={reprocessando === l.id} disabled={!!reprocessando} onClick={() => reprocessar(l)}>
                                  {l.status === 'sem_baixa' ? 'Dar baixa' : 'Atualizar líquido'}
                                </Button>
                              </span>
                            ) : null}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="ck-ml__empty">
                <span className="ck-mt-empty__icon" aria-hidden><Icon name={query ? 'search' : 'landmark'} size={20} /></span>
                <strong>{query ? `Nada encontrado para "${query}"` : (filter === 'pendencias' ? 'Nenhuma pendência no período' : 'Nenhum pagamento neste filtro')}</strong>
                <p>{query ? 'Confira a grafia ou busque pelo número da matrícula.' : 'Escolha outro filtro ou outro período.'}</p>
              </div>
            )}

          {!loading && rows.length ? (
            <div className="ck-ml__foot">
              <span>{`${rows.length} ${rows.length === 1 ? 'linha' : 'linhas'} · ${pct(rows.filter((l) => l.status === 'conciliado').length, rows.length)}% conciliado`}</span>
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
