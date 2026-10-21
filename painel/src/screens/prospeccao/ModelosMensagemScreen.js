import React, { useCallback, useEffect, useRef, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, IconButton, Modal, PageHeader, SelectField, Switch, TextAreaField, TextField } from '../../components/ck';
import prospeccaoService from '../../services/prospeccaoService';
import { VARIAVEIS_MODELO, aplicarModelo, erroApi } from '../../utils/prospeccao';
import { showError, showSuccess } from '../../utils/toast';
import { useProspeccaoContexto } from './useProspeccao';

const EXEMPLO = { nome: 'Maria Oliveira', modalidade: 'Natação', plano_anterior: 'Mensal 3x', estabelecimento: 'sua academia' };

export default function ModelosMensagemScreen() {
  const { ctx } = useProspeccaoContexto();
  const [modelos, setModelos] = useState(null);
  const [erro, setErro] = useState('');
  const [edit, setEdit] = useState(null);
  const [excluir, setExcluir] = useState(null);

  const carregar = useCallback(async () => {
    setErro('');
    try { setModelos((await prospeccaoService.modelos()).modelos); } catch (e) { setErro(erroApi(e, 'Não foi possível carregar os modelos.')); }
  }, []);
  useEffect(() => { carregar(); }, [carregar]);

  const alternar = async (m) => {
    try { await prospeccaoService.atualizarModelo(m.id, { ...m, ativo: !Number(m.ativo) }); carregar(); } catch (e) { showError(erroApi(e, 'Não foi possível alterar.')); }
  };
  const confirmarExclusao = async () => {
    try { const r = await prospeccaoService.excluirModelo(excluir.id); showSuccess(r.message); setExcluir(null); carregar(); } catch (e) { showError(erroApi(e, 'Não foi possível excluir.')); }
  };
  const exemplo = { ...EXEMPLO, estabelecimento: ctx?.estabelecimento || EXEMPLO.estabelecimento };

  return <LayoutBase title="Modelos de mensagem" pageHeader={false}>
    <div className="ck-pr">
      <PageHeader title="Modelos de mensagem" description="Textos prontos para abordar alunos pelo WhatsApp. O atendente revisa e envia manualmente."
        actions={<Button variant="primary" icon="plus" onClick={() => setEdit({ nome: '', etapa: 'primeiro_contato', conteudo: '', ativo: true })}>Novo modelo</Button>} />
      {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
      {!modelos && !erro ? <p role="status" className="ck-enrollment-state">Carregando…</p> : null}
      <div className="ck-pr-modelos">{(modelos || []).map((m) => <article key={m.id} className={`ck-card ck-pr-modelo${Number(m.ativo) ? '' : ' is-off'}`}>
        <header>
          <div><h2>{m.nome}</h2><Badge tone="info">{ctx?.etapas_modelo?.[m.etapa] || m.etapa}</Badge>{Number(m.ativo) ? null : <Badge tone="outline">Inativo</Badge>}</div>
          <div className="ck-pr-modelo__acoes">
            <Switch ariaLabel={`${Number(m.ativo) ? 'Desativar' : 'Ativar'} ${m.nome}`} checked={!!Number(m.ativo)} onChange={() => alternar(m)} />
            <IconButton icon="pencil" size="sm" label={`Editar ${m.nome}`} onClick={() => setEdit({ ...m, ativo: !!Number(m.ativo) })} />
            <IconButton icon="trash" size="sm" label={`Excluir ${m.nome}`} onClick={() => setExcluir(m)} />
          </div>
        </header>
        <p className="ck-pr-modelo__texto">{aplicarModelo(m.conteudo, exemplo).texto}</p>
      </article>)}</div>
    </div>
    <ModeloForm modelo={edit} ctx={ctx} exemplo={exemplo} onClose={() => setEdit(null)} onSalvo={() => { setEdit(null); carregar(); }} />
    <Modal open={!!excluir} onClose={() => setExcluir(null)} title="Excluir modelo?" size="sm"
      footer={<><Button onClick={() => setExcluir(null)}>Cancelar</Button><Button variant="danger" onClick={confirmarExclusao}>Excluir</Button></>}>
      <p className="ck-pr-p">"{excluir?.nome}" será excluído. Se já foi usado em algum contato, ele é só desativado para manter o histórico.</p>
    </Modal>
  </LayoutBase>;
}

function ModeloForm({ modelo, ctx, exemplo, onClose, onSalvo }) {
  const [f, setF] = useState({});
  const [erro, setErro] = useState('');
  const [salvando, setSalvando] = useState(false);
  const area = useRef(null);
  useEffect(() => { if (modelo) { setF(modelo); setErro(''); } }, [modelo]);
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const inserir = (v) => {
    const el = area.current?.querySelector('textarea');
    const txt = f.conteudo || '';
    const pos = el ? el.selectionStart : txt.length;
    set('conteudo', `${txt.slice(0, pos)}{${v}}${txt.slice(pos)}`);
  };
  const salvar = async () => {
    setSalvando(true); setErro('');
    try {
      const dados = { nome: f.nome, etapa: f.etapa, conteudo: f.conteudo, ativo: f.ativo };
      if (f.id) await prospeccaoService.atualizarModelo(f.id, dados); else await prospeccaoService.criarModelo(dados);
      showSuccess('Modelo salvo'); onSalvo();
    } catch (e) { setErro(erroApi(e, 'Não foi possível salvar.')); } finally { setSalvando(false); }
  };
  return <Modal open={!!modelo} onClose={onClose} title={f.id ? 'Editar modelo' : 'Novo modelo'} size="lg"
    footer={<><Button onClick={onClose}>Cancelar</Button><Button variant="primary" loading={salvando} onClick={salvar}>Salvar</Button></>}>
    <div className="ck-pr-form">
      {erro ? <Callout tone="danger">{erro}</Callout> : null}
      <div className="ck-pr-grid2">
        <TextField label="Nome" value={f.nome || ''} onChange={(e) => set('nome', e.target.value)} maxLength={100} />
        <SelectField label="Etapa" value={f.etapa || 'outro'} onChange={(e) => set('etapa', e.target.value)} options={Object.entries(ctx?.etapas_modelo || {}).map(([value, label]) => ({ value, label }))} />
      </div>
      <div ref={area}><TextAreaField label="Mensagem" rows={7} value={f.conteudo || ''} onChange={(e) => set('conteudo', e.target.value)} maxLength={2000} /></div>
      <div className="ck-pr-vars"><span>Inserir:</span>{VARIAVEIS_MODELO.map((v) => <button key={v} type="button" className="ck-ml__chip" onClick={() => inserir(v)}>{`{${v}}`}</button>)}</div>
      <div className="ck-pr-preview"><strong>Prévia</strong><p>{aplicarModelo(f.conteudo, exemplo).texto || 'Escreva a mensagem para ver a prévia.'}</p></div>
    </div>
  </Modal>;
}
