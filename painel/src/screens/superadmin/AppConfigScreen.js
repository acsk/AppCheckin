import React, { useCallback, useEffect, useMemo, useState } from 'react';
import AppMobileLayout from './AppMobileLayout';
import AppConfigExcecoesModal from './AppConfigExcecoesModal';
import { ActionMenu, Badge, Button, Callout, Icon, Modal, PageHeader, SegmentedControl, SelectField, Switch, Tabs, TextAreaField, TextField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import { ConfigSection, ConfigValueInput, ExcecoesButton, ExcecoesNote } from './AppConfigPresentation';
import appConfigService from '../../services/appConfigService';
import appVersaoService from '../../services/appVersaoService';
import { superAdminService } from '../../services/superAdminService';
import { showError, showSuccess } from '../../utils/toast';

// Super Admin → App Mobile: editor compartilhado de configurações e versões do app,
// com rascunho e publicação conjunta (com histórico e rollback). O catálogo vem da API
// (App\Support\AppConfigCatalogo); aqui só se escolhem valores. Estilos: ck-app-config.css.

const fdata = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—');
const fdataHora = (v) => (v ? `${fdata(v)} ${String(v).slice(11, 16)}` : '—');
const ORIGEM = { padrao: 'Padrão', global: 'Geral', academia: 'Academia', bloqueio: 'Bloqueio emergencial', versao: 'Versão do app', papel: 'Papel', rollout: 'Rollout' };
const ACAO = { alterar: 'Rascunho', publicar: 'Publicação', descartar: 'Rascunho descartado', bloquear: 'Bloqueio emergencial', desbloquear: 'Fim do bloqueio', rollback: 'Rollback', imediata: 'Publicação imediata' };

function exibir(def, valor) {
  if (valor === null || valor === undefined) return 'herda';
  if (def?.tipo === 'booleano') return valor ? 'Ligado' : 'Desligado';
  if (valor === '') return '(vazio)';
  return String(valor);
}

/** Campo de um valor do catálogo (texto, número, versão, url, booleano) que grava ao sair do campo. */
function ValorField({ def, valor, onSalvar, disabled, label }) {
  const [texto, setTexto] = useState(valor ?? '');
  const [erro, setErro] = useState('');
  useEffect(() => { setTexto(valor ?? ''); setErro(''); }, [valor]);

  if (def.tipo === 'booleano') {
    return <Switch label={label || def.nome} description={def.descricao} checked={!!valor} disabled={disabled} onChange={(v) => onSalvar(v).catch((e) => showError(e.message))} />;
  }
  const commit = async () => {
    if (String(texto) === String(valor ?? '')) return;
    try { await onSalvar(def.tipo === 'inteiro' ? (texto === '' ? null : Number(texto)) : texto); setErro(''); } catch (e) { setErro(e.message); }
  };
  const Comp = def.tipo === 'texto' && (def.max || 0) > 120 ? TextAreaField : TextField;
  return (
    <Comp label={label || def.nome} value={String(texto)} disabled={disabled} error={erro || null}
      hint={def.tipo === 'inteiro' ? `${def.descricao} (${def.min} a ${def.max})` : def.descricao}
      inputMode={def.tipo === 'inteiro' ? 'numeric' : undefined} placeholder={def.padrao !== '' ? `Padrão: ${def.padrao}` : undefined}
      onChange={(e) => setTexto(def.tipo === 'inteiro' ? e.target.value.replace(/\D/g, '').slice(0, 6) : e.target.value)}
      onBlur={commit} onKeyDown={(e) => { if (e.key === 'Enter' && Comp === TextField) e.currentTarget.blur(); }} />
  );
}

function FuncionalidadesTab({ estado, defs, academias, busca, setBusca, onDefinir, onExcecoes, onBloqueio }) {
  const [filtro, setFiltro] = useState('todas');
  const funcs = defs.filter((d) => d.grupo === 'funcionalidade' && d.chave.startsWith('funcionalidade.'));
  const normalizar = (v) => v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const termo = normalizar(busca.trim());
  const pendente = (d) => estado.pendencias.some((p) => p.chave === d.chave || p.chave === `rollout.${d.item}`);
  const comExcecao = (d) => Object.values(estado.rascunho.tenants || {}).some((valores) => valores[d.chave] !== undefined || valores[`rollout.${d.item}`] !== undefined);
  const desligadaOuParcial = (d) => estado.bloqueios.includes(`bloqueio.${d.item}`)
    || !(estado.rascunho.global[d.chave] ?? d.padrao)
    || (estado.rascunho.global[`rollout.${d.item}`] ?? 100) < 100;
  const filtros = { todas: () => true, alteradas: pendente, excecoes: comExcecao, desligadas: desligadaOuParcial };
  const visiveis = funcs.filter((d) => filtros[filtro](d)
    && (!termo || normalizar(`${d.nome} ${d.descricao} ${d.modulo} ${d.chave}`).includes(termo)));
  const modulos = [...new Set(visiveis.map((d) => d.modulo))];
  const salvar = (chave, valor) => onDefinir(chave, valor).catch((e) => showError(e.message));
  const copiar = async (chave) => {
    try { await navigator.clipboard.writeText(chave); showSuccess('Chave copiada'); }
    catch { showError('Não foi possível copiar a chave'); }
  };

  return (
    <>
      <div className="ck-control ck-cf-search">
        <Icon name="search" size={16} className="ck-control__icon" />
        <input type="search" className="ck-control__input" aria-label="Buscar funcionalidade" value={busca}
          onChange={(e) => setBusca(e.target.value)} placeholder="Buscar por nome, módulo, descrição ou chave" />
      </div>
      <SegmentedControl label="Mostrar funcionalidades" value={filtro} onChange={setFiltro} className="ck-cf-flt"
        options={[
          { value: 'todas', label: `Todas ${funcs.length}` },
          { value: 'alteradas', label: `Alteradas ${funcs.filter(pendente).length}` },
          { value: 'excecoes', label: `Com exceção ${funcs.filter(comExcecao).length}` },
          { value: 'desligadas', label: `Desligadas ou parciais ${funcs.filter(desligadaOuParcial).length}` },
        ]} />
      {modulos.map((m) => (
        <ConfigSection key={m} title={m}>
          <ul className="ck-pm-list">
            {visiveis.filter((d) => d.modulo === m).map((d) => {
              const bloqueada = estado.bloqueios.includes(`bloqueio.${d.item}`);
              const rolloutDef = defs.find((x) => x.chave === `rollout.${d.item}`);
              const rollout = estado.rascunho.global[`rollout.${d.item}`] ?? rolloutDef?.padrao ?? 100;
              const ligada = !!(estado.rascunho.global[d.chave] ?? d.padrao);
              const parcial = ligada && rollout < 100;
              return (
                <React.Fragment key={d.chave}>
                  <li className={`ck-pm-row${pendente(d) ? ' ck-cf-changed' : ''}${bloqueada ? ' ck-cf-killed' : ''}`}>
                    <div className="ck-pm-row__txt">
                      <span className="ck-pm-row__label">{d.nome}{pendente(d) ? <em className="ck-cf-dot">alterado</em> : null}{bloqueada ? <Badge tone="danger" icon="power" className="ck-cf-killb">Desligada em emergência</Badge> : null}</span>
                      <span className="ck-pm-row__desc">{d.descricao}</span>
                      <ExcecoesNote def={d} estado={estado} academias={academias} />
                      <code className="ck-pm-key">{d.item}</code>
                      {d.versao_minima?.android || d.versao_minima?.ios ? <span className="ck-pm-row__desc">
                        {[d.versao_minima.android && `Android ${d.versao_minima.android}+`, d.versao_minima.ios && `iOS ${d.versao_minima.ios}+`].filter(Boolean).join(' · ')}
                      </span> : null}
                      {bloqueada ? <span className="ck-cf-killnote">Desligada em todas as academias até religar, mesmo que o rascunho diga outra coisa.</span> : null}
                    </div>
                    <div className="ck-pm-row__ctl">
                      {bloqueada ? <Button size="sm" icon="refresh-cw" onClick={() => onBloqueio(d, false)}>Religar</Button> : <>
                        {parcial ? <Badge tone="warning">{`${rollout}%`}</Badge> : null}
                        <ExcecoesButton def={d} estado={estado} onClick={() => onExcecoes(d)} />
                        <Switch ariaLabel={`${d.nome}: ligada para todas`} checked={ligada} onChange={(v) => salvar(d.chave, v)} />
                      </>}
                      <ActionMenu label={`Mais ações para ${d.nome}`} items={[
                        ligada && !parcial && rolloutDef ? { label: 'Liberar aos poucos', icon: 'users', onClick: () => salvar(rolloutDef.chave, 50) } : null,
                        parcial && rolloutDef ? { label: 'Liberar para todos', icon: 'check', onClick: () => salvar(rolloutDef.chave, 100) } : null,
                        { label: 'Copiar chave', icon: 'copy', onClick: () => copiar(d.item) },
                        '-',
                        bloqueada
                          ? { label: 'Religar em todas', icon: 'refresh-cw', onClick: () => onBloqueio(d, false) }
                          : { label: 'Desligar agora em todas', icon: 'power', danger: true, onClick: () => onBloqueio(d, true) },
                      ].filter(Boolean)} />
                    </div>
                  </li>
                  {parcial && !bloqueada && rolloutDef ? <li className="ck-pm-row is-child">
                    <div className="ck-pm-row__txt">
                      <span className="ck-pm-row__label">Liberada para parte dos usuários</span>
                      <span className="ck-pm-row__desc">Cada usuário cai sempre no mesmo grupo. Suba aos poucos e acompanhe antes de liberar para todos.</span>
                    </div>
                    <div className="ck-pm-row__ctl">
                      <ConfigValueInput def={rolloutDef} valor={rollout} unit="%" onSalvar={(v) => onDefinir(rolloutDef.chave, v)} />
                    </div>
                  </li> : null}
                </React.Fragment>
              );
            })}
          </ul>
        </ConfigSection>
      ))}
      {!visiveis.length ? <div className="ck-card ck-pm-empty">
        <Icon name="search" size={24} /><strong>Nenhuma funcionalidade encontrada{busca ? ` para “${busca}”` : ''}.</strong>
        <p>Busque pelo nome, módulo, descrição ou chave, ou altere o filtro.</p>
        <Button size="sm" onClick={() => { setBusca(''); setFiltro('todas'); }}>Mostrar todas</Button>
      </div> : null}
    </>
  );
}

function ParametrosTab({ estado, defs, academias, onDefinir, onExcecoes }) {
  const itens = defs.filter((d) => d.grupo === 'parametro');
  return [...new Set(itens.map((d) => d.modulo))].map((modulo) => (
    <ConfigSection key={modulo} title={modulo}>
      <ul className="ck-pm-list">
        {itens.filter((d) => d.modulo === modulo).map((d) => {
          const valor = estado.rascunho.global[d.chave] ?? d.padrao;
          const pendente = estado.pendencias.some((p) => p.chave === d.chave);
          const texto = d.tipo === 'texto';
          const unidade = d.item === 'cache_ttl_segundos' ? 'segundos' : d.item === 'ranking_limite_home' ? 'pessoas' : null;
          return (
            <li key={d.chave} className={`ck-pm-row${pendente ? ' ck-cf-changed' : ''}${texto ? ' ck-cf-textrow' : ''}`}>
              <div className="ck-pm-row__txt">
                <span className="ck-pm-row__label">{d.nome}{pendente ? <em className="ck-cf-dot">alterado</em> : null}</span>
                <span className="ck-pm-row__desc">{d.descricao}</span>
                {d.tipo === 'inteiro' ? <span className="ck-pm-row__desc">{`De ${d.min} a ${d.max}.`}</span> : null}
                <ExcecoesNote def={d} estado={estado} academias={academias} />
                {texto ? <ConfigValueInput def={d} valor={valor} multiline onSalvar={(v) => onDefinir(d.chave, v)} /> : null}
                <code className="ck-pm-key">{d.item}</code>
              </div>
              <div className="ck-pm-row__ctl">
                {d.escopo === 'tenant' ? <ExcecoesButton def={d} estado={estado} onClick={() => onExcecoes(d)} /> : <Badge tone="outline">Vale para todas</Badge>}
                {d.tipo === 'booleano' ? <Switch ariaLabel={d.nome} checked={!!valor}
                  onChange={(v) => onDefinir(d.chave, v).catch((e) => showError(e.message))} />
                  : !texto ? <ConfigValueInput def={d} valor={valor} unit={unidade} onSalvar={(v) => onDefinir(d.chave, v)} /> : null}
              </div>
            </li>
          );
        })}
      </ul>
    </ConfigSection>
  ));
}

function ItensTab({ estado, defs, grupo, onDefinir, onExcecoes }) {
  const itens = defs.filter((d) => d.grupo === grupo);
  const modulos = [...new Set(itens.map((d) => d.modulo))];
  return modulos.map((m) => (
    <Panel key={m} title={m}>
      <div className="ck-mc-form">
        {itens.filter((d) => d.modulo === m).map((d) => {
          const n = Object.values(estado.rascunho.tenants || {}).filter((v) => v[d.chave] !== undefined).length;
          return (
            <div key={d.chave}>
              <ValorField def={d} valor={estado.rascunho.global[d.chave] ?? d.padrao} onSalvar={(v) => onDefinir(d.chave, v)} />
              <div className="ck-pf__chips">
                {estado.pendencias.some((p) => p.chave === d.chave) ? <Badge tone="warning">Não publicado</Badge> : null}
                {d.escopo === 'tenant'
                  ? <Button size="sm" variant="ghost" icon="building-2" onClick={() => onExcecoes(d)}>{n ? `Exceções: ${n} academia${n > 1 ? 's' : ''}` : 'Exceções por academia'}</Button>
                  : <Badge tone="outline">Vale para todas as academias</Badge>}
              </div>
            </div>
          );
        })}
      </div>
    </Panel>
  ));
}

function VersoesTab(props) {
  const [versao, setVersao] = useState(null);
  useEffect(() => { appVersaoService.buscar().then(setVersao).catch(() => {}); }, []);
  const webObrigatoria = props.estado.rascunho.global['versao.web_obrigatoria'] ?? true;
  return (
    <>
      <Panel title="App web publicado" description="A versão do app web é registrada pelo build (npm run web:build).">
        <dl className="ck-mc-dl">
          <div><dt>Versão</dt><dd>{versao?.version || '—'}</dd></div>
          <div><dt>Publicada em</dt><dd>{fdata(versao?.release_date)}</dd></div>
        </dl>
        {webObrigatoria ? <Callout tone="warning" title="Use para correções urgentes">Atualização obrigatória força todo mundo a atualizar na hora, inclusive quem está no meio de um check-in.</Callout> : null}
      </Panel>
      <ItensTab {...props} grupo="versao" />
    </>
  );
}

function EfetivaTab({ defs, academias }) {
  const [tenantId, setTenantId] = useState('');
  const [plataforma, setPlataforma] = useState('android');
  const [versao, setVersao] = useState('');
  const [dados, setDados] = useState(null);
  const [erro, setErro] = useState('');
  useEffect(() => {
    let ativa = true;
    setErro('');
    setDados(null);
    appConfigService.efetiva({ tenantId, plataforma, versao: versao.trim() })
      .then((r) => { if (ativa) setDados(r); })
      .catch((e) => { if (ativa) setErro(e.message); });
    return () => { ativa = false; };
  }, [tenantId, plataforma, versao]);
  const nomeDe = (grupo, item) => (defs.find((d) => d.chave === `${grupo}.${item}`) || {}).nome || item;
  const at = dados?.contrato?.atualizacao;
  const origem = (o) => <span className={`ck-cf-origin${o === 'academia' ? ' is-acad' : ''}`}>{ORIGEM[o] || o}</span>;
  const nomeAcademia = academias.find((a) => String(a.id) === tenantId)?.nome;

  return (
    <>
      <ConfigSection title="O que o app recebe" icon="layout-dashboard" description="A configuração publicada para uma academia, plataforma e versão, e de onde vem cada valor.">
        <div className="ck-cf-simf">
          <SelectField label="Academia" value={tenantId} onChange={(e) => setTenantId(e.target.value)}
            options={[{ value: '', label: 'Antes do login (só o geral)' }, ...academias.map((a) => ({ value: String(a.id), label: a.nome }))]} />
          <div className="ck-field">
            <span className="ck-field__label">Plataforma</span>
            <SegmentedControl label="Plataforma" value={plataforma} onChange={setPlataforma}
              options={[{ value: 'android', label: 'Android' }, { value: 'ios', label: 'iOS' }, { value: 'web', label: 'Web' }]} />
          </div>
          {plataforma !== 'web' ? <TextField label="Versão do app" value={versao} placeholder="ex.: 1.0.2" onChange={(e) => setVersao(e.target.value)} /> : null}
        </div>
      </ConfigSection>
      {erro ? <Callout tone="danger">{erro}</Callout> : null}
      {!dados && !erro ? <p className="ck-mc-hint" role="status">Consultando configuração publicada…</p> : null}
      {at ? <Callout tone={at.obrigatoria ? 'warning' : 'info'} title="Atualização">
        {plataforma === 'web'
          ? (at.obrigatoria ? 'Versão nova bloqueia o app web até recarregar.' : 'Versão nova só mostra um aviso.')
          : `${at.obrigatoria ? 'Bloqueado até atualizar.' : at.disponivel ? 'Vê o aviso de nova versão.' : 'Em dia.'} Mínima: ${at.versao_minima || '—'} · Publicada: ${at.versao_recomendada || '—'}`}
      </Callout> : null}
      {dados ? <ConfigSection title={tenantId ? `Resultado para ${nomeAcademia || 'a academia'}` : 'Resultado antes do login'} icon="check"
        description={`Versão publicada da configuração: ${dados.efetiva.versao}. O rollout parcial aparece como desligado porque depende do usuário.`}>
        <ul className="ck-pm-list">
          {Object.entries(dados.efetiva.funcionalidades).map(([k, f]) => (
            <li key={k} className="ck-pm-row">
              <div className="ck-pm-row__txt"><span className="ck-pm-row__label">{nomeDe('funcionalidade', k)}</span></div>
              <div className="ck-pm-row__ctl">{origem(f.origem)}<Badge tone={f.ativo ? 'success' : 'outline'} dot={f.ativo}>{f.ativo ? 'Ligada' : 'Desligada'}</Badge></div>
            </li>
          ))}
          {Object.entries(dados.efetiva.parametros).map(([k, p]) => (
            <li key={k} className="ck-pm-row">
              <div className="ck-pm-row__txt"><span className="ck-pm-row__label">{nomeDe('parametro', k)}</span></div>
              <div className="ck-pm-row__ctl">{origem(p.origem)}<strong className="ck-cf-val">{p.valor === '' ? '(vazio)' : String(p.valor)}</strong></div>
            </li>
          ))}
        </ul>
      </ConfigSection> : null}
    </>
  );
}

function HistoricoTab({ versaoAtual, onRestaurar }) {
  const [dados, setDados] = useState(null);
  const [erro, setErro] = useState('');
  useEffect(() => { appConfigService.historico().then(setDados).catch((e) => setErro(e.message)); }, [versaoAtual]);
  if (erro) return <Callout tone="danger">{erro}</Callout>;
  if (!dados) return <p className="ck-mc-hint" role="status">Carregando…</p>;
  return (
    <>
      <ConfigSection title="Publicações" icon="clock" description="Restaurar publica de novo o conteúdo daquela versão. O rascunho atual é substituído.">
        <ul className="ck-pm-list">
          {dados.publicacoes.map((p) => (
            <li key={p.versao} className="ck-pm-row ck-cf-hrow">
              <div className="ck-pm-row__txt">
                <span className="ck-pm-row__label">{`Versão ${p.versao}`}{p.nota ? `: ${p.nota}` : ''}</span>
                <span className="ck-pm-row__desc">{fdataHora(p.created_at)}{p.publicado_por ? `, por ${p.publicado_por}` : ''}</span>
              </div>
              <div className="ck-pm-row__ctl">
                {p.origem !== 'publicacao' ? <Badge tone="outline">{p.origem === 'rollback' ? `Rollback da ${p.rollback_de}` : p.origem === 'imediata' ? 'Imediata' : 'Migração'}</Badge> : null}
                {p.versao === versaoAtual ? <Badge tone="success" dot>Em uso</Badge>
                  : <Button size="sm" icon="refresh-cw" onClick={() => onRestaurar(p.versao)}>Restaurar</Button>}
              </div>
            </li>
          ))}
          {!dados.publicacoes.length ? <li className="ck-pm-none">Nenhuma publicação registrada.</li> : null}
        </ul>
      </ConfigSection>
      <ConfigSection title="Alterações recentes" icon="file-text">
        <ul className="ck-pm-list">
          {dados.auditoria.map((a) => (
            <li key={a.id} className="ck-pm-row ck-cf-hrow">
              <div className="ck-pm-row__txt">
                <span className="ck-pm-row__label">{ACAO[a.acao] || a.acao}{a.chave ? ` · ${a.chave}` : ''}{a.academia ? ` · ${a.academia}` : ''}</span>
                <span className="ck-pm-row__desc">
                  {a.acao === 'alterar' || a.chave ? `${exibir(null, a.valor_anterior)} → ${exibir(null, a.valor_novo)} · ` : ''}
                  {a.versao ? `versão ${a.versao} · ` : ''}{fdataHora(a.created_at)}{a.usuario ? ` · ${a.usuario}` : ''}{a.nota ? ` · ${a.nota}` : ''}
                </span>
              </div>
            </li>
          ))}
          {!dados.auditoria.length ? <li className="ck-pm-none">Nenhuma alteração registrada.</li> : null}
        </ul>
      </ConfigSection>
    </>
  );
}

function AppConfigEditor({ secao }) {
  const versoes = secao === 'versoes';
  const [estado, setEstado] = useState(null);
  const [academias, setAcademias] = useState([]);
  const [erro, setErro] = useState('');
  const [aba, setAba] = useState('funcionalidades');
  const [busca, setBusca] = useState('');
  const [excecoes, setExcecoes] = useState(null);
  const [publicar, setPublicar] = useState(false);
  const [nota, setNota] = useState('');
  const [bloqueio, setBloqueio] = useState(null); // { def, bloquear }
  const [restaurar, setRestaurar] = useState(null);
  const [salvando, setSalvando] = useState(false);

  const carregar = useCallback(async () => {
    setErro('');
    try { setEstado(await appConfigService.estado()); } catch (e) { setErro(e.message); }
  }, []);
  useEffect(() => {
    carregar();
    superAdminService.listarAcademias().then((r) => setAcademias((r.academias || []).map((a) => ({ id: a.id, nome: a.nome })))).catch(() => {});
  }, [carregar]);

  const defs = estado?.catalogo || [];
  const defPorChave = useMemo(() => Object.fromEntries(defs.map((d) => [d.chave, d])), [defs]);
  const definir = async (chave, valor, tenantId = null) => setEstado(await appConfigService.definir(chave, valor, tenantId));
  const nomeAcademia = (id) => (academias.find((a) => a.id === id) || {}).nome || estado?.academias?.[id] || `Academia ${id}`;

  const acao = (fn, msg) => async () => {
    setSalvando(true);
    try { setEstado(await fn()); showSuccess(msg); return true; } catch (e) { showError(e.message); return false; } finally { setSalvando(false); }
  };
  const confirmarPublicar = async () => { if (await acao(() => appConfigService.publicar(nota), 'Configuração publicada')()) { setPublicar(false); setNota(''); } };
  const confirmarBloqueio = async () => {
    const { def, bloquear } = bloqueio;
    if (await acao(() => appConfigService.bloquear(def.item, bloquear, nota), bloquear ? `${def.nome} desligada para todos` : `${def.nome} religada`)()) { setBloqueio(null); setNota(''); }
  };
  const confirmarRestaurar = async () => { if (await acao(() => appConfigService.restaurar(restaurar), `Versão ${restaurar} restaurada`)()) setRestaurar(null); };

  const pend = estado?.pendencias || [];
  const props = { estado, defs, academias, onDefinir: definir, onExcecoes: setExcecoes };
  const ABAS = [
    { value: 'funcionalidades', label: 'Funcionalidades' },
    { value: 'parametros', label: 'Parâmetros' },
    { value: 'efetiva', label: 'Por academia' },
    { value: 'historico', label: 'Histórico' },
  ];

  return (
    <>
      <div className={versoes ? 'ck-mc' : 'ck-cf ck-pm'}>
        {!versoes ? <PageHeader title="Configurações"
          description="Liga, desliga e ajusta o app do aluno sem publicar versão nova. As mudanças ficam num rascunho até você publicar."
          actions={estado ? <span className="ck-cf-ver"><span className="ck-cf-ver__dot" aria-hidden="true" />{`Em uso: versão ${estado.versao_publicada}`}</span> : null} />
          : estado ? <p className="ck-mc-hint">{`Em uso: versão ${estado.versao_publicada} da configuração.`}</p> : null}

        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
        {versoes && pend.length ? (
          <Callout tone="warning" title={`${pend.length} alteraç${pend.length > 1 ? 'ões' : 'ão'} não publicada${pend.length > 1 ? 's' : ''}`}
            action={(
              <span className="ck-pf__chips">
                <Button size="sm" variant="ghost" disabled={salvando} onClick={acao(() => appConfigService.descartar(), 'Rascunho descartado')}>Descartar</Button>
                <Button size="sm" variant="primary" icon="check" onClick={() => setPublicar(true)}>Revisar e publicar</Button>
              </span>
            )}>
            O app só recebe as mudanças depois de publicar. O rascunho reúne alterações de Configurações e Versões.
          </Callout>
        ) : null}

        {!versoes ? <Tabs label="Seções das configurações do app" value={aba} onChange={setAba} tabs={ABAS} className="ck-mc-tabs" /> : null}

        <div className={versoes ? 'ck-mc' : 'ck-pm-main'}>
        {!estado ? (erro ? null : <p className="ck-mc-hint" role="status">Carregando…</p>)
          : versoes ? <VersoesTab {...props} />
            : aba === 'funcionalidades' ? <FuncionalidadesTab {...props} busca={busca} setBusca={setBusca} onBloqueio={(def, bloquear) => setBloqueio({ def, bloquear })} />
              : aba === 'parametros' ? <ParametrosTab {...props} />
                : aba === 'efetiva' ? <EfetivaTab defs={defs} academias={academias} />
                  : <HistoricoTab versaoAtual={estado.versao_publicada} onRestaurar={setRestaurar} />}
        </div>
        {!versoes && pend.length ? <div className="ck-savebar" role="status">
          <span className="ck-savebar__dot" aria-hidden="true" />
          <span className="ck-savebar__text">{`${pend.length} alteraç${pend.length === 1 ? 'ão' : 'ões'} no rascunho. O app só recebe depois de publicar.`}</span>
          <Button variant="ghost" disabled={salvando} onClick={acao(() => appConfigService.descartar(), 'Rascunho descartado')}>Descartar</Button>
          <Button variant="primary" icon="check" disabled={salvando} onClick={() => setPublicar(true)}>Revisar e publicar</Button>
        </div> : null}
      </div>

      {excecoes && estado ? <AppConfigExcecoesModal key={excecoes.chave} def={excecoes} estado={estado} academias={academias} onDefinir={definir} onClose={() => setExcecoes(null)} /> : null}

      <Modal open={publicar} size="lg" title={versoes ? 'Publicar configuração' : `Publicar versão ${(estado?.versao_publicada || 0) + 1}`} dismissable={!salvando} onClose={() => setPublicar(false)}
        description="Os apps recebem a nova configuração na próxima consulta (ao abrir, ao voltar para o app ou depois do intervalo de atualização)."
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvando} onClick={() => setPublicar(false)}>Cancelar</Button>
            <Button variant="primary" icon="check" loading={salvando} onClick={confirmarPublicar}>Publicar</Button>
          </>
        )}>
        <div className="ck-mc-form">
          <p className="ck-mc-hint">Esta revisão inclui as alterações pendentes de Configurações e Versões.</p>
          <ul className="ck-cf-changes is-big">
            {pend.map((p) => {
              const d = defPorChave[p.chave];
              return (
                <li key={`${p.tenant_id}-${p.chave}`}>
                  <span className="ck-cj-rows__main">
                    <strong>{d ? d.nome : p.chave}{p.chave.startsWith('rollout.') ? ' (rollout %)' : ''}</strong>
                    <small>{p.tenant_id ? nomeAcademia(p.tenant_id) : 'Todas as academias'}</small>
                  </span>
                  <Badge tone="outline">{exibir(d, p.de)}</Badge>
                  <span aria-hidden>→</span>
                  <Badge tone="info">{exibir(d, p.para)}</Badge>
                </li>
              );
            })}
          </ul>
          <TextField label="Nota" value={nota} onChange={(e) => setNota(e.target.value)} placeholder="O que muda e por quê" labelAside={<span className="ck-field__hint">Opcional</span>} />
        </div>
      </Modal>

      <Modal open={!!bloqueio} size="sm" dismissable={!salvando} onClose={() => setBloqueio(null)}
        title={bloqueio?.bloquear ? `Desligar ${bloqueio?.def.nome} agora?` : `Religar ${bloqueio?.def.nome}?`}
        description={bloqueio?.bloquear
          ? 'Publica na hora, para todas as academias, sem levar o resto do rascunho. Nenhuma exceção por academia religa. O servidor também recusa as ações dessa função, inclusive de apps com configuração antiga.'
          : 'Publica na hora. A funcionalidade volta a seguir o valor geral e as exceções por academia.'}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvando} onClick={() => setBloqueio(null)}>Cancelar</Button>
            <Button variant={bloqueio?.bloquear ? 'danger' : 'primary'} icon={bloqueio?.bloquear ? 'power' : 'refresh-cw'} loading={salvando} onClick={confirmarBloqueio}>
              {bloqueio?.bloquear ? 'Desligar agora' : 'Religar'}
            </Button>
          </>
        )}>
        <TextField label="Motivo" value={nota} onChange={(e) => setNota(e.target.value)} placeholder="Ex.: instabilidade no gateway" labelAside={<span className="ck-field__hint">Opcional</span>} />
      </Modal>

      <Modal open={!!restaurar} size="sm" title={`Restaurar a versão ${restaurar}?`} dismissable={!salvando} onClose={() => setRestaurar(null)}
        description="O conteúdo dela é publicado de novo como uma versão nova. Alterações não publicadas do rascunho são descartadas."
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvando} onClick={() => setRestaurar(null)}>Cancelar</Button>
            <Button variant="primary" icon="refresh-cw" loading={salvando} onClick={confirmarRestaurar}>Restaurar</Button>
          </>
        )} />
    </>
  );
}

export default function AppConfigScreen({ secao = 'configuracoes' }) {
  const versoes = secao === 'versoes';
  return (
    <AppMobileLayout
      title={versoes ? 'Versões' : 'Configurações'}
      pageHeader={versoes}
      description={versoes
        ? 'Gerencie as versões publicadas, as versões mínimas e os avisos de atualização do app.'
        : 'Liga, desliga e ajusta o app do aluno sem publicar versão nova.'}
    >
      <AppConfigEditor key={secao} secao={secao} />
    </AppMobileLayout>
  );
}
