import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions, TextInput } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';
import api from '../../services/api';

export default function ContratosScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [contratos, setContratos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busca, setBusca] = useState('');
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });

  useEffect(() => {
    loadContratos();
  }, []);

  const loadContratos = async () => {
    try {
      setLoading(true);
      const response = await api.get('/superadmin/contratos');
      setContratos(response.data.contratos || []);
    } catch (error) {
      console.error('Erro ao carregar contratos:', error);
      showError(error.error || 'Erro ao carregar contratos');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = (id, planoNome) => {
    setConfirmDelete({ visible: true, id, nome: planoNome });
  };

  const confirmDeleteAction = async () => {
    try {
      const { id } = confirmDelete;
      await api.delete(`/superadmin/contratos/${id}`);
      showSuccess('Contrato cancelado com sucesso');
      setConfirmDelete({ visible: false, id: null, nome: '' });
      loadContratos();
    } catch (error) {
      showError(error.error || 'Não foi possível cancelar o contrato');
    }
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

  const contratosFiltrados = contratos.filter(contrato =>
    contrato.academia_nome?.toLowerCase().includes(busca.toLowerCase()) ||
    contrato.plano_nome?.toLowerCase().includes(busca.toLowerCase())
  );

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
      {contratosFiltrados.map((contrato) => (
        <View key={contrato.id} style={styles.card}>
          <View style={styles.cardHeader}>
            <View style={{ flex: 1 }}>
              <Text style={styles.cardAcademia}>{contrato.academia_nome}</Text>
              <Text style={styles.cardPlano}>{contrato.plano_nome}</Text>
              <Text style={styles.cardValor}>{formatarValor(contrato.valor)}</Text>
            </View>
            <View style={[styles.statusBadge, { backgroundColor: getStatusColor(contrato.status) }]}>
              <Text style={styles.statusText}>{contrato.status.toUpperCase()}</Text>
            </View>
          </View>

          <View style={styles.cardInfo}>
            <View style={styles.infoRow}>
              <Feather name="calendar" size={14} color="#6b7280" />
              <Text style={styles.infoText}>
                {formatarData(contrato.data_inicio)} → {formatarData(contrato.data_vencimento)}
              </Text>
            </View>
            <View style={styles.infoRow}>
              <Feather name="credit-card" size={14} color="#6b7280" />
              <Text style={styles.infoText}>{getFormaPagamentoLabel(contrato.forma_pagamento)}</Text>
            </View>
          </View>

          <View style={styles.cardActions}>
            <TouchableOpacity
              style={styles.cardButton}
              onPress={() => router.push(`/contratos/academia?id=${contrato.academia_id}&nome=${encodeURIComponent(contrato.academia_nome)}`)}
            >
              <Feather name="eye" size={16} color="#f97316" />
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.cardButton}
              onPress={() => handleDelete(contrato.id, contrato.plano_nome)}
            >
              <Feather name="trash-2" size={16} color="#ef4444" />
            </TouchableOpacity>
          </View>
        </View>
      ))}
    </ScrollView>
  );

  const renderDesktopTable = () => (
    <View style={styles.tableContainer}>
      <View style={styles.tableHeader}>
        <Text style={[styles.headerText, { flex: 2 }]}>Academia</Text>
        <Text style={[styles.headerText, { flex: 1.5 }]}>Plano</Text>
        <Text style={[styles.headerText, { flex: 1.5 }]}>Período</Text>
        <Text style={[styles.headerText, { flex: 1 }]}>Pagamento</Text>
        <Text style={[styles.headerText, { flex: 1 }]}>Valor</Text>
        <Text style={[styles.headerText, { flex: 1 }]}>Status</Text>
        <Text style={[styles.headerText, { flex: 1 }]}>Ações</Text>
      </View>

      <ScrollView style={styles.tableBody}>
        {contratosFiltrados.map((contrato) => (
          <View key={contrato.id} style={styles.tableRow}>
            <View style={[styles.tableCell, { flex: 2 }]}>
              <Text style={styles.cellText}>{contrato.academia_nome}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1.5 }]}>
              <Text style={styles.cellText}>{contrato.plano_nome}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1.5 }]}>
              <Text style={styles.cellTextSmall}>{formatarData(contrato.data_inicio)}</Text>
              <Text style={styles.cellTextSmall}>{formatarData(contrato.data_vencimento)}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1 }]}>
              <Text style={styles.cellText}>{getFormaPagamentoLabel(contrato.forma_pagamento)}</Text>
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
                <TouchableOpacity
                  style={[styles.actionButton, styles.btnView]}
                  onPress={() => router.push(`/contratos/academia?id=${contrato.academia_id}&nome=${encodeURIComponent(contrato.academia_nome)}`)}
                >
                  <Feather name="eye" size={16} color="#fff" />
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.actionButton, styles.btnDelete]}
                  onPress={() => handleDelete(contrato.id, contrato.plano_nome)}
                >
                  <Feather name="trash-2" size={16} color="#fff" />
                </TouchableOpacity>
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
          <View style={{ flex: 1 }}>
            <Text style={styles.title}>Contratos de Planos</Text>
            <Text style={styles.subtitle}>{contratos.length} contrato(s) cadastrado(s)</Text>
          </View>
        </View>

        {/* Busca */}
        <View style={styles.searchContainer}>
          <Feather name="search" size={20} color="#9ca3af" />
          <TextInput
            style={styles.searchInput}
            placeholder="Buscar por academia ou plano..."
            placeholderTextColor="#9ca3af"
            value={busca}
            onChangeText={setBusca}
          />
        </View>

        {/* Conteúdo */}
        {contratosFiltrados.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="file-text" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>
              {busca ? 'Nenhum contrato encontrado' : 'Nenhum contrato cadastrado'}
            </Text>
          </View>
        ) : isMobile ? (
          renderMobileCards()
        ) : (
          renderDesktopTable()
        )}

        {/* Modal de Confirmação */}
        <ConfirmModal
          visible={confirmDelete.visible}
          title="Cancelar Contrato"
          message={`Deseja realmente cancelar o contrato do plano "${confirmDelete.nome}"?`}
          onConfirm={confirmDeleteAction}
          onCancel={() => setConfirmDelete({ visible: false, id: null, nome: '' })}
        />
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f9fafb' },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },

  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  title: { fontSize: 24, fontWeight: 'bold', color: '#111827' },
  subtitle: { fontSize: 14, color: '#6b7280', marginTop: 4 },

  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    margin: 20,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  searchInput: { flex: 1, fontSize: 15, color: '#111827' },

  // Mobile Cards
  mobileContainer: { flex: 1, paddingHorizontal: 20 },
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
  cardAcademia: { fontSize: 12, color: '#6b7280', marginBottom: 4 },
  cardPlano: { fontSize: 16, fontWeight: 'bold', color: '#111827', marginBottom: 4 },
  cardValor: { fontSize: 14, color: '#10b981', fontWeight: '600' },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  statusText: { fontSize: 10, fontWeight: 'bold', color: '#fff' },
  cardInfo: { marginBottom: 12 },
  infoRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 6 },
  infoText: { fontSize: 13, color: '#6b7280' },
  cardActions: {
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'flex-end',
  },
  cardButton: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: '#f3f4f6',
    justifyContent: 'center',
    alignItems: 'center',
  },

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
  btnView: { backgroundColor: '#f97316' },
  btnDelete: { backgroundColor: '#ef4444' },

  emptyContainer: {
    padding: 80,
    alignItems: 'center',
    backgroundColor: '#fff',
    margin: 20,
    borderRadius: 12,
  },
  emptyText: { fontSize: 15, color: '#9ca3af', marginTop: 16, textAlign: 'center' },
});
