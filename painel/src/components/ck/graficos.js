/* AppCheckin — gráficos do Dashboard: Sparkline, ColumnChart, ChartBarList, Heatmap e Meter.
   Portado de Dashboard.html do design system. Estilos: src/theme/ck-graficos.css. StatTile e Panel vêm de ./pessoas. */
import React, { useState } from 'react';

const cx = (...a) => a.filter(Boolean).join(' ');
const num = (v) => new Intl.NumberFormat('pt-BR').format(v || 0);

/** Arredonda o topo do eixo para um valor "redondo" (1, 1.2, 1.6, 2… × 10^n). */
export function niceMax(v) {
  if (v <= 0) return 1;
  const p = 10 ** Math.floor(Math.log10(v));
  const n = v / p;
  return [1, 1.2, 1.6, 2, 2.4, 3, 4, 5, 6, 8, 10].find((x) => n <= x) * p;
}

/** Variação para o StatTile. Com `fmtAbs` mostra a diferença absoluta; sem, em %. */
export function deltaObj(cur, prev, vs, goodWhenUp, fmtAbs) {
  if (prev == null || cur == null) return null;
  if (fmtAbs) {
    const dv = cur - prev;
    return { value: dv, label: `${dv > 0 ? '+' : dv < 0 ? '−' : ''}${fmtAbs(Math.abs(dv))}`, vs, goodWhenUp };
  }
  if (!prev) return null;
  const d = ((cur - prev) / prev) * 100;
  return { value: Math.round(d * 10) / 10, label: `${d > 0 ? '+' : d < 0 ? '−' : ''}${Math.abs(d).toFixed(1).replace('.', ',')}%`, vs, goodWhenUp };
}

export function Sparkline({ data }) {
  const d = data || [];
  if (d.length < 2) return null;
  const w = 96; const hh = 28;
  const mx = Math.max(...d); const mn = Math.min(...d); const r = mx - mn || 1;
  const pts = d.map((v, i) => [(i * (w - 6)) / (d.length - 1) + 3, hh - 4 - ((v - mn) / r) * (hh - 8)]);
  const last = pts[pts.length - 1];
  return (
    <svg className="ck-spark" width={w} height={hh} viewBox={`0 0 ${w} ${hh}`} aria-hidden>
      <polyline points={pts.map((q) => q.join(',')).join(' ')} fill="none" className="ck-spark__line" strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
      <circle cx={last[0]} cy={last[1]} r={4} className="ck-spark__dot" />
    </svg>
  );
}

export function ColumnChart({ data = [], format = num, formatAxis, height = 200, highlight, highlightLast, labelEvery, reference, referenceLabel, caption, minMax = 0, onSelect }) {
  const [hv, setHv] = useState(null);
  const fmtAxis = formatAxis || format;
  const mx = niceMax(Math.max(...data.map((d) => d.value), minMax));
  const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => t * mx);
  const Tag = onSelect ? 'button' : 'div';
  return (
    <figure className="ck-cc" style={{ '--cc-h': `${height}px` }}>
      <div className="ck-cc__plot">
        <div className="ck-cc__axis" aria-hidden>{ticks.slice().reverse().map((t, i) => <span key={i}>{fmtAxis(t)}</span>)}</div>
        <div className="ck-cc__area">
          {ticks.map((t, i) => <span key={i} className="ck-cc__grid" style={{ bottom: `${(t / mx) * 100}%` }} aria-hidden />)}
          {reference != null ? <span className="ck-cc__ref" style={{ bottom: `${(reference / mx) * 100}%` }}><span>{referenceLabel || ''}</span></span> : null}
          <div className="ck-cc__cols">
            {data.map((d, i) => {
              const on = highlight === i || (highlight == null && highlightLast && i === data.length - 1);
              return (
                <Tag
                  key={i}
                  type={onSelect ? 'button' : undefined}
                  className={cx('ck-cc__col', on && 'is-on', hv === i && 'is-hover')}
                  onMouseEnter={() => setHv(i)}
                  onMouseLeave={() => setHv(null)}
                  onFocus={() => setHv(i)}
                  onBlur={() => setHv(null)}
                  onClick={onSelect ? () => onSelect(i, d) : undefined}
                  aria-label={`${d.tip || d.label}: ${format(d.value)}`}
                >
                  <span className="ck-cc__bar" style={{ height: `${Math.max((d.value / mx) * 100, d.value ? 1.5 : 0)}%` }}>
                    {on ? <span className="ck-cc__val">{format(d.value)}</span> : null}
                  </span>
                  {hv === i ? (
                    <span className="ck-tip" role="tooltip"><strong>{d.tip || d.label}</strong><span>{format(d.value)}</span>{d.sub ? <span>{d.sub}</span> : null}</span>
                  ) : null}
                </Tag>
              );
            })}
          </div>
        </div>
      </div>
      <div className="ck-cc__labels" aria-hidden>
        <span className="ck-cc__axisgap">{fmtAxis(mx)}</span>
        <div>
          {data.map((d, i) => {
            const show = !labelEvery || i % labelEvery === 0 || i === data.length - 1;
            return <span key={i} className={highlight === i ? 'is-on' : null}>{show ? d.label : ''}</span>;
          })}
        </div>
      </div>
      {caption ? <figcaption className="ck-sr">{caption}</figcaption> : null}
    </figure>
  );
}

