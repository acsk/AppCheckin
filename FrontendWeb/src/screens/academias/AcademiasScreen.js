import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  useWindowDimensions,
  TextInput,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import { superAdminService } from '../../services/superAdminService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

export default function AcademiasScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [loading, setLoading] = useState(true);
  const [academias, setAcademias] = useState([]);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });
  const [searchText, setSearchText] = useState('');

  useEffect(() => {
    loadAcademias();
  }, []);

  const loadAcademias = async (busca = '') => {
    try {
      setLoading(true);
      console.log('🔍 Buscando academias com termo:', busca);
      const response = await superAdminService.listarAcademias(busca);
      console.log('✅ Academias encontradas:', response.academias?.length);
      setAcademias(response.academias || []);
    } catch (error) {
      console.error('❌ Erro ao carregar academias:', error);
      showError('Erro ao carregar academias');
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = () => {
    const termo = searchText.trim();
    console.log('🔎 Executando busca com termo:', termo);
    loadAcademias(termo);
  };

  const handleClearSearch = () => {
    console.log('🗑️ Limpando busca');
    setSearchText('');
    loadAcademias('');
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

  const renderMobileCard = (item) => (
    <View key={item.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <Text style={styles.cardName}>{item.nome}</Text>
          <View style={[
            styles.statusBadge,
            item.ativo ? styles.badgeActive : styles.badgeInactive
          ]}>
            <Text style={styles.badgeText}>
              {item.ativo ? 'Ativa' : 'Inativa'}
            </Text>
          </View>
        </View>
        <View style={styles.cardActions}>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => router.push(`/academias/${item.id}`)}
          >
            <Feather name="edit-2" size={18} color="#3b82f6" />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => handleDelete(item.id, item.nome)}
          >
            <Feather name="trash-2" size={18} color="#ef4444" />
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.cardBody}>
        <View style={styles.cardRow}>
          <Feather name="mail" size={14} color="#666" />
          <Text style={styles.cardLabel}>Email:</Text>
          <Text style={styles.cardValue}>{item.email}</Text>
        </View>

        <View style={styles.cardRow}>
          <Feather name="phone" size={14} color="#666" />
          <Text style={styles.cardLabel}>Telefone:</Text>
          <Text style={styles.cardValue}>{item.telefone || '-'}</Text>
        </View>
      </View>
    </View>
  );

  const renderTable = () => (
    <View style={styles.tableContainer}>
      <View style={styles.tableHeader}>
        <Text style={[styles.headerText, styles.colNome]}>NOME</Text>
        <Text style={[styles.headerText, styles.colEmail]}>EMAIL</Text>
        <Text style={[styles.headerText, styles.colTelefone]}>TELEFONE</Text>
        <Text style={[styles.headerText, styles.colStatus]}>STATUS</Text>
        <Text style={[styles.headerText, styles.colAcoes]}>AÇÕES</Text>
      </View>

      <ScrollView style={styles.tableBody} showsVerticalScrollIndicator={true}>
        {academias.map((item) => (
          <View key={item.id} style={styles.tableRow}>
            <Text style={[styles.cellText, styles.colNome]} numberOfLines={2}>
              {item.nome}
            </Text>
            <Text style={[styles.cellText, styles.colEmail]} numberOfLines={1}>
              {item.email}
            </Text>
            <Text style={[styles.cellText, styles.colTelefone]} numberOfLines={1}>
              {item.telefone || '-'}
            </Text>
            <View style={[styles.cellText, styles.colStatus]}>
              <View style={[
                styles.statusBadge,
                item.ativo ? styles.badgeActive : styles.badgeInactive
              ]}>
                <Text style={styles.badgeText}>
                  {item.ativo ? 'Ativa' : 'Inativa'}
                </Text>
              </View>
            </View>
            <View style={[styles.cellText, styles.colAcoes]}>
              <View style={styles.actionCell}>
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={() => router.push({
                    pathname: '/contratos/academia',
                    params: { id: item.id, nome: item.nome }
                  })}
                >
                  <Feather name="file-text" size={16} color="#10b981" />
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={() => router.push(`/academias/${item.id}`)}
                >
                  <Feather name="edit-2" size={16} color="#3b82f6" />
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={() => handleDelete(item.id, item.nome)}
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
      {/* <View style={styles.tableCell}>
        <Text style={styles.cellText}>{item.endereco || '-'}</Text>
      </View> */}
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
          style={[styles.actionButton, styles.contractButton]}
          onPress={() => router.push({
            pathname: '/contratos/academia',
            params: { id: item.id, nome: item.nome }
          })}
        >
          <Feather name="file-text" size={16} color="#fff" />
        </TouchableOpacity>
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
        <View style={[styles.header, isMobile && styles.headerMobile]}>
          <View style={styles.headerLeft}>
            <Text style={[styles.headerTitle, isMobile && styles.headerTitleMobile]}>Academias</Text>
            <Text style={styles.headerSubtitle}>
              {academias.length} {academias.length === 1 ? 'academia' : 'academias'} cadastradas
            </Text>
          </View>
          <View style={styles.headerRight}>
            <View style={[styles.searchContainer, isMobile && styles.searchContainerMobile]}>
              <Feather name="search" size={18} color="#666" style={styles.searchIcon} />
              <TextInput
                style={[styles.searchInput, isMobile && styles.searchInputMobile]}
                placeholder="Buscar por nome, email ou CNPJ..."
                value={searchText}
                onChangeText={setSearchText}
                onSubmitEditing={handleSearch}
                returnKeyType="search"
              />
              {searchText.length > 0 && (
                <TouchableOpacity onPress={handleClearSearch} style={styles.clearButton}>
                  <Feather name="x" size={18} color="#666" />
                </TouchableOpacity>
              )}
              <TouchableOpacity onPress={handleSearch} style={styles.searchButton}>
                <Text style={styles.searchButtonText}>Buscar</Text>
              </TouchableOpacity>
            </View>
            <TouchableOpacity
              style={[styles.addButton, isMobile && styles.addButtonMobile]}
              onPress={() => router.push('/academias/novo')}
            >
              <Feather name="plus" size={18} color="#fff" />
              {!isMobile && <Text style={styles.addButtonText}>Nova Academia</Text>}
            </TouchableOpacity>
          </View>
        </View>

        {academias.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="inbox" size={48} color="#ccc" />
            <Text style={styles.emptyText}>Nenhuma academia cadastrada</Text>
          </View>
        ) : (
          isMobile ? (
            <ScrollView style={styles.cardsContainer} showsVerticalScrollIndicator={false}>
              {academias.map(renderMobileCard)}
            </ScrollView>
          ) : (
            renderTable()
          )
        )}
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
    backgroundColor: '#f5f5f5',
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
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e5e5',
    gap: 16,
    flexWrap: 'wrap',
    rowGap: 12,
  },
  headerMobile: {
    flexDirection: 'column',
    padding: 16,
    gap: 12,
  },
  headerLeft: {
    flexShrink: 0,
    minWidth: 220,
    paddingRight: 12,
  },
  headerRight: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    gap: 12,
    flexWrap: 'wrap',
  },
  searchContainer: {
    flexGrow: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f3f4f6',
    borderRadius: 8,
    paddingHorizontal: 12,
    height: 44,
    maxWidth: 500,
    minWidth: 280,
  },
  searchContainerMobile: {
    maxWidth: '100%',
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: '#333',
    outlineStyle: 'none',
  },
  searchInputMobile: {
    fontSize: 14,
  },
  clearButton: {
    padding: 4,
    marginLeft: 4,
  },
  searchButton: {
    backgroundColor: '#3b82f6',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 6,
    marginLeft: 8,
  },
  searchButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
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
    flexShrink: 0,
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
    minWidth: 70,
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
  headerText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#666',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  cellText: {
    fontSize: 14,
    color: '#333',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  colNome: { flex: 2, minWidth: 150 },
  colEmail: { flex: 2.5, minWidth: 180 },
  colTelefone: { flex: 1.5, minWidth: 120 },
  colStatus: { flex: 1, minWidth: 100 },
  colAcoes: { flex: 1, minWidth: 100 },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  badgeActive: {
    backgroundColor: '#d1fae5',
  },
  badgeInactive: {
    backgroundColor: '#fee2e2',
  },
  badgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#374151',
  },
  actionCell: {
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'flex-end',
  },
  actionButton: {
    padding: 8,
  },
  contractButton: {
    backgroundColor: '#10b981',
    borderRadius: 8,
    padding: 8,
  },
  editButton: {
    backgroundColor: '#3b82f6',
    borderRadius: 8,
    padding: 8,
  },
  deleteButton: {
    backgroundColor: '#ef4444',
    borderRadius: 8,
    padding: 8,
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
});
