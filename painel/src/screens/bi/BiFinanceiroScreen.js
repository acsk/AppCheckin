import React, { useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, PageHeader, SegmentedControl } from '../../components/ck';
import { Panel, StatTile } from '../../components/ck/pessoas';
import { ChartBarList, ChartLegend, ColumnChart, GroupedColumnChart, deltaObj } from '../../components/ck/graficos';
import { fdate, money, moneyShort } from '../../components/ck/financeiro';
import { buscarBiFinanceiro } from '../../services/biService';
import BiDetalheModal from './BiDetalheModal';
import { ComoCalculamos, Empty, PERIODOS, deltaPp, mesCurto, mesLongo, num, pct, textoVs, useBiCarga } from './biComum';

// Fórmulas exibidas na tela; a fonte é App\Repositories\Bi\BiTenantRepository
const COMO_CALCULAMOS = [
  ['Comparação', 'Contra o período anterior de mesma duração. Em "Este mês", do dia 1 até hoje contra o mesmo trecho do mês passado.'],
  ['Receita prevista', 'Parcelas não canceladas que vencem no período, já com desconto.'],
  ['Receita recebida', 'Parcelas pagas, pela data do pagamento. Uma parcela que vence em um mês e é paga no seguinte entra no previsto do primeiro e no recebido do segundo.'],
  ['A vencer e vencido', 'Parcelas do período ainda não pagas: a vencer se vencem de hoje em diante, vencido se já venceram.'],
  ['Inadimplência', 'Valor vencido ÷ receita prevista do mesmo período.'],
  ['Ticket médio', 'Receita recebida ÷ alunos que pagaram.'],
  ['Descontos concedidos', 'Soma dos descontos das parcelas pagas no período.'],
  ['Por plano e por forma', 'Receita recebida no período. O plano é o da parcela, ou o da matrícula quando a parcela não tem.'],
];

const ITENS_LISTA = 6;

/** Limita a lista de barras e junta o resto em "Outros" */
function barras(lista, outrosLabel) {
  const itens = (lista || []).slice(0, ITENS_LISTA).map((x) => ({ label: x.nome, value: x.valor, note: `${num(x.parcelas)} ${x.parcelas === 1 ? 'parcela' : 'parcelas'}` }));
  const resto = (lista || []).slice(ITENS_LISTA).reduce((a, x) => a + x.valor, 0);
  return resto ? [...itens, { label: outrosLabel, value: resto, muted: true }] : itens;
}

