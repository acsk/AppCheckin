import React, { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { financeiroService } from '../../services/financeiroService';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, Icon, IconButton, PageHeader, SegmentedControl, SelectField, TextField } from '../../components/ck';
import { BarList, KpiCard, fdate, isoDate, money, monthEnd, monthStart, pct } from '../../components/ck/financeiro';
import { showError, showSuccess } from '../../utils/toast';

const PAGE_SIZE = 25;
const MAX_DIAS = 186;
const PRESETS = [
  { value: 'mes', label: 'Este mês' },
  { value: 'mes-passado', label: 'Mês passado' },
  { value: '3m', label: '3 meses' },
  { value: 'custom', label: 'Entre datas' },
];

// Situação de cada linha (mesmos códigos do backend), com o que significa e o que fazer
const SITUACAO = {
  conciliado: {
    label: 'Conciliado', tone: 'success',
    texto: 'O pagamento do provedor tem uma parcela paga no Check-in com o mesmo valor (e o mesmo líquido, quando gravado). Nada a fazer.',
  },
  sem_baixa: {
    label: 'Sem baixa', tone: 'danger',
    texto: 'O provedor recebeu, mas a parcela do aluno não está paga no Check-in. Use "Dar baixa" (Asaas) ou dê a baixa na matrícula.',
  },
  divergente: {
    label: 'Divergente', tone: 'danger',
    texto: 'Casou com a parcela, mas o valor ou o líquido não batem. Confira no provedor; no Asaas, "Atualizar líquido" regrava a taxa e o líquido.',
  },
  sem_recebimento: {
    label: 'Sem recebimento', tone: 'warning',
    texto: 'A parcela foi baixada automaticamente, mas o provedor não mostra o pagamento no período. Confira se houve estorno ou se a baixa está certa.',
  },
  sem_vinculo: {
    label: 'Sem vínculo', tone: 'outline',
    texto: 'Entrou no provedor, mas não está ligado a nenhuma matrícula (cobrança criada direto no provedor, pacote). Não mexe nas parcelas.',
  },
  manual: {
    label: 'Baixa manual', tone: 'info',
    texto: 'Parcela paga direto na academia (dinheiro, Pix na conta, maquininha) e baixada à mão. Não passa pelo provedor: confira com o caixa.',
  },
};
const PENDENTES = ['divergente', 'sem_baixa', 'sem_recebimento'];
const CHIPS = [
  ['todos', 'Todos'],
  ['pendencias', 'Pendências'],
  ['conciliado', 'Conciliados'],
  ['sem_baixa', 'Sem baixa'],
  ['divergente', 'Divergentes'],
  ['sem_recebimento', 'Sem recebimento'],
  ['sem_vinculo', 'Sem vínculo'],
  ['manual', 'Baixa manual'],
];
const BAIXAS = [
  { value: 'todas', label: 'Todos os tipos' },
  { value: 'automatica', label: 'Automática (provedor)' },
  { value: 'manual', label: 'Manual' },
  { value: 'sem_baixa', label: 'Sem baixa' },
];

const titleCase = (n) => String(n || '').toLowerCase().replace(/(^|\s)(\p{L})/gu, (m, s, c) => s + c.toUpperCase()).replace(/\s(Da|De|Do|Das|Dos|E)\s/g, (m) => m.toLowerCase());
const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const num = (v) => (v == null ? '' : (Number(v) || 0).toFixed(2).replace('.', ','));
const quem = (l) => (l.aluno_nome ? titleCase(l.aluno_nome) : (l.descricao || (l.origem === 'provedor' ? 'Sem aluno ligado' : '—')));
const rotulo = (l) => (l.status === 'manual' ? (l.baixa_nome || 'Baixa manual') : SITUACAO[l.status]?.label || l.status);

