import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import { superAdminService } from '../../services/superAdminService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal } from '../../components/ck';
import { AcademiasLista } from '../../components/ck/pessoas';
import { showSuccess, showError } from '../../utils/toast';

const bool = (v) => v === true || v === 1 || v === '1';
const todayIso = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const daysUntil = (iso, today) => Math.round((new Date(`${iso}T00:00:00`) - new Date(`${today}T00:00:00`)) / 86400000);

const toRow = (a) => {
  const c = a.contrato;
  const contractName = c ? [c.plano_nome || 'Contrato', c.status_id !== 1 && c.status_nome ? c.status_nome.toLowerCase() : null].filter(Boolean).join(', ') : null;
  return {
    id: a.id,
    name: a.nome,
    email: a.email,
    phone: a.telefone,
    cnpj: a.cnpj,
    city: a.cidade,
    uf: a.estado,
    logo: a.logo_url || undefined,
    status: bool(a.ativo) ? 'ativa' : 'inativa',
    students: a.alunos_ativos != null ? Number(a.alunos_ativos) : undefined,
    checkins: Number(a.checkins_mes) || 0,
    contract: contractName,
    contractUntil: c?.data_fim ? String(c.data_fim).slice(0, 10) : null,
  };
};

export default function AcademiasScreen() {
  const router = useRouter();
  const today = todayIso();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [confirm, setConfirm] = useState(null);
  const [toggling, setToggling] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await superAdminService.listarAcademias();
      setRows((response.academias || []).map(toRow));
    } catch (err) {
      setError(err?.message || err?.error || 'Não foi possível carregar as academias.');
    } finally {
      setLoading(false);
    }
  };

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
  }, []);

  const toggleActive = async () => {
    if (!confirm) return;
    const ativar = confirm.status !== 'ativa';
    setToggling(true);
    try {
      await superAdminService.toggleAtivoAcademia(confirm.id, ativar);
      setRows((prev) => prev.map((r) => (r.id === confirm.id ? { ...r, status: ativar ? 'ativa' : 'inativa' } : r)));
      showSuccess(`${confirm.name} ${ativar ? 'reativada' : 'desativada'}`);
      setConfirm(null);
    } catch (err) {
      showError(err?.message || err?.error || 'Não foi possível alterar a situação da academia');
    } finally {
      setToggling(false);
    }
  };

  const ativas = rows.filter((r) => r.status === 'ativa');
  const summary = rows.length ? {
    active: ativas.length,
    students: rows.reduce((s, r) => s + (r.students || 0), 0),
    checkins: rows.reduce((s, r) => s + r.checkins, 0),
    renewals: rows.filter((r) => r.contractUntil && daysUntil(r.contractUntil, today) >= 0 && daysUntil(r.contractUntil, today) <= 30).length,
  } : null;

  const reativar = confirm && confirm.status !== 'ativa';

  return (
    <LayoutBase title="Academias" pageHeader={false}>
      {error ? (
        <Callout tone="danger" title="Não foi possível carregar as academias" action={<Button size="sm" onClick={load}>Tentar de novo</Button>}>{error}</Callout>
      ) : null}
      <div aria-busy={loading || undefined} style={loading ? { opacity: 0.6, pointerEvents: 'none' } : undefined}>
        <AcademiasLista
          rows={rows}
          today={today}
          summary={summary}
          onNew={() => router.push('/academias/novo')}
          onOpen={(r) => router.push(`/academias/${r.id}`)}
          onEdit={(r) => router.push(`/academias/${r.id}`)}
          onContract={(r) => router.push(`/contratos/academia?id=${r.id}&nome=${encodeURIComponent(r.name)}`)}
          onToggleActive={(r) => setConfirm(r)}
        />
      </div>
      <Modal
        open={!!confirm}
        size="sm"
        title={reativar ? `Reativar ${confirm?.name}?` : `Desativar ${confirm?.name}?`}
        description={reativar
          ? 'A academia volta a aparecer como ativa na rede.'
          : 'A academia passa a aparecer como inativa. Alunos, matrículas e contrato ficam guardados.'}
        dismissable={!toggling}
        onClose={() => setConfirm(null)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={toggling} onClick={() => setConfirm(null)}>Cancelar</Button>
            <Button variant={reativar ? 'primary' : 'danger'} icon={reativar ? 'power' : 'ban'} loading={toggling} onClick={toggleActive}>{reativar ? 'Reativar' : 'Desativar'}</Button>
          </>
        )}
      />
    </LayoutBase>
  );
}
