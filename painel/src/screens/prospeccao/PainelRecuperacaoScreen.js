import React, { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, PageHeader, SelectField, TextField } from '../../components/ck';
import { Panel, StatTile, titleCase } from '../../components/ck/pessoas';
import { ChartBarList, ColumnChart } from '../../components/ck/graficos';
import prospeccaoService from '../../services/prospeccaoService';
import { erroApi, mesCurto, moeda, numero } from '../../utils/prospeccao';

const DEFINICOES = [
  ['Contagens', 'São de alunos (não de matrículas). Aluno com várias matrículas canceladas conta uma vez, pelo cancelamento mais recente.'],
  ['Período', 'Considera os alunos cujo cancelamento caiu no período escolhido. O status é o de hoje.'],
  ['Cancelados', 'Sem diárias avulsas e sem quem continua com outra matrícula em andamento.'],
  ['Elegíveis', 'Cancelados menos os que pediram para não ser contatados.'],
  ['Taxa de conversão', 'Recuperados ÷ elegíveis do mesmo período.'],
  ['Receita mensal recuperada', 'Valor mensal das matrículas de retorno que continuam ativas hoje.'],
  ['Recuperações por mês', 'Pela data em que o aluno voltou a ter matrícula ativa.'],
];

// Padrão: últimos 12 meses, do dia 1º (mesmo critério da API)
function periodoPadrao() {
  const hoje = new Date();
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  return { de: iso(new Date(hoje.getFullYear(), hoje.getMonth() - 11, 1)), ate: iso(hoje) };
}

