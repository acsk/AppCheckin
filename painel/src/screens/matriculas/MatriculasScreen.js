import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { MatriculasLista, Button } from '../../components/ck';
import ExcluirMatriculaModal from '../../components/ck/ExcluirMatriculaModal';
import { matriculaService } from '../../services/matriculaService';
import { authService } from '../../services/authService';
import { obterMensagemErro } from '../../utils/errorHandler';
import { showError, showSuccess } from '../../utils/toast';

const STATUS = { 1: 'ativa', 2: 'vencida', 3: 'cancelada', 4: 'finalizada', 5: 'pendente', 6: 'bloqueada' };
const normalize = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
const statusOf = (row) => STATUS[Number(row.status_id)] || (row.status === 'bloqueado' ? 'bloqueada' : normalize(row.status));
const listOf = (data) => Array.isArray(data) ? data : data?.matriculas || data?.data?.matriculas || [];
const emptyPreview = { row: null, data: null, loading: false, error: '', pacoteId: null };

export default function MatriculasScreen() {
  const router = useRouter();
  const [matriculas, setMatriculas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState('todas');
  const [page, setPage] = useState(1);
  const [reload, setReload] = useState(0);
  // Excluir/vincular matrícula: permissão "Ações financeiras avançadas" (concedida pelo super admin)
  // ou super admin no "Acessar como academia". A API confere a mesma permissão.
  const [podeExcluir, setPodeExcluir] = useState(false);
  const [preview, setPreview] = useState(emptyPreview);
  const [deleting, setDeleting] = useState(false);
  const cache = useRef({});
  const deleteBusy = useRef(false);
  const previewRequest = useRef(0);
  const pageSize = 20;
  const today = new Date();
  const todayISO = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

  useEffect(() => {
    let active = true;
    authService.temPermissao('financeiro_avancado').then((pode) => { if (active) setPodeExcluir(pode); }).catch((err) => {
      console.error('Erro ao carregar permissões:', err);
      if (active) showError('Não foi possível carregar as permissões do usuário.');
    });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    let active = true;
    const load = async () => {
      setLoading(true);
      setError('');
      try {
        if (cache.current[status]) {
          setMatriculas(cache.current[status]);
          return;
        }
        const rows = [];
        for (let pagina = 1; pagina <= 100; pagina += 1) {
          const data = await matriculaService.listar({
            pagina, por_pagina: 200,
            status: status === 'todas' ? undefined : status === 'bloqueada' ? 'bloqueado' : status,
            incluir_inativos: status === 'todas',
          });
          if (!active) return;
          const list = listOf(data);
          rows.push(...list);
          const meta = data?.paginacao || data?.pagination || data?.meta || {};
          const total = meta.total || data?.total || data?.total_registros || data?.total_itens;
          const pages = Number(meta.total_paginas || meta.totalPages || meta.last_page || (total ? Math.ceil(total / 200) : 0));
          if ((pages && pagina >= pages) || (!pages && list.length < 200)) break;
          if (pagina === 100) throw new Error('A consulta excedeu 100 páginas. Refine o filtro para carregar as matrículas.');
        }
        if (active) {
          cache.current[status] = rows;
          setMatriculas(rows);
        }
      } catch (err) {
        if (!active) return;
        console.error('Erro ao carregar matrículas:', err);
        setError(obterMensagemErro(err, 'Não foi possível carregar as matrículas.'));
      } finally {
        if (active) setLoading(false);
      }
    };
    load();
    return () => { active = false; };
  }, [status, reload]);

  const filtered = useMemo(() => matriculas.filter((row) => {
    const term = normalize(query);
    return (status === 'todas' || statusOf(row) === status) &&
      (!term || normalize([row.usuario_nome, row.usuario_email, row.plano_nome, row.modalidade_nome, `#${row.id}`].join(' ')).includes(term));
  }), [matriculas, query, status]);
  const counts = status === 'todas' ? matriculas.reduce((result, row) => {
    const key = statusOf(row);
    result[key] = (result[key] || 0) + 1;
    return result;
  }, { todas: matriculas.length }) : undefined;
  const currentPage = Math.min(page, Math.max(1, Math.ceil(filtered.length / pageSize)));
  const rows = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize).map((row) => ({
    id: row.id, code: `#${row.id}`, student: { name: row.usuario_nome || 'Aluno', email: row.usuario_email || '' },
    modality: row.modalidade_nome || row.modalidade?.nome, plan: row.plano_nome, amount: Number(row.valor),
    modalityIcon: row.modalidade_icone || row.modalidade?.icone, modalityColor: row.modalidade_cor || row.modalidade?.cor,
    cycle: row.ciclo_nome || row.frequencia_nome,
    startDate: row.data_inicio?.slice(0, 10),
    accessUntil: (row.proxima_data_vencimento || row.data_vencimento)?.slice(0, 10),
    status: statusOf(row), href: `/matriculas/detalhe?id=${row.id}`,
    original: row,
  }));
  const refresh = () => { cache.current = {}; setReload((v) => v + 1); };
  const closePreview = () => {
    if (deleteBusy.current) return;
    previewRequest.current += 1;
    setPreview(emptyPreview);
  };
  const openPreview = async (row) => {
    if (!podeExcluir) return;
    const request = ++previewRequest.current;
    setPreview({ ...emptyPreview, row, loading: true });
    try {
      const data = await matriculaService.deletePreview(row.id);
      if (request === previewRequest.current) setPreview({ ...emptyPreview, row, data });
    } catch (err) {
      if (request !== previewRequest.current) return;
      console.error('Erro ao carregar prévia de exclusão:', err);
      setPreview({ ...emptyPreview, row, error: obterMensagemErro(err, 'Não foi possível carregar a prévia de exclusão.') });
    }
  };
  const pacoteId = preview.data?.matricula?.pacote_contrato_id || preview.pacoteId;
  const remove = async () => {
    if (deleteBusy.current || !podeExcluir || !preview.data || pacoteId || preview.error) return;
    deleteBusy.current = true;
    setDeleting(true);
    try {
      const result = await matriculaService.deletar(preview.data.matricula?.id || preview.row.id);
      showSuccess(result?.message || 'Matrícula excluída com sucesso');
      setPreview(emptyPreview);
      refresh();
    } catch (err) {
      console.error('Erro ao excluir matrícula:', err);
      setPreview((prev) => ({
        ...prev,
        error: obterMensagemErro(err, 'Não foi possível excluir a matrícula.').replace(/DELETE\s+\/admin\/pacotes\/contratos\/\{contratoId\}/gi, '').trim(),
        pacoteId: err.pacote_contrato_id || err.response?.data?.pacote_contrato_id,
      }));
    } finally {
      deleteBusy.current = false;
      setDeleting(false);
    }
  };

  const [vinculando, setVinculando] = useState(false);
  const vincular = async (id, atualId) => {
    setVinculando(true);
    try {
      const result = await matriculaService.vincularAtual(id, atualId);
      showSuccess(result?.message || 'Matrícula vinculada');
      setPreview(emptyPreview);
      refresh();
    } catch (err) {
      showError(obterMensagemErro(err, 'Não foi possível vincular a matrícula.'));
    } finally {
      setVinculando(false);
    }
  };

  return (
    <LayoutBase title="Matrículas" pageHeader={false}>
      <MatriculasLista
        rows={rows}
        query={query}
        onQueryChange={(value) => { setQuery(value); setPage(1); }}
        status={status}
        onStatusChange={(value) => { setStatus(value); setPage(1); }}
        counts={counts}
        total={loading || error ? 0 : filtered.length}
        page={currentPage}
        pageSize={pageSize}
        onPageChange={setPage}
        today={todayISO}
        loading={loading}
        error={error}
        onNew={() => router.push('/matriculas/novo')}
        onOpen={(row) => router.push(row.href)}
        onDelete={podeExcluir ? (row) => openPreview(row.original) : undefined}
        toolbarActions={<Button size="sm" icon="refresh-cw" loading={loading} onClick={refresh}>{error ? 'Tentar novamente' : 'Atualizar'}</Button>}
      />
      <ExcluirMatriculaModal key={preview.row?.id || 'fechada'} preview={preview} deleting={deleting} pacoteId={pacoteId}
        onClose={closePreview} onConfirm={remove} onPacotes={() => router.push('/pacotes')}
        onRetry={() => openPreview(preview.row)} onVincular={vincular} vinculando={vinculando} />
    </LayoutBase>
  );
}
