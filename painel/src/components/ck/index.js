/* AppCheckin — componentes do design system do painel (artefato "AppCheckin" / painel).
   Portado de project/components/bundle.js para módulo ES. Estilos: src/theme/ck.css + ck-tokens.css.
   Extensões locais: ícones file-text, building-2 e menu; AppHeader com `leading`,
   `notifications={false}` e trilha com onClick. */
/* @ds-bundle: {"format":4,"namespace":"AppCheckin","components":[{"name":"AppShell"},{"name":"Sidebar"},{"name":"AppHeader"},{"name":"PageHeader"},{"name":"Button"},{"name":"IconButton"},{"name":"Switch"},{"name":"SegmentedControl"},{"name":"TextField"},{"name":"SecretField"},{"name":"Fieldset"},{"name":"Tabs"},{"name":"ChoiceCard"},{"name":"Card"},{"name":"ProviderTile"},{"name":"ProviderCard"},{"name":"ProviderLogo"},{"name":"SaveBar"},{"name":"Badge"},{"name":"Callout"},{"name":"Avatar"},{"name":"Icon"}]} */
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
    'plus': [P('M5 12h14'), P('M12 5v14')]
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
      h('div', null, p.title ? h('span', { className: 'ck-callout__title' }, p.title) : null, p.children ? h('span', { className: 'ck-callout__text' }, p.children) : null));
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

  var api = { AppShell: AppShell, Sidebar: Sidebar, AppHeader: AppHeader, PageHeader: PageHeader, Button: Button, IconButton: IconButton, Switch: Switch, SegmentedControl: SegmentedControl, TextField: TextField, SecretField: SecretField, Fieldset: Fieldset, Card: Card, ProviderCard: ProviderCard, ProviderTile: ProviderTile, ProviderLogo: ProviderLogo, ChoiceCard: ChoiceCard, ChoiceGroup: ChoiceGroup, Tabs: Tabs, SaveBar: SaveBar, Badge: Badge, Callout: Callout, Avatar: Avatar, Icon: Icon };

export { AppShell, Sidebar, AppHeader, PageHeader, Button, IconButton, Switch, SegmentedControl, TextField, SecretField, Fieldset, Card, ProviderCard, ProviderTile, ProviderLogo, ChoiceCard, ChoiceGroup, Tabs, SaveBar, Badge, Callout, Avatar, Icon };
export default api;
