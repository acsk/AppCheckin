import React, { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, Modal, PageHeader, Tabs, TextField } from '../../components/ck';
import { Panel, digits, maskCep, maskCpf, maskPhone } from '../../components/ck/pessoas';
import { authService } from '../../services/authService';
import cepService from '../../services/cepService';
import minhaContaService from '../../services/minhaContaService';
import MeuPlanoPainel from '../meu-plano/MeuPlanoPainel';
import { showError, showSuccess } from '../../utils/toast';

// Minha Conta: perfil e segurança (qualquer usuário do painel) + escola, CNPJ e plano (admin da academia).
// Estilos: src/theme/ck-minha-conta.css. Permissões são conferidas na API; a tela só reflete o que ela devolve.

const AVISO_COMPARTILHADO = 'Este CNPJ é compartilhado com outra conta. Os dados jurídicos são administrados pela conta responsável.';
const ENDERECO_VAZIO = { cep: '', logradouro: '', numero: '', complemento: '', bairro: '', cidade: '', estado: '' };
const fdate = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—');
const plural = (n, um, muitos) => `${n} ${n === 1 ? um : muitos}`;

function useCarregar(fn) {
  const [dados, setDados] = useState(null);
  const [erro, setErro] = useState('');
  const carregar = useCallback(async () => {
    setErro('');
    try {
      setDados(await fn());
    } catch (e) {
      setErro(e.message);
    }
  }, [fn]);
  useEffect(() => { carregar(); }, [carregar]);
  return { dados, setDados, erro, carregar };
}

function Estado({ erro, onRetry, children, carregando }) {
  if (erro) return <Callout tone="danger" title="Não foi possível carregar" action={<Button size="sm" onClick={onRetry}>Tentar de novo</Button>}>{erro}</Callout>;
  if (carregando) return <p className="ck-mc-hint" role="status">Carregando…</p>;
  return children;
}

/** Endereço com busca de CEP. `readOnly` trava todos os campos. */
function EnderecoCampos({ value, onChange, readOnly, prefixo }) {
  const [buscando, setBuscando] = useState(false);
  const set = (campo) => (e) => onChange({ [campo]: campo === 'estado' ? e.target.value.toUpperCase().slice(0, 2) : e.target.value });
  const buscarCep = async () => {
    if (readOnly || digits(value.cep).length !== 8) return;
    setBuscando(true);
    try {
      const d = await cepService.buscar(value.cep);
      if (d) onChange({ logradouro: d.logradouro || value.logradouro, bairro: d.bairro || value.bairro, cidade: d.cidade || value.cidade, estado: d.estado || value.estado });
    } catch {
      // CEP não encontrado: o usuário preenche à mão
    } finally {
      setBuscando(false);
    }
  };
  const ro = { readOnly, icon: readOnly ? 'lock' : undefined };
  return (
    <div className="ck-mc-grid">
      <TextField label="CEP" id={`${prefixo}-cep`} inputMode="numeric" value={maskCep(value.cep)} onChange={(e) => onChange({ cep: digits(e.target.value) })} onBlur={buscarCep} hint={buscando ? 'Buscando endereço…' : null} {...ro} />
      <TextField label="Logradouro" id={`${prefixo}-logradouro`} value={value.logradouro || ''} onChange={set('logradouro')} className="ck-mc-span2" {...ro} />
      <TextField label="Número" id={`${prefixo}-numero`} value={value.numero || ''} onChange={set('numero')} {...ro} />
      <TextField label="Complemento" id={`${prefixo}-complemento`} value={value.complemento || ''} onChange={set('complemento')} className="ck-mc-span2" {...ro} />
      <TextField label="Bairro" id={`${prefixo}-bairro`} value={value.bairro || ''} onChange={set('bairro')} {...ro} />
      <TextField label="Cidade" id={`${prefixo}-cidade`} value={value.cidade || ''} onChange={set('cidade')} {...ro} />
      <TextField label="UF" id={`${prefixo}-estado`} value={value.estado || ''} onChange={set('estado')} maxLength={2} {...ro} />
    </div>
  );
}

