import React, { useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, PageHeader, SegmentedControl } from '../../components/ck';
import { Panel, StatTile } from '../../components/ck/pessoas';
import { ChartBarList, ChartLegend, ColumnChart, GroupedColumnChart, deltaObj } from '../../components/ck/graficos';
import { fdate } from '../../components/ck/financeiro';
import { buscarBiRetencao } from '../../services/biService';
import BiDetalheModal from './BiDetalheModal';
import { BotaoExportar, ComoCalculamos, Empty, PERIODOS, deltaPp, mesCurto, mesLongo, num, pct, textoVs, useBiCarga } from './biComum';

// Fórmulas exibidas na tela; a fonte é App\Services\Bi\BiRetencaoService
const COMO_CALCULAMOS = [
  ['Comparação', 'Contra o período anterior de mesma duração. Em "Este mês", do dia 1 até hoje contra o mesmo trecho do mês passado.'],
  ['Ativos no início', 'Alunos com matrícula vigente na véspera do primeiro dia do período. Cada um termina o período em um dos quatro grupos abaixo.'],
  ['Continuam', 'Ainda têm matrícula vigente no último dia do período (inclui quem trocou de plano ou renovou).'],
  ['Cancelaram', 'Tiveram matrícula cancelada no período e não estão ativos no fim.'],
  ['Não renovaram', 'Não cancelaram, mas o acesso acabou há mais de 7 dias sem matrícula nova.'],
  ['Renovação atrasada', 'O acesso acabou há até 7 dias. Ainda não conta como evasão.'],
  ['Retenção e evasão', 'Retenção = continuam ÷ ativos no início. Evasão = (cancelaram + não renovaram) ÷ ativos no início.'],
  ['Saídas por motivo', 'Cancelamentos do período (sem diárias e sem troca de plano) pelo motivo escolhido ao cancelar. "Não informado" são cancelamentos antigos ou sem motivo.'],
  ['Prospecção', 'Oportunidades criadas no período e até onde chegaram. Recuperados = voltaram com matrícula nova pela Prospecção no período.'],
  ['Em risco', 'Alunos ativos sem check-in há 14 dias ou com frequência em queda, no fim do período (ver Frequência e ocupação).'],
];

const GRUPOS = [
  ['continuam', 'Continuam', null],
  ['cancelaram', 'Cancelaram', 'cancelaram'],
  ['nao_renovaram', 'Não renovaram', 'nao_renovaram'],
  ['renovacao_atrasada', 'Renovação atrasada', 'renovacao_atrasada'],
];