function rangeOf(preset, now) {
  switch (preset) {
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

/** Totais das linhas filtradas (mesmas regras do resumo do backend). */
function resumir(linhas) {
  const r = {
    contagem: Object.fromEntries(Object.keys(SITUACAO).map((k) => [k, 0])),
    quantidade: 0, bruto: 0, taxas: 0, liquido: 0, aReceber: 0, semLiquido: 0,
    porProvedor: {}, porForma: {}, automatica: 0, manual: 0, manualQtd: 0, manualPorForma: {},
  };
  const parcelasAuto = {};
  linhas.forEach((l) => {
    r.contagem[l.status] = (r.contagem[l.status] || 0) + 1;
    if (l.baixa === 'automatica' && l.parcela) parcelasAuto[l.parcela.id] = Number(l.parcela.valor) || 0;
    if (l.status === 'manual') {
      r.manual += l.valor;
      r.manualQtd += 1;
      const f = l.forma || 'Não informada';
      r.manualPorForma[f] = r.manualPorForma[f] || { label: f, value: 0, count: 0 };
      r.manualPorForma[f].value += l.valor;
      r.manualPorForma[f].count += 1;
    }
    if (l.origem !== 'provedor' || l.fora_do_periodo || l.em_dinheiro) return;
    const liquido = l.liquido ?? l.valor;
    const taxa = l.taxa || 0;
    r.quantidade += 1;
    r.bruto += l.valor;
    r.taxas += taxa;
    r.liquido += liquido;
    if (l.liquido == null) r.semLiquido += 1;
    if (l.a_receber) r.aReceber += liquido;
    const p = r.porProvedor[l.provedor] || (r.porProvedor[l.provedor] = { provedor: l.provedor, nome: l.provedor_nome, quantidade: 0, bruto: 0, taxas: 0, liquido: 0 });
    p.quantidade += 1;
    p.bruto += l.valor;
    p.taxas += taxa;
    p.liquido += liquido;
    const f = l.forma || 'Outros';
    const pf = r.porForma[f] || (r.porForma[f] = { label: f, value: 0, count: 0 });
    pf.value += taxa;
    pf.count += 1;
  });
  r.automatica = Object.values(parcelasAuto).reduce((a, v) => a + v, 0);
  r.pendencias = PENDENTES.reduce((a, k) => a + (r.contagem[k] || 0), 0);
  r.porProvedor = Object.values(r.porProvedor);
  r.porForma = Object.values(r.porForma).filter((f) => f.value > 0).sort((a, b) => b.value - a.value);
  r.manualPorForma = Object.values(r.manualPorForma).sort((a, b) => b.value - a.value);
  return r;
}

function SituacaoBadge({ linha }) {
  const st = SITUACAO[linha.status] || { tone: 'outline', texto: '' };
  return <span title={st.texto}><Badge tone={st.tone} dot>{rotulo(linha)}</Badge></span>;
}

export default function ConciliacaoBancariaScreen() {
  const router = useRouter();
  const now = new Date();

  const [preset, setPreset] = useState('mes');
  const [range, setRange] = useState(() => {
    const [a, b] = rangeOf('mes', now);
    return { from: isoDate(a), to: isoDate(b) };
  });
  const [provedor, setProvedor] = useState('todos');
  const [baixa, setBaixa] = useState('todas');
  const [filter, setFilter] = useState('todos');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [legenda, setLegenda] = useState(false);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reload, setReload] = useState(0);
  const [reprocessando, setReprocessando] = useState(null);

  const escolherPreset = (v) => {
    setPreset(v);
    if (v === 'custom') return;
    const [a, b] = rangeOf(v, now);
    setRange({ from: isoDate(a), to: isoDate(b) });
  };
  const mudarData = (campo, valor) => {
    setPreset('custom');
    setRange((r) => ({ ...r, [campo]: valor }));
  };

  const { from, to } = range;
  const dias = from && to ? (new Date(`${to}T00:00:00`) - new Date(`${from}T00:00:00`)) / 86400000 : 0;
  const rangeError = !from || !to ? 'Informe as duas datas.'
    : from > to ? 'A data final precisa ser igual ou posterior à inicial.'
      : dias > MAX_DIAS ? 'Escolha um período de até 6 meses.' : '';

  useEffect(() => {
    if (rangeError) return undefined;
    let active = true;
    setLoading(true);
    setError('');
    financeiroService.conciliacao({ dataInicio: from, dataFim: to })
      .then((res) => { if (active) setData(res); })
      .catch((err) => { if (active) { setData(null); setError(err?.mensagemLimpa || err?.error || 'Não foi possível carregar a conciliação.'); } })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [from, to, rangeError, reload]);

  useEffect(() => { setPage(1); }, [from, to, filter, query, provedor, baixa]);

  const provedores = data?.provedores || [];
  const opcoesProvedor = [
    { value: 'todos', label: 'Todos' },
    ...provedores.map((p) => ({ value: p.id, label: p.nome })),
    { value: 'checkin', label: 'Sem provedor (baixa manual)' },
  ];

  // Provedor e tipo de baixa recortam tudo: KPIs, painéis, chips e lista
  const base = useMemo(() => (data?.linhas || []).filter((l) => (
    (provedor === 'todos' || (provedor === 'checkin' ? l.origem === 'checkin' : l.provedor === provedor))
    && (baixa === 'todas' || l.baixa === baixa)
  )), [data, provedor, baixa]);
  const res = useMemo(() => resumir(base), [base]);

  const searched = useMemo(() => {
    const q = norm(query.trim());
    if (!q) return base;
    return base.filter((l) => norm(`${l.aluno_nome} ${l.descricao} ${l.payment_id} ${l.matricula_id} ${l.plano_nome} ${l.forma}`).includes(q));
  }, [base, query]);
  const counts = useMemo(() => {
    const c = { todos: searched.length, pendencias: 0 };
    Object.keys(SITUACAO).forEach((k) => { c[k] = 0; });
    searched.forEach((l) => { c[l.status] += 1; if (PENDENTES.includes(l.status)) c.pendencias += 1; });
    return c;
  }, [searched]);
  const rows = useMemo(() => searched.filter((l) => filter === 'todos' || (filter === 'pendencias' ? PENDENTES.includes(l.status) : l.status === filter)), [searched, filter]);
  const pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
  const pageRows = rows.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  const filtrado = provedor !== 'todos' || baixa !== 'todas';
  const periodLabel = from && to ? `${fdate(from)} a ${fdate(to)}` : '—';

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
      ['Data', 'Crédito', 'Provedor', 'ID no provedor', 'Forma', 'Tipo de baixa', 'Aluno', 'Matrícula', 'Bruto', 'Taxa', 'Líquido', 'Parcela Check-in', 'Valor no Check-in', 'Situação', 'Detalhe'],
      ...rows.map((l) => [
        fdate(l.data), l.data_credito ? fdate(l.data_credito) : '', l.provedor_nome || '', l.payment_id || '', l.forma || '', l.baixa_nome || '', quem(l), l.matricula_id || '',
        num(l.origem === 'provedor' ? l.valor : null), num(l.taxa), num(l.liquido), l.parcela?.id || '', num(l.parcela?.valor), rotulo(l), l.motivo || l.nota || '',
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

        <section className="ck-card ck-fin__period ck-cb-filters" aria-label="Período e filtros">
          <SegmentedControl label="Período" options={PRESETS} value={preset} onChange={escolherPreset} />
          <div className="ck-cb-filters__grid">
            <TextField label="De" type="date" value={from} max={to || undefined} onChange={(e) => mudarData('from', e.target.value)} />
            <TextField label="Até" type="date" value={to} min={from || undefined} onChange={(e) => mudarData('to', e.target.value)} error={rangeError || undefined} />
            <SelectField label="Provedor" value={provedor} options={opcoesProvedor} onChange={(e) => setProvedor(e.target.value)} />
            <SelectField label="Tipo de baixa" value={baixa} options={BAIXAS} onChange={(e) => setBaixa(e.target.value)} />
          </div>
          <p className="ck-fin__range">
            <Icon name="calendar" size={15} />
            <span>
              Pagamentos de <strong>{periodLabel}</strong>
              {provedores.length ? ` · ${provedores.map((p) => p.nome).join(' e ')}` : null}
              {filtrado ? (
                <>
                  {' · '}
                  <button type="button" className="ck-cb__link" onClick={() => { setProvedor('todos'); setBaixa('todas'); }}>limpar filtros</button>
                </>
              ) : null}
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
        {!loading && data && !semProvedor ? (
          res.pendencias > 0 ? (
            <Callout
              tone="warning"
              title={`${res.pendencias} ${res.pendencias === 1 ? 'pendência para conferir' : 'pendências para conferir'}`}
              action={filter !== 'pendencias' ? <Button size="sm" onClick={() => setFilter('pendencias')}>Ver pendências</Button> : null}
            >
              {[
                res.contagem.sem_baixa ? `${res.contagem.sem_baixa} sem baixa no Check-in` : null,
                res.contagem.divergente ? `${res.contagem.divergente} com valor ou líquido diferente` : null,
                res.contagem.sem_recebimento ? `${res.contagem.sem_recebimento} baixa${res.contagem.sem_recebimento === 1 ? '' : 's'} sem recebimento no provedor` : null,
              ].filter(Boolean).join(' · ')}
            </Callout>
          ) : (
            <Callout tone="success" title={filtrado ? 'Nenhuma pendência neste filtro' : 'Tudo conciliado'}>
              {`Os ${res.quantidade} pagamentos ${filtrado ? 'do filtro' : 'do período'} batem com as baixas do Check-in.`}
            </Callout>
          )
        ) : null}

        <div className="ck-kpis">
          <KpiCard emphasis tone="success" icon="landmark" label="Recebido (bruto)" value={loading ? '—' : money(res.bruto)} hint={`${res.quantidade} ${res.quantidade === 1 ? 'pagamento' : 'pagamentos'} no provedor`} />
          <KpiCard tone="danger" icon="percent" label="Taxas" value={loading ? '—' : money(res.taxas)} hint={res.bruto ? `${(res.taxas / res.bruto * 100).toFixed(2).replace('.', ',')}% do bruto` : 'Sem pagamentos'} />
          <KpiCard tone="info" icon="wallet" label="Líquido" value={loading ? '—' : money(res.liquido)} hint={res.semLiquido ? `${res.semLiquido} sem líquido informado` : 'Bruto menos taxas'} />
          <KpiCard tone="warning" icon="clock" label="A receber" value={loading ? '—' : money(res.aReceber)} hint="Cartões confirmados com crédito futuro" />
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
                {res.porProvedor.map((p) => (
                  <div key={p.provedor}>
                    <dt>{p.nome}<span>{p.quantidade} {p.quantidade === 1 ? 'pagamento' : 'pagamentos'}</span></dt>
                    <dd>{money(p.bruto)}<span>{`taxas ${money(p.taxas)} · líquido ${money(p.liquido)}`}</span></dd>
                  </div>
                ))}
                <div>
                  <dt>Baixas automáticas<span>Parcelas pagas pela integração com o provedor</span></dt>
                  <dd>{money(res.automatica)}</dd>
                </div>
                <div>
                  <dt>Baixas manuais<span>{`${res.manualQtd} ${res.manualQtd === 1 ? 'parcela baixada' : 'parcelas baixadas'} à mão, fora do provedor`}</span></dt>
                  <dd>{money(res.manual)}</dd>
                </div>
              </dl>
            )}
          </section>
          <section className="ck-card ck-dash__panel" aria-label="Taxas e baixas manuais por forma">
            <header className="ck-dash__head">
              <div>
                <h2 className="ck-mt-box__title">Por forma de pagamento</h2>
                <p className="ck-dash__sub">Taxas do provedor e baixas manuais</p>
              </div>
            </header>
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : (
              <div className="ck-cb-bars">
                <h3>Taxas do provedor</h3>
                <BarList items={res.porForma} total={res.taxas} unit={['pagamento', 'pagamentos']} empty="Nenhuma taxa no período." />
                <h3>Baixas manuais</h3>
                <BarList items={res.manualPorForma} total={res.manual} empty="Nenhuma baixa manual no período." />
              </div>
            )}
          </section>
        </div>

        <section className="ck-card ck-ml__panel" aria-label="Pagamentos">
          <div className="ck-ml__toolbar">
            <div className="ck-cb-toolbar__top">
              <div className="ck-control ck-ml__search">
                <Icon name="search" size={18} className="ck-control__icon" />
                <input type="search" className="ck-control__input" value={query} placeholder="Buscar por aluno, matrícula ou ID no provedor" aria-label="Buscar pagamentos" onChange={(e) => setQuery(e.target.value)} />
                {query ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setQuery('')} /> : null}
              </div>
              <Button size="sm" variant="ghost" icon="help-circle" aria-expanded={legenda} onClick={() => setLegenda((v) => !v)}>
                O que significa cada situação
              </Button>
            </div>
            {legenda ? (
              <dl className="ck-cb-legend">
                {Object.entries(SITUACAO).map(([k, st]) => (
                  <div key={k}>
                    <dt><Badge tone={st.tone} dot>{st.label}</Badge></dt>
                    <dd>{st.texto}</dd>
                  </div>
                ))}
              </dl>
            ) : null}
            <div className="ck-ml__filters" role="radiogroup" aria-label="Filtrar por situação">
              {CHIPS.map(([key, label]) => (key === 'todos' || key === 'pendencias' || counts[key] ? (
                <button key={key} type="button" role="radio" aria-checked={filter === key} className="ck-ml__chip" title={SITUACAO[key]?.texto} onClick={() => setFilter(key)}>
                  {SITUACAO[key] ? <span className={`ck-ml__chipdot ck-ml__chipdot--${SITUACAO[key].tone}`} aria-hidden /> : null}
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
                      <th scope="col">Origem</th>
                      <th scope="col" className="is-num">Bruto</th>
                      <th scope="col" className="is-num">Taxa</th>
                      <th scope="col" className="is-num">Líquido</th>
                      <th scope="col">Check-in</th>
                      <th scope="col">Situação</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pageRows.map((l) => {
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
                              {doProvedor ? l.provedor_nome : l.baixa_nome}
                              {l.invoice_url ? <IconButton icon="open-in-new" size="sm" label={`Abrir no ${l.provedor_nome}`} onClick={() => window.open(l.invoice_url, '_blank', 'noopener')} /> : null}
                            </span>
                            <span className="ck-mt-cell__sub">{[l.forma || 'Forma não informada', l.antecipado ? 'antecipado' : null].filter(Boolean).join(' · ')}</span>
                          </td>
                          <td className="is-num">
                            <span className="ck-mt-cell__main">{money(l.valor)}</span>
                            {doProvedor ? null : <span className="ck-mt-cell__sub">no Check-in</span>}
                          </td>
                          <td className="is-num"><span className={`ck-mt-cell__main${l.taxa ? ' ck-cb__fee' : ''}`}>{l.taxa ? `−${money(l.taxa)}` : '—'}</span></td>
                          <td className="is-num"><span className="ck-mt-cell__main">{l.liquido != null ? money(l.liquido) : '—'}</span></td>
                          <td>
                            {l.parcela ? (
                              <>
                                <span className="ck-mt-cell__main">{`#${l.parcela.id} · ${money(l.parcela.valor)}`}</span>
                                <span className="ck-mt-cell__sub">{l.parcela.status_pagamento_id === 2 ? `pago ${fdate(l.parcela.data_pagamento)}` : 'em aberto'}</span>
                                {l.parcela.status_pagamento_id === 2 ? <span className="ck-mt-cell__sub">{l.baixa === 'automatica' ? 'baixa automática' : 'baixa manual'}</span> : null}
                              </>
                            ) : <span className="ck-mt-cell__sub">Sem parcela</span>}
                          </td>
                          <td className="ck-cb__sit">
                            <SituacaoBadge linha={l} />
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
