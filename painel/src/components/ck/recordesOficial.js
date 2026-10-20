/* Componentes portados dos quatro layouts oficiais de recordes. */
import React from 'react';
import { ActionMenu, Avatar, Badge, Button, Callout, Icon, IconButton, PageHeader, SegmentedControl, SelectField, Switch, Tabs, TextAreaField, TextField } from './index';

import { RC_CAT, RC_TYPES, fmtMetric, betterOf, isBetter, rankRecords, metricLabel } from '../../utils/recordesOficiais';
const h = React.createElement;
const { useState } = React;
const cx = (...values) => values.filter(Boolean).join(' ');
function useControlled(value, initial, onChange) { const [state,setState] = useState(initial); return [value === undefined ? state : value, next => { setState(next); onChange?.(next); }]; }
const fdate = value => value ? String(value).slice(0,10).split('-').reverse().join('/') : '—';
const titleCase = value => String(value || '');
const BackLink = ({children,onClick}) => h('button', {type:'button',className:'ck-mt-back',onClick},h(Icon,{name:'arrow-left',size:16}),children);
  function AlSection(p) {
    return h('section', { className: 'ck-al-sec', 'aria-labelledby': p.id },
      h('header', { className: 'ck-al-sec__head' },
        h('span', { className: cx('ck-al-sec__icon', p.done && 'is-done'), 'aria-hidden': true }, h(Icon, { name: p.done ? 'check' : p.icon, size: 17, strokeWidth: p.done ? 2.5 : 1.75 })),
        h('div', null, h('h2', { className: 'ck-al-sec__title', id: p.id }, p.title), p.description ? h('p', { className: 'ck-al-sec__desc' }, p.description) : null),
        p.aside || null),
      h('div', { className: 'ck-al-sec__body' }, p.children));
  }
