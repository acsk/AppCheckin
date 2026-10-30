import React, { useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, PageHeader, SegmentedControl } from '../../components/ck';
import { Panel, StatTile } from '../../components/ck/pessoas';
import { ChartBarList, ChartLegend, ColumnChart, GroupedColumnChart, deltaObj } from '../../components/ck/graficos';
import { fdate } from '../../components/ck/financeiro';
import { buscarBiAlunos } from '../../services/biService';
import BiDetalheModal from './BiDetalheModal';
import { BotaoExportar, ComoCalculamos, Empty, PERIODOS, deltaPp, mesCurto, mesLongo, num, pct, textoVs, useBiCarga } from './biComum';

// Fórmulas exibidas na tela; a fonte é App\Repositories\Bi\BiTenantRepository e App\Services\Bi\BiAlunosService
const COMO_CALCULAMOS = [
  ['Comparação', 'Contra o período anterior de mesma duração. Em "Este mês", do dia 1 até hoje contra o mesmo trecho do mês passado.'],
  ['Alunos ativos', 'Alunos com matrícula vigente no último dia do período. Matrículas pendentes não contam.'],
  ['Novos alunos', 'Primeira matrícula do aluno na academia dentro do período.'],
  ['Voltaram', 'Alunos que começaram uma matrícula no período depois de mais de 30 dias sem nenhuma. Renovação e troca de plano não contam.'],
  ['Saídas', 'Matrícula cancelada no período sem outra começando em até 7 dias (troca de plano não é saída). Diárias ficam de fora.'],
  ['Saldo', 'Novos alunos + voltaram − saídas.'],
  ['Retenção', 'Dos alunos ativos na véspera do período, quantos continuam ativos no fim.'],
  ['Permanência de quem saiu', 'Da primeira matrícula do aluno na academia até o cancelamento. Média em meses.'],
  ['Por plano e por modalidade', 'Alunos ativos no fim do período, pela matrícula vigente mais recente de cada um.'],
];

const ITENS_LISTA = 6;
const meses = (v) => (v == null ? '—' : `${String(v).replace('.', ',')} ${v === 1 ? 'mês' : 'meses'}`);

function barras(lista, outrosLabel) {
  const itens = (lista || []).slice(0, ITENS_LISTA).map((x) => ({ label: x.nome, value: x.alunos }));
  const resto = (lista || []).slice(ITENS_LISTA).reduce((a, x) => a + x.alunos, 0);
  return resto ? [...itens, { label: outrosLabel, value: resto, muted: true }] : itens;
}

