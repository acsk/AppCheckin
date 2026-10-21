import React, { useCallback, useEffect, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, IconButton, Modal, PageHeader, TextField } from '../../components/ck';
import { Panel, titleCase } from '../../components/ck/pessoas';
import prospeccaoService from '../../services/prospeccaoService';
import { erroApi } from '../../utils/prospeccao';
import { showError, showSuccess } from '../../utils/toast';
import { limparContextoProspeccao, useProspeccaoContexto } from './useProspeccao';

export default function ConfiguracoesProspeccaoScreen() {
  const { ctx } = useProspeccaoContexto();
  const [equipe, setEquipe] = useState(null);
  const [score, setScore] = useState(null);
  const [erro, setErro] = useState('');
  const [novo, setNovo] = useState(null);
  const [salvandoScore, setSalvandoScore] = useState(false);

  const carregar = useCallback(async () => {
    setErro('');
    try {
      const [e, s] = await Promise.all([prospeccaoService.equipe(), prospeccaoService.scoreConfig()]);
      setEquipe(e); setScore(s);
    } catch (e) { setErro(erroApi(e, 'Não foi possível carregar as configurações.')); }
  }, []);
  useEffect(() => { carregar(); }, [carregar]);

  if (ctx && !ctx.admin) return <LayoutBase title="Equipe e score"><Callout tone="warning">Apenas administradores acessam esta tela.</Callout></LayoutBase>;

  const remover = async (u) => {
    try { setEquipe(await prospeccaoService.removerEquipe(u.id)); limparContextoProspeccao(); showSuccess('Papel Comercial removido'); } catch (e) { showError(erroApi(e, 'Não foi possível remover.')); }
  };
  const setPeso = (c, v) => setScore((s) => ({ ...s, config: { ...s.config, pesos: { ...s.config.pesos, [c]: v === '' ? '' : Math.max(0, Math.min(100, Number(v) || 0)) } } }));
  const toggleMotivo = (m) => setScore((s) => {
    const atual = s.config.motivos_reversiveis;
    return { ...s, config: { ...s.config, motivos_reversiveis: atual.includes(m) ? atual.filter((x) => x !== m) : [...atual, m] } };
  });
  const salvarScore = async (recalcular) => {
    setSalvandoScore(true);
    try {
      const pesos = Object.fromEntries(Object.entries(score.config.pesos).map(([k, v]) => [k, Number(v) || 0]));
      await prospeccaoService.salvarScoreConfig({ pesos, motivos_reversiveis: score.config.motivos_reversiveis });
      if (recalcular) { const r = await prospeccaoService.recalcularTodos(); showSuccess(`Configuração salva e ${r.recalculadas} oportunidades recalculadas`); }
      else showSuccess('Configuração salva');
    } catch (e) { showError(erroApi(e, 'Não foi possível salvar.')); } finally { setSalvandoScore(false); }
  };
  const total = score ? Object.values(score.config.pesos).reduce((a, v) => a + (Number(v) || 0), 0) : 0;

  return <LayoutBase title="Equipe e score" pageHeader={false}>
    <div className="ck-pr">
      <PageHeader title="Equipe e score" description="Quem acessa o módulo Prospecção e como o potencial de recuperação é calculado." />
      {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
      <div className="ck-dash__row ck-dash__row--even">
        <Panel title="Equipe comercial" description="O papel Comercial acessa só a Prospecção: não vê o restante do painel nem valores financeiros, e não faz matrículas."
          actions={<Button size="sm" icon="user-plus" onClick={() => setNovo({ email: '', nome: '', senha: '' })}>Adicionar</Button>}>
          {equipe?.comerciais?.length ? <ul className="ck-pr-list">{equipe.comerciais.map((u) => <li key={u.id}>
            <span className="ck-pr-list__main"><strong>{titleCase(u.nome)}</strong><small>{u.email}{u.papeis.includes(3) ? ' · também administrador' : ''}</small></span>
            <IconButton icon="trash" size="sm" label={`Remover ${u.nome} da equipe comercial`} onClick={() => remover(u)} />
          </li>)}</ul> : <p className="ck-pr-hint">Ninguém com o papel Comercial ainda. Administradores já têm acesso ao módulo.</p>}
        </Panel>

        <Panel title="Score de recuperação" description="Pesos de 0 a 100. Critério sem dado do aluno não conta no cálculo.">
          {score ? <div className="ck-pr-form">
            <div className="ck-pr-pesos">{Object.entries(score.criterios).map(([c, rotulo]) => <TextField key={c} label={rotulo} type="number" min={0} max={100}
              value={score.config.pesos[c]} onChange={(e) => setPeso(c, e.target.value)} />)}</div>
            <p className="ck-pr-hint">Soma dos pesos: {total} (o score é sempre reescalado para 0–100).</p>
            <fieldset className="ck-pr-motivos">
              <legend>Motivos de cancelamento reversíveis</legend>
              {Object.entries(score.motivos).map(([m, rotulo]) => <label key={m} className="ck-pr-check"><input type="checkbox" checked={score.config.motivos_reversiveis.includes(m)} onChange={() => toggleMotivo(m)} /> {rotulo}</label>)}
            </fieldset>
            <div className="ck-pr-row">
              <Button onClick={() => setScore((s) => ({ ...s, config: s.padrao }))}>Restaurar padrão</Button>
              <Button onClick={() => salvarScore(false)} loading={salvandoScore}>Salvar</Button>
              <Button variant="primary" onClick={() => salvarScore(true)} loading={salvandoScore} disabled={total <= 0}>Salvar e recalcular</Button>
            </div>
          </div> : null}
        </Panel>
      </div>
    </div>
    <NovoComercialModal dados={novo} onClose={() => setNovo(null)} onSalvo={(r) => { setEquipe(r); setNovo(null); limparContextoProspeccao(); showSuccess(r.message); }} />
  </LayoutBase>;
}

function NovoComercialModal({ dados, onClose, onSalvo }) {
  const [f, setF] = useState({});
  const [erro, setErro] = useState('');
  const [precisaCriar, setPrecisaCriar] = useState(false);
  const [salvando, setSalvando] = useState(false);
  useEffect(() => { if (dados) { setF(dados); setErro(''); setPrecisaCriar(false); } }, [dados]);
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const salvar = async () => {
    setSalvando(true); setErro('');
    try { onSalvo(await prospeccaoService.adicionarEquipe(f)); }
    catch (e) {
      const msg = erroApi(e, 'Não foi possível adicionar.');
      if (/Usuário novo/.test(msg)) setPrecisaCriar(true);
      setErro(msg);
    } finally { setSalvando(false); }
  };
  return <Modal open={!!dados} onClose={onClose} title="Adicionar à equipe comercial" size="sm"
    description="Se o e-mail já tem cadastro no Check-in, a pessoa recebe o papel Comercial. Se não tem, um usuário novo é criado."
    footer={<><Button onClick={onClose}>Cancelar</Button><Button variant="primary" loading={salvando} onClick={salvar}>Adicionar</Button></>}>
    <div className="ck-pr-form">
      {erro ? <Callout tone={precisaCriar ? 'info' : 'danger'}>{precisaCriar ? 'E-mail sem cadastro: informe nome e senha para criar o usuário.' : erro}</Callout> : null}
      <TextField label="E-mail" type="email" value={f.email || ''} onChange={(e) => set('email', e.target.value)} />
      {precisaCriar ? <>
        <TextField label="Nome" value={f.nome || ''} onChange={(e) => set('nome', e.target.value)} />
        <TextField label="Senha inicial" type="password" value={f.senha || ''} onChange={(e) => set('senha', e.target.value)} hint="Mínimo de 6 caracteres. A pessoa pode trocar depois." />
      </> : null}
    </div>
  </Modal>;
}
