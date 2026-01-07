import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import planoService from '../../services/planoService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

export default function PlanosScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [planos, setPlanos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });

  useEffect(() => {
    loadPlanos();
  }, []);

  const loadPlanos = async () => {
    try {
      setLoading(true);
      console.log('🔄 Carregando planos...');
      const response = await planoService.listar();
      console.log('✅ Resposta da API:', response);
      setPlanos(response.planos || []);
      console.log('📊 Total de planos:', response.planos?.length || 0);
    } catch (error) {
      console.error('❌ Erro ao carregar planos:', error);
      showError(error.error || 'Erro ao carregar planos');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = (id, nome) => {
    setConfirmDelete({ visible: true, id, nome });
  };

  const confirmDeleteAction = async () => {
    try {
      const { id } = confirmDelete;
      await planoService.desativar(id);
      showSuccess('Plano desativado com sucesso');
      setConfirmDelete({ visible: false, id: null, nome: '' });
      loadPlanos();
    } catch (error) {
      showError(error.error || 'Não foi possível desativar o plano');
    }
  };

  const cancelDelete = () => {
    setConfirmDelete({ visible: false, id: null, nome: '' });
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  };

  const renderMobileCard = (plano) => (
    <View key={plano.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <View style={styles.cardTitleRow}>
            {plano.modalidade_icone && (
              <View style={[styles.cardIconBadge, { backgroundColor: plano.modalidade_cor || '#f97316' }]}>
                <MaterialCommunityIcons name={plano.modalidade_icone} size={16} color="#fff" />
              </View>
            )}
            <View>
              <View style={styles.idNomeRow}>
                <Text style={styles.cardId}>#{plano.id}</Text>
                <Text style={styles.cardName}>{plano.nome}</Text>
              </View>
              <View style={[
                styles.statusBadge,
                plano.ativo ? styles.statusActive : styles.statusInactive
              ]}>
                <Text style={[
                  styles.statusText,
                  plano.ativo ? styles.statusTextActive : styles.statusTextInactive
                ]}>
                  {plano.ativo ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
            </View>
          </View>
        </View>
        <View style={styles.cardActions}>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => router.push(`/planos/${plano.id}`)}
          >
            <Feather name="edit-2" size={18} color="#3b82f6" />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => handleDelete(plano.id, plano.nome)}
          >
            <Feather name="trash-2" size={18} color="#ef4444" />
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.cardBody}>
        {plano.modalidade_nome && (
          <View style={styles.cardRow}>
            <Feather name="grid" size={14} color="#666" />
            <Text style={styles.cardLabel}>Modalidade:</Text>
            <Text style={styles.cardValue}>{plano.modalidade_nome}</Text>
          </View>
        )}

        <View style={styles.cardRow}>
          <Feather name="dollar-sign" size={14} color="#666" />
          <Text style={styles.cardLabel}>Valor:</Text>
          <Text style={styles.cardValue}>{formatCurrency(plano.valor)}</Text>
        </View>

        <View style={styles.cardRow}>
          <Feather name="check-circle" size={14} color="#666" />
          <Text style={styles.cardLabel}>Checkins/Semana:</Text>
          <Text style={styles.cardValue}>
            {plano.checkins_semanais >= 999 
              ? 'Ilimitado' 
              : `${plano.checkins_semanais}x`}
          </Text>
        </View>

        {plano.atual !== undefined && (
          <View style={styles.cardRow}>
            <Feather name={plano.atual ? "unlock" : "lock"} size={14} color="#666" />
            <Text style={styles.cardLabel}>Novos Contratos:</Text>
            <Text style={[styles.cardValue, !plano.atual && styles.cardValueInactive]}>
              {plano.atual ? 'Disponível' : 'Bloqueado'}
            </Text>
          </View>
        )}
      </View>
    </View>
  );

  const renderTable = () => (
    <View style={styles.tableContainer}>
      {/* Table Header */}
      <View style={styles.tableHeader}>
        <Text style={[styles.headerText, styles.colId]}>ID</Text>
        <Text style={[styles.headerText, styles.colNome]}>NOME</Text>
        <Text style={[styles.headerText, styles.colModalidade]}>MODALIDADE</Text>
        <Text style={[styles.headerText, styles.colValor]}>VALOR</Text>
        <Text style={[styles.headerText, styles.colCheckins]}>CHECKINS/SEM</Text>
        <Text style={[styles.headerText, styles.colAtual]}>NOVOS CONTR.</Text>
        <Text style={[styles.headerText, styles.colStatus]}>STATUS</Text>
        <Text style={[styles.headerText, styles.colAcoes]}>AÇÕES</Text>
      </View>

      {/* Table Body */}
      <ScrollView style={styles.tableBody} showsVerticalScrollIndicator={true}>
        {planos.map((plano) => (
          <View key={plano.id} style={styles.tableRow}>
            <View style={[styles.cellText, styles.colId]}>
              <Text style={styles.tableIdText}>#{plano.id}</Text>
            </View>
            <View style={[styles.cellText, styles.colNome, styles.nomeCell]}>
              {plano.modalidade_icone && (
                <View style={[styles.tableIconBadge, { backgroundColor: plano.modalidade_cor || '#f97316' }]}>
                  <MaterialCommunityIcons name={plano.modalidade_icone} size={14} color="#fff" />
                </View>
              )}
              <Text style={styles.cellTextNome} numberOfLines={2}>
                {plano.nome}
              </Text>
            </View>
            <Text style={[styles.cellText, styles.colModalidade]} numberOfLines={1}>
              {plano.modalidade_nome || '-'}
            </Text>
            <Text style={[styles.cellText, styles.colValor]} numberOfLines={1}>
              {formatCurrency(plano.valor)}
            </Text>
            <Text style={[styles.cellText, styles.colCheckins]} numberOfLines={1}>
              {plano.checkins_semanais >= 999 
                ? 'Ilimitado' 
                : `${plano.checkins_semanais}x`}
            </Text>
            <View style={[styles.cellText, styles.colAtual]}>
              <View style={[
                styles.atualBadge,
                plano.atual ? styles.atualAvailable : styles.atualLocked,
              ]}>
                <Text style={[
                  styles.atualText,
                  plano.atual ? styles.atualTextAvailable : styles.atualTextLocked,
                ]}>
                  {plano.atual ? 'Sim' : 'Não'}
                </Text>
              </View>
            </View>
            <View style={[styles.cellText, styles.colStatus]}>
              <View style={[
                styles.statusBadge,
                plano.ativo ? styles.statusActive : styles.statusInactive,
              ]}>
                <Text style={[
                  styles.statusText,
                  plano.ativo ? styles.statusTextActive : styles.statusTextInactive,
                ]}>
                  {plano.ativo ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
            </View>
            <View style={[styles.cellText, styles.colAcoes]}>
              <View style={styles.actionCell}>
                <TouchableOpacity
                  onPress={() => router.push(`/planos/${plano.id}`)}
                  style={styles.actionButton}
                >
                  <Feather name="edit-2" size={16} color="#3b82f6" />
                </TouchableOpacity>
                <TouchableOpacity
                  onPress={() => handleDelete(plano.id, plano.nome)}
                  style={styles.actionButton}
                >
                  <Feather name="trash-2" size={16} color="#ef4444" />
                </TouchableOpacity>
              </View>
            </View>
          </View>
        ))}
      </ScrollView>
    </View>
  );

  if (loading) {
    return (
      <LayoutBase title="Planos" subtitle="Gerenciar planos de assinatura">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando planos...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Planos" subtitle="Gerenciar planos de assinatura">
      <View style={styles.container}>
        {/* Header Actions */}
        <View style={[styles.header, isMobile && styles.headerMobile]}>
          <View>
            <Text style={[styles.headerTitle, isMobile && styles.headerTitleMobile]}>Lista de Planos</Text>
            <Text style={styles.headerSubtitle}>{planos.length} plano(s) cadastrado(s)</Text>
          </View>
          <TouchableOpacity
            style={[styles.addButton, isMobile && styles.addButtonMobile]}
            onPress={() => router.push('/planos/novo')}
            activeOpacity={0.8}
          >
            <Feather name="plus" size={18} color="#fff" />
            {!isMobile && <Text style={styles.addButtonText}>Novo Plano</Text>}
          </TouchableOpacity>
        </View>

        {planos.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="inbox" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhum plano cadastrado</Text>
            <Text style={styles.emptySubtext}>Clique em "Novo Plano" para começar</Text>
          </View>
        ) : (
          isMobile ? (
            <ScrollView style={styles.cardsContainer} showsVerticalScrollIndicator={false}>
              {planos.map(renderMobileCard)}
            </ScrollView>
          ) : (
            renderTable()
          )
        )}
      </View>

      <ConfirmModal
        visible={confirmDelete.visible}
        title="Desativar Plano"
        message={`Tem certeza que deseja desativar o plano "${confirmDelete.nome}"?`}
        type="danger"
        onConfirm={confirmDeleteAction}
        onCancel={cancelDelete}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 100,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#6b7280',
    fontWeight: '500',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e5e5',
  },
  headerMobile: {
    padding: 16,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
  },
  headerTitleMobile: {
    fontSize: 20,
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
    fontWeight: '400',
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f97316',
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
    fontSize: 16,
    fontWeight: '600',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 80,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#6b7280',
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 8,
  },
  // Cards Mobile
  cardsContainer: {
    flex: 1,
    padding: 16,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  cardHeaderLeft: {
    flex: 1,
  },
  cardActions: {
    flexDirection: 'row',
    gap: 8,
  },
  cardActionButton: {
    padding: 8,
    borderRadius: 8,
    backgroundColor: '#f5f5f5',
  },
  cardBody: {
    gap: 10,
  },
  cardRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cardLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
    minWidth: 90,
  },
  cardValue: {
    flex: 1,
    fontSize: 14,
    color: '#333',
  },
  // Tabela Desktop
  tableContainer: {
    flex: 1,
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
  headerText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#666',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  tableBody: {
    flex: 1,
  },
  tableRow: {
    flexDirection: 'row',
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
  },
  cellText: {
    fontSize: 14,
    color: '#333',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  colId: { flex: 0.6, minWidth: 60 },
  colNome: { flex: 2, minWidth: 150 },
  colModalidade: { flex: 1.5, minWidth: 120 },
  colValor: { flex: 1.2, minWidth: 100 },
  colCheckins: { flex: 1.2, minWidth: 110 },
  colAtual: { flex: 1.2, minWidth: 110 },
  colStatus: { flex: 1, minWidth: 100 },
  colAcoes: { flex: 1, minWidth: 100 },
  tableIdText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#64748b',
  },
  nomeCell: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cellTextNome: {
    fontSize: 14,
    color: '#333',
    flex: 1,
  },
  tableIconBadge: {
    width: 24,
    height: 24,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  cardIconBadge: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  idNomeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  cardId: {
    fontSize: 12,
    fontWeight: '700',
    color: '#64748b',
    backgroundColor: '#f1f5f9',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  cardName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1e293b',
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  statusActive: {
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
  },
  statusInactive: {
    backgroundColor: 'rgba(239, 68, 68, 0.1)',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  statusTextActive: {
    color: '#10b981',
  },
  statusTextInactive: {
    color: '#ef4444',
  },
  atualBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  atualAvailable: {
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
  },
  atualLocked: {
    backgroundColor: 'rgba(156, 163, 175, 0.1)',
  },
  atualText: {
    fontSize: 12,
    fontWeight: '600',
  },
  atualTextAvailable: {
    color: '#3b82f6',
  },
  atualTextLocked: {
    color: '#6b7280',
  },
  cardValueInactive: {
    color: '#9ca3af',
    fontStyle: 'italic',
  },
  actionCell: {
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'flex-end',
  },
  actionButton: {
    padding: 8,
  },
});
