/* LogsViewer — layout portado de LogsViewer.html. Dados vindos de /admin/logs.
   Estilos: ck-logs.css; mantém as primitivas do design system do painel. */
import React, { useEffect, useId, useMemo, useState } from 'react';
import { Button, Callout, Icon, IconButton, PageHeader, SegmentedControl, SelectField } from './index';
import { filterLogEntries, groupLogEntries, logLevel, LOG_LEVELS } from '../../utils/laravelLogs';

const cx = (...values) => values.filter(Boolean).join(' ');
const num = (value) => Number(value || 0).toLocaleString('pt-BR');
const dateTime = (value) => {
  if (!value) return 'Sem data';
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}:\d{2}:\d{2})/);
  return match ? `${match[3]}/${match[2]} ${match[4]}` : value;
};
const fileSize = (value) => value == null ? '' : value < 1024 ? `${value} B`
  : value < 1024 * 1024 ? `${num(Math.round(value / 1024))} KB` : `${(value / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
const shortPath = (file) => String(file || '').replace(/^.*?\/(apiV2|app|vendor|public)\//, '$1/');

function LogEntry({ entry, count = 1, first, onCopy, onFilterSimilar }) {
  const [open, setOpen] = useState(false);
  const [showVendor, setShowVendor] = useState(false);
  const [copied, setCopied] = useState(false);
  const id = useId();
  useEffect(() => { setCopied(false); }, [entry.raw]);
  const level = logLevel(entry.level);
  const trace = entry.trace || [];
  const appFrames = trace.filter((frame) => !frame.vendor);
  const shown = showVendor ? trace : appFrames;
  const chips = [];
  if (entry.request) chips.push(`${entry.request.method || ''} ${entry.request.path}${entry.request.status ? ` ${entry.request.status}` : ''}`.trim());
  [['userId', 'usuário'], ['user_id', 'usuário'], ['matricula_id', 'matrícula'], ['tenant_id', 'academia']].forEach(([key, label]) => {
    if (['number', 'string'].includes(typeof entry.context[key])) chips.push(`${label} ${entry.context[key]}`);
  });
  const copy = async () => {
    if (await onCopy([entry])) setCopied(true);
  };
  return (
    <li className={cx('ck-lg__entry', `is-${level}`, open && 'is-open')}>
      <button type="button" className="ck-lg__head" aria-expanded={open} aria-controls={id} onClick={() => setOpen(!open)}>
        <span className="ck-lg__lv" title={entry.level || 'Nível não disponível'}><span className="ck-lg__dot" aria-hidden="true" />{LOG_LEVELS[level].label}</span>
        <time className="ck-lg__time" dateTime={entry.at || undefined} title={entry.at || 'Data não disponível'}>{dateTime(entry.at)}</time>
        <span className="ck-lg__msg">{entry.exception ? <code>{entry.exception.split('\\').pop()}</code> : null}<span>{entry.message}</span></span>
        {count > 1 ? <span className="ck-lg__count" title={`${count} ocorrências`}>×{num(count)}</span> : <span />}
        <Icon name="chevron-down" size={16} className="ck-lg__chev" />
      </button>
      {chips.length && !open ? <div className="ck-lg__chips">{chips.map((chip, index) => <span key={index} className="ck-lg__chip">{chip}</span>)}</div> : null}
      {open ? <div className="ck-lg__body" id={id}>
        <div className="ck-lg__full">
          <p>{entry.exception ? <code>{entry.exception}</code> : null}{entry.message}</p>
          <time className="ck-lg__detailtime" dateTime={entry.at || undefined}>{entry.at ? entry.at.replace('T', ' ') : 'Data não disponível no trecho carregado'}</time>
          {count > 1 ? <span className="ck-lg__rep"><Icon name="repeat" size={14} />{num(count)} vezes, de {dateTime(first)} a {dateTime(entry.at)}. Mostrando a mais recente.</span> : null}
          {entry.file ? <span className="ck-lg__where"><Icon name="file-text" size={14} />{shortPath(entry.file)}{entry.line ? `:${entry.line}` : ''}</span> : null}
          {chips.length ? <div className="ck-lg__chips is-in">{chips.map((chip, index) => <span key={index} className="ck-lg__chip">{chip}</span>)}</div> : null}
          <div className="ck-lg__acts">
            <Button size="sm" variant="ghost" icon={copied ? 'check' : 'copy'} onClick={copy}>{copied ? 'Copiado' : 'Copiar entrada'}</Button>
            {onFilterSimilar ? <Button size="sm" variant="ghost" icon="search" onClick={() => onFilterSimilar(entry)}>Ver só este registro</Button> : null}
          </div>
        </div>
        {Object.keys(entry.context).length ? <div className="ck-lg__sec"><span className="ck-lg__label">Contexto</span><pre className="ck-lg__pre">{JSON.stringify(entry.context, null, 2)}</pre></div> : null}
        {trace.length ? <div className="ck-lg__sec">
          <span className="ck-lg__label">Stack trace<em>{appFrames.length} do app, {trace.length - appFrames.length} do framework</em></span>
          {shown.length ? <ol className="ck-lg__trace">{shown.map((frame, index) => <li key={index} className={frame.vendor ? 'is-vendor' : undefined}>
            <span className="ck-lg__n">#{frame.n}</span><span className="ck-lg__tf">{shortPath(frame.file)}{frame.line ? `:${frame.line}` : ''}</span><span className="ck-lg__tc">{frame.call}</span>
          </li>)}</ol> : <p className="ck-lg__hint">Os frames deste registro são do framework.</p>}
          {trace.length > appFrames.length ? <button type="button" className="ck-linkbtn ck-lg__more" onClick={() => setShowVendor(!showVendor)}>{showVendor ? 'Esconder frames do framework' : `Mostrar os ${trace.length - appFrames.length} frames do framework`}</button> : null}
        </div> : null}
        <details className="ck-lg__original"><summary>Texto original do registro</summary><pre className="ck-lg__pre">{entry.raw}</pre></details>
      </div> : null}
    </li>
  );
}

export function LogsViewer({ entries = [], files = [], file, meta, lineLimit = 200, loading, error,
  query, level, mode, onQueryChange, onLevelChange, onModeChange, onFileChange, onLineLimitChange,
  onRefresh, onCopy, onDownload }) {
  const [copied, setCopied] = useState(false);
  useEffect(() => { setCopied(false); }, [entries, query, level]);
  const visible = useMemo(() => filterLogEntries(entries, level, query), [entries, level, query]);
  const groups = useMemo(() => groupLogEntries(visible), [visible]);
  const counts = { todos: entries.length, error: 0, warning: 0, info: 0, debug: 0, other: 0 };
  entries.forEach((entry) => { counts[logLevel(entry.level)] += 1; });
  const lastError = entries.filter((entry) => logLevel(entry.level) === 'error' && entry.at).sort((a, b) => b.at.localeCompare(a.at))[0];
  const sorted = useMemo(() => [...visible].sort((a, b) => b.at.localeCompare(a.at)), [visible]);
  const copyAll = async () => { if (await onCopy(visible)) setCopied(true); };
  const changeQuery = (value) => { setCopied(false); onQueryChange(value); };
  const nextLimit = [100, 200, 500, 1000].find((value) => value > lineLimit);
  const displayFile = meta?.arquivo || file;

  return (
    <div className="ck-lg">
      <PageHeader title="Logs da API" description="API v2. Os registros iguais aparecem juntos, com quantas vezes aconteceram."
        actions={<>
          <Button icon={copied ? 'check' : 'copy'} disabled={loading || !visible.length} onClick={copyAll} title={`Copia os ${visible.length} registros visíveis`}>{copied ? 'Log copiado' : 'Copiar log'}</Button>
          <Button icon="refresh-cw" loading={loading} onClick={onRefresh}>{loading ? 'Atualizando…' : 'Atualizar'}</Button>
          <IconButton icon="download" label="Baixar registros visíveis" title="Baixar somente o trecho carregado com os filtros atuais" variant="secondary" disabled={loading || !visible.length} onClick={() => onDownload(visible)} />
        </>}
      />
      {error ? <Callout tone="danger" title="Não foi possível ler os logs" action={<Button size="sm" onClick={onRefresh} disabled={loading}>Tentar novamente</Button>}>{error}</Callout> : null}
      <div className="ck-lg__stats" aria-label="Resumo dos registros carregados">
        <div className={counts.error ? 'is-bad' : undefined}><span>Erros</span><strong>{num(counts.error)}</strong><em>{lastError ? `último em ${dateTime(lastError.at)}` : 'nenhum no trecho carregado'}</em></div>
        <div className={counts.warning ? 'is-warn' : undefined}><span>Avisos</span><strong>{num(counts.warning)}</strong></div>
        <div><span>Info e debug</span><strong>{num(counts.info + counts.debug)}</strong>{counts.other ? <em>{counts.other} sem nível identificado</em> : null}</div>
        <div><span>Arquivo</span><strong className="is-file" title={displayFile}>{displayFile}</strong><em>{[fileSize(meta?.tamanho_bytes), `${num(entries.length)} registros em ${num(meta?.total_linhas_retornadas)} linhas`].filter(Boolean).join(', ')}</em></div>
      </div>
      <section className="ck-lg__panel" aria-label="Registros" aria-busy={loading}>
        <div className="ck-lg__bar">
          <div className="ck-control ck-ml__search">
            <Icon name="search" size={18} className="ck-control__icon" />
            <input type="search" className="ck-control__input" value={query} placeholder="Buscar: SQLSTATE, JWT, matricula_id, rota…" aria-label="Buscar nos logs carregados" onChange={(event) => changeQuery(event.target.value)} />
            {query ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => changeQuery('')} /> : null}
          </div>
          <SelectField aria-label="Arquivo de log" value={file} options={files.length ? files.map((item) => ({ value: item.nome, label: item.nome })) : [{ value: file, label: file }]} onChange={(event) => onFileChange(event.target.value)} />
          <SelectField className="ck-lg__limit" aria-label="Limite de linhas" value={String(lineLimit)} options={[100, 200, 500, 1000].map((value) => ({ value: String(value), label: `${num(value)} linhas` }))} onChange={(event) => onLineLimitChange(Number(event.target.value))} />
          <SegmentedControl label="Visualização" value={mode} onChange={onModeChange} options={[{ value: 'agrupado', label: 'Agrupado' }, { value: 'tempo', label: 'Linha do tempo' }]} />
        </div>
        <div className="ck-lg__filters ck-ml__filters" role="group" aria-label="Nível">
          {[['todos', 'Todos'], ...Object.entries(LOG_LEVELS).filter(([key]) => key !== 'other' || counts.other).map(([key, item]) => [key, item.plural, item.tone])].map(([key, label, tone]) => (
            <button key={key} type="button" aria-pressed={level === key} className="ck-ml__chip" onClick={() => { setCopied(false); onLevelChange(key); }}>
              {tone ? <span className={`ck-ml__chipdot ck-ml__chipdot--${tone}`} aria-hidden="true" /> : null}{label}<span className="ck-ml__count">{num(counts[key])}</span>
            </button>
          ))}
        </div>
        <p className="ck-lg__scope">Busca e contadores consideram o trecho carregado, de até {num(lineLimit)} linhas. Aumente o limite para ampliar a leitura.</p>
        {loading ? <div className="ck-ml__empty" role="status"><Icon name="loader" size={24} className="ck-spin" /><strong>Carregando registros…</strong></div>
          : visible.length ? <ul className="ck-lg__list">{mode === 'agrupado'
            ? groups.map((group) => <LogEntry key={`${file}-${group.signature}`} entry={group.latest} count={group.count} first={group.first} onCopy={onCopy} onFilterSimilar={(entry) => { changeQuery(entry.exception ? entry.exception.split('\\').pop() : entry.message); onModeChange('tempo'); }} />)
            : sorted.map((entry) => <LogEntry key={`${file}-${entry.id}`} entry={entry} onCopy={onCopy} />)}</ul>
            : <div className="ck-ml__empty"><span className="ck-mt-empty__icon" aria-hidden="true"><Icon name={query ? 'search' : 'file-text'} size={20} /></span><strong>{query ? `Nada com “${query}”` : 'Nenhum registro neste nível'}</strong><p>{query ? 'A busca inclui a mensagem, o contexto e o stack trace.' : 'Escolha outro nível, arquivo ou carregue mais linhas.'}</p>{query || level !== 'todos' ? <Button size="sm" onClick={() => { changeQuery(''); onLevelChange('todos'); }}>Limpar filtros</Button> : null}</div>}
        <footer className="ck-ml__foot">
          <span aria-live="polite">{loading ? 'Lendo arquivo…' : mode === 'agrupado' ? `${num(groups.length)} tipos de registro, ${num(visible.length)} entradas` : `${num(visible.length)} entradas`}</span>
          {meta?.truncado && nextLimit ? <Button size="sm" icon="download" disabled={loading} onClick={() => onLineLimitChange(nextLimit)}>Carregar entradas mais antigas</Button> : null}
          {meta?.truncado && !nextLimit ? <span className="ck-lg__hint">Limite máximo de 1.000 linhas. Há mais conteúdo no arquivo.</span> : null}
        </footer>
      </section>
    </div>
  );
}
