import React, { useState } from 'react';
import { Avatar, Button, Icon, IconButton } from './index';
import { Panel, titleCase } from './pessoas';
import { AlunoFoto, ScoreBadge } from './prospeccao';
import { mesCurto, moeda, numero, tempoInativo } from '../../utils/prospeccao';

const pct = (n, total) => total ? `${Number((n / total * 100).toFixed(1)).toLocaleString('pt-BR')}%` : '—';
const ETAPAS = [['nao_contatado', 'Não contatado', 'muted'], ['contatado', 'Contatado', 'info'], ['interessado', 'Interessado', 'brand'], ['em_negociacao', 'Negociação', 'warning'], ['retorno_agendado', 'Retorno agendado', 'info'], ['recuperado', 'Recuperado', 'success']];

function Barras({ items }) {
  const total = items.reduce((s, x) => s + Number(x.total), 0);
  return <ul className="ck-rp__bars2">{items.map((x) => <li key={x.label} className={x.muted ? 'is-none' : undefined}>
    <span>{x.label}</span><b>{numero(x.total)}</b><i aria-hidden="true"><i style={{ width: `${total ? x.total / total * 100 : 0}%` }} /></i>
  </li>)}</ul>;
}

export default function PainelRecuperacaoResumo({ dashboard: d, relatorio: rel, fila, erroFila, onAbrir, onModelos, onEquipe }) {
  const [tabela, setTabela] = useState(false);
  const k = d.indicadores;
  const meses = rel?.meses || [];
  const maximo = Math.max(1, ...meses.flatMap((m) => [m.cancelamentos, m.recuperacoes]));
  const totalCancelados = meses.reduce((s, m) => s + Number(m.cancelamentos), 0);
  const totalRetornos = meses.reduce((s, m) => s + Number(m.recuperacoes), 0);
  // `contatados` já inclui interessados, negociação, retorno e recuperados na API.
  const etapas = ETAPAS.map(([status, label, tom]) => ({ status, label, tom, total: Number(d.graficos.por_status.find((s) => s.status === status)?.total || 0) }));
  const totalEtapas = etapas.reduce((s, e) => s + e.total, 0);
  const motivos = d.graficos.motivos || [];
  const semMotivo = motivos.length > 0 && motivos.every((m) => !m.motivo);
  const emConversa = Number(k.interessados) + Number(k.em_negociacao) + Number(k.retorno_agendado);
  return <>
    <dl className="ck-rp__kpis">
      <div><dt>Cancelaram</dt><dd>{numero(k.cancelados)}</dd><span>{numero(k.elegiveis)} podem ser contatados</span></div>
      <div className={!k.contatados && k.nao_contatados ? 'is-alert' : undefined}><dt>Sem nenhum contato</dt><dd>{numero(k.nao_contatados)}</dd><span>{pct(k.nao_contatados, k.elegiveis)} dos que podem</span></div>
      <div><dt>Em conversa</dt><dd>{numero(emConversa)}</dd><span>{numero(k.interessados)} interessados, {numero(k.em_negociacao)} negociando, {numero(k.retorno_agendado)} com retorno</span></div>
      <div className="is-hero"><dt>Voltaram</dt><dd>{numero(k.recuperados)}</dd><span>{k.taxa_conversao == null ? 'Sem elegíveis no período' : `${Number(k.taxa_conversao).toLocaleString('pt-BR')}% de conversão`}</span></div>
      <div><dt>Receita recuperada</dt><dd>{moeda(k.receita_mensal_recuperada)}</dd><span>por mês, matrículas de volta ativas</span></div>
    </dl>
    {!k.contatados && k.nao_contatados > 0 ? <section className="ck-rp__start" aria-label="Por onde começar">
      <div className="ck-rp__starttxt">
        <span className="ck-rp__eyebrow">Por onde começar</span><h2>{numero(k.nao_contatados)} alunos esperando o primeiro contato</h2>
        <p>Comece pelos de maior score. Confira a ficha, revise a mensagem e registre como foi o contato.</p>
        <div className="ck-rp__startacts"><Button variant="primary" icon="message-circle" disabled={!fila.length} onClick={() => onAbrir(fila[0])}>Começar pelos de maior score</Button><Button icon="file-text" onClick={onModelos}>Revisar mensagens</Button></div>
      </div>
      {fila.length ? <ol className="ck-rp__queue" aria-label="Alunos de maior score">{fila.map((a, i) => <li key={a.id}>
        <span className="ck-rp__qn">{i + 1}</span><AlunoFoto nome={a.aluno_nome} foto={a.foto_caminho} />
        <button type="button" className="ck-rp__qname" onClick={() => onAbrir(a)}><strong>{titleCase(a.aluno_nome)}</strong><span>{a.plano_nome} · cancelou há {tempoInativo(a.dias_inatividade)}</span></button>
        <ScoreBadge score={a.score} potencial={a.potencial} confianca={a.score_confianca} />
        <IconButton icon="chevron-right" label={`Abrir ficha de ${titleCase(a.aluno_nome)}`} size="sm" onClick={() => onAbrir(a)} />
      </li>)}</ol> : <p className="ck-rp__queue-state" role="status">{erroFila || 'Carregando alunos de maior score…'}</p>}
    </section> : null}
    <div className="ck-rp__grid">
      <Panel title="Cancelamentos e retornos por mês" description={`${numero(totalCancelados)} cancelaram e ${numero(totalRetornos)} voltaram no período. Cada série considera a data do respectivo evento.`}
        actions={<Button size="sm" variant="ghost" icon={tabela ? 'trending-up' : 'file-text'} aria-pressed={tabela} onClick={() => setTabela((v) => !v)}>{tabela ? 'Ver gráfico' : 'Ver tabela'}</Button>}>
        {tabela ? <div className="ck-rp__table-wrap"><table className="ck-rp__tbl"><caption className="ck-sr">Cancelamentos e retornos por mês</caption><thead><tr><th scope="col">Mês</th><th scope="col" className="is-num">Cancelaram</th><th scope="col" className="is-num">Voltaram</th></tr></thead><tbody>{meses.slice().reverse().map((m) => <tr key={m.mes}><th scope="row">{mesCurto(m.mes)}</th><td className="is-num">{numero(m.cancelamentos)}</td><td className="is-num">{numero(m.recuperacoes)}</td></tr>)}</tbody></table></div>
          : <div className="ck-rp__chart-scroll"><div className="ck-rp__chart" style={{ minWidth: Math.max(480, meses.length * 42) }} role="img" aria-label={meses.map((m) => `${mesCurto(m.mes)}: ${m.cancelamentos} cancelaram, ${m.recuperacoes} voltaram`).join('; ')}>
            <div className="ck-rp__bars">{meses.map((m) => <div key={m.mes} className="ck-rp__col" style={{ '--h': Math.max(m.cancelamentos, m.recuperacoes) / maximo * 100 }} title={`${mesCurto(m.mes)}: ${m.cancelamentos} cancelaram, ${m.recuperacoes} voltaram`}>
              <span className="ck-rp__val">{m.cancelamentos || ''}</span><div className="ck-rp__pair"><i className="is-c" style={{ height: `${m.cancelamentos / maximo * 100}%` }} /><i className="is-r" style={{ height: `${m.recuperacoes / maximo * 100}%` }} /></div><span className="ck-rp__lbl">{mesCurto(m.mes)}</span>
            </div>)}</div><div className="ck-rp__legend"><span className="is-c">Cancelaram</span><span className="is-r">Voltaram</span></div>
          </div></div>}
      </Panel>
      <Panel title="Onde estão os alunos" description="Situação de hoje dos que cancelaram no período.">
        <div className="ck-rp__stack" aria-hidden="true">{etapas.filter((e) => e.total).map((e) => <i key={e.status} className={`is-${e.tom}`} style={{ width: `${e.total / totalEtapas * 100}%` }} />)}</div>
        <ul className="ck-rp__stages">{etapas.map((e) => <li key={e.status} className={e.total ? undefined : 'is-zero'}><span className={`ck-rp__dot is-${e.tom}`} aria-hidden="true" /><span>{e.label}</span><b>{numero(e.total)}</b><em>{pct(e.total, totalEtapas)}</em></li>)}</ul>
        <p className="ck-rp__out">Fora da lista: <b>{numero(k.sem_interesse)}</b> sem interesse, <b>{numero(k.nao_contatar)}</b> pediram para não ser contatados.</p>
      </Panel>
    </div>
    <div className="ck-rp__grid3">
      <Panel title="Motivos de cancelamento">{semMotivo || !motivos.length ? <div className="ck-rp__empty"><Icon name="help-circle" size={18} /><p><b>Nenhum motivo registrado.</b> Pergunte no primeiro contato e marque na ficha do aluno. O motivo entra no score.</p></div> : <Barras items={motivos.map((m) => ({ label: m.rotulo, total: m.total, muted: !m.motivo }))} />}</Panel>
      <Panel title="Retornos por modalidade">{d.graficos.por_modalidade.length ? <Barras items={d.graficos.por_modalidade.map((m) => ({ label: m.modalidade || 'Sem modalidade', total: m.total }))} /> : <div className="ck-rp__empty"><Icon name="trending-up" size={18} /><p>Aparece quando o primeiro aluno voltar.</p></div>}</Panel>
      <Panel title="Contatos da equipe">{rel?.equipe?.length ? <ul className="ck-rp__team">{rel.equipe.map((u) => <li key={u.usuario_id || 'removido'}><Avatar name={titleCase(u.nome || 'Removido')} size="sm" decorative /><span>{titleCase(u.nome || 'Removido')}</span><b>{numero(u.contatos)} contatos</b><em>{numero(u.oportunidades)} alunos diferentes</em></li>)}</ul> : <div className="ck-rp__empty"><Icon name="users" size={18} /><p>Ninguém registrou contato no período.{onEquipe ? <Button variant="ghost" size="sm" onClick={onEquipe}>Definir a equipe comercial</Button> : null}</p></div>}</Panel>
    </div>
  </>;
}
