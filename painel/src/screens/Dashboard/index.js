import React, { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import { buscarDashboard, buscarDashboardCards } from '../../services/dashboardService';
import { financeiroService } from '../../services/financeiroService';
import { DASHBOARD_CARDS_DEFAULT } from '../../models';
import LayoutBase from '../../components/LayoutBase';
import { Avatar, Badge, Button, Callout, Icon, PageHeader } from '../../components/ck';
import {
  KpiCard, MonthlyChart, StatusSplit, byMonth, fdate, isoDate, money, monthEnd, monthStart, pct, statusOf, summarize,
} from '../../components/ck/financeiro';

const titleCase = (n) => String(n || '').toLowerCase().replace(/(^|\s)(\p{L})/gu, (m, s, c) => s + c.toUpperCase()).replace(/\s(Da|De|Do|Das|Dos|E)\s/g, (m) => m.toLowerCase());
const greeting = (h) => (h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite');
const dayDiff = (a, b) => Math.round((new Date(`${b}T00:00:00`) - new Date(`${a}T00:00:00`)) / 86400000);

const QUICK_LINKS = [
  { label: 'Matrículas', icon: 'receipt', path: '/matriculas' },
  { label: 'Alunos', icon: 'users', path: '/alunos' },
  { label: 'Planos', icon: 'layers', path: '/planos' },
  { label: 'Aulas', icon: 'calendar', path: '/turmas' },
];

export default function Dashboard() {
  const router = useRouter();
  const [user, setUser] = useState(null);
  const [cards, setCards] = useState(null);
  const [stats, setStats] = useState(null);
  const [payments, setPayments] = useState(null);
  const [recebidos, setRecebidos] = useState(null);
  const [paymentsError, setPaymentsError] = useState('');
  const [loading, setLoading] = useState(true);
  const [reload, setReload] = useState(0);

  const now = new Date();
  const today = isoDate(now);
  const from = monthStart(now, -5);
  const to = monthEnd(now, 1);
  const currentKey = today.slice(0, 7);
  const prevKey = isoDate(monthStart(now, -1)).slice(0, 7);

  useEffect(() => {
    authService.getCurrentUser().then((u) => {
      if (!u) router.replace('/login');
      else setUser(u);
    }).catch(() => router.replace('/login'));
  }, []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setPaymentsError('');
    Promise.all([
      buscarDashboardCards().catch(() => null),
      buscarDashboard().catch(() => null),
      financeiroService.listarPagamentos({ dataInicio: isoDate(from), dataFim: isoDate(to) }).catch((err) => {
        if (active) setPaymentsError(err?.mensagemLimpa || err?.error || 'Não foi possível carregar as parcelas.');
        return null;
      }),
      // "Recebido" pela data do pagamento, mesmo critério do Relatório e de Assinaturas
      financeiroService.listarRecebidos({ dataInicio: isoDate(monthStart(now, -1)), dataFim: isoDate(monthEnd(now)) }).catch(() => null),
    ]).then(([c, s, p, r]) => {
      if (!active) return;
      setCards(c?.data || c || null);
      setStats(s?.data || s || null);
      setPayments(p);
      setRecebidos(r);
      setLoading(false);
    });
    return () => { active = false; };
  }, [reload]);

  const c = cards || DASHBOARD_CARDS_DEFAULT;
  const list = payments || [];

  const derived = useMemo(() => {
    const inMonth = (p, key) => String(p.data_vencimento || '').slice(0, 7) === key;
    const paidIn = (key) => (recebidos || []).filter((p) => String(p.data_pagamento || '').slice(0, 7) === key).reduce((a, p) => a + (Number(p.valor) || 0), 0);
    const month = summarize(list.filter((p) => inMonth(p, currentKey)), today);
    const late = list.filter((p) => statusOf(p, today) === 'atrasado');
    const upcoming = list
      .filter((p) => statusOf(p, today) === 'aguardando' && dayDiff(today, String(p.data_vencimento).slice(0, 10)) <= 7)
      .sort((a, b) => String(a.data_vencimento).localeCompare(String(b.data_vencimento)));
    return {
      month,
      receivedNow: paidIn(currentKey),
      receivedPrev: paidIn(prevKey),
      lateCount: late.length,
      lateValue: late.reduce((a, p) => a + (Number(p.valor) || 0), 0),
      upcoming,
      months: byMonth(list, today, from, monthEnd(now)),
    };
  }, [payments, recebidos]);

  const received = recebidos ? derived.receivedNow : Number(c.receita_mensal?.valor) || 0;
  const trend = recebidos && derived.receivedPrev > 0
    ? (() => {
      const d = Math.round(((derived.receivedNow - derived.receivedPrev) / derived.receivedPrev) * 100);
      return { direction: d < 0 ? 'down' : 'up', label: `${d > 0 ? '+' : ''}${d}% vs. mês passado` };
    })()
    : null;

  const cadastrados = Number(c.total_alunos?.ativos) || 0;
  const mats = c.matriculas || null;
  const ativos = mats ? Number(mats.alunos_com_matricula_ativa) || 0 : cadastrados;
  const semMatricula = Math.max(0, cadastrados - ativos);
  const firstName = titleCase(user?.nome || '').split(' ')[0];
  const dateLabel = now.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });

  return (
    <LayoutBase title="Dashboard" pageHeader={false}>
      <div className="ck-dash">
        <PageHeader
          title={`${greeting(now.getHours())}${firstName ? `, ${firstName}` : ''}`}
          description={`${dateLabel.charAt(0).toUpperCase()}${dateLabel.slice(1)}. Veja como estão as matrículas e os recebimentos.`}
          actions={(
            <>
              <Button icon="bar-chart" onClick={() => router.push('/financeiro/relatorio')}>Relatório financeiro</Button>
              <Button variant="primary" icon="plus" onClick={() => router.push('/matriculas/novo')}>Nova matrícula</Button>
            </>
          )}
        />

        {derived.lateCount > 0 ? (
          <Callout
            tone="danger"
            icon="clock"
            title={`${derived.lateCount} ${derived.lateCount === 1 ? 'parcela atrasada soma' : 'parcelas atrasadas somam'} ${money(derived.lateValue)}`}
            action={<Button size="sm" onClick={() => router.push('/financeiro/relatorio?status=atrasado&periodo=6m')}>Ver parcelas</Button>}
          >
            Considera os vencimentos dos últimos 6 meses. Dar baixa em dia mantém o acesso dos alunos liberado.
          </Callout>
        ) : null}
        {paymentsError ? <Callout tone="warning" title="Parte dos números financeiros não carregou">{paymentsError}</Callout> : null}

        <div className="ck-kpis">
          <KpiCard emphasis icon="wallet" label="Recebido no mês" value={loading ? '—' : money(received)} trend={trend} hint={trend ? null : 'Parcelas pagas neste mês, pela data do pagamento'} onClick={() => router.push('/financeiro/relatorio')} />
          <KpiCard tone="warning" icon="clock" label="A receber no mês" value={loading ? '—' : money(derived.month.aguardando)} hint={`${derived.month.count.aguardando} ${derived.month.count.aguardando === 1 ? 'parcela aguardando' : 'parcelas aguardando'}`} />
          <KpiCard tone="danger" icon="alert-triangle" label="Em atraso" value={loading ? '—' : money(derived.lateValue)} hint={`${derived.lateCount} ${derived.lateCount === 1 ? 'parcela' : 'parcelas'} nos últimos 6 meses`} onClick={derived.lateCount ? () => router.push('/financeiro/relatorio?status=atrasado&periodo=6m') : undefined} />
          <KpiCard tone="info" icon="users" label="Alunos com matrícula ativa" value={loading ? '—' : ativos.toLocaleString('pt-BR')} hint={`${Number(c.planos_vencendo?.novos_este_mes) || 0} matrículas novas este mês`} onClick={() => router.push('/matriculas')} />
        </div>

        <div className="ck-dash__row">
          <section className="ck-card ck-dash__panel" aria-label="Parcelas por mês">
            <header className="ck-dash__head">
              <div>
                <h2 className="ck-mt-box__title">Parcelas por mês</h2>
                <p className="ck-dash__sub">Últimos 6 meses, pela data de vencimento</p>
              </div>
              <Button size="sm" variant="ghost" iconRight="arrow-right" onClick={() => router.push('/financeiro/relatorio?periodo=6m')}>Detalhar</Button>
            </header>
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : <MonthlyChart months={derived.months} currentKey={currentKey} />}
          </section>

          <section className="ck-card ck-mt-box ck-dash__month" aria-label="Este mês">
            <div className="ck-mt-box__head">
              <div>
                <h2 className="ck-mt-box__title">Este mês</h2>
                <span className="ck-mt-box__sub">Parcelas com vencimento em {now.toLocaleDateString('pt-BR', { month: 'long' })}</span>
              </div>
              <span className="ck-mt-box__aside">{pct(derived.month.pago, derived.month.total)}% pago</span>
            </div>
            <StatusSplit summary={derived.month} />
          </section>
        </div>

        <div className="ck-dash__row ck-dash__row--even">
          <section className="ck-card ck-dash__panel" aria-label="Próximos vencimentos">
            <header className="ck-dash__head">
              <div>
                <h2 className="ck-mt-box__title">Próximos vencimentos</h2>
                <p className="ck-dash__sub">Parcelas aguardando pagamento nos próximos 7 dias</p>
              </div>
              {derived.upcoming.length ? <Badge tone="warning">{derived.upcoming.length}</Badge> : null}
            </header>
            {derived.upcoming.length ? (
              <ul className="ck-dash__due">
                {derived.upcoming.slice(0, 6).map((p) => {
                  const d = dayDiff(today, String(p.data_vencimento).slice(0, 10));
                  return (
                    <li key={p.id}>
                      <button type="button" onClick={() => router.push(`/matriculas/detalhe?id=${p.matricula_id}`)}>
                        <Avatar name={titleCase(p.aluno_nome)} size="sm" decorative />
                        <span className="ck-dash__who"><strong>{titleCase(p.aluno_nome)}</strong><span>{p.plano_nome}</span></span>
                        <span className="ck-dash__when">
                          <strong>{money(p.valor)}</strong>
                          <span className={d === 0 ? 'is-today' : null}>{d === 0 ? 'Vence hoje' : d === 1 ? 'Amanhã' : `${fdate(p.data_vencimento)}, em ${d} dias`}</span>
                        </span>
                      </button>
                    </li>
                  );
                })}
              </ul>
            ) : (
              <div className="ck-mt-empty">
                <span className="ck-mt-empty__icon" aria-hidden><Icon name="check-circle" size={20} /></span>
                <strong>Nada vencendo nos próximos 7 dias</strong>
                <p>As próximas parcelas aparecem aqui conforme chegam perto do vencimento.</p>
              </div>
            )}
            <footer className="ck-dash__foot">
              <Button size="sm" variant="ghost" iconRight="arrow-right" onClick={() => router.push('/matriculas/vencimentos')}>Ver vencimentos</Button>
            </footer>
          </section>

          <section className="ck-card ck-dash__panel" aria-label="Academia">
            <header className="ck-dash__head">
              <div>
                <h2 className="ck-mt-box__title">Academia</h2>
                <p className="ck-dash__sub">Alunos, frequência e renovações</p>
              </div>
              <Button size="sm" variant="ghost" icon="refresh-cw" loading={loading} onClick={() => setReload((v) => v + 1)}>Atualizar</Button>
            </header>
            <div className="ck-dash__students">
              <div className="ck-dash__studentsrow">
                <span><strong>{ativos.toLocaleString('pt-BR')}</strong> com matrícula ativa</span>
                <span>de {cadastrados.toLocaleString('pt-BR')} cadastrados</span>
              </div>
              <span className="ck-mt-plan__track ck-dash__track" role="progressbar" aria-label="Alunos cadastrados com matrícula ativa" aria-valuenow={pct(ativos, cadastrados)} aria-valuemin={0} aria-valuemax={100}>
                <span className="ck-mt-plan__fill" style={{ width: `${Math.max(pct(ativos, cadastrados), 2)}%` }} />
              </span>
              {mats ? (
                <ul className="ck-dash__mats">
                  <li><button type="button" onClick={() => router.push('/alunos')}><strong>{semMatricula.toLocaleString('pt-BR')}</strong> sem matrícula ativa</button></li>
                  {Number(mats.vencidas) ? <li className="is-danger"><button type="button" onClick={() => router.push('/matriculas')}><strong>{mats.vencidas}</strong> {Number(mats.vencidas) === 1 ? 'vencida' : 'vencidas'}</button></li> : null}
                  {Number(mats.pendentes) ? <li className="is-warning"><button type="button" onClick={() => router.push('/matriculas')}><strong>{mats.pendentes}</strong> {Number(mats.pendentes) === 1 ? 'pendente' : 'pendentes'}</button></li> : null}
                  {Number(mats.bloqueadas) ? <li><button type="button" onClick={() => router.push('/matriculas')}><strong>{mats.bloqueadas}</strong> {Number(mats.bloqueadas) === 1 ? 'bloqueada' : 'bloqueadas'}</button></li> : null}
                </ul>
              ) : null}
            </div>
            <dl className="ck-dash__facts">
              <div><dt><Icon name="check-circle" size={15} />Check-ins hoje</dt><dd>{Number(c.checkins_hoje?.hoje) || 0}</dd></div>
              <div><dt><Icon name="activity" size={15} />Check-ins no mês</dt><dd>{(Number(c.checkins_hoje?.no_mes) || 0).toLocaleString('pt-BR')}</dd></div>
              <div><dt><Icon name="calendar" size={15} />Matrículas vencendo em 7 dias</dt><dd>{Number(c.planos_vencendo?.vencendo) || 0}</dd></div>
              <div><dt><Icon name="user-plus" size={15} />Novos alunos no mês</dt><dd>{Number(stats?.novos_alunos_mes) || 0}</dd></div>
            </dl>
            <nav className="ck-dash__links" aria-label="Atalhos">
              {QUICK_LINKS.map((l) => (
                <button key={l.path} type="button" onClick={() => router.push(l.path)}>
                  <Icon name={l.icon} size={18} />{l.label}
                </button>
              ))}
            </nav>
          </section>
        </div>
      </div>
    </LayoutBase>
  );
}
