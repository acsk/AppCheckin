import React, { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, IconButton, Modal, SelectField, TextAreaField, TextField } from '../../components/ck';
import { Panel, titleCase, maskPhone } from '../../components/ck/pessoas';
import { ColumnChart } from '../../components/ck/graficos';
import { WhatsappContato } from '../../components/ck/whatsapp';
import { AgendarModal, AlunoFoto, ScoreBadge, StatusBadge, StatusModal, useFluxoContato } from '../../components/ck/prospeccao';
import prospeccaoService from '../../services/prospeccaoService';
import { telefoneDoAluno } from '../../utils/whatsapp';
import { dataBR, dataHoraBR, erroApi, mesCurto, moeda, paraApiDataHora, tempoInativo } from '../../utils/prospeccao';
import { showError, showSuccess } from '../../utils/toast';
import { useModelosAtivos, useProspeccaoContexto } from './useProspeccao';

const TOM_MATRICULA = { ativa: 'success', pendente: 'warning', vencida: 'danger', bloqueado: 'danger', cancelada: 'neutral', finalizada: 'outline', concluida: 'outline' };

export default function FichaProspeccaoScreen() {
  const { id } = useLocalSearchParams();
  const router = useRouter();
  const { ctx } = useProspeccaoContexto();
  const carregarModelos = useModelosAtivos();
  const [d, setD] = useState(null);
  const [erro, setErro] = useState('');
  const [form, setForm] = useState({});
  const [salvando, setSalvando] = useState(false);
  const [modal, setModal] = useState(null); // status | agendar | restricao | recuperar
  const [recuperacao, setRecuperacao] = useState(null);
  const [motivoAutorizacao, setMotivoAutorizacao] = useState('');

  const aplicar = (data) => {
    setD(data);
    const o = data.oportunidade;
    setForm({ responsavel_id: o.responsavel_id ? String(o.responsavel_id) : '', motivo_categoria: o.motivo_categoria || '', interesse: o.interesse || '', condicoes_apresentadas: o.condicoes_apresentadas || '', observacoes: o.observacoes || '' });
  };
  const carregar = useCallback(async () => {
    setErro('');
    try { aplicar(await prospeccaoService.detalhar(id)); } catch (e) { setErro(erroApi(e, 'Não foi possível abrir a ficha.')); }
  }, [id]);
  useEffect(() => { carregar(); }, [carregar]);

  const fluxo = useFluxoContato({
    ctx,
    registrar: async (oid, dados) => aplicar(await prospeccaoService.registrarInteracao(oid, dados)),
    depois: () => showSuccess('Contato registrado'),
  });

  if (erro) return <LayoutBase title="Ficha de prospecção" parent={{ label: 'Recuperação de Alunos', path: '/prospeccao/recuperacao' }}><Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout></LayoutBase>;
  if (!d || !ctx) return <LayoutBase title="Ficha de prospecção" parent={{ label: 'Recuperação de Alunos', path: '/prospeccao/recuperacao' }}><p role="status" className="ck-enrollment-state">Carregando…</p></LayoutBase>;

  const o = d.oportunidade, aluno = d.aluno, nome = titleCase(aluno.nome);
  const aberta = ctx.status_abertos.includes(o.status);
  const podeContatar = !o.nao_contatar && o.status !== 'recuperado';
  const alvo = { id: o.id, nome: aluno.nome, status: o.status, aluno, variaveis: d.variaveis, transicoes: o.transicoes };
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const salvarComercial = async () => {
    setSalvando(true);
    try { aplicar(await prospeccaoService.atualizar(o.id, { ...form, responsavel_id: form.responsavel_id || null })); showSuccess('Ficha atualizada'); }
    catch (e) { showError(erroApi(e, 'Não foi possível salvar.')); } finally { setSalvando(false); }
  };
  const abordar = async () => {
    try { fluxo.abordar({ ...alvo, modelos: await carregarModelos() }); } catch (e) { showError(erroApi(e, 'Não foi possível carregar os modelos.')); }
  };
  const recuperar = async () => {
    try {
      const r = await prospeccaoService.verificarRecuperacao(o.id);
      if (r.pode_matricular) router.push(`/matriculas/novo?usuario_id=${r.usuario_id}`);
      else { setRecuperacao(r); setModal('recuperar'); }
    } catch (e) { showError(erroApi(e, 'Não foi possível verificar a situação do aluno.')); }
  };
  const removerRestricao = async () => {
    try { aplicar(await prospeccaoService.removerRestricao(o.id, motivoAutorizacao)); setModal(null); setMotivoAutorizacao(''); showSuccess('Restrição removida'); }
    catch (e) { showError(erroApi(e, 'Não foi possível remover a restrição.')); }
  };
  const fin = d.financeiro;
  const meses = Object.keys(d.checkins.por_mes || {}).sort();
  const timeline = [
    ...d.interacoes.map((i) => ({ tipo: 'contato', quando: i.ocorrido_em, i })),
    ...d.historico.map((h) => ({ tipo: 'status', quando: h.created_at, h })),
  ].sort((a, b) => String(b.quando).localeCompare(String(a.quando)));

  return <LayoutBase title={nome} pageHeader={false} parent={{ label: 'Recuperação de Alunos', path: '/prospeccao/recuperacao' }}>
    <div className="ck-pr ck-pr-ficha">
      <header className="ck-card ck-pr-head">
        <AlunoFoto nome={aluno.nome} foto={aluno.foto_caminho} size="lg" />
        <div className="ck-pr-head__main">
          <h1 className="ck-pr-head__nome">{nome}</h1>
          <div className="ck-pr-head__meta">
            <StatusBadge status={o.status} rotulo={o.status_rotulo} />
            <ScoreBadge score={o.score} potencial={o.potencial} confianca={o.score_confianca} />
            <span>{o.modalidade_nome} · {o.plano_nome}</span>
            <span>Cancelou em {dataBR(o.data_cancelamento)}{o.data_cancelamento_estimada ? ' (estimado)' : ''}, há {tempoInativo(o.dias_inatividade)}</span>
          </div>
        </div>
        <div className="ck-pr-head__acoes">
          {podeContatar ? <WhatsappContato variant="button" telefone={telefoneDoAluno(aluno)} nome={nome} onAbrir={() => fluxo.registrarDepoisDoWhatsapp(alvo)} /> : null}
          {podeContatar ? <Button icon="message-circle" onClick={abordar}>Mensagem pronta</Button> : null}
          {podeContatar ? <Button icon="phone" onClick={() => fluxo.registrarDepoisDoWhatsapp({ ...alvo })}>Registrar contato</Button> : null}
          {aberta && podeContatar ? <Button icon="calendar" onClick={() => setModal('agendar')}>Agendar retorno</Button> : null}
          {o.transicoes.length ? <Button onClick={() => setModal('status')}>Mudar status</Button> : null}
          {ctx.admin && o.status !== 'recuperado' ? <Button variant="primary" icon="user-plus" onClick={recuperar}>Recuperar aluno</Button> : null}
        </div>
      </header>

      {o.restricao ? <Callout tone="danger" title="Aluno pediu para não ser contatado"
        action={<Button size="sm" onClick={() => setModal('restricao')}>Registrar nova autorização</Button>}>
        Desde {dataHoraBR(o.restricao.desde)}{o.restricao.motivo ? ` · ${o.restricao.motivo}` : ''}. A abordagem fica bloqueada, inclusive em cancelamentos futuros.
      </Callout> : null}
      {o.status === 'recuperado' ? <Callout tone="success" title="Aluno recuperado">Nova matrícula #{o.matricula_conversao_id} ativa desde {dataHoraBR(o.convertida_em)}.</Callout> : null}
      {o.ultimo_contato_por && o.ultimo_contato_por !== ctx.usuario_id && o.ultimo_contato_em && (Date.now() - new Date(String(o.ultimo_contato_em).replace(' ', 'T')).getTime()) < 86400000
        ? <Callout tone="info">{titleCase(o.ultimo_contato_por_nome || 'Outra pessoa')} falou com o aluno em {dataHoraBR(o.ultimo_contato_em)}. Combine antes de abordar de novo.</Callout> : null}

      <div className="ck-pr-cols">
        <div className="ck-pr-col">
          <Panel title="Dados do aluno">
            <dl className="ck-pr-kv">
              <dt>Telefone</dt><dd>{aluno.telefone ? maskPhone(aluno.telefone) : '—'}</dd>
              <dt>WhatsApp</dt><dd>{aluno.whatsapp ? maskPhone(aluno.whatsapp) : 'Usa o telefone'}</dd>
              <dt>E-mail</dt><dd className="ck-pr-kv__cut" title={aluno.email || undefined}>{aluno.email || '—'}</dd>
              <dt>Nascimento</dt><dd>{dataBR(aluno.data_nascimento)}</dd>
              <dt>Cadastro</dt><dd>{aluno.ativo ? 'Ativo' : 'Inativo'}</dd>
            </dl>
          </Panel>
          <Panel title="Score de recuperação" description="Heurística sem IA. Critério sem dado não pontua nem conta no total."
            actions={<IconButton icon="refresh-cw" label="Recalcular score" onClick={async () => { try { aplicar(await prospeccaoService.recalcularScore(o.id)); } catch (e) { showError(erroApi(e, 'Falhou')); } }} />}>
            <ul className="ck-pr-score-list">{(o.score_detalhes || []).map((c) => <li key={c.criterio}>
              <span><strong>{c.rotulo}</strong><small>{c.detalhe}</small></span>
              <span className={c.pontos == null ? 'is-muted' : ''}>{c.pontos == null ? 'sem dado' : `${String(c.pontos).replace('.', ',')}/${c.peso}`}</span>
            </li>)}</ul>
            <p className="ck-pr-hint">Confiança: {o.score_confianca ?? 0}% dos critérios avaliados{o.score_calculado_em ? ` · calculado em ${dataHoraBR(o.score_calculado_em)}` : ''}.</p>
          </Panel>
          <Panel title="Frequência e financeiro">
            <dl className="ck-pr-kv">
              <dt>Check-ins (total)</dt><dd>{d.checkins.total}</dd>
              <dt>Último check-in</dt><dd>{dataBR(d.checkins.ultimo)}</dd>
              <dt>Parcelas pagas</dt><dd>{fin.pagas} ({fin.pagas ? Math.round((fin.pagas_em_dia / fin.pagas) * 100) : 0}% em dia)</dd>
              <dt>Em atraso</dt><dd>{fin.em_atraso}{fin.valor_em_atraso != null && fin.em_atraso ? ` · ${moeda(fin.valor_em_atraso)}` : ''}</dd>
              {fin.valor_pago != null ? <><dt>Total pago</dt><dd>{moeda(fin.valor_pago)}</dd></> : null}
            </dl>
            {meses.length ? <ColumnChart height={120} data={meses.map((m) => ({ label: mesCurto(m), value: d.checkins.por_mes[m], tip: mesCurto(m) }))} caption="Check-ins por mês nos últimos 12 meses" /> : <p className="ck-pr-hint">Sem check-ins nos últimos 12 meses.</p>}
          </Panel>
        </div>

        <div className="ck-pr-col">
          <Panel title="Prospecção" description="Informações comerciais. Não alteram o cadastro do aluno."
            actions={<Button size="sm" variant="primary" loading={salvando} onClick={salvarComercial}>Salvar</Button>}>
            <div className="ck-pr-form">
              <div className="ck-pr-grid2">
                <SelectField label="Responsável" value={form.responsavel_id} onChange={(e) => set('responsavel_id', e.target.value)}
                  options={[{ value: '', label: 'Ninguém' }, ...ctx.responsaveis.map((u) => ({ value: String(u.id), label: titleCase(u.nome) }))]} />
                <SelectField label="Motivo do cancelamento" value={form.motivo_categoria} onChange={(e) => set('motivo_categoria', e.target.value)}
                  hint={o.motivo_cancelamento ? `Registrado na matrícula: "${o.motivo_cancelamento}"` : 'Pergunte ao aluno; ajuda no score.'}
                  options={[{ value: '', label: 'Não informado' }, ...Object.entries(ctx.motivos).map(([value, label]) => ({ value, label }))]} />
              </div>
              <TextField label="Interesse demonstrado" value={form.interesse} onChange={(e) => set('interesse', e.target.value)} placeholder="Ex.: quer voltar à noite, 2x por semana" />
              <TextAreaField label="Condições comerciais apresentadas" rows={2} value={form.condicoes_apresentadas} onChange={(e) => set('condicoes_apresentadas', e.target.value)} />
              <TextAreaField label="Observações" rows={3} value={form.observacoes} onChange={(e) => set('observacoes', e.target.value)} />
            </div>
          </Panel>

          <Panel title="Próximos contatos">
            {d.agendamentos.filter((a) => a.situacao === 'pendente').length ? <ul className="ck-pr-list">{d.agendamentos.filter((a) => a.situacao === 'pendente').map((a) => <li key={a.id}>
              <span><strong>{dataHoraBR(a.agendado_para)}</strong><small>{ctx.tipos_contato[a.tipo_contato] || a.tipo_contato} · {titleCase(a.responsavel_nome || '')}{a.observacao ? ` · ${a.observacao}` : ''}</small></span>
              <Button size="sm" onClick={async () => { await prospeccaoService.atualizarAgendamento(a.id, { acao: 'concluir' }); carregar(); }}>Concluir</Button>
            </li>)}</ul> : <p className="ck-pr-hint">Nenhum retorno agendado.</p>}
          </Panel>

          <Panel title="Histórico">
            {timeline.length ? <ol className="ck-pr-timeline">{timeline.map((t, k) => t.tipo === 'contato'
              ? <li key={`c${t.i.id}`} className="is-contato">
                <span className="ck-pr-timeline__when">{dataHoraBR(t.quando)}</span>
                <strong>{ctx.resultados[t.i.resultado] || t.i.resultado} · {ctx.canais[t.i.canal] || t.i.canal}</strong>
                <small>{titleCase(t.i.usuario_nome || '')}{t.i.modelo_nome ? ` · modelo "${t.i.modelo_nome}"` : ''}</small>
                {t.i.observacao ? <p>{t.i.observacao}</p> : null}
              </li>
              : <li key={`s${t.h.id}`}>
                <span className="ck-pr-timeline__when">{dataHoraBR(t.quando)}</span>
                <strong>{t.h.status_anterior && t.h.status_anterior !== t.h.status_novo ? `${ctx.status[t.h.status_anterior]} → ${ctx.status[t.h.status_novo]}` : ctx.status[t.h.status_novo]}</strong>
                <small>{t.h.usuario_nome ? titleCase(t.h.usuario_nome) : 'Sistema'}{t.h.observacao ? ` · ${t.h.observacao}` : ''}</small>
              </li>)}</ol> : <p className="ck-pr-hint">Sem movimentações.</p>}
          </Panel>

          <Panel title="Matrículas na academia">
            <div className="ck-mt-table-wrap"><table className="ck-mt-table ck-pr-mini">
              <thead><tr><th scope="col">Plano</th><th scope="col">Período</th><th scope="col">Situação</th></tr></thead>
              <tbody>{d.matriculas.map((m) => <tr key={m.id}>
                <td><span className="ck-mt-cell__main">{m.plano_nome}</span><span className="ck-mt-cell__sub">{m.modalidade_nome}{ctx.admin ? ` · ${moeda(m.valor)}` : ''}</span></td>
                <td><span className="ck-mt-cell__main">{dataBR(m.data_inicio)}</span><span className="ck-mt-cell__sub">{m.data_cancelamento ? `cancelada em ${dataBR(m.data_cancelamento)}` : `até ${dataBR(m.proxima_data_vencimento || m.data_vencimento)}`}</span></td>
                <td><Badge tone={TOM_MATRICULA[m.status_codigo] || 'neutral'} dot>{m.status_nome}</Badge>{m.motivo_cancelamento ? <span className="ck-mt-cell__sub">{m.motivo_cancelamento}</span> : null}</td>
              </tr>)}</tbody>
            </table></div>
            {d.ciclos.length ? <p className="ck-pr-hint">Prospecções anteriores: {d.ciclos.map((c) => `${ctx.status[c.status]} (cancelou ${dataBR(c.data_cancelamento_referencia)})`).join('; ')}.</p> : null}
          </Panel>
        </div>
      </div>
    </div>

    {fluxo.modais}
    <StatusModal open={modal === 'status'} ctx={ctx} atual={o.status} transicoes={o.transicoes} onClose={() => setModal(null)}
      onSalvar={async (dados) => { aplicar(await prospeccaoService.alterarStatus(o.id, dados)); setModal(null); showSuccess('Status atualizado'); }} />
    <AgendarModal open={modal === 'agendar'} ctx={ctx} alvoNome={aluno.nome} onClose={() => setModal(null)}
      onSalvar={async (f) => { await prospeccaoService.agendar({ ...f, oportunidade_id: o.id, agendado_para: paraApiDataHora(f.agendado_para) }); setModal(null); showSuccess('Retorno agendado'); carregar(); }} />
    <Modal open={modal === 'restricao'} onClose={() => setModal(null)} title="Registrar nova autorização" size="sm"
      description="Use só se o próprio aluno voltou a autorizar o contato."
      footer={<><Button onClick={() => setModal(null)}>Cancelar</Button><Button variant="primary" onClick={removerRestricao} disabled={motivoAutorizacao.trim().length < 5}>Remover restrição</Button></>}>
      <TextAreaField label="Como o aluno autorizou" rows={3} value={motivoAutorizacao} onChange={(e) => setMotivoAutorizacao(e.target.value)} placeholder="Ex.: veio à recepção em 21/10 e pediu informações sobre os planos" />
    </Modal>
    <Modal open={modal === 'recuperar'} onClose={() => setModal(null)} title="Antes de matricular" size="sm"
      footer={<><Button onClick={() => setModal(null)}>Fechar</Button>{recuperacao?.matriculas_em_andamento?.length ? <Button variant="primary" onClick={() => router.push(`/matriculas/detalhe?id=${recuperacao.matriculas_em_andamento[0].id}`)}>Ver matrícula</Button> : null}</>}>
      <ul className="ck-pr-pend">{(recuperacao?.pendencias || []).map((p) => <li key={p}>{p}</li>)}</ul>
    </Modal>
  </LayoutBase>;
}
