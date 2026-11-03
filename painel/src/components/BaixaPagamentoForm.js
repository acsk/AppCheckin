import React, { useEffect, useRef, useState } from 'react';
import { BaixaModal, Button, Callout, Icon, Modal } from './ck';
import api from '../services/api';
import pagamentoContratoService from '../services/pagamentoContratoService';
import { obterMensagemErro, extrairMensagemErro } from '../utils/errorHandler';
import { showError, showSuccess } from '../utils/toast';

export default function BaixaPagamentoForm({ pagamento, onClose, onSuccess, plano = false, student, plan }) {
  const [methods, setMethods] = useState([]);
  const [loadingMethods, setLoadingMethods] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [confirmation, setConfirmation] = useState(null);
  const [duplicidade, setDuplicidade] = useState(null);
  const [attempt, setAttempt] = useState(0);
  const busy = useRef(false);
  const now = new Date();
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  const id = pagamento.pagamento_id || pagamento.id || (plano ? pagamento.conta_id : null);
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
    try {
      const payload = {
        data_pagamento: values.paidDate,
        forma_pagamento_id: values.method,
        observacoes: values.notes || '',
      };
      if (plano) {
        await api.post(`/admin/matriculas/contas/${id}/baixa`, {
          ...payload,
          data_vencimento: values.dueDate,
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
    showSuccess(plano ? 'Pagamento confirmado! Próximo pagamento gerado automaticamente.' : 'Pagamento confirmado com sucesso!');
    onClose();
    onSuccess?.();
  };

  return (
    <>
      <BaixaModal
        open={!confirmation && !duplicidade}
        invoice={{ id: `#${id || '-'}`, number: pagamento.numero_parcela, amount, dueDate: pagamento.data_vencimento?.slice(0, 10) }}
        today={today}
        student={student || pagamento.usuario_nome || pagamento.aluno_nome}
        plan={plan || pagamento.plano_nome}
        methods={methods}
        defaultMethod={pagamento.forma_pagamento_id ? String(pagamento.forma_pagamento_id) : ''}
        defaultNotes={plano ? '' : 'Baixa Manual'}
        canEditDue={plano}
        minDueDate={plano ? `${today.slice(0, 7)}-01` : undefined}
        allowReceipt={false}
        nextNote={plano ? undefined : false}
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
        description={`Registrar o recebimento de ${currency} para a fatura #${id}?`}
        dismissable={!saving}
        onClose={() => setConfirmation(null)}
        footer={<>
          <Button variant="ghost" disabled={saving} onClick={() => setConfirmation(null)}>Voltar</Button>
          <Button variant="primary" loading={saving} onClick={() => submit(confirmation)}>Confirmar pagamento</Button>
        </>}
      >
        <p>A próxima parcela será gerada automaticamente. Confirme somente se o pagamento já foi recebido.</p>
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
