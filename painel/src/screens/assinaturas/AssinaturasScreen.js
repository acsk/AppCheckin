import React, { useMemo, useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  TextInput,
  ActivityIndicator,
  Modal,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import assinaturaService from '../../services/assinaturaService';
import mercadoPagoService from '../../services/mercadoPagoService';
import { showError, showSuccess } from '../../utils/toast';
import { authService } from '../../services/authService';
import { superAdminService } from '../../services/superAdminService';

const STATUS_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'ativa', label: 'Ativa' },
  { value: 'pendente', label: 'Pendente' },
  { value: 'cancelada', label: 'Cancelada' },
  { value: 'pausada', label: 'Pausada' },
  { value: 'expirada', label: 'Expirada' },
];

const TIPO_COBRANCA_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'recorrente', label: 'Recorrente' },
  { value: 'avulso', label: 'Avulso' },
];

const formatCurrency = (value) => {
  if (value == null || Number.isNaN(Number(value))) return '-';
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(Number(value));
};

const formatDate = (date) => {
  if (!date) return '-';
  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return '-';
  return parsed.toLocaleDateString('pt-BR');
};

const formatDateTime = (date) => {
  if (!date) return '-';
  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return '-';
  return parsed.toLocaleString('pt-BR');
};

const getProximaCobrancaLabel = (assinatura) => {
  const data = assinatura?.proxima_cobranca || assinatura?.data_fim;
  return formatDate(data);
};

const getStatusColor = (codigo) => {
  switch ((codigo || '').toLowerCase()) {
    case 'ativa':
      return '#10b981';
    case 'pendente':
      return '#f59e0b';
    case 'cancelada':
      return '#ef4444';
    case 'pausada':
    case 'suspensa':
      return '#f97316';
    case 'expirada':
    case 'vencida':
      return '#6b7280';
    default:
      return '#94a3b8';
  }
};

const getStatusInfo = (assinatura) => {
  const status = assinatura?.status || {};
  const codigo = status.codigo || assinatura?.status_codigo || assinatura?.status_gateway || assinatura?.status || 'pendente';
  const nome = status.nome || assinatura?.status_nome || codigo;
  const cor = status.cor || getStatusColor(codigo);
  return { codigo, nome, cor };
};

