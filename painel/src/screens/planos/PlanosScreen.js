import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import planoService from '../../services/planoService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Icon, IconButton, Modal } from '../../components/ck';
import { PlanosLista } from '../../components/ck/planos';
import { abrirRelatorioPlanos } from '../../utils/relatorioPlanos';
import { showSuccess, showError } from '../../utils/toast';
import { authService } from '../../services/authService';

const bool = (v) => v === true || v === 1 || v === '1';

const toPlan = (p) => ({
  id: p.id,
  name: p.nome,
  amount: Number(p.valor) || 0,
  checkins: Number(p.checkins_semanais) || 0,
  durationDays: Number(p.duracao_dias) || 0,
  active: bool(p.ativo),
  open: bool(p.atual),
  modality: { name: p.modalidade_nome, color: p.modalidade_cor },
  tenantName: p.academia_nome || p.tenant_nome,
  cycles: Array.isArray(p.ciclos)
    ? [...p.ciclos].sort((a, b) => (a.meses || 0) - (b.meses || 0)).map((c) => ({ id: c.id, label: c.frequencia_nome || c.nome, months: c.meses }))
    : undefined,
});

export default function PlanosScreen() {
  const router = useRouter();
  const [planos, setPlanos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [tenants, setTenants] = useState([]);
  const [tenant, setTenant] = useState(null);
  const [tenantQuery, setTenantQuery] = useState('');
  const [confirm, setConfirm] = useState(null);
  const [deactivating, setDeactivating] = useState(false);
  const [gerandoRelatorio, setGerandoRelatorio] = useState(false);

  const loadPlanos = async (superAdmin, selected) => {
    setLoading(true);
    setError('');
    try {
      if (superAdmin && !selected) {
        setPlanos([]);
        return;
      }
      const response = superAdmin ? await planoService.listarTodos(selected.id) : await planoService.listar();
      setPlanos((response.planos || []).map(toPlan));
    } catch (err) {
      setError(err?.error || 'Não foi possível carregar os planos.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    (async () => {
      const user = await authService.getCurrentUser();
      const superAdmin = user?.papel_id === 4;
      setIsSuperAdmin(superAdmin);
      if (superAdmin) {
        try {
          const response = await planoService.listarTodos(null);
          setTenants(response.tenants || []);
        } catch {
          showError('Não foi possível carregar a lista de academias');
        } finally {
          setLoading(false);
        }
      } else {
        loadPlanos(false);
      }
    })();
  }, []);

  const selectTenant = (t) => {
    setTenant(t);
    setTenantQuery('');
    loadPlanos(true, t);
  };

  const deactivate = async () => {
    if (!confirm) return;
    setDeactivating(true);
    try {
      await planoService.desativar(confirm.id);
      showSuccess('Plano desativado');
      setConfirm(null);
      loadPlanos(false);
    } catch (err) {
      showError(err?.error || 'Não foi possível desativar o plano');
    } finally {
      setDeactivating(false);
    }
  };

  const gerarRelatorio = async () => {
    setGerandoRelatorio(true);
    try {
      abrirRelatorioPlanos(await planoService.relatorioPlanosECiclos());
    } catch (err) {
      showError(err?.error || 'Não foi possível gerar o relatório');
    } finally {
      setGerandoRelatorio(false);
    }
  };

  const tenantResults = tenantQuery
    ? tenants.filter((t) => String(t.nome || '').toLowerCase().includes(tenantQuery.toLowerCase())).slice(0, 8)
    : [];

  const tenantPicker = isSuperAdmin ? (
    <section className="ck-card ck-pl__tenant" aria-label="Academia">
      {tenant ? (
        <div className="ck-nm__student">
          <span className="ck-ml__mod" aria-hidden><Icon name="building-2" size={16} /></span>
          <span style={{ flex: 1, minWidth: 0 }}>
            <strong>{tenant.nome}</strong>
            <span>{[tenant.cidade, tenant.estado].filter(Boolean).join('/') || 'Academia selecionada'}</span>
          </span>
          <Button size="sm" variant="ghost" icon="repeat" onClick={() => { setTenant(null); setPlanos([]); }}>Trocar</Button>
        </div>
      ) : (
        <div className="ck-nm__combo">
          <div className="ck-control">
            <Icon name="search" size={18} className="ck-control__icon" />
            <input className="ck-control__input" placeholder="Buscar academia pelo nome" value={tenantQuery} aria-label="Buscar academia" onChange={(e) => setTenantQuery(e.target.value)} />
            {tenantQuery ? <IconButton icon="x" label="Limpar busca" size="sm" onClick={() => setTenantQuery('')} /> : null}
          </div>
          {tenantQuery ? (
            <ul className="ck-nm__results" role="listbox">
              {tenantResults.length ? tenantResults.map((t) => (
                <li key={t.id}>
                  <button type="button" role="option" onClick={() => selectTenant(t)}>
                    <span className="ck-ml__mod" aria-hidden><Icon name="building-2" size={16} /></span>
                    <span><strong>{t.nome}</strong><span>{[t.cidade, t.estado].filter(Boolean).join('/')}</span></span>
                  </button>
                </li>
              )) : <li className="ck-nm__noresult">Nenhuma academia com esse nome.</li>}
            </ul>
          ) : null}
        </div>
      )}
    </section>
  ) : null;

  return (
    <LayoutBase title="Planos" pageHeader={false}>
      <PlanosLista
        plans={planos}
        loading={loading && (!isSuperAdmin || !!tenant)}
        error={error}
        readOnly={isSuperAdmin}
        showTenant={false}
        toolbarStart={tenantPicker}
        emptyHint={isSuperAdmin && !tenant ? 'Escolha uma academia acima para ver os planos dela.' : undefined}
        onNew={() => router.push('/planos/novo')}
        onOpen={(p) => router.push(`/planos/${p.id}`)}
        onDeactivate={(p) => setConfirm(p)}
        onReport={gerarRelatorio}
        reportLoading={gerandoRelatorio}
      />
      <Modal
        open={!!confirm}
        size="sm"
        title="Desativar plano?"
        description={confirm ? `"${confirm.name}" deixa de aparecer no app e na nova matrícula.` : undefined}
        dismissable={!deactivating}
        onClose={() => setConfirm(null)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={deactivating} onClick={() => setConfirm(null)}>Cancelar</Button>
            <Button variant="danger" icon="power" loading={deactivating} onClick={deactivate}>Desativar</Button>
          </>
        )}
      >
        <p className="ck-pl__confirm">Só é possível desativar um plano que nenhum aluno ativo esteja usando.</p>
      </Modal>
    </LayoutBase>
  );
}
