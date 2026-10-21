import React, { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { financeiroService } from '../../services/financeiroService';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, Icon, IconButton, PageHeader, SegmentedControl, SelectField, TextField } from '../../components/ck';
import { fdate, isoDate, money, monthEnd, monthStart } from '../../components/ck/financeiro';
import { showError, showSuccess } from '../../utils/toast';
import { gerarPdfConciliacao } from '../../utils/relatorioConciliacaoPdf';

const PAGE_SIZE = 25;
const MAX_DIAS = 186;
const PRESETS = [
  { value: 'mes', label: 'Este mês' },
  { value: 'mes-passado', label: 'Mês passado' },
  { value: '3m', label: '3 meses' },
  { value: 'custom', label: 'Entre datas' },
];

// Situação (mesmos códigos do backend), com o que significa e o que fazer
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
  nao_conferido: {
    label: 'Não conferido', tone: 'outline',
    texto: 'Baixa automática de um provedor que não pôde ser consultado (sem credencial salva ou fora do ar). Não é uma pendência: só não dá para conferir aqui.',
  },
  manual: {
    label: 'Baixa manual', tone: 'info',
    texto: 'Parcela paga direto na academia (dinheiro, Pix na conta, maquininha) e baixada à mão. Não passa pelo provedor: confira com o caixa.',
  },
};
// A situação do grupo é a mais grave entre as linhas dele
const GRAVIDADE = ['sem_baixa', 'divergente', 'sem_recebimento', 'nao_conferido', 'sem_vinculo', 'manual', 'conciliado'];
const PENDENTES = ['divergente', 'sem_baixa', 'sem_recebimento'];
const CHIPS = [
  ['todos', 'Todos'],
  ['pendencias', 'Pendências'],
  ['conciliado', 'Conciliados'],
  ['sem_baixa', 'Sem baixa'],
  ['divergente', 'Divergentes'],
  ['sem_recebimento', 'Sem recebimento'],
  ['sem_vinculo', 'Sem vínculo'],
  ['nao_conferido', 'Não conferido'],
  ['manual', 'Baixa manual'],
];
const BAIXAS = [
  { value: 'todas', label: 'Todas as baixas' },
  { value: 'automatica', label: 'Automáticas' },
  { value: 'manual', label: 'Manuais' },
  { value: 'sem_baixa', label: 'Sem baixa' },
];
const COLS = [26, 12, 10, 9, 10, 7, 10, 16];

const cx = (...a) => a.filter(Boolean).join(' ');
const titleCase = (n) => String(n || '').toLowerCase().replace(/(^|\s)(\p{L})/gu, (m, s, c) => s + c.toUpperCase()).replace(/\s(Da|De|Do|Das|Dos|E)\s/g, (m) => m.toLowerCase());
const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const num = (v) => (v == null ? '' : (Number(v) || 0).toFixed(2).replace('.', ','));
const plural = (n, um, varios) => `${n} ${n === 1 ? um : varios}`;
const curto = (iso) => fdate(iso).slice(0, 5);
const quem = (l) => (l.aluno_nome ? titleCase(l.aluno_nome) : (l.descricao || (l.origem === 'provedor' ? 'Sem aluno ligado' : '—')));
const rotulo = (st, g) => (st === 'manual' && g?.baixaNome && g.baixaNome !== 'Baixa manual' ? g.baixaNome : SITUACAO[st]?.label || st);
// Entra nos totais do provedor: pagamento do período, que não foi recebido em dinheiro
const noPeriodo = (l) => l.origem === 'provedor' && !l.fora_do_periodo && !l.em_dinheiro;

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

