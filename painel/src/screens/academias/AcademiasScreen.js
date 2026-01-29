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
import { authService } from '../../services/authService';

export default function AcademiasScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [loading, setLoading] = useState(true);
  const [academias, setAcademias] = useState([]);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });
  const [confirmToggle, setConfirmToggle] = useState({ visible: false, id: null, nome: '', ativo: false });
  const [searchText, setSearchText] = useState('');

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
    loadAcademias();
  };

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

  const handleToggleAtivo = (id, nome, ativoAtual) => {
    setConfirmToggle({ visible: true, id, nome, ativo: !ativoAtual });
  };

  const confirmToggleAction = async () => {
    const { id, nome, ativo } = confirmToggle;
    setConfirmToggle({ visible: false, id: null, nome: '', ativo: false });
    
    try {
      await superAdminService.toggleAtivoAcademia(id, ativo);
      showSuccess(`Academia "${nome}" ${ativo ? 'ativada' : 'desativada'} com sucesso!`);
      loadAcademias();
    } catch (error) {
      console.error('Erro ao alterar status:', error);
      const errorMessage = error.response?.data?.error || error.message || 'Erro ao alterar status';
      showError(errorMessage);
    }
  };

  const cancelToggle = () => {
    setConfirmToggle({ visible: false, id: null, nome: '', ativo: false });
  };

  const renderMobileCard = (item) => (
    <View key={item.id} style={styles.card}>
      <TouchableOpacity
        style={styles.cardHeader}
        onPress={() => router.push(`/academias/${item.id}`)}
        activeOpacity={0.7}
      >
        <View style={styles.cardHeaderLeft}>
          <View style={styles.cardIdRow}>
            <Text style={styles.cardId}>#{item.id}</Text>
            <Text style={styles.cardName}>{item.nome}</Text>
          </View>
          <View style={[
            styles.statusBadge,
            item.ativo ? styles.badgeActive : styles.badgeInactive
          ]}>
            <Text style={styles.badgeText}>
              {item.ativo ? 'Ativa' : 'Inativa'}
            </Text>
          </View>
        </View>
        <Feather name="chevron-right" size={20} color="#9ca3af" />
      </TouchableOpacity>

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
    <View className="mx-4 my-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-3">
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colId}>ID</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colNome}>NOME</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colEmail}>EMAIL</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colTelefone}>TELEFONE</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colStatus}>STATUS</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-center" style={styles.colAcoes}>AÇÕES</Text>
      </View>

      <ScrollView className="max-h-[520px]" showsVerticalScrollIndicator={true}>
        {academias.map((item) => (
          <TouchableOpacity 
            key={item.id} 
            className="flex-row items-center border-b border-slate-100 px-4 py-3"
            onPress={() => router.push(`/academias/${item.id}`)}
            activeOpacity={0.7}
          >
            <View style={styles.colId}>
              <Text className="text-[12px] font-semibold text-slate-400">#{item.id}</Text>
            </View>
            <Text className="text-[13px] font-semibold text-slate-800" style={styles.colNome} numberOfLines={2}>
              {item.nome}
            </Text>
            <Text className="text-[13px] text-slate-600" style={styles.colEmail} numberOfLines={1}>
              {item.email}
            </Text>
            <Text className="text-[13px] text-slate-600" style={styles.colTelefone} numberOfLines={1}>
              {item.telefone || '-'}
            </Text>
            <View style={styles.colStatus}>
              <View className={`self-start rounded-full px-2.5 py-1 ${item.ativo ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                <Text className={`text-[11px] font-bold ${item.ativo ? 'text-emerald-700' : 'text-rose-700'}`}>
                  {item.ativo ? 'Ativa' : 'Inativa'}
                </Text>
              </View>
            </View>
            <View className="items-center" style={styles.colAcoes}>
              <View className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50">
                <Feather name="edit-2" size={16} color="#f97316" />
              </View>
            </View>
          </TouchableOpacity>
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
          style={[styles.actionButton, item.ativo ? styles.toggleActiveButton : styles.toggleInactiveButton]}
          onPress={() => handleToggleAtivo(item.id, item.nome, item.ativo)}
        >
          <Feather name={item.ativo ? 'toggle-right' : 'toggle-left'} size={16} color="#fff" />
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
    <LayoutBase title="Academias" subtitle="Gerenciar academias" noPadding> 
      <View style={styles.container}>
        {/* Banner Header */}
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="home" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Academias Parceiras</Text>
                <Text style={styles.bannerSubtitle}>
                  Gerencie todas as academias cadastradas no sistema
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
                  <Text style={styles.searchCardTitle}>Buscar Academia</Text>
                  <Text style={styles.searchCardSubtitle}>
                    {academias.length} {academias.length === 1 ? 'academia' : 'academias'} cadastradas
                  </Text>
                </View>
              </View>
              <TouchableOpacity
                style={[styles.addButton, isMobile && styles.addButtonMobile]}
                onPress={() => router.push('/academias/novo')}
              >
                <Feather name="plus" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>Nova Academia</Text>}
              </TouchableOpacity>
            </View>

            <View style={styles.searchInputContainer}>
              <Feather name="search" size={20} color="#9ca3af" style={styles.searchInputIcon} />
              <TextInput
                style={styles.searchInput}
                placeholder="Buscar por nome, email ou CNPJ..."
                placeholderTextColor="#9ca3af"
                value={searchText}
                onChangeText={setSearchText}
                onSubmitEditing={handleSearch}
                returnKeyType="search"
              />
              {searchText.length > 0 && (
                <TouchableOpacity onPress={handleClearSearch} style={styles.clearButton}>
                  <Feather name="x-circle" size={20} color="#9ca3af" />
                </TouchableOpacity>
              )}
              <TouchableOpacity onPress={handleSearch} style={styles.searchButton}>
                <Text style={styles.searchButtonText}>Buscar</Text>
              </TouchableOpacity>
            </View>
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

      <ConfirmModal
        visible={confirmToggle.visible}
        title={confirmToggle.ativo ? "Ativar Academia" : "Desativar Academia"}
        message={`Deseja ${confirmToggle.ativo ? 'ativar' : 'desativar'} a academia "${confirmToggle.nome}"?`}
        confirmText={confirmToggle.ativo ? "Ativar" : "Desativar"}
        cancelText="Cancelar"
        type={confirmToggle.ativo ? "success" : "warning"}
        onConfirm={confirmToggleAction}
        onCancel={cancelToggle}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
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
  searchButton: {
    backgroundColor: '#f97316',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
    marginLeft: 10,
  },
  searchButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#10b981',
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
    fontSize: 14,
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
  colId: { flex: 0.6, minWidth: 60 },
  colNome: { flex: 2, minWidth: 150 },
  colEmail: { flex: 2.5, minWidth: 180 },
  colTelefone: { flex: 1.5, minWidth: 120 },
  colStatus: { flex: 1, minWidth: 100 },
  colAcoes: { flex: 0.8, minWidth: 80 },
  colAcoesCenter: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTextCenter: {
    textAlign: 'center',
  },
  tableIdText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#f97316',
  },
  cardIdRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  cardId: {
    fontSize: 12,
    fontWeight: '700',
    color: '#f97316',
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
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
  editButton: {
    backgroundColor: '#3b82f6',
    borderRadius: 8,
    padding: 8,
  },
  toggleActiveButton: {
    backgroundColor: '#10b981',
    borderRadius: 8,
    padding: 8,
  },
  toggleInactiveButton: {
    backgroundColor: '#6b7280',
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
