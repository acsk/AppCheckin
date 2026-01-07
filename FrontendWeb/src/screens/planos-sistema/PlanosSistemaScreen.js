import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import planosSistemaService from '../../services/planosSistemaService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';
import { authService } from '../../services/authService';

export default function PlanosSistemaScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [planos, setPlanos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });

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
    loadPlanos();
  };

  const loadPlanos = async () => {
    try {
      setLoading(true);
      console.log('🔄 Carregando planos do sistema...');
      const response = await planosSistemaService.listar();
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
      await planosSistemaService.desativar(id);
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
          <Text style={styles.cardName}>{plano.nome}</Text>
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
        <View style={styles.cardActions}>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => router.push(`/planos-sistema/${plano.id}`)}
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
        <View style={styles.cardRow}>
          <Feather name="dollar-sign" size={14} color="#666" />
          <Text style={styles.cardLabel}>Valor:</Text>
          <Text style={styles.cardValue}>{formatCurrency(plano.valor)}</Text>
        </View>

        <View style={styles.cardRow}>
          <Feather name="users" size={14} color="#666" />
          <Text style={styles.cardLabel}>Capacidade:</Text>
          <Text style={styles.cardValue}>
            {!plano.max_alunos || plano.max_alunos >= 9999 
              ? 'Ilimitado' 
              : `${plano.max_alunos} alunos`}
          </Text>
        </View>
      </View>
    </View>
  );

  const renderTable = () => (
    <View style={styles.tableContainer}>
      {/* Table Header */}
      <View style={styles.tableHeader}>
        <Text style={[styles.headerText, styles.colNome]}>NOME</Text>
        <Text style={[styles.headerText, styles.colValor]}>VALOR MENSAL</Text>
        <Text style={[styles.headerText, styles.colCapacidade]}>CAPACIDADE</Text>
        <Text style={[styles.headerText, styles.colAtual]}>PLANO ATUAL</Text>
        <Text style={[styles.headerText, styles.colStatus]}>STATUS</Text>
        <Text style={[styles.headerText, styles.colAcoes]}>AÇÕES</Text>
      </View>

      {/* Table Body */}
      <ScrollView style={styles.tableBody} showsVerticalScrollIndicator={true}>
        {planos.map((plano) => (
          <View key={plano.id} style={styles.tableRow}>
            <Text style={[styles.cellText, styles.colNome]} numberOfLines={2}>
              {plano.nome}
            </Text>
            <Text style={[styles.cellText, styles.colValor]} numberOfLines={1}>
              {formatCurrency(plano.valor)}
            </Text>
            <Text style={[styles.cellText, styles.colCapacidade]} numberOfLines={1}>
              {!plano.max_alunos || plano.max_alunos >= 9999 
                ? 'Ilimitado' 
                : `${plano.max_alunos} alunos`}
            </Text>
            <View style={[styles.cellText, styles.colAtual]}>
              <View style={[
                styles.statusBadge,
                plano.atual ? styles.statusCurrent : styles.statusHistoric,
              ]}>
                <Text style={[
                  styles.statusText,
                  plano.atual ? styles.statusTextCurrent : styles.statusTextHistoric,
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
                  onPress={() => router.push(`/planos-sistema/${plano.id}`)}
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
      <LayoutBase title="Planos Sistema" subtitle="Gerenciar planos de assinatura">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando planos...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Planos Sistema" subtitle="Gerenciar planos de assinatura">
      <View style={styles.container}>
        {/* Header Actions */}
        <View style={[styles.header, isMobile && styles.headerMobile]}>
          <View>
            <Text style={[styles.headerTitle, isMobile && styles.headerTitleMobile]}>Lista de Planos Sistema</Text>
            <Text style={styles.headerSubtitle}>{planos.length} plano(s) cadastrado(s)</Text>
          </View>
          <View style={styles.headerButtons}>
            <TouchableOpacity
              style={[styles.contractButton, isMobile && styles.contractButtonMobile]}
              onPress={() => router.push('/contratos/novo')}
              activeOpacity={0.8}
            >
              <Feather name="file-plus" size={18} color="#fff" />
              {!isMobile && <Text style={styles.contractButtonText}>Novo Contrato</Text>}
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.addButton, isMobile && styles.addButtonMobile]}
              onPress={() => router.push('/planos-sistema/novo')}
              activeOpacity={0.8}
            >
              <Feather name="plus" size={18} color="#fff" />
              {!isMobile && <Text style={styles.addButtonText}>Novo Plano</Text>}
            </TouchableOpacity>
          </View>
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
  headerButtons: {
    flexDirection: 'row',
    gap: 12,
  },
  contractButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#10b981',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
    gap: 8,
  },
  contractButtonMobile: {
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 50,
  },
  contractButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
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
    gap: 8,
  },
  cardName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
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
  colNome: { flex: 2, minWidth: 150 },
  colValor: { flex: 1.5, minWidth: 120 },
  colCapacidade: { flex: 1.5, minWidth: 120 },
  colAtual: { flex: 1, minWidth: 100 },
  colStatus: { flex: 1, minWidth: 100 },
  colAcoes: { flex: 1, minWidth: 100 },
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
  statusCurrent: {
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
  },
  statusHistoric: {
    backgroundColor: 'rgba(156, 163, 175, 0.1)',
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
  statusTextCurrent: {
    color: '#3b82f6',
  },
  statusTextHistoric: {
    color: '#6b7280',
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
