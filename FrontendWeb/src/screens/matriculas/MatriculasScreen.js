import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  ActivityIndicator,
  useWindowDimensions,
  Alert,
  Platform,
  ToastAndroid,
  TextInput,
} from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { matriculaService } from '../../services/matriculaService';
import { StyleSheet } from 'react-native';

export default function MatriculasScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const [matriculas, setMatriculas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('ativa');

  const isMobile = width < 768;

  // Função de filtro
  const filteredMatriculas = matriculas.filter(matricula => {
    const matchSearch = searchTerm === '' || 
      matricula.usuario_nome?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      matricula.usuario_email?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      matricula.plano_nome?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      matricula.modalidade_nome?.toLowerCase().includes(searchTerm.toLowerCase());
    
    const matchStatus = statusFilter === 'todos' || matricula.status === statusFilter;
    
    return matchSearch && matchStatus;
  });

  useEffect(() => {
    carregarMatriculas();
  }, []);

  const carregarMatriculas = async () => {
    setLoading(true);
    try {
      const data = await matriculaService.listar();
      const lista = Array.isArray(data?.matriculas) 
        ? data.matriculas 
        : Array.isArray(data) 
        ? data 
        : [];
      setMatriculas(lista);
    } catch (error) {
      showAlert('Erro', 'Não foi possível carregar as matrículas');
    } finally {
      setLoading(false);
    }
  };

  const handleCancelar = async (matricula) => {
    Alert.alert(
      'Cancelar Matrícula',
      `Deseja realmente cancelar a matrícula de ${matricula.usuario_nome} no plano ${matricula.plano_nome} (${matricula.modalidade_nome})?`,
      [
        { text: 'Não', style: 'cancel' },
        {
          text: 'Sim, Cancelar',
          style: 'destructive',
          onPress: async () => {
            try {
              console.log('Cancelando matrícula ID:', matricula.id);
              const resultado = await matriculaService.cancelar(matricula.id);
              console.log('Resultado cancelamento:', resultado);
              showToast('Matrícula cancelada com sucesso');
              await carregarMatriculas();
            } catch (error) {
              console.error('Erro ao cancelar:', error);
              showAlert('Erro', error.message || error.error || 'Não foi possível cancelar a matrícula');
            }
          },
        },
      ]
    );
  };

  const showAlert = (title, message) => {
    Alert.alert(title, message);
  };

  const showToast = (message) => {
    if (Platform.OS === 'android') {
      ToastAndroid.show(message, ToastAndroid.SHORT);
    } else {
      Alert.alert('', message);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'ativa':
        return '#10b981';
      case 'vencida':
        return '#f59e0b';
      case 'cancelada':
        return '#ef4444';
      case 'finalizada':
        return '#6b7280';
      default:
        return '#6b7280';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'ativa':
        return 'Ativa';
      case 'vencida':
        return 'Vencida';
      case 'cancelada':
        return 'Cancelada';
      case 'finalizada':
        return 'Finalizada';
      default:
        return status;
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Evita problema de timezone criando a data diretamente com os valores
    const [year, month, day] = dateString.split('-');
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('pt-BR');
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value || 0);
  };

  const renderMobileCard = (matricula) => (
    <View key={matricula.id} style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.cardTitleRow}>
          {matricula.modalidade_icone && (
            <View
              style={[
                styles.cardIconBadge,
                { backgroundColor: matricula.modalidade_cor || '#3b82f6' },
              ]}
            >
              <MaterialCommunityIcons
                name={matricula.modalidade_icone}
                size={20}
                color="#fff"
              />
            </View>
          )}
          <View style={styles.cardTitleContent}>
            <Text style={styles.cardName}>{matricula.usuario_nome}</Text>
            <Text style={styles.cardEmail}>{matricula.usuario_email}</Text>
          </View>
        </View>
        <View
          style={[
            styles.statusBadge,
            { backgroundColor: getStatusColor(matricula.status) },
          ]}
        >
          <Text style={styles.statusText}>{getStatusLabel(matricula.status)}</Text>
        </View>
      </View>

      <View style={styles.cardContent}>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Plano:</Text>
          <Text style={styles.infoValue}>
            {matricula.plano_nome} - {matricula.modalidade_nome}
          </Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Valor:</Text>
          <Text style={styles.infoValue}>{formatCurrency(matricula.valor)}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Início:</Text>
          <Text style={styles.infoValue}>{formatDate(matricula.data_inicio)}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Vencimento:</Text>
          <Text style={styles.infoValue}>{formatDate(matricula.data_vencimento)}</Text>
        </View>
      </View>

      <View style={styles.cardActions}>
        <Pressable
          onPress={() => router.push(`/matriculas/detalhe?id=${matricula.id}`)}
          style={({ pressed }) => [
            styles.btnAction,
            styles.btnDetalhes,
            pressed && { opacity: 0.7 },
          ]}
        >
          <Feather name="file-text" size={16} color="#3b82f6" />
          <Text style={styles.btnDetalhesText}>Detalhes</Text>
        </Pressable>
        {matricula.status !== 'cancelada' && matricula.status !== 'finalizada' && (
          <Pressable
            onPress={() => handleCancelar(matricula)}
            style={({ pressed }) => [
              styles.btnAction,
              styles.btnCancelar,
              pressed && { opacity: 0.7 },
            ]}
          >
            <Feather name="x-circle" size={16} color="#ef4444" />
            <Text style={styles.btnCancelarText}>Cancelar</Text>
          </Pressable>
        )}
      </View>
    </View>
  );

  const renderTable = () => (
    <View style={styles.tableContainer}>
      <View style={styles.table}>
          {/* Header */}
          <View style={styles.tableHeader}>
            <Text style={[styles.tableHeaderText, styles.colAluno]}>Aluno</Text>
            <Text style={[styles.tableHeaderText, styles.colPlano]}>Plano</Text>
            <Text style={[styles.tableHeaderText, styles.colModalidade]}>Modalidade</Text>
            <Text style={[styles.tableHeaderText, styles.colValor]}>Valor</Text>
            <Text style={[styles.tableHeaderText, styles.colDatas]}>Início</Text>
            <Text style={[styles.tableHeaderText, styles.colDatas]}>Vencimento</Text>
            <Text style={[styles.tableHeaderText, styles.colStatus]}>Status</Text>
            <Text style={[styles.tableHeaderText, styles.colAcoes]}>Ações</Text>
          </View>

          {/* Body */}
          {filteredMatriculas.map((matricula) => (
            <View key={matricula.id} style={styles.tableRow}>
              <View style={styles.colAluno}>
                <Text style={styles.cellTextBold}>{matricula.usuario_nome}</Text>
                <Text style={styles.cellTextSmall}>{matricula.usuario_email}</Text>
              </View>
              <Text style={[styles.cellText, styles.colPlano]}>{matricula.plano_nome}</Text>
              <View style={styles.colModalidade}>
                <View style={styles.modalidadeCell}>
                  {matricula.modalidade_icone && (
                    <View
                      style={[
                        styles.tableIconBadge,
                        { backgroundColor: matricula.modalidade_cor || '#3b82f6' },
                      ]}
                    >
                      <MaterialCommunityIcons
                        name={matricula.modalidade_icone}
                        size={14}
                        color="#fff"
                      />
                    </View>
                  )}
                  <Text style={styles.cellText}>{matricula.modalidade_nome}</Text>
                </View>
              </View>
              <Text style={[styles.cellText, styles.colValor]}>
                {formatCurrency(matricula.valor)}
              </Text>
              <Text style={[styles.cellText, styles.colDatas]}>
                {formatDate(matricula.data_inicio)}
              </Text>
              <Text style={[styles.cellText, styles.colDatas]}>
                {formatDate(matricula.data_vencimento)}
              </Text>
              <View style={styles.colStatus}>
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(matricula.status) },
                  ]}
                >
                  <Text style={styles.statusText}>{getStatusLabel(matricula.status)}</Text>
                </View>
              </View>
              <View style={styles.colAcoes}>
                <Pressable
                  onPress={() => router.push(`/matriculas/detalhe?id=${matricula.id}`)}
                  style={({ pressed }) => [
                    styles.btnTableAction,
                    pressed && { opacity: 0.7 },
                  ]}
                >
                  <Feather name="file-text" size={18} color="#3b82f6" />
                </Pressable>
                {matricula.status !== 'cancelada' && matricula.status !== 'finalizada' && (
                  <Pressable
                    onPress={() => handleCancelar(matricula)}
                    style={({ pressed }) => [
                      styles.btnTableAction,
                      { marginLeft: 8 },
                      pressed && { opacity: 0.7 },
                    ]}
                  >
                    <Feather name="x-circle" size={18} color="#ef4444" />
                  </Pressable>
                )}
              </View>
            </View>
          ))}
        </View>
    </View>
  );

  return (
    <LayoutBase title="Matrículas" subtitle="Gerencie as matrículas dos alunos">
      <View style={styles.container}>
        <View style={styles.header}>
          <View style={{ flex: 1 }}>
            <Text style={styles.title}>Matrículas</Text>
            <Text style={styles.subtitle}>Gerencie as matrículas dos alunos</Text>
          </View>
          <Pressable
            onPress={() => router.push('/matriculas/novo')}
            style={({ pressed }) => [styles.btnPrimary, pressed && { opacity: 0.8 }]}
          >
            <Feather name="plus" size={20} color="#fff" />
            <Text style={styles.btnPrimaryText}>Nova Matrícula</Text>
          </Pressable>
        </View>

        {/* Barra de Pesquisa e Filtros */}
        <View style={styles.searchContainer}>
          <View style={styles.searchInputWrapper}>
            <Feather name="search" size={20} color="#9ca3af" style={styles.searchIcon} />
            <TextInput
              style={styles.searchInput}
              placeholder="Buscar por aluno, email, plano ou modalidade..."
              placeholderTextColor="#9ca3af"
              value={searchTerm}
              onChangeText={setSearchTerm}
            />
            {searchTerm !== '' && (
              <Pressable onPress={() => setSearchTerm('')} style={styles.clearButton}>
                <Feather name="x" size={18} color="#9ca3af" />
              </Pressable>
            )}
          </View>
          
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterScroll}>
            <View style={styles.filterContainer}>
              {['todos', 'ativa', 'vencida', 'cancelada', 'finalizada'].map(status => (
                <Pressable
                  key={status}
                  onPress={() => setStatusFilter(status)}
                  style={[styles.filterChip, statusFilter === status && styles.filterChipActive]}
                >
                  <Text style={[styles.filterChipText, statusFilter === status && styles.filterChipTextActive]}>
                    {status === 'todos' ? 'Todas' : status.charAt(0).toUpperCase() + status.slice(1)}
                  </Text>
                </Pressable>
              ))}
            </View>
          </ScrollView>
          
          <Text style={styles.resultCount}>
            {filteredMatriculas.length} {filteredMatriculas.length === 1 ? 'matrícula' : 'matrículas'}
          </Text>
        </View>

        {loading ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#3b82f6" />
            <Text style={styles.loadingText}>Carregando matrículas...</Text>
          </View>
        ) : matriculas.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="user-check" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhuma matrícula encontrada</Text>
            <Text style={styles.emptySubtext}>
              Clique em "Nova Matrícula" para matricular um aluno
            </Text>
          </View>
        ) : filteredMatriculas.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="search" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhum resultado encontrado</Text>
            <Text style={styles.emptySubtext}>
              Tente ajustar os filtros ou termo de busca
            </Text>
          </View>
        ) : (
          <ScrollView showsVerticalScrollIndicator={false}>
            {isMobile ? (
              <View style={styles.cardsContainer}>
                {filteredMatriculas.map(renderMobileCard)}
              </View>
            ) : (
              renderTable()
            )}
          </ScrollView>
        )}
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 24,
    paddingVertical: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  searchContainer: {
    backgroundColor: '#fff',
    paddingHorizontal: 24,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  searchInputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    paddingHorizontal: 12,
    marginBottom: 12,
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 10,
    fontSize: 14,
    color: '#111827',
  },
  clearButton: {
    padding: 4,
  },
  filterScroll: {
    marginBottom: 12,
  },
  filterContainer: {
    flexDirection: 'row',
    gap: 8,
  },
  filterChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#f3f4f6',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  filterChipActive: {
    backgroundColor: '#3b82f6',
    borderColor: '#3b82f6',
  },
  filterChipText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6b7280',
  },
  filterChipTextActive: {
    color: '#fff',
  },
  resultCount: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '500',
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 14,
    color: '#6b7280',
  },
  btnPrimary: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#3b82f6',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
  },
  btnPrimaryText: {
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
    marginTop: 16,
    fontSize: 14,
    color: '#6b7280',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  emptyText: {
    marginTop: 16,
    fontSize: 18,
    fontWeight: '600',
    color: '#374151',
  },
  emptySubtext: {
    marginTop: 8,
    fontSize: 14,
    color: '#6b7280',
    textAlign: 'center',
  },
  // Cards Mobile
  cardsContainer: {
    padding: 16,
    gap: 16,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  cardIconBadge: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardTitleContent: {
    flex: 1,
  },
  cardName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  cardEmail: {
    fontSize: 13,
    color: '#6b7280',
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#fff',
  },
  cardContent: {
    gap: 10,
    marginBottom: 16,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  infoLabel: {
    fontSize: 14,
    color: '#6b7280',
    fontWeight: '500',
  },
  infoValue: {
    fontSize: 14,
    color: '#111827',
    fontWeight: '600',
  },
  cardActions: {
    flexDirection: 'row',
    gap: 8,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
    paddingTop: 12,
  },
  btnAction: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 6,
  },
  btnDetalhes: {
    backgroundColor: '#eff6ff',
  },
  btnDetalhesText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#3b82f6',
  },
  btnCancelar: {
    backgroundColor: '#fef2f2',
  },
  btnCancelarText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#ef4444',
  },
  // Table Desktop
  tableContainer: {
    margin: 24,
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    overflow: 'hidden',
  },
  table: {
    width: '100%',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f9fafb',
    borderBottomWidth: 2,
    borderBottomColor: '#e5e7eb',
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  tableHeaderText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#374151',
    textTransform: 'uppercase',
  },
  tableRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    paddingVertical: 12,
    paddingHorizontal: 16,
    alignItems: 'center',
  },
  colAluno: { flex: 2.5 },
  colPlano: { flex: 1.5 },
  colModalidade: { flex: 1.5 },
  colValor: { flex: 1 },
  colDatas: { flex: 1 },
  colStatus: { flex: 1 },
  colAcoes: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center' },
  cellText: {
    fontSize: 14,
    color: '#374151',
  },
  cellTextBold: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  cellTextSmall: {
    fontSize: 12,
    color: '#6b7280',
  },
  modalidadeCell: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  tableIconBadge: {
    width: 24,
    height: 24,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  btnTableAction: {
    padding: 8,
  },
});
