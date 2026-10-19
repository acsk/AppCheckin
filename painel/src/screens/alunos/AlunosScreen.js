import React, { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import alunoService from '../../services/alunoService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal } from '../../components/ck';
import { AlunosLista, digits, maskPhone, titleCase } from '../../components/ck/pessoas';
import { showSuccess, showError } from '../../utils/toast';

const PAGE_SIZE = 20;
const bool = (v) => v === true || v === 1 || v === '1';

export const toStudentRow = (a) => ({
  id: a.id,
  userId: a.usuario_id,
  name: a.nome || '',
  email: a.email || '',
  phone: a.telefone ? maskPhone(a.telefone) : '',
  cpf: a.cpf || '',
  status: bool(a.ativo) ? 'ativo' : 'inativo',
  payment: a.plano ? (a.pagamento_ativo === true ? 'em_dia' : 'pendente') : undefined,
  plan: a.plano ? {
    name: a.plano.nome,
    modality: a.plano.modalidade_nome || '',
    modalityIcon: a.plano.modalidade_icone || undefined,
    amount: Number(a.plano.valor) || 0,
  } : null,
});

const matches = (r, f) => {
  if (f === 'ativos') return r.status !== 'inativo';
  if (f === 'inativos') return r.status === 'inativo';
  if (f === 'sem_plano') return !r.plan;
  if (f === 'pendentes') return r.payment === 'pendente' || r.payment === 'atrasado';
  return true;
};

export default function AlunosScreen() {
  const router = useRouter();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState('todos');
  const [page, setPage] = useState(1);
  const [confirm, setConfirm] = useState(null);
  const [toggling, setToggling] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const response = await alunoService.listar();
      const lista = Array.isArray(response) ? response : (response.alunos || []);
      setRows(lista.map(toStudentRow));
    } catch (err) {
      setError(err?.message || err?.error || 'Não foi possível carregar os alunos.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    (async () => {
      try {
        const user = await authService.getCurrentUser();
        if (!user || ![3, 4].includes(user.papel_id)) {
          showError('Acesso restrito aos administradores');
          router.replace('/');
          return;
        }
      } catch {
        router.replace('/');
        return;
      }
      load();
    })();
  }, []);

  // Busca e filtro rodam aqui para que a contagem das pílulas e a paginação reflitam a lista inteira
  const searched = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return rows;
    const dq = digits(q);
    return rows.filter((r) => `${r.name} ${r.email}`.toLowerCase().includes(q)
      || (dq.length >= 3 && `${digits(r.cpf)} ${digits(r.phone)}`.includes(dq)));
  }, [rows, query]);

  const counts = useMemo(() => ({
    todos: searched.length,
    ativos: searched.filter((r) => matches(r, 'ativos')).length,
    pendentes: searched.filter((r) => matches(r, 'pendentes')).length,
    sem_plano: searched.filter((r) => matches(r, 'sem_plano')).length,
    inativos: searched.filter((r) => matches(r, 'inativos')).length,
  }), [searched]);

  const filtered = useMemo(() => searched.filter((r) => matches(r, filter)), [searched, filter]);
  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const current = Math.min(page, pages);
  const pageRows = filtered.slice((current - 1) * PAGE_SIZE, current * PAGE_SIZE);

  const toggleActive = async () => {
    if (!confirm) return;
    const ativar = confirm.status === 'inativo';
    setToggling(true);
    try {
      await alunoService.toggleStatus(confirm.id, ativar);
      setRows((prev) => prev.map((r) => (r.id === confirm.id ? { ...r, status: ativar ? 'ativo' : 'inativo' } : r)));
      showSuccess(ativar ? 'Aluno reativado' : 'Aluno desativado');
      setConfirm(null);
    } catch (err) {
      showError(err?.message || err?.error || 'Não foi possível alterar a situação do aluno');
    } finally {
      setToggling(false);
    }
  };

  const confirmName = confirm ? titleCase(confirm.name) : '';
  const reativar = confirm?.status === 'inativo';

  return (
    <LayoutBase title="Alunos" pageHeader={false}>
      {error ? (
        <Callout tone="danger" title="Não foi possível carregar os alunos" action={<Button size="sm" onClick={load}>Tentar de novo</Button>}>{error}</Callout>
      ) : null}
      <div aria-busy={loading || undefined} style={loading ? { opacity: 0.6, pointerEvents: 'none' } : undefined}>
        <AlunosLista
          rows={pageRows}
          total={filtered.length}
          counts={counts}
          page={current}
          pageSize={PAGE_SIZE}
          onPageChange={setPage}
          query={query}
          onQueryChange={(v) => { setQuery(v); setPage(1); }}
          filter={filter}
          onFilterChange={(v) => { setFilter(v); setPage(1); }}
          onNew={() => router.push('/alunos/novo')}
          onOpen={(r) => router.push(`/alunos/${r.id}`)}
          onEdit={(r) => router.push(`/alunos/${r.id}?edit=true`)}
          onEnroll={(r) => router.push(`/matriculas/novo?usuario_id=${r.userId}`)}
          onToggleActive={(r) => setConfirm(r)}
        />
      </div>
      <Modal
        open={!!confirm}
        size="sm"
        title={reativar ? `Reativar ${confirmName}?` : `Desativar ${confirmName}?`}
        description={reativar
          ? 'O aluno volta a entrar no app e a fazer check-in, se tiver plano ativo.'
          : 'O aluno deixa de entrar no app e de fazer check-in. O histórico fica guardado.'}
        dismissable={!toggling}
        onClose={() => setConfirm(null)}
        footer={(
          <>
            <span style={{ flex: 1 }} />
            <Button variant="ghost" disabled={toggling} onClick={() => setConfirm(null)}>Cancelar</Button>
            <Button variant={reativar ? 'primary' : 'danger'} icon={reativar ? 'power' : 'ban'} loading={toggling} onClick={toggleActive}>
              {reativar ? 'Reativar' : 'Desativar'}
            </Button>
          </>
        )}
      />
    </LayoutBase>
  );
}
