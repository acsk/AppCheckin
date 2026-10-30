import React, { useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, PageHeader, SegmentedControl } from '../../components/ck';
import { Panel, StatTile } from '../../components/ck/pessoas';
import { ColumnChart, Meter, deltaObj } from '../../components/ck/graficos';
import { fdate, money, moneyShort } from '../../components/ck/financeiro';
import { buscarBiVisaoGeral } from '../../services/biService';
import BiDetalheModal from './BiDetalheModal';
import { ComoCalculamos, Empty, PERIODOS, deltaPp, mesCurto, mesLongo, num, pct, textoVs, useBiCarga } from './biComum';

// Fórmulas exibidas na tela; a fonte é App\Repositories\Bi\BiTenantRepository
const COMO_CALCULAMOS = [
  ['Comparação', 'Contra o período anterior de mesma duração. Em "Este mês", do dia 1 até hoje contra o mesmo trecho do mês passado.'],
  ['Alunos ativos', 'Alunos com matrícula vigente no último dia do período. Matrículas pendentes não contam.'],
  ['Novos alunos', 'Alunos cuja primeira matrícula na academia é do período. Renovações não contam.'],
  ['Saídas', 'Alunos com matrícula cancelada no período que não começaram outra em até 7 dias (troca de plano não é saída). Diárias ficam de fora.'],
  ['Receita recebida', 'Parcelas pagas, pela data do pagamento.'],
  ['Receita prevista', 'Parcelas não canceladas que vencem no período.'],
  ['Inadimplência', 'Parcelas do período já vencidas e não pagas ÷ receita prevista.'],
  ['Ticket médio', 'Receita recebida ÷ alunos que pagaram no período.'],
  ['Check-ins', 'Pelo dia da aula. Check-ins marcados como ausente não contam.'],
];

