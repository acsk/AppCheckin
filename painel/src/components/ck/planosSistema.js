/* AppCheckin — Planos do sistema (super admin): PlanosSistemaLista e PlanoSistemaForm.
   Estilos: src/theme/ck-planos-sistema.css (reaproveita ck-al-* de ck-pessoas.css e ck-ml__chip). Primitivas de ./index. */
import React, { useId, useMemo, useRef, useState } from 'react';
import {
  ActionMenu, Badge, Button, Callout, Icon, PageHeader, Switch, TextAreaField, TextField,
} from './index';
import { maskMoney, money, parseMoney } from './planos';

const cx = (...a) => a.filter(Boolean).join(' ');
const num = (v) => new Intl.NumberFormat('pt-BR').format(v || 0);
const plural = (n, one, many) => `${num(n)} ${n === 1 ? one : many}`;
const intOrNull = (v) => {
  const s = String(v ?? '').trim();
  return s === '' ? null : Number(s);
};
const isPosInt = (v) => Number.isInteger(v) && v > 0;
const SAMPLE_STUDENTS = 100;

export const TYPES = [
  { value: 'gratuito', label: 'Grátis', description: 'Sem cobrança', icon: 'check-circle' },
  { value: 'fixo', label: 'Valor fixo', description: 'Mesmo valor todo ciclo', icon: 'banknote' },
  { value: 'por_aluno', label: 'Por aluno ativo', description: 'Alunos ativos vezes o valor', icon: 'users' },
];

export function cycleLabel(days) {
  const d = Number(days) || 30;
  if (d === 30) return 'por mês';
  if (d === 365) return 'por ano';
  if (d === 7) return 'por semana';
  return `a cada ${d} dias`;
}
function cycleHint(days) {
  const d = Number(days) || 0;
  if (!d) return null;
  if (d === 30) return 'Cobrança mensal.';
  if (d === 365) return 'Cobrança anual.';
  if (d === 7) return 'Cobrança semanal.';
  if (d === 90) return 'Cobrança trimestral.';
  if (d === 180) return 'Cobrança semestral.';
  return `Cobrança a cada ${d} dias.`;
}

export function systemPlanFromApi(x) {
  const type = x.tipo_cobranca || (Number(x.valor) > 0 ? 'fixo' : 'gratuito');
  return {
    id: x.id,
    code: x.codigo || null,
    name: x.nome || '',
    description: x.descricao || '',
    type,
    price: Number(x.valor || 0),
    perStudent: Number(x.valor_por_aluno || 0),
    cycleDays: Number(x.duracao_dias || 30),
    termDays: x.prazo_dias ? Number(x.prazo_dias) : null,
    maxStudents: x.max_alunos ? Number(x.max_alunos) : null,
    maxAdmins: x.max_admins ? Number(x.max_admins) : null,
    active: Number(x.ativo) === 1 || x.ativo === true,
    onSale: Number(x.atual) === 1 || x.atual === true,
    order: Number(x.ordem || 0),
    contracts: Number(x.contratos_ativos || 0),
  };
}

