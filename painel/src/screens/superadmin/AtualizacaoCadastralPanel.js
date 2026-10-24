import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Badge, Button, Callout, ChoiceCard, ChoiceGroup, Modal, SegmentedControl, Switch, TextAreaField, TextField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import atualizacaoCadastralService from '../../services/atualizacaoCadastralService';
import { superAdminService } from '../../services/superAdminService';
import { showError, showSuccess } from '../../utils/toast';

// Super Admin → App mobile → Atualização cadastral: pede ao usuário do app (alunos e professores) para revisar o
// cadastro, confirmar o e-mail por código e/ou trocar a senha. Estilos: ck-mc-*/ck-cj-* (ck-minha-conta.css).

const NIVEIS = [
  { value: 'basica', title: 'Básica', description: 'Só o tamanho mínimo.' },
  { value: 'media', title: 'Média', description: 'Letras e números.' },
  { value: 'forte', title: 'Forte', description: 'Maiúscula, minúscula, número e símbolo.' },
];
const PEDIDO = [
  { value: 'nao', label: 'Não pedir' },
  { value: 'opcional', label: 'Opcional' },
  { value: 'obrigatorio', label: 'Obrigatório' },
];
const VAZIA = {
  titulo: 'Atualize seu cadastro',
  mensagem: 'Precisamos que você confira seus dados para continuar usando o app.',
  campos: [],
  campos_obrigatorios: [],
  validar_email: false,
  trocar_senha: false,
  senha_nivel: 'media',
  senha_min_caracteres: 8,
  bloquear_app: true,
  todas_academias: true,
  academias: [],
};
const fdata = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—');

function resumo(c, campos) {
  const rotulo = (k) => (campos.find((f) => f.campo === k) || {}).label || k;
  const partes = [];
  if (c.campos.length) partes.push(`${c.campos.length} campo${c.campos.length > 1 ? 's' : ''}: ${c.campos.map(rotulo).join(', ')}`);
  if (c.validar_email) partes.push('validação de e-mail');
  if (c.trocar_senha) partes.push(`troca de senha (${(NIVEIS.find((n) => n.value === c.senha_nivel) || {}).title || c.senha_nivel}, ${c.senha_min_caracteres}+ caracteres)`);
  return partes.join(' · ');
}

