/* AppCheckin — Alunos, Login e Academias: AlunosLista, AlunoDetalhe, AlunoForm, LoginScreen e AcademiasLista.
   Portado de project/components/bundle.js do design system. Estilos: src/theme/ck-pessoas.css. Primitivas de ./index.
   Extensões locais: LoginScreen `defaultPassword`; sexo com os valores da API (feminino, masculino…); AlunoDetalhe com
   `checkinsHeader`/`checkinsEmpty` na aba de check-ins e `onCancel` em cada lançamento de crédito; AlunoForm com
   `requireCpf` e busca por CPF que devolve `alreadyHere` (aviso + `onOpenExisting`); AcademiasLista diz "Desativar"
   (a API só tem ativa/inativa). */
import React from 'react';
import {
  ActionMenu, Avatar, Badge, Button, Callout, Icon, IconButton, PageHeader, SelectField, Switch, Tabs, TextField,
} from './index';

  var h = React.createElement;
  var useState = React.useState, useRef = React.useRef;

  function cx() { return Array.prototype.filter.call(arguments, Boolean).join(' '); }
  function useControlled(value, def, onChange) {
    var s = useState(def), isC = value !== undefined;
    return [isC ? value : s[0], function (v) { if (!isC) s[1](v); if (onChange) onChange(v); }];
  }
  var BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  function money(v) { return BRL.format(v || 0); }
  function num(v) { return new Intl.NumberFormat('pt-BR').format(v || 0); }
  function pd(iso) { if (!iso) return null; var a = String(iso).split('T')[0].split('-'); return new Date(+a[0], +a[1] - 1, +a[2]); }
  function fdate(iso) { if (!iso) return '—'; var a = String(iso).split('T')[0].split('-'); return a[2] + '/' + a[1] + '/' + a[0]; }
  function fdatetime(iso) { if (!iso) return '—'; var d = String(iso).replace(' ', 'T').split('T'); return fdate(d[0]) + (d[1] ? ' às ' + d[1].slice(0, 5) : ''); }
  function dayDiff(a, b) { return Math.round((pd(b) - pd(a)) / 86400000); }
  function initialsOf(name) {
    if (!name) return '';
    var w = String(name).trim().split(/\s+/);
    return ((w[0] || '')[0] + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase();
  }
  function titleCase(n) {
    if (!n) return n;
    var low = ['da', 'de', 'do', 'das', 'dos', 'e'];
    return String(n).toLowerCase().split(/\s+/).map(function (w, i) { return i > 0 && low.indexOf(w) >= 0 ? w : w.charAt(0).toUpperCase() + w.slice(1); }).join(' ');
  }
  var LIST_STATUS = {
    ativa: ['success', 'Ativa', 'Ativas'], pendente: ['warning', 'Pendente', 'Pendentes'], vencida: ['danger', 'Vencida', 'Vencidas'],
    cancelada: ['neutral', 'Cancelada', 'Canceladas'], finalizada: ['outline', 'Finalizada', 'Finalizadas'], bloqueada: ['outline', 'Bloqueada', 'Bloqueadas']
  };

  function StatTile(p) {
    var d = p.delta;
    var tone = d ? (d.value === 0 ? 'flat' : ((d.value > 0) === (d.goodWhenUp !== false) ? 'good' : 'bad')) : null;
    return h('div', { className: cx('ck-stat', p.featured && 'is-featured', p.className) },
      h('div', { className: 'ck-stat__top' },
        h('span', { className: 'ck-stat__label' }, p.label),
        p.icon ? h('span', { className: 'ck-stat__icon', 'aria-hidden': true }, h(Icon, { name: p.icon, size: 16 })) : null),
      h('div', { className: 'ck-stat__row' },
        h('strong', { className: 'ck-stat__value' }, p.value)),
      d || p.note ? h('div', { className: 'ck-stat__foot' },
        d ? h('span', { className: 'ck-stat__delta ck-stat__delta--' + tone },
          d.value !== 0 ? h(Icon, { name: d.value > 0 ? 'trending-up' : 'trending-down', size: 14, strokeWidth: 2 }) : null,
          d.label) : null,
        d && d.vs ? h('span', { className: 'ck-stat__vs' }, d.vs) : null,
        p.note ? h('span', { className: 'ck-stat__vs' }, p.note) : null) : null);
  }


  function Panel(p) {
    return h('section', { className: cx('ck-card ck-panel', p.className), 'aria-label': typeof p.title === 'string' ? p.title : undefined },
      h('header', { className: 'ck-panel__head' },
        h('div', { style: { minWidth: 0 } }, h('h2', { className: 'ck-panel__title' }, p.title), p.description ? h('p', { className: 'ck-panel__desc' }, p.description) : null),
        p.actions ? h('div', { className: 'ck-panel__actions' }, p.actions) : null),
      h('div', { className: 'ck-panel__body' }, p.children));
  }

  /* ---------- Alunos: helpers ---------- */
  function digits(v) { return String(v || '').replace(/\D/g, ''); }
  function maskCpf(v) { var d = digits(v).slice(0, 11); return d.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2'); }
  function maskPhone(v) {
    var d = digits(v).slice(0, 11); if (!d) return ''; if (d.length < 3) return '(' + d;
    var r = d.slice(2), cut = d.length > 10 ? 5 : 4;
    return '(' + d.slice(0, 2) + ') ' + (r.length > cut ? r.slice(0, cut) + '-' + r.slice(cut) : r);
  }
  function maskCep(v) { var d = digits(v).slice(0, 8); return d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d; }
  function maskDate(v) { var d = digits(v).slice(0, 8); return d.length > 4 ? d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4) : d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d; }
  function brToIso(br) { var m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(br || ''); if (!m) return null; var d = new Date(+m[3], +m[2] - 1, +m[1]); if (d.getDate() !== +m[1] || d.getMonth() !== +m[2] - 1) return null; return m[3] + '-' + m[2] + '-' + m[1]; }
  function ageOn(iso, today) {
    if (!iso) return null; var b = pd(iso), t = today ? pd(today) : new Date();
    var a = t.getFullYear() - b.getFullYear(); if (t.getMonth() < b.getMonth() || (t.getMonth() === b.getMonth() && t.getDate() < b.getDate())) a--;
    return a >= 0 && a < 120 ? a : null;
  }
  function firstName(n) { return titleCase(String(n || '').trim().split(/\s+/)[0] || ''); }
  function ago(days) { return days <= 0 ? 'hoje' : days === 1 ? 'ontem' : 'há ' + days + ' dias'; }
  var STU_PAY = { em_dia: ['success', 'Em dia', 'Em dia'], pendente: ['warning', 'Pagamento pendente', 'Pendente'], atrasado: ['danger', 'Pagamento em atraso', 'Em atraso'] };
  var GENDERS = [{ value: 'feminino', label: 'Feminino' }, { value: 'masculino', label: 'Masculino' }, { value: 'outro', label: 'Outro' }, { value: 'prefiro_nao_informar', label: 'Prefere não informar' }];
  var UFS = [['AC', 'Acre'], ['AL', 'Alagoas'], ['AP', 'Amapá'], ['AM', 'Amazonas'], ['BA', 'Bahia'], ['CE', 'Ceará'], ['DF', 'Distrito Federal'], ['ES', 'Espírito Santo'], ['GO', 'Goiás'], ['MA', 'Maranhão'], ['MT', 'Mato Grosso'], ['MS', 'Mato Grosso do Sul'], ['MG', 'Minas Gerais'], ['PA', 'Pará'], ['PB', 'Paraíba'], ['PR', 'Paraná'], ['PE', 'Pernambuco'], ['PI', 'Piauí'], ['RJ', 'Rio de Janeiro'], ['RN', 'Rio Grande do Norte'], ['RS', 'Rio Grande do Sul'], ['RO', 'Rondônia'], ['RR', 'Roraima'], ['SC', 'Santa Catarina'], ['SP', 'São Paulo'], ['SE', 'Sergipe'], ['TO', 'Tocantins']];
  function Missing(p) { return h('span', { className: 'ck-al__missing' }, (p && p.children) || 'Não informado'); }
  function BackLink(p) { return p.onClick ? h('button', { type: 'button', className: 'ck-mt-back', onClick: p.onClick }, h(Icon, { name: 'arrow-left', size: 16 }), p.children) : null; }

  /* ---------- AlunosLista ---------- */
  var STU_FILTERS = [['todos', 'Todos'], ['ativos', 'Ativos', 'success'], ['pendentes', 'Pagamento pendente', 'warning'], ['sem_plano', 'Sem plano', 'outline'], ['inativos', 'Inativos', 'neutral']];
  function stuMatch(r, f) {
    if (f === 'ativos') return r.status !== 'inativo';
    if (f === 'inativos') return r.status === 'inativo';
    if (f === 'sem_plano') return !r.plan;
    if (f === 'pendentes') return r.payment === 'pendente' || r.payment === 'atrasado';
    return true;
  }
  function AlunosLista(p) {
    var qs = useControlled(p.query, '', p.onQueryChange), q = qs[0];
    var fs = useControlled(p.filter, 'todos', p.onFilterChange), f = fs[0];
    var counts = p.counts || {};
    var rows = (p.rows || []).filter(function (r) {
      if (p.onFilterChange == null && !stuMatch(r, f)) return false;
      if (p.onQueryChange == null && q) {
        var t = (r.name + ' ' + (r.email || '')).toLowerCase(), dq = digits(q);
        return t.indexOf(q.toLowerCase()) >= 0 || (dq.length >= 3 && (digits(r.cpf) + ' ' + digits(r.phone)).indexOf(dq) >= 0);
      }
      return true;
    });
    var page = p.page || 1, size = p.pageSize || rows.length || 1;
    var total = p.total != null ? p.total : rows.length;
    var pages = Math.max(1, Math.ceil(total / size));
    var from = total ? (page - 1) * size + 1 : 0, to = Math.min(total, (page - 1) * size + rows.length);
    function open(r) { p.onOpen && p.onOpen(r); }
    return h('div', { className: 'ck-ml ck-al' },
      h(PageHeader, { title: 'Alunos', description: 'Quem treina na academia, com contato, plano atual e situação do pagamento.',
        actions: h(React.Fragment, null,
          p.onExport ? h(Button, { icon: 'download', onClick: p.onExport }, 'Exportar') : null,
          h(Button, { variant: 'primary', icon: 'user-plus', onClick: p.onNew }, 'Novo aluno')) }),
      h('section', { className: 'ck-card ck-ml__panel', 'aria-label': 'Lista de alunos' },
        h('div', { className: 'ck-ml__toolbar' },
          h('div', { className: 'ck-control ck-ml__search' },
            h(Icon, { name: 'search', size: 18, className: 'ck-control__icon' }),
            h('input', { type: 'search', className: 'ck-control__input', value: q, placeholder: 'Buscar por nome, e-mail, CPF ou telefone', 'aria-label': 'Buscar alunos', onChange: function (e) { qs[1](e.target.value); } }),
            q ? h(IconButton, { icon: 'x', label: 'Limpar busca', size: 'sm', onClick: function () { qs[1](''); } }) : null),
          h('div', { className: 'ck-ml__filters', role: 'radiogroup', 'aria-label': 'Filtrar alunos' }, STU_FILTERS.map(function (x) {
            var on = f === x[0];
            return h('button', { key: x[0], type: 'button', role: 'radio', 'aria-checked': on, className: 'ck-ml__chip', onClick: function () { fs[1](x[0]); } },
              x[2] ? h('span', { className: 'ck-ml__chipdot ck-ml__chipdot--' + (x[2] === 'neutral' ? 'outline' : x[2]), 'aria-hidden': true }) : null,
              x[1], counts[x[0]] != null ? h('span', { className: 'ck-ml__count' }, counts[x[0]]) : null);
          }))),
        rows.length ? h('div', { className: 'ck-mt-table-wrap' },
          h('table', { className: 'ck-mt-table ck-ml__table ck-al__table' },
            h('thead', null, h('tr', null,
              h('th', { scope: 'col' }, 'Aluno'), h('th', { scope: 'col' }, 'Contato'), h('th', { scope: 'col' }, 'Plano atual'),
              h('th', { scope: 'col' }, 'Situação'), h('th', { scope: 'col' }, h('span', { className: 'ck-sr' }, 'Ações')))),
            h('tbody', null, rows.map(function (r) {
              var name = titleCase(r.name), pay = r.plan ? STU_PAY[r.payment] : null, off = r.status === 'inativo';
              return h('tr', { key: r.id, className: cx('ck-ml__row', off && 'is-off'), onClick: function (e) { if (!e.target.closest('button, a')) open(r); } },
                h('td', null, h('div', { className: 'ck-ml__who' },
                  h(Avatar, { name: name, size: 'sm', decorative: true }),
                  h('span', { style: { minWidth: 0 } },
                    h('a', { href: r.href || '#', className: 'ck-ml__name', onClick: function (e) { if (p.onOpen) { e.preventDefault(); open(r); } } }, name),
                    h('span', { className: 'ck-mt-cell__sub' }, 'Aluno #' + r.id)))),
                h('td', null,
                  r.email ? h('span', { className: 'ck-mt-cell__main ck-al__contact' }, r.email) : h(Missing, null, 'Sem e-mail'),
                  h('span', { className: 'ck-mt-cell__sub' }, r.phone || 'Sem telefone')),
                h('td', null, r.plan ? h('div', { className: 'ck-ml__plan' },
                  h('span', { className: 'ck-ml__mod', 'aria-hidden': true }, h(Icon, { name: r.plan.modalityIcon || 'dumbbell', size: 16 })),
                  h('span', null, h('span', { className: 'ck-mt-cell__main' }, r.plan.name), h('span', { className: 'ck-mt-cell__sub' }, (r.plan.modality ? r.plan.modality + ', ' : '') + money(r.plan.amount))))
                  : h('div', { className: 'ck-al__noplan' }, h(Missing, null, 'Sem plano'),
                    p.onEnroll && !off ? h('button', { type: 'button', className: 'ck-linkbtn', onClick: function () { p.onEnroll(r); } }, 'Matricular') : null)),
                h('td', null, off ? h(Badge, { tone: 'outline' }, 'Inativo') : pay ? h(Badge, { tone: pay[0], dot: true }, pay[2]) : h(Badge, { tone: 'neutral', dot: true }, 'Ativo')),
                h('td', { className: 'ck-mt-actions' },
                  p.onEdit ? h(IconButton, { icon: 'pencil', label: 'Editar ' + name, size: 'sm', onClick: function () { p.onEdit(r); } }) : null,
                  h(ActionMenu, { label: 'Mais ações para ' + name, items: [
                    { label: 'Ver detalhes', icon: 'arrow-right', onClick: function () { open(r); } },
                    p.onEnroll && !off ? { label: 'Nova matrícula', icon: 'plus', onClick: function () { p.onEnroll(r); } } : null,
                    p.onToggleActive ? '-' : null,
                    p.onToggleActive ? (off ? { label: 'Reativar aluno', icon: 'power', onClick: function () { p.onToggleActive(r); } } : { label: 'Desativar aluno', icon: 'ban', danger: true, onClick: function () { p.onToggleActive(r); } }) : null].filter(Boolean) })));
            })))) :
          h('div', { className: 'ck-ml__empty' },
            h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: q ? 'search' : 'users', size: 20 })),
            h('strong', null, q ? 'Nenhum aluno encontrado para "' + q + '"' : 'Nenhum aluno neste filtro'),
            h('p', null, q ? 'Busque pelo CPF ou pelo telefone, só com números.' : 'Escolha outro filtro ou cadastre um aluno.'),
            q ? h(Button, { size: 'sm', onClick: function () { qs[1](''); } }, 'Limpar busca') : h(Button, { size: 'sm', icon: 'user-plus', onClick: p.onNew }, 'Novo aluno')),
        h('footer', { className: 'ck-ml__foot' },
          h('span', null, total ? 'Mostrando ' + from + ' a ' + to + ' de ' + total : 'Nenhum resultado'),
          pages > 1 ? h('nav', { className: 'ck-ml__pager', 'aria-label': 'Páginas' },
            h(IconButton, { icon: 'chevron-left', label: 'Página anterior', size: 'sm', variant: 'secondary', disabled: page <= 1, onClick: function () { p.onPageChange && p.onPageChange(page - 1); } }),
            h('span', { className: 'ck-ml__pageinfo' }, 'Página ' + page + ' de ' + pages),
            h(IconButton, { icon: 'chevron-right', label: 'Próxima página', size: 'sm', variant: 'secondary', disabled: page >= pages, onClick: function () { p.onPageChange && p.onPageChange(page + 1); } })) : null)));
  }

  /* ---------- AlunoDetalhe ---------- */
  function AlDl(p) {
    return h('dl', { className: cx('ck-al__dl', p.className) }, p.items.filter(Boolean).map(function (it) {
      var v = it[1];
      return h('div', { key: it[0], className: it[3] ? 'is-wide' : undefined },
        h('dt', null, it[2] ? h(Icon, { name: it[2], size: 15 }) : null, it[0]),
        h('dd', null, v == null || v === '' ? h(Missing) : v));
    }));
  }
  function AlPlanCard(p) {
    var e = p.enrollment;
    if (!e) return h('section', { className: 'ck-card ck-al-noplan', 'aria-label': 'Plano atual' },
      h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: 'receipt', size: 20 })),
      h('strong', null, 'Sem plano ativo'),
      h('p', null, 'Sem matrícula, o aluno não consegue fazer check-in.'),
      p.onEnroll ? h(Button, { variant: 'primary', icon: 'plus', onClick: p.onEnroll }, 'Nova matrícula') : null);
    var pay = STU_PAY[e.payment];
    var left = p.today && e.accessUntil ? dayDiff(p.today, e.accessUntil) : null;
    return h('section', { className: 'ck-mt-plan ck-al-plan', 'aria-label': 'Plano atual' },
      h('div', { className: 'ck-mt-plan__top' },
        h('span', { className: 'ck-mt-plan__icon', 'aria-hidden': true }, h(Icon, { name: e.modalityIcon || 'dumbbell', size: 20, strokeWidth: 2 })),
        h('div', { style: { flex: 1, minWidth: 0 } },
          h('span', { className: 'ck-al-plan__eyebrow' }, 'Plano atual'),
          h('h2', { className: 'ck-mt-plan__name' }, e.plan),
          h('span', { className: 'ck-mt-plan__freq' }, e.modality))),
      h('div', { className: 'ck-mt-plan__price' },
        h('span', { className: 'ck-mt-plan__value' }, money(e.amount)),
        e.cycle ? h('span', { className: 'ck-mt-plan__per' }, e.cycle) : null),
      h('dl', { className: 'ck-mt-plan__facts' },
        h('div', null, h('dt', null, 'Matrícula'), h('dd', null, '#' + e.id)),
        h('div', null, h('dt', null, 'Acesso até'), h('dd', null, fdate(e.accessUntil),
          left != null ? h('span', { className: cx('ck-al-plan__left', left < 0 && 'is-late') }, left < 0 ? 'Venceu há ' + (-left) + (left === -1 ? ' dia' : ' dias') : left === 0 ? 'Vence hoje' : 'Faltam ' + left + (left === 1 ? ' dia' : ' dias')) : null))),
      h('div', { className: 'ck-al-plan__foot' },
        pay ? h('span', { className: 'ck-al-plan__pay ck-al-plan__pay--' + pay[0] }, h('span', { className: 'ck-al-plan__dot', 'aria-hidden': true }), pay[1]) : h('span'),
        p.onOpenEnrollment ? h('button', { type: 'button', className: 'ck-al-plan__link', onClick: function () { p.onOpenEnrollment(e); } }, 'Abrir matrícula', h(Icon, { name: 'arrow-right', size: 15 })) : null));
  }
  function AlCheckinCard(p) {
    var c = p.checkins || {};
    var lastDay = c.last && p.today ? dayDiff(String(c.last).split('T')[0], p.today) : null;
    var idle = lastDay != null && lastDay > (p.idleDays || 14);
    return h('section', { className: 'ck-card ck-al-ci', 'aria-label': 'Frequência' },
      h('div', { className: 'ck-al-ci__stat' },
        h('span', { className: 'ck-al-ci__label' }, h(Icon, { name: 'check-circle', size: 15 }), 'Check-ins'),
        h('strong', { className: 'ck-al-ci__value' }, c.total != null ? c.total : 0),
        h('span', { className: 'ck-al-ci__sub' }, c.month != null ? c.month + ' neste mês' : 'desde o cadastro')),
      h('div', { className: 'ck-al-ci__stat' },
        h('span', { className: 'ck-al-ci__label' }, h(Icon, { name: 'clock', size: 15 }), 'Último check-in'),
        h('strong', { className: 'ck-al-ci__value ck-al-ci__value--sm' }, c.last ? fdate(String(c.last).split('T')[0]) : 'Nenhum ainda'),
        c.last && String(c.last).indexOf('T') > 0 ? h('span', { className: 'ck-al-ci__sub' }, 'às ' + String(c.last).split('T')[1].slice(0, 5) + (idle || lastDay == null ? '' : ', ' + ago(lastDay))) : null,
        idle ? h('span', { className: 'ck-al-ci__sub is-warn' }, h(Icon, { name: 'alert-triangle', size: 13 }), 'Sem treinar há ' + lastDay + ' dias') : null));
  }
  function AlunoDetalhe(p) {
    var s = p.student || {}, a = s.address || {};
    var name = titleCase(s.name);
    var e = p.enrollment;
    var hist = p.history || [], cks = (p.checkins && p.checkins.recent) || [], cr = p.credits || {};
    var tab = useControlled(p.tab, p.defaultTab || 'geral', p.onTabChange);
    var pay = e ? STU_PAY[e.payment] : null;
    var age = ageOn(s.birthDate, p.today);
    var missingAddr = ['cep', 'street', 'number', 'district', 'city'].filter(function (k) { return !a[k]; });
    var addrLine = [a.street ? a.street + (a.number ? ', ' + a.number : '') : null, a.complement, a.district].filter(Boolean).join(', ');
    function count(n) { return n ? h('span', { className: 'ck-tabcount' }, n) : null; }
    var tabs = [
      { value: 'geral', label: 'Visão geral' },
      { value: 'historico', label: h(React.Fragment, null, 'Histórico de planos', count(hist.length)) },
      { value: 'creditos', label: 'Créditos' },
      { value: 'checkins', label: h(React.Fragment, null, 'Check-ins', count(p.checkins && p.checkins.total)) }];
    var body;
    if (tab[0] === 'historico') {
      body = hist.length ? h('section', { className: 'ck-card ck-al-tabcard' }, h('div', { className: 'ck-mt-table-wrap' }, h('table', { className: 'ck-mt-table' },
        h('thead', null, h('tr', null, h('th', { scope: 'col' }, 'Plano'), h('th', { scope: 'col' }, 'Período'), h('th', { scope: 'col', className: 'is-num' }, 'Valor'), h('th', { scope: 'col' }, 'Situação'))),
        h('tbody', null, hist.map(function (x) {
          var st = LIST_STATUS[x.status] || LIST_STATUS.finalizada;
          return h('tr', { key: x.id, className: p.onOpenEnrollment ? 'ck-ml__row' : undefined, onClick: function () { p.onOpenEnrollment && p.onOpenEnrollment(x); } },
            h('td', null, h('div', { className: 'ck-ml__plan' }, h('span', { className: 'ck-ml__mod', 'aria-hidden': true }, h(Icon, { name: x.modalityIcon || 'dumbbell', size: 16 })),
              h('span', null, h('span', { className: 'ck-mt-cell__main' }, x.plan), h('span', { className: 'ck-mt-cell__sub' }, x.modality + ', matrícula #' + x.id)))),
            h('td', null, h('span', { className: 'ck-mt-cell__main' }, fdate(x.start) + ' a ' + fdate(x.end))),
            h('td', { className: 'is-num' }, h('span', { className: 'ck-mt-cell__main' }, money(x.amount)), x.cycle ? h('span', { className: 'ck-mt-cell__sub' }, x.cycle) : null),
            h('td', null, h(Badge, { tone: st[0], dot: st[0] !== 'outline' }, st[1])));
        }))))) : h('div', { className: 'ck-card ck-ml__empty' }, h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: 'receipt', size: 20 })), h('strong', null, 'Nenhum plano ainda'), h('p', null, 'As matrículas do aluno aparecem aqui, da mais recente para a mais antiga.'));
    } else if (tab[0] === 'creditos') {
      var unit = cr.unit === 'aulas' ? function (v) { return Math.abs(v) + (Math.abs(v) === 1 ? ' aula' : ' aulas'); } : function (v) { return money(Math.abs(v)); };
      body = h('div', { className: 'ck-al-credit' },
        h('section', { className: 'ck-card ck-al-credit__bal' },
          h('span', { className: 'ck-al-ci__label' }, h(Icon, { name: 'wallet', size: 15 }), 'Saldo de créditos'),
          h('strong', { className: 'ck-al-ci__value' }, unit(cr.balance || 0)),
          h('span', { className: 'ck-al-ci__sub' }, cr.note || 'Créditos abatem cobranças futuras do aluno.'),
          p.onAddCredit ? h(Button, { icon: 'plus', onClick: p.onAddCredit }, 'Lançar crédito') : null),
        (cr.entries || []).length ? h('section', { className: 'ck-card ck-al-tabcard' }, h('ul', { className: 'ck-al-feed' }, cr.entries.map(function (x, i) {
          return h('li', { key: i },
            h('span', { className: cx('ck-al-feed__icon', x.amount < 0 && 'is-out'), 'aria-hidden': true }, h(Icon, { name: x.amount < 0 ? 'trending-down' : 'trending-up', size: 16 })),
            h('span', { className: 'ck-al-feed__main' }, h('strong', null, x.desc), h('span', null, fdate(x.date))),
            h('span', { className: cx('ck-al-feed__amt', x.amount < 0 && 'is-out') }, (x.amount < 0 ? '−' : '+') + unit(x.amount)),
            x.onCancel ? h(IconButton, { icon: 'x', label: 'Cancelar crédito', size: 'sm', onClick: x.onCancel }) : null);
        }))) : h('div', { className: 'ck-card ck-ml__empty' }, h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: 'wallet', size: 20 })), h('strong', null, 'Nenhum crédito lançado'), h('p', null, 'Reposições, bônus e trocas de plano com saldo aparecem aqui.')));
    } else if (tab[0] === 'checkins') {
      body = h('div', { className: 'ck-al-tabgrid' },
        h(AlCheckinCard, { checkins: p.checkins, today: p.today }),
        cks.length ? h('section', { className: 'ck-card ck-al-tabcard' }, p.checkinsHeader || null, h('ul', { className: 'ck-al-feed' }, cks.map(function (x, i) {
          return h('li', { key: i },
            h('span', { className: 'ck-al-feed__icon', 'aria-hidden': true }, h(Icon, { name: x.modalityIcon || 'check-circle', size: 16 })),
            h('span', { className: 'ck-al-feed__main' }, h('strong', null, x.modality || 'Check-in'), h('span', null, ({ app: 'Pelo app', recepcao: 'Pela recepção', catraca: 'Pela catraca' })[x.by || 'app'] || x.by)),
            h('span', { className: 'ck-al-feed__time' }, fdatetime(x.at)));
        }))) : h('div', { className: 'ck-card ck-ml__empty' }, p.checkinsHeader || null, h('strong', null, p.checkinsEmpty || 'Nenhum check-in ainda')));
    } else {
      body = h('div', { className: 'ck-al-overview' },
        h('div', { className: 'ck-al-overview__side' },
          h(AlPlanCard, { enrollment: e, today: p.today, onEnroll: p.onEnroll, onOpenEnrollment: p.onOpenEnrollment }),
          h(AlCheckinCard, { checkins: p.checkins, today: p.today })),
        h('div', { className: 'ck-al-overview__main' },
          h(Panel, { title: 'Dados pessoais', actions: p.onEdit ? h(Button, { size: 'sm', variant: 'ghost', icon: 'pencil', onClick: p.onEdit }, 'Editar') : null },
            h(AlDl, { items: [
              ['CPF', s.cpf, 'clipboard'], ['Nascimento', s.birthDate ? fdate(s.birthDate) + (age != null ? ', ' + age + ' anos' : '') : null, 'calendar'],
              ['Telefone', s.phone, 'phone'], ['E-mail', s.email, 'mail'],
              ['Sexo', (GENDERS.filter(function (g) { return g.value === s.gender; })[0] || {}).label, 'user'], ['ID do usuário', s.userId != null ? '#' + s.userId : null, 'key']] })),
          h(Panel, { title: 'Endereço', actions: p.onEdit && missingAddr.length ? h(Button, { size: 'sm', variant: 'ghost', icon: 'pencil', onClick: p.onEdit }, 'Completar') : null },
            missingAddr.length >= 4 && (a.city || a.uf) ? h('div', { className: 'ck-al-addr' },
              h('span', { className: 'ck-al-addr__icon', 'aria-hidden': true }, h(Icon, { name: 'map-pin', size: 18 })),
              h('div', null, h('strong', null, [titleCase(a.city), a.uf].filter(Boolean).join(', ')), h('span', null, 'Só a cidade foi informada. Faltam CEP, rua, número e bairro.')))
              : missingAddr.length >= 4 ? h('div', { className: 'ck-al-addr' },
                h('span', { className: 'ck-al-addr__icon', 'aria-hidden': true }, h(Icon, { name: 'map-pin', size: 18 })),
                h('div', null, h('strong', null, 'Endereço não informado'), h('span', null, 'Opcional, mas necessário para emitir boleto.')))
              : h(AlDl, { items: [['Endereço', addrLine, 'map-pin', true], ['Cidade', [titleCase(a.city), a.uf].filter(Boolean).join(', ')], ['CEP', a.cep]] })),
          h('p', { className: 'ck-al-stamp' }, h(Icon, { name: 'clock', size: 14 }), 'Cadastrado em ' + fdatetime(s.createdAt) + (s.updatedAt && s.updatedAt !== s.createdAt ? '. Atualizado em ' + fdatetime(s.updatedAt) + '.' : '. Nenhuma alteração desde então.'))));
    }
    return h('div', { className: 'ck-mt ck-al-detail' },
      h('div', { className: 'ck-mt-hero' },
        h(BackLink, { onClick: p.onBack }, 'Alunos'),
        h('div', { className: 'ck-mt-hero__row' },
          h(Avatar, { name: name, src: s.avatar, size: 'lg', className: 'ck-mt-hero__avatar', decorative: true }),
          h('div', { className: 'ck-mt-hero__text' },
            h('div', { className: 'ck-mt-hero__titleline' },
              h('h1', { className: 'ck-mt-hero__name' }, name),
              s.status === 'inativo' ? h(Badge, { tone: 'outline' }, 'Inativo') : h(Badge, { tone: 'success', dot: true }, 'Ativo'),
              pay && e.payment !== 'em_dia' ? h(Badge, { tone: pay[0], dot: true }, pay[1]) : null),
            h('ul', { className: 'ck-mt-hero__meta' },
              s.email ? h('li', null, h(Icon, { name: 'mail', size: 15 }), s.email) : null,
              s.phone ? h('li', null, h(Icon, { name: 'phone', size: 15 }), s.phone) : null,
              h('li', null, h(Icon, { name: 'user', size: 15 }), 'Aluno #' + s.id))),
          h('div', { className: 'ck-mt-hero__actions' },
            p.onEdit ? h(Button, { variant: 'secondary', icon: 'pencil', onClick: p.onEdit }, 'Editar dados') : null,
            !e && p.onEnroll ? h(Button, { variant: 'primary', icon: 'plus', onClick: p.onEnroll }, 'Nova matrícula') : null,
            p.onToggleActive || p.onResetPassword ? h(ActionMenu, { label: 'Mais ações', variant: 'secondary', size: 'md', items: [
              p.onResetPassword ? { label: 'Enviar nova senha', icon: 'key', onClick: p.onResetPassword } : null,
              p.onToggleActive ? '-' : null,
              p.onToggleActive ? (s.status === 'inativo' ? { label: 'Reativar aluno', icon: 'power', onClick: p.onToggleActive } : { label: 'Desativar aluno', icon: 'ban', danger: true, onClick: p.onToggleActive }) : null].filter(Boolean) }) : null))),
      h(Tabs, { label: 'Seções do aluno', tabs: tabs, value: tab[0], onChange: tab[1], className: 'ck-al-tabs' }),
      h('div', { role: 'tabpanel' }, body));
  }

  /* ---------- AlunoForm (novo e editar) ---------- */
  function AlSection(p) {
    return h('section', { className: 'ck-al-sec', 'aria-labelledby': p.id },
      h('header', { className: 'ck-al-sec__head' },
        h('span', { className: cx('ck-al-sec__icon', p.done && 'is-done'), 'aria-hidden': true }, h(Icon, { name: p.done ? 'check' : p.icon, size: 17, strokeWidth: p.done ? 2.5 : 1.75 })),
        h('div', null, h('h2', { className: 'ck-al-sec__title', id: p.id }, p.title), p.description ? h('p', { className: 'ck-al-sec__desc' }, p.description) : null),
        p.aside || null),
      h('div', { className: 'ck-al-sec__body' }, p.children));
  }
  function fromStudent(s) {
    s = s || {}; var a = s.address || {};
    return { name: s.name ? titleCase(s.name) : '', cpf: maskCpf(s.cpf), birth: s.birthDate ? fdate(s.birthDate) : '', gender: s.gender || '', email: s.email || '', phone: maskPhone(s.phone),
      cep: maskCep(a.cep), street: a.street || '', number: a.number || '', complement: a.complement || '', district: a.district || '', city: a.city ? titleCase(a.city) : '', uf: a.uf || '',
      password: '', active: s.status !== 'inativo' };
  }
  function AlunoForm(p) {
    var edit = p.mode === 'editar';
    var init = useState(function () { return fromStudent(p.student); })[0];
    var fm = useState(init), f = fm[0];
    var tried = useState(false), sh = useState(false), pwOpen = useState(!edit);
    var lk = useState({ state: 'idle' }), linked = useState(null);
    var cp = useState({ state: 'idle' });
    var rootRef = useRef(null);
    function set(k, v) { var n = Object.assign({}, f); n[k] = v; fm[1](n); }
    function setMany(o) { fm[1](Object.assign({}, f, o)); }
    var birthIso = brToIso(f.birth);
    var err = {};
    if (!f.name.trim() || f.name.trim().split(/\s+/).length < 2) err.name = f.name.trim() ? 'Escreva nome e sobrenome.' : 'Informe o nome completo.';
    if (!f.email.trim()) err.email = 'Informe o e-mail. O aluno usa ele para entrar no app.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) err.email = 'Confira o e-mail. Falta algo como @ ou .com.';
    if (f.cpf && digits(f.cpf).length !== 11) err.cpf = 'O CPF tem 11 números.';
    else if (!f.cpf && !edit && p.requireCpf) err.cpf = 'Informe o CPF. Ele identifica o aluno em qualquer academia.';
    if (f.birth && !birthIso) err.birth = 'Use o formato DD/MM/AAAA.';
    if (f.phone && digits(f.phone).length < 10) err.phone = 'Inclua o DDD.';
    if (!linked[0] && pwOpen[0] && (!edit || f.password) && f.password.length < 6) err.password = edit ? 'A nova senha precisa de pelo menos 6 caracteres.' : 'Crie uma senha com pelo menos 6 caracteres.';
    var show = tried[0] ? err : {};
    var dirty = JSON.stringify(f) !== JSON.stringify(init) || !!linked[0];
    var age = ageOn(birthIso, p.today);
    var steps = [
      ['Identificação', !err.name && !err.cpf && !err.birth && lk[0].state !== 'here'],
      ['Contato', !err.email && !err.phone],
      ['Endereço', !!(f.cep && f.street && f.number), 'Opcional'],
      ['Acesso ao app', linked[0] ? true : edit ? !err.password : !err.password]];

    function lookup() {
      if (digits(f.cpf).length !== 11 || !p.onLookupCpf) return;
      lk[1]({ state: 'loading' });
      Promise.resolve(p.onLookupCpf(digits(f.cpf))).then(function (r) { lk[1](r ? { state: r.alreadyHere ? 'here' : 'found', student: r } : { state: 'none' }); }, function () { lk[1]({ state: 'error' }); });
    }
    function useFound() {
      var s = lk[0].student, n = fromStudent(s);
      linked[1](s);
      setMany({ name: n.name, email: n.email, phone: n.phone || f.phone, birth: n.birth || f.birth, gender: n.gender || f.gender, cpf: n.cpf || f.cpf });
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
      }
    }
    function submit(e) {
      if (e) e.preventDefault();
      tried[1](true);
      if (Object.keys(err).length) {
        setTimeout(function () { var el = rootRef.current && rootRef.current.querySelector('[aria-invalid="true"]'); if (el) el.focus(); }, 0);
        return;
      }
      p.onSave && p.onSave({ name: f.name.trim(), cpf: digits(f.cpf) || null, birthDate: birthIso, gender: f.gender || null, email: f.email.trim().toLowerCase(), phone: digits(f.phone) || null,
        address: { cep: digits(f.cep) || null, street: f.street || null, number: f.number || null, complement: f.complement || null, district: f.district || null, city: f.city || null, uf: f.uf || null },
        password: !linked[0] && f.password ? f.password : null, status: f.active ? 'ativo' : 'inativo', linkedUserId: linked[0] ? linked[0].userId : null });
    }

    var lkState = lk[0].state;
    var cpfBlock = !edit ? h(AlSection, { id: 'al-s0', icon: 'search', title: 'Comece pelo CPF', description: 'Se o aluno já usa o Check-in em outra academia, você aproveita o cadastro dele em vez de criar outro.' },
      h('div', { className: 'ck-al-cpf' },
        h(TextField, { label: 'CPF', value: f.cpf, inputMode: 'numeric', placeholder: '000.000.000-00', icon: 'clipboard', error: show.cpf, readOnly: !!linked[0],
          onChange: function (e) { set('cpf', maskCpf(e.target.value)); if (lkState !== 'idle') lk[1]({ state: 'idle' }); },
          onKeyDown: function (e) { if (e.key === 'Enter') { e.preventDefault(); lookup(); } } }),
        h(Button, { icon: 'search', loading: lkState === 'loading', disabled: digits(f.cpf).length !== 11 || !!linked[0], onClick: lookup }, lkState === 'loading' ? 'Buscando…' : 'Buscar cadastro')),
      lkState === 'found' && !linked[0] ? h('div', { className: 'ck-al-found' },
        h(Avatar, { name: titleCase(lk[0].student.name), size: 'md', decorative: true }),
        h('div', { className: 'ck-al-found__text' },
          h('strong', null, titleCase(lk[0].student.name)),
          h('span', null, [lk[0].student.email, lk[0].student.origin ? 'cadastro em ' + lk[0].student.origin : 'já tem cadastro no Check-in'].filter(Boolean).join(', '))),
        h(Button, { variant: 'primary', icon: 'user-check', onClick: useFound }, 'Usar este cadastro')) : null,
      linked[0] ? h(Callout, { tone: 'success', title: 'Cadastro vinculado', action: h(Button, { size: 'sm', variant: 'ghost', onClick: function () { linked[1](null); lk[1]({ state: 'idle' }); } }, 'Desfazer') },
        firstName(linked[0].name) + ' vai entrar no app com o mesmo e-mail e senha que já usa. Confira os dados abaixo.') : null,
      lkState === 'here' ? h(Callout, { tone: 'info', title: titleCase(lk[0].student.name) + ' já é aluno desta academia',
        action: p.onOpenExisting ? h(Button, { size: 'sm', onClick: function () { p.onOpenExisting(lk[0].student); } }, 'Abrir cadastro') : null },
        'Não é preciso cadastrar de novo. Abra o cadastro para editar ou fazer uma matrícula.') : null,
      lkState === 'none' ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'Nenhum cadastro com este CPF. Preencha os dados abaixo para criar um novo.') : null,
      lkState === 'error' ? h('p', { className: 'ck-al-hint is-error' }, h(Icon, { name: 'alert-triangle', size: 15 }), 'Não foi possível buscar agora. Você pode seguir com o cadastro.') : null) : null;

    var primaryLabel = edit ? 'Salvar alterações' : linked[0] ? 'Vincular ' + firstName(f.name) : f.name.trim() ? 'Cadastrar ' + firstName(f.name) : 'Cadastrar aluno';
    return h('form', { className: 'ck-al-form', onSubmit: submit, noValidate: true, ref: rootRef },
      h('div', null,
        h(BackLink, { onClick: p.onBack }, edit ? titleCase(p.student && p.student.name) || 'Aluno' : 'Alunos'),
        h(PageHeader, { title: edit ? 'Editar aluno' : 'Novo aluno', description: edit ? 'Alterações valem na hora, inclusive no app do aluno.' : (p.requireCpf ? 'Só nome, e-mail e CPF são obrigatórios. O resto pode ser completado depois.' : 'Só nome e e-mail são obrigatórios. O resto pode ser completado depois.') })),
      h('div', { className: 'ck-al-form__grid' },
        h('div', { className: 'ck-card ck-al-form__main' },
          cpfBlock,
          h(AlSection, { id: 'al-s1', icon: 'user', title: 'Identificação', done: steps[0][1] && !!f.name },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { className: 'is-wide', label: 'Nome completo', value: f.name, placeholder: 'Como está no documento', autoComplete: 'off', error: show.name, readOnly: !!linked[0],
                onChange: function (e) { set('name', e.target.value); }, onBlur: function () { if (f.name && f.name === f.name.toUpperCase()) set('name', titleCase(f.name)); } }),
              edit ? h(TextField, { label: 'CPF', value: f.cpf, inputMode: 'numeric', placeholder: '000.000.000-00', error: show.cpf, onChange: function (e) { set('cpf', maskCpf(e.target.value)); } }) : null,
              h(TextField, { label: 'Data de nascimento', value: f.birth, inputMode: 'numeric', placeholder: 'DD/MM/AAAA', icon: 'calendar', error: show.birth,
                hint: age != null ? age + ' anos' + (age < 18 ? '. Menor de idade: confira o responsável.' : '') : null, onChange: function (e) { set('birth', maskDate(e.target.value)); } }),
              h(SelectField, { label: 'Sexo', value: f.gender, placeholder: 'Escolha', options: GENDERS, className: edit ? 'is-wide' : undefined, onChange: function (e) { set('gender', e.target.value); } }))),
          h(AlSection, { id: 'al-s2', icon: 'mail', title: 'Contato', done: steps[1][1] && !!f.email },
            h('div', { className: 'ck-al-grid' },
              h(TextField, { label: 'E-mail', type: 'email', value: f.email, placeholder: 'nome@exemplo.com', icon: 'mail', autoComplete: 'off', error: show.email, readOnly: !!linked[0],
                hint: !show.email ? 'É o login do aluno no app.' : null, onChange: function (e) { set('email', e.target.value); } }),
              h(TextField, { label: 'Telefone', type: 'tel', value: f.phone, inputMode: 'numeric', placeholder: '(00) 00000-0000', icon: 'phone', error: show.phone,
                hint: !show.phone ? 'Usado para avisos de cobrança no WhatsApp.' : null, onChange: function (e) { set('phone', maskPhone(e.target.value)); } }))),
          h(AlSection, { id: 'al-s3', icon: 'map-pin', title: 'Endereço', description: 'Opcional. Necessário para emitir boleto.', done: steps[2][1] },
            h('div', { className: 'ck-al-grid ck-al-grid--addr' },
              h(TextField, { label: 'CEP', value: f.cep, inputMode: 'numeric', placeholder: '00000-000', error: cp[0].state === 'none' ? 'CEP não encontrado. Preencha o endereço à mão.' : null,
                hint: cp[0].state === 'ok' ? 'Endereço preenchido pelo CEP.' : 'Preenche rua, bairro e cidade.',
                suffix: cp[0].state === 'loading' ? h(Icon, { name: 'loader', size: 16, className: 'ck-spin ck-al-cepspin' }) : cp[0].state === 'ok' ? h(Icon, { name: 'check-circle', size: 16, className: 'ck-al-cepok' }) : null,
                onChange: function (e) { onCep(e.target.value); } }),
              h(TextField, { className: 'ck-al-span2', label: 'Rua', value: f.street, placeholder: 'Rua, avenida, travessa', onChange: function (e) { set('street', e.target.value); } }),
              h(TextField, { label: 'Número', value: f.number, placeholder: 'Nº ou s/n', onChange: function (e) { set('number', e.target.value); } }),
              h(TextField, { className: 'ck-al-span2', label: 'Complemento', value: f.complement, placeholder: 'Apartamento, bloco, referência', onChange: function (e) { set('complement', e.target.value); } }),
              h(TextField, { label: 'Bairro', value: f.district, onChange: function (e) { set('district', e.target.value); } }),
              h(TextField, { label: 'Cidade', value: f.city, onChange: function (e) { set('city', e.target.value); } }),
              h(SelectField, { label: 'Estado', value: f.uf, placeholder: 'Escolha', options: UFS.map(function (u) { return { value: u[0], label: u[0] + ', ' + u[1] }; }), onChange: function (e) { set('uf', e.target.value); } }))),
          h(AlSection, { id: 'al-s4', icon: 'key', title: 'Acesso ao app', done: steps[3][1] && (edit || linked[0] || f.password.length >= 6) },
            linked[0] ? h('p', { className: 'ck-al-hint' }, h(Icon, { name: 'info', size: 15 }), 'A senha continua a mesma que o aluno já usa.')
              : edit && !pwOpen[0] ? h('div', { className: 'ck-al-pwrow' },
                h('div', null, h('strong', null, 'Senha'), h('span', null, 'O aluno troca a senha pelo app. Defina uma nova só se ele pedir.')),
                h(Button, { icon: 'key', onClick: function () { pwOpen[1](true); } }, 'Definir nova senha'))
              : h('div', { className: 'ck-al-grid' },
                h(TextField, { label: edit ? 'Nova senha' : 'Senha', type: sh[0] ? 'text' : 'password', value: f.password, placeholder: 'Mínimo de 6 caracteres', icon: 'lock', autoComplete: 'new-password', error: show.password,
                  hint: !show.password ? (f.password ? (f.password.length >= 6 ? 'Senha válida.' : 'Faltam ' + (6 - f.password.length) + ' caracteres.') : 'Combine com o aluno e peça para trocar no primeiro acesso.') : null,
                  suffix: h(IconButton, { icon: sh[0] ? 'eye-off' : 'eye', label: sh[0] ? 'Ocultar senha' : 'Mostrar senha', size: 'sm', onClick: function () { sh[1](!sh[0]); } }),
                  onChange: function (e) { set('password', e.target.value); } }),
                edit ? h('div', { className: 'ck-al-pwcancel' }, h(Button, { variant: 'ghost', onClick: function () { pwOpen[1](false); set('password', ''); } }, 'Manter senha atual')) : null),
            h('div', { className: 'ck-al-active' },
              h(Switch, { label: 'Aluno ativo', description: f.active ? 'Pode fazer check-in e entrar no app.' : 'Não consegue fazer check-in nem entrar no app. O histórico fica guardado.', checked: f.active, onChange: function (v) { set('active', v); } })))),
        h('aside', { className: 'ck-al-sum', 'aria-label': 'Resumo do cadastro' },
          h('div', { className: 'ck-al-sum__who' },
            f.name.trim() ? h(Avatar, { name: f.name, size: 'lg', decorative: true }) : h('span', { className: 'ck-al-sum__ph', 'aria-hidden': true }, h(Icon, { name: 'user', size: 24 })),
            h('div', { style: { minWidth: 0 } },
              h('strong', null, f.name.trim() || 'Nome do aluno'),
              h('span', null, f.email.trim() || 'E-mail ainda não informado'))),
          h('ol', { className: 'ck-al-sum__steps' }, steps.map(function (s2, i) {
            var ok = s2[1] && (i !== 0 || f.name) && (i !== 1 || f.email) && (i !== 3 || edit || linked[0] || f.password.length >= 6);
            var bad = !ok && tried[0] && !s2[2];
            return h('li', { key: i, className: ok ? 'is-done' : (bad ? 'is-error' : undefined) },
              h('span', { className: 'ck-al-sum__mark', 'aria-hidden': true }, ok ? h(Icon, { name: 'check', size: 13, strokeWidth: 3 }) : (bad ? h(Icon, { name: 'alert-triangle', size: 12, strokeWidth: 2.25 }) : i + 1)),
              h('span', null, s2[0]), !ok && s2[2] ? h('em', null, s2[2]) : null,
              h('span', { className: 'ck-sr' }, ok ? ', concluído' : bad ? ', com erro' : ', pendente'));
          })),
          h('div', { className: 'ck-al-sum__status' }, h('span', { className: cx('ck-al-sum__dot', f.active && 'is-on'), 'aria-hidden': true }), f.active ? 'Entra como ativo' : 'Entra como inativo'),
          tried[0] && Object.keys(err).length ? h('p', { className: 'ck-al-sum__err', role: 'alert' }, Object.keys(err).length === 1 ? 'Falta corrigir 1 campo.' : 'Falta corrigir ' + Object.keys(err).length + ' campos.') : null,
          h(Button, { type: 'submit', variant: 'primary', size: 'lg', block: true, icon: edit ? 'save' : linked[0] ? 'user-check' : 'user-plus', loading: p.saving, disabled: edit && !dirty }, p.saving ? 'Salvando…' : primaryLabel),
          edit && !dirty ? h('span', { className: 'ck-al-sum__note' }, 'Nenhuma alteração ainda.') : null,
          p.onCancel ? h('button', { type: 'button', className: 'ck-al-sum__cancel', onClick: p.onCancel }, 'Cancelar') : null)));
  }

  /* ---------- LoginScreen ---------- */
  function LoginScreen(p) {
    var md = useControlled(p.mode, 'login', p.onModeChange), mode = md[0];
    var em = useState(p.defaultEmail || ''), pw = useState(p.defaultPassword || ''), sh = useState(false), rm = useState(p.defaultRemember !== false);
    var tried = useState(false), sent = useState(false), caps = useState(false);
    var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em[0].trim());
    var eErr = tried[0] && !emailOk ? (em[0].trim() ? 'Confira o e-mail.' : 'Informe seu e-mail.') : null;
    var pErr = tried[0] && mode === 'login' && !pw[0] ? 'Informe sua senha.' : null;
    function go(m) { tried[1](false); sent[1](false); md[1](m); }
    function submit(e) {
      e.preventDefault(); tried[1](true);
      if (mode === 'login') { if (emailOk && pw[0] && p.onSubmit) p.onSubmit({ email: em[0].trim(), password: pw[0], remember: rm[0] }); }
      else if (emailOk) { Promise.resolve(p.onForgot && p.onForgot(em[0].trim())).then(function () { sent[1](true); }, function () {}); }
    }
    function capsKey(e) { if (e.getModifierState) caps[1](e.getModifierState('CapsLock')); }
    var feed = p.feed || [];
    var brand = h('div', { className: 'ck-login__logo' },
      h('span', { className: 'ck-sidebar__logo', 'aria-hidden': true }, h(Icon, { name: 'activity', size: 18, strokeWidth: 2.25 })),
      h('span', { className: 'ck-login__logoname' }, p.brandName || 'Check-in'));
    var form;
    if (mode === 'recuperar' && sent[0]) {
      form = h('div', { className: 'ck-login__sent', role: 'status' },
        h('span', { className: 'ck-login__senticon', 'aria-hidden': true }, h(Icon, { name: 'mail', size: 22 })),
        h('h1', { className: 'ck-login__title' }, 'Confira seu e-mail'),
        h('p', { className: 'ck-login__lead' }, 'Se ', h('strong', null, em[0].trim()), ' tiver acesso ao painel, você recebe em instantes um link para criar uma nova senha. O link vale por 1 hora.'),
        h(Button, { variant: 'secondary', size: 'lg', block: true, icon: 'arrow-left', onClick: function () { go('login'); } }, 'Voltar para o login'),
        h('button', { type: 'button', className: 'ck-linkbtn ck-login__resend', onClick: function () { sent[1](false); } }, 'Não chegou? Enviar de novo'));
    } else {
      var rec = mode === 'recuperar';
      form = h('form', { className: 'ck-login__form', onSubmit: submit, noValidate: true },
        p.academy ? h('span', { className: 'ck-login__academy' }, h(Icon, { name: 'dumbbell', size: 14 }), p.academy) : null,
        h('h1', { className: 'ck-login__title' }, rec ? 'Recuperar senha' : 'Entrar no painel'),
        h('p', { className: 'ck-login__lead' }, rec ? 'Informe o e-mail do seu acesso. Enviamos um link para criar uma senha nova.' : 'Use o e-mail e a senha do seu acesso de administrador.'),
        !rec && p.error ? h(Callout, { tone: 'danger', title: p.errorTitle || 'Não foi possível entrar' }, p.error) : null,
        h(TextField, { label: 'E-mail', type: 'email', icon: 'mail', autoComplete: 'username', placeholder: 'voce@academia.com.br', value: em[0], error: eErr, onChange: function (e) { em[1](e.target.value); } }),
        rec ? null : h(TextField, { label: 'Senha', type: sh[0] ? 'text' : 'password', icon: 'lock', autoComplete: 'current-password', placeholder: 'Sua senha', value: pw[0], error: pErr,
          labelAside: h('button', { type: 'button', className: 'ck-linkbtn', onClick: function () { go('recuperar'); } }, 'Esqueci minha senha'),
          hint: caps[0] ? 'Caps Lock está ativado.' : null, onKeyUp: capsKey, onKeyDown: capsKey,
          suffix: h(IconButton, { icon: sh[0] ? 'eye-off' : 'eye', label: sh[0] ? 'Ocultar senha' : 'Mostrar senha', size: 'sm', onClick: function () { sh[1](!sh[0]); } }),
          onChange: function (e) { pw[1](e.target.value); } }),
        rec ? null : h('label', { className: 'ck-check' },
          h('input', { type: 'checkbox', checked: rm[0], onChange: function (e) { rm[1](e.target.checked); } }),
          h('span', { className: 'ck-check__box', 'aria-hidden': true }, h(Icon, { name: 'check', size: 13, strokeWidth: 3 })),
          h('span', null, 'Manter conectado neste computador')),
        h(Button, { type: 'submit', variant: 'primary', size: 'lg', block: true, loading: !rec && p.loading, iconRight: rec ? undefined : 'arrow-right' }, rec ? 'Enviar link' : (p.loading ? 'Entrando…' : 'Entrar')),
        rec ? h('button', { type: 'button', className: 'ck-login__back', onClick: function () { go('login'); } }, h(Icon, { name: 'arrow-left', size: 16 }), 'Voltar para o login') : null);
    }
    return h('div', { className: cx('ck-login', p.className), style: p.height ? { minHeight: p.height } : undefined },
      h('aside', { className: 'ck-login__brand' },
        brand,
        h('div', { className: 'ck-login__pitch' },
          h('h2', null, p.headline || 'Sua academia inteira em um painel só.'),
          h('p', null, p.subline || 'Alunos, matrículas, check-ins e cobranças no Asaas e no Mercado Pago, do jeito que a recepção precisa.')),
        feed.length ? h('div', { className: 'ck-login__feed', 'aria-hidden': true },
          h('div', { className: 'ck-login__feedhead' }, h('span', { className: 'ck-login__live' }), p.feedTitle || 'Check-ins de agora'),
          h('ul', null, feed.slice(0, 3).map(function (x, i) {
            return h('li', { key: i }, h(Avatar, { name: x.name, size: 'sm', decorative: true }),
              h('span', { className: 'ck-login__feedtext' }, h('strong', null, x.name), h('span', null, x.what)),
              h('span', { className: 'ck-login__feedtime' }, x.time));
          }))) : null,
        h('ul', { className: 'ck-login__feats' },
          (p.features || [['check-circle', 'Check-in pelo app, com acesso liberado pelo plano'], ['credit-card', 'Cobrança automática por Pix, boleto e cartão'], ['trending-up', 'Receita bruta e líquida, mês a mês']]).map(function (x, i) {
            return h('li', { key: i }, h(Icon, { name: x[0], size: 16 }), x[1]);
          })),
        h('svg', { className: 'ck-login__wave', viewBox: '0 0 600 200', 'aria-hidden': true, preserveAspectRatio: 'none' },
          h('path', { d: 'M0 120 H170 L200 60 L240 180 L280 20 L320 150 L345 120 H600', fill: 'none', stroke: 'currentColor', strokeWidth: 3, strokeLinejoin: 'round', strokeLinecap: 'round' }))),
      h('main', { className: 'ck-login__main' },
        h('div', { className: 'ck-login__panel' },
          h('div', { className: 'ck-login__mobilelogo' }, brand),
          form),
        h('footer', { className: 'ck-login__foot' }, p.footer || 'Precisa de acesso? Fale com o responsável pela academia.')));
  }

  /* ---------- AcademiasLista (super admin) ---------- */
  var ACAD_STATUS = { ativa: ['success', 'Ativa'], implantacao: ['info', 'Em implantação'], suspensa: ['warning', 'Suspensa'], inativa: ['outline', 'Inativa'] };
  var ACAD_FILTERS = [['todas', 'Todas'], ['ativa', 'Ativas', 'success'], ['implantacao', 'Em implantação', 'info'], ['suspensa', 'Suspensas', 'warning'], ['inativa', 'Inativas', 'outline']];
  function maskCnpj(v) { var d = digits(v).slice(0, 14); return d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2'); }
  function AcademiasLista(p) {
    var qs = useControlled(p.query, '', p.onQueryChange), q = qs[0];
    var fs = useControlled(p.filter, 'todas', p.onFilterChange), f = fs[0];
    var all = p.rows || [];
    var counts = p.counts || (function () { var c = { todas: all.length }; all.forEach(function (r) { c[r.status] = (c[r.status] || 0) + 1; }); return c; })();
    var rows = all.filter(function (r) {
      if (p.onFilterChange == null && f !== 'todas' && r.status !== f) return false;
      if (p.onQueryChange == null && q) {
        var t = (r.name + ' ' + (r.email || '') + ' ' + (r.city || '')).toLowerCase(), dq = digits(q);
        return t.indexOf(q.toLowerCase()) >= 0 || (dq.length >= 3 && (digits(r.cnpj) + ' ' + digits(r.phone)).indexOf(dq) >= 0);
      }
      return true;
    });
    var s = p.summary;
    var filters = ACAD_FILTERS.filter(function (x) { return x[0] === 'todas' || counts[x[0]]; });
    function open(r) { p.onOpen && p.onOpen(r); }
    return h('div', { className: 'ck-ml ck-ac' },
      h(PageHeader, { title: 'Academias', description: 'Academias parceiras do Check-in, com contato, contrato e alunos de cada uma.',
        actions: h(Button, { variant: 'primary', icon: 'plus', onClick: p.onNew }, 'Nova academia') }),
      s ? h('div', { className: 'ck-db__kpis' },
        h(StatTile, { label: 'Academias ativas', icon: 'building', value: num(s.active), note: s.onboarding ? s.onboarding + ' em implantação' : 'de ' + num(counts.todas) + ' cadastradas' }),
        h(StatTile, { label: 'Alunos ativos', icon: 'users', value: num(s.students), note: 'somando todas as academias' }),
        h(StatTile, { label: 'Check-ins no mês', icon: 'activity', value: num(s.checkins), note: s.checkinsNote || null }),
        h(StatTile, { label: 'Contratos a renovar', icon: 'calendar', value: num(s.renewals || 0), note: s.renewals ? 'nos próximos 30 dias' : 'Nenhum nos próximos 30 dias' })) : null,
      h('section', { className: 'ck-card ck-ml__panel', 'aria-label': 'Lista de academias' },
        h('div', { className: 'ck-ml__toolbar' },
          h('div', { className: 'ck-control ck-ml__search' },
            h(Icon, { name: 'search', size: 18, className: 'ck-control__icon' }),
            h('input', { type: 'search', className: 'ck-control__input', value: q, placeholder: 'Buscar por nome, e-mail, cidade ou CNPJ', 'aria-label': 'Buscar academias', onChange: function (e) { qs[1](e.target.value); } }),
            q ? h(IconButton, { icon: 'x', label: 'Limpar busca', size: 'sm', onClick: function () { qs[1](''); } }) : null),
          filters.length > 2 ? h('div', { className: 'ck-ml__filters', role: 'radiogroup', 'aria-label': 'Filtrar academias' }, filters.map(function (x) {
            var on = f === x[0];
            return h('button', { key: x[0], type: 'button', role: 'radio', 'aria-checked': on, className: 'ck-ml__chip', onClick: function () { fs[1](x[0]); } },
              x[2] ? h('span', { className: 'ck-ml__chipdot ck-ml__chipdot--' + x[2], 'aria-hidden': true }) : null,
              x[1], counts[x[0]] != null ? h('span', { className: 'ck-ml__count' }, counts[x[0]]) : null);
          })) : null),
        rows.length ? h('ul', { className: 'ck-ac__list' }, rows.map(function (r) {
          var st = ACAD_STATUS[r.status] || ACAD_STATUS.ativa;
          var left = r.contractUntil && p.today ? dayDiff(p.today, r.contractUntil) : null;
          return h('li', { key: r.id, className: cx('ck-ac__row', r.status === 'inativa' && 'is-off'), onClick: function (e) { if (!e.target.closest('button, a')) open(r); } },
            h('div', { className: 'ck-ac__who' },
              r.logo ? h('span', { className: 'ck-ac__logo has-img', 'aria-hidden': true }, h('img', { src: r.logo, alt: '' })) : h('span', { className: 'ck-ac__logo', 'aria-hidden': true }, initialsOf(r.name)),
              h('div', { style: { minWidth: 0 } },
                h('div', { className: 'ck-ac__titleline' },
                  h('a', { href: r.href || '#', className: 'ck-ml__name ck-ac__name', onClick: function (e) { if (p.onOpen) { e.preventDefault(); open(r); } } }, r.name),
                  h(Badge, { tone: st[0], dot: st[0] !== 'outline' }, st[1])),
                h('ul', { className: 'ck-ac__meta' },
                  h('li', null, 'Academia #' + r.id),
                  r.city ? h('li', null, h(Icon, { name: 'map-pin', size: 14 }), titleCase(r.city) + (r.uf ? ', ' + r.uf : '')) : null,
                  r.cnpj ? h('li', null, 'CNPJ ' + maskCnpj(r.cnpj)) : null))),
            h('div', { className: 'ck-ac__contact' },
              h('span', null, h(Icon, { name: 'mail', size: 14 }), r.email || h(Missing, null, 'Sem e-mail')),
              h('span', null, h(Icon, { name: 'phone', size: 14 }), r.phone ? maskPhone(r.phone) : h(Missing, null, 'Sem telefone'))),
            h('dl', { className: 'ck-ac__nums' },
              h('div', null, h('dt', null, 'Alunos ativos'), h('dd', null, r.students != null ? num(r.students) : '—')),
              h('div', null, h('dt', null, 'Contrato'), h('dd', null, r.contract || h(Missing, null, 'Sem contrato'),
                left != null ? h('span', { className: cx('ck-ac__left', left <= 30 && 'is-warn') }, left < 0 ? 'venceu em ' + fdate(r.contractUntil) : 'até ' + fdate(r.contractUntil)) : null))),
            h('div', { className: 'ck-mt-actions ck-ac__actions' },
              p.onEdit ? h(IconButton, { icon: 'pencil', label: 'Editar ' + r.name, size: 'sm', onClick: function () { p.onEdit(r); } }) : null,
              h(ActionMenu, { label: 'Mais ações para ' + r.name, items: [
                { label: 'Ver detalhes', icon: 'arrow-right', onClick: function () { open(r); } },
                p.onEnterAs ? { label: 'Entrar no painel da academia', icon: 'log-in', onClick: function () { p.onEnterAs(r); } } : null,
                p.onContract ? { label: 'Ver contrato', icon: 'receipt', onClick: function () { p.onContract(r); } } : null,
                p.onToggleActive ? '-' : null,
                p.onToggleActive ? (r.status === 'ativa' || r.status === 'implantacao' ? { label: 'Desativar academia', icon: 'ban', danger: true, onClick: function () { p.onToggleActive(r); } } : { label: 'Reativar academia', icon: 'power', onClick: function () { p.onToggleActive(r); } }) : null].filter(Boolean) })));
        })) :
          h('div', { className: 'ck-ml__empty' },
            h('span', { className: 'ck-mt-empty__icon', 'aria-hidden': true }, h(Icon, { name: q ? 'search' : 'building', size: 20 })),
            h('strong', null, q ? 'Nenhuma academia encontrada para "' + q + '"' : all.length ? 'Nenhuma academia neste filtro' : 'Nenhuma academia cadastrada'),
            h('p', null, q ? 'Busque pelo CNPJ só com números, ou pela cidade.' : 'Cadastre a primeira academia para liberar o painel dela.'),
            q ? h(Button, { size: 'sm', onClick: function () { qs[1](''); } }, 'Limpar busca') : h(Button, { size: 'sm', icon: 'plus', onClick: p.onNew }, 'Nova academia')),
        h('footer', { className: 'ck-ml__foot' },
          h('span', null, rows.length === 1 ? '1 academia' : rows.length + ' academias', f !== 'todas' || q ? ' de ' + counts.todas : ''))));
  }

export { AlunosLista, AlunoDetalhe, AlunoForm, LoginScreen, AcademiasLista, StatTile, Panel, maskCpf, maskPhone, maskCep, maskCnpj, digits, titleCase };
