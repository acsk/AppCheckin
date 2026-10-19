import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import BaixaPagamentoModal from '../../components/BaixaPagamentoModal';
import { Button, Callout, Icon, Modal, SelectField, TextAreaField, TextField } from '../../components/ck';
import ContratoDetalhe, { dayDiff, fdate, money, payStatus } from '../../components/ck/contratoDetalhe';
import api from '../../services/api';
import { authService } from '../../services/authService';
import { showError, showSuccess } from '../../utils/toast';

const hoje = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const somarDias = (iso, dias) => {
  const [y, m, d] = iso.split('-').map(Number);
  const dt = new Date(y, m - 1, d + dias);
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
};
const erroApi = (e, padrao) => e?.response?.data?.message || e?.response?.data?.error || padrao;

function eventos(contrato, pagamentos, anterior, today) {
  const lista = [];
  if (anterior) {
    lista.push({ at: contrato.created_at, title: `Plano trocado de ${anterior.plano_nome} para ${contrato.plano_nome}` });
  } else {
    lista.push({ at: contrato.created_at, title: 'Contrato criado' });
  }
  if (Number(contrato.status_id) === 3) lista.push({ at: contrato.updated_at, title: 'Contrato cancelado', tone: 'danger' });

  pagamentos.forEach((p) => {
    if (p.status === 'pago' && p.paidDate) {
      lista.push({ at: p.paidDate, title: `Cobrança #${p.id} paga${p.method ? ` via ${p.method}` : ''}`, tone: 'success' });
    } else if (p.status === 'atrasado') {
      const venceu = somarDias(p.dueDate.slice(0, 10), 1);
      if (dayDiff(venceu, today) >= 0) lista.push({ at: venceu, title: `Cobrança #${p.id} venceu sem pagamento`, tone: 'danger' });
    } else if (p.status === 'cancelado') {
      lista.push({ at: p.updatedAt, title: `Cobrança #${p.id} cancelada` });
    }
  });

  return lista.filter((e) => e.at).sort((a, b) => String(b.at).localeCompare(String(a.at)));
}

function LancarPagamento({ open, contrato, formas, sugestao, saving, onClose, onSave }) {
  const [valor, setValor] = useState('');
  const [vencimento, setVencimento] = useState(hoje());
  const [forma, setForma] = useState('');
  const [obs, setObs] = useState('');
  const [erro, setErro] = useState('');

  useEffect(() => {
    if (!open) return;
    setValor(sugestao ? String(sugestao.toFixed(2)).replace('.', ',') : '');
    setVencimento(hoje());
    setForma(formas[0]?.value || '');
    setObs('');
    setErro('');
  }, [open, sugestao, formas]);

  const salvar = () => {
    const v = Number(String(valor).replace(/\./g, '').replace(',', '.'));
    if (!v || v <= 0) return setErro('Informe um valor maior que zero.');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(vencimento)) return setErro('Informe a data de vencimento.');
    if (!forma) return setErro('Escolha a forma de pagamento.');
    setErro('');
    return onSave({ valor: v, data_vencimento: vencimento, forma_pagamento_id: Number(forma), observacoes: obs || null });
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      dismissable={!saving}
      title="Lançar pagamento"
      description={contrato ? `Nova cobrança no contrato #${contrato.id}, ${contrato.tenant_nome}.` : undefined}
      footer={(
        <>
          <Button variant="ghost" disabled={saving} onClick={onClose}>Cancelar</Button>
          <Button variant="primary" icon="check" loading={saving} onClick={salvar}>Lançar cobrança</Button>
        </>
      )}
    >
      <div style={{ display: 'grid', gap: 'var(--space-4)' }}>
        <div style={{ display: 'grid', gap: 'var(--space-4)', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))' }}>
          <TextField label="Valor (R$)" inputMode="decimal" value={valor} onChange={(e) => setValor(e?.target ? e.target.value : e)} placeholder="0,00" />
          <TextField label="Vencimento" type="date" value={vencimento} onChange={(e) => setVencimento(e?.target ? e.target.value : e)} />
        </div>
        <SelectField label="Forma de pagamento" value={forma} onChange={(e) => setForma(e.target.value)} options={formas} placeholder="Escolha" />
        <TextAreaField label="Observação" value={obs} onChange={(e) => setObs(e?.target ? e.target.value : e)} rows={2} placeholder="Opcional" />
        {erro ? <Callout tone="danger" icon="alert-triangle" title={erro} /> : null}
      </div>
    </Modal>
  );
}

