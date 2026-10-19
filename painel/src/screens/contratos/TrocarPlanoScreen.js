import React, { useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import api from '../../services/api';
import { authService } from '../../services/authService';
import superAdminService from '../../services/superAdminService';
import planosSistemaService from '../../services/planosSistemaService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout } from '../../components/ck';
import { TrocarPlanoContrato, pickPaymentMethods, planFromApi } from '../../components/ck/contratos';
import { showSuccess, showError } from '../../utils/toast';

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const apiError = (error, fallback) => {
  const d = error?.response?.data || error || {};
  if (Array.isArray(d.errors) && d.errors.length) return d.errors.join(', ');
  return d.message || d.error || fallback;
};

export default function TrocarPlanoScreen() {
  const router = useRouter();
  const { academiaId, academiaNome } = useLocalSearchParams();
  const tenantId = academiaId ? parseInt(academiaId, 10) : null;
  const [contract, setContract] = useState(null);
  const [currentPlan, setCurrentPlan] = useState(null);
  const [plans, setPlans] = useState([]);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [activeStudents, setActiveStudents] = useState(undefined);
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    (async () => {
      const user = await authService.getCurrentUser();
      if (!user || user.papel_id !== 4) {
        showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
        router.replace('/');
        return;
      }
      load();
    })();
  }, [tenantId]);

  const load = async () => {
    setLoading(true);
    try {
      const [ct, pl, fp, ac] = await Promise.all([
        api.get(`/superadmin/academias/${tenantId}/contrato-ativo`),
        planosSistemaService.listarDisponiveis(),
        api.get('/formas-pagamento').catch(() => ({ data: {} })),
        superAdminService.listarAcademias().catch(() => null),
      ]);
      const c = ct.data;
      if (!c || !c.id) {
        setMissing(true);
        return;
      }
      const academia = (ac?.academias || []).find((a) => a.id === tenantId);
      setContract({ id: c.id, academy: academia?.nome || academiaNome || `Academia #${tenantId}`, plan: c.plano_nome, start: c.data_inicio });
      setCurrentPlan(planFromApi({
        id: c.plano_sistema_id, nome: c.plano_nome, codigo: c.plano_codigo, tipo_cobranca: c.tipo_cobranca,
        valor: c.valor, valor_por_aluno: c.valor_por_aluno, duracao_dias: c.duracao_dias, max_alunos: c.max_alunos, features: c.features,
      }));
      setPlans((pl.planos || []).map(planFromApi));
      setPaymentMethods(pickPaymentMethods((fp.data.formas || fp.data.formas_pagamento || []).filter((f) => f.ativo === undefined || Number(f.ativo) === 1)));
      setActiveStudents(academia && academia.alunos_ativos != null ? Number(academia.alunos_ativos) : undefined);
    } catch (error) {
      showError(apiError(error, 'Não foi possível carregar o contrato'));
    } finally {
      setLoading(false);
    }
  };

  const back = () => (contract ? router.push(`/contratos/detalhe?id=${contract.id}`) : router.push('/contratos'));

  const save = async (v) => {
    setSaving(true);
    try {
      const response = await api.post(`/superadmin/academias/${tenantId}/trocar-plano`, {
        plano_sistema_id: v.planId,
        forma_pagamento_id: v.paymentId,
        observacoes: v.notes || 'Troca de plano',
      });
      showSuccess('Plano trocado');
      const novoId = response.data?.contrato?.id;
      router.push(novoId ? `/contratos/detalhe?id=${novoId}` : '/contratos');
    } catch (error) {
      showError(apiError(error, 'Não foi possível trocar o plano'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <LayoutBase title="Trocar plano" pageHeader={false}>
      {loading ? (
        <p className="ck-al-hint" role="status">Carregando contrato…</p>
      ) : missing ? (
        <Callout
          tone="warning"
          title={`${academiaNome || 'Esta academia'} não tem contrato ativo`}
          action={<Button variant="primary" icon="plus" onClick={() => router.replace(`/contratos/novo?academiaId=${tenantId}`)}>Novo contrato</Button>}
        >
          Para mudar de plano é preciso ter um contrato ativo. Crie um contrato com o plano desejado.
        </Callout>
      ) : contract ? (
        <TrocarPlanoContrato
          contract={contract}
          currentPlan={currentPlan}
          plans={plans}
          paymentMethods={paymentMethods}
          activeStudents={activeStudents}
          today={today()}
          saving={saving}
          onSave={save}
          onBack={back}
          onCancel={back}
        />
      ) : null}
    </LayoutBase>
  );
}
