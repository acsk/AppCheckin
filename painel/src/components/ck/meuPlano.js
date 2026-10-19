/* AppCheckin — Plano do Check-in, visto pelo admin da academia: plano atual, cobranças e pagamento
   pelo Asaas da conta do sistema. Estilos: ck-contrato-detalhe.css (ck-cd-*, ck-mt-plan) + ck-contratos.css (ck-ct-pay)
   + bloco "Meu plano" em ck-contratos.css. */
import React, { useState } from 'react';
import { Badge, Button, Callout, Icon, IconButton, Modal, PageHeader } from './index';
import { fdate, money, payStatus } from './contratoDetalhe';

const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;
const CONTRACT_STATUS = { 1: ['success', 'Ativo'], 2: ['warning', 'Aguardando pagamento'], 3: ['neutral', 'Cancelado'], 4: ['danger', 'Bloqueado'] };
const PAY_STATUS = { aguardando: ['warning', 'Aguardando'], pago: ['success', 'Pago'], atrasado: ['danger', 'Atrasado'], cancelado: ['neutral', 'Cancelado'] };

export const BILLING = {
  PIX: { name: 'Pix', icon: 'qr-code', hint: 'Aprovação na hora' },
  CREDIT_CARD: { name: 'Cartão de crédito', icon: 'credit-card', hint: 'Na página segura do Asaas' },
  BOLETO: { name: 'Boleto', icon: 'receipt', hint: 'Compensa em até 3 dias úteis' },
};

export function priceText(pl) {
  if (!pl) return '—';
  if (pl.tipo_cobranca === 'gratuito') return 'Grátis';
  if (pl.tipo_cobranca === 'por_aluno') return `${money(pl.valor_por_aluno)} por aluno ativo`;
  const d = Number(pl.duracao_dias) || 30;
  return `${money(pl.valor)} ${d === 30 ? 'por mês' : d === 365 ? 'por ano' : `a cada ${d} dias`}`;
}

function PlanCard({ contract: c, alunos }) {
  const pl = c.plano || {};
  const free = pl.tipo_cobranca === 'gratuito';
  const [value, ...rest] = priceText(pl).split(' ');
  return (
    <section className="ck-mt-plan ck-cd-plan" aria-label="Plano atual">
      <div className="ck-mt-plan__top">
        <span className="ck-mt-plan__icon" aria-hidden="true"><Icon name="layers" size={20} strokeWidth={2} /></span>
        <div style={{ flex: 1, minWidth: 0 }}>
          <span className="ck-mt-plan__freq">Plano atual</span>
          <h2 className="ck-mt-plan__name">{pl.nome}</h2>
        </div>
      </div>
      <div className="ck-mt-plan__price">
        <span className="ck-mt-plan__value">{free ? 'Grátis' : pl.tipo_cobranca === 'por_aluno' ? money(pl.valor_por_aluno) : value}</span>
        <span className="ck-mt-plan__per">{free ? 'sem cobrança' : pl.tipo_cobranca === 'por_aluno' ? 'por aluno ativo' : rest.join(' ')}</span>
      </div>
      <div className="ck-mt-plan__access">
        <div className="ck-mt-plan__row"><span>Vigência</span><strong>{c.data_fim ? `Até ${fdate(c.data_fim)}` : 'Sem data de fim'}</strong></div>
        <div className="ck-mt-plan__row ck-mt-plan__dates"><span>Desde {fdate(c.data_inicio)}</span></div>
      </div>
      <dl className="ck-mt-plan__facts">
        <div><dt>Alunos ativos</dt><dd>{pl.max_alunos ? `${alunos} de ${pl.max_alunos}` : String(alunos)}</dd></div>
        <div><dt>Administradores</dt><dd>{pl.max_admins ? `Até ${pl.max_admins}` : 'Sem limite'}</dd></div>
      </dl>
    </section>
  );
}