const pickEndereco = (o) => Object.fromEntries(Object.keys(ENDERECO_VAZIO).map((k) => [k, o?.[k] || '']));

// ------------------------------------------------------------------ Meu perfil

function PerfilAba() {
  const { dados, setDados, erro, carregar } = useCarregar(minhaContaService.perfil);
  const [form, setForm] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const [pedirSenha, setPedirSenha] = useState(false);
  const [senha, setSenha] = useState('');
  const [erroSenha, setErroSenha] = useState('');

  const inicial = (d) => ({ nome: d.nome || '', telefone: digits(d.telefone), email: d.email || '' });
  useEffect(() => { if (dados) setForm(inicial(dados)); }, [dados]);

  const trocaEmail = form && dados && form.email.trim().toLowerCase() !== String(dados.email || '').toLowerCase();
  const alterado = form && dados && (form.nome !== (dados.nome || '') || digits(form.telefone) !== digits(dados.telefone) || trocaEmail);
  const erros = form ? {
    nome: !form.nome.trim() ? 'Informe o nome' : null,
    email: !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()) ? 'E-mail inválido' : null,
    telefone: digits(form.telefone) && ![10, 11].includes(digits(form.telefone).length) ? 'DDD + número' : null,
  } : {};
  const valido = form && !Object.values(erros).some(Boolean);

  const salvar = async (senhaAtual) => {
    setSalvando(true);
    setErroSenha('');
    try {
      const r = await minhaContaService.salvarPerfil({ nome: form.nome.trim(), telefone: digits(form.telefone), email: form.email.trim(), senhaAtual });
      setDados(r.perfil);
      setPedirSenha(false);
      setSenha('');
      showSuccess(trocaEmail ? 'Perfil salvo. Use o novo e-mail no próximo login.' : 'Perfil salvo');
    } catch (e) {
      if (e.code === 'SENHA_ATUAL_INVALIDA' || e.code === 'RATE_LIMIT_EXCEEDED') setErroSenha(e.message);
      else { setPedirSenha(false); showError(e.message); }
    } finally {
      setSalvando(false);
    }
  };

  return (
    <Estado erro={erro} onRetry={carregar} carregando={!form}>
      {form ? (
        <div className="ck-mc-cols">
          <Panel title="Meu perfil" description="Seus dados de acesso ao painel.">
            <div className="ck-mc-form">
              <TextField label="Nome" value={form.nome} error={erros.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} />
              <div className="ck-mc-grid ck-mc-grid--2">
                <TextField label="Telefone" inputMode="tel" icon="phone" value={maskPhone(form.telefone)} error={erros.telefone} onChange={(e) => setForm({ ...form, telefone: digits(e.target.value) })} />
                <TextField label="E-mail" type="email" icon="mail" value={form.email} error={erros.email}
                  hint={trocaEmail ? 'É o seu login. Vamos pedir sua senha para confirmar.' : null}
                  onChange={(e) => setForm({ ...form, email: e.target.value })} />
              </div>
              <div className="ck-mc-actions">
                <Button variant="ghost" disabled={!alterado || salvando} onClick={() => setForm(inicial(dados))}>Descartar</Button>
                <Button variant="primary" icon="save" loading={salvando && !pedirSenha} disabled={!alterado || !valido}
                  onClick={() => (trocaEmail ? setPedirSenha(true) : salvar())}>Salvar perfil</Button>
              </div>
            </div>
          </Panel>
          <Panel title="Informações da conta">
            <dl className="ck-mc-dl">
              <div><dt>CPF</dt><dd>{dados.cpf ? maskCpf(dados.cpf) : '—'}</dd></div>
              <div><dt>Conta criada em</dt><dd>{fdate(dados.criado_em)}</dd></div>
              <div><dt>Código do usuário</dt><dd className="ck-mc-mono">{dados.id}</dd></div>
            </dl>
          </Panel>
        </div>
      ) : null}
      <Modal open={pedirSenha} size="sm" title="Confirme sua senha" dismissable={!salvando} onClose={() => { setPedirSenha(false); setSenha(''); setErroSenha(''); }}
        description={`O e-mail de login vai mudar para ${form?.email?.trim()}.`}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvando} onClick={() => { setPedirSenha(false); setSenha(''); setErroSenha(''); }}>Cancelar</Button>
            <Button variant="primary" icon="check" loading={salvando} disabled={!senha} onClick={() => salvar(senha)}>Confirmar</Button>
          </>
        )}>
        <TextField label="Senha atual" type="password" autoComplete="current-password" value={senha} error={erroSenha} onChange={(e) => setSenha(e.target.value)} />
      </Modal>
    </Estado>
  );
}

