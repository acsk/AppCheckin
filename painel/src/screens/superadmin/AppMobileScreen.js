import React, { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, PageHeader, Switch, TextField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import { authService } from '../../services/authService';
import appVersaoService from '../../services/appVersaoService';
import AtualizacaoCadastralPanel from './AtualizacaoCadastralPanel';
import { showError, showSuccess } from '../../utils/toast';

// Super Admin → App mobile: versão publicada, se a versão nova bloqueia o app ou só avisa,
// ranking da Home e as atualizações cadastrais pedidas ao usuário.
const fdata = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—');

export default function AppMobileScreen() {
  const router = useRouter();
  const [ok, setOk] = useState(false);
  const [versao, setVersao] = useState(null);
  const [erro, setErro] = useState('');
  const [limiteRanking, setLimiteRanking] = useState('15');
  const [limiteSalvo, setLimiteSalvo] = useState(null);
  const [salvandoRanking, setSalvandoRanking] = useState(false);
  const [salvando, setSalvando] = useState(false);

  useEffect(() => {
    authService.getCurrentUser().then((u) => {
      if (authService.getEffectivePapelId(u) !== 4) { showError('Acesso negado. Apenas Super Admin pode acessar esta página.'); router.replace('/'); return; }
      setOk(true);
    });
  }, []);

  const carregar = useCallback(async () => {
    setErro('');
    try {
      const [mobile, ranking] = await Promise.all([appVersaoService.buscar(), appVersaoService.buscarRankingCheckins()]);
      setVersao(mobile);
      setLimiteRanking(String(ranking.limite));
      setLimiteSalvo(ranking.limite);
    } catch (e) { setErro(e.message); }
  }, []);
  useEffect(() => { if (ok) carregar(); }, [ok, carregar]);

  const alterar = async (obrigatoria) => {
    setSalvando(true);
    try {
      setVersao(await appVersaoService.definirObrigatoria(obrigatoria));
      showSuccess(obrigatoria ? 'Atualização obrigatória ativada' : 'Atualização agora é opcional');
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvando(false);
    }
  };

  const limiteValido = Number.isInteger(Number(limiteRanking)) && Number(limiteRanking) >= 1 && Number(limiteRanking) <= 100;
  const salvarRanking = async (event) => {
    event.preventDefault();
    if (!limiteValido || salvandoRanking) return;
    setSalvandoRanking(true);
    try {
      const ranking = await appVersaoService.definirLimiteRankingCheckins(Number(limiteRanking));
      setLimiteRanking(String(ranking.limite));
      setLimiteSalvo(ranking.limite);
      showSuccess('Limite do ranking atualizado');
    } catch (e) {
      showError(e.message);
    } finally {
      setSalvandoRanking(false);
    }
  };

  if (!ok) return <LayoutBase title="App mobile" pageHeader={false}><p className="ck-mc-hint" role="status">Carregando…</p></LayoutBase>;

  const android = versao?.android;

  return (
    <LayoutBase title="App mobile" pageHeader={false}>
      <div className="ck-mc">
        <PageHeader title="App mobile" description="Configurações do app do aluno, atualizações (de versão e cadastral) e versões publicadas." />

        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}

        <Panel title="Atualização do app" description="Vale para o app na web e para o APK Android.">
          {!versao ? <p className="ck-mc-hint" role="status">Carregando…</p> : (
            <div className="ck-mc-form">
              <Switch
                label="Atualização obrigatória"
                description={versao.obrigatoria
                  ? 'Quem está numa versão anterior fica bloqueado até atualizar. Na web o app recarrega sozinho.'
                  : 'Quem está numa versão anterior vê um aviso de nova versão e pode continuar usando.'}
                checked={!!versao.obrigatoria}
                disabled={salvando}
                onChange={alterar}
              />
              {versao.obrigatoria
                ? <Callout tone="warning" title="Use para correções urgentes">Bloquear força todo mundo a atualizar na hora, inclusive quem está no meio de um check-in.</Callout>
                : null}
            </div>
          )}
        </Panel>

        <AtualizacaoCadastralPanel />

        <Panel title="Ranking de check-ins" description="Limite de pessoas exibidas na lista do ranking na Home, em todas as academias.">
          {limiteSalvo === null ? <p className="ck-mc-hint" role="status">Carregando…</p> : (
            <form className="ck-mc-form" onSubmit={salvarRanking}>
              <TextField
                label="Pessoas no ranking da Home"
                type="number"
                min={1}
                max={100}
                step={1}
                required
                value={limiteRanking}
                disabled={salvandoRanking}
                onChange={(event) => setLimiteRanking(event.target.value)}
                hint="De 1 a 100 pessoas. O pódio continua mostrando os três campeões."
              />
              <div><Button type="submit" icon="save" loading={salvandoRanking} disabled={!limiteValido || Number(limiteRanking) === limiteSalvo}>Salvar limite</Button></div>
            </form>
          )}
        </Panel>

        <Panel title="Versões publicadas">
          {!versao ? <p className="ck-mc-hint" role="status">Carregando…</p> : (
            <dl className="ck-mc-dl">
              <div><dt>App web</dt><dd>{versao.version}</dd></div>
              <div><dt>Publicada em</dt><dd>{fdata(versao.release_date)}</dd></div>
              <div><dt>APK Android</dt><dd>{android ? android.version : 'Não configurado'}</dd></div>
              {android ? <div><dt>Link do APK</dt><dd><a href={android.apk_url} target="_blank" rel="noreferrer">{android.apk_url}</a></dd></div> : null}
            </dl>
          )}
          {versao && !android ? <p className="ck-mc-hint">A versão e o link do APK ficam no .env da API (APP_ANDROID_VERSION e APP_ANDROID_APK_URL).</p> : null}
        </Panel>
      </div>
    </LayoutBase>
  );
}
