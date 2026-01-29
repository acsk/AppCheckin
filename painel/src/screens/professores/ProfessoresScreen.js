import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, ActivityIndicator, useWindowDimensions, TextInput } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { professorService } from '../../services/professorService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';
import { mascaraTelefone, mascaraCPF } from '../../utils/masks';

export default function ProfessoresScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 960;
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
      const novoStatus = professor.ativo ? 0 : 1;
      await professorService.atualizar(professor.id, {
        ativo: novoStatus
      });
      
      const updated = professores.map(p =>
        p.id === professor.id ? { ...p, ativo: novoStatus } : p
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

  const renderCards = () => (
    <ScrollView className="px-4 pb-4" showsVerticalScrollIndicator={false}>
      {professoresFiltrados.length === 0 ? (
        <View style={styles.emptyContainer}>
          <Feather name="users" size={40} color="#d1d5db" />
          <Text style={styles.emptyText}>Nenhum professor encontrado</Text>
        </View>
      ) : (
        <View className="gap-3">
          {professoresFiltrados.map((professor) => (
            <View key={professor.id} className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
              {/* Header do Card */}
              <View className="flex-row items-center justify-between mb-3">
                <View className="flex-row items-center gap-3 flex-1">
                  <View className="w-12 h-12 rounded-full bg-orange-100 items-center justify-center">
                    <Feather name="user" size={22} color="#f97316" />
                  </View>
                  <View className="flex-1">
                    <Text className="text-[15px] font-bold text-slate-800" numberOfLines={1}>{professor.nome}</Text>
                    <Text className="text-[13px] text-slate-500" numberOfLines={1}>{professor.email || 'Sem email'}</Text>
                  </View>
                </View>
                <View className={`px-2.5 py-1 rounded-full ${professor.ativo ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                  <Text className={`text-[11px] font-bold ${professor.ativo ? 'text-emerald-700' : 'text-rose-700'}`}>
                    {professor.ativo ? 'Ativo' : 'Inativo'}
                  </Text>
                </View>
              </View>

              {/* Info do Card */}
              <View className="flex-row flex-wrap gap-x-4 gap-y-2 mb-4 pt-3 border-t border-slate-100">
                <View className="flex-row items-center gap-2">
                  <Feather name="credit-card" size={14} color="#9ca3af" />
                  <Text className="text-[13px] text-slate-600">{professor.cpf ? mascaraCPF(professor.cpf) : '-'}</Text>
                </View>
                <View className="flex-row items-center gap-2">
                  <Feather name="phone" size={14} color="#9ca3af" />
                  <Text className="text-[13px] text-slate-600">{professor.telefone ? mascaraTelefone(professor.telefone) : '-'}</Text>
                </View>
              </View>

              {/* Ações do Card */}
              <View className="flex-row gap-2 pt-3 border-t border-slate-100">
                <TouchableOpacity
                  className="flex-1 flex-row items-center justify-center gap-2 py-2.5 rounded-lg bg-orange-50 border border-orange-200"
                  onPress={() => router.push(`/professores/${professor.id}`)}
                >
                  <Feather name="edit-2" size={16} color="#f97316" />
                  <Text className="text-[13px] font-semibold text-orange-600">Editar</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  className={`flex-1 flex-row items-center justify-center gap-2 py-2.5 rounded-lg border ${professor.ativo ? 'bg-rose-50 border-rose-200' : 'bg-emerald-50 border-emerald-200'}`}
                  onPress={() => handleToggleStatus(professor)}
                >
                  <Feather 
                    name={professor.ativo ? "toggle-left" : "toggle-right"} 
                    size={16} 
                    color={professor.ativo ? '#ef4444' : '#16a34a'} 
                  />
                  <Text className={`text-[13px] font-semibold ${professor.ativo ? 'text-rose-600' : 'text-emerald-600'}`}>
                    {professor.ativo ? 'Inativar' : 'Ativar'}
                  </Text>
                </TouchableOpacity>
                <TouchableOpacity
                  className="w-11 items-center justify-center rounded-lg bg-slate-50 border border-slate-200"
                  onPress={() => handleDeletar(professor.id, professor.nome)}
                >
                  <Feather name="trash-2" size={16} color="#ef4444" />
                </TouchableOpacity>
              </View>
            </View>
          ))}
        </View>
      )}
    </ScrollView>
  );

  const renderTable = () => (
    <View className="mx-4 my-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      {/* Header da Tabela */}
      <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-3">
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colNome}>NOME</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colEmail}>EMAIL</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colCPF}>CPF</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colTelefone}>TELEFONE</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colStatus}>STATUS</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-right" style={styles.colAcoes}>AÇÕES</Text>
      </View>

      {/* Linhas da Tabela */}
      <ScrollView className="max-h-[520px]" showsVerticalScrollIndicator={true}>
        {professoresFiltrados.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="users" size={40} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhum professor encontrado</Text>
          </View>
        ) : (
          professoresFiltrados.map((professor) => (
            <View key={professor.id} className="flex-row items-center border-b border-slate-100 px-4 py-3">
              <Text className="text-[13px] font-semibold text-slate-800" style={styles.colNome} numberOfLines={2}>{professor.nome}</Text>
              <Text className="text-[13px] text-slate-600" style={styles.colEmail} numberOfLines={1}>{professor.email || '-'}</Text>
              <Text className="text-[13px] text-slate-600" style={styles.colCPF} numberOfLines={1}>{professor.cpf ? mascaraCPF(professor.cpf) : '-'}</Text>
              <Text className="text-[13px] text-slate-600" style={styles.colTelefone} numberOfLines={1}>{professor.telefone ? mascaraTelefone(professor.telefone) : '-'}</Text>
              
              <View style={styles.colStatus}>
                <View className={`self-start rounded-full px-2.5 py-1 ${professor.ativo ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                  <Text className={`text-[11px] font-bold ${professor.ativo ? 'text-emerald-700' : 'text-rose-700'}`}>
                    {professor.ativo ? 'Ativo' : 'Inativo'}
                  </Text>
                </View>
              </View>

              <View style={styles.colAcoes}>
                <View className="flex-row justify-end gap-2">
                  <TouchableOpacity
                    className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                    onPress={() => router.push(`/professores/${professor.id}`)}
                  >
                    <Feather name="edit-2" size={16} color="#f97316" />
                  </TouchableOpacity>
                  <TouchableOpacity
                    className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                    onPress={() => handleToggleStatus(professor)}
                  >
                    <Feather 
                      name={professor.ativo ? "toggle-right" : "toggle-left"} 
                      size={18} 
                      color={professor.ativo ? '#16a34a' : '#ef4444'} 
                    />
                  </TouchableOpacity>
                  <TouchableOpacity
                    className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
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
        {/* Banner Header */}
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="users" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Professores</Text>
                <Text style={styles.bannerSubtitle}>Gerencie todos os professores cadastrados</Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>

          <View style={[styles.searchCard, isMobile && styles.searchCardMobile]}>
            <View style={styles.searchCardHeader}>
              <View style={styles.searchCardInfo}>
                <View style={styles.searchCardIconContainer}>
                  <Feather name="search" size={20} color="#f97316" />
                </View>
                <View>
                  <Text style={styles.searchCardTitle}>Buscar Professores</Text>
                  <Text style={styles.searchCardSubtitle}>
                    {professoresFiltrados.length} {professoresFiltrados.length === 1 ? 'professor encontrado' : 'professores encontrados'}
                  </Text>
                </View>
              </View>
              <TouchableOpacity
                style={[styles.addButton, isMobile && styles.addButtonMobile]}
                onPress={() => router.push('/professores/novo')}
                activeOpacity={0.8}
              >
                <Feather name="plus" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>Novo Professor</Text>}
              </TouchableOpacity>
            </View>

            <View style={styles.searchInputContainer}>
              <Feather name="search" size={20} color="#9ca3af" style={styles.searchInputIcon} />
              <TextInput
                style={styles.searchInput}
                placeholder="Buscar por nome, email, CPF ou telefone..."
                placeholderTextColor="#9ca3af"
                value={searchText}
                onChangeText={handleSearchChange}
              />
              {searchText.length > 0 && (
                <TouchableOpacity onPress={handleClearSearch} style={styles.clearButton}>
                  <Feather name="x-circle" size={20} color="#9ca3af" />
                </TouchableOpacity>
              )}
            </View>
          </View>
        </View>

        {/* Tabela ou Cards */}
        {isMobile ? renderCards() : renderTable()}
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
  bannerContainer: {
    padding: 16,
    gap: 16,
  },
  banner: {
    backgroundColor: '#f97316',
    borderRadius: 16,
    padding: 20,
    overflow: 'hidden',
  },
  bannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
    zIndex: 2,
  },
  bannerIconContainer: {
    width: 54,
    height: 54,
    borderRadius: 14,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconOuter: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconInner: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerTextContainer: {
    flex: 1,
  },
  bannerTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: '#fff',
  },
  bannerSubtitle: {
    marginTop: 4,
    fontSize: 14,
    color: 'rgba(255,255,255,0.9)',
  },
  bannerDecoration: {
    position: 'absolute',
    right: 0,
    top: 0,
    bottom: 0,
    width: 140,
  },
  decorCircle1: {
    position: 'absolute',
    top: -40,
    right: -40,
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  decorCircle2: {
    position: 'absolute',
    bottom: -30,
    right: 10,
    width: 90,
    height: 90,
    borderRadius: 45,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  decorCircle3: {
    position: 'absolute',
    top: 20,
    right: 50,
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: 'rgba(255,255,255,0.15)',
  },
  searchCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 6,
    elevation: 2,
  },
  searchCardMobile: {
    padding: 14,
  },
  searchCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
    gap: 12,
  },
  searchCardInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  searchCardIconContainer: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#fff7ed',
    alignItems: 'center',
    justifyContent: 'center',
  },
  searchCardTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  searchCardSubtitle: {
    marginTop: 2,
    fontSize: 12,
    color: '#6b7280',
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    paddingHorizontal: 12,
    height: 52,
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