function priceParts(pl) {
  if (pl.type === 'gratuito') return { big: 'Grátis', per: pl.termDays ? `por ${plural(pl.termDays, 'dia', 'dias')}` : null };
  if (pl.type === 'por_aluno') return { big: money(pl.perStudent), per: `por aluno ativo, ${cycleLabel(pl.cycleDays)}` };
  return { big: money(pl.price), per: cycleLabel(pl.cycleDays) };
}
function sampleLine(pl) {
  if (pl.type !== 'por_aluno' || !pl.perStudent) return null;
  const n = pl.maxStudents || SAMPLE_STUDENTS;
  return `Academia com ${num(n)} alunos paga ${money(n * pl.perStudent)} ${cycleLabel(pl.cycleDays)}.`;
}
function planFacts(pl, { usage } = {}) {
  const out = [
    { icon: 'users', text: pl.maxStudents ? `Até ${plural(pl.maxStudents, pl.type === 'por_aluno' ? 'aluno ativo' : 'aluno', pl.type === 'por_aluno' ? 'alunos ativos' : 'alunos')}` : 'Alunos ilimitados' },
    { icon: 'shield-check', text: pl.maxAdmins ? plural(pl.maxAdmins, 'administrador', 'administradores') : 'Administradores ilimitados' },
  ];
  if (pl.termDays) out.push({ icon: 'clock', text: `Contrato de ${plural(pl.termDays, 'dia', 'dias')}` });
  if (usage) out.push({ icon: 'building', text: pl.contracts ? plural(pl.contracts, 'academia usando', 'academias usando') : 'Nenhuma academia usando' });
  else if (pl.type !== 'gratuito') out.push({ icon: 'calendar', text: `Ciclo de ${plural(pl.cycleDays, 'dia', 'dias')}` });
  return out;
}
function FactList({ facts, className }) {
  return (
    <ul className={cx('ck-ps-facts', className)}>
      {facts.map((f) => <li key={f.text}><Icon name={f.icon} size={15} />{f.text}</li>)}
    </ul>
  );
}

/* ---------- PlanosSistemaLista ---------- */
const saleKey = (pl) => (pl.active && pl.onSale ? 'venda' : 'fora');
const FILTERS = [['todos', 'Todos'], ['venda', 'À venda', 'success'], ['fora', 'Fora de venda', 'outline']];

function PlanCard({ plan, onEdit, onNewContract, onToggleSale, onToggleActive }) {
  const pr = priceParts(plan);
  const sample = sampleLine(plan);
  const status = !plan.active
    ? <Badge tone="outline" icon="ban">Inativo</Badge>
    : plan.onSale ? <Badge tone="success" dot>À venda</Badge> : <Badge tone="outline" icon="eye-off">Fora de venda</Badge>;
  const items = [];
  if (plan.active && plan.onSale && onNewContract) items.push({ label: 'Novo contrato com este plano', icon: 'file-text', onClick: () => onNewContract(plan) });
  if (plan.active && onToggleSale) items.push(plan.onSale
    ? { label: 'Tirar de venda', icon: 'eye-off', onClick: () => onToggleSale(plan) }
    : { label: 'Pôr à venda', icon: 'eye', onClick: () => onToggleSale(plan) });
  if (onToggleActive) {
    if (items.length) items.push('-');
    items.push(plan.active
      ? { label: 'Desativar plano', icon: 'power', danger: true, onClick: () => onToggleActive(plan) }
      : { label: 'Reativar plano', icon: 'power', onClick: () => onToggleActive(plan) });
  }
  return (
    <article className={cx('ck-card ck-ps-card', !plan.active && 'is-off')} aria-labelledby={`ps-${plan.id}`}>
      <header className="ck-ps-card__top">
        <h2 className="ck-ps-card__name" id={`ps-${plan.id}`} title={plan.name}>{plan.name}</h2>
        {status}
      </header>
      <div className="ck-ps-card__price">
        <strong>{pr.big}</strong>
        {pr.per ? <span>{pr.per}</span> : null}
        {sample ? <p>{sample}</p> : null}
      </div>
      <FactList facts={planFacts(plan, { usage: true })} />
      <footer className="ck-ps-card__foot">
        <Button size="sm" icon="pencil" onClick={() => onEdit?.(plan)}>Editar</Button>
        {items.length ? <ActionMenu label={`Ações do plano ${plan.name}`} items={items} /> : null}
      </footer>
    </article>
  );
}

