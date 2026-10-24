import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { Badge, Button, Callout, Modal, PageHeader, SegmentedControl, SelectField, Switch, Tabs, TextAreaField, TextField } from '../../components/ck';
import { Panel } from '../../components/ck/pessoas';
import { authService } from '../../services/authService';
import appConfigService from '../../services/appConfigService';
import appVersaoService from '../../services/appVersaoService';
import { superAdminService } from '../../services/superAdminService';
import { showError, showSuccess } from '../../utils/toast';
import AtualizacaoCadastralPanel from './AtualizacaoCadastralPanel';

// Super Admin → Configurações do app: funcionalidades, parâmetros e versões do app do aluno, editados
// num rascunho e publicados de uma vez (com histórico e rollback). O catálogo vem da API
// (App\Support\AppConfigCatalogo); aqui só se escolhem valores. Estilos: ck-mc-*/ck-cj-* (ck-minha-conta.css).

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

/** Exceções por academia de um item de escopo tenant. */
function ExcecoesModal({ def, estado, academias, onDefinir, onClose }) {
  const [nova, setNova] = useState('');
  if (!def) return null;
  const tenants = estado.rascunho.tenants || {};
  const comExcecao = Object.keys(tenants).filter((t) => tenants[t][def.chave] !== undefined).map(Number);
  const nome = (id) => (academias.find((a) => a.id === id) || {}).nome || estado.academias?.[id] || `Academia ${id}`;
  const livres = academias.filter((a) => !comExcecao.includes(a.id));
  const geral = estado.rascunho.global[def.chave] ?? def.padrao;

  return (
    <Modal open size="md" title={`${def.nome}: exceções por academia`} onClose={onClose}
      description={`Valor geral: ${exibir(def, geral)}. A exceção vale só para a academia; bloqueio emergencial e versão mínima continuam valendo.`}>
      <div className="ck-mc-form">
        {comExcecao.length ? (
          <ul className="ck-cj-rows">
            {comExcecao.map((id) => (
              <li key={id}>
                <span className="ck-cj-rows__main"><strong>{nome(id)}</strong></span>
                <span style={{ flex: '1 1 220px' }}><ValorField def={def} label={def.tipo === 'booleano' ? 'Ligada nesta academia' : 'Valor'} valor={tenants[id][def.chave]} onSalvar={(v) => onDefinir(def.chave, v, id)} /></span>
                <Button size="sm" variant="ghost" icon="x" onClick={() => onDefinir(def.chave, null, id).catch((e) => showError(e.message))}>Remover</Button>
              </li>
            ))}
          </ul>
        ) : <p className="ck-mc-hint">Nenhuma academia com exceção. Todas usam o valor geral.</p>}
        {livres.length ? (
          <div className="ck-pf__chips">
            <SelectField label="Adicionar exceção" value={nova} placeholder="Escolha a academia" onChange={(e) => setNova(e.target.value)}
              options={livres.map((a) => ({ value: String(a.id), label: a.nome }))} />
            <Button size="sm" icon="plus" disabled={!nova}
              onClick={() => onDefinir(def.chave, def.tipo === 'booleano' ? !geral : geral, Number(nova)).then(() => setNova('')).catch((e) => showError(e.message))}>Adicionar</Button>
          </div>
        ) : null}
      </div>
    </Modal>
  );
}