/** Junta as linhas do backend por parcela do Check-in: a parcela em cima, os pagamentos do provedor por baixo. */
function agrupar(linhas) {
  const grupos = new Map();
  linhas.forEach((l) => {
    const key = l.parcela ? `p${l.parcela.id}` : l.id;
    if (!grupos.has(key)) grupos.set(key, { key, linhas: [] });
    grupos.get(key).linhas.push(l);
  });
  return [...grupos.values()].map((g) => {
    const ls = g.linhas;
    const ref = ls.find((l) => l.aluno_nome) || ls[0];
    const pays = ls.filter((l) => l.origem === 'provedor');
    const parcela = ls.find((l) => l.parcela)?.parcela || null;
    const status = GRAVIDADE.find((s) => ls.some((l) => l.status === s)) || ls[0].status;
    const t = pays.reduce((s, l) => {
      const liquido = l.liquido ?? l.valor;
      s.gross += l.valor;
      s.fee += l.taxa || 0;
      s.net += liquido;
      if (l.a_receber) s.rec += liquido;
      if (noPeriodo(l)) {
        s.grossP += l.valor; s.feeP += l.taxa || 0; s.netP += liquido; s.paysP += 1;
        if (l.a_receber) s.recP += liquido;
      }
      return s;
    }, { gross: 0, fee: 0, net: 0, rec: 0, grossP: 0, feeP: 0, netP: 0, recP: 0, paysP: 0 });
    const paga = parcela?.status_pagamento_id === 2;
    let notas = [...new Set(ls.filter((l) => l.status === status || l.status === 'conciliado').map((l) => l.motivo || l.nota).filter(Boolean))];
    // Cartão parcelado: uma nota por parcela do cartão diz a mesma coisa; fica uma só
    const doCartao = notas.filter((n) => /cartão parcelado/.test(n));
    if (doCartao.length > 1) notas = [...notas.filter((n) => !doCartao.includes(n)), `Cartão parcelado em ${doCartao.length} vezes; no Check-in a parcela é o valor cheio.`];
    return {
      key: g.key,
      linhas: ls,
      pays,
      status,
      student: quem(ref),
      matriculaId: ref.matricula_id,
      plano: ref.plano_nome,
      parcela,
      paga,
      baixa: ls[0].baixa,
      baixaNome: ls.find((l) => l.origem === 'checkin')?.baixa_nome || ls[0].baixa_nome,
      forma: parcela?.forma || ls.find((l) => l.origem === 'checkin')?.forma || null,
      provedores: [...new Set(pays.map((l) => l.provedor))],
      t,
      diff: pays.length && paga ? t.gross - parcela.valor : null,
      ...resultadoDaTaxa(parcela, pays, t),
      notas,
      reprocessaveis: pays.filter((l) => l.pode_reprocessar),
      busca: norm([ref.aluno_nome, ref.descricao, ref.matricula_id, parcela?.id, ref.plano_nome, ...ls.map((l) => `${l.payment_id || ''} ${l.forma || ''}`)].join(' ')),
    };
  });
}

/**
 * Quanto a parcela rendeu frente à mensalidade: líquido que caiu na conta menos o valor do plano.
 * Pix e boleto costumam ficar abaixo (a taxa sai da academia); no cartão com acréscimo (tarifa_cartao,
 * já somado ao valor da parcela) o aluno paga a taxa e a sobra vira ganho.
 */
function resultadoDaTaxa(parcela, pays, t) {
  if (!parcela || !pays.length || pays.some((l) => l.liquido == null)) return { resultado: null };
  const acrescimo = Number(parcela.acrescimo) || 0;
  const mensalidade = Math.round((parcela.valor - acrescimo) * 100) / 100;
  return { mensalidade, acrescimo, resultado: Math.round((t.net - mensalidade) * 100) / 100 };
}

/** Totais da faixa de resumo, sobre os grupos filtrados. */
function resumir(grupos) {
  const r = {
    gross: 0, fees: 0, net: 0, receivable: 0, payments: 0, semLiquido: 0, auto: 0, autoCount: 0, manual: 0, manualCount: 0, naoConferido: 0,
    taxa: {
      parcelas: 0, mensalidades: 0, liquido: 0, taxas: 0, acrescimos: 0, resultado: 0, perdas: 0, perdaQtd: 0, ganhos: 0, ganhoQtd: 0,
      // Ponte entre "Recebido no provedor" (pagamentos do período) e as parcelas comparadas.
      brutoComparado: 0, pagsComparados: 0, semLiquido: { qtd: 0, bruto: 0 }, foraPeriodo: { qtd: 0, bruto: 0 },
    },
  };
  grupos.forEach((g) => {
    if (g.resultado == null) {
      if (g.parcela) { r.taxa.semLiquido.qtd += g.t.paysP; r.taxa.semLiquido.bruto += g.t.grossP; }
    } else {
      const x = r.taxa;
      x.brutoComparado += g.t.grossP;
      x.pagsComparados += g.t.paysP;
      // Parcela comparada com pagamento fora do período (ex.: cartão parcelado): entra na mensalidade, não no recebido.
      if (g.t.paysP < g.pays.length) { x.foraPeriodo.qtd += g.pays.length - g.t.paysP; x.foraPeriodo.bruto += g.t.gross - g.t.grossP; }
      x.parcelas += 1;
      x.mensalidades += g.mensalidade;
      x.liquido += g.t.net;
      x.taxas += g.t.fee;
      x.acrescimos += g.acrescimo;
      x.resultado += g.resultado;
      if (g.resultado < -0.004) { x.perdas += g.resultado; x.perdaQtd += 1; }
      if (g.resultado > 0.004) { x.ganhos += g.resultado; x.ganhoQtd += 1; }
    }
    r.gross += g.t.grossP;
    r.fees += g.t.feeP;
    r.net += g.t.netP;
    r.receivable += g.t.recP;
    r.payments += g.t.paysP;
    r.semLiquido += g.pays.filter((l) => noPeriodo(l) && l.liquido == null).length;
    if (g.status === 'nao_conferido') r.naoConferido += 1;
    if (!g.paga) return;
    if (g.baixa === 'automatica') { r.auto += g.parcela.valor; r.autoCount += 1; }
    if (g.baixa === 'manual') { r.manual += g.parcela.valor; r.manualCount += 1; }
  });
  return r;
}

