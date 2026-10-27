import React, { useCallback, useEffect, useRef, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { ActionMenu, Badge, Button, Callout, Icon, Modal, PageHeader, SelectField, Tabs, TextField } from '../../components/ck';
import comunicacaoService from '../../services/comunicacaoService';
import alunoService from '../../services/alunoService';
import { authService } from '../../services/authService';
import { showError, showSuccess } from '../../utils/toast';
import ComunicacaoFormModal from './ComunicacaoFormModal';
import ComunicacaoDetalheModal from './ComunicacaoDetalheModal';
import { STATUS, TIPO, fdatetime, msg, paraForm, publicoResumo, vazia } from './comunicacaoUtil';

/**
 * Comunicação → Central de Comunicação: notificações, comunicados, mensagens e enquetes.
 * Comunicação obrigatória ativa trava o check-in de quem não confirmou ou respondeu (regra na API).
 */
const ABAS = [
  { value: '', label: 'Todas' },
  { value: 'notificacao', label: 'Notificações' },
  { value: 'comunicado', label: 'Comunicados' },
  { value: 'mensagem', label: 'Mensagens' },
  { value: 'enquete', label: 'Enquetes' },
  { value: 'relatorios', label: 'Relatórios' },
];
const FILTRO_STATUS = [{ value: '', label: 'Todos os status' }, ...Object.entries(STATUS).map(([value, [, label]]) => ({ value, label }))];
const FILTRO_OBRIG = [{ value: '', label: 'Obrigatórias e opcionais' }, { value: '1', label: 'Só obrigatórias' }, { value: '0', label: 'Só opcionais' }];
const COLUNAS = [
  ['titulo', 'Título'], [null, 'Tipo'], [null, 'Público'], [null, 'Obrigatória'], [null, 'Status'],
  ['inicio_em', 'Início'], ['termino_em', 'Término'], [null, 'Leituras', true], [null, 'Pendentes', true],
];

export default function ComunicacaoScreen() {
  const [aba, setAba] = useState('');
  const [filtros, setFiltros] = useState({ busca: '', status: '', obrigatoria: '' });
  const [ordem, setOrdem] = useState({ campo: 'created_at', direcao: 'desc' });
  const [pagina, setPagina] = useState(1);
  const [dados, setDados] = useState({ comunicacoes: [], paginacao: { total: 0, paginas: 0 } });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [modal, setModal] = useState(null);
  const [busy, setBusy] = useState(false);
  const [opcoes, setOpcoes] = useState({});
  const [alunos, setAlunos] = useState([]);
  const [perms, setPerms] = useState([]);
  const [indicadores, setIndicadores] = useState(null);
  const mounted = useRef(true);
  useEffect(() => () => { mounted.current = false; }, []);

  const pode = p => perms.includes(p);

  const carregar = useCallback(async () => {
    if (aba === 'relatorios') return;
    setLoading(true); setError('');
    try {
      const r = await comunicacaoService.listar({
        tipo: aba || undefined, status: filtros.status || undefined, obrigatoria: filtros.obrigatoria || undefined,
        busca: filtros.busca.trim() || undefined, ordem: ordem.campo, direcao: ordem.direcao, pagina, por_pagina: 20,
      });
      if (mounted.current) setDados(r);
    } catch (e) {
      if (mounted.current) setError(msg(e, 'Não foi possível carregar as comunicações.'));
    } finally {
      if (mounted.current) setLoading(false);
    }
  }, [aba, filtros, ordem, pagina]);

  useEffect(() => { const t = setTimeout(carregar, filtros.busca ? 300 : 0); return () => clearTimeout(t); }, [carregar]);
  useEffect(() => { setPagina(1); }, [aba, filtros, ordem]);
  useEffect(() => {
    authService.getPermissoes().then(p => mounted.current && setPerms(p || [])).catch(() => {});
    comunicacaoService.opcoes().then(r => mounted.current && setOpcoes(r)).catch(e => console.error('Opções da Central:', e));
    alunoService.listarBasico().then(r => mounted.current && setAlunos((r?.alunos || []).map(a => ({ id: a.id, name: a.nome, email: a.email }))))
      .catch(e => console.error('Alunos:', e));
  }, []);
  useEffect(() => {
    if (aba !== 'relatorios') return;
    setIndicadores(null);
    comunicacaoService.indicadores().then(r => mounted.current && setIndicadores(r)).catch(e => mounted.current && setError(msg(e, 'Não foi possível carregar os relatórios.')));
  }, [aba]);

  async function executar(acao, sucesso, fechar = true) {
    setBusy(true);
    try {
      const r = await acao();
      if (!mounted.current) return r;
      if (fechar) setModal(null);
      showSuccess(r?.message || sucesso);
      carregar();
      return r;
    } catch (e) {
      showError(msg(e, 'Não foi possível concluir.'));
      return null;
    } finally {
      if (mounted.current) setBusy(false);
    }
  }

  async function abrirEdicao(c) {
    try {
      const r = await comunicacaoService.obter(c.id);
      setModal({ tipo: 'form', valor: paraForm(r.comunicacao) });
    } catch (e) {
      showError(msg(e, 'Não foi possível abrir.'));
    }
  }

  async function salvar(payload, publicar) {
    const id = modal.valor.id;
    if (!id) return executar(() => comunicacaoService.criar({ ...payload, publicar }), publicar ? 'Comunicação publicada.' : 'Rascunho salvo.');
    if (!publicar) return executar(() => comunicacaoService.atualizar(id, payload), 'Comunicação salva.');
    return executar(async () => {
      await comunicacaoService.atualizar(id, payload);
      return comunicacaoService.publicar(id);
    }, 'Comunicação publicada.');
  }

  const confirmar = (c, acao) => setModal({ tipo: 'confirmar', c, acao });
  const ACOES = {
    publicar: { titulo: 'Publicar comunicação', texto: c => `Envia "${c.titulo}" para ${publicoResumo(c.publico).toLowerCase()}.${c.obrigatoria ? ' Quem não concluir fica sem check-in enquanto ela estiver ativa.' : ''}`, botao: 'Publicar', fn: c => comunicacaoService.publicar(c.id) },
    encerrar: { titulo: 'Encerrar agora', texto: c => `"${c.titulo}" deixa de ser exibida${c.obrigatoria ? ' e para de travar o check-in na hora' : ''}.${c.manter_historico ? ' Continua no histórico dos alunos.' : ''}`, botao: 'Encerrar', fn: c => comunicacaoService.encerrar(c.id) },
    cancelar: { titulo: 'Cancelar comunicação', texto: c => `"${c.titulo}" sai do app e do histórico dos alunos. Leituras e respostas continuam nos relatórios.`, botao: 'Cancelar comunicação', danger: true, fn: c => comunicacaoService.cancelar(c.id) },
    excluir: { titulo: 'Excluir rascunho', texto: c => `O rascunho "${c.titulo}" será excluído.`, botao: 'Excluir', danger: true, fn: c => comunicacaoService.excluir(c.id) },
  };

  function menu(c) {
    const ativa = ['agendada', 'ativa'].includes(c.status);
    return [
      { label: 'Visualizar', icon: 'eye', onClick: () => setModal({ tipo: 'detalhe', c }) },
      pode('comunicacoes.editar') && c.status !== 'cancelada' && c.status !== 'encerrada' ? { label: 'Editar', icon: 'pencil', onClick: () => abrirEdicao(c) } : null,
      pode('comunicacoes.criar') ? { label: 'Duplicar', icon: 'copy', onClick: () => executar(() => comunicacaoService.duplicar(c.id), 'Cópia criada como rascunho.', false) } : null,
      c.status === 'rascunho' && pode('comunicacoes.publicar') ? { label: 'Publicar', icon: 'send', onClick: () => confirmar(c, 'publicar') } : null,
      ativa && pode('comunicacoes.publicar') ? { label: 'Incluir novos alunos', icon: 'user-plus', onClick: () => executar(() => comunicacaoService.incluirDestinatarios(c.id), 'Público atualizado.', false) } : null,
      ativa && pode('comunicacoes.encerrar') ? { label: 'Encerrar agora', icon: 'flag', onClick: () => confirmar(c, 'encerrar') } : null,
      (ativa || c.status === 'encerrada') && pode('comunicacoes.encerrar') ? '-' : null,
      (ativa || c.status === 'encerrada') && pode('comunicacoes.encerrar') ? { label: 'Cancelar', icon: 'close-circle', danger: true, onClick: () => confirmar(c, 'cancelar') } : null,
      c.status === 'rascunho' && pode('comunicacoes.editar') ? { label: 'Excluir', icon: 'trash', danger: true, onClick: () => confirmar(c, 'excluir') } : null,
    ].filter(Boolean);
  }

  const ordenar = campo => setOrdem(o => ({ campo, direcao: o.campo === campo && o.direcao === 'asc' ? 'desc' : 'asc' }));
  const lista = dados.comunicacoes || [];
  const pag = dados.paginacao || {};

  return <LayoutBase title="Central de Comunicação" pageHeader={false}>
    <div className="ck-com">
      <PageHeader title="Central de Comunicação" description="Avisos, comunicados e enquetes para os alunos no app. As obrigatórias travam o check-in até o aluno concluir."
        actions={pode('comunicacoes.criar') ? <Button variant="primary" icon="plus" onClick={() => setModal({ tipo: 'form', valor: { ...vazia(), tipo: TIPO[aba] ? aba : 'comunicado' } })}>Nova comunicação</Button> : null} />
      {error ? <Callout tone="danger" title="Não foi possível carregar" action={<Button size="sm" onClick={carregar}>Tentar novamente</Button>}>{error}</Callout> : null}

      <div className="ck-pc__panel">
        <div className="ck-pc__top">
          <Tabs label="Tipo" tabs={ABAS.filter(a => a.value !== 'relatorios' || pode('comunicacoes.relatorios'))} value={aba} onChange={setAba} />
        </div>
        {aba === 'relatorios' ? <Relatorios dados={indicadores} /> : <>
          <div className="ck-com__filtros">
            <TextField icon="search" placeholder="Buscar pelo título" aria-label="Buscar" value={filtros.busca} onChange={e => setFiltros({ ...filtros, busca: e.target.value })} />
            <SelectField aria-label="Status" value={filtros.status} options={FILTRO_STATUS} onChange={e => setFiltros({ ...filtros, status: e.target.value })} />
            <SelectField aria-label="Obrigatoriedade" value={filtros.obrigatoria} options={FILTRO_OBRIG} onChange={e => setFiltros({ ...filtros, obrigatoria: e.target.value })} />
          </div>
          {loading && !lista.length ? <div className="ck-ml__empty" role="status"><p>Carregando…</p></div>
            : lista.length ? <div className="ck-pc__wrap"><table className="ck-pc__table ck-com__table">
              <thead><tr>
                {COLUNAS.map(([campo, rotulo, num]) => <th key={rotulo} scope="col" className={num ? 'is-num' : undefined}
                  aria-sort={campo && ordem.campo === campo ? (ordem.direcao === 'asc' ? 'ascending' : 'descending') : undefined}>
                  {campo ? <button type="button" className="ck-com__sort" onClick={() => ordenar(campo)}>{rotulo}
                    {ordem.campo === campo ? <Icon name={ordem.direcao === 'asc' ? 'arrow-up' : 'arrow-down'} size={12} /> : null}</button> : rotulo}
                </th>)}
                <th scope="col"><span className="ck-sr">Ações</span></th>
              </tr></thead>
              <tbody>{lista.map(c => {
                const [tone, label] = STATUS[c.status] || STATUS.rascunho;
                return <tr key={c.id} className="is-click" tabIndex={0} aria-label={`Visualizar ${c.titulo}`}
                  onClick={e => { if (!e.target.closest('button, a, [role="menu"]')) setModal({ tipo: 'detalhe', c }); }}
                  onKeyDown={e => { if (e.key === 'Enter' && e.target === e.currentTarget) setModal({ tipo: 'detalhe', c }); }}>
                  <th scope="row"><div className="ck-pc__name">
                    <span className={'ck-pc__icon ck-com__cls is-' + c.classificacao} aria-hidden><Icon name={c.icone || TIPO[c.tipo]?.icon || 'bell'} size={18} glyph /></span>
                    <div style={{ minWidth: 0 }}><strong>{c.titulo}</strong>{c.descricao ? <span>{c.descricao}</span> : null}</div>
                  </div></th>
                  <td>{TIPO[c.tipo]?.label || c.tipo}</td>
                  <td>{publicoResumo(c.publico)}</td>
                  <td>{c.obrigatoria ? <Badge tone="warning" icon="lock">Sim</Badge> : <span className="ck-pc__dash">Não</span>}</td>
                  <td><Badge tone={tone} dot>{label}</Badge></td>
                  <td>{fdatetime(c.inicio_em || c.publicada_em)}</td>
                  <td>{fdatetime(c.termino_em)}</td>
                  <td className="is-num">{c.status === 'rascunho' ? '—' : c.leituras}</td>
                  <td className="is-num">{c.status === 'rascunho' ? '—' : <span className={c.obrigatoria && c.pendentes && c.status === 'ativa' ? 'ck-com__warn' : undefined}>{c.pendentes}</span>}</td>
                  <td><div className="ck-pc__acts"><ActionMenu label={`Ações para ${c.titulo}`} items={menu(c)} /></div></td>
                </tr>;
              })}</tbody>
            </table></div>
            : <div className="ck-ml__empty">
              <span className="ck-mt-empty__icon" aria-hidden><Icon name="bullhorn" size={20} glyph /></span>
              <strong>{filtros.busca || filtros.status || filtros.obrigatoria ? 'Nada com esses filtros' : 'Nenhuma comunicação ainda'}</strong>
              <p>Avise sobre horários, feriados e regras, ou colete a opinião dos alunos com uma enquete.</p>
            </div>}
          {pag.paginas > 1 ? <div className="ck-com__pag">
            <span>{pag.total} comunicações · página {pagina} de {pag.paginas}</span>
            <Button size="sm" icon="chevron-left" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)}>Anterior</Button>
            <Button size="sm" iconRight="chevron-right" disabled={pagina >= pag.paginas} onClick={() => setPagina(pagina + 1)}>Próxima</Button>
          </div> : null}
        </>}
      </div>
    </div>

    {modal?.tipo === 'form' ? <ComunicacaoFormModal open value={modal.valor} options={opcoes} students={alunos} saving={busy}
      canPublish={pode('comunicacoes.publicar')} onCancel={() => !busy && setModal(null)} onSave={salvar} /> : null}
    {modal?.tipo === 'detalhe' ? <ComunicacaoDetalheModal open comunicacao={modal.c} canReports={pode('comunicacoes.relatorios')}
      canAnswers={pode('comunicacoes.respostas')} onClose={() => setModal(null)} /> : null}
    {modal?.tipo === 'confirmar' ? (() => {
      const a = ACOES[modal.acao];
      return <Modal open size="sm" title={a.titulo} dismissable={!busy} onClose={() => setModal(null)}
        footer={<><Button disabled={busy} onClick={() => setModal(null)}>Voltar</Button>
          <Button variant={a.danger ? 'danger' : 'primary'} loading={busy} onClick={() => executar(() => a.fn(modal.c), 'Pronto.')}>{a.botao}</Button></>}>
        <p>{a.texto(modal.c)}</p>
      </Modal>;
    })() : null}
  </LayoutBase>;
}

