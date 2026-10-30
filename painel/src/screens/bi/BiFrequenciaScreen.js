import React, { useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Avatar, Button, Callout, PageHeader, SegmentedControl } from '../../components/ck';
import { Panel, StatTile, titleCase } from '../../components/ck/pessoas';
import { ChartBarList, ColumnChart, Heatmap, deltaObj } from '../../components/ck/graficos';
import { fdate } from '../../components/ck/financeiro';
import { buscarBiFrequencia } from '../../services/biService';
import BiDetalheModal from './BiDetalheModal';
import { ComoCalculamos, Empty, PERIODOS, deltaPp, mesCurto, mesLongo, num, pct, textoVs, useBiCarga } from './biComum';

// Fórmulas exibidas na tela; a fonte é App\Services\Bi\BiFrequenciaService
const COMO_CALCULAMOS = [
  ['Comparação', 'Contra o período anterior de mesma duração. Em "Este mês", do dia 1 até hoje contra o mesmo trecho do mês passado.'],
  ['Check-ins', 'Pelo dia da aula. Check-ins marcados como ausente não contam.'],
  ['Alunos que vieram', 'Alunos com pelo menos um check-in no período. A participação é esse número ÷ alunos ativos no fim do período.'],
  ['Frequência semanal', 'Check-ins ÷ alunos que vieram ÷ semanas do período. Em períodos longos fica mais baixa, porque inclui quem treinou só algumas semanas.'],
  ['Ocupação', 'Check-ins da aula ÷ limite de alunos, só nas aulas com limite cadastrado. A ocupação de uma turma é a média das aulas dela.'],
  ['Turma', 'Aulas com a mesma modalidade, horário e professor, somadas no período.'],
  ['Mapa de horários', 'Média de check-ins por dia da semana e hora da aula: total ÷ quantas vezes aquele dia da semana aparece no período.'],
  ['Sem check-in há 14 dias', 'Alunos ativos sem check-in há 14 dias ou mais (ou que nunca fizeram). Quem começou a matrícula atual há menos de 14 dias fica de fora.'],
  ['Frequência em queda', 'Alunos ativos cuja média semanal das últimas 4 semanas ficou abaixo de 60% da média das 12 semanas anteriores, com pelo menos 4 check-ins nessas 12 semanas.'],
];

const DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const vezes = (v) => (v == null ? '—' : `${String(v).replace('.', ',')}x`);

/** Lista curta de alunos em atenção; o nome abre a matrícula */
function ListaAtencao({ itens, vazio, onAbrir }) {
  if (!itens.length) return <Empty icon="check-circle" title={vazio} />;
  return (
    <ul className="ck-db__list">
      {itens.map((x) => (
        <li key={x.aluno_id}>
          <Avatar name={titleCase(x.aluno_nome)} size="sm" decorative />
          <button type="button" className="ck-db__who ck-db__link" onClick={() => onAbrir(x.matricula_id)} title="Abrir a matrícula">
            <strong>{titleCase(x.aluno_nome)}</strong><span>{x.plano_nome || 'Sem plano'}</span>
          </button>
          <span className="ck-db__when">
            <strong>{x.data ? fdate(x.data) : '—'}</strong>
            <span>{x.detalhe}</span>
          </span>
        </li>
      ))}
    </ul>
  );
}

