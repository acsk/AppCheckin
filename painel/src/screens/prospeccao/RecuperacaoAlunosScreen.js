import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Icon, IconButton, PageHeader, SelectField, TextField } from '../../components/ck';
import { AgendarModal, DiagnosticoProspeccao, IniciarProspeccaoModal, RecuperacaoTabela, useFluxoContato } from '../../components/ck/prospeccao';
import prospeccaoService from '../../services/prospeccaoService';
import { erroApi, paraApiDataHora } from '../../utils/prospeccao';
import { showError, showSuccess } from '../../utils/toast';
import { useModelosAtivos, useProspeccaoContexto } from './useProspeccao';

const CHIPS = [
  ['abertas', 'Em prospecção'], ['nao_contatado', 'Não contatados'], ['interessado', 'Interessados'],
  ['em_negociacao', 'Em negociação'], ['retorno_agendado', 'Retorno agendado'], ['recuperado', 'Recuperados'],
  ['sem_interesse', 'Sem interesse'], ['nao_contatar', 'Não contatar'], ['todas', 'Todas'],
];
const INATIVIDADE = [
  { value: '', label: 'Qualquer tempo' }, { value: '0-30', label: 'Até 30 dias' }, { value: '31-90', label: '1 a 3 meses' },
  { value: '91-180', label: '3 a 6 meses' }, { value: '181-365', label: '6 a 12 meses' }, { value: '366-', label: 'Mais de 1 ano' },
];
const FILTROS_INICIAIS = { status: 'abertas', busca: '', modalidade_id: '', plano_id: '', inatividade: '', responsavel: '', cancelamento_de: '', cancelamento_ate: '', sem_contato: false, com_retorno: false, potencial: '' };

function paramsDe(f, pagina, ordem, direcao) {
  const [min, max] = (f.inatividade || '').split('-');
  const p = { status: f.status, pagina, por_pagina: 20, ordem, direcao };
  if (f.busca.trim()) p.busca = f.busca.trim();
  ['modalidade_id', 'plano_id', 'responsavel', 'cancelamento_de', 'cancelamento_ate', 'potencial'].forEach((k) => { if (f[k]) p[k] = f[k]; });
  if (min) p.inatividade_min = min;
  if (max) p.inatividade_max = max;
  if (f.sem_contato) p.sem_contato = 1;
  if (f.com_retorno) p.com_retorno = 1;
  return p;
}

