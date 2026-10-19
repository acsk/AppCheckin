import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import meuContratoService from '../../services/meuContratoService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout } from '../../components/ck';
import { TrocarPlanoContrato, planFromApi } from '../../components/ck/contratos';
import { BILLING, PagarModal } from '../../components/ck/meuPlano';
import { openUrl } from './MeuPlanoScreen';
import { showSuccess, showError } from '../../utils/toast';

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export default function TrocarMeuPlanoScreen() {
  const router = useRouter();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [paid, setPaid] = useState(null);

  useEffect(() => {
    (async () => {
      const user = await authService.getCurrentUser();
      if (!user || user.papel_id !== 3) {
        router.replace('/');
        return;
      }
      try {
        setData(await meuContratoService.buscar());
      } catch (err) {
        setError(err.message);
      }
    })();
  }, []);

  const back = () => router.push('/meu-plano');

  const save = async (v) => {
    setSaving(true);
    try {
      const r = await meuContratoService.trocarPlano({ planoId: v.planId, formaPagamento: v.paymentId, motivo: v.notes });
      if (r.aviso) showError(r.aviso);
      if (r.cobranca) {
        // Plano pago: mostra o Pix (ou o link do cartão) na hora, sem precisar voltar à lista
        setPaid({ cobranca: r.cobranca, valor: r.valor });
      } else {
        showSuccess(r.message || 'Plano trocado');
        back();
      }
    } catch (err) {
      showError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const c = data?.contrato;
  const toPlan = (p) => planFromApi({ ...p, nome: p.nome, max_alunos: p.max_alunos, max_admins: p.max_admins, prazo_dias: p.prazo_dias });
  const current = c?.plano ? toPlan(c.plano) : null;
  const methods = (data?.formas || []).filter((f) => BILLING[f]).map((f) => ({ id: f, name: BILLING[f].name }));

  return (
    <LayoutBase title="Trocar plano" pageHeader={false}>
      {error ? (
        <Callout tone="danger" title="Não foi possível carregar os planos" action={<Button size="sm" onClick={back}>Voltar</Button>}>{error}</Callout>
      ) : !data ? (
        <p className="ck-al-hint" role="status">Carregando planos…</p>
      ) : (
        <>
          {!methods.length ? (
            <Callout tone="info" title="Pagamento online indisponível" className="ck-ct-scope">
              No momento só é possível trocar para planos grátis por aqui. Para um plano pago, fale com o suporte do Check-in.
            </Callout>
          ) : null}
          <TrocarPlanoContrato
            contract={{ id: c?.id, academy: data.academia?.nome || 'A academia', plan: current?.name, pending: Number(c?.status_id) === 2 }}
            currentPlan={current || { id: null, name: 'Sem plano', type: 'gratuito' }}
            plans={(data.planos || []).map(toPlan).filter((p) => methods.length || p.type === 'gratuito' || p.id === current?.id)}
            paymentMethods={methods}
            activeStudents={Number(data.alunos_ativos) || 0}
            today={today()}
            saving={saving}
            blockOverLimit
            payOnSave
            backLabel="Plano do Check-in"
            description="Escolha o novo plano do Check-in. Nos planos pagos, a primeira cobrança sai na hora e o plano é ativado quando ela for paga."
            onSave={save}
            onBack={back}
            onCancel={back}
          />
        </>
      )}
      <PagarModal
        open={!!paid}
        payment={paid ? { valor: paid.valor || 0, plano_nome: null } : null}
        formas={data?.formas}
        result={paid?.cobranca}
        onOpenUrl={openUrl}
        onGenerate={() => {}}
        onClose={() => { setPaid(null); showSuccess('Plano trocado'); back(); }}
      />
    </LayoutBase>
  );
}
