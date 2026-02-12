import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  ActivityIndicator,
  useWindowDimensions,
  Alert,
  Modal,
  Platform,
  ToastAndroid,
  TextInput,
} from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { matriculaService } from '../../services/matriculaService';
import { StyleSheet } from 'react-native';

export default function MatriculasScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const [matriculas, setMatriculas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isSearching, setIsSearching] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [serverSearchTerm, setServerSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('todos');
  const [pagina, setPagina] = useState(1);
  const [porPagina] = useState(15
    
  );
  const [totalPaginas, setTotalPaginas] = useState(1);
  const [totalItens, setTotalItens] = useState(0);
  const [cache, setCache] = useState({});
  const [deletePreview, setDeletePreview] = useState({
    visible: false,
    loading: false,
    data: null,
    error: null,
    matricula: null,
  });
  const [deleting, setDeleting] = useState(false);
  const statusEffectRef = useRef(false);

  const isMobile = width < 768;

  // Função de filtro
  const filteredMatriculas = matriculas.filter(matricula => {
    const term = searchTerm.trim().toLowerCase();
    const matchSearch = term === '' || 
      matricula.usuario_nome?.toLowerCase().includes(term) ||
      matricula.usuario_email?.toLowerCase().includes(term) ||
      matricula.plano_nome?.toLowerCase().includes(term) ||
      matricula.modalidade_nome?.toLowerCase().includes(term);
    
    // Mapear filtro de texto para status_id
    const statusMap = { 'ativa': 1, 'vencida': 2, 'cancelada': 3, 'finalizada': 4, 'pendente': 5, 'bloqueado': 6 };
    const statusId = statusMap[statusFilter];
    const statusTexto = matricula.status?.toString().toLowerCase();
    const matchStatus = statusFilter === 'todos' ||
      (matricula.status_id != null ? matricula.status_id === statusId : statusTexto === statusFilter);
    
    return matchSearch && matchStatus;
  });

  useEffect(() => {
    carregarMatriculas();
  }, []);

  useEffect(() => {
    if (!statusEffectRef.current) {
      statusEffectRef.current = true;
      return;
    }
    setPagina(1);
    carregarMatriculas({ pagina: 1, busca: serverSearchTerm });
  }, [statusFilter]);

  const buildCacheKey = ({ pagina, porPagina, status, incluirInativos, alunoId, busca }) =>
    JSON.stringify({ pagina, porPagina, status, incluirInativos, alunoId, busca });

  const normalizeLista = (data) => {
    if (Array.isArray(data?.matriculas)) return data.matriculas;
    if (Array.isArray(data?.data?.matriculas)) return data.data.matriculas;
    if (Array.isArray(data)) return data;
    return [];
  };

  const extractPagination = (data, fallbackPorPagina, fallbackPagina) => {
    const paginacao = data?.paginacao || data?.pagination || data?.meta || {};
    const total = paginacao.total || data?.total || data?.total_registros || data?.total_itens;
    const totalPaginas =
      paginacao.total_paginas ||
      paginacao.totalPages ||
      paginacao.total_paginas ||
      (total ? Math.ceil(total / fallbackPorPagina) : 1);
    const paginaAtual = paginacao.pagina || paginacao.page || fallbackPagina;

    return {
      total: total ?? null,
      totalPaginas: totalPaginas || 1,
      pagina: paginaAtual || 1,
    };
  };

  const carregarMatriculas = async ({ pagina: paginaParam, busca, force = false } = {}) => {
    const paginaAtual = paginaParam || pagina;
    const status = statusFilter !== 'todos' ? statusFilter : undefined;
    const incluirInativos = statusFilter === 'todos';
    const alunoId = undefined;
    const buscaParam = busca ?? serverSearchTerm;
    const key = buildCacheKey({
      pagina: paginaAtual,
      porPagina,
      status,
      incluirInativos,
      alunoId,
      busca: buscaParam || '',
    });

    if (!force && cache[key]) {
      const cached = cache[key];
      setMatriculas(cached.matriculas);
      setTotalPaginas(cached.totalPaginas || 1);
      setTotalItens(cached.totalItens || 0);
      setPagina(cached.pagina || paginaAtual);
      setLoading(false);
      return;
    }

    setLoading(true);
    try {
      const data = await matriculaService.listar({
        pagina: paginaAtual,
        por_pagina: porPagina,
        status,
        aluno_id: alunoId,
        incluir_inativos: incluirInativos,
        busca: buscaParam || undefined,
      });
      const lista = normalizeLista(data);
      const pagination = extractPagination(data, porPagina, paginaAtual);

      setMatriculas(lista);
      setTotalPaginas(pagination.totalPaginas || 1);
      setTotalItens(pagination.total ?? 0);
      setPagina(pagination.pagina || paginaAtual);

      setCache(prev => ({
        ...prev,
        [key]: {
          matriculas: lista,
          totalPaginas: pagination.totalPaginas || 1,
          totalItens: pagination.total ?? 0,
          pagina: pagination.pagina || paginaAtual,
        },
      }));
    } catch (error) {
      showAlert('Erro', 'Não foi possível carregar as matrículas');
    } finally {
      setLoading(false);
    }
  };

  const handlePesquisar = async () => {
    const termo = searchTerm.trim();
    setIsSearching(true);
    setServerSearchTerm(termo);
    setPagina(1);
    await carregarMatriculas({ pagina: 1, busca: termo });
    setIsSearching(false);
  };

  const handlePageChange = (novaPagina) => {
    if (novaPagina < 1 || novaPagina > totalPaginas || loading) return;
    setPagina(novaPagina);
    carregarMatriculas({ pagina: novaPagina });
  };

  const handleOpenDeletePreview = async (matricula) => {
    setDeletePreview({
      visible: true,
      loading: true,
      data: null,
      error: null,
      matricula,
    });

    try {
      const data = await matriculaService.deletePreview(matricula.id);
      setDeletePreview((prev) => ({
        ...prev,
        loading: false,
        data,
      }));
    } catch (error) {
      console.error('Erro ao carregar prévia de exclusão:', error);
      const mensagemErro =
        error.mensagemLimpa ||
        error.message ||
        error.error ||
        'Não foi possível carregar a prévia de exclusão';
      setDeletePreview((prev) => ({
        ...prev,
        loading: false,
        error: mensagemErro,
      }));
    }
  };

  const handleCloseDeletePreview = () => {
    if (deleting) return;
    setDeletePreview({
      visible: false,
      loading: false,
      data: null,
      error: null,
      matricula: null,
    });
  };

  const handleConfirmDelete = async () => {
    if (deleting) return;
    const matriculaId =
      deletePreview?.data?.matricula?.id || deletePreview?.matricula?.id;
    if (!matriculaId) return;
    try {
      setDeleting(true);
      const resultado = await matriculaService.deletar(matriculaId);
      const mensagem = resultado?.message || 'Matrícula cancelada com sucesso';
      showToast(mensagem);
      setDeletePreview({
        visible: false,
        loading: false,
        data: null,
        error: null,
        matricula: null,
      });
      await carregarMatriculas({ pagina, busca: serverSearchTerm, force: true });
    } catch (error) {
      console.error('Erro ao excluir:', error);
      const mensagemErro =
        error.mensagemLimpa ||
        error.message ||
        error.error ||
        'Não foi possível excluir a matrícula';
      showAlert('Erro', mensagemErro);
    } finally {
      setDeleting(false);
    }
  };

  const showAlert = (title, message) => {
    Alert.alert(title, message);
  };

  const showToast = (message) => {
    if (Platform.OS === 'android') {
      ToastAndroid.show(message, ToastAndroid.SHORT);
    } else {
      Alert.alert('', message);
    }
  };

  // Status de Matrícula: 1=Ativa, 2=Vencida, 3=Cancelada, 4=Finalizada, 5=Pendente, 6=Bloqueado
  const getStatusColor = (statusId) => {
    const id = Number(statusId);
    switch (id) {
      case 1:
        return '#10b981'; // Ativa (verde)
      case 2:
        return '#f97316'; // Vencida (laranja)
      case 3:
        return '#ef4444'; // Cancelada (vermelho)
      case 4:
        return '#6b7280'; // Finalizada (cinza)
      case 5:
        return '#f59e0b'; // Pendente (amarelo)
      case 6:
        return '#8b5cf6'; // Bloqueado (roxo)
      default:
        return '#6b7280';
    }
  };

  const getStatusLabel = (statusId) => {
    const id = Number(statusId);
    switch (id) {
      case 1:
        return 'Ativa';
      case 2:
        return 'Vencida';
      case 3:
        return 'Cancelada';
      case 4:
        return 'Finalizada';
      case 5:
        return 'Pendente';
      case 6:
        return 'Bloqueado';
      default:
        return 'Desconhecido';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Evita problema de timezone criando a data diretamente com os valores
    const [year, month, day] = dateString.split('-');
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('pt-BR');
  };

  const isProximoVencer = (dateString) => {
    if (!dateString) return false;
    const [year, month, day] = dateString.split('-');
    const vencimento = new Date(year, month - 1, day);
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);
    vencimento.setHours(0, 0, 0, 0);
    const diffDias = Math.ceil((vencimento.getTime() - hoje.getTime()) / (1000 * 60 * 60 * 24));
    return diffDias >= 0 && diffDias <= 3;
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value || 0);
  };

  const formatResumo = (resumo) => {
    if (!resumo) return null;
    if (Array.isArray(resumo)) {
      return resumo.map((item) => String(item)).join('\n');
    }
    if (typeof resumo === 'object') {
      if (resumo.message || resumo.mensagem) {
        return resumo.message || resumo.mensagem;
      }
      return Object.entries(resumo)
        .map(([key, value]) => `${key}: ${String(value)}`)
        .join('\n');
    }
    return String(resumo);
  };

  const getPreviewCount = (items) => (Array.isArray(items) ? items.length : items ? 1 : 0);

  const renderPreviewRow = (label, value) => {
    if (value === null || value === undefined || value === '') return null;
    return (
      <View style={styles.previewRow}>
        <Text style={styles.previewLabel}>{label}</Text>
        <Text style={styles.previewValue}>{value}</Text>
      </View>
    );
  };

  const renderMobileCard = (matricula) => (
    <View key={matricula.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardTitleRow}>
          {matricula.modalidade_icone && (
            <View
              style={[
                styles.cardIconBadge,
                { backgroundColor: matricula.modalidade_cor || '#3b82f6' },
              ]}
            >
              <MaterialCommunityIcons
                name={matricula.modalidade_icone}
                size={20}
                color="#fff"
              />
            </View>
          )}
          <View style={styles.cardTitleContent}>
            <Text style={styles.cardName}>{matricula.usuario_nome}</Text>
            <Text style={styles.cardEmail}>{matricula.usuario_email}</Text>
          </View>
        </View>
        <View
          className="self-start rounded-full px-2.5 py-1"
          style={{ backgroundColor: getStatusColor(matricula.status_id) }}
        >
          <Text className="text-[11px] font-bold text-white">{getStatusLabel(matricula.status_id)}</Text>
        </View>
      </View>

      <View style={styles.cardContent}>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Plano:</Text>
          <Text style={styles.infoValue}>
            {matricula.plano_nome} - {matricula.modalidade_nome}
          </Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Valor:</Text>
          <Text style={styles.infoValue}>{formatCurrency(matricula.valor)}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Início:</Text>
          <Text style={styles.infoValue}>{formatDate(matricula.data_inicio)}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Acesso até:</Text>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
            <Text style={styles.infoValue}>
              {formatDate(matricula.proxima_data_vencimento || matricula.data_vencimento)}
            </Text>
            {isProximoVencer(matricula.proxima_data_vencimento || matricula.data_vencimento) && (
              <View className="rounded-full bg-amber-100 px-2 py-0.5">
                <Text className="text-[10px] font-bold text-amber-700">Vence em breve</Text>
              </View>
            )}
          </View>
        </View>
      </View>

      <View style={styles.cardActions}>
        <Pressable
          onPress={() => router.push(`/matriculas/detalhe?id=${matricula.id}`)}
          style={({ pressed }) => [
            styles.btnAction,
            styles.btnDetalhes,
            pressed && { opacity: 0.7 },
          ]}
        >
          <Feather name="file-text" size={16} color="#3b82f6" />
          <Text style={styles.btnDetalhesText}>Detalhes</Text>
        </Pressable>
        {matricula.status_id !== 3 && matricula.status_id !== 4 && (
          <Pressable
            onPress={() => handleOpenDeletePreview(matricula)}
            style={({ pressed }) => [
              styles.btnAction,
              styles.btnCancelar,
              pressed && { opacity: 0.7 },
            ]}
          >
            <Feather name="trash-2" size={16} color="#ef4444" />
            <Text style={styles.btnCancelarText}>Excluir</Text>
          </Pressable>
        )}
      </View>
    </View>
  );

  const renderTable = () => (
    <View className="mx-4 my-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      {/* Header */}
      <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-3">
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colAluno}>Aluno</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colPlano}>Plano</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colModalidade}>Modalidade</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colValor}>Valor</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colDatas}>Início</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colDatas}>Acesso até</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colStatus}>Status</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-right" style={styles.colAcoes}>Ações</Text>
      </View>

      {/* Body */}
      {filteredMatriculas.map((matricula) => (
        <View key={matricula.id} className="flex-row items-center border-b border-slate-100 px-4 py-3">
          <View style={styles.colAluno}>
            <Text className="text-[13px] font-semibold text-slate-800">{matricula.usuario_nome}</Text>
            <Text className="text-[12px] text-slate-500">{matricula.usuario_email}</Text>
          </View>
          <Text className="text-[13px] text-slate-600" style={styles.colPlano}>{matricula.plano_nome}</Text>
          <View style={styles.colModalidade}>
            <View className="flex-row items-center gap-2">
              {matricula.modalidade_icone && (
                <View
                  style={[
                    styles.tableIconBadge,
                    { backgroundColor: matricula.modalidade_cor || '#f97316' },
                  ]}
                  className="h-6 w-6 items-center justify-center rounded-full"
                >
                  <MaterialCommunityIcons
                    name={matricula.modalidade_icone}
                    size={14}
                    color="#fff"
                  />
                </View>
              )}
              <Text className="text-[13px] text-slate-600">{matricula.modalidade_nome}</Text>
            </View>
          </View>
          <Text className="text-[13px] font-semibold text-slate-700" style={styles.colValor}>
            {formatCurrency(matricula.valor)}
          </Text>
          <Text className="text-[13px] text-slate-600" style={styles.colDatas}>
            {formatDate(matricula.data_inicio)}
          </Text>
          <View style={styles.colDatas}>
            <Text className="text-[13px] text-slate-600">
              {formatDate(matricula.proxima_data_vencimento || matricula.data_vencimento)}
            </Text>
            {isProximoVencer(matricula.proxima_data_vencimento || matricula.data_vencimento) && (
              <View className="mt-1 self-start rounded-full bg-amber-100 px-2 py-0.5">
                <Text className="text-[10px] font-bold text-amber-700">Vence em breve</Text>
              </View>
            )}
          </View>
          <View style={styles.colStatus}>
            <View
              className="self-start rounded-full px-2.5 py-1"
              style={{ backgroundColor: getStatusColor(matricula.status_id) }}
            >
              <Text className="text-[11px] font-bold text-white">{getStatusLabel(matricula.status_id)}</Text>
            </View>
          </View>
          <View className="flex-row justify-end gap-2" style={styles.colAcoes}>
            <Pressable
              onPress={() => router.push(`/matriculas/detalhe?id=${matricula.id}`)}
              className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
              style={({ pressed }) => [pressed && { opacity: 0.7 }]}
            >
              <Feather name="file-text" size={18} color="#f97316" />
            </Pressable>
            {matricula.status_id !== 3 && matricula.status_id !== 4 && (
              <Pressable
                onPress={() => handleOpenDeletePreview(matricula)}
                className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                style={({ pressed }) => [pressed && { opacity: 0.7 }]}
              >
                <Feather name="trash-2" size={18} color="#ef4444" />
              </Pressable>
            )}
          </View>
        </View>
      ))}
    </View>
  );

  const previewData = deletePreview.data;
  const previewResumo = formatResumo(previewData?.resumo);
  const previewAluno = previewData?.aluno || {};
  const previewPlano = previewData?.plano || {};
  const previewModalidade = previewPlano?.modalidade || {};
  const previewMatricula = previewData?.matricula || {};
  const previewStatus =
    previewMatricula.status_nome ||
    (previewMatricula.status_id ? getStatusLabel(previewMatricula.status_id) : undefined);
  const previewCounts = {
    pagamentosPlano: getPreviewCount(previewData?.pagamentos_plano),
    assinaturas: getPreviewCount(previewData?.assinaturas),
    assinaturasMp: getPreviewCount(previewData?.assinaturas_mercadopago),
    pagamentosMp: getPreviewCount(previewData?.pagamentos_mercadopago),
  };

  const previewModalContent = (
    <View style={[styles.previewOverlay, Platform.OS === 'web' && styles.previewOverlayWeb]}>
      <View style={[styles.previewModal, isMobile && styles.previewModalMobile]}>
        <View style={styles.previewHeader}>
          <Text style={styles.previewTitle}>Revisar exclusão</Text>
          <Pressable
            onPress={handleCloseDeletePreview}
            style={({ pressed }) => [
              styles.previewCloseButton,
              pressed && { opacity: 0.7 },
            ]}
          >
            <Feather name="x" size={18} color="#64748b" />
          </Pressable>
        </View>

        <View style={styles.previewDivider} />

        {deletePreview.loading ? (
          <View style={styles.previewState}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.previewStateText}>Carregando prévia...</Text>
          </View>
        ) : deletePreview.error ? (
          <View style={styles.previewState}>
            <Feather name="alert-circle" size={22} color="#ef4444" />
            <Text style={styles.previewErrorText}>{deletePreview.error}</Text>
          </View>
        ) : (
          <ScrollView
            style={styles.previewBody}
            contentContainerStyle={styles.previewBodyContent}
            showsVerticalScrollIndicator={false}
          >
            {previewResumo ? (
              <View style={styles.previewSection}>
                <Text style={styles.previewSectionTitle}>Resumo</Text>
                <Text style={styles.previewResumo}>{previewResumo}</Text>
              </View>
            ) : null}

            <View style={styles.previewSection}>
              <Text style={styles.previewSectionTitle}>Aluno</Text>
              {renderPreviewRow('Nome', previewAluno.nome)}
              {renderPreviewRow('Email', previewAluno.email)}
              {renderPreviewRow('Telefone', previewAluno.telefone)}
            </View>

            <View style={styles.previewSection}>
              <Text style={styles.previewSectionTitle}>Plano</Text>
              {renderPreviewRow('Plano', previewPlano.nome)}
              {renderPreviewRow('Modalidade', previewModalidade.nome)}
              {renderPreviewRow(
                'Valor do plano',
                previewPlano.valor != null ? formatCurrency(previewPlano.valor) : null
              )}
              {renderPreviewRow(
                'Duração',
                previewPlano.duracao_dias != null ? `${previewPlano.duracao_dias} dias` : null
              )}
              {renderPreviewRow(
                'Check-ins semanais',
                previewPlano.checkins_semanais != null
                  ? `${previewPlano.checkins_semanais}x/sem`
                  : null
              )}
            </View>

            <View style={styles.previewSection}>
              <Text style={styles.previewSectionTitle}>Matrícula</Text>
              {renderPreviewRow('ID', previewMatricula.id)}
              {renderPreviewRow('Status', previewStatus)}
              {renderPreviewRow(
                'Valor',
                previewMatricula.valor != null ? formatCurrency(previewMatricula.valor) : null
              )}
              {renderPreviewRow(
                'Data matrícula',
                previewMatricula.data_matricula
                  ? formatDate(previewMatricula.data_matricula)
                  : null
              )}
              {renderPreviewRow(
                'Início',
                previewMatricula.data_inicio
                  ? formatDate(previewMatricula.data_inicio)
                  : null
              )}
              {renderPreviewRow(
                'Vencimento',
                previewMatricula.data_vencimento
                  ? formatDate(previewMatricula.data_vencimento)
                  : null
              )}
              {renderPreviewRow(
                'Próximo vencimento',
                previewMatricula.proxima_data_vencimento
                  ? formatDate(previewMatricula.proxima_data_vencimento)
                  : null
              )}
              {renderPreviewRow('Tipo cobrança', previewMatricula.tipo_cobranca)}
              {renderPreviewRow('Dia vencimento', previewMatricula.dia_vencimento)}
              {renderPreviewRow('Observações', previewMatricula.observacoes)}
            </View>

            <View style={styles.previewSection}>
              <Text style={styles.previewSectionTitle}>Registros relacionados</Text>
              {renderPreviewRow('Pagamentos do plano', previewCounts.pagamentosPlano)}
              {renderPreviewRow('Assinaturas', previewCounts.assinaturas)}
              {renderPreviewRow('Assinaturas Mercado Pago', previewCounts.assinaturasMp)}
              {renderPreviewRow('Pagamentos Mercado Pago', previewCounts.pagamentosMp)}
            </View>
          </ScrollView>
        )}

        <View style={styles.previewActions}>
          <Pressable
            onPress={handleCloseDeletePreview}
            disabled={deleting}
            style={({ pressed }) => [
              styles.previewButton,
              styles.previewButtonSecondary,
              pressed && { opacity: 0.8 },
            ]}
          >
            <Text style={styles.previewButtonSecondaryText}>Cancelar</Text>
          </Pressable>
          <Pressable
            onPress={handleConfirmDelete}
            disabled={deletePreview.loading || deleting || deletePreview.error}
            style={({ pressed }) => [
              styles.previewButton,
              styles.previewButtonDanger,
              (deletePreview.loading || deleting || deletePreview.error) &&
                styles.previewButtonDisabled,
              pressed && { opacity: 0.9 },
            ]}
          >
            {deleting ? (
              <ActivityIndicator size="small" color="#fff" />
            ) : (
              <Text style={styles.previewButtonDangerText}>Confirmar exclusão</Text>
            )}
          </Pressable>
        </View>
      </View>
    </View>
  );

  return (
    <LayoutBase title="Matrículas" subtitle="Gerencie as matrículas dos alunos">
      <View style={styles.container}>
        <View style={styles.header}>
          <View style={{ flex: 1 }}>
            <Text style={styles.title}>Matrículas</Text>
            <Text style={styles.subtitle}>Gerencie as matrículas dos alunos</Text>
          </View>
          <Pressable
            onPress={() => router.push('/matriculas/novo')}
            style={({ pressed }) => [styles.btnPrimary, pressed && { opacity: 0.8 }]}
          >
            <Feather name="plus" size={20} color="#fff" />
            <Text style={styles.btnPrimaryText}>Nova Matrícula</Text>
          </Pressable>
        </View>

        {/* Barra de Pesquisa e Filtros */}
        <View style={styles.searchContainer}>
          <View style={styles.searchRow}>
            <View style={styles.searchInputWrapper}>
              <Feather name="search" size={20} color="#9ca3af" style={styles.searchIcon} />
              <TextInput
                style={styles.searchInput}
                placeholder="Buscar por aluno, email, plano ou modalidade..."
                placeholderTextColor="#9ca3af"
                value={searchTerm}
                onChangeText={setSearchTerm}
                onSubmitEditing={handlePesquisar}
              />
              {searchTerm !== '' && (
                <Pressable onPress={() => setSearchTerm('')} style={styles.clearButton}>
                  <Feather name="x" size={18} color="#9ca3af" />
                </Pressable>
              )}
            </View>

            <Pressable
              onPress={handlePesquisar}
              disabled={isSearching || loading}
              style={({ pressed }) => [
                styles.searchButton,
                (isSearching || loading) && styles.searchButtonDisabled,
                pressed && { opacity: 0.8 },
              ]}
            >
              {isSearching ? (
                <ActivityIndicator size="small" color="#fff" />
              ) : (
                <>
                  <Feather name="search" size={16} color="#fff" />
                  <Text style={styles.searchButtonText}>Pesquisar</Text>
                </>
              )}
            </Pressable>
          </View>
          
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterScroll}>
            <View style={styles.filterContainer}>
              {['todos', 'ativa', 'pendente', 'vencida', 'cancelada', 'finalizada', 'bloqueado'].map(status => (
                <Pressable
                  key={status}
                  onPress={() => setStatusFilter(status)}
                  style={[styles.filterChip, statusFilter === status && styles.filterChipActive]}
                >
                  <Text style={[styles.filterChipText, statusFilter === status && styles.filterChipTextActive]}>
                    {status === 'todos' ? 'Todas' : status.charAt(0).toUpperCase() + status.slice(1)}
                  </Text>
                </Pressable>
              ))}
            </View>
          </ScrollView>
          
          <Text style={styles.resultCount}>
            {filteredMatriculas.length} {filteredMatriculas.length === 1 ? 'matrícula' : 'matrículas'}
            {totalItens ? ` • Total: ${totalItens}` : ''}
          </Text>
        </View>

        {loading ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.loadingText}>Carregando matrículas...</Text>
          </View>
        ) : matriculas.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="user-check" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhuma matrícula encontrada</Text>
            <Text style={styles.emptySubtext}>
              Clique em "Nova Matrícula" para matricular um aluno
            </Text>
          </View>
        ) : filteredMatriculas.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="search" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhum resultado encontrado</Text>
            <Text style={styles.emptySubtext}>
              Tente ajustar os filtros ou termo de busca
            </Text>
          </View>
        ) : (
          <View style={{ flex: 1 }}>
            <ScrollView showsVerticalScrollIndicator={false}>
              {isMobile ? (
                <View style={styles.cardsContainer}>
                  {filteredMatriculas.map(renderMobileCard)}
                </View>
              ) : (
                renderTable()
              )}
            </ScrollView>
            <View style={styles.paginationContainer}>
              <Pressable
                onPress={() => handlePageChange(pagina - 1)}
                disabled={pagina <= 1 || loading}
                style={({ pressed }) => [
                  styles.paginationButton,
                  (pagina <= 1 || loading) && styles.paginationButtonDisabled,
                  pressed && { opacity: 0.8 },
                ]}
              >
                <Feather name="chevron-left" size={18} color="#111827" />
                <Text style={styles.paginationButtonText}>Anterior</Text>
              </Pressable>

              <Text style={styles.paginationInfo}>
                Página {pagina} de {totalPaginas}
              </Text>

              <Pressable
                onPress={() => handlePageChange(pagina + 1)}
                disabled={pagina >= totalPaginas || loading}
                style={({ pressed }) => [
                  styles.paginationButton,
                  (pagina >= totalPaginas || loading) && styles.paginationButtonDisabled,
                  pressed && { opacity: 0.8 },
                ]}
              >
                <Text style={styles.paginationButtonText}>Próxima</Text>
                <Feather name="chevron-right" size={18} color="#111827" />
              </Pressable>
            </View>
          </View>
        )}

        {Platform.OS === 'web' ? (
          deletePreview.visible ? (
            previewModalContent
          ) : null
        ) : (
          <Modal
            visible={deletePreview.visible}
            transparent
            animationType="fade"
            onRequestClose={handleCloseDeletePreview}
          >
            {previewModalContent}
          </Modal>
        )}
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
    position: 'relative',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 24,
    paddingVertical: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  searchContainer: {
    backgroundColor: '#fff',
    paddingHorizontal: 24,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  searchInputWrapper: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    paddingHorizontal: 12,
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 10,
    fontSize: 14,
    color: '#111827',
  },
  clearButton: {
    padding: 4,
  },
  searchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 12,
  },
  searchButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#f97316',
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 8,
  },
  searchButtonDisabled: {
    opacity: 0.6,
  },
  searchButtonText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '600',
  },
  filterScroll: {
    marginBottom: 12,
  },
  filterContainer: {
    flexDirection: 'row',
    gap: 8,
  },
  filterChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#f3f4f6',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  filterChipActive: {
    backgroundColor: '#f97316',
    borderColor: '#f97316',
  },
  filterChipText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6b7280',
  },
  filterChipTextActive: {
    color: '#fff',
  },
  resultCount: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '500',
  },
  paginationContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 24,
    paddingVertical: 16,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
    backgroundColor: '#fff',
  },
  paginationButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    backgroundColor: '#f9fafb',
  },
  paginationButtonDisabled: {
    opacity: 0.5,
  },
  paginationButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#111827',
  },
  paginationInfo: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 14,
    color: '#6b7280',
  },
  btnPrimary: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#f97316',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
  },
  btnPrimaryText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 14,
    color: '#6b7280',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  emptyText: {
    marginTop: 16,
    fontSize: 18,
    fontWeight: '600',
    color: '#374151',
  },
  emptySubtext: {
    marginTop: 8,
    fontSize: 14,
    color: '#6b7280',
    textAlign: 'center',
  },
  // Cards Mobile
  cardsContainer: {
    padding: 16,
    gap: 16,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  cardIconBadge: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardTitleContent: {
    flex: 1,
  },
  cardName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  cardEmail: {
    fontSize: 13,
    color: '#6b7280',
  },
  cardContent: {
    gap: 10,
    marginBottom: 16,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  infoLabel: {
    fontSize: 14,
    color: '#6b7280',
    fontWeight: '500',
  },
  infoValue: {
    fontSize: 14,
    color: '#111827',
    fontWeight: '600',
  },
  cardActions: {
    flexDirection: 'row',
    gap: 8,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
    paddingTop: 12,
  },
  btnAction: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 6,
  },
  btnDetalhes: {
    backgroundColor: '#fff7ed',
  },
  btnDetalhesText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#f97316',
  },
  btnCancelar: {
    backgroundColor: '#fef2f2',
  },
  btnCancelarText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#ef4444',
  },
  // Table Desktop
  tableContainer: {
    margin: 24,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    overflow: 'hidden',
  },
  table: {
    width: '100%',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f9fafb',
    borderBottomWidth: 2,
    borderBottomColor: '#e5e7eb',
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  tableHeaderText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#374151',
    textTransform: 'uppercase',
  },
  tableRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    paddingVertical: 12,
    paddingHorizontal: 16,
    alignItems: 'center',
  },
  colAluno: { flex: 2.5 },
  colPlano: { flex: 1.5 },
  colModalidade: { flex: 1.5 },
  colValor: { flex: 1 },
  colDatas: { flex: 1 },
  colStatus: { flex: 1 },
  colAcoes: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center' },
  cellText: {
    fontSize: 14,
    color: '#374151',
  },
  cellTextBold: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  cellTextSmall: {
    fontSize: 12,
    color: '#6b7280',
  },
  modalidadeCell: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  tableIconBadge: {
    width: 24,
    height: 24,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  btnTableAction: {
    padding: 8,
  },
  // Modal de prévia de exclusão
  previewOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  previewOverlayWeb: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
    zIndex: 50,
  },
  previewModal: {
    width: '100%',
    maxWidth: 720,
    maxHeight: '85%',
    backgroundColor: '#fff',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    overflow: 'hidden',
  },
  previewModalMobile: {
    maxHeight: '90%',
  },
  previewHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  previewTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  previewCloseButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#f3f4f6',
  },
  previewDivider: {
    height: 1,
    backgroundColor: '#e5e7eb',
  },
  previewBody: {
    paddingHorizontal: 20,
  },
  previewBodyContent: {
    paddingVertical: 16,
    gap: 16,
  },
  previewSection: {
    gap: 8,
  },
  previewSectionTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: '#64748b',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  previewResumo: {
    fontSize: 13,
    color: '#334155',
    lineHeight: 18,
    backgroundColor: '#f8fafc',
    borderRadius: 10,
    padding: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  previewRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  previewLabel: {
    fontSize: 13,
    color: '#64748b',
    fontWeight: '500',
    flex: 1,
    paddingRight: 12,
  },
  previewValue: {
    fontSize: 13,
    color: '#111827',
    fontWeight: '600',
    textAlign: 'right',
    flex: 1,
  },
  previewActions: {
    flexDirection: 'row',
    gap: 12,
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
    backgroundColor: '#fff',
  },
  previewButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  previewButtonSecondary: {
    backgroundColor: '#f3f4f6',
  },
  previewButtonSecondaryText: {
    color: '#64748b',
    fontSize: 14,
    fontWeight: '600',
  },
  previewButtonDanger: {
    backgroundColor: '#ef4444',
  },
  previewButtonDangerText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  previewButtonDisabled: {
    opacity: 0.6,
  },
  previewState: {
    padding: 24,
    alignItems: 'center',
    gap: 12,
  },
  previewStateText: {
    fontSize: 14,
    color: '#6b7280',
  },
  previewErrorText: {
    fontSize: 13,
    color: '#ef4444',
    textAlign: 'center',
  },
});
