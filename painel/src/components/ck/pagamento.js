/* AppCheckin — EditarPagamentoModal, portado do design system (estilos em src/theme/ck-pagamento.css).
   Adaptado: parcela com baixa automática do provedor abre só para consulta (o backend também recusa a edição),
   e a forma de pagamento não é editável aqui (a API de edição não troca a forma). */
import React, { useState } from 'react';
import { Button, Callout, Icon, Modal, TextAreaField, TextField } from './index';

const cx = (...a) => a.filter(Boolean).join(' ');
const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const money = (v) => BRL.format(Number(v) || 0);
const fdate = (iso) => { if (!iso) return '—'; const [y, m, d] = String(iso).slice(0, 10).split('-'); return `${d}/${m}/${y}`; };
const dayDiff = (a, b) => Math.round((new Date(`${String(b).slice(0, 10)}T00:00:00`) - new Date(`${String(a).slice(0, 10)}T00:00:00`)) / 86400000);
const parseMoney = (v) => { if (typeof v === 'number') return v; let s = String(v || '').replace(/[^0-9.,]/g, ''); if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.'); const n = parseFloat(s); return Number.isNaN(n) ? null : n; };
const moneyInput = (n) => (n == null ? '' : Number(n).toFixed(2).replace('.', ','));

const STATUS = [[1, 'Aguardando', 'warning'], [2, 'Pago', 'success'], [3, 'Atrasado', 'danger'], [4, 'Cancelado', 'outline']];
const BAIXA_INTEGRACAO = 4;

/** Parcela paga pela integração com o provedor: só consulta. */
export function isBaixaAutomatica(pg) {
  if (!pg || Number(pg.status_pagamento_id) !== 2) return false;
  return Number(pg.tipo_baixa_id) === BAIXA_INTEGRACAO || /integra/i.test(String(pg.tipo_baixa_nome || ''));
}

/** Quem deu a baixa, pelo que ficou registrado na parcela. */
export function provedorDaBaixa(pg) {
  const obs = String(pg?.observacoes || '');
  if (/\[Asaas pay_|via Asaas/i.test(obs)) return 'Asaas';
  if (/Mercado Pago|Payment #/i.test(obs)) return 'Mercado Pago';
  return 'provedor';
}

function registroProvedor(pg) {
  const obs = String(pg?.observacoes || '');
  const id = (obs.match(/\[Asaas (pay_[A-Za-z0-9]+)\]/) || obs.match(/Payment #(\d+)/) || obs.match(/\bID: (\d+)/) || [])[1];
  const num = (v) => (v != null && v !== '' && Number(v) !== 0 ? Number(v) : null);
  return [
    ['Provedor', provedorDaBaixa(pg)],
    id ? ['ID da cobrança', id, true] : null,
    ['Valor cobrado', money(pg.valor)],
    num(pg.valor_liquido) != null ? ['Líquido recebido', money(pg.valor_liquido)] : null,
    num(pg.desconto_provedor) != null ? ['Desconto do provedor', money(pg.desconto_provedor)] : null,
    num(pg.tarifa_cartao) != null ? ['Tarifa do cartão', money(pg.tarifa_cartao)] : null,
    num(pg.sobretaxa_antecipacao) != null ? ['Sobretaxa de antecipação', money(pg.sobretaxa_antecipacao)] : null,
    pg.forma_pagamento_nome ? ['Forma', pg.forma_pagamento_nome] : null,
    obs ? ['Observação gravada', obs] : null,
  ].filter(Boolean);
}

function CopyId({ value }) {
  const [ok, setOk] = useState(false);
  return (
    <span className="ck-ep__copy">
      <code>{value}</code>
      <button type="button" className="ck-ep__copybtn" aria-label={ok ? 'Copiado' : 'Copiar ID'} title={ok ? 'Copiado' : 'Copiar ID'}
        onClick={() => { try { navigator.clipboard.writeText(String(value)); } catch (e) { /* sem clipboard */ } setOk(true); setTimeout(() => setOk(false), 1400); }}>
        <Icon name={ok ? 'check' : 'copy'} size={14} />
      </button>
    </span>
  );
}

/**
 * payment: linha da API (pagamentos_plano). context: texto do cabeçalho (ex.: "Matrícula #63, Natação").
 * onSave(payload) recebe { desconto, motivo_desconto, data_vencimento, data_pagamento, status_pagamento_id, observacoes }.
 */
export function EditarPagamentoModal({ open, payment, context, saving, onSave, onCancel }) {
  const pg = payment || {};
  const readOnly = isBaixaAutomatica(pg);
  const provedor = provedorDaBaixa(pg);
  const [init] = useState(() => ({
    discount: moneyInput(pg.desconto || 0), reason: pg.motivo_desconto || '', due: String(pg.data_vencimento || '').slice(0, 10),
    paid: String(pg.data_pagamento || '').slice(0, 10), status: Number(pg.status_pagamento_id) || 1, notes: pg.observacoes || '',
  }));
  const [f, setF] = useState(init);
  const [tried, setTried] = useState(false);
  const [log, setLog] = useState(readOnly);
  const set = (k, v) => setF((cur) => ({ ...cur, [k]: v }));

  const amount = Number(pg.valor) || 0;
  const disc = parseMoney(f.discount) || 0;
  const isPaid = f.status === 2;
  const final = Math.max(0, amount - disc);
  const err = {};
  if (disc < 0 || disc > amount) err.discount = `O desconto precisa ficar entre R$ 0,00 e ${money(amount)}.`;
  if (disc > 0 && !f.reason.trim()) err.reason = 'Diga o motivo do desconto. Ele fica no histórico.';
  if (!f.due) err.due = 'Informe o vencimento.';
  if (isPaid && !f.paid) err.paid = 'Informe quando foi pago.';
  const show = tried ? err : {};
  const dirty = JSON.stringify(f) !== JSON.stringify(init);
  const lateDays = isPaid && f.paid && f.due ? dayDiff(f.due, f.paid) : 0;

  const save = () => {
    setTried(true);
    if (Object.keys(err).length) return;
    onSave && onSave({
      desconto: disc, motivo_desconto: disc > 0 ? f.reason.trim() : null, data_vencimento: f.due,
      data_pagamento: isPaid ? f.paid : null, status_pagamento_id: f.status, observacoes: f.notes.trim() || null,
    });
  };

  const id = pg.id ?? pg.pagamento_id;
  return (
    <Modal
      open={open}
      size="md"
      onClose={onCancel}
      autoFocus={false}
      title={readOnly ? `Parcela #${id}` : `Editar parcela #${id}`}
      description={[context, pg.data_vencimento ? `vence em ${fdate(pg.data_vencimento)}` : null].filter(Boolean).join(', ')}
      footer={(
        <div className="ck-adm__foot">
          {readOnly ? <Button variant="primary" onClick={onCancel}>Fechar</Button> : (
            <>
              <Button variant="ghost" onClick={onCancel}>Cancelar</Button>
              <Button variant="primary" icon="save" loading={saving} disabled={!dirty} onClick={save}>{saving ? 'Salvando…' : 'Salvar alterações'}</Button>
            </>
          )}
        </div>
      )}
    >
      <div className="ck-ep">
        {readOnly ? (
          <Callout tone="info" icon="lock" title={`Baixa automática pelo ${provedor}`}>
            {`Esta parcela foi paga e confirmada pelo ${provedor}, por isso só pode ser consultada. Para estornar ou corrigir, faça no ${provedor}.`}
          </Callout>
        ) : null}

        <div className="ck-ep__sum">
          <div><span><Icon name="lock" size={12} />Valor da parcela</span><strong>{money(amount)}</strong></div>
          <div><span>Desconto</span><strong className={disc ? 'is-disc' : undefined}>{disc ? `−${money(disc)}` : 'R$ 0,00'}</strong></div>
          <div className="is-final"><span>{isPaid ? 'Valor recebido' : 'Valor a cobrar'}</span><strong>{money(final)}</strong></div>
        </div>

        <div className="ck-field">
          <span className="ck-field__label" id="ep-st">Situação</span>
          <div className="ck-ep__status" role="radiogroup" aria-labelledby="ep-st">
            {STATUS.map(([sid, label, tone]) => (
              <button key={sid} type="button" role="radio" aria-checked={f.status === sid} disabled={readOnly && f.status !== sid}
                className={cx('ck-ml__chip', `ck-ep__chip--${tone}`)} onClick={() => !readOnly && set('status', sid)}>
                <span className={`ck-ml__chipdot ck-ml__chipdot--${tone}`} aria-hidden="true" />{label}
              </button>
            ))}
          </div>
        </div>

        <div className="ck-al-grid">
          <TextField type="date" label="Vencimento" value={f.due} error={show.due} readOnly={readOnly} onChange={(e) => set('due', e.target.value)} />
          {isPaid ? (
            <TextField type="date" label="Pago em" value={f.paid} error={show.paid} readOnly={readOnly}
              hint={!show.paid && lateDays > 0 ? `Pago ${lateDays} ${lateDays === 1 ? 'dia' : 'dias'} depois do vencimento.` : (!show.paid && lateDays < 0 ? 'Pago antes do vencimento.' : null)}
              onChange={(e) => set('paid', e.target.value)} />
          ) : <div />}
        </div>

        {readOnly ? null : (
          <div className="ck-ep__disc">
            <TextField label="Desconto" prefix="R$" inputMode="decimal" value={f.discount} error={show.discount}
              onChange={(e) => set('discount', e.target.value.replace(/[^\d,.]/g, ''))} onBlur={() => set('discount', moneyInput(parseMoney(f.discount) || 0))} />
            {disc > 0
              ? <TextField label="Motivo do desconto" value={f.reason} error={show.reason} placeholder="Ex.: desconto de indicação" onChange={(e) => set('reason', e.target.value)} />
              : <p className="ck-ep__hint">Com desconto, o motivo passa a ser obrigatório.</p>}
          </div>
        )}

        {readOnly ? null : (
          <TextAreaField label="Observações" value={f.notes} placeholder="Anotações da equipe sobre esta parcela" hint="Só a equipe vê." onChange={(e) => set('notes', e.target.value)} />
        )}

        {readOnly || provedor !== 'provedor' ? (
          <div className="ck-ep__log">
            <button type="button" className="ck-ep__logbtn" aria-expanded={log} onClick={() => setLog(!log)}>
              <Icon name="receipt" size={15} />Registro do provedor
              <Icon name="chevron-down" size={15} className={cx('ck-ep__chev', log && 'is-open')} />
            </button>
            {log ? (
              <dl className="ck-ep__tech">
                {registroProvedor(pg).map(([label, value, copy]) => (
                  <div key={label}><dt>{label}</dt><dd>{copy ? <CopyId value={value} /> : value}</dd></div>
                ))}
              </dl>
            ) : null}
          </div>
        ) : null}
      </div>
    </Modal>
  );
}
