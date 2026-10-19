/* AppCheckin — Detalhe do contrato da academia (super admin).
   Estilos: src/theme/ck-contrato-detalhe.css + ck-mt-* (ck.css) e ck-ct-logo (ck-contratos.css). */
import React from 'react';
import { ActionMenu, Badge, Button, Callout, Icon } from './index';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
export const money = (v) => BRL.format(Number(v) || 0);

const pd = (v) => {
  if (!v) return null;
  const [y, m, d] = String(v).slice(0, 10).split('-').map(Number);
  return new Date(y, m - 1, d);
};
export const fdate = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—');
const fdatetime = (v) => (v ? `${fdate(v)} às ${String(v).slice(11, 16) || '00:00'}` : '—');
export const dayDiff = (a, b) => Math.round((pd(b) - pd(a)) / 86400000);
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;
const initials = (name) => {
  const w = String(name || '').trim().split(/\s+/);
  return ((w[0] || '')[0] + (w.length > 1 ? w[w.length - 1][0] : '')).toUpperCase();
};

const CONTRACT_STATUS = {
  1: ['success', 'Ativo'],
  2: ['warning', 'Pendente'],
  3: ['neutral', 'Cancelado'],
  4: ['danger', 'Bloqueado'],
};
const PAY_STATUS = {
  aguardando: ['warning', 'Aguardando'],
  pago: ['success', 'Pago'],
  atrasado: ['danger', 'Atrasado'],
  cancelado: ['neutral', 'Cancelado'],
};

export function methodIcon(name) {
  const n = String(name || '').toLowerCase();
  if (n.includes('pix')) return 'zap';
  if (n.includes('boleto')) return 'receipt';
  if (n.includes('cart') || n.includes('créd') || n.includes('déb')) return 'credit-card';
  return 'wallet';
}

/** Situação real da cobrança: "aguardando" com vencimento passado conta como atrasada. */
export function payStatus(p, today) {
  const id = Number(p.status_pagamento_id);
  if (id === 2) return 'pago';
  if (id === 4) return 'cancelado';
  if (id === 3 || (p.data_vencimento && dayDiff(today, p.data_vencimento) < 0)) return 'atrasado';
  return 'aguardando';
}

function priceOf(c) {
  if (c.tipo_cobranca === 'gratuito') return { value: 'Grátis', per: c.data_fim ? 'até o fim do contrato' : 'sem cobrança' };
  if (c.tipo_cobranca === 'por_aluno') return { value: money(c.valor_por_aluno), per: 'por aluno ativo/mês' };
  const dias = Number(c.duracao_dias) || 30;
  return { value: money(c.plano_valor), per: dias === 30 ? 'por mês' : dias === 365 ? 'por ano' : `a cada ${dias} dias` };
}

function PlanCard({ contract: c, today, method }) {
  const price = priceOf(c);
  const total = c.data_fim ? Math.max(1, dayDiff(c.data_inicio, c.data_fim)) : null;
  const left = c.data_fim ? dayDiff(today, c.data_fim) : null;
  const pct = total ? Math.min(100, Math.max(0, Math.round(((total - Math.max(0, left)) / total) * 100))) : null;

  return (
    <section className="ck-mt-plan ck-cd-plan" aria-label="Plano contratado">
      <div className="ck-mt-plan__top">
        <span className="ck-mt-plan__icon" aria-hidden="true"><Icon name="layers" size={20} strokeWidth={2} /></span>
        <div style={{ flex: 1, minWidth: 0 }}>
          <span className="ck-mt-plan__freq">Plano contratado</span>
          <h2 className="ck-mt-plan__name">{c.plano_nome}</h2>
        </div>
      </div>
      <div className="ck-mt-plan__price">
        <span className="ck-mt-plan__value">{price.value}</span>
        <span className="ck-mt-plan__per">{price.per}</span>
      </div>
      <div className="ck-mt-plan__access">
        <div className="ck-mt-plan__row">
          <span>Vigência</span>
          <strong>
            {left == null ? 'Sem data de fim' : left > 0 ? plural(left, 'dia restante', 'dias restantes') : left === 0 ? 'Termina hoje' : 'Encerrada'}
          </strong>
        </div>
        {pct != null ? (
          <div className="ck-mt-plan__track" role="progressbar" aria-label="Vigência usada" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100}>
            <span className="ck-mt-plan__fill" style={{ width: `${Math.max(pct, 2)}%` }} />
          </div>
        ) : null}
        <div className="ck-mt-plan__row ck-mt-plan__dates">
          <span>{c.data_fim ? `${fdate(c.data_inicio)} a ${fdate(c.data_fim)}` : `${fdate(c.data_inicio)}, vale até ser encerrado`}</span>
        </div>
      </div>
      <dl className="ck-mt-plan__facts">
        <div><dt>Pagamento</dt><dd>{c.tipo_cobranca === 'gratuito' ? 'Sem cobrança' : method || '—'}</dd></div>
        <div><dt>Limite</dt><dd>{c.max_alunos ? plural(Number(c.max_alunos), 'aluno', 'alunos') : 'Sem limite'}</dd></div>
      </dl>
    </section>
  );
}