export function PlanosSistemaLista({
  plans = [], loading, error, filter: filterProp, onFilterChange, onNew, onNewContract, onEdit, onToggleSale, onToggleActive,
}) {
  const [own, setOwn] = useState('todos');
  const filter = filterProp ?? own;
  const setFilter = (v) => { setOwn(v); onFilterChange?.(v); };
  const counts = useMemo(() => {
    const c = { todos: plans.length, venda: 0, fora: 0 };
    plans.forEach((pl) => { c[saleKey(pl)] += 1; });
    return c;
  }, [plans]);
  const visible = plans.filter((pl) => filter === 'todos' || saleKey(pl) === filter);

  return (
    <div className="ck-ps">
      <PageHeader
        title="Planos do sistema"
        description="O que as academias podem contratar do Check-in: preço, limite de alunos e se está à venda."
        actions={(
          <>
            {onNewContract ? <Button icon="file-text" onClick={() => onNewContract(null)}>Novo contrato</Button> : null}
            <Button variant="primary" icon="plus" onClick={onNew}>Novo plano</Button>
          </>
        )}
      />
      <div className="ck-ml__filters ck-ps__filters" role="radiogroup" aria-label="Filtrar planos">
        {FILTERS.map(([k, label, dot]) => (
          <button key={k} type="button" role="radio" aria-checked={filter === k} className="ck-ml__chip" onClick={() => setFilter(k)}>
            {dot ? <span className={`ck-ml__chipdot ck-ml__chipdot--${dot}`} aria-hidden /> : null}
            {label}<span className="ck-ml__count">{counts[k]}</span>
          </button>
        ))}
      </div>
      {error ? <Callout tone="danger" title="Não foi possível carregar os planos">{error}</Callout>
        : loading ? <p role="status" className="ck-al-hint"><Icon name="loader" size={15} className="ck-spin" />Carregando planos…</p>
          : visible.length ? (
            <div className="ck-ps-grid">
              {visible.map((pl) => (
                <PlanCard key={pl.id} plan={pl} onEdit={onEdit} onNewContract={onNewContract} onToggleSale={onToggleSale} onToggleActive={onToggleActive} />
              ))}
            </div>
          ) : (
            <div className="ck-card ck-ml__empty">
              <span className="ck-mt-empty__icon" aria-hidden><Icon name="layers" size={20} /></span>
              <strong>{plans.length ? 'Nenhum plano neste filtro' : 'Nenhum plano cadastrado'}</strong>
              <p>{plans.length ? 'Escolha outro filtro para ver os demais planos.' : 'Crie o primeiro plano para poder fechar contratos com as academias.'}</p>
              {!plans.length ? <Button size="sm" icon="plus" onClick={onNew}>Novo plano</Button> : null}
            </div>
          )}
    </div>
  );
}

/* ---------- PlanoSistemaForm ---------- */
function AffixField({ label, hint, error, prefix, suffix, className, disabled, ...rest }) {
  const id = useId();
  return (
    <div className={cx('ck-field', className)}>
      <div className="ck-field__top"><label className="ck-field__label" htmlFor={id}>{label}</label></div>
      <div className={cx('ck-control ck-ps-affix', error && 'is-invalid', disabled && 'is-readonly')}>
        {prefix ? <span className="ck-ps-affix__pre" aria-hidden>{prefix}</span> : null}
        <input id={id} className="ck-control__input" disabled={disabled} aria-invalid={error ? true : undefined} aria-describedby={hint || error ? `${id}-m` : undefined} {...rest} />
        {suffix ? <span className="ck-ps-affix__suf" aria-hidden>{suffix}</span> : null}
      </div>
      {error ? <span className="ck-field__error" id={`${id}-m`}><Icon name="alert-triangle" size={13} />{error}</span>
        : hint ? <span className="ck-field__hint" id={`${id}-m`}>{hint}</span> : null}
    </div>
  );
}

function Section({ id, icon, title, description, done, children }) {
  return (
    <section className="ck-al-sec" aria-labelledby={id}>
      <header className="ck-al-sec__head">
        <span className={cx('ck-al-sec__icon', done && 'is-done')} aria-hidden>
          <Icon name={done ? 'check' : icon} size={17} strokeWidth={done ? 2.5 : 1.75} />
        </span>
        <div style={{ flex: 1, minWidth: 0 }}>
          <h2 className="ck-al-sec__title" id={id}>{title}</h2>
          {description ? <p className="ck-al-sec__desc">{description}</p> : null}
        </div>
      </header>
      <div className="ck-al-sec__body">{children}</div>
    </section>
  );
}