function Confirmar({ open, title, children, confirmLabel, danger, saving, onClose, onConfirm }) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      dismissable={!saving}
      size="sm"
      title={title}
      footer={(
        <>
          <Button variant="ghost" disabled={saving} onClick={onClose}>Voltar</Button>
          <Button variant={danger ? 'danger' : 'primary'} loading={saving} onClick={onConfirm}>{confirmLabel}</Button>
        </>
      )}
    >
      <div style={{ fontSize: 15, lineHeight: '22px', color: 'var(--ink-muted)' }}>{children}</div>
    </Modal>
  );
}

export default function ContratoDetalheScreen() {
  const { id } = useLocalSearchParams();
  const [today] = useState(hoje);
  const [loading, setLoading] = useState(true);
  const [contrato, setContrato] = useState(null);
  const [brutos, setBrutos] = useState([]);
  const [anterior, setAnterior] = useState(null);
  const [formas, setFormas] = useState([]);
  const [baixa, setBaixa] = useState(null);
  const [modal, setModal] = useState(null);
  const [saving, setSaving] = useState(false);

  const carregar = useCallback(async () => {
    try {
      const { data } = await api.get(`/superadmin/contratos/${id}`);
      const c = data.contrato || data;
      const [pags, historico] = await Promise.all([
        api.get(`/superadmin/contratos/${id}/pagamentos-contrato`).then((r) => r.data.pagamentos || []).catch(() => []),
        api.get(`/superadmin/academias/${c.tenant_id}/contratos`).then((r) => r.data.contratos || []).catch(() => []),
      ]);
      const anteriores = historico
        .filter((x) => Number(x.id) < Number(c.id) && String(x.created_at || '') <= String(c.created_at || ''))
        .sort((a, b) => Number(b.id) - Number(a.id));
      setContrato(c);
      setBrutos(pags);
      setAnterior(anteriores[0] || null);
    } catch (e) {
      setContrato(null);
      showError(erroApi(e, 'Não foi possível carregar o contrato.'));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    (async () => {
      const user = await authService.getCurrentUser();
      if (!user || user.papel_id !== 4) {
        showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
        router.replace('/');
        return;
      }
      carregar();
      api.get('/formas-pagamento')
        .then(({ data }) => setFormas((data.formas || []).map((f) => ({ value: String(f.id), label: f.nome }))))
        .catch(() => setFormas([]));
    })();
  }, [carregar]);

  const pagamentos = useMemo(() => brutos.map((p) => ({
    id: p.id,
    dueDate: p.data_vencimento,
    amount: Number(p.valor) || 0,
    status: payStatus(p, today),
    paidDate: p.data_pagamento,
    method: p.forma_pagamento_nome || (p.gateway_billing_type === 'PIX' ? 'Pix' : p.gateway_billing_type === 'BOLETO' ? 'Boleto' : p.gateway_billing_type === 'CREDIT_CARD' ? 'Cartão' : null),
    gatewayUrl: p.gateway_url,
    updatedAt: p.updated_at,
    raw: p,
  })), [brutos, today]);

  const historico = useMemo(
    () => (contrato ? eventos(contrato, pagamentos, anterior, today) : []),
    [contrato, pagamentos, anterior, today],
  );

  const valorSugerido = useMemo(() => {
    const ultimo = pagamentos.find((p) => p.status !== 'cancelado' && p.amount > 0);
    if (ultimo) return ultimo.amount;
    return contrato?.tipo_cobranca === 'fixo' ? Number(contrato.plano_valor) || 0 : 0;
  }, [pagamentos, contrato]);

  const novaDataFim = contrato
    ? somarDias(contrato.data_fim && contrato.data_fim > today ? contrato.data_fim.slice(0, 10) : today, Number(contrato.duracao_dias) || 30)
    : null;

  const executar = async (fn, sucesso) => {
    setSaving(true);
    try {
      await fn();
      showSuccess(sucesso);
      setModal(null);
      await carregar();
    } catch (e) {
      showError(erroApi(e, 'Não foi possível concluir. Tente de novo.'));
    } finally {
      setSaving(false);
    }
  };

  const titulo = contrato ? `Contrato #${contrato.id}` : 'Contrato';

  return (
    <LayoutBase title={titulo} pageHeader={false}>
      {loading ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, color: 'var(--ink-muted)' }}>
          <Icon name="loader" size={18} className="ck-spin" />Carregando contrato…
        </div>
      ) : !contrato ? (
        <Callout tone="danger" icon="alert-triangle" title="Contrato não encontrado" action={<Button variant="secondary" onClick={() => router.push('/contratos')}>Voltar aos contratos</Button>}>
          Ele pode ter sido removido ou o link está incorreto.
        </Callout>
      ) : (
        <ContratoDetalhe
          contract={contrato}
          payments={pagamentos}
          events={historico}
          today={today}
          onBack={() => router.push('/contratos')}
          onOpenAcademy={() => router.push(`/academias/${contrato.tenant_id}`)}
          onChangePlan={() => router.push(`/contratos/trocar-plano?academiaId=${contrato.tenant_id}&academiaNome=${encodeURIComponent(contrato.tenant_nome || '')}`)}
          onRenew={() => setModal({ tipo: 'renovar' })}
          onCancelContract={() => setModal({ tipo: 'cancelar-contrato' })}
          onSettle={(p) => setBaixa({ ...p.raw, status_pagamento_id: p.raw.status_pagamento_id })}
          onAddPayment={() => setModal({ tipo: 'lancar' })}
          onCancelPayment={(p) => setModal({ tipo: 'cancelar-cobranca', pagamento: p })}
        />
      )}

      <BaixaPagamentoModal
        visible={!!baixa}
        pagamento={baixa}
        onClose={() => setBaixa(null)}
        onSuccess={() => { setBaixa(null); carregar(); }}
      />

      <LancarPagamento
        open={modal?.tipo === 'lancar'}
        contrato={contrato}
        formas={formas}
        sugestao={valorSugerido}
        saving={saving}
        onClose={() => setModal(null)}
        onSave={(dados) => executar(() => api.post(`/superadmin/contratos/${contrato.id}/pagamentos-contrato`, dados), 'Cobrança lançada')}
      />

      <Confirmar
        open={modal?.tipo === 'renovar'}
        title="Renovar contrato"
        confirmLabel="Renovar"
        saving={saving}
        onClose={() => setModal(null)}
        onConfirm={() => executar(() => api.post(`/superadmin/contratos/${contrato.id}/renovar`, { data_fim: novaDataFim }), 'Contrato renovado')}
      >
        {contrato ? (
          <>
            O contrato passa a valer até <strong style={{ color: 'var(--ink)' }}>{fdate(novaDataFim)}</strong>
            {contrato.data_fim ? `, ${Number(contrato.duracao_dias) || 30} dias depois de ${contrato.data_fim > today ? 'o fim atual' : 'hoje'}` : ''}.
          </>
        ) : null}
      </Confirmar>

      <Confirmar
        open={modal?.tipo === 'cancelar-contrato'}
        title="Encerrar contrato?"
        confirmLabel="Encerrar contrato"
        danger
        saving={saving}
        onClose={() => setModal(null)}
        onConfirm={() => executar(() => api.delete(`/superadmin/contratos/${contrato.id}`), 'Contrato encerrado')}
      >
        {contrato ? `${contrato.tenant_nome} perde o acesso ao painel até ter um novo contrato. As cobranças pagas continuam no histórico.` : null}
      </Confirmar>

      <Confirmar
        open={modal?.tipo === 'cancelar-cobranca'}
        title="Cancelar cobrança"
        confirmLabel="Cancelar cobrança"
        danger
        saving={saving}
        onClose={() => setModal(null)}
        onConfirm={() => executar(() => api.delete(`/superadmin/pagamentos-contrato/${modal.pagamento.id}`), 'Cobrança cancelada')}
      >
        {modal?.pagamento ? `A cobrança #${modal.pagamento.id} de ${money(modal.pagamento.amount)}, com vencimento em ${fdate(modal.pagamento.dueDate)}, deixa de contar como devida.` : null}
      </Confirmar>
    </LayoutBase>
  );
}
