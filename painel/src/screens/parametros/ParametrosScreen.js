import React, { useEffect, useMemo, useRef, useState } from 'react';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Icon, PageHeader, SelectField, Switch } from '../../components/ck';
import parametrosService from '../../services/parametrosService';
import { booleanoParametro, normalizarBusca, opcoesParametro, textoParametro, tipoParametro, validarParametro } from '../../utils/parametros';

const ICONES = { pagamentos: 'wallet', checkin: 'qr-code', aulas: 'calendar', notificacoes: 'bell', matriculas: 'user-plus', prospeccao: 'target', sistema: 'settings' };
const mensagemErro = (e) => e?.response?.data?.message || e?.response?.data?.error || e?.message || 'Não foi possível salvar. Tente novamente.';

function CampoParametro({ param, value, disabled, onSalvar }) {
  const [texto, setTexto] = useState(() => textoParametro(param, value));
  const [erro, setErro] = useState('');
  const tipo = tipoParametro(param), id = `pm-i-${param.codigo}`, descId = `pm-d-${param.codigo}`;
  useEffect(() => { setTexto(textoParametro(param, value)); setErro(''); }, [value]);
  const salvar = () => {
    if (disabled || texto === textoParametro(param, value)) return;
    const resultado = validarParametro(param, texto);
    setErro(resultado.erro || '');
    if (!resultado.erro) onSalvar(resultado.valor);
  };
  if (tipo === 'boolean') return <Switch id={id} checked={booleanoParametro(value) ?? false} ariaLabel={param.nome || param.codigo} disabled={disabled} onChange={() => onSalvar(null, true)} />;
  if (tipo === 'select') return <div className="ck-pm-sel"><SelectField id={id} aria-label={param.nome || param.codigo} value={String(value ?? '')} disabled={disabled} options={opcoesParametro(param)} onChange={(e) => onSalvar(e.target.value)} /></div>;
  const numerico = tipo === 'integer' || tipo === 'decimal';
  const props = { id, className: 'ck-control__input', value: texto, disabled, 'aria-describedby': `${descId}${erro ? ` ${id}-error` : ''}`, 'aria-invalid': !!erro,
    onChange: (e) => { setTexto(e.target.value); setErro(''); }, onBlur: salvar,
    onKeyDown: (e) => {
      if (e.key === 'Enter' && tipo !== 'json') { e.preventDefault(); salvar(); }
      if (e.key === 'Escape') { setTexto(textoParametro(param, value)); setErro(''); }
    } };
  return <div className={`ck-pm-num${tipo === 'json' ? ' ck-pm-json' : ''}`}>
    <div className={`ck-control ${numerico ? 'ck-pm-num__ctl' : 'ck-pm-text__ctl'}${erro ? ' is-invalid' : ''}${disabled ? ' is-disabled' : ''}`}>
      {tipo === 'json' ? <textarea {...props} rows={5} spellCheck={false} /> : <input {...props} type="text" inputMode={numerico ? (tipo === 'integer' ? 'numeric' : 'decimal') : 'text'} />}
    </div>
    {erro ? <span id={`${id}-error`} className="ck-pm-err" role="alert">{erro}</span> : null}
  </div>;
}