/**
 * Duas séries lado a lado por coluna (portado do BiGroupedBars do protótipo do BI): `a` em contorno,
 * `b` cheia — ex.: previsto × recebido. Estilos `ck-bi-gb` em ck-graficos.css.
 */
export function GroupedColumnChart({ labels = [], tips, a, b, format = num, formatAxis, height = 220, ratioText, caption }) {
  const [hv, setHv] = useState(null);
  const fmtAxis = formatAxis || format;
  const mx = niceMax(Math.max(...a.values, ...b.values, 1));
  const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => t * mx);
  return (
    <figure className="ck-cc ck-bi-gb" style={{ '--cc-h': `${height}px` }}>
      <div className="ck-cc__plot">
        <div className="ck-cc__axis" aria-hidden>{ticks.slice().reverse().map((t, i) => <span key={i}>{fmtAxis(t)}</span>)}</div>
        <div className="ck-cc__area">
          {ticks.map((t, i) => <span key={i} className="ck-cc__grid" style={{ bottom: `${(t / mx) * 100}%` }} aria-hidden />)}
          <div className="ck-cc__cols">
            {labels.map((l, i) => {
              const va = a.values[i] || 0; const vb = b.values[i] || 0; const tip = (tips && tips[i]) || l;
              return (
                <div key={i} className={cx('ck-bi-gb__col', hv === i && 'is-hover')} tabIndex={0}
                  aria-label={`${tip}: ${a.name} ${format(va)}, ${b.name} ${format(vb)}`}
                  onMouseEnter={() => setHv(i)} onMouseLeave={() => setHv(null)} onFocus={() => setHv(i)} onBlur={() => setHv(null)}>
                  <span className="ck-bi-gb__bar is-outline" style={{ height: `${(va / mx) * 100}%` }} />
                  <span className="ck-bi-gb__bar is-fill" style={{ height: `${(vb / mx) * 100}%` }} />
                  {hv === i ? (
                    <span className="ck-tip" role="tooltip">
                      <strong>{tip}</strong>
                      <span className="ck-bi-tiprow"><i className="is-outline" />{a.name}: <b>{format(va)}</b></span>
                      <span className="ck-bi-tiprow"><i className="is-accent" />{b.name}: <b>{format(vb)}</b></span>
                      {va ? <span>{`${Math.round((vb / va) * 100)}% ${ratioText || `de ${a.name.toLowerCase()}`}`}</span> : null}
                    </span>
                  ) : null}
                </div>
              );
            })}
          </div>
        </div>
      </div>
      <div className="ck-cc__labels" aria-hidden>
        <span className="ck-cc__axisgap">{fmtAxis(mx)}</span>
        <div>{labels.map((l, i) => <span key={i}>{l}</span>)}</div>
      </div>
      {caption ? <figcaption className="ck-sr">{caption}</figcaption> : null}
    </figure>
  );
}

/** Legenda curta das séries (`kind`: accent, muted, outline) */
export function ChartLegend({ items = [] }) {
  return (
    <ul className="ck-bi-legend">
      {items.map((x) => <li key={x.label}><i className={`is-${x.kind || 'accent'}`} aria-hidden />{x.label}</li>)}
    </ul>
  );
}