function Stats({ totals }) {
  return (
    <section className="ck-card ck-cd-stats" aria-label="Resumo financeiro">
      <div className="ck-cd-stat">
        <span className="ck-cd-stat__label">Total cobrado</span>
        <strong className="ck-cd-stat__value">{money(totals.billed)}</strong>
      </div>
      <div className="ck-cd-stat">
        <span className="ck-cd-stat__label">Recebido</span>
        <strong className="ck-cd-stat__value is-success">{money(totals.paid)}</strong>
      </div>
      <div className="ck-cd-stat">
        <span className="ck-cd-stat__label">
          {totals.late > 0 ? `Em aberto, ${money(totals.late)} atrasado` : 'Em aberto'}
        </span>
        <strong className={`ck-cd-stat__value${totals.late > 0 ? ' is-danger' : ''}`}>{money(totals.open)}</strong>
      </div>
    </section>
  );
}

function Payments({ payments, today, onSettle, onAdd, onCancel, canAdd }) {
  const visible = payments.filter((p) => p.status !== 'cancelado');
  const cancelled = payments.length - visible.length;
  const rows = [...visible, ...payments.filter((p) => p.status === 'cancelado')];
  return (
    <section className="ck-card ck-cd-pays" aria-labelledby="cd-pays">
      <div className="ck-cd-box__head">
        <div>
          <h2 className="ck-mt-box__title" id="cd-pays">Pagamentos</h2>
          <p className="ck-cd-box__sub">
            {visible.length ? `${plural(visible.length, 'cobrança', 'cobranças')} deste contrato.` : 'Nenhuma cobrança neste contrato.'}
            {cancelled ? ` ${plural(cancelled, 'cancelada', 'canceladas')}.` : ''}
          </p>
        </div>
        {canAdd ? <Button variant="ghost" icon="plus" onClick={onAdd}>Lançar pagamento</Button> : null}
      </div>
      {rows.length ? (
        <div className="ck-mt-table-wrap">
          <table className="ck-mt-table">
            <thead>
              <tr>
                <th scope="col">Vencimento</th>
                <th scope="col" className="is-num">Valor</th>
                <th scope="col">Situação</th>
                <th scope="col">Pago em</th>
                <th scope="col"><span className="ck-sr">Ações</span></th>
              </tr>
            </thead>
            <tbody>
              {rows.map((p) => {
                const st = PAY_STATUS[p.status];
                const open = p.status === 'aguardando' || p.status === 'atrasado';
                const diff = open ? dayDiff(today, p.dueDate) : null;
                const menu = [
                  p.gatewayUrl ? { label: 'Abrir cobrança no Asaas', icon: 'arrow-right', onClick: () => window.open(p.gatewayUrl, '_blank', 'noopener') } : null,
                  open ? { label: 'Cancelar cobrança', icon: 'ban', danger: true, onClick: () => onCancel(p) } : null,
                ].filter(Boolean);
                return (
                  <tr key={p.id} className={p.status === 'cancelado' ? 'is-cancelled' : open ? 'is-due' : undefined}>
                    <td>
                      <span className="ck-mt-cell__main">{fdate(p.dueDate)}</span>
                      <span className={`ck-mt-cell__sub${p.status === 'atrasado' ? ' is-late' : ''}`}>
                        Cobrança #{p.id}
                        {diff != null && diff >= 0 && diff <= 7 ? ` · ${diff === 0 ? 'vence hoje' : `em ${plural(diff, 'dia', 'dias')}`}` : ''}
                        {p.status === 'atrasado' && diff != null ? ` · há ${plural(-diff, 'dia', 'dias')}` : ''}
                      </span>
                    </td>
                    <td className="is-num"><span className="ck-mt-cell__main ck-mt-money">{money(p.amount)}</span></td>
                    <td><Badge tone={st[0]} dot>{st[1]}</Badge></td>
                    <td>
                      {p.paidDate ? (
                        <>
                          <span className="ck-mt-cell__main">{fdate(p.paidDate)}</span>
                          {p.method ? <span className="ck-mt-cell__sub ck-cd-method"><Icon name={methodIcon(p.method)} size={13} />{p.method}</span> : null}
                        </>
                      ) : <span className="ck-mt-cell__sub">—</span>}
                    </td>
                    <td className="ck-mt-actions">
                      {open ? <Button size="sm" variant="secondary" onClick={() => onSettle(p)}>Dar baixa</Button> : null}
                      {menu.length ? <ActionMenu label={`Ações da cobrança ${p.id}`} items={menu} /> : null}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="ck-cd-empty">
          <Icon name="receipt" size={20} />
          <span>{canAdd ? 'Lance o primeiro pagamento ou aguarde a cobrança automática.' : 'Contrato sem cobrança.'}</span>
        </div>
      )}
    </section>
  );
}

function History({ note, events }) {
  return (
    <section className="ck-card ck-cd-hist" aria-labelledby="cd-hist">
      <h2 className="ck-mt-box__title" id="cd-hist">Histórico</h2>
      {note ? <p className="ck-cd-note"><Icon name="file-text" size={15} />{note}</p> : null}
      {events.length ? (
        <ol className="ck-cd-timeline">
          {events.map((e, i) => (
            <li key={`${e.at}-${i}`} className={e.tone ? `is-${e.tone}` : undefined}>
              <strong>{e.title}</strong>
              <span>{fdatetime(e.at)}{e.by ? `, por ${e.by}` : ''}</span>
            </li>
          ))}
        </ol>
      ) : <p className="ck-cd-box__sub">Sem eventos ainda.</p>}
    </section>
  );
}

/**
 * contract: linha de GET /superadmin/contratos/:id.
 * payments: [{ id, dueDate, amount, status, paidDate, method, gatewayUrl }] (status já resolvido com payStatus).
 * events: [{ at, title, tone?, by? }] do mais recente para o mais antigo.
 */
export default function ContratoDetalhe({
  contract: c, payments, events, today, onBack, onOpenAcademy, onChangePlan, onRenew, onCancelContract,
  onSettle, onAddPayment, onCancelPayment,
}) {
  const [tone, label] = CONTRACT_STATUS[Number(c.status_id)] || ['neutral', c.status_nome || '—'];
  const cancelled = Number(c.status_id) === 3;
  const paid = c.tipo_cobranca !== 'gratuito';
  // Cobranças de R$ 0,00 (sobras de plano grátis) não pedem baixa nem geram aviso
  const open = payments.filter((p) => (p.status === 'aguardando' || p.status === 'atrasado') && p.amount > 0);
  const late = open.filter((p) => p.status === 'atrasado').sort((a, b) => pd(a.dueDate) - pd(b.dueDate));
  const soon = open.filter((p) => p.status === 'aguardando' && dayDiff(today, p.dueDate) <= 7).sort((a, b) => pd(a.dueDate) - pd(b.dueDate));
  const alertPay = late[0] || soon[0] || null;
  const left = c.data_fim ? dayDiff(today, c.data_fim) : null;
  const lastPaid = payments.find((p) => p.status === 'pago' && p.method);
  const method = lastPaid?.method || payments.find((p) => p.method)?.method || null;

  const totals = payments.reduce((t, p) => {
    if (p.status === 'cancelado') return t;
    t.billed += p.amount;
    if (p.status === 'pago') t.paid += p.amount;
    else t.open += p.amount;
    if (p.status === 'atrasado') t.late += p.amount;
    return t;
  }, { billed: 0, paid: 0, open: 0, late: 0 });

  const menu = [
    { label: 'Abrir academia', icon: 'building-2', onClick: onOpenAcademy },
    !cancelled && paid ? { label: 'Lançar pagamento', icon: 'plus', onClick: onAddPayment } : null,
    !cancelled && c.data_fim ? { label: 'Renovar contrato', icon: 'refresh-cw', onClick: onRenew } : null,
    !cancelled ? '-' : null,
    !cancelled ? { label: 'Encerrar contrato', icon: 'ban', danger: true, onClick: onCancelContract } : null,
  ].filter(Boolean);

  return (
    <div className="ck-mt ck-cd">
      <div className="ck-mt-hero">
        <button type="button" className="ck-mt-back" onClick={onBack}><Icon name="arrow-left" size={16} />Contratos</button>
        <div className="ck-mt-hero__row">
          <span className="ck-ct-logo ck-ct-logo--lg" aria-hidden="true">{initials(c.tenant_nome)}</span>
          <div className="ck-mt-hero__text">
            <div className="ck-mt-hero__titleline">
              <h1 className="ck-mt-hero__name">Contrato #{c.id}</h1>
              <Badge tone={tone} dot>{label}</Badge>
            </div>
            <ul className="ck-mt-hero__meta">
              <li><Icon name="building-2" size={15} /><button type="button" className="ck-cd-link" onClick={onOpenAcademy}>{c.tenant_nome}</button></li>
              <li><Icon name="layers" size={15} />Plano {c.plano_nome}</li>
              <li><Icon name="calendar" size={15} />Desde {fdate(c.data_inicio)}</li>
            </ul>
          </div>
          <div className="ck-mt-hero__actions">
            {!cancelled ? <Button variant="secondary" icon="repeat" onClick={onChangePlan}>Trocar plano</Button> : null}
            <ActionMenu label="Mais ações do contrato" items={menu} />
          </div>
        </div>
      </div>

      {cancelled ? (
        <Callout tone="neutral" icon="ban" title="Contrato cancelado">
          A academia precisa de um novo contrato para voltar a usar o painel.
        </Callout>
      ) : null}

      {!cancelled && Number(c.status_id) === 2 && !alertPay ? (
        <Callout tone="warning" icon="clock" title="Aguardando o primeiro pagamento">
          O contrato é ativado quando a primeira cobrança for paga.
        </Callout>
      ) : null}

      {!cancelled && alertPay ? (
        <Callout
          tone={alertPay.status === 'atrasado' ? 'danger' : 'warning'}
          icon={alertPay.status === 'atrasado' ? 'alert-triangle' : 'clock'}
          title={`Cobrança de ${money(alertPay.amount)} ${alertPay.status === 'atrasado'
            ? `venceu em ${fdate(alertPay.dueDate)}`
            : dayDiff(today, alertPay.dueDate) === 0 ? 'vence hoje' : `vence em ${fdate(alertPay.dueDate)}`}`}
          action={<Button variant="primary" icon="check-circle" onClick={() => onSettle(alertPay)}>Dar baixa</Button>}
        >
          {alertPay.status === 'atrasado'
            ? `Sem o pagamento, o painel da academia pode ser bloqueado.${late.length > 1 ? ` Há ${late.length} cobranças atrasadas.` : ''}`
            : 'A academia já recebeu a cobrança.'}
        </Callout>
      ) : null}

      {!cancelled && left != null && left <= 30 ? (
        <Callout
          tone={left < 0 ? 'danger' : 'warning'}
          icon="calendar"
          title={left < 0 ? `O contrato terminou em ${fdate(c.data_fim)}` : left === 0 ? 'O contrato termina hoje' : `O contrato termina em ${plural(left, 'dia', 'dias')}`}
          action={<Button variant="secondary" icon="refresh-cw" onClick={onRenew}>Renovar</Button>}
        >
          {left < 0 ? 'O painel da academia está bloqueado até a renovação ou um novo contrato.' : 'Depois disso o painel da academia fica bloqueado até um novo contrato.'}
        </Callout>
      ) : null}

      <div className="ck-cd-grid">
        <div className="ck-cd-side">
          <PlanCard contract={c} today={today} method={method} />
        </div>
        <div className="ck-cd-main">
          {/* Plano grátis sem cobranças: uma frase no lugar do resumo zerado e da tabela vazia */}
          {!paid && !payments.length ? (
            <section className="ck-card ck-cd-free">
              <span className="ck-cd-free__icon" aria-hidden="true"><Icon name="check-circle" size={18} /></span>
              <div>
                <strong>Plano grátis, sem cobranças</strong>
                <span>Nenhum pagamento é gerado enquanto o contrato estiver no plano {c.plano_nome}. Para começar a cobrar, troque o plano.</span>
              </div>
            </section>
          ) : (
            <>
              {paid || payments.length ? <Stats totals={totals} /> : null}
              <Payments
                payments={payments}
                today={today}
                canAdd={!cancelled && paid}
                onSettle={onSettle}
                onAdd={onAddPayment}
                onCancel={onCancelPayment}
              />
            </>
          )}
          <History note={c.observacoes} events={events} />
        </div>
      </div>
    </div>
  );
}
