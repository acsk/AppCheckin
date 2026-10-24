import React, { useCallback, useEffect, useState } from 'react';
import { Button, Callout, ChoiceCard, ChoiceGroup, Modal, SegmentedControl, Switch, TextAreaField, TextField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import AtualizacaoCadastralTable from './AtualizacaoCadastralTable';
import atualizacaoCadastralService from '../../services/atualizacaoCadastralService';
import { superAdminService } from '../../services/superAdminService';
import { showError, showSuccess } from '../../utils/toast';

// Super Admin → App mobile → Atualização cadastral: pede aos alunos com matrícula ativa para revisar o
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
  forcar_validacao_email: false,
  trocar_senha: false,
  senha_nivel: 'media',
  senha_min_caracteres: 8,
  bloquear_app: true,
  todas_academias: true,
  academias: [],
};

function resumo(c, campos) {
  const rotulo = (k) => (campos.find((f) => f.campo === k) || {}).label || k;
  const partes = [];
  if (c.campos.length) partes.push(`${c.campos.length} campo${c.campos.length > 1 ? 's' : ''}: ${c.campos.map(rotulo).join(', ')}`);
  if (c.validar_email) partes.push(c.forcar_validacao_email ? 'nova validação de e-mail obrigatória' : 'validação de e-mail');
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
      description="Alunos com matrícula ativa nas academias escolhidas veem o pedido na próxima vez que abrirem o app."
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

        <Switch label="Validar e-mail" description="Envia um código de 6 dígitos para o e-mail; o usuário digita no app. Quem já confirmou o e-mail pula esta etapa, exceto quando uma nova validação é forçada."
          checked={f.validar_email} onChange={(v) => setF((p) => ({ ...p, validar_email: v, forcar_validacao_email: v ? p.forcar_validacao_email : false }))} />
        {f.validar_email ? <Switch label="Forçar nova validação de e-mail"
          description="Exige um novo código nesta campanha, mesmo de quem já confirmou o e-mail. Quem já concluiu esta campanha não recebe o pedido novamente; para essas pessoas, crie uma nova campanha."
          checked={f.forcar_validacao_email} onChange={set('forcar_validacao_email')} /> : null}

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
  const [busca, setBusca] = useState('');
  useEffect(() => {
    if (!campanha) return;
    let atual = true;
    setLista(null);
    setErro('');
    setBusca('');
    atualizacaoCadastralService.pendentes(campanha.id)
      .then((r) => { if (atual) setLista(r); })
      .catch((e) => { if (atual) setErro(e.message); });
    return () => { atual = false; };
  }, [campanha]);

  const termo = busca.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const filtrados = (lista || []).filter((u) => `${u.nome} ${u.email} ${u.academias.join(' ')}`.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().includes(termo));

  return (
    <Modal open={!!campanha} size="lg" title="Alunos com matrícula ativa pendentes" onClose={onClose}
      description="Apenas alunos com matrícula ativa nas academias desta campanha. Quem está em mais de uma academia responde uma vez.">
      {erro ? <Callout tone="danger">{erro}</Callout>
        : !lista ? <p className="ck-mc-hint" role="status">Carregando…</p>
          : lista.length ? <div className="ck-cad-table">
            <TextField label="Buscar usuário" icon="search" type="search" value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Nome, e-mail ou academia" />
            <p className="ck-mc-hint" role="status">{filtrados.length} de {lista.length} alunos com matrícula ativa pendentes</p>
            <div className="ck-pc__wrap">
              <table className="ck-pc__table ck-cad-table__pending">
                <caption className="ck-sr">Alunos com matrícula ativa que ainda não responderam</caption>
                <thead><tr><th scope="col">Aluno</th><th scope="col">E-mail</th><th scope="col">Academias</th></tr></thead>
                <tbody>{filtrados.map((u) => <tr key={u.id}><td>{u.nome}</td><td>{u.email}</td><td>{u.academias.join(', ')}</td></tr>)}
                  {!filtrados.length ? <tr><td colSpan={3} className="ck-cad-table__empty">Nenhum usuário encontrado nesta busca.</td></tr> : null}
                </tbody>
              </table>
            </div>
          </div> : <p className="ck-mc-hint">Nenhum aluno com matrícula ativa pendente.</p>}

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
  const [excluir, setExcluir] = useState(null);
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

  const confirmarExcluir = async () => {
    if (!excluir || salvando) return;
    setSalvando(true);
    try {
      await atualizacaoCadastralService.excluir(excluir.id);
      showSuccess('Atualização cadastral excluída definitivamente');
      setExcluir(null);
      await carregar();
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvando(false);
    }
  };

  return (
    <>
      <Panel
        title="Atualização cadastral"
        description="Peça aos alunos com matrícula ativa para revisar o cadastro, confirmar o e-mail ou trocar a senha. Cada usuário responde uma vez, valendo para todas as academias; se houver mais de uma em andamento, o app pede na ordem em que foram criadas."
        actions={<Button size="sm" variant="primary" icon="plus" onClick={() => setModal({ campanha: null })}>Nova atualização</Button>}
      >
        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout>
          : !dados ? <p className="ck-mc-hint" role="status">Carregando…</p>
            : <AtualizacaoCadastralTable campanhas={dados.campanhas || []} resumo={(c) => resumo(c, campos)}
              onPendentes={setPendentes} onEditar={(c) => setModal({ campanha: c })}
              onEncerrar={setEncerrar} onExcluir={setExcluir} disabled={salvando} />}

      </Panel>

      <CampanhaModal open={!!modal} campanha={modal?.campanha} campos={campos} academias={academias} saving={salvando} onSave={salvar} onClose={() => setModal(null)} />

      <PendentesModal campanha={pendentes} onClose={() => setPendentes(null)} />

      <Modal open={!!excluir} size="sm" title="Excluir esta atualização definitivamente?" dismissable={!salvando} onClose={() => setExcluir(null)}
        description="A campanha, as respostas e os códigos de e-mail vinculados a ela serão apagados do banco. Os dados cadastrais, a senha e o e-mail já confirmado dos usuários serão mantidos. Esta ação não pode ser desfeita."
        footer={<>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={salvando} onClick={() => setExcluir(null)}>Cancelar</Button>
          <Button variant="danger" icon="trash" loading={salvando} onClick={confirmarExcluir}>Excluir do banco</Button>
        </>}>
        <p className="ck-mc-hint">{excluir?.titulo}</p>
        {excluir?.ativa ? <Callout tone="warning">Esta atualização está em andamento. Os usuários deixam de receber o pedido após a exclusão.</Callout> : null}
      </Modal>

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
