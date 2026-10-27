/* Usuários: lista e formulário, portados das referências UsuariosLista/UsuarioForm.
   Integração da API nas telas de usuarios; estilos ck-usuarios.css e primitivas do painel. */
import React from 'react';
import { ActionMenu, Avatar, Badge, Button, Icon, IconButton, PageHeader, SelectField, Switch, TextField } from './index';
import { AlSection, BackLink, dayDiff, digits, fdate, maskCep, maskCpf, maskPhone, titleCase, UFS } from './pessoas';
import { pfCpfOk } from './professores';
var h = React.createElement;
var useState = React.useState, useEffect = React.useEffect, useRef = React.useRef;
function cx() { return Array.prototype.filter.call(arguments, Boolean).join(' '); }
function pmNorm(v) { return String(v || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
function num(v) { return Number(v || 0).toLocaleString('pt-BR'); }
function isoToday() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  var US_TYPES = { aluno: ['info', 'Aluno'], professor: ['brand', 'Professor'], admin: ['neutral', 'Admin'], financeiro: ['neutral', 'Financeiro'], comercial: ['neutral', 'Comercial'], suporte: ['warning', 'SuperAdmin'] };
  function usHi(text, q) {
    if (!q) return text; var nt = pmNorm(text), i = nt.indexOf(q);
    if (i < 0) return text;
    return h(React.Fragment, null, text.slice(0, i), h('mark', { className: 'ck-us__hi' }, text.slice(i, i + q.length)), text.slice(i + q.length));
  }
  function usAgo(iso, today) {
    if (!iso) return 'Não informado';
    var d = dayDiff(iso.slice(0, 10), today || isoToday());
    return d <= 0 ? 'Hoje' : d === 1 ? 'Ontem' : d < 30 ? 'Há ' + d + ' dias' : d < 365 ? 'Há ' + Math.floor(d / 30) + (Math.floor(d / 30) === 1 ? ' mês' : ' meses') : 'Há mais de 1 ano';
  }
  function UsuariosLista(p) {
    var qs = useState(''), dq = useState(''), type = useState('todos'), stt = useState('ativos'), page = useState(1), size = useState(p.pageSize || 20);
    var srv = useState(null), loading = useState(false), inRef = useRef(null), reqId = useRef(0);
    useEffect(function () { var t = setTimeout(function () { dq[1](qs[0].trim()); page[1](1); }, 300); return function () { clearTimeout(t); }; }, [qs[0]]);
    useEffect(function () {
      function k(e) { if (e.key === '/' && document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(document.activeElement.tagName) < 0) { e.preventDefault(); if (inRef.current) inRef.current.focus(); } }
      document.addEventListener('keydown', k); return function () { document.removeEventListener('keydown', k); };
    }, []);
    var q = pmNorm(dq[0]), qd = digits(dq[0]);
    var local = null;
    if (!p.onQuery) {
      var all = p.users || [];
      var base = all.filter(function (u) { return !q || pmNorm(u.name + ' ' + (u.email || '')).indexOf(q) >= 0 || (qd.length >= 3 && (digits(u.cpf) + ' ' + digits(u.phone)).indexOf(qd) >= 0); });
      var counts = { todos: 0, aluno: 0, equipe: 0, ativos: 0, inativos: 0 };
      base.forEach(function (u) { var eq = (u.types || []).some(function (t) { return t !== 'aluno'; }); if ((stt[0] === 'ativos') === (u.active !== false) || stt[0] === 'todos') { counts.todos++; if ((u.types || []).indexOf('aluno') >= 0) counts.aluno++; if (eq) counts.equipe++; } if (u.active === false) counts.inativos++; else counts.ativos++; });
      var rows = base.filter(function (u) {
        if (stt[0] === 'ativos' && u.active === false) return false; if (stt[0] === 'inativos' && u.active !== false) return false;
        if (type[0] === 'aluno') return (u.types || []).indexOf('aluno') >= 0;
        if (type[0] === 'equipe') return (u.types || []).some(function (t) { return t !== 'aluno'; });
        return true;
      });
      local = { rows: rows.slice((Math.min(page[0], Math.max(1, Math.ceil(rows.length / size[0]))) - 1) * size[0], Math.min(page[0], Math.max(1, Math.ceil(rows.length / size[0]))) * size[0]), total: rows.length, counts: counts, grand: all.length };
    }
    useEffect(function () {
      if (!p.onQuery) return;
      var id = ++reqId.current; loading[1](true);
      Promise.resolve(p.onQuery({ q: dq[0], type: type[0], status: stt[0], page: page[0], size: size[0] })).then(function (r) { if (id === reqId.current) { srv[1](r); loading[1](false); } }, function () { if (id === reqId.current) loading[1](false); });
    }, [dq[0], type[0], stt[0], page[0], size[0], p.version]);
    var data = local || srv[0] || { rows: [], total: 0, counts: {} };
    var pages = Math.max(1, Math.ceil(data.total / size[0])), cur = Math.min(page[0], pages);
    var from = data.total ? (cur - 1) * size[0] + 1 : 0, to = Math.min(cur * size[0], data.total);
    var typing = qs[0].trim() !== dq[0];
    function pageList() { var out = [], s = Math.max(1, cur - 2), e = Math.min(pages, s + 4); s = Math.max(1, e - 4); if (s > 1) out.push(1, s > 2 ? '…' : null); for (var i = s; i <= e; i++) out.push(i); if (e < pages) out.push(e < pages - 1 ? '…' : null, pages); return out.filter(function (x) { return x != null; }); }
    function go(n) { page[1](Math.max(1, Math.min(pages, n))); var el = document.querySelector('.ck-us .ck-pc__panel'); if (el && el.scrollIntoView && el.getBoundingClientRect().top < 0) el.scrollIntoView({ block: 'start' }); }
    var c = data.counts || {};
    return h('div', { className: 'ck-pc ck-us' },
      h(PageHeader, { title: 'Usuários', description: 'Todas as contas que entram no Check-in: alunos e equipe. Aqui ficam login, senha e situação da conta.',
        actions: p.onNew ? h(Button, { variant: 'primary', icon: 'user-plus', onClick: p.onNew }, 'Novo usuário') : null }),
      h('section', { className: 'ck-pc__panel' },
        h('div', { className: 'ck-pf__bar ck-us__bar' },
          h('div', { className: 'ck-control ck-ml__search' }, h(Icon, { name: 'search', size: 16, className: 'ck-control__icon' }),
            h('input', { ref: inRef, type: 'search', className: 'ck-control__input', value: qs[0], placeholder: 'Nome, e-mail, CPF ou telefone', 'aria-label': 'Buscar usuário', 'aria-controls': 'us-results', onChange: function (e) { qs[1](e.target.value); }, onKeyDown: function (e) { if (e.key === 'Escape') qs[1](''); } }),
            typing || loading[0] ? h(Icon, { name: 'loader', size: 16, className: 'ck-spin ck-us__spin' }) : qs[0] ? h(IconButton, { icon: 'x', label: 'Limpar busca', size: 'sm', onClick: function () { qs[1](''); if (inRef.current) inRef.current.focus(); } }) : h('kbd', { className: 'ck-us__kbd', 'aria-hidden': true }, '/')),
          h('div', { className: 'ck-ml__filters', role: 'radiogroup', 'aria-label': 'Tipo' }, [['todos', 'Todos'], ['aluno', 'Alunos'], ['equipe', 'Equipe']].map(function (x) {
            return h('button', { key: x[0], type: 'button', role: 'radio', 'aria-checked': type[0] === x[0], className: 'ck-ml__chip', onClick: function () { type[1](x[0]); page[1](1); } }, x[1], c[x[0]] != null ? h('span', { className: 'ck-ml__count' }, num(c[x[0]])) : null);
          })),
          h('div', { className: 'ck-us__status' }, h(SelectField, { 'aria-label': 'Situação', value: stt[0], onChange: function (e) { stt[1](e.target.value); page[1](1); }, options: [{ value: 'ativos', label: 'Ativos' + (c.ativos != null ? ' (' + num(c.ativos) + ')' : '') }, { value: 'inativos', label: 'Inativos' + (c.inativos != null ? ' (' + num(c.inativos) + ')' : '') }, { value: 'todos', label: 'Ativos e inativos' }] }))),
        h('p', { className: 'ck-us__count', role: 'status', 'aria-live': 'polite' }, p.loading ? 'Carregando…' : typing ? 'Buscando…' : dq[0] ? (data.total ? h(React.Fragment, null, h('b', null, num(data.total)), data.total === 1 ? ' usuário com "' : ' usuários com "', dq[0], '"') : 'Nenhum usuário com "' + dq[0] + '"') : h(React.Fragment, null, h('b', null, num(data.total)), data.total === 1 ? ' usuário' : ' usuários')),
        p.loading && !(p.users || []).length ? h('p', { className: 'ck-us__loading', role: 'status' }, 'Carregando usuários…') : data.rows.length ? h('div', { className: cx('ck-pc__wrap', loading[0] && 'is-loading'), id: 'us-results' }, h('table', { className: 'ck-pc__table is-fixed ck-pf__table' },
          h('colgroup', null, [36, 22, 16, 14, 12].map(function (w, i) { return h('col', { key: i, style: { width: w + '%' } }); })),
          h('thead', null, h('tr', null, h('th', { scope: 'col' }, 'Usuário'), h('th', { scope: 'col' }, 'Tipo'), p.showAcademy ? h('th', { scope: 'col' }, 'Academia') : h('th', { scope: 'col' }, 'Último acesso'), h('th', { scope: 'col' }, 'Situação'), h('th', { scope: 'col' }, h('span', { className: 'ck-sr' }, 'Ações')))),
          h('tbody', null, data.rows.map(function (u) {
            var off = u.active === false, nm = titleCase(u.name);
            return h('tr', { key: u.id, className: off ? 'is-off' : undefined },
              h('th', { scope: 'row' }, h('div', { className: 'ck-pc__name' },
                h(Avatar, { name: nm, src: u.photo, size: 'md' }),
                h('div', { style: { minWidth: 0 } },
                  p.onEdit ? h('button', { type: 'button', className: 'ck-pf__link', onClick: function () { p.onEdit(u); } }, usHi(nm, q)) : h('strong', null, usHi(nm, q)),
                  h('span', { title: u.email }, usHi(u.email || 'Sem e-mail', q))))),
              h('td', null, h('div', { className: 'ck-us__types' }, (u.types || []).map(function (t) { var x = US_TYPES[t] || ['neutral', t]; return h(Badge, { key: t, tone: x[0] }, x[1]); }))),
              p.showAcademy ? h('td', null, u.academy || '—') : h('td', null, h('span', { className: 'ck-pc__main ck-us__ago' }, usAgo(u.lastAccess, p.today)), u.lastAccess ? h('span', { className: 'ck-pc__sub' }, fdate(u.lastAccess.slice(0, 10))) : null),
              h('td', null, off ? h(Badge, { tone: 'outline' }, 'Inativo') : h(Badge, { tone: 'success', dot: true }, 'Ativo')),
              h('td', null, h('div', { className: 'ck-pc__acts' },
                p.onEdit ? h(IconButton, { icon: 'pencil', label: 'Editar ' + nm, variant: 'secondary', size: 'sm', onClick: function () { p.onEdit(u); } }) : null,
                h(ActionMenu, { label: 'Mais ações para ' + nm, items: [
                  p.onOpenStudent && (u.types || []).indexOf('aluno') >= 0 ? { label: 'Abrir ficha do aluno', icon: 'user', onClick: function () { p.onOpenStudent(u); } } : null,
                  p.onPermissions && (u.types || []).some(function (t) { return t !== 'aluno'; }) ? { label: 'Papel e permissões', icon: 'shield-check', onClick: function () { p.onPermissions(u); } } : null,
                  p.onResetPassword ? { label: 'Enviar link de nova senha', icon: 'key', onClick: function () { p.onResetPassword(u); } } : null,
                  p.onToggleActive ? '-' : null,
                  p.onToggleActive ? { label: off ? 'Reativar conta' : 'Desativar conta', icon: 'power', danger: !off, onClick: function () { p.onToggleActive(u); } } : null].filter(Boolean) }))));
          })))) : h('div', { className: 'ck-ml__empty' },
            h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: dq[0] ? 'search' : 'users', size: 20 })),
            h('strong', null, dq[0] ? 'Nenhum usuário com "' + dq[0] + '"' : 'Nenhum usuário neste filtro'),
            h('p', null, dq[0] ? 'Confira a grafia ou busque por e-mail, CPF ou telefone.' : 'Troque o tipo ou a situação.'),
            dq[0] || type[0] !== 'todos' || stt[0] !== 'ativos' ? h(Button, { size: 'sm', onClick: function () { qs[1](''); type[1]('todos'); stt[1]('ativos'); } }, 'Limpar busca e filtros') : null),
        data.total ? h('div', { className: 'ck-ml__foot ck-us__foot' },
          h('span', null, num(from) + '–' + num(to) + ' de ' + num(data.total)),
          h('nav', { className: 'ck-us__pages', 'aria-label': 'Páginas' },
            h(IconButton, { icon: 'chevron-left', label: 'Página anterior', variant: 'secondary', size: 'sm', disabled: cur <= 1, onClick: function () { go(cur - 1); } }),
            pageList().map(function (n, i) { return n === '…' ? h('span', { key: 'e' + i, className: 'ck-us__gap' }, '…') : h('button', { key: n, type: 'button', className: cx('ck-us__pg', n === cur && 'is-on'), 'aria-current': n === cur ? 'page' : undefined, 'aria-label': 'Página ' + n, onClick: function () { go(n); } }, n); }),
            h(IconButton, { icon: 'chevron-right', label: 'Próxima página', variant: 'secondary', size: 'sm', disabled: cur >= pages, onClick: function () { go(cur + 1); } })),
          h('label', { className: 'ck-us__size' }, 'Por página',
            h('select', { value: size[0], onChange: function (e) { size[1](+e.target.value); page[1](1); } }, [20, 50, 100].map(function (n) { return h('option', { key: n, value: n }, n); })))) : null));
  }

  function UsuarioForm(p) {
    var u = p.user || {}, a = u.address || {};
    var init = useState(function () { return { name: u.name ? titleCase(u.name) : '', email: u.email || '', phone: maskPhone(u.phone), cpf: maskCpf(u.cpf), cep: maskCep(a.cep), street: a.street || '', number: a.number || '', complement: a.complement || '', district: a.district || '', city: a.city ? titleCase(a.city) : '', uf: a.uf || '', active: u.active !== false }; })[0];
    var fm = useState(init), f = fm[0], tried = useState(false), cp = useState({ state: 'idle' }), pw = useState(p.creating ? 'set' : 'closed'), pass = useState(''), sh = useState(false), sent = useState(false), mail = useState({ state: 'idle' });
    function set(k, v) { var n = Object.assign({}, f); n[k] = v; fm[1](n); }
    var err = {};
    if (!f.name.trim() || f.name.trim().split(/\s+/).length < 2) err.name = f.name.trim() ? 'Escreva nome e sobrenome.' : 'Informe o nome completo.';
    if (!f.email.trim()) err.email = 'Informe o e-mail. É o login.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.email.trim())) err.email = 'Confira o e-mail. Falta algo como @ ou .com.';
    else if (mail[0].state === 'taken') err.email = 'Este e-mail já é de ' + mail[0].who + '.';
    if (f.phone && digits(f.phone).length < 10) err.phone = 'Inclua o DDD.';
    if (p.requireCpf && !f.cpf) err.cpf = 'Informe o CPF.';
    if (f.cpf && !pfCpfOk(f.cpf)) err.cpf = digits(f.cpf).length !== 11 ? 'O CPF tem 11 números.' : 'CPF inválido. Confira os números.';
    var hasAddr = [f.cep, f.street, f.number, f.district, f.city, f.uf].some(function (x) { return String(x).trim(); });
    var addrChanged = ['cep', 'street', 'number', 'complement', 'district', 'city', 'uf'].some(function (k) { return f[k] !== init[k]; });
    if (hasAddr && addrChanged) { if (digits(f.cep).length !== 8) err.cep = 'O CEP tem 8 números.'; if (!f.street.trim()) err.street = 'Informe a rua.'; if (!f.number.trim()) err.number = 'Informe o número ou s/n.'; if (!f.city.trim()) err.city = 'Informe a cidade.'; if (!f.uf) err.uf = 'Escolha o estado.'; }
    if (pw[0] === 'set' && !p.associating && pass[0].length < 6) err.password = 'A senha precisa de pelo menos 6 caracteres.';
    var show = Object.assign({}, p.serverErrors || {}, tried[0] ? err : {});
    var dirty = JSON.stringify(f) !== JSON.stringify(init) || (pw[0] === 'set' && pass[0]);
    var isStudent = (u.types || []).indexOf('aluno') >= 0, isTeam = (u.types || []).some(function (t) { return t !== 'aluno'; });
    function onCep(v) {
      var m = maskCep(v); set('cep', m);
      if (digits(m).length === 8 && p.onCepLookup) {
        cp[1]({ state: 'loading' });
        Promise.resolve(p.onCepLookup(digits(m))).then(function (r) {
          if (!r) { cp[1]({ state: 'none' }); return; }
          cp[1]({ state: 'ok' });
          fm[1](function (cur) { if (cur.cep !== m) return cur; return Object.assign({}, cur, { cep: m, street: r.street || cur.street, district: r.district || cur.district, city: r.city ? titleCase(r.city) : cur.city, uf: r.uf || cur.uf }); });
        }, function () { cp[1]({ state: 'none' }); });
      } else cp[1]({ state: 'idle' });
    }
    function checkMail() {
      var e = f.email.trim().toLowerCase();
      if (!p.onCheckEmail || err.email && mail[0].state !== 'taken' || e === (u.email || '').toLowerCase()) { mail[1]({ state: 'idle' }); return; }
      mail[1]({ state: 'loading' });
      Promise.resolve(p.onCheckEmail(e)).then(function (r) { mail[1](r && r.name ? { state: 'taken', who: titleCase(r.name) } : { state: 'ok' }); }, function () { mail[1]({ state: 'idle' }); });
    }
    function submit(e) {
      e.preventDefault(); if (p.saving) return; if (p.associating) { p.onAssociate && p.onAssociate(); return; } tried[1](true);
      if (Object.keys(err).length) { setTimeout(function () { var el = document.querySelector('.ck-al-form [aria-invalid="true"]'); if (el) el.focus(); }, 0); return; }
      p.onSave && p.onSave({ id: u.id, name: f.name.trim(), email: f.email.trim().toLowerCase(), phone: digits(f.phone) || null, cpf: digits(f.cpf) || null,
        address: hasAddr ? { cep: digits(f.cep), street: f.street.trim(), number: f.number.trim(), complement: f.complement.trim() || null, district: f.district.trim() || null, city: f.city.trim(), uf: f.uf } : null,
        password: pw[0] === 'set' ? pass[0] : null, active: f.active });
    }
    var steps = [['Dados da conta', !err.name && !err.email && !err.phone && !err.cpf && !!f.name && !!f.email], ['Endereço', hasAddr && !err.cep && !err.street && !err.number && !err.city && !err.uf, 'Opcional'], ['Senha e acesso', !err.password]];
    return h('form', { className: 'ck-al-form', onSubmit: submit, noValidate: true },
      h('div', null,
        h(BackLink, { onClick: p.onBack }, 'Usuários'),
        h(PageHeader, { title: p.creating ? 'Novo usuário' : 'Editar usuário', description: 'Login, contato e acesso da conta. Vale para o app e para o painel.' })),
      h('div', { className: 'ck-al-form__grid' },
        h('fieldset', { disabled: !!p.saving || !!p.associating, className: 'ck-card ck-al-form__main ck-us__fields' }, p.notice || null,
          isStudent || isTeam ? h('div', { className: 'ck-us__links' },
            h('span', null, 'Esta conta é de ', (u.types || []).map(function (t, i) { var x = US_TYPES[t] || ['neutral', t]; return h(React.Fragment, { key: t }, i ? ' e ' : '', h('b', null, x[1].toLowerCase())); }), '.'),
            isStudent && p.onOpenStudent ? h('button', { type: 'button', className: 'ck-linkbtn', onClick: p.onOpenStudent }, 'Ficha do aluno') : null,
            isTeam && p.onPermissions ? h('button', { type: 'button', className: 'ck-linkbtn', onClick: p.onPermissions }, 'Papel e permissões') : null) : null,
          h(AlSection, { id: 'us-s1', icon: 'user', title: 'Dados da conta', done: steps[0][1] },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { className: 'is-wide', label: 'Nome completo', value: f.name, error: show.name, onChange: function (e) { set('name', e.target.value); }, onBlur: function () { if (f.name && f.name === f.name.toUpperCase()) set('name', titleCase(f.name)); } }),
              h(TextField, { className: 'is-wide', label: 'E-mail', type: 'email', icon: 'mail', value: f.email, error: show.email || (mail[0].state === 'taken' ? err.email : null),
                hint: mail[0].state === 'loading' ? 'Conferindo se o e-mail já está em uso…' : mail[0].state === 'ok' ? 'E-mail livre. O novo login vale a partir do próximo acesso.' : 'É o login no app e no painel.',
                onChange: function (e) { set('email', e.target.value); if (mail[0].state !== 'idle') mail[1]({ state: 'idle' }); }, onBlur: checkMail }),
              h(TextField, { label: 'Celular', labelAside: 'opcional', type: 'tel', icon: 'phone', inputMode: 'numeric', value: f.phone, placeholder: '(00) 00000-0000', error: show.phone, onChange: function (e) { set('phone', maskPhone(e.target.value)); } }),
              h(TextField, { label: 'CPF', labelAside: p.requireCpf ? null : 'opcional', inputMode: 'numeric', value: f.cpf, placeholder: '000.000.000-00', error: show.cpf || (f.cpf && digits(f.cpf).length === 11 && !pfCpfOk(f.cpf) ? 'CPF inválido. Confira os números.' : null), hint: f.cpf && pfCpfOk(f.cpf) ? 'CPF válido.' : null, onChange: function (e) { set('cpf', maskCpf(e.target.value)); } }))),
          h(AlSection, { id: 'us-s2', icon: 'map-pin', title: 'Endereço', description: 'Opcional. Ao mexer no endereço, rua, número, cidade e estado passam a ser obrigatórios.', done: steps[1][1] },
            h('div', { className: 'ck-al-grid ck-al-grid--addr' },
              h(TextField, { label: 'CEP', value: f.cep, inputMode: 'numeric', placeholder: '00000-000', error: show.cep || (cp[0].state === 'none' ? 'CEP não encontrado. Preencha o endereço à mão.' : null),
                hint: cp[0].state === 'ok' ? 'Endereço preenchido pelo CEP.' : 'Preenche rua, bairro e cidade.',
                suffix: cp[0].state === 'loading' ? h(Icon, { name: 'loader', size: 16, className: 'ck-spin ck-al-cepspin' }) : cp[0].state === 'ok' ? h(Icon, { name: 'check-circle', size: 16, className: 'ck-al-cepok' }) : null,
                onChange: function (e) { onCep(e.target.value); } }),
              h(TextField, { className: 'ck-al-span2', label: 'Rua', value: f.street, error: show.street, placeholder: 'Rua, avenida, travessa', onChange: function (e) { set('street', e.target.value); } }),
              h(TextField, { label: 'Número', value: f.number, error: show.number, placeholder: 'Nº ou s/n', onChange: function (e) { set('number', e.target.value); } }),
              h(TextField, { className: 'ck-al-span2', label: 'Complemento', value: f.complement, placeholder: 'Apartamento, bloco, referência', onChange: function (e) { set('complement', e.target.value); } }),
              h(TextField, { label: 'Bairro', value: f.district, onChange: function (e) { set('district', e.target.value); } }),
              h(TextField, { label: 'Cidade', value: f.city, error: show.city, onChange: function (e) { set('city', e.target.value); } }),
              h(SelectField, { label: 'Estado', value: f.uf, placeholder: 'Escolha', error: show.uf, options: UFS.map(function (x) { return { value: x[0], label: x[0] + ', ' + x[1] }; }), onChange: function (e) { set('uf', e.target.value); } }))),
          h(AlSection, { id: 'us-s3', icon: 'key', title: 'Senha e acesso', done: steps[2][1] && pw[0] !== 'closed' },
            h('dl', { className: 'ck-us__facts' },
              h('div', null, h('dt', null, 'Último acesso'), h('dd', null, usAgo(u.lastAccess, p.today) + (u.lastAccess ? ', ' + fdate(u.lastAccess.slice(0, 10)) : ''))),
              h('div', null, h('dt', null, 'Conta criada'), h('dd', null, u.createdAt ? fdate(u.createdAt) : '—'))),
            pw[0] === 'closed' ? h('div', { className: 'ck-al-pwrow' },
              h('div', null, h('strong', null, 'Senha'), h('span', null, sent[0] ? 'Link enviado para ' + f.email + '. Vale por 24 horas.' : (p.onResetPassword ? 'Envie um link para a pessoa criar a própria senha.' : 'A senha atual será mantida. Use Definir agora para alterá-la.'))),
              h('div', { className: 'ck-us__pwacts' },
                p.onResetPassword ? h(Button, { icon: sent[0] ? 'check' : 'mail', disabled: sent[0], onClick: function () { Promise.resolve(p.onResetPassword(u)).then(function () { sent[1](true); }); } }, sent[0] ? 'Link enviado' : 'Enviar link de nova senha') : null,
                h(Button, { variant: 'ghost', onClick: function () { pw[1]('set'); } }, 'Definir agora')))
              : h('div', { className: 'ck-al-grid' },
                h(TextField, { label: 'Nova senha', type: sh[0] ? 'text' : 'password', disabled: !!p.associating, icon: 'lock', autoComplete: 'new-password', value: pass[0], placeholder: 'Mínimo de 6 caracteres', error: show.password,
                  hint: !show.password ? (pass[0] ? (pass[0].length >= 6 ? 'Senha válida. Avise a pessoa para trocar no primeiro acesso.' : 'Faltam ' + (6 - pass[0].length) + ' caracteres.') : 'A senha atual deixa de valer ao salvar.') : null,
                  suffix: h(IconButton, { icon: sh[0] ? 'eye-off' : 'eye', label: sh[0] ? 'Ocultar senha' : 'Mostrar senha', size: 'sm', onClick: function () { sh[1](!sh[0]); } }), onChange: function (e) { pass[1](e.target.value); } }),
                h('div', { className: 'ck-al-pwcancel' }, !p.creating ? h(Button, { variant: 'ghost', onClick: function () { pw[1]('closed'); pass[1](''); } }, 'Manter senha atual') : null)),
            h('div', { className: 'ck-al-active' }, h(Switch, { label: 'Vínculo ativo', description: 'Situação nesta academia. Para ativar ou desativar, use as ações da lista de usuários.', checked: f.active, disabled: true })))),
        h('aside', { className: 'ck-al-sum', 'aria-label': 'Resumo' },
          h('div', { className: 'ck-al-sum__who' }, h(Avatar, { name: f.name || '?', size: 'lg', decorative: true }), h('div', { style: { minWidth: 0 } }, h('strong', null, f.name.trim() || 'Nome'), h('span', null, f.email.trim() || 'E-mail'))),
          h('ol', { className: 'ck-al-sum__steps' }, steps.map(function (s2, i) {
            var ok = s2[1] && (i !== 2 || pw[0] !== 'closed' || true), bad = !s2[1] && tried[0] && (!s2[2] || (i === 1 && hasAddr));
            return h('li', { key: i, className: s2[1] ? 'is-done' : (bad ? 'is-error' : undefined) },
              h('span', { className: 'ck-al-sum__mark', 'aria-hidden': true }, s2[1] ? h(Icon, { name: 'check', size: 13, strokeWidth: 3 }) : (bad ? h(Icon, { name: 'alert-triangle', size: 12, strokeWidth: 2.25 }) : i + 1)),
              h('span', null, s2[0]), !s2[1] && s2[2] && !bad ? h('em', null, s2[2]) : null);
          })),
          h('div', { className: 'ck-al-sum__status' }, h('span', { className: cx('ck-al-sum__dot', f.active && 'is-on'), 'aria-hidden': true }), f.active ? 'Vínculo ativo' : 'Vínculo inativo'),
          tried[0] && Object.keys(err).length ? h('p', { className: 'ck-al-sum__err', role: 'alert' }, Object.keys(err).length === 1 ? 'Falta corrigir 1 campo.' : 'Falta corrigir ' + Object.keys(err).length + ' campos.') : null,
          h(Button, { type: 'submit', variant: 'primary', size: 'lg', block: true, icon: 'save', loading: p.saving, disabled: (!dirty && !p.associating) || mail[0].state === 'loading' }, p.saving ? 'Salvando…' : p.associating ? 'Associar aluno' : p.creating ? 'Cadastrar usuário' : 'Salvar alterações'),
          !dirty ? h('span', { className: 'ck-al-sum__note' }, 'Nenhuma alteração ainda.') : null,
          p.onCancel ? h('button', { type: 'button', className: 'ck-al-sum__cancel', onClick: p.onCancel }, 'Cancelar') : null)));
  }

export { UsuariosLista, UsuarioForm };