export default function RecuperacaoAlunosScreen() {
  const router = useRouter();
  const { ctx, erro: erroCtx } = useProspeccaoContexto();
  const carregarModelos = useModelosAtivos();
  const [filtros, setFiltros] = useState(FILTROS_INICIAIS);
  const [busca, setBusca] = useState('');
  const [mais, setMais] = useState(false);
  const [pagina, setPagina] = useState(1);
  const [ordem, setOrdem] = useState('score');
  const [direcao, setDirecao] = useState('desc');
  const [dados, setDados] = useState({ oportunidades: [], paginacao: { total: 0, paginas: 1 } });
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');
  const [agendar, setAgendar] = useState(null);
  const [iniciar, setIniciar] = useState(false);
  const [diagnostico, setDiagnostico] = useState(null);
  const [sincronizando, setSincronizando] = useState(false);
  const req = useRef(0);

  const carregar = useCallback(async () => {
    const id = ++req.current;
    setCarregando(true); setErro('');
    try {
      const data = await prospeccaoService.listar(paramsDe(filtros, pagina, ordem, direcao));
      if (id === req.current) {
        setDados(data);
        // Lista vazia: explica por que (quantos cancelados, quem ficou de fora e o motivo)
        if (!data.oportunidades.length) prospeccaoService.diagnostico().then((r) => setDiagnostico(r.diagnostico)).catch(() => setDiagnostico(null));
      }
    } catch (e) {
      if (id === req.current) setErro(erroApi(e, 'Não foi possível carregar a lista.'));
    } finally {
      if (id === req.current) setCarregando(false);
    }
  }, [filtros, pagina, ordem, direcao]);
  useEffect(() => { carregar(); }, [carregar]);

  // Busca com espera curta para não consultar a cada tecla
  useEffect(() => {
    const t = setTimeout(() => { setFiltros((f) => (f.busca === busca ? f : { ...f, busca })); setPagina(1); }, 350);
    return () => clearTimeout(t);
  }, [busca]);

  const fluxo = useFluxoContato({ ctx, registrar: prospeccaoService.registrarInteracao, depois: () => { showSuccess('Contato registrado'); carregar(); } });
  const set = (k, v) => { setFiltros((f) => ({ ...f, [k]: v })); setPagina(1); };
  const ordenar = (chave) => {
    if (ordem === chave) setDirecao((d) => (d === 'asc' ? 'desc' : 'asc'));
    else { setOrdem(chave); setDirecao(chave === 'nome' ? 'asc' : 'desc'); }
    setPagina(1);
  };
  const variaveis = (r) => ({ nome: r.aluno_nome, modalidade: r.modalidade_nome, plano_anterior: r.plano_nome, estabelecimento: ctx?.estabelecimento });
  const abordar = async (r) => {
    try {
      const modelos = await carregarModelos();
      fluxo.abordar({ id: r.id, nome: r.aluno_nome, status: r.status, aluno: r, variaveis: variaveis(r), modelos, transicoes: [] });
    } catch (e) { showError(erroApi(e, 'Não foi possível carregar os modelos de mensagem.')); }
  };
  const salvarAgendamento = async (f) => {
    await prospeccaoService.agendar({ ...f, oportunidade_id: agendar.id, agendado_para: paraApiDataHora(f.agendado_para) });
    setAgendar(null); showSuccess('Retorno agendado'); carregar();
  };
  const sincronizar = async () => {
    setSincronizando(true);
    try {
      const r = await prospeccaoService.sincronizar(!!ctx?.admin);
      setDiagnostico(r.diagnostico);
      showSuccess(r.executada ? `Lista atualizada${r.criadas ? `: ${r.criadas} novo${r.criadas > 1 ? 's' : ''} aluno${r.criadas > 1 ? 's' : ''}` : ''}` : r.message);
      carregar();
    } catch (e) { showError(erroApi(e, 'Não foi possível sincronizar.')); } finally { setSincronizando(false); }
  };
  const { total, paginas } = dados.paginacao;
  const de = total ? (pagina - 1) * 20 + 1 : 0, ate = Math.min(total, (pagina - 1) * 20 + dados.oportunidades.length);
  const filtrosAtivos = ['modalidade_id', 'plano_id', 'inatividade', 'responsavel', 'cancelamento_de', 'cancelamento_ate', 'potencial'].filter((k) => filtros[k]).length + (filtros.sem_contato ? 1 : 0) + (filtros.com_retorno ? 1 : 0);
  const planos = (ctx?.filtros?.planos || []).filter((p) => !filtros.modalidade_id || String(p.modalidade_id) === filtros.modalidade_id);

  return <LayoutBase title="Recuperação de Alunos" pageHeader={false}>
    <div className="ck-ml ck-pr">
      <PageHeader title="Recuperação de Alunos" description="Alunos que cancelaram a matrícula e ainda não voltaram. Uma linha por aluno, a partir do cancelamento mais recente."
        actions={<>
          <Button icon="calendar" onClick={() => router.push('/prospeccao/contatos')}>Contatos pendentes</Button>
          <Button variant="primary" icon="user-plus" onClick={() => setIniciar(true)}>Iniciar prospecção</Button>
        </>} />
      {erroCtx ? <Callout tone="danger">{erroCtx}</Callout> : null}
      <section className="ck-card ck-ml__panel" aria-label="Alunos para recuperar">
        <div className="ck-ml__toolbar">
          <div className="ck-pr-toolbar">
            <div className="ck-control ck-ml__search">
              <Icon name="search" size={18} className="ck-control__icon" />
              <input type="search" className="ck-control__input" value={busca} placeholder="Buscar por nome ou telefone" aria-label="Buscar alunos" onChange={(e) => setBusca(e.target.value)} />
              {busca ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setBusca('')} /> : null}
            </div>
            <Button icon="filter" onClick={() => setMais((v) => !v)} aria-expanded={mais}>Filtros{filtrosAtivos ? ` (${filtrosAtivos})` : ''}</Button>
          </div>
          <div className="ck-ml__filters" role="radiogroup" aria-label="Status da prospecção">
            {CHIPS.map(([v, rotulo]) => <button key={v} type="button" role="radio" aria-checked={filtros.status === v} className="ck-ml__chip" onClick={() => set('status', v)}>{rotulo}</button>)}
          </div>
          {mais ? <div className="ck-pr-filtros">
            <SelectField label="Modalidade" value={filtros.modalidade_id} onChange={(e) => { set('modalidade_id', e.target.value); set('plano_id', ''); }}
              options={[{ value: '', label: 'Todas' }, ...(ctx?.filtros?.modalidades || []).map((m) => ({ value: String(m.id), label: m.nome }))]} />
            <SelectField label="Plano anterior" value={filtros.plano_id} onChange={(e) => set('plano_id', e.target.value)}
              options={[{ value: '', label: 'Todos' }, ...planos.map((p) => ({ value: String(p.id), label: p.nome }))]} />
            <SelectField label="Tempo inativo" value={filtros.inatividade} onChange={(e) => set('inatividade', e.target.value)} options={INATIVIDADE} />
            <SelectField label="Potencial" value={filtros.potencial} onChange={(e) => set('potencial', e.target.value)}
              options={[{ value: '', label: 'Todos' }, { value: 'alto', label: 'Alto (80+)' }, { value: 'medio', label: 'Médio (50–79)' }, { value: 'baixo', label: 'Baixo (até 49)' }]} />
            <TextField label="Cancelou a partir de" type="date" value={filtros.cancelamento_de} onChange={(e) => set('cancelamento_de', e.target.value)} />
            <TextField label="Cancelou até" type="date" value={filtros.cancelamento_ate} onChange={(e) => set('cancelamento_ate', e.target.value)} />
            <SelectField label="Responsável" value={filtros.responsavel} onChange={(e) => set('responsavel', e.target.value)}
              options={[{ value: '', label: 'Todos' }, { value: 'meus', label: 'Meus' }, ...(ctx?.responsaveis || []).map((u) => ({ value: String(u.id), label: u.nome }))]} />
            <div className="ck-pr-checks">
              <label className="ck-pr-check"><input type="checkbox" checked={filtros.sem_contato} onChange={(e) => set('sem_contato', e.target.checked)} /> Sem nenhum contato</label>
              <label className="ck-pr-check"><input type="checkbox" checked={filtros.com_retorno} onChange={(e) => set('com_retorno', e.target.checked)} /> Com retorno agendado</label>
              {filtrosAtivos ? <Button size="sm" variant="ghost" onClick={() => { setFiltros({ ...FILTROS_INICIAIS, status: filtros.status, busca: filtros.busca }); setPagina(1); }}>Limpar filtros</Button> : null}
            </div>
          </div> : null}
        </div>

        {erro ? <div className="ck-pr-pad"><Callout tone="danger" title="Não foi possível carregar" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout></div> : null}
        {carregando && !dados.oportunidades.length ? <p role="status" className="ck-enrollment-state">Carregando alunos…</p> : null}
        {!carregando && !erro && !dados.oportunidades.length ? <div className="ck-ml__empty">
          <span className="ck-mt-empty__icon" aria-hidden><Icon name={filtros.busca ? 'search' : 'users'} size={20} /></span>
          <strong>{filtros.busca ? `Ninguém encontrado para "${filtros.busca}"` : 'Nenhum aluno neste filtro'}</strong>
          <p>{filtros.status === 'abertas' && !filtrosAtivos && !filtros.busca ? 'Alunos com matrícula cancelada entram aqui automaticamente. Você também pode escolher um aluno e iniciar a prospecção.' : 'Mude o filtro ou a busca.'}</p>
          <DiagnosticoProspeccao diagnostico={diagnostico} admin={ctx?.admin} sincronizando={sincronizando} onSincronizar={sincronizar} onIniciar={() => setIniciar(true)} />
        </div> : null}
        {dados.oportunidades.length ? <div aria-busy={carregando} className={carregando ? 'ck-pr-loading' : undefined}>
          <RecuperacaoTabela rows={dados.oportunidades} ordem={ordem} direcao={direcao} onOrdenar={ordenar}
            onAbrir={(r) => router.push(`/prospeccao/recuperacao/${r.id}`)} onAbordar={abordar}
            onWhatsappAberto={(r) => fluxo.registrarDepoisDoWhatsapp({ id: r.id, transicoes: [] })} onAgendar={(r) => setAgendar(r)} />
        </div> : null}

        <footer className="ck-ml__foot">
          <span>{total ? `Mostrando ${de} a ${ate} de ${total}` : 'Nenhum resultado'}</span>
          {paginas > 1 ? <nav className="ck-ml__pager" aria-label="Páginas">
            <IconButton icon="chevron-left" label="Página anterior" size="sm" variant="secondary" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)} />
            <span className="ck-ml__pageinfo">Página {pagina} de {paginas}</span>
            <IconButton icon="chevron-right" label="Próxima página" size="sm" variant="secondary" disabled={pagina >= paginas} onClick={() => setPagina(pagina + 1)} />
          </nav> : null}
        </footer>
      </section>
    </div>
    {fluxo.modais}
    <IniciarProspeccaoModal open={iniciar} buscar={prospeccaoService.alunosCancelados} iniciar={prospeccaoService.iniciar}
      onClose={() => setIniciar(false)} onAbrir={(id) => { setIniciar(false); router.push(`/prospeccao/recuperacao/${id}`); }} />
    <AgendarModal open={!!agendar} ctx={ctx} alvoNome={agendar?.aluno_nome} onClose={() => setAgendar(null)} onSalvar={salvarAgendamento} />
  </LayoutBase>;
}
