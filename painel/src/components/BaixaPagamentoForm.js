import React, { useEffect, useRef, useState } from 'react';
import { BaixaModal, Button, Modal } from './ck';
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
  const [attempt, setAttempt] = useState(0);
  const busy = useRef(false);
  const now = new Date();
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  const id = pagamento.pagamento_id || pagamento.id || (plano ? pagamento.conta_id : null);
  const amount = Number(pagamento.valor);
  const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(amount);

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
          label: plano ? forma.nome : forma.forma_pagamento_nome,
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
  const submit = async (values) => {
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
        });
      } else {
        await pagamentoContratoService.confirmar(id, payload);
      }
    } catch (err) {
      console.error('Erro ao confirmar pagamento:', err);
      const message = extrairMensagemErro(err.response?.data || err);
      setError(message);
      showError(message);
      setConfirmation(null);
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
        open={!confirmation}
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
    </>
  );
}
