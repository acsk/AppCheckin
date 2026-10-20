import React, { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import { buscarDashboardCards, buscarDashboardVisao } from '../../services/dashboardService';
import { financeiroService } from '../../services/financeiroService';
import { DASHBOARD_CARDS_DEFAULT } from '../../models';
import LayoutBase from '../../components/LayoutBase';
import BaixaPagamentoPlanoModal from '../../components/BaixaPagamentoPlanoModal';
import { Avatar, Button, Callout, Icon, PageHeader } from '../../components/ck';
import { Panel, StatTile } from '../../components/ck/pessoas';
import { ChartBarList, ColumnChart, Heatmap, Meter, deltaObj } from '../../components/ck/graficos';
import { fdate, isoDate, money, monthEnd, monthStart, statusOf, summarize } from '../../components/ck/financeiro';

const titleCase = (n) => String(n || '').toLowerCase().replace(/(^|\s)(\p{L})/gu, (m, s, c) => s + c.toUpperCase()).replace(/\s(Da|De|Do|Das|Dos|E)\s/g, (m) => m.toLowerCase());
const greeting = (h) => (h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite');
const dayDiff = (a, b) => Math.round((new Date(`${b}T00:00:00`) - new Date(`${a}T00:00:00`)) / 86400000);
const num = (v) => new Intl.NumberFormat('pt-BR').format(v || 0);
const plural = (n, um, varios) => `${num(n)} ${n === 1 ? um : varios}`;
const monthName = (d) => d.toLocaleDateString('pt-BR', { month: 'long' });

const DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const NOS_DIAS = ['aos domingos', 'às segundas', 'às terças', 'às quartas', 'às quintas', 'às sextas', 'aos sábados'];
const DIA_LONGO = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
const MAX_PLANOS = 6;

function Empty({ icon = 'check-circle', title, children }) {
  return (
    <div className="ck-mt-empty">
      <span className="ck-mt-empty__icon" aria-hidden><Icon name={icon} size={20} /></span>
      <strong>{title}</strong>
      {children ? <p>{children}</p> : null}
    </div>
  );
}

function whatsappLink(telefone, nome, ate) {
  let d = String(telefone || '').replace(/\D/g, '');
  if (d.length < 10) return null;
  if (d.length <= 11) d = `55${d}`;
  const texto = `Oi, ${titleCase(nome).split(' ')[0]}! Seu plano na academia vale até ${fdate(ate)}. Quer renovar para continuar treinando?`;
  return `https://wa.me/${d}?text=${encodeURIComponent(texto)}`;
}

export default function Dashboard() {
  const router = useRouter();
  const [user, setUser] = useState(null);
  const [cards, setCards] = useState(null);
  const [visao, setVisao] = useState(null);
  const [payments, setPayments] = useState(null);
  const [recebidos, setRecebidos] = useState(null);
  const [erro, setErro] = useState('');
  const [loading, setLoading] = useState(true);
  const [reload, setReload] = useState(0);
  const [baixa, setBaixa] = useState(null);

  const now = new Date();
  const today = isoDate(now);
  const currentKey = today.slice(0, 7);
  const prevMonth = monthStart(now, -1);
  const prevKey = isoDate(prevMonth).slice(0, 7);

  useEffect(() => {
    authService.getCurrentUser().then((u) => {
      if (!u) router.replace('/login');
      else setUser(u);
    }).catch(() => router.replace('/login'));
  }, []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setErro('');
    const falhou = (msg) => (err) => {
      if (active) setErro((e) => e || err?.mensagemLimpa || err?.error || msg);
      return null;
    };
    Promise.all([
      buscarDashboardCards().catch(() => null),
      buscarDashboardVisao().catch(falhou('Não foi possível carregar os gráficos de frequência.')),
      financeiroService.listarPagamentos({ dataInicio: isoDate(monthStart(now, -5)), dataFim: isoDate(monthEnd(now, 1)) })
        .catch(falhou('Não foi possível carregar as parcelas.')),
      // "Recebido" pela data do pagamento, mesmo critério do Relatório e de Assinaturas
      financeiroService.listarRecebidos({ dataInicio: isoDate(monthStart(now, -5)), dataFim: isoDate(monthEnd(now)) }).catch(() => null),
    ]).then(([c, v, p, r]) => {
      if (!active) return;
      setCards(c?.data || c || null);
      setVisao(v);
      setPayments(p);
      setRecebidos(r);
      setLoading(false);
    });
    return () => { active = false; };
  }, [reload]);

  const c = cards || DASHBOARD_CARDS_DEFAULT;

  const fin = useMemo(() => {
    const list = payments || [];
    const recs = recebidos || [];
    const somaRecebida = (filtro) => recs.filter((p) => filtro(String(p.data_pagamento || '').slice(0, 10))).reduce((a, p) => a + (Number(p.valor) || 0), 0);
    const diaHoje = today.slice(8, 10);
    const late = list.filter((p) => statusOf(p, today) === 'atrasado');
    const upcoming = list
      .filter((p) => statusOf(p, today) === 'aguardando' && dayDiff(today, String(p.data_vencimento).slice(0, 10)) <= 7)
      .sort((a, b) => String(a.data_vencimento).localeCompare(String(b.data_vencimento)));
    const meses = [5, 4, 3, 2, 1, 0].map((i) => isoDate(monthStart(now, -i)).slice(0, 7));
    return {
      month: summarize(list.filter((p) => String(p.data_vencimento || '').slice(0, 7) === currentKey), today),
      receivedToday: somaRecebida((d) => d === today),
      receivedNow: somaRecebida((d) => d.slice(0, 7) === currentKey),
      // Mesmo intervalo do mês anterior (até o mesmo dia), para comparar sem o mês em curso sair perdendo
      receivedPrev: somaRecebida((d) => d.slice(0, 7) === prevKey && d.slice(8, 10) <= diaHoje),
      receivedTrend: meses.map((k) => somaRecebida((d) => d.slice(0, 7) === k)),
      lateCount: late.length,
      lateValue: late.reduce((a, p) => a + (Number(p.valor) || 0), 0),
      upcoming,
      upcomingValue: upcoming.reduce((a, p) => a + (Number(p.valor) || 0), 0),
    };
  }, [payments, recebidos]);

  const freq = useMemo(() => {
    const dias = visao?.checkins_30_dias || [];
    const total30 = dias.reduce((a, d) => a + d.total, 0);
    const horarios = visao?.checkins_por_horario || [];
    const horas = horarios.map((x) => x.hora);
    const de = Math.min(6, ...horas);
    const ate = Math.max(21, ...horas);
    const cols = Array.from({ length: ate - de + 1 }, (_, i) => `${de + i}h`);
    const heat = DIAS.map((_, i) => cols.map((__, j) => horarios.find((x) => x.dia_semana === i + 1 && x.hora === de + j)?.media || 0));
    const planos = visao?.alunos_por_plano || [];
    const outros = planos.slice(MAX_PLANOS).reduce((a, x) => a + x.alunos, 0);
    const hist = (visao?.alunos_ativos_historico || []).map((x) => x.total);
    return {
      checkins: dias.map((d) => {
        const dt = new Date(`${d.data}T00:00:00`);
        return { label: fdate(d.data).slice(0, 5), tip: `${DIA_LONGO[dt.getDay()]}, ${fdate(d.data).slice(0, 5)}`, value: d.total };
      }),
      total30,
      avg30: dias.length ? total30 / dias.length : null,
      last7: dias.slice(-7).map((d) => d.total),
      cols,
      heat,
      temHorarios: horarios.length > 0,
      porPlano: [
        ...planos.slice(0, MAX_PLANOS).map((x) => ({ label: x.plano, value: x.alunos })),
        ...(outros ? [{ label: 'Outros planos', value: outros, muted: true }] : []),
      ],
      hist,
    };
  }, [visao]);

  const ativos = c.matriculas ? Number(c.matriculas.alunos_com_matricula_ativa) || 0 : Number(c.total_alunos?.ativos) || 0;
  const checkinsHoje = Number(c.checkins_hoje?.hoje) || 0;
  const novos = Number(c.planos_vencendo?.novos_este_mes) || 0;
  const firstName = titleCase(user?.nome || '').split(' ')[0];
  const dateLabel = now.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });
  const prevName = monthName(prevMonth);
  const terminando = visao?.acesso_terminando || [];
  const ver = (v) => (loading ? '—' : v);

  const abrirBaixa = (p) => {
    if (p.pacote_contrato_id) {
      // Parcela rateada de pacote: a baixa é feita pelo pacote, na tela da matrícula
      router.push(`/matriculas/detalhe?id=${p.matricula_id}`);
      return;
    }
    setBaixa(p);
  };

  return (
    <LayoutBase title="Dashboard" pageHeader={false}>
      <div className="ck-db">
        <PageHeader
          title={`${greeting(now.getHours())}${firstName ? `, ${firstName}` : ''}`}
          description={`${dateLabel.charAt(0).toUpperCase()}${dateLabel.slice(1)}.${loading ? '' : ` Até agora, ${plural(checkinsHoje, 'check-in', 'check-ins')} e ${money(fin.receivedToday)} ${fin.receivedToday ? 'recebidos' : 'recebido'} hoje.`}`}
          actions={(
            <>
              <Button icon="receipt" onClick={() => router.push('/financeiro/relatorio')}>Relatório financeiro</Button>
              <Button variant="primary" icon="plus" onClick={() => router.push('/matriculas/novo')}>Nova matrícula</Button>
            </>
          )}
        />

        {erro ? (
          <Callout tone="warning" title="Parte do dashboard não carregou" action={<Button size="sm" icon="refresh-cw" onClick={() => setReload((v) => v + 1)}>Tentar de novo</Button>}>
            {erro}
          </Callout>
        ) : null}

        <div className="ck-db__kpis">
          <StatTile
            label="Alunos ativos"
            icon="users"
            value={ver(num(ativos))}
            delta={freq.hist.length > 1 ? deltaObj(freq.hist[freq.hist.length - 1], freq.hist[freq.hist.length - 2], `vs fim de ${prevName}`, true, num) : null}
            note={freq.hist.length > 1 ? null : 'Com matrícula ativa'}
            trend={freq.hist}
          />
          <StatTile
            label="Recebido no mês"
            icon="wallet"
            value={ver(money(fin.receivedNow))}
            delta={recebidos ? deltaObj(fin.receivedNow, fin.receivedPrev, `no mesmo dia de ${prevName}`, true) : null}
            note={recebidos && !fin.receivedPrev ? 'Pela data do pagamento' : null}
            trend={recebidos ? fin.receivedTrend : null}
          />
          <StatTile
            label="Check-ins hoje"
            icon="activity"
            value={ver(num(checkinsHoje))}
            note={visao ? `Média de ${num(Math.round(visao.checkins_media_dia_semana || 0))} ${NOS_DIAS[now.getDay()]}` : null}
            trend={freq.last7}
          />
          <StatTile
            label="Em atraso"
            icon="alert-triangle"
            value={ver(money(fin.lateValue))}
            note={payments ? `${plural(fin.lateCount, 'parcela', 'parcelas')} nos últimos 6 meses` : null}
          />
        </div>

        <div className="ck-db__row ck-db__row--2-1">
          <Panel
            title="Check-ins nos últimos 30 dias"
            description={visao ? `${plural(freq.total30, 'check-in', 'check-ins')} no período` : null}
          >
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : freq.total30 ? (
              <ColumnChart
                data={freq.checkins}
                highlightLast
                height={200}
                labelEvery={5}
                reference={freq.avg30}
                referenceLabel={`Média ${num(Math.round(freq.avg30))}`}
                caption="Check-ins por dia"
              />
            ) : <Empty icon="activity" title="Nenhum check-in nos últimos 30 dias">Os check-ins dos alunos aparecem aqui dia a dia.</Empty>}
          </Panel>
          <Panel
            title="Receita do mês"
            description={`Parcelas que vencem em ${monthName(now)}: previsto ${money(fin.month.total)}`}
            actions={<Button size="sm" variant="ghost" iconRight="arrow-right" onClick={() => router.push('/financeiro/relatorio')}>Detalhes</Button>}
          >
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : fin.month.total ? (
              <Meter
                total={fin.month.total}
                format={money}
                segments={[
                  { label: 'Pago', value: fin.month.pago, tone: 'accent', note: plural(fin.month.count.pago, 'parcela', 'parcelas') },
                  { label: 'A vencer', value: fin.month.aguardando, tone: 'muted', note: 'Ainda no prazo' },
                  { label: 'Em atraso', value: fin.month.atrasado, tone: 'danger', note: plural(fin.month.count.atrasado, 'parcela', 'parcelas') },
                ]}
              />
            ) : <Empty icon="receipt" title={`Nenhuma parcela vence em ${monthName(now)}`} />}
          </Panel>
        </div>

        <div className="ck-db__row ck-db__row--2-1">
          <Panel title="Horários de pico" description="Média de check-ins por dia da semana e hora da aula, nas últimas 4 semanas.">
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : freq.temHorarios ? (
              <Heatmap rows={DIAS} cols={freq.cols} values={freq.heat} unit="check-ins" label="Check-ins por dia e hora" />
            ) : <Empty icon="clock" title="Sem check-ins nas últimas 4 semanas">O mapa mostra os horários mais cheios quando houver frequência.</Empty>}
          </Panel>
          <Panel
            title="Alunos por plano"
            description={visao ? `+${num(novos)} ${novos === 1 ? 'matrícula nova' : 'matrículas novas'} e ${plural(visao.saidas_mes || 0, 'saída', 'saídas')} este mês` : null}
          >
            {loading ? <p className="ck-fin-empty" role="status">Carregando…</p> : freq.porPlano.length ? (
              <ChartBarList items={freq.porPlano} />
            ) : <Empty icon="layers" title="Nenhuma matrícula ativa" />}
          </Panel>
        </div>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel
            title="Vencem nos próximos 7 dias"
            description={fin.upcoming.length ? `${money(fin.upcomingValue)} em ${plural(fin.upcoming.length, 'parcela', 'parcelas')}` : null}
            actions={<Button size="sm" variant="ghost" iconRight="arrow-right" onClick={() => router.push('/matriculas/vencimentos')}>Ver todas</Button>}
          >
            {fin.upcoming.length ? (
              <ul className="ck-db__list">
                {fin.upcoming.slice(0, 6).map((p) => {
                  const d = dayDiff(today, String(p.data_vencimento).slice(0, 10));
                  return (
                    <li key={p.id}>
                      <Avatar name={titleCase(p.aluno_nome)} size="sm" decorative />
                      <button type="button" className="ck-db__who ck-db__link" onClick={() => router.push(`/matriculas/detalhe?id=${p.matricula_id}`)}>
                        <strong>{titleCase(p.aluno_nome)}</strong><span>{p.plano_nome}</span>
                      </button>
                      <span className="ck-db__when">
                        <strong>{money(p.valor)}</strong>
                        <span>{d === 0 ? 'Vence hoje' : d === 1 ? 'Vence amanhã' : `Em ${d} dias`}</span>
                      </span>
                      <Button size="sm" variant="secondary" onClick={() => abrirBaixa(p)}>Dar baixa</Button>
                    </li>
                  );
                })}
              </ul>
            ) : (
              <Empty title={loading ? 'Carregando…' : 'Nada vencendo nos próximos 7 dias'}>
                {loading ? null : 'As próximas parcelas aparecem aqui conforme chegam perto do vencimento.'}
              </Empty>
            )}
          </Panel>
          <Panel
            title="Acesso terminando"
            description="Matrículas que encerram nos próximos 15 dias e ainda não renovaram."
            actions={<Button size="sm" variant="ghost" iconRight="arrow-right" onClick={() => router.push('/matriculas')}>Matrículas</Button>}
          >
            {terminando.length ? (
              <ul className="ck-db__list">
                {terminando.map((m) => {
                  const d = dayDiff(today, m.data_vencimento);
                  const wa = whatsappLink(m.telefone, m.aluno_nome, m.data_vencimento);
                  return (
                    <li key={m.matricula_id}>
                      <Avatar name={titleCase(m.aluno_nome)} size="sm" decorative />
                      <button type="button" className="ck-db__who ck-db__link" onClick={() => router.push(`/matriculas/detalhe?id=${m.matricula_id}`)}>
                        <strong>{titleCase(m.aluno_nome)}</strong><span>{m.plano_nome || 'Sem plano'}</span>
                      </button>
                      <span className="ck-db__when">
                        <strong>{fdate(m.data_vencimento)}</strong>
                        <span>{d === 0 ? 'Termina hoje' : `Em ${d} ${d === 1 ? 'dia' : 'dias'}`}</span>
                      </span>
                      {wa ? (
                        <Button size="sm" variant="ghost" icon="message-circle" onClick={() => window.open(wa, '_blank', 'noopener')}>Lembrar</Button>
                      ) : (
                        <Button size="sm" variant="ghost" onClick={() => router.push(`/matriculas/detalhe?id=${m.matricula_id}`)}>Abrir</Button>
                      )}
                    </li>
                  );
                })}
              </ul>
            ) : (
              <Empty title={loading ? 'Carregando…' : 'Nenhum acesso terminando'}>
                {loading ? null : 'Quem estiver a 15 dias do fim do plano, sem renovação, aparece aqui.'}
              </Empty>
            )}
          </Panel>
        </div>
      </div>

      <BaixaPagamentoPlanoModal
        visible={!!baixa}
        pagamento={baixa}
        student={titleCase(baixa?.aluno_nome)}
        plan={baixa?.plano_nome}
        onClose={() => setBaixa(null)}
        onSuccess={() => { setBaixa(null); setReload((v) => v + 1); }}
      />
    </LayoutBase>
  );
}
