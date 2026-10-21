/* Agenda e formulário portados dos layouts HTML fornecidos. */
import React from 'react';
import { ActionMenu, Badge, Button, Icon, IconButton, Modal, PageHeader, SelectField, Switch, TextField } from './index';
const h = React.createElement;
const { useState } = React;
const cx = (...values) => values.filter(Boolean).join(' ');
function useControlled(value, initial, onChange) {
  const [state, setState] = useState(initial);
  return [value === undefined ? state : value, next => { setState(next); onChange?.(next); }];
}
function pd(iso) { const [y,m,d] = iso.split('-').map(Number); return new Date(y,m-1,d); }
function addDays(iso, count) { const date = pd(iso); date.setDate(date.getDate()+count); return isoOf(date); }
function titleCase(value) { return String(value || ''); }
function modColor(value) { return /^#[0-9a-f]{3,8}$/i.test(value) ? value : ({azul:'#2563eb',verde:'#16a34a',laranja:'#ea580c',roxo:'#7c3aed',vermelho:'#dc2626'}[value] || '#2563eb'); }
  /* ---------- Aulas ---------- */
  var DOW = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  var DOWL = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
  var MON = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  var MONL = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  function isoOf(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function dowOf(iso) { return pd(iso).getDay(); }
  function dayLabel(iso, withDow) { var d = pd(iso); return (withDow ? DOWL[d.getDay()] + ', ' : '') + d.getDate() + ' de ' + MON[d.getMonth()] + '.'; }
  function weekStart(iso) { return addDays(iso, -dowOf(iso)); }
  function toMin(t) { var a = String(t || '').split(':'); return a.length < 2 ? null : (+a[0]) * 60 + (+a[1]); }
  function fromMin(m) { m = ((m % 1440) + 1440) % 1440; return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'); }
  function durTxt(mins) { if (mins == null || isNaN(mins)) return ''; var hh = Math.floor(mins / 60), mm = mins % 60; return hh && mm ? hh + 'h' + String(mm).padStart(2, '0') : hh ? hh + (hh === 1 ? ' hora' : ' horas') : mm + ' min'; }
  function minTxt(m) { if (m == null || m === '') return null; m = +m; if (m >= 1440 && m % 1440 === 0) return m / 1440 + (m === 1440 ? ' dia' : ' dias'); if (m >= 60) return durTxt(m); return m + ' min'; }
  function ModIcon(p) { var m = p.modality || {}; return h('span', { className: 'ck-au__mod', style: { background: modColor(m.color || 'azul') }, 'aria-hidden': true }, h(Icon, { name: m.icon || 'activity', size: 16, strokeWidth: 2 })); }

  function AulasAgenda(p) {
    var today = p.today || isoOf(new Date());
    var dt = useControlled(p.date, today, p.onDateChange), date = dt[0];
    var qs = useState(''), mf = useState('todas');
    var ws = weekStart(date), week = [0, 1, 2, 3, 4, 5, 6].map(function (i) { return addDays(ws, i); });
    var counts = p.counts || {};
    var aulas = (p.aulas || []).slice().sort(function (a, b) { return toMin(a.start) - toMin(b.start); });
    var mods = []; aulas.forEach(function (a) { if (mods.indexOf(a.modality.name) < 0) mods.push(a.modality.name); });
    var q = qs[0].trim().toLowerCase();
    var list = aulas.filter(function (a) { return (mf[0] === 'todas' || a.modality.name === mf[0]) && (!q || (a.modality.name + ' ' + a.teacher).toLowerCase().indexOf(q) >= 0); });
    var nowMin = date === today && p.now ? toMin(p.now) : null;
    var totalB = aulas.reduce(function (s, a) { return s + (a.active === false ? 0 : (a.booked || 0)); }, 0), totalC = aulas.reduce(function (s, a) { return s + (a.active === false ? 0 : (a.capacity || 0)); }, 0);
    var actives = aulas.filter(function (a) { return a.active !== false; }).length;
    return h('div', { className: 'ck-au' },
      h(PageHeader, { title: 'Aulas', description: 'A grade de turmas de cada dia, com professor e vagas. Copie a grade usando os modelos de dia ou semana em "Replicar".',
        actions: h('fieldset', { className: 'ck-au__fieldset ck-au__headeractions', disabled: p.loading || !!p.error },
          p.onOpenWod ? h(Button, { icon: 'clipboard', onClick: p.onOpenWod }, 'WOD do dia') : null,
          p.onReplicate ? h(Button, { icon: 'copy', onClick: function () { p.onReplicate(date); } }, 'Replicar') : null,
          h(Button, { variant: 'primary', icon: 'plus', onClick: function () { p.onNew && p.onNew(date); } }, 'Nova aula')) }),
      h('section', { className: 'ck-au__week', 'aria-label': 'Escolher dia' },
        h('div', { className: 'ck-au__weekhead' },
          h('strong', null, MONL[pd(date).getMonth()].replace(/^./, function (c) { return c.toUpperCase(); }) + ' de ' + pd(date).getFullYear()),
          h('div', { className: 'ck-au__weeknav' },
            h(IconButton, { icon: 'chevron-left', label: 'Semana anterior', variant: 'secondary', size: 'sm', onClick: function () { dt[1](addDays(date, -7)); } }),
            h(Button, { size: 'sm', variant: date === today ? 'ghost' : 'secondary', disabled: date === today, onClick: function () { dt[1](today); } }, 'Hoje'),
            h(IconButton, { icon: 'chevron-right', label: 'Próxima semana', variant: 'secondary', size: 'sm', onClick: function () { dt[1](addDays(date, 7)); } }),
            h('label', { className: 'ck-au__pick' }, h(Icon, { name: 'calendar', size: 16 }), h('span', { className: 'ck-sr' }, 'Ir para data'),
              h('input', { type: 'date', 'aria-label': 'Escolher data', value: date, onChange: function (e) { if (e.target.value) dt[1](e.target.value); } })))),
        h('div', { className: 'ck-au__days', role: 'tablist', 'aria-label': 'Dias da semana' }, week.map(function (d) {
          var on = d === date, n = counts[d];
          return h('button', { key: d, type: 'button', role: 'tab', 'aria-selected': on, className: cx('ck-au__day', on && 'is-on', d === today && 'is-today', d < today && 'is-past'), onClick: function () { dt[1](d); } },
            h('span', { className: 'ck-au__dow' }, DOW[dowOf(d)]),
            h('span', { className: 'ck-au__dnum' }, pd(d).getDate()),
            h('span', { className: 'ck-au__dcount' }, n == null ? '' : n ? n + (n === 1 ? ' aula' : ' aulas') : 'sem aulas'));
        }))),
      h('section', { className: 'ck-au__panel', 'aria-label': 'Aulas de ' + dayLabel(date, true) },
        h('header', { className: 'ck-au__dayhead' },
          h('div', null,
            h('h2', null, dayLabel(date, true).replace(/^./, function (c) { return c.toUpperCase(); }), date === today ? h(Badge, { tone: 'info' }, 'Hoje') : null),
            h('p', null, p.loading ? 'Carregando aulas…' : p.error ? 'Atualize a agenda para consultar as aulas.' : aulas.length ? actives + (actives === 1 ? ' aula ativa' : ' aulas ativas') + ', ' + totalB + ' de ' + totalC + ' vagas ocupadas' + (aulas.length > actives ? ', ' + (aulas.length - actives) + ' desativada' + (aulas.length - actives > 1 ? 's' : '') : '') : 'Nenhuma aula neste dia.')),
          aulas.length ? h('div', { className: 'ck-au__tools' },
            h('div', { className: 'ck-control ck-ml__search ck-au__search' }, h(Icon, { name: 'search', size: 16, className: 'ck-control__icon' }),
              h('input', { type: 'search', className: 'ck-control__input', value: qs[0], placeholder: 'Modalidade ou professor', 'aria-label': 'Buscar aulas', onChange: function (e) { qs[1](e.target.value); } })),
            p.onDeleteDay ? h(ActionMenu, { label: 'Mais ações do dia', variant: 'secondary', size: 'md', items: [
              p.onReplicate ? { label: 'Replicar este dia', icon: 'copy', onClick: function () { p.onReplicate(date); } } : null, '-',
              { label: 'Excluir as ' + aulas.length + ' aulas do dia', icon: 'trash', danger: true, onClick: function () { p.onDeleteDay(date); } }].filter(Boolean) }) : null) : null),
        mods.length > 1 ? h('div', { className: 'ck-ml__filters ck-au__mods', role: 'radiogroup', 'aria-label': 'Modalidade' }, ['todas'].concat(mods).map(function (m) {
          return h('button', { key: m, type: 'button', role: 'radio', 'aria-checked': mf[0] === m, className: 'ck-ml__chip', onClick: function () { mf[1](m); } }, m === 'todas' ? 'Todas' : m,
            h('span', { className: 'ck-ml__count' }, m === 'todas' ? aulas.length : aulas.filter(function (a) { return a.modality.name === m; }).length));
        })) : null,
        p.loading || p.error ? null : list.length ? h('ol', { className: 'ck-au__list' }, list.map(function (a, i) {
          var off = a.active === false, pct = a.capacity ? Math.min(100, Math.round((a.booked || 0) / a.capacity * 100)) : 0, full = a.capacity && a.booked >= a.capacity;
          var past = nowMin != null && toMin(a.end) <= nowMin, live = nowMin != null && toMin(a.start) <= nowMin && nowMin < toMin(a.end);
          var nextLine = nowMin != null && !past && !live && (i === 0 || toMin(list[i - 1].end) <= nowMin) ? h('li', { key: 'now', className: 'ck-au__now', 'aria-hidden': true }, h('span', null, 'Agora, ' + p.now)) : null;
          return [nextLine, h('li', { key: a.id, className: cx('ck-au__row', off && 'is-off', past && 'is-past', live && 'is-live') },
            h('button', { type: 'button', className: 'ck-au__open', onClick: function () { p.onOpen && p.onOpen(a); }, 'aria-label': a.modality.name + ' das ' + a.start + ' às ' + a.end + ' com ' + titleCase(a.teacher) }),
            h('div', { className: 'ck-au__time' }, h('strong', null, a.start), h('span', null, 'até ' + a.end + ', ' + durTxt(toMin(a.end) - toMin(a.start)).replace(' hora', 'h').replace(' horas', 'h'))),
            h('div', { className: 'ck-au__what' },
              h(ModIcon, { modality: a.modality }),
              h('div', { style: { minWidth: 0 } },
                h('strong', null, a.modality.name, live ? h('span', { className: 'ck-au__live' }, 'Acontecendo') : null),
                h('span', null, h(Icon, { name: 'user', size: 13 }), titleCase(a.teacher)))),
            h('div', { className: 'ck-au__cap', title: (a.booked || 0) + ' de ' + a.capacity + ' vagas' },
              h('span', { className: 'ck-au__capn' }, h('b', null, a.booked || 0), ' de ' + a.capacity, h('em', null, full ? 'lotada' : (a.capacity - (a.booked || 0)) + ' livres')),
              h('span', { className: cx('ck-au__bar', full && 'is-full'), role: 'progressbar', 'aria-valuenow': a.booked || 0, 'aria-valuemin': 0, 'aria-valuemax': a.capacity, 'aria-label': 'Vagas ocupadas' }, h('i', { style: { width: pct + '%' } }))),
            h('div', { className: 'ck-au__st' },
              off ? h(Badge, { tone: 'outline' }, 'Desativada') : past ? h(Badge, { tone: 'neutral' }, 'Encerrada') : a.checkinLocked ? h(Badge, { tone: 'warning', icon: 'lock' }, 'Check-in fechado') : h(Badge, { tone: 'success', dot: true }, 'Ativa'),
              a.checkinLocked && past && !off ? h(Badge, { tone: 'warning', icon: 'lock' }, 'Check-in fechado') : null,
              off && a.deactivatedNote ? h('span', null, a.deactivatedNote) : null),
            h('div', { className: 'ck-au__acts' },
              h(ActionMenu, { label: 'Ações da aula das ' + a.start, items: [
                p.onParticipants ? { label: 'Ver alunos e check-ins', icon: 'users', onClick: function () { p.onParticipants(a); } } : null,
                p.onEdit ? { label: 'Editar aula', icon: 'pencil', onClick: function () { p.onEdit(a); } } : null,
                p.onToggleLock && !off ? { label: a.checkinLocked ? 'Reabrir check-in' : 'Fechar check-in', icon: a.checkinLocked ? 'unlock' : 'lock', onClick: function () { p.onToggleLock(a); } } : null,
                '-',
                off ? (p.onReactivate ? { label: 'Reativar aula', icon: 'power', onClick: function () { p.onReactivate(a); } } : null) : (p.onDeactivate ? { label: 'Desativar…', icon: 'ban', onClick: function () { p.onDeactivate(a); } } : null),
                p.onDelete ? { label: 'Excluir aula', icon: 'trash', danger: true, onClick: function () { p.onDelete(a); } } : null].filter(Boolean) })))];
        })) : h('div', { className: 'ck-ml__empty' },
          h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: q ? 'search' : 'calendar', size: 20 })),
          h('strong', null, q ? 'Nenhuma aula com "' + qs[0] + '"' : 'Nenhuma aula em ' + dayLabel(date, true)),
          h('p', null, q ? 'Busque pela modalidade ou pelo professor.' : p.copyHint || 'Crie uma aula ou navegue até um dia com aulas para replicar.'),
          q ? h(Button, { size: 'sm', onClick: function () { qs[1](''); } }, 'Limpar busca') : h('div', { style: { display: 'flex', gap: '8px' } },
            h(Button, { size: 'sm', variant: 'primary', icon: 'plus', onClick: function () { p.onNew && p.onNew(date); } }, 'Nova aula'),
            p.onCopyFrom ? h(Button, { size: 'sm', icon: 'copy', onClick: function () { p.onCopyFrom(date); } }, 'Copiar de outro dia') : null))));
  }

  function AulaModal(p) {
    var a = p.aula || {};
    var edit = a.id != null;
    var init = useState(function () { return { modality: a.modality ? a.modality.id : '', teacher: a.teacherId || '', date: a.date || p.date || '', start: a.start || '', end: a.end || '', cap: a.capacity != null ? String(a.capacity) : '', openBefore: a.openBefore != null ? String(a.openBefore) : '480', lateAfter: a.lateAfter != null ? String(a.lateAfter) : '10', closeBefore: a.closeBefore != null ? String(a.closeBefore) : '', cancelBefore: a.cancelBefore != null ? String(a.cancelBefore) : '', active: a.active !== false }; })[0];
    var fm = useState(init), f = fm[0], tried = useState(false), rules = useState(false);
    function set(k, v) { var n = Object.assign({}, f); n[k] = v; fm[1](n); }
    var mods = p.modalities || [], teachers = p.teachers || [];
    var dur = f.start && f.end ? toMin(f.end) - toMin(f.start) : null;
    var err = {};
    if (!f.modality) err.modality = 'Escolha a modalidade.';
    if (!f.teacher) err.teacher = 'Escolha o professor.';
    if (!f.start) err.start = 'Informe o início.';
    if (!f.end) err.end = 'Informe o fim.'; else if (dur != null && dur <= 0) err.end = 'O fim precisa ser depois do início.';
    if (!Number.isInteger(Number(f.cap)) || !(Number(f.cap) > 0)) err.cap = 'Quantos alunos cabem na aula.';
    var show = tried[0] ? err : {};
    var dirty = JSON.stringify(f) !== JSON.stringify(init);
    var st = toMin(f.start);
    var ruleTxt = st == null ? 'Defina o horário para ver quando o check-in abre e fecha.' :
      'Check-in abre ' + (f.openBefore ? (+f.openBefore > st ? 'no dia anterior, às ' + fromMin(st - +f.openBefore) : 'às ' + fromMin(st - +f.openBefore)) : 'no início') +
      ' e fecha ' + (f.closeBefore !== '' ? 'às ' + fromMin(st - +f.closeBefore) : 'às ' + fromMin(st + (+f.lateAfter || 0)) + (+f.lateAfter ? ', ' + f.lateAfter + ' min depois do início' : ', no início')) +
      '. Cancelamento: ' + (f.cancelBefore !== '' ? 'até ' + fromMin(st - +f.cancelBefore) : 'até o início') + '.';
    function setDur(m) { if (st != null) set('end', fromMin(st + m)); }
    function save() {
      tried[1](true);
      if (Object.keys(err).length || p.saving) return;
      var n = function (v) { return v === '' ? null : +v; };
      p.onSave && p.onSave({ id: a.id, modalityId: f.modality, teacherId: f.teacher, date: f.date || null, start: f.start, end: f.end, capacity: +f.cap, openBefore: n(f.openBefore), lateAfter: n(f.lateAfter), closeBefore: n(f.closeBefore), cancelBefore: n(f.cancelBefore), active: f.active });
    }
    var modSel = mods.filter(function (m) { return String(m.id) === String(f.modality); })[0];
    return h(Modal, { open: p.open, inline: p.inline, onClose: p.onCancel, dismissable: !p.saving, sheet: true, size: 'md',
      leading: modSel ? h(ModIcon, { modality: modSel }) : h('span', { className: 'ck-au__modalicon', 'aria-hidden': true }, h(Icon, { name: 'calendar', size: 20 })),
      title: edit ? 'Editar aula' : 'Nova aula',
      description: f.date ? (edit ? '' : 'Em ') + dayLabel(f.date, true) : null,
      footer: h('div', { className: 'ck-adm__foot' },
        h(Button, { variant: 'ghost', disabled: p.saving, onClick: p.onCancel }, 'Cancelar'),
        h(Button, { variant: 'primary', icon: edit ? 'save' : 'plus', loading: p.saving, disabled: edit && !dirty, onClick: save }, edit ? 'Salvar aula' : 'Criar aula' + (f.start ? ' das ' + f.start : ''))) },
      p.error ? h('p', { role: 'alert', className: 'ck-field__error' }, p.error) : null,
      h('fieldset', { className: 'ck-adm ck-au__fieldset ck-au__modalbody', disabled: p.saving },
        h('div', { className: 'ck-al-grid' },
          h(SelectField, { label: 'Modalidade', value: f.modality, placeholder: 'Escolha', error: show.modality, options: mods.map(function (m) { return { value: m.id, label: m.name }; }), onChange: function (e) { set('modality', e.target.value); } }),
          h(SelectField, { label: 'Professor', value: f.teacher, placeholder: 'Escolha', error: show.teacher, options: teachers.map(function (t) { return { value: t.id, label: titleCase(t.name) }; }), onChange: function (e) { set('teacher', e.target.value); } })),
        h('div', { className: 'ck-au__times' },
          h(TextField, { type: 'time', label: 'Começa', value: f.start, error: show.start, onChange: function (e) { var v = e.target.value; var keep = dur && dur > 0 ? fromMin(toMin(v) + dur) : f.end; fm[1](Object.assign({}, f, { start: v, end: f.end ? keep : (v ? fromMin(toMin(v) + 60) : '') })); } }),
          h(TextField, { type: 'time', label: 'Termina', value: f.end, error: show.end, hint: !show.end && dur > 0 ? durTxt(dur) + ' de aula' : null, onChange: function (e) { set('end', e.target.value); } }),
          h('div', { className: 'ck-au__durs', role: 'group', 'aria-label': 'Duração rápida' }, [45, 60, 90].map(function (m) {
            return h('button', { key: m, type: 'button', className: 'ck-ml__chip', 'aria-pressed': dur === m, disabled: st == null, onClick: function () { setDur(m); } }, durTxt(m).replace(' hora', 'h'));
          }))),
        h('div', { className: 'ck-field' },
          h('span', { className: 'ck-field__label', id: 'au-cap' }, 'Vagas'),
          h('div', { className: 'ck-au__step', role: 'group', 'aria-labelledby': 'au-cap' },
            h(IconButton, { icon: 'minus', label: 'Menos uma vaga', variant: 'secondary', className: 'ck-au__minus', disabled: !(+f.cap > 1), onClick: function () { set('cap', String(Math.max(1, (+f.cap || 1) - 1))); } }),
            h('input', { className: cx('ck-au__stepin', show.cap && 'is-invalid'), inputMode: 'numeric', value: f.cap, placeholder: '0', 'aria-label': 'Quantidade de vagas', onChange: function (e) { set('cap', e.target.value.replace(/\D/g, '')); } }),
            h(IconButton, { icon: 'plus', label: 'Mais uma vaga', variant: 'secondary', onClick: function () { set('cap', String((+f.cap || 0) + 1)); } }),
            h('span', { className: 'ck-au__stephint' }, 'alunos por aula')),
          show.cap ? h('span', { className: 'ck-field__error' }, h(Icon, { name: 'alert-triangle', size: 13 }), show.cap) : null),
        h('div', { className: 'ck-au__rules' },
          h('button', { type: 'button', className: 'ck-au__ruleshead', 'aria-expanded': rules[0], onClick: function () { rules[1](!rules[0]); } },
            h(Icon, { name: 'clock', size: 16 }), h('div', null, h('strong', null, 'Regras de check-in'), h('span', null, ruleTxt)), h(Icon, { name: 'chevron-down', size: 16, className: cx('ck-ep__chev', rules[0] && 'is-open') })),
          rules[0] ? h('div', { className: 'ck-au__rulesbody' },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { label: 'Abre quanto antes do início', inputMode: 'numeric', value: f.openBefore, suffix: h('span', { className: 'ck-sp-unit' }, 'min'), hint: minTxt(f.openBefore) ? minTxt(f.openBefore) + ' antes' : 'Vazio: abre no início', onChange: function (e) { set('openBefore', e.target.value.replace(/\D/g, '')); } }),
              h(TextField, { label: 'Aceita atraso de até', inputMode: 'numeric', value: f.lateAfter, suffix: h('span', { className: 'ck-sp-unit' }, 'min'), hint: 'Depois do início', onChange: function (e) { set('lateAfter', e.target.value.replace(/\D/g, '')); } }),
              h(TextField, { label: 'Fecha quanto antes do início', inputMode: 'numeric', value: f.closeBefore, placeholder: 'Segue o atraso aceito', suffix: h('span', { className: 'ck-sp-unit' }, 'min'), hint: 'Vazio: início mais a tolerância de atraso', onChange: function (e) { set('closeBefore', e.target.value.replace(/\D/g, '')); } }),
              h(TextField, { label: 'Cancelamento até', inputMode: 'numeric', value: f.cancelBefore, placeholder: 'Até o início', suffix: h('span', { className: 'ck-sp-unit' }, 'min antes'), hint: 'Vazio: cancelamento até o início', onChange: function (e) { set('cancelBefore', e.target.value.replace(/\D/g, '')); } }))) : null),
        h('div', { className: 'ck-al-active' }, h(Switch, { label: 'Aula ativa', description: f.active ? 'Aparece no app e aceita check-in.' : 'Fica escondida do app.', checked: f.active, onChange: function (v) { set('active', v); } }))));
  }


export { AulasAgenda, AulaModal };