export default function AssinaturasScreen() {
  const [assinaturas, setAssinaturas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [tenants, setTenants] = useState([]);
  const [selectedTenant, setSelectedTenant] = useState(null);
  const [loadingTenants, setLoadingTenants] = useState(false);
  const [showTenantDropdown, setShowTenantDropdown] = useState(false);

  const [statusFilter, setStatusFilter] = useState('');
  const [tipoCobrancaFilter, setTipoCobrancaFilter] = useState('');
  const [searchText, setSearchText] = useState('');
  const [serverSearch, setServerSearch] = useState('');

  const [page, setPage] = useState(1);
  const [perPage] = useState(20);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);

  const [showCobrancasModal, setShowCobrancasModal] = useState(false);
  const [cobrancasAssinatura, setCobrancasAssinatura] = useState(null);
  const [cobrancas, setCobrancas] = useState(null);
  const [loadingCobrancas, setLoadingCobrancas] = useState(false);

  useEffect(() => {
    const init = async () => {
      try {
        const user = await authService.getCurrentUser();
        const superAdmin = user?.papel_id === 4;
        setIsSuperAdmin(superAdmin);
        if (superAdmin) {
          await loadTenants();
        }
      } catch (error) {
        console.error('Erro ao carregar usuário:', error);
      }
    };
    init();
  }, []);

  useEffect(() => {
    if (!isSuperAdmin || selectedTenant) {
      loadAssinaturas(1, serverSearch);
    }
  }, [statusFilter, tipoCobrancaFilter, selectedTenant]);

  const loadTenants = async () => {
    try {
      setLoadingTenants(true);
      const response = await superAdminService.listarAcademias();
      const lista = response?.academias || response?.data?.academias || response || [];
      setTenants(lista);
      if (lista.length > 0) {
        setSelectedTenant(lista[0]);
      }
    } catch (error) {
      console.error('Erro ao carregar academias:', error);
      showError(error.error || 'Erro ao carregar academias');
    } finally {
      setLoadingTenants(false);
    }
  };

  const normalizeResponse = (response) => {
    if (!response) return { lista: [], total: 0, totalPages: 1, page: 1, perPage };
    const lista = response.assinaturas || response.data?.assinaturas || response.data || [];
    const totalItems = response.total || response.data?.total || lista.length;
    const totalPagesValue = response.total_pages || response.data?.total_pages || 1;
    const pageValue = response.page || response.data?.page || 1;
    const perPageValue = response.per_page || response.data?.per_page || perPage;
    return {
      lista,
      total: totalItems,
      totalPages: totalPagesValue,
      page: pageValue,
      perPage: perPageValue,
    };
  };

  const loadAssinaturas = async (pageParam = page, buscaParam = serverSearch, overrides = {}) => {
    try {
      setLoading(true);
      const statusValue = Object.prototype.hasOwnProperty.call(overrides, 'status')
        ? overrides.status
        : statusFilter;
      const tipoCobrancaValue = Object.prototype.hasOwnProperty.call(overrides, 'tipo_cobranca')
        ? overrides.tipo_cobranca
        : tipoCobrancaFilter;
      const filtros = {
        status: statusValue || undefined,
        tipo_cobranca: tipoCobrancaValue || undefined,
        busca: buscaParam || undefined,
        page: pageParam,
        per_page: perPage,
      };

      let response;
      if (isSuperAdmin && selectedTenant) {
        response = await assinaturaService.listarTodas(selectedTenant.id, filtros);
      } else {
        response = await assinaturaService.listar(filtros);
      }

      const normalized = normalizeResponse(response);
      setAssinaturas(normalized.lista);
      setTotal(normalized.total || 0);
      setTotalPages(normalized.totalPages || 1);
      setPage(normalized.page || pageParam);
    } catch (error) {
      console.error('Erro ao listar assinaturas:', error);
      showError(error.error || 'Erro ao carregar assinaturas');
      setAssinaturas([]);
    } finally {
      setLoading(false);
    }
  };

  const handlePesquisar = async () => {
    const termo = searchText.trim();
    setServerSearch(termo);
    setPage(1);
    await loadAssinaturas(1, termo);
  };

  const handleClearFilters = async () => {
    setStatusFilter('');
    setTipoCobrancaFilter('');
    setSearchText('');
    setServerSearch('');
    setPage(1);
    await loadAssinaturas(1, '', { status: '', tipo_cobranca: '' });
  };

  const handlePageChange = (novaPagina) => {
    if (novaPagina < 1 || novaPagina > totalPages || loading) return;
    setPage(novaPagina);
    loadAssinaturas(novaPagina, serverSearch);
  };

  const fetchCobrancas = async (assinatura) => {
    if (!assinatura?.external_reference) return;
    try {
      setLoadingCobrancas(true);
      const response = await mercadoPagoService.consultarCobrancas(assinatura.external_reference);
      setCobrancasAssinatura(assinatura);
      setCobrancas(response);
      setShowCobrancasModal(true);
      showSuccess('Cobranças carregadas');
    } catch (error) {
      console.error('Erro ao consultar cobranças:', error);
      showError(error.error || 'Erro ao consultar cobranças');
    } finally {
      setLoadingCobrancas(false);
    }
  };

  const cobrancasMercadoPago = useMemo(() => {
    const lista = cobrancas?.mercadopago?.pagamentos;
    return Array.isArray(lista) ? lista : [];
  }, [cobrancas]);

  const pagamentosPlano = useMemo(() => {
    const lista = cobrancas?.local?.pagamentos_plano;
    return Array.isArray(lista) ? lista : [];
  }, [cobrancas]);

  const pagamentosMpLocal = useMemo(() => {
    const lista = cobrancas?.local?.pagamentos_mercadopago;
    return Array.isArray(lista) ? lista : [];
  }, [cobrancas]);

  const webhookPayloads = useMemo(() => {
    const lista = cobrancas?.local?.webhook_payloads || cobrancas?.local?.webhook_payloads_mercadopago;
    return Array.isArray(lista) ? lista : [];
  }, [cobrancas]);

  return (
    <LayoutBase title="Assinaturas" subtitle="Lista de assinaturas do tenant">
      <ScrollView className="flex-1">
        <View className="px-5 pt-4 pb-2">
          <Text className="text-lg font-semibold text-slate-800">Assinaturas</Text>
          <Text className="text-xs text-slate-500">Gerencie assinaturas, filtros e cobranças</Text>
        </View>

        <View className="px-5 pb-4">
          {isSuperAdmin && (
            <View className="mb-4">
              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-2">Academia</Text>
              <TouchableOpacity
                className="flex-row items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2.5"
                onPress={() => setShowTenantDropdown(!showTenantDropdown)}
              >
                <Text className="text-sm text-slate-700">
                  {selectedTenant?.nome || (loadingTenants ? 'Carregando...' : 'Selecione uma academia')}
                </Text>
                <Feather name="chevron-down" size={16} color="#94a3b8" />
              </TouchableOpacity>

              {showTenantDropdown && (
                <View className="mt-2 rounded-lg border border-slate-200 bg-white overflow-hidden">
                  {tenants.map((tenant) => (
                    <TouchableOpacity
                      key={tenant.id}
                      className="px-3 py-2 border-b border-slate-100"
                      onPress={() => {
                        setSelectedTenant(tenant);
                        setShowTenantDropdown(false);
                      }}
                    >
                      <Text className="text-sm text-slate-700">{tenant.nome}</Text>
                    </TouchableOpacity>
                  ))}
                </View>
              )}
            </View>
          )}

          <View className="rounded-xl border border-slate-200 bg-white p-3">
            <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-2">Filtros</Text>
            <View className="flex-row flex-wrap gap-2 mb-2">
              {STATUS_OPTIONS.map((status) => {
                const selected = statusFilter === status.value;
                return (
                  <TouchableOpacity
                    key={status.value || 'todos'}
                    className={`rounded-full px-2.5 py-1 ${selected ? 'bg-orange-500' : 'bg-slate-100'}`}
                    onPress={() => setStatusFilter(status.value)}
                  >
                    <Text className={`text-[11px] font-semibold ${selected ? 'text-white' : 'text-slate-600'}`}>
                      {status.label}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>

            <View className="flex-row flex-wrap gap-2 mb-2">
              {TIPO_COBRANCA_OPTIONS.map((tipo) => {
                const selected = tipoCobrancaFilter === tipo.value;
                return (
                  <TouchableOpacity
                    key={tipo.value || 'todos'}
                    className={`rounded-full px-2.5 py-1 ${selected ? 'bg-slate-900' : 'bg-slate-100'}`}
                    onPress={() => setTipoCobrancaFilter(tipo.value)}
                  >
                    <Text className={`text-[11px] font-semibold ${selected ? 'text-white' : 'text-slate-600'}`}>
                      {tipo.label}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>

            <TextInput
              className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
              placeholder="Buscar por aluno"
              value={searchText}
              onChangeText={setSearchText}
            />

            <View className="flex-row gap-2 mt-2">
              <TouchableOpacity
                className="flex-row items-center justify-center gap-2 rounded-lg bg-orange-500 px-3 py-2"
                onPress={handlePesquisar}
              >
                <Feather name="search" size={14} color="#fff" />
                <Text className="text-sm font-semibold text-white">Buscar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                className="flex-row items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2"
                onPress={handleClearFilters}
              >
                <Feather name="x" size={14} color="#64748b" />
                <Text className="text-sm font-semibold text-slate-600">Limpar</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>

        <View className="px-5 pb-6">
          <View className="flex-row items-center justify-between mb-2">
            <Text className="text-sm font-semibold text-slate-700">Resultado</Text>
            <Text className="text-xs text-slate-400">Total: {total}</Text>
          </View>

          {loading ? (
            <View className="items-center justify-center py-10">
              <ActivityIndicator size="large" color="#f97316" />
            </View>
          ) : assinaturas.length === 0 ? (
            <View className="items-center rounded-xl border border-slate-200 bg-white py-12">
              <Feather name="inbox" size={42} color="#cbd5f5" />
              <Text className="mt-3 text-sm font-semibold text-slate-600">Nenhuma assinatura encontrada</Text>
              <Text className="text-xs text-slate-400">Ajuste os filtros e tente novamente</Text>
            </View>
          ) : (
            <View className="gap-2">
              {assinaturas.map((assinatura) => {
                const statusInfo = getStatusInfo(assinatura);
                return (
                  <View
                    key={assinatura.id}
                    className="rounded-xl border border-slate-200 bg-white p-3"
                  >
                    <View className="flex-row items-start justify-between">
                      <View className="flex-1">
                        <Text className="text-sm font-semibold text-slate-800">{assinatura.aluno_nome || '-'}</Text>
                        <Text className="text-xs text-slate-500">
                          {assinatura.plano_nome || '-'} {assinatura.modalidade_nome ? `• ${assinatura.modalidade_nome}` : ''}
                        </Text>
                      </View>
                      <View className="items-end">
                        <Text className="text-sm font-semibold text-orange-500">{formatCurrency(assinatura.valor)}</Text>
                        <Text className="text-[11px] text-slate-400">{assinatura.tipo_cobranca || '-'}</Text>
                      </View>
                    </View>

                    <View className="flex-row items-center justify-between mt-2">
                      <View className="flex-row items-center gap-2">
                        <View
                          className="rounded-full px-2 py-0.5"
                          style={{ backgroundColor: `${statusInfo.cor}20` }}
                        >
                          <Text className="text-[11px] font-semibold" style={{ color: statusInfo.cor }}>
                            {statusInfo.nome}
                          </Text>
                        </View>
                        {assinatura.status_gateway ? (
                          <Text className="text-[11px] text-slate-400">Gateway: {assinatura.status_gateway}</Text>
                        ) : null}
                      </View>
                      <Text className="text-[11px] text-slate-400">
                        Próx. cobrança: {getProximaCobrancaLabel(assinatura)}
                      </Text>
                    </View>

                    <View className="mt-3 rounded-lg border border-slate-100 bg-slate-50 p-2">
                      <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-2">Dados da assinatura</Text>
                      <View className="flex-row flex-wrap gap-2">
                        <View className="min-w-[140px] flex-1 rounded-md bg-white px-2 py-1.5">
                          <Text className="text-[11px] text-slate-400">Tipo</Text>
                          <Text className="text-xs font-semibold text-slate-700">{assinatura.tipo_cobranca || '-'}</Text>
                        </View>
                        <View className="min-w-[140px] flex-1 rounded-md bg-white px-2 py-1.5">
                          <Text className="text-[11px] text-slate-400">Início</Text>
                          <Text className="text-xs font-semibold text-slate-700">{formatDate(assinatura.data_inicio)}</Text>
                        </View>
                        <View className="min-w-[140px] flex-1 rounded-md bg-white px-2 py-1.5">
                          <Text className="text-[11px] text-slate-400">Fim</Text>
                          <Text className="text-xs font-semibold text-slate-700">{formatDate(assinatura.data_fim)}</Text>
                        </View>
                        <View className="min-w-[140px] flex-1 rounded-md bg-white px-2 py-1.5">
                          <Text className="text-[11px] text-slate-400">Criado</Text>
                          <Text className="text-xs font-semibold text-slate-700">{formatDateTime(assinatura.criado_em)}</Text>
                        </View>
                        <View className="min-w-[180px] flex-1 rounded-md bg-white px-2 py-1.5">
                          <Text className="text-[11px] text-slate-400">External ref</Text>
                          <Text className="text-xs font-semibold text-slate-700">{assinatura.external_reference || '-'}</Text>
                        </View>
                        <View className="min-w-[180px] flex-1 rounded-md bg-white px-2 py-1.5">
                          <Text className="text-[11px] text-slate-400">MP preapproval</Text>
                          <Text className="text-xs font-semibold text-slate-700">{assinatura.mp_preapproval_id || '-'}</Text>
                        </View>
                        <View className="min-w-[140px] flex-1 rounded-md bg-white px-2 py-1.5">
                          <Text className="text-[11px] text-slate-400">Status gateway</Text>
                          <Text className="text-xs font-semibold text-slate-700">{assinatura.status_gateway || '-'}</Text>
                        </View>
                      </View>

                      <View className="mt-2 flex-row items-center justify-end">
                        <TouchableOpacity
                          className={`flex-row items-center gap-2 rounded-lg px-3 py-1.5 ${assinatura.external_reference ? 'bg-orange-500' : 'bg-slate-200'}`}
                          onPress={() => fetchCobrancas(assinatura)}
                          disabled={!assinatura.external_reference || loadingCobrancas}
                        >
                          {loadingCobrancas ? (
                            <ActivityIndicator size="small" color="#fff" />
                          ) : (
                            <>
                              <Feather name="search" size={12} color={assinatura.external_reference ? '#fff' : '#94a3b8'} />
                              <Text className={`text-xs font-semibold ${assinatura.external_reference ? 'text-white' : 'text-slate-500'}`}>
                                Cobranças
                              </Text>
                            </>
                          )}
                        </TouchableOpacity>
                      </View>
                    </View>
                  </View>
                );
              })}
            </View>
          )}

          <View className="flex-row items-center justify-between mt-5">
            <TouchableOpacity
              className={`rounded-lg border px-3 py-2 ${page <= 1 ? 'border-slate-200 bg-slate-100' : 'border-slate-200 bg-white'}`}
              onPress={() => handlePageChange(page - 1)}
              disabled={page <= 1}
            >
              <Text className={`text-xs font-semibold ${page <= 1 ? 'text-slate-400' : 'text-slate-700'}`}>
                Anterior
              </Text>
            </TouchableOpacity>
            <Text className="text-xs text-slate-500">
              Página {page} de {totalPages}
            </Text>
            <TouchableOpacity
              className={`rounded-lg border px-3 py-2 ${page >= totalPages ? 'border-slate-200 bg-slate-100' : 'border-slate-200 bg-white'}`}
              onPress={() => handlePageChange(page + 1)}
              disabled={page >= totalPages}
            >
              <Text className={`text-xs font-semibold ${page >= totalPages ? 'text-slate-400' : 'text-slate-700'}`}>
                Próxima
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>

      <Modal
        visible={showCobrancasModal}
        transparent
        animationType="slide"
        onRequestClose={() => setShowCobrancasModal(false)}
      >
        <View className="flex-1 bg-slate-50 px-5 pt-10">
          <View className="flex-row items-center justify-between mb-4">
            <View>
              <Text className="text-lg font-semibold text-slate-800">Cobranças</Text>
              <Text className="text-xs text-slate-500">
                {cobrancasAssinatura?.aluno_nome || '-'} • {cobrancasAssinatura?.external_reference || '-'}
              </Text>
            </View>
            <TouchableOpacity
              className="h-9 w-9 items-center justify-center rounded-full bg-white border border-slate-200"
              onPress={() => setShowCobrancasModal(false)}
            >
              <Feather name="x" size={18} color="#0f172a" />
            </TouchableOpacity>
          </View>

          <ScrollView>
            {!cobrancas ? (
              <View className="items-center justify-center py-10">
                <Text className="text-sm text-slate-500">Nenhum resultado carregado.</Text>
              </View>
            ) : (
              <View className="gap-4">
                <View className="rounded-xl border border-slate-200 bg-white p-4">
                  <Text className="text-xs font-semibold text-slate-500 mb-2">Mercado Pago</Text>
                  {cobrancasMercadoPago.length === 0 ? (
                    <Text className="text-xs text-slate-400">Nenhum pagamento encontrado.</Text>
                  ) : (
                    cobrancasMercadoPago.map((pagamento) => (
                      <View key={pagamento.id} className="rounded-lg border border-slate-100 bg-slate-50 p-3 mb-2">
                        <Text className="text-xs text-slate-500">ID: {pagamento.id}</Text>
                        <Text className="text-xs text-slate-500">Status: {pagamento.status}</Text>
                        <Text className="text-xs text-slate-500">Detalhe: {pagamento.status_detail || '-'}</Text>
                        <Text className="text-xs text-slate-500">Valor: {formatCurrency(pagamento.transaction_amount)}</Text>
                        <Text className="text-xs text-slate-500">Método: {pagamento.payment_method_id || '-'}</Text>
                        <Text className="text-xs text-slate-500">Criado: {formatDateTime(pagamento.date_created)}</Text>
                      </View>
                    ))
                  )}
                </View>

                <View className="rounded-xl border border-slate-200 bg-white p-4">
                  <Text className="text-xs font-semibold text-slate-500 mb-2">Pagamentos do plano</Text>
                  {pagamentosPlano.length === 0 ? (
                    <Text className="text-xs text-slate-400">Nenhum pagamento local encontrado.</Text>
                  ) : (
                    pagamentosPlano.map((pagamento) => (
                      <View key={pagamento.id} className="rounded-lg border border-slate-100 bg-slate-50 p-3 mb-2">
                        <Text className="text-xs text-slate-500">ID: {pagamento.id}</Text>
                        <Text className="text-xs text-slate-500">Matrícula: {pagamento.matricula_id}</Text>
                        <Text className="text-xs text-slate-500">Valor: {formatCurrency(pagamento.valor)}</Text>
                        <Text className="text-xs text-slate-500">Status: {pagamento.status_pagamento_id}</Text>
                        <Text className="text-xs text-slate-500">Vencimento: {formatDate(pagamento.data_vencimento)}</Text>
                      </View>
                    ))
                  )}
                </View>

                <View className="rounded-xl border border-slate-200 bg-white p-4">
                  <Text className="text-xs font-semibold text-slate-500 mb-2">Espelho MP (local)</Text>
                  {pagamentosMpLocal.length === 0 ? (
                    <Text className="text-xs text-slate-400">Nenhum espelho local encontrado.</Text>
                  ) : (
                    pagamentosMpLocal.map((pagamento) => (
                      <View key={pagamento.id} className="rounded-lg border border-slate-100 bg-slate-50 p-3 mb-2">
                        <Text className="text-xs text-slate-500">ID: {pagamento.id}</Text>
                        <Text className="text-xs text-slate-500">Payment ID: {pagamento.payment_id}</Text>
                        <Text className="text-xs text-slate-500">Status: {pagamento.status}</Text>
                        <Text className="text-xs text-slate-500">Valor: {formatCurrency(pagamento.transaction_amount)}</Text>
                        <Text className="text-xs text-slate-500">Criado: {formatDateTime(pagamento.created_at)}</Text>
                      </View>
                    ))
                  )}
                </View>

                <View className="rounded-xl border border-slate-200 bg-white p-4">
                  <Text className="text-xs font-semibold text-slate-500 mb-2">Webhooks recebidos</Text>
                  {webhookPayloads.length === 0 ? (
                    <Text className="text-xs text-slate-400">Nenhum webhook encontrado.</Text>
                  ) : (
                    webhookPayloads.map((payload) => (
                      <View key={payload.id} className="rounded-lg border border-slate-100 bg-slate-50 p-3 mb-2">
                        <Text className="text-xs text-slate-500">ID: {payload.id}</Text>
                        <Text className="text-xs text-slate-500">Tipo: {payload.tipo}</Text>
                        <Text className="text-xs text-slate-500">Status: {payload.status}</Text>
                        <Text className="text-xs text-slate-500">Criado: {formatDateTime(payload.created_at)}</Text>
                      </View>
                    ))
                  )}
                </View>
              </View>
            )}
          </ScrollView>
        </View>
      </Modal>
    </LayoutBase>
  );
}
