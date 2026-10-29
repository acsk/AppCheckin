import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import alunoService from '../../services/alunoService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import { Button, Callout, Modal } from '../../components/ck';
import { AlunosLista, maskPhone, titleCase } from '../../components/ck/pessoas';
import { showSuccess, showError } from '../../utils/toast';
import { API_V2_BASE_URL } from '../../config/api';
import { fotoUrl } from '../../utils/prospeccao';

const PAGE_SIZE = 20;
const bool = (v) => v === true || v === 1 || v === '1';

export const toStudentRow = (a) => ({
  id: a.id,
  userId: a.usuario_id,
  name: a.nome || '',
  email: a.email || '',
  phone: a.telefone ? maskPhone(a.telefone) : '',
  cpf: a.cpf || '',
  avatar: fotoUrl(a.foto_caminho, API_V2_BASE_URL),
  status: bool(a.ativo) ? 'ativo' : 'inativo',
  payment: a.plano ? (a.pagamento_ativo === true ? 'em_dia' : 'pendente') : undefined,
  plan: a.plano ? {
    name: a.plano.nome,
    modality: a.plano.modalidade_nome || '',
    modalityIcon: a.plano.modalidade_icone || undefined,
    amount: Number(a.plano.valor) || 0,
  } : null,
});

export default function AlunosScreen() {
  const router = useRouter();
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [counts, setCounts] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [busca, setBusca] = useState('');
  const [filter, setFilter] = useState('todos');
  const [page, setPage] = useState(1);
  const [liberado, setLiberado] = useState(false);
  const [confirm, setConfirm] = useState(null);
  const [toggling, setToggling] = useState(false);
  const request = useRef(0);

  // Busca, filtro, contagens e paginação no servidor: só a página atual vem da API
  const load = useCallback(async () => {
    const id = ++request.current;
    setLoading(true);
    setError('');
    try {
      const r = await alunoService.listar({ pagina: page, porPagina: PAGE_SIZE, busca, filtro: filter });
      if (id !== request.current) return;
      const lista = (r.alunos || []).map(toStudentRow);
      // Última página esvaziou (ex.: aluno desativado com filtro "ativos"): volta uma
      if (!lista.length && page > 1 && r.total > 0) { setPage(Math.max(1, Math.ceil(r.total / PAGE_SIZE))); return; }
      setRows(lista);
      setTotal(r.total || 0);
      setCounts(r.contagens || {});
    } catch (err) {
      if (id === request.current) setError(err?.message || err?.error || 'Não foi possível carregar os alunos.');
    } finally {
      if (id === request.current) setLoading(false);
    }
  }, [page, busca, filter]);

  useEffect(() => {
    (async () => {
      try {
        const user = await authService.getCurrentUser();
        // Permissão (não papel): ex. Financeiro vê alunos. Super admin sempre.
        if (!user || (user.papel_id !== 4 && !(await authService.temPermissao('alunos.ver')))) {
          showError('Você não tem acesso a esta tela');
          router.replace('/');
          return;
        }
      } catch {
        router.replace('/');
        return;
      }
      setLiberado(true);
    })();
  }, []);

  useEffect(() => { if (liberado) load(); }, [liberado, load]);
  useEffect(() => {
    const t = setTimeout(() => { setBusca(query.trim()); setPage(1); }, 300);
    return () => clearTimeout(t);
  }, [query]);

  const toggleActive = async () => {
    if (!confirm) return;
    const ativar = confirm.status === 'inativo';
    setToggling(true);
    try {
      await alunoService.toggleStatus(confirm.id, ativar);
      showSuccess(ativar ? 'Aluno reativado' : 'Aluno desativado');
      setConfirm(null);
      load();
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
      <div aria-busy={loading || undefined} style={loading && rows.length ? { opacity: 0.6, transition: 'opacity .15s' } : undefined}>
        <AlunosLista
          rows={rows}
          total={total}
          counts={counts}
          page={page}
          pageSize={PAGE_SIZE}
          onPageChange={setPage}
          query={query}
          onQueryChange={setQuery}
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
