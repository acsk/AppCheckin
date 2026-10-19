/* AppCheckin — Academia (novo e editar), Contratos e Novo contrato do super admin.
   Estilos: src/theme/ck-contratos.css (reaproveita ck-al-*, ck-ml* e ck-stat de ck-pessoas.css). Primitivas de ./index. */
import React from 'react';
import {
  ActionMenu, Avatar, Badge, Button, Callout, Icon, IconButton, Modal, PageHeader, SegmentedControl, SelectField, Switch,
  TextAreaField, TextField,
} from './index';
import { StatTile, digits, maskCep, maskCnpj, maskCpf, maskPhone, titleCase } from './pessoas';

  var h = React.createElement;
  var useState = React.useState, useRef = React.useRef, useMemo = React.useMemo;

  function cx() { return Array.prototype.filter.call(arguments, Boolean).join(' '); }
  function useControlled(value, def, onChange) {
    var s = useState(def), isC = value !== undefined;
    return [isC ? value : s[0], function (v) { if (!isC) s[1](v); if (onChange) onChange(v); }];
  }
  var BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  function money(v) { return BRL.format(v || 0); }
  function num(v) { return new Intl.NumberFormat('pt-BR').format(v || 0); }
  function pd(iso) { if (!iso) return null; var a = String(iso).split('T')[0].split('-'); return new Date(+a[0], +a[1] - 1, +a[2]); }
  function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function todayIso() { return iso(new Date()); }
  function fdate(v) { if (!v) return '—'; var a = String(v).split('T')[0].split('-'); return a[2] + '/' + a[1] + '/' + a[0]; }
  function dayDiff(a, b) { return Math.round((pd(b) - pd(a)) / 86400000); }
  function addMonths(v, n) {
    var d = pd(v), day = d.getDate();
    d.setDate(1); d.setMonth(d.getMonth() + n);
    d.setDate(Math.min(day, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()));
    return iso(d);
  }
  function monthsBetween(a, b) {
    if (!a || !b) return null;
    for (var n = 1; n <= 60; n++) if (addMonths(a, n) === b) return n;
    return null;
  }
  function initialsOf(name) {
    if (!name) return '';
    var w = String(name).trim().split(/\s+/);
    return ((w[0] || '')[0] + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase();
  }
  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }
  function emailOk(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v || '').trim()); }
  function Missing(p) { return h('span', { className: 'ck-al__missing' }, (p && p.children) || 'Não informado'); }
  function BackLink(p) { return p.onClick ? h('button', { type: 'button', className: 'ck-mt-back', onClick: p.onClick }, h(Icon, { name: 'arrow-left', size: 16 }), p.children) : null; }
  function Logo(p) { return h('span', { className: cx('ck-ct-logo', p.size && 'ck-ct-logo--' + p.size), 'aria-hidden': true }, initialsOf(p.name)); }

  function Section(p) {
    return h('section', { className: 'ck-al-sec', 'aria-labelledby': p.id },
      h('header', { className: 'ck-al-sec__head' },
        h('span', { className: cx('ck-al-sec__icon', p.done && 'is-done'), 'aria-hidden': true }, h(Icon, { name: p.done ? 'check' : p.icon, size: 17, strokeWidth: p.done ? 2.5 : 1.75 })),
        h('div', { style: { flex: 1, minWidth: 0 } }, h('h2', { className: 'ck-al-sec__title', id: p.id }, p.title), p.description ? h('p', { className: 'ck-al-sec__desc' }, p.description) : null),
        p.aside || null),
      h('div', { className: 'ck-al-sec__body' }, p.children));
  }
  function Steps(p) {
    return h('ol', { className: 'ck-al-sum__steps' }, p.steps.map(function (s, i) {
      var ok = s.done, bad = !ok && p.tried && !s.optional;
      return h('li', { key: i, className: ok ? 'is-done' : (bad ? 'is-error' : undefined) },
        h('span', { className: 'ck-al-sum__mark', 'aria-hidden': true }, ok ? h(Icon, { name: 'check', size: 13, strokeWidth: 3 }) : (bad ? h(Icon, { name: 'alert-triangle', size: 12, strokeWidth: 2.25 }) : i + 1)),
        h('span', null, s.label), !ok && s.optional ? h('em', null, 'Opcional') : null,
        h('span', { className: 'ck-sr' }, ok ? ', concluído' : bad ? ', com erro' : ', pendente'));
    }));
  }
  function errCount(n) { return n === 1 ? 'Falta corrigir 1 item.' : 'Falta corrigir ' + n + ' itens.'; }
  function focusFirstInvalid(root) {
    setTimeout(function () {
      var el = root && root.querySelector('[aria-invalid="true"], .ck-ct-admins__empty.is-error button, .ck-ct-pick.is-error button');
      if (el) { el.focus(); if (el.scrollIntoView) el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
    }, 0);
  }

  /* ---------- Planos: rótulos ---------- */
  var FEATURE_LABELS = {
    checkins: 'Check-in pelo app', app_mobile: 'App do aluno', turmas: 'Turmas e horários', multi_admin: 'Vários administradores',
    relatorios_basicos: 'Relatórios básicos', relatorios_avancados: 'Relatórios avançados', api_access: 'Acesso à API', suporte_prioritario: 'Suporte prioritário',
  };
  function planFeatures(pl) {
    var out = [];
    if (pl.type === 'por_aluno' && pl.maxStudents) out.push('Até ' + num(pl.maxStudents) + ' alunos ativos');
    else out.push(pl.maxStudents ? 'Até ' + num(pl.maxStudents) + ' alunos' : 'Alunos ilimitados');
    if (pl.maxAdmins) out.push(plural(pl.maxAdmins, 'administrador', 'administradores'));
    (pl.features || []).forEach(function (k) { if (FEATURE_LABELS[k]) out.push(FEATURE_LABELS[k]); });
    return out.slice(0, 4);
  }
  function PlanPrice(p) {
    var pl = p.plan;
    if (pl.type === 'gratuito') return h('span', { className: 'ck-ct-price' }, h('strong', null, 'Grátis'));
    if (pl.type === 'por_aluno') return h('span', { className: 'ck-ct-price' }, h('strong', null, money(pl.perStudent)), h('span', null, 'por aluno ativo'));
    return h('span', { className: 'ck-ct-price' }, h('strong', null, money(pl.price)), h('span', null, pl.cycle || 'por mês'));
  }
  function PlanCard(p) {
    var pl = p.plan, feats = planFeatures(pl);
    return h('button', { type: 'button', role: 'radio', 'aria-checked': !!p.checked, className: 'ck-ct-plan', onClick: p.onSelect },
      h('span', { className: 'ck-ct-plan__top' }, h('span', { className: 'ck-ct-plan__name' }, pl.name), h('span', { className: 'ck-choice__radio ck-ct-radio', 'aria-hidden': true })),
      h(PlanPrice, { plan: pl }),
      feats.length ? h('ul', { className: 'ck-ct-plan__feats' }, feats.map(function (f, i) { return h('li', { key: i }, h(Icon, { name: 'check', size: 13, strokeWidth: 2.5 }), f); })) : null);
  }
  function planFromApi(x) {
    var feats = x.features;
    if (typeof feats === 'string') { try { feats = JSON.parse(feats); } catch (e) { feats = null; } }
    var keys = Array.isArray(feats) ? feats : feats ? Object.keys(feats).filter(function (k) { return feats[k]; }) : [];
    var dias = Number(x.duracao_dias || 30);
    return {
      id: x.id, name: x.nome, code: x.codigo || null,
      type: x.tipo_cobranca || (Number(x.valor) > 0 ? 'fixo' : 'gratuito'),
      price: Number(x.valor || 0), perStudent: Number(x.valor_por_aluno || 0),
      cycle: dias === 30 ? 'por mês' : dias === 365 ? 'por ano' : 'a cada ' + dias + ' dias',
      maxStudents: x.max_alunos ? Number(x.max_alunos) : null, maxAdmins: x.max_admins ? Number(x.max_admins) : null,
      features: keys, description: x.descricao || null,
    };
  }
  function planSummary(pl, end) {
    if (!pl) return '';
    var price = pl.type === 'gratuito' ? 'Grátis' : pl.type === 'por_aluno' ? money(pl.perStudent) + ' por aluno ativo' : money(pl.price) + ' ' + (pl.cycle || 'por mês');
    return price + ', ' + (end ? 'até ' + fdate(end) : 'sem data de fim');
  }

  /* ---------- AcademiaForm (novo e editar) ---------- */
  var UFS = [['AC', 'Acre'], ['AL', 'Alagoas'], ['AP', 'Amapá'], ['AM', 'Amazonas'], ['BA', 'Bahia'], ['CE', 'Ceará'], ['DF', 'Distrito Federal'], ['ES', 'Espírito Santo'], ['GO', 'Goiás'], ['MA', 'Maranhão'], ['MT', 'Mato Grosso'], ['MS', 'Mato Grosso do Sul'], ['MG', 'Minas Gerais'], ['PA', 'Pará'], ['PB', 'Paraíba'], ['PR', 'Paraná'], ['PE', 'Pernambuco'], ['PI', 'Piauí'], ['RJ', 'Rio de Janeiro'], ['RN', 'Rio Grande do Norte'], ['RS', 'Rio Grande do Sul'], ['RO', 'Rondônia'], ['RR', 'Roraima'], ['SC', 'Santa Catarina'], ['SP', 'São Paulo'], ['SE', 'Sergipe'], ['TO', 'Tocantins']];
  var ROLE_NAMES = { 1: 'Aluno', 2: 'Professor', 3: 'Admin' };
  var WA_RE = /^https:\/\/(chat\.)?whatsapp\.com\//i;

  function fromAcademy(a) {
    a = a || {}; var ad = a.address || {};
    return {
      name: a.name || '', cnpj: maskCnpj(a.cnpj), phone: maskPhone(a.phone), email: a.email || '',
      cep: maskCep(ad.cep), street: ad.street || '', number: ad.number || '', complement: ad.complement || '', district: ad.district || '', city: ad.city || '', uf: ad.uf || '',
      whatsapp: (a.whatsapp || []).map(function (w) { return { nome: w.nome || '', url: w.url || '' }; }),
      active: a.active !== false,
    };
  }

  var ROLE_OPTIONS = [
    { id: 3, label: 'Admin', description: 'Acessa o painel administrativo da academia.', locked: true },
    { id: 2, label: 'Professor', description: 'Marca presença e gerencia turmas.' },
    { id: 1, label: 'Aluno', description: 'Usa o app e faz check-in.' },
  ];
  function AdminDialog(p) {
    var a = p.admin, isNew = !a;
    var fm = useState(function () {
      var roles = (a && a.roles) || [];
      return { name: (a && a.name) || '', email: (a && a.email) || '', phone: maskPhone(a && a.phone), cpf: maskCpf(a && a.cpf), password: '', roles: [3].concat(roles.filter(function (r) { return r !== 3; })) };
    }), f = fm[0];
    var tried = useState(false), sh = useState(false);
    function set(k, v) { var n = Object.assign({}, f); n[k] = v; fm[1](n); }
    function toggleRole(id) { set('roles', f.roles.indexOf(id) >= 0 ? f.roles.filter(function (r) { return r !== id; }) : f.roles.concat([id])); }
    var err = {};
    if (!f.name.trim()) err.name = 'Informe o nome.';
    if (!f.email.trim()) err.email = 'Informe o e-mail. É o login no painel.';
    else if (!emailOk(f.email)) err.email = 'Confira o e-mail.';
    if (f.cpf && digits(f.cpf).length !== 11) err.cpf = 'O CPF tem 11 números.';
    if (f.phone && digits(f.phone).length < 10) err.phone = 'Inclua o DDD.';
    if (isNew && f.password.length < 6) err.password = 'Crie uma senha com pelo menos 6 caracteres.';
    else if (!isNew && f.password && f.password.length < 6) err.password = 'A nova senha precisa de pelo menos 6 caracteres.';
    var show = tried[0] ? err : {};
    function save() {
      tried[1](true);
      if (Object.keys(err).length) return;
      var roles = ROLE_OPTIONS.map(function (o) { return o.id; }).filter(function (id) { return f.roles.indexOf(id) >= 0; });
      p.onSave({ name: f.name.trim(), email: f.email.trim().toLowerCase(), phone: digits(f.phone) || null, cpf: digits(f.cpf) || null, password: f.password || null, roles: roles });
    }
    var where = p.academyName ? ' de ' + p.academyName : ' desta academia';
    return h(Modal, { open: true, onClose: p.onClose, size: 'md',
      leading: h('span', { className: 'ck-ct-dlgicon', 'aria-hidden': true }, h(Icon, { name: isNew ? 'user-plus' : 'pencil', size: 18 })),
      title: isNew ? 'Novo administrador' : 'Editar administrador',
      description: (isNew ? 'Vai acessar o painel' : 'Acessa o painel') + where + '.',
      footer: h(React.Fragment, null,
        h(Button, { variant: 'ghost', onClick: p.onClose }, 'Cancelar'),
        h(Button, { variant: 'primary', icon: isNew ? 'check' : 'save', loading: p.saving, onClick: save }, isNew ? 'Adicionar administrador' : 'Salvar alterações')) },
      h('div', { className: 'ck-al-grid' },
        h(TextField, { className: 'is-wide', label: 'Nome completo', value: f.name, error: show.name, onChange: function (e) { set('name', e.target.value); } }),
        h(TextField, { className: 'is-wide', label: 'E-mail', type: 'email', icon: 'mail', value: f.email, placeholder: 'nome@academia.com.br', error: show.email, autoComplete: 'off',
          hint: !show.email ? 'É o login no painel.' : null, onChange: function (e) { set('email', e.target.value); } }),
        h(TextField, { className: 'is-wide', label: isNew ? 'Senha' : 'Nova senha', type: sh[0] ? 'text' : 'password', icon: 'lock', value: f.password, autoComplete: 'new-password',
          placeholder: isNew ? 'Mínimo de 6 caracteres' : 'Deixe em branco para manter', error: show.password,
          suffix: h(IconButton, { icon: sh[0] ? 'eye-off' : 'eye', label: sh[0] ? 'Ocultar senha' : 'Mostrar senha', size: 'sm', onClick: function () { sh[1](!sh[0]); } }),
          onChange: function (e) { set('password', e.target.value); } }),
        h(TextField, { label: 'Telefone', type: 'tel', inputMode: 'numeric', value: f.phone, placeholder: '(00) 00000-0000', error: show.phone, onChange: function (e) { set('phone', maskPhone(e.target.value)); } }),
        h(TextField, { label: 'CPF', inputMode: 'numeric', value: f.cpf, placeholder: '000.000.000-00', error: show.cpf, onChange: function (e) { set('cpf', maskCpf(e.target.value)); } }),
        h('fieldset', { className: 'is-wide ck-ct-roles' },
          h('legend', { className: 'ck-field__label' }, 'Papéis'),
          ROLE_OPTIONS.map(function (o) {
            var on = f.roles.indexOf(o.id) >= 0;
            return h('label', { key: o.id, className: cx('ck-ct-role', on && 'is-on', o.locked && 'is-locked') },
              h('span', { className: 'ck-check' },
                h('input', { type: 'checkbox', checked: on, disabled: o.locked, onChange: function () { if (!o.locked) toggleRole(o.id); } }),
                h('span', { className: 'ck-check__box', 'aria-hidden': true }, h(Icon, { name: 'check', size: 13, strokeWidth: 3 }))),
              h('span', { className: 'ck-ct-role__text' }, h('strong', null, o.label), h('span', null, o.description)),
              o.locked ? h('span', { className: 'ck-ct-role__lock' }, h(Icon, { name: 'lock', size: 13 }), 'Sempre incluído') : null);
          }))));
  }

  function AcademiaForm(p) {
    var edit = p.mode === 'editar';
    var init = useState(function () { return fromAcademy(p.academy); })[0];
    var fm = useState(init), f = fm[0];
    var tried = useState(false), cp = useState({ state: 'idle' });
    var dlg = useState(null), dlgSaving = useState(false);
    var plans = p.plans || [];
    var planId = useControlled(p.planId, null, p.onPlanChange);
    var endMode = useState('sem'), endDate = useState('');
    var rootRef = useRef(null);
    var admins = p.admins || [];
    function set(k, v) { fm[1](function (cur) { var n = Object.assign({}, cur); n[k] = v; return n; }); }

    var waRows = f.whatsapp.filter(function (w) { return w.nome.trim() || w.url.trim(); });
    var err = {};
    if (!f.name.trim()) err.name = 'Informe o nome da academia.';
    if (f.cnpj && digits(f.cnpj).length !== 14) err.cnpj = 'O CNPJ tem 14 números.';
    if (f.phone && digits(f.phone).length < 10) err.phone = 'Inclua o DDD.';
    if (!f.email.trim()) err.email = 'Informe o e-mail de contato.';
    else if (!emailOk(f.email)) err.email = 'Confira o e-mail. Falta algo como @ ou .com.';
    f.whatsapp.forEach(function (w, i) {
      if (!w.nome.trim() && !w.url.trim()) return;
      if (!w.nome.trim()) err['wan' + i] = 'Dê um nome ao grupo.';
      if (!WA_RE.test(w.url.trim())) err['wau' + i] = 'Use o link de convite, https://chat.whatsapp.com/…';
    });
    if (!edit && !admins.length) err.admins = 'Adicione pelo menos um administrador.';
    if (!edit && !planId[0]) err.plan = 'Escolha o plano.';
    if (!edit && endMode[0] === 'ate' && (!endDate[0] || endDate[0] < todayIso())) err.end = endDate[0] ? 'A data de fim não pode ser antes de hoje.' : 'Escolha até quando vale o contrato.';
    var show = tried[0] ? err : {};
    var nErr = Object.keys(err).length;
    var dirty = JSON.stringify(f) !== JSON.stringify(init);

    var dataOk = !err.name && !err.cnpj && !err.phone && !err.email && !!f.name.trim();
    var steps = [
      { label: 'Dados da academia', done: dataOk },
      { label: 'Endereço', done: !!(f.cep && f.street && f.number && f.city), optional: true },
      { label: 'Administradores', done: admins.length > 0 },
    ];
    if (!edit) steps.push({ label: 'Plano', done: !err.plan && !err.end });

    function onCep(v) {
      var m = maskCep(v); set('cep', m);
      if (digits(m).length === 8 && p.onCepLookup) {
        cp[1]({ state: 'loading' });
        Promise.resolve(p.onCepLookup(digits(m))).then(function (r) {
          if (!r) { cp[1]({ state: 'none' }); return; }
          cp[1]({ state: 'ok' });
          fm[1](function (cur) { return Object.assign({}, cur, { street: r.street || cur.street, district: r.district || cur.district, city: r.city || cur.city, uf: r.uf || cur.uf }); });
        }, function () { cp[1]({ state: 'none' }); });
      } else if (cp[0].state !== 'idle') cp[1]({ state: 'idle' });
    }
    function setWa(i, k, v) { set('whatsapp', f.whatsapp.map(function (w, j) { if (j !== i) return w; var n = Object.assign({}, w); n[k] = v; return n; })); }
    function submit(e) {
      if (e) e.preventDefault();
      tried[1](true);
      if (nErr) { focusFirstInvalid(rootRef.current); return; }
      p.onSave && p.onSave({
        name: f.name.trim(), cnpj: digits(f.cnpj) || null, phone: digits(f.phone) || null, email: f.email.trim().toLowerCase(),
        address: { cep: digits(f.cep) || null, street: f.street || null, number: f.number || null, complement: f.complement || null, district: f.district || null, city: f.city || null, uf: f.uf || null },
        whatsapp: waRows.map(function (w) { return { nome: w.nome.trim(), url: w.url.trim() }; }),
        active: f.active,
        planId: edit ? null : planId[0], endDate: edit || endMode[0] === 'sem' ? null : endDate[0],
      });
    }
    function saveAdmin(data) {
      var target = dlg[0] && dlg[0].admin;
      dlgSaving[1](true);
      Promise.resolve(p.onSaveAdmin && p.onSaveAdmin(data, target || null)).then(function (ok) {
        dlgSaving[1](false);
        if (ok !== false) dlg[1](null);
      }, function () { dlgSaving[1](false); });
    }

    var contract = p.contract;
    var selPlan = plans.filter(function (x) { return x.id === planId[0]; })[0];
    var cityLine = [f.city ? titleCase(f.city) : null, f.uf].filter(Boolean).join(', ');
    var primary = edit ? 'Salvar alterações' : 'Cadastrar academia';

    var adminList = admins.length ? h('ul', { className: 'ck-ct-admins' }, admins.map(function (a) {
      var off = a.active === false;
      return h('li', { key: a.id != null ? a.id : a.key, className: cx('ck-ct-admin', off && 'is-off') },
        h(Avatar, { name: a.name, size: 'md', decorative: true }),
        h('div', { className: 'ck-ct-admin__who' }, h('strong', null, a.name), h('span', null, a.email)),
        h('div', { className: 'ck-ct-admin__roles' },
          off ? h(Badge, { tone: 'outline' }, 'Inativo') : (a.roles || [3]).slice().sort(function (x, y) { return y - x; }).map(function (r) { return h(Badge, { key: r, tone: 'neutral' }, ROLE_NAMES[r] || 'Papel ' + r); })),
        h('div', { className: 'ck-mt-actions' },
          off ? null : h(IconButton, { icon: 'pencil', label: 'Editar ' + a.name, size: 'sm', onClick: function () { dlg[1]({ admin: a }); } }),
          p.onToggleAdmin ? h(ActionMenu, { label: 'Mais ações para ' + a.name, items: [
            off ? { label: 'Reativar acesso', icon: 'power', onClick: function () { p.onToggleAdmin(a); } } : { label: 'Desativar acesso', icon: 'ban', danger: true, onClick: function () { p.onToggleAdmin(a); } }] }) : null,
          p.onRemoveAdmin ? h(IconButton, { icon: 'trash', label: 'Remover ' + a.name, size: 'sm', onClick: function () { p.onRemoveAdmin(a); } }) : null));
    })) : h('div', { className: cx('ck-ct-admins__empty', show.admins && 'is-error') },
      h('span', { className: 'ck-ct-admins__emptyicon', 'aria-hidden': true }, h(Icon, { name: 'user-plus', size: 18 })),
      h('div', null, h('strong', null, 'Nenhum administrador ainda'), h('span', null, 'Adicione pelo menos um administrador para a academia conseguir entrar no painel.')),
      h(Button, { icon: 'user-plus', onClick: function () { dlg[1]({ admin: null }); } }, 'Adicionar administrador'));

    return h('form', { className: 'ck-al-form ck-ct-form', onSubmit: submit, noValidate: true, ref: rootRef },
      h('div', null,
        h(BackLink, { onClick: p.onBack }, 'Academias'),
        h(PageHeader, { title: edit ? 'Editar academia' : 'Nova academia', description: edit ? 'Os dados aparecem para os alunos no app e nas cobranças.' : 'Só o nome, o e-mail e um administrador são obrigatórios. O resto pode ser completado depois.' })),
      h('div', { className: 'ck-al-form__grid' },
        h('div', { className: 'ck-card ck-al-form__main' },
          h(Section, { id: 'ac-s1', icon: 'building', title: 'Dados da academia', done: dataOk },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { className: 'is-wide', label: 'Nome da academia', value: f.name, placeholder: 'Como os alunos conhecem a academia', error: show.name, onChange: function (e) { set('name', e.target.value); } }),
              h(TextField, { label: 'CNPJ', value: f.cnpj, inputMode: 'numeric', placeholder: '00.000.000/0000-00', error: show.cnpj, hint: !show.cnpj ? 'Opcional. Usado nas notas e cobranças.' : null, onChange: function (e) { set('cnpj', maskCnpj(e.target.value)); } }),
              h(TextField, { label: 'Telefone', type: 'tel', icon: 'phone', inputMode: 'numeric', value: f.phone, placeholder: '(00) 00000-0000', error: show.phone, onChange: function (e) { set('phone', maskPhone(e.target.value)); } }),
              h(TextField, { className: 'is-wide', label: 'E-mail de contato', type: 'email', icon: 'mail', value: f.email, placeholder: 'contato@academia.com.br', autoComplete: 'off', error: show.email,
                hint: !show.email ? 'Recebe avisos do Check-in sobre contrato e cobranças.' : null, onChange: function (e) { set('email', e.target.value); } }))),
          h(Section, { id: 'ac-s2', icon: 'map-pin', title: 'Endereço', description: 'Opcional. Aparece no app para os alunos encontrarem a academia.', done: steps[1].done },
            h('div', { className: 'ck-al-grid ck-al-grid--addr' },
              h(TextField, { label: 'CEP', value: f.cep, inputMode: 'numeric', placeholder: '00000-000', error: cp[0].state === 'none' ? 'CEP não encontrado. Preencha à mão.' : null,
                hint: cp[0].state === 'ok' ? 'Endereço preenchido pelo CEP.' : 'Preenche rua, bairro e cidade.',
                suffix: cp[0].state === 'loading' ? h(Icon, { name: 'loader', size: 16, className: 'ck-spin ck-al-cepspin' }) : cp[0].state === 'ok' ? h(Icon, { name: 'check-circle', size: 16, className: 'ck-al-cepok' }) : null,
                onChange: function (e) { onCep(e.target.value); } }),
              h(TextField, { className: 'ck-al-span2', label: 'Rua', value: f.street, onChange: function (e) { set('street', e.target.value); } }),
              h(TextField, { label: 'Número', value: f.number, placeholder: 'Nº ou s/n', onChange: function (e) { set('number', e.target.value); } }),
              h(TextField, { className: 'ck-al-span2', label: 'Complemento', value: f.complement, placeholder: 'Sala, bloco, referência', onChange: function (e) { set('complement', e.target.value); } }),
              h(TextField, { label: 'Bairro', value: f.district, onChange: function (e) { set('district', e.target.value); } }),
              h(TextField, { label: 'Cidade', value: f.city, onChange: function (e) { set('city', e.target.value); } }),
              h(SelectField, { label: 'Estado', value: f.uf, placeholder: 'Escolha', options: UFS.map(function (u) { return { value: u[0], label: u[0] + ', ' + u[1] }; }), onChange: function (e) { set('uf', e.target.value); } }))),
          h(Section, { id: 'ac-s3', icon: 'message-circle', title: 'Grupos de WhatsApp', description: 'Opcional. Os alunos veem os links no app.', done: waRows.length > 0 && !Object.keys(err).some(function (k) { return k.indexOf('wa') === 0; }),
            aside: h(Button, { size: 'sm', icon: 'plus', onClick: function () { set('whatsapp', f.whatsapp.concat([{ nome: '', url: '' }])); } }, 'Adicionar') },
            f.whatsapp.length ? h('div', { className: 'ck-ct-wa' }, f.whatsapp.map(function (w, i) {
              return h('div', { key: i, className: 'ck-ct-wa__row' },
                h(TextField, { label: 'Nome do grupo', value: w.nome, placeholder: 'Avisos da academia', error: show['wan' + i], onChange: function (e) { setWa(i, 'nome', e.target.value); } }),
                h(TextField, { label: 'Link de convite', value: w.url, placeholder: 'https://chat.whatsapp.com/…', error: show['wau' + i], onChange: function (e) { setWa(i, 'url', e.target.value); } }),
                h(IconButton, { icon: 'trash', label: 'Remover grupo', size: 'sm', className: 'ck-ct-wa__del', onClick: function () { set('whatsapp', f.whatsapp.filter(function (_, j) { return j !== i; })); } }));
            })) : h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'Nenhum grupo cadastrado.')),
          !edit ? h(Section, { id: 'ac-s4', icon: 'file-text', title: 'Plano do sistema', description: 'O contrato começa hoje. Dá para trocar de plano depois, em Contratos.', done: steps[3].done },
            h('div', { className: cx('ck-ct-plans ck-ct-pick', show.plan && 'is-error'), role: 'radiogroup', 'aria-label': 'Plano do sistema' },
              p.plansLoading ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'loader', size: 15, className: 'ck-spin' }), 'Carregando planos…')
                : plans.length ? plans.map(function (pl) { return h(PlanCard, { key: pl.id, plan: pl, checked: planId[0] === pl.id, onSelect: function () { planId[1](pl.id); } }); })
                  : h('p', { className: 'ck-al-hint is-error' }, h(Icon, { name: 'alert-triangle', size: 15 }), 'Nenhum plano disponível. Cadastre um em Planos do sistema.')),
            show.plan ? h('p', { className: 'ck-ct-err' }, h(Icon, { name: 'alert-triangle', size: 14 }), show.plan) : null,
            h('div', { className: 'ck-ct-end' },
              h('div', { className: 'ck-field' }, h('div', { className: 'ck-field__top' }, h('span', { className: 'ck-field__label' }, 'Fim do contrato')),
                h(SegmentedControl, { label: 'Fim do contrato', block: true, value: endMode[0], onChange: endMode[1], options: [{ value: 'sem', label: 'Sem data de fim' }, { value: 'ate', label: 'Até uma data' }] })),
              endMode[0] === 'ate' ? h(TextField, { label: 'Termina em', type: 'date', value: endDate[0], min: todayIso(), error: show.end, onChange: function (e) { endDate[1](e.target.value); } }) : null)) : null,
          h(Section, { id: 'ac-s5', icon: 'shield-check', title: 'Administradores', description: 'Quem entra no painel desta academia.', done: admins.length > 0,
            aside: admins.length ? h(Button, { size: 'sm', icon: 'user-plus', onClick: function () { dlg[1]({ admin: null }); } }, 'Adicionar') : null },
            p.adminsLoading ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'loader', size: 15, className: 'ck-spin' }), 'Carregando administradores…') : adminList,
            show.admins && admins.length === 0 ? h('p', { className: 'ck-ct-err' }, h(Icon, { name: 'alert-triangle', size: 14 }), show.admins) : null,
            h('div', { className: 'ck-al-active' },
              h(Switch, { label: 'Academia ativa', description: f.active ? 'O painel e o app funcionam normalmente.' : 'Admins e alunos não conseguem entrar. Os dados ficam guardados.', checked: f.active, onChange: function (v) { set('active', v); } })))),
        h('aside', { className: 'ck-al-sum', 'aria-label': 'Resumo da academia' },
          h('div', { className: 'ck-al-sum__who' },
            f.name.trim() ? h('span', { className: 'ck-ct-sumlogo', 'aria-hidden': true }, initialsOf(f.name)) : h('span', { className: 'ck-al-sum__ph ck-ct-sumph', 'aria-hidden': true }, h(Icon, { name: 'building', size: 22 })),
            h('div', { style: { minWidth: 0 } },
              h('strong', null, f.name.trim() || 'Nome da academia'),
              h('span', null, cityLine || f.email.trim() || 'Cidade ainda não informada'))),
          h(Steps, { steps: steps, tried: tried[0] }),
          edit ? h('div', { className: 'ck-ct-sumct' },
            h('span', { className: 'ck-ct-sumct__label' }, 'Contrato'),
            contract ? h(React.Fragment, null, h('strong', null, contract.plan), h('span', null, contract.note))
              : h(React.Fragment, null, h('strong', null, 'Sem contrato'), h('span', null, 'O painel fica bloqueado até ter um contrato.')),
            p.onOpenContract ? h('button', { type: 'button', className: 'ck-ct-sumct__link', onClick: p.onOpenContract }, contract ? 'Ver contrato' : 'Criar contrato', h(Icon, { name: 'arrow-right', size: 15 })) : null)
            : h('div', { className: 'ck-ct-sumct' },
              h('span', { className: 'ck-ct-sumct__label' }, 'Contrato'),
              selPlan ? h(React.Fragment, null, h('strong', null, selPlan.name), h('span', null, planSummary(selPlan, endMode[0] === 'ate' ? endDate[0] : null))) : h('span', null, 'Escolha o plano.')),
          tried[0] && nErr ? h('p', { className: 'ck-al-sum__err', role: 'alert' }, errCount(nErr)) : null,
          h(Button, { type: 'submit', variant: 'primary', size: 'lg', block: true, icon: edit ? 'save' : 'plus', loading: p.saving, disabled: edit && !dirty }, p.saving ? 'Salvando…' : primary),
          edit && !dirty ? h('span', { className: 'ck-al-sum__note' }, 'Nenhuma alteração ainda.') : null,
          p.onCancel ? h('button', { type: 'button', className: 'ck-al-sum__cancel', onClick: p.onCancel }, 'Cancelar') : null)),
      dlg[0] ? h(AdminDialog, { admin: dlg[0].admin, academyName: f.name.trim() || null, saving: dlgSaving[0], onClose: function () { dlg[1](null); }, onSave: saveAdmin }) : null);
  }

  /* ---------- ContratosLista ---------- */
  var CT_SIT = {
    ativo: ['success', 'Ativo'], pendente: ['warning', 'Pagamento pendente'], vence: ['warning', 'Vence em breve'],
    bloqueado: ['danger', 'Bloqueado'], encerrado: ['outline', 'Encerrado'],
  };
  var CT_FILTERS = [['todos', 'Todos'], ['ativos', 'Ativos', 'success'], ['vencem', 'Vencem em 30 dias', 'warning'], ['encerrados', 'Encerrados', 'outline']];
  function situacao(r, today) {
    if (r.status === 'cancelado' || (r.end && r.end < today)) return 'encerrado';
    if (r.status === 'bloqueado') return 'bloqueado';
    if (r.status === 'pendente') return 'pendente';
    if (r.end && dayDiff(today, r.end) <= 30) return 'vence';
    return 'ativo';
  }
  function ctMatch(sit, f) {
    if (f === 'ativos') return sit !== 'encerrado';
    if (f === 'vencem') return sit === 'vence';
    if (f === 'encerrados') return sit === 'encerrado';
    return true;
  }
  var PAY_ICON = { pix: 'qr-code', boleto: 'receipt', cartao: 'credit-card', cortesia: 'tag' };
  function payKind(name) {
    var n = String(name || '').toLowerCase();
    if (n.indexOf('pix') >= 0) return 'pix';
    if (n.indexOf('boleto') >= 0) return 'boleto';
    if (n.indexOf('cart') >= 0 || n.indexOf('créd') >= 0 || n.indexOf('cred') >= 0 || n.indexOf('déb') >= 0) return 'cartao';
    return null;
  }
  function ContratosLista(p) {
    var today = p.today || todayIso();
    var qs = useControlled(p.query, '', p.onQueryChange), q = qs[0];
    var fs = useControlled(p.filter, 'todos', p.onFilterChange), f = fs[0];
    var all = useMemo(function () { return (p.rows || []).map(function (r) { return Object.assign({ sit: situacao(r, today) }, r); }); }, [p.rows, today]);
    var counts = { todos: all.length, ativos: 0, vencem: 0, encerrados: 0 };
    all.forEach(function (r) { ['ativos', 'vencem', 'encerrados'].forEach(function (k) { if (ctMatch(r.sit, k)) counts[k]++; }); });
    var rows = all.filter(function (r) {
      if (!ctMatch(r.sit, f)) return false;
      if (!q) return true;
      return (r.academy + ' ' + r.plan + ' #' + r.id).toLowerCase().indexOf(q.toLowerCase()) >= 0;
    });
    var s = p.summary || {};
    function open(r) { p.onOpen && p.onOpen(r); }
    return h('div', { className: 'ck-ml ck-ct' },
      h(PageHeader, { title: 'Contratos', description: 'Qual plano do Check-in cada academia usa, por quanto e até quando.',
        actions: h(Button, { variant: 'primary', icon: 'plus', onClick: p.onNew }, 'Novo contrato') }),
      h('div', { className: 'ck-db__kpis' },
        h(StatTile, { label: 'Contratos ativos', icon: 'file-text', value: num(counts.ativos), note: 'de ' + plural(counts.todos, 'contrato', 'contratos') }),
        h(StatTile, { label: 'Receita mensal', icon: 'wallet', value: money(s.revenue), note: s.revenueNote || 'somando os contratos ativos' }),
        h(StatTile, { label: 'Vencem em 30 dias', icon: 'calendar', value: num(counts.vencem), note: counts.vencem ? 'renove antes do bloqueio' : 'nenhum vencendo' }),
        h(StatTile, { label: 'Academias sem contrato', icon: 'building', value: s.withoutContract != null ? num(s.withoutContract) : '—',
          note: s.withoutContract ? (p.onNew ? h('button', { type: 'button', className: 'ck-linkbtn', onClick: p.onNew }, 'criar contrato') : 'painel bloqueado') : 'todas cobertas' })),
      h('section', { className: 'ck-card ck-ml__panel', 'aria-label': 'Lista de contratos' },
        h('div', { className: 'ck-ml__toolbar' },
          h('div', { className: 'ck-control ck-ml__search' },
            h(Icon, { name: 'search', size: 18, className: 'ck-control__icon' }),
            h('input', { type: 'search', className: 'ck-control__input', value: q, placeholder: 'Buscar por academia ou plano', 'aria-label': 'Buscar contratos', onChange: function (e) { qs[1](e.target.value); } }),
            q ? h(IconButton, { icon: 'x', label: 'Limpar busca', size: 'sm', onClick: function () { qs[1](''); } }) : null),
          h('div', { className: 'ck-ml__filters', role: 'radiogroup', 'aria-label': 'Filtrar contratos' }, CT_FILTERS.map(function (x) {
            return h('button', { key: x[0], type: 'button', role: 'radio', 'aria-checked': f === x[0], className: 'ck-ml__chip', onClick: function () { fs[1](x[0]); } },
              x[2] ? h('span', { className: 'ck-ml__chipdot ck-ml__chipdot--' + x[2], 'aria-hidden': true }) : null,
              x[1], h('span', { className: 'ck-ml__count' }, counts[x[0]]));
          }))),
        p.loading ? h('div', { className: 'ck-ml__empty' }, h(Icon, { name: 'loader', size: 20, className: 'ck-spin' }), h('strong', null, 'Carregando contratos…'))
          : rows.length ? h('div', { className: 'ck-mt-table-wrap' },
            h('table', { className: 'ck-mt-table ck-ml__table ck-ct__table' },
              h('thead', null, h('tr', null,
                h('th', { scope: 'col' }, 'Academia'), h('th', { scope: 'col' }, 'Plano'), h('th', { scope: 'col' }, 'Vigência'),
                h('th', { scope: 'col', className: 'is-num' }, 'Valor'), h('th', { scope: 'col' }, 'Situação'), h('th', { scope: 'col' }, h('span', { className: 'ck-sr' }, 'Ações')))),
              h('tbody', null, rows.map(function (r) {
                var st = CT_SIT[r.sit], left = r.end ? dayDiff(today, r.end) : null, off = r.sit === 'encerrado';
                var pk = r.planType === 'gratuito' ? 'cortesia' : payKind(r.payment);
                var payLabel = r.planType === 'gratuito' ? 'Cortesia' : r.payment;
                return h('tr', { key: r.id, className: cx('ck-ml__row', off && 'is-off'), onClick: function (e) { if (!e.target.closest('button, a')) open(r); } },
                  h('td', null, h('div', { className: 'ck-ml__who' },
                    h(Logo, { name: r.academy, size: 'sm' }),
                    h('span', { style: { minWidth: 0 } },
                      h('a', { href: '#', className: 'ck-ml__name', onClick: function (e) { e.preventDefault(); open(r); } }, r.academy),
                      h('span', { className: 'ck-mt-cell__sub' }, 'Contrato #' + r.id)))),
                  h('td', null,
                    h('span', { className: 'ck-mt-cell__main' }, r.plan),
                    payLabel ? h('span', { className: 'ck-mt-cell__sub ck-ct__pay' }, h(Icon, { name: PAY_ICON[pk] || 'wallet', size: 13 }), payLabel) : h('span', { className: 'ck-mt-cell__sub' }, 'Sem pagamento')),
                  h('td', null,
                    h('span', { className: 'ck-mt-cell__main' }, (r.start && r.start > today ? 'A partir de ' : 'Desde ') + fdate(r.start)),
                    h('span', { className: cx('ck-mt-cell__sub', r.sit === 'vence' && 'ck-ct__warn') },
                      !r.end ? 'Sem data de fim' : left < 0 ? 'Venceu em ' + fdate(r.end) : left === 0 ? 'Vence hoje' : r.sit === 'vence' ? 'Vence em ' + plural(left, 'dia', 'dias') : 'Até ' + fdate(r.end))),
                  h('td', { className: 'is-num' },
                    r.planType === 'gratuito' ? h('span', { className: 'ck-mt-cell__main ck-ct__free' }, 'Grátis')
                      : r.planType === 'por_aluno' ? h(React.Fragment, null, h('span', { className: 'ck-mt-cell__main' }, money(r.perStudent)), h('span', { className: 'ck-mt-cell__sub' }, 'por aluno ativo'))
                        : h(React.Fragment, null, h('span', { className: 'ck-mt-cell__main' }, money(r.amount)), h('span', { className: 'ck-mt-cell__sub' }, r.cycle || 'por mês'))),
                  h('td', null, h(Badge, { tone: st[0], dot: st[0] !== 'outline' }, st[1])),
                  h('td', { className: 'ck-mt-actions' },
                    h(ActionMenu, { label: 'Ações do contrato #' + r.id, items: [
                      { label: 'Ver detalhes', icon: 'arrow-right', onClick: function () { open(r); } },
                      p.onOpenAcademy ? { label: 'Abrir academia', icon: 'building', onClick: function () { p.onOpenAcademy(r); } } : null,
                      !off && p.onChangePlan ? { label: 'Trocar plano', icon: 'repeat', onClick: function () { p.onChangePlan(r); } } : null,
                      !off && p.onCancel ? '-' : null,
                      !off && p.onCancel ? { label: 'Encerrar contrato', icon: 'ban', danger: true, onClick: function () { p.onCancel(r); } } : null].filter(Boolean) })));
              })))) :
            h('div', { className: 'ck-ml__empty' },
              h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: q ? 'search' : 'file-text', size: 20 })),
              h('strong', null, q ? 'Nenhum contrato encontrado para "' + q + '"' : all.length ? 'Nenhum contrato neste filtro' : 'Nenhum contrato ainda'),
              h('p', null, q ? 'Busque pelo nome da academia ou do plano.' : 'Ligue uma academia a um plano do Check-in para liberar o painel dela.'),
              q ? h(Button, { size: 'sm', onClick: function () { qs[1](''); } }, 'Limpar busca') : h(Button, { size: 'sm', icon: 'plus', onClick: p.onNew }, 'Novo contrato')),
        h('footer', { className: 'ck-ml__foot' },
          h('span', null, plural(rows.length, 'contrato', 'contratos'), f !== 'todos' || q ? ' de ' + counts.todos : ''))));
  }

  /* ---------- NovoContrato ---------- */
  var MONTHS = [3, 6, 12, 24];
  function NovoContrato(p) {
    var today = p.today || todayIso();
    var academies = p.academies || [], plans = p.plans || [], pays = p.paymentMethods || [];
    var ac = useState(p.academyId != null ? p.academyId : (academies.length === 1 ? academies[0].id : null));
    var pl = useState(p.planId != null ? p.planId : null);
    var pay = useState(null);
    var start = useState(today), endMode = useState('sem'), end = useState(addMonths(today, 12));
    var notes = useState(''), tried = useState(false), aq = useState('');
    var rootRef = useRef(null);

    var academy = academies.filter(function (a) { return a.id === ac[0]; })[0];
    var plan = plans.filter(function (x) { return x.id === pl[0]; })[0];
    var free = plan && plan.type === 'gratuito';
    var swap = !!(academy && academy.currentPlan);
    var startVal = swap ? today : start[0];
    var endVal = endMode[0] === 'ate' ? end[0] : null;
    var months = endVal ? monthsBetween(startVal, endVal) : null;

    var err = {};
    if (!academy) err.academy = 'Escolha a academia.';
    if (!plan) err.plan = 'Escolha o plano.';
    if (plan && !free && !pay[0]) err.pay = 'Escolha como a academia paga.';
    if (!swap && !startVal) err.start = 'Informe o início.';
    if (endVal && endVal <= startVal) err.end = 'O fim precisa ser depois do início.';
    else if (endMode[0] === 'ate' && !endVal) err.end = 'Escolha até quando vale o contrato.';
    var show = tried[0] ? err : {};
    var nErr = Object.keys(err).length;

    var shownAcademies = academies.length > 6 && aq[0]
      ? academies.filter(function (a) { return (a.name + ' ' + (a.city || '')).toLowerCase().indexOf(aq[0].toLowerCase()) >= 0 || a.id === ac[0]; })
      : academies;
    var payOptions = pays;
    var payName = (pays.filter(function (x) { return x.id === pay[0]; })[0] || {}).name;

    function submit(e) {
      if (e) e.preventDefault();
      tried[1](true);
      if (nErr) { focusFirstInvalid(rootRef.current); return; }
      p.onSubmit && p.onSubmit({ academyId: academy.id, planId: plan.id, paymentId: free ? null : pay[0], start: startVal, end: endVal, notes: notes[0].trim() || null, swap: swap });
    }

    var bigPrice = !plan ? '—' : plan.type === 'gratuito' ? 'Grátis' : plan.type === 'por_aluno' ? money(plan.perStudent) : money(plan.price);
    var per = !plan || plan.type === 'gratuito' ? null : plan.type === 'por_aluno' ? 'por aluno' : (plan.cycle || 'por mês');

    return h('form', { className: 'ck-al-form ck-ct-form', onSubmit: submit, noValidate: true, ref: rootRef },
      h('div', null,
        h(BackLink, { onClick: p.onBack }, 'Contratos'),
        h(PageHeader, { title: 'Novo contrato', description: 'Ligue uma academia a um plano do Check-in. O painel dela é liberado no início do contrato.' })),
      h('div', { className: 'ck-al-form__grid' },
        h('div', { className: 'ck-card ck-al-form__main' },
          h(Section, { id: 'nc-s1', icon: 'building', title: 'Academia', done: !!academy },
            academies.length > 6 ? h('div', { className: 'ck-control ck-ml__search ck-ct-acsearch' },
              h(Icon, { name: 'search', size: 18, className: 'ck-control__icon' }),
              h('input', { type: 'search', className: 'ck-control__input', value: aq[0], placeholder: 'Buscar academia', 'aria-label': 'Buscar academia', onChange: function (e) { aq[1](e.target.value); } })) : null,
            p.loading ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'loader', size: 15, className: 'ck-spin' }), 'Carregando academias…')
              : academies.length ? h('div', { className: cx('ck-ct-acs ck-ct-pick', show.academy && 'is-error'), role: 'radiogroup', 'aria-label': 'Academia' }, shownAcademies.map(function (a) {
                return h('button', { key: a.id, type: 'button', role: 'radio', 'aria-checked': ac[0] === a.id, className: 'ck-ct-ac', onClick: function () { ac[1](a.id); } },
                  h(Logo, { name: a.name, size: 'sm' }),
                  h('span', { className: 'ck-ct-ac__text' }, h('strong', null, a.name),
                    h('span', null, a.currentPlan ? 'Contrato atual: ' + a.currentPlan : [a.city, a.uf].filter(Boolean).join(', ') || 'Sem contrato')),
                  h('span', { className: 'ck-choice__radio ck-ct-radio', 'aria-hidden': true }));
              })) : h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'Nenhuma academia ativa cadastrada.'),
            show.academy ? h('p', { className: 'ck-ct-err' }, h(Icon, { name: 'alert-triangle', size: 14 }), show.academy) : null,
            swap ? h(Callout, { tone: 'warning', title: academy.name + ' já tem um contrato ativo' },
              'O contrato ' + academy.currentPlan + ' é encerrado e o novo começa hoje. O histórico de pagamentos continua guardado.') : null),
          h(Section, { id: 'nc-s2', icon: 'package', title: 'Plano do sistema', done: !!plan },
            h('div', { className: cx('ck-ct-plans ck-ct-pick', show.plan && 'is-error'), role: 'radiogroup', 'aria-label': 'Plano do sistema' },
              plans.map(function (x) { return h(PlanCard, { key: x.id, plan: x, checked: pl[0] === x.id, onSelect: function () { pl[1](x.id); } }); })),
            show.plan ? h('p', { className: 'ck-ct-err' }, h(Icon, { name: 'alert-triangle', size: 14 }), show.plan) : null,
            plan && plan.type === 'por_aluno' ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'A cobrança de cada mês soma os alunos com matrícula ativa' + (plan.maxStudents ? ', até ' + num(plan.maxStudents) + '.' : '.')) : null),
          !free ? h(Section, { id: 'nc-s3', icon: 'wallet', title: 'Forma de pagamento', description: 'Como a academia paga o Check-in.', done: !!pay[0] },
            h('div', { className: cx('ck-ct-pays ck-ct-pick', show.pay && 'is-error'), role: 'radiogroup', 'aria-label': 'Forma de pagamento' }, payOptions.map(function (x) {
              return h('button', { key: x.id, type: 'button', role: 'radio', 'aria-checked': pay[0] === x.id, className: 'ck-ct-pay', onClick: function () { pay[1](x.id); } },
                h('span', { className: 'ck-ct-pay__icon', 'aria-hidden': true }, h(Icon, { name: PAY_ICON[payKind(x.name)] || 'wallet', size: 16 })),
                h('strong', null, x.name),
                h('span', { className: 'ck-choice__radio ck-ct-radio', 'aria-hidden': true }));
            })),
            show.pay ? h('p', { className: 'ck-ct-err' }, h(Icon, { name: 'alert-triangle', size: 14 }), show.pay) : null) : null,
          h(Section, { id: 'nc-s4', icon: 'calendar', title: 'Vigência', done: !err.start && !err.end },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { label: 'Início', type: 'date', value: startVal, readOnly: swap, error: show.start,
                hint: swap ? 'Na troca de plano, o novo contrato começa hoje.' : startVal === today ? 'Hoje.' : startVal > today ? 'Daqui a ' + plural(dayDiff(today, startVal), 'dia', 'dias') + '.' : 'Data no passado.',
                onChange: function (e) { start[1](e.target.value); } }),
              h('div', { className: 'ck-field' }, h('div', { className: 'ck-field__top' }, h('span', { className: 'ck-field__label' }, 'Fim')),
                h(SegmentedControl, { label: 'Fim do contrato', block: true, value: endMode[0], onChange: endMode[1], options: [{ value: 'sem', label: 'Sem data de fim' }, { value: 'ate', label: 'Até uma data' }] }))),
            endMode[0] === 'ate' ? h('div', { className: 'ck-al-grid' },
              h(TextField, { label: 'Termina em', type: 'date', value: end[0], min: startVal, error: show.end,
                hint: !show.end && months ? plural(months, 'mês', 'meses') + ' de contrato.' : !show.end && endVal ? plural(dayDiff(startVal, endVal), 'dia', 'dias') + ' de contrato.' : null,
                onChange: function (e) { end[1](e.target.value); } }),
              h('div', { className: 'ck-ct-months', role: 'group', 'aria-label': 'Duração' }, MONTHS.map(function (m) {
                return h('button', { key: m, type: 'button', className: 'ck-ml__chip', 'aria-pressed': months === m, 'aria-checked': months === m, onClick: function () { end[1](addMonths(startVal, m)); } }, m + ' meses');
              })))
              : h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'O contrato vale até ser encerrado ou trocado de plano.'),
            h(TextAreaField, { label: 'Observações', value: notes[0], placeholder: 'Condições combinadas, desconto, contato do financeiro', hint: 'Só o super admin vê.', onChange: function (e) { notes[1](e.target.value); } }))),
        h('aside', { className: 'ck-al-sum', 'aria-label': 'Resumo do contrato' },
          h('div', { className: 'ck-al-sum__who' },
            academy ? h('span', { className: 'ck-ct-sumlogo', 'aria-hidden': true }, initialsOf(academy.name)) : h('span', { className: 'ck-al-sum__ph ck-ct-sumph', 'aria-hidden': true }, h(Icon, { name: 'building', size: 22 })),
            h('div', { style: { minWidth: 0 } },
              h('strong', null, academy ? academy.name : 'Academia'),
              h('span', null, plan ? 'Plano ' + plan.name : 'Escolha o plano'))),
          h('div', { className: 'ck-ct-sumprice' }, h('strong', null, bigPrice), per ? h('span', null, per) : null),
          h('dl', { className: 'ck-ct-sumfacts' },
            h('div', null, h('dt', null, 'Pagamento'), h('dd', null, free ? 'Cortesia' : payName || '—')),
            h('div', null, h('dt', null, 'Vigência'), h('dd', null, fdate(startVal), h('span', null, endVal ? 'até ' + fdate(endVal) : 'sem data de fim')))),
          tried[0] && nErr ? h('p', { className: 'ck-al-sum__err', role: 'alert' }, errCount(nErr)) : null,
          h(Button, { type: 'submit', variant: 'primary', size: 'lg', block: true, icon: swap ? 'repeat' : 'file-text', loading: p.saving },
            p.saving ? 'Salvando…' : academy ? (swap ? 'Trocar plano de ' : 'Criar contrato para ') + academy.name : 'Criar contrato'),
          p.onCancel ? h('button', { type: 'button', className: 'ck-al-sum__cancel', onClick: p.onCancel }, 'Cancelar') : null)));
  }

export { AcademiaForm, ContratosLista, NovoContrato, payKind, planFromApi };