/** Lista de barras horizontais do design (`ck-bl`). Diferente do BarList de ./financeiro, que conta parcelas. */
export function ChartBarList({ items = [], format = num, showShare = true }) {
  const mx = Math.max(...items.map((x) => x.value), 1);
  const total = items.reduce((a, x) => a + x.value, 0);
  return (
    <ul className="ck-bl">
      {items.map((x, i) => (
        <li key={i} title={`${x.label}: ${format(x.value)}`}>
          <div className="ck-bl__top">
            <span className="ck-bl__label">{x.label}</span>
            <span className="ck-bl__value">{format(x.value)}{showShare && total ? <span>{Math.round((x.value / total) * 100)}%</span> : null}</span>
          </div>
          <span className="ck-bl__track" aria-hidden>
            <span className={cx('ck-bl__bar', x.muted && 'is-muted')} style={{ width: `${Math.max((x.value / mx) * 100, x.value ? 1 : 0)}%` }} />
          </span>
          {x.note ? <span className="ck-bl__note">{x.note}</span> : null}
        </li>
      ))}
    </ul>
  );
}

export function Heatmap({ rows = [], cols = [], values = [], unit = '', label, colEvery = 2, formatValue = (v) => String(v).replace('.', ',') }) {
  const [hv, setHv] = useState(null);
  const mx = Math.max(...values.flat(), 1);
  const lvl = (v) => (v ? Math.min(5, Math.ceil((v / mx) * 5)) : 0);
  return (
    <figure className="ck-hm">
      <div className="ck-hm__grid" style={{ gridTemplateColumns: `36px repeat(${cols.length}, minmax(0, 1fr))` }} role="table" aria-label={label}>
        <span role="presentation" />
        {cols.map((c, j) => <span key={`c${j}`} className="ck-hm__col" role="columnheader">{j % colEvery === 0 ? c : ''}</span>)}
        {rows.map((r, i) => [
          <span key={`r${i}`} className="ck-hm__row" role="rowheader">{r}</span>,
          ...cols.map((c, j) => {
            const v = (values[i] || [])[j] || 0;
            const k = `${i}-${j}`;
            return (
              <span
                key={k}
                role="cell"
                tabIndex={0}
                className={`ck-hm__cell ck-hm__cell--${lvl(v)}`}
                aria-label={`${r} ${c}: ${formatValue(v)} ${unit}`}
                onMouseEnter={() => setHv(k)}
                onMouseLeave={() => setHv(null)}
                onFocus={() => setHv(k)}
                onBlur={() => setHv(null)}
              >
                {hv === k ? <span className="ck-tip" role="tooltip"><strong>{`${r}, ${c}`}</strong><span>{`${formatValue(v)} ${unit}`}</span></span> : null}
              </span>
            );
          }),
        ])}
      </div>
      <figcaption className="ck-hm__legend">
        <span>Menos</span>
        {[0, 1, 2, 3, 4, 5].map((i) => <span key={i} className={`ck-hm__cell ck-hm__cell--${i}`} aria-hidden />)}
        <span>Mais</span>
      </figcaption>
    </figure>
  );
}

export function Meter({ segments = [], total, format }) {
  const t = total || segments.reduce((a, x) => a + x.value, 0) || 1;
  const fmt = format || num;
  return (
    <div className="ck-meter">
      <div className="ck-meter__track" role="img" aria-label={segments.map((x) => `${x.label} ${fmt(x.value)}`).join(', ')}>
        {segments.map((x, i) => (x.value > 0 ? <span key={i} className={`ck-meter__seg ck-meter__seg--${x.tone}`} style={{ width: `${(x.value / t) * 100}%` }} title={`${x.label}: ${fmt(x.value)}`} /> : null))}
      </div>
      <ul className="ck-meter__legend">
        {segments.map((x, i) => (
          <li key={i}>
            <span className={`ck-meter__key ck-meter__seg--${x.tone}`} aria-hidden />
            <span className="ck-meter__l">{x.label}{x.note ? <span>{x.note}</span> : null}</span>
            <span className="ck-meter__v">{fmt(x.value)}<span>{Math.round((x.value / t) * 100)}%</span></span>
          </li>
        ))}
      </ul>
    </div>
  );
}