export default function BiAlunosScreen() {
  const [periodo, setPeriodo] = useState('mes');
  const [detalhe, setDetalhe] = useState(null);
  const { dados, erro, loading, recarregar } = useBiCarga(buscarBiAlunos, periodo);

  const ind = dados?.indicadores || {};
  const per = dados?.periodo;
  const vs = textoVs(per);
  const v = (x) => (loading && !dados ? '—' : x);
  const par = (k) => ind[k] || {};
  const abre = (k, nome) => (dados ? { onClick: () => setDetalhe(k), clickLabel: `Ver ${nome}` } : {});
  const carregando = loading && !dados;
  const carregandoTxt = <p className="ck-fin-empty" role="status">Carregando…</p>;
  const sinal = (n) => (n == null ? '—' : `${n > 0 ? '+' : n < 0 ? '−' : ''}${num(Math.abs(n))}`);

  const mensal = dados?.mensal || [];
  const temMovimento = mensal.some((m) => m.novos || m.reativados || m.saidas);
  const faixas = (dados?.permanencia_saidas || []).map((f) => ({ label: f.faixa, value: f.alunos }));
  const totalSaidasFaixas = faixas.reduce((a, f) => a + f.value, 0);

  return (
    <LayoutBase title="Alunos e matrículas" pageHeader={false}>
      <div className="ck-db">
        <PageHeader
          title="Alunos e matrículas"
          description={per ? `De ${fdate(per.inicio)} a ${fdate(per.fim)}. Quem entrou, quem voltou, quem saiu e como a base se distribui.` : 'Como a base de alunos cresce, encolhe e se distribui.'}
          actions={<>
            <SegmentedControl label="Período" value={periodo} onChange={setPeriodo} options={PERIODOS} />
            <BotaoExportar alvo={{ tela: 'alunos' }} periodo={periodo}>Exportar meses</BotaoExportar>
          </>}
        />

        {erro ? (
          <Callout tone="warning" title="Os indicadores não carregaram" action={<Button size="sm" icon="refresh-cw" onClick={recarregar}>Tentar de novo</Button>}>
            {erro}
          </Callout>
        ) : null}

        <div className="ck-db__kpis" aria-busy={loading}>
          <StatTile label="Alunos ativos" icon="users" value={v(num(par('alunos_ativos').atual))}
            delta={deltaObj(par('alunos_ativos').atual, par('alunos_ativos').anterior, vs, true, num)}
            trend={mensal.map((m) => m.ativos)} {...abre('alunos_ativos', 'os alunos ativos')} />
          <StatTile label="Novos alunos" icon="user-plus" value={v(num(par('novos_alunos').atual))}
            delta={deltaObj(par('novos_alunos').atual, par('novos_alunos').anterior, vs, true, num)} {...abre('novos_alunos', 'os novos alunos')} />
          <StatTile label="Voltaram" icon="refresh-cw" value={v(num(par('reativados').atual))}
            delta={deltaObj(par('reativados').atual, par('reativados').anterior, vs, true, num)} {...abre('reativados', 'quem voltou')} />
          <StatTile label="Saídas" icon="log-out" value={v(num(par('saidas').atual))}
            delta={deltaObj(par('saidas').atual, par('saidas').anterior, vs, false, num)} {...abre('saidas', 'quem saiu')} />
        </div>

        <div className="ck-db__kpis">
          <StatTile label="Saldo" icon="trending-up" value={v(sinal(par('saldo').atual))}
            delta={deltaObj(par('saldo').atual, par('saldo').anterior, vs, true, num)} note={dados ? 'Novos + voltaram − saídas' : null} />
          <StatTile label="Retenção" icon="repeat" value={v(pct(par('retencao').atual))}
            delta={deltaPp(par('retencao').atual, par('retencao').anterior, vs, true)} note={dados ? 'Dos ativos no início' : null} />
          <StatTile label="Permanência de quem saiu" icon="clock" value={v(meses(par('permanencia_meses').atual))}
            delta={deltaObj(par('permanencia_meses').atual, par('permanencia_meses').anterior, vs, true, (x) => meses(Math.round(x * 10) / 10))}
            note={dados ? 'Média, até o cancelamento' : null} />
        </div>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel title="Alunos ativos por mês" description="No último dia de cada mês; o último ponto é o fim do período.">
            {carregando ? carregandoTxt : mensal.some((m) => m.ativos) ? (
              <ColumnChart data={mensal.map((m) => ({ label: mesCurto(m.mes), tip: mesLongo(m.mes), value: m.ativos }))}
                highlightLast height={200} caption="Alunos ativos por mês" />
            ) : <Empty icon="users" title="Nenhum aluno ativo nos últimos 12 meses" />}
          </Panel>
          <Panel
            title="Entradas e saídas por mês"
            description="Entradas = novos alunos + quem voltou."
            actions={<ChartLegend items={[{ label: 'Saídas', kind: 'outline' }, { label: 'Entradas', kind: 'accent' }]} />}
          >
            {carregando ? carregandoTxt : temMovimento ? (
              <GroupedColumnChart
                labels={mensal.map((m) => mesCurto(m.mes))}
                tips={mensal.map((m) => mesLongo(m.mes))}
                a={{ name: 'Saídas', values: mensal.map((m) => m.saidas) }}
                b={{ name: 'Entradas', values: mensal.map((m) => m.novos + m.reativados) }}
                ratioText={false}
                height={200}
                caption="Entradas e saídas de alunos por mês"
              />
            ) : <Empty icon="activity" title="Nenhuma entrada ou saída nos últimos 12 meses" />}
          </Panel>
        </div>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel title="Alunos ativos por plano" description="No fim do período.">
            {carregando ? carregandoTxt : (dados?.por_plano || []).length ? <ChartBarList items={barras(dados.por_plano, 'Outros planos')} />
              : <Empty icon="layers" title="Nenhum aluno ativo" />}
          </Panel>
          <Panel title="Alunos ativos por modalidade" description="No fim do período, pela modalidade do plano.">
            {carregando ? carregandoTxt : (dados?.por_modalidade || []).length ? <ChartBarList items={barras(dados.por_modalidade, 'Outras modalidades')} />
              : <Empty icon="dumbbell" title="Nenhum aluno ativo" />}
          </Panel>
        </div>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel title="Quanto tempo ficou quem saiu" description={totalSaidasFaixas ? `${num(totalSaidasFaixas)} ${totalSaidasFaixas === 1 ? 'saída' : 'saídas'} no período, pelo tempo desde a primeira matrícula.` : 'Saídas do período, pelo tempo desde a primeira matrícula.'}>
            {carregando ? carregandoTxt : totalSaidasFaixas ? <ChartBarList items={faixas} />
              : <Empty icon="check-circle" title="Ninguém saiu no período" />}
          </Panel>
          <Panel title="Resumo por mês" description="Do mês mais recente para o mais antigo.">
            {carregando ? carregandoTxt : (
              <div className="ck-mt-table-wrap">
                <table className="ck-mt-table">
                  <thead>
                    <tr>
                      <th scope="col">Mês</th>
                      <th scope="col" className="is-num">Ativos</th>
                      <th scope="col" className="is-num">Novos</th>
                      <th scope="col" className="is-num">Voltaram</th>
                      <th scope="col" className="is-num">Saídas</th>
                      <th scope="col" className="is-num">Saldo</th>
                    </tr>
                  </thead>
                  <tbody>
                    {mensal.slice().reverse().map((m) => (
                      <tr key={m.mes}>
                        <th scope="row">{mesLongo(m.mes)}</th>
                        <td className="is-num">{num(m.ativos)}</td>
                        <td className="is-num">{num(m.novos)}</td>
                        <td className="is-num">{num(m.reativados)}</td>
                        <td className="is-num">{num(m.saidas)}</td>
                        <td className="is-num">{sinal(m.novos + m.reativados - m.saidas)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>
        </div>

        <ComoCalculamos itens={COMO_CALCULAMOS} />
      </div>

      <BiDetalheModal indicador={detalhe} periodo={periodo} onClose={() => setDetalhe(null)} />
    </LayoutBase>
  );
}
