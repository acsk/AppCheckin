import React, { useEffect, useRef, useState } from 'react';
import { BaixaModal, Button, Callout, Icon, Modal } from './ck';
import api from '../services/api';
import pagamentoContratoService from '../services/pagamentoContratoService';
import { obterMensagemErro, extrairMensagemErro } from '../utils/errorHandler';
import { showError, showSuccess, showWarning } from '../utils/toast';

function sugerirProximoVencimento(value) {
  const base = String(value || '').slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(base)) return '';
  const [y, m, d] = base.split('-').map(Number);
  const alvo = new Date(Date.UTC(y, m, 1));
  const ultimoDia = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
  alvo.setUTCDate(Math.min(d, ultimoDia));
  return alvo.toISOString().slice(0, 10);
}

export default function BaixaPagamentoForm({ pagamento, onClose, onSuccess, plano = false, student, plan, matriculaId }) {
  const [methods, setMethods] = useState([]);
  const [loadingMethods, setLoadingMethods] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [confirmation, setConfirmation] = useState(null);
  const [duplicidade, setDuplicidade] = useState(null);
  const [attempt, setAttempt] = useState(0);
  const [previa, setPrevia] = useState(null);
  const [loadingPrevia, setLoadingPrevia] = useState(!!(matriculaId || pagamento.matricula_id) && plano);
  const busy = useRef(false);
  const now = new Date();
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  const id = pagamento.pagamento_id || pagamento.id || (plano ? pagamento.conta_id : null) || matriculaId;
  const amount = Number(pagamento.valor);
  const brl = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value) || 0);
  const currency = brl(amount);
  const dataBr = (value) => (value ? String(value).slice(0, 10).split('-').reverse().join('/') : '—');

  useEffect(() => {
    let active = true;
    const load = async () => {
      setLoadingMethods(true);
      setError('');
      try {
        const { data } = await api.get(plano ? '/formas-pagamento' : '/config/formas-pagamento-ativas');
        if (!active) return;
        const formas = data.formas || [];
        setMethods(formas.map((forma) => ({
          value: String(forma.id),
          label: plano ? forma.nome : forma.forma_pagamento_nome || forma.nome,
          icon: /pix/i.test(forma.nome || forma.forma_pagamento_nome) ? 'qr-code' : 'wallet',
        })));
        if (!formas.length) setError('Nenhuma forma de pagamento disponível.');
      } catch (err) {
        if (!active) return;
        console.error('Erro ao carregar formas de pagamento:', err);
        setError(obterMensagemErro(err.response?.data || err, 'Não foi possível carregar as formas de pagamento.'));
      } finally {
        if (active) setLoadingMethods(false);
      }
    };
    load();
    return () => { active = false; };
  }, [plano, attempt]);

  const matriculaDaBaixa = matriculaId || pagamento.matricula_id;
  const pagamentoDaPrevia = matriculaId ? null : (pagamento.pagamento_id || pagamento.id || pagamento.conta_id);
  useEffect(() => {
    if (!plano || !matriculaDaBaixa) return undefined;
    let active = true;
    api.get(`/admin/matriculas/${matriculaDaBaixa}/baixa-manual/previa`, { params: pagamentoDaPrevia ? { pagamento_id: pagamentoDaPrevia } : {} })
      .then(({ data }) => { if (active) setPrevia(data); })
      .catch((err) => console.error('Erro ao carregar prévia da baixa:', err))
      .finally(() => { if (active) setLoadingPrevia(false); });
    return () => { active = false; };
  }, [plano, matriculaDaBaixa, pagamentoDaPrevia]);

  const close = () => { if (!busy.current) onClose(); };
  const submit = async (values, confirmarDuplicidade = false) => {
    if (busy.current) return;
    if (!id || !Number.isFinite(amount) || amount < 0) {
      setError('Pagamento sem identificação ou valor válido.');
      setConfirmation(null);
      return;
    }
    if (plano && pagamento.pacote_contrato_id) {
      setError('Esta parcela faz parte de um pacote. Use a baixa do pacote ou renove a matrícula fora do pacote.');
      setConfirmation(null);
      return;
    }
    busy.current = true;
    setSaving(true);
    setError('');
    let resposta = null;
    try {
      const payload = {
        data_pagamento: values.paidDate,
        forma_pagamento_id: values.method,
        observacoes: values.notes || '',
      };
      if (plano) {
        resposta = await api.post(matriculaId ? `/admin/matriculas/${matriculaId}/baixa-manual` : `/admin/matriculas/contas/${id}/baixa`, {
          ...payload,
          data_vencimento: values.dueDate,
          proxima_data_vencimento: values.nextDueDate,
          ...(confirmarDuplicidade ? { confirmar_duplicidade: true } : {}),
        });
      } else {
        await pagamentoContratoService.confirmar(id, payload);
      }
    } catch (err) {
      // Outra parcela do mesmo período já foi paga: mostra o resumo e pede confirmação explícita
      if (err.response?.status === 409 && err.response.data?.code === 'BAIXA_PERIODO_JA_PAGO') {
        setDuplicidade({ values, resumo: err.response.data.resumo || {} });
        setConfirmation(null);
        return;
      }
      console.error('Erro ao confirmar pagamento:', err);
      const message = extrairMensagemErro(err.response?.data || err);
      setError(message);
      showError(message);
      setConfirmation(null);
      setDuplicidade(null);
      return;
    } finally {
      busy.current = false;
      setSaving(false);
    }
    const corpo = resposta?.data || {};
    if (plano && corpo.aviso_cobranca) {
      // Outra parcela cobre o mesmo período: a próxima não foi gerada e o admin precisa conferir
      showWarning(`Pagamento confirmado. ${corpo.aviso_cobranca}`);
    } else {
      showSuccess(plano
        ? 'Pagamento confirmado!'
        : 'Pagamento confirmado com sucesso!');
    }
    onClose();
    onSuccess?.();
  };

  return (
    <>
      <BaixaModal
        open={!confirmation && !duplicidade && !loadingPrevia}
        invoice={{ id: matriculaId ? 'da matrícula' : `#${id || '-'}`, number: pagamento.numero_parcela, amount: previa?.valor ?? amount, dueDate: (previa?.vencimento_atual || pagamento.data_vencimento)?.slice(0, 10) }}
        today={today}
        student={student || pagamento.usuario_nome || pagamento.aluno_nome}
        plan={previa?.plano?.nome || plan || pagamento.plano_nome}
        methods={methods}
        defaultMethod={pagamento.forma_pagamento_id ? String(pagamento.forma_pagamento_id) : ''}
        defaultNotes={plano ? '' : 'Baixa Manual'}
        canEditDue={plano}
        minDueDate={plano ? `${today.slice(0, 7)}-01` : undefined}
        allowReceipt={false}
        askNextDue={plano}
        defaultNextDue={plano ? (previa?.proximo_vencimento || sugerirProximoVencimento(pagamento.data_vencimento)) : ''}
        nextNote={false}
        error={loadingMethods ? 'Carregando formas de pagamento…' : error}
        errorAction={!loadingMethods && !methods.length ? <Button size="sm" onClick={() => setAttempt((value) => value + 1)}>Tentar novamente</Button> : null}
        disabled={loadingMethods || !methods.length}
        confirming={saving}
        onCancel={close}
        onConfirm={plano ? (values) => {
          if (pagamento.pacote_contrato_id) {
            setError('Esta parcela faz parte de um pacote. Use a baixa do pacote ou renove a matrícula fora do pacote.');
          } else {
            setConfirmation(values);
          }
        } : submit}
      />
      <Modal
        open={!!confirmation}
        title="Confirmar baixa"
        description={`Registrar o recebimento de ${currency} para ${matriculaId ? 'esta matrícula' : `a fatura #${id}`}?`}
        dismissable={!saving}
        onClose={() => setConfirmation(null)}
        footer={<>
          <Button variant="ghost" disabled={saving} onClick={() => setConfirmation(null)}>Voltar</Button>
          <Button variant="primary" loading={saving} onClick={() => submit(confirmation)}>Confirmar pagamento</Button>
        </>}
      >
        <p>Confirme somente se o pagamento já foi recebido. O próximo vencimento informado será registrado na matrícula; nenhuma parcela futura é criada.</p>
      </Modal>
      <Modal
        open={!!duplicidade}
        size="sm"
        leading={<span className="ck-xm__icon" aria-hidden="true"><Icon name="alert-triangle" size={20} /></span>}
        title="Este período já foi pago"
        description={`${student || pagamento.usuario_nome || pagamento.aluno_nome || 'O aluno'} já tem parcela paga com vencimento próximo desta. Confira antes de registrar outra baixa.`}
        dismissable={!saving}
        onClose={() => setDuplicidade(null)}
        footer={<>
          <Button variant="ghost" disabled={saving} onClick={() => setDuplicidade(null)}>Voltar</Button>
          <Button variant="danger" loading={saving} onClick={() => submit(duplicidade.values, true)}>Registrar mesmo assim</Button>
        </>}
      >
        {duplicidade ? (() => {
          const { resumo, values } = duplicidade;
          const pagas = resumo.pagas || [];
          const abertas = resumo.em_aberto || [];
          return (
            <div className="ck-bd">
              <div className="ck-bd__group">
                <span className="ck-bd__label">Baixa que você está registrando</span>
                <ul className="ck-bd__list ck-bd__list--atual">
                  <li>
                    <span>Fatura #{id}<small>Vence em {dataBr(values.dueDate || resumo.parcela?.data_vencimento)} · pago em {dataBr(values.paidDate)}</small></span>
                    <strong>{currency}</strong>
                  </li>
                </ul>
              </div>
              <div className="ck-bd__group">
                <span className="ck-bd__label">{pagas.length > 1 ? 'Já pagas no mesmo período' : 'Já paga no mesmo período'}</span>
                <ul className="ck-bd__list">
                  {pagas.map((p) => (
                    <li key={p.id}>
                      <span>Fatura #{p.id}<small>Vence em {dataBr(p.data_vencimento)} · pago em {dataBr(p.data_pagamento)}{p.baixado_por_nome ? ` · baixa por ${p.baixado_por_nome}` : ''}</small></span>
                      <strong>{brl(p.valor)}</strong>
                    </li>
                  ))}
                </ul>
              </div>
              {abertas.length ? (
                <div className="ck-bd__group">
                  <span className="ck-bd__label">Outras parcelas em aberto</span>
                  <ul className="ck-bd__list">
                    {abertas.map((p) => (
                      <li key={p.id}>
                        <span>Fatura #{p.id}<small>Vence em {dataBr(p.data_vencimento)}</small></span>
                        <strong>{brl(p.valor)}</strong>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
              <Callout tone="warning" title="Se confirmar">
                O aluno fica com {pagas.length + 1} pagamentos para o mesmo período e uma nova parcela é gerada
                {abertas.length ? ', além das que já estão em aberto' : ''}. Registre só se o valor foi recebido em dobro de verdade.
              </Callout>
            </div>
          );
        })() : null}
      </Modal>
    </>
  );
}
