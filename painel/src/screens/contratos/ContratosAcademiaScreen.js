import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Modal, TextInput, Alert, ActivityIndicator, useWindowDimensions } from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import { buscarContratos, criarContrato, trocarPlano, renovarContrato } from '../../services/contratoService';
import planoService from '../../services/planoService';
import { authService } from '../../services/authService';
import { showError } from '../../utils/toast';

export default function ContratosAcademiaScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const { id: academiaIdParam, nome: academiaNome } = useLocalSearchParams();
  const academiaId = parseInt(academiaIdParam);

  const [loading, setLoading] = useState(true);
  const [contratos, setContratos] = useState([]);
  const [planos, setPlanos] = useState([]);

  useEffect(() => {
    checkAccess();
  }, []);

  const checkAccess = async () => {
    const user = await authService.getCurrentUser();
    if (!user || user.papel_id !== 4) {
      showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
      router.replace('/');
      return;
    }
  };
  
  // Modais
  const [modalCriar, setModalCriar] = useState(false);
  const [modalEditar, setModalEditar] = useState(false);
  const [selectedContrato, setSelectedContrato] = useState(null);

  // Formulários
  const [formCriar, setFormCriar] = useState({
    plano_id: '',
    forma_pagamento: 'pix',
    data_inicio: '',
    data_vencimento: '',
    observacoes: ''
  });

  useEffect(() => {
    carregarDados();
  }, [academiaId]);

  const carregarDados = async () => {
    setLoading(true);
    
    const resultContratos = await buscarContratos(academiaId);
    if (resultContratos.success) {
      // A API retorna { contratos: [...] } ou { historico: [...] }
      setContratos(resultContratos.data.contratos || resultContratos.data.historico || []);
    }

    try {
      const planosData = await planoService.listar();
      setPlanos(planosData.planos || planosData || []);
    } catch (error) {
      console.error('Erro ao carregar planos:', error);
    }

    setLoading(false);
  };

  const handleCriarContrato = async () => {
    if (!formCriar.plano_id || !formCriar.forma_pagamento) {
      Alert.alert('Atenção', 'Preencha os campos obrigatórios');
      return;
    }

    const result = await criarContrato(academiaId, {
      plano_id: parseInt(formCriar.plano_id),
      forma_pagamento: formCriar.forma_pagamento,
      data_inicio: formCriar.data_inicio || undefined,
      data_vencimento: formCriar.data_vencimento || undefined,
      observacoes: formCriar.observacoes || null
    });

    if (result.success) {
      Alert.alert('Sucesso', 'Contrato criado com sucesso!');
      setModalCriar(false);
      setFormCriar({ plano_id: '', forma_pagamento: 'pix', data_inicio: '', data_vencimento: '', observacoes: '' });
      carregarDados();
    } else {
      Alert.alert('Erro', result.error);
    }
  };

  const handleRenovar = async (contratoId) => {
    Alert.alert(
      'Renovar Contrato',
      'Deseja renovar este contrato por mais 1 mês?',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Confirmar',
          onPress: async () => {
            const result = await renovarContrato(contratoId, 'Renovação via sistema');
            if (result.success) {
              Alert.alert('Sucesso', 'Contrato renovado!');
              carregarDados();
            } else {
              Alert.alert('Erro', result.error);
            }
          }
        }
      ]
    );
  };

  const formatarData = (data) => {
    if (!data) return '-';
    const [ano, mes, dia] = data.split('-');
    return `${dia}/${mes}/${ano}`;
  };

  const formatarValor = (valor) => {
    return parseFloat(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'ativo': return '#10b981';
      case 'inativo': return '#6b7280';
      case 'cancelado': return '#ef4444';
      default: return '#6b7280';
    }
  };

  const getFormaPagamentoLabel = (forma) => {
    switch (forma) {
      case 'cartao': return 'Cartão';
      case 'pix': return 'PIX';
      case 'operadora': return 'Operadora';
      default: return forma;
    }
  };

  if (loading) {
    return (
      <LayoutBase>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
        </View>
      </LayoutBase>
    );
  }

  const renderMobileCards = () => (
    <ScrollView style={styles.mobileContainer}>
      {contratos.map((contrato) => (
        <View key={contrato.id} style={styles.card}>
          <View style={styles.cardHeader}>
            <View>
              <Text style={styles.cardPlano}>{contrato.plano_nome}</Text>
              <Text style={styles.cardValor}>{formatarValor(contrato.valor)}</Text>
            </View>
            <View style={[styles.statusBadge, { backgroundColor: getStatusColor(contrato.status) }]}>
              <Text style={styles.statusText}>{contrato.status.toUpperCase()}</Text>
            </View>
          </View>

          <View style={styles.cardInfo}>
            <View style={styles.infoRow}>
              <Ionicons name="calendar-outline" size={14} color="#6b7280" />
              <Text style={styles.infoText}>
                {formatarData(contrato.data_inicio)} → {formatarData(contrato.data_vencimento)}
              </Text>
            </View>
            <View style={styles.infoRow}>
              <Ionicons name="card-outline" size={14} color="#6b7280" />
              <Text style={styles.infoText}>{getFormaPagamentoLabel(contrato.forma_pagamento)}</Text>
            </View>
          </View>

          <View style={styles.cardActions}>
            {contrato.status === 'ativo' && (
              <TouchableOpacity
                style={[styles.actionBtn, styles.btnRenovar]}
                onPress={() => handleRenovar(contrato.id)}
              >
                <Ionicons name="refresh" size={16} color="#fff" />
                <Text style={styles.actionBtnText}>Renovar</Text>
              </TouchableOpacity>
            )}
          </View>
        </View>
      ))}
    </ScrollView>
  );

  const renderDesktopTable = () => (
    <View style={styles.tableContainer}>
      <View style={styles.tableHeader}>
        <Text style={[styles.headerText, { flex: 2 }]}>Plano</Text>
        <Text style={[styles.headerText, { flex: 1.5 }]}>Período</Text>
        <Text style={[styles.headerText, { flex: 1 }]}>Valor</Text>
        <Text style={[styles.headerText, { flex: 1 }]}>Status</Text>
        <Text style={[styles.headerText, { flex: 1 }]}>Ações</Text>
      </View>

      <ScrollView style={styles.tableBody}>
        {contratos.map((contrato) => (
          <View key={contrato.id} style={styles.tableRow}>
            <View style={[styles.tableCell, { flex: 2 }]}>
              <Text style={styles.cellText}>{contrato.plano_nome}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1.5 }]}>
              <Text style={styles.cellTextSmall}>
                {formatarData(contrato.data_inicio)}
              </Text>
              <Text style={styles.cellTextSmall}>
                {formatarData(contrato.data_vencimento)}
              </Text>
            </View>
                 <View style={[styles.tableCell, { flex: 1 }]}>
              <Text style={styles.cellText}>{formatarValor(contrato.valor)}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1 }]}>
              <View style={[styles.statusBadge, { backgroundColor: getStatusColor(contrato.status) }]}>
                <Text style={styles.statusText}>{contrato.status}</Text>
              </View>
            </View>
            <View style={[styles.tableCell, { flex: 1 }]}>
              <View style={styles.actionCell}>
                {contrato.status === 'ativo' && (
                  <TouchableOpacity
                    style={[styles.actionButton, styles.btnRenovarSmall]}
                    onPress={() => handleRenovar(contrato.id)}
                  >
                    <Ionicons name="refresh" size={16} color="#fff" />
                  </TouchableOpacity>
                )}
              </View>
            </View>
          </View>
        ))}
      </ScrollView>
    </View>
  );

  return (
    <LayoutBase noPadding>
      <View style={styles.container}>
        {/* Banner Header */}
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <TouchableOpacity onPress={() => router.back()} style={styles.backButtonBanner}>
                <Feather name="arrow-left" size={24} color="#fff" />
              </TouchableOpacity>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="file-text" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Contratos</Text>
                <Text style={styles.bannerSubtitle} numberOfLines={1}>
                  {academiaNome || 'Gerenciar contratos da academia'}
                </Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>

          {/* Card de Ações */}
          <View style={[styles.actionCard, isMobile && styles.actionCardMobile]}>
            <View style={styles.actionCardHeader}>
              <View style={styles.actionCardInfo}>
                <View style={styles.actionCardIconContainer}>
                  <Feather name="layers" size={20} color="#f97316" />
                </View>
                <View>
                  <Text style={styles.actionCardTitle}>Contratos da Academia</Text>
                  <Text style={styles.actionCardSubtitle}>
                    {contratos.length} {contratos.length === 1 ? 'contrato' : 'contratos'} encontrado(s)
                  </Text>
                </View>
              </View>
              <TouchableOpacity style={[styles.btnNovo, isMobile && styles.btnNovoMobile]} onPress={() => setModalCriar(true)}>
                <Feather name="plus" size={18} color="#fff" />
                {!isMobile && <Text style={styles.btnNovoText}>Novo Contrato</Text>}
              </TouchableOpacity>
            </View>
          </View>
        </View>

        {/* Conteúdo */}
        {contratos.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Ionicons name="document-text-outline" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhum contrato encontrado</Text>
          </View>
        ) : isMobile ? (
          renderMobileCards()
        ) : (
          renderDesktopTable()
        )}

        {/* Modal Criar Contrato */}
        <Modal visible={modalCriar} transparent animationType="slide">
          <View style={styles.modalOverlay}>
            <View style={styles.modalContent}>
              <View style={styles.modalHeader}>
                <Text style={styles.modalTitle}>Novo Contrato</Text>
                <TouchableOpacity onPress={() => setModalCriar(false)}>
                  <Ionicons name="close" size={24} color="#111" />
                </TouchableOpacity>
              </View>

              <ScrollView>
                <Text style={styles.label}>Plano *</Text>
                <View style={styles.selectContainer}>
                  {planos.map((plano) => (
                    <TouchableOpacity
                      key={plano.id}
                      style={[
                        styles.selectOption,
                        formCriar.plano_id === plano.id.toString() && styles.selectOptionActive
                      ]}
                      onPress={() => setFormCriar({ ...formCriar, plano_id: plano.id.toString() })}
                    >
                      <Text style={styles.selectOptionText}>{plano.nome}</Text>
                      <Text style={styles.selectOptionPrice}>{formatarValor(plano.valor)}</Text>
                    </TouchableOpacity>
                  ))}
                </View>

                <Text style={styles.label}>Forma de Pagamento *</Text>
                <View style={styles.radioGroup}>
                  {['pix', 'cartao', 'operadora'].map((forma) => (
                    <TouchableOpacity
                      key={forma}
                      style={[
                        styles.radioOption,
                        formCriar.forma_pagamento === forma && styles.radioOptionActive
                      ]}
                      onPress={() => setFormCriar({ ...formCriar, forma_pagamento: forma })}
                    >
                      <View style={styles.radio}>
                        {formCriar.forma_pagamento === forma && <View style={styles.radioInner} />}
                      </View>
                      <Text style={styles.radioLabel}>{getFormaPagamentoLabel(forma)}</Text>
                    </TouchableOpacity>
                  ))}
                </View>

                <Text style={styles.label}>Data Início (opcional)</Text>
                <TextInput
                  style={styles.input}
                  value={formCriar.data_inicio}
                  onChangeText={(text) => setFormCriar({ ...formCriar, data_inicio: text })}
                  placeholder="AAAA-MM-DD"
                  placeholderTextColor="#9ca3af"
                />

                <Text style={styles.label}>Data Vencimento (opcional)</Text>
                <TextInput
                  style={styles.input}
                  value={formCriar.data_vencimento}
                  onChangeText={(text) => setFormCriar({ ...formCriar, data_vencimento: text })}
                  placeholder="AAAA-MM-DD"
                  placeholderTextColor="#9ca3af"
                />

                <Text style={styles.label}>Observações</Text>
                <TextInput
                  style={[styles.input, styles.textArea]}
                  value={formCriar.observacoes}
                  onChangeText={(text) => setFormCriar({ ...formCriar, observacoes: text })}
                  placeholder="Observações sobre o contrato"
                  placeholderTextColor="#9ca3af"
                  multiline
                  numberOfLines={3}
                />

                <TouchableOpacity style={styles.btnSubmit} onPress={handleCriarContrato}>
                  <Text style={styles.btnSubmitText}>Criar Contrato</Text>
                </TouchableOpacity>
              </ScrollView>
            </View>
          </View>
        </Modal>
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },

  // Banner Header
  bannerContainer: {
    backgroundColor: '#f8fafc',
  },
  banner: {
    backgroundColor: '#f97316',
    paddingVertical: 28,
    paddingHorizontal: 24,
    position: 'relative',
    overflow: 'hidden',
  },
  bannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    zIndex: 2,
  },
  backButtonBanner: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  bannerIconContainer: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconOuter: {
    width: 64,
    height: 64,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconInner: {
    width: 48,
    height: 48,
    borderRadius: 14,
    backgroundColor: 'rgba(255, 255, 255, 0.25)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerTextContainer: {
    flex: 1,
  },
  bannerTitle: {
    fontSize: 26,
    fontWeight: '800',
    color: '#fff',
    letterSpacing: -0.5,
  },
  bannerSubtitle: {
    fontSize: 14,
    color: 'rgba(255, 255, 255, 0.85)',
    marginTop: 4,
    lineHeight: 20,
  },
  bannerDecoration: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    width: 200,
    zIndex: 1,
  },
  decorCircle1: {
    position: 'absolute',
    top: -30,
    right: -30,
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
  },
  decorCircle2: {
    position: 'absolute',
    top: 40,
    right: 60,
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  decorCircle3: {
    position: 'absolute',
    bottom: -20,
    right: 20,
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(255, 255, 255, 0.06)',
  },
  // Action Card
  actionCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 20,
    marginHorizontal: 20,
    marginTop: -24,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
    zIndex: 10,
  },
  actionCardMobile: {
    marginHorizontal: 16,
    padding: 16,
  },
  actionCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    flexWrap: 'wrap',
  },
  actionCardInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  actionCardIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionCardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1f2937',
  },
  actionCardSubtitle: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  btnNovo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#10b981',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
  },
  btnNovoMobile: {
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 50,
  },
  btnNovoText: { color: '#fff', fontSize: 14, fontWeight: '600' },

  // Mobile Cards
  mobileContainer: { flex: 1, padding: 16 },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  cardPlano: { fontSize: 16, fontWeight: 'bold', color: '#111827' },
  cardValor: { fontSize: 14, color: '#10b981', marginTop: 4, fontWeight: '600' },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  statusText: { fontSize: 10, fontWeight: 'bold', color: '#fff' },
  cardInfo: { marginBottom: 12 },
  infoRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 6 },
  infoText: { fontSize: 13, color: '#6b7280' },
  cardActions: { flexDirection: 'row', gap: 8 },
  actionBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 10,
    borderRadius: 8,
  },
  btnRenovar: { backgroundColor: '#10b981' },
  actionBtnText: { color: '#fff', fontSize: 13, fontWeight: '600' },

  // Desktop Table
  tableContainer: {
    margin: 20,
    backgroundColor: '#fff',
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f8f9fa',
    padding: 16,
    borderBottomWidth: 2,
    borderBottomColor: '#e5e5e5',
  },
  tableBody: { flex: 1 },
  tableRow: {
    flexDirection: 'row',
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
  },
  headerText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#666',
    textTransform: 'uppercase',
  },
  tableCell: { justifyContent: 'center', paddingHorizontal: 4 },
  cellText: { fontSize: 14, color: '#333' },
  cellTextSmall: { fontSize: 12, color: '#6b7280' },
  actionCell: { flexDirection: 'row', gap: 8, justifyContent: 'center' },
  actionButton: {
    width: 36,
    height: 36,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 8,
  },
  btnRenovarSmall: { backgroundColor: '#10b981' },

  emptyContainer: {
    padding: 80,
    alignItems: 'center',
    backgroundColor: '#fff',
  },
  emptyText: { fontSize: 15, color: '#9ca3af', marginTop: 16 },

  // Modal
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 24,
    width: '90%',
    maxWidth: 500,
    maxHeight: '80%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  modalTitle: { fontSize: 20, fontWeight: 'bold', color: '#111' },

  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
    marginTop: 12,
  },
  input: {
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 14,
    color: '#111',
  },
  textArea: { minHeight: 80, textAlignVertical: 'top' },

  selectContainer: { marginBottom: 12 },
  selectOption: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderWidth: 2,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    padding: 16,
    marginBottom: 8,
  },
  selectOptionActive: {
    backgroundColor: 'rgba(249,115,22,0.1)',
    borderColor: '#f97316',
  },
  selectOptionText: { fontSize: 15, fontWeight: '600', color: '#111' },
  selectOptionPrice: { fontSize: 14, color: '#10b981', fontWeight: 'bold' },

  radioGroup: { marginBottom: 12 },
  radioOption: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 16,
    backgroundColor: '#f9fafb',
    borderWidth: 2,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    marginBottom: 8,
  },
  radioOptionActive: {
    backgroundColor: 'rgba(249,115,22,0.1)',
    borderColor: '#f97316',
  },
  radio: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: '#d1d5db',
    marginRight: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  radioInner: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#f97316',
  },
  radioLabel: { fontSize: 14, color: '#111', fontWeight: '500' },

  btnSubmit: {
    backgroundColor: '#f97316',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 20,
  },
  btnSubmitText: { color: '#fff', fontSize: 16, fontWeight: 'bold' },
});
