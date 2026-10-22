import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, Modal, PageHeader, TextField } from '../../components/ck';
import { Panel, maskCpf, titleCase } from '../../components/ck/pessoas';
import { authService } from '../../services/authService';
import superAdminService from '../../services/superAdminService';
import { showError, showSuccess } from '../../utils/toast';

// Super Admin: unifica dois cadastros da mesma pessoa (ex.: admin e professor com e-mails diferentes).
// Regras na API (UnificacaoUsuarioService): prévia obrigatória, bloqueio se os dois têm histórico de
// aluno, cadastro que sai fica desativado e anonimizado.

const PAPEIS = { 1: 'Aluno', 2: 'Professor', 3: 'Admin', 4: 'Super admin', 5: 'Comercial', 6: 'Financeiro' };
const FICHA_ALUNO = {
  nenhuma: 'Quem sai não tem ficha de aluno.',
  mover: 'A ficha de aluno de quem sai passa para quem fica.',
  descartar_ficha_vazia: 'A ficha de aluno de quem sai está vazia e será descartada.',
  substituir_ficha_vazia_do_destino: 'A ficha vazia de quem fica é trocada pela de quem sai (que tem histórico).',
  bloqueada: 'Os dois têm histórico de aluno.',
};
const FICHA_PROFESSOR = {
  nenhuma: 'Quem sai não é professor.',
  mover: 'O cadastro de professor passa para quem fica.',
  mesclar_turmas_e_academias: 'Turmas e academias do professor passam para o cadastro de professor de quem fica.',
};
const erroApi = (e, fallback) => e?.response?.data?.error || e?.response?.data?.message || fallback;

function Pessoa({ titulo, p, tone }) {
  return (
    <Panel title={titulo} actions={<Badge tone={tone}>{`#${p.id}`}</Badge>}>
      <dl className="ck-mc-dl">
        <div><dt>Nome</dt><dd>{titleCase(p.nome)}</dd></div>
        <div><dt>E-mail</dt><dd>{p.email}</dd></div>
        <div><dt>CPF</dt><dd>{p.cpf ? maskCpf(p.cpf) : '—'}</dd></div>
        <div><dt>Academias</dt><dd>{p.academias.length ? p.academias.map((a) => `${a.nome} (${String(a.papeis).split(',').map((x) => PAPEIS[x] || x).join(', ')})`).join('; ') : '—'}</dd></div>
      </dl>
    </Panel>
  );
}

export default function UnificarUsuariosScreen() {
  const router = useRouter();
  const [origem, setOrigem] = useState('');
  const [destino, setDestino] = useState('');
  const [previa, setPrevia] = useState(null);
  const [erro, setErro] = useState('');
  const [carregando, setCarregando] = useState(false);
  const [confirmar, setConfirmar] = useState(false);
  const [unificando, setUnificando] = useState(false);

  useEffect(() => {
    authService.getCurrentUser().then((u) => { if (authService.getEffectivePapelId(u) !== 4) router.replace('/'); });
  }, []);

  const verPrevia = async () => {
    setCarregando(true); setErro(''); setPrevia(null);
    try { setPrevia(await superAdminService.previaUnificacao(origem.trim(), destino.trim())); } catch (e) { setErro(erroApi(e, 'Não foi possível gerar a prévia')); } finally { setCarregando(false); }
  };
  const unificar = async () => {
    setUnificando(true);
    try {
      const r = await superAdminService.unificarUsuarios(origem.trim(), destino.trim());
      showSuccess(r.message);
      setConfirmar(false); setPrevia(null); setOrigem(''); setDestino('');
    } catch (e) { showError(erroApi(e, 'Não foi possível unificar')); } finally { setUnificando(false); }
  };
  const movidos = previa ? Object.entries(previa.registros_movidos) : [];

  return (
    <LayoutBase title="Unificar cadastros" pageHeader={false}>
      <div className="ck-mc">
        <PageHeader title="Unificar cadastros" description="Junte dois cadastros da mesma pessoa. Tudo de quem sai passa para quem fica; quem sai é desativado." />
        <Panel title="Cadastros">
          <div className="ck-mc-form">
            <div className="ck-mc-grid ck-mc-grid--2">
              <TextField label="Cadastro que sai" value={origem} onChange={(e) => { setOrigem(e.target.value); setPrevia(null); }} hint="Id, CPF ou e-mail" />
              <TextField label="Cadastro que fica" value={destino} onChange={(e) => { setDestino(e.target.value); setPrevia(null); }} hint="Id, CPF ou e-mail. Mantém e-mail, senha e CPF dele." />
            </div>
            <div className="ck-mc-actions">
              <Button variant="primary" icon="search" loading={carregando} disabled={!origem.trim() || !destino.trim()} onClick={verPrevia}>Ver prévia</Button>
            </div>
            {erro ? <Callout tone="danger">{erro}</Callout> : null}
          </div>
        </Panel>
        {previa ? (
          <>
            <div className="ck-mc-cols">
              <Pessoa titulo="Sai" p={previa.origem} tone="warning" />
              <Pessoa titulo="Fica" p={previa.destino} tone="success" />
            </div>
            <Panel title="O que acontece">
              <div className="ck-mc-form">
                {previa.bloqueio ? <Callout tone="danger" title="Não é possível unificar">{previa.bloqueio}</Callout> : null}
                <ul className="ck-mc-tips">
                  <li>{FICHA_ALUNO[previa.ficha_aluno]}</li>
                  <li>{FICHA_PROFESSOR[previa.ficha_professor]}</li>
                  <li>{previa.papeis_movidos.length ? `Papéis que passam: ${previa.papeis_movidos.map((p) => `${PAPEIS[p.papel_id] || p.papel_id} em ${p.academia}`).join('; ')}.` : 'Quem sai não tem papéis ativos.'}</li>
                  <li>{movidos.length ? `Registros atualizados: ${movidos.map(([k, n]) => `${k} (${n})`).join(', ')}.` : 'Nenhum outro registro aponta para quem sai.'}</li>
                  <li>Quem sai fica desativado, sem e-mail e sem CPF (ficam livres).</li>
                </ul>
                <div className="ck-mc-actions">
                  <Button variant="danger" icon="users" disabled={!previa.pode_unificar} onClick={() => setConfirmar(true)}>Unificar cadastros</Button>
                </div>
              </div>
            </Panel>
          </>
        ) : null}
      </div>
      <Modal open={confirmar} size="sm" title="Unificar os cadastros?" dismissable={!unificando} onClose={() => setConfirmar(false)}
        description={previa ? `#${previa.origem.id} (${previa.origem.email}) será desativado e tudo passa para #${previa.destino.id} (${previa.destino.email}). Não dá para desfazer pelo painel.` : ''}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={unificando} onClick={() => setConfirmar(false)}>Cancelar</Button>
            <Button variant="danger" icon="check" loading={unificando} onClick={unificar}>Unificar</Button>
          </>
        )} />
    </LayoutBase>
  );
}