// ------------------------------------------------------------------ Segurança

function SegurancaAba({ admin, suporte }) {
  if (suporte) return <AcessosSuporte />;
  return (
    <div className="ck-cj-stack">
      <AlterarSenha />
      {admin ? <AcessosSuporte /> : null}
    </div>
  );
}

function AlterarSenha() {
  const vazio = { senhaAtual: '', novaSenha: '', confirmacao: '' };
  const [f, setF] = useState(vazio);
  const [salvando, setSalvando] = useState(false);
  const [erroAtual, setErroAtual] = useState('');
  const [confirmar, setConfirmar] = useState(false);

  const erros = {
    novaSenha: f.novaSenha && f.novaSenha.length < 6 ? 'Mínimo de 6 caracteres' : f.novaSenha && f.novaSenha === f.senhaAtual ? 'Use uma senha diferente da atual' : null,
    confirmacao: f.confirmacao && f.confirmacao !== f.novaSenha ? 'A confirmação não confere' : null,
  };
  const pronto = f.senhaAtual && f.novaSenha && f.confirmacao && !erros.novaSenha && !erros.confirmacao;
  const set = (k) => (e) => { setF({ ...f, [k]: e.target.value }); if (k === 'senhaAtual') setErroAtual(''); };

  const salvar = async () => {
    setSalvando(true);
    try {
      await minhaContaService.alterarSenha(f);
      setF(vazio);
      setConfirmar(false);
      showSuccess('Senha alterada. Use a nova senha no próximo login.');
    } catch (e) {
      setConfirmar(false);
      if (e.code === 'SENHA_ATUAL_INVALIDA' || e.code === 'RATE_LIMIT_EXCEEDED') setErroAtual(e.message);
      else showError(e.message);
    } finally {
      setSalvando(false);
    }
  };

  return (
    <div className="ck-mc-cols">
      <Panel title="Alterar senha" description="Por segurança, confirme a senha atual antes de definir a nova.">
        <form className="ck-mc-form" onSubmit={(e) => { e.preventDefault(); if (pronto) setConfirmar(true); }}>
          <TextField label="Senha atual" type="password" autoComplete="current-password" icon="lock" value={f.senhaAtual} error={erroAtual} onChange={set('senhaAtual')} />
          <div className="ck-mc-grid ck-mc-grid--2">
            <TextField label="Nova senha" type="password" autoComplete="new-password" icon="key" value={f.novaSenha} error={erros.novaSenha} hint={!erros.novaSenha ? 'Mínimo de 6 caracteres' : null} onChange={set('novaSenha')} />
            <TextField label="Confirmar nova senha" type="password" autoComplete="new-password" icon="key" value={f.confirmacao} error={erros.confirmacao} onChange={set('confirmacao')} />
          </div>
          <div className="ck-mc-actions">
            <Button type="submit" variant="primary" icon="lock" disabled={!pronto}>Alterar senha</Button>
          </div>
        </form>
      </Panel>
      <Panel title="Dicas">
        <ul className="ck-mc-tips">
          <li>Não reutilize a senha de outros serviços.</li>
          <li>Misture letras, números e símbolos.</li>
          <li>Esqueceu a senha? Saia do painel e use “Esqueci minha senha” no login.</li>
        </ul>
      </Panel>
      <Modal open={confirmar} size="sm" title="Alterar a senha?" dismissable={!salvando} onClose={() => setConfirmar(false)}
        description="A nova senha passa a valer no próximo login, no painel e no app."
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvando} onClick={() => setConfirmar(false)}>Cancelar</Button>
            <Button variant="primary" icon="check" loading={salvando} onClick={salvar}>Alterar senha</Button>
          </>
        )} />
    </div>
  );
}