function CampanhaModal({ open, campanha, campos, academias, saving, onSave, onClose }) {
  const [f, setF] = useState(VAZIA);
  const [tentou, setTentou] = useState(false);
  useEffect(() => {
    if (!open) return;
    setTentou(false);
    setF(campanha ? { ...VAZIA, ...campanha, academias: (campanha.academias || []).map((a) => a.id) } : VAZIA);
  }, [open, campanha]);

  const set = (k) => (v) => setF((p) => ({ ...p, [k]: v }));
  const pedido = (k) => (f.campos_obrigatorios.includes(k) ? 'obrigatorio' : f.campos.includes(k) ? 'opcional' : 'nao');
  const setPedido = (k, v) => setF((p) => ({
    ...p,
    campos: v === 'nao' ? p.campos.filter((c) => c !== k) : [...new Set([...p.campos, k])],
    campos_obrigatorios: v === 'obrigatorio' ? [...new Set([...p.campos_obrigatorios, k])] : p.campos_obrigatorios.filter((c) => c !== k),
  }));
  const toggleAcademia = (id) => setF((p) => ({ ...p, academias: p.academias.includes(id) ? p.academias.filter((a) => a !== id) : [...p.academias, id] }));
  const pedirTodos = (v) => setF((p) => ({ ...p, campos: v === 'nao' ? [] : campos.map((c) => c.campo), campos_obrigatorios: v === 'obrigatorio' ? campos.map((c) => c.campo) : [] }));

  const min = Number(f.senha_min_caracteres) || 0;
  const erros = {};
  if (!f.titulo.trim()) erros.titulo = 'Dê um título.';
  if (!f.campos.length && !f.validar_email && !f.trocar_senha) erros.geral = 'Peça ao menos um campo, a validação do e-mail ou a troca de senha.';
  if (f.trocar_senha && (min < 6 || min > 32)) erros.min = 'Entre 6 e 32 caracteres.';
  if (!f.todas_academias && !f.academias.length) erros.academias = 'Escolha ao menos uma academia.';
  const ok = !Object.keys(erros).length;

  const salvar = () => {
    setTentou(true);
    if (ok && !saving) onSave({ ...f, senha_min_caracteres: min });
  };

  return (
    <Modal open={open} size="lg" title={campanha ? 'Editar atualização cadastral' : 'Nova atualização cadastral'}
      description="Quem usa o app nas academias escolhidas vê o pedido na próxima vez que abrir o app."
      dismissable={!saving} onClose={onClose}
      footer={(
        <>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={saving} onClick={onClose}>Cancelar</Button>
          <Button variant="primary" icon="save" loading={saving} onClick={salvar}>{campanha ? 'Salvar' : 'Criar e enviar'}</Button>
        </>
      )}>
      <div className="ck-mc-form">
        <TextField label="Título" value={f.titulo} onChange={(e) => set('titulo')(e.target.value)} error={tentou ? erros.titulo : null} hint="Aparece no topo da tela do app." />
        <TextAreaField label="Mensagem" value={f.mensagem || ''} onChange={(e) => set('mensagem')(e.target.value)} labelAside={<span className="ck-field__hint">Opcional</span>} />

        <div className="ck-field">
          <span className="ck-field__label">Academias</span>
          <Switch label="Todas as academias" description={f.todas_academias ? 'Inclui academias criadas depois.' : 'Só as academias marcadas abaixo.'} checked={f.todas_academias} onChange={set('todas_academias')} />
          {f.todas_academias ? null : (
            <div className="ck-pf__chips" role="group" aria-label="Academias">
              {academias.map((a) => (
                <button key={a.id} type="button" role="checkbox" aria-checked={f.academias.includes(a.id)} className="ck-pf__chip" onClick={() => toggleAcademia(a.id)}>{a.nome}</button>
              ))}
            </div>
          )}
          {tentou && erros.academias ? <span className="ck-field__error">{erros.academias}</span> : null}
        </div>

        <div className="ck-field">
          <span className="ck-field__label">Dados cadastrais</span>
          <span className="ck-field__hint">O app mostra o valor atual de cada campo para o usuário conferir ou corrigir.</span>
          <div className="ck-pf__chips">
            <Button size="sm" variant="ghost" onClick={() => pedirTodos('opcional')}>Pedir todos</Button>
            <Button size="sm" variant="ghost" onClick={() => pedirTodos('obrigatorio')}>Todos obrigatórios</Button>
            <Button size="sm" variant="ghost" onClick={() => pedirTodos('nao')}>Limpar</Button>
          </div>
          <ul className="ck-cj-rows">
            {campos.map((c) => (
              <li key={c.campo}>
                <span className="ck-cj-rows__main"><strong>{c.label}</strong></span>
                <SegmentedControl label={c.label} options={PEDIDO} value={pedido(c.campo)} onChange={(v) => setPedido(c.campo, v)} />
              </li>
            ))}
          </ul>
        </div>

        <Switch label="Validar e-mail" description="Envia um código de 6 dígitos para o e-mail; o usuário digita no app. Quem já confirmou o e-mail pula esta etapa." checked={f.validar_email} onChange={set('validar_email')} />

        <Switch label="Forçar troca de senha" description="Pede a senha atual e uma nova. A nova nunca pode ser o CPF." checked={f.trocar_senha} onChange={set('trocar_senha')} />
        {f.trocar_senha ? (
          <>
            <ChoiceGroup label="Tipo de senha segura">
              {NIVEIS.map((n) => <ChoiceCard key={n.value} title={n.title} description={n.description} checked={f.senha_nivel === n.value} onSelect={() => set('senha_nivel')(n.value)} />)}
            </ChoiceGroup>
            <TextField label="Quantidade mínima de caracteres" inputMode="numeric" value={String(f.senha_min_caracteres)}
              onChange={(e) => set('senha_min_caracteres')(e.target.value.replace(/\D/g, '').slice(0, 2))} error={tentou ? erros.min : null} hint="Entre 6 e 32." />
          </>
        ) : null}

        <Switch label="Bloquear o app até concluir" description={f.bloquear_app ? 'O usuário só volta a usar o app depois de atualizar.' : 'O usuário pode tocar em "Agora não"; o pedido volta na próxima abertura do app.'} checked={f.bloquear_app} onChange={set('bloquear_app')} />

        {tentou && erros.geral ? <Callout tone="danger">{erros.geral}</Callout> : null}
      </div>
    </Modal>
  );
}

function PendentesModal({ campanha, onClose }) {
  const [lista, setLista] = useState(null);
  const [erro, setErro] = useState('');
  useEffect(() => {
    if (!campanha) return;
    setLista(null);
    setErro('');
    atualizacaoCadastralService.pendentes(campanha.id).then(setLista).catch((e) => setErro(e.message));
  }, [campanha]);

  return (
    <Modal open={!!campanha} size="md" title="Quem ainda não atualizou" onClose={onClose}
      description="O cadastro é único: quem está em mais de uma academia responde uma vez e vale para todas.">
      {erro ? <Callout tone="danger">{erro}</Callout>
        : !lista ? <p className="ck-mc-hint" role="status">Carregando…</p>
          : lista.length ? (
            <ul className="ck-cj-rows">
              {lista.map((u) => (
                <li key={u.id}>
                  <span className="ck-cj-rows__main">
                    <strong>{u.nome}</strong>
                    <small>{u.email} · {u.academias.join(', ')}</small>
                  </span>
                </li>
              ))}
            </ul>
          ) : <p className="ck-mc-hint">Todos já atualizaram.</p>}
    </Modal>
  );
}

