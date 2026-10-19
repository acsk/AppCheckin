import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import planosSistemaService from '../../services/planosSistemaService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Modal } from '../../components/ck';
import { PlanosSistemaLista, systemPlanFromApi } from '../../components/ck/planosSistema';
import { showSuccess, showError } from '../../utils/toast';

export default function PlanosSistemaScreen() {
  const router = useRouter();
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [toDeactivate, setToDeactivate] = useState(null);
  const [busy, setBusy] = useState(false);

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
    setError(null);
    try {
      const res = await planosSistemaService.listar();
      setPlans((res.planos || []).map(systemPlanFromApi));
    } catch (e) {
      setError(e.message || 'Tente de novo em instantes.');
    } finally {
      setLoading(false);
    }
  };

  const update = async (plan, data, message) => {
    try {
      await planosSistemaService.atualizar(plan.id, data);
      showSuccess(message);
      load();
    } catch (e) {
      showError(e.message || 'Não foi possível atualizar o plano');
    }
  };

  const toggleSale = (plan) => update(
    plan,
    { atual: plan.onSale ? 0 : 1 },
    plan.onSale ? `${plan.name} saiu de venda` : `${plan.name} está à venda`,
  );

  const toggleActive = (plan) => {
    if (plan.active) setToDeactivate(plan);
    else update(plan, { ativo: 1 }, `${plan.name} reativado`);
  };

  const confirmDeactivate = async () => {
    setBusy(true);
    try {
      await planosSistemaService.desativar(toDeactivate.id);
      showSuccess(`${toDeactivate.name} desativado`);
      setToDeactivate(null);
      load();
    } catch (e) {
      showError(e.message || 'Não foi possível desativar o plano');
    } finally {
      setBusy(false);
    }
  };

  return (
    <LayoutBase title="Planos do sistema" pageHeader={false}>
      <PlanosSistemaLista
        plans={plans}
        loading={loading}
        error={error}
        onNew={() => router.push('/planos-sistema/novo')}
        onEdit={(p) => router.push(`/planos-sistema/${p.id}`)}
        onNewContract={(p) => router.push(p ? `/contratos/novo?planoId=${p.id}` : '/contratos/novo')}
        onToggleSale={toggleSale}
        onToggleActive={toggleActive}
      />
      <Modal
        open={!!toDeactivate}
        onClose={() => !busy && setToDeactivate(null)}
        size="sm"
        title="Desativar plano?"
        description={toDeactivate ? `${toDeactivate.name} não poderá ser usado em contratos novos nem em trocas de plano.` : ''}
        footer={(
          <>
            <Button variant="ghost" onClick={() => setToDeactivate(null)} disabled={busy}>Manter ativo</Button>
            <Button variant="danger" icon="power" loading={busy} onClick={confirmDeactivate}>Desativar plano</Button>
          </>
        )}
      >
        {toDeactivate?.contracts ? (
          <p style={{ margin: 0 }}>
            {toDeactivate.contracts === 1 ? '1 academia usa' : `${toDeactivate.contracts} academias usam`} este plano. Troque o plano delas em Contratos antes de desativar.
          </p>
        ) : <p style={{ margin: 0 }}>Dá para reativar depois pelo menu do plano.</p>}
      </Modal>
    </LayoutBase>
  );
}