/** Acessos do suporte Check-in a esta academia ("acessar como academia" do super admin). */
function AcessosSuporte() {
  const { dados, erro, carregar } = useCarregar(minhaContaService.acessosSuporte);
  const fhora = (v) => (v ? `${fdate(v)} ${String(v).slice(11, 16)}` : '');
  return (
    <Panel title="Acessos do suporte Check-in" description="Quando o suporte entra no painel da sua academia, fica registrado aqui com o motivo.">
      <Estado erro={erro} onRetry={carregar} carregando={!dados}>
        {dados?.length ? (
          <ul className="ck-cj-rows">
            {dados.map((a) => (
              <li key={a.id}>
                <span className="ck-cj-rows__main">
                  <strong>{a.motivo}</strong>
                  <small>{`${a.usuario_nome} · ${fhora(a.iniciado_em)}${a.encerrado_em ? ` até ${String(a.encerrado_em).slice(11, 16)}` : ''} · ${plural(a.alteracoes, 'alteração', 'alterações')}`}</small>
                </span>
                {a.em_andamento ? <Badge tone="warning">Em andamento</Badge> : <Badge>Encerrado</Badge>}
              </li>
            ))}
          </ul>
        ) : <p className="ck-mc-hint">Nenhum acesso do suporte até agora.</p>}
      </Estado>
    </Panel>
  );
}

// ------------------------------------------------------------------ Dados da escola

function EscolaAba() {
  const { dados, setDados, erro, carregar } = useCarregar(minhaContaService.escola);
  const [form, setForm] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const inicial = (e) => ({ nome: e.nome || '', email: e.email || '', telefone: digits(e.telefone), ...pickEndereco(e), cep: digits(e.cep) });
  useEffect(() => { if (dados) setForm(inicial(dados)); }, [dados]);

  const alterado = form && dados && JSON.stringify(form) !== JSON.stringify(inicial(dados));
  const erros = form ? {
    nome: !form.nome.trim() ? 'Informe o nome da escola' : null,
    email: !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()) ? 'E-mail inválido' : null,
    telefone: digits(form.telefone) && ![10, 11].includes(digits(form.telefone).length) ? 'DDD + número' : null,
  } : {};

  const salvar = async () => {
    setSalvando(true);
    try {
      const r = await minhaContaService.salvarEscola({ ...form, telefone: digits(form.telefone) });
      setDados(r.escola);
      showSuccess('Dados da escola salvos');
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvando(false);
    }
  };

  return (
    <Estado erro={erro} onRetry={carregar} carregando={!form}>
      {form ? (
        <Panel title="Dados da escola" description="Informações desta unidade. Mesmo com CNPJ compartilhado, outras escolas não são afetadas.">
          <div className="ck-mc-form">
            <TextField label="Nome de exibição" value={form.nome} error={erros.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} />
            <div className="ck-mc-grid ck-mc-grid--2">
              <TextField label="E-mail de contato" type="email" icon="mail" value={form.email} error={erros.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
              <TextField label="Telefone" inputMode="tel" icon="phone" value={maskPhone(form.telefone)} error={erros.telefone} onChange={(e) => setForm({ ...form, telefone: digits(e.target.value) })} />
            </div>
            <h3 className="ck-mc-sub">Endereço da unidade</h3>
            <EnderecoCampos prefixo="escola" value={form} onChange={(p) => setForm({ ...form, ...p })} />
            <p className="ck-mc-hint">Grupos de WhatsApp e formas de pagamento ficam em Configurações.</p>
            <div className="ck-mc-actions">
              <Button variant="ghost" disabled={!alterado || salvando} onClick={() => setForm(inicial(dados))}>Descartar</Button>
              <Button variant="primary" icon="save" loading={salvando} disabled={!alterado || Object.values(erros).some(Boolean)} onClick={salvar}>Salvar dados da escola</Button>
            </div>
          </div>
        </Panel>
      ) : null}
    </Estado>
  );
}

