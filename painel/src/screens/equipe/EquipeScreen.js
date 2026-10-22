import React, { useCallback, useEffect, useMemo, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, Icon, IconButton, Modal, PageHeader, TextField } from '../../components/ck';
import { Panel, digits, maskCpf, maskPhone, titleCase } from '../../components/ck/pessoas';
import { authService } from '../../services/authService';
import equipeService from '../../services/equipeService';
import { showError, showSuccess } from '../../utils/toast';

// Equipe e permissões da academia. Regras na API (AdminEquipeService): uma pessoa = um cadastro
// (CPF/e-mail), limite do plano, admin principal só pelo suporte, ninguém altera a si mesmo,
// só concede o que tem. Professor é gerido na tela Professores. Estilos: ck-ct-role* (ck-contratos.css).

const PAPEIS = { 2: 'Professor', 3: 'Admin', 5: 'Comercial', 6: 'Financeiro' };
const DESCRICAO_PAPEL = {
  3: 'Gerencia a academia: alunos, matrículas, financeiro, aulas e configurações.',
  6: 'Alunos, matrículas e cobranças.',
  5: 'Recuperação de alunos, sem valores financeiros.',
};

function Opcao({ on, disabled, titulo, descricao, aviso, onToggle }) {
  return (
    <label className={`ck-ct-role${on ? ' is-on' : ''}${disabled ? ' is-locked' : ''}`}>
      <span className="ck-check">
        <input type="checkbox" checked={on} disabled={disabled} onChange={onToggle} />
        <span className="ck-check__box" aria-hidden="true"><Icon name="check" size={13} strokeWidth={3} /></span>
      </span>
      <span className="ck-ct-role__text"><strong>{titulo}</strong>{descricao ? <span>{descricao}</span> : null}</span>
      {aviso ? <span className="ck-ct-role__lock">{disabled ? <Icon name="lock" size={13} /> : null}{aviso}</span> : null}
    </label>
  );
}

function PapeisCampo({ gerenciaveis, value, onChange }) {
  const alternar = (p) => onChange(value.includes(p) ? value.filter((x) => x !== p) : [...value, p]);
  return (
    <fieldset className="ck-ct-roles">
      <legend className="ck-field__label">Papéis</legend>
      {gerenciaveis.map((p) => <Opcao key={p} on={value.includes(p)} titulo={PAPEIS[p]} descricao={DESCRICAO_PAPEL[p]} onToggle={() => alternar(p)} />)}
    </fieldset>
  );
}

function AdicionarModal({ open, gerenciaveis, onClose, onDone }) {
  const vazio = { cpf: '', email: '', nome: '', telefone: '', senha: '', papeis: [6] };
  const [f, setF] = useState(vazio);
  const [busca, setBusca] = useState(null); // null = ainda não procurou; {encontrado, usuario}
  const [procurando, setProcurando] = useState(false);
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState('');
  useEffect(() => { if (open) { setF(vazio); setBusca(null); setErro(''); } }, [open]);
  const set = (k) => (e) => { setF({ ...f, [k]: e.target.value }); if (k === 'cpf' || k === 'email') setBusca(null); };

  const procurar = async () => {
    setProcurando(true); setErro('');
    try { setBusca(await equipeService.buscar({ cpf: digits(f.cpf), email: f.email.trim() })); } catch (e) { setErro(e.message); } finally { setProcurando(false); }
  };
  const salvar = async () => {
    setSalvando(true); setErro('');
    try {
      const r = await equipeService.adicionar({ ...f, cpf: digits(f.cpf), telefone: digits(f.telefone), email: f.email.trim() });
      showSuccess(r.message);
      onDone(r);
    } catch (e) { setErro(e.message); } finally { setSalvando(false); }
  };
  const nova = busca && !busca.encontrado;
  const podeProcurar = digits(f.cpf).length === 11 || /\S+@\S+\.\S+/.test(f.email);

  return (
    <Modal open={open} size="md" title="Adicionar à equipe" dismissable={!salvando} onClose={onClose}
      description="Procure pelo CPF ou e-mail. Se a pessoa já tem cadastro no Check-in (por exemplo, como professor), ela só ganha o papel: nada de cadastro duplicado."
      footer={(
        <>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={salvando} onClick={onClose}>Cancelar</Button>
          {busca ? <Button variant="primary" icon="check" loading={salvando} disabled={!f.papeis.length} onClick={salvar}>{busca.encontrado ? 'Adicionar papel' : 'Cadastrar e adicionar'}</Button>
            : <Button variant="primary" icon="search" loading={procurando} disabled={!podeProcurar} onClick={procurar}>Procurar</Button>}
        </>
      )}>
      <div className="ck-mc-form">
        <div className="ck-mc-grid ck-mc-grid--2">
          <TextField label="CPF" inputMode="numeric" value={maskCpf(f.cpf)} onChange={set('cpf')} placeholder="000.000.000-00" />
          <TextField label="E-mail" type="email" value={f.email} onChange={set('email')} placeholder="nome@email.com" />
        </div>
        {erro ? <Callout tone="danger">{erro}</Callout> : null}
        {busca?.encontrado ? (
          <Callout tone="success" title={`Já cadastrada: ${titleCase(busca.usuario.nome)}`}>
            {`${busca.usuario.email}. ${busca.papeis_na_academia?.length ? `Nesta academia já é: ${busca.papeis_na_academia.map((p) => PAPEIS[p] || (p === 1 ? 'Aluno' : `Papel ${p}`)).join(', ')}.` : 'Ainda não participa desta academia.'}`}
          </Callout>
        ) : null}
        {nova ? (
          <>
            <Callout tone="info">Pessoa nova no Check-in. Complete o cadastro.</Callout>
            <div className="ck-mc-grid ck-mc-grid--2">
              <TextField label="Nome completo" value={f.nome} onChange={set('nome')} />
              <TextField label="Telefone" inputMode="tel" value={maskPhone(f.telefone)} onChange={set('telefone')} />
              <TextField label="Senha inicial" type="password" autoComplete="new-password" value={f.senha} onChange={set('senha')} hint="Mínimo de 6 caracteres. Peça para trocar no primeiro acesso." />
            </div>
          </>
        ) : null}
        {busca ? <PapeisCampo gerenciaveis={gerenciaveis} value={f.papeis} onChange={(papeis) => setF({ ...f, papeis })} /> : null}
      </div>
    </Modal>
  );
}

