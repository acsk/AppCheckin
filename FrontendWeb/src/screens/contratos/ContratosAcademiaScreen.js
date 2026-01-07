import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Modal, TextInput, Alert, ActivityIndicator, useWindowDimensions } from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import { buscarContratos, criarContrato, trocarPlano, renovarContrato } from '../../services/contratoService';
import { buscarPlanos } from '../../services/planoService';
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
    if (!user || user.role_id !== 3) {
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
      setContratos(resultContratos.data.historico || []);
    }

    const resultPlanos = await buscarPlanos();
    if (resultPlanos.success) {
      setPlanos(resultPlanos.data.planos || []);
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
    <LayoutBase>
      <View style={styles.container}>
        {/* Header */}
        <View style={styles.header}>
          <View style={styles.headerLeft}>
            <TouchableOpacity onPress={() => router.back()} style={styles.backButton}>
              <Ionicons name="arrow-back" size={20} color="#666" />
            </TouchableOpacity>
            <View>
              <Text style={styles.title}>Contratos - {academiaNome}</Text>
              <Text style={styles.subtitle}>Gerenciar contratos de planos</Text>
            </View>
          </View>
          <TouchableOpacity style={styles.btnNovo} onPress={() => setModalCriar(true)}>
            <Ionicons name="add" size={20} color="#fff" />
            <Text style={styles.btnNovoText}>Novo Contrato</Text>
          </TouchableOpacity>
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
  container: { flex: 1, backgroundColor: '#f9fafb' },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },

  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  headerLeft: { flexDirection: 'row', alignItems: 'center', gap: 16, flex: 1 },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 8,
    backgroundColor: '#f3f4f6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  title: { fontSize: 20, fontWeight: 'bold', color: '#111827' },
  subtitle: { fontSize: 13, color: '#6b7280', marginTop: 2 },
  btnNovo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#f97316',
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
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
