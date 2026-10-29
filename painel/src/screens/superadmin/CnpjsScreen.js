import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, ChoiceCard, ChoiceGroup, Modal, PageHeader, SelectField, SegmentedControl, Tabs, Icon, IconButton, TextAreaField, TextField } from '../../components/ck';
import { Panel, digits, maskCep, maskCnpj } from '../../components/ck/pessoas';
import { authService } from '../../services/authService';
import cnpjService from '../../services/cnpjService';
import { showError, showSuccess } from '../../utils/toast';

// Super Admin → CNPJs: política de CNPJ duplicado, exceções, academias vinculadas e gestor cadastral.
// Layout de CNPJs.html; estilos em src/theme/ck-cnpjs.css.

const POLITICAS = [
  { value: 'excecao', title: 'Só com autorização', description: 'Um CNPJ fica numa academia. Para outra usar o mesmo, você autoriza aqui, com motivo. (Recomendado)' },
  { value: 'bloquear', title: 'Nunca compartilhar', description: 'Cada CNPJ fica numa academia só. Cadastros com CNPJ repetido são recusados.' },
  { value: 'permitir', title: 'Compartilhar livremente', description: 'Qualquer academia pode usar um CNPJ já cadastrado, sem pedir.' },
];
const ACOES = {
  migracao: 'CNPJ importado do cadastro',
  vinculo_criado: 'Academia vinculada',
  vinculo_encerrado: 'Vínculo encerrado',
  dados_juridicos_atualizados: 'Dados jurídicos alterados',
  excecao_autorizada: 'Compartilhamento autorizado',
  excecao_revogada: 'Exceção revogada',
  gestor_transferido: 'Gestor cadastral definido',
};
const CAMPOS = ['razao_social', 'nome_fantasia', 'inscricao_estadual', 'inscricao_municipal', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'];
const fdatetime = (v) => {
  if (!v) return '—';
  const [d, t] = String(v).replace('T', ' ').split(' ');
  return `${d.split('-').reverse().join('/')}${t ? ` ${t.slice(0, 5)}` : ''}`;
};
const pick = (e) => Object.fromEntries(CAMPOS.map((k) => [k, e?.[k] || '']));

/** Modal com texto obrigatório (justificativa / motivo). */
function JustificativaModal({ open, title, description, label, confirmLabel, tone = 'primary', saving, disabled, children, onSubmit, onClose }) {
  const [texto, setTexto] = useState('');
  useEffect(() => { if (open) setTexto(''); }, [open]);
  const curto = texto.trim().length < 10;
  return (
    <Modal open={open} size="sm" title={title} description={description} dismissable={!saving} onClose={onClose}
      footer={(
        <>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={saving} onClick={onClose}>Cancelar</Button>
          <Button variant={tone} icon="check" loading={saving} disabled={curto || disabled} onClick={() => onSubmit(texto.trim())}>{confirmLabel}</Button>
        </>
      )}>
      <div className="ck-mc-form">
        {children}
        <TextAreaField label={label} value={texto} onChange={(e) => setTexto(e.target.value)} hint="Mínimo de 10 caracteres. Fica registrado no histórico." />
      </div>
    </Modal>
  );
}

function Detalhe({ cnpj, onChanged, onClose }) {
  const [d, setD] = useState(null);
  const [erro, setErro] = useState('');
  const [form, setForm] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const [modal, setModal] = useState(null); // { tipo: 'autorizar' | 'revogar' | 'gestor', ... }
  const [gestorId, setGestorId] = useState('');
  const [aba, setAba] = useState('academias');

  const aplicar = (r) => { setD(r); setForm(pick(r.entidade)); };
  const carregar = useCallback(async () => {
    setErro('');
    try { aplicar(await cnpjService.detalhe(cnpj)); } catch (e) { setErro(e.message); }
  }, [cnpj]);
  useEffect(() => { setD(null); carregar(); }, [carregar]);

  const executar = async (fn, msg) => {
    setSalvando(true);
    try {
      aplicar(await fn());
      setModal(null);
      showSuccess(msg);
      onChanged();
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvando(false);
    }
  };

  if (erro) return <Modal open title="CNPJ" onClose={onClose}><Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout></Modal>;
  if (!d || !form) return <Modal open title="CNPJ" onClose={onClose}><p className="ck-mc-hint" role="status">Carregando CNPJ…</p></Modal>;

  const ativos = d.vinculos.filter((v) => v.status === 'ativo');
  const gestor = ativos.find((v) => v.papel === 'gestor');
  const vigente = d.excecoes.find((x) => x.vigente);
  const alterado = JSON.stringify(form) !== JSON.stringify(pick(d.entidade));
  const set = (k) => (e) => setForm({ ...form, [k]: k === 'estado' ? e.target.value.toUpperCase().slice(0, 2) : e.target.value });

  return (
    <>
      <Modal open={!modal} size="lg" dialogClassName="ck-cj-dlg" autoFocus={false} dismissable={!salvando} onClose={onClose}
        leading={<span className="ck-pc__icon"><Icon name="file-text" size={20} /></span>}
        title={d.entidade.nome_fantasia || d.entidade.razao_social || d.entidade.cnpj_formatado}
        description={<span className="ck-cj-sub"><span className="ck-cj-copy">{d.entidade.cnpj_formatado}<IconButton icon="copy" label="Copiar CNPJ" size="sm" onClick={async () => {
          try { await navigator.clipboard.writeText(d.entidade.cnpj); showSuccess('CNPJ copiado'); } catch { showError('Não foi possível copiar o CNPJ'); }
        }} /></span><span>{d.entidade.razao_social}</span></span>}
        footer={aba === 'dados' ? <>
          <span className="ck-cj-footnote">{alterado ? 'Alterações não salvas' : 'Nada alterado'}</span>
          <Button variant="ghost" disabled={!alterado || salvando} onClick={() => setForm(pick(d.entidade))}>Descartar</Button>
          <Button variant="primary" icon="save" loading={salvando} disabled={!alterado || !form.razao_social.trim()}
            onClick={() => executar(() => cnpjService.salvarDados(cnpj, form), 'Dados jurídicos salvos')}>
            {ativos.length > 1 ? `Salvar para ${ativos.length} academias` : 'Salvar dados'}
          </Button>
        </> : <><span style={{ flex: 1 }} /><Button onClick={onClose}>Fechar</Button></>}>
        <Tabs label="Seções do CNPJ" className="ck-mc-tabs" value={aba} onChange={setAba} tabs={[
          { value: 'academias', label: `Academias (${ativos.length})` }, { value: 'dados', label: 'Dados jurídicos' }, { value: 'historico', label: 'Histórico' },
        ]} />
        <div className="ck-cj-body" role="tabpanel" aria-label={aba === 'academias' ? 'Academias' : aba === 'dados' ? 'Dados jurídicos' : 'Histórico'}>
          {aba === 'dados' && <section className="ck-cj-sec">
            <p className="ck-cj-note"><Icon name="info" size={15} />{gestor ? `Quem mantém estes dados é a ${gestor.tenant_nome}. ${ativos.length > 1 ? `A mudança aparece para as ${ativos.length} academias.` : ''}` : 'Sem academia gestora: só o super admin edita.'}</p>
            <div className="ck-mc-form">
              <fieldset className="ck-cj-fs">
                <legend>Empresa</legend>
                <div className="ck-mc-grid ck-mc-grid--2">
                  <TextField label="Razão social" value={form.razao_social} onChange={set('razao_social')} error={!form.razao_social.trim() ? 'Obrigatória' : null} />
                  <TextField label="Nome fantasia" value={form.nome_fantasia} onChange={set('nome_fantasia')} />
                  <TextField label="Inscrição estadual" value={form.inscricao_estadual} onChange={set('inscricao_estadual')} />
                  <TextField label="Inscrição municipal" value={form.inscricao_municipal} onChange={set('inscricao_municipal')} />
                </div>
              </fieldset>
              <fieldset className="ck-cj-fs">
                <legend>Endereço</legend>
                <div className="ck-mc-grid">
                  <TextField label="CEP" value={maskCep(form.cep)} onChange={(e) => setForm({ ...form, cep: digits(e.target.value) })} />
                  <TextField label="Logradouro" value={form.logradouro} onChange={set('logradouro')} className="ck-mc-span2" />
                  <TextField label="Número" value={form.numero} onChange={set('numero')} />
                  <TextField label="Complemento" value={form.complemento} onChange={set('complemento')} className="ck-mc-span2" />
                  <TextField label="Bairro" value={form.bairro} onChange={set('bairro')} />
                  <TextField label="Cidade" value={form.cidade} onChange={set('cidade')} />
                  <TextField label="UF" value={form.estado} onChange={set('estado')} maxLength={2} />
                </div>
              </fieldset>
            </div>
          </section>}

          {aba === 'academias' && <div className="ck-cj-sec">
            <Panel title="Academias vinculadas" description="Só a gestora edita os dados jurídicos. Cada academia mantém usuários, plano e pagamentos próprios."
              actions={ativos.length > 1 || (ativos.length > 0 && !gestor) ? (
                <Button size="sm" icon="repeat" onClick={() => { setGestorId(String((ativos.find((v) => v.papel !== 'gestor') || {}).tenant_id || '')); setModal({ tipo: 'gestor' }); }}>
                  {gestor ? 'Transferir gestão' : 'Definir gestora'}
                </Button>
              ) : null}>
              {d.vinculos.length ? (
                <ul className="ck-cj-acs">
                  {d.vinculos.map((v) => (
                    <li key={v.id}>
                      <AcademiaIcon nome={v.tenant_nome} />
                      <span className="ck-cj-acs__txt">
                        <strong>{v.tenant_nome}</strong>
                        <small>{v.status === 'ativo' ? `${v.papel === 'gestor' ? 'Edita os dados jurídicos.' : 'Usa o CNPJ; não edita os dados.'} Desde ${fdatetime(v.vinculado_em)}` : `Encerrado em ${fdatetime(v.encerrado_em)}`}</small>
                      </span>
                      {v.status === 'ativo'
                        ? <Badge tone={v.papel === 'gestor' ? 'success' : 'neutral'}>{v.papel === 'gestor' ? 'Gestora' : 'Vinculada'}</Badge>
                        : <Badge>Encerrado</Badge>}
                      {v.status === 'ativo' && !v.tenant_ativo ? <Badge tone="warning">Academia inativa</Badge> : null}
                    </li>
                  ))}
                </ul>
              ) : <p className="ck-mc-hint">Nenhuma academia usa este CNPJ.</p>}
            </Panel>

            <Panel title="Exceções de compartilhamento"
              description={d.politica === 'excecao' ? 'Com exceção vigente, novas academias podem usar este CNPJ.' : d.politica === 'bloquear' ? 'A política atual bloqueia duplicados mesmo com exceção.' : 'A política atual permite duplicados sem exceção.'}
              actions={!vigente && d.politica === 'excecao' ? <Button size="sm" icon="check-circle" onClick={() => setModal({ tipo: 'autorizar' })}>Autorizar compartilhamento</Button> : null}>
              {d.excecoes.length ? (
                <ul className="ck-cj-rows">
                  {d.excecoes.map((x) => (
                    <li key={x.id}>
                      <span className="ck-cj-rows__main">
                        <strong>{x.justificativa}</strong>
                        <small>{`Autorizada por ${x.autorizado_por_nome || '—'} em ${fdatetime(x.autorizado_em)}`}</small>
                        {!x.vigente ? <small>{`Revogada por ${x.revogado_por_nome || '—'} em ${fdatetime(x.revogado_em)}: ${x.motivo_revogacao}`}</small> : null}
                      </span>
                      {x.vigente ? (
                        <>
                          <Badge tone="success">Vigente</Badge>
                          <Button size="sm" variant="ghost" onClick={() => setModal({ tipo: 'revogar', excecao: x })}>Revogar</Button>
                        </>
                      ) : <Badge>Revogada</Badge>}
                    </li>
                  ))}
                </ul>
              ) : <p className="ck-mc-hint">Nenhuma exceção registrada.</p>}
            </Panel>
          </div>}

          {aba === 'historico' && <Panel title="Histórico">
            {d.auditoria.length ? (
              <ul className="ck-cj-tl">
                {d.auditoria.map((a) => (
                  <li key={a.id}>
                    <span className="ck-cj-tl__dot" aria-hidden="true"><Icon name={a.acao === 'dados_juridicos_atualizados' ? 'pencil' : a.acao === 'gestor_transferido' ? 'repeat' : 'clock'} size={13} /></span>
                    <span className="ck-cj-rows__main">
                      <strong>{ACOES[a.acao] || a.acao}{a.tenant_nome ? ` · ${a.tenant_nome}` : ''}</strong>
                      <small>{`${fdatetime(a.created_at)} · ${a.usuario_nome || 'Sistema'}${a.justificativa ? ` · ${a.justificativa}` : ''}`}</small>
                      {a.acao === 'dados_juridicos_atualizados' && a.dados_novos ? <small>{`Campos: ${Object.keys(a.dados_novos).join(', ')}`}</small> : null}
                    </span>
                  </li>
                ))}
              </ul>
            ) : <p className="ck-mc-hint">Sem registros.</p>}
          </Panel>}

        </div>
      </Modal>

      <JustificativaModal open={modal?.tipo === 'autorizar'} saving={salvando} title="Autorizar compartilhamento"
        description={`Novas academias poderão usar o CNPJ ${d.entidade.cnpj_formatado}. Elas entram como vinculadas, sem editar os dados jurídicos.`}
        label="Justificativa" confirmLabel="Autorizar" onClose={() => setModal(null)}
        onSubmit={(t) => executar(() => cnpjService.autorizar(cnpj, t), 'Compartilhamento autorizado')} />
      <JustificativaModal open={modal?.tipo === 'revogar'} saving={salvando} tone="danger" title="Revogar exceção"
        description={`Novas academias deixam de poder usar este CNPJ. ${ativos.length === 1 ? 'A academia já vinculada continua como está.' : `As ${ativos.length} academias já vinculadas continuam como estão.`}`}
        label="Motivo da revogação" confirmLabel="Revogar" onClose={() => setModal(null)}
        onSubmit={(t) => executar(() => cnpjService.revogar(modal.excecao.id, t), 'Exceção revogada. Os vínculos atuais foram mantidos.')} />
      <JustificativaModal open={modal?.tipo === 'gestor'} saving={salvando} title={gestor ? 'Transferir gestão do CNPJ' : 'Definir academia gestora'}
        description="A academia escolhida passa a editar os dados jurídicos. A atual vira vinculada."
        label="Justificativa" confirmLabel="Confirmar" disabled={!gestorId} onClose={() => setModal(null)}
        onSubmit={(t) => executar(() => cnpjService.definirGestor(cnpj, Number(gestorId), t), 'Gestor cadastral definido')}>
        <SelectField label="Nova gestora" value={gestorId} placeholder="Escolha a academia" onChange={(e) => setGestorId(e.target.value)}
          options={ativos.filter((v) => v.papel !== 'gestor').map((v) => ({ value: String(v.tenant_id), label: v.tenant_nome }))} />
      </JustificativaModal>
    </>
  );
}

function AcademiaIcon({ nome, small = false }) {
  const palavras = (nome || '').trim().split(/\s+/);
  const iniciais = `${palavras[0]?.[0] || ''}${palavras.length > 1 ? palavras[palavras.length - 1][0] : ''}`.toUpperCase();
  return <span className={`ck-cj-acad${small ? ' is-sm' : ''}`} aria-hidden="true">{iniciais}</span>;
}

export default function CnpjsScreen() {
  const router = useRouter();
  const [ok, setOk] = useState(false);
  const [politica, setPolitica] = useState(null);
  const [novaPolitica, setNovaPolitica] = useState(null);
  const [salvandoPolitica, setSalvandoPolitica] = useState(false);
  const [busca, setBusca] = useState('');
  const [filtro, setFiltro] = useState('todos');
  const [lista, setLista] = useState(null);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');
  const [selecionado, setSelecionado] = useState(null);
  const requisicao = useRef(0);

  useEffect(() => {
    let ativo = true;
    authService.getCurrentUser().then((u) => {
      if (!ativo) return;
      if (authService.getEffectivePapelId(u) !== 4) { showError('Acesso negado. Apenas Super Admin pode acessar esta página.'); router.replace('/'); return; }
      setOk(true);
    }).catch(() => { if (ativo) router.replace('/'); });
    return () => { ativo = false; };
  }, [router]);

  const carregar = useCallback(async () => {
    const id = ++requisicao.current;
    setErro('');
    setCarregando(true);
    try {
      const r = await cnpjService.listar({ busca, semAcademia: true });
      if (id !== requisicao.current) return;
      setLista(r.cnpjs);
      setPolitica(r.politica);
    } catch (e) {
      if (id === requisicao.current) setErro(e.message);
    } finally {
      if (id === requisicao.current) setCarregando(false);
    }
  }, [busca]);

  useEffect(() => {
    if (!ok) return undefined;
    const t = setTimeout(carregar, 300);
    return () => { clearTimeout(t); requisicao.current += 1; };
  }, [ok, carregar]);

  const salvarPolitica = async () => {
    setSalvandoPolitica(true);
    try {
      const r = await cnpjService.definirPolitica(novaPolitica);
      setPolitica(r.politica);
      setNovaPolitica(null);
      showSuccess('Política de CNPJ duplicado atualizada');
      carregar();
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvandoPolitica(false);
    }
  };

  const estado = (c) => c.tenants_ativos > 1 ? 'compartilhados' : c.tenants_ativos === 1 ? 'unica' : 'sem';
  const contagem = (value) => (lista || []).filter((c) => value === 'todos' || estado(c) === value).length;
  const linhas = (lista || []).filter((c) => filtro === 'todos' || estado(c) === filtro);
  const regra = POLITICAS.find((p) => p.value === politica);

  if (!ok) return <LayoutBase title="CNPJs" pageHeader={false}><p className="ck-mc-hint" role="status">Carregando…</p></LayoutBase>;

  return (
    <LayoutBase title="CNPJs" pageHeader={false}>
      <div className="ck-cj ck-mc">
        <PageHeader title="CNPJs" description="Um CNPJ pode atender mais de uma academia. Aqui você autoriza esse compartilhamento e define qual academia cuida dos dados jurídicos."
          actions={<button type="button" className="ck-cj-rule" disabled={!politica} onClick={() => setNovaPolitica(politica)}>
            <Icon name="shield-check" size={16} /><span><small>Regra para CNPJ repetido</small><strong>{regra?.title || 'Carregando…'}</strong></span><em>Mudar</em>
          </button>} />
        <section className="ck-pc__panel" aria-label="CNPJs cadastrados" aria-busy={carregando}>
          <div className="ck-cj-top">
            <SegmentedControl label="Mostrar CNPJs" value={filtro} onChange={setFiltro} options={[
              { value: 'todos', label: `Todos ${contagem('todos')}` },
              { value: 'compartilhados', label: `Compartilhados ${contagem('compartilhados')}` },
              { value: 'unica', label: `Uma academia ${contagem('unica')}` },
              { value: 'sem', label: `Sem academia ${contagem('sem')}` },
            ]} />
            <div className="ck-control ck-cj-search"><Icon name="search" size={16} className="ck-control__icon" />
              <input type="search" className="ck-control__input" placeholder="CNPJ, razão social ou academia" aria-label="Buscar CNPJ" value={busca} onChange={(e) => setBusca(e.target.value)} />
            </div>
          </div>
          {erro ? <div className="ck-cj-feedback"><Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout></div>
            : carregando ? <p className="ck-cj-feedback ck-mc-hint" role="status">Carregando CNPJs…</p>
              : linhas.length ? <div className="ck-pc__wrap">
                <table className="ck-pc__table is-fixed ck-cj-table">
                  <colgroup>{[36, 32, 20, 12].map((w) => <col key={w} style={{ width: `${w}%` }} />)}</colgroup>
                  <thead><tr><th scope="col">Empresa</th><th scope="col">Academias</th><th scope="col">Compartilhamento</th><th scope="col">Atualizado</th></tr></thead>
                  <tbody>{linhas.map((c) => {
                    const nome = c.nome_fantasia || c.razao_social || 'Sem razão social';
                    const autorizado = politica === 'permitir' || (politica === 'excecao' && c.excecao_vigente);
                    return <tr key={c.id} onClick={() => setSelecionado(c.cnpj)}>
                      <th scope="row"><div className="ck-pc__name">
                        <span className="ck-pc__icon" aria-hidden="true"><Icon name="file-text" size={18} /></span>
                        <div><button type="button" className="ck-cj-company" onClick={(e) => { e.stopPropagation(); setSelecionado(c.cnpj); }} aria-label={`Abrir ${nome}`}>{nome}</button>
                          <span className="ck-cj-num">{c.cnpj_formatado || maskCnpj(c.cnpj)}</span><span title={c.razao_social}>{c.razao_social}</span></div>
                      </div></th>
                      <td>{c.academias.length ? <ul className="ck-cj-chips">{c.academias.map((a) => <li key={a.id} className={a.papel === 'gestor' ? 'is-boss' : undefined} title={a.papel === 'gestor' ? 'Gestora: edita os dados jurídicos' : 'Usa o CNPJ'}>
                        <AcademiaIcon nome={a.nome} small />{a.nome}{a.papel === 'gestor' && c.academias.length > 1 ? <Icon name="key" size={12} /> : null}
                      </li>)}</ul> : <span className="ck-pc__dash">Nenhuma</span>}</td>
                      <td><Badge tone={autorizado ? 'info' : 'outline'} icon={autorizado ? 'unlock' : undefined}>
                        {!c.tenants_ativos ? 'Sem academia' : autorizado ? (c.tenants_ativos > 1 ? 'Autorizado' : 'Aberto para outras') : c.tenants_ativos > 1 ? 'Compartilhado' : 'Exclusivo'}
                      </Badge>{c.tenants_ativos > 1 && !autorizado ? <span className="ck-pc__sub">{politica === 'bloquear' ? 'Novos vínculos bloqueados' : 'Sem autorização vigente'}</span> : null}</td>
                      <td><span className="ck-pc__sub">{fdatetime(c.updated_at)}</span></td>
                    </tr>;
                  })}</tbody>
                </table>
              </div> : <div className="ck-cj-empty-list"><Icon name="search" size={24} /><strong>{busca ? `Nenhum CNPJ com “${busca}”` : 'Nenhum CNPJ neste filtro'}</strong><p>Busque pelo número, razão social, nome fantasia ou academia.</p></div>}
          {!carregando && lista?.length >= 100 ? <p className="ck-cj-feedback ck-mc-hint">Exibindo até 100 CNPJs. Refine a busca para encontrar outros cadastros. Os filtros contam os resultados carregados.</p> : null}
        </section>
      </div>

      {selecionado ? <Detalhe key={selecionado} cnpj={selecionado} onChanged={carregar} onClose={() => setSelecionado(null)} /> : null}

      <Modal open={!!novaPolitica} size="sm" title="Regra para CNPJ repetido" description="Vale para todo cadastro de academia: super admin, site e painel."
        dismissable={!salvandoPolitica} onClose={() => setNovaPolitica(null)} footer={<>
          <span style={{ flex: 1 }} /><Button variant="ghost" disabled={salvandoPolitica} onClick={() => setNovaPolitica(null)}>Cancelar</Button>
          <Button variant="primary" icon="check" disabled={novaPolitica === politica} loading={salvandoPolitica} onClick={salvarPolitica}>Salvar regra</Button>
        </>}>
        <div className="ck-cj-sec"><ChoiceGroup label="Regra para CNPJ repetido" className="ck-cj-pol">
          {POLITICAS.map((p) => <ChoiceCard key={p.value} title={p.title} description={p.description} checked={novaPolitica === p.value} disabled={salvandoPolitica} onSelect={() => setNovaPolitica(p.value)} />)}
        </ChoiceGroup>
        {novaPolitica !== politica ? <Callout tone={novaPolitica === 'bloquear' ? 'warning' : 'info'}>Academias que já compartilham CNPJ continuam vinculadas. A nova regra vale para os próximos cadastros.</Callout> : null}</div>
      </Modal>
    </LayoutBase>
  );
}
