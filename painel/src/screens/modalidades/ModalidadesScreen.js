import React, { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal } from '../../components/ck';
import { ModalidadesLista } from '../../components/ck/modalidades';
import { showSuccess, showError } from '../../utils/toast';

const bool = (v) => v === true || v === 1 || v === '1';

export const modalidadeFromApi = (m) => ({
  id: m.id,
  name: m.nome,
  description: m.descricao || '',
  icon: m.icone || 'dumbbell',
  color: m.cor || null,
  active: bool(m.ativo),
  // A lista já conta só os ativos; o detalhe traz também os inativos
  plans: m.planos_count != null ? Number(m.planos_count) : (m.planos || []).filter((p) => bool(p.ativo)).length,
});

export default function ModalidadesScreen() {
  const router = useRouter();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [confirm, setConfirm] = useState(null);
  const [toggling, setToggling] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const lista = await modalidadeService.listar();
      setRows(lista.map(modalidadeFromApi));
    } catch (err) {
      setError(err?.response?.data?.message || err?.message || 'Não foi possível carregar as modalidades.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const toggleActive = async () => {
    if (!confirm) return;
    setToggling(true);
    try {
      // DELETE alterna ativo/inativo no backend
      await modalidadeService.excluir(confirm.id);
      const ativa = !confirm.active;
      setRows((prev) => prev.map((r) => (r.id === confirm.id ? { ...r, active: ativa } : r)));
      showSuccess(`${confirm.name} ${ativa ? 'ativada' : 'desativada'}`);
      setConfirm(null);
    } catch (err) {
      showError(err?.message || err?.error || 'Não foi possível alterar a modalidade');
    } finally {
      setToggling(false);
    }
  };

  const ativar = confirm && !confirm.active;

  return (
    <LayoutBase title="Modalidades" pageHeader={false}>
      {error ? (
        <Callout tone="danger" title="Não foi possível carregar as modalidades" action={<Button size="sm" onClick={load}>Tentar de novo</Button>}>{error}</Callout>
      ) : null}
      <ModalidadesLista
        rows={rows}
        loading={loading && !rows.length}
        onNew={() => router.push('/modalidades/novo')}
        onEdit={(r) => router.push(`/modalidades/${r.id}`)}
        onOpenPlans={() => router.push('/planos')}
        onToggleActive={(r) => setConfirm(r)}
      />
      <Modal
        open={!!confirm}
        size="sm"
        title={ativar ? `Ativar ${confirm?.name}?` : `Desativar ${confirm?.name}?`}
        description={ativar
          ? 'A modalidade volta a aparecer no app e a aceitar novas matrículas.'
          : 'A modalidade some do app e não aceita novas matrículas. Quem já está matriculado continua.'}
        dismissable={!toggling}
        onClose={() => setConfirm(null)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={toggling} onClick={() => setConfirm(null)}>Cancelar</Button>
            <Button variant={ativar ? 'primary' : 'danger'} icon={ativar ? 'power' : 'ban'} loading={toggling} onClick={toggleActive}>{ativar ? 'Ativar' : 'Desativar'}</Button>
          </>
        )}
      />
    </LayoutBase>
  );
}