const toMoneyInput = (v) => (Number(v) > 0 ? maskMoney(Number(v).toFixed(2)) : '');
function fromPlan(pl) {
  return {
    name: pl?.name || '',
    description: pl?.description || '',
    type: pl?.type || 'por_aluno',
    price: toMoneyInput(pl?.price),
    perStudent: toMoneyInput(pl ? pl.perStudent : 3.5),
    cycleDays: String(pl?.cycleDays || 30),
    maxStudents: pl?.maxStudents ? String(pl.maxStudents) : '',
    maxAdmins: pl ? (pl.maxAdmins ? String(pl.maxAdmins) : '') : '1',
    termDays: pl?.termDays ? String(pl.termDays) : '',
    onSale: pl ? pl.onSale : true,
    active: pl ? pl.active : true,
    order: String(pl?.order ?? 0),
  };
}
const digitsOnly = (v) => String(v || '').replace(/\D/g, '');

export function PlanoSistemaForm({ mode, plan, saving, onSave, onBack, onCancel }) {
  const edit = mode === 'editar';
  const [init] = useState(() => fromPlan(plan));
  const [f, setF] = useState(init);
  const [tried, setTried] = useState(false);
  const rootRef = useRef(null);
  const set = (k, v) => setF((cur) => ({ ...cur, [k]: v }));
  const locked = edit && plan?.contracts > 0;

  const cycle = intOrNull(f.cycleDays);
  const maxStudents = intOrNull(f.maxStudents);
  const maxAdmins = intOrNull(f.maxAdmins);
  const termDays = intOrNull(f.termDays);
  const order = intOrNull(f.order);
  const price = parseMoney(f.price);
  const perStudent = parseMoney(f.perStudent);

  const err = {};
  if (!f.name.trim()) err.name = 'Dê um nome ao plano.';
  if (f.type === 'fixo' && !(price > 0)) err.price = 'Informe o valor. Para não cobrar, escolha Grátis.';
  if (f.type === 'por_aluno' && !(perStudent > 0)) err.perStudent = 'Informe quanto custa cada aluno ativo.';
  if (f.type !== 'gratuito' && !isPosInt(cycle)) err.cycleDays = 'O ciclo precisa ter pelo menos 1 dia.';
  if (maxStudents !== null && !isPosInt(maxStudents)) err.maxStudents = 'Use um número maior que zero, ou deixe vazio.';
  if (maxAdmins !== null && !isPosInt(maxAdmins)) err.maxAdmins = 'Use um número maior que zero, ou deixe vazio.';
  if (termDays !== null && !isPosInt(termDays)) err.termDays = 'Use um número de dias maior que zero, ou deixe vazio.';
  if (order === null || !Number.isInteger(order) || order < 0) err.order = 'Use 0 ou um número positivo.';
  const show = tried ? err : {};
  const nErr = Object.keys(err).length;
  const dirty = JSON.stringify(f) !== JSON.stringify(init);

  const preview = {
    name: f.name.trim() || 'Nome do plano',
    type: f.type,
    price,
    perStudent,
    cycleDays: isPosInt(cycle) ? cycle : 30,
    maxStudents: isPosInt(maxStudents) ? maxStudents : null,
    maxAdmins: isPosInt(maxAdmins) ? maxAdmins : null,
    termDays: isPosInt(termDays) ? termDays : null,
  };
  const pr = priceParts(preview);

  let example = null;
  if (f.type === 'gratuito') {
    example = preview.termDays
      ? `A academia usa sem pagar por ${plural(preview.termDays, 'dia', 'dias')}. Depois, troque o contrato para um plano pago.`
      : 'A academia usa sem pagar e sem prazo. Bom para parceiros e cortesias.';
  } else if (f.type === 'por_aluno' && perStudent > 0) {
    const n = preview.maxStudents || SAMPLE_STUDENTS;
    example = `Com ${num(n)} alunos ativos, a academia paga ${money(n * perStudent)} por ciclo.`;
  } else if (f.type === 'fixo' && price > 0) {
    example = `A academia paga ${money(price)} a cada ${plural(preview.cycleDays, 'dia', 'dias')}, ${preview.maxStudents ? `com até ${num(preview.maxStudents)} alunos ativos` : 'sem limite de alunos'}.`;
  }

  const identOk = !!f.name.trim();
  const billingOk = !err.price && !err.perStudent && !err.cycleDays;
  const limitsOk = !err.maxStudents && !err.maxAdmins && !err.termDays;

  function submit(e) {
    e?.preventDefault();
    setTried(true);
    if (nErr) {
      setTimeout(() => {
        const el = rootRef.current?.querySelector('[aria-invalid="true"]');
        if (el) { el.focus(); el.scrollIntoView?.({ block: 'center', behavior: 'smooth' }); }
      }, 0);
      return;
    }
    const data = {
      nome: f.name.trim(),
      descricao: f.description.trim() || null,
      max_alunos: maxStudents,
      max_admins: maxAdmins,
      prazo_dias: termDays,
      atual: f.onSale ? 1 : 0,
      ativo: f.active ? 1 : 0,
      ordem: order,
    };
    if (!locked) {
      Object.assign(data, {
        tipo_cobranca: f.type,
        valor: f.type === 'fixo' ? price : 0,
        valor_por_aluno: f.type === 'por_aluno' ? perStudent : null,
        duracao_dias: f.type === 'gratuito' ? (isPosInt(cycle) ? cycle : 30) : cycle,
      });
    }
    onSave?.(data);
  }

  return (
    <form className="ck-al-form ck-ps-form" onSubmit={submit} noValidate ref={rootRef}>
      <div>
        {onBack ? <button type="button" className="ck-mt-back" onClick={onBack}><Icon name="arrow-left" size={16} />Planos do sistema</button> : null}
        <PageHeader
          title={edit ? 'Editar plano' : 'Novo plano'}
          description={edit ? 'Mudanças em limites, prazo e textos valem também para quem já tem contrato.' : 'Depois de criado e à venda, o plano aparece na tela de novo contrato.'}
        />
      </div>
      <div className="ck-al-form__grid">
        <div className="ck-card ck-al-form__main">
          <Section id="ps-s1" icon="package" title="Identificação" done={identOk}>
            <div className="ck-al-grid">
              <TextField className="is-wide" label="Nome do plano" value={f.name} placeholder="Ex.: Professional" error={show.name} onChange={(e) => set('name', e.target.value)} />
              <TextAreaField className="is-wide" label="Descrição" value={f.description} placeholder="Para quem é o plano e o que inclui" hint="Aparece para a academia no contrato." onChange={(e) => set('description', e.target.value)} />
            </div>
          </Section>

          <Section id="ps-s2" icon="wallet" title="Cobrança" done={billingOk}>
            {locked ? (
              <Callout tone="warning" title={`${plural(plan.contracts, 'academia usa', 'academias usam')} este plano`}>
                Por isso o preço e o ciclo não podem mudar. Limites, prazo e textos podem. Para cobrar outro valor, crie um novo plano.
              </Callout>
            ) : null}
            <div className="ck-ps-types" role="radiogroup" aria-label="Tipo de cobrança">
              {TYPES.map((t) => (
                <button key={t.value} type="button" role="radio" aria-checked={f.type === t.value} className="ck-ps-type" disabled={locked} onClick={() => set('type', t.value)}>
                  <span className="ck-ps-type__icon" aria-hidden><Icon name={t.icon} size={16} /></span>
                  <span className="ck-ps-type__text"><strong>{t.label}</strong><span>{t.description}</span></span>
                  <span className="ck-choice__radio ck-ct-radio" aria-hidden />
                </button>
              ))}
            </div>
            {f.type !== 'gratuito' ? (
              <div className="ck-al-grid">
                {f.type === 'fixo' ? (
                  <AffixField label="Valor do ciclo" prefix="R$" inputMode="numeric" placeholder="0,00" value={f.price} error={show.price} disabled={locked} onChange={(e) => set('price', maskMoney(e.target.value))} />
                ) : (
                  <AffixField label="Valor por aluno ativo" prefix="R$" inputMode="numeric" placeholder="0,00" value={f.perStudent} error={show.perStudent} disabled={locked} onChange={(e) => set('perStudent', maskMoney(e.target.value))} />
                )}
                <AffixField label="Duração do ciclo" suffix="dias" inputMode="numeric" value={f.cycleDays} error={show.cycleDays} hint={cycleHint(cycle)} disabled={locked} onChange={(e) => set('cycleDays', digitsOnly(e.target.value))} />
              </div>
            ) : null}
            {example ? <Callout tone="info">{example}</Callout> : null}
          </Section>

          <Section id="ps-s3" icon="users" title="Limites" description="Passar do limite não bloqueia a academia: ela e você recebem um aviso no painel." done={limitsOk}>
            <div className="ck-al-grid">
              <AffixField label="Limite de alunos ativos" suffix="alunos" inputMode="numeric" placeholder="Sem limite" value={f.maxStudents} error={show.maxStudents} hint="Vazio é ilimitado." onChange={(e) => set('maxStudents', digitsOnly(e.target.value))} />
              <AffixField label="Administradores" suffix={maxAdmins === 1 ? 'pessoa' : 'pessoas'} inputMode="numeric" placeholder="Sem limite" value={f.maxAdmins} error={show.maxAdmins} hint="Quantas pessoas da academia acessam o painel." onChange={(e) => set('maxAdmins', digitsOnly(e.target.value))} />
              <AffixField label="Prazo do contrato" suffix="dias" inputMode="numeric" placeholder="Sem prazo" value={f.termDays} error={show.termDays}
                hint="Sugere a data de fim no novo contrato. Vazio, vale até ser encerrado." onChange={(e) => set('termDays', digitsOnly(e.target.value))} />
            </div>
          </Section>

          <Section id="ps-s4" icon="eye" title="Disponibilidade" done={!err.order}>
            <div className="ck-ps-switches">
              <Switch label="À venda" description="Aparece na tela de novo contrato." checked={f.onSale} onChange={(v) => set('onSale', v)} />
              <Switch label="Plano ativo" description={f.active ? 'Pode ser usado em contratos.' : 'Não pode ser usado em contratos novos nem em trocas.'} checked={f.active} onChange={(v) => set('active', v)} />
            </div>
            {f.onSale && !f.active ? <Callout tone="warning">Plano inativo não aparece para venda, mesmo marcado como à venda.</Callout> : null}
            <div className="ck-al-grid">
              <TextField label="Posição na lista" inputMode="numeric" value={f.order} error={show.order} hint="Menor aparece primeiro." onChange={(e) => set('order', digitsOnly(e.target.value))} />
            </div>
          </Section>
        </div>

        <aside className="ck-al-sum ck-ps-sum" aria-label="Como o plano aparece no contrato">
          <span className="ck-ps-sum__label">Como aparece no contrato</span>
          <div className="ck-ps-preview">
            <strong className="ck-ps-preview__name">{preview.name}</strong>
            <span className="ck-ps-preview__price">{pr.big}</span>
            {pr.per ? <span className="ck-ps-preview__per">{pr.per}</span> : null}
            <FactList facts={planFacts(preview)} className="ck-ps-facts--dark" />
          </div>
          {tried && nErr ? <p className="ck-al-sum__err" role="alert">{nErr === 1 ? 'Falta corrigir 1 item.' : `Falta corrigir ${nErr} itens.`}</p> : null}
          <Button type="submit" variant="primary" size="lg" block icon={edit ? 'save' : 'plus'} loading={saving} disabled={edit && !dirty}>
            {saving ? 'Salvando…' : edit ? 'Salvar alterações' : 'Criar plano'}
          </Button>
          {edit && !dirty ? <span className="ck-al-sum__note">Nenhuma alteração ainda.</span> : null}
          {onCancel ? <button type="button" className="ck-al-sum__cancel" onClick={onCancel}>Cancelar</button> : null}
        </aside>
      </div>
    </form>
  );
}
