import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions, TextInput } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';
import api from '../../services/api';
import { authService } from '../../services/authService';

export default function ContratosScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [contratos, setContratos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busca, setBusca] = useState('');
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });

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
    loadContratos();
  };

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
    const statusLower = status?.toLowerCase() || '';
    switch (statusLower) {
      case 'ativo': return '#10b981';
      case 'pendente': return '#f59e0b';
      case 'cancelado': return '#ef4444';
      default: return '#6b7280';
    }
  };

  const getStatusBgColor = (status) => {
    const statusLower = status?.toLowerCase() || '';
    switch (statusLower) {
      case 'ativo': return 'rgba(16, 185, 129, 0.1)';
      case 'pendente': return 'rgba(245, 158, 11, 0.1)';
      case 'cancelado': return 'rgba(239, 68, 68, 0.1)';
      default: return 'rgba(107, 114, 128, 0.1)';
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
        <TouchableOpacity 
          key={contrato.id} 
          style={styles.card}
          onPress={() => router.push(`/contratos/detalhe?id=${contrato.id}`)}
          activeOpacity={0.7}
        >
          <View style={styles.cardHeader}>
            <View style={{ flex: 1 }}>
              <View style={styles.cardIdRow}>
                <Text style={styles.cardId}>#{contrato.id}</Text>
                <View style={[styles.statusBadgeNew, { backgroundColor: getStatusBgColor(contrato.status_nome) }]}>
                  <View style={[styles.statusDot, { backgroundColor: getStatusColor(contrato.status_nome) }]} />
                  <Text style={[styles.statusTextNew, { color: getStatusColor(contrato.status_nome) }]}>
                    {contrato.status_nome || 'N/A'}
                  </Text>
                </View>
              </View>
              <Text style={styles.cardAcademia}>{contrato.academia_nome}</Text>
              <Text style={styles.cardPlano}>{contrato.plano_nome}</Text>
            </View>
            <Feather name="chevron-right" size={20} color="#9ca3af" />
          </View>

          <View style={styles.cardInfo}>
            <View style={styles.infoRow}>
              <Feather name="calendar" size={14} color="#6b7280" />
              <Text style={styles.infoText}>
                {formatarData(contrato.data_inicio)} → {formatarData(contrato.data_vencimento)}
              </Text>
            </View>
            <View style={styles.infoRow}>
              <Feather name="dollar-sign" size={14} color="#10b981" />
              <Text style={styles.cardValor}>{formatarValor(contrato.valor)}</Text>
            </View>
          </View>
        </TouchableOpacity>
      ))}
    </ScrollView>
  );

  const renderDesktopTable = () => (
    <View style={styles.tableContainer}>
      <View style={styles.tableHeader}>
        <Text style={[styles.headerText, { flex: 0.5 }]}>ID</Text>
        <Text style={[styles.headerText, { flex: 2 }]}>ACADEMIA</Text>
        <Text style={[styles.headerText, { flex: 1.5 }]}>PLANO</Text>
        <Text style={[styles.headerText, { flex: 1.5 }]}>PERÍODO</Text>
        <Text style={[styles.headerText, { flex: 1 }]}>VALOR</Text>
        <Text style={[styles.headerText, { flex: 1.2 }]}>STATUS</Text>
        <Text style={[styles.headerText, { flex: 0.8, textAlign: 'center' }]}>AÇÕES</Text>
      </View>

      <ScrollView style={styles.tableBody}>
        {contratosFiltrados.map((contrato) => (
          <TouchableOpacity 
            key={contrato.id} 
            style={styles.tableRow}
            onPress={() => router.push(`/contratos/detalhe?id=${contrato.id}`)}
            activeOpacity={0.7}
          >
            <View style={[styles.tableCell, { flex: 0.5 }]}>
              <Text style={styles.tableIdText}>#{contrato.id}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 2 }]}>
              <Text style={styles.cellText} numberOfLines={1}>{contrato.academia_nome}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1.5 }]}>
              <Text style={styles.cellText} numberOfLines={1}>{contrato.plano_nome}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1.5 }]}>
              <Text style={styles.cellTextSmall}>{formatarData(contrato.data_inicio)}</Text>
              <Text style={styles.cellTextSmall}>{formatarData(contrato.data_vencimento)}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1 }]}>
              <Text style={styles.cellTextValor}>{formatarValor(contrato.valor)}</Text>
            </View>
            <View style={[styles.tableCell, { flex: 1.2 }]}>
              <View style={[styles.statusBadgeNew, { backgroundColor: getStatusBgColor(contrato.status_nome) }]}>
                <View style={[styles.statusDot, { backgroundColor: getStatusColor(contrato.status_nome) }]} />
                <Text style={[styles.statusTextNew, { color: getStatusColor(contrato.status_nome) }]}>
                  {contrato.status_nome || 'N/A'}
                </Text>
              </View>
            </View>
            <View style={[styles.tableCell, { flex: 0.8, alignItems: 'center' }]}>
              <TouchableOpacity
                style={styles.viewButton}
                onPress={() => router.push(`/contratos/detalhe?id=${contrato.id}`)}
              >
                <Feather name="arrow-right" size={18} color="#f97316" />
              </TouchableOpacity>
            </View>
          </TouchableOpacity>
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
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="file-text" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Contratos de Planos</Text>
                <Text style={styles.bannerSubtitle}>
                  Gerencie todos os contratos das academias parceiras
                </Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>

          {/* Card de Busca e Ações */}
          <View style={[styles.searchCard, isMobile && styles.searchCardMobile]}>
            <View style={styles.searchCardHeader}>
              <View style={styles.searchCardInfo}>
                <View style={styles.searchCardIconContainer}>
                  <Feather name="search" size={20} color="#f97316" />
                </View>
                <View>
                  <Text style={styles.searchCardTitle}>Buscar Contratos</Text>
                  <Text style={styles.searchCardSubtitle}>
                    {contratos.length} {contratos.length === 1 ? 'contrato' : 'contratos'} cadastrado(s)
                  </Text>
                </View>
              </View>
              <TouchableOpacity
                style={[styles.addButton, isMobile && styles.addButtonMobile]}
                onPress={() => router.push('/contratos/novo')}
                activeOpacity={0.8}
              >
                <Feather name="plus" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>Novo Contrato</Text>}
              </TouchableOpacity>
            </View>

            <View style={styles.searchInputContainer}>
              <Feather name="search" size={20} color="#9ca3af" style={styles.searchInputIcon} />
              <TextInput
                style={styles.searchInput}
                placeholder="Buscar por academia ou plano..."
                placeholderTextColor="#9ca3af"
                value={busca}
                onChangeText={setBusca}
              />
              {busca.length > 0 && (
                <TouchableOpacity onPress={() => setBusca('')} style={styles.clearButton}>
                  <Feather name="x-circle" size={20} color="#9ca3af" />
                </TouchableOpacity>
              )}
            </View>
          </View>
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
    gap: 18,
    zIndex: 2,
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
  // Search Card
  searchCard: {
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
  searchCardMobile: {
    marginHorizontal: 16,
    padding: 16,
  },
  searchCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
    gap: 12,
    flexWrap: 'wrap',
  },
  searchCardInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  searchCardIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  searchCardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1f2937',
  },
  searchCardSubtitle: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e5e7eb',
    paddingHorizontal: 14,
    height: 52,
  },
  searchInputIcon: {
    marginRight: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 16,
    color: '#1f2937',
    outlineStyle: 'none',
    height: '100%',
  },
  clearButton: {
    padding: 6,
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#10b981',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
    gap: 8,
  },
  addButtonMobile: {
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 50,
  },
  addButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },

  // Mobile Cards
  mobileContainer: { flex: 1, paddingHorizontal: 20, paddingTop: 8 },
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
  cardIdRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 6,
  },
  cardId: {
    fontSize: 13,
    fontWeight: '700',
    color: '#f97316',
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  cardAcademia: { fontSize: 12, color: '#6b7280', marginBottom: 4 },
  cardPlano: { fontSize: 16, fontWeight: 'bold', color: '#111827' },
  cardValor: { fontSize: 14, color: '#10b981', fontWeight: '700' },
  statusBadgeNew: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
    gap: 6,
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  statusTextNew: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  statusText: { fontSize: 10, fontWeight: 'bold', color: '#fff' },
  cardInfo: { marginBottom: 0 },
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
    fontSize: 11,
    fontWeight: '700',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  tableCell: { justifyContent: 'center', paddingHorizontal: 4 },
  tableIdText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#f97316',
  },
  cellText: { fontSize: 14, color: '#333' },
  cellTextValor: { fontSize: 14, color: '#10b981', fontWeight: '600' },
  cellTextSmall: { fontSize: 12, color: '#6b7280' },
  viewButton: {
    width: 40,
    height: 40,
    borderRadius: 10,
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: 'rgba(249, 115, 22, 0.2)',
  },

  emptyContainer: {
    padding: 80,
    alignItems: 'center',
    backgroundColor: '#fff',
    margin: 20,
    borderRadius: 12,
  },
  emptyText: { fontSize: 15, color: '#9ca3af', marginTop: 16, textAlign: 'center' },
});
