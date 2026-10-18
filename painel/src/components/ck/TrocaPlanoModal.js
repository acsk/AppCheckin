import React from 'react';
import { Badge, Button, Callout, Icon, Modal, SelectField, TextAreaField, TextField } from './index';

const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value) || 0);
const number = (value) => Number(String(value || 0).replace(',', '.')) || 0;

function moveChoice(event) {
  const keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
  if (!keys.includes(event.key)) return;
  const choices = [...event.currentTarget.querySelectorAll('button[role="radio"]:not(:disabled)')];
  const index = choices.indexOf(event.target);
  if (index < 0 || !choices.length) return;
  event.preventDefault();
  const next = event.key === 'Home' ? 0 : event.key === 'End' ? choices.length - 1
    : (index + (['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1) + choices.length) % choices.length;
  choices[next].focus();
  choices[next].click();
}

function Section({ step, title, description, children }) {
  return (
    <section className="ck-tp__section">
      <header className="ck-tp__sectionhead">
        <span className="ck-tp__step">{step}</span>
        <div><h3>{title}</h3><p>{description}</p></div>
      </header>
      {children}
    </section>
  );
}

export default function TrocaPlanoModal({
  open, matricula, currentCycle, plans, cycles, payments, form, onChange,
  loadingPlans, loadingCycles, loadingBalance, balance, cancelled, saving, error,
  cycleLabel, onClose, onConfirm, preview, loadingPreview,
}) {
  const [review, setReview] = React.useState(false);
  const set = (patch) => onChange((previous) => ({ ...previous, ...patch }));
  const plan = plans.find((item) => String(item.id) === String(form.plano_id));
  const cycle = cycles.find((item) => String(item.id) === String(form.plano_ciclo_id));
  const paid = payments.some((item) => Number(item.status_pagamento_id) === 2);
  const currentValue = number(matricula?.valor);
  const availableBalance = Math.max(0, number(balance?.saldo_total));
  const geraCredito = preview?.aptidao?.gera_credito !== false && !cancelled;
  const proportionalCredit = number(preview?.credito?.tipo === 'proporcional' ? preview.credito.gerado : 0);
  const generatedCredit = form.tipo_credito === 'abater_plano' ? currentValue
    : form.tipo_credito === 'abater' ? proportionalCredit
    : form.tipo_credito === 'manual' ? Math.max(0, number(form.credito)) : 0;
  const existingCredit = form.usar_credito_existente ? availableBalance : 0;
  const amountReady = !!plan && !loadingCycles && (!cycles.length || !!cycle);
  const amount = preview?.valor_plano_novo != null && amountReady ? number(preview.valor_plano_novo)
    : amountReady ? number(cycle ? cycle.valor : plan.valor) : 0;
  const usedGenerated = number(preview?.credito?.gerado != null && amountReady ? Math.min(amount, preview.credito.gerado) : Math.min(amount, generatedCredit));
  const usedExisting = number(preview?.credito?.existente != null && amountReady
    ? Math.min(preview.credito.existente, Math.max(0, amount - usedGenerated))
    : Math.min(Math.max(0, amount - usedGenerated), existingCredit));
  const total = preview?.valor_parcela != null && amountReady ? number(preview.valor_parcela) : Math.max(0, amount - generatedCredit - existingCredit);
  const remaining = preview?.credito?.restante != null && amountReady ? number(preview.credito.restante) : Math.max(0, generatedCredit + existingCredit - amount);
  const validDate = /^\d{4}-\d{2}-\d{2}$/.test(form.data_inicio) &&
    !Number.isNaN(Date.parse(`${form.data_inicio}T12:00:00`)) &&
    new Date(`${form.data_inicio}T12:00:00Z`).toISOString().slice(0, 10) === form.data_inicio;
  const ready = amountReady && validDate && !loadingPlans && !loadingBalance && !saving && !loadingPreview && !!preview?.success &&
    (form.tipo_credito !== 'manual' || (number(form.credito) > 0 && /^\d+(?:[.,]\d{1,2})?$/.test(form.credito)));
  const options = [
    { key: 'nenhum', icon: 'ban', title: 'Sem novo crédito', detail: 'Apenas o saldo existente, se marcado abaixo.' },
    ...(currentValue > 0 && paid && geraCredito ? [{ key: 'abater_plano', icon: 'wallet', title: 'Valor do plano atual', detail: `${money(currentValue)} de crédito na primeira parcela.` }] : []),
    ...(paid && geraCredito ? [{ key: 'abater', icon: 'clock', title: 'Crédito proporcional', detail: preview?.credito?.tipo === 'proporcional' && preview.credito.gerado > 0 ? `${money(preview.credito.gerado)} pelos ${preview.credito.dias_restantes} dias restantes.` : 'Calculado pelos dias restantes do plano atual.' }] : []),
    { key: 'manual', icon: 'pencil', title: 'Crédito manual', detail: 'Informe um valor e o motivo do ajuste.' },
  ];
  React.useEffect(() => { if (!open) setReview(false); }, [open]);

  return (
    <div className="ck-tp">
      <Modal
        open={open}
        size="xl"
        title={review ? 'Confirmar troca de plano' : 'Alterar plano'}
        description={review ? 'Confira o que será feito. A matrícula atual não será cancelada.' : 'Simule a troca nesta matrícula. Nada é alterado até você confirmar.'}
        leading={<span className="ck-tp__headingicon"><Icon name="repeat" size={22} /></span>}
        dismissable={!saving}
        onClose={onClose}
        footer={review ? <>
          <Button disabled={saving} onClick={() => setReview(false)}>Voltar</Button>
          <Button variant="primary" icon="check" loading={saving} disabled={!ready} onClick={onConfirm}>Confirmar troca</Button>
        </> : <>
          <span className="ck-tp__footnote"><Icon name="info" size={16} />A matrícula não é cancelada. Você confere o crédito e a parcela antes de executar.</span>
          <Button disabled={saving} onClick={onClose}>Cancelar</Button>
          <Button variant="primary" icon="arrow-right" loading={loadingPreview} disabled={!ready} onClick={() => setReview(true)}>Revisar alteração</Button>
        </>}
      >
        {error ? <Callout tone="danger" title="Não foi possível alterar o plano">{error}</Callout> : null}
        <div className="ck-tp__grid">
          {review ? (
            <div className="ck-tp__review">
              <Callout tone="info" title="A matrícula não será cancelada">
                O plano é atualizado na matrícula #{matricula?.id}. Parcelas em aberto podem ser canceladas; o vínculo do aluno permanece.
              </Callout>
              {preview?.credito?.aplicado > 0 ? (
                <Callout tone="success" title={`Crédito de ${money(preview.credito.aplicado)}`}>
                  {preview.credito.motivo || 'O crédito será abatido na primeira parcela.'}
                  {preview.credito.restante > 0 ? ` Saldo restante: ${money(preview.credito.restante)}.` : ''}
                </Callout>
              ) : (
                <Callout tone="neutral" title="Sem crédito nesta troca">
                  {preview?.aptidao?.message || 'A primeira parcela será o valor integral do novo plano.'}
                </Callout>
              )}
              <ul className="ck-tp__effects">
                {(preview?.efeitos || []).map((item) => <li key={item}>{item}</li>)}
              </ul>
            </div>
          ) : (
          <fieldset className="ck-tp__form" disabled={saving}>
            <legend className="ck-sr">Dados do novo plano</legend>
            <div className="ck-tp__current">
              <Icon name="layers" size={18} />
              <div><span>Plano atual</span><strong>{matricula?.plano_nome || '—'}</strong></div>
              <Badge>{[matricula?.modalidade_nome, currentCycle?.frequencia].filter(Boolean).join(' · ') || 'Matrícula atual'}</Badge>
            </div>
            <Section step="1" title="Plano e ciclo" description="Defina o acesso e o período de cobrança.">
              {loadingPlans ? <p role="status" className="ck-tp__wait"><Icon name="loader" className="ck-spin" size={16} />Carregando planos…</p>
                : plans.length ? <SelectField label="Novo plano" placeholder="Selecione um plano" value={form.plano_id} options={plans.map((item) => ({ value: String(item.id), label: `${item.nome} · ${money(item.valor)}` }))} onChange={(event) => set({ plano_id: event.target.value, plano_ciclo_id: '' })} />
                  : <Callout tone="warning" title="Nenhum plano disponível">Não há planos disponíveis para esta modalidade.</Callout>}
              {plan ? <p className="ck-tp__access"><Icon name="check-circle" size={15} />{number(plan.checkins_semanais) > 0 ? `${plan.checkins_semanais} check-ins por semana` : 'Check-ins ilimitados'}{number(plan.duracao_dias) > 0 ? ` · ${plan.duracao_dias} dias de acesso` : ''}</p> : null}
              <div className="ck-tp__cyclefield">
                <span className="ck-field__label" id="tp-cycles">Ciclo de pagamento</span>
                {loadingCycles ? <p role="status" className="ck-tp__wait"><Icon name="loader" className="ck-spin" size={16} />Carregando ciclos…</p>
                  : cycles.length ? <div className="ck-tp__cycles" role="radiogroup" aria-labelledby="tp-cycles" onKeyDown={moveChoice}>
                    {cycles.map((item) => {
                      const selected = String(item.id) === form.plano_ciclo_id;
                      return (
                        <button type="button" role="radio" aria-checked={selected} tabIndex={selected || (!cycle && item === cycles[0]) ? 0 : -1} className="ck-tp__cycle" key={item.id} onClick={() => set({ plano_ciclo_id: String(item.id) })}>
                          <span className="ck-tp__cycletop"><span>{cycleLabel(item)}</span><span className="ck-tp__radio">{selected ? <Icon name="check" size={12} /> : null}</span></span>
                          <strong>{money(item.valor)}</strong>
                          {number(item.meses) > 1 ? <span className="ck-tp__monthly">{money(number(item.valor) / number(item.meses))}/mês</span> : <span className="ck-tp__monthly">Valor do ciclo</span>}
                          {number(item.desconto_percentual) > 0 ? <span className="ck-tp__discount">{Number(item.desconto_percentual).toLocaleString('pt-BR')}% de desconto</span> : null}
                        </button>
                      );
                    })}
                  </div> : <p className="ck-tp__hint">{plan ? 'Este plano usa cobrança única, pelo valor do plano.' : 'Selecione um plano para ver os ciclos disponíveis.'}</p>}
              </div>
              <TextField type="date" label="Data de início" value={form.data_inicio} onChange={(event) => {
                const value = event.target.value;
                const day = Number((value || '').split('-')[2] || 0);
                set({ data_inicio: value, ...(day >= 1 && day <= 31 ? { dia_vencimento: String(day) } : {}) });
              }} hint={Number(form.dia_vencimento) >= 1 && Number(form.dia_vencimento) <= 31 ? `Vencimento das cobranças: dia ${Number(form.dia_vencimento)}.` : 'O dia de início define o vencimento das cobranças.'} />
            </Section>
            <Section step="2" title="Créditos e ajustes" description="Escolha como abater o valor da primeira parcela.">
              <div className="ck-tp__credits" role="radiogroup" aria-label="Crédito na primeira parcela" onKeyDown={moveChoice}>
                {options.map((option) => (
                  <button type="button" role="radio" aria-checked={form.tipo_credito === option.key} tabIndex={form.tipo_credito === option.key ? 0 : -1} className="ck-tp__credit" key={option.key} onClick={() => set({ tipo_credito: option.key, ...(option.key !== 'manual' ? { credito: '', motivo_credito: '' } : {}) })}>
                    <Icon name={option.icon} size={18} />
                    <span><strong>{option.title}</strong><span>{option.detail}</span></span>
                    <span className="ck-tp__radio">{form.tipo_credito === option.key ? <Icon name="check" size={12} /> : null}</span>
                  </button>
                ))}
              </div>
              {form.tipo_credito === 'manual' ? <div className="ck-tp__manual">
                <TextField label="Valor do crédito (R$)" inputMode="decimal" value={form.credito} placeholder="0,00" onChange={(event) => set({ credito: event.target.value.replace(/[^0-9.,]/g, '').replace(',', '.') })} hint="Informe um valor maior que zero, com até duas casas decimais." />
                <TextField label="Motivo do crédito" value={form.motivo_credito} placeholder="Ex.: Acordo comercial" onChange={(event) => set({ motivo_credito: event.target.value })} />
              </div> : null}
              {loadingBalance ? <p role="status" className="ck-tp__wait">Verificando créditos do aluno…</p>
                : availableBalance > 0 ? <label className="ck-tp__balance">
                  <input type="checkbox" checked={form.usar_credito_existente} onChange={(event) => set({ usar_credito_existente: event.target.checked })} />
                  <span><strong>Usar saldo de créditos do aluno</strong><span>Disponível: {money(availableBalance)}</span></span>
                  <Icon name="wallet" size={20} />
                </label> : <p className="ck-tp__hint">O aluno não tem saldo de créditos disponível.</p>}
            </Section>
            <TextAreaField label="Observações" labelAside={<span className="ck-field__hint">Opcional</span>} placeholder="Ex.: Troca de plano solicitada pelo aluno" value={form.observacoes} onChange={(event) => set({ observacoes: event.target.value })} rows={2} />
          </fieldset>
          )}
          <aside className="ck-tp__summary" aria-label="Resumo da primeira cobrança">
            <span className="ck-tp__eyebrow"><Icon name="receipt" size={16} />Resumo da alteração</span>
            <h3>{plan?.nome || 'Seu novo plano'}</h3>
            <p className="ck-tp__summarycycle">{loadingCycles ? 'Carregando ciclos…' : cycle ? cycleLabel(cycle) : plan && !cycles.length ? 'Cobrança única' : 'Escolha um ciclo de pagamento'}</p>
            <dl>
              <div><dt>Valor {cycle ? 'do ciclo' : 'do plano'}</dt><dd>{amountReady ? money(amount) : '—'}</dd></div>
              {usedGenerated > 0 && amountReady ? <div className="ck-tp__deduction"><dt>{form.tipo_credito === 'manual' ? 'Crédito manual' : form.tipo_credito === 'abater' ? 'Crédito proporcional' : 'Crédito do plano atual'}</dt><dd>− {money(usedGenerated)}</dd></div> : null}
              {existingCredit > 0 && amountReady ? <div className="ck-tp__deduction"><dt>Saldo de créditos</dt><dd>− {money(usedExisting)}</dd></div> : null}
            </dl>
            <div className="ck-tp__total" aria-live="polite" aria-atomic="true">
              <span>Primeira parcela</span>
              <strong>{!amountReady || loadingPreview ? 'R$ —' : money(total)}</strong>
              <span>{loadingPreview ? 'Calculando a troca…' : preview?.success ? 'Prévia da troca nesta matrícula, sem cancelar.' : 'Selecione o plano para simular a troca.'}</span>
            </div>
            {amountReady && remaining > 0 ? <p className="ck-tp__remaining"><Icon name="wallet" size={16} />{money(remaining)} permanecerá como crédito do aluno.</p> : null}
            {!amountReady ? <p className="ck-tp__summaryhint">Selecione o plano e o ciclo para conferir os valores.</p> : null}
            {form.data_inicio && validDate ? <div className="ck-tp__start"><Icon name="calendar" size={16} /><span>Início em <strong>{form.data_inicio.split('-').reverse().join('/')}</strong></span></div> : null}
          </aside>
        </div>
      </Modal>
    </div>
  );
}
