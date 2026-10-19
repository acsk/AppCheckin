import React, { useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import api from '../../services/api';
import { authService } from '../../services/authService';
import superAdminService from '../../services/superAdminService';
import planosSistemaService from '../../services/planosSistemaService';
import LayoutBase from '../../components/LayoutBase';
import { NovoContrato, payKind, planFromApi } from '../../components/ck/contratos';
import { showSuccess, showError } from '../../utils/toast';

const ativa = (a) => a.ativo === undefined || a.ativo === true || a.ativo === 1 || a.ativo === '1';
const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const apiError = (error, fallback) => {
  const d = error?.response?.data || error || {};
  if (Array.isArray(d.errors) && d.errors.length) return d.errors.join(', ');
  return d.message || d.error || fallback;
};

// Uma opção por tipo (Pix, boleto, cartão); as demais formas ficam de fora da escolha
const pickPaymentMethods = (formas) => {
  const byKind = {};
  formas.forEach((f) => {
    const k = payKind(f.nome);
    if (!k) return;
    const exact = ['pix', 'boleto', 'cartão'].includes(String(f.nome).toLowerCase());
    if (!byKind[k] || exact) byKind[k] = f;
  });
  const picked = ['pix', 'boleto', 'cartao'].map((k) => byKind[k]).filter(Boolean);
  const list = picked.length ? picked : formas;
  return list.map((f) => ({ id: f.id, name: payKind(f.nome) === 'cartao' && picked.length ? 'Cartão de crédito' : f.nome }));
};

export default function NovoContratoScreen() {
  const router = useRouter();
  const { academiaId } = useLocalSearchParams();
  const [academies, setAcademies] = useState([]);
  const [plans, setPlans] = useState([]);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

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
      const [ac, pl, fp] = await Promise.all([
        superAdminService.listarAcademias(),
        planosSistemaService.listarDisponiveis(),
        api.get('/formas-pagamento').catch(() => ({ data: {} })),
      ]);
      setAcademies((ac.academias || []).filter(ativa).map((a) => ({
        id: a.id,
        name: a.nome,
        city: a.cidade || null,
        uf: a.estado || null,
        currentPlan: a.contrato ? a.contrato.plano_nome : null,
      })));
      setPlans((pl.planos || []).map(planFromApi));
      setPaymentMethods(pickPaymentMethods((fp.data.formas || fp.data.formas_pagamento || []).filter((f) => f.ativo === undefined || Number(f.ativo) === 1)));
    } catch (error) {
      showError(apiError(error, 'Erro ao carregar dados do contrato'));
    } finally {
      setLoading(false);
    }
  };

  const submit = async (v) => {
    setSaving(true);
    const dados = {
      plano_sistema_id: v.planId,
      forma_pagamento_id: v.paymentId,
      data_fim: v.end,
      observacoes: v.notes,
    };
    try {
      if (v.swap) {
        await api.post(`/superadmin/academias/${v.academyId}/trocar-plano`, dados);
        showSuccess('Plano trocado');
      } else {
        await api.post(`/superadmin/academias/${v.academyId}/contratos`, { ...dados, data_inicio: v.start });
        showSuccess('Contrato criado');
      }
      router.push('/contratos');
    } catch (error) {
      showError(apiError(error, 'Não foi possível criar o contrato'));
    } finally {
      setSaving(false);
    }
  };

  const initialId = academiaId ? parseInt(academiaId, 10) : undefined;

  return (
    <LayoutBase title="Novo contrato" pageHeader={false}>
      {loading ? null : (
        <NovoContrato
          academies={academies}
          plans={plans}
          paymentMethods={paymentMethods}
          academyId={initialId}
          today={today()}
          saving={saving}
          onSubmit={submit}
          onBack={() => router.push('/contratos')}
          onCancel={() => router.push('/contratos')}
        />
      )}
    </LayoutBase>
  );
}
