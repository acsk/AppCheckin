import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
  useWindowDimensions,
  FlatList,
  RefreshControl,
  TextInput,
  Modal,
} from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { listarWods, deletarWod, criarWod, atualizarWod } from '../../services/wodService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

export default function WodsScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [wods, setWods] = useState([]);
  const [wodsFiltradas, setWodsFiltradas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchText, setSearchText] = useState('');
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, titulo: '' });
  const [filtroStatus, setFiltroStatus] = useState('todos');
  
  // Estados para modal de criar/editar WOD
  const [modalFormVisible, setModalFormVisible] = useState(false);
  const [editandoWod, setEditandoWod] = useState(null);
  const [formSubmitting, setFormSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    titulo: '',
    descricao: '',
  });

  useEffect(() => {
    carregarWods();
  }, []);

  const carregarWods = async () => {
    try {
      setLoading(true);
      const response = await listarWods();

      if (response.type === 'success') {
        setWods(response.data || []);
        setWodsFiltradas(response.data || []);
      } else {
        showError('Erro ao carregar WODs');
      }
    } catch (error) {
      console.error('Erro ao carregar WODs:', error);
      showError('Erro ao carregar WODs');
    } finally {
      setLoading(false);
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await carregarWods();
    setRefreshing(false);
  };

  const handleSearchChange = (termo) => {
    setSearchText(termo);
    filtrarWods(termo, filtroStatus);
  };

  const filtrarWods = (termo, status) => {
    let resultado = wods;

    if (status !== 'todos') {
      resultado = resultado.filter((wod) => wod.status === status);
    }

    if (termo.trim() !== '') {
      const termoBaixo = termo.toLowerCase();
      resultado = resultado.filter(
        (wod) =>
          wod.titulo?.toLowerCase().includes(termoBaixo) ||
          wod.descricao?.toLowerCase().includes(termoBaixo)
      );
    }

    setWodsFiltradas(resultado);
  };

  const handleFiltroStatus = (novoStatus) => {
    setFiltroStatus(novoStatus);
    filtrarWods(searchText, novoStatus);
  };

  const handleDelete = async () => {
    try {
      const response = await deletarWod(confirmDelete.id);

      if (response.type === 'success') {
        showSuccess('WOD deletado com sucesso');
        carregarWods();
      } else {
        showError(response.message || 'Erro ao deletar WOD');
      }
    } catch (error) {
      console.error('Erro ao deletar WOD:', error);
      showError('Erro ao deletar WOD');
    } finally {
      setConfirmDelete({ visible: false, id: null, titulo: '' });
    }
  };

  const handleAbrirFormCriar = () => {
    router.push('/wods/criar');
  };

  const handleAbrirFormEditar = (wod) => {
    router.push(`/wods/criar?id=${wod.id}`);
  };

  const handleDuplicarWod = (wod) => {
    // Navegar para criar com o ID do WOD a duplicar
    router.push(`/wods/criar?duplicate=${wod.id}`);
  };

  const handleSalvarWod = async () => {
    if (!formData.titulo.trim()) {
      showError('Título é obrigatório');
      return;
    }

    try {
      setFormSubmitting(true);
      let response;

      if (editandoWod) {
        response = await atualizarWod(editandoWod.id, formData);
      } else {
        response = await criarWod(formData);
      }

      if (response.type === 'success') {
        showSuccess(editandoWod ? 'WOD atualizado com sucesso' : 'WOD criado com sucesso');
        setModalFormVisible(false);
        carregarWods();
      } else {
        showError(response.message || 'Erro ao salvar WOD');
      }
    } catch (error) {
      console.error('Erro ao salvar WOD:', error);
      showError('Erro ao salvar WOD');
    } finally {
      setFormSubmitting(false);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'published':
        return '#10b981';
      case 'draft':
        return '#f59e0b';
      case 'archived':
        return '#6b7280';
      default:
        return '#3b82f6';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'published':
        return 'Publicado';
      case 'draft':
        return 'Rascunho';
      case 'archived':
        return 'Arquivado';
      default:
        return status;
    }
  };

  const renderWod = ({ item }) => (
    <View style={styles.tableRow}>
      <View style={styles.idCell}>
        <Text style={styles.idText}>{item.id}</Text>
      </View>

      <View style={styles.tituloCell}>
        <Text style={styles.tituloText}>{item.titulo}</Text>
      </View>

      <View style={styles.descricaoCell}>
        <Text style={styles.descricaoText} numberOfLines={1}>
          {item.descricao || '-'}
        </Text>
      </View>

      <View style={styles.statusCell}>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) + '20' }]}>
          <Text style={[styles.statusText, { color: getStatusColor(item.status) }]}>
            {getStatusLabel(item.status)}
          </Text>
        </View>
      </View>

      <View style={styles.acoesCell}>
        <TouchableOpacity
          style={[styles.actionIconButton]}
          onPress={() => {
            if (item.status === 'draft') {
              handleAbrirFormEditar(item);
            } else {
              router.push(`/wods/detalhes/${item.id}`);
            }
          }}
        >
          <Feather
            name={item.status === 'draft' ? 'edit-2' : 'eye'}
            size={16}
            color="#3b82f6"
          />
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionIconButton]}
          onPress={() => handleDuplicarWod(item)}
        >
          <Feather name="copy" size={16} color="#8b5cf6" />
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionIconButton]}
          onPress={() =>
            setConfirmDelete({ visible: true, id: item.id, titulo: item.titulo })
          }
        >
          <Feather name="trash-2" size={16} color="#ef4444" />
        </TouchableOpacity>
      </View>
    </View>
  );

  if (loading && wodsFiltradas.length === 0) {
    return (
      <LayoutBase title="WODs">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="WODs">
      <ScrollView
        style={styles.container}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        {/* Banner */}
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <MaterialCommunityIcons name="dumbbell" size={24} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>WODs</Text>
                <Text style={styles.bannerSubtitle}>Gerencie todos os WODs do seu negócio</Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>
        </View>

        {/* Search Card */}
        <View style={[styles.searchCard, isMobile && styles.searchCardMobile]}>
          <View style={styles.searchCardHeader}>
            <View style={styles.searchCardInfo}>
              <View style={styles.searchCardIconContainer}>
                <Feather name="search" size={20} color="#f97316" />
              </View>
              <View>
                <Text style={styles.searchCardTitle}>Buscar WODs</Text>
                <Text style={styles.searchCardSubtitle}>{wodsFiltradas.length} resultado(s)</Text>
              </View>
            </View>
            <TouchableOpacity
              style={styles.addButton}
              onPress={handleAbrirFormCriar}
            >
              <Feather name="plus" size={18} color="#fff" />
              <Text style={styles.addButtonText}>Novo WOD</Text>
            </TouchableOpacity>
          </View>

          {/* Search Input */}
          <View style={styles.searchInputContainer}>
            <Feather name="search" size={18} color="#9ca3af" style={styles.searchInputIcon} />
            <TextInput
              style={styles.searchInput}
              placeholder="Buscar por título ou descrição..."
              placeholderTextColor="#9ca3af"
              value={searchText}
              onChangeText={handleSearchChange}
            />
            {searchText !== '' && (
              <TouchableOpacity
                style={styles.clearButton}
                onPress={() => handleSearchChange('')}
              >
                <Feather name="x" size={18} color="#9ca3af" />
              </TouchableOpacity>
            )}
          </View>

          {/* Filtros */}
          <View style={styles.filterSection}>
            <Text style={styles.filterLabel}>Filtrar por Status</Text>
            <View style={styles.filterButtons}>
              {['todos', 'published', 'draft', 'archived'].map((status) => (
                <TouchableOpacity
                  key={status}
                  style={[
                    styles.filterBtn,
                    filtroStatus === status && styles.filterBtnActive,
                  ]}
                  onPress={() => handleFiltroStatus(status)}
                >
                  <Text
                    style={[
                      styles.filterBtnText,
                      filtroStatus === status && styles.filterBtnTextActive,
                    ]}
                  >
                    {status === 'todos' ? 'Todos' : getStatusLabel(status)}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>
        </View>

        {/* Tabela */}
        {wodsFiltradas.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="inbox" size={48} color="#9ca3af" />
            <Text style={styles.emptyText}>Nenhum WOD encontrado</Text>
          </View>
        ) : (
          <View style={styles.tableContainer}>
            <View style={styles.tableHeader}>
              <View style={styles.idCell}>
                <Text style={styles.tableHeaderText}>ID</Text>
              </View>
              <View style={styles.tituloCell}>
                <Text style={styles.tableHeaderText}>Título</Text>
              </View>
              <View style={styles.descricaoCell}>
                <Text style={styles.tableHeaderText}>Descrição</Text>
              </View>
              <View style={styles.statusCell}>
                <Text style={styles.tableHeaderText}>Status</Text>
              </View>
              <View style={styles.acoesCell}>
                <Text style={styles.tableHeaderText}>Ações</Text>
              </View>
            </View>

            <FlatList
              data={wodsFiltradas}
              renderItem={renderWod}
              keyExtractor={(item) => item.id.toString()}
              scrollEnabled={false}
              ItemSeparatorComponent={() => <View style={{ height: 0 }} />}
            />
          </View>
        )}
      </ScrollView>

      {/* Form Modal for Create/Edit WOD */}
      <Modal
        visible={modalFormVisible}
        animationType="fade"
        transparent={true}
        onRequestClose={() => setModalFormVisible(false)}
      >
        <View style={{ flex: 1, backgroundColor: 'rgba(0, 0, 0, 0.5)', justifyContent: 'center', alignItems: 'center' }}>
          <View
            style={{
              backgroundColor: '#fff',
              borderRadius: 16,
              padding: 20,
              maxHeight: '80%',
              width: '90%',
            }}
          >
            {/* Header */}
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
              <Text style={{ fontSize: 18, fontWeight: 'bold', color: '#1f2937' }}>
                {editandoWod ? 'Editar WOD' : 'Novo WOD'}
              </Text>
              <TouchableOpacity
                onPress={() => setModalFormVisible(false)}
                disabled={formSubmitting}
              >
                <Feather name="x" size={24} color="#6b7280" />
              </TouchableOpacity>
            </View>

            {/* Form Fields */}
            <ScrollView showsVerticalScrollIndicator={false} style={{ marginBottom: 20 }}>
              {/* Título */}
              <View style={{ marginBottom: 16 }}>
                <Text style={{ fontSize: 14, fontWeight: '500', color: '#374151', marginBottom: 8 }}>
                  Título <Text style={{ color: '#ef4444' }}>*</Text>
                </Text>
                <TextInput
                  style={{
                    borderWidth: 1,
                    borderColor: '#d1d5db',
                    borderRadius: 8,
                    padding: 12,
                    fontSize: 14,
                    color: '#1f2937',
                    backgroundColor: '#f9fafb',
                  }}
                  placeholder="Digite o título do WOD"
                  placeholderTextColor="#9ca3af"
                  value={formData.titulo}
                  onChangeText={(text) => setFormData({ ...formData, titulo: text })}
                  editable={!formSubmitting}
                />
              </View>

              {/* Descrição */}
              <View style={{ marginBottom: 16 }}>
                <Text style={{ fontSize: 14, fontWeight: '500', color: '#374151', marginBottom: 8 }}>
                  Descrição
                </Text>
                <TextInput
                  style={{
                    borderWidth: 1,
                    borderColor: '#d1d5db',
                    borderRadius: 8,
                    padding: 12,
                    fontSize: 14,
                    color: '#1f2937',
                    backgroundColor: '#f9fafb',
                    minHeight: 100,
                    textAlignVertical: 'top',
                  }}
                  placeholder="Digite a descrição do WOD (opcional)"
                  placeholderTextColor="#9ca3af"
                  value={formData.descricao}
                  onChangeText={(text) => setFormData({ ...formData, descricao: text })}
                  multiline={true}
                  numberOfLines={4}
                  editable={!formSubmitting}
                />
              </View>
            </ScrollView>

            {/* Buttons */}
            <View style={{ flexDirection: 'row', gap: 12, justifyContent: 'flex-end' }}>
              <TouchableOpacity
                style={{
                  paddingHorizontal: 16,
                  paddingVertical: 10,
                  borderRadius: 8,
                  borderWidth: 1,
                  borderColor: '#d1d5db',
                  backgroundColor: '#f3f4f6',
                  minWidth: 100,
                  alignItems: 'center',
                }}
                onPress={() => setModalFormVisible(false)}
                disabled={formSubmitting}
              >
                <Text style={{ color: '#374151', fontWeight: '500', fontSize: 14 }}>
                  Cancelar
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={{
                  paddingHorizontal: 16,
                  paddingVertical: 10,
                  borderRadius: 8,
                  backgroundColor: '#f97316',
                  minWidth: 100,
                  alignItems: 'center',
                  opacity: formSubmitting || !formData.titulo.trim() ? 0.6 : 1,
                }}
                onPress={handleSalvarWod}
                disabled={formSubmitting || !formData.titulo.trim()}
              >
                {formSubmitting ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <Text style={{ color: '#fff', fontWeight: '500', fontSize: 14 }}>
                    Salvar
                  </Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Confirm Delete Modal */}
      <ConfirmModal
        visible={confirmDelete.visible}
        title="Deletar WOD"
        message={`Tem certeza que deseja deletar o WOD "${confirmDelete.titulo}"?`}
        onConfirm={handleDelete}
        onCancel={() => setConfirmDelete({ visible: false, id: null, titulo: '' })}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },

  // Banner
  bannerContainer: {
    backgroundColor: '#f9fafb',
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
    color: '#4b5563',
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
    marginBottom: 16,
  },
  searchInputIcon: {
    marginRight: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 16,
    color: '#4b5563',
    outlineStyle: 'none',
    height: '100%',
  },
  clearButton: {
    padding: 6,
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
  addButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },

  // Filtros
  filterSection: {
    marginBottom: 0,
  },
  filterLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
    marginBottom: 8,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  filterButtons: {
    flexDirection: 'row',
    gap: 8,
    flexWrap: 'wrap',
  },
  filterBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    backgroundColor: '#f3f4f6',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  filterBtnActive: {
    backgroundColor: '#fed7aa',
    borderColor: '#f97316',
  },
  filterBtnText: {
    fontSize: 12,
    fontWeight: '500',
    color: '#6b7280',
  },
  filterBtnTextActive: {
    color: '#9a3412',
  },

  // Tabela
  tableContainer: {
    margin: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f9fafb',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 2,
    borderBottomColor: '#e5e5e5',
  },
  tableRow: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
    backgroundColor: '#fff',
  },
  tableHeaderText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  idCell: {
    flex: 0.3,
  },
  idText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#9ca3af',
  },
  tituloCell: {
    flex: 1.5,
  },
  tituloText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#4b5563',
  },
  descricaoCell: {
    flex: 2,
  },
  descricaoText: {
    fontSize: 13,
    color: '#6b7280',
  },
  statusCell: {
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 20,
    alignSelf: 'flex-start',
  },
  statusText: {
    fontSize: 11,
    fontWeight: '700',
  },
  acoesCell: {
    flex: 0.8,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'flex-end',
  },
  actionIconButton: {
    padding: 8,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    minWidth: 40,
    minHeight: 40,
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },

  // Empty State
  emptyContainer: {
    padding: 80,
    alignItems: 'center',
    backgroundColor: '#fff',
    margin: 20,
    borderRadius: 12,
  },
  emptyText: {
    fontSize: 15,
    color: '#9ca3af',
    marginTop: 16,
    textAlign: 'center',
  },
});