function CopyValue({value}) { const [copied,setCopied] = useState(false); return h('span',{className:'ck-rc-copy'},h('code',null,value),h(IconButton,{icon:copied?'check':'copy',size:'sm',label:'Copiar código',onClick:async()=>{ try { await navigator.clipboard.writeText(value); setCopied(true); } catch { setCopied(false); } }})); }
  function RecordesPainel(p) {
    var defs = (p.definitions || []).filter(function (d) { return d.active !== false; }), recs = p.records || [];
    var sel = useControlled(p.selected, defs[0] && defs[0].id, p.onSelect);
    var dq = useState(''), sq = useState(''), tab = useState('ranking'), modal = useState(null);
    var def = defs.filter(function (d) { return d.id === sel[0]; })[0] || defs[0];
    var groups = [];
    defs.filter(function (d) { return !dq[0] || d.name.toLowerCase().indexOf(dq[0].toLowerCase()) >= 0; }).forEach(function (d) {
      var g = d.modality || 'Geral', x = groups.filter(function (y) { return y.name === g; })[0];
      if (!x) { x = { name: g, items: [] }; groups.push(x); } x.items.push(d);
    });
    var rank = def ? rankRecords(recs, def) : [], leader = rank[0];
    var dRecs = def ? recs.filter(function (r) { return r.defId === def.id; }).sort(function (a, b) { return a.date < b.date ? 1 : -1; }) : [];
    var q = sq[0].trim().toLowerCase();
    function match(r) { return !q || (r.student ? r.student.name : 'academia').toLowerCase().indexOf(q) >= 0; }
    var pbIds = {};
    if (def) { var cur = {}; dRecs.slice().reverse().forEach(function (r) { var k = r.student ? r.student.id : 'a'; if (r.value == null) return; if (cur[k] == null || isBetter(r.value, cur[k], def.metric)) { if (cur[k] != null) pbIds[r.id] = true; cur[k] = r.value; } }); }
    function openNew() { if (p.onNewRecord) p.onNewRecord(def); }
    return h('div', { className: 'ck-rc' },
      h(PageHeader, { title: 'Recordes', description: 'Melhores marcas dos alunos em cada prova, movimento e teste.',
        actions: h(React.Fragment, null,
          p.onManage ? h(Button, { icon: 'settings', onClick: p.onManage }, 'Definições') : null,
          h(Button, { variant: 'primary', icon: 'plus', onClick: openNew, disabled: !defs.length }, 'Registrar recorde')) }),
      !defs.length ? h('div', { className: 'ck-card ck-ml__empty' },
        h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: 'trophy', size: 20 })),
        h('strong', null, 'Nenhuma definição ativa'),
        h('p', null, 'Crie as provas e movimentos que a academia acompanha, como 50m crawl ou deadlift.'),
        p.onManage ? h(Button, { size: 'sm', icon: 'plus', onClick: p.onManage }, 'Criar definição') : null) :
      h('div', { className: 'ck-rc__layout' },
        h('nav', { className: 'ck-rc__rail', 'aria-label': 'Definições' },
          h('div', { className: 'ck-control ck-rc__railsearch' },
            h(Icon, { name: 'search', size: 16, className: 'ck-control__icon' }),
            h('input', { type: 'search', className: 'ck-control__input', value: dq[0], placeholder: 'Filtrar provas', 'aria-label': 'Filtrar definições', onChange: function (e) { dq[1](e.target.value); } })),
          groups.map(function (g) {
            return h('div', { key: g.name, className: 'ck-rc__rg' },
              h('span', { className: 'ck-rc__rgl' }, g.name),
              h('ul', null, g.items.map(function (d) {
                var top = rankRecords(recs, d)[0], on = def && d.id === def.id, cat = RC_CAT[d.category] || RC_CAT.movimento;
                return h('li', { key: d.id }, h('button', { type: 'button', className: cx('ck-rc__ri', on && 'is-on'), 'aria-current': on ? 'true' : undefined, onClick: function () { sel[1](d.id); sq[1](''); } },
                  h(Icon, { name: cat[1], size: 16 }),
                  h('span', { className: 'ck-rc__rn' }, d.name),
                  h('span', { className: 'ck-rc__rv' }, top ? fmtMetric(top.value, d.metric) : '—')));
              })));
          }),
          groups.length ? null : h('p', { className: 'ck-rc__none' }, 'Nenhuma prova com esse nome.')),
        def ? h('section', { className: 'ck-rc__main', 'aria-label': def.name },
          h('header', { className: 'ck-rc__head' },
            h('div', { style: { minWidth: 0 } },
              h('h2', null, def.name),
              h('div', { className: 'ck-rc__tags' },
                h('span', null, h(Icon, { name: (RC_CAT[def.category] || RC_CAT.movimento)[1], size: 14 }), (RC_CAT[def.category] || RC_CAT.movimento)[0]),
                def.modality ? h('span', null, def.modality) : null,
                h('span', null, h(Icon, { name: betterOf(def.metric) === 'menor' ? 'trending-down' : 'trending-up', size: 14 }), metricLabel(def.metric)))),
            null),
          h('dl', { className: 'ck-rc__stats' },
            h('div', { className: 'is-lead' }, h('dt', null, h(Icon, { name: 'trophy', size: 14 }), 'Recorde da academia'),
              h('dd', null, leader ? fmtMetric(leader.value, def.metric) : '—'),
              h('span', null, leader ? (leader.student ? titleCase(leader.student.name) : 'Sem aluno') + ', ' + fdate(leader.date) : 'Ainda sem marca')),
            h('div', null, h('dt', null, 'Participantes'), h('dd', null, rank.filter(function (r) { return r.student; }).length), h('span', null, 'alunos com marca')),
            h('div', null, h('dt', null, 'Registros'), h('dd', null, dRecs.length), h('span', null, dRecs[0] ? 'último em ' + fdate(dRecs[0].date) : 'nenhum ainda'))),
          h('div', { className: 'ck-rc__bar' },
            h(Tabs, { label: 'Visualização', value: tab[0], onChange: tab[1], tabs: [{ value: 'ranking', label: 'Ranking' }, { value: 'historico', label: h(React.Fragment, null, 'Todos os registros', dRecs.length ? h('span', { className: 'ck-tabcount' }, dRecs.length) : null) }] }),
            h('div', { className: 'ck-control ck-ml__search ck-rc__search' },
              h(Icon, { name: 'search', size: 16, className: 'ck-control__icon' }),
              h('input', { type: 'search', className: 'ck-control__input', value: sq[0], placeholder: 'Buscar aluno', 'aria-label': 'Buscar aluno', onChange: function (e) { sq[1](e.target.value); } }))),
          !dRecs.length ? h('div', { className: 'ck-ml__empty' },
            h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: 'trophy', size: 20 })),
            h('strong', null, 'Ninguém marcou ' + def.name + ' ainda'),
            h('p', null, 'A primeira marca registrada já vira o recorde da academia.'),
            h(Button, { size: 'sm', variant: 'primary', icon: 'plus', onClick: openNew }, 'Registrar a primeira marca')) :
          !(tab[0] === 'ranking' ? rank : dRecs).some(match) ? h('div', {className:'ck-ml__empty'}, h('strong',null,'Nenhum registro para esta busca'),h('p',null,'Tente outro nome ou limpe a busca.')) : tab[0] === 'ranking' ? h('ol', { className: 'ck-rc__rank' }, rank.map(function (r, i) {
            if (!match(r)) return null;
            var gap = i && leader ? r.value - leader.value : null;
            return h('li', { key: r.id, className: cx(i === 0 && 'is-first') },
              h('span', { className: cx('ck-rc__pos', i < 3 && 'is-top') }, i + 1),
              r.student ? h(Avatar, { name: titleCase(r.student.name), size: 'sm', decorative: true }) : h('span', { className: 'ck-rc__noav', 'aria-hidden': true }, h(Icon, { name: 'building', size: 14 })),
              h('div', { className: 'ck-rc__who' },
                h('strong', null, r.student ? titleCase(r.student.name) : 'Marca da academia'),
                h('span', null, fdate(r.date) + (r.origin === 'aluno' ? ', informado pelo aluno' : ''))),
              h('div', { className: 'ck-rc__val' },
                h('strong', null, fmtMetric(r.value, def.metric)),
                gap != null ? h('span', null, fmtMetric(gap, def.metric, true) + ' do líder') : h('span', { className: 'ck-rc__lead' }, 'Recorde')));
          })) :
          h('ul', { className: 'ck-rc__hist' }, dRecs.map(function (r) {
            if (!match(r)) return null;
            return h('li', { key: r.id },
              h('span', { className: 'ck-rc__hdate' }, fdate(r.date)),
              h('div', { className: 'ck-rc__who' },
                h('strong', null, r.student ? titleCase(r.student.name) : 'Marca da academia'),
                h('span', null, (r.origin === 'aluno' ? 'Informado pelo aluno' : 'Medido pela equipe') + (r.notes ? ', ' + r.notes : ''))),
              pbIds[r.id] ? h(Badge, { tone: 'success', icon: 'trending-up' }, 'Melhorou') : h('span'),
              h('strong', { className: 'ck-rc__hval' }, fmtMetric(r.value, def.metric)),
              p.onEditRecord || p.onDeleteRecord ? h(ActionMenu, { label: 'Ações do registro', items: [
                p.onEditRecord ? { label: 'Editar', icon: 'pencil', onClick: function () { p.onEditRecord(r); } } : null,
                p.onDeleteRecord ? '-' : null,
                p.onDeleteRecord ? { label: 'Excluir registro', icon: 'trash', danger: true, onClick: function () { p.onDeleteRecord(r); } } : null].filter(Boolean) }) : h('span'));
          }))) : null),
      null);
  }

  function DefinicoesRecordes(p) {
    var defs = p.definitions || [];
    var qs = useState(''), cat = useState('todas'), act = useState(false);
    var q = qs[0].toLowerCase();
    var list = defs.filter(function (d) { return (cat[0] === 'todas' || d.category === cat[0]) && (!act[0] || d.active !== false) && (!q || d.name.toLowerCase().indexOf(q) >= 0); });
    var groups = [];
    list.forEach(function (d) { var g = d.modality || 'Sem modalidade', x = groups.filter(function (y) { return y.name === g; })[0]; if (!x) { x = { name: g, items: [] }; groups.push(x); } x.items.push(d); });
    var cc = { todas: defs.length }; defs.forEach(function (d) { cc[d.category] = (cc[d.category] || 0) + 1; });
    return h('div', { className: 'ck-ml ck-ac' },
      h('div', null,
        h(BackLink, { onClick: p.onBack }, 'Recordes'),
        h(PageHeader, { title: 'Definições de recordes', description: 'As provas e movimentos que a academia acompanha, e como cada marca é medida.',
          actions: h(Button, { variant: 'primary', icon: 'plus', onClick: p.onNew }, 'Nova definição') })),
      h('section', { className: 'ck-card ck-ml__panel', 'aria-label': 'Definições' },
        h('div', { className: 'ck-ml__toolbar' },
          h('div', { className: 'ck-mod__toolbar' },
            h('div', { className: 'ck-control ck-ml__search' }, h(Icon, { name: 'search', size: 18, className: 'ck-control__icon' }),
              h('input', { type: 'search', className: 'ck-control__input', value: qs[0], placeholder: 'Buscar definição', 'aria-label': 'Buscar definição', onChange: function (e) { qs[1](e.target.value); } })),
            h(Switch, { label: 'Só ativas', checked: act[0], onChange: act[1] })),
          h('div', { className: 'ck-ml__filters', role: 'radiogroup', 'aria-label': 'Categoria' }, [['todas', 'Todas']].concat(Object.keys(RC_CAT).filter(function (k) { return cc[k]; }).map(function (k) { return [k, RC_CAT[k][0], RC_CAT[k][1]]; })).map(function (x) {
            return h('button', { key: x[0], type: 'button', role: 'radio', 'aria-checked': cat[0] === x[0], className: 'ck-ml__chip', onClick: function () { cat[1](x[0]); } }, x[2] ? h(Icon, { name: x[2], size: 14 }) : null, x[1], h('span', { className: 'ck-ml__count' }, cc[x[0]]));
          }))),
        list.length ? h('div', { className: 'ck-mt-table-wrap' }, h('table', { className: 'ck-mt-table ck-rc__deft' },
          h('thead', null, h('tr', null, h('th', { scope: 'col' }, 'Definição'), h('th', { scope: 'col' }, 'Como mede'), h('th', { scope: 'col', className: 'is-num' }, 'Registros'), h('th', { scope: 'col' }, 'Recorde atual'), h('th', { scope: 'col' }, 'Ativa'), h('th', { scope: 'col' }, h('span', { className: 'ck-sr' }, 'Ações')))),
          groups.map(function (g) {
            return h('tbody', { key: g.name },
              h('tr', { className: 'ck-rc__gh' }, h('th', { colSpan: 6, scope: 'colgroup' }, g.name, h('span', null, g.items.length))),
              g.items.map(function (d) {
                var c = RC_CAT[d.category] || RC_CAT.movimento, t = RC_TYPES[d.metric.type] || RC_TYPES.numero;
                return h('tr', { key: d.id, className: cx('ck-ml__row', d.active === false && 'is-off'), onClick: function (e) { if (!e.target.closest('button, a')) p.onEdit && p.onEdit(d); } },
                  h('td', null, h('div', { className: 'ck-ml__plan' }, h('span', { className: 'ck-ml__mod', 'aria-hidden': true }, h(Icon, { name: c[1], size: 16 })),
                    h('span', null, h('span', { className: 'ck-mt-cell__main' }, d.name), h('span', { className: 'ck-mt-cell__sub' }, c[0])))),
                  h('td', null, h('span', { className: 'ck-mt-cell__main' }, d.metric.name || t.label), d.extraMetrics?.length ? h('span',{className:'ck-mt-cell__sub'},'+ ' + d.extraMetrics.length + ' métrica(s) adicional(is)') : null, h('span', { className: 'ck-mt-cell__sub ck-rc__dir' }, h(Icon, { name: betterOf(d.metric) === 'menor' ? 'trending-down' : 'trending-up', size: 13 }), betterOf(d.metric) === 'menor' ? 'Menor é melhor' : 'Maior é melhor')),
                  h('td', { className: 'is-num' }, d.count == null ? '—' : d.count),
                  h('td', null, d.top ? h(React.Fragment, null, h('span', { className: 'ck-mt-cell__main' }, fmtMetric(d.top.value, d.metric)), h('span', { className: 'ck-mt-cell__sub' }, d.top.who)) : h('span', { className: 'ck-al__missing' }, d.count == null ? 'Não disponível' : 'Sem marca')),
                  h('td', null, h(Switch, { checked: d.active !== false, ariaLabel: (d.active === false ? 'Ativar ' : 'Desativar ') + d.name, disabled: !!p.busy, onChange: function () { p.onToggleActive && p.onToggleActive(d); } })),
                  h('td', { className: 'ck-mt-actions' }, h(IconButton, { icon: 'pencil', size: 'sm', label: 'Editar ' + d.name, onClick: function () { p.onEdit && p.onEdit(d); } })));
              }));
          }))) : h('div', { className: 'ck-ml__empty' }, h('strong', null, 'Nenhuma definição encontrada'), h('p', null, 'Mude o filtro ou crie uma nova.'))));
  }

  function DefinicaoRecordeForm(p) {
    var d = p.definition || {};
    var edit = d.id != null;
    var init = useState(function () { var mt = d.metric || {}; return { name: d.name || '', category: d.category || 'prova', modality: d.modalityId == null ? '' : String(d.modalityId), description: d.description || '', type: mt.type || 'tempo', mname: mt.name || '', unit: mt.unit || '', better: mt.better || (RC_TYPES[mt.type || 'tempo'] || {}).better || 'menor', active: d.active !== false }; })[0];
    var extras = useState(d.extraMetrics || []);
    var fm = useState(init), f = fm[0], tried = useState(false), adv = useState(false);
    function set(k, v) { var n = Object.assign({}, f); n[k] = v; fm[1](n); }
    function setType(t) { var x = RC_TYPES[t]; fm[1](Object.assign({}, f, { type: t, better: x.better, unit: t === 'numero' ? f.unit : '', mname: '' })); }
    var tp = RC_TYPES[f.type];
    var err = {}; if (!f.name.trim()) err.name = 'Dê um nome. Ex.: 50m crawl, deadlift.';
    if (f.type === 'numero' && !f.mname.trim()) err.mname = 'Diga o que é medido. Ex.: pontos, rounds.';
    var show = tried[0] ? err : {};
    var dirty = JSON.stringify(f) !== JSON.stringify(init) || JSON.stringify(extras[0]) !== JSON.stringify(d.extraMetrics || []);
    var metric = { type: f.type, name: f.mname.trim() || tp.label, unit: f.type === 'numero' ? f.unit : tp.unit, better: f.better };
    var code = (f.mname.trim() || tp.label).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_') + (f.type === 'tempo' ? '_ms' : f.type === 'carga' ? '_kg' : '');
    var ex = f.type === 'tempo' ? [35400, 36120, 38900] : f.type === 'carga' ? [140, 130, 122.5] : f.type === 'reps' ? [42, 38, 31] : f.type === 'distancia' ? [2400, 2250, 2100] : [96, 88, 75];
    if (f.better === 'maior' && f.type === 'tempo') ex = ex.slice().reverse();
    if (f.better === 'menor' && f.type !== 'tempo') ex = ex.slice().reverse();
    function submit(e) { e.preventDefault(); tried[1](true); if (Object.keys(err).length) return;
      p.onSave && p.onSave({ id: d.id, name: f.name.trim(), category: f.category, modality: f.modality || null, description: f.description.trim() || null, active: f.active, metric: Object.assign({ code: code }, metric), extraMetrics: extras[0] }); }
    return h('form', { className: 'ck-al-form ck-rc-form', onSubmit: submit, noValidate: true }, h('fieldset', { disabled: !!p.saving, className: 'ck-rc-form__fieldset' },
      h('div', null,
        h(BackLink, { onClick: p.onBack }, 'Definições de recordes'),
        h(PageHeader, { title: edit ? 'Editar definição' : 'Nova definição', description: 'Uma prova ou movimento que a academia acompanha, e como a marca é medida.' })),
      edit && d.count ? h(Callout, { tone: 'info', title: d.count + ' registros usam esta definição' }, 'Mudar o tipo de medida ou a direção muda a ordem do ranking desses registros.') : null,
      h('div', { className: 'ck-al-form__grid' },
        h('div', { className: 'ck-card ck-al-form__main' },
          h(AlSection, { id: 'rd-s1', icon: 'trophy', title: 'O que é', done: !!f.name.trim() },
            h(TextField, { label: 'Nome', value: f.name, error: show.name, placeholder: 'Ex.: 50m crawl, Deadlift, Fran', onChange: function (e) { set('name', e.target.value); } }),
            h('div', { className: 'ck-field' }, h('span', { className: 'ck-field__label' }, 'Categoria'),
              h('div', { className: 'ck-ct-opts ck-rc__cats', role: 'radiogroup', 'aria-label': 'Categoria' }, Object.keys(RC_CAT).map(function (k) {
                var on = f.category === k, sub = { prova: 'Percurso ou distância', movimento: 'Um exercício, como agachamento', workout: 'Um treino completo', teste: 'Avaliação, como salto ou flexibilidade' }[k];
                return h('button', { key: k, type: 'button', role: 'radio', 'aria-checked': on, className: 'ck-ct-opt', onClick: function () { set('category', k); } },
                  h('span', { className: 'ck-cbm__micon', 'aria-hidden': true }, h(Icon, { name: RC_CAT[k][1], size: 18 })), h('span', { className: 'ck-ct-opt__text' }, h('strong', null, RC_CAT[k][0]), h('span', null, sub)));
              }))),
            h('div', { className: 'ck-al-grid' },
              h(SelectField, { label: 'Modalidade', value: f.modality, options: [{ value: '', label: 'Nenhuma, vale para todas' }].concat((p.modalities || []).map(function (m) { return { value: String(m.id), label: m.nome }; })), onChange: function (e) { set('modality', e.target.value); } }),
              h('div')),
            h(TextAreaField, { label: 'Descrição', value: f.description, placeholder: 'Opcional. Ex.: 5 pull-ups, 10 push-ups, 15 squats', onChange: function (e) { set('description', e.target.value); } })),
          h(AlSection, { id: 'rd-s2', icon: 'target', title: 'Como a marca é medida', done: !err.mname },
            h('div', { className: 'ck-ct-opts ck-rc__types', role: 'radiogroup', 'aria-label': 'Tipo de medida' }, Object.keys(RC_TYPES).map(function (k) {
              var t = RC_TYPES[k], on = f.type === k;
              return h('button', { key: k, type: 'button', role: 'radio', 'aria-checked': on, className: 'ck-ct-opt', onClick: function () { setType(k); } },
                h('span', { className: 'ck-cbm__micon', 'aria-hidden': true }, h(Icon, { name: t.icon, size: 18 })), h('span', { className: 'ck-ct-opt__text' }, h('strong', null, t.label), h('span', null, t.hint)));
            })),
            f.type === 'numero' ? h('div', { className: 'ck-al-grid' },
              h(TextField, { label: 'O que é medido', value: f.mname, error: show.mname, placeholder: 'Ex.: Pontos', onChange: function (e) { set('mname', e.target.value); } }),
              h(TextField, { label: 'Unidade', value: f.unit, placeholder: 'Ex.: pts', hint: 'Opcional.', onChange: function (e) { set('unit', e.target.value); } })) : null,
            h('div', { className: 'ck-field' }, h('span', { className: 'ck-field__label' }, 'Qual marca é melhor'),
              h(SegmentedControl, { label: 'Qual marca é melhor', block: true, value: f.better, onChange: function (v) { set('better', v); }, options: [{ value: 'menor', label: 'A menor', icon: 'trending-down' }, { value: 'maior', label: 'A maior', icon: 'trending-up' }] }),
              h('span', { className: 'ck-field__hint' }, f.type === 'tempo' ? (f.better === 'menor' ? 'Quem faz em menos tempo fica em 1º. Certo para provas.' : 'Quem aguenta mais tempo fica em 1º. Certo para prancha, por exemplo.') : (f.better === 'maior' ? 'Quem faz mais fica em 1º.' : 'Quem faz menos fica em 1º.'))),
            h('button', { type: 'button', className: 'ck-linkbtn', style: { justifySelf: 'start' }, 'aria-expanded': adv[0], onClick: function () { adv[1](!adv[0]); } }, adv[0] ? 'Esconder detalhes técnicos' : 'Detalhes técnicos'),
            adv[0] ? h('dl', { className: 'ck-cbm__tech' },
              h('div', null, h('dt', null, 'Código da métrica'), h('dd', null, h(CopyValue, { value: code, label: 'código' }))),
              h('div', null, h('dt', null, 'Valor salvo como'), h('dd', null, f.type === 'tempo' ? 'Milissegundos, número inteiro' : f.type === 'reps' ? 'Número inteiro' : 'Número decimal'))) : null),
          p.renderExtraMetrics ? p.renderExtraMetrics(extras[0], extras[1]) : null,
          h(AlSection, { id: 'rd-s3', icon: 'power', title: 'Situação', done: true },
            h('div', { className: 'ck-al-active' }, h(Switch, { label: 'Definição ativa', description: !edit ? 'Novas definições são criadas ativas.' : f.active ? 'Aparece em Recordes para registrar e no ranking.' : 'Some de Recordes. Os registros continuam guardados.', checked: f.active, disabled: !edit, onChange: function (v) { set('active', v); } })))),
        h('aside', { className: 'ck-al-sum', 'aria-label': 'Prévia do ranking' },
          h('span', { className: 'ck-ac-sumct__label' }, 'Como fica o ranking'),
          h('div', { className: 'ck-rc__prev' },
            h('strong', null, f.name.trim() || 'Nome da definição'),
            h('span', null, (RC_CAT[f.category] || RC_CAT.prova)[0] + (f.modality ? ', ' + ((p.modalities || []).find(m => String(m.id) === f.modality)?.nome || d.modality || '') : '') + ', ' + metricLabel(metric).toLowerCase()),
            h('ol', null, ['Ana', 'Bruno', 'Carla'].map(function (n, i) { return h('li', { key: n }, h('em', null, i + 1), h('span', null, n), h('b', null, fmtMetric(ex[i], metric))); }))),
          p.error ? h('p', {className:'ck-al-sum__err',role:'alert'}, p.error) : null,
          tried[0] && Object.keys(err).length ? h('p', { className: 'ck-al-sum__err', role: 'alert' }, err[Object.keys(err)[0]]) : null,
          h(Button, { type: 'submit', variant: 'primary', size: 'lg', block: true, icon: edit ? 'save' : 'plus', loading: p.saving, disabled: edit && !dirty }, p.saving ? 'Salvando…' : edit ? 'Salvar alterações' : 'Criar definição'),
          p.onCancel ? h('button', { type: 'button', className: 'ck-al-sum__cancel', onClick: p.onCancel }, 'Cancelar') : null))));
  }


export { RecordesPainel, DefinicoesRecordes, DefinicaoRecordeForm };
