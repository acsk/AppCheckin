import React, { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import api from '../../services/api';
import { authService } from '../../services/authService';
import superAdminService from '../../services/superAdminService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Modal } from '../../components/ck';
import { ContratosLista } from '../../components/ck/contratos';
import { showSuccess, showError } from '../../utils/toast';

const STATUS = { 1: 'ativo', 2: 'pendente', 3: 'cancelado', 4: 'bloqueado' };
const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const cycleLabel = (dias) => {
  const n = Number(dias || 30);
  if (n === 30) return 'por mês';
  if (n === 365) return 'por ano';
  return `a cada ${n} dias`;
};

const toRow = (c) => ({
  id: c.id,
  academyId: c.academia_id || c.tenant_id,
  academy: c.academia_nome || `Academia #${c.tenant_id}`,
  plan: c.plano_nome,
  planType: c.tipo_cobranca || (Number(c.valor) > 0 ? 'fixo' : 'gratuito'),
  amount: Number(c.valor || 0),
  perStudent: Number(c.valor_por_aluno || 0),
  cycle: cycleLabel(c.duracao_dias),
  durationDays: Number(c.duracao_dias || 30),
  payment: c.forma_pagamento_nome || null,
  start: c.data_inicio ? String(c.data_inicio).slice(0, 10) : null,
  end: c.data_fim ? String(c.data_fim).slice(0, 10) : null,
  status: STATUS[c.status_id] || String(c.status_nome || '').toLowerCase(),
});

export default function ContratosScreen() {
  const router = useRouter();
  const [rows, setRows] = useState([]);
  const [academias, setAcademias] = useState(null);
  const [loading, setLoading] = useState(true);
  const [toCancel, setToCancel] = useState(null);
  const [cancelling, setCancelling] = useState(false);

  useEffect(() => {
    (async () => {
      const user = await authService.getCurrentUser();
      if (!user || user.papel_id !== 4) {
        router.replace('/');
        return;
      }
      load();
    })();
  }, []);

  const load = async () => {
    setLoading(true);
    try {
      const [ct, ac] = await Promise.all([
        api.get('/superadmin/contratos'),
        superAdminService.listarAcademias().catch(() => null),
      ]);
      setRows((ct.data.contratos || []).map(toRow));
      setAcademias(ac ? ac.academias || [] : null);
    } catch (error) {
      showError(error.response?.data?.error || error.response?.data?.message || 'Erro ao carregar contratos');
    } finally {
      setLoading(false);
    }
  };

  const summary = useMemo(() => {
    const t = today();
    const alunos = {};
    (academias || []).forEach((a) => { alunos[a.id] = Number(a.alunos_ativos || 0); });
    let revenue = 0;
    let estimado = false;
    rows.forEach((r) => {
      if (r.status !== 'ativo' || (r.end && r.end < t)) return;
      if (r.planType === 'fixo') revenue += r.amount * (30 / (r.durationDays || 30));
      if (r.planType === 'por_aluno') {
        revenue += r.perStudent * (alunos[r.academyId] || 0);
        estimado = true;
      }
    });
    const ativas = (academias || []).filter((a) => a.ativo === undefined || a.ativo === true || a.ativo === 1 || a.ativo === '1');
    return {
      revenue,
      revenueNote: estimado ? 'estimada pelos alunos ativos' : 'somando os contratos ativos',
      withoutContract: academias ? ativas.filter((a) => !a.contrato).length : null,
    };
  }, [rows, academias]);

  const confirmCancel = async () => {
    if (!toCancel) return;
    setCancelling(true);
    try {
      await api.delete(`/superadmin/contratos/${toCancel.id}`);
      showSuccess('Contrato encerrado');
      setToCancel(null);
      load();
    } catch (error) {
      showError(error.response?.data?.message || error.response?.data?.error || 'Não foi possível encerrar o contrato');
    } finally {
      setCancelling(false);
    }
  };

  return (
    <LayoutBase title="Contratos" pageHeader={false}>
      <ContratosLista
        rows={rows}
        loading={loading}
        today={today()}
        summary={summary}
        onNew={() => router.push('/contratos/novo')}
        onOpen={(r) => router.push(`/contratos/detalhe?id=${r.id}`)}
        onOpenAcademy={(r) => router.push(`/academias/${r.academyId}`)}
        onChangePlan={(r) => router.push(`/contratos/trocar-plano?academiaId=${r.academyId}&academiaNome=${encodeURIComponent(r.academy)}`)}
        onCancel={(r) => setToCancel(r)}
      />
      <Modal
        open={!!toCancel}
        onClose={() => !cancelling && setToCancel(null)}
        size="sm"
        title="Encerrar contrato?"
        description={toCancel ? `${toCancel.academy} perde o acesso ao painel até ter um novo contrato.` : ''}
        footer={
          <>
            <Button variant="ghost" onClick={() => setToCancel(null)} disabled={cancelling}>Manter contrato</Button>
            <Button variant="danger" icon="ban" loading={cancelling} onClick={confirmCancel}>Encerrar contrato</Button>
          </>
        }
      >
        {toCancel ? <p style={{ margin: 0 }}>Contrato #{toCancel.id}, plano {toCancel.plan}. O histórico de pagamentos continua guardado.</p> : null}
      </Modal>
    </LayoutBase>
  );
}
