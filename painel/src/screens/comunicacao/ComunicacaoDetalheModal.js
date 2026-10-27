import React, { useCallback, useEffect, useState } from 'react';
import { Badge, Button, Callout, Modal, SegmentedControl, Tabs, TextField } from '../../components/ck';
import { ChartBarList, Meter } from '../../components/ck/graficos';
import comunicacaoService from '../../services/comunicacaoService';
import { downloadCsv } from '../../utils/csv';
import { showError } from '../../utils/toast';
import { STATUS, TIPO, fdatetime, msg, publicoResumo } from './comunicacaoUtil';

/**
 * Visualização de uma comunicação com o relatório: quem confirmou/respondeu, quem está pendente e,
 * nas enquetes, a distribuição das respostas. Props: open, comunicacao (linha da lista), canReports,
 * canAnswers, onClose.
 */
export default function ComunicacaoDetalheModal({ open, comunicacao: c, canReports, canAnswers, onClose }) {
  const enquete = c.tipo === 'enquete';
  const [tab, setTab] = useState('resumo');
  const [situacao, setSituacao] = useState('todos');
  const [busca, setBusca] = useState('');
  const [dest, setDest] = useState(null);
  const [res, setRes] = useState(null);
  const [erro, setErro] = useState('');
  const [exportando, setExportando] = useState(false);

  const carregarDest = useCallback(() => {
    if (!canReports) return;
    setDest(null); setErro('');
    comunicacaoService.destinatarios(c.id, { situacao, busca: busca.trim() || undefined, por_pagina: 200 })
      .then(setDest).catch(e => setErro(msg(e, 'Não foi possível carregar os destinatários.')));
  }, [c.id, situacao, busca, canReports]);

  useEffect(() => { if (tab === 'destinatarios') { const t = setTimeout(carregarDest, busca ? 300 : 0); return () => clearTimeout(t); } return undefined; }, [tab, carregarDest, busca]);
  useEffect(() => {
    if (enquete && canReports && tab === 'resultados' && !res) {
      comunicacaoService.resultados(c.id).then(setRes).catch(e => setErro(msg(e, 'Não foi possível carregar os resultados.')));
    }
  }, [tab, enquete, canReports, c.id, res]);

  const acao = enquete ? 'Responderam' : 'Confirmaram';
  async function exportarDestinatarios() {
    setExportando(true);
    try {
      const r = await comunicacaoService.destinatarios(c.id, { situacao, por_pagina: 5000 });
      downloadCsv(`comunicacao-${c.id}-destinatarios.csv`, [
        ['Aluno', 'E-mail', 'Telefone', 'Situação', enquete ? 'Respondeu em' : 'Confirmou em', 'Visualizou em', 'Recebeu em'],
        ...(r.destinatarios || []).map(d => [d.nome, d.email, d.telefone, d.situacao === 'concluido' ? (enquete ? 'Respondeu' : 'Confirmou') : 'Pendente',
          fdatetime(d.concluida_em), fdatetime(d.visualizada_em), fdatetime(d.disponibilizada_em)]),
      ]);
    } catch (e) {
      showError(msg(e, 'Não foi possível exportar.'));
    } finally {
      setExportando(false);
    }
  }
  async function exportarRespostas() {
    setExportando(true);
    try {
      if (res?.anonima || !canAnswers) {
        downloadCsv(`enquete-${c.id}-resultados.csv`, [
          ['Pergunta', 'Opção', 'Total'],
          ...(res?.perguntas || []).flatMap(p => (p.opcoes ? p.opcoes.map(o => [p.pergunta, o.rotulo, o.total]) : (p.textos || []).map(t => [p.pergunta, t.texto, '']))),
        ]);
      } else {
        const r = await comunicacaoService.respostas(c.id);
        downloadCsv(`enquete-${c.id}-respostas.csv`, [['Aluno', 'E-mail', 'Pergunta', 'Resposta', 'Respondida em'],
          ...(r.respostas || []).map(x => [x.aluno, x.email, x.pergunta, x.resposta, fdatetime(x.respondida_em)])]);
      }
    } catch (e) {
      showError(msg(e, 'Não foi possível exportar.'));
    } finally {
      setExportando(false);
    }
  }

  const [tone, label] = STATUS[c.status] || STATUS.rascunho;
  const tabs = [{ value: 'resumo', label: 'Resumo' }];
  if (canReports && c.status !== 'rascunho') tabs.push({ value: 'destinatarios', label: 'Destinatários' });
  if (canReports && enquete && c.status !== 'rascunho') tabs.push({ value: 'resultados', label: 'Resultados' });

  return <Modal open={open} size="lg" title={c.titulo} description={`${TIPO[c.tipo]?.label || c.tipo} · ${publicoResumo(c.publico)}`} onClose={onClose}
    footer={<Button onClick={onClose}>Fechar</Button>}>
    <div className="ck-com__det">
      <div className="ck-com__badges">
        <Badge tone={tone} dot>{label}</Badge>
        {c.obrigatoria ? <Badge tone="warning" icon="lock">Obrigatória: trava o check-in</Badge> : null}
        <span>{fdatetime(c.inicio_em || c.publicada_em)} → {fdatetime(c.termino_em)}</span>
      </div>
      {tabs.length > 1 ? <Tabs label="Seções" tabs={tabs} value={tab} onChange={setTab} /> : null}
      {erro ? <Callout tone="danger">{erro}</Callout> : null}

      {tab === 'resumo' ? <>
        {c.status !== 'rascunho' ? <Meter total={c.destinatarios} segments={[
          { label: acao, value: c.leituras, tone: 'success' },
          { label: 'Pendentes', value: c.pendentes, tone: c.obrigatoria ? 'danger' : 'muted' },
        ]} /> : <Callout tone="info">Rascunho: ainda não foi enviado aos alunos.</Callout>}
        {c.descricao ? <p className="ck-com__pdesc">{c.descricao}</p> : null}
        {c.conteudo ? <div className="ck-com__texto">{c.conteudo}</div> : null}
        {(c.configuracoes?.links || []).map((l, i) => <a key={i} className="ck-com__plink" href={l.url} target="_blank" rel="noreferrer">{l.rotulo}</a>)}
        {(c.configuracoes?.anexos || []).map((a, i) => <a key={i} className="ck-com__plink" href={a.url} target="_blank" rel="noreferrer">{a.nome}</a>)}
      </> : null}

      {tab === 'destinatarios' ? <>
        <div className="ck-com__bar">
          <SegmentedControl label="Situação" value={situacao} onChange={setSituacao}
            options={[{ value: 'todos', label: 'Todos' }, { value: 'concluidos', label: acao }, { value: 'pendentes', label: 'Pendentes' }]} />
          <TextField icon="search" placeholder="Buscar aluno" value={busca} onChange={e => setBusca(e.target.value)} />
          <Button icon="download" loading={exportando} onClick={exportarDestinatarios}>CSV</Button>
        </div>
        {!dest ? <p className="ck-com__hint" role="status">Carregando…</p> : dest.destinatarios.length ? <div className="ck-pc__wrap"><table className="ck-com__tbl">
          <thead><tr><th scope="col">Aluno</th><th scope="col">Situação</th><th scope="col">{enquete ? 'Respondeu' : 'Confirmou'}</th><th scope="col">Visualizou</th></tr></thead>
          <tbody>{dest.destinatarios.map(d => <tr key={d.aluno_id}>
            <th scope="row"><strong>{d.nome}</strong><small>{d.email}</small></th>
            <td>{d.situacao === 'concluido' ? <Badge tone="success" dot>{enquete ? 'Respondeu' : 'Confirmou'}</Badge> : <Badge tone={c.obrigatoria ? 'warning' : 'outline'} dot>Pendente</Badge>}</td>
            <td>{fdatetime(d.concluida_em)}</td>
            <td>{fdatetime(d.visualizada_em)}</td>
          </tr>)}</tbody>
        </table>{dest.paginacao.total > dest.destinatarios.length ? <p className="ck-com__hint">Mostrando {dest.destinatarios.length} de {dest.paginacao.total}. Exporte o CSV para ver todos.</p> : null}</div>
          : <p className="ck-com__hint">Ninguém nesta situação.</p>}
      </> : null}

      {tab === 'resultados' ? (!res ? <p className="ck-com__hint" role="status">Carregando…</p> : <>
        <div className="ck-com__bar">
          <span className="ck-com__hint">{res.participantes} de {res.destinatarios} responderam{res.anonima ? ' · anônima' : ''}</span>
          <Button icon="download" loading={exportando} onClick={exportarRespostas}>{res.anonima || !canAnswers ? 'CSV dos resultados' : 'CSV das respostas'}</Button>
        </div>
        {res.perguntas.map(p => <section key={p.id} className="ck-com__res">
          <h3>{p.pergunta}</h3>
          {p.opcoes ? <ChartBarList showShare={false} format={v => String(v)}
            items={p.opcoes.map(o => ({ label: o.rotulo, value: o.total, note: p.respondentes ? `${Math.round((o.total / p.respondentes) * 100)}% de ${p.respondentes}` : undefined }))} /> : null}
          {p.media != null ? <p className="ck-com__hint">Média {String(p.media).replace('.', ',')}</p> : null}
          {p.textos ? (p.textos.length ? <ul className="ck-com__textos">{p.textos.map((t, i) => <li key={i}>{t.texto}{t.aluno ? <small>{t.aluno}</small> : null}</li>)}</ul>
            : <p className="ck-com__hint">Sem respostas.</p>) : null}
        </section>)}
      </>) : null}
    </div>
  </Modal>;
}