export default function PainelRecuperacaoScreen() {
  const router = useRouter();
  const [filtro, setFiltro] = useState(() => ({ ...periodoPadrao(), modalidade_id: '' }));
  const [d, setD] = useState(null);
  const [rel, setRel] = useState(null);
  const [erro, setErro] = useState('');

  const carregar = useCallback(async () => {
    setErro('');
    const params = Object.fromEntries(Object.entries(filtro).filter(([, v]) => v));
    try {
      const [dash, relatorio] = await Promise.all([prospeccaoService.dashboard(params), prospeccaoService.relatorio(params)]);
      setD(dash); setRel(relatorio);
    } catch (e) { setErro(erroApi(e, 'Não foi possível carregar os indicadores.')); }
  }, [filtro.de, filtro.ate, filtro.modalidade_id]);
  useEffect(() => { carregar(); }, [carregar]);

  const k = d?.indicadores;
  const set = (c, v) => setFiltro((f) => ({ ...f, [c]: v }));

  return <LayoutBase title="Painel de recuperação" pageHeader={false}>
    <div className="ck-db ck-pr">
      <PageHeader title="Recuperação de alunos" description="Quem cancelou, quem já foi abordado e quem voltou."
        actions={<>
          <Button icon="calendar" onClick={() => router.push('/prospeccao/contatos')}>Contatos pendentes{d?.agenda ? ` (${d.agenda.hoje + d.agenda.atrasados})` : ''}</Button>
          <Button variant="primary" icon="users" onClick={() => router.push('/prospeccao/recuperacao')}>Ver alunos</Button>
        </>} />
      <div className="ck-card ck-pr-periodo">
        <TextField label="De" type="date" value={filtro.de} onChange={(e) => set('de', e.target.value)} />
        <TextField label="Até" type="date" value={filtro.ate} onChange={(e) => set('ate', e.target.value)} />
        <SelectField label="Modalidade" value={filtro.modalidade_id} onChange={(e) => set('modalidade_id', e.target.value)}
          options={[{ value: '', label: 'Todas' }, ...(d?.filtros?.modalidades || []).map((m) => ({ value: String(m.id), label: m.nome }))]} />
      </div>
      {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
      {d?.agenda?.atrasados ? <Callout tone="warning" title={`${d.agenda.atrasados} retorno${d.agenda.atrasados > 1 ? 's' : ''} atrasado${d.agenda.atrasados > 1 ? 's' : ''}`}
        action={<Button size="sm" onClick={() => router.push('/prospeccao/contatos')}>Ver agenda</Button>}>Alunos esperando o contato combinado.</Callout> : null}
      {!d && !erro ? <p role="status" className="ck-enrollment-state">Carregando indicadores…</p> : null}

      {k ? <>
        <div className="ck-db__kpis">
          <StatTile label="Alunos que cancelaram" icon="users" value={numero(k.cancelados)} note={`${numero(k.elegiveis)} elegíveis para contato`} />
          <StatTile label="Ainda não contatados" icon="phone" value={numero(k.nao_contatados)} note={`${numero(k.contatados)} já contatados`} />
          <StatTile label="Interessados / negociando" icon="message-circle" value={numero(k.interessados + k.em_negociacao)} note={`${numero(k.retorno_agendado)} com retorno agendado`} />
          <StatTile label="Recuperados" icon="check-circle" featured value={numero(k.recuperados)}
            note={k.taxa_conversao == null ? 'Sem elegíveis no período' : `${String(k.taxa_conversao).replace('.', ',')}% de conversão`} />
        </div>
        <div className="ck-db__kpis ck-pr-kpis2">
          <StatTile label="Receita mensal recuperada" icon="wallet" value={moeda(k.receita_mensal_recuperada)} note="Matrículas de retorno ativas hoje" />
          <StatTile label="Sem interesse" icon="x" value={numero(k.sem_interesse)} />
          <StatTile label="Pediram para não ser contatados" icon="lock" value={numero(k.nao_contatar)} />
        </div>

        <div className="ck-dash__row">
          <Panel title="Recuperações por mês" description="Pela data em que o aluno voltou a ter matrícula ativa.">
            <ColumnChart data={d.graficos.recuperacoes_por_mes.map((p) => ({ label: mesCurto(p.mes), value: p.total, tip: mesCurto(p.mes) }))} highlightLast labelEvery={d.graficos.recuperacoes_por_mes.length > 8 ? 2 : 1} caption="Recuperações por mês" />
          </Panel>
          <Panel title="Funil" description="Situação atual dos alunos do período.">
            <ChartBarList items={d.graficos.por_status.map((s) => ({ label: s.rotulo, value: s.total, muted: ['sem_interesse', 'nao_contatar'].includes(s.status) }))} />
          </Panel>
        </div>
        <div className="ck-dash__row ck-dash__row--even">
          <Panel title="Recuperações por modalidade">
            {d.graficos.por_modalidade.length ? <ChartBarList items={d.graficos.por_modalidade.map((m) => ({ label: m.modalidade || 'Sem modalidade', value: m.total }))} /> : <p className="ck-pr-hint">Nenhuma recuperação no período.</p>}
          </Panel>
          <Panel title="Motivos de cancelamento" description="Categoria registrada pela equipe na ficha do aluno.">
            <ChartBarList items={d.graficos.motivos.map((m) => ({ label: m.rotulo, value: m.total, muted: !m.motivo }))} />
          </Panel>
        </div>

        {rel ? <div className="ck-dash__row ck-dash__row--even">
          <Panel title="Relatório mensal">
            <div className="ck-mt-table-wrap"><table className="ck-mt-table ck-pr-mini">
              <thead><tr><th scope="col">Mês</th><th scope="col">Cancelaram</th><th scope="col">Recuperados</th></tr></thead>
              <tbody>{rel.meses.slice().reverse().map((m) => <tr key={m.mes}><td>{mesCurto(m.mes)}</td><td>{numero(m.cancelamentos)}</td><td>{numero(m.recuperacoes)}</td></tr>)}</tbody>
            </table></div>
          </Panel>
          <Panel title="Contatos da equipe" description="Contatos registrados no período.">
            {rel.equipe.length ? <ChartBarList items={rel.equipe.map((u) => ({ label: titleCase(u.nome || 'Removido'), value: u.contatos, note: `${numero(u.oportunidades)} alunos diferentes` }))} showShare={false} /> : <p className="ck-pr-hint">Nenhum contato registrado no período.</p>}
          </Panel>
        </div> : null}

        <details className="ck-card ck-pr-def">
          <summary>Como os indicadores são calculados</summary>
          <dl>{DEFINICOES.map(([t, txt]) => <React.Fragment key={t}><dt>{t}</dt><dd>{txt}</dd></React.Fragment>)}</dl>
        </details>
      </> : null}
    </div>
  </LayoutBase>;
}
