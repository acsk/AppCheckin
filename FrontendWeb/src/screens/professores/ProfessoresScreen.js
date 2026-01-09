import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, ActivityIndicator, useWindowDimensions, TextInput } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { professorService } from '../../services/professorService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

export default function ProfessoresScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [professores, setProfessores] = useState([]);
  const [professoresFiltrados, setProfessoresFiltrados] = useState([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });
  const [searchText, setSearchText] = useState('');

  useEffect(() => {
    carregarProfessores();
  }, []);

  const carregarProfessores = async () => {
    try {
      setLoading(true);
      const data = await professorService.listar();
      setProfessores(data);
      setProfessoresFiltrados(data);
    } catch (error) {
      console.error('Erro ao carregar professores:', error);
      showError('Não foi possível carregar os professores');
    } finally {
      setLoading(false);
    }
  };

  const filtrarProfessoresLocal = (lista, termo) => {
    const termoLower = termo.toLowerCase();
    const filtrados = lista.filter(prof => 
      prof.nome?.toLowerCase().includes(termoLower) ||
      prof.email?.toLowerCase().includes(termoLower) ||
      prof.cpf?.includes(termo) ||
      prof.telefone?.includes(termo)
    );
    setProfessoresFiltrados(filtrados);
  };

  const handleSearchChange = (text) => {
    setSearchText(text);
    if (text.trim()) {
      filtrarProfessoresLocal(professores, text.trim());
    } else {
      setProfessoresFiltrados(professores);
    }
  };

  const handleClearSearch = () => {
    setSearchText('');
    setProfessoresFiltrados(professores);
  };

  const handleDeletar = (id, nome) => {
    setConfirmDelete({ visible: true, id, nome });
  };

  const confirmarDelete = async () => {
    try {
      await professorService.deletar(confirmDelete.id);
      setProfessores(professores.filter(p => p.id !== confirmDelete.id));
      setProfessoresFiltrados(professoresFiltrados.filter(p => p.id !== confirmDelete.id));
      showSuccess('Professor deletado com sucesso');
      setConfirmDelete({ visible: false, id: null, nome: '' });
    } catch (error) {
      console.error('Erro ao deletar:', error);
      showError('Erro ao deletar professor');
    }
  };

  const handleToggleStatus = async (professor) => {
    try {
      await professorService.atualizar(professor.id, {
        ativo: !professor.ativo
      });
      
      const updated = professores.map(p =>
        p.id === professor.id ? { ...p, ativo: !p.ativo } : p
      );
      setProfessores(updated);
      setProfessoresFiltrados(updated.filter(p => {
        const termo = searchText.toLowerCase();
        return (
          p.nome?.toLowerCase().includes(termo) ||
          p.email?.toLowerCase().includes(termo) ||
          p.cpf?.includes(searchText) ||
          p.telefone?.includes(searchText)
        );
      }));
      
      showSuccess(professor.ativo ? 'Professor inativado' : 'Professor ativado');
    } catch (error) {
      console.error('Erro ao atualizar status:', error);
      showError('Erro ao atualizar status');
    }
  };

  const renderTable = () => (
    <View style={styles.tableContainer}>
      {/* Header da Tabela */}
      <View style={styles.tableHeader}>
        <Text style={[styles.tableHeaderText, styles.colNome]}>NOME</Text>
        <Text style={[styles.tableHeaderText, styles.colEmail]}>EMAIL</Text>
        <Text style={[styles.tableHeaderText, styles.colCPF]}>CPF</Text>
        <Text style={[styles.tableHeaderText, styles.colTelefone]}>TELEFONE</Text>
        <Text style={[styles.tableHeaderText, styles.colStatus]}>STATUS</Text>
        <Text style={[styles.tableHeaderText, styles.colAcoes]}>AÇÕES</Text>
      </View>

      {/* Linhas da Tabela */}
      <ScrollView style={styles.tableBody} showsVerticalScrollIndicator={true}>
        {professoresFiltrados.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="users" size={40} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhum professor encontrado</Text>
          </View>
        ) : (
          professoresFiltrados.map((professor) => (
            <View key={professor.id} style={styles.tableRow}>
              <Text style={[styles.tableCell, styles.colNome]} numberOfLines={2}>{professor.nome}</Text>
              <Text style={[styles.tableCell, styles.colEmail]} numberOfLines={1}>{professor.email || '-'}</Text>
              <Text style={[styles.tableCell, styles.colCPF]} numberOfLines={1}>{professor.cpf || '-'}</Text>
              <Text style={[styles.tableCell, styles.colTelefone]} numberOfLines={1}>{professor.telefone || '-'}</Text>
              
              <View style={[styles.tableCell, styles.colStatus]}>
                <View style={[
                  styles.statusBadge,
                  professor.ativo ? styles.statusAtivo : styles.statusInativo
                ]}>
                  <Text style={styles.statusText}>
                    {professor.ativo ? 'Ativo' : 'Inativo'}
                  </Text>
                </View>
              </View>

              <View style={[styles.tableCell, styles.colAcoes]}>
                <View style={styles.actions}>
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => router.push(`/professores/${professor.id}`)}
                  >
                    <Feather name="edit-2" size={16} color="#3b82f6" />
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => handleToggleStatus(professor)}
                  >
                    <Feather 
                      name={professor.ativo ? "toggle-right" : "toggle-left"} 
                      size={18} 
                      color={professor.ativo ? '#16a34a' : '#ef4444'} 
                    />
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => handleDeletar(professor.id, professor.nome)}
                  >
                    <Feather name="trash-2" size={16} color="#ef4444" />
                  </TouchableOpacity>
                </View>
              </View>
            </View>
          ))
        )}
      </ScrollView>
    </View>
  );

  if (loading) {
    return (
      <LayoutBase title="Professores" subtitle="Gerenciar professores">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#3b82f6" />
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Professores" subtitle="Gerenciar professores">
      <View style={styles.container}>
        {/* Header */}
        <View style={[styles.header, isMobile && styles.headerMobile]}>
          <View style={styles.headerLeft}>
            <Text style={[styles.headerTitle, isMobile && styles.headerTitleMobile]}>Professores</Text>
            <Text style={styles.headerSubtitle}>
              {professoresFiltrados.length} {professoresFiltrados.length === 1 ? 'professor encontrado' : 'professores encontrados'}
            </Text>
          </View>
          
          {!isMobile && (
            <View style={styles.searchContainer}>
              <View style={styles.searchInputContainer}>
                <Feather name="search" size={20} color="#999" style={styles.searchIcon} />
                <TextInput
                  style={styles.searchInput}
                  placeholder="Buscar por nome, email, CPF ou telefone..."
                  placeholderTextColor="#999"
                  value={searchText}
                  onChangeText={handleSearchChange}
                />
                {searchText.length > 0 && (
                  <TouchableOpacity onPress={handleClearSearch} style={styles.clearButton}>
                    <Feather name="x" size={18} color="#999" />
                  </TouchableOpacity>
                )}
              </View>
            </View>
          )}

          <TouchableOpacity
            style={styles.botaoNovo}
            onPress={() => router.push('/professores/novo')}
          >
            <Feather name="plus" size={20} color="#fff" />
            <Text style={styles.botaoNovoText}>Novo</Text>
          </TouchableOpacity>
        </View>

        {/* Tabela */}
        {renderTable()}
      </View>

      <ConfirmModal
        visible={confirmDelete.visible}
        title="Deletar Professor"
        message={`Tem certeza que deseja deletar "${confirmDelete.nome}"?`}
        confirmText="Deletar"
        cancelText="Cancelar"
        onConfirm={confirmarDelete}
        onCancel={() => setConfirmDelete({ visible: false, id: null, nome: '' })}
        isDangerous={true}
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
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 16,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
    gap: 16,
  },
  headerMobile: {
    flexDirection: 'column',
    alignItems: 'flex-start',
  },
  headerLeft: {
    flex: 1,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: '#111827',
  },
  headerTitleMobile: {
    fontSize: 20,
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  searchContainer: {
    flex: 1,
    maxWidth: 400,
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 12,
    backgroundColor: '#fff',
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 8,
    fontSize: 14,
    color: '#111827',
  },
  clearButton: {
    padding: 4,
  },
  botaoNovo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#3b82f6',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 8,
  },
  botaoNovoText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 14,
  },
  tableContainer: {
    flex: 1,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f3f4f6',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  tableHeaderText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#374151',
  },
  colNome: {
    flex: 2,
  },
  colEmail: {
    flex: 2,
  },
  colCPF: {
    flex: 1.5,
  },
  colTelefone: {
    flex: 1.5,
  },
  colStatus: {
    flex: 1,
  },
  colAcoes: {
    flex: 1.2,
  },
  tableBody: {
    flex: 1,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    backgroundColor: '#fff',
  },
  tableCell: {
    fontSize: 14,
    color: '#111827',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 100,
  },
  emptyText: {
    fontSize: 16,
    color: '#6b7280',
    marginTop: 12,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
    alignSelf: 'flex-start',
  },
  statusAtivo: {
    backgroundColor: '#dcfce7',
  },
  statusInativo: {
    backgroundColor: '#fee2e2',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#15803d',
  },
  actions: {
    flexDirection: 'row',
    gap: 8,
  },
  actionButton: {
    padding: 6,
    borderRadius: 6,
    backgroundColor: '#f3f4f6',
  },
});