function EditarModal({ membro, dados, minhas, onClose, onDone }) {
  const gerenciaveis = dados.papeis_gerenciaveis;
  const [papeis, setPapeis] = useState(() => membro.papeis.filter((p) => gerenciaveis.includes(p)));
  const [marcadas, setMarcadas] = useState(() => membro.permissoes);
  const [salvando, setSalvando] = useState(false);
  const outrosPapeis = membro.papeis.filter((p) => !gerenciaveis.includes(p));
  const padrao = useMemo(() => [...new Set([...papeis, ...outrosPapeis].flatMap((p) => dados.padrao[p] || []))], [papeis]);
  const modulos = useMemo(() => dados.catalogo.reduce((acc, p) => ({ ...acc, [p.modulo]: [...(acc[p.modulo] || []), p] }), {}), [dados.catalogo]);

  // Ao trocar papéis, as permissões acompanham o padrão (os ajustes manuais continuam valendo)
  useEffect(() => {
    const ajustesC = membro.ajustes?.conceder || [];
    const ajustesR = membro.ajustes?.retirar || [];
    setMarcadas([...new Set([...padrao.filter((c) => !ajustesR.includes(c)), ...ajustesC])]);
  }, [padrao]);

  const salvar = async () => {
    setSalvando(true);
    try {
      const naoSensiveis = dados.catalogo.filter((p) => !p.sensivel).map((p) => p.codigo);
      const conceder = marcadas.filter((c) => naoSensiveis.includes(c) && !padrao.includes(c));
      const retirar = padrao.filter((c) => naoSensiveis.includes(c) && !marcadas.includes(c));
      const r = await equipeService.atualizar(membro.id, { papeis, conceder, retirar });
      showSuccess(r.message);
      onDone(r);
    } catch (e) { showError(e.message); } finally { setSalvando(false); }
  };

  return (
    <Modal open size="lg" title={titleCase(membro.nome)} description={membro.email} dismissable={!salvando} onClose={onClose}
      footer={(
        <>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={salvando} onClick={onClose}>Cancelar</Button>
          <Button variant="primary" icon="save" loading={salvando} disabled={!papeis.length && !outrosPapeis.length} onClick={salvar}>Salvar</Button>
        </>
      )}>
      <div className="ck-mc-form">
        <PapeisCampo gerenciaveis={gerenciaveis} value={papeis} onChange={setPapeis} />
        {outrosPapeis.includes(2) ? <p className="ck-mc-hint">Também é professor: isso é gerenciado na tela Professores.</p> : null}
        <span className="ck-field__label">Permissões</span>
        <p className="ck-mc-hint">Marcadas pelo papel automaticamente. Você pode conceder ou retirar as que também tem.</p>
        {Object.entries(modulos).map(([modulo, itens]) => (
          <fieldset key={modulo} className="ck-ct-roles">
            <legend className="ck-field__label">{modulo}</legend>
            {itens.map((p) => {
              const on = marcadas.includes(p.codigo);
              const travada = p.sensivel || !minhas.includes(p.codigo);
              const origem = p.sensivel ? 'Só o suporte' : !minhas.includes(p.codigo) ? 'Você não tem' : padrao.includes(p.codigo) ? (on ? 'Do papel' : 'Retirada') : (on ? 'Concedida' : null);
              return <Opcao key={p.codigo} on={on} disabled={travada} titulo={p.nome} descricao={p.descricao} aviso={origem}
                onToggle={() => setMarcadas(on ? marcadas.filter((x) => x !== p.codigo) : [...marcadas, p.codigo])} />;
            })}
          </fieldset>
        ))}
      </div>
    </Modal>
  );
}

