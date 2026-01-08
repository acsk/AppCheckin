import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions, TextInput } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

export default function ModalidadesScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [modalidades, setModalidades] = useState([]);
  const [modalidadesFiltradas, setModalidadesFiltradas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '', ativo: false, acao: '' });
  const [searchText, setSearchText] = useState('');

  useEffect(() => {
    loadModalidades();
  }, []);

  const loadModalidades = async (termo = '') => {
    try {
      setLoading(true);
      const lista = await modalidadeService.listar();
      
      setModalidades(lista);
      
      // Aplicar filtro local se houver termo de busca
      if (termo) {
        filtrarModalidadesLocal(lista, termo);
      } else {
        setModalidadesFiltradas(lista);
      }
    } catch (error) {
      console.error('Erro ao carregar modalidades:', error);
      showError('Não foi possível carregar as modalidades');
    } finally {
      setLoading(false);
    }
  };

  const filtrarModalidadesLocal = (lista, termo) => {
    const termoLower = termo.toLowerCase();
    const filtradas = lista.filter(modalidade => 
      modalidade.nome?.toLowerCase().includes(termoLower) ||
      modalidade.descricao?.toLowerCase().includes(termoLower)
    );
    setModalidadesFiltradas(filtradas);
  };

  const handleSearchChange = (text) => {
    setSearchText(text);
    // Filtro local ao digitar
    if (text.trim()) {
      filtrarModalidadesLocal(modalidades, text.trim());
    } else {
      setModalidadesFiltradas(modalidades);
    }
  };

  const handleSearch = () => {
    // Busca na API ao clicar no botão
    const termo = searchText.trim();
    console.log('🔎 Executando busca com termo:', termo);
    loadModalidades(termo);
  };

  const handleClearSearch = () => {
    console.log('🗑️ Limpando busca');
    setSearchText('');
    loadModalidades('');
  };

  const handleEdit = (id) => {
    router.push(`/modalidades/${id}`);
  };

  const handleToggleStatus = (modalidade) => {
    const acao = modalidade.ativo ? 'desativar' : 'ativar';
    setConfirmDelete({
      visible: true,
      id: modalidade.id,
      nome: modalidade.nome,
      ativo: modalidade.ativo,
      acao: acao
    });
  };

  const confirmDeleteModalidade = async () => {
    try {
      await modalidadeService.excluir(confirmDelete.id);
      const acao = confirmDelete.ativo ? 'desativada' : 'ativada';
      showSuccess(`Modalidade ${acao} com sucesso`);
      setConfirmDelete({ visible: false, id: null, nome: '', ativo: false, acao: '' });
      loadModalidades();
    } catch (error) {
      console.error('Erro ao alterar modalidade:', error);
      showError(error.message || 'Erro ao alterar modalidade');
    }
  };

  const formatarValor = (valor) => {
    if (!valor) return 'R$ 0,00';
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(parseFloat(valor));
  };

  const renderModalidadeCard = (modalidade) => (
    <View key={modalidade.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.modalidadeInfo}>
          <View style={[styles.iconeBadge, { backgroundColor: modalidade.cor || '#f97316' }]}>
            <MaterialCommunityIcons name={modalidade.icone || 'dumbbell'} size={18} color="#fff" />
          </View>
          <View style={styles.modalidadeTexto}>
            <View style={styles.tituloRow}>
              <Text style={styles.nome}>{modalidade.nome}</Text>
              <View style={[styles.statusBadge, { backgroundColor: modalidade.ativo ? '#10b981' : '#94a3b8' }]}>
                <Text style={styles.statusText}>{modalidade.ativo ? 'Ativa' : 'Inativa'}</Text>
              </View>
            </View>
            {modalidade.descricao && (
              <Text style={styles.descricao} numberOfLines={1}>{modalidade.descricao}</Text>
            )}
            {modalidade.planos && modalidade.planos.length > 0 && (
              <View style={styles.planosContainer}>
                <Text style={styles.planosLabel}>
                  {modalidade.planos.length} {modalidade.planos.length === 1 ? 'plano' : 'planos'}:
                </Text>
                <Text style={styles.planosNomes} numberOfLines={1}>
                  {modalidade.planos.map(p => p.nome).join(', ')}
                </Text>
              </View>
            )}
          </View>
        </View>
        <View style={styles.cardActions}>
          <TouchableOpacity
            style={[styles.actionButton, styles.editButton]}
            onPress={() => handleEdit(modalidade.id)}
          >
            <Feather name="edit-2" size={14} color="#fff" />
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.actionButton, modalidade.ativo ? styles.toggleOffButton : styles.toggleOnButton]}
            onPress={() => handleToggleStatus(modalidade)}
          >
            <Feather name={modalidade.ativo ? "toggle-right" : "toggle-left"} size={16} color="#fff" />
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );

  return (
    <LayoutBase showSidebar showHeader>
      <View style={styles.container}>
        <View style={styles.header}>
          <Text style={styles.title}>Modalidades</Text>
          
          {/* Layout Desktop: Busca e Novo na mesma linha */}
          {!isMobile && (
            <View style={styles.headerActionsDesktop}>
              <View style={styles.searchContainer}>
                <TextInput
                  style={styles.searchInput}
                  placeholder="Buscar modalidades..."
                  value={searchText}
                  onChangeText={handleSearchChange}
                  placeholderTextColor="#94a3b8"
                />
                {searchText ? (
                  <TouchableOpacity onPress={handleClearSearch} style={styles.clearButton}>
                    <Feather name="x" size={20} color="#64748b" />
                  </TouchableOpacity>
                ) : null}
                <TouchableOpacity onPress={handleSearch} style={styles.searchButton}>
                  <Feather name="search" size={20} color="#fff" />
                </TouchableOpacity>
              </View>
              <TouchableOpacity
                style={styles.addButton}
                onPress={() => router.push('/modalidades/novo')}
              >
                <Feather name="plus" size={20} color="#fff" />
                <Text style={styles.addButtonText}>Nova Modalidade</Text>
              </TouchableOpacity>
            </View>
          )}

          {/* Layout Mobile: Novo acima da busca */}
          {isMobile && (
            <View style={styles.headerActionsMobile}>
              <TouchableOpacity
                style={styles.addButton}
                onPress={() => router.push('/modalidades/novo')}
              >
                <Feather name="plus" size={20} color="#fff" />
                <Text style={styles.addButtonText}>Nova Modalidade</Text>
              </TouchableOpacity>
              <View style={styles.searchContainer}>
                <TextInput
                  style={styles.searchInput}
                  placeholder="Buscar modalidades..."
                  value={searchText}
                  onChangeText={handleSearchChange}
                  placeholderTextColor="#94a3b8"
                />
                {searchText ? (
                  <TouchableOpacity onPress={handleClearSearch} style={styles.clearButton}>
                    <Feather name="x" size={20} color="#64748b" />
                  </TouchableOpacity>
                ) : null}
                <TouchableOpacity onPress={handleSearch} style={styles.searchButton}>
                  <Feather name="search" size={20} color="#fff" />
                </TouchableOpacity>
              </View>
            </View>
          )}
        </View>

        {loading ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.loadingText}>Carregando modalidades...</Text>
          </View>
        ) : modalidadesFiltradas.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="inbox" size={64} color="#cbd5e1" />
            <Text style={styles.emptyText}>
              {searchText ? 'Nenhuma modalidade encontrada' : 'Nenhuma modalidade cadastrada'}
            </Text>
            {!searchText && (
              <TouchableOpacity
                style={styles.emptyButton}
                onPress={() => router.push('/modalidades/novo')}
              >
                <Feather name="plus" size={20} color="#fff" />
                <Text style={styles.emptyButtonText}>Cadastrar Primeira Modalidade</Text>
              </TouchableOpacity>
            )}
          </View>
        ) : (
          <ScrollView style={styles.list} showsVerticalScrollIndicator={false}>
            {modalidadesFiltradas.map(renderModalidadeCard)}
          </ScrollView>
        )}

        <ConfirmModal
          visible={confirmDelete.visible}
          title={confirmDelete.acao === 'desativar' ? 'Confirmar Desativação' : 'Confirmar Ativação'}
          message={`Deseja realmente ${confirmDelete.acao} a modalidade "${confirmDelete.nome}"?`}
          onConfirm={confirmDeleteModalidade}
          onCancel={() => setConfirmDelete({ visible: false, id: null, nome: '', ativo: false, acao: '' })}
        />
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  header: {
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#1e293b',
    marginBottom: 16,
  },
  headerActionsDesktop: {
    flexDirection: 'row',
    gap: 12,
    alignItems: 'center',
  },
  headerActionsMobile: {
    gap: 12,
  },
  searchContainer: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f1f5f9',
    borderRadius: 8,
    paddingHorizontal: 12,
    height: 44,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: '#1e293b',
    paddingVertical: 8,
  },
  clearButton: {
    padding: 4,
    marginRight: 4,
  },
  searchButton: {
    backgroundColor: '#f97316',
    padding: 8,
    borderRadius: 6,
    marginLeft: 4,
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f97316',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 8,
    gap: 8,
  },
  addButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    color: '#64748b',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  emptyText: {
    marginTop: 16,
    fontSize: 16,
    color: '#64748b',
    textAlign: 'center',
  },
  emptyButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f97316',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
    marginTop: 20,
    gap: 8,
  },
  emptyButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  list: {
    flex: 1,
    padding: 20,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  modalidadeInfo: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  iconeBadge: {
    width: 36,
    height: 36,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalidadeTexto: {
    flex: 1,
  },
  tituloRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  nome: {
    fontSize: 15,
    fontWeight: '600',
    color: '#1e293b',
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#fff',
  },
  descricao: {
    fontSize: 12,
    color: '#64748b',
    marginBottom: 6,
  },
  planosContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 4,
  },
  planosLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: '#64748b',
  },
  planosNomes: {
    flex: 1,
    fontSize: 11,
    color: '#3b82f6',
  },
  cardActions: {
    flexDirection: 'row',
    gap: 6,
  },
  actionButton: {
    width: 30,
    height: 30,
    borderRadius: 6,
    justifyContent: 'center',
    alignItems: 'center',
  },
  editButton: {
    backgroundColor: '#3b82f6',
  },
  toggleOffButton: {
    backgroundColor: '#ef4444',
  },
  toggleOnButton: {
    backgroundColor: '#10b981',
  },
  cardBody: {
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
    paddingTop: 12,
  },
  infoRow: {
    flexDirection: 'row',
    gap: 16,
  },
  infoItem: {
    flex: 1,
    gap: 4,
  },
  infoLabel: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1e293b',
  },
});
