import React, { useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import planosSistemaService from '../../services/planosSistemaService';
import LayoutBase from '../../components/LayoutBase';
import { PlanoSistemaForm, systemPlanFromApi } from '../../components/ck/planosSistema';
import { showSuccess, showError } from '../../utils/toast';

export default function PlanoSistemaFormScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const edit = !!id && id !== 'novo';
  const [plan, setPlan] = useState(null);
  const [loading, setLoading] = useState(edit);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    (async () => {
      const user = await authService.getCurrentUser();
      if (!user || user.papel_id !== 4) {
        router.replace('/');
        return;
      }
      if (!edit) return;
      try {
        setPlan(systemPlanFromApi(await planosSistemaService.buscarPorId(id)));
      } catch (e) {
        showError(e.message || 'Plano não encontrado');
        router.replace('/planos-sistema');
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  const save = async (data) => {
    setSaving(true);
    try {
      if (edit) {
        await planosSistemaService.atualizar(id, data);
        showSuccess('Plano atualizado');
      } else {
        await planosSistemaService.criar(data);
        showSuccess('Plano criado');
      }
      router.push('/planos-sistema');
    } catch (e) {
      showError(e.message || 'Não foi possível salvar o plano');
    } finally {
      setSaving(false);
    }
  };

  const back = () => router.push('/planos-sistema');

  return (
    <LayoutBase title={edit ? 'Editar plano' : 'Novo plano'} parent={{ label: 'Planos do sistema', path: '/planos-sistema' }} pageHeader={false}>
      {loading ? null : (
        <PlanoSistemaForm key={plan?.id || 'novo'} mode={edit ? 'editar' : 'novo'} plan={plan} saving={saving} onSave={save} onBack={back} onCancel={back} />
      )}
    </LayoutBase>
  );
}