// ------------------------------------------------------------------ Dados do CNPJ

function CnpjAba() {
  const { dados, setDados, erro, carregar } = useCarregar(minhaContaService.cnpj);
  const [form, setForm] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const [confirmar, setConfirmar] = useState(false);
  const inicial = (d) => ({ razao_social: d.razao_social || '', nome_fantasia: d.nome_fantasia || '', inscricao_estadual: d.inscricao_estadual || '', inscricao_municipal: d.inscricao_municipal || '', ...pickEndereco(d), cep: digits(d.cep) });
  useEffect(() => { if (dados?.vinculado) setForm(inicial(dados.dados)); }, [dados]);

  if (dados && !dados.vinculado) {
    return (
      <Callout tone="info" title="Nenhum CNPJ vinculado a esta escola">
        O CNPJ é pedido quando você paga o plano do Check-in. Para cadastrar ou trocar o CNPJ, fale com o suporte.
      </Callout>
    );
  }

  const podeEditar = !!dados?.pode_editar;
  const alterado = form && dados && JSON.stringify(form) !== JSON.stringify(inicial(dados.dados));
  const erroRazao = form && podeEditar && !form.razao_social.trim() ? 'Informe a razão social' : null;
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });
  const ro = { readOnly: !podeEditar, icon: !podeEditar ? 'lock' : undefined };

  const salvar = async () => {
    setSalvando(true);
    try {
      const r = await minhaContaService.salvarCnpj({ ...form, cep: digits(form.cep) });
      setDados(r.cnpj);
      setConfirmar(false);
      showSuccess(dados.compartilhado ? 'Dados do CNPJ salvos para todas as contas vinculadas' : 'Dados do CNPJ salvos');
    } catch (e) {
      setConfirmar(false);
      showError(e.message);
      if (e.code === 'CNPJ_SOMENTE_LEITURA') carregar();
    } finally {
      setSalvando(false);
    }
  };

  return (
    <Estado erro={erro} onRetry={carregar} carregando={!form}>
      {form ? (
        <Panel title="Dados do CNPJ" description="Dados jurídicos da empresa responsável por esta escola."
          actions={(
            <span className="ck-mc-badges">
              <Badge tone={podeEditar ? 'success' : 'neutral'} icon={podeEditar ? 'check-circle' : 'lock'}>{dados.papel === 'gestor' ? 'Conta responsável' : 'Conta vinculada'}</Badge>
              {dados.compartilhado ? <Badge tone="info">{`Compartilhado com ${plural(dados.outras_contas, 'outra conta', 'outras contas')}`}</Badge> : null}
            </span>
          )}>
          <div className="ck-mc-form">
            {!podeEditar ? (
              <p className="ck-mc-note" role="note">
                {dados.tem_gestor ? AVISO_COMPARTILHADO : 'Este CNPJ está sem conta responsável no momento. Para alterar os dados jurídicos, fale com o suporte.'}
              </p>
            ) : dados.compartilhado ? (
              <Callout tone="warning">{`As alterações aparecem para todas as ${dados.outras_contas + 1} contas que usam este CNPJ. Plano, pagamentos e dados de cada escola continuam separados.`}</Callout>
            ) : null}
            <div className="ck-mc-grid ck-mc-grid--2">
              <TextField label="CNPJ" value={dados.cnpj_formatado} readOnly icon="lock" mono hint="Para trocar o CNPJ, fale com o suporte." />
              <TextField label="Razão social" value={form.razao_social} error={erroRazao} onChange={set('razao_social')} {...ro} />
              <TextField label="Nome fantasia" value={form.nome_fantasia} onChange={set('nome_fantasia')} {...ro} />
              <TextField label="Inscrição estadual" value={form.inscricao_estadual} onChange={set('inscricao_estadual')} {...ro} />
              <TextField label="Inscrição municipal" value={form.inscricao_municipal} onChange={set('inscricao_municipal')} {...ro} />
            </div>
            <h3 className="ck-mc-sub">Endereço fiscal</h3>
            <EnderecoCampos prefixo="fiscal" value={form} readOnly={!podeEditar} onChange={(p) => setForm({ ...form, ...p })} />
            <p className="ck-mc-hint">{`Vinculado desde ${fdate(dados.vinculado_em)} · atualizado em ${fdate(dados.atualizado_em)}`}</p>
            {podeEditar ? (
              <div className="ck-mc-actions">
                <Button variant="ghost" disabled={!alterado || salvando} onClick={() => setForm(inicial(dados.dados))}>Descartar</Button>
                <Button variant="primary" icon="save" loading={salvando && !confirmar} disabled={!alterado || !!erroRazao}
                  onClick={() => (dados.compartilhado ? setConfirmar(true) : salvar())}>Salvar dados do CNPJ</Button>
              </div>
            ) : null}
          </div>
        </Panel>
      ) : null}
      <Modal open={confirmar} size="sm" title="Salvar para todas as contas?" dismissable={!salvando} onClose={() => setConfirmar(false)}
        description={dados ? `Os dados jurídicos deste CNPJ aparecem em ${dados.outras_contas + 1} contas. A alteração vale para todas.` : ''}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvando} onClick={() => setConfirmar(false)}>Cancelar</Button>
            <Button variant="primary" icon="check" loading={salvando} onClick={salvar}>Salvar</Button>
          </>
        )} />
    </Estado>
  );
}

