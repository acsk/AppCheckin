/* AppCheckin — Professores: ProfessoresLista e ProfessorForm.
   Portado do bundle do design system (ProfessoresLista.html / ProfessorForm.html). Estilos: src/theme/ck-professores.css,
   reaproveitando ck-pc__ (pacotes), ck-al-* (pessoas) e ck-ml__ (modalidades).
   Extensões locais: seção Endereço com busca por CEP (`onCepLookup`, como no AlunoForm); vínculos e modalidades vêm da API
   (`bonds` [{value,label}], `modalities` [{value,label}], o professor guarda os values); `requireCpf` no cadastro novo;
   `serverErrors` (campo → mensagem) mostra o que a API recusou; a linha "Usa o app" só aparece quando `appAccess` é conhecido. */
import React from 'react';
import {
  ActionMenu, Avatar, Badge, Button, Callout, Icon, PageHeader, SelectField, Switch, TextAreaField, TextField,
} from './index';
import {
  AlSection, BackLink, ageOn, brToIso, dayDiff, digits, fdate, firstName, maskCep, maskCpf, maskDate, maskPhone, titleCase, UFS,
} from './pessoas';

  var h = React.createElement;
  var useState = React.useState;

  function cx() { return Array.prototype.filter.call(arguments, Boolean).join(' '); }
  function pmNorm(v) { return String(v || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function isoToday() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }

  /* ---------- ProfessoresLista ---------- */
  function ProfessoresLista(p) {
    var rows = p.teachers || [];
    var fs = useState('ativos'), qs = useState('');
    var q = pmNorm(qs[0].trim()), qd = digits(qs[0]);
    var counts = { ativos: 0, inativos: 0, todos: rows.length };
    rows.forEach(function (t) { counts[t.active === false ? 'inativos' : 'ativos']++; });
    var list = rows.filter(function (t) {
      if (fs[0] === 'ativos' && t.active === false) return false;
      if (fs[0] === 'inativos' && t.active !== false) return false;
      if (!q) return true;
      return pmNorm(t.name + ' ' + (t.email || '') + ' ' + (t.modalities || []).join(' ')).indexOf(q) >= 0 || (qd.length >= 3 && (digits(t.cpf) + ' ' + digits(t.phone)).indexOf(qd) >= 0);
    });
    return h('div', { className: 'ck-pc' },
      h(PageHeader, { title: 'Professores', description: 'Quem dá aulas. Aparecem na escolha de professor das turmas e fazem check-in dos alunos pelo app.',
        actions: p.onNew ? h(Button, { variant: 'primary', icon: 'user-plus', onClick: p.onNew }, 'Novo professor') : null }),
      h('section', { className: 'ck-pc__panel' },
        h('div', { className: 'ck-pf__bar' },
          h('div', { className: 'ck-control ck-ml__search' }, h(Icon, { name: 'search', size: 16, className: 'ck-control__icon' }),
            h('input', { type: 'search', className: 'ck-control__input', value: qs[0], placeholder: 'Nome, e-mail, CPF, telefone ou modalidade', 'aria-label': 'Buscar professor', onChange: function (e) { qs[1](e.target.value); } })),
          h('div', { className: 'ck-ml__filters', role: 'radiogroup', 'aria-label': 'Situação' }, [['ativos', 'Ativos', 'success'], ['inativos', 'Inativos', 'outline'], ['todos', 'Todos']].map(function (x) {
            return h('button', { key: x[0], type: 'button', role: 'radio', 'aria-checked': fs[0] === x[0], className: 'ck-ml__chip', onClick: function () { fs[1](x[0]); } },
              x[2] ? h('span', { className: 'ck-ml__chipdot ck-ml__chipdot--' + x[2], 'aria-hidden': true }) : null, x[1], h('span', { className: 'ck-ml__count' }, counts[x[0]]));
          }))),
        p.loading ? h('div', { className: 'ck-ml__empty' }, h(Icon, { name: 'loader', size: 20, className: 'ck-spin' }), h('p', null, 'Carregando professores…'))
        : list.length ? h('div', { className: 'ck-pc__wrap' }, h('table', { className: 'ck-pc__table is-fixed ck-pf__table' },
          h('colgroup', null, [30, 18, 22, 16, 14].map(function (w, i) { return h('col', { key: i, style: { width: w + '%' } }); })),
          h('thead', null, h('tr', null, h('th', { scope: 'col' }, 'Professor'), h('th', { scope: 'col' }, 'Contato'), h('th', { scope: 'col' }, 'Aulas'), h('th', { scope: 'col' }, 'Situação'), h('th', { scope: 'col' }, h('span', { className: 'ck-sr' }, 'Ações')))),
          h('tbody', null, list.map(function (t) {
            var off = t.active === false, n = t.weeklyClasses || 0, total = t.classesCount || 0;
            var cd = t.crefDue ? dayDiff(p.today || isoToday(), t.crefDue) : null;
            return h('tr', { key: t.id, className: off ? 'is-off' : undefined },
              h('th', { scope: 'row' }, h('div', { className: 'ck-pc__name' },
                h(Avatar, { name: titleCase(t.name), src: t.photo, size: 'md' }),
                h('div', { style: { minWidth: 0 } },
                  p.onEdit ? h('button', { type: 'button', className: 'ck-pf__link', onClick: function () { p.onEdit(t); } }, titleCase(t.name)) : h('strong', null, titleCase(t.name)),
                  h('span', { title: t.email }, t.email || 'Sem e-mail')))),
              h('td', null, h('span', { className: 'ck-pc__main' }, t.phone ? maskPhone(t.phone) : '—'), t.cpf ? h('span', { className: 'ck-pc__sub' }, 'CPF ' + maskCpf(t.cpf)) : null),
              h('td', null, n ? h(React.Fragment, null, h('span', { className: 'ck-pc__main' }, n + (n === 1 ? ' aula por semana' : ' aulas por semana')), h('span', { className: 'ck-pc__sub' }, (t.modalities || []).join(', ') + (t.nextClass ? (t.modalities && t.modalities.length ? '. ' : '') + 'Próxima: ' + t.nextClass : '')))
                : h(React.Fragment, null, h('span', { className: 'ck-pc__sub' }, off ? 'Sem aulas' : total ? 'Nenhuma aula nos próximos 7 dias' : 'Nenhuma aula ainda'), (t.modalities || []).length ? h('span', { className: 'ck-pc__sub' }, t.modalities.join(', ')) : null)),
              h('td', null, off ? h(Badge, { tone: 'outline' }, 'Inativo') : h(Badge, { tone: 'success', dot: true }, 'Ativo'),
                cd != null && cd <= 30 ? h('span', { className: cx('ck-pc__sub ck-pf__cref', cd < 0 && 'is-bad') }, h(Icon, { name: 'alert-triangle', size: 12 }), cd < 0 ? 'CREF vencido' : 'CREF vence em ' + cd + (cd === 1 ? ' dia' : ' dias')) : null,
                t.appAccess != null ? h('span', { className: 'ck-pc__sub' }, t.appAccess ? 'Usa o app' : 'Ainda não entrou no app') : null),
              h('td', null, h('div', { className: 'ck-pc__acts' },
                p.onEdit ? h(Button, { size: 'sm', icon: 'pencil', onClick: function () { p.onEdit(t); } }, 'Editar') : null,
                h(ActionMenu, { label: 'Mais ações para ' + titleCase(t.name), items: [
                  p.onSeeClasses && total ? { label: 'Ver aulas', icon: 'calendar', onClick: function () { p.onSeeClasses(t); } } : null,
                  p.onToggleActive ? { label: off ? 'Reativar' : 'Desativar', icon: 'power', onClick: function () { p.onToggleActive(t); } } : null,
                  p.onDelete ? '-' : null,
                  p.onDelete ? { label: total ? 'Excluir (tem ' + total + (total === 1 ? ' aula)' : ' aulas)') : 'Excluir professor', icon: 'trash', danger: !total, disabled: !!total, onClick: function () { p.onDelete(t); } } : null].filter(Boolean) }))));
          })))) : h('div', { className: 'ck-ml__empty' },
            h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: q ? 'search' : 'users', size: 20 })),
            h('strong', null, q ? 'Nenhum professor com "' + qs[0] + '"' : fs[0] === 'inativos' ? 'Nenhum professor inativo' : 'Nenhum professor cadastrado'),
            h('p', null, q ? 'Busque pelo nome, e-mail, CPF, telefone ou modalidade.' : 'Cadastre quem dá aulas para escolher nas turmas.'),
            !q && fs[0] !== 'inativos' && p.onNew ? h(Button, { size: 'sm', variant: 'primary', icon: 'user-plus', onClick: p.onNew }, 'Novo professor') : null)),
      h('p', { className: 'ck-pf__foot' }, list.length + ' de ' + rows.length + (rows.length === 1 ? ' professor' : ' professores')));
  }

  /* ---------- ProfessorForm (novo e editar) ---------- */
  function pfCpfOk(v) {
    var d = digits(v); if (d.length !== 11 || /^(\d)\1+$/.test(d)) return false;
    for (var t = 9; t < 11; t++) { var s = 0; for (var i = 0; i < t; i++) s += +d[i] * (t + 1 - i); if (((s * 10) % 11) % 10 !== +d[t]) return false; }
    return true;
  }
  function pfCref(v) { var x = String(v || '').toUpperCase().replace(/[^0-9A-Z]/g, ''); var m = /^(\d{0,6})([GP]?)([A-Z]{0,2})/.exec(x) || ['', '', '', '']; return m[1] + (m[2] || m[3] ? '-' + m[2] : '') + (m[3] ? '/' + m[3] : ''); }
  var PF_UFS = UFS.map(function (u) { return u[0]; });
  /* Campo da API → campo do formulário (para `serverErrors`) */
  var PF_API = { nome: 'name', cpf: 'cpf', data_nascimento: 'birth', email: 'email', telefone: 'phone', whatsapp: 'whatsapp', cref: 'cref', cref_validade: 'crefDue',
    vinculo_id: 'bond', admitido_em: 'hired', modalidade_ids: 'modalities', emergencia_nome: 'emName', emergencia_telefone: 'emPhone', emergencia_parentesco: 'emRel',
    cep: 'cep', logradouro: 'street', numero: 'number', complemento: 'complement', bairro: 'district', cidade: 'city', estado: 'uf', observacoes: 'notes' };

  function ProfessorForm(p) {
    var t = p.teacher || {}, edit = t.id != null, mods = p.modalities || [], bonds = p.bonds || [], today = p.today || isoToday();
    var init = useState(function () {
      var e = t.emergency || {}, a = t.address || {};
      return { name: t.name ? titleCase(t.name) : '', cpf: maskCpf(t.cpf), birth: t.birthDate ? fdate(t.birthDate) : '', email: t.email || '', phone: maskPhone(t.phone),
        sameWa: !t.whatsapp || digits(t.whatsapp) === digits(t.phone), whatsapp: maskPhone(t.whatsapp), cref: t.cref || '', crefDue: t.crefDue ? fdate(t.crefDue) : '',
        bond: t.bond != null ? String(t.bond) : '', hired: t.hiredAt ? fdate(t.hiredAt) : '', modalities: (t.modalities || []).map(String),
        emName: e.name || '', emPhone: maskPhone(e.phone), emRel: e.relation || '',
        cep: maskCep(a.cep), street: a.street || '', number: a.number || '', complement: a.complement || '', district: a.district || '', city: a.city ? titleCase(a.city) : '', uf: a.uf || '',
        notes: t.notes || '', active: t.active !== false };
    })[0];
    var fm = useState(init), f = fm[0], tried = useState(false), mail = useState({ state: 'idle' }), cp = useState({ state: 'idle' });
    function set(k, v) { fm[1](function (cur) { var n = Object.assign({}, cur); n[k] = v; return n; }); }
    var birthIso = brToIso(f.birth), age = birthIso ? ageOn(birthIso, today) : null, crefIso = brToIso(f.crefDue), hiredIso = brToIso(f.hired);
    var crefDays = crefIso ? dayDiff(today, crefIso) : null;
    var err = {};
    if (!f.name.trim() || f.name.trim().split(/\s+/).length < 2) err.name = f.name.trim() ? 'Escreva nome e sobrenome.' : 'Informe o nome completo.';
    if (p.requireCpf && !f.cpf) err.cpf = 'Informe o CPF.';
    else if (f.cpf && !pfCpfOk(f.cpf)) err.cpf = digits(f.cpf).length !== 11 ? 'O CPF tem 11 números.' : 'CPF inválido. Confira os números.';
    if (f.birth && !birthIso) err.birth = 'Use o formato DD/MM/AAAA.';
    else if (birthIso && (age == null || age < 16)) err.birth = birthIso > today ? 'A data está no futuro.' : 'Precisa ter pelo menos 16 anos.';
    if (!f.email.trim()) err.email = 'Informe o e-mail. É o login do professor no app.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.email.trim())) err.email = 'Confira o e-mail. Falta algo como @ ou .com.';
    else if (mail[0].state === 'taken') err.email = 'Este e-mail já é de ' + mail[0].who + '. Use outro ou vincule o cadastro.';
    if (f.phone && digits(f.phone).length < 10) err.phone = 'Inclua o DDD.';
    if (!f.sameWa && f.whatsapp && digits(f.whatsapp).length < 11) err.whatsapp = 'WhatsApp é celular: DDD e 9 dígitos.';
    if (f.sameWa && f.phone && digits(f.phone).length === 10) err.phone = err.phone || 'Número fixo não tem WhatsApp. Desmarque a opção abaixo ou use um celular.';
    if (f.cref && !/^\d{6}-[GP]\/[A-Z]{2}$/.test(f.cref)) err.cref = 'Formato: 000000-G/AL.';
    else if (f.cref && PF_UFS.indexOf(f.cref.slice(-2)) < 0) err.cref = 'A UF do CREF não existe.';
    if (f.crefDue && !crefIso) err.crefDue = 'Use o formato DD/MM/AAAA.';
    if (f.hired && !hiredIso) err.hired = 'Use o formato DD/MM/AAAA.';
    else if (hiredIso && hiredIso > today) err.hired = 'A data está no futuro.';
    if (f.emPhone && digits(f.emPhone).length < 10) err.emPhone = 'Inclua o DDD.';
    if (f.emPhone && !f.emName.trim()) err.emName = 'Diga de quem é o telefone.';
    if (f.emName.trim() && !f.emPhone) err.emPhone = 'Informe o telefone de ' + firstName(f.emName) + '.';
    if (f.cep && digits(f.cep).length !== 8) err.cep = 'O CEP tem 8 números.';
    // O que a API recusou vale até o campo mudar
    var srv = p.serverErrors || {};
    Object.keys(srv).forEach(function (k) { var fk = PF_API[k]; if (fk && !err[fk] && JSON.stringify(f[fk]) === JSON.stringify(p.serverValues && p.serverValues[fk])) err[fk] = srv[k]; });
    var show = tried[0] ? err : {};
    var dirty = JSON.stringify(f) !== JSON.stringify(init);
    var n = t.weeklyClasses || 0;
    var steps = [
      ['Identificação', !err.name && !err.cpf && !err.birth && !!f.name],
      ['Contato e acesso', !err.email && !err.phone && !err.whatsapp && !!f.email],
      ['Registro profissional', !!f.cref && !err.cref && !err.crefDue && (crefDays == null || crefDays >= 0), 'Recomendado'],
      ['Vínculo e modalidades', !!f.bond && f.modalities.length > 0 && !err.hired, 'Opcional'],
      ['Contato de emergência', !!f.emName && !!f.emPhone && !err.emPhone && !err.emName, 'Opcional'],
      ['Endereço', !!(f.cep && f.street && f.number) && !err.cep, 'Opcional']];
    var STEP_FIELDS = { 2: ['cref', 'crefDue'], 3: ['hired', 'bond', 'modalities'], 4: ['emName', 'emPhone'], 5: ['cep', 'uf'] };
    function checkMail() {
      var e = f.email.trim().toLowerCase();
      if (!p.onCheckEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(e) || e === (t.email || '').toLowerCase()) { mail[1]({ state: 'idle' }); return; }
      mail[1]({ state: 'loading' });
      Promise.resolve(p.onCheckEmail(e)).then(function (r) { mail[1](r && r.name ? { state: 'taken', who: titleCase(r.name) } : { state: 'ok' }); }, function () { mail[1]({ state: 'idle' }); });
    }
    function onCep(v) {
      var m = maskCep(v); set('cep', m);
      if (digits(m).length === 8 && p.onCepLookup) {
        cp[1]({ state: 'loading' });
        Promise.resolve(p.onCepLookup(digits(m))).then(function (r) {
          if (!r) { cp[1]({ state: 'none' }); return; }
          cp[1]({ state: 'ok' });
          fm[1](function (cur) { return Object.assign({}, cur, { cep: m, street: r.street || cur.street, district: r.district || cur.district, city: r.city ? titleCase(r.city) : cur.city, uf: r.uf || cur.uf }); });
        }, function () { cp[1]({ state: 'none' }); });
      } else if (cp[0].state !== 'idle') cp[1]({ state: 'idle' });
    }
    function submit(e) {
      e.preventDefault(); tried[1](true);
      if (Object.keys(err).length) { setTimeout(function () { var el = document.querySelector('.ck-al-form [aria-invalid="true"]'); if (el) el.focus(); }, 0); return; }
      p.onSave && p.onSave({ id: t.id, name: f.name.trim(), cpf: digits(f.cpf) || null, birthDate: birthIso, email: f.email.trim().toLowerCase(), phone: digits(f.phone) || null, whatsapp: digits(f.sameWa ? f.phone : f.whatsapp) || null,
        cref: f.cref || null, crefDue: crefIso, bond: f.bond || null, hiredAt: hiredIso, modalities: f.modalities,
        emergency: f.emName.trim() || f.emPhone ? { name: f.emName.trim(), phone: digits(f.emPhone), relation: f.emRel.trim() || null } : null,
        address: { cep: digits(f.cep) || null, street: f.street.trim() || null, number: f.number.trim() || null, complement: f.complement.trim() || null, district: f.district.trim() || null, city: f.city.trim() || null, uf: f.uf || null },
        notes: f.notes.trim() || null, active: f.active }, f);
    }
    var label = edit ? 'Salvar alterações' : f.name.trim() ? 'Cadastrar ' + firstName(f.name) : 'Cadastrar professor';
    var crefMsg = crefDays == null ? null : crefDays < 0 ? ['danger', 'CREF vencido há ' + (-crefDays) + (crefDays === -1 ? ' dia' : ' dias') + '. Peça a renovação.'] : crefDays <= 30 ? ['warning', 'CREF vence em ' + crefDays + (crefDays === 1 ? ' dia' : ' dias') + '.'] : ['ok', 'Válido até ' + f.crefDue + '.'];
    return h('form', { className: 'ck-al-form', onSubmit: submit, noValidate: true },
      h('div', null,
        h(BackLink, { onClick: p.onBack }, 'Professores'),
        h(PageHeader, { title: edit ? 'Editar professor' : 'Novo professor', description: edit ? 'Alterações valem na hora, inclusive nas turmas e no app.' : 'Só nome, CPF e e-mail são obrigatórios. O resto pode ser completado depois.' })),
      h('div', { className: 'ck-al-form__grid' },
        h('div', { className: 'ck-card ck-al-form__main' },
          h(AlSection, { id: 'pf-s1', icon: 'user', title: 'Identificação', done: steps[0][1] },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { className: 'is-wide', label: 'Nome completo', value: f.name, placeholder: 'Como aparece para os alunos', error: show.name, onChange: function (e) { set('name', e.target.value); }, onBlur: function () { if (f.name && f.name === f.name.toUpperCase()) set('name', titleCase(f.name)); } }),
              h(TextField, { label: 'CPF', labelAside: p.requireCpf ? null : 'opcional', value: f.cpf, inputMode: 'numeric', placeholder: '000.000.000-00', readOnly: !!p.cpfLocked, error: show.cpf || (f.cpf && digits(f.cpf).length === 11 && !pfCpfOk(f.cpf) ? 'CPF inválido. Confira os números.' : null), hint: f.cpf && pfCpfOk(f.cpf) ? 'CPF válido.' : 'Necessário para pagar como prestador.', onChange: function (e) { set('cpf', maskCpf(e.target.value)); } }),
              h(TextField, { label: 'Data de nascimento', labelAside: 'opcional', value: f.birth, inputMode: 'numeric', placeholder: 'DD/MM/AAAA', icon: 'calendar', error: show.birth, hint: !show.birth && age != null && age >= 16 ? age + ' anos' : null, onChange: function (e) { set('birth', maskDate(e.target.value)); } }))),
          h(AlSection, { id: 'pf-s2', icon: 'mail', title: 'Contato e acesso', done: steps[1][1] },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { className: 'is-wide', label: 'E-mail', type: 'email', value: f.email, icon: 'mail', placeholder: 'nome@exemplo.com', error: show.email || (mail[0].state === 'taken' ? err.email : null),
                hint: mail[0].state === 'loading' ? 'Conferindo se o e-mail já está em uso…' : mail[0].state === 'ok' ? 'E-mail livre.' : 'Login no app, onde faz o check-in das turmas.',
                suffix: mail[0].state === 'loading' ? h(Icon, { name: 'loader', size: 16, className: 'ck-spin' }) : mail[0].state === 'ok' ? h(Icon, { name: 'check-circle', size: 16, className: 'ck-al-cepok' }) : null,
                onChange: function (e) { set('email', e.target.value); if (mail[0].state !== 'idle') mail[1]({ state: 'idle' }); }, onBlur: checkMail }),
              h(TextField, { label: 'Celular', labelAside: 'opcional', type: 'tel', value: f.phone, icon: 'phone', inputMode: 'numeric', placeholder: '(00) 00000-0000', error: show.phone, onChange: function (e) { set('phone', maskPhone(e.target.value)); } }),
              f.sameWa ? h('div', { className: 'ck-pf__wa' },
                h('label', { className: 'ck-check' }, h('input', { type: 'checkbox', checked: f.sameWa, onChange: function (e) { set('sameWa', e.target.checked); } }), h('span', { className: 'ck-check__box', 'aria-hidden': true }, h(Icon, { name: 'check', size: 13, strokeWidth: 3 })), h('span', null, 'Este número também é o WhatsApp')))
                : h(TextField, { label: 'WhatsApp', type: 'tel', value: f.whatsapp, icon: 'message-circle', inputMode: 'numeric', placeholder: '(00) 00000-0000', error: show.whatsapp, hint: !show.whatsapp ? 'Avisos de turma e substituições.' : null, onChange: function (e) { set('whatsapp', maskPhone(e.target.value)); } })),
            !f.sameWa ? h('button', { type: 'button', className: 'ck-linkbtn ck-pf__walink', onClick: function () { set('sameWa', true); } }, 'Usar o celular como WhatsApp') : null,
            edit ? (t.appAccess != null ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: t.appAccess ? 'check-circle' : 'info', size: 15 }), t.appAccess ? 'Já entrou no app' + (t.lastAccess ? ', último acesso em ' + fdate(t.lastAccess) : '') + '.' : 'Ainda não entrou no app.', !t.appAccess && p.onInvite ? h('button', { type: 'button', className: 'ck-linkbtn', onClick: p.onInvite }, 'Reenviar convite') : null) : null)
              : h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), p.inviteHint || 'Ao cadastrar, mostramos uma senha temporária para você passar ao professor.')),
          h(AlSection, { id: 'pf-s3', icon: 'shield-check', title: 'Registro profissional', description: 'CREF, exigido para quem dá aula de educação física.', done: steps[2][1] },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { label: 'CREF', value: f.cref, placeholder: '000000-G/AL', mono: true, error: show.cref, hint: !show.cref ? 'G para graduado, P para provisionado.' : null, onChange: function (e) { set('cref', pfCref(e.target.value)); } }),
              h(TextField, { label: 'Validade da carteira', value: f.crefDue, inputMode: 'numeric', placeholder: 'DD/MM/AAAA', icon: 'calendar', error: show.crefDue, onChange: function (e) { set('crefDue', maskDate(e.target.value)); } })),
            crefMsg && !show.crefDue ? (crefMsg[0] === 'ok' ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'check-circle', size: 15 }), crefMsg[1]) : h(Callout, { tone: crefMsg[0], title: crefMsg[1] }, 'O professor continua aparecendo nas turmas. A data fica destacada na lista de professores.')) : null),
          h(AlSection, { id: 'pf-s4', icon: 'layers', title: 'Vínculo e modalidades', done: steps[3][1] },
            h('div', { className: 'ck-al-grid' },
              h(SelectField, { label: 'Tipo de vínculo', value: f.bond, placeholder: 'Escolha', options: bonds, error: show.bond, onChange: function (e) { set('bond', e.target.value); } }),
              h(TextField, { label: 'Na academia desde', value: f.hired, inputMode: 'numeric', placeholder: 'DD/MM/AAAA', icon: 'calendar', error: show.hired, hint: !show.hired && hiredIso ? (function () { var d = dayDiff(hiredIso, today), y = Math.floor(d / 365), mth = Math.floor((d % 365) / 30); return y ? y + (y === 1 ? ' ano' : ' anos') + (mth ? ' e ' + mth + (mth === 1 ? ' mês' : ' meses') : '') : mth ? mth + (mth === 1 ? ' mês' : ' meses') : 'Começou este mês'; })() : null, onChange: function (e) { set('hired', maskDate(e.target.value)); } })),
            h('div', { className: 'ck-field', style: { marginTop: 'var(--space-4)' } },
              h('span', { className: 'ck-field__label' }, 'Modalidades que dá'),
              mods.length ? h('div', { className: 'ck-ml__filters', role: 'group', 'aria-label': 'Modalidades' }, mods.map(function (m) {
                var v = String(m.value), on = f.modalities.indexOf(v) >= 0;
                return h('button', { key: v, type: 'button', 'aria-pressed': on, className: 'ck-ml__chip', onClick: function () { set('modalities', on ? f.modalities.filter(function (x) { return x !== v; }) : f.modalities.concat([v])); } }, on ? h(Icon, { name: 'check', size: 14, strokeWidth: 2.5 }) : null, m.label);
              })) : h('p', { className: 'ck-al-hint' }, 'Nenhuma modalidade cadastrada.'),
              show.modalities ? h('span', { className: 'ck-field__error', role: 'alert' }, show.modalities) : h('span', { className: 'ck-field__hint' }, 'Ele aparece primeiro na escolha de professor das aulas dessas modalidades.')),
            edit && n ? h('div', { className: 'ck-pf__classes' }, h(Icon, { name: 'calendar', size: 16 }), h('span', null, h('b', null, n + (n === 1 ? ' aula por semana' : ' aulas por semana')), t.nextClass ? ', próxima ' + t.nextClass : ''), p.onSeeClasses ? h('button', { type: 'button', className: 'ck-linkbtn', onClick: p.onSeeClasses }, 'Ver aulas') : null) : null),
          h(AlSection, { id: 'pf-s5', icon: 'heart-pulse', title: 'Contato de emergência', description: 'Quem avisar se algo acontecer durante uma aula.', done: steps[4][1] },
            h('div', { className: 'ck-al-grid ck-pf__em' },
              h(TextField, { label: 'Nome', value: f.emName, placeholder: 'Ex.: Maria Silva', error: show.emName, onChange: function (e) { set('emName', e.target.value); } }),
              h(TextField, { label: 'Parentesco', value: f.emRel, placeholder: 'Ex.: Mãe, cônjuge', error: show.emRel, onChange: function (e) { set('emRel', e.target.value); } }),
              h(TextField, { label: 'Telefone', type: 'tel', value: f.emPhone, icon: 'phone', inputMode: 'numeric', placeholder: '(00) 00000-0000', error: show.emPhone, onChange: function (e) { set('emPhone', maskPhone(e.target.value)); } }))),
          h(AlSection, { id: 'pf-s6', icon: 'map-pin', title: 'Endereço', description: 'Opcional. Usado em contratos e pagamentos de prestador.', done: steps[5][1] },
            h('div', { className: 'ck-al-grid ck-al-grid--addr' },
              h(TextField, { label: 'CEP', value: f.cep, inputMode: 'numeric', placeholder: '00000-000', error: show.cep || (cp[0].state === 'none' ? 'CEP não encontrado. Preencha o endereço à mão.' : null),
                hint: cp[0].state === 'ok' ? 'Endereço preenchido pelo CEP.' : 'Preenche rua, bairro e cidade.',
                suffix: cp[0].state === 'loading' ? h(Icon, { name: 'loader', size: 16, className: 'ck-spin ck-al-cepspin' }) : cp[0].state === 'ok' ? h(Icon, { name: 'check-circle', size: 16, className: 'ck-al-cepok' }) : null,
                onChange: function (e) { onCep(e.target.value); } }),
              h(TextField, { className: 'ck-al-span2', label: 'Rua', value: f.street, placeholder: 'Rua, avenida, travessa', error: show.street, onChange: function (e) { set('street', e.target.value); } }),
              h(TextField, { label: 'Número', value: f.number, placeholder: 'Nº ou s/n', error: show.number, onChange: function (e) { set('number', e.target.value); } }),
              h(TextField, { className: 'ck-al-span2', label: 'Complemento', value: f.complement, placeholder: 'Apartamento, bloco, referência', error: show.complement, onChange: function (e) { set('complement', e.target.value); } }),
              h(TextField, { label: 'Bairro', value: f.district, error: show.district, onChange: function (e) { set('district', e.target.value); } }),
              h(TextField, { label: 'Cidade', value: f.city, error: show.city, onChange: function (e) { set('city', e.target.value); } }),
              h(SelectField, { label: 'Estado', value: f.uf, placeholder: 'Escolha', error: show.uf, options: UFS.map(function (u) { return { value: u[0], label: u[0] + ', ' + u[1] }; }), onChange: function (e) { set('uf', e.target.value); } }))),
          h(AlSection, { id: 'pf-s7', icon: 'power', title: 'Situação e observações' },
            h('div', { className: 'ck-al-active' }, h(Switch, { label: 'Professor ativo', description: f.active ? 'Aparece nas turmas e pode fazer check-in pelo app.' : 'Some da escolha de professor e não entra no app. O histórico fica guardado.', checked: f.active, onChange: function (v) { set('active', v); } })),
            edit && !f.active && init.active && n ? h(Callout, { tone: 'warning', title: n + (n === 1 ? ' aula continua' : ' aulas continuam') + ' com ' + firstName(f.name) }, 'Troque o professor dessas aulas em Aulas. Até lá, elas aparecem sem professor para os alunos.') : null,
            h('div', { style: { marginTop: 'var(--space-4)' } }, h(TextAreaField, { label: 'Observações internas', labelAside: (f.notes.length) + '/500', rows: 3, maxLength: 500, value: f.notes, placeholder: 'Ex.: disponível só de manhã, cobre férias da Juliana', error: show.notes, hint: 'Só a equipe vê.', onChange: function (e) { set('notes', e.target.value); } })))),
        h('aside', { className: 'ck-al-sum', 'aria-label': 'Resumo do cadastro' },
          h('div', { className: 'ck-al-sum__who' },
            f.name.trim() ? h(Avatar, { name: f.name, size: 'lg', decorative: true }) : h('span', { className: 'ck-al-sum__ph', 'aria-hidden': true }, h(Icon, { name: 'user', size: 24 })),
            h('div', { style: { minWidth: 0 } }, h('strong', null, f.name.trim() || 'Nome do professor'),
              h('span', null, [mods.filter(function (m) { return f.modalities.indexOf(String(m.value)) >= 0; }).map(function (m) { return m.label; }).join(', '), f.cref].filter(Boolean).join(', ') || f.email.trim() || 'E-mail ainda não informado'))),
          h('ol', { className: 'ck-al-sum__steps' }, steps.map(function (s2, i) {
            var bad = !s2[1] && tried[0] && (!s2[2] || (i === 2 && crefDays != null && crefDays < 0) || Object.keys(show).some(function (k) { return (STEP_FIELDS[i] || []).indexOf(k) >= 0; }));
            return h('li', { key: i, className: s2[1] ? 'is-done' : (bad ? 'is-error' : undefined) },
              h('span', { className: 'ck-al-sum__mark', 'aria-hidden': true }, s2[1] ? h(Icon, { name: 'check', size: 13, strokeWidth: 3 }) : (bad ? h(Icon, { name: 'alert-triangle', size: 12, strokeWidth: 2.25 }) : i + 1)),
              h('span', null, s2[0]), !s2[1] && s2[2] && !bad ? h('em', null, s2[2]) : null);
          })),
          h('div', { className: 'ck-al-sum__status' }, h('span', { className: cx('ck-al-sum__dot', f.active && 'is-on'), 'aria-hidden': true }), f.active ? (edit ? 'Ativo' : 'Entra como ativo') : (edit ? 'Inativo' : 'Entra como inativo')),
          tried[0] && Object.keys(err).length ? h('p', { className: 'ck-al-sum__err', role: 'alert' }, Object.keys(err).length === 1 ? 'Falta corrigir 1 campo.' : 'Falta corrigir ' + Object.keys(err).length + ' campos.') : null,
          h(Button, { type: 'submit', variant: 'primary', size: 'lg', block: true, icon: edit ? 'save' : 'user-plus', loading: p.saving, disabled: (edit && !dirty) || mail[0].state === 'loading' }, p.saving ? 'Salvando…' : label),
          edit && !dirty ? h('span', { className: 'ck-al-sum__note' }, 'Nenhuma alteração ainda.') : null,
          p.onCancel ? h('button', { type: 'button', className: 'ck-al-sum__cancel', onClick: p.onCancel }, 'Cancelar') : null)));
  }

export { ProfessoresLista, ProfessorForm, pfCpfOk };