export default function EquipeScreen() {
  const [dados, setDados] = useState(null);
  const [erro, setErro] = useState('');
  const [minhas, setMinhas] = useState([]);
  const [adicionar, setAdicionar] = useState(false);
  const [editando, setEditando] = useState(null);
  const [removendo, setRemovendo] = useState(null);
  const [ocupado, setOcupado] = useState(false);

  const carregar = useCallback(async () => {
    setErro('');
    try { setDados(await equipeService.listar()); } catch (e) { setErro(e.message); }
  }, []);
  useEffect(() => { carregar(); authService.getPermissoes().then(setMinhas); }, [carregar]);

  const remover = async () => {
    setOcupado(true);
    try { const r = await equipeService.remover(removendo.id); showSuccess(r.message); setDados(r); setRemovendo(null); } catch (e) { showError(e.message); } finally { setOcupado(false); }
  };

  const l = dados?.limite;
  const lotado = l && l.max !== null && l.usados >= l.max;

  return (
    <LayoutBase title="Equipe e permissões" pageHeader={false}>
      <div className="ck-mc">
        <PageHeader title="Equipe e permissões" description="Quem acessa o painel da academia e o que cada pessoa pode fazer."
          actions={<Button variant="primary" icon="user-plus" disabled={!dados} onClick={() => setAdicionar(true)}>Adicionar pessoa</Button>} />
        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
        {l ? (
          <Callout tone={lotado ? 'warning' : 'info'} title={l.max === null ? `${l.usados} ${l.usados === 1 ? 'pessoa' : 'pessoas'} na equipe` : `${l.usados} de ${l.max} ${l.max === 1 ? 'pessoa' : 'pessoas'} do plano`}>
            {lotado ? 'O plano está completo. Para adicionar mais pessoas, troque de plano em Minha conta → Plano e assinatura. Quem já é da equipe pode ganhar outros papéis.' : 'Todos os papéis da equipe contam: admin, professor, financeiro e comercial.'}
          </Callout>
        ) : null}
        {!dados && !erro ? <p className="ck-mc-hint" role="status">Carregando equipe…</p> : null}
        {dados ? (
          <Panel title="Equipe">
            <ul className="ck-cj-rows">
              {dados.membros.map((m) => (
                <li key={m.id}>
                  <span className="ck-cj-rows__main">
                    <strong>{titleCase(m.nome)}{m.voce ? ' (você)' : ''}</strong>
                    <small>{m.email}</small>
                  </span>
                  {m.admin_principal ? <Badge tone="success" icon="key">Admin principal</Badge> : null}
                  {m.papeis.map((p) => <Badge key={p} tone="neutral">{PAPEIS[p] || `Papel ${p}`}</Badge>)}
                  {m.gerenciavel ? (
                    <>
                      <IconButton icon="pencil" size="sm" label={`Editar ${m.nome}`} onClick={() => setEditando(m)} />
                      <IconButton icon="trash" size="sm" label={`Remover ${m.nome} da equipe`} onClick={() => setRemovendo(m)} />
                    </>
                  ) : <small className="ck-mc-hint">{m.admin_principal ? 'Alterado só pelo suporte' : 'Seu acesso'}</small>}
                </li>
              ))}
            </ul>
          </Panel>
        ) : null}
      </div>

      {dados ? <AdicionarModal open={adicionar} gerenciaveis={dados.papeis_gerenciaveis} onClose={() => setAdicionar(false)} onDone={(r) => { setDados(r); setAdicionar(false); }} /> : null}
      {editando ? <EditarModal key={editando.id} membro={editando} dados={dados} minhas={minhas} onClose={() => setEditando(null)} onDone={(r) => { setDados(r); setEditando(null); }} /> : null}
      <Modal open={!!removendo} size="sm" title={`Remover ${removendo ? titleCase(removendo.nome) : ''} da equipe?`} dismissable={!ocupado} onClose={() => setRemovendo(null)}
        description="A pessoa perde o acesso ao painel desta academia. Se também for professor ou aluno, isso continua."
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={ocupado} onClick={() => setRemovendo(null)}>Cancelar</Button>
            <Button variant="danger" icon="trash" loading={ocupado} onClick={remover}>Remover</Button>
          </>
        )} />
    </LayoutBase>
  );
}
