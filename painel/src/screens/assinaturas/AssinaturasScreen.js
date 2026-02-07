import React, { useState, useEffect } from 'react';
import { View, Text, ScrollView, TouchableOpacity, Modal, TextInput } from 'react-native';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import LoadingOverlay from '../../components/LoadingOverlay';
import assinaturaService from '../../services/assinaturaService';
import { showSuccess, showError } from '../../utils/toast';
import authService from '../../services/authService';

const AssinaturasScreen = () => {
  const [assinaturas, setAssinaturas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [selectedTenant, setSelectedTenant] = useState(null);
  const [tenants, setTenants] = useState([]);
  const [loadingTenants, setLoadingTenants] = useState(false);

  // Filtros
  const [statusFilter, setStatusFilter] = useState('ativa');
  const [searchText, setSearchText] = useState('');
  const [showTenantDropdown, setShowTenantDropdown] = useState(false);

  // Modais
  const [confirmAction, setConfirmAction] = useState({ visible: false, action: null, assinatura: null });
  const [selectedAssinatura, setSelectedAssinatura] = useState(null);
  const [showDetails, setShowDetails] = useState(false);

  useEffect(() => {
    checkUserAndLoadData();
  }, []);

  useEffect(() => {
    loadAssinaturas();
  }, [statusFilter, selectedTenant]);

  const checkUserAndLoadData = async () => {
    try {
      const user = await authService.getCurrentUser();
      const superAdmin = user?.papel_id === 4;
      setIsSuperAdmin(superAdmin);

      if (superAdmin) {
        loadTenants();
      } else {
        loadAssinaturas();
      }
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
      showError('Erro ao carregar dados do usuário');
    }
  };

  const loadTenants = async () => {
    try {
      setLoadingTenants(true);
      const response = await fetch('http://localhost:8080/superadmin/academias', {
        headers: { 'Authorization': `Bearer ${await authService.getToken()}` }
      });
      const data = await response.json();
      setTenants(data.academias || []);
      if (data.academias?.length > 0) {
        setSelectedTenant(data.academias[0]);
      }
    } catch (error) {
      console.error('Erro ao carregar academias:', error);
      showError('Erro ao carregar academias');
    } finally {
      setLoadingTenants(false);
    }
  };

  const loadAssinaturas = async () => {
    try {
      setLoading(true);
      const filtros = { status: statusFilter };

      let response;
      if (isSuperAdmin && selectedTenant) {
        response = await assinaturaService.listarTodas(selectedTenant.id, filtros);
      } else {
        response = await assinaturaService.listar(filtros);
      }

      setAssinaturas(response.assinaturas || response.data?.assinaturas || []);
    } catch (error) {
      console.error('Erro ao listar assinaturas:', error);
      showError(error.error || 'Erro ao carregar assinaturas');
      setAssinaturas([]);
    } finally {
      setLoading(false);
    }
  };

  const handleSuspender = (assinatura) => {
    setConfirmAction({
      visible: true,
      action: 'suspender',
      assinatura
    });
  };

  const handleReativar = (assinatura) => {
    setConfirmAction({
      visible: true,
      action: 'reativar',
      assinatura
    });
  };

  const handleCancelar = (assinatura) => {
    setConfirmAction({
      visible: true,
      action: 'cancelar',
      assinatura
    });
  };

  const handleRenovar = (assinatura) => {
    setConfirmAction({
      visible: true,
      action: 'renovar',
      assinatura
    });
  };

  const executarAcao = async () => {
    const { action, assinatura } = confirmAction;
    try {
      setLoading(true);

      switch (action) {
        case 'suspender':
          await assinaturaService.suspender(assinatura.id, 'Suspensão manual pelo admin');
          showSuccess('Assinatura suspensa com sucesso');
          break;
        case 'reativar':
          await assinaturaService.reativar(assinatura.id);
          showSuccess('Assinatura reativada com sucesso');
          break;
        case 'cancelar':
          await assinaturaService.cancelar(assinatura.id, 'Cancelamento manual pelo admin');
          showSuccess('Assinatura cancelada com sucesso');
          break;
        case 'renovar':
          await assinaturaService.renovar(assinatura.id, {});
          showSuccess('Assinatura renovada com sucesso');
          break;
      }

      setConfirmAction({ visible: false, action: null, assinatura: null });
      loadAssinaturas();
    } catch (error) {
      console.error(`Erro ao ${action}:`, error);
      showError(error.error || `Erro ao ${action} assinatura`);
    } finally {
      setLoading(false);
    }
  };

  const filteredAssinaturas = assinaturas.filter(a =>
    a.aluno_nome?.toLowerCase().includes(searchText.toLowerCase()) ||
    a.plano_nome?.toLowerCase().includes(searchText.toLowerCase())
  );

  const getStatusColor = (status) => {
    switch (status) {
      case 'ativa':
        return '#10b981';
      case 'suspensa':
        return '#f59e0b';
      case 'cancelada':
        return '#ef4444';
      case 'vencida':
        return '#6b7280';
      default:
        return '#9ca3af';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'ativa':
        return 'Ativa';
      case 'suspensa':
        return 'Suspensa';
      case 'cancelada':
        return 'Cancelada';
      case 'vencida':
        return 'Vencida';
      default:
        return status;
    }
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  };

  const formatDate = (date) => {
    return new Date(date).toLocaleDateString('pt-BR');
  };

  if (loading && assinaturas.length === 0) {
    return (
      <LayoutBase title="Assinaturas" subtitle="Gerenciar assinaturas dos alunos">
        <LoadingOverlay visible={true} />
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Assinaturas" subtitle="Gerenciar assinaturas dos alunos">
      <ScrollView style={{ flex: 1 }}>
        {/* Header com filtros */}
        <View style={{ padding: 16, gap: 12 }}>
          {/* Seleção de Academia (SuperAdmin) */}
          {isSuperAdmin && (
            <View style={{ marginBottom: 12 }}>
              <Text style={{ fontSize: 12, fontWeight: '600', color: '#6b7280', marginBottom: 8 }}>
                ACADEMIA
              </Text>
              <TouchableOpacity
                onPress={() => setShowTenantDropdown(!showTenantDropdown)}
                style={{
                  flexDirection: 'row',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  paddingHorizontal: 12,
                  paddingVertical: 10,
                  borderWidth: 1,
                  borderColor: '#e5e7eb',
                  borderRadius: 6,
                  backgroundColor: '#f9fafb'
                }}
              >
                <Text style={{ fontSize: 14, color: '#1f2937' }}>
                  {selectedTenant?.nome || 'Selecione uma academia'}
                </Text>
                <Feather name="chevron-down" size={16} color="#6b7280" />
              </TouchableOpacity>

              {showTenantDropdown && (
                <View style={{
                  position: 'absolute',
                  top: 60,
                  left: 0,
                  right: 0,
                  backgroundColor: '#fff',
                  borderWidth: 1,
                  borderColor: '#e5e7eb',
                  borderRadius: 6,
                  zIndex: 10,
                  marginHorizontal: 0
                }}>
                  {tenants.map(tenant => (
                    <TouchableOpacity
                      key={tenant.id}
                      onPress={() => {
                        setSelectedTenant(tenant);
                        setShowTenantDropdown(false);
                      }}
                      style={{
                        paddingHorizontal: 12,
                        paddingVertical: 10,
                        borderBottomWidth: 1,
                        borderBottomColor: '#e5e7eb'
                      }}
                    >
                      <Text style={{ fontSize: 14, color: '#1f2937' }}>
                        {tenant.nome}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
              )}
            </View>
          )}

          {/* Filtro de Status */}
          <View>
            <Text style={{ fontSize: 12, fontWeight: '600', color: '#6b7280', marginBottom: 8 }}>
              STATUS
            </Text>
            <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap' }}>
              {['ativa', 'suspensa', 'cancelada', 'vencida'].map(status => (
                <TouchableOpacity
                  key={status}
                  onPress={() => setStatusFilter(status)}
                  style={{
                    paddingHorizontal: 12,
                    paddingVertical: 8,
                    borderRadius: 6,
                    backgroundColor: statusFilter === status ? getStatusColor(status) : '#f3f4f6',
                    borderWidth: statusFilter === status ? 0 : 1,
                    borderColor: '#e5e7eb'
                  }}
                >
                  <Text
                    style={{
                      color: statusFilter === status ? '#fff' : '#6b7280',
                      fontSize: 12,
                      fontWeight: '600'
                    }}
                  >
                    {getStatusLabel(status)}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>

          {/* Busca */}
          <View>
            <TextInput
              style={{
                paddingHorizontal: 12,
                paddingVertical: 10,
                borderWidth: 1,
                borderColor: '#e5e7eb',
                borderRadius: 6,
                fontSize: 14,
                backgroundColor: '#f9fafb'
              }}
              placeholder="Buscar por aluno ou plano..."
              value={searchText}
              onChangeText={setSearchText}
              placeholderTextColor="#9ca3af"
            />
          </View>
        </View>

        {/* Lista de Assinaturas */}
        <View style={{ paddingHorizontal: 16, paddingBottom: 20 }}>
          {filteredAssinaturas.length === 0 ? (
            <View style={{
              alignItems: 'center',
              justifyContent: 'center',
              paddingVertical: 40,
              gap: 8
            }}>
              <Feather name="inbox" size={32} color="#d1d5db" />
              <Text style={{ color: '#9ca3af', fontSize: 14 }}>
                Nenhuma assinatura encontrada
              </Text>
            </View>
          ) : (
            filteredAssinaturas.map(assinatura => (
              <TouchableOpacity
                key={assinatura.id}
                onPress={() => {
                  setSelectedAssinatura(assinatura);
                  setShowDetails(true);
                }}
                style={{
                  backgroundColor: '#fff',
                  borderWidth: 1,
                  borderColor: '#e5e7eb',
                  borderRadius: 8,
                  padding: 12,
                  marginBottom: 12,
                  flexDirection: 'row',
                  alignItems: 'center',
                  gap: 12
                }}
              >
                <View
                  style={{
                    width: 40,
                    height: 40,
                    borderRadius: 20,
                    backgroundColor: getStatusColor(assinatura.status),
                    alignItems: 'center',
                    justifyContent: 'center'
                  }}
                >
                  <Feather name="check-circle" size={20} color="#fff" />
                </View>

                <View style={{ flex: 1, gap: 4 }}>
                  <Text style={{ fontSize: 14, fontWeight: '600', color: '#1f2937' }}>
                    {assinatura.aluno_nome}
                  </Text>
                  <Text style={{ fontSize: 12, color: '#6b7280' }}>
                    {assinatura.plano_nome}
                  </Text>
                  <View style={{ flexDirection: 'row', gap: 8 }}>
                    <View
                      style={{
                        paddingHorizontal: 8,
                        paddingVertical: 2,
                        backgroundColor: getStatusColor(assinatura.status) + '20',
                        borderRadius: 4
                      }}
                    >
                      <Text
                        style={{
                          fontSize: 10,
                          fontWeight: '600',
                          color: getStatusColor(assinatura.status)
                        }}
                      >
                        {getStatusLabel(assinatura.status)}
                      </Text>
                    </View>
                    <Text style={{ fontSize: 10, color: '#9ca3af' }}>
                      Vence: {formatDate(assinatura.data_vencimento)}
                    </Text>
                  </View>
                </View>

                <View style={{ alignItems: 'flex-end', gap: 4 }}>
                  <Text style={{ fontSize: 14, fontWeight: '700', color: '#f97316' }}>
                    {formatCurrency(assinatura.valor_mensal)}
                  </Text>
                  <Feather name="chevron-right" size={16} color="#9ca3af" />
                </View>
              </TouchableOpacity>
            ))
          )}
        </View>
      </ScrollView>

      {/* Modal de Detalhes */}
      {selectedAssinatura && (
        <Modal
          visible={showDetails}
          transparent
          animationType="slide"
          onRequestClose={() => setShowDetails(false)}
        >
          <View style={{
            flex: 1,
            backgroundColor: '#f9fafb',
            paddingTop: 50,
            paddingHorizontal: 16
          }}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
              <Text style={{ fontSize: 18, fontWeight: '700', color: '#1f2937' }}>
                Detalhes da Assinatura
              </Text>
              <TouchableOpacity onPress={() => setShowDetails(false)}>
                <Feather name="x" size={24} color="#1f2937" />
              </TouchableOpacity>
            </View>

            <ScrollView>
              <View style={{ backgroundColor: '#fff', padding: 16, borderRadius: 8, marginBottom: 16 }}>
                <Text style={{ fontSize: 12, fontWeight: '600', color: '#6b7280', marginBottom: 4 }}>
                  ALUNO
                </Text>
                <Text style={{ fontSize: 16, fontWeight: '700', color: '#1f2937', marginBottom: 12 }}>
                  {selectedAssinatura.aluno_nome}
                </Text>

                <Text style={{ fontSize: 12, fontWeight: '600', color: '#6b7280', marginBottom: 4 }}>
                  PLANO
                </Text>
                <Text style={{ fontSize: 14, fontWeight: '600', color: '#1f2937', marginBottom: 12 }}>
                  {selectedAssinatura.plano_nome}
                </Text>

                <Text style={{ fontSize: 12, fontWeight: '600', color: '#6b7280', marginBottom: 4 }}>
                  STATUS
                </Text>
                <Text
                  style={{
                    fontSize: 14,
                    fontWeight: '600',
                    color: getStatusColor(selectedAssinatura.status),
                    marginBottom: 12
                  }}
                >
                  {getStatusLabel(selectedAssinatura.status)}
                </Text>

                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 12 }}>
                  <View>
                    <Text style={{ fontSize: 12, fontWeight: '600', color: '#6b7280', marginBottom: 4 }}>
                      INÍCIO
                    </Text>
                    <Text style={{ fontSize: 14, fontWeight: '600', color: '#1f2937' }}>
                      {formatDate(selectedAssinatura.data_inicio)}
                    </Text>
                  </View>
                  <View>
                    <Text style={{ fontSize: 12, fontWeight: '600', color: '#6b7280', marginBottom: 4 }}>
                      VENCIMENTO
                    </Text>
                    <Text style={{ fontSize: 14, fontWeight: '600', color: '#1f2937' }}>
                      {formatDate(selectedAssinatura.data_vencimento)}
                    </Text>
                  </View>
                </View>

                <View>
                  <Text style={{ fontSize: 12, fontWeight: '600', color: '#6b7280', marginBottom: 4 }}>
                    VALOR MENSAL
                  </Text>
                  <Text style={{ fontSize: 18, fontWeight: '700', color: '#f97316' }}>
                    {formatCurrency(selectedAssinatura.valor_mensal)}
                  </Text>
                </View>
              </View>

              {/* Ações */}
              <View style={{ gap: 8, marginBottom: 20 }}>
                {selectedAssinatura.status === 'ativa' && (
                  <>
                    <TouchableOpacity
                      onPress={() => {
                        handleRenovar(selectedAssinatura);
                        setShowDetails(false);
                      }}
                      style={{
                        backgroundColor: '#10b981',
                        paddingVertical: 12,
                        borderRadius: 6,
                        alignItems: 'center'
                      }}
                    >
                      <Text style={{ color: '#fff', fontWeight: '600', fontSize: 14 }}>
                        Renovar
                      </Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      onPress={() => {
                        handleSuspender(selectedAssinatura);
                        setShowDetails(false);
                      }}
                      style={{
                        backgroundColor: '#f59e0b',
                        paddingVertical: 12,
                        borderRadius: 6,
                        alignItems: 'center'
                      }}
                    >
                      <Text style={{ color: '#fff', fontWeight: '600', fontSize: 14 }}>
                        Suspender
                      </Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      onPress={() => {
                        handleCancelar(selectedAssinatura);
                        setShowDetails(false);
                      }}
                      style={{
                        backgroundColor: '#ef4444',
                        paddingVertical: 12,
                        borderRadius: 6,
                        alignItems: 'center'
                      }}
                    >
                      <Text style={{ color: '#fff', fontWeight: '600', fontSize: 14 }}>
                        Cancelar
                      </Text>
                    </TouchableOpacity>
                  </>
                )}

                {selectedAssinatura.status === 'suspensa' && (
                  <TouchableOpacity
                    onPress={() => {
                      handleReativar(selectedAssinatura);
                      setShowDetails(false);
                    }}
                    style={{
                      backgroundColor: '#3b82f6',
                      paddingVertical: 12,
                      borderRadius: 6,
                      alignItems: 'center'
                    }}
                  >
                    <Text style={{ color: '#fff', fontWeight: '600', fontSize: 14 }}>
                      Reativar
                    </Text>
                  </TouchableOpacity>
                )}

                <TouchableOpacity
                  onPress={() => setShowDetails(false)}
                  style={{
                    backgroundColor: '#f3f4f6',
                    paddingVertical: 12,
                    borderRadius: 6,
                    alignItems: 'center'
                  }}
                >
                  <Text style={{ color: '#6b7280', fontWeight: '600', fontSize: 14 }}>
                    Fechar
                  </Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </Modal>
      )}

      {/* Modal de Confirmação */}
      <ConfirmModal
        visible={confirmAction.visible}
        title={`${confirmAction.action?.charAt(0).toUpperCase()}${confirmAction.action?.slice(1)}`}
        message={`Tem certeza que deseja ${confirmAction.action} a assinatura de ${confirmAction.assinatura?.aluno_nome}?`}
        onConfirm={executarAcao}
        onCancel={() => setConfirmAction({ visible: false, action: null, assinatura: null })}
        isLoading={loading}
      />

      <LoadingOverlay visible={loading} />
    </LayoutBase>
  );
};

export default AssinaturasScreen;
