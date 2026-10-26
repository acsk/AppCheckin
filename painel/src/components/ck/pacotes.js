/* Pacotes portados do layout HTML fornecido (Pacotes.html). */
import React from 'react';
import { ActionMenu, Avatar, Badge, Button, Callout, ChoiceCard, ChoiceGroup, Icon, IconButton, Modal, PageHeader, SegmentedControl, SelectField, Switch, Tabs, TextAreaField, TextField } from './index';
const h = React.createElement;
const { useState, useEffect, useRef } = React;
const cx = (...values) => values.filter(Boolean).join(' ');
function useControlled(value, initial, onChange) {
  const [state, setState] = useState(initial);
  return [value === undefined ? state : value, next => { setState(next); onChange?.(next); }];
}
const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
function money(v) { return BRL.format(+v || 0); }
function fdate(v) { if (!v) return '—'; const a = String(v).split(' ')[0].split('T')[0].split('-'); return a[2] + '/' + a[1] + '/' + a[0]; }
function isoToday() { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
function titleCase(value) {
  const s = String(value || '').trim();
  if (s !== s.toUpperCase()) return s;
  return s.toLowerCase().replace(/(^|\s)(\S)/g, (m, sp, c) => sp + c.toUpperCase()).replace(/\b(Da|De|Do|Das|Dos|E)\b/g, w => w.toLowerCase());
}
function firstName(value) { return titleCase(value).split(' ')[0]; }

  /* ---------- Pacotes ---------- */
  /* Ciclo do pacote = um ciclo do plano (plano_ciclos): { id, months, value }. */
  var PC_CYCLE_NAMES = { 1: 'Mensal', 2: 'Bimestral', 3: 'Trimestral', 4: 'Quadrimestral', 6: 'Semestral', 12: 'Anual' };
  function pcCycleLabel(m) { return PC_CYCLE_NAMES[m] || m + ' meses'; }
  function pcMonthsTxt(m) { return m === 1 ? '1 mês' : m + ' meses'; }
  function pcCalc(k) {
    var cy = k.cycle || null, months = cy ? Math.max(1, +cy.months || 1) : 1, people = Math.max(1, +k.people || 1), price = k.plan ? +k.plan.price || 0 : 0, total = +k.total || 0;
    // O ciclo do plano já traz o preço do período (com o desconto do ciclo); sem ele, mensalidade × meses.
    var unit = cy && +cy.value > 0 ? +cy.value : price * months;
    var normal = unit * people, save = normal - total;
    return { months: months, people: people, price: price, unit: unit, total: total, normal: normal, save: save, pct: normal ? save / normal : 0, perPersonMonth: total / people / months, perPerson: total / people };
  }
  function pcPct(x) { return (Math.round(Math.abs(x) * 1000) / 10).toFixed(1).replace(/\.0$/, '').replace('.', ',') + '%'; }
  function PcSaving(p) {
    var c = p.calc;
    if (!c.normal || !c.total) return h('span', { className: 'ck-pc__dash' }, '—');
    if (c.save > 0.004) return h(Badge, { tone: 'success' }, pcPct(c.pct) + ' mais barato');
    if (c.save < -0.004) return h(Badge, { tone: 'warning', icon: 'alert-triangle' }, pcPct(c.pct) + ' mais caro');
    return h(Badge, { tone: 'outline' }, 'Mesmo preço');
  }
  // Venda pelo app (pacotes.venda_app)
  var PC_SALE = [{ value: 'nao', label: 'Não vende' }, { value: 'liberados', label: 'Só liberados' }, { value: 'todos', label: 'Todos os alunos' }];
  var PC_SALE_HINT = {
    nao: 'Só o admin contrata, pelo painel.',
    liberados: 'Aparece no app só para os alunos que você liberar (ex.: depois de uma negociação).',
    todos: 'Qualquer aluno vê o pacote no app e pode contratar e pagar.',
  };
  var PC_CT = { pendente: ['warning', 'Aguardando pagamento'], ativo: ['success', 'Ativo'], expirado: ['outline', 'Encerrado'], cancelado: ['outline', 'Cancelado'] };

  function PacotesLista(p) {
    var pk = p.packages || [], cts = p.contracts || [];
    var tab = useControlled(p.tab, 'pacotes', p.onTabChange);
    var qs = useState(''), q = qs[0].trim().toLowerCase();
    var pend = cts.filter(function (c) { return c.status === 'pendente'; }), act = cts.filter(function (c) { return c.status === 'ativo'; });
    function tl(label, n, warn) { return h(React.Fragment, null, label, h('span', { className: cx('ck-pc__tabn', warn && n && 'is-warn') }, n)); }
    var tabs = [{ value: 'pacotes', label: tl('Pacotes', pk.length) }, { value: 'pendentes', label: tl('Aguardando pagamento', pend.length, true) }, { value: 'ativos', label: tl('Contratos ativos', act.length) }];
    var body;
    if (p.loading) {
      body = h('div', { className: 'ck-ml__empty' }, h('p', null, 'Carregando…'));
    } else if (tab[0] === 'pacotes') {
      var list = pk.filter(function (k) { return !q || (k.name + ' ' + (k.description || '') + ' ' + (k.plan ? k.plan.name : '')).toLowerCase().indexOf(q) >= 0; });
      body = list.length ? h('div', { className: 'ck-pc__wrap' }, h('table', { className: 'ck-pc__table is-fixed' },
        h('colgroup', null, [30, 15, 8, 15, 18, 14].map(function (w, i) { return h('col', { key: i, style: { width: w + '%' } }); })),
        h('thead', null, h('tr', null,
          h('th', { scope: 'col' }, 'Pacote'), h('th', { scope: 'col' }, 'Plano e ciclo'), h('th', { scope: 'col', className: 'is-num' }, 'Pessoas'),
          h('th', { scope: 'col', className: 'is-num' }, 'Valor do pacote'), h('th', { scope: 'col' }, 'Comparado ao plano avulso'),
          h('th', { scope: 'col' }, h('span', { className: 'ck-sr' }, 'Ações')))),
        h('tbody', null, list.map(function (k) {
          var c = pcCalc(k), off = k.active === false;
          return h('tr', { key: k.id, className: off ? 'is-off' : undefined },
            h('th', { scope: 'row' }, h('div', { className: 'ck-pc__name' },
              h('span', { className: 'ck-pc__icon', 'aria-hidden': true }, h(Icon, { name: 'package', size: 18 })),
              h('div', { style: { minWidth: 0 } },
                h('strong', null, k.name, off ? h(Badge, { tone: 'outline' }, 'Inativo') : null,
                  k.sale === 'todos' ? h(Badge, { tone: 'info', icon: 'cellphone' }, 'Vende no app') : k.sale === 'liberados' ? h(Badge, { tone: 'info', icon: 'cellphone' }, 'App: só liberados') : null),
                k.description ? h('span', { title: k.description }, k.description) : null,
                k.contracts ? h('span', null, k.contracts + (k.contracts === 1 ? ' contrato em aberto' : ' contratos em aberto')) : null))),
            h('td', null, h('span', { className: 'ck-pc__main' }, k.plan ? k.plan.name : '—'), h('span', { className: 'ck-pc__sub' }, k.cycle ? pcCycleLabel(c.months) + ', ' + pcMonthsTxt(c.months) : 'Sem ciclo')),
            h('td', { className: 'is-num' }, h('span', { className: 'ck-pc__main' }, c.people)),
            h('td', { className: 'is-num' }, h('span', { className: 'ck-pc__main' }, money(c.total)), h('span', { className: 'ck-pc__sub' }, money(c.perPersonMonth) + ' por pessoa/mês')),
            h('td', null, h(PcSaving, { calc: c }), c.normal ? h('span', { className: 'ck-pc__sub' }, 'Avulso: ' + money(c.normal) + (c.save > 0.004 ? ', economia de ' + money(c.save) : c.save < -0.004 ? ', ' + money(-c.save) + ' a menos' : '')) : null),
            h('td', null, h('div', { className: 'ck-pc__acts' },
              !off && p.onContract ? h(Button, { size: 'sm', onClick: function () { p.onContract(k); } }, 'Contratar') : null,
              h(ActionMenu, { label: 'Mais ações para ' + k.name, items: [
                p.onEdit ? { label: 'Editar pacote', icon: 'pencil', onClick: function () { p.onEdit(k); } } : null,
                p.onDuplicate ? { label: 'Duplicar', icon: 'copy', onClick: function () { p.onDuplicate(k); } } : null,
                p.onReleases ? { label: 'Alunos liberados no app', icon: 'user-check', onClick: function () { p.onReleases(k); } } : null,
                p.onToggleActive ? { label: off ? 'Ativar' : 'Desativar', icon: 'power', onClick: function () { p.onToggleActive(k); } } : null].filter(Boolean) }))));
        })))) : h('div', { className: 'ck-ml__empty' },
          h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: q ? 'search' : 'package', size: 20 })),
          h('strong', null, q ? 'Nenhum pacote com "' + qs[0] + '"' : 'Nenhum pacote criado'),
          h('p', null, q ? 'Busque pelo nome, descrição ou plano.' : 'Um pacote junta várias pessoas num só pagamento, como um plano família.'),
          q || !p.onNew ? null : h(Button, { size: 'sm', variant: 'primary', icon: 'plus', onClick: p.onNew }, 'Novo pacote'));
    } else {
      var cl = (tab[0] === 'pendentes' ? pend : act).filter(function (c) { return !q || (('#' + c.id) + ' ' + c.pkg.name + ' ' + c.payer.name + ' ' + (c.members || []).map(function (m) { return m.name; }).join(' ')).toLowerCase().indexOf(q) >= 0; });
      body = cl.length ? h('div', { className: 'ck-pc__wrap' }, h('table', { className: 'ck-pc__table' },
        h('thead', null, h('tr', null,
          h('th', { scope: 'col' }, 'Contrato'), h('th', { scope: 'col' }, 'Pagante'), h('th', { scope: 'col' }, 'Beneficiários'),
          h('th', { scope: 'col', className: 'is-num' }, 'Valor'), h('th', { scope: 'col' }, tab[0] === 'pendentes' ? 'Situação' : 'Vigência'), h('th', { scope: 'col' }, h('span', { className: 'ck-sr' }, 'Ações')))),
        h('tbody', null, cl.map(function (c) {
          var mem = c.members || [], slots = c.people || mem.length, miss = slots - mem.length, st = PC_CT[c.status] || PC_CT.pendente;
          return h('tr', { key: c.id },
            h('th', { scope: 'row' }, h('div', { className: 'ck-pc__name' },
              h('span', { className: 'ck-pc__icon', 'aria-hidden': true }, h(Icon, { name: 'file-text', size: 18 })),
              h('div', { style: { minWidth: 0 } }, h('strong', null, c.pkg.name, c.origin === 'app' ? h(Badge, { tone: 'info', icon: 'cellphone' }, 'Pelo app') : null),
                h('span', null, 'Contrato #' + c.id + (c.createdAt ? ', criado em ' + fdate(c.createdAt) : '')),
                c.origin === 'app' && c.status === 'ativo' && miss > 0 ? h('span', null, 'Pago pelo aluno: associe os demais participantes') : null))),
            h('td', null, h('div', { className: 'ck-pc__person' }, h(Avatar, { name: titleCase(c.payer.name), size: 'sm' }), h('span', null, titleCase(c.payer.name)))),
            h('td', null, h('div', { className: 'ck-pc__members' },
              h('div', { className: 'ck-pc__faces', 'aria-hidden': true }, mem.slice(0, 4).map(function (m, i) { return h(Avatar, { key: i, name: titleCase(m.name), size: 'sm' }); })),
              h('span', null, mem.length + ' de ' + slots, miss > 0 ? h('em', null, miss === 1 ? 'falta 1' : 'faltam ' + miss) : c.missingEnrollments > 0 ? h('em', null, c.missingEnrollments === 1 ? '1 sem matrícula' : c.missingEnrollments + ' sem matrícula') : null))),
            h('td', { className: 'is-num' }, h('span', { className: 'ck-pc__main' }, money(c.total))),
            h('td', null, tab[0] === 'pendentes' ? h(React.Fragment, null, h(Badge, { tone: st[0], dot: true }, st[1]), c.createdAt ? h('span', { className: 'ck-pc__sub' }, 'desde ' + fdate(c.createdAt)) : null)
              : h(React.Fragment, null, h('span', { className: 'ck-pc__main' }, fdate(c.start) + ' a ' + fdate(c.end)))),
            h('td', null, h('div', { className: 'ck-pc__acts' },
              c.status === 'pendente' && p.onConfirmPayment ? h(Button, { size: 'sm', variant: 'primary', icon: 'check', onClick: function () { p.onConfirmPayment(c); } }, 'Confirmar pagamento') : null,
              c.status !== 'pendente' && miss > 0 && p.onBeneficiaries ? h(Button, { size: 'sm', icon: 'user-plus', onClick: function () { p.onBeneficiaries(c); } }, 'Completar') : null,
              c.status === 'ativo' && miss <= 0 && c.missingEnrollments > 0 && p.onGenerateEnrollments ? h(Button, { size: 'sm', icon: 'refresh-cw', onClick: function () { p.onGenerateEnrollments(c); } }, 'Gerar matrículas') : null,
              h(ActionMenu, { label: 'Mais ações do contrato #' + c.id, items: [
                p.onBeneficiaries ? { label: 'Beneficiários', icon: 'users', onClick: function () { p.onBeneficiaries(c); } } : null,
                p.onGenerateEnrollments && c.status === 'ativo' ? { label: 'Gerar matrículas que faltam', icon: 'refresh-cw', disabled: !(c.missingEnrollments > 0), onClick: function () { p.onGenerateEnrollments(c); } } : null,
                p.onCancelContract && c.status === 'pendente' ? '-' : null,
                p.onCancelContract && c.status === 'pendente' ? { label: 'Cancelar contrato', icon: 'ban', danger: true, onClick: function () { p.onCancelContract(c); } } : null].filter(Boolean) }))));
        })))) : h('div', { className: 'ck-ml__empty' },
          h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: q ? 'search' : 'check-circle', size: 20 })),
          h('strong', null, q ? 'Nenhum contrato com "' + qs[0] + '"' : tab[0] === 'pendentes' ? 'Nenhum contrato aguardando pagamento' : 'Nenhum contrato ativo'),
          h('p', null, q ? 'Busque pelo número, pacote, pagante ou beneficiário.' : 'Contratos aparecem aqui depois de "Contratar" um pacote.'));
    }
    return h('div', { className: 'ck-pc' },
      h(PageHeader, { title: 'Pacotes', description: 'Um pagamento para várias pessoas, como planos família. Crie o pacote, contrate para um pagante e confirme o pagamento.',
        actions: h(React.Fragment, null,
          p.onContract ? h(Button, { icon: 'file-text', onClick: function () { p.onContract(null); } }, 'Contratar pacote') : null,
          p.onNew ? h(Button, { variant: 'primary', icon: 'plus', onClick: p.onNew }, 'Novo pacote') : null) }),
      h('section', { className: 'ck-pc__panel' },
        h('div', { className: 'ck-pc__top' },
          h(Tabs, { label: 'Pacotes e contratos', value: tab[0], onChange: function (v) { tab[1](v); qs[1](''); }, tabs: tabs }),
          h('div', { className: 'ck-control ck-ml__search ck-pc__search' }, h(Icon, { name: 'search', size: 16, className: 'ck-control__icon' }),
            h('input', { type: 'search', className: 'ck-control__input', value: qs[0], placeholder: tab[0] === 'pacotes' ? 'Buscar pacote ou plano' : 'Contrato, pagante ou beneficiário', 'aria-label': 'Buscar', onChange: function (e) { qs[1](e.target.value); } }))),
        body));
  }

  function pcMoneyIn(v) { return v ? (+v).toFixed(2).replace('.', ',') : ''; }
  function pcParseMoney(v) { var s = String(v || ''); if (s.indexOf(',') >= 0) s = s.replace(/\./g, '').replace(',', '.'); return parseFloat(s) || 0; }

  function PacoteModal(p) {
    var k = p.package || {}, edit = k.id != null, plans = p.plans || [];
    // Com contrato em aberto, plano e ciclo ficam presos ao grupo já formado (a API também recusa)
    var locked = edit && k.contracts > 0;
    var init = useState(function () { return { name: k.name || '', description: k.description || '', plan: k.plan ? String(k.plan.id) : '', cycle: k.cycle ? String(k.cycle.id) : '', people: k.people ? String(k.people) : '2', total: pcMoneyIn(k.total), active: k.active !== false, sale: k.sale || 'nao' }; })[0];
    var appRef = useRef(null);
    useEffect(function () { if (p.initialSection === 'app') appRef.current?.scrollIntoView({ block: 'start' }); }, []);
    var fm = useState(init), f = fm[0], tried = useState(false);
    function set(key, v) { var n = Object.assign({}, f); n[key] = v; fm[1](n); }
    var plan = plans.filter(function (x) { return String(x.id) === String(f.plan); })[0];
    var cycles = plan ? plan.cycles || [] : [];
    var cycle = cycles.filter(function (x) { return String(x.id) === String(f.cycle); })[0];
    var total = pcParseMoney(f.total);
    var c = pcCalc({ plan: plan, cycle: cycle, people: +f.people || 1, total: total });
    var err = {};
    if (!f.name.trim()) err.name = 'Dê um nome ao pacote.';
    if (!plan) err.plan = 'Escolha o plano que cada pessoa vai ter.';
    else if (!cycle) err.cycle = cycles.length ? 'Escolha o ciclo.' : 'Esse plano não tem ciclos ativos. Cadastre um em Planos.';
    if (!(+f.people >= 2)) err.people = 'Pelo menos 2 pessoas: quem paga e mais uma.';
    if (!(total > 0)) err.total = 'Informe o valor do pacote.';
    var show = tried[0] ? err : {};
    var dirty = JSON.stringify(f) !== JSON.stringify(init);
    function pickPlan(id) {
      var np = plans.filter(function (x) { return String(x.id) === id; })[0], list = np ? np.cycles || [] : [];
      var keep = cycle ? list.filter(function (x) { return +x.months === +cycle.months; })[0] : null;
      fm[1](Object.assign({}, f, { plan: id, cycle: keep ? String(keep.id) : list[0] ? String(list[0].id) : '' }));
    }
    function save() {
      tried[1](true);
      if (Object.keys(err).length) return;
      p.onSave && p.onSave({ id: k.id, name: f.name.trim(), description: f.description.trim() || null, planId: plan.id, cycleId: cycle.id, people: +f.people, total: total, active: f.active, sale: f.sale });
    }
    function suggest(pct) { set('total', (Math.round(c.normal * (1 - pct) * 100) / 100).toFixed(2).replace('.', ',')); }
    function sec(n, title, desc, kids, ref) {
      return h('section', { className: 'ck-pc__sec', ref: ref },
        h('header', null, h('span', { className: 'ck-pc__secn', 'aria-hidden': true }, n), h('div', null, h('h3', null, title), desc ? h('p', null, desc) : null)), kids);
    }
    var live = (p.releases || []).filter(function (r) { return !r.vencida; }).length;
    return h(Modal, { open: p.open, inline: p.inline, onClose: p.onCancel, dismissable: !p.saving, size: 'lg', dialogClassName: 'ck-pc__dlg',
      leading: h('span', { className: 'ck-adm__icon', 'aria-hidden': true }, h(Icon, { name: 'package', size: 20 })),
      title: edit ? 'Editar pacote' : 'Novo pacote',
      description: edit && k.contracts ? 'Tem ' + k.contracts + (k.contracts === 1 ? ' contrato em aberto' : ' contratos em aberto') + '. O valor novo vale para os próximos contratos e renovações; plano, ciclo e menos vagas ficam travados.' : 'Várias pessoas com o mesmo plano, num só pagamento.',
      footer: h('div', { className: 'ck-adm__foot ck-pc__foot' },
        h('span', { className: 'ck-pc__footnote' }, dirty ? 'Alterações não salvas' : edit ? 'Nada alterado' : null),
        h(Button, { variant: 'ghost', disabled: p.saving, onClick: p.onCancel }, 'Cancelar'),
        h(Button, { variant: 'primary', icon: edit ? 'save' : 'plus', loading: p.saving, disabled: edit && !dirty, onClick: save }, edit ? 'Salvar pacote' : 'Criar pacote')) },
      h('div', { className: 'ck-pc__form' },
        h('div', { className: 'ck-pc__fields' },
          sec(1, 'Pacote', null, h(React.Fragment, null,
          h(TextField, { label: 'Nome', value: f.name, error: show.name, placeholder: 'Ex.: Família 2x bimestral', onChange: function (e) { set('name', e.target.value); } }),
          h(TextAreaField, { label: 'Descrição', labelAside: 'opcional', rows: 2, value: f.description, placeholder: 'Ex.: Para pessoas da mesma família.', onChange: function (e) { set('description', e.target.value); } }))),
          sec(2, 'Preço', 'Quanto custa e o que cada pessoa recebe.', h(React.Fragment, null,
          h(SelectField, { label: 'Plano de cada pessoa', value: f.plan, placeholder: 'Escolha o plano', error: show.plan, disabled: locked,
            hint: locked ? 'Plano e ciclo não mudam com contratos em aberto. Para mudar, duplique o pacote e desative este.' : undefined, options: plans.map(function (x) { return { value: String(x.id), label: x.name + (x.modality ? ', ' + x.modality : '') + ' — ' + money(x.price) + '/mês' }; }), onChange: function (e) { pickPlan(e.target.value); } }),
          plan ? h('div', { className: 'ck-field' },
            h('span', { className: 'ck-field__label' }, 'Ciclo'),
            cycles.length ? h('div', { className: 'ck-ml__filters', role: 'radiogroup', 'aria-label': 'Ciclo' }, cycles.map(function (x) {
              return h('button', { key: x.id, type: 'button', role: 'radio', 'aria-checked': String(f.cycle) === String(x.id), className: 'ck-ml__chip', disabled: locked, onClick: function () { set('cycle', String(x.id)); } }, pcCycleLabel(+x.months));
            })) : null,
            show.cycle ? h('span', { className: 'ck-field__error' }, show.cycle) : cycle ? h('span', { className: 'ck-field__hint' }, 'O pacote cobre ' + pcMonthsTxt(c.months) + '. No plano, esse ciclo custa ' + money(cycle.value) + ' por pessoa.') : null) : null,
          h('div', { className: 'ck-al-grid' },
            h('div', { className: 'ck-field' },
              h('span', { className: 'ck-field__label', id: 'pc-people' }, 'Pessoas no pacote'),
              h('div', { className: 'ck-au__step', role: 'group', 'aria-labelledby': 'pc-people' },
                h(IconButton, { icon: 'minus', label: 'Menos uma pessoa', variant: 'secondary', disabled: !(+f.people > 2), onClick: function () { set('people', String(Math.max(2, (+f.people || 2) - 1))); } }),
                h('input', { className: 'ck-au__stepin', inputMode: 'numeric', value: f.people, 'aria-label': 'Quantidade de pessoas', onChange: function (e) { set('people', e.target.value.replace(/\D/g, '').slice(0, 2)); } }),
                h(IconButton, { icon: 'plus', label: 'Mais uma pessoa', variant: 'secondary', onClick: function () { set('people', String((+f.people || 0) + 1)); } })),
              show.people ? h('span', { className: 'ck-field__error' }, show.people) : h('span', { className: 'ck-field__hint' }, 'Contando quem paga.')),
            h(TextField, { label: 'Valor do pacote', prefix: 'R$', inputMode: 'decimal', value: f.total, error: show.total, placeholder: '0,00', hint: !show.total ? 'Total pago pelo ciclo inteiro' : null, onChange: function (e) { set('total', e.target.value.replace(/[^\d,.]/g, '')); } })))),
          sec(3, 'Venda', 'Onde o pacote pode ser contratado.', h(React.Fragment, null,
            h('div', { className: 'ck-al-active' }, h(Switch, { label: 'Pacote ativo', description: f.active ? 'Pode ser contratado.' : 'Não aparece para novos contratos. Contratos atuais continuam.', checked: f.active, onChange: function (v) { set('active', v); } })),
            h(ChoiceGroup, { label: 'Venda pelo app', className: 'ck-pc__sale' }, PC_SALE.map(function (sale) {
              return h(ChoiceCard, { key: sale.value, title: sale.label, description: { nao: 'Só pelo painel.', liberados: 'Apenas alunos escolhidos.', todos: 'Qualquer aluno pode contratar.' }[sale.value], checked: f.sale === sale.value, disabled: !f.active, onSelect: function () { set('sale', sale.value); } });
            })),
            f.sale !== 'nao' ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'Quem compra vira o pagante. Depois do pagamento, você completa os beneficiários em Contratos. O pagante renova pelo app.') : null,
            edit && p.initialSection === 'app' && f.sale !== 'liberados' ? h(Callout, { tone: 'info' }, 'A lista fica guardada, mas só vale quando a venda está em “Só liberados”.') : null,
            f.sale === 'liberados' || (edit && p.initialSection === 'app') ? (edit ? h(PacoteLiberacoesEditor, { releases: p.releases, students: p.students, saving: p.saving, onAdd: p.onRelease, onRemove: p.onRevoke, error: p.releasesError, onRetry: p.onRetryReleases })
              : h(Callout, { tone: 'info' }, 'Salve o novo pacote para liberar os alunos nesta seção.'))
              : (p.releases || []).length ? h('p', { className: 'ck-al-hint' }, 'A lista de alunos liberados fica guardada, mas só vale em “Só liberados”.') : null), appRef)),
        h('aside', { className: 'ck-pc__calc', 'aria-live': 'polite' },
          h('span', { className: 'ck-pc__calctitle' }, 'Comparação com o plano avulso'),
          plan && cycle ? h(React.Fragment, null,
            h('dl', null,
              h('div', null, h('dt', null, 'Sem o pacote', h('small', null, c.people + (c.people === 1 ? ' pessoa' : ' pessoas') + ' × ' + money(c.unit) + ' (' + pcCycleLabel(c.months).toLowerCase() + ')')), h('dd', null, money(c.normal))),
              h('div', null, h('dt', null, 'Com o pacote'), h('dd', null, total ? money(total) : '—')),
              total ? (Math.abs(c.save) < 0.005 ? h('div', { className: 'is-total is-same' }, h('dt', null, 'Mesmo preço do plano avulso', h('small', null, 'Sem desconto para quem contrata o pacote')), h('dd', null, '0%'))
                : h('div', { className: cx('is-total', c.save < -0.004 && 'is-bad') }, h('dt', null, c.save >= 0 ? 'Economia' : 'Fica mais caro'), h('dd', null, money(Math.abs(c.save)), h('small', null, pcPct(c.pct))))) : null),
            total ? h('dl', { className: 'ck-pc__per' },
              h('div', null, h('dt', null, 'Por pessoa, por mês'), h('dd', null, money(c.perPersonMonth))),
              h('div', null, h('dt', null, 'Por pessoa, no ciclo'), h('dd', null, money(c.perPerson)))) : null,
            c.save < -0.004 && total ? h('p', { className: 'ck-pc__warn', role: 'alert' }, h(Icon, { name: 'alert-triangle', size: 15 }), 'O pacote está mais caro que cada pessoa pagar o plano. Confira o valor ou o ciclo.') : null,
            c.normal ? h('div', { className: 'ck-pc__sugg' }, h('span', null, 'Sugerir valor com desconto'),
              h('div', null, [0.1, 0.15, 0.2, 0.25].map(function (x) { return h('button', { key: x, type: 'button', className: 'ck-ml__chip', 'aria-pressed': Math.abs(Math.round(c.normal * (1 - x) * 100) / 100 - total) < 0.005, onClick: function () { suggest(x); } }, pcPct(x)); }))) : null)
            : h('p', { className: 'ck-pc__calcempty' }, plan ? 'Escolha o ciclo para ver quanto o pacote economiza.' : 'Escolha o plano para ver quanto o pacote economiza.'),
          h('div', { className: 'ck-pc__calcsale' }, h('span', null, 'No app'),
            h('strong', null, !f.active ? 'Inativo' : f.sale === 'nao' ? 'Não aparece' : f.sale === 'todos' ? 'Todos os alunos' : p.releases == null && edit ? 'Carregando liberações…' : live ? live + (live === 1 ? ' aluno liberado' : ' alunos liberados') : 'Ninguém liberado'),
            f.active && f.sale === 'liberados' && !live ? h('small', null, 'Sem liberações válidas, não aparece para ninguém.') : null))));
  }

  function pcNorm(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  /* Seletor de alunos para pacotes: busca + lista dos escolhidos. */
  function PcPeople(p) {
    var qs = useState(''), q = pcNorm(qs[0].trim()), focus = useState(false);
    var sel = p.value || [], ids = sel.map(function (s) { return String(s.id); });
    var ex = (p.exclude || []).map(String);
    var full = p.max != null && sel.length >= p.max;
    var res = (p.students || []).filter(function (s) { return ids.indexOf(String(s.id)) < 0 && ex.indexOf(String(s.id)) < 0 && (!q || pcNorm(s.name + ' ' + (s.email || '') + ' ' + (s.cpf || '') + ' ' + String(s.cpf || '').replace(/\D/g, '') + ' ' + (s.enrollment || '') + ' ' + s.id).indexOf(q) >= 0); }).slice(0, 6);
    var open = !full && focus[0] && (!p.single || !sel.length);
    return h('div', { className: 'ck-pc__people' },
      p.single && sel.length ? h('span', { className: 'ck-field__label' }, p.label) : h('div', { className: 'ck-pc__pick', onBlur: function (e) { if (!e.currentTarget.contains(e.relatedTarget)) focus[1](false); }, onKeyDown: function (e) { if (e.key === 'Escape' && open) { e.preventDefault(); e.stopPropagation(); focus[1](false); } } },
        h(TextField, { label: p.label, labelAside: p.labelAside, icon: 'search', value: qs[0], disabled: full, placeholder: full ? 'Todas as vagas preenchidas' : p.placeholder || 'Nome ou e-mail do aluno', error: p.error, onFocus: function () { focus[1](true); }, onClick: function () { focus[1](true); }, onChange: function (e) { qs[1](e.target.value); focus[1](true); } }),
        open ? h('ul', { className: 'ck-pc__res', role: 'listbox', 'aria-label': 'Alunos encontrados' }, res.length ? res.map(function (s) {
          return h('li', { key: s.id }, h('button', { type: 'button', role: 'option', 'aria-selected': false, onClick: function () { qs[1](''); focus[1](false); p.onAdd(s); } },
            h(Avatar, { name: titleCase(s.name), size: 'sm' }), h('span', null, h('strong', null, titleCase(s.name)), h('small', null, s.email || 'Sem e-mail')), h(Icon, { name: 'plus', size: 16 })));
        }) : h('li', { className: 'ck-pc__resempty' }, q ? 'Nenhum aluno com "' + qs[0] + '".' : 'Comece a digitar o nome.')) : null),
      sel.length ? h('ul', { className: 'ck-pc__sel', 'aria-label': 'Pessoas escolhidas' }, sel.map(function (s) {
        return h('li', { key: s.id }, h(Avatar, { name: titleCase(s.name), size: 'sm' }),
          h('span', null, h('strong', null, titleCase(s.name)), s.tag ? h('small', null, s.tag) : null),
          p.onRemove && !s.locked ? h(IconButton, { icon: 'x', label: 'Tirar ' + titleCase(s.name), size: 'sm', onClick: function () { p.onRemove(s); } }) : null);
      })) : null);
  }

  /* O pagante sempre usa o pacote (ocupa 1 vaga): a API inclui o aluno dele no contrato. */
  function ContratarPacoteModal(p) {
    var pk = (p.packages || []).filter(function (k) { return k.active !== false; });
    var ks = useState(p.package ? String(p.package.id) : ''), payer = useState([]), mem = useState([]), tried = useState(false), busy = useState(false);
    var k = pk.filter(function (x) { return String(x.id) === ks[0]; })[0];
    var c = k ? pcCalc(k) : null;
    var payerS = payer[0][0];
    var payers = (p.students || []).filter(function (s) { return s.usuarioId; });
    var people = (payerS ? [Object.assign({}, payerS, { tag: 'Pagante', locked: true })] : []).concat(mem[0]);
    var slots = c ? c.people : 0, left = slots - people.length;
    var err = {};
    if (!k) err.pkg = 'Escolha o pacote.';
    if (!payerS) err.payer = 'Escolha quem vai pagar.';
    if (k && people.length > slots) err.mem = 'O pacote tem ' + slots + ' vagas. Tire ' + (people.length - slots) + '.';
    var show = tried[0] ? err : {};
    function go() {
      tried[1](true); if (Object.keys(err).length) return;
      busy[1](true);
      Promise.resolve(p.onConfirm && p.onConfirm({ packageId: k.id, payerUsuarioId: payerS.usuarioId, beneficiaryIds: mem[0].map(function (x) { return x.id; }) })).then(function () { busy[1](false); }, function () { busy[1](false); });
    }
    return h(Modal, { open: p.open, inline: p.inline, onClose: p.onCancel, size: 'md', autoFocus: false, dialogClassName: 'ck-pc__mdl',
      leading: h('span', { className: 'ck-adm__icon', 'aria-hidden': true }, h(Icon, { name: 'file-text', size: 20 })),
      title: 'Contratar pacote', description: 'O contrato fica aguardando pagamento até você confirmar.',
      footer: h('div', { className: 'ck-adm__foot' },
        h(Button, { variant: 'ghost', onClick: p.onCancel }, 'Cancelar'),
        h(Button, { variant: 'primary', icon: 'check', loading: busy[0], onClick: go }, k ? 'Contratar por ' + money(k.total) : 'Contratar')) },
      h('div', { className: 'ck-adm' },
        h(SelectField, { label: '1. Pacote', value: ks[0], placeholder: 'Escolha o pacote', error: show.pkg, options: pk.map(function (x) { return { value: String(x.id), label: x.name + ' — ' + money(x.total) + ', ' + x.people + (x.people === 1 ? ' pessoa' : ' pessoas') }; }), onChange: function (e) { ks[1](e.target.value); } }),
        k ? h('div', { className: 'ck-pc__mini' },
          h('span', null, h(Icon, { name: 'layers', size: 15 }), (k.plan ? k.plan.name : '—') + (k.cycle ? ', ' + pcCycleLabel(c.months).toLowerCase() : '')),
          h('span', null, h(Icon, { name: 'users', size: 15 }), c.people + (c.people === 1 ? ' pessoa' : ' pessoas')),
          h('span', null, h(Icon, { name: 'wallet', size: 15 }), money(c.perPersonMonth) + ' por pessoa/mês')) : null,
        h(PcPeople, { label: '2. Quem paga', single: true, students: payers, value: payer[0], error: show.payer, onAdd: function (s) { payer[1]([s]); mem[1](mem[0].filter(function (x) { return x.id !== s.id; })); }, onRemove: function () { payer[1]([]); } }),
        k ? h(PcPeople, { label: '3. Beneficiários', labelAside: people.length + ' de ' + slots, students: p.students, value: people, max: slots, error: show.mem,
          onAdd: function (s) { mem[1](mem[0].concat([s])); }, onRemove: function (s) { mem[1](mem[0].filter(function (x) { return x.id !== s.id; })); } }) : null,
        k && payerS ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), firstName(payerS.name) + ' paga e também usa o pacote, ocupando 1 vaga.' + (left > 0 ? (left === 1 ? ' Sobra 1 vaga, dá para completar depois.' : ' Sobram ' + left + ' vagas, dá para completar depois.') : '')) : null));
  }

  function PcContractCard(p) {
    var c = p.contract;
    return h('div', { className: 'ck-pc__card' },
      h('span', { className: 'ck-pc__icon', 'aria-hidden': true }, h(Icon, { name: 'package', size: 18 })),
      h('div', null, h('strong', null, c.pkg.name), h('span', null, 'Contrato #' + c.id + ', pago por ' + titleCase(c.payer.name))),
      h('b', null, money(c.total)));
  }

  function PacoteBeneficiariosModal(p) {
    var c = p.contract || { pkg: {}, payer: {} };
    var slots = c.people || 0, ativo = c.status === 'ativo';
    // Contrato pago: quem já está tem matrícula ativa e não sai por aqui. O pagante nunca sai.
    var init = (c.members || []).map(function (m) { var pag = c.payer.id != null && String(m.id) === String(c.payer.id); return Object.assign({}, m, { tag: pag ? 'Pagante' : ativo ? 'Com matrícula' : m.tag, locked: pag || ativo }); });
    var mem = useState(init), busy = useState(false);
    var changed = JSON.stringify(mem[0].map(function (m) { return m.id; })) !== JSON.stringify(init.map(function (m) { return m.id; }));
    var added = mem[0].filter(function (m) { return !init.some(function (x) { return x.id === m.id; }); }).length;
    var removed = init.filter(function (m) { return !mem[0].some(function (x) { return x.id === m.id; }); }).length;
    var parts = []; if (added) parts.push(added === 1 ? '1 entra' : added + ' entram'); if (removed) parts.push(removed === 1 ? '1 sai' : removed + ' saem');
    return h(Modal, { open: p.open, inline: p.inline, onClose: p.onCancel, size: 'md', autoFocus: false, dialogClassName: 'ck-pc__mdl',
      leading: h('span', { className: 'ck-adm__icon', 'aria-hidden': true }, h(Icon, { name: 'users', size: 20 })),
      title: 'Beneficiários', description: 'Quem usa o pacote. Cada pessoa ganha uma matrícula no plano do pacote.',
      footer: h('div', { className: 'ck-adm__foot' },
        parts.length ? h('span', { className: 'ck-au__replinfo' }, parts.join(', ')) : null,
        h(Button, { variant: 'ghost', onClick: p.onCancel }, 'Cancelar'),
        h(Button, { variant: 'primary', icon: 'save', disabled: !changed, loading: busy[0], onClick: function () { busy[1](true); Promise.resolve(p.onSave && p.onSave({ contractId: c.id, beneficiaryIds: mem[0].map(function (m) { return m.id; }) })).then(function () { busy[1](false); }, function () { busy[1](false); }); } }, 'Salvar beneficiários')) },
      h('div', { className: 'ck-adm' },
        h(PcContractCard, { contract: c }),
        h(PcPeople, { label: 'Beneficiários', labelAside: mem[0].length + ' de ' + slots, students: p.students, value: mem[0], max: slots,
          onAdd: function (s) { mem[1](mem[0].concat([s])); }, onRemove: function (s) { mem[1](mem[0].filter(function (x) { return x.id !== s.id; })); } }),
        slots - mem[0].length > 0 ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), (slots - mem[0].length === 1 ? 'Falta 1 pessoa' : 'Faltam ' + (slots - mem[0].length) + ' pessoas') + ' para completar o pacote.') : null,
        ativo ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'Quem entrar agora ganha matrícula ativa até ' + fdate(c.end) + '. Para tirar alguém, cancele a matrícula da pessoa.')
          : h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'O valor do pacote é rateado de novo entre as pessoas.')));
  }

  function pcMethodIcon(nome) {
    var n = pcNorm(nome);
    if (n.indexOf('pix') >= 0) return 'qr-code';
    if (n.indexOf('dinheiro') >= 0) return 'banknote';
    if (n.indexOf('cart') >= 0 || n.indexOf('credito') >= 0 || n.indexOf('debito') >= 0) return 'credit-card';
    if (n.indexOf('boleto') >= 0) return 'receipt';
    if (n.indexOf('transf') >= 0 || n.indexOf('ted') >= 0) return 'landmark';
    return 'wallet';
  }

  function ConfirmarPagamentoPacoteModal(p) {
    var c = p.contract || { pkg: {}, payer: {} };
    var today = p.today || isoToday();
    var methods = p.methods || [];
    var m = useState(p.defaultMethod ? String(p.defaultMethod) : ''), d = useState(today), ref = useState(''), tried = useState(false), busy = useState(false);
    var err = {}; if (!m[0]) err.m = 'Escolha como foi pago.'; if (!d[0]) err.d = 'Informe a data.';
    var show = tried[0] ? err : {};
    var mem = c.members || [], slots = c.people || mem.length;
    function go() { tried[1](true); if (Object.keys(err).length) return; busy[1](true); Promise.resolve(p.onConfirm && p.onConfirm({ contractId: c.id, methodId: +m[0], paidAt: d[0], reference: ref[0].trim() || null })).then(function () { busy[1](false); }, function () { busy[1](false); }); }
    return h(Modal, { open: p.open, inline: p.inline, onClose: p.onCancel, size: 'md', autoFocus: false, dialogClassName: 'ck-pc__mdl',
      leading: h('span', { className: 'ck-adm__icon', 'aria-hidden': true }, h(Icon, { name: 'check-circle', size: 20 })),
      title: 'Confirmar pagamento', description: 'Ao confirmar, as matrículas dos beneficiários ficam ativas.',
      footer: h('div', { className: 'ck-adm__foot' },
        h(Button, { variant: 'ghost', onClick: p.onCancel }, 'Cancelar'),
        h(Button, { variant: 'primary', icon: 'check', loading: busy[0], onClick: go }, 'Confirmar pagamento')) },
      h('div', { className: 'ck-adm' },
        h(PcContractCard, { contract: c }),
        h('div', { className: 'ck-field' },
          h('span', { className: 'ck-field__label' }, 'Forma de pagamento'),
          h('div', { className: 'ck-ml__filters', role: 'radiogroup', 'aria-label': 'Forma de pagamento' }, methods.map(function (x) {
            return h('button', { key: x.id, type: 'button', role: 'radio', 'aria-checked': m[0] === String(x.id), className: 'ck-ml__chip', onClick: function () { m[1](String(x.id)); } }, h(Icon, { name: pcMethodIcon(x.nome), size: 15 }), x.nome);
          })),
          show.m ? h('span', { className: 'ck-field__error' }, h(Icon, { name: 'alert-triangle', size: 13 }), show.m) : null),
        h('div', { className: 'ck-al-grid' },
          h(TextField, { type: 'date', label: 'Pago em', value: d[0], error: show.d, max: today, onChange: function (e) { d[1](e.target.value); } }),
          h(TextField, { label: 'Comprovante ou ID', labelAside: 'opcional', mono: true, value: ref[0], placeholder: 'Ex.: MP-123456', onChange: function (e) { ref[1](e.target.value); } })),
        h('dl', { className: 'ck-pc__after' },
          h('div', null, h('dt', null, 'Vigência'), h('dd', null, c.start ? fdate(c.start) + ' a ' + fdate(c.end) : c.pkg.months ? pcMonthsTxt(c.pkg.months) : '—', !c.start && c.pkg.months ? h('small', null, ', ' + pcCycleLabel(c.pkg.months).toLowerCase()) : null)),
          h('div', null, h('dt', null, 'Matrículas ativadas'), h('dd', null, mem.length + (mem.length === 1 ? ' pessoa' : ' pessoas'), slots > mem.length ? h('small', null, ', ' + (slots - mem.length) + ' vaga' + (slots - mem.length > 1 ? 's' : '') + ' livre' + (slots - mem.length > 1 ? 's' : '')) : null))),
        mem.length < slots ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'Quem entrar depois em Beneficiários ganha matrícula até o fim da mesma vigência.') : null));
  }

  /* Liberações usam os endpoints existentes; alterações são imediatas, separadas do pacote. */
  function PacoteLiberacoesEditor(p) {
    var list = p.releases, today = isoToday();
    var [until, setUntil] = useState('');
    var [note, setNote] = useState('');
    function release(data) { Promise.resolve(p.onAdd?.(data)).catch(function () {}); }
    return h('fieldset', { className: 'ck-pc__allow', disabled: p.saving },
      h('legend', { className: 'ck-sr' }, 'Alunos liberados no app'),
      h('div', { className: 'ck-pc__allowhead' }, h('strong', null, 'Alunos liberados'),
        list ? h('span', null, list.filter(function (r) { return !r.vencida; }).length + ' com liberação válida') : null),
      p.error ? h(Callout, { tone: 'danger', action: h(Button, { size: 'sm', onClick: p.onRetry }, 'Tentar novamente') }, p.error) : null,
      list == null ? (p.error ? null : h('p', { className: 'ck-field__hint', role: 'status' }, 'Carregando liberações…')) : h(React.Fragment, null,
        h(PcPeople, { label: 'Liberar aluno', students: p.students, exclude: list.map(function (r) { return r.aluno_id; }), value: [], placeholder: 'Nome, CPF ou e-mail',
          onAdd: function (student) { release({ aluno_id: student.id, valida_ate: until || null, observacao: note.trim() || null }); } }),
        h('div', { className: 'ck-al-grid' },
          h(TextField, { label: 'Liberar até', labelAside: 'opcional', type: 'date', min: today, value: until, onChange: function (e) { setUntil(e.target.value); } }),
          h(TextField, { label: 'Observação', labelAside: 'opcional', value: note, maxLength: 255, onChange: function (e) { setNote(e.target.value); } })),
        list.length ? h('ul', { className: 'ck-pc__sel ck-pc__allowlist', 'aria-label': 'Alunos liberados' }, list.map(function (r) {
          var name = titleCase(r.nome);
          return h('li', { key: r.aluno_id, className: r.vencida ? 'is-gone' : undefined },
            h(Avatar, { name: name, size: 'sm' }),
            h('span', null, h('strong', null, name), h('small', null, r.vencida ? 'Venceu em ' + fdate(r.valida_ate) + '. Não vê mais o pacote.' : 'Liberado em ' + fdate(r.created_at)), r.observacao ? h('small', null, r.observacao) : null),
            h(ReleaseUntil, { key: String(r.valida_ate), release: r, onSave: p.onAdd, today: today }),
            h(IconButton, { icon: 'x', label: 'Tirar ' + name + ' da lista', size: 'sm', onClick: function () { Promise.resolve(p.onRemove?.(r.aluno_id)).catch(function () {}); } }));
        })) : h('div', { className: 'ck-pc__allowempty' }, h(Icon, { name: 'user-plus', size: 18 }), h('div', null, h('strong', null, 'Ninguém liberado ainda'), h('span', null, 'Enquanto a lista estiver vazia, o pacote não aparece no app para ninguém.'))),
        h('span', { className: 'ck-field__hint' }, 'Liberações são salvas imediatamente, inclusive ao cancelar a edição do pacote. Sem data, valem até você remover.')));
  }

  function ReleaseUntil(p) {
    var r = p.release, [value, setValue] = useState(r.valida_ate || '');
    return h('label', { className: 'ck-pc__until' }, h('span', null, 'Até'), h('input', { type: 'date', value: value, min: p.today,
      'aria-label': 'Liberado até, ' + titleCase(r.nome), onChange: function (e) { setValue(e.target.value); },
      onBlur: function () {
        if (value === (r.valida_ate || '')) return;
        Promise.resolve(p.onSave?.({ aluno_id: r.aluno_id, valida_ate: value || null, observacao: r.observacao }))
          .catch(function () { setValue(r.valida_ate || ''); });
      } }));
  }

  function PacoteLiberacoesModal(p) {
    return h(Modal, { open: p.open, onClose: p.onCancel, size: 'lg', dialogClassName: 'ck-pc__releases-dlg', title: 'Alunos liberados no app',
      description: p.package?.name + '. Quem está aqui pode contratar e pagar pelo app quando a venda é “Só liberados”.',
      footer: h(Button, { onClick: p.onCancel }, 'Fechar') },
      p.package?.sale !== 'liberados' ? h(Callout, { tone: 'warning' }, 'A lista fica guardada, mas só vale quando a venda está em “Só liberados”.') : null,
      h(PacoteLiberacoesEditor, p));
  }

export { PacotesLista, PacoteModal, ContratarPacoteModal, PacoteBeneficiariosModal, ConfirmarPagamentoPacoteModal, PacoteLiberacoesModal, pcCycleLabel };
