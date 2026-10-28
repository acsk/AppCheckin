/* AppCheckin — módulo de planos: PlanosLista, PlanoForm e CicloModal.
   Estilos: src/theme/ck-planos.css. Primitivas de ./index. */
import React, { useEffect, useMemo, useState } from 'react';
import {
  ActionMenu, Badge, Button, Callout, Icon, IconButton, Modal, PageHeader, SegmentedControl, SelectField, Switch, TextAreaField, TextField,
} from './index';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
export const money = (v) => BRL.format(Number(v) || 0);
export const UNLIMITED = 999;

export const parseMoney = (v) => {
  if (typeof v === 'number') return v;
  const n = parseFloat(String(v || '').replace(/\./g, '').replace(',', '.'));
  return Number.isFinite(n) ? n : 0;
};
export const maskMoney = (raw) => {
  const digits = String(raw || '').replace(/\D/g, '');
  if (!digits) return '';
  return (parseInt(digits, 10) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const isDaily = (t) => /diar/i.test(`${t?.codigo || ''} ${t?.nome || ''}`);
export const periodLabel = (t) => {
  if (isDaily(t)) return '1 dia';
  const m = Number(t?.meses) || 1;
  return m === 1 ? '1 mês' : `${m} meses`;
};
export const frequencyLabel = (n) => {
  const v = Number(n) || 0;
  if (v >= UNLIMITED) return 'Livre';
  return v ? `${v}x por semana` : '—';
};

export const planStatus = (p) => {
  if (!p.active) return { tone: 'neutral', label: 'Inativo', key: 'inativos' };
  if (!p.open) return { tone: 'warning', label: 'Fechado a novas', key: 'fechados' };
  return { tone: 'success', label: 'Ativo', key: 'abertos' };
};

/* ---------- PlanosLista ---------- */
const FILTERS = [
  ['todos', 'Todos'],
  ['ativos', 'Ativos'],
  ['abertos', 'Abertos a matrículas'],
  ['inativos', 'Inativos'],
];
const matchFilter = (p, f) => f === 'todos' || (f === 'ativos' && p.active) || (f === 'abertos' && p.active && p.open) || (f === 'inativos' && !p.active);
const norm = (s) => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

function CycleTags({ cycles, max = 3 }) {
  if (!cycles || !cycles.length) return <Badge tone="danger" icon="alert-triangle">Nenhum ciclo</Badge>;
  const shown = cycles.slice(0, max);
  return (
    <span className="ck-pl__cycles">
      {shown.map((c) => <Badge key={c.id} tone="outline">{c.label}</Badge>)}
      {cycles.length > max ? <Badge tone="outline">+{cycles.length - max}</Badge> : null}
    </span>
  );
}

export function PlanosLista({
  plans = [], loading, error, readOnly, onNew, onOpen, onDeactivate, onReport, reportLoading, toolbarStart, emptyHint, showTenant, onTermos,
}) {
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState('todos');

  const counts = useMemo(() => FILTERS.reduce((acc, [k]) => ({ ...acc, [k]: plans.filter((p) => matchFilter(p, k)).length }), {}), [plans]);
  const groups = useMemo(() => {
    const q = norm(query);
    const visible = plans.filter((p) => matchFilter(p, filter) && (!q || norm(`${p.name} ${p.modality?.name} #${p.id}`).includes(q)));
    const map = new Map();
    visible.forEach((p) => {
      const key = p.modality?.name || 'Sem modalidade';
      if (!map.has(key)) map.set(key, { name: key, color: p.modality?.color, icon: p.modality?.icon, plans: [] });
      map.get(key).plans.push(p);
    });
    return [...map.values()].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
  }, [plans, query, filter]);
  const noCycles = plans.filter((p) => p.active && p.open && p.cycles && !p.cycles.length).length;
  const total = groups.reduce((a, g) => a + g.plans.length, 0);

  return (
    <div className="ck-pl">
      <PageHeader
        title="Planos"
        description={readOnly ? 'Planos criados pelas academias, com os ciclos de cobrança de cada um.' : 'Defina quanto custa cada modalidade e em quais ciclos o aluno pode pagar.'}
        actions={readOnly ? null : (
          <>
            {onTermos ? <Button icon="file-text" onClick={onTermos}>Termos da fidelidade</Button> : null}
            {onReport ? <Button icon="download" loading={reportLoading} onClick={onReport}>Relatório PDF</Button> : null}
            <Button variant="primary" icon="plus" onClick={onNew}>Novo plano</Button>
          </>
        )}
      />
      {toolbarStart || null}
      {!readOnly && noCycles > 0 ? (
        <Callout tone="warning" title={noCycles === 1 ? '1 plano aberto a matrículas está sem ciclo' : `${noCycles} planos abertos a matrículas estão sem ciclo`}>
          Sem ciclo de cobrança o plano não aparece para venda. Abra o plano e adicione pelo menos um ciclo.
        </Callout>
      ) : null}
      <section className="ck-card ck-ml__panel ck-pl__panel" aria-label="Lista de planos">
        <div className="ck-ml__toolbar">
          <div className="ck-control ck-ml__search">
            <Icon name="search" size={18} className="ck-control__icon" />
            <input type="search" className="ck-control__input" value={query} placeholder="Buscar por plano, modalidade ou código" aria-label="Buscar planos" onChange={(e) => setQuery(e.target.value)} />
            {query ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setQuery('')} /> : null}
          </div>
          <div className="ck-ml__filters" role="radiogroup" aria-label="Filtrar planos">
            {FILTERS.map(([k, label]) => (
              <button key={k} type="button" role="radio" aria-checked={filter === k} className="ck-ml__chip" onClick={() => setFilter(k)}>
                {label}<span className="ck-ml__count">{counts[k]}</span>
              </button>
            ))}
          </div>
        </div>

        {error ? <p role="alert" className="ck-enrollment-state">{error}</p>
          : loading ? <p role="status" className="ck-enrollment-state">Carregando planos…</p>
          : total ? (
            <div className="ck-mt-table-wrap">
              <table className="ck-mt-table ck-pl__table">
                <thead>
                  <tr>
                    <th scope="col">Plano</th>
                    {showTenant ? <th scope="col">Academia</th> : null}
                    <th scope="col" className="is-num">Valor</th>
                    <th scope="col">Ciclos</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span className="ck-sr">Ações</span></th>
                  </tr>
                </thead>
                {groups.map((g) => (
                  <tbody key={g.name}>
                    <tr className="ck-pl__group">
                      <th colSpan={showTenant ? 6 : 5} scope="rowgroup">
                        <ModalityBadge icon={g.icon} color={g.color} size="sm" />
                        {g.name}<span className="ck-pl__groupcount">{g.plans.length === 1 ? '1 plano' : `${g.plans.length} planos`}</span>
                      </th>
                    </tr>
                    {g.plans.map((p) => {
                      const st = planStatus(p);
                      return (
                        <tr key={p.id} className={`ck-ml__row${p.active ? '' : ' is-off'}`} onClick={(e) => { if (!e.target.closest('button, a')) onOpen?.(p); }}>
                          <td>
                            <a href={`/planos/${p.id}`} className="ck-ml__name" onClick={(e) => { e.preventDefault(); onOpen?.(p); }}>{p.name}</a>
                            <span className="ck-mt-cell__sub">
                              #{p.id} · {frequencyLabel(p.checkins)}{p.durationDays ? ` · ${p.durationDays === 1 ? '1 dia' : `${p.durationDays} dias`} de acesso` : ''}
                            </span>
                          </td>
                          {showTenant ? <td><span className="ck-mt-cell__sub">{p.tenantName || '—'}</span></td> : null}
                          <td className="is-num">
                            {p.amount > 0
                              ? <><span className="ck-mt-cell__main">{money(p.amount)}</span><span className="ck-mt-cell__sub">por mês</span></>
                              : <Badge tone="info">Sem custo</Badge>}
                          </td>
                          <td>{p.cycles ? <CycleTags cycles={p.cycles} /> : <span className="ck-mt-cell__sub">—</span>}</td>
                          <td><Badge tone={st.tone} dot>{st.label}</Badge></td>
                          <td className="ck-mt-actions">
                            <ActionMenu
                              label={`Ações do plano ${p.name}`}
                              items={[
                                { label: readOnly ? 'Ver plano' : 'Editar plano', icon: readOnly ? 'eye' : 'pencil', onClick: () => onOpen?.(p) },
                                ...(!readOnly && p.active && onDeactivate ? ['-', { label: 'Desativar plano', icon: 'power', danger: true, onClick: () => onDeactivate(p) }] : []),
                              ]}
                            />
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                ))}
              </table>
            </div>
          ) : (
            <div className="ck-ml__empty">
              <span className="ck-mt-empty__icon" aria-hidden><Icon name={query ? 'search' : 'layers'} size={20} /></span>
              <strong>{query ? `Nenhum plano encontrado para "${query}"` : plans.length ? 'Nenhum plano neste filtro' : 'Nenhum plano cadastrado'}</strong>
              <p>{query ? 'Confira a grafia ou busque pelo nome da modalidade.' : emptyHint || 'Crie o primeiro plano e depois adicione os ciclos de cobrança.'}</p>
              {query ? <Button size="sm" onClick={() => setQuery('')}>Limpar busca</Button>
                : !readOnly && !plans.length ? <Button size="sm" icon="plus" onClick={onNew}>Novo plano</Button> : null}
            </div>
          )}
        <footer className="ck-ml__foot">
          <span>{total ? `${total} ${total === 1 ? 'plano' : 'planos'}${filter !== 'todos' || query ? ` de ${plans.length}` : ''}` : 'Nenhum resultado'}</span>
        </footer>
      </section>
    </div>
  );
}

function ModalityBadge({ icon, color, size = 'md' }) {
  return (
    <span className={`ck-pl__modicon ck-pl__modicon--${size}`} style={color ? { background: color, color: '#fff' } : undefined} aria-hidden>
      <Icon name={icon || 'dumbbell'} size={size === 'sm' ? 14 : 16} strokeWidth={2} />
    </span>
  );
}

/* ---------- PlanoForm ---------- */
const FREQ_OPTIONS = [1, 2, 3, 4, 5, 6, 7, UNLIMITED];
export const isDailyPlan = (values) => Number(values?.duracao_dias) === 1;

function FormSection({ n, title, description, aside, children }) {
  return (
    <section className="ck-nm__step ck-pf__section">
      <header className="ck-nm__stephead">
        <span className="ck-nm__num" aria-hidden>{n}</span>
        <div>
          <h2>{title}</h2>
          {description ? <p className="ck-pf__desc">{description}</p> : null}
        </div>
        {aside || null}
      </header>
      <div className="ck-nm__stepbody">{children}</div>
    </section>
  );
}

function OptionChips({ label, options, value, onChange, disabled }) {
  return (
    <div className="ck-field">
      <span className="ck-field__label">{label}</span>
      <div className="ck-pf__chips" role="radiogroup" aria-label={label}>
        {options.map((o) => (
          <button key={o.value} type="button" role="radio" aria-checked={Number(value) === o.value} disabled={disabled} className="ck-pf__chip" onClick={() => onChange(o.value)}>
            {o.icon ? <Icon name={o.icon} size={16} /> : null}{o.label}
          </button>
        ))}
      </div>
    </div>
  );
}

export function CycleRow({ cycle, monthly, onEdit, onRemove, onToggleBlock, readOnly, busy }) {
  const daily = isDaily(cycle);
  const months = Number(cycle.months) || 1;
  const perMonth = daily ? null : cycle.amount / months;
  const pct = !daily && monthly > 0 && perMonth < monthly ? Math.round((1 - perMonth / monthly) * 100) : 0;
  return (
    <li className={cycle.active ? null : 'is-off'}>
      <span className="ck-pf__cyclemonths" aria-hidden>{daily ? '1d' : `${months}m`}</span>
      <span className="ck-pf__cyclemain">
        <strong>{cycle.label}</strong>
        <span>{periodLabel(cycle)}{perMonth != null && months > 1 ? `, equivale a ${money(perMonth)} por mês` : ''}</span>
      </span>
      <span className="ck-pf__cycletags">
        {pct > 0 ? <Badge tone="success">{pct}% de desconto</Badge> : null}
        {cycle.fidelity?.mensal
          ? <Badge tone="brand" icon="repeat">Fidelidade · {money(perMonth)}/mês</Badge>
          : <Badge tone={cycle.recurring ? 'info' : 'neutral'} icon={cycle.recurring ? 'repeat' : undefined}>{cycle.recurring ? 'Renova no cartão (MP)' : 'À vista'}</Badge>}
        {cycle.makeup ? <Badge tone="outline">Reposição</Badge> : null}
        {cycle.active ? null : <Badge tone="danger" icon="lock">Bloqueado</Badge>}
      </span>
      <span className="ck-pf__cycleprice">{money(cycle.amount)}</span>
      {readOnly ? null : (
        <ActionMenu
          label={`Ações do ciclo ${cycle.label}`}
          items={[
            { label: 'Editar ciclo', icon: 'pencil', onClick: () => onEdit?.(cycle) },
            ...(onToggleBlock ? [cycle.active
              ? { label: 'Bloquear ciclo', icon: 'lock', disabled: busy, onClick: () => onToggleBlock(cycle) }
              : { label: 'Desbloquear ciclo', icon: 'unlock', disabled: busy, onClick: () => onToggleBlock(cycle) }] : []),
            '-',
            { label: 'Excluir ciclo', icon: 'trash', danger: true, onClick: () => onRemove?.(cycle) },
          ]}
        />
      )}
    </li>
  );
}

export function PlanoForm({
  values, onChange, errors = {}, modalities = [], isEdit, readOnly, lockedRules, saving, onSave, onCancel, onBack,
  cycles = [], cyclesLoading, onAddCycle, onEditCycle, onRemoveCycle, onToggleCycleBlock, blockingCycleId, onGenerateCycles, generating, missingCount = 0,
}) {
  const set = (k) => (v) => onChange({ ...values, [k]: v });
  const daily = isDailyPlan(values);
  const monthly = monthlyBase(cycles);
  const dailyCycle = cycles.find((c) => c.active && isDaily(c));
  const modality = modalities.find((m) => String(m.id) === String(values.modalidade_id));
  const freqOptions = FREQ_OPTIONS.map((v) => ({ value: v, label: v >= UNLIMITED ? 'Livre' : `${v}x`, icon: v >= UNLIMITED ? 'infinity' : undefined }));
  const checkins = Number(values.checkins_semanais) || 0;
  if (checkins && !FREQ_OPTIONS.includes(checkins)) freqOptions.splice(7, 0, { value: checkins, label: `${checkins}x` });
  const setDaily = (on) => onChange({ ...values, duracao_dias: on ? '1' : '30', checkins_semanais: on ? '1' : values.checkins_semanais });
  const sellable = cycles.filter((c) => c.active);
  const cheapest = sellable.reduce((min, c) => {
    if (isDaily(c)) return min;
    const pm = c.amount / (Number(c.months) || 1);
    return min == null || pm < min ? pm : min;
  }, null);

  return (
    <div className="ck-pf">
      <button type="button" className="ck-mt-back" onClick={onBack}><Icon name="arrow-left" size={16} />Planos</button>
      <PageHeader
        title={readOnly ? values.nome || 'Plano' : isEdit ? 'Editar plano' : 'Novo plano'}
        description={isEdit ? 'Ajuste o acesso e os ciclos em que o aluno pode pagar.' : 'Preencha os dados do plano. Depois de salvar, você adiciona os ciclos de cobrança.'}
      />
      <div className="ck-nm__grid ck-pf__grid">
        <fieldset className="ck-card ck-nm__form ck-enrollment-fields" disabled={saving || readOnly}>
          <FormSection n={1} title="Dados do plano">
            {lockedRules ? (
              <Callout tone="info" icon="lock" title="Plano com matrículas">
                Modalidade, check-ins por semana e duração ficam fixos para não mudar a regra de quem já está no plano. Nome, descrição e situação continuam editáveis. Para outra regra, crie um novo plano e desmarque "Aceita novas matrículas" neste.
              </Callout>
            ) : null}
            <div className="ck-field">
              <span className="ck-field__label">Modalidade</span>
              <div className="ck-nm__mods" role="radiogroup" aria-label="Modalidade">
                {modalities.map((m) => (
                  <button key={m.id} type="button" role="radio" aria-checked={String(m.id) === String(values.modalidade_id)} disabled={lockedRules && String(m.id) !== String(values.modalidade_id)} className="ck-nm__mod" onClick={() => set('modalidade_id')(String(m.id))}>
                    <ModalityBadge icon={m.icone} color={m.cor} />{m.nome}
                  </button>
                ))}
                {!modalities.length ? <p className="ck-nm__wait">Nenhuma modalidade ativa. Cadastre uma modalidade antes de criar planos.</p> : null}
              </div>
              {errors.modalidade_id ? <span className="ck-field__error"><Icon name="alert-triangle" size={13} />{errors.modalidade_id}</span> : null}
            </div>
            <TextField label="Nome do plano" value={values.nome} placeholder="Ex.: 3x por semana" onChange={(e) => set('nome')(e.target.value)} error={errors.nome} hint="É o nome que o aluno vê no app." />
            <TextAreaField label="Descrição" value={values.descricao} placeholder="O que está incluído no plano" onChange={(e) => set('descricao')(e.target.value)} labelAside={<span className="ck-field__hint">Opcional</span>} />
          </FormSection>

          <FormSection n={2} title="Acesso">
            <Switch label="Plano diária" description={daily ? 'Vale 1 check-in no dia da compra. Cadastre o ciclo Diária com o valor.' : 'Plano por período: o valor e a duração vêm dos ciclos de cobrança.'} checked={daily} disabled={saving || readOnly || lockedRules} onChange={setDaily} />
            {daily ? null : (
              <>
                <OptionChips label="Check-ins por semana" options={freqOptions} value={checkins} disabled={lockedRules} onChange={(v) => set('checkins_semanais')(String(v))} />
                {errors.checkins_semanais ? <span className="ck-field__error"><Icon name="alert-triangle" size={13} />{errors.checkins_semanais}</span> : null}
              </>
            )}
          </FormSection>

          <FormSection
            n={3}
            title="Ciclos de cobrança"
            description="Em quais períodos o aluno pode pagar este plano."
            aside={isEdit && !readOnly ? (
              <div className="ck-pf__cycleactions">
                {onGenerateCycles && !daily && missingCount > 0 ? <Button size="sm" variant="ghost" icon="zap" loading={generating} onClick={onGenerateCycles}>{`Gerar ${missingCount === 1 ? 'ciclo que falta' : `${missingCount} ciclos que faltam`}`}</Button> : null}
                <Button size="sm" icon="plus" onClick={onAddCycle}>Novo ciclo</Button>
              </div>
            ) : null}
          >
            {!isEdit ? (
              <Callout tone="info" title="Salve o plano para adicionar ciclos">{daily ? 'Depois de criar, você volta para esta tela e cadastra o ciclo Diária com o valor.' : 'Depois de criar, você volta para esta tela e cadastra Mensal, Trimestral e os outros períodos. O ciclo Mensal é a base do desconto dos demais.'}</Callout>
            ) : cyclesLoading ? <p role="status" className="ck-nm__wait">Carregando ciclos…</p>
              : cycles.length ? (
                <ul className="ck-pf__cyclelist">
                  {cycles.map((c) => <CycleRow key={c.id} cycle={c} monthly={monthly} onEdit={onEditCycle} onRemove={onRemoveCycle} onToggleBlock={onToggleCycleBlock} busy={blockingCycleId === c.id} readOnly={readOnly} />)}
                </ul>
              ) : (
                <div className="ck-mt-empty">
                  <span className="ck-mt-empty__icon" aria-hidden><Icon name="repeat" size={20} /></span>
                  <strong>Nenhum ciclo ainda</strong>
                  <p>Sem ciclo, o plano não aparece para venda no app nem na nova matrícula.</p>
                  {readOnly ? null : <Button size="sm" icon="plus" onClick={onAddCycle}>Adicionar ciclo</Button>}
                </div>
              )}
          </FormSection>
        </fieldset>

        <aside className="ck-pf__aside" aria-label="Prévia e status">
          <section className="ck-mt-plan ck-pf__preview" aria-label="Como o aluno vê">
            <span className="ck-pf__eyebrow">Como o aluno vê</span>
            <div className="ck-mt-plan__top">
              <span className="ck-mt-plan__icon" style={modality?.cor ? { background: modality.cor, color: '#fff' } : undefined} aria-hidden><Icon name={modality?.icone || 'dumbbell'} size={20} strokeWidth={2} /></span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <h2 className="ck-mt-plan__name">{values.nome || 'Nome do plano'}</h2>
                <span className="ck-mt-plan__freq">{modality?.nome || 'Modalidade'}</span>
              </div>
            </div>
            <div className="ck-mt-plan__price">
              {daily ? (
                <>
                  <span className="ck-mt-plan__value">{dailyCycle ? money(dailyCycle.amount) : 'Sem valor'}</span>
                  {dailyCycle ? <span className="ck-mt-plan__per">por dia</span> : null}
                </>
              ) : (
                <>
                  <span className="ck-mt-plan__value">{monthly > 0 ? money(monthly) : 'Sem valor'}</span>
                  {monthly > 0 ? <span className="ck-mt-plan__per">por mês</span> : null}
                </>
              )}
            </div>
            {!daily && cheapest != null && cheapest < monthly ? <p className="ck-mt-plan__note">A partir de {money(cheapest)} por mês no ciclo mais longo</p> : null}
            <dl className="ck-mt-plan__facts">
              <div><dt>Frequência</dt><dd>{daily ? '1 check-in' : frequencyLabel(checkins)}</dd></div>
              <div><dt>Acesso</dt><dd>{daily ? '1 dia' : 'Conforme o ciclo'}</dd></div>
            </dl>
            {isEdit ? (
              <div className="ck-pf__previewcycles">
                {sellable.length ? sellable.map((c) => <span key={c.id}>{c.label}</span>) : <span className="is-empty">Sem ciclos à venda</span>}
              </div>
            ) : null}
          </section>

          <section className="ck-card ck-pf__status" aria-label="Status do plano">
            <Switch label="Plano ativo" description={values.ativo ? 'Matrículas existentes continuam valendo.' : 'O plano some do app e da nova matrícula.'} checked={!!values.ativo} disabled={saving || readOnly} onChange={set('ativo')} />
            <Switch label="Aceita novas matrículas" description={values.atual ? 'Aparece para novos alunos.' : 'Só quem já tem o plano continua nele.'} checked={!!values.atual} disabled={saving || readOnly || !values.ativo} onChange={set('atual')} />
            {readOnly ? null : (
              <div className="ck-pf__save">
                <Button variant="primary" size="lg" block icon="save" loading={saving} onClick={onSave}>{saving ? 'Salvando…' : isEdit ? 'Salvar plano' : 'Criar plano'}</Button>
                <Button variant="ghost" block disabled={saving} onClick={onCancel}>Cancelar</Button>
              </div>
            )}
          </section>
        </aside>
      </div>
    </div>
  );
}

// Termo modelo para os "Termos adicionais" da fidelização (a academia pode editar antes de salvar)
export const TERMO_MODELO_FIDELIZACAO = `TERMOS ADICIONAIS — PLANO COM FIDELIZAÇÃO

1. Da fidelização e do benefício promocional
Ao contratar este plano, o aluno concorda com o período mínimo de fidelização informado na contratação, recebendo, em contrapartida, um desconto promocional sobre o valor da mensalidade convencional.

2. Da cobrança recorrente
As mensalidades serão cobradas individualmente, conforme os vencimentos estabelecidos. A contratação não constitui parcelamento do valor total do plano, sendo cada mensalidade cobrada separadamente.

3. Do cancelamento antecipado
O aluno poderá solicitar o cancelamento a qualquer momento. Caso ocorra antes do término da fidelização, poderá ser exigida, quando legal e contratualmente cabível, a regularização proporcional dos descontos efetivamente usufruídos.
A regularização corresponderá à diferença entre a mensalidade convencional e a promocional, considerando exclusivamente o período utilizado, sem cobrança de descontos referentes a meses futuros.

4. Do direito de arrependimento
Nas contratações realizadas fora do estabelecimento comercial, será respeitado o prazo legal de arrependimento de 7 dias, conforme o artigo 49 do Código de Defesa do Consumidor, com as restituições legalmente devidas.
Eventual prazo adicional de desistência sem custo oferecido pelo estabelecimento observará as condições expressamente informadas na contratação, sem restringir os direitos legais do consumidor.

5. Da reposição de aulas
A reposição de aulas não realizadas estará sujeita à disponibilidade de horários, vagas e regras do estabelecimento. Aulas não utilizadas não serão automaticamente acumuladas para meses posteriores, salvo previsão expressa no plano contratado.

6. Da renovação do plano
Ao término da fidelização, a assinatura seguirá a modalidade de continuidade previamente informada e aceita pelo aluno. Não haverá imposição automática de novo período de fidelização sem consentimento expresso.

7. Da ciência e aceitação
Ao confirmar a contratação, o aluno declara ter recebido informações claras sobre valores, descontos, prazo de fidelização, cobrança recorrente, cancelamento e condições de utilização do serviço, concordando com os termos apresentados, sem prejuízo dos direitos assegurados pela legislação vigente.`;

/* ---------- CicloModal ---------- */
const FID_PADRAO = { mensal: false, renovacao: 'mensal', regularizacao: false, proporcional: false, arrependimento: 7, termos: '', proprios: false };
const FID_RENOVACAO = [
  { value: 'mensal', label: 'Continua mensal, pelo valor sem desconto' },
  { value: 'promocional', label: 'Continua pelo mesmo valor, sem prazo' },
  { value: 'nova_fidelizacao', label: 'Oferece nova fidelização (aluno aceita)' },
  { value: 'encerrar', label: 'Encerra a assinatura' },
];
const QUICK_DISCOUNTS = [0, 5, 10, 15, 20];

export function CicloModal({ open, cycle, types = [], cycles = [], monthly = 0, saving, onSave, onCancel, termosAcademia = '', onEditarTermos }) {
  const editing = !!cycle?.id;
  const [typeId, setTypeId] = useState('');
  const [amount, setAmount] = useState('');
  const [recurring, setRecurring] = useState(true);
  const [makeup, setMakeup] = useState(false);
  const [active, setActive] = useState(true);
  const [tried, setTried] = useState(false);
  // Fidelização: assinatura mensal com o preço do ciclo dividido por mês (nunca venda parcelada)
  const [fid, setFid] = useState(FID_PADRAO);
  const setF = (patch) => setFid((f) => ({ ...f, ...patch }));

  useEffect(() => {
    if (!open) return;
    // Sem texto próprio, o ciclo herda os termos da academia
    setFid({ ...FID_PADRAO, ...(cycle?.fidelity || {}), proprios: !!(cycle?.fidelity?.termos || '').trim() });
    setTypeId(cycle?.typeId ? String(cycle.typeId) : '');
    setAmount(cycle?.amount != null ? maskMoney(Math.round(cycle.amount * 100)) : '');
    setRecurring(cycle ? !!cycle.recurring : true);
    setMakeup(cycle ? !!cycle.makeup : false);
    setActive(cycle ? cycle.active !== false : true);
    setTried(false);
  }, [open, cycle]);

  const type = types.find((t) => String(t.id) === typeId);
  const taken = (t) => cycles.some((c) => String(c.typeId) === String(t.id) && !!c.recurring === (fid.mensal ? false : recurring)
    && !!c.fidelity?.mensal === fid.mensal && c.id !== cycle?.id);
  const daily = isDaily(type);
  const months = Number(type?.meses) || 1;
  const value = parseMoney(amount);
  const base = daily || months === 1 ? 0 : monthly * months;
  const perMonth = daily ? null : value / months;
  const diff = base > 0 && value > 0 ? (1 - value / base) * 100 : null;

  const errs = {};
  if (!type) errs.type = 'Escolha o período do ciclo.';
  else if (taken(type)) errs.type = `Já existe um ciclo ${type.nome} ${fid.mensal ? 'com fidelização' : recurring ? 'com renovação no cartão' : 'à vista'} neste plano.`;
  if (fid.mensal && type && months < 2) errs.type = 'Fidelização mensal vale para ciclos de 2 meses ou mais.';
  if (fid.mensal && (Number(fid.arrependimento) < 7 || Number(fid.arrependimento) > 90)) errs.arrependimento = 'De 7 (mínimo legal) a 90 dias.';
  if (amount === '') errs.amount = 'Informe o valor do ciclo.';
  else if (value === 0 && recurring) errs.amount = 'Ciclo recorrente precisa ter valor maior que zero.';
  const ok = !Object.keys(errs).length;

  const save = () => {
    setTried(true);
    if (!ok || saving) return;
    onSave?.({ id: cycle?.id, typeId: Number(typeId), amount: value, recurring: fid.mensal ? false : recurring, makeup, active,
      fidelity: { ...fid, termos: fid.proprios ? fid.termos : '' } });
  };

  return (
    <Modal
      open={open}
      onClose={onCancel}
      dismissable={!saving}
      size="md"
      title={editing ? `Editar ciclo ${cycle.label || ''}`.trim() : 'Novo ciclo'}
      description={monthly > 0 ? `Base mensal (ciclo ${baseCycle(cycles)?.label || 'Mensal'}): ${money(monthly)}` : 'Cadastre o ciclo Mensal: ele é a base do desconto dos demais.'}
      footer={(
        <>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={saving} onClick={onCancel}>Cancelar</Button>
          <Button variant="primary" icon="save" loading={saving} onClick={save}>{saving ? 'Salvando…' : editing ? 'Salvar ciclo' : 'Adicionar ciclo'}</Button>
        </>
      )}
    >
      <div className="ck-cm">
        <div className="ck-section">
          <span className="ck-field__label">Período</span>
          {editing ? (
            <div className="ck-cm__locked">
              <Icon name="clock" size={16} />
              <strong>{type?.nome || cycle.label}</strong>
              <span>{periodLabel(type || cycle)}</span>
              <span className="ck-cm__lockednote">Para trocar o período, exclua o ciclo e crie outro.</span>
            </div>
          ) : (
            <div className="ck-cm__types" role="radiogroup" aria-label="Período">
              {types.map((t) => {
                const isTaken = taken(t);
                return (
                  <button key={t.id} type="button" role="radio" aria-checked={String(t.id) === typeId} disabled={isTaken} className={`ck-cm__type${isTaken ? ' is-taken' : ''}`} onClick={() => setTypeId(String(t.id))}>
                    <strong>{t.nome}</strong>
                    <span>{isTaken ? 'Já existe' : periodLabel(t)}</span>
                  </button>
                );
              })}
            </div>
          )}
          {tried && errs.type ? <span className="ck-field__error"><Icon name="alert-triangle" size={13} />{errs.type}</span> : null}
        </div>

        <TextField label="Valor do ciclo" inputMode="numeric" icon="banknote" className="ck-dm__amount" value={amount} placeholder="0,00" onChange={(e) => setAmount(maskMoney(e.target.value))} error={tried ? errs.amount : null} hint={daily ? 'Valor cobrado por dia de acesso.' : `Valor total cobrado a cada ${months === 1 ? 'mês' : `${months} meses`}.`} />

        {base > 0 ? (
          <div className="ck-cm__quick" role="group" aria-label="Atalhos de valor">
            {QUICK_DISCOUNTS.map((d) => (
              <button key={d} type="button" className="ck-pf__chip" onClick={() => setAmount(maskMoney(Math.round(base * (1 - d / 100) * 100)))}>
                {d === 0 ? 'Sem desconto' : `${d}% off`}<span>{money(base * (1 - d / 100))}</span>
              </button>
            ))}
          </div>
        ) : null}

        {perMonth != null && value > 0 ? (
          <div className={`ck-cm__preview${diff != null && diff < 0 ? ' is-above' : ''}`} aria-live="polite">
            <span className="ck-dm__to">{money(perMonth)}</span>
            <span className="ck-dm__note">
              por mês{diff == null ? '' : Math.abs(diff) < 0.5 ? ', igual ao mensal' : diff > 0 ? `, ${Math.round(diff)}% abaixo do mensal` : `, ${Math.round(-diff)}% acima do mensal`}
            </span>
          </div>
        ) : null}

        {!daily && months >= 2 ? (
          <div className="ck-cm__fid">
            <Switch label="Também oferecer como assinatura mensal com fidelidade" checked={fid.mensal} onChange={(v) => setF({ mensal: v })}
              description={fid.mensal
                ? `O aluno escolhe no app: pagar ${value > 0 ? money(value) : 'o ciclo'} de uma vez, ou assinar por ${perMonth > 0 ? money(perMonth) : 'o valor ÷ meses'} por mês (cobranças separadas, não é parcelamento) com fidelidade de ${months} meses.`
                : 'Desligado: o ciclo só é vendido à vista, de uma vez.'} />
            {fid.mensal ? (
              <div className="ck-cm__fidgrid">
                <SelectField label="Ao fim da fidelização" value={fid.renovacao} onChange={(e) => setF({ renovacao: e.target.value })} options={FID_RENOVACAO} />
                <TextField label="Desistência sem custo (dias)" inputMode="numeric" value={String(fid.arrependimento)} error={tried ? errs.arrependimento : null}
                  onChange={(e) => setF({ arrependimento: e.target.value.replace(/\D/g, '').slice(0, 2) })} hint="Mínimo legal: 7 dias após o aceite." />
                <Switch label="Cobrar regularização no cancelamento antecipado" checked={fid.regularizacao} onChange={(v) => setF({ regularizacao: v })}
                  description={fid.regularizacao
                    ? `Quem sair antes paga a diferença do desconto já recebido${monthly > 0 && perMonth > 0 ? ` (${money(Math.max(0, monthly - perMonth))} por mensalidade paga)` : ''}, nunca de meses futuros.`
                    : 'Desligado: o sistema só calcula e mostra o valor; nada é cobrado.'} />
                {fid.regularizacao ? <Switch label="Proporcional ao último mês" checked={fid.proporcional} onChange={(v) => setF({ proporcional: v })}
                  description="Se o cancelamento cortar o acesso no meio de um mês pago, conta só os dias usados." /> : null}
                <div className="ck-cm__termos">
                  <SegmentedControl label="Termos adicionais" block value={fid.proprios ? 'proprios' : 'academia'}
                    onChange={(v) => setF({ proprios: v === 'proprios', termos: v === 'proprios' && !fid.termos.trim() ? termosAcademia : fid.termos })}
                    options={[{ value: 'academia', label: 'Termos da academia' }, { value: 'proprios', label: 'Próprios deste ciclo' }]} />
                  {fid.proprios ? (
                    <TextAreaField label="Termos deste ciclo" rows={8} maxLength={5000} value={fid.termos} onChange={(e) => setF({ termos: e.target.value })}
                      labelAside={<span className="ck-cm__acts">
                        {termosAcademia ? <Button size="sm" variant="ghost" icon="copy" onClick={() => setF({ termos: termosAcademia })}>Copiar da academia</Button> : null}
                        <Button size="sm" variant="ghost" icon="file-text" onClick={() => setF({ termos: TERMO_MODELO_FIDELIZACAO })}>Termo modelo</Button>
                      </span>}
                      hint="Só este ciclo usa este texto. Mudar algo gera uma nova versão; contratos já aceitos não mudam." />
                  ) : (
                    <>
                      <div className={'ck-cm__termosprev' + (termosAcademia ? '' : ' is-vazio')}>
                        {termosAcademia || 'A academia ainda não tem termos da fidelidade. O aceite mostra só o texto gerado com valores, prazo e regras deste ciclo.'}
                      </div>
                      {onEditarTermos ? <Button size="sm" icon="pencil" onClick={onEditarTermos}>{termosAcademia ? 'Editar termos da academia' : 'Criar termos da academia'}</Button> : null}
                    </>
                  )}
                </div>
              </div>
            ) : null}
          </div>
        ) : null}

        <div className="ck-cm__switches">
          {fid.mensal ? null : <Switch label="Renovação automática no cartão (Mercado Pago)" checked={recurring} onChange={setRecurring}
            description={recurring
              ? 'Só com Mercado Pago e cartão: o valor inteiro do ciclo é cobrado de novo a cada período. No Asaas não muda nada. Para cobrar mês a mês, use a Fidelização acima.'
              : 'O aluno paga o ciclo uma vez e renova pelo app quando vencer.'} />}
          <Switch label="Permite reposição" description={makeup ? 'O limite de check-ins conta por mês, então o aluno compensa aulas perdidas.' : 'O limite de check-ins conta por semana.'} checked={makeup} onChange={setMakeup} />
          <Switch label="Bloquear ciclo" description={!active ? 'Ninguém contrata este ciclo, nem pelo app nem por nova matrícula. Quem já tem matrícula nele continua normalmente.' : 'O aluno pode escolher este ciclo no app e na nova matrícula.'} checked={!active} onChange={(v) => setActive(!v)} />
        </div>
      </div>
    </Modal>
  );
}

/* ---------- GerarCiclosModal ---------- */
const MARGINS = [0, 5, 10];
const round2 = (n) => Math.round(n * 100) / 100;
const typeMonths = (t) => Number(t?.meses) || 1;

export const missingTypes = (types = [], cycles = []) => types
  .filter((t) => !isDaily(t) && !cycles.some((c) => String(c.typeId) === String(t.id)))
  .sort((a, b) => typeMonths(a) - typeMonths(b));

// Semanal e quinzenal vêm com meses = 1 e não servem de base mensal.
const SUBMONTHLY = ['semanal', 'quinzenal'];

export const baseCycle = (cycles = []) => {
  const regular = cycles.filter((c) => !isDaily(c) && !SUBMONTHLY.includes(c.codigo)).sort((a, b) => (a.months - b.months) || (Number(b.active) - Number(a.active)));
  return regular.find((c) => c.codigo === 'mensal') || regular[0] || null;
};

/* Valor por mês do ciclo base (Mensal, ou o menor período): base do desconto dos demais. */
export const monthlyBase = (cycles = []) => {
  const base = baseCycle(cycles);
  return base ? base.amount / (Number(base.months) || 1) : 0;
};

export function GerarCiclosModal({ open, types = [], cycles = [], monthly = 0, saving, onConfirm, onCancel }) {
  const base = baseCycle(cycles);
  const [margin, setMargin] = useState('5');
  const [recurring, setRecurring] = useState(false);
  const [makeup, setMakeup] = useState(false);
  const [active, setActive] = useState(true);

  useEffect(() => {
    if (!open) return;
    setMargin('5');
    setRecurring(base ? !!base.recurring : false);
    setMakeup(base ? !!base.makeup : false);
    setActive(base ? base.active !== false : true);
  }, [open]);

  const step = Math.min(Math.max(parseFloat(String(margin).replace(',', '.')) || 0, 0), 50);

  const rows = useMemo(() => {
    let prev = null;
    return types
      .filter((t) => !isDaily(t))
      .sort((a, b) => typeMonths(a) - typeMonths(b))
      .map((t) => {
        const months = typeMonths(t);
        const full = monthly * months;
        const existing = cycles.filter((c) => String(c.typeId) === String(t.id));
        if (existing.length) {
          const ref = existing.find((c) => !!c.recurring === recurring) || existing[0];
          const discount = full > 0 ? Math.max(0, (1 - ref.amount / full) * 100) : 0;
          prev = discount;
          return { type: t, months, existing: true, discount, amount: ref.amount };
        }
        const discount = prev == null ? 0 : Math.min(prev + step, 90);
        prev = discount;
        return { type: t, months, existing: false, discount, amount: round2(full * (1 - discount / 100)) };
      });
  }, [types, cycles, monthly, step, recurring]);

  const toCreate = rows.filter((r) => !r.existing);
  const blocked = monthly <= 0;

  const confirm = () => {
    if (!toCreate.length || blocked || saving) return;
    onConfirm?.(toCreate.map((r) => ({ typeId: r.type.id, label: r.type.nome, amount: r.amount, recurring, makeup, active })));
  };

  return (
    <Modal
      open={open}
      onClose={onCancel}
      dismissable={!saving}
      size="md"
      title={toCreate.length === 1 ? 'Gerar o ciclo que falta' : `Gerar ${toCreate.length} ciclos que faltam`}
      description={monthly > 0 ? `Base mensal: ${money(monthly)}. Os ciclos que já existem não mudam.` : 'Sem ciclo Mensal para usar como base. Os ciclos que já existem não mudam.'}
      footer={(
        <>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={saving} onClick={onCancel}>Cancelar</Button>
          <Button variant="primary" icon="zap" loading={saving} disabled={!toCreate.length || blocked} onClick={confirm}>
            {saving ? 'Gerando…' : toCreate.length === 1 ? 'Gerar ciclo' : `Gerar ${toCreate.length} ciclos`}
          </Button>
        </>
      )}
    >
      <div className="ck-cm">
        <div className="ck-section">
          <span className="ck-field__label" id="gc-margin">Margem de desconto em relação ao ciclo anterior</span>
          <div className="ck-gc__margin">
            <div className="ck-pf__chips" role="radiogroup" aria-labelledby="gc-margin">
              {MARGINS.map((m) => (
                <button key={m} type="button" role="radio" aria-checked={step === m} className="ck-pf__chip" onClick={() => setMargin(String(m))}>
                  {m === 0 ? 'Sem margem' : `+${m}%`}
                </button>
              ))}
            </div>
            <TextField aria-label="Margem personalizada" inputMode="decimal" className="ck-gc__custom" value={margin} suffix={<span className="ck-gc__pct">%</span>} onChange={(e) => setMargin(e.target.value.replace(/[^\d,.]/g, '').slice(0, 5))} />
          </div>
          <span className="ck-field__hint">Cada ciclo novo recebe o desconto do período anterior mais a margem. Ex.: mensal 0%, bimestral {step}%, trimestral {round2(step * 2)}%.</span>
        </div>

        <div className="ck-gc__table" role="table" aria-label="Prévia dos ciclos">
          <div className="ck-gc__row ck-gc__row--head" role="row">
            <span role="columnheader">Período</span>
            <span role="columnheader" className="is-num">Desconto</span>
            <span role="columnheader" className="is-num">Valor</span>
            <span role="columnheader" className="is-num">Por mês</span>
          </div>
          {rows.map((r) => (
            <div key={r.type.id} role="row" className={`ck-gc__row${r.existing ? ' is-existing' : ''}`}>
              <span role="cell">
                <strong>{r.type.nome}</strong>
                {r.existing ? <Badge tone="neutral">Já existe</Badge> : <Badge tone="success" icon="plus">Novo</Badge>}
              </span>
              <span role="cell" className="is-num">{r.discount < 0.5 ? '—' : `${Math.round(r.discount * 10) / 10}%`}</span>
              <span role="cell" className="is-num">{money(r.amount)}</span>
              <span role="cell" className="is-num">{money(r.amount / r.months)}</span>
            </div>
          ))}
        </div>

        {!toCreate.length ? <Callout tone="success" title="Todos os períodos já têm ciclo">Não há nada para gerar neste plano.</Callout> : null}
        {blocked ? <Callout tone="warning" title="Cadastre o ciclo Mensal primeiro">Os valores dos outros períodos são calculados a partir dele.</Callout> : null}

        <div className="ck-cm__switches">
          <p className="ck-gc__from">
            <Icon name="layers" size={15} />
            {base ? <>Opções copiadas do ciclo <strong>{base.label}</strong>{base.recurring ? ' com renovação no cartão' : ''}. Ajuste se quiser.</> : 'Nenhum ciclo existente para copiar as opções. Ajuste abaixo.'}
          </p>
          <Switch label="Renovação automática no cartão (Mercado Pago)" checked={recurring} onChange={setRecurring}
            description={recurring ? 'Só com Mercado Pago e cartão: o valor inteiro do ciclo é cobrado de novo a cada período. No Asaas não muda nada.' : 'O aluno paga o ciclo uma vez e renova pelo app quando vencer.'} />
          <Switch label="Permite reposição" description={makeup ? 'O limite de check-ins conta por mês, então o aluno compensa aulas perdidas.' : 'O limite de check-ins conta por semana.'} checked={makeup} onChange={setMakeup} />
          <Switch label="Criar bloqueados" description={!active ? 'Os ciclos novos ficam bloqueados: ninguém contrata até você desbloquear.' : 'Os ciclos novos já ficam disponíveis para contratar.'} checked={!active} onChange={(v) => setActive(!v)} />
        </div>
      </div>
    </Modal>
  );
}
