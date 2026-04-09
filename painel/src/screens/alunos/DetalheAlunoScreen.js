import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
  useWindowDimensions,
  Modal,
  TextInput,
  Pressable,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter, useLocalSearchParams } from 'expo-router';
import alunoService from '../../services/alunoService';
import { authService } from '../../services/authService';
import { creditoService } from '../../services/creditoService';
import LayoutBase from '../../components/LayoutBase';
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
  const [activeTab, setActiveTab] = useState('dados');
  const [creditos, setCreditos] = useState([]);
  const [saldoCreditos, setSaldoCreditos] = useState(0);
  const [loadingCreditos, setLoadingCreditos] = useState(false);
  const [modalAdicionarCredito, setModalAdicionarCredito] = useState(false);
  const [modalCancelarCredito, setModalCancelarCredito] = useState(null);
  const [salvandoCredito, setSalvandoCredito] = useState(false);
  const [cancelandoCredito, setCancelandoCredito] = useState(false);
  const [formCredito, setFormCredito] = useState({ valor: '', motivo: '' });
  const [checkinSummary, setCheckinSummary] = useState(null);
  const [checkinsCache, setCheckinsCache] = useState({});
  const [loadingCheckins, setLoadingCheckins] = useState(false);
  const [loadingCalMonth, setLoadingCalMonth] = useState(false);
  const [calendarDate, setCalendarDate] = useState(() => {
    const now = new Date();
    return { year: now.getFullYear(), month: now.getMonth() };
  });
  const [calendarSelected, setCalendarSelected] = useState(null);

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
    if (tab === 'creditos') {
      loadCreditos();
    }
    if (tab === 'checkins') {
      loadCheckins();
    }
  };

  const loadCreditos = async () => {
    try {
      setLoadingCreditos(true);
      const [creditosList, saldoResp] = await Promise.all([
        creditoService.listar(alunoId),
        creditoService.consultarSaldo(alunoId),
      ]);
      setCreditos(Array.isArray(creditosList) ? creditosList : []);
      setSaldoCreditos(saldoResp?.saldo_total || 0);
    } catch (error) {
      console.error('Erro ao carregar créditos:', error);
      setCreditos([]);
      setSaldoCreditos(0);
    } finally {
      setLoadingCreditos(false);
    }
  };

  const loadMonthCheckins = async (year, mes1based) => {
    const key = `${year}-${mes1based}`;
    if (checkinsCache[key] !== undefined) return;
    try {
      setLoadingCalMonth(true);
      const response = await alunoService.checkins(alunoId, { mes: mes1based, ano: year });
      setCheckinsCache(prev => ({ ...prev, [key]: response.checkins || [] }));
    } catch (error) {
      console.error('Erro ao carregar check-ins do mês:', error);
      setCheckinsCache(prev => ({ ...prev, [key]: [] }));
    } finally {
      setLoadingCalMonth(false);
    }
  };

  const loadCheckins = async () => {
    try {
      setLoadingCheckins(true);
      const summary = await alunoService.checkins(alunoId);
      setCheckinSummary(summary);
      const now = new Date();
      await loadMonthCheckins(now.getFullYear(), now.getMonth() + 1);
    } catch (error) {
      console.error('Erro ao carregar check-ins:', error);
    } finally {
      setLoadingCheckins(false);
    }
  };

  const handleAdicionarCredito = async () => {
    const valor = parseFloat(formCredito.valor?.replace(',', '.'));
    if (!valor || valor <= 0) {
      showError('Informe um valor válido maior que zero');
      return;
    }
    try {
      setSalvandoCredito(true);
      await creditoService.criar(alunoId, {
        valor,
        motivo: formCredito.motivo?.trim() || 'Crédito manual',
      });
      showSuccess('Crédito adicionado com sucesso');
      setModalAdicionarCredito(false);
      setFormCredito({ valor: '', motivo: '' });
      await loadCreditos();
    } catch (error) {
      showError(error.message || 'Erro ao adicionar crédito');
    } finally {
      setSalvandoCredito(false);
    }
  };

  const handleCancelarCredito = async (creditoId) => {
    try {
      setCancelandoCredito(true);
      await creditoService.cancelar(creditoId);
      showSuccess('Crédito cancelado com sucesso');
      setModalCancelarCredito(null);
      await loadCreditos();
    } catch (error) {
      showError(error.message || 'Erro ao cancelar crédito');
    } finally {
      setCancelandoCredito(false);
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

  const getCreditoStatusStyle = (status) => {
    switch (status) {
      case 'ativo':
        return { bg: '#d1fae5', text: '#16a34a', label: 'Ativo' };
      case 'utilizado':
        return { bg: '#e5e7eb', text: '#6b7280', label: 'Utilizado' };
      case 'cancelado':
        return { bg: '#fee2e2', text: '#ef4444', label: 'Cancelado' };
      default:
        return { bg: '#e5e7eb', text: '#6b7280', label: status || '-' };
    }
  };

  const renderCreditosTab = () => (
    <View style={styles.tabContent}>
      {/* Card de Saldo */}
      <View style={[styles.card, { marginBottom: 16 }]}>
        <View style={[styles.cardHeader, { backgroundColor: '#ecfdf5', borderBottomColor: '#a7f3d0' }]}>
          <Feather name="credit-card" size={20} color="#16a34a" />
          <Text style={styles.cardTitle}>Saldo de Créditos</Text>
        </View>
        <View style={[styles.cardContent, { alignItems: 'center', paddingVertical: 20 }]}>
          <Text style={{ fontSize: 28, fontWeight: '800', color: saldoCreditos > 0 ? '#16a34a' : '#6b7280' }}>
            {formatCurrency(saldoCreditos)}
          </Text>
          <Text style={{ fontSize: 12, color: '#6b7280', marginTop: 4 }}>Saldo disponível</Text>
        </View>
      </View>

      {/* Botão Adicionar */}
      <TouchableOpacity
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 8,
          backgroundColor: '#f97316',
          borderRadius: 10,
          paddingVertical: 12,
          marginBottom: 16,
        }}
        onPress={() => {
          setFormCredito({ valor: '', motivo: '' });
          setModalAdicionarCredito(true);
        }}
      >
        <Feather name="plus-circle" size={18} color="#fff" />
        <Text style={{ color: '#fff', fontWeight: '700', fontSize: 14 }}>Adicionar Crédito</Text>
      </TouchableOpacity>

      {/* Lista de Créditos */}
      {loadingCreditos ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando créditos...</Text>
        </View>
      ) : creditos.length === 0 ? (
        <View style={styles.emptyState}>
          <Feather name="credit-card" size={48} color="#ccc" />
          <Text style={styles.emptyText}>Nenhum crédito encontrado</Text>
          <Text style={styles.emptySubtext}>O aluno não possui créditos registrados</Text>
        </View>
      ) : (
        <View style={{ gap: 12 }}>
          {creditos.map((credito) => {
            const statusStyle = getCreditoStatusStyle(credito.status);
            return (
              <View key={credito.id} style={styles.historicoCard}>
                <View style={styles.historicoHeader}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 16, fontWeight: '700', color: '#111827' }}>
                      {formatCurrency(credito.valor)}
                    </Text>
                    <Text style={{ fontSize: 12, color: '#6b7280', marginTop: 2 }}>
                      Saldo: {formatCurrency(credito.saldo)}
                    </Text>
                  </View>
                  <View style={[styles.badge, { backgroundColor: statusStyle.bg }]}>
                    <Text style={[styles.badgeText, { color: statusStyle.text }]}>
                      {statusStyle.label}
                    </Text>
                  </View>
                </View>
                <View style={{ padding: 16 }}>
                  {credito.motivo && (
                    <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 8, marginBottom: 8 }}>
                      <Feather name="message-circle" size={14} color="#6b7280" />
                      <Text style={{ fontSize: 13, color: '#374151', flex: 1 }}>{credito.motivo}</Text>
                    </View>
                  )}
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                    <Feather name="calendar" size={14} color="#6b7280" />
                    <Text style={{ fontSize: 12, color: '#6b7280' }}>
                      Criado em: {formatDate(credito.created_at)}
                    </Text>
                  </View>
                  {credito.valor_utilizado && parseFloat(credito.valor_utilizado) > 0 && (
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                      <Feather name="check" size={14} color="#6b7280" />
                      <Text style={{ fontSize: 12, color: '#6b7280' }}>
                        Utilizado: {formatCurrency(credito.valor_utilizado)}
                      </Text>
                    </View>
                  )}
                  {credito.status === 'ativo' && (
                    <TouchableOpacity
                      style={{
                        flexDirection: 'row',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 6,
                        marginTop: 8,
                        paddingVertical: 8,
                        borderRadius: 8,
                        borderWidth: 1,
                        borderColor: '#fecaca',
                        backgroundColor: '#fef2f2',
                      }}
                      onPress={() => setModalCancelarCredito(credito)}
                    >
                      <Feather name="x-circle" size={14} color="#ef4444" />
                      <Text style={{ fontSize: 13, fontWeight: '600', color: '#ef4444' }}>Cancelar crédito</Text>
                    </TouchableOpacity>
                  )}
                </View>
              </View>
            );
          })}
        </View>
      )}
    </View>
  );

  const renderCheckinsTab = () => {
    const { year, month } = calendarDate;

    // grade de células
    const firstDow      = new Date(year, month, 1).getDay();
    const daysInMonth   = new Date(year, month + 1, 0).getDate();
    const daysInPrev    = new Date(year, month, 0).getDate();
    const cells = [];
    for (let i = firstDow - 1; i >= 0; i--) cells.push({ day: daysInPrev - i,  current: false, dateStr: null });
    for (let d = 1; d <= daysInMonth; d++) {
      const mm = String(month + 1).padStart(2, '0');
      const dd = String(d).padStart(2, '0');
      cells.push({ day: d, current: true, dateStr: `${year}-${mm}-${dd}` });
    }
    let nd = 1; while (cells.length < 42) cells.push({ day: nd++, current: false, dateStr: null });

    // dados do mês (via cache)
    const cacheKey      = `${year}-${month + 1}`;
    const monthCheckins = checkinsCache[cacheKey] || [];
    const checkinDays   = new Set(monthCheckins.map(c => c.data_aula?.slice(0, 10)));
    const todayStr      = new Date().toISOString().slice(0, 10);
    const selectedCheckins = calendarSelected
      ? monthCheckins.filter(c => c.data_aula?.slice(0, 10) === calendarSelected)
      : [];

    // contagem do mês (do summary, ou do cache se já carregado)
    const monthSummary = checkinSummary?.meses?.find(m => m.ano === year && m.mes === month + 1);
    const monthCount   = monthSummary?.total ?? monthCheckins.length;

    const MONTHS = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

    const goToPrev = () => {
      setCalendarSelected(null);
      const cur = calendarDate;
      const next = cur.month === 0 ? { year: cur.year - 1, month: 11 } : { year: cur.year, month: cur.month - 1 };
      loadMonthCheckins(next.year, next.month + 1);
      setCalendarDate(next);
    };

    const goToNext = () => {
      setCalendarSelected(null);
      const cur = calendarDate;
      const next = cur.month === 11 ? { year: cur.year + 1, month: 0 } : { year: cur.year, month: cur.month + 1 };
      loadMonthCheckins(next.year, next.month + 1);
      setCalendarDate(next);
    };

    if (loadingCheckins) {
      return (
        <View style={[styles.tabContent, styles.loadingContainer]}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      );
    }

    return (
      <View style={styles.tabContent}>
        {/* Barra de totais */}
        {checkinSummary && (
          <View style={styles.calSummaryBar}>
            <View style={styles.calSummaryItem}>
              <Text style={styles.calSummaryNum}>{checkinSummary.total_geral ?? 0}</Text>
              <Text style={styles.calSummaryLabel}>Total geral</Text>
            </View>
            <View style={styles.calSummarySep} />
            <View style={styles.calSummaryItem}>
              <Text style={[styles.calSummaryNum, { color: '#16a34a' }]}>{monthCount}</Text>
              <Text style={styles.calSummaryLabel}>Neste mês</Text>
            </View>
            {monthSummary?.presentes !== undefined && (
              <>
                <View style={styles.calSummarySep} />
                <View style={styles.calSummaryItem}>
                  <Text style={[styles.calSummaryNum, { color: '#2563eb' }]}>{monthSummary.presentes}</Text>
                  <Text style={styles.calSummaryLabel}>Presentes</Text>
                </View>
              </>
            )}
          </View>
        )}

        {/* Calendário */}
        <View style={styles.calCard}>
          {/* Navegação */}
          <View style={styles.calNav}>
            <TouchableOpacity style={styles.calNavBtn} onPress={goToPrev}>
              <Feather name="chevron-left" size={20} color="#374151" />
            </TouchableOpacity>
            <View style={{ alignItems: 'center' }}>
              <Text style={styles.calNavTitle}>{MONTHS[month]} {year}</Text>
              <Text style={styles.calNavSub}>
                {monthCount} check-in{monthCount !== 1 ? 's' : ''} no mês
              </Text>
            </View>
            <TouchableOpacity style={styles.calNavBtn} onPress={goToNext}>
              <Feather name="chevron-right" size={20} color="#374151" />
            </TouchableOpacity>
          </View>

          {/* Dias da semana */}
          <View style={styles.calWeekRow}>
            {['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'].map(d => (
              <Text key={d} style={styles.calWeekCell}>{d}</Text>
            ))}
          </View>

          {/* Grade */}
          {loadingCalMonth ? (
            <View style={styles.calLoading}>
              <ActivityIndicator color="#f97316" />
              <Text style={{ fontSize: 13, color: '#94a3b8', marginTop: 8 }}>Carregando mês...</Text>
            </View>
          ) : (
            <View style={styles.calGrid}>
              {cells.map((cell, i) => {
                const hasCheckin = cell.current && checkinDays.has(cell.dateStr);
                const isToday    = cell.current && cell.dateStr === todayStr;
                const isSelected = cell.current && cell.dateStr === calendarSelected;
                return (
                  <TouchableOpacity
                    key={i}
                    style={styles.calCell}
                    onPress={() => cell.current && setCalendarSelected(isSelected ? null : cell.dateStr)}
                    activeOpacity={cell.current ? 0.7 : 1}
                    disabled={!cell.current}
                  >
                    <View style={[
                      styles.calDayCircle,
                      hasCheckin && styles.calDayHasCheckin,
                      isSelected && styles.calDaySelected,
                      isToday && !isSelected && styles.calDayToday,
                    ]}>
                      <Text style={[
                        styles.calDayText,
                        !cell.current                          && styles.calDayTextFaded,
                        hasCheckin && !isSelected             && styles.calDayTextOnCheckin,
                        isSelected                            && styles.calDayTextOnSelected,
                        isToday && !hasCheckin && !isSelected  && styles.calDayTextToday,
                      ]}>
                        {cell.day}
                      </Text>
                    </View>
                  </TouchableOpacity>
                );
              })}
            </View>
          )}

          {/* Legenda */}
          <View style={styles.calLegend}>
            <View style={[styles.calLegendDot, { backgroundColor: '#16a34a' }]} />
            <Text style={styles.calLegendText}>Check-in</Text>
            <View style={[styles.calLegendDot, { borderWidth: 2, borderColor: '#f97316', backgroundColor: 'transparent' }]} />
            <Text style={styles.calLegendText}>Hoje</Text>
            <View style={[styles.calLegendDot, { backgroundColor: '#f97316' }]} />
            <Text style={styles.calLegendText}>Selecionado</Text>
          </View>
        </View>

        {/* Detalhe do dia selecionado */}
        {calendarSelected && (
          <View style={styles.calDetailCard}>
            <View style={styles.calDetailHeader}>
              <Feather name="calendar" size={16} color="#f97316" />
              <Text style={styles.calDetailTitle}>
                {new Date(calendarSelected + 'T12:00:00').toLocaleDateString('pt-BR', {
                  weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
                })}
              </Text>
              <TouchableOpacity onPress={() => setCalendarSelected(null)}>
                <Feather name="x" size={16} color="#9ca3af" />
              </TouchableOpacity>
            </View>
            {selectedCheckins.length === 0 ? (
              <View style={styles.calDetailEmpty}>
                <Text style={styles.calDetailEmptyText}>Nenhum check-in neste dia</Text>
              </View>
            ) : (
              <View style={{ padding: 12, gap: 8 }}>
                {selectedCheckins.map(c => (
                  <View key={c.id} style={styles.calCheckinItem}>
                    <View style={[
                      styles.calCheckinIcon,
                      c.presente
                        ? { backgroundColor: '#f0fdf4', borderColor: '#bbf7d0' }
                        : { backgroundColor: '#fef2f2', borderColor: '#fecaca' },
                    ]}>
                      <Feather
                        name={c.presente ? 'check' : 'x'}
                        size={14}
                        color={c.presente ? '#16a34a' : '#ef4444'}
                      />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.calCheckinModalidade}>{c.modalidade}</Text>
                      <Text style={styles.calCheckinHorario}>
                        {c.horario_inicio?.slice(0, 5)} – {c.horario_fim?.slice(0, 5)}
                        {c.registrado_por_admin ? '  ·  Admin' : ''}
                      </Text>
                    </View>
                    <View style={styles.calCheckinId}>
                      <Text style={styles.calCheckinIdText}>#{c.id}</Text>
                    </View>
                  </View>
                ))}
              </View>
            )}
          </View>
        )}
      </View>
    );
  };

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
          <TouchableOpacity
            style={[styles.tab, activeTab === 'creditos' && styles.tabActive]}
            onPress={() => handleTabChange('creditos')}
          >
            <Feather name="credit-card" size={16} color={activeTab === 'creditos' ? '#f97316' : '#6b7280'} />
            <Text style={[styles.tabText, activeTab === 'creditos' && styles.tabTextActive]}>
              Créditos
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.tab, activeTab === 'checkins' && styles.tabActive]}
            onPress={() => handleTabChange('checkins')}
          >
            <Feather name="check-circle" size={16} color={activeTab === 'checkins' ? '#f97316' : '#6b7280'} />
            <Text style={[styles.tabText, activeTab === 'checkins' && styles.tabTextActive]}>
              Check-ins
            </Text>
          </TouchableOpacity>
        </View>

        {/* Conteúdo da Tab */}
        {activeTab === 'dados' && renderDadosTab()}
        {activeTab === 'historico' && renderHistoricoTab()}
        {activeTab === 'creditos' && renderCreditosTab()}
        {activeTab === 'checkins' && renderCheckinsTab()}
      </ScrollView>

      {/* Modal Adicionar Crédito */}
      <Modal
        visible={modalAdicionarCredito}
        transparent
        animationType="fade"
        onRequestClose={() => setModalAdicionarCredito(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Adicionar Crédito</Text>
              <TouchableOpacity onPress={() => setModalAdicionarCredito(false)}>
                <Feather name="x" size={20} color="#6b7280" />
              </TouchableOpacity>
            </View>
            <View style={styles.modalBody}>
              <Text style={styles.inputLabel}>Valor (R$)</Text>
              <TextInput
                style={styles.input}
                value={formCredito.valor}
                onChangeText={(text) =>
                  setFormCredito((prev) => ({ ...prev, valor: text.replace(/[^0-9.,]/g, '') }))
                }
                placeholder="Ex: 50.00"
                keyboardType="decimal-pad"
              />
              <Text style={[styles.inputLabel, { marginTop: 12 }]}>Motivo</Text>
              <TextInput
                style={styles.input}
                value={formCredito.motivo}
                onChangeText={(text) =>
                  setFormCredito((prev) => ({ ...prev, motivo: text }))
                }
                placeholder="Ex: Cortesia por indicação"
              />
            </View>
            <View style={styles.modalFooter}>
              <TouchableOpacity
                style={styles.modalCancelButton}
                onPress={() => setModalAdicionarCredito(false)}
                disabled={salvandoCredito}
              >
                <Text style={styles.modalCancelText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalConfirmButton, salvandoCredito && { opacity: 0.6 }]}
                onPress={handleAdicionarCredito}
                disabled={salvandoCredito}
              >
                {salvandoCredito ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.modalConfirmText}>Adicionar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Cancelar Crédito */}
      <Modal
        visible={modalCancelarCredito !== null}
        transparent
        animationType="fade"
        onRequestClose={() => setModalCancelarCredito(null)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Cancelar Crédito</Text>
              <TouchableOpacity onPress={() => setModalCancelarCredito(null)}>
                <Feather name="x" size={20} color="#6b7280" />
              </TouchableOpacity>
            </View>
            <View style={styles.modalBody}>
              <Text style={{ fontSize: 14, color: '#374151', textAlign: 'center' }}>
                Tem certeza que deseja cancelar este crédito de{' '}
                {formatCurrency(modalCancelarCredito?.valor)}?
              </Text>
              {modalCancelarCredito?.motivo && (
                <Text style={{ fontSize: 12, color: '#6b7280', textAlign: 'center', marginTop: 8 }}>
                  Motivo: {modalCancelarCredito.motivo}
                </Text>
              )}
            </View>
            <View style={styles.modalFooter}>
              <TouchableOpacity
                style={styles.modalCancelButton}
                onPress={() => setModalCancelarCredito(null)}
                disabled={cancelandoCredito}
              >
                <Text style={styles.modalCancelText}>Não</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalDeleteButton, cancelandoCredito && { opacity: 0.6 }]}
                onPress={() => handleCancelarCredito(modalCancelarCredito?.id)}
                disabled={cancelandoCredito}
              >
                {cancelandoCredito ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.modalConfirmText}>Sim, cancelar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
  /* ── Modal styles ─────────────────────────── */
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 16,
  },
  modalContainer: {
    backgroundColor: '#fff',
    borderRadius: 16,
    width: '100%',
    maxWidth: 420,
    overflow: 'hidden',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  modalTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  modalBody: {
    padding: 16,
  },
  modalFooter: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 10,
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  modalCancelButton: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#d1d5db',
    backgroundColor: '#fff',
  },
  modalCancelText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
  },
  modalConfirmButton: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
    backgroundColor: '#f97316',
  },
  modalConfirmText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#fff',
  },
  modalDeleteButton: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
    backgroundColor: '#ef4444',
  },
  inputLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 6,
  },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#111827',
    backgroundColor: '#f9fafb',
  },

  /* ── Calendário de check-ins ───────────────────────────── */
  calSummaryBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderRadius: 12,
    paddingVertical: 14,
    paddingHorizontal: 18,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    gap: 4,
  },
  calSummaryItem: {
    alignItems: 'center',
    paddingHorizontal: 16,
  },
  calSummaryNum: {
    fontSize: 22,
    fontWeight: '800',
    color: '#111827',
    lineHeight: 26,
  },
  calSummaryLabel: {
    fontSize: 10,
    color: '#94a3b8',
    fontWeight: '500',
    marginTop: 2,
  },
  calSummarySep: {
    width: 1,
    height: 32,
    backgroundColor: '#e5e7eb',
  },
  calCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    marginBottom: 12,
    overflow: 'hidden',
  },
  calNav: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  calNavBtn: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  calNavTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  calNavSub: {
    fontSize: 11,
    color: '#94a3b8',
    marginTop: 2,
  },
  calWeekRow: {
    flexDirection: 'row',
    backgroundColor: '#f9fafb',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  calWeekCell: {
    width: '14.285714%',
    textAlign: 'center',
    paddingVertical: 8,
    fontSize: 11,
    fontWeight: '600',
    color: '#9ca3af',
  },
  calGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: 4,
    paddingVertical: 6,
  },
  calCell: {
    width: '14.285714%',
    paddingVertical: 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  calDayCircle: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
  },
  calDayHasCheckin: {
    backgroundColor: '#16a34a',
  },
  calDaySelected: {
    backgroundColor: '#f97316',
  },
  calDayToday: {
    borderWidth: 2,
    borderColor: '#f97316',
  },
  calDayText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
  },
  calDayTextFaded: {
    color: '#d1d5db',
    fontWeight: '400',
  },
  calDayTextOnCheckin: {
    color: '#fff',
    fontWeight: '700',
  },
  calDayTextOnSelected: {
    color: '#fff',
    fontWeight: '800',
  },
  calDayTextToday: {
    color: '#f97316',
    fontWeight: '800',
  },
  calLoading: {
    paddingVertical: 40,
    alignItems: 'center',
  },
  calLegend: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderTopWidth: 1,
    borderTopColor: '#f3f4f6',
  },
  calLegendDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
  },
  calLegendText: {
    fontSize: 11,
    color: '#6b7280',
    marginRight: 8,
  },
  calDetailCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    marginBottom: 12,
    overflow: 'hidden',
  },
  calDetailHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    backgroundColor: '#fff7ed',
    borderBottomWidth: 1,
    borderBottomColor: '#fed7aa',
  },
  calDetailTitle: {
    flex: 1,
    fontSize: 13,
    fontWeight: '700',
    color: '#111827',
    textTransform: 'capitalize',
  },
  calDetailEmpty: {
    paddingVertical: 24,
    alignItems: 'center',
  },
  calDetailEmptyText: {
    fontSize: 13,
    color: '#9ca3af',
  },
  calCheckinItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    padding: 10,
  },
  calCheckinIcon: {
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  calCheckinModalidade: {
    fontSize: 13,
    fontWeight: '700',
    color: '#111827',
  },
  calCheckinHorario: {
    fontSize: 11,
    color: '#6b7280',
    marginTop: 2,
  },
  calCheckinId: {
    backgroundColor: '#f1f5f9',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  calCheckinIdText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#475569',
  },
});