export default function BiFrequenciaScreen() {
  const router = useRouter();
  const [periodo, setPeriodo] = useState('mes');
  const [detalhe, setDetalhe] = useState(null);
  const { dados, erro, loading, recarregar } = useBiCarga(buscarBiFrequencia, periodo);

  const ind = dados?.indicadores || {};
  const per = dados?.periodo;
  const vs = textoVs(per);
  const v = (x) => (loading && !dados ? '—' : x);
  const par = (k) => ind[k] || {};
  const abre = (k, nome) => (dados ? { onClick: () => setDetalhe(k), clickLabel: `Ver ${nome}` } : {});
  const carregando = loading && !dados;
  const carregandoTxt = <p className="ck-fin-empty" role="status">Carregando…</p>;
  const abrirMatricula = (id) => router.push(`/matriculas/detalhe?id=${id}`);

  const serie = dados?.checkins_serie;
  const checkins = (serie?.pontos || []).map((p) => (serie.agrupamento === 'dia'
    ? { label: fdate(p.data).slice(0, 5), tip: fdate(p.data), value: p.total }
    : { label: mesCurto(p.data), tip: mesLongo(p.data), value: p.total }));
  const totalCheckins = checkins.reduce((a, x) => a + x.value, 0);

  const mapa = dados?.mapa || [];
  const horas = mapa.map((x) => x.hora);
  const de = Math.min(6, ...horas);
  const ate = Math.max(21, ...horas);
  const cols = Array.from({ length: ate - de + 1 }, (_, i) => `${de + i}h`);
  const heat = DIAS.map((_, i) => cols.map((__, j) => mapa.find((x) => x.dia_semana === i + 1 && x.hora === de + j)?.media || 0));

  const turmas = dados?.turmas || [];
  const professores = (dados?.por_professor || []).map((x) => ({
    label: x.nome, value: x.checkins, note: `${num(x.aulas)} ${x.aulas === 1 ? 'aula' : 'aulas'}, ${String(x.media_por_aula).replace('.', ',')} por aula`,
  }));
  const atencao = dados?.atencao || { sem_checkin: [], em_queda: [] };

  return (
    <LayoutBase title="Frequência e ocupação" pageHeader={false}>
      <div className="ck-db">
        <PageHeader
          title="Frequência e ocupação"
          description={per ? `De ${fdate(per.inicio)} a ${fdate(per.fim)}. Quando os alunos vêm, quais turmas enchem e quem está vindo menos.` : 'Quando os alunos vêm, quais turmas enchem e quem está vindo menos.'}
          actions={<SegmentedControl label="Período" value={periodo} onChange={setPeriodo} options={PERIODOS} />}
        />

        {erro ? (
          <Callout tone="warning" title="Os indicadores não carregaram" action={<Button size="sm" icon="refresh-cw" onClick={recarregar}>Tentar de novo</Button>}>
            {erro}
          </Callout>
        ) : null}

        <div className="ck-db__kpis" aria-busy={loading}>
          <StatTile label="Check-ins" icon="activity" value={v(num(par('checkins').atual))}
            delta={deltaObj(par('checkins').atual, par('checkins').anterior, vs, true)} />
          <StatTile label="Alunos que vieram" icon="users" value={v(num(par('participantes').atual))}
            delta={deltaObj(par('participantes').atual, par('participantes').anterior, vs, true, num)}
            note={par('participacao').atual != null ? `${pct(par('participacao').atual)} dos ativos` : null} />
          <StatTile label="Frequência semanal" icon="repeat" value={v(par('frequencia_semanal').atual == null ? '—' : `${vezes(par('frequencia_semanal').atual)} por semana`)}
            delta={deltaObj(par('frequencia_semanal').atual, par('frequencia_semanal').anterior, vs, true)} note={dados ? 'Por aluno que veio' : null} />
          <StatTile label="Ocupação média" icon="calendar" value={v(pct(par('ocupacao').atual))}
            delta={deltaPp(par('ocupacao').atual, par('ocupacao').anterior, vs, true)} note={dados ? 'Aulas com limite de alunos' : null} />
        </div>

        <div className="ck-db__kpis">
          <StatTile label="Sem check-in há 14 dias" icon="alert-triangle" value={v(num(par('sem_checkin').atual))}
            note={dados ? 'Alunos ativos, no fim do período' : null} {...abre('sem_checkin', 'quem está sem check-in')} />
          <StatTile label="Frequência em queda" icon="trending-down" value={v(num(par('em_queda').atual))}
            note={dados ? 'Vindo bem menos que antes' : null} {...abre('em_queda', 'quem está vindo menos')} />
        </div>

        <div className="ck-db__row ck-db__row--2-1">
          <Panel title={serie?.agrupamento === 'mes' ? 'Check-ins por mês' : 'Check-ins por dia'}
            description={dados ? `${num(totalCheckins)} ${totalCheckins === 1 ? 'check-in' : 'check-ins'} no período` : null}>
            {carregando ? carregandoTxt : totalCheckins ? (
              <ColumnChart data={checkins} highlightLast height={200} labelEvery={serie?.agrupamento === 'dia' ? 5 : 1}
                reference={serie?.agrupamento === 'dia' ? totalCheckins / checkins.length : null}
                referenceLabel={serie?.agrupamento === 'dia' ? `Média ${num(Math.round(totalCheckins / checkins.length))}` : null}
                caption="Check-ins no período" />
            ) : <Empty icon="activity" title="Nenhum check-in no período" />}
          </Panel>
          <Panel title="Check-ins por professor" description="Nas aulas de cada professor, no período.">
            {carregando ? carregandoTxt : professores.length ? <ChartBarList items={professores} />
              : <Empty icon="user" title="Nenhuma aula no período" />}
          </Panel>
        </div>

        <Panel title="Mapa de horários" description="Média de check-ins por dia da semana e hora da aula. Mais escuro, mais movimento.">
          {carregando ? carregandoTxt : mapa.length ? (
            <Heatmap rows={DIAS} cols={cols} values={heat} unit="check-ins" label="Check-ins por dia da semana e hora" />
          ) : <Empty icon="clock" title="Nenhum check-in no período" />}
        </Panel>

        <Panel title="Turmas" description="Modalidade, horário e professor, somando as aulas do período. A ocupação só aparece quando a turma tem limite de alunos.">
          {carregando ? carregandoTxt : turmas.length ? (
            <div className="ck-mt-table-wrap">
              <table className="ck-mt-table">
                <thead>
                  <tr>
                    <th scope="col">Turma</th>
                    <th scope="col">Professor</th>
                    <th scope="col" className="is-num">Aulas</th>
                    <th scope="col" className="is-num">Check-ins</th>
                    <th scope="col" className="is-num">Por aula</th>
                    <th scope="col" className="is-num">Limite</th>
                    <th scope="col" className="is-num">Ocupação</th>
                  </tr>
                </thead>
                <tbody>
                  {turmas.map((t) => (
                    <tr key={`${t.modalidade}-${t.horario}-${t.professor}`}>
                      <th scope="row">{`${t.modalidade} ${t.horario}`}</th>
                      <td>{t.professor ? titleCase(t.professor) : 'Sem professor'}</td>
                      <td className="is-num">{num(t.aulas)}</td>
                      <td className="is-num">{num(t.checkins)}</td>
                      <td className="is-num">{String(t.media_por_aula).replace('.', ',')}</td>
                      <td className="is-num">{t.limite == null ? 'Sem limite' : num(t.limite)}</td>
                      <td className="is-num">{pct(t.ocupacao)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : <Empty icon="calendar" title="Nenhuma aula no período" />}
        </Panel>

        <div className="ck-db__row ck-db__row--1-1">
          <Panel title="Sem check-in há 14 dias" description="Os mais tempo sem vir primeiro."
            actions={atencao.sem_checkin.length ? <Button size="sm" variant="ghost" iconRight="arrow-right" onClick={() => setDetalhe('sem_checkin')}>Ver todos</Button> : null}>
            {carregando ? carregandoTxt : <ListaAtencao itens={atencao.sem_checkin} vazio="Todos os ativos vieram nos últimos 14 dias" onAbrir={abrirMatricula} />}
          </Panel>
          <Panel title="Frequência em queda" description="As maiores quedas primeiro."
            actions={atencao.em_queda.length ? <Button size="sm" variant="ghost" iconRight="arrow-right" onClick={() => setDetalhe('em_queda')}>Ver todos</Button> : null}>
            {carregando ? carregandoTxt : <ListaAtencao itens={atencao.em_queda} vazio="Ninguém com queda de frequência" onAbrir={abrirMatricula} />}
          </Panel>
        </div>

        <ComoCalculamos itens={COMO_CALCULAMOS} />
      </div>

      <BiDetalheModal indicador={detalhe} periodo={periodo} onClose={() => setDetalhe(null)} />
    </LayoutBase>
  );
}
