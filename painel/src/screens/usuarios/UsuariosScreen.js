import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions, TextInput } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import superAdminService from '../../services/superAdminService';
import usuarioService from '../../services/usuarioService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

export default function UsuariosScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [usuarios, setUsuarios] = useState([]);
  const [usuariosFiltrados, setUsuariosFiltrados] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '', status: '', acao: '' });
  const [searchText, setSearchText] = useState('');

  useEffect(() => {
    checkUserRole();
    loadUsuarios();
  }, []);

  const checkUserRole = async () => {
    try {
      const user = await authService.getCurrentUser();
      const isSuper = user?.papel_id === 4;
      setIsSuperAdmin(isSuper);
    } catch (error) {
      console.error('Erro ao verificar role do usuário:', error);
    }
  };

  const loadUsuarios = async (termo = '') => {
    try {
      setLoading(true);
      const user = await authService.getCurrentUser();
      const isSuper = user?.papel_id === 4;
      
      let response;
      if (isSuper) {
        response = await superAdminService.listarTodosUsuarios();
      } else {
        response = await usuarioService.listar();
      }
      
      // Response pode vir como array direto ou objeto com propriedade usuarios
      const lista = Array.isArray(response) ? response : (response.usuarios || []);
      setUsuarios(lista);
      
      // Aplicar filtro local se houver termo de busca
      if (termo) {
        filtrarUsuariosLocal(lista, termo);
      } else {
        setUsuariosFiltrados(lista);
      }
    } catch (error) {
      console.error('Erro ao carregar usuários:', error);
      showError('Não foi possível carregar os usuários');
    } finally {
      setLoading(false);
    }
  };

  const filtrarUsuariosLocal = (lista, termo) => {
    const termoLower = termo.toLowerCase();
    const filtrados = lista.filter(usuario => 
      usuario.nome?.toLowerCase().includes(termoLower) ||
      usuario.email?.toLowerCase().includes(termoLower) ||
      usuario.cpf?.includes(termo) ||
      usuario.telefone?.includes(termo)
    );
    setUsuariosFiltrados(filtrados);
  };

  const handleSearchChange = (text) => {
    setSearchText(text);
    // Filtro local ao digitar
    if (text.trim()) {
      filtrarUsuariosLocal(usuarios, text.trim());
    } else {
      setUsuariosFiltrados(usuarios);
    }
  };

  const handleSearch = () => {
    // Busca na API ao clicar no botão
    const termo = searchText.trim();
    console.log('🔎 Executando busca na API com termo:', termo);
    loadUsuarios(termo);
  };

  const handleClearSearch = () => {
    console.log('🗑️ Limpando busca');
    setSearchText('');
    loadUsuarios('');
  };

  const handleToggleStatus = (usuario) => {
    const acao = (usuario.status === 'ativo' || usuario.ativo) ? 'desativar' : 'ativar';
    setConfirmDelete({
      visible: true,
      id: usuario.id,
      nome: usuario.nome,
      status: usuario.status || (usuario.ativo ? 'ativo' : 'inativo'),
      acao: acao
    });
  };

  const confirmDeleteUsuario = async () => {
    try {
      await usuarioService.desativar(confirmDelete.id, isSuperAdmin);
      const acao = confirmDelete.acao === 'desativar' ? 'desativado' : 'ativado';
      showSuccess(`Usuário ${acao} com sucesso`);
      setConfirmDelete({ visible: false, id: null, nome: '', status: '', acao: '' });
      loadUsuarios();
    } catch (error) {
      showError(error.error || error.message || 'Erro ao alterar status do usuário');
    }
  };

  const getRoleBadge = (roleId) => {
    const roles = {
      1: { label: 'Aluno', color: '#10b981', icon: 'user' },
      2: { label: 'Professor', color: '#3b82f6', icon: 'book' },
      3: { label: 'Admin', color: '#f97316', icon: 'shield' },
      4: { label: 'SuperAdmin', color: '#8b5cf6', icon: 'star' }
    };
    return roles[roleId] || roles[1];
  };

  const renderMobileCard = (usuario) => {
    const roleBadge = getRoleBadge(usuario.papel_id);
    
    return (
    <View key={usuario.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <View style={styles.nameWithRole}>
            <Text style={styles.cardName}>{usuario.nome}</Text>
            <View style={[styles.roleBadge, { backgroundColor: roleBadge.color }]}>
              <Feather name={roleBadge.icon} size={12} color="#fff" />
              <Text style={styles.roleText}>{roleBadge.label}</Text>
            </View>
          </View>
          <View style={[
            styles.statusBadge,
            (usuario.status === 'ativo' || usuario.ativo) ? styles.statusAtivo : styles.statusInativo
          ]}>
            <Text style={styles.statusText}>
              {(usuario.status === 'ativo' || usuario.ativo) ? 'Ativo' : 'Inativo'}
            </Text>
          </View>
        </View>
        <View style={styles.cardActions}>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => router.push(`/usuarios/${usuario.id}`)}
          >
            <Feather name="edit-2" size={18} color="#3b82f6" />
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.cardActionButton}
            onPress={() => handleToggleStatus(usuario)}
          >
            <Feather 
              name={(usuario.status === 'ativo' || usuario.ativo) ? "toggle-right" : "toggle-left"} 
              size={20} 
              color={(usuario.status === 'ativo' || usuario.ativo) ? '#16a34a' : '#ef4444'} 
            />
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.cardBody}>
        <View style={styles.cardRow}>
          <Feather name="mail" size={14} color="#666" />
          <Text style={styles.cardLabel}>Email:</Text>
          <Text style={styles.cardValue}>{usuario.email}</Text>
        </View>

        {isSuperAdmin && (
          <View style={styles.cardRow}>
            <Feather name="home" size={14} color="#666" />
            <Text style={styles.cardLabel}>Academia:</Text>
            <Text style={styles.cardValue}>{usuario.tenant?.nome || '-'}</Text>
          </View>
        )}

      
      </View>
    </View>
    );
  };

  const renderTable = () => (
    <View className="mx-4 my-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      {/* Header da Tabela */}
      <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-3">
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colNome}>NOME</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colEmail}>EMAIL</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colTipo}>TIPO</Text>
        {isSuperAdmin && (
          <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colTenant}>ACADEMIA</Text>
        )}
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={styles.colStatus}>STATUS</Text>
        <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-right" style={styles.colAcoes}>AÇÕES</Text>
      </View>

      {/* Linhas da Tabela */}
      <ScrollView className="max-h-[520px]" showsVerticalScrollIndicator={true}>
        {usuariosFiltrados.map((usuario) => {
          const roleBadge = getRoleBadge(usuario.papel_id);
          
          return (
          <View key={usuario.id} className="flex-row items-center border-b border-slate-100 px-4 py-3">
            <Text className="text-[13px] font-semibold text-slate-800" style={styles.colNome} numberOfLines={2}>{usuario.nome}</Text>
            <Text className="text-[13px] text-slate-600" style={styles.colEmail} numberOfLines={1}>{usuario.email}</Text>
            <View style={styles.colTipo}>
              <View style={[styles.roleBadge, { backgroundColor: roleBadge.color }]}>
                <Feather name={roleBadge.icon} size={10} color="#fff" />
                <Text style={styles.roleText}>{roleBadge.label}</Text>
              </View>
            </View>
            {isSuperAdmin && (
              <Text className="text-[13px] text-slate-600" style={styles.colTenant} numberOfLines={1}>
                {usuario.tenant?.nome || '-'}
              </Text>
            )}
   
            <View style={styles.colStatus}>
              <View className={`self-start rounded-full px-2.5 py-1 ${(usuario.status === 'ativo' || usuario.ativo) ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                <Text className={`text-[11px] font-bold ${(usuario.status === 'ativo' || usuario.ativo) ? 'text-emerald-700' : 'text-rose-700'}`}>
                  {(usuario.status === 'ativo' || usuario.ativo) ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
            </View>
            <View className="flex-row justify-end gap-2" style={styles.colAcoes}>
              <TouchableOpacity
                className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                onPress={() => router.push(`/usuarios/${usuario.id}`)}
              >
                <Feather name="edit-2" size={16} color="#f97316" />
              </TouchableOpacity>
              <TouchableOpacity
                className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                onPress={() => handleToggleStatus(usuario)}
              >
                <Feather 
                  name={(usuario.status === 'ativo' || usuario.ativo) ? "toggle-right" : "toggle-left"} 
                  size={18} 
                  color={(usuario.status === 'ativo' || usuario.ativo) ? '#16a34a' : '#ef4444'} 
                />
              </TouchableOpacity>
            </View>
          </View>
          );
        })}
      </ScrollView>
    </View>
  );

  return (
    <LayoutBase title="Usuários" subtitle="Gerenciar usuários do sistema">
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
                <Text style={styles.bannerTitle}>Usuários</Text>
                <Text style={styles.bannerSubtitle}>Gerencie todos os usuários do sistema</Text>
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
                  <Text style={styles.searchCardTitle}>Buscar Usuários</Text>
                  <Text style={styles.searchCardSubtitle}>
                    {usuariosFiltrados.length} {usuariosFiltrados.length === 1 ? 'usuário encontrado' : 'usuários encontrados'}
                  </Text>
                </View>
              </View>
              <TouchableOpacity
                style={[styles.addButton, isMobile && styles.addButtonMobile]}
                onPress={() => router.push('/usuarios/novo')}
              >
                <Feather name="plus" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>Novo Usuário</Text>}
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

        {/* Loading */}
        {loading && (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.loadingText}>Carregando usuários...</Text>
          </View>
        )}

        {/* Lista de Usuários */}
        {!loading && usuariosFiltrados.length === 0 && (
          <View style={styles.emptyState}>
            <Feather name="users" size={48} color="#ccc" />
            <Text style={styles.emptyText}>
              {searchText ? 'Nenhum usuário encontrado' : 'Nenhum usuário cadastrado'}
            </Text>
            <Text style={styles.emptySubtext}>
              {searchText ? 'Tente buscar com outros termos' : 'Clique em "Novo Usuário" para começar'}
            </Text>
          </View>
        )}

        {!loading && usuariosFiltrados.length > 0 && (
          isMobile ? (
            <ScrollView style={styles.cardsContainer} showsVerticalScrollIndicator={false}>
              {usuariosFiltrados.map(renderMobileCard)}
            </ScrollView>
          ) : (
            renderTable()
          )
        )}
      </View>

      {/* Modal de Confirmação */}
      <ConfirmModal
        visible={confirmDelete.visible}
        title={confirmDelete.acao === 'desativar' ? 'Confirmar Desativação' : 'Confirmar Ativação'}
        message={`Deseja realmente ${confirmDelete.acao} o usuário "${confirmDelete.nome}"?`}
        onConfirm={confirmDeleteUsuario}
        onCancel={() => setConfirmDelete({ visible: false, id: null, nome: '', status: '', acao: '' })}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
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
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666',
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
  nameWithRole: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flexWrap: 'wrap',
  },
  cardName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
  },
  roleBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  roleText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#fff',
    textAlign: 'center',
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
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f8f9fa',
    padding: 16,
    borderBottomWidth: 2,
    borderBottomColor: '#e5e5e5',
  },
  tableHeaderText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#666',
    textTransform: 'uppercase',
  },
  tableBody: {
    flex: 1,
  },
  tableRow: {
    flexDirection: 'row',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  tableCell: {
    fontSize: 14,
    color: '#333',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  colNome: { flex: 2, minWidth: 150 },
  colEmail: { flex: 2.5, minWidth: 180 },
  colTipo: { flex: 1.2, minWidth: 110 },
  colTenant: { flex: 1.5, minWidth: 120 },
  colStatus: { flex: 1, minWidth: 100 },
  colAcoes: { flex: 1, minWidth: 100 },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 4,
    paddingHorizontal: 12,
    borderRadius: 12,
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
    textAlign: 'center',
  },
  actions: {
    flexDirection: 'row',
    gap: 12,
  },
  actionButton: {
    padding: 8,
  },
  emptyState: {
    padding: 60,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#999',
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#999',
    marginTop: 8,
  },
});
