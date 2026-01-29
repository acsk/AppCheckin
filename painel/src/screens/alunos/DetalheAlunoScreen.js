import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
  useWindowDimensions,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter, useLocalSearchParams } from 'expo-router';
import alunoService from '../../services/alunoService';
import { authService } from '../../services/authService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

export default function DetalheAlunoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const alunoId = id ? parseInt(id) : null;
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [loading, setLoading] = useState(true);
  const [aluno, setAluno] = useState(null);
  const [historico, setHistorico] = useState([]);
  const [loadingHistorico, setLoadingHistorico] = useState(false);
  const [confirmModal, setConfirmModal] = useState({ visible: false, acao: '', novoStatus: null });
  const [activeTab, setActiveTab] = useState('dados');

  useEffect(() => {
    ensureAdminAccess();
    if (alunoId) {
      loadAluno();
    }
  }, [alunoId]);

  const ensureAdminAccess = async () => {
    try {
      const user = await authService.getCurrentUser();
      if (!user || ![3, 4].includes(user.papel_id)) {
        showError('Acesso restrito aos administradores');
        router.replace('/');
      }
    } catch (error) {
      router.replace('/');
    }
  };

  const loadAluno = async () => {
    try {
      setLoading(true);
      const response = await alunoService.buscar(alunoId);
      const dadosAluno = response.aluno || response;
      setAluno(dadosAluno);
    } catch (error) {
      console.error('Erro ao carregar aluno:', error);
      showError(error.message || 'Não foi possível carregar os dados do aluno');
      router.back();
    } finally {
      setLoading(false);
    }
  };

  const loadHistorico = async () => {
    try {
      setLoadingHistorico(true);
      const response = await alunoService.historico(alunoId);
      setHistorico(response.historico || []);
    } catch (error) {
      console.error('Erro ao carregar histórico:', error);
      showError('Não foi possível carregar o histórico de planos');
    } finally {
      setLoadingHistorico(false);
    }
  };

  const handleTabChange = (tab) => {
    setActiveTab(tab);
    if (tab === 'historico' && historico.length === 0) {
      loadHistorico();
    }
  };

  const handleToggleStatus = () => {
    const acao = aluno.ativo ? 'desativar' : 'ativar';
    setConfirmModal({ visible: true, acao, novoStatus: aluno.ativo ? 0 : 1 });
  };

  const confirmToggleStatus = async () => {
    try {
      await alunoService.toggleStatus(alunoId, confirmModal.novoStatus);
      const acao = confirmModal.acao === 'desativar' ? 'desativado' : 'ativado';
      showSuccess(`Aluno ${acao} com sucesso`);
      setConfirmModal({ visible: false, acao: '', novoStatus: null });
      loadAluno();
    } catch (error) {
      showError(error.error || error.message || 'Erro ao alterar status do aluno');
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  };

  const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const formatCurrency = (value) => {
    if (!value) return '-';
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value);
  };

  const formatCPF = (cpf) => {
    if (!cpf) return '-';
    const cleaned = cpf.replace(/\D/g, '');
    return cleaned.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
  };

  const formatCEP = (cep) => {
    if (!cep) return '-';
    const cleaned = cep.replace(/\D/g, '');
    return cleaned.replace(/(\d{5})(\d{3})/, '$1-$2');
  };

  const formatTelefone = (telefone) => {
    if (!telefone) return '-';
    const cleaned = telefone.replace(/\D/g, '');
    if (cleaned.length === 11) {
      return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }
    if (cleaned.length === 10) {
      return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }
    return telefone;
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'ativa':
        return { bg: '#d1fae5', text: '#16a34a' };
      case 'cancelada':
        return { bg: '#fee2e2', text: '#ef4444' };
      case 'suspensa':
        return { bg: '#fef3c7', text: '#d97706' };
      case 'encerrada':
        return { bg: '#e5e7eb', text: '#6b7280' };
      default:
        return { bg: '#e5e7eb', text: '#6b7280' };
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Detalhes do Aluno" subtitle="Carregando...">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando dados...</Text>
        </View>
      </LayoutBase>
    );
  }

  if (!aluno) {
    return (
      <LayoutBase title="Detalhes do Aluno" subtitle="Aluno não encontrado">
        <View style={styles.emptyState}>
          <Feather name="user-x" size={48} color="#ccc" />
          <Text style={styles.emptyText}>Aluno não encontrado</Text>
          <TouchableOpacity style={styles.backButtonLarge} onPress={() => router.push('/alunos')}>
            <Text style={styles.backButtonLargeText}>Voltar para lista</Text>
          </TouchableOpacity>
        </View>
      </LayoutBase>
    );
  }

  const renderDadosTab = () => (
    <View style={styles.tabContent}>
      {/* Card Resumo */}
      <View style={styles.resumoCard}>
        <View style={styles.resumoHeader}>
          <View style={styles.avatarContainer}>
            <Text style={styles.avatarText}>
              {aluno.nome?.charAt(0)?.toUpperCase() || '?'}
            </Text>
          </View>
          <View style={styles.resumoInfo}>
            <Text style={styles.resumoNome}>{aluno.nome}</Text>
            <Text style={styles.resumoEmail}>{aluno.email}</Text>
            <View style={styles.badgesRow}>
              <View style={[styles.badge, aluno.ativo ? styles.badgeAtivo : styles.badgeInativo]}>
                <Text style={[styles.badgeText, aluno.ativo ? styles.badgeTextAtivo : styles.badgeTextInativo]}>
                  {aluno.ativo ? 'Ativo' : 'Inativo'}
                </Text>
              </View>
              {aluno.pagamento_ativo === null ? (
                <View style={[styles.badge, styles.badgeSemPlano]}>
                  <Text style={[styles.badgeText, styles.badgeTextSemPlano]}>Sem plano</Text>
                </View>
              ) : (
                <View style={[styles.badge, aluno.pagamento_ativo ? styles.badgePago : styles.badgePendente]}>
                  <Text style={[styles.badgeText, aluno.pagamento_ativo ? styles.badgeTextPago : styles.badgeTextPendente]}>
                    {aluno.pagamento_ativo ? 'Pagamento em dia' : 'Pagamento pendente'}
                  </Text>
                </View>
              )}
            </View>
          </View>
        </View>
      </View>

      {/* Plano Atual */}
      {aluno.plano && (
        <View style={styles.card}>
          <View style={styles.cardHeader}>
            <Feather name="credit-card" size={20} color="#f97316" />
            <Text style={styles.cardTitle}>Plano Atual</Text>
          </View>
          <View style={styles.cardContent}>
            <View style={styles.planoInfo}>
              <Text style={styles.planoNome}>{aluno.plano.nome}</Text>
              <Text style={styles.planoValor}>{formatCurrency(aluno.plano.valor)}</Text>
            </View>
            {aluno.matricula_id && (
              <Text style={styles.matriculaId}>Matrícula #{aluno.matricula_id}</Text>
            )}
          </View>
        </View>
      )}

      {/* Estatísticas */}
      <View style={styles.statsContainer}>
        <View style={styles.statCard}>
          <Feather name="check-circle" size={24} color="#16a34a" />
          <Text style={styles.statValue}>{aluno.total_checkins || 0}</Text>
          <Text style={styles.statLabel}>Check-ins</Text>
        </View>
        <View style={styles.statCard}>
          <Feather name="clock" size={24} color="#6366f1" />
          <Text style={styles.statValue} numberOfLines={1}>
            {aluno.ultimo_checkin ? formatDateTime(aluno.ultimo_checkin) : '-'}
          </Text>
          <Text style={styles.statLabel}>Último Check-in</Text>
        </View>
      </View>

      {/* Dados Pessoais */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Feather name="user" size={20} color="#f97316" />
          <Text style={styles.cardTitle}>Dados Pessoais</Text>
        </View>
        <View style={styles.cardContent}>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Telefone</Text>
            <Text style={styles.infoValue}>{formatTelefone(aluno.telefone)}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>CPF</Text>
            <Text style={styles.infoValue}>{formatCPF(aluno.cpf)}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>ID do Usuário</Text>
            <Text style={styles.infoValue}>{aluno.usuario_id || '-'}</Text>
          </View>
        </View>
      </View>

      {/* Endereço */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Feather name="map-pin" size={20} color="#f97316" />
          <Text style={styles.cardTitle}>Endereço</Text>
        </View>
        <View style={styles.cardContent}>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>CEP</Text>
            <Text style={styles.infoValue}>{formatCEP(aluno.cep)}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Logradouro</Text>
            <Text style={styles.infoValue}>
              {aluno.logradouro ? `${aluno.logradouro}${aluno.numero ? `, ${aluno.numero}` : ''}` : '-'}
            </Text>
          </View>
          {aluno.complemento && (
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Complemento</Text>
              <Text style={styles.infoValue}>{aluno.complemento}</Text>
            </View>
          )}
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Bairro</Text>
            <Text style={styles.infoValue}>{aluno.bairro || '-'}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Cidade/UF</Text>
            <Text style={styles.infoValue}>
              {aluno.cidade && aluno.estado ? `${aluno.cidade} - ${aluno.estado}` : '-'}
            </Text>
          </View>
        </View>
      </View>

      {/* Datas */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Feather name="calendar" size={20} color="#f97316" />
          <Text style={styles.cardTitle}>Datas</Text>
        </View>
        <View style={styles.cardContent}>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Cadastrado em</Text>
            <Text style={styles.infoValue}>{formatDateTime(aluno.created_at)}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Última atualização</Text>
            <Text style={styles.infoValue}>{formatDateTime(aluno.updated_at)}</Text>
          </View>
        </View>
      </View>
    </View>
  );

  const renderHistoricoTab = () => (
    <View style={styles.tabContent}>
      {loadingHistorico ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando histórico...</Text>
        </View>
      ) : historico.length === 0 ? (
        <View style={styles.emptyState}>
          <Feather name="file-text" size={48} color="#ccc" />
          <Text style={styles.emptyText}>Nenhum histórico de planos</Text>
          <Text style={styles.emptySubtext}>O aluno não possui matrículas anteriores</Text>
        </View>
      ) : (
        <View style={styles.historicoList}>
          {historico.map((item, index) => {
            const statusColor = getStatusColor(item.status);
            return (
              <View key={item.id || index} style={styles.historicoCard}>
                <View style={styles.historicoHeader}>
                  <View style={styles.historicoPlano}>
                    <Text style={styles.historicoPlanoNome}>{item.plano_nome}</Text>
                    <Text style={styles.historicoPlanoValor}>{formatCurrency(item.plano_valor)}</Text>
                  </View>
                  <View style={[styles.badge, { backgroundColor: statusColor.bg }]}>
                    <Text style={[styles.badgeText, { color: statusColor.text }]}>
                      {item.status?.charAt(0).toUpperCase() + item.status?.slice(1)}
                    </Text>
                  </View>
                </View>
                <View style={styles.historicoBody}>
                  <View style={styles.historicoRow}>
                    <Feather name="calendar" size={14} color="#666" />
                    <Text style={styles.historicoLabel}>Início:</Text>
                    <Text style={styles.historicoValue}>{formatDate(item.data_inicio)}</Text>
                  </View>
                  {item.data_fim && (
                    <View style={styles.historicoRow}>
                      <Feather name="calendar" size={14} color="#666" />
                      <Text style={styles.historicoLabel}>Fim:</Text>
                      <Text style={styles.historicoValue}>{formatDate(item.data_fim)}</Text>
                    </View>
                  )}
                  {item.observacoes && (
                    <View style={styles.historicoObservacoes}>
                      <Text style={styles.historicoObservacoesLabel}>Observações:</Text>
                      <Text style={styles.historicoObservacoesText}>{item.observacoes}</Text>
                    </View>
                  )}
                </View>
              </View>
            );
          })}
        </View>
      )}
    </View>
  );

  return (
    <LayoutBase title="Detalhes do Aluno" subtitle={aluno.nome}>
      <ScrollView style={styles.container}>
        {/* Ações no topo */}
        <View style={styles.headerActions}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.push('/alunos')}
          >
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>

          <View style={styles.actionButtons}>
            <TouchableOpacity
              style={styles.editButton}
              onPress={() => router.push(`/alunos/${alunoId}?edit=true`)}
            >
              <Feather name="edit-2" size={16} color="#fff" />
              {!isMobile && <Text style={styles.editButtonText}>Editar</Text>}
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.statusButton, aluno.ativo ? styles.statusButtonDesativar : styles.statusButtonAtivar]}
              onPress={handleToggleStatus}
            >
              <Feather name={aluno.ativo ? 'user-x' : 'user-check'} size={16} color="#fff" />
              {!isMobile && (
                <Text style={styles.statusButtonText}>
                  {aluno.ativo ? 'Desativar' : 'Ativar'}
                </Text>
              )}
            </TouchableOpacity>
          </View>
        </View>

        {/* Tabs */}
        <View style={styles.tabsContainer}>
          <TouchableOpacity
            style={[styles.tab, activeTab === 'dados' && styles.tabActive]}
            onPress={() => handleTabChange('dados')}
          >
            <Feather name="user" size={16} color={activeTab === 'dados' ? '#f97316' : '#6b7280'} />
            <Text style={[styles.tabText, activeTab === 'dados' && styles.tabTextActive]}>
              Dados
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.tab, activeTab === 'historico' && styles.tabActive]}
            onPress={() => handleTabChange('historico')}
          >
            <Feather name="file-text" size={16} color={activeTab === 'historico' ? '#f97316' : '#6b7280'} />
            <Text style={[styles.tabText, activeTab === 'historico' && styles.tabTextActive]}>
              Histórico de Planos
            </Text>
          </TouchableOpacity>
        </View>

        {/* Conteúdo da Tab */}
        {activeTab === 'dados' ? renderDadosTab() : renderHistoricoTab()}
      </ScrollView>

      <ConfirmModal
        visible={confirmModal.visible}
        title={confirmModal.acao === 'desativar' ? 'Confirmar Desativação' : 'Confirmar Ativação'}
        message={`Deseja realmente ${confirmModal.acao} o aluno "${aluno.nome}"?`}
        onConfirm={confirmToggleStatus}
        onCancel={() => setConfirmModal({ visible: false, acao: '', novoStatus: null })}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
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
  emptyState: {
    alignItems: 'center',
    padding: 40,
  },
  emptyText: {
    marginTop: 12,
    fontSize: 16,
    fontWeight: '600',
    color: '#444',
  },
  emptySubtext: {
    marginTop: 6,
    fontSize: 13,
    color: '#888',
  },
  headerActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: '#f97316',
    borderRadius: 8,
  },
  backButtonText: {
    fontSize: 14,
    color: '#fff',
    fontWeight: '600',
  },
  backButtonLarge: {
    marginTop: 16,
    paddingVertical: 12,
    paddingHorizontal: 24,
    backgroundColor: '#f97316',
    borderRadius: 8,
  },
  backButtonLargeText: {
    fontSize: 14,
    color: '#fff',
    fontWeight: '600',
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 8,
  },
  editButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: '#6366f1',
    borderRadius: 8,
  },
  editButtonText: {
    fontSize: 14,
    color: '#fff',
    fontWeight: '600',
  },
  statusButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 8,
  },
  statusButtonDesativar: {
    backgroundColor: '#ef4444',
  },
  statusButtonAtivar: {
    backgroundColor: '#16a34a',
  },
  statusButtonText: {
    fontSize: 14,
    color: '#fff',
    fontWeight: '600',
  },
  tabsContainer: {
    flexDirection: 'row',
    marginHorizontal: 16,
    marginBottom: 16,
    backgroundColor: '#f3f4f6',
    borderRadius: 10,
    padding: 4,
  },
  tab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 10,
    borderRadius: 8,
  },
  tabActive: {
    backgroundColor: '#fff',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  tabText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
  },
  tabTextActive: {
    color: '#f97316',
  },
  tabContent: {
    paddingHorizontal: 16,
    paddingBottom: 24,
  },
  resumoCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 20,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  resumoHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  avatarContainer: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#f97316',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontSize: 28,
    fontWeight: '700',
    color: '#fff',
  },
  resumoInfo: {
    flex: 1,
  },
  resumoNome: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111827',
  },
  resumoEmail: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 2,
  },
  badgesRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 8,
    flexWrap: 'wrap',
  },
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  badgeAtivo: {
    backgroundColor: '#d1fae5',
  },
  badgeInativo: {
    backgroundColor: '#fee2e2',
  },
  badgePago: {
    backgroundColor: '#dbeafe',
  },
  badgePendente: {
    backgroundColor: '#fef3c7',
  },
  badgeText: {
    fontSize: 11,
    fontWeight: '700',
  },
  badgeTextAtivo: {
    color: '#16a34a',
  },
  badgeTextInativo: {
    color: '#ef4444',
  },
  badgeTextPago: {
    color: '#2563eb',
  },
  badgeTextPendente: {
    color: '#d97706',
  },
  badgeSemPlano: {
    backgroundColor: '#f3f4f6',
  },
  badgeTextSemPlano: {
    color: '#6b7280',
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    overflow: 'hidden',
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 16,
    backgroundColor: '#fff7ed',
    borderBottomWidth: 1,
    borderBottomColor: '#fed7aa',
  },
  cardTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  cardContent: {
    padding: 16,
  },
  planoInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  planoNome: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  planoValor: {
    fontSize: 18,
    fontWeight: '700',
    color: '#16a34a',
  },
  matriculaId: {
    marginTop: 8,
    fontSize: 12,
    color: '#6b7280',
  },
  statsContainer: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 16,
  },
  statCard: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  statValue: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
    marginTop: 8,
    textAlign: 'center',
  },
  statLabel: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 4,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  infoLabel: {
    fontSize: 14,
    color: '#6b7280',
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
    textAlign: 'right',
    flex: 1,
    marginLeft: 16,
  },
  historicoList: {
    gap: 12,
  },
  historicoCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    overflow: 'hidden',
  },
  historicoHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#f9fafb',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  historicoPlano: {
    flex: 1,
  },
  historicoPlanoNome: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  historicoPlanoValor: {
    fontSize: 14,
    color: '#16a34a',
    fontWeight: '600',
  },
  historicoBody: {
    padding: 16,
  },
  historicoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  historicoLabel: {
    fontSize: 13,
    color: '#6b7280',
  },
  historicoValue: {
    fontSize: 13,
    fontWeight: '600',
    color: '#111827',
  },
  historicoObservacoes: {
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#f3f4f6',
  },
  historicoObservacoesLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
    marginBottom: 4,
  },
  historicoObservacoesText: {
    fontSize: 13,
    color: '#374151',
    fontStyle: 'italic',
  },
});