/** Explica por que "Recebido no provedor" e "Mensalidades" não batem: o que ficou fora da comparação. */
function PonteRecebido({ sm }) {
  const x = sm.taxa;
  const extra = x.brutoComparado - x.mensalidades;
  if (Math.abs(sm.gross - x.mensalidades) < 0.005) return null;
  return (
    <div className="ck-cb__ponte">
      <p className="ck-cb__ponteh">{`Recebido no provedor (${money(sm.gross)}) × mensalidades comparadas (${money(x.mensalidades)}): diferença de ${money(sm.gross - x.mensalidades)}`}</p>
      <ul>
        <li><span>{`${plural(x.pagsComparados, 'pagamento', 'pagamentos')} de ${plural(x.parcelas, 'parcela comparada', 'parcelas comparadas')}`}</span><b>{money(x.brutoComparado)}</b></li>
        {Math.abs(extra) >= 0.005 ? (
          <li className="is-sub"><span>{extra > 0 ? 'cobrado acima da mensalidade (acréscimo do cartão ou valor diferente do plano)' : 'recebido abaixo da mensalidade (desconto ou pagamento fora do período)'}</span><b>{`${extra > 0 ? '+' : '−'}${money(Math.abs(extra))}`}</b></li>
        ) : null}
        {x.semLiquido.qtd ? (
          <li className="is-out"><span>{`${plural(x.semLiquido.qtd, 'pagamento', 'pagamentos')} sem líquido informado pelo provedor, fora da comparação`}</span><b>{money(x.semLiquido.bruto)}</b></li>
        ) : null}
        {x.foraPeriodo.qtd ? (
          <li className="is-sub"><span>{`${plural(x.foraPeriodo.qtd, 'pagamento', 'pagamentos')} de parcelas comparadas caem fora do período (não somam no recebido)`}</span><b>{money(x.foraPeriodo.bruto)}</b></li>
        ) : null}
      </ul>
    </div>
  );
}

function SituacaoBadge({ status, grupo }) {
  const st = SITUACAO[status] || { tone: 'outline', texto: '' };
  return <span title={st.texto}><Badge tone={st.tone} dot>{rotulo(status, grupo)}</Badge></span>;
}

function Resultado({ grupo: g }) {
  if (g.resultado == null) return <span className="ck-cb__dash">—</span>;
  const perda = g.resultado < -0.004;
  const ganho = g.resultado > 0.004;
  return (
    <>
      <span className={perda ? 'ck-cb__loss' : ganho ? 'ck-cb__gain' : 'ck-cb__dash'}>{perda || ganho ? `${ganho ? '+' : '−'}${num(Math.abs(g.resultado))}` : '0,00'}</span>
      <span className="ck-cb__sub ck-cb__wrapsub">{g.acrescimo ? `acréscimo ${num(g.acrescimo)}` : `plano ${num(g.mensalidade)}`}</span>
    </>
  );
}