function FuncionalidadesTab({ estado, defs, academias, busca, setBusca, onDefinir, onExcecoes, onBloqueio }) {
  const funcs = defs.filter((d) => d.grupo === 'funcionalidade' && d.chave.startsWith('funcionalidade.'));
  const termo = busca.trim().toLowerCase();
  const visiveis = funcs.filter((d) => !termo || `${d.nome} ${d.descricao} ${d.modulo}`.toLowerCase().includes(termo));
  const modulos = [...new Set(visiveis.map((d) => d.modulo))];
  const pendente = (chave) => estado.pendencias.some((p) => p.chave === chave);
  const excecoes = (chave) => Object.values(estado.rascunho.tenants || {}).filter((v) => v[chave] !== undefined).length;

  return (
    <>
      <TextField label="Buscar funcionalidade" icon="search" value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Nome, módulo ou descrição" />
      {modulos.map((m) => (
        <Panel key={m} title={m}>
          <ul className="ck-cj-rows">
            {visiveis.filter((d) => d.modulo === m).map((d) => {
              const k = d.item;
              const bloqueada = estado.bloqueios.includes(`bloqueio.${k}`);
              const rollout = estado.rascunho.global[`rollout.${k}`] ?? 100;
              const n = excecoes(d.chave);
              return (
                <li key={d.chave}>
                  <span className="ck-cj-rows__main">
                    <strong>{d.nome}</strong>
                    <small>{d.descricao}</small>
                    <small>
                      <code>{k}</code>
                      {d.versao_minima?.android ? ` · Android ${d.versao_minima.android}+` : ''}
                      {d.versao_minima?.ios ? ` · iOS ${d.versao_minima.ios}+` : ''}
                    </small>
                  </span>
                  {bloqueada ? <Badge tone="danger" icon="alert-triangle">Bloqueada</Badge> : null}
                  {pendente(d.chave) || pendente(`rollout.${k}`) ? <Badge tone="warning">Não publicado</Badge> : null}
                  {rollout < 100 ? <Badge tone="info">{`Rollout ${rollout}%`}</Badge> : null}
                  <Switch ariaLabel={`${d.nome}: ligada para todas`} checked={!!(estado.rascunho.global[d.chave] ?? d.padrao)}
                    onChange={(v) => onDefinir(d.chave, v).catch((e) => showError(e.message))} />
                  <Button size="sm" variant="ghost" icon="building-2" onClick={() => onExcecoes(d)}>{n ? `${n} academia${n > 1 ? 's' : ''}` : 'Por academia'}</Button>
                  {bloqueada
                    ? <Button size="sm" variant="ghost" icon="refresh-cw" onClick={() => onBloqueio(d, false)}>Religar</Button>
                    : <Button size="sm" variant="danger" icon="power" onClick={() => onBloqueio(d, true)}>Desligar agora</Button>}
                </li>
              );
            })}
          </ul>
        </Panel>
      ))}
      {!visiveis.length ? <p className="ck-mc-hint">Nenhuma funcionalidade encontrada.</p> : null}
      <Panel title="Rollout gradual" description="Percentual de usuários que recebem cada funcionalidade. A escolha é estável: o mesmo usuário sempre cai no mesmo grupo.">
        <div className="ck-mc-form">
          {funcs.map((d) => {
            const def = defs.find((x) => x.chave === `rollout.${d.item}`);
            return def ? <ValorField key={def.chave} def={def} label={`${d.nome} (%)`} valor={estado.rascunho.global[def.chave] ?? def.padrao} onSalvar={(v) => onDefinir(def.chave, v)} /> : null;
          })}
        </div>
      </Panel>
    </>
  );
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
    setErro('');
    appConfigService.efetiva({ tenantId, plataforma, versao: versao.trim() }).then(setDados).catch((e) => { setDados(null); setErro(e.message); });
  }, [tenantId, plataforma, versao]);
  const nomeDe = (grupo, item) => (defs.find((d) => d.chave === `${grupo}.${item}`) || {}).nome || item;
  const tone = (o) => (o === 'academia' ? 'info' : o === 'global' ? 'neutral' : ['bloqueio', 'versao', 'papel', 'rollout'].includes(o) ? 'warning' : 'outline');
  const at = dados?.contrato?.atualizacao;

  return (
    <Panel title="O que o app recebe" description="Configuração publicada, resolvida para uma academia, plataforma e versão. Mostra de onde vem cada valor.">
      <div className="ck-mc-form">
        <div className="ck-pf__chips">
          <SelectField label="Academia" value={tenantId} onChange={(e) => setTenantId(e.target.value)}
            options={[{ value: '', label: 'Antes do login (só o geral)' }, ...academias.map((a) => ({ value: String(a.id), label: a.nome }))]} />
          <SegmentedControl label="Plataforma" value={plataforma} onChange={setPlataforma}
            options={[{ value: 'android', label: 'Android' }, { value: 'ios', label: 'iOS' }, { value: 'web', label: 'Web' }]} />
          {plataforma !== 'web' ? <TextField label="Versão do app" value={versao} placeholder="ex.: 1.0.2" onChange={(e) => setVersao(e.target.value)} /> : null}
        </div>
        {erro ? <Callout tone="danger">{erro}</Callout> : null}
        {dados ? (
          <>
            <p className="ck-mc-hint">{`Versão publicada da configuração: ${dados.efetiva.versao}. O rollout parcial aparece como desligado porque depende do usuário.`}</p>
            <ul className="ck-cj-rows">
              {Object.entries(dados.efetiva.funcionalidades).map(([k, f]) => (
                <li key={k}>
                  <span className="ck-cj-rows__main"><strong>{nomeDe('funcionalidade', k)}</strong></span>
                  <Badge tone={f.ativo ? 'success' : 'danger'}>{f.ativo ? 'Ligada' : 'Desligada'}</Badge>
                  <Badge tone={tone(f.origem)}>{ORIGEM[f.origem] || f.origem}</Badge>
                </li>
              ))}
              {Object.entries(dados.efetiva.parametros).map(([k, p]) => (
                <li key={k}>
                  <span className="ck-cj-rows__main"><strong>{nomeDe('parametro', k)}</strong><small>{p.valor === '' ? '(vazio)' : String(p.valor)}</small></span>
                  <Badge tone={tone(p.origem)}>{ORIGEM[p.origem] || p.origem}</Badge>
                </li>
              ))}
            </ul>
            {at ? (
              <Callout tone={at.obrigatoria ? 'warning' : 'info'} title="Atualização">
                {plataforma === 'web'
                  ? (at.obrigatoria ? 'Versão nova bloqueia o app web até recarregar.' : 'Versão nova só mostra um aviso.')
                  : `${at.obrigatoria ? 'Bloqueado até atualizar.' : at.disponivel ? 'Vê o aviso de nova versão.' : 'Em dia.'} Mínima: ${at.versao_minima || '—'} · Publicada: ${at.versao_recomendada || '—'}`}
              </Callout>
            ) : null}
          </>
        ) : null}
      </div>
    </Panel>
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
      <Panel title="Publicações" description="Restaurar publica de novo o conteúdo daquela versão (o rascunho atual é substituído).">
        <ul className="ck-cj-rows">
          {dados.publicacoes.map((p) => (
            <li key={p.versao}>
              <span className="ck-cj-rows__main">
                <strong>{`Versão ${p.versao}`}{p.nota ? ` · ${p.nota}` : ''}</strong>
                <small>{fdataHora(p.created_at)}{p.publicado_por ? ` · ${p.publicado_por}` : ''}</small>
              </span>
              {p.versao === versaoAtual ? <Badge tone="success">Em uso</Badge> : null}
              {p.origem !== 'publicacao' ? <Badge tone="outline">{p.origem === 'rollback' ? `Rollback da ${p.rollback_de}` : p.origem === 'imediata' ? 'Imediata' : 'Migração'}</Badge> : null}
              {p.versao !== versaoAtual ? <Button size="sm" variant="ghost" icon="refresh-cw" onClick={() => onRestaurar(p.versao)}>Restaurar</Button> : null}
            </li>
          ))}
        </ul>
      </Panel>
      <Panel title="Alterações recentes">
        <ul className="ck-cj-rows">
          {dados.auditoria.map((a) => (
            <li key={a.id}>
              <span className="ck-cj-rows__main">
                <strong>{ACAO[a.acao] || a.acao}{a.chave ? ` · ${a.chave}` : ''}{a.academia ? ` · ${a.academia}` : ''}</strong>
                <small>
                  {a.acao === 'alterar' || a.chave ? `${exibir(null, a.valor_anterior)} → ${exibir(null, a.valor_novo)} · ` : ''}
                  {a.versao ? `versão ${a.versao} · ` : ''}{fdataHora(a.created_at)}{a.usuario ? ` · ${a.usuario}` : ''}{a.nota ? ` · ${a.nota}` : ''}
                </small>
              </span>
            </li>
          ))}
        </ul>
      </Panel>
    </>
  );
}