export default function BiRetencaoScreen() {
  const router = useRouter();
  const [periodo, setPeriodo] = useState('mes');
  const [detalhe, setDetalhe] = useState(null);
  const { dados, erro, loading, recarregar } = useBiCarga(buscarBiRetencao, periodo);

  const ind = dados?.indicadores || {};
  const per = dados?.periodo;
  const vs = textoVs(per);
  const v = (x) => (loading && !dados ? '—' : x);
  const par = (k) => ind[k] || {};
  const abre = (k, nome) => (dados ? { onClick: () => setDetalhe(k), clickLabel: `Ver ${nome}` } : {});
  const carregando = loading && !dados;
  const carregandoTxt = <p className="ck-fin-empty" role="status">Carregando…</p>;

  const comp = dados?.composicao;
  const grupos = comp ? GRUPOS.map(([k, label]) => ({ label, value: comp[k] })) : [];
  const motivos = (dados?.motivos || []).map((m) => ({ label: m.nome, value: m.alunos }));
  const semMotivo = (dados?.motivos || []).find((m) => !m.codigo)?.alunos || 0;
  const totalSaidas = motivos.reduce((a, m) => a + m.value, 0);
  const mensal = dados?.mensal || [];
  const temMensal = mensal.some((m) => m.cancelaram || m.nao_renovaram);
  const funil = dados?.funil;
  const risco = dados?.risco;

  return (
    <LayoutBase title="Retenção e evasão" pageHeader={false}>
      <div className="ck-db">
        <PageHeader
          title="Retenção e evasão"
          description={per ? `De ${fdate(per.inicio)} a ${fdate(per.fim)}. Quem ficou, quem saiu e por quê, e quem voltou pela Prospecção.` : 'Quem ficou, quem saiu e por quê, e quem voltou pela Prospecção.'}
          actions={<>
            <SegmentedControl label="Período" value={periodo} onChange={setPeriodo} options={PERIODOS} />
            <BotaoExportar alvo={{ tela: 'retencao' }} periodo={periodo}>Exportar meses</BotaoExportar>
          </>}
        />

        {erro ? (
          <Callout tone="warning" title="Os indicadores não carregaram" action={<Button size="sm" icon="refresh-cw" onClick={recarregar}>Tentar de novo</Button>}>
            {erro}
          </Callout>
        ) : null}

        <div className="ck-db__kpis" aria-busy={loading}>
          <StatTile label="Retenção" icon="user-check" value={v(pct(par('retencao').atual))}
            delta={deltaPp(par('retencao').atual, par('retencao').anterior, vs, true)}
            note={dados ? `De ${num(par('ativos_inicio').atual)} ativos no início` : null} />
          <StatTile label="Evasão" icon="trending-down" value={v(pct(par('evasao').atual))}
            delta={deltaPp(par('evasao').atual, par('evasao').anterior, vs, false)} note={dados ? 'Cancelaram ou não renovaram' : null} />
          <StatTile label="Cancelaram" icon="ban" value={v(num(par('cancelaram').atual))}
            delta={deltaObj(par('cancelaram').atual, par('cancelaram').anterior, vs, false)} {...abre('cancelaram', 'quem cancelou')} />
          <StatTile label="Não renovaram" icon="clock" value={v(num(par('nao_renovaram').atual))}
            delta={deltaObj(par('nao_renovaram').atual, par('nao_renovaram').anterior, vs, false)} {...abre('nao_renovaram', 'quem não renovou')} />
        </div>

        <div className="ck-db__kpis">
          <StatTile label="Renovação atrasada" icon="alert-triangle" value={v(num(par('renovacao_atrasada').atual))}
            note={dados ? 'Acesso acabou há até 7 dias' : null} {...abre('renovacao_atrasada', 'quem está com a renovação atrasada')} />
          <StatTile label="Recuperados" icon="user-plus" value={v(num(par('recuperados').atual))}
            delta={deltaObj(par('recuperados').atual, par('recuperados').anterior, vs, true)} note={dados ? 'Voltaram pela Prospecção' : null}
            {...abre('recuperados', 'quem voltou pela Prospecção')} />
          <StatTile label="Em risco agora" icon="activity" value={v(num(par('em_risco').atual))}
            note={risco ? `${num(risco.sem_checkin)} sem check-in, ${num(risco.em_queda)} vindo menos` : null}
            onClick={dados ? () => router.push('/bi/frequencia') : undefined} clickLabel="Ver em Frequência e ocupação" />
        </div>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel title="O que aconteceu com os ativos do início" description={comp ? `${num(comp.ativos_inicio)} alunos ativos na véspera do período.` : null}>
            {carregando ? carregandoTxt : comp?.ativos_inicio ? <ChartBarList items={grupos} />
              : <Empty icon="users" title="Nenhum aluno ativo no início do período" />}
          </Panel>
          <Panel title="Saídas por motivo" description={dados ? `${num(totalSaidas)} ${totalSaidas === 1 ? 'cancelamento' : 'cancelamentos'} no período.` : null}>
            {carregando ? carregandoTxt : motivos.length ? (
              <>
                <ChartBarList items={motivos} />
                {semMotivo ? <p className="ck-fin-empty">Cancelamentos sem motivo aparecem como "Não informado". Ao cancelar pelo painel, o motivo passa a ser obrigatório.</p> : null}
              </>
            ) : <Empty icon="check-circle" title="Nenhuma saída no período" />}
          </Panel>
        </div>

        <div className="ck-db__row ck-db__row--2-1">
          <Panel title="Evasão por mês" description="Alunos que cancelaram ou não renovaram em cada mês."
            actions={<ChartLegend items={[{ label: 'Cancelaram', kind: 'outline' }, { label: 'Não renovaram', kind: 'accent' }]} />}>
            {carregando ? carregandoTxt : temMensal ? (
              <GroupedColumnChart
                labels={mensal.map((m) => mesCurto(m.mes))}
                tips={mensal.map((m) => `${mesLongo(m.mes)}: evasão ${pct(m.evasao)}`)}
                a={{ name: 'Cancelaram', values: mensal.map((m) => m.cancelaram) }}
                b={{ name: 'Não renovaram', values: mensal.map((m) => m.nao_renovaram) }}
                ratioText={false}
                height={200}
                caption="Saídas de alunos por mês"
              />
            ) : <Empty icon="activity" title="Nenhuma saída nos últimos 12 meses" />}
          </Panel>
          <Panel title="Recuperação pela Prospecção" description="Oportunidades criadas no período e até onde chegaram."
            actions={<Button size="sm" variant="ghost" iconRight="arrow-right" onClick={() => router.push('/prospeccao')}>Abrir</Button>}>
            {carregando ? carregandoTxt : funil?.criadas ? (
              <>
                <ChartBarList showShare={false} items={[
                  { label: 'Criadas', value: funil.criadas },
                  { label: 'Contatadas', value: funil.contatadas },
                  { label: 'Recuperadas', value: funil.recuperadas, note: funil.taxa_recuperacao != null ? `${pct(funil.taxa_recuperacao)} das criadas` : undefined },
                  { label: 'Sem interesse', value: funil.sem_interesse },
                  { label: 'Ainda abertas', value: funil.abertas },
                ]} />
              </>
            ) : <Empty icon="target" title="Nenhuma oportunidade criada no período" />}
          </Panel>
        </div>

        {mensal.length ? (
          <Panel title="Recuperados por mês" description="Alunos que voltaram pela Prospecção.">
            {mensal.some((m) => m.recuperados) ? (
              <ColumnChart data={mensal.map((m) => ({ label: mesCurto(m.mes), tip: mesLongo(m.mes), value: m.recuperados }))} highlightLast height={160} caption="Recuperados por mês" />
            ) : <Empty icon="user-plus" title="Nenhum aluno recuperado nos últimos 12 meses" />}
          </Panel>
        ) : null}

        <ComoCalculamos itens={COMO_CALCULAMOS} />
      </div>

      <BiDetalheModal indicador={detalhe} periodo={periodo} onClose={() => setDetalhe(null)} />
    </LayoutBase>
  );
}