export default function BiVisaoGeralScreen() {
  const [periodo, setPeriodo] = useState('mes');
  const [detalhe, setDetalhe] = useState(null);
  const { dados, erro, loading, recarregar } = useBiCarga(buscarBiVisaoGeral, periodo);

  const ind = dados?.indicadores || {};
  const per = dados?.periodo;
  const vs = textoVs(per);
  const v = (x) => (loading && !dados ? '—' : x);
  const par = (k) => ind[k] || {};
  // Cards que abrem a lista do que compõe o número
  const abre = (k, nome) => (dados ? { onClick: () => setDetalhe(k), clickLabel: `Ver ${nome}` } : {});

  const receita = (dados?.receita_mensal || []).map((m) => ({
    label: mesCurto(m.mes),
    tip: mesLongo(m.mes),
    value: m.recebido,
    sub: `Previsto ${money(m.previsto)}`,
  }));
  const serie = dados?.checkins_serie;
  const checkins = (serie?.pontos || []).map((p) => (serie.agrupamento === 'dia'
    ? { label: fdate(p.data).slice(0, 5), tip: fdate(p.data), value: p.total }
    : { label: mesCurto(p.data), tip: mesLongo(p.data), value: p.total }));
  const totalCheckins = checkins.reduce((a, x) => a + x.value, 0);
  const parcelas = dados?.parcelas_periodo;

  return (
    <LayoutBase title="Visão geral" pageHeader={false}>
      <div className="ck-db">
        <PageHeader
          title="Visão geral"
          description={per ? `De ${fdate(per.inicio)} a ${fdate(per.fim)}, comparado com o período anterior de mesma duração.` : 'Os principais números da academia, comparados com o período anterior.'}
          actions={<SegmentedControl label="Período" value={periodo} onChange={setPeriodo} options={PERIODOS} />}
        />

        {erro ? (
          <Callout tone="warning" title="Os indicadores não carregaram" action={<Button size="sm" icon="refresh-cw" onClick={recarregar}>Tentar de novo</Button>}>
            {erro}
          </Callout>
        ) : null}

        <div className="ck-db__kpis" aria-busy={loading}>
          <StatTile label="Alunos ativos" icon="users" value={v(num(par('alunos_ativos').atual))}
            delta={deltaObj(par('alunos_ativos').atual, par('alunos_ativos').anterior, vs, true, num)}
            trend={(dados?.alunos_ativos_mensal || []).map((x) => x.total)} {...abre('alunos_ativos', 'os alunos ativos')} />
          <StatTile label="Novos alunos" icon="user-plus" value={v(num(par('novos_alunos').atual))}
            delta={deltaObj(par('novos_alunos').atual, par('novos_alunos').anterior, vs, true, num)} {...abre('novos_alunos', 'os novos alunos')} />
          <StatTile label="Saídas" icon="log-out" value={v(num(par('saidas').atual))}
            delta={deltaObj(par('saidas').atual, par('saidas').anterior, vs, false, num)} {...abre('saidas', 'quem saiu')} />
          <StatTile label="Check-ins" icon="activity" value={v(num(par('checkins').atual))}
            delta={deltaObj(par('checkins').atual, par('checkins').anterior, vs, true)} />
        </div>

        <div className="ck-db__kpis">
          <StatTile label="Receita recebida" icon="wallet" value={v(money(par('receita_recebida').atual))}
            delta={deltaObj(par('receita_recebida').atual, par('receita_recebida').anterior, vs, true)} {...abre('receita_recebida', 'as parcelas recebidas')} />
          <StatTile label="Receita prevista" icon="calendar" value={v(money(par('receita_prevista').atual))}
            delta={deltaObj(par('receita_prevista').atual, par('receita_prevista').anterior, vs, true)} />
          <StatTile label="Inadimplência" icon="alert-triangle" value={v(pct(par('inadimplencia').atual))}
            delta={deltaPp(par('inadimplencia').atual, par('inadimplencia').anterior, vs, false)}
            note={dados ? `${money(par('valor_vencido').atual)} vencido` : null} {...abre('valor_vencido', 'as parcelas vencidas')} />
          <StatTile label="Ticket médio" icon="receipt" value={v(par('ticket_medio').atual == null ? '—' : money(par('ticket_medio').atual))}
            delta={deltaObj(par('ticket_medio').atual, par('ticket_medio').anterior, vs, true)} />
        </div>

        <div className="ck-db__row ck-db__row--2-1">
          <Panel title="Receita recebida nos últimos 12 meses" description="Pela data do pagamento. Passe o mouse para ver o previsto do mês.">
            {loading && !dados ? <p className="ck-fin-empty" role="status">Carregando…</p> : receita.some((m) => m.value) ? (
              <ColumnChart data={receita} highlightLast height={220} format={money} formatAxis={moneyShort} caption="Receita recebida por mês" />
            ) : <Empty icon="wallet" title="Nenhum pagamento nos últimos 12 meses" />}
          </Panel>
          <Panel title="Parcelas do período" description={parcelas ? `Vencem no período: ${money(parcelas.previsto)}` : null}>
            {loading && !dados ? <p className="ck-fin-empty" role="status">Carregando…</p> : parcelas?.previsto ? (
              <Meter
                total={parcelas.previsto}
                format={money}
                segments={[
                  { label: 'Pago', value: parcelas.pago, tone: 'accent' },
                  { label: 'A vencer', value: parcelas.a_vencer, tone: 'muted', note: 'Ainda no prazo' },
                  { label: 'Vencido', value: parcelas.vencido, tone: 'danger', note: `${num(parcelas.vencido_qtd)} ${parcelas.vencido_qtd === 1 ? 'parcela' : 'parcelas'}` },
                ]}
              />
            ) : <Empty icon="receipt" title="Nenhuma parcela vence no período" />}
          </Panel>
        </div>

        <Panel
          title={serie?.agrupamento === 'mes' ? 'Check-ins por mês' : 'Check-ins por dia'}
          description={dados ? `${num(totalCheckins)} ${totalCheckins === 1 ? 'check-in' : 'check-ins'} no período` : null}
        >
          {loading && !dados ? <p className="ck-fin-empty" role="status">Carregando…</p> : totalCheckins ? (
            <ColumnChart data={checkins} highlightLast height={200} labelEvery={serie?.agrupamento === 'dia' ? 5 : 1}
              reference={serie?.agrupamento === 'dia' ? totalCheckins / checkins.length : null}
              referenceLabel={serie?.agrupamento === 'dia' ? `Média ${num(Math.round(totalCheckins / checkins.length))}` : null}
              caption="Check-ins no período" />
          ) : <Empty icon="activity" title="Nenhum check-in no período" />}
        </Panel>

        <ComoCalculamos itens={COMO_CALCULAMOS} />
      </div>

      <BiDetalheModal indicador={detalhe} periodo={periodo} onClose={() => setDetalhe(null)} />
    </LayoutBase>
  );
}
