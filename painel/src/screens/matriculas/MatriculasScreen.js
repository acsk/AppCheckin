import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { MatriculasLista, Button, Modal } from '../../components/ck';
import { matriculaService } from '../../services/matriculaService';
import { authService } from '../../services/authService';
import { obterMensagemErro } from '../../utils/errorHandler';
import { showError, showSuccess } from '../../utils/toast';

const STATUS = { 1: 'ativa', 2: 'vencida', 3: 'cancelada', 4: 'finalizada', 5: 'pendente', 6: 'bloqueada' };
const normalize = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
const statusOf = (row) => STATUS[Number(row.status_id)] || (row.status === 'bloqueado' ? 'bloqueada' : normalize(row.status));
const listOf = (data) => Array.isArray(data) ? data : data?.matriculas || data?.data?.matriculas || [];
const text = (value) => value == null ? '-' : typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value);
const count = (value) => Array.isArray(value) ? value.length : value ? 1 : 0;
const formatDate = (value) => value ? String(value).slice(0, 10).split('-').reverse().join('/') : '-';
const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
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
  const [userId, setUserId] = useState(null);
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
    authService.getCurrentUser().then((user) => { if (active) setUserId(Number(user?.id)); }).catch((err) => {
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
    modality: row.modalidade_nome, plan: row.plano_nome, amount: Number(row.valor),
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
    if (userId !== 3) return;
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
    if (deleteBusy.current || userId !== 3 || !preview.data || pacoteId || preview.error) return;
    deleteBusy.current = true;
    setDeleting(true);
    try {
      const result = await matriculaService.deletar(preview.data.matricula?.id || preview.row.id);
      showSuccess(result?.message || 'Matrícula cancelada com sucesso');
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
  const data = preview.data;
  const enrollment = data?.matricula || {};
  const summary = data?.resumo;
  const previewRow = (label, value) => value == null || value === '' ? null : <div key={label}><dt>{label}</dt><dd>{text(value)}</dd></div>;
  const summaryText = typeof summary === 'object' && summary && !Array.isArray(summary)
    ? summary.message || summary.mensagem || text(Object.fromEntries(Object.entries(summary).filter(([key]) => key !== 'impacto')))
    : Array.isArray(summary) ? summary.join('\n') : summary;

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
        onDelete={userId === 3 ? (row) => openPreview(row.original) : undefined}
        toolbarActions={<Button size="sm" icon="refresh-cw" loading={loading} onClick={refresh}>{error ? 'Tentar novamente' : 'Atualizar'}</Button>}
      />
      <Modal
        open={!!preview.row}
        title="Revisar exclusão"
        description="Confira os registros relacionados antes de confirmar."
        dismissable={!deleting}
        onClose={closePreview}
        footer={<>
          <Button variant="ghost" disabled={deleting} onClick={closePreview}>Cancelar</Button>
          {!preview.error && !pacoteId ? <Button variant="primary" disabled={preview.loading || !data} loading={deleting} onClick={remove}>Confirmar exclusão</Button> : null}
        </>}
      >
        {preview.loading ? <p role="status">Carregando prévia…</p> : preview.error || pacoteId ? (
          <div role="alert">
            <p>{preview.error || 'Não é possível excluir esta matrícula diretamente. Exclua pelo módulo de pacotes.'}</p>
            {pacoteId ? <Button onClick={() => router.push('/pacotes')}>Ir para Pacotes (Contrato #{pacoteId})</Button> : null}
          </div>
        ) : data ? (
          <div className="ck-enrollment-review">
            {summaryText ? <><h3>Resumo</h3><p style={{ whiteSpace: 'pre-wrap' }}>{summaryText}</p></> : null}
            <h3>Aluno</h3><dl>
              {previewRow('Nome', data.aluno?.nome)}{previewRow('Email', data.aluno?.email)}{previewRow('Telefone', data.aluno?.telefone)}
            </dl>
            <h3>Plano</h3><dl>
              {previewRow('Plano', data.plano?.nome)}{previewRow('Modalidade', data.plano?.modalidade?.nome)}
              {previewRow('Valor do plano', data.plano?.valor != null ? money(data.plano.valor) : null)}
              {previewRow('Duração', data.plano?.duracao_dias != null ? `${data.plano.duracao_dias} dias` : null)}
              {previewRow('Check-ins semanais', data.plano?.checkins_semanais)}
            </dl>
            <h3>Matrícula</h3><dl>
              {previewRow('ID', enrollment.id)}{previewRow('Status', enrollment.status_nome || STATUS[enrollment.status_id])}
              {previewRow('Valor', enrollment.valor != null ? money(enrollment.valor) : null)}
              {previewRow('Data matrícula', enrollment.data_matricula ? formatDate(enrollment.data_matricula) : null)}
              {previewRow('Início', enrollment.data_inicio ? formatDate(enrollment.data_inicio) : null)}
              {previewRow('Vencimento', enrollment.data_vencimento ? formatDate(enrollment.data_vencimento) : null)}
              {previewRow('Próximo vencimento', enrollment.proxima_data_vencimento ? formatDate(enrollment.proxima_data_vencimento) : null)}
              {previewRow('Tipo cobrança', enrollment.tipo_cobranca)}{previewRow('Dia vencimento', enrollment.dia_vencimento)}{previewRow('Observações', enrollment.observacoes)}
            </dl>
            <h3>Registros relacionados</h3><dl>
              {previewRow('Pagamentos do plano', count(data.pagamentos_plano))}
              {previewRow('Assinaturas', count(data.assinaturas))}
              {previewRow('Assinaturas Mercado Pago', count(data.assinaturas_mercadopago))}
              {previewRow('Pagamentos Mercado Pago', count(data.pagamentos_mercadopago))}
            </dl>
          </div>
        ) : null}
      </Modal>
    </LayoutBase>
  );
}