// ------------------------------------------------------------------ tela

const ABAS = [
  { value: 'perfil', label: 'Meu perfil' },
  { value: 'seguranca', label: 'Segurança' },
  { value: 'escola', label: 'Dados da escola', admin: true },
  { value: 'cnpj', label: 'Dados do CNPJ', admin: true },
  { value: 'plano', label: 'Plano e assinatura', admin: true },
];

export default function MinhaContaScreen() {
  const params = useLocalSearchParams();
  const [admin, setAdmin] = useState(null);
  const [suporte, setSuporte] = useState(false);
  const [aba, setAba] = useState(ABAS.some((a) => a.value === params.aba) ? params.aba : 'perfil');

  useEffect(() => {
    authService.getCurrentUser().then((u) => { setSuporte(!!u?.acesso_suporte); setAdmin(authService.getEffectivePapelId(u) === 3); }).catch(() => setAdmin(false));
  }, []);

  // No acesso de suporte o "meu perfil" seria o do super admin: fica de fora
  const abas = ABAS.filter((a) => (!a.admin || admin) && !(suporte && a.value === 'perfil'));
  const atual = abas.some((a) => a.value === aba) ? aba : abas[0]?.value;

  return (
    <LayoutBase title="Minha conta" pageHeader={false}>
      <div className="ck-mc">
        <PageHeader title="Minha conta" description={admin ? 'Seu perfil, a segurança do acesso e os dados da academia.' : 'Seu perfil e a segurança do acesso.'} />
        {admin === null ? <p className="ck-mc-hint" role="status">Carregando…</p> : (
          <>
            <Tabs label="Seções de Minha conta" value={atual} onChange={setAba} tabs={abas} className="ck-mc-tabs" />
            <div role="tabpanel" aria-label={abas.find((a) => a.value === atual)?.label}>
              {atual === 'perfil' ? <PerfilAba /> : null}
              {atual === 'seguranca' ? <SegurancaAba admin={admin} suporte={suporte} /> : null}
              {atual === 'escola' ? <EscolaAba /> : null}
              {atual === 'cnpj' ? <CnpjAba /> : null}
              {atual === 'plano' ? <MeuPlanoPainel /> : null}
            </div>
          </>
        )}
      </div>
    </LayoutBase>
  );
}