export function MeuPlano({ data, today, onPay, onChangePlan, onOpenInvoice }) {
  const c = data.contrato;
  const alunos = Number(data.alunos_ativos) || 0;
  const pays = (data.pagamentos || []).map((p) => ({ ...p, st: payStatus(p, today), amount: Number(p.valor) || 0 }));
  const open = pays.filter((p) => p.st === 'aguardando' || p.st === 'atrasado')
    .sort((a, b) => (a.st === 'atrasado' ? 0 : 1) - (b.st === 'atrasado' ? 0 : 1) || String(a.data_vencimento).localeCompare(String(b.data_vencimento)));
  const next = open[0];
  const pl = c?.plano;
  const overLimit = pl?.max_alunos && alunos > pl.max_alunos;
  const canPay = (data.formas || []).length > 0;

  if (!c) {
    return (
      <div className="ck-mp">
        <PageHeader title="Plano do Check-in" description="O plano que a sua academia usa no Check-in." />
        <Callout tone="warning" title="A academia não tem contrato com o Check-in"
          action={onChangePlan ? <Button variant="primary" icon="layers" onClick={onChangePlan}>Escolher um plano</Button> : null}>
          Escolha um plano para continuar usando o painel e o app.
        </Callout>
      </div>
    );
  }

  const [tone, label] = CONTRACT_STATUS[Number(c.status_id)] || ['neutral', c.status_nome || '—'];
  return (
    <div className="ck-mp">
      <PageHeader
        title="Plano do Check-in"
        description="O plano que a sua academia usa no Check-in, as cobranças e o pagamento."
        actions={onChangePlan ? <Button icon="repeat" onClick={onChangePlan}>Trocar plano</Button> : null}
      />

      {Number(c.status_id) === 2 && next ? (
        <Callout tone="warning" icon="clock" title={`Pague ${money(next.amount)} para ativar o plano ${pl?.nome}`}
          action={canPay ? <Button variant="primary" icon="check-circle" onClick={() => onPay(next)}>{next.tem_cobranca ? 'Abrir cobrança' : 'Pagar agora'}</Button> : null}>
          O plano é ativado assim que o pagamento for confirmado. Pelo Pix, isso leva alguns segundos.
        </Callout>
      ) : next ? (
        <Callout tone={next.st === 'atrasado' ? 'danger' : 'warning'} icon={next.st === 'atrasado' ? 'alert-triangle' : 'clock'}
          title={`Cobrança de ${money(next.amount)} ${next.st === 'atrasado' ? `venceu em ${fdate(next.data_vencimento)}` : `vence em ${fdate(next.data_vencimento)}`}`}
          action={canPay ? <Button variant="primary" icon="check-circle" onClick={() => onPay(next)}>{next.tem_cobranca ? 'Abrir cobrança' : 'Pagar agora'}</Button> : null}>
          {next.st === 'atrasado' ? 'Sem o pagamento, o painel da academia pode ser bloqueado.' : 'Pague pelo Pix ou cartão, aqui mesmo.'}
        </Callout>
      ) : null}

      {overLimit ? (
        <Callout tone="warning" title={`A academia tem ${alunos} alunos ativos`}
          action={onChangePlan ? <Button onClick={onChangePlan}>Ver planos</Button> : null}>
          O plano {pl.nome} aceita até {pl.max_alunos}. Troque para um plano maior para matricular novos alunos.
        </Callout>
      ) : null}

      {!canPay && open.length ? (
        <Callout tone="info" title="Pagamento online indisponível">O Check-in envia a cobrança por e-mail. Se precisar, fale com o suporte.</Callout>
      ) : null}

      <div className="ck-cd-grid">
        <div className="ck-cd-side">
          <PlanCard contract={c} alunos={alunos} />
          <p className="ck-mp__status"><Badge tone={tone} dot>{label}</Badge><span>Contrato #{c.id}</span></p>
        </div>
        <div className="ck-cd-main">
          {pl?.tipo_cobranca === 'gratuito' && !pays.length ? (
            <section className="ck-card ck-cd-free">
              <span className="ck-cd-free__icon" aria-hidden="true"><Icon name="check-circle" size={18} /></span>
              <div>
                <strong>Plano grátis, sem cobranças</strong>
                <span>Nenhum pagamento é gerado enquanto a academia estiver no plano {pl.nome}. Precisa de mais? Troque de plano quando quiser.</span>
              </div>
            </section>
          ) : (
            <section className="ck-card ck-cd-pays" aria-labelledby="mp-pays">
              <div className="ck-cd-box__head">
                <div>
                  <h2 className="ck-mt-box__title" id="mp-pays">Cobranças</h2>
                  <p className="ck-cd-box__sub">{pays.length ? plural(pays.length, 'cobrança', 'cobranças') : 'Nenhuma cobrança ainda.'}</p>
                </div>
              </div>
              {pays.length ? (
                <div className="ck-mt-table-wrap">
                  <table className="ck-mt-table">
                    <thead><tr><th scope="col">Vencimento</th><th scope="col" className="is-num">Valor</th><th scope="col">Situação</th><th scope="col">Pago em</th><th scope="col"><span className="ck-sr">Ações</span></th></tr></thead>
                    <tbody>
                      {pays.map((p) => {
                        const [t, l] = PAY_STATUS[p.st];
                        const isOpen = p.st === 'aguardando' || p.st === 'atrasado';
                        return (
                          <tr key={p.id}>
                            <td>
                              <span className="ck-mt-cell__main">{fdate(p.data_vencimento)}</span>
                              <span className={`ck-mt-cell__sub${p.st === 'atrasado' ? ' is-late' : ''}`}>{p.plano_nome ? `Plano ${p.plano_nome}` : `Cobrança #${p.id}`}</span>
                            </td>
                            <td className="is-num"><span className="ck-mt-cell__main">{money(p.amount)}</span></td>
                            <td><Badge tone={t} dot={t !== 'neutral'}>{l}</Badge></td>
                            <td>{p.data_pagamento ? <span className="ck-mt-cell__main">{fdate(p.data_pagamento)}</span> : <span className="ck-al__missing">—</span>}</td>
                            <td className="ck-mt-actions">
                              {isOpen && canPay ? <Button size="sm" variant={p === next ? 'primary' : 'secondary'} onClick={() => onPay(p)}>{p.tem_cobranca ? 'Abrir cobrança' : 'Pagar'}</Button> : null}
                              {!isOpen && p.gateway_url && onOpenInvoice ? <IconButton icon="receipt" label="Ver fatura" size="sm" onClick={() => onOpenInvoice(p.gateway_url)} /> : null}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : null}
            </section>
          )}
        </div>
      </div>
    </div>
  );
}

/**
 * Pagamento de uma cobrança: escolhe Pix, cartão ou boleto (as formas que a conta do sistema aceita) e
 * mostra o resultado. Pix: QR Code e copia e cola na tela. Cartão e boleto: página do Asaas.
 */
export function PagarModal({ open, payment, formas, generating, result, error, onGenerate, onOpenUrl, onClose }) {
  const [forma, setForma] = useState(null);
  const [copied, setCopied] = useState(false);
  if (!open || !payment) return null;
  const list = (formas || []).filter((f) => BILLING[f]);
  const chosen = forma || (list.length === 1 ? list[0] : null);
  const amount = money(Number(payment.valor) || payment.amount || 0);
  const r = result;

  const copy = async () => {
    try { await navigator.clipboard.writeText(r.pix_payload); setCopied(true); setTimeout(() => setCopied(false), 1600); } catch { /* sem permissão: o código continua visível */ }
  };

  const close = () => { setForma(null); setCopied(false); onClose(); };

  if (r) {
    const isPix = r.billing_type === 'PIX' && (r.pix_qr_code || r.pix_payload);
    return (
      <Modal open size="sm" title={isPix ? `Pague ${amount} com Pix` : `Pagamento de ${amount}`} onClose={close}
        description={isPix ? 'Abra o app do seu banco e escaneie o código, ou use o copia e cola.' : 'A cobrança está pronta na página segura do Asaas.'}
        footer={(
          <>
            {r.url ? <Button variant="ghost" icon="arrow-right" onClick={() => onOpenUrl(r.url)}>{isPix ? 'Abrir fatura' : 'Abrir página de pagamento'}</Button> : null}
            <span style={{ flex: 1 }} />
            <Button variant="primary" onClick={close}>{isPix ? 'Já paguei' : 'Fechar'}</Button>
          </>
        )}>
        {isPix ? (
          <div className="ck-mp-pix">
            {r.pix_qr_code ? <img src={r.pix_qr_code} alt="QR Code do Pix" width={200} height={200} /> : null}
            {r.pix_payload ? (
              <div className="ck-mp-pix__code">
                <code>{r.pix_payload}</code>
                <Button size="sm" icon={copied ? 'check' : 'copy'} onClick={copy}>{copied ? 'Copiado' : 'Copiar código'}</Button>
              </div>
            ) : null}
            <p className="ck-al-hint"><Icon name="info" size={15} />A confirmação chega sozinha em alguns segundos. Atualize esta página depois de pagar.</p>
          </div>
        ) : (
          <div className="ck-mp-pix">
            <p className="ck-al-hint"><Icon name="info" size={15} />{r.billing_type === 'BOLETO' ? 'O boleto compensa em até 3 dias úteis; o plano é ativado na confirmação.' : 'Preencha o cartão na página do Asaas. O plano é ativado na aprovação.'}</p>
            {r.url ? <Button variant="primary" size="lg" block icon="credit-card" onClick={() => onOpenUrl(r.url)}>Abrir página de pagamento</Button> : null}
          </div>
        )}
      </Modal>
    );
  }

  return (
    <Modal open size="sm" title={`Pagar ${amount}`} dismissable={!generating} onClose={close}
      description={payment.plano_nome ? `Cobrança do plano ${payment.plano_nome}, vencimento ${fdate(payment.data_vencimento)}.` : `Vencimento ${fdate(payment.data_vencimento)}.`}
      footer={(
        <>
          <span style={{ flex: 1 }} />
          <Button variant="ghost" disabled={generating} onClick={close}>Cancelar</Button>
          <Button variant="primary" icon={chosen ? BILLING[chosen].icon : 'check-circle'} loading={generating} disabled={!chosen} onClick={() => onGenerate(chosen)}>
            {generating ? 'Gerando…' : chosen ? `Pagar ${amount} com ${BILLING[chosen].name}` : 'Escolha como pagar'}
          </Button>
        </>
      )}>
      <div className="ck-ct-pays ck-mp-formas" role="radiogroup" aria-label="Forma de pagamento">
        {list.map((f) => (
          <button key={f} type="button" role="radio" aria-checked={chosen === f} className="ck-ct-pay" onClick={() => setForma(f)}>
            <span className="ck-ct-pay__icon" aria-hidden="true"><Icon name={BILLING[f].icon} size={16} /></span>
            <span className="ck-mp-forma"><strong>{BILLING[f].name}</strong><span>{BILLING[f].hint}</span></span>
            <span className="ck-choice__radio ck-ct-radio" aria-hidden="true" />
          </button>
        ))}
      </div>
      {error ? <p className="ck-field__error" role="alert" style={{ marginTop: 12 }}><Icon name="alert-triangle" size={13} />{error}</p> : null}
    </Modal>
  );
}
