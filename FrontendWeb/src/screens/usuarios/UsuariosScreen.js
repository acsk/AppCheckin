import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, ScrollView, useWindowDimensions } from 'react-native';
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
  const [loading, setLoading] = useState(true);
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });

  useEffect(() => {
    checkUserRole();
    loadUsuarios();
  }, []);

  const checkUserRole = async () => {
    try {
      const user = await authService.getCurrentUser();
      const isSuper = user?.role_id === 3;
      setIsSuperAdmin(isSuper);
    } catch (error) {
      console.error('Erro ao verificar role do usuário:', error);
    }
  };

  const loadUsuarios = async () => {
    try {
      setLoading(true);
      const user = await authService.getCurrentUser();
      const isSuper = user?.role_id === 3;
      
      if (isSuper) {
        const response = await superAdminService.listarTodosUsuarios();
        setUsuarios(response.usuarios || []);
      } else {
        const response = await usuarioService.listar();
        setUsuarios(response.usuarios || []);
      }
    } catch (error) {
      console.error('Erro ao carregar usuários:', error);
      showError('Não foi possível carregar os usuários');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = (usuario) => {
    setConfirmDelete({
      visible: true,
      id: usuario.id,
      nome: usuario.nome
    });
  };

  const confirmDeleteUsuario = async () => {
    try {
      await usuarioService.desativar(confirmDelete.id, isSuperAdmin);
      showSuccess('Usuário desativado com sucesso');
      setConfirmDelete({ visible: false, id: null, nome: '' });
      loadUsuarios();
    } catch (error) {
      showError(error.error || error.message || 'Erro ao desativar usuário');
    }
  };

  const renderMobileCard = (usuario) => (
    <View key={usuario.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <Text style={styles.cardName}>{usuario.nome}</Text>
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
            onPress={() => handleDelete(usuario)}
          >
            <Feather name="trash-2" size={18} color="#ef4444" />
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

        <View style={styles.cardRow}>
          <Feather name="credit-card" size={14} color="#666" />
          <Text style={styles.cardLabel}>Plano:</Text>
          <Text style={styles.cardValue}>{usuario.plano_nome || 'Sem plano'}</Text>
        </View>
      </View>
    </View>
  );

  const renderTable = () => (
    <View style={styles.tableContainer}>
      {/* Header da Tabela */}
      <View style={styles.tableHeader}>
        <Text style={[styles.tableHeaderText, styles.colNome]}>NOME</Text>
        <Text style={[styles.tableHeaderText, styles.colEmail]}>EMAIL</Text>
        {isSuperAdmin && (
          <Text style={[styles.tableHeaderText, styles.colTenant]}>ACADEMIA</Text>
        )}
        <Text style={[styles.tableHeaderText, styles.colPlano]}>PLANO</Text>
        <Text style={[styles.tableHeaderText, styles.colStatus]}>STATUS</Text>
        <Text style={[styles.tableHeaderText, styles.colAcoes]}>AÇÕES</Text>
      </View>

      {/* Linhas da Tabela */}
      <ScrollView style={styles.tableBody} showsVerticalScrollIndicator={true}>
        {usuarios.map((usuario) => (
          <View key={usuario.id} style={styles.tableRow}>
            <Text style={[styles.tableCell, styles.colNome]} numberOfLines={2}>{usuario.nome}</Text>
            <Text style={[styles.tableCell, styles.colEmail]} numberOfLines={1}>{usuario.email}</Text>
            {isSuperAdmin && (
              <Text style={[styles.tableCell, styles.colTenant]} numberOfLines={1}>
                {usuario.tenant?.nome || '-'}
              </Text>
            )}
            <Text style={[styles.tableCell, styles.colPlano]} numberOfLines={1}>
              {usuario.plano_nome || 'Sem plano'}
            </Text>
            <View style={[styles.tableCell, styles.colStatus]}>
              <View style={[
                styles.statusBadge,
                (usuario.status === 'ativo' || usuario.ativo) ? styles.statusAtivo : styles.statusInativo
              ]}>
                <Text style={styles.statusText}>
                  {(usuario.status === 'ativo' || usuario.ativo) ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
            </View>
            <View style={[styles.tableCell, styles.colAcoes]}>
              <View style={styles.actions}>
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={() => router.push(`/usuarios/${usuario.id}`)}
                >
                  <Feather name="edit-2" size={16} color="#3b82f6" />
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={() => handleDelete(usuario)}
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

  return (
    <LayoutBase title="Usuários" subtitle="Gerenciar usuários do sistema">
      <View style={styles.container}>
        {/* Header com botão Novo */}
        <View style={[styles.header, isMobile && styles.headerMobile]}>
          <View>
            <Text style={[styles.headerTitle, isMobile && styles.headerTitleMobile]}>Lista de Usuários</Text>
            <Text style={styles.headerSubtitle}>
              {usuarios.length} {usuarios.length === 1 ? 'usuário cadastrado' : 'usuários cadastrados'}
            </Text>
          </View>
          <TouchableOpacity
            style={[styles.newButton, isMobile && styles.newButtonMobile]}
            onPress={() => router.push('/usuarios/novo')}
          >
            <Feather name="plus" size={20} color="#fff" />
            {!isMobile && <Text style={styles.newButtonText}>Novo Usuário</Text>}
          </TouchableOpacity>
        </View>

        {/* Loading */}
        {loading && (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.loadingText}>Carregando usuários...</Text>
          </View>
        )}

        {/* Lista de Usuários */}
        {!loading && usuarios.length === 0 && (
          <View style={styles.emptyState}>
            <Feather name="users" size={48} color="#ccc" />
            <Text style={styles.emptyText}>Nenhum usuário cadastrado</Text>
            <Text style={styles.emptySubtext}>Clique em "Novo Usuário" para começar</Text>
          </View>
        )}

        {!loading && usuarios.length > 0 && (
          isMobile ? (
            <ScrollView style={styles.cardsContainer} showsVerticalScrollIndicator={false}>
              {usuarios.map(renderMobileCard)}
            </ScrollView>
          ) : (
            renderTable()
          )
        )}
      </View>

      {/* Modal de Confirmação */}
      <ConfirmModal
        visible={confirmDelete.visible}
        title="Desativar Usuário"
        message={`Tem certeza que deseja desativar o usuário "${confirmDelete.nome}"?`}
        onConfirm={confirmDeleteUsuario}
        onCancel={() => setConfirmDelete({ visible: false, id: null, nome: '' })}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
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
    color: '#666',
    marginTop: 4,
  },
  newButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f97316',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
    gap: 8,
  },
  newButtonMobile: {
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 50,
  },
  newButtonText: {
    color: '#fff',
    fontSize: 16,
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
  colTenant: { flex: 1.5, minWidth: 120 },
  colPlano: { flex: 1.5, minWidth: 120 },
  colStatus: { flex: 1, minWidth: 100 },
  colAcoes: { flex: 1, minWidth: 100 },
  statusBadge: {
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
