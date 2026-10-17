/* AppCheckin — componentes do design system do painel (artefato "AppCheckin" / painel).
   Portado de project/components/bundle.js para módulo ES. Estilos: src/theme/ck.css + ck-tokens.css.
   Extensões locais: ícones file-text, building-2, menu, unlock e layers; AppHeader com `leading`,
   `notifications={false}` e trilha com onClick. Matricula: `enrollment.statusLabel/statusTone`,
   `actions` (substitui os botões do cabeçalho), `notices` (abaixo do cabeçalho), `children` (no fim),
   fatura com `canSettle: false` + `settleNote`, e `invoiceMenu={false}` (sem editar/excluir). */
/* @ds-bundle: {"format":4,"namespace":"AppCheckin","components":[{"name":"AppShell"},{"name":"Sidebar"},{"name":"AppHeader"},{"name":"PageHeader"},{"name":"Button"},{"name":"IconButton"},{"name":"Switch"},{"name":"SegmentedControl"},{"name":"TextField"},{"name":"SecretField"},{"name":"Fieldset"},{"name":"Tabs"},{"name":"ChoiceCard"},{"name":"Card"},{"name":"ProviderTile"},{"name":"ProviderCard"},{"name":"ProviderLogo"},{"name":"SaveBar"},{"name":"ActionMenu"},{"name":"Modal"},{"name":"SelectField"},{"name":"Matricula"},{"name":"PacoteMatricula"},{"name":"DescontoModal"},{"name":"Badge"},{"name":"Callout"},{"name":"Avatar"},{"name":"Icon"}]} */
import React from 'react';


  var h = React.createElement;
  var useState = React.useState, useEffect = React.useEffect, useRef = React.useRef, useId = React.useId;

  function cx() { return Array.prototype.filter.call(arguments, Boolean).join(' '); }
  function omit(o, keys) { var r = {}; for (var k in o) { if (keys.indexOf(k) < 0) r[k] = o[k]; } return r; }
  function useControlled(value, def, onChange) {
    var s = useState(def), isC = value !== undefined;
    return [isC ? value : s[0], function (v) { if (!isC) s[1](v); if (onChange) onChange(v); }];
  }

  /* Icons — Lucide geometry (ISC), 24px grid, stroke 1.75 by default */
  function P(d) { return ['path', { d: d }]; }
  function Ci(x, y, r) { return ['circle', { cx: x, cy: y, r: r }]; }
  function R(x, y, w, hh, rx) { return ['rect', { x: x, y: y, width: w, height: hh, rx: rx }]; }
  var ICONS = {
    'file-text': [P('M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z'), P('M14 2v4a2 2 0 0 0 2 2h4'), P('M10 9H8'), P('M16 13H8'), P('M16 17H8')],
    'building-2': [P('M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z'), P('M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2'), P('M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2'), P('M10 6h4'), P('M10 10h4'), P('M10 14h4'), P('M10 18h4')],
    'menu': [P('M4 12h16'), P('M4 6h16'), P('M4 18h16')],
    'unlock': [R(3, 11, 18, 11, 2), P('M7 11V7a5 5 0 0 1 9.9-1')],
    'layers': [P('m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z'), P('m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65'), P('m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65')],
    'layout-dashboard': [R(3, 3, 7, 9, 1), R(14, 3, 7, 5, 1), R(14, 12, 7, 9, 1), R(3, 16, 7, 5, 1)],
    'wrench': [P('M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z')],
    'wallet': [P('M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1'), P('M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4')],
    'dumbbell': [P('m6.5 6.5 11 11'), P('m21 21-1-1'), P('m3 3 1 1'), P('m18 22 4-4'), P('m2 6 4-4'), P('m3 10 7-7'), P('m14 21 7-7')],
    'settings': [P('M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z'), Ci(12, 12, 3)],
    'credit-card': [R(2, 5, 20, 14, 2), P('M2 10h20')],
    'message-circle': [P('M7.9 20A9 9 0 1 0 4 16.1L2 22Z')],
    'shield-check': [P('M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z'), P('m9 12 2 2 4-4')],
    'chevron-down': [P('m6 9 6 6 6-6')],
    'chevron-right': [P('m9 18 6-6-6-6')],
    'panel-left': [R(3, 3, 18, 18, 2), P('M9 3v18')],
    'eye': [P('M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z'), Ci(12, 12, 3)],
    'eye-off': [P('M9.88 9.88a3 3 0 1 0 4.24 4.24'), P('M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68'), P('M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61'), P('M2 2l20 20')],
    'copy': [R(8, 8, 14, 14, 2), P('M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2')],
    'zap': [P('M13 2 3 14h9l-1 8 10-12h-9l1-8z')],
    'save': [P('M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z'), P('M17 21v-8H7v8'), P('M7 3v5h8')],
    'log-out': [P('M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4'), P('m16 17 5-5-5-5'), P('M21 12H9')],
    'bell': [P('M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9'), P('M10.3 21a1.94 1.94 0 0 0 3.4 0')],
    'search': [Ci(11, 11, 8), P('m21 21-4.3-4.3')],
    'check': [P('M20 6 9 17l-5-5')],
    'check-circle': [Ci(12, 12, 10), P('m9 12 2 2 4-4')],
    'activity': [P('M22 12h-4l-3 9L9 3l-3 9H2')],
    'alert-triangle': [P('m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z'), P('M12 9v4'), P('M12 17h.01')],
    'info': [Ci(12, 12, 10), P('M12 16v-4'), P('M12 8h.01')],
    'help-circle': [Ci(12, 12, 10), P('M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3'), P('M12 17h.01')],
    'user': [P('M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2'), Ci(12, 7, 4)],
    'key': [Ci(7.5, 15.5, 5.5), P('m21 2-9.6 9.6'), P('m15.5 7.5 3 3L22 7l-3-3')],
    'lock': [R(3, 11, 18, 11, 2), P('M7 11V7a5 5 0 0 1 10 0v4')],
    'pencil': [P('M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z')],
    'x': [P('M18 6 6 18'), P('m6 6 12 12')],
    'loader': [P('M21 12a9 9 0 1 1-6.22-8.56')],
    'power': [P('M12 2v10'), P('M18.4 6.6a9 9 0 1 1-12.77.04')],
    'plus': [P('M5 12h14'), P('M12 5v14')],
    'arrow-left': [P('m12 19-7-7 7-7'), P('M19 12H5')],
    'calendar': [R(3, 4, 18, 18, 2), P('M16 2v4'), P('M8 2v4'), P('M3 10h18')],
    'clock': [Ci(12, 12, 10), P('M12 6v6l4 2')],
    'waves': [P('M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1'), P('M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1'), P('M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1')],
    'more-horizontal': [Ci(12, 12, 1), Ci(19, 12, 1), Ci(5, 12, 1)],
    'refresh-cw': [P('M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8'), P('M21 3v5h-5'), P('M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16'), P('M8 16H3v5')],
    'repeat': [P('m17 2 4 4-4 4'), P('M3 11v-1a4 4 0 0 1 4-4h14'), P('m7 22-4-4 4-4'), P('M21 13v1a4 4 0 0 1-4 4H3')],
    'trash': [P('M3 6h18'), P('M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6'), P('M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2')],
    'percent': [P('M19 5 5 19'), Ci(6.5, 6.5, 2.5), Ci(17.5, 17.5, 2.5)],
    'receipt': [P('M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z'), P('M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8'), P('M12 17.5v-11')],
    'ban': [Ci(12, 12, 10), P('m4.9 4.9 14.2 14.2')],
    'package': [P('m7.5 4.27 9 5.15'), P('M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z'), P('m3.3 7 8.7 5 8.7-5'), P('M12 22V12')],
    'users': [P('M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'), Ci(9, 7, 4), P('M22 21v-2a4 4 0 0 0-3-3.87'), P('M16 3.13a4 4 0 0 1 0 7.75')],
    'user-check': [P('M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'), Ci(9, 7, 4), P('m16 11 2 2 4-4')],
    'arrow-right': [P('M5 12h14'), P('m12 5 7 7-7 7')]
  };

  function Icon(p) {
    var parts = ICONS[p.name] || [];
    var size = p.size || 18;
    return h('svg', {
      width: size, height: size, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
      strokeWidth: p.strokeWidth || 1.75, strokeLinecap: 'round', strokeLinejoin: 'round',
      className: cx('ck-icon', p.className), role: p.label ? 'img' : undefined,
      'aria-label': p.label, 'aria-hidden': p.label ? undefined : true, focusable: 'false'
    }, parts.map(function (e, i) { return h(e[0], Object.assign({ key: i }, e[1])); }));
  }
  Icon.names = Object.keys(ICONS);

  function Button(p) {
    var variant = p.variant || 'secondary', size = p.size || 'md';
    var rest = omit(p, ['variant', 'size', 'icon', 'iconRight', 'loading', 'block', 'className', 'children']);
    var isz = size === 'sm' ? 15 : 16;
    return h('button', Object.assign({ type: 'button' }, rest, {
      className: cx('ck-btn', 'ck-btn--' + variant, 'ck-btn--' + size, p.block && 'ck-btn--block', p.loading && 'is-loading', p.className),
      disabled: p.disabled || p.loading, 'aria-busy': p.loading || undefined
    }),
      p.loading ? h(Icon, { name: 'loader', size: isz, className: 'ck-spin' }) : (p.icon ? h(Icon, { name: p.icon, size: isz }) : null),
      p.children != null ? h('span', null, p.children) : null,
      p.iconRight ? h(Icon, { name: p.iconRight, size: isz }) : null);
  }

  function IconButton(p) {
    var rest = omit(p, ['icon', 'label', 'variant', 'size', 'dot', 'className', 'iconSize']);
    return h('button', Object.assign({ type: 'button', 'aria-label': p.label, title: p.label }, rest, {
      className: cx('ck-iconbtn', p.size === 'sm' && 'ck-iconbtn--sm', p.variant === 'secondary' && 'ck-iconbtn--secondary', p.className)
    }), h(Icon, { name: p.icon, size: p.iconSize || (p.size === 'sm' ? 16 : 18) }), p.dot ? h('span', { className: 'ck-iconbtn__dot' }) : null);
  }

  function Badge(p) {
    return h('span', { className: cx('ck-badge', 'ck-badge--' + (p.tone || 'neutral'), p.className) },
      p.icon ? h(Icon, { name: p.icon, size: 12, strokeWidth: 2.25 }) : (p.dot ? h('span', { className: 'ck-badge__dot', 'aria-hidden': true }) : null),
      p.children);
  }

  function initialsOf(name) {
    if (!name) return '';
    var w = String(name).trim().split(/\s+/);
    return ((w[0] || '')[0] + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase();
  }
  function Avatar(p) {
    return h('span', { className: cx('ck-avatar', 'ck-avatar--' + (p.size || 'md'), p.className), 'aria-hidden': p.decorative ? true : undefined, title: p.decorative ? undefined : p.name },
      p.src ? h('img', { src: p.src, alt: p.name || '' }) : (p.initials || initialsOf(p.name)));
  }

  function Switch(p) {
    var st = useControlled(p.checked, !!p.defaultChecked, p.onChange);
    var id = useId();
    var btn = h('button', {
      type: 'button', role: 'switch', id: id, 'aria-checked': st[0], disabled: p.disabled,
      'aria-label': p.label ? undefined : p.ariaLabel,
      'aria-describedby': p.description ? id + '-d' : undefined,
      className: cx('ck-switch', st[0] && 'is-on'), onClick: function () { st[1](!st[0]); }
    }, h('span', { className: 'ck-switch__thumb' }));
    if (!p.label) return btn;
    return h('div', { className: 'ck-switchfield' },
      h('label', { htmlFor: id, style: { cursor: 'pointer' } },
        h('span', { className: 'ck-switchfield__label' }, p.label),
        p.description ? h('span', { className: 'ck-switchfield__desc', id: id + '-d' }, p.description) : null),
      btn);
  }

  function SegmentedControl(p) {
    var opts = p.options || [];
    var st = useControlled(p.value, p.defaultValue !== undefined ? p.defaultValue : (opts[0] && opts[0].value), p.onChange);
    var refs = useRef([]);
    function onKey(e, i) {
      var d = e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : (e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? -1 : 0);
      if (!d) return;
      e.preventDefault();
      var n = (i + d + opts.length) % opts.length;
      st[1](opts[n].value);
      if (refs.current[n]) refs.current[n].focus();
    }
    return h('div', { role: 'radiogroup', 'aria-label': p.label, className: cx('ck-seg', p.block && 'ck-seg--block', p.className) },
      opts.map(function (o, i) {
        var on = o.value === st[0];
        return h('button', {
          key: o.value, type: 'button', role: 'radio', 'aria-checked': on, tabIndex: on ? 0 : -1,
          ref: function (el) { refs.current[i] = el; },
          className: 'ck-seg__opt', onClick: function () { st[1](o.value); }, onKeyDown: function (e) { onKey(e, i); }
        },
          o.icon ? h(Icon, { name: o.icon, size: 16 }) : null,
          h('span', null, o.label),
          o.status ? h('span', { className: 'ck-seg__status ck-seg__status--' + o.status, title: o.statusLabel },
            h(Icon, { name: o.status === 'success' ? 'check-circle' : 'alert-triangle', size: 14, strokeWidth: 2 }),
            o.statusLabel ? h('span', { className: 'ck-sr' }, o.statusLabel) : null) : null);
      }));
  }

  function FieldShell(p, id, control) {
    return h('div', { className: cx('ck-field', p.className) },
      (p.label || p.labelAside) ? h('div', { className: 'ck-field__top' },
        p.label ? h('label', { className: 'ck-field__label', htmlFor: id }, p.label) : h('span'),
        p.labelAside || null) : null,
      control,
      p.error ? h('span', { className: 'ck-field__error', id: id + '-m' }, h(Icon, { name: 'alert-triangle', size: 13 }), p.error)
        : (p.hint ? h('span', { className: 'ck-field__hint', id: id + '-m' }, p.hint) : null));
  }

  function TextField(p) {
    var auto = useId(), id = p.id || auto;
    var rest = omit(p, ['label', 'hint', 'error', 'mono', 'icon', 'suffix', 'labelAside', 'className', 'id']);
    var control = h('div', { className: cx('ck-control', p.error && 'is-invalid', p.readOnly && 'is-readonly') },
      p.icon ? h(Icon, { name: p.icon, size: 16, className: 'ck-control__icon' }) : null,
      h('input', Object.assign({ id: id, className: cx('ck-control__input', p.mono && 'ck-control__input--mono'), 'aria-invalid': p.error ? true : undefined, 'aria-describedby': (p.hint || p.error) ? id + '-m' : undefined, spellCheck: p.mono ? false : undefined }, rest)),
      p.suffix ? h('div', { className: 'ck-control__actions' }, p.suffix) : null);
    return FieldShell(p, id, control);
  }

  function SecretField(p) {
    var id = useId();
    var configured = !!p.configured;
    var ed = useState(!configured), editing = ed[0];
    var rv = useState(false), reveal = rv[0];
    var val = useControlled(p.value, '', p.onChange);
    var copied = useState(false);
    var inputRef = useRef(null);
    useEffect(function () { if (editing && configured && inputRef.current) inputRef.current.focus(); }, [editing]);
    var control;
    if (configured && !editing) {
      control = h('div', { className: 'ck-control is-readonly', id: id, role: 'group', 'aria-label': p.label },
        h(Icon, { name: 'lock', size: 15, className: 'ck-control__icon' }),
        h('span', { className: 'ck-control__value' }, p.maskedValue || '••••••••••••'),
        h('div', { className: 'ck-control__actions' },
          p.copyable && p.onCopy ? h(IconButton, { icon: copied[0] ? 'check' : 'copy', label: copied[0] ? 'Copiado' : 'Copiar', size: 'sm', onClick: function () { p.onCopy(); copied[1](true); setTimeout(function () { copied[1](false); }, 1400); } }) : null,
          h(Button, { size: 'sm', variant: 'ghost', icon: 'pencil', onClick: function () { ed[1](true); } }, 'Substituir')));
    } else {
      control = h('div', { className: cx('ck-control', p.error && 'is-invalid') },
        h(Icon, { name: 'key', size: 15, className: 'ck-control__icon' }),
        h('input', {
          id: id, ref: inputRef, type: reveal ? 'text' : 'password', autoComplete: 'off', spellCheck: false,
          className: 'ck-control__input ck-control__input--mono', placeholder: p.placeholder, value: val[0],
          onChange: function (e) { val[1](e.target.value); }, 'aria-describedby': (p.hint || p.error) ? id + '-m' : undefined
        }),
        h('div', { className: 'ck-control__actions' },
          h(IconButton, { icon: reveal ? 'eye-off' : 'eye', label: reveal ? 'Ocultar valor' : 'Mostrar valor', size: 'sm', onClick: function () { rv[1](!reveal); } }),
          configured ? h(Button, { size: 'sm', variant: 'ghost', onClick: function () { ed[1](false); rv[1](false); val[1](''); } }, 'Cancelar') : null));
    }
    return FieldShell(p, id, control);
  }

  function Fieldset(p) {
    return h('fieldset', { className: cx('ck-fieldset', p.current && 'is-current', p.className) },
      h('div', { className: 'ck-fieldset__head' },
        h('legend', { className: 'ck-fieldset__title', style: { float: 'left' } }, p.title),
        p.badges || null,
        p.aside ? h('div', { className: 'ck-fieldset__aside' }, p.aside) : null),
      p.description ? h('p', { className: 'ck-fieldset__desc' }, p.description) : null,
      p.children);
  }

  function Callout(p) {
    var tone = p.tone || 'info';
    var icon = p.icon || ({ info: 'info', warning: 'alert-triangle', success: 'check-circle', danger: 'alert-triangle', neutral: 'info' })[tone];
    return h('div', { className: cx('ck-callout', 'ck-callout--' + tone, p.className), role: tone === 'danger' ? 'alert' : 'note' },
      h(Icon, { name: icon, size: 16, className: 'ck-callout__icon' }),
      h('div', { style: { flex: 1, minWidth: 0 } }, p.title ? h('span', { className: 'ck-callout__title' }, p.title) : null, p.children ? h('span', { className: 'ck-callout__text' }, p.children) : null),
      p.action ? h('div', { className: 'ck-callout__action' }, p.action) : null);
  }

  function Card(p) {
    var hasHead = p.title || p.actions;
    return h('section', { className: cx('ck-card', p.className), 'aria-label': typeof p.title === 'string' ? p.title : undefined },
      hasHead ? h('header', { className: 'ck-card__head' },
        p.leading || null,
        h('div', { className: 'ck-card__titles' },
          p.title ? h('h2', { className: 'ck-card__title' }, p.title) : null,
          p.description ? h('p', { className: 'ck-card__desc' }, p.description) : null,
          p.meta || null),
        p.actions || null) : null,
      p.children ? h('div', { className: 'ck-card__body' }, p.children) : null,
      p.footer ? h('footer', { className: 'ck-card__foot' }, p.footer) : null);
  }

  function ProviderCard(p) {
    var st = useControlled(p.enabled, p.defaultEnabled !== undefined ? !!p.defaultEnabled : true, p.onEnabledChange);
    var on = st[0];
    return h('section', { className: cx('ck-card ck-provider', p.className), 'aria-label': p.name },
      h('header', { className: 'ck-card__head' },
        h(ProviderLogo, { src: p.logo, name: p.name, monogram: p.monogram }),
        h('div', { className: 'ck-card__titles' },
          h('h2', { className: 'ck-card__title' }, p.name),
          p.description ? h('p', { className: 'ck-card__desc' }, p.description) : null,
          h('div', { className: 'ck-provider__meta' },
            on ? h(Badge, { tone: 'success', dot: true }, 'Ativo no app') : h(Badge, { tone: 'neutral', dot: true }, 'Desativado'),
            on && p.badges ? p.badges : null)),
        h('div', { className: 'ck-provider__toggle' },
          h(Switch, { checked: on, onChange: st[1], ariaLabel: (on ? 'Desativar ' : 'Ativar ') + p.name }))),
      on ? h('div', { className: 'ck-card__body' }, h('div', { className: 'ck-provider__body ck-stack', style: { gap: 'var(--space-5)' } }, p.children)) :
        h('div', { className: 'ck-provider__off' }, h(Icon, { name: 'info', size: 16 }), p.offNote || ('Os alunos não verão ' + p.name + ' como forma de pagamento no app.')),
      on && p.footer ? h('footer', { className: 'ck-card__foot' }, p.footer) : null);
  }


  function ProviderLogo(p) {
    return h('span', { className: cx('ck-logo', 'ck-logo--' + (p.size || 'md'), p.className), 'aria-hidden': true },
      p.src ? h('img', { src: p.src, alt: '' }) : h('span', { className: 'ck-logo__mono' }, p.monogram || initialsOf(p.name)));
  }

  function Tabs(p) {
    var tabs = p.tabs || [];
    var st = useControlled(p.value, p.defaultValue !== undefined ? p.defaultValue : (tabs[0] && tabs[0].value), p.onChange);
    var refs = useRef([]);
    function onKey(e, i) {
      var d = e.key === 'ArrowRight' ? 1 : (e.key === 'ArrowLeft' ? -1 : 0);
      if (!d) return; e.preventDefault();
      var n = (i + d + tabs.length) % tabs.length; st[1](tabs[n].value); if (refs.current[n]) refs.current[n].focus();
    }
    return h('div', { role: 'tablist', 'aria-label': p.label, className: cx('ck-tabs', p.className) }, tabs.map(function (t, i) {
      var on = t.value === st[0];
      return h('button', { key: t.value, type: 'button', role: 'tab', 'aria-selected': on, tabIndex: on ? 0 : -1, className: 'ck-tab',
        ref: function (el) { refs.current[i] = el; }, onClick: function () { st[1](t.value); }, onKeyDown: function (e) { onKey(e, i); } },
        h('span', null, t.label),
        t.status ? h('span', { className: 'ck-tab__status ck-tab__status--' + t.status, title: t.statusLabel },
          h(Icon, { name: t.status === 'success' ? 'check-circle' : 'alert-triangle', size: 15, strokeWidth: 2 }),
          t.statusLabel ? h('span', { className: 'ck-sr' }, t.statusLabel) : null) : null);
    }));
  }

  function ChoiceCard(p) {
    return h('button', { type: 'button', role: 'radio', 'aria-checked': !!p.checked, className: cx('ck-choice', p.className), onClick: p.onSelect, disabled: p.disabled },
      h('span', { className: 'ck-choice__radio', 'aria-hidden': true }),
      h('span', { className: 'ck-choice__title' }, p.title),
      p.description ? h('span', { className: 'ck-choice__desc' }, p.description) : null,
      p.status ? h('span', { className: 'ck-choice__status ck-choice__status--' + p.status },
        h(Icon, { name: p.status === 'success' ? 'check-circle' : 'alert-triangle', size: 15, strokeWidth: 2 }), p.statusLabel) : null);
  }
  function ChoiceGroup(p) {
    return h('div', { role: 'radiogroup', 'aria-label': p.label, className: cx('ck-choices', p.className) }, p.children);
  }

  function ProviderTile(p) {
    return h('button', { type: 'button', role: 'radio', 'aria-checked': !!p.selected, className: cx('ck-tile', p.enabled === false && 'is-off', p.className), onClick: p.onSelect },
      h(ProviderLogo, { src: p.logo, name: p.name, monogram: p.monogram }),
      h('span', { style: { minWidth: 0 } },
        h('span', { className: 'ck-tile__name' }, p.name),
        h('span', { className: 'ck-tile__status' },
          p.enabled === false ? h(Badge, { dot: true }, 'Desativado') : h(Badge, { tone: 'success', dot: true }, 'Ativo no app'),
          p.enabled === false ? null : (p.badges || null))),
      p.footnote ? h('span', { className: 'ck-tile__foot' }, p.footIcon ? h(Icon, { name: p.footIcon, size: 15 }) : null, p.footnote) : null,
      h('span', { className: 'ck-tile__check', 'aria-hidden': true }, h(Icon, { name: 'check', size: 14, strokeWidth: 3 })));
  }

  function SaveBar(p) {
    if (!p.visible) return null;
    return h('div', { className: cx('ck-savebar', p.className), role: 'status' },
      h('span', { className: 'ck-savebar__dot', 'aria-hidden': true }),
      h('span', { className: 'ck-savebar__text' }, p.message || 'Você tem alterações não salvas'),
      h(Button, { variant: 'ghost', onClick: p.onDiscard }, p.discardLabel || 'Descartar'),
      h(Button, { variant: 'primary', icon: 'save', loading: p.saving, onClick: p.onSave }, p.saving ? 'Salvando…' : (p.saveLabel || 'Salvar alterações')));
  }


  function ActionMenu(p) {
    var o = useState(false), open = o[0];
    var ref = useRef(null);
    useEffect(function () {
      if (!open) return;
      function off(e) { if (ref.current && !ref.current.contains(e.target)) o[1](false); }
      function esc(e) { if (e.key === 'Escape') o[1](false); }
      document.addEventListener('mousedown', off); document.addEventListener('keydown', esc);
      return function () { document.removeEventListener('mousedown', off); document.removeEventListener('keydown', esc); };
    }, [open]);
    return h('div', { className: 'ck-actionmenu', ref: ref },
      h(IconButton, { icon: p.icon || 'more-horizontal', label: p.label || 'Mais ações', size: p.size || 'sm', variant: p.variant, 'aria-haspopup': 'menu', 'aria-expanded': open, onClick: function () { o[1](!open); } }),
      open ? h('div', { className: 'ck-menu ck-menu--compact', role: 'menu' }, (p.items || []).map(function (it, i) {
        if (it === '-') return h('div', { key: i, className: 'ck-menu__sep', role: 'separator' });
        return h('button', { key: i, type: 'button', role: 'menuitem', className: cx('ck-menu__item', it.danger && 'ck-menu__item--danger'), disabled: it.disabled,
          onClick: function () { o[1](false); if (it.onClick) it.onClick(); } }, it.icon ? h(Icon, { name: it.icon, size: 16 }) : null, it.label);
      })) : null);
  }

  /* ---------- Matrícula: tela completa de uma matrícula ---------- */
  var BRL = typeof Intl !== 'undefined' ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }) : null;
  function money(v) { return BRL ? BRL.format(v || 0) : 'R$ ' + (v || 0).toFixed(2); }
  function pd(iso) { if (!iso) return null; var a = String(iso).split('-'); return new Date(+a[0], +a[1] - 1, +a[2]); }
  function fdate(iso) { if (!iso) return '—'; var a = String(iso).split('-'); return a[2] + '/' + a[1] + '/' + a[0]; }
  function dayDiff(a, b) { return Math.round((pd(b) - pd(a)) / 86400000); }
  var ENROLL_STATUS = { pendente: ['warning', 'Pagamento pendente'], ativa: ['success', 'Ativa'], bloqueada: ['danger', 'Bloqueada'], cancelada: ['neutral', 'Cancelada'] };
  var INV_STATUS = { aguardando: ['warning', 'Aguardando'], pago: ['success', 'Pago'], atrasado: ['danger', 'Atrasado'], cancelado: ['neutral', 'Cancelado'] };

  function MtPlanCard(p) {
    var e = p.enrollment, today = p.today;
    var total = Math.max(1, dayDiff(e.startDate, e.accessUntil));
    var left = Math.max(0, dayDiff(today, e.accessUntil));
    var used = Math.min(total, Math.max(0, total - left));
    var pct = Math.round(used / total * 100);
    var c = e.cycle || {};
    return h('section', { className: 'ck-mt-plan', 'aria-label': 'Plano' },
      h('div', { className: 'ck-mt-plan__top' },
        h('span', { className: 'ck-mt-plan__icon', 'aria-hidden': true }, h(Icon, { name: e.modalityIcon || 'dumbbell', size: 20, strokeWidth: 2 })),
        h('div', { style: { flex: 1, minWidth: 0 } },
          h('h2', { className: 'ck-mt-plan__name' }, e.modality),
          h('span', { className: 'ck-mt-plan__freq' }, e.frequency)),
        c.label ? h('span', { className: 'ck-mt-plan__chip' }, c.label) : null),
      h('div', { className: 'ck-mt-plan__price' },
        h('span', { className: 'ck-mt-plan__value' }, money(c.amount)),
        h('span', { className: 'ck-mt-plan__per' }, c.per || 'por mês')),
      c.monthlyEquivalent != null && c.monthlyEquivalent !== c.amount ? h('p', { className: 'ck-mt-plan__note' }, 'Equivale a ' + money(c.monthlyEquivalent) + ' por mês') : null,
      h('div', { className: 'ck-mt-plan__access' },
        h('div', { className: 'ck-mt-plan__row' },
          h('span', null, 'Acesso'),
          h('strong', null, left === 0 ? 'Encerra hoje' : left + (left === 1 ? ' dia restante' : ' dias restantes'))),
        h('div', { className: 'ck-mt-plan__track', role: 'progressbar', 'aria-label': 'Período de acesso usado', 'aria-valuenow': pct, 'aria-valuemin': 0, 'aria-valuemax': 100 },
          h('span', { className: 'ck-mt-plan__fill', style: { width: Math.max(pct, 2) + '%' } })),
        h('div', { className: 'ck-mt-plan__row ck-mt-plan__dates' }, h('span', null, fdate(e.startDate)), h('span', null, fdate(e.accessUntil)))),
      h('dl', { className: 'ck-mt-plan__facts' },
        h('div', null, h('dt', null, 'Duração'), h('dd', null, e.duration)),
        h('div', null, h('dt', null, 'Próximo vencimento'), h('dd', null, fdate(c.nextDue)))));
  }

  function MtFinance(p) {
    var inv = (p.invoices || []).filter(function (i) { return i.status !== 'cancelado'; });
    var sum = function (st) { return inv.filter(function (i) { return i.status === st; }).reduce(function (a, i) { return a + (i.amount || 0); }, 0); };
    var paid = sum('pago'), wait = sum('aguardando'), late = sum('atrasado'), total = paid + wait + late;
    var pc = function (v) { return total ? (v / total * 100) : 0; };
    var rows = [['success', 'Pago', paid], ['warning', 'Aguardando', wait], ['danger', 'Atrasado', late]];
    return h('section', { className: 'ck-card ck-mt-box', 'aria-label': 'Resumo financeiro' },
      h('div', { className: 'ck-mt-box__head' },
        h('h2', { className: 'ck-mt-box__title' }, 'Resumo financeiro'),
        h('span', { className: 'ck-mt-box__aside' }, Math.round(pc(paid)) + '% pago')),
      h('div', { className: 'ck-mt-bar', role: 'img', 'aria-label': rows.map(function (r) { return r[1] + ' ' + money(r[2]); }).join(', ') },
        rows.map(function (r) { return r[2] > 0 ? h('span', { key: r[1], className: 'ck-mt-bar__seg ck-mt-bar__seg--' + r[0], style: { width: pc(r[2]) + '%' } }) : null; })),
      h('ul', { className: 'ck-mt-legend' }, rows.map(function (r) {
        return h('li', { key: r[1], className: r[2] === 0 ? 'is-zero' : null },
          h('span', { className: 'ck-mt-legend__key ck-mt-legend__key--' + r[0], 'aria-hidden': true }),
          h('span', { className: 'ck-mt-legend__label' }, r[1]),
          h('span', { className: 'ck-mt-legend__value' }, money(r[2])));
      }),
        h('li', { className: 'ck-mt-legend__total' }, h('span', { className: 'ck-mt-legend__label' }, 'Total das parcelas ativas'), h('span', { className: 'ck-mt-legend__value' }, money(total)))));
  }

  function discountValue(d) { return d.mode === 'percentual' ? String(d.value).replace('.', ',') + '%' : money(d.value); }
  var DISCOUNT_TYPE = { recorrente: 'Recorrente', primeira: '1ª mensalidade' };

  function MtDiscounts(p) {
    var d = p.discounts || [];
    var active = d.filter(function (x) { return x.active !== false; }).length;
    return h('section', { className: 'ck-card ck-mt-box', 'aria-label': 'Descontos' },
      h('div', { className: 'ck-mt-box__head' },
        h('div', null, h('h2', { className: 'ck-mt-box__title' }, 'Descontos'),
          d.length ? h('span', { className: 'ck-mt-box__sub' }, active + (active === 1 ? ' ativo' : ' ativos')) : null),
        d.length ? h(Button, { size: 'sm', variant: 'ghost', icon: 'plus', onClick: p.onAdd }, 'Adicionar') : null),
      d.length ? h('ul', { className: 'ck-mt-discounts' }, d.map(function (x) {
        return h('li', { key: x.id, className: x.active === false ? 'is-off' : null },
          h('div', { className: 'ck-mt-discounts__top' },
            h('span', { className: 'ck-mt-discounts__value' }, '− ' + discountValue(x)),
            h('span', { className: 'ck-mt-discounts__tags' },
              h(Badge, { tone: 'brand' }, DISCOUNT_TYPE[x.type] || x.type),
              x.active === false ? h(Badge, null, 'Encerrado') : null),
            h(ActionMenu, { label: 'Ações do desconto', items: [
              { label: 'Editar desconto', icon: 'pencil', onClick: function () { p.onEdit && p.onEdit(x); } }, '-',
              { label: 'Remover desconto', icon: 'trash', danger: true, onClick: function () { p.onRemove && p.onRemove(x); } }] })),
          h('p', { className: 'ck-mt-discounts__reason' }, x.reason),
          h('div', { className: 'ck-mt-discounts__meta' },
            h('span', null, h(Icon, { name: 'calendar', size: 14 }), x.end ? fdate(x.start) + ' a ' + fdate(x.end) : 'Desde ' + fdate(x.start) + ', sem fim'),
            x.authorizedBy ? h('span', null, h(Icon, { name: 'user-check', size: 14 }), 'Autorizado por ' + x.authorizedBy) : null,
            x.remaining != null ? h('span', null, h(Icon, { name: 'receipt', size: 14 }), x.remaining + (x.remaining === 1 ? ' parcela restante' : ' parcelas restantes')) : null));
      })) :
        h('div', { className: 'ck-mt-empty' },
          h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: 'percent', size: 20 })),
          h('strong', null, 'Nenhum desconto ativo'),
          h('p', null, 'Descontos cadastrados entram automaticamente nas próximas parcelas.'),
          h(Button, { size: 'sm', variant: 'secondary', icon: 'plus', onClick: p.onAdd }, 'Adicionar desconto')));
  }

  function MtInvoices(p) {
    var f = useState('ativas'), filter = f[0];
    var counts = p.counts || {};
    var list = (p.invoices || []).filter(function (i) { return filter === 'canceladas' ? i.status === 'cancelado' : i.status !== 'cancelado'; });
    return h('section', { className: 'ck-card ck-mt-invoices', 'aria-label': 'Faturas' },
      h('div', { className: 'ck-mt-invoices__head' },
        h('div', { style: { flex: 1, minWidth: 200 } },
          h('h2', { className: 'ck-mt-box__title' }, 'Faturas'),
          h('p', { className: 'ck-mt-invoices__sub' }, counts.overdue ? h(Badge, { tone: 'danger', dot: true }, counts.overdue + (counts.overdue === 1 ? ' atrasada' : ' atrasadas')) : h(Badge, { tone: 'success', icon: 'check' }, 'Nenhuma atrasada'))),
        h(SegmentedControl, { label: 'Filtrar faturas', value: filter, onChange: f[1], options: [
          { value: 'ativas', label: 'Ativas ' + (counts.active != null ? counts.active : '') },
          { value: 'canceladas', label: 'Canceladas ' + (counts.cancelled != null ? counts.cancelled : '') }] }),
        h(IconButton, { icon: 'refresh-cw', label: 'Atualizar líquido no Asaas', variant: 'secondary', onClick: p.onReload })),
      h('div', { className: 'ck-mt-table-wrap' },
        h('table', { className: 'ck-mt-table' },
          h('thead', null, h('tr', null,
            h('th', { scope: 'col' }, 'Parcela'), h('th', { scope: 'col' }, 'Vencimento'), h('th', { scope: 'col', className: 'is-num' }, 'Valor'),
            h('th', { scope: 'col' }, 'Pagamento'), h('th', { scope: 'col' }, 'Status'), h('th', { scope: 'col' }, h('span', { className: 'ck-sr' }, 'Ações')))),
          h('tbody', null, list.length ? list.map(function (i) {
            var st = INV_STATUS[i.status] || INV_STATUS.aguardando;
            var due = i.status === 'aguardando' || i.status === 'atrasado';
            var dueNote = due && p.today ? (function () { var d = dayDiff(p.today, i.dueDate); return d === 0 ? 'Vence hoje' : d > 0 ? 'Em ' + d + (d === 1 ? ' dia' : ' dias') : 'Há ' + (-d) + (d === -1 ? ' dia' : ' dias'); })() : null;
            return h('tr', { key: i.id, className: due ? 'is-due' : null },
              h('td', null, h('span', { className: 'ck-mt-cell__main' }, 'Parcela ' + i.number), h('span', { className: 'ck-mt-cell__sub' }, 'Fatura ' + i.id)),
              h('td', null, h('span', { className: 'ck-mt-cell__main' }, fdate(i.dueDate)), dueNote ? h('span', { className: cx('ck-mt-cell__sub', i.status === 'atrasado' && 'is-late') }, dueNote) : null),
              h('td', { className: 'is-num' }, h('span', { className: 'ck-mt-cell__main ck-mt-money' }, money(i.amount)),
                i.net != null && !Number.isNaN(i.net) ? h('span', { className: 'ck-mt-cell__sub' }, 'Líquido ' + money(i.net)) : null,
                i.credit ? h('span', { className: 'ck-mt-cell__sub' }, 'Crédito de ' + money(i.credit)) : null,
                i.discount ? h('span', { className: 'ck-mt-cell__sub ck-mt-cell__sub--discount' }, '− ' + money(i.discount) + (i.discountNote ? ' (' + i.discountNote + ')' : ' de desconto')) : null),
              h('td', null, i.paidDate ? h('div', { className: 'ck-mt-paid' },
                  i.paidBy ? h(Avatar, { name: i.paidBy, size: 'sm', className: 'ck-mt-paid__avatar' }) : null,
                  h('span', null, h('span', { className: 'ck-mt-cell__main' }, fdate(i.paidDate)),
                    h('span', { className: 'ck-mt-cell__sub' }, [i.method, i.channel].filter(Boolean).join(', ')))) :
                h('span', { className: 'ck-mt-cell__sub' }, 'Não pago')),
              h('td', null, h(Badge, { tone: st[0], dot: true }, st[1])),
              h('td', { className: 'ck-mt-actions' },
                due && i.canSettle !== false ? h(Button, { size: 'sm', variant: 'secondary', icon: 'check-circle', onClick: function () { p.onSettle && p.onSettle(i); } }, 'Dar baixa') : null,
                due && i.canSettle === false && i.settleNote ? h(Badge, { tone: 'info', icon: 'package' }, i.settleNote) : null,
                p.menu === false ? null : h(ActionMenu, { label: 'Ações da parcela ' + i.number, items: [
                  { label: 'Editar parcela', icon: 'pencil', onClick: function () { p.onEdit && p.onEdit(i); } }, '-',
                  { label: 'Excluir parcela', icon: 'trash', danger: true, onClick: function () { p.onDelete && p.onDelete(i); } }] })));
          }) : h('tr', null, h('td', { colSpan: 6, className: 'ck-mt-table__empty' }, filter === 'canceladas' ? 'Nenhuma fatura cancelada.' : 'Nenhuma fatura ativa.'))))));
  }

  function Matricula(p) {
    var e = p.enrollment || {};
    var st = e.statusLabel ? [e.statusTone || 'neutral', e.statusLabel] : (ENROLL_STATUS[e.status] || ENROLL_STATUS.ativa);
    var next = (p.invoices || []).filter(function (i) { return (i.status === 'atrasado' || i.status === 'aguardando') && i.canSettle !== false; })
      .sort(function (a, b) { return a.status === 'atrasado' ? -1 : (pd(a.dueDate) - pd(b.dueDate)); })[0];
    var nextDiff = next && p.today ? dayDiff(p.today, next.dueDate) : null;
    var alertDays = p.alertDays != null ? p.alertDays : 7;
    if (next && next.status !== 'atrasado' && nextDiff != null && nextDiff > alertDays) next = null;
    var whenTxt = (!next || nextDiff == null) ? '' : nextDiff === 0 ? 'vence hoje' : nextDiff > 0 ? 'vence em ' + fdate(next.dueDate) : 'venceu em ' + fdate(next.dueDate);
    return h('div', { className: cx('ck-mt', p.className) },
      h('div', { className: 'ck-mt-hero' },
        p.onBack ? h('button', { type: 'button', className: 'ck-mt-back', onClick: p.onBack }, h(Icon, { name: 'arrow-left', size: 16 }), p.backLabel || 'Matrículas') : null,
        h('div', { className: 'ck-mt-hero__row' },
          h(Avatar, { name: e.student && e.student.name, src: e.student && e.student.avatar, size: 'lg', className: 'ck-mt-hero__avatar', decorative: true }),
          h('div', { className: 'ck-mt-hero__text' },
            h('div', { className: 'ck-mt-hero__titleline' },
              h('h1', { className: 'ck-mt-hero__name' }, e.student && e.student.name),
              h(Badge, { tone: st[0], dot: true }, st[1]),
              p.pkg ? h(Badge, { tone: 'info', icon: 'package' }, 'Pacote ' + p.pkg.contract) : null),
            h('ul', { className: 'ck-mt-hero__meta' },
              h('li', null, h(Icon, { name: 'receipt', size: 15 }), 'Matrícula ' + e.id),
              h('li', null, h(Icon, { name: e.modalityIcon || 'dumbbell', size: 15 }), e.modality + ', ' + String(e.frequency || '').toLowerCase()),
              h('li', null, h(Icon, { name: 'calendar', size: 15 }), 'Desde ' + fdate(e.startDate)))),
          p.actions !== undefined ? (p.actions ? h('div', { className: 'ck-mt-hero__actions' }, p.actions) : null) :
          h('div', { className: 'ck-mt-hero__actions' },
            h(Button, { variant: 'ghost', icon: 'lock', onClick: p.onBlock }, 'Bloquear matrícula'),
            h(Button, { variant: 'secondary', icon: 'repeat', onClick: p.onChangePlan }, 'Alterar plano')))),
      p.notices || null,
      next ? h(Callout, { tone: next.status === 'atrasado' ? 'danger' : 'warning', icon: 'clock', className: 'ck-mt-alert',
        title: 'Parcela ' + next.number + ' de ' + money(next.amount) + ' ' + whenTxt,
        action: h(Button, { variant: 'primary', icon: 'check-circle', onClick: function () { p.onSettle && p.onSettle(next); } }, 'Dar baixa') },
        'O acesso do aluno depende desse pagamento.') : null,
      p.pkg ? h(PacoteMatricula, Object.assign({ today: p.today }, p.pkg, { currentStudent: e.student && e.student.name, onSettle: p.onSettlePackage })) : null,
      h('div', { className: 'ck-mt-overview' },
        h(MtPlanCard, { enrollment: e, today: p.today }),
        h(MtFinance, { invoices: p.invoices }),
        h(MtDiscounts, { discounts: p.discounts, onAdd: p.onAddDiscount, onEdit: p.onEditDiscount, onRemove: p.onRemoveDiscount })),
      h(MtInvoices, { invoices: p.invoices, counts: p.counts, today: p.today, onSettle: p.onSettle, onEdit: p.onEditInvoice, onDelete: p.onDeleteInvoice, onReload: p.onReload, menu: p.invoiceMenu }),
      p.children || null);
  }


  /* ---------- Modal ---------- */
  function Modal(p) {
    var ref = useRef(null), id = useId();
    useEffect(function () {
      if (!p.open) return;
      var prev = document.activeElement;
      var el = ref.current;
      var first = el && el.querySelector(p.initialFocus || 'input:not([type="date"]), select, textarea');
      if (first && p.autoFocus !== false) first.focus(); else if (el) el.focus();
      function key(e) {
        if (e.key === 'Escape' && p.onClose) { e.preventDefault(); p.onClose(); }
        if (e.key === 'Tab' && el) {
          var f = el.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea, [href], [tabindex]:not([tabindex="-1"])');
          if (!f.length) return;
          var a = f[0], z = f[f.length - 1];
          if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
          else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
        }
      }
      document.addEventListener('keydown', key);
      return function () { document.removeEventListener('keydown', key); if (prev && prev.focus) prev.focus(); };
    }, [p.open]);
    if (!p.open) return null;
    return h('div', { className: cx('ck-modal', p.inline && 'ck-modal--inline'), onMouseDown: function (e) { if (e.target === e.currentTarget && p.onClose && p.dismissable !== false) p.onClose(); } },
      h('div', { className: cx('ck-modal__dialog', 'ck-modal__dialog--' + (p.size || 'md')), role: 'dialog', 'aria-modal': true, 'aria-labelledby': id, tabIndex: -1, ref: ref },
        h('header', { className: 'ck-modal__head' },
          h('div', { style: { flex: 1, minWidth: 0 } },
            h('h2', { className: 'ck-modal__title', id: id }, p.title),
            p.description ? h('p', { className: 'ck-modal__desc' }, p.description) : null),
          h(IconButton, { icon: 'x', label: 'Fechar', className: 'ck-modal__close', onClick: p.onClose })),
        h('div', { className: 'ck-modal__body' }, p.children),
        p.footer ? h('footer', { className: 'ck-modal__foot' }, p.footer) : null));
  }

  function SelectField(p) {
    var auto = useId(), id = p.id || auto;
    var rest = omit(p, ['label', 'hint', 'error', 'options', 'placeholder', 'icon', 'className', 'id', 'labelAside']);
    var control = h('div', { className: cx('ck-control ck-select', p.error && 'is-invalid') },
      p.icon ? h(Icon, { name: p.icon, size: 16, className: 'ck-control__icon' }) : null,
      h('select', Object.assign({ id: id, className: 'ck-control__input', 'aria-describedby': (p.hint || p.error) ? id + '-m' : undefined }, rest),
        p.placeholder ? h('option', { value: '', disabled: true }, p.placeholder) : null,
        (p.options || []).map(function (o) { var v = typeof o === 'string' ? o : o.value; return h('option', { key: v, value: v }, typeof o === 'string' ? o : o.label); })),
      h(Icon, { name: 'chevron-down', size: 16, className: 'ck-control__icon ck-select__chev' }));
    return FieldShell(p, id, control);
  }

  /* ---------- PacoteMatricula ---------- */
  function PacoteMatricula(p) {
    var members = p.members || [];
    var settled = !!p.settled;
    var total = p.total != null ? p.total : members.reduce(function (a, m) { return a + (m.amount || 0); }, 0);
    var per = p.perPerson != null ? p.perPerson : (members.length ? total / members.length : 0);
    var left = p.today && p.endDate ? dayDiff(p.today, p.endDate) : null;
    return h('section', { className: 'ck-card ck-pkg', 'aria-label': 'Pacote ' + (p.contract || '') },
      h('header', { className: 'ck-pkg__head' },
        h('span', { className: 'ck-pkg__icon', 'aria-hidden': true }, h(Icon, { name: 'package', size: 20 })),
        h('div', { style: { flex: 1, minWidth: 220 } },
          h('div', { className: 'ck-pkg__eyebrow' }, 'Matrícula vinculada ao pacote ' + (p.contract || '')),
          h('h2', { className: 'ck-pkg__title' }, p.name),
          h('div', { className: 'ck-pkg__badges' },
            h(Badge, { tone: p.status === 'ativo' ? 'success' : 'neutral', dot: true }, p.status === 'ativo' ? 'Pacote ativo' : 'Pacote encerrado'),
            h(Badge, { icon: 'users' }, members.length + (members.length === 1 ? ' pessoa' : ' pessoas')))),
        settled ? h('span', { className: 'ck-pkg__settled' }, h(Icon, { name: 'check-circle', size: 18 }),
            h('span', null, h('strong', null, 'Pago pelo grupo'), p.settledAt ? h('span', null, 'Baixa em ' + fdate(p.settledAt)) : null))
          : h(Button, { variant: 'primary', icon: 'check-circle', onClick: p.onSettle }, 'Dar baixa no pacote')),
      h('div', { className: 'ck-pkg__body' },
        h('dl', { className: 'ck-pkg__facts' },
          h('div', null, h('dt', null, 'Vigência'), h('dd', null, fdate(p.startDate), h(Icon, { name: 'arrow-right', size: 14, className: 'ck-pkg__arrow' }), fdate(p.endDate)),
            left != null ? h('span', { className: 'ck-pkg__hint' }, left > 0 ? 'Termina em ' + left + (left === 1 ? ' dia' : ' dias') : left === 0 ? 'Termina hoje' : 'Encerrada') : null),
          h('div', null, h('dt', null, 'Duração'), h('dd', null, p.duration)),
          h('div', null, h('dt', null, 'Valor do pacote'), h('dd', null, money(total))),
          h('div', null, h('dt', null, 'Por pessoa'), h('dd', null, money(per)), h('span', { className: 'ck-pkg__hint' }, 'Valor rateado'))),
        h('div', { className: 'ck-pkg__split' },
          h('div', { className: 'ck-pkg__splithead' },
            h('h3', null, 'Beneficiários e rateio'),
            p.payer ? h('span', null, 'Quem paga: ', h('strong', null, p.payer)) : null),
          h('div', { className: 'ck-pkg__bar', role: 'img', 'aria-label': members.map(function (m) { return m.name + ' ' + money(m.amount); }).join(', ') },
            members.map(function (m, i) {
              var cur = p.currentStudent && m.name === p.currentStudent;
              return h('span', { key: i, className: cx('ck-pkg__seg', cur && 'is-current'), style: { flexGrow: m.amount || 1 } }, initialsOf(m.name));
            })),
          h('ul', { className: 'ck-pkg__members' }, members.map(function (m, i) {
            var cur = p.currentStudent && m.name === p.currentStudent;
            return h('li', { key: i, className: cur ? 'is-current' : null },
              h(Avatar, { name: m.name, size: 'sm', decorative: true }),
              h('span', { className: 'ck-pkg__who' },
                h('strong', null, m.name),
                h('span', null, m.role + (m.status && m.status !== 'ativo' ? ', ' + m.status : ''))),
              cur ? h(Badge, { tone: 'brand' }, 'Esta matrícula') : null,
              m.role === 'Pagante' ? h(Badge, { tone: 'info', icon: 'wallet' }, 'Pagante') : null,
              h('span', { className: 'ck-pkg__amount' }, money(m.amount)));
          }))),
        h('div', { className: 'ck-pkg__rule' },
          h(Icon, { name: 'info', size: 16 }),
          h('p', null, p.rule || ('O desconto do pacote vale só durante a vigência. A baixa do grupo usa o valor rateado (' + money(per) + ' por pessoa). Depois de ' + fdate(p.endDate) + ', as parcelas individuais voltam ao valor cheio do plano.')))));
  }

  /* ---------- DescontoModal ---------- */
  function DescontoModal(p) {
    var init = p.discount || {};
    var t = useState(init.type || 'recorrente'), md = useState(init.mode || 'fixo');
    var v = useState(init.value != null ? ((init.mode || 'fixo') === 'fixo' ? Number(init.value).toFixed(2) : String(init.value)).replace('.', ',') : '');
    var r = useState(init.reason || ''), au = useState(init.authorizedBy || (p.authorizers && p.authorizers[0]) || '');
    var st = useState(init.start || p.today || ''), en = useState(init.end || '');
    var rem = useState(init.remaining != null ? String(init.remaining) : '');
    var tried = useState(false), saving = useState(false);
    var num = parseFloat(String(v[0]).replace(/\./g, '').replace(',', '.')) || 0;
    var base = p.planAmount || 0;
    var off = md[0] === 'percentual' ? base * num / 100 : num;
    var result = Math.max(0, base - off);
    var errs = {};
    if (!r[0].trim()) errs.reason = 'Escreva o motivo do desconto.';
    if (!num) errs.value = 'Informe um valor maior que zero.';
    else if (md[0] === 'percentual' && num > 100) errs.value = 'O percentual vai até 100%.';
    else if (md[0] === 'fixo' && base && num > base) errs.value = 'O desconto passa do valor da parcela (' + money(base) + ').';
    if (en[0] && st[0] && en[0] < st[0]) errs.end = 'O fim precisa ser depois do início.';
    var ok = !Object.keys(errs).length;
    function save() {
      tried[1](true);
      if (!ok) return;
      var data = { id: init.id, type: t[0], mode: md[0], value: num, reason: r[0].trim(), authorizedBy: au[0], start: st[0], end: en[0] || null, remaining: rem[0] ? parseInt(rem[0], 10) : null };
      if (p.onSave) { var res = p.onSave(data); if (res && res.then) { saving[1](true); res.then(function () { saving[1](false); }, function () { saving[1](false); }); } }
    }
    var editing = !!init.id;
    return h(Modal, { open: p.open, onClose: p.onCancel, inline: p.inline, size: 'md',
      title: editing ? 'Editar desconto' : 'Novo desconto',
      description: p.studentName ? 'Matrícula de ' + p.studentName + (base ? ', parcela de ' + money(base) : '') : null,
      footer: h(React.Fragment, null,
        editing && p.onRemove ? h(Button, { variant: 'ghost', icon: 'trash', className: 'ck-modal__danger', onClick: function () { p.onRemove(init); } }, 'Remover') : null,
        h('span', { style: { flex: 1 } }),
        h(Button, { variant: 'ghost', onClick: p.onCancel }, 'Cancelar'),
        h(Button, { variant: 'primary', icon: 'save', loading: saving[0], onClick: save }, saving[0] ? 'Salvando…' : (editing ? 'Salvar desconto' : 'Criar desconto')))
    },
      h('div', { className: 'ck-dm' },
        h('div', { className: 'ck-section' },
          h('span', { className: 'ck-field__label', id: 'dm-type' }, 'Quando aplicar'),
          h(ChoiceGroup, { label: 'Quando aplicar', className: 'ck-dm__choices' },
            h(ChoiceCard, { checked: t[0] === 'primeira', onSelect: function () { t[1]('primeira'); }, title: 'Só na 1ª mensalidade', description: 'Entra uma vez, na próxima parcela.' }),
            h(ChoiceCard, { checked: t[0] === 'recorrente', onSelect: function () { t[1]('recorrente'); }, title: 'Recorrente', description: 'Entra em todas as parcelas da vigência.' }))),
        h('div', { className: 'ck-dm__value' },
          h('div', { className: 'ck-field' },
            h('span', { className: 'ck-field__label' }, 'Desconto em'),
            h(SegmentedControl, { label: 'Desconto em', value: md[0], onChange: md[1], options: [{ value: 'fixo', label: 'R$' }, { value: 'percentual', label: '%' }] })),
          h(TextField, { label: md[0] === 'fixo' ? 'Valor (R$)' : 'Percentual (%)', inputMode: 'decimal', value: v[0], placeholder: md[0] === 'fixo' ? '0,00' : '0', className: 'ck-dm__amount',
            onChange: function (e) { v[1](e.target.value.replace(/[^0-9.,]/g, '')); }, error: tried[0] ? errs.value : null })),
        base ? h('div', { className: 'ck-dm__preview', 'aria-live': 'polite' },
          h('span', { className: 'ck-dm__from' }, money(base)),
          h(Icon, { name: 'arrow-right', size: 16 }),
          h('span', { className: 'ck-dm__to' }, money(result)),
          h('span', { className: 'ck-dm__note' }, off > 0 ? 'por parcela, ' + money(Math.min(off, base)) + ' a menos' + (t[0] === 'primeira' ? ', só na próxima' : '') : 'Sem desconto ainda')) : null,
        h(TextField, { label: 'Motivo', value: r[0], onChange: function (e) { r[1](e.target.value); }, placeholder: 'Ex.: Pacote família, indicação, convênio', error: tried[0] ? errs.reason : null, hint: 'Aparece no histórico da matrícula e nas faturas.' }),
        h(SelectField, { label: 'Autorizado por', icon: 'user-check', value: au[0], onChange: function (e) { au[1](e.target.value); }, options: p.authorizers || [] }),
        h('div', { className: 'ck-dm__row' },
          h(TextField, { label: 'Início da vigência', type: 'date', value: st[0], onChange: function (e) { st[1](e.target.value); } }),
          h(TextField, { label: 'Fim da vigência', type: 'date', value: en[0], onChange: function (e) { en[1](e.target.value); }, error: tried[0] ? errs.end : null,
            hint: en[0] ? null : 'Sem data de fim',
            labelAside: en[0] ? h('button', { type: 'button', className: 'ck-linkbtn', onClick: function () { en[1](''); } }, 'Sem fim') : null })),
        t[0] === 'recorrente' ? h(TextField, { label: 'Limite de parcelas', type: 'number', min: 1, inputMode: 'numeric', value: rem[0], onChange: function (e) { rem[1](e.target.value); }, placeholder: 'Sem limite', hint: 'Deixe vazio para aplicar até o fim da vigência.' }) : null));
  }

  function Sidebar(p) {
    var col = useControlled(p.collapsed, !!p.defaultCollapsed, p.onCollapsedChange);
    var sections = p.sections || [];
    var initOpen = {};
    sections.forEach(function (s) { (s.items || []).forEach(function (it) {
      if (it.children && it.children.some(function (c) { return c.id === p.activeId; })) initOpen[it.id] = true;
    }); });
    (p.defaultExpanded || []).forEach(function (id) { initOpen[id] = true; });
    var op = useState(initOpen), open = op[0];
    function nav(id, e) { if (p.onNavigate) { if (e) e.preventDefault(); p.onNavigate(id); } }
    var brand = p.brand || {};
    return h('aside', { className: cx('ck-sidebar', col[0] && 'is-collapsed', p.className), 'aria-label': 'Menu principal' },
      h('div', { className: 'ck-sidebar__brand' },
        h('span', { className: 'ck-sidebar__logo', 'aria-hidden': true }, h(Icon, { name: brand.icon || 'activity', size: 18, strokeWidth: 2.25 })),
        h('span', { className: 'ck-sidebar__brandtext' },
          h('span', { className: 'ck-sidebar__name' }, brand.name || 'Check-in'),
          brand.subtitle ? h('span', { className: 'ck-sidebar__sub' }, brand.subtitle) : null)),
      h('nav', { className: 'ck-sidebar__nav' },
        sections.map(function (s, si) {
          return h('div', { key: si, className: 'ck-nav__section' },
            s.label ? h('div', { className: 'ck-nav__label' }, s.label) : null,
            h('ul', { className: 'ck-nav__list' }, (s.items || []).map(function (it) {
              var kids = it.children && it.children.length ? it.children : null;
              var childActive = kids && kids.some(function (c) { return c.id === p.activeId; });
              var isOpen = !!open[it.id] && !col[0];
              var content = [
                h(Icon, { key: 'i', name: it.icon || 'chevron-right', size: 18 }),
                h('span', { key: 't', className: 'ck-nav__text' }, it.label),
                it.count != null ? h('span', { key: 'c', className: 'ck-nav__count' }, it.count) : null,
                kids ? h(Icon, { key: 'v', name: 'chevron-down', size: 16, className: 'ck-nav__chev' }) : null
              ];
              var item = kids
                ? h('button', { type: 'button', className: cx('ck-nav__item', childActive && 'has-active'), 'aria-expanded': isOpen, title: col[0] ? it.label : undefined,
                    onClick: function () { if (col[0]) { col[1](false); var o = {}; o[it.id] = true; op[1](Object.assign({}, open, o)); return; } var o2 = {}; o2[it.id] = !open[it.id]; op[1](Object.assign({}, open, o2)); } }, content)
                : h('a', { href: it.href || '#', className: cx('ck-nav__item', it.id === p.activeId && 'is-active'), 'aria-current': it.id === p.activeId ? 'page' : undefined, title: col[0] ? it.label : undefined, onClick: function (e) { nav(it.id, e); } }, content);
              return h('li', { key: it.id }, item,
                kids && isOpen ? h('ul', { className: 'ck-nav__children' }, kids.map(function (c) {
                  return h('li', { key: c.id }, h('a', { href: c.href || '#', className: cx('ck-nav__child', c.id === p.activeId && 'is-active'), 'aria-current': c.id === p.activeId ? 'page' : undefined, onClick: function (e) { nav(c.id, e); } }, c.label));
                })) : null);
            })));
        })),
      h('div', { className: 'ck-sidebar__foot' },
        p.footer || null,
        h('button', { type: 'button', className: 'ck-nav__item ck-sidebar__toggle', onClick: function () { col[1](!col[0]); }, 'aria-label': col[0] ? 'Expandir menu' : 'Recolher menu', title: col[0] ? 'Expandir menu' : undefined },
          h(Icon, { name: 'panel-left', size: 18 }), h('span', { className: 'ck-nav__text' }, 'Recolher menu'))));
  }

  function AppHeader(p) {
    var m = useState(!!p.defaultMenuOpen), menuOpen = m[0];
    var ref = useRef(null);
    useEffect(function () {
      if (!menuOpen) return;
      function off(e) { if (ref.current && !ref.current.contains(e.target)) m[1](false); }
      function esc(e) { if (e.key === 'Escape') m[1](false); }
      document.addEventListener('mousedown', off); document.addEventListener('keydown', esc);
      return function () { document.removeEventListener('mousedown', off); document.removeEventListener('keydown', esc); };
    }, [menuOpen]);
    var u = p.user || {};
    var crumbs = p.breadcrumbs || [];
    return h('header', { className: cx('ck-header', p.className) },
      p.leading || null,
      h('nav', { 'aria-label': 'Trilha' }, h('ol', { className: 'ck-crumbs' }, crumbs.map(function (c, i) {
        var last = i === crumbs.length - 1;
        return h('li', { key: i },
          i > 0 ? h(Icon, { name: 'chevron-right', size: 14 }) : null,
          last ? h('span', { 'aria-current': 'page' }, c.label) : h('a', { href: c.href || '#', onClick: c.onClick ? function (e) { e.preventDefault(); c.onClick(); } : undefined }, c.label));
      }))),
      h('div', { className: 'ck-header__spacer' }),
      p.search !== false ? h('button', { type: 'button', className: 'ck-search', onClick: p.onSearch },
        h(Icon, { name: 'search', size: 16 }), h('span', { className: 'ck-search__text' }, p.searchPlaceholder || 'Buscar alunos, planos…'), h('kbd', { className: 'ck-kbd' }, '⌘K')) : null,
      p.actions || null,
      p.notifications === false ? null : h(IconButton, { icon: 'bell', label: p.notifications ? 'Notificações (' + p.notifications + ' novas)' : 'Notificações', dot: !!p.notifications, onClick: p.onNotifications }),
      h('span', { className: 'ck-header__divider', 'aria-hidden': true }),
      h('div', { className: 'ck-usermenu', ref: ref },
        h('button', { type: 'button', className: 'ck-userbtn', 'aria-haspopup': 'menu', 'aria-expanded': menuOpen, onClick: function () { m[1](!menuOpen); } },
          h(Avatar, { name: u.name, initials: u.initials, size: 'sm', decorative: true }),
          h('span', null, h('span', { className: 'ck-userbtn__name' }, u.name), u.role ? h('span', { className: 'ck-userbtn__role' }, u.role) : null),
          h(Icon, { name: 'chevron-down', size: 16 })),
        menuOpen ? h('div', { className: 'ck-menu', role: 'menu' },
          h('div', { className: 'ck-menu__head' }, h(Avatar, { name: u.name, initials: u.initials, size: 'md', decorative: true }), h('div', null, h('strong', null, u.name), u.email ? h('span', null, u.email) : null)),
          (p.menuItems || [{ label: 'Meu perfil', icon: 'user' }, { label: 'Preferências', icon: 'settings' }]).map(function (it, i) {
            return h('button', { key: i, type: 'button', role: 'menuitem', className: 'ck-menu__item', onClick: function () { m[1](false); if (it.onClick) it.onClick(); } }, h(Icon, { name: it.icon, size: 16 }), it.label);
          }),
          h('div', { className: 'ck-menu__sep', role: 'separator' }),
          h('button', { type: 'button', role: 'menuitem', className: 'ck-menu__item ck-menu__item--danger', onClick: function () { m[1](false); if (p.onSignOut) p.onSignOut(); } }, h(Icon, { name: 'log-out', size: 16 }), 'Sair')) : null));
  }

  function PageHeader(p) {
    return h('div', { className: cx('ck-pagehead', p.className) },
      h('div', null,
        h('h1', { className: 'ck-pagehead__title' }, p.title),
        p.description ? h('p', { className: 'ck-pagehead__desc' }, p.description) : null),
      p.actions ? h('div', { className: 'ck-pagehead__actions' }, p.actions) : null);
  }

  function AppShell(p) {
    return h('div', { className: cx('ck-shell', p.className), style: p.height ? { height: p.height } : undefined },
      p.sidebar,
      h('div', { className: 'ck-shell__main' },
        p.header,
        h('main', { className: 'ck-shell__content' }, h('div', { className: 'ck-shell__inner' }, p.children))));
  }

  var api = { AppShell: AppShell, Sidebar: Sidebar, AppHeader: AppHeader, PageHeader: PageHeader, Button: Button, IconButton: IconButton, Switch: Switch, SegmentedControl: SegmentedControl, TextField: TextField, SecretField: SecretField, Fieldset: Fieldset, Card: Card, ProviderCard: ProviderCard, ProviderTile: ProviderTile, ProviderLogo: ProviderLogo, ChoiceCard: ChoiceCard, ChoiceGroup: ChoiceGroup, Tabs: Tabs, SaveBar: SaveBar, ActionMenu: ActionMenu, Matricula: Matricula, Modal: Modal, SelectField: SelectField, PacoteMatricula: PacoteMatricula, DescontoModal: DescontoModal, Badge: Badge, Callout: Callout, Avatar: Avatar, Icon: Icon };

export { AppShell, Sidebar, AppHeader, PageHeader, Button, IconButton, Switch, SegmentedControl, TextField, SecretField, Fieldset, Card, ProviderCard, ProviderTile, ProviderLogo, ChoiceCard, ChoiceGroup, Tabs, SaveBar, ActionMenu, Matricula, Modal, SelectField, PacoteMatricula, DescontoModal, Badge, Callout, Avatar, Icon };
export default api;
