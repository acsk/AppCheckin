import React, { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, ChoiceCard, ChoiceGroup, Modal, PageHeader, SelectField, Switch, TextAreaField, TextField } from '../../components/ck';
import { Panel, digits, maskCep, maskCnpj } from '../../components/ck/pessoas';
import { authService } from '../../services/authService';
import cnpjService from '../../services/cnpjService';
import { showError, showSuccess } from '../../utils/toast';

// Super Admin → CNPJs: política de CNPJ duplicado, exceções, academias vinculadas e gestor cadastral.
// Estilos: ck-cj-* em src/theme/ck-minha-conta.css.

const POLITICAS = [
  { value: 'excecao', title: 'Permitir mediante exceção', description: 'Bloqueia por padrão. Você autoriza CNPJs específicos, com justificativa. (Recomendado)' },
  { value: 'bloquear', title: 'Bloquear duplicados', description: 'Cada CNPJ fica em uma única academia, sem exceções.' },
  { value: 'permitir', title: 'Permitir duplicados', description: 'Qualquer academia pode usar um CNPJ já cadastrado.' },
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
  const [d, t] = String(v).split(' ');
  return `${d.split('-').reverse().join('/')}${t ? ` ${t.slice(0, 5)}` : ''}`;
};
const pick = (e) => Object.fromEntries(CAMPOS.map((k) => [k, e?.[k] || '']));

/** Modal com texto obrigatório (justificativa / motivo). */
function JustificativaModal({ open, title, description, label, confirmLabel, tone = 'primary', saving, children, onSubmit, onClose }) {
  const [texto, setTexto] = useState('');
  useEffect(() => { if (open) setTexto(''); }, [open]);
  const curto = texto.trim().length < 10;
  return (
    <Modal open={open} size="sm" title={title} description={description} dismissable={!saving} onClose={onClose}
      footer={(
        <>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={saving} onClick={onClose}>Cancelar</Button>
          <Button variant={tone} icon="check" loading={saving} disabled={curto} onClick={() => onSubmit(texto.trim())}>{confirmLabel}</Button>
        </>
      )}>
      <div className="ck-mc-form">
        {children}
        <TextAreaField label={label} value={texto} onChange={(e) => setTexto(e.target.value)} hint="Mínimo de 10 caracteres. Fica registrado no histórico." />
      </div>
    </Modal>
  );
}