export default function ParametrosScreen() {
  const [loading, setLoading] = useState(true);
  const [categorias, setCategorias] = useState([]);
  const [valores, setValores] = useState({});
  const [status, setStatus] = useState({});
  const [erro, setErro] = useState('');
  const [busca, setBusca] = useState('');
  const [chaves, setChaves] = useState(false);
  const [ativa, setAtiva] = useState('');
  const salvando = useRef(new Set());
  const secoes = useRef({});

  const carregar = async () => {
    if (salvando.current.size) return;
    setLoading(true); setErro('');
    try {
      const r = await parametrosService.listar();
      if (!r?.success) throw new Error(r?.message || 'Não foi possível carregar os parâmetros.');
      const data = (Array.isArray(r.data) ? r.data : []).filter((c) => String(c.categoria?.ativo ?? 1) !== '0').sort((a, b) => Number(a.categoria?.ordem ?? 999) - Number(b.categoria?.ordem ?? 999));
      const next = {};
      data.forEach((c) => (c.parametros || []).forEach((p) => { if (p.codigo) next[p.codigo] = p.valor; }));
      setCategorias(data); setValores(next); setStatus({});
      setAtiva((atual) => data.some((c) => String(c.categoria.codigo || c.categoria.id) === atual) ? atual : String(data[0]?.categoria?.codigo || data[0]?.categoria?.id || ''));
    } catch (e) { setErro(mensagemErro(e)); } finally { setLoading(false); }
  };
  useEffect(() => { carregar(); }, []);

  const salvar = async (param, valor, toggle = false) => {
    const codigo = param.codigo;
    if (salvando.current.has(codigo)) return;
    salvando.current.add(codigo);
    setStatus((s) => ({ ...s, [codigo]: { tipo: 'saving' } }));
    try {
      const r = toggle ? await parametrosService.toggleParametro(codigo) : await parametrosService.atualizarParametro(codigo, valor);
      if (!r?.success) throw new Error(r?.message || 'Não foi possível salvar.');
      const salvo = toggle ? booleanoParametro(r.valor) ?? !(booleanoParametro(valores[codigo]) ?? false) : r.data?.valor ?? r.valor ?? valor;
      setValores((v) => ({ ...v, [codigo]: salvo }));
      setStatus((s) => ({ ...s, [codigo]: { tipo: 'saved' } }));
    } catch (e) { setStatus((s) => ({ ...s, [codigo]: { tipo: 'error', mensagem: mensagemErro(e) } })); }
    finally { salvando.current.delete(codigo); }
  };

  const q = normalizarBusca(busca);
  const secoesVisiveis = useMemo(() => categorias.map((c) => ({ ...c, visiveis: (c.parametros || []).filter((p) => p.codigo && (!q || normalizarBusca(`${p.nome} ${p.descricao || ''} ${p.codigo} ${c.categoria?.nome || ''}`).includes(q))) })).filter((c) => !q || c.visiveis.length), [categorias, q]);
  const pendente = Object.values(status).some((s) => s.tipo === 'saving');
  const navegar = (id) => {
    setAtiva(id);
    secoes.current[id]?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  };

  return <LayoutBase title="Parâmetros" pageHeader={false}>
    <div className="ck-pm">
      <PageHeader title="Parâmetros" description="Como a academia funciona no app. Cada mudança é salva na hora. Nos campos, saia da edição ou pressione Enter para salvar."
        actions={<><div className="ck-pm-keys"><Switch checked={chaves} onChange={setChaves} ariaLabel="Mostrar chaves técnicas" /><span>Chaves técnicas</span></div><Button icon="refresh-cw" onClick={carregar} loading={loading} disabled={pendente}>Recarregar</Button></>} />
      {erro ? <Callout tone="danger" title="Não foi possível carregar os parâmetros" action={<Button size="sm" onClick={carregar}>Tentar novamente</Button>}>{erro}</Callout> : null}
      {loading ? <p role="status" className="ck-enrollment-state">Carregando parâmetros…</p> : !erro ? <div className="ck-pm-layout">
        <nav className="ck-pm-nav" aria-label="Categorias de parâmetros">
          <div className="ck-control"><Icon name="search" size={16} className="ck-control__icon" /><input type="search" className="ck-control__input" value={busca} placeholder="Buscar parâmetro" aria-label="Buscar parâmetro" onChange={(e) => setBusca(e.target.value)} /></div>
          <ul>{categorias.map((c) => {
            const cat = c.categoria || {}, id = String(cat.codigo || cat.id), encontrados = secoesVisiveis.find((s) => s.categoria === c.categoria)?.visiveis.length || 0;
            return <li key={id}><button type="button" className={`ck-pm-navi${ativa === id ? ' is-on' : ''}${q && !encontrados ? ' is-dim' : ''}`} aria-current={ativa === id ? 'location' : undefined} disabled={!!q && !encontrados} onClick={() => navegar(id)}><Icon name={ICONES[cat.codigo] || 'settings'} size={16} /><span>{cat.nome || cat.codigo || 'Categoria'}</span><em>{q ? encontrados : (c.parametros || []).length || '—'}</em></button></li>;
          })}</ul>
        </nav>
        <div className="ck-pm-main">
          {secoesVisiveis.map((c) => {
            const cat = c.categoria || {}, id = String(cat.codigo || cat.id);
            return <section key={id} ref={(el) => { secoes.current[id] = el; }} className="ck-card ck-pm-sec" aria-labelledby={`pm-h-${id}`}>
              <header className="ck-pm-sechead"><span className="ck-pm-secicon" aria-hidden="true"><Icon name={ICONES[cat.codigo] || 'settings'} size={18} /></span><div><h2 id={`pm-h-${id}`}>{cat.nome || cat.codigo || 'Categoria'}</h2>{cat.descricao ? <p>{cat.descricao}</p> : null}</div></header>
              {c.visiveis.length ? <ul className="ck-pm-list">{c.visiveis.map((p) => {
                const s = status[p.codigo], label = p.nome || p.codigo;
                return <li key={p.codigo} className="ck-pm-row">
                  <div className="ck-pm-row__txt"><label htmlFor={tipoParametro(p) === 'boolean' ? undefined : `pm-i-${p.codigo}`} className="ck-pm-row__label">{label}</label><span id={`pm-d-${p.codigo}`} className="ck-pm-row__desc">{p.descricao !== p.nome ? p.descricao : ''}</span>
                    {p.codigo === 'max_tolerancia_checkin_antes_minutos' ? <span className="ck-pm-ex"><Icon name="info" size={13} />0 = desligado. Ex.: 30 = check-in abre no máximo 30 minutos antes do início.</span> : null}
                    {chaves ? <code className="ck-pm-key">{p.codigo}</code> : null}
                  </div>
                  <div className="ck-pm-row__ctl">
                    <span className={`ck-pm-st${s?.tipo === 'saved' ? ' is-ok' : s?.tipo === 'error' ? ' is-err' : ''}`} role="status">{s?.tipo === 'saving' ? 'Salvando…' : s?.tipo === 'saved' ? <><Icon name="check" size={13} />Salvo</> : s?.tipo === 'error' ? s.mensagem : null}</span>
                    <CampoParametro param={p} value={valores[p.codigo]} disabled={s?.tipo === 'saving'} onSalvar={(valor, toggle) => salvar(p, valor, toggle)} />
                  </div>
                </li>;
              })}</ul> : <p className="ck-pm-none">Nenhum parâmetro ajustável nesta categoria por enquanto.</p>}
            </section>;
          })}
          {!secoesVisiveis.length ? <div className="ck-card ck-pm-empty" role="status"><Icon name="search" size={24} /><strong>{busca ? `Nenhum parâmetro com “${busca}”` : 'Nenhum parâmetro disponível'}</strong><p>Busque pelo nome, pela descrição ou pela chave técnica.</p></div> : null}
        </div>
      </div> : null}
    </div>
  </LayoutBase>;
}