function Diferenca({ value }) {
  if (value == null) return <span className="ck-cb__dash">—</span>;
  if (Math.abs(value) < 0.005) return <span className="ck-cb__dash">0,00</span>;
  return `${value > 0 ? '+' : '−'}${num(Math.abs(value))}`;
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
  const [open, setOpen] = useState({});
  const [all, setAll] = useState(false);
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
  const mudarData = (campo, valor) => setRange((r) => ({ ...r, [campo]: valor }));

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
    { value: 'todos', label: 'Todos os provedores' },
    ...provedores.map((p) => ({ value: p.id, label: p.ativo === false ? `${p.nome} (desativado)` : p.nome })),
    { value: 'checkin', label: 'Sem provedor (baixa manual)' },
  ];

  // Provedor e tipo de baixa recortam tudo: resumo, chips e tabela
  const grupos = useMemo(() => agrupar(data?.linhas || []).filter((g) => (
    (provedor === 'todos' || (provedor === 'checkin' ? !g.pays.length : g.provedores.includes(provedor)))
    && (baixa === 'todas' || g.baixa === baixa)
  )), [data, provedor, baixa]);
  const sm = useMemo(() => resumir(grupos), [grupos]);

  const searched = useMemo(() => {
    const q = norm(query.trim().replace('#', ''));
    return q ? grupos.filter((g) => g.busca.includes(q)) : grupos;
  }, [grupos, query]);
  const counts = useMemo(() => {
    const c = { todos: searched.length, pendencias: 0 };
    Object.keys(SITUACAO).forEach((k) => { c[k] = 0; });
    searched.forEach((g) => { c[g.status] += 1; if (PENDENTES.includes(g.status)) c.pendencias += 1; });
    return c;
  }, [searched]);
  const list = useMemo(() => searched.filter((g) => filter === 'todos' || (filter === 'pendencias' ? PENDENTES.includes(g.status) : g.status === filter)), [searched, filter]);
  const pages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
  const pageList = list.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
  const tot = list.reduce((s, g) => {
    s.gross += g.t.grossP; s.fee += g.t.feeP; s.net += g.t.netP; s.pays += g.t.paysP;
    if (g.paga) s.inv += g.parcela.valor;
    return s;
  }, { gross: 0, fee: 0, net: 0, inv: 0, pays: 0 });

  const filtrado = provedor !== 'todos' || baixa !== 'todas';
  const periodLabel = from && to ? `${fdate(from)} a ${fdate(to)}` : '—';
  const pctTaxa = sm.gross ? `${((sm.fees / sm.gross) * 100).toFixed(2).replace('.', ',')}% do bruto` : null;
  const isOpen = (g) => (open[g.key] != null ? open[g.key] : all);
  const toggle = (g) => setOpen((o) => ({ ...o, [g.key]: !isOpen(g) }));
  const abrirMatricula = (g) => router.push(`/matriculas/detalhe?id=${g.matriculaId}`);

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
  const BotaoReprocessar = ({ linha, grupo }) => (
    <Button
      size="sm"
      variant={grupo.status === 'conciliado' ? 'ghost' : 'secondary'}
      icon="refresh-cw"
      loading={reprocessando === linha.id}
      disabled={!!reprocessando}
      onClick={() => reprocessar(linha)}
    >
      {linha.status === 'sem_baixa' ? 'Dar baixa' : 'Atualizar líquido'}
    </Button>
  );

  const exportCsv = () => {
    const rows = list.flatMap((g) => g.linhas);
    downloadCsv(`conciliacao_${from}_${to}.csv`, [
      ['Data', 'Crédito', 'Provedor', 'ID no provedor', 'Forma', 'Tipo de baixa', 'Aluno', 'Matrícula', 'Bruto', 'Taxa', 'Líquido', 'Parcela Check-in', 'Valor no Check-in', 'Mensalidade', 'Acréscimo cobrado', 'Ganho/perda', 'Situação', 'Detalhe'],
      ...rows.map((l) => [
        fdate(l.data), l.data_credito ? fdate(l.data_credito) : '', l.provedor_nome || '', l.payment_id || '', l.forma || '', l.baixa_nome || '', quem(l), l.matricula_id || '',
        num(l.origem === 'provedor' ? l.valor : null), num(l.taxa), num(l.liquido), l.parcela?.id || '', num(l.parcela?.valor),
        ...(() => { const g = list.find((x) => x.linhas.includes(l)); return g?.resultado != null && g.linhas.find((y) => y.origem === 'provedor') === l ? [num(g.mensalidade), num(g.acrescimo), num(g.resultado)] : ['', '', '']; })(),
        l.status === 'manual' ? (l.baixa_nome || 'Baixa manual') : SITUACAO[l.status]?.label || l.status, l.motivo || l.nota || '',
      ]),
    ]);
  };

  const exportPdf = () => {
    try {
      gerarPdfConciliacao({
        grupos: list,
        resumo: resumir(list),
        academia: data?.academia,
        inicio: from,
        fim: to,
        provedor: opcoesProvedor.find((o) => o.value === provedor)?.label.replace('Todos os provedores', 'Todos') || 'Todos',
      });
    } catch (err) {
      showError('Não foi possível gerar o PDF.');
    }
  };

  const semProvedor = !loading && data && provedores.length === 0;

  const baixaCell = (g) => {
    if (!g.parcela) return <><span className="ck-cb__dash">—</span><span className="ck-cb__sub">Sem parcela no Check-in</span></>;
    const p = g.parcela;
    const sub = g.paga
      ? [`${g.baixa === 'manual' ? (g.baixaNome || 'Manual').replace(/^Baixa /, '').replace(/^./, (c) => c.toUpperCase()) : 'Automática'} em ${curto(p.data_pagamento)}`, g.forma].filter(Boolean).join(', ')
      : `Em aberto${p.data_vencimento ? `, vence ${curto(p.data_vencimento)}` : ''}`;
    return <><span className="ck-cb__main">{money(p.valor)}</span><span className="ck-cb__sub">{sub}</span></>;
  };

  return (
    <LayoutBase title="Conciliação bancária" pageHeader={false}>
      <div className="ck-cb">
        <PageHeader
          title="Conciliação bancária"
          description="O que entrou no provedor, com taxas e líquido, conferido parcela por parcela com as baixas do Check-in."
          actions={(
            <>
              <Button icon="download" disabled={loading || !list.length} onClick={exportCsv}>Exportar CSV</Button>
              <Button icon="file-text" disabled={loading || !list.length} onClick={exportPdf}>Exportar PDF</Button>
              <Button icon="refresh-cw" loading={loading} onClick={() => setReload((v) => v + 1)}>{loading ? 'Atualizando…' : 'Atualizar'}</Button>
            </>
          )}
        />

        <div className="ck-cb__filters">
          <SegmentedControl label="Período" options={PRESETS} value={preset} onChange={escolherPreset} />
          {preset === 'custom' ? (
            <div className="ck-cb__dates">
              <TextField label="De" type="date" value={from} max={to || undefined} onChange={(e) => mudarData('from', e.target.value)} />
              <TextField label="Até" type="date" value={to} min={from || undefined} onChange={(e) => mudarData('to', e.target.value)} error={rangeError || undefined} />
            </div>
          ) : null}
          <SelectField label="Provedor" value={provedor} options={opcoesProvedor} onChange={(e) => setProvedor(e.target.value)} />
          <SelectField label="Tipo de baixa" value={baixa} options={BAIXAS} onChange={(e) => setBaixa(e.target.value)} />
          <span className="ck-cb__period">
            <Icon name="calendar" size={15} />
            {periodLabel}
            {filtrado ? <button type="button" className="ck-linkbtn" onClick={() => { setProvedor('todos'); setBaixa('todas'); }}>Limpar filtros</button> : null}
          </span>
        </div>

        {error ? <Callout tone="danger" title="Não foi possível carregar a conciliação">{error}</Callout> : null}
        {(data?.erros || []).map((e) => (
          <Callout key={e.provedor} tone="danger" title={`O ${e.provedor_nome} não respondeu`}>
            {`Os pagamentos dele ficaram de fora desta conciliação. ${e.erro}`}
          </Callout>
        ))}
        {data?.truncado ? <Callout tone="warning" title="Período com muitos pagamentos">Mostramos os primeiros 1.000 pagamentos de cada provedor. Escolha um período menor para ver tudo.</Callout> : null}
        {semProvedor ? (
          <Callout tone="info" title="Nenhum provedor de pagamento conectado" action={<Button size="sm" onClick={() => router.push('/configuracoes-pagamento')}>Configurar</Button>}>
            A conciliação compara o Asaas ou o Mercado Pago com o Check-in. Salve a credencial de um provedor para começar.
          </Callout>
        ) : null}
        {!loading && sm.naoConferido ? (
          <Callout tone="info" title={`${plural(sm.naoConferido, 'baixa automática não pôde ser conferida', 'baixas automáticas não puderam ser conferidas')}`}>
            Elas vieram de um provedor que não foi consultado. Salve a credencial dele em Configurações › Pagamentos (pode ficar desativado) para incluí-lo na conciliação.
          </Callout>
        ) : null}

        <dl className="ck-cb__sum">
          <div><dt>Recebido no provedor</dt><dd>{loading ? '—' : money(sm.gross)}</dd><span>{plural(sm.payments, 'pagamento', 'pagamentos')}</span></div>
          <div><dt>Taxas</dt><dd className="is-neg">{loading ? '—' : `−${money(sm.fees)}`}</dd>{pctTaxa ? <span>{pctTaxa}</span> : null}</div>
          <div><dt>Líquido</dt><dd>{loading ? '—' : money(sm.net)}</dd><span>{sm.semLiquido ? `${sm.semLiquido} sem líquido informado` : 'bruto menos taxas'}</span></div>
          <div><dt>A receber</dt><dd>{loading ? '—' : money(sm.receivable)}</dd><span>cartão com crédito futuro</span></div>
          <div className="ck-cb__sumsep"><dt>Baixas automáticas</dt><dd>{loading ? '—' : money(sm.auto)}</dd><span>{plural(sm.autoCount, 'parcela', 'parcelas')}</span></div>
          <div><dt>Baixas manuais</dt><dd>{loading ? '—' : money(sm.manual)}</dd><span>{`${plural(sm.manualCount, 'parcela', 'parcelas')}, fora do provedor`}</span></div>
        </dl>

        {!loading && sm.taxa.parcelas ? (
          <section className="ck-cb__taxa" aria-label="Ganho ou perda com as taxas">
            <div className="ck-cb__taxahead">
              <h2>Taxas x mensalidade</h2>
              <p>O líquido que caiu na conta comparado com o valor do plano. Pix e boleto ficam abaixo, porque a taxa sai da academia; o acréscimo no cartão compensa.</p>
            </div>
            <dl className="ck-cb__taxafacts">
              <div><dt>Mensalidades</dt><dd>{money(sm.taxa.mensalidades)}</dd><span>{plural(sm.taxa.parcelas, 'parcela recebida', 'parcelas recebidas')} no provedor</span></div>
              <div><dt>Taxas do provedor</dt><dd className="is-neg">{`−${money(sm.taxa.taxas)}`}</dd><span>descontadas no recebimento</span></div>
              <div><dt>Acréscimos cobrados</dt><dd>{`+${money(sm.taxa.acrescimos)}`}</dd><span>repasse da taxa do cartão ao aluno</span></div>
              <div className={cx('ck-cb__taxares', sm.taxa.resultado < -0.004 ? 'is-loss' : 'is-gain')}>
                <dt>{sm.taxa.resultado < -0.004 ? 'Perda com taxas' : 'Ganho com taxas'}</dt>
                <dd>{`${sm.taxa.resultado < -0.004 ? '−' : '+'}${money(Math.abs(sm.taxa.resultado))}`}</dd>
                <span>{`líquido de ${money(sm.taxa.liquido)} para ${money(sm.taxa.mensalidades)} em mensalidades`}</span>
              </div>
            </dl>
            <PonteRecebido sm={sm} />
            <p className="ck-cb__taxasplit">
              {sm.taxa.perdaQtd ? <span className="is-loss">{`${plural(sm.taxa.perdaQtd, 'parcela abaixo', 'parcelas abaixo')} da mensalidade: −${money(Math.abs(sm.taxa.perdas))}`}</span> : null}
              {sm.taxa.ganhoQtd ? <span className="is-gain">{`${plural(sm.taxa.ganhoQtd, 'parcela acima', 'parcelas acima')}: +${money(sm.taxa.ganhos)}`}</span> : null}
              {!sm.taxa.perdaQtd && !sm.taxa.ganhoQtd ? <span>Todas as parcelas renderam exatamente a mensalidade.</span> : null}
            </p>
          </section>
        ) : null}

        <section className="ck-cb__report" aria-label="Conciliação por matrícula">
          <div className="ck-cb__bar">
            <div className="ck-control ck-ml__search">
              <Icon name="search" size={18} className="ck-control__icon" />
              <input type="search" className="ck-control__input" value={query} placeholder="Aluno, matrícula, parcela ou ID no provedor" aria-label="Buscar na conciliação" onChange={(e) => setQuery(e.target.value)} />
              {query ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setQuery('')} /> : null}
            </div>
            <div className="ck-ml__filters" role="radiogroup" aria-label="Filtrar situação">
              {CHIPS.map(([key, label]) => (key === 'todos' || key === 'pendencias' || counts[key] ? (
                <button key={key} type="button" role="radio" aria-checked={filter === key} className="ck-ml__chip" title={SITUACAO[key]?.texto} onClick={() => setFilter(key)}>
                  {SITUACAO[key] ? <span className={`ck-ml__chipdot ck-ml__chipdot--${SITUACAO[key].tone}`} aria-hidden /> : null}
                  {label}<span className="ck-ml__count">{counts[key] || 0}</span>
                </button>
              ) : null))}
            </div>
            <button type="button" className="ck-linkbtn ck-cb__expand" aria-expanded={legenda} onClick={() => setLegenda((v) => !v)}>
              {legenda ? 'Fechar legenda' : 'O que significa cada situação'}
            </button>
            <button type="button" className="ck-linkbtn" onClick={() => { setAll((v) => !v); setOpen({}); }}>{all ? 'Recolher todas' : 'Expandir todas'}</button>
          </div>
          {legenda ? (
            <dl className="ck-cb__legend">
              {Object.entries(SITUACAO).map(([k, st]) => (
                <div key={k}>
                  <dt><Badge tone={st.tone} dot>{st.label}</Badge></dt>
                  <dd>{st.texto}</dd>
                </div>
              ))}
            </dl>
          ) : null}

          {loading ? <p role="status" className="ck-enrollment-state">Consultando o provedor…</p>
            : pageList.length ? (
              <div className="ck-cb__wrap">
                <table className="ck-cb__table">
                  <colgroup>{COLS.map((w, i) => <col key={i} style={{ width: `${w}%` }} />)}</colgroup>
                  <thead>
                    <tr>
                      <th scope="col">Matrícula e parcela</th>
                      <th scope="col">Baixa no Check-in</th>
                      <th scope="col" className="is-num">Bruto</th>
                      <th scope="col" className="is-num">Taxas</th>
                      <th scope="col" className="is-num">Líquido</th>
                      <th scope="col" className="is-num">Diferença</th>
                      <th scope="col" className="is-num" title="Líquido recebido menos a mensalidade (valor do plano, sem o acréscimo do cartão)">Taxa x plano</th>
                      <th scope="col">Situação</th>
                    </tr>
                  </thead>
                  {pageList.map((g) => {
                    const o = isOpen(g) && g.pays.length > 0;
                    const tem = g.pays.length > 0;
                    return (
                      <tbody key={g.key}>
                        <tr className={cx('ck-cb__group', o && 'is-open', g.status === 'divergente' && 'is-bad')}>
                          <th scope="row">
                            <div className="ck-cb__who">
                              {tem ? (
                                <button type="button" className="ck-cb__toggle" aria-expanded={o} aria-label={`${o ? 'Recolher' : 'Expandir'} pagamentos de ${g.student}`} onClick={() => toggle(g)}>
                                  <Icon name="chevron-right" size={16} />
                                </button>
                              ) : <span className="ck-cb__toggle is-empty" aria-hidden />}
                              <div style={{ minWidth: 0 }}>
                                <span className="ck-cb__name">{g.student}</span>
                                <span className="ck-cb__sub">
                                  {g.matriculaId ? <button type="button" className="ck-cb__ref" onClick={() => abrirMatricula(g)}>{`Matrícula #${g.matriculaId}`}</button> : 'Sem matrícula'}
                                  {[g.parcela ? `parcela #${g.parcela.id}` : null, g.plano].filter(Boolean).map((x) => `, ${x}`).join('')}
                                </span>
                              </div>
                            </div>
                          </th>
                          <td>{baixaCell(g)}</td>
                          <td className="is-num">
                            {tem ? money(g.t.gross) : <span className="ck-cb__dash">—</span>}
                            {g.pays.length > 1 ? <span className="ck-cb__sub">{plural(g.pays.length, 'pagamento', 'pagamentos')}</span> : null}
                          </td>
                          <td className="is-num ck-cb__fee">{tem ? `−${money(g.t.fee)}` : <span className="ck-cb__dash">—</span>}</td>
                          <td className="is-num">
                            {tem ? money(g.t.net) : <span className="ck-cb__dash">—</span>}
                            {g.t.rec ? <span className="ck-cb__sub">{`${money(g.t.rec)} a receber`}</span> : null}
                          </td>
                          <td className={cx('is-num', g.diff != null && Math.abs(g.diff) > 0.05 && 'ck-cb__diff--bad')}><Diferenca value={g.diff} /></td>
                          <td className="is-num"><Resultado grupo={g} /></td>
                          <td>
                            <SituacaoBadge status={g.status} grupo={g} />
                            {g.notas.map((n) => <span key={n} className="ck-cb__note">{n}</span>)}
                            {g.reprocessaveis.length === 1 ? <span className="ck-cb__acts"><BotaoReprocessar linha={g.reprocessaveis[0]} grupo={g} /></span> : null}
                          </td>
                        </tr>
                        {o ? g.pays.map((x) => (
                          <tr key={x.id} className="ck-cb__pay">
                            <td>
                              <div className="ck-cb__payline">
                                <span className="ck-cb__paydate">{curto(x.data)}</span>
                                <span className="ck-cb__paydesc">
                                  {[x.provedor_nome, `${x.forma || 'Forma não informada'}${x.parcela_numero ? ` ${x.parcela_numero}ª parcela` : ''}`, x.antecipado ? 'antecipado' : null, x.em_dinheiro ? 'recebido em dinheiro' : null].filter(Boolean).join(', ')}
                                </span>
                                {x.invoice_url ? (
                                  <a href={x.invoice_url} target="_blank" rel="noopener noreferrer" className="ck-cb__ext" aria-label={`Abrir pagamento no ${x.provedor_nome}`}><Icon name="open-in-new" size={14} /></a>
                                ) : null}
                              </div>
                            </td>
                            <td>
                              {x.fora_do_periodo ? <span className="ck-cb__sub ck-cb__fora">fora do período</span>
                                : x.a_receber ? <span className="ck-cb__sub ck-cb__wait">{`crédito em ${fdate(x.data_credito)}`}</span>
                                  : <span className="ck-cb__sub">{`creditado ${fdate(x.data_credito || x.data)}`}</span>}
                            </td>
                            <td className="is-num">{money(x.valor)}</td>
                            <td className="is-num ck-cb__fee">{x.taxa ? `−${money(x.taxa)}` : '—'}</td>
                            <td className="is-num">{x.liquido != null ? money(x.liquido) : '—'}</td>
                            <td />
                            <td />
                            <td>
                              {x.payment_id ? <span className="ck-cb__pid" title={x.payment_id}>{x.payment_id}</span> : null}
                              {g.reprocessaveis.length > 1 && x.pode_reprocessar ? <span className="ck-cb__acts"><BotaoReprocessar linha={x} grupo={g} /></span> : null}
                            </td>
                          </tr>
                        )) : null}
                      </tbody>
                    );
                  })}
                  <tfoot>
                    <tr>
                      <th scope="row">
                        {plural(list.length, 'matrícula', 'matrículas')}
                        <span className="ck-cb__sub">{`${plural(tot.pays, 'pagamento', 'pagamentos')} no provedor`}</span>
                      </th>
                      <td>{money(tot.inv)}</td>
                      <td className="is-num">{money(tot.gross)}</td>
                      <td className="is-num ck-cb__fee">{`−${money(tot.fee)}`}</td>
                      <td className="is-num">{money(tot.net)}</td>
                      <td />
                      <td className="is-num">{sm.taxa.parcelas ? <span className={sm.taxa.resultado < -0.004 ? 'ck-cb__loss' : 'ck-cb__gain'}>{`${sm.taxa.resultado < -0.004 ? '−' : '+'}${num(Math.abs(sm.taxa.resultado))}`}</span> : null}</td>
                      <td>{counts.pendencias && filter === 'todos' ? <span className="ck-cb__sub">{plural(counts.pendencias, 'pendência', 'pendências')}</span> : null}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            ) : (
              <div className="ck-ml__empty">
                <span className="ck-mt-empty__icon" aria-hidden><Icon name={query ? 'search' : 'check-circle'} size={20} /></span>
                <strong>{query ? `Nada encontrado para "${query}"` : 'Nada nesta situação'}</strong>
                <p>{query ? 'Busque pelo nome, número da matrícula ou ID do pagamento.' : 'Escolha outro filtro ou outro período.'}</p>
              </div>
            )}

          {!loading && pages > 1 ? (
            <div className="ck-cb__foot">
              <span>{`${(page - 1) * PAGE_SIZE + 1}–${Math.min(page * PAGE_SIZE, list.length)} de ${list.length}`}</span>
              <div className="ck-ml__pager">
                <IconButton icon="chevron-left" label="Página anterior" size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((v) => v - 1)} />
                <span className="ck-ml__pageinfo">{page} de {pages}</span>
                <IconButton icon="chevron-right" label="Próxima página" size="sm" variant="secondary" disabled={page >= pages} onClick={() => setPage((v) => v + 1)} />
              </div>
            </div>
          ) : null}
        </section>
      </div>
    </LayoutBase>
  );
}