export default function BiFinanceiroScreen() {
  const [periodo, setPeriodo] = useState('mes');
  const [detalhe, setDetalhe] = useState(null);
  const { dados, erro, loading, recarregar } = useBiCarga(buscarBiFinanceiro, periodo);

  const ind = dados?.indicadores || {};
  const per = dados?.periodo;
  const vs = textoVs(per);
  const v = (x) => (loading && !dados ? '—' : x);
  const par = (k) => ind[k] || {};
  const abre = (k, nome) => (dados ? { onClick: () => setDetalhe(k), clickLabel: `Ver ${nome}` } : {});
  const carregando = loading && !dados;
  const carregandoTxt = <p className="ck-fin-empty" role="status">Carregando…</p>;

  const mensal = dados?.mensal || [];
  const temMensal = mensal.some((m) => m.previsto || m.recebido);
  const porPlano = barras(dados?.por_plano, 'Outros planos');
  const porForma = barras(dados?.por_forma, 'Outras formas');

  return (
    <LayoutBase title="Financeiro" pageHeader={false}>
      <div className="ck-db">
        <PageHeader
          title="Financeiro"
          description={per ? `De ${fdate(per.inicio)} a ${fdate(per.fim)}. Receita, recebimentos e inadimplência, com o histórico dos últimos 12 meses.` : 'Receita, recebimentos e inadimplência ao longo do tempo.'}
          actions={<SegmentedControl label="Período" value={periodo} onChange={setPeriodo} options={PERIODOS} />}
        />

        {erro ? (
          <Callout tone="warning" title="Os indicadores não carregaram" action={<Button size="sm" icon="refresh-cw" onClick={recarregar}>Tentar de novo</Button>}>
            {erro}
          </Callout>
        ) : null}

        <div className="ck-db__kpis" aria-busy={loading}>
          <StatTile label="Receita prevista" icon="calendar" value={v(money(par('receita_prevista').atual))}
            delta={deltaObj(par('receita_prevista').atual, par('receita_prevista').anterior, vs, true)} />
          <StatTile label="Receita recebida" icon="wallet" value={v(money(par('receita_recebida').atual))}
            delta={deltaObj(par('receita_recebida').atual, par('receita_recebida').anterior, vs, true)} {...abre('receita_recebida', 'as parcelas recebidas')} />
          <StatTile label="A vencer" icon="clock" value={v(money(par('a_vencer').atual))}
            note={dados ? 'Ainda no prazo' : null} {...abre('a_vencer', 'as parcelas a vencer')} />
          <StatTile label="Vencido" icon="alert-triangle" value={v(money(par('valor_vencido').atual))}
            delta={deltaObj(par('valor_vencido').atual, par('valor_vencido').anterior, vs, false)} {...abre('valor_vencido', 'as parcelas vencidas')} />
        </div>

        <div className="ck-db__kpis">
          <StatTile label="Inadimplência" icon="percent" value={v(pct(par('inadimplencia').atual))}
            delta={deltaPp(par('inadimplencia').atual, par('inadimplencia').anterior, vs, false)} />
          <StatTile label="Ticket médio" icon="receipt" value={v(par('ticket_medio').atual == null ? '—' : money(par('ticket_medio').atual))}
            delta={deltaObj(par('ticket_medio').atual, par('ticket_medio').anterior, vs, true)} />
          <StatTile label="Descontos concedidos" icon="tag" value={v(money(par('descontos').atual))}
            delta={deltaObj(par('descontos').atual, par('descontos').anterior, vs, false)} note={dados ? 'Nas parcelas pagas' : null} />
        </div>

        <Panel
          title="Previsto e recebido nos últimos 12 meses"
          description="Previsto pelo mês do vencimento, recebido pelo mês do pagamento."
          actions={<ChartLegend items={[{ label: 'Previsto', kind: 'outline' }, { label: 'Recebido', kind: 'accent' }]} />}
        >
          {carregando ? carregandoTxt : temMensal ? (
            <GroupedColumnChart
              labels={mensal.map((m) => mesCurto(m.mes))}
              tips={mensal.map((m) => mesLongo(m.mes))}
              a={{ name: 'Previsto', values: mensal.map((m) => m.previsto) }}
              b={{ name: 'Recebido', values: mensal.map((m) => m.recebido) }}
              format={money}
              formatAxis={moneyShort}
              ratioText="do previsto"
              caption="Receita prevista e recebida por mês"
            />
          ) : <Empty icon="wallet" title="Nenhuma parcela nos últimos 12 meses" />}
        </Panel>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel title="Receita por plano" description="Recebido no período.">
            {carregando ? carregandoTxt : porPlano.length ? <ChartBarList items={porPlano} format={money} />
              : <Empty icon="layers" title="Nenhum pagamento no período" />}
          </Panel>
          <Panel title="Formas de pagamento" description="Recebido no período.">
            {carregando ? carregandoTxt : porForma.length ? <ChartBarList items={porForma} format={money} />
              : <Empty icon="credit-card" title="Nenhum pagamento no período" />}
          </Panel>
        </div>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel title="Inadimplência por mês" description="Vencido ÷ previsto de cada mês.">
            {carregando ? carregandoTxt : temMensal ? (
              <ColumnChart data={mensal.map((m) => ({ label: mesCurto(m.mes), tip: mesLongo(m.mes), value: m.inadimplencia || 0, sub: `${money(m.vencido)} vencido` }))}
                highlightLast height={180} format={(x) => pct(Math.round(x * 10) / 10)} caption="Inadimplência por mês" />
            ) : <Empty icon="percent" title="Sem previsto nos últimos 12 meses" />}
          </Panel>
          <Panel title="Ticket médio por mês" description="Recebido ÷ alunos que pagaram no mês.">
            {carregando ? carregandoTxt : temMensal ? (
              <ColumnChart data={mensal.map((m) => ({ label: mesCurto(m.mes), tip: mesLongo(m.mes), value: m.ticket_medio || 0, sub: `${num(m.pagantes)} ${m.pagantes === 1 ? 'aluno pagou' : 'alunos pagaram'}` }))}
                highlightLast height={180} format={money} formatAxis={moneyShort} caption="Ticket médio por mês" />
            ) : <Empty icon="receipt" title="Nenhum pagamento nos últimos 12 meses" />}
          </Panel>
        </div>

        <Panel title="Resumo por mês" description="Os mesmos números dos gráficos, do mês mais recente para o mais antigo.">
          {carregando ? carregandoTxt : (
            <div className="ck-mt-table-wrap">
              <table className="ck-mt-table">
                <thead>
                  <tr>
                    <th scope="col">Mês</th>
                    <th scope="col" className="is-num">Previsto</th>
                    <th scope="col" className="is-num">Recebido</th>
                    <th scope="col" className="is-num">A vencer</th>
                    <th scope="col" className="is-num">Vencido</th>
                    <th scope="col" className="is-num">Inadimplência</th>
                    <th scope="col" className="is-num">Ticket médio</th>
                  </tr>
                </thead>
                <tbody>
                  {mensal.slice().reverse().map((m) => (
                    <tr key={m.mes}>
                      <th scope="row">{mesLongo(m.mes)}</th>
                      <td className="is-num">{money(m.previsto)}</td>
                      <td className="is-num">{money(m.recebido)}</td>
                      <td className="is-num">{money(m.a_vencer)}</td>
                      <td className="is-num">{money(m.vencido)}</td>
                      <td className="is-num">{pct(m.inadimplencia)}</td>
                      <td className="is-num">{m.ticket_medio == null ? '—' : money(m.ticket_medio)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>

        <ComoCalculamos itens={COMO_CALCULAMOS} />
      </div>

      <BiDetalheModal indicador={detalhe} periodo={periodo} onClose={() => setDetalhe(null)} />
    </LayoutBase>
  );
}