function Detalhe({ cnpj, onChanged }) {
  const [d, setD] = useState(null);
  const [erro, setErro] = useState('');
  const [form, setForm] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const [modal, setModal] = useState(null); // { tipo: 'autorizar' | 'revogar' | 'gestor', ... }
  const [gestorId, setGestorId] = useState('');

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

  if (erro) return <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout>;
  if (!d || !form) return <p className="ck-mc-hint" role="status">Carregando CNPJ…</p>;

  const ativos = d.vinculos.filter((v) => v.status === 'ativo');
  const gestor = ativos.find((v) => v.papel === 'gestor');
  const vigente = d.excecoes.find((x) => x.vigente);
  const alterado = JSON.stringify(form) !== JSON.stringify(pick(d.entidade));
  const set = (k) => (e) => setForm({ ...form, [k]: k === 'estado' ? e.target.value.toUpperCase().slice(0, 2) : e.target.value });

  return (
    <div className="ck-cj-stack">
      <Panel title={d.entidade.razao_social || 'Sem razão social'} description={`CNPJ ${d.entidade.cnpj_formatado}`}
        actions={(
          <span className="ck-mc-badges">
            {ativos.length > 1 ? <Badge tone="info">{`${ativos.length} academias`}</Badge> : null}
            {gestor ? null : ativos.length ? <Badge tone="warning" icon="alert-triangle">Sem gestor</Badge> : null}
          </span>
        )}>
        <div className="ck-mc-form">
          <div className="ck-mc-grid ck-mc-grid--2">
            <TextField label="Razão social" value={form.razao_social} onChange={set('razao_social')} error={!form.razao_social.trim() ? 'Obrigatória' : null} />
            <TextField label="Nome fantasia" value={form.nome_fantasia} onChange={set('nome_fantasia')} />
            <TextField label="Inscrição estadual" value={form.inscricao_estadual} onChange={set('inscricao_estadual')} />
            <TextField label="Inscrição municipal" value={form.inscricao_municipal} onChange={set('inscricao_municipal')} />
          </div>
          <div className="ck-mc-grid">
            <TextField label="CEP" value={maskCep(form.cep)} onChange={(e) => setForm({ ...form, cep: digits(e.target.value) })} />
            <TextField label="Logradouro" value={form.logradouro} onChange={set('logradouro')} className="ck-mc-span2" />
            <TextField label="Número" value={form.numero} onChange={set('numero')} />
            <TextField label="Complemento" value={form.complemento} onChange={set('complemento')} className="ck-mc-span2" />
            <TextField label="Bairro" value={form.bairro} onChange={set('bairro')} />
            <TextField label="Cidade" value={form.cidade} onChange={set('cidade')} />
            <TextField label="UF" value={form.estado} onChange={set('estado')} maxLength={2} />
          </div>
          <div className="ck-mc-actions">
            <Button variant="ghost" disabled={!alterado || salvando} onClick={() => setForm(pick(d.entidade))}>Descartar</Button>
            <Button variant="primary" icon="save" loading={salvando && !modal} disabled={!alterado || !form.razao_social.trim()}
              onClick={() => executar(() => cnpjService.salvarDados(cnpj, form), 'Dados jurídicos salvos')}>Salvar dados jurídicos</Button>
          </div>
        </div>
      </Panel>

      <Panel title="Academias vinculadas" description="Só a gestora edita os dados jurídicos. Cada academia mantém usuários, plano e pagamentos próprios."
        actions={ativos.length > 1 || (ativos.length && !gestor) ? (
          <Button size="sm" icon="repeat" onClick={() => { setGestorId(String((ativos.find((v) => v.papel !== 'gestor') || {}).tenant_id || '')); setModal({ tipo: 'gestor' }); }}>
            {gestor ? 'Transferir gestão' : 'Definir gestora'}
          </Button>
        ) : null}>
        {d.vinculos.length ? (
          <ul className="ck-cj-rows">
            {d.vinculos.map((v) => (
              <li key={v.id}>
                <span className="ck-cj-rows__main">
                  <strong>{v.tenant_nome}</strong>
                  <small>{v.status === 'ativo' ? `Desde ${fdatetime(v.vinculado_em)}` : `Encerrado em ${fdatetime(v.encerrado_em)}`}</small>
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
        actions={!vigente ? <Button size="sm" icon="check-circle" onClick={() => setModal({ tipo: 'autorizar' })}>Autorizar compartilhamento</Button> : null}>
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

      <Panel title="Histórico">
        {d.auditoria.length ? (
          <ul className="ck-cj-rows">
            {d.auditoria.map((a) => (
              <li key={a.id}>
                <span className="ck-cj-rows__main">
                  <strong>{ACOES[a.acao] || a.acao}{a.tenant_nome ? ` · ${a.tenant_nome}` : ''}</strong>
                  <small>{`${fdatetime(a.created_at)} · ${a.usuario_nome || 'Sistema'}${a.justificativa ? ` · ${a.justificativa}` : ''}`}</small>
                  {a.acao === 'dados_juridicos_atualizados' && a.dados_novos ? <small>{`Campos: ${Object.keys(a.dados_novos).join(', ')}`}</small> : null}
                </span>
              </li>
            ))}
          </ul>
        ) : <p className="ck-mc-hint">Sem registros.</p>}
      </Panel>

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
        label="Justificativa" confirmLabel="Confirmar" onClose={() => setModal(null)}
        onSubmit={(t) => executar(() => cnpjService.definirGestor(cnpj, Number(gestorId), t), 'Gestor cadastral definido')}>
        <SelectField label="Nova gestora" value={gestorId} placeholder="Escolha a academia" onChange={(e) => setGestorId(e.target.value)}
          options={ativos.filter((v) => v.papel !== 'gestor').map((v) => ({ value: String(v.tenant_id), label: v.tenant_nome }))} />
      </JustificativaModal>
    </div>
  );
}

export default function CnpjsScreen() {
  const router = useRouter();
  const [ok, setOk] = useState(false);
  const [politica, setPolitica] = useState(null);
  const [novaPolitica, setNovaPolitica] = useState(null);
  const [salvandoPolitica, setSalvandoPolitica] = useState(false);
  const [busca, setBusca] = useState('');
  const [compartilhados, setCompartilhados] = useState(false);
  const [lista, setLista] = useState(null);
  const [erro, setErro] = useState('');
  const [selecionado, setSelecionado] = useState(null);

  useEffect(() => {
    authService.getCurrentUser().then((u) => {
      if (authService.getEffectivePapelId(u) !== 4) { showError('Acesso negado. Apenas Super Admin pode acessar esta página.'); router.replace('/'); return; }
      setOk(true);
    });
  }, []);

  const carregar = useCallback(async () => {
    setErro('');
    try {
      const r = await cnpjService.listar({ busca, compartilhados });
      setLista(r.cnpjs);
      setPolitica(r.politica);
    } catch (e) {
      setErro(e.message);
    }
  }, [busca, compartilhados]);

  useEffect(() => {
    if (!ok) return undefined;
    const t = setTimeout(carregar, 300);
    return () => clearTimeout(t);
  }, [ok, carregar]);

  const salvarPolitica = async () => {
    setSalvandoPolitica(true);
    try {
      const r = await cnpjService.definirPolitica(novaPolitica);
      setPolitica(r.politica);
      setNovaPolitica(null);
      showSuccess('Política de CNPJ duplicado atualizada');
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvandoPolitica(false);
    }
  };

  if (!ok) return <LayoutBase title="CNPJs" pageHeader={false}><p className="ck-mc-hint" role="status">Carregando…</p></LayoutBase>;

  return (
    <LayoutBase title="CNPJs" pageHeader={false}>
      <div className="ck-mc">
        <PageHeader title="CNPJs" description="Quem pode compartilhar CNPJ entre academias e quem administra os dados jurídicos de cada um." />

        <Panel title="Política de CNPJ duplicado" description="Vale para todo cadastro de academia: super admin, site e painel.">
          <ChoiceGroup label="Política de CNPJ duplicado">
            {POLITICAS.map((p) => (
              <ChoiceCard key={p.value} title={p.title} description={p.description} checked={politica === p.value}
                disabled={!politica} onSelect={() => { if (p.value !== politica) setNovaPolitica(p.value); }} />
            ))}
          </ChoiceGroup>
        </Panel>

        <div className="ck-cj-layout">
          <Panel title="Pesquisar CNPJ">
            <div className="ck-mc-form">
              <div className="ck-cj-toolbar">
                <TextField label="CNPJ, razão social ou academia" icon="search" value={busca}
                  onChange={(e) => setBusca(/^[\d./-]+$/.test(e.target.value) ? maskCnpj(e.target.value) : e.target.value)} />
              </div>
              <Switch label="Só CNPJs compartilhados" checked={compartilhados} onChange={setCompartilhados} />
              {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
              {!lista ? <p className="ck-mc-hint" role="status">Carregando…</p> : lista.length ? (
                <ul className="ck-cj-list">
                  {lista.map((c) => (
                    <li key={c.id}>
                      <button type="button" className={`ck-cj-item${selecionado === c.cnpj ? ' is-active' : ''}`} aria-current={selecionado === c.cnpj || undefined} onClick={() => setSelecionado(c.cnpj)}>
                        <span className="ck-cj-item__main">
                          <strong>{c.razao_social || 'Sem razão social'}</strong>
                          <small>{c.cnpj_formatado}</small>
                        </span>
                        {c.excecao_vigente ? <Badge tone="success">Exceção</Badge> : null}
                        <Badge tone={c.tenants_ativos > 1 ? 'info' : 'neutral'}>{c.tenants_ativos === 1 ? '1 academia' : `${c.tenants_ativos} academias`}</Badge>
                      </button>
                    </li>
                  ))}
                </ul>
              ) : <p className="ck-mc-hint">Nenhum CNPJ encontrado.</p>}
            </div>
          </Panel>
          <div className="ck-cj-stack">
            {selecionado ? <Detalhe key={selecionado} cnpj={selecionado} onChanged={carregar} /> : (
              <Callout tone="info" title="Escolha um CNPJ">Pesquise pelo CNPJ, razão social ou nome da academia para ver as academias vinculadas, as exceções e o histórico.</Callout>
            )}
          </div>
        </div>
      </div>

      <Modal open={!!novaPolitica} size="sm" title="Alterar a política?" dismissable={!salvandoPolitica} onClose={() => setNovaPolitica(null)}
        description={`Nova política: ${(POLITICAS.find((p) => p.value === novaPolitica) || {}).title || ''}. Vale para os próximos cadastros; academias que já compartilham CNPJ continuam vinculadas.`}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvandoPolitica} onClick={() => setNovaPolitica(null)}>Cancelar</Button>
            <Button variant="primary" icon="check" loading={salvandoPolitica} onClick={salvarPolitica}>Alterar política</Button>
          </>
        )} />
    </LayoutBase>
  );
}
