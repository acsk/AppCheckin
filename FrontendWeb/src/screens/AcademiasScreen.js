import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  FlatList,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { superAdminService } from '../services/superAdminService';
import LayoutBase from '../components/LayoutBase';
import ConfirmModal from '../components/ConfirmModal';
import { showSuccess, showError } from '../utils/toast';

export default function AcademiasScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [academias, setAcademias] = useState([]);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });

  useEffect(() => {
    loadAcademias();
  }, []);

  const loadAcademias = async () => {
    try {
      setLoading(true);
      const response = await superAdminService.listarAcademias();
      setAcademias(response.academias || []);
    } catch (error) {
      console.error('Erro ao carregar academias:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id, nome) => {
    setConfirmDelete({ visible: true, id, nome });
  };

  const confirmDeleteAction = async () => {
    const { id, nome } = confirmDelete;
    setConfirmDelete({ visible: false, id: null, nome: '' });
    
    try {
      await superAdminService.excluirAcademia(id);
      showSuccess(`Academia "${nome}" excluída com sucesso!`);
      loadAcademias();
    } catch (error) {
      console.error('Erro ao excluir academia:', error);
      const errorMessage = error.response?.data?.error || error.message || 'Erro ao excluir academia';
      showError(errorMessage);
    }
  };

  const cancelDelete = () => {
    setConfirmDelete({ visible: false, id: null, nome: '' });
  };

  const renderAcademia = (item) => (
    <View key={item.id} style={styles.tableRow}>
      <View style={styles.tableCell}>
        <Text style={styles.cellText}>{item.nome}</Text>
      </View>
      <View style={styles.tableCell}>
        <Text style={styles.cellText}>{item.email}</Text>
      </View>
      <View style={styles.tableCell}>
        <Text style={styles.cellText}>{item.telefone || '-'}</Text>
      </View>
      <View style={styles.tableCell}>
        <Text style={styles.cellText}>{item.endereco || '-'}</Text>
      </View>
      <View style={styles.tableCell}>
        <View style={[
          styles.statusBadge,
          item.ativo ? styles.badgeActive : styles.badgeInactive
        ]}>
          <Text style={styles.badgeText}>
            {item.ativo ? 'Ativa' : 'Inativa'}
          </Text>
        </View>
      </View>
      <View style={[styles.tableCell, styles.actionCell]}>
        <TouchableOpacity
          style={[styles.actionButton, styles.editButton]}
          onPress={() => router.push(`/academias/${item.id}`)}
        >
          <Feather name="edit-2" size={16} color="#fff" />
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.actionButton, styles.deleteButton]}
          onPress={() => handleDelete(item.id, item.nome)}
        >
          <Feather name="trash-2" size={16} color="#fff" />
        </TouchableOpacity>
      </View>
    </View>
  );

  if (loading) {
    return (
      <LayoutBase title="Academias" subtitle="Gerenciar academias">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#2b1a04" />
          <Text style={styles.loadingText}>Carregando academias...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Academias" subtitle="Gerenciar academias">
      <View style={styles.container}>
        <View style={styles.header}>
          <View>
            <Text style={styles.headerTitle}>Academias</Text>
            <Text style={styles.headerSubtitle}>
              {academias.length} {academias.length === 1 ? 'academia' : 'academias'} cadastradas
            </Text>
          </View>
          <TouchableOpacity
            style={styles.addButton}
            onPress={() => router.push('/academias/novo')}
          >
            <Feather name="plus" size={18} color="#fff" style={styles.addButtonIcon} />
            <Text style={styles.addButtonText}>Nova Academia</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.tableContainer}>
          <View style={styles.tableHeader}>
            <View style={styles.tableCell}>
              <Text style={styles.headerText}>Nome</Text>
            </View>
            <View style={styles.tableCell}>
              <Text style={styles.headerText}>Email</Text>
            </View>
            <View style={styles.tableCell}>
              <Text style={styles.headerText}>Telefone</Text>
            </View>
            <View style={styles.tableCell}>
              <Text style={styles.headerText}>Endereço</Text>
            </View>
            <View style={styles.tableCell}>
              <Text style={styles.headerText}>Status</Text>
            </View>
            <View style={[styles.tableCell, styles.actionCell]}>
              <Text style={styles.headerText}>Ações</Text>
            </View>
          </View>

          {academias.length === 0 ? (
            <View style={styles.emptyContainer}>
              <Feather name="inbox" size={48} color="#ccc" />
              <Text style={styles.emptyText}>Nenhuma academia cadastrada</Text>
            </View>
          ) : (
            <View style={styles.tableBody}>
              {academias.map((item) => renderAcademia(item))}
            </View>
          )}
        </View>
      </View>

      <ConfirmModal
        visible={confirmDelete.visible}
        title="Excluir Academia"
        message={`Deseja realmente excluir a academia "${confirmDelete.nome}"? Esta ação não pode ser desfeita.`}
        confirmText="Excluir"
        cancelText="Cancelar"
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
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 100,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: '#666',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 20,
    marginBottom: 24,
  },
  headerTitle: {
    fontSize: 28,
    fontWeight: '700',
    color: '#1f2937',
    letterSpacing: -0.5,
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
    fontWeight: '400',
  },
  addButton: {
    backgroundColor: '#f97316',
    paddingHorizontal: 18,
    paddingVertical: 10,
    borderRadius: 8,
    flexDirection: 'row',
    alignItems: 'center',
    shadowColor: '#f97316',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
  },
  tableContainer: {
    backgroundColor: 'rgba(255, 255, 255, 0.8)',
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: 'rgba(249, 250, 251, 0.7)',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  tableBody: {
    backgroundColor: 'rgba(255, 255, 255, 0.5)',
  },
  tableRow: {
    flexDirection: 'row',
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
    transition: 'background-color 0.15s',
  },
  tableCell: {
    flex: 1,
    paddingHorizontal: 8,
  },
  actionCell: {
    flex: 0.5,
    flexDirection: 'row',
    gap: 6,
    justifyContent: 'flex-end',
  },
  headerText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  cellText: {
    fontSize: 14,
    color: '#374151',
    fontWeight: '400',
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
    alignSelf: 'flex-start',
  },
  actionButton: {
    paddingHorizontal: 10,
    paddingVertical: 8,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  editButton: {
    backgroundColor: '#6b7280',
  },
  deleteButton: {
    backgroundColor: '#6b7280',
  },
  badgeActive: {
    backgroundColor: '#d1fae5',
  },
  badgeInactive: {
    backgroundColor: '#fee2e2',
  },
  badgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#374151',
  },
  emptyContainer: {
    padding: 80,
    alignItems: 'center',
    backgroundColor: '#fff',
  },
  emptyText: {
    fontSize: 15,
    color: '#9ca3af',
    marginTop: 16,
    fontWeight: '400',
  },
  addButtonIcon: {
    marginRight: 6,
  },
  addButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  cardInfoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
});
