import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, ActivityIndicator, useWindowDimensions, TextInput } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { turmaService } from '../../services/turmaService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

export default function TurmasScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [turmas, setTurmas] = useState([]);
  const [turmasFiltradas, setTurmasFiltradas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });
  const [searchText, setSearchText] = useState('');
  const [dataSelecionada, setDataSelecionada] = useState(obterHoje());

  useEffect(() => {
    carregarDados();
  }, [dataSelecionada]);

  // Retorna a data de hoje em formato YYYY-MM-DD
  function obterHoje() {
    const hoje = new Date();
    return hoje.toISOString().split('T')[0];
  }

  // Formata a data para exibição (ex: "09 Jan 2026")
  function formatarDataExibicao(dataStr) {
    const date = new Date(dataStr + 'T00:00:00');
    const opcoes = { day: '2-digit', month: 'short', year: 'numeric' };
    return date.toLocaleDateString('pt-BR', opcoes);
  }

  // Obtém o dia da semana (seg, ter, etc)
  function obterDiaSemana(dataStr) {
    const date = new Date(dataStr + 'T00:00:00');
    const dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
    return dias[date.getDay()];
  }

  // Navega para um dia específico
  function irParaData(dias) {
    const data = new Date(dataSelecionada + 'T00:00:00');
    data.setDate(data.getDate() + dias);
    const novaData = data.toISOString().split('T')[0];
    setDataSelecionada(novaData);
  }

  // Volta para hoje
  function voltarParaHoje() {
    setDataSelecionada(obterHoje());
  }

  const carregarDados = async () => {
    try {
      setLoading(true);
      // Passa a data selecionada como parâmetro
      const turmasData = await turmaService.listar(dataSelecionada);
      
      setTurmas(turmasData);
      setTurmasFiltradas(turmasData);
      setSearchText('');
    } catch (error) {
      showError('Erro ao carregar turmas');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const filtrarTurmasLocal = (lista, termo) => {
    const termoLower = termo.toLowerCase();
    const filtradas = lista.filter(turma => 
      turma.nome?.toLowerCase().includes(termoLower) ||
      turma.modalidade_nome?.toLowerCase().includes(termoLower) ||
      turma.professor_nome?.toLowerCase().includes(termoLower)
    );
    setTurmasFiltradas(filtradas);
  };

  const handleSearchChange = (text) => {
    setSearchText(text);
    if (text.trim()) {
      filtrarTurmasLocal(turmas, text.trim());
    } else {
      setTurmasFiltradas(turmas);
    }
  };

  const handleClearSearch = () => {
    setSearchText('');
    setTurmasFiltradas(turmas);
  };

  const handleDeletar = (id, nome) => {
    setConfirmDelete({ visible: true, id, nome });
  };

  const confirmarDelete = async () => {
    try {
      await turmaService.deletar(confirmDelete.id);
      setTurmas(turmas.filter(t => t.id !== confirmDelete.id));
      setTurmasFiltradas(turmasFiltradas.filter(t => t.id !== confirmDelete.id));
      showSuccess('Turma deletada com sucesso');
      setConfirmDelete({ visible: false, id: null, nome: '' });
    } catch (error) {
      console.error('Erro ao deletar:', error);
      showError('Erro ao deletar turma');
    }
  };

  const handleToggleStatus = async (turma) => {
    try {
      await turmaService.atualizar(turma.id, {
        ativo: !turma.ativo
      });
      
      const updated = turmas.map(t =>
        t.id === turma.id ? { ...t, ativo: !t.ativo } : t
      );
      setTurmas(updated);
      setTurmasFiltradas(updated.filter(t => {
        const termo = searchText.toLowerCase();
        return (
          t.nome?.toLowerCase().includes(termo) ||
          t.modalidade_nome?.toLowerCase().includes(termo) ||
          t.professor_nome?.toLowerCase().includes(termo)
        );
      }));
      
      showSuccess(turma.ativo ? 'Turma inativada' : 'Turma ativada');
    } catch (error) {
      console.error('Erro ao atualizar status:', error);
      showError('Erro ao atualizar status');
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Aulas" subtitle="Gerenciar turmas">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#3b82f6" />
        </View>
      </LayoutBase>
    );
  }

  const renderTable = () => (
    <View style={styles.tableContainer}>
      {/* Header da Tabela */}
      <View style={styles.tableHeader}>
        <Text style={[styles.tableHeaderText, styles.colIcone]}>MODALIDADE</Text>
        <Text style={[styles.tableHeaderText, styles.colModalidade]}>TIPO</Text>
        <Text style={[styles.tableHeaderText, styles.colProfessor]}>PROFESSOR</Text>
        <Text style={[styles.tableHeaderText, styles.colVagas]}>VAGAS</Text>
        <Text style={[styles.tableHeaderText, styles.colStatus]}>STATUS</Text>
        <Text style={[styles.tableHeaderText, styles.colAcoes]}>AÇÕES</Text>
      </View>

      {/* Linhas da Tabela */}
      <ScrollView style={styles.tableBody} showsVerticalScrollIndicator={true}>
        {turmasFiltradas.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="calendar" size={40} color="#d1d5db" />
            <Text style={styles.emptyText}>Nenhuma turma encontrada</Text>
          </View>
        ) : (
          turmasFiltradas.map((turma) => (
            <View key={turma.id} style={styles.tableRow}>
              <View style={[styles.tableCell, styles.colIcone]}>
                <Feather name={turma.modalidade_icone || 'circle'} size={24} color="#3b82f6" />
              </View>
              <Text style={[styles.tableCell, styles.colModalidade]} numberOfLines={1}>
                {turma.modalidade_nome || '-'}
              </Text>
              <Text style={[styles.tableCell, styles.colProfessor]} numberOfLines={1}>
                {turma.professor_nome || '-'}
              </Text>
              
              <View style={[styles.tableCell, styles.colVagas]}>
                <Text style={styles.vagasText}>
                  {turma.alunos_count || 0}/{turma.limite_alunos}
                </Text>
              </View>

              <View style={[styles.tableCell, styles.colStatus]}>
                <View style={[
                  styles.statusBadge,
                  turma.ativo ? styles.statusAtivo : styles.statusInativo
                ]}>
                  <Text style={styles.statusText}>
                    {turma.ativo ? 'Ativa' : 'Inativa'}
                  </Text>
                </View>
              </View>

              <View style={[styles.tableCell, styles.colAcoes]}>
                <View style={styles.actions}>
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => router.push(`/turmas/${turma.id}`)}
                  >
                    <Feather name="edit-2" size={16} color="#3b82f6" />
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => handleToggleStatus(turma)}
                  >
                    <Feather 
                      name={turma.ativo ? "toggle-right" : "toggle-left"} 
                      size={18} 
                      color={turma.ativo ? '#16a34a' : '#ef4444'} 
                    />
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => handleDeletar(turma.id, turma.nome)}
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

  return (
    <LayoutBase title="Aulas" subtitle="Gerenciar turmas">
      <View style={styles.container}>
        {/* Seletor de Data */}
        <View style={styles.dateSelector}>
          <TouchableOpacity 
            style={styles.dateSelectorButton}
            onPress={() => irParaData(-1)}
          >
            <Feather name="chevron-left" size={20} color="#3b82f6" />
          </TouchableOpacity>

          <View style={styles.dateSelectorContent}>
            <Text style={styles.dateDisplay}>
              {obterDiaSemana(dataSelecionada)} • {formatarDataExibicao(dataSelecionada)}
            </Text>
            {dataSelecionada !== obterHoje() && (
              <TouchableOpacity onPress={voltarParaHoje}>
                <Text style={styles.voltarHojeText}>Voltar para hoje</Text>
              </TouchableOpacity>
            )}
          </View>

          <TouchableOpacity 
            style={styles.dateSelectorButton}
            onPress={() => irParaData(1)}
          >
            <Feather name="chevron-right" size={20} color="#3b82f6" />
          </TouchableOpacity>
        </View>

        {/* Header */}
        <View style={[styles.header, isMobile && styles.headerMobile]}>
          <View style={styles.headerLeft}>
            <Text style={[styles.headerTitle, isMobile && styles.headerTitleMobile]}>Aulas (Turmas)</Text>
            <Text style={styles.headerSubtitle}>
              {turmasFiltradas.length} {turmasFiltradas.length === 1 ? 'turma encontrada' : 'turmas encontradas'}
            </Text>
          </View>
          
          {!isMobile && (
            <View style={styles.searchContainer}>
              <View style={styles.searchInputContainer}>
                <Feather name="search" size={20} color="#999" style={styles.searchIcon} />
                <TextInput
                  style={styles.searchInput}
                  placeholder="Buscar por nome, modalidade ou professor..."
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
            onPress={() => router.push('/turmas/novo')}
          >
            <Feather name="plus" size={20} color="#fff" />
            <Text style={styles.botaoNovoText}>Nova</Text>
          </TouchableOpacity>
        </View>

        {/* Tabela */}
        {renderTable()}
      </View>

      <ConfirmModal
        visible={confirmDelete.visible}
        title="Deletar Turma"
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
  dateSelector: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#eff6ff',
    borderBottomWidth: 1,
    borderBottomColor: '#bfdbfe',
    gap: 12,
  },
  dateSelectorButton: {
    padding: 8,
    borderRadius: 6,
    backgroundColor: '#dbeafe',
    justifyContent: 'center',
    alignItems: 'center',
  },
  dateSelectorContent: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  dateDisplay: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1e40af',
  },
  voltarHojeText: {
    fontSize: 12,
    color: '#0284c7',
    textDecorationLine: 'underline',
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
  colIcone: {
    flex: 0.8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  colModalidade: {
    flex: 1.5,
  },
  colProfessor: {
    flex: 1.8,
  },
  colVagas: {
    flex: 1.2,
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
  vagasText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
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