export default function AppConfigScreen() {
  const router = useRouter();
  const [ok, setOk] = useState(false);
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

  useEffect(() => {
    authService.getCurrentUser().then((u) => {
      if (authService.getEffectivePapelId(u) !== 4) { showError('Acesso negado. Apenas Super Admin pode acessar esta página.'); router.replace('/'); return; }
      setOk(true);
    });
  }, []);

  const carregar = useCallback(async () => {
    setErro('');
    try { setEstado(await appConfigService.estado()); } catch (e) { setErro(e.message); }
  }, []);
  useEffect(() => {
    if (!ok) return;
    carregar();
    superAdminService.listarAcademias().then((r) => setAcademias((r.academias || []).map((a) => ({ id: a.id, nome: a.nome })))).catch(() => {});
  }, [ok, carregar]);

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

  if (!ok) return <LayoutBase title="Configurações do app" pageHeader={false}><p className="ck-mc-hint" role="status">Carregando…</p></LayoutBase>;

  const pend = estado?.pendencias || [];
  const props = { estado, defs, academias, onDefinir: definir, onExcecoes: setExcecoes };
  const ABAS = [
    { value: 'funcionalidades', label: 'Funcionalidades' },
    { value: 'parametros', label: 'Parâmetros' },
    { value: 'versoes', label: 'Versões' },
    { value: 'cadastral', label: 'Atualização cadastral' },
    { value: 'efetiva', label: 'Por academia' },
    { value: 'historico', label: 'Histórico' },
  ];

  return (
    <LayoutBase title="Configurações do app" pageHeader={false}>
      <div className="ck-mc">
        <PageHeader title="Configurações do app" description={`Liga, desliga e ajusta o app do aluno sem publicar versão nova.${estado ? ` Em uso: versão ${estado.versao_publicada} da configuração.` : ''}`} />

        {erro ? <Callout tone="danger" action={<Button size="sm" onClick={carregar}>Tentar de novo</Button>}>{erro}</Callout> : null}
        {pend.length ? (
          <Callout tone="warning" title={`${pend.length} alteraç${pend.length > 1 ? 'ões' : 'ão'} não publicada${pend.length > 1 ? 's' : ''}`}
            action={(
              <span className="ck-pf__chips">
                <Button size="sm" variant="ghost" disabled={salvando} onClick={acao(() => appConfigService.descartar(), 'Rascunho descartado')}>Descartar</Button>
                <Button size="sm" variant="primary" icon="check" onClick={() => setPublicar(true)}>Revisar e publicar</Button>
              </span>
            )}>
            O app só recebe as mudanças depois de publicar.
          </Callout>
        ) : null}

        <Tabs label="Seções das configurações do app" value={aba} onChange={setAba} tabs={ABAS} className="ck-mc-tabs" />

        {aba === 'cadastral' ? <AtualizacaoCadastralPanel />
          : !estado ? (erro ? null : <p className="ck-mc-hint" role="status">Carregando…</p>)
            : aba === 'funcionalidades' ? <FuncionalidadesTab {...props} busca={busca} setBusca={setBusca} onBloqueio={(def, bloquear) => setBloqueio({ def, bloquear })} />
              : aba === 'parametros' ? <ItensTab {...props} grupo="parametro" />
                : aba === 'versoes' ? <VersoesTab {...props} />
                  : aba === 'efetiva' ? <EfetivaTab defs={defs} academias={academias} />
                    : <HistoricoTab versaoAtual={estado.versao_publicada} onRestaurar={setRestaurar} />}
      </div>

      {excecoes && estado ? <ExcecoesModal def={excecoes} estado={estado} academias={academias} onDefinir={definir} onClose={() => setExcecoes(null)} /> : null}

      <Modal open={publicar} size="lg" title="Publicar configuração" dismissable={!salvando} onClose={() => setPublicar(false)}
        description="Os apps recebem a nova configuração na próxima consulta (ao abrir, ao voltar para o app ou depois do intervalo de atualização)."
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={salvando} onClick={() => setPublicar(false)}>Cancelar</Button>
            <Button variant="primary" icon="check" loading={salvando} onClick={confirmarPublicar}>Publicar</Button>
          </>
        )}>
        <div className="ck-mc-form">
          <ul className="ck-cj-rows">
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
    </LayoutBase>
  );
}