// Seção da tela App mobile (o acesso de super admin é checado pela tela).
export default function AtualizacaoCadastralPanel() {
  const [dados, setDados] = useState(null);
  const [academias, setAcademias] = useState([]);
  const [erro, setErro] = useState('');
  const [modal, setModal] = useState(null); // { campanha } (null campanha = nova)
  const [salvando, setSalvando] = useState(false);
  const [encerrar, setEncerrar] = useState(null);
  const [pendentes, setPendentes] = useState(null);

  const carregar = useCallback(async () => {
    setErro('');
    try { setDados(await atualizacaoCadastralService.listar()); } catch (e) { setErro(e.message); }
  }, []);
  useEffect(() => {
    carregar();
    superAdminService.listarAcademias().then((r) => setAcademias((r.academias || []).map((a) => ({ id: a.id, nome: a.nome })))).catch(() => {});
  }, [carregar]);

  const campos = dados?.campos || [];
  const [ativas, encerradas] = useMemo(() => {
    const lista = dados?.campanhas || [];
    return [lista.filter((c) => c.ativa), lista.filter((c) => !c.ativa)];
  }, [dados]);

  const salvar = async (form) => {
    setSalvando(true);
    try {
      await atualizacaoCadastralService.salvar(modal?.campanha?.id, form);
      showSuccess(modal?.campanha ? 'Atualização cadastral salva' : 'Atualização cadastral criada');
      setModal(null);
      carregar();
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvando(false);
    }
  };

  const confirmarEncerrar = async () => {
    setSalvando(true);
    try {
      await atualizacaoCadastralService.encerrar(encerrar.id);
      showSuccess('Atualização cadastral encerrada');
      setEncerrar(null);
      carregar();
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvando(false);
    }
  };

  const linha = (c) => (
    <li key={c.id}>
      <span className="ck-cj-rows__main">
        <strong>{c.titulo}</strong>
        <small>{resumo(c, campos)}</small>
        <small>{c.todas_academias ? 'Todas as academias' : c.academias.map((a) => a.nome).join(', ')} · criada em {fdata(c.created_at)}{c.encerrada_em ? ` · encerrada em ${fdata(c.encerrada_em)}` : ''}</small>
      </span>
      <Badge tone={c.respostas >= c.publico && c.publico > 0 ? 'success' : 'info'}>{`${c.respostas} de ${c.publico} atualizaram`}</Badge>
      {c.bloquear_app ? <Badge tone="warning" icon="lock">Bloqueia o app</Badge> : <Badge tone="outline">Pode adiar</Badge>}
      {c.respostas < c.publico ? <Button size="sm" variant="ghost" icon="users" onClick={() => setPendentes(c)}>Ver pendentes</Button> : null}
      {c.ativa ? (
        <>
          <Button size="sm" variant="ghost" icon="pencil" onClick={() => setModal({ campanha: c })}>Editar</Button>
          <Button size="sm" variant="ghost" icon="x" onClick={() => setEncerrar(c)}>Encerrar</Button>
        </>
      ) : null}
    </li>
  );

  return (
    <>
      <Panel
        title="Atualização cadastral"
        description="Peça aos usuários do app para revisar o cadastro, confirmar o e-mail ou trocar a senha. Cada usuário responde uma vez, valendo para todas as academias; se houver mais de uma em andamento, o app pede na ordem em que foram criadas."
        actions={<Button size="sm" variant="primary" icon="plus" onClick={() => setModal({ campanha: null })}>Nova atualização</Button>}
      >
        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout>
          : !dados ? <p className="ck-mc-hint" role="status">Carregando…</p>
            : ativas.length ? <ul className="ck-cj-rows">{ativas.map(linha)}</ul>
              : <p className="ck-mc-hint">Nenhuma atualização cadastral em andamento.</p>}
        {encerradas.length ? (
          <details>
            <summary className="ck-mc-hint">{`Encerradas (${encerradas.length})`}</summary>
            <ul className="ck-cj-rows">{encerradas.map(linha)}</ul>
          </details>
        ) : null}
      </Panel>

      <CampanhaModal open={!!modal} campanha={modal?.campanha} campos={campos} academias={academias} saving={salvando} onSave={salvar} onClose={() => setModal(null)} />

      <PendentesModal campanha={pendentes} onClose={() => setPendentes(null)} />

      <Modal open={!!encerrar} size="sm" title="Encerrar esta atualização?" dismissable={!salvando} onClose={() => setEncerrar(null)}
        description="Quem ainda não respondeu deixa de ver o pedido. As respostas já recebidas ficam registradas."
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvando} onClick={() => setEncerrar(null)}>Cancelar</Button>
            <Button variant="danger" icon="x" loading={salvando} onClick={confirmarEncerrar}>Encerrar</Button>
          </>
        )} />
    </>
  );
}