function Relatorios({ dados: d }) {
  if (!d) return <div className="ck-ml__empty" role="status"><p>Carregando…</p></div>;
  const pct = v => (v == null ? '—' : `${String(v).replace('.', ',')}%`);
  const cards = [
    ['Publicadas', d.publicadas, `${d.por_tipo.comunicado} comunicados · ${d.por_tipo.enquete} enquetes`],
    ['Alunos alcançados', d.alunos_alcancados, `${d.entregas} entregas`],
    ['Leituras confirmadas', d.leituras_confirmadas, `Taxa de confirmação ${pct(d.taxa_confirmacao)}`],
    ['Enquetes respondidas', d.enquetes_respondidas, `Participação ${pct(d.taxa_participacao)}`],
    ['Pendências obrigatórias', d.pendencias_obrigatorias, d.alunos_bloqueados === 1 ? '1 aluno sem check-in agora' : `${d.alunos_bloqueados} alunos sem check-in agora`, d.pendencias_obrigatorias > 0],
  ];
  return <div className="ck-com__kpis">
    {cards.map(([rotulo, valor, sub, alerta]) => <div key={rotulo} className={'ck-com__kpi' + (alerta ? ' is-warn' : '')}>
      <span>{rotulo}</span><strong>{valor}</strong><small>{sub}</small>
    </div>)}
    <p className="ck-com__hint">Para ver quem confirmou ou está pendente, abra a comunicação na lista. O CSV abre no Excel.</p>
  </div>;
}
