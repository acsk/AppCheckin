import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  Image,
  Pressable,
  TextInput,
  ActivityIndicator,
  Alert,
  Platform,
  ToastAndroid,
  useWindowDimensions,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import { superAdminService } from '../../services/superAdminService';
import { buscarDashboard, buscarDashboardCards } from '../../services/dashboardService';
import { DASHBOARD_CARDS_DEFAULT, DashboardCardsData } from '../../models';
import LayoutBase from '../../components/LayoutBase';
import styles, { MENU, KPI, BAR_VALUES, MONTHS, FEED, CAL_DAYS, CAL_NUMS } from './styles';
import LoaderOverlay from './components/LoaderOverlay';
import AcademiaList from './components/AcademiaList';
import AcademiaForm from './components/AcademiaForm';

// Componente auxiliar para gráfico de check-ins
const CheckinsChart = ({ data }) => {
  if (!data || data.length === 0) {
    return (
      <View style={{ padding: 40, alignItems: 'center', justifyContent: 'center', height: 200 }}>
        <MaterialCommunityIcons name="chart-bar" size={48} color="#cbd5e1" />
        <Text style={{ marginTop: 12, color: '#94a3b8', fontSize: 14 }}>Nenhum check-in registrado</Text>
      </View>
    );
  }

  const maxValue = Math.max(...data.map(d => d.total), 1);

  return (
    <View style={{ padding: 16, height: 200 }}>
      <View style={{ flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', height: 150, gap: 8 }}>
        {data.map((item) => {
          const heightPercent = (item.total / maxValue) * 100;
          const dateObj = new Date(item.data + 'T00:00:00');
          const dayName = dateObj.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');
          
          return (
            <View key={item.data} style={{ flex: 1, alignItems: 'center', gap: 4 }}>
              <View style={{ flex: 1, width: '100%', justifyContent: 'flex-end' }}>
                <View 
                  style={{ 
                    width: '100%', 
                    backgroundColor: '#a855f7',
                    borderTopLeftRadius: 8,
                    borderTopRightRadius: 8,
                    height: `${Math.max(heightPercent, 5)}%`,
                  }}
                />
              </View>
              <Text style={{ fontSize: 11, fontWeight: '700', color: '#1e293b' }}>{item.total}</Text>
              <Text style={{ fontSize: 10, fontWeight: '600', color: '#64748b' }}>{dayName}</Text>
            </View>
          );
        })}
      </View>
    </View>
  );
};

// Componente auxiliar para linha de estatística
const StatRow = ({ icon, label, value }) => (
  <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 8 }}>
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
      <MaterialCommunityIcons name={icon} size={18} color="#64748b" />
      <Text style={{ fontSize: 13, color: '#64748b' }}>{label}</Text>
    </View>
    <Text style={{ fontSize: 14, fontWeight: '700', color: '#1e293b' }}>{value}</Text>
  </View>
);

export default function Dashboard() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isSmallScreen = width < 768;
  const isMediumScreen = width >= 768 && width < 1024;
  const [usuarioInfo, setUsuarioInfo] = useState(null);
  const nome = usuarioInfo?.nome || 'Usuário';
  const email = usuarioInfo?.email || '';

  const [active, setActive] = useState('dashboard');
  const [showForm, setShowForm] = useState(false);
  const [academias, setAcademias] = useState([]);
  const [loadingAcademias, setLoadingAcademias] = useState(false);
  
  // Estados do Dashboard
  const [dashboardData, setDashboardData] = useState(null);
  /** @type {[DashboardCardsData | null, React.Dispatch<React.SetStateAction<DashboardCardsData | null>>]} */
  const [cardsData, setCardsData] = useState(null);
  const [loadingDashboard, setLoadingDashboard] = useState(false);
  
  const [saving, setSaving] = useState(false);
  const [editAcademia, setEditAcademia] = useState(null);
  const [novaAcademia, setNovaAcademia] = useState({
    nome: '',
    email: '',
    telefone: '',
    endereco: '',
  });

  useEffect(() => {
    const checkAuth = async () => {
      try {
        const user = await authService.getCurrentUser();
        if (!user) {
          console.log('⚠️ Usuário não autenticado, redirecionando para login...');
          router.replace('/login');
          return;
        }
        console.log('✅ Usuário autenticado:', user);
        setUsuarioInfo(user);
        
        // Carregar dados do dashboard (disponível para admin)
        carregarDadosDashboard();
        
        // Carregar academias apenas para super_admin (papel_id = 4)
        if (user.papel_id === 4) {
          carregarAcademias();
        }
      } catch (error) {
        console.error('❌ Erro ao verificar autenticação:', error);
        router.replace('/login');
      }
    };
    
    checkAuth();
  }, []);

  const carregarAcademias = async () => {
    setLoadingAcademias(true);
    try {
      const data = await superAdminService.listarAcademias();
      const lista = Array.isArray(data?.academias) ? data.academias : Array.isArray(data) ? data : [];
      setAcademias(lista);
    } catch (error) {
      Alert.alert('Erro', 'Não foi possível carregar academias');
    } finally {
      setLoadingAcademias(false);
    }
  };

  const carregarDadosDashboard = async () => {
    setLoadingDashboard(true);
    try {
      // Buscar dados em paralelo
      const [dashboard, cards] = await Promise.all([
        buscarDashboard().catch(err => {
          console.error('Erro ao buscar dashboard:', err);
          return { data: null };
        }),
        buscarDashboardCards().catch(err => {
          console.error('Erro ao buscar cards:', err);
          return { data: null };
        }),
      ]);
      
      setDashboardData(dashboard?.data || dashboard);
      setCardsData(cards?.data || cards);
    } catch (error) {
      console.error('Erro geral ao carregar dashboard:', error);
    } finally {
      setLoadingDashboard(false);
    }
  };

  const carregarAcademia = async (id) => {
    setLoadingAcademias(true);
    try {
      const data = await superAdminService.buscarAcademia(id);
      const acad = data?.academia || data;
      if (acad) {
        setEditAcademia(acad);
        setNovaAcademia({
          nome: acad.nome || '',
          email: acad.email || '',
          telefone: acad.telefone || '',
          endereco: acad.endereco || '',
        });
        setShowForm(true);
      }
    } catch (error) {
      Alert.alert('Erro', 'Não foi possível carregar a academia');
    } finally {
      setLoadingAcademias(false);
    }
  };

  const salvarAcademia = async () => {
    const isEdit = Boolean(editAcademia?.id || editAcademia?.tenant_id);
    const acadId = editAcademia?.id || editAcademia?.tenant_id;
    const acao = isEdit ? 'salvar as alterações' : 'cadastrar a academia';
    const confirma = await confirmarAcao('Confirmação', `Deseja ${acao}?`);
    if (!confirma) return;

    if (!novaAcademia.nome.trim() || !novaAcademia.email.trim()) {
      Alert.alert('Atenção', 'Informe nome e email');
      return;
    }
    setSaving(true);
    try {
      if (isEdit && acadId) {
        await superAdminService.atualizarAcademia(acadId, novaAcademia);
        showToast('Academia atualizada');
      } else {
        await superAdminService.criarAcademia(novaAcademia);
        showToast('Academia cadastrada');
      }
      setNovaAcademia({ nome: '', email: '', telefone: '', endereco: '' });
      setEditAcademia(null);
      await carregarAcademias();
      Alert.alert('Sucesso', isEdit ? 'Academia atualizada' : 'Academia cadastrada');
      setShowForm(false);
    } catch (error) {
      const msg = error?.errors?.[0] || error?.error || 'Não foi possível salvar';
      Alert.alert('Erro', msg);
    } finally {
      setSaving(false);
    }
  };

  const confirmarAcao = (title, message) =>
    new Promise((resolve) => {
      Alert.alert(title, message, [
        { text: 'Cancelar', style: 'cancel', onPress: () => resolve(false) },
        { text: 'Confirmar', onPress: () => resolve(true) },
      ]);
    });

  const showToast = (message) => {
    if (!message) return;
    if (Platform.OS === 'android') {
      ToastAndroid.show(message, ToastAndroid.SHORT);
    } else {
      Alert.alert('', message);
    }
  };

  const excluirAcademia = async (acad) => {
    const acadId = acad?.id || acad?.tenant_id;
    if (!acadId) return;
    const confirma = await confirmarAcao(
      'Confirmação',
      `Deseja realmente excluir a academia ${acad?.nome || ''}?`
    );
    if (!confirma) return;

    setSaving(true);
    try {
      await superAdminService.excluirAcademia(acadId);
      showToast('Academia excluída');
      if (editAcademia && acadId === (editAcademia.id || editAcademia.tenant_id)) {
        setEditAcademia(null);
        setShowForm(false);
        setNovaAcademia({ nome: '', email: '', telefone: '', endereco: '' });
      }
      await carregarAcademias();
    } catch (error) {
      const msg = error?.error || error?.errors?.[0] || 'Não foi possível excluir';
      Alert.alert('Erro', msg);
    } finally {
      setSaving(false);
    }
  };

  const handleLogout = async () => {
    await authService.logout();
    router.replace('/login');
  };

  const renderAcademias = () => (
    <View style={styles.acadWrap}>
      <Text style={styles.acadTitle}>Academias</Text>
      <Text style={styles.acadSub}>Gerencie o cadastro de academias</Text>

      <AcademiaList
        academias={academias}
        loading={loadingAcademias}
        onNovo={() => router.push('/academias/novo')}
        onEditar={(acad) => {
          router.push(`/academias/${acad.id || acad.tenant_id}`);
        }}
        onExcluir={excluirAcademia}
      />
    </View>
  );

  const renderAcademiaForm = () => (
    <View style={styles.acadWrap}>
      <View style={styles.acadHeader}>
        <View>
          <Text style={styles.acadTitle}>{editAcademia ? 'Editar academia' : 'Nova academia'}</Text>
          <Text style={styles.acadSub}>Preencha os campos obrigatórios</Text>
        </View>
        <Pressable style={styles.iconChip} onPress={() => setShowForm(false)}>
          <Feather name="arrow-left" size={16} color="#2b1a04" />
        </Pressable>
      </View>

      <AcademiaForm
        values={novaAcademia}
        onChange={setNovaAcademia}
        onSubmit={salvarAcademia}
        saving={saving}
        isEditing={Boolean(editAcademia)}
      />
    </View>
  );

  const renderDashboard = () => {
    // Usar dados do endpoint /cards com fallback para o default
    const cards = cardsData || DASHBOARD_CARDS_DEFAULT;
    
    const totalAlunos = cards.total_alunos.total;
    const alunosAtivos = cards.total_alunos.ativos;
    const alunosInativos = cards.total_alunos.inativos;
    const novosAlunosMes = cards.planos_vencendo.novos_este_mes;
    const checkinsHoje = cards.checkins_hoje.hoje;
    const checkinsMes = cards.checkins_hoje.no_mes;
    const planosVencendo = cards.planos_vencendo.vencendo;
    const receitaMensal = cards.receita_mensal.valor;
    const receitaMensalFormatada = cards.receita_mensal.valor_formatado;
    const contasPendentesQtd = cards.receita_mensal.contas_pendentes;
    const contasPendentesValor = dashboardData?.contas_pendentes_valor ?? 0;
    const contasVencidasQtd = dashboardData?.contas_vencidas_qtd ?? 0;
    const contasVencidasValor = dashboardData?.contas_vencidas_valor ?? 0;
    
    const totalTurmas = dashboardData?.turmas ?? 0;
    const professores = dashboardData?.professores ?? 0;
    const modalidades = dashboardData?.modalidades ?? 0;

    // Formatar moeda
    const formatMoney = (value) => {
      return new Intl.NumberFormat('pt-BR', { 
        style: 'currency', 
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(value);
    };

    // Cards de métricas principais
    const metricsCards = [
      { 
        title: 'Total de Alunos', 
        value: totalAlunos.toString(), 
        subtitle: `${alunosAtivos} ativos, ${alunosInativos} inativos`,
        icon: 'users',
        color: '#3b82f6',
        bgColor: '#eff6ff'
      },
      { 
        title: 'Receita Mensal', 
        value: receitaMensalFormatada || formatMoney(receitaMensal), 
        subtitle: `${contasPendentesQtd} contas pendentes`,
        icon: 'dollar-sign',
        color: '#10b981',
        bgColor: '#f0fdf4'
      },
      { 
        title: 'Check-ins Hoje', 
        value: checkinsHoje.toString(), 
        subtitle: `${checkinsMes} no mês`,
        icon: 'check-circle',
        color: '#f59e0b',
        bgColor: '#fffbeb'
      },
      { 
        title: 'Planos Vencendo', 
        value: planosVencendo.toString(), 
        subtitle: `${novosAlunosMes} novos este mês`,
        icon: 'alert-circle',
        color: '#ef4444',
        bgColor: '#fef2f2'
      },
    ];

    return (
      <ScrollView style={{ flex: 1, backgroundColor: '#f9fafb' }} showsVerticalScrollIndicator={false}>
        {loadingDashboard ? (
          <View style={{ padding: 60, alignItems: 'center' }}>
            <ActivityIndicator size="large" color="#3b82f6" />
            <Text style={{ marginTop: 16, color: '#64748b', fontSize: 14 }}>Carregando dados...</Text>
          </View>
        ) : (
          <View style={{ padding: isSmallScreen ? 16 : 24, gap: isSmallScreen ? 16 : 24 }}>
            {/* Metrics Cards */}
            <View style={{ 
              flexDirection: isSmallScreen ? 'column' : 'row', 
              flexWrap: 'wrap',
              gap: isSmallScreen ? 12 : 16 
            }}>
              {metricsCards.map((card, index) => (
                <View 
                  key={index} 
                  style={{ 
                    flex: isSmallScreen ? undefined : 1,
                    minWidth: isSmallScreen ? '100%' : isMediumScreen ? '48%' : undefined,
                    backgroundColor: '#ffffff', 
                    borderRadius: 12, 
                    padding: isSmallScreen ? 16 : 20,
                    shadowColor: '#000',
                    shadowOffset: { width: 0, height: 1 },
                    shadowOpacity: 0.05,
                    shadowRadius: 3,
                    elevation: 2,
                  }}
                >
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 }}>
                    <Text style={{ fontSize: 13, color: '#64748b', fontWeight: '500' }}>{card.title}</Text>
                    <View style={{ 
                      width: 32, 
                      height: 32, 
                      borderRadius: 8, 
                      backgroundColor: card.bgColor,
                      alignItems: 'center',
                      justifyContent: 'center'
                    }}>
                      <Feather name={card.icon} size={16} color={card.color} />
                    </View>
                  </View>
                  <Text style={{ fontSize: isSmallScreen ? 22 : 28, fontWeight: '700', color: '#1e293b', marginBottom: 8 }}>
                    {card.value}
                  </Text>
                  <Text style={{ fontSize: 11, color: '#94a3b8' }}>
                    {card.subtitle}
                  </Text>
                </View>
              ))}
            </View>

            {/* Contas a Receber e Estatísticas de Alunos */}
            <View style={{ flexDirection: isSmallScreen ? 'column' : 'row', gap: isSmallScreen ? 12 : 16 }}>
              {/* Contas Card */}
              <View style={{ 
                flex: isSmallScreen ? undefined : 1, 
                backgroundColor: '#ffffff', 
                borderRadius: 12, 
                padding: isSmallScreen ? 16 : 24,
                shadowColor: '#000',
                shadowOffset: { width: 0, height: 1 },
                shadowOpacity: 0.05,
                shadowRadius: 3,
                elevation: 2,
              }}>
                <Text style={{ fontSize: 16, fontWeight: '700', color: '#1e293b', marginBottom: 20 }}>
                  Contas a Receber
                </Text>
                <View style={{ gap: 16 }}>
                  <View>
                    <Text style={{ fontSize: 11, color: '#64748b', marginBottom: 8 }}>Contas Pendentes</Text>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                      <Text style={{ fontSize: 24, fontWeight: '700', color: '#f59e0b' }}>
                        {contasPendentesQtd}
                      </Text>
                      <Text style={{ fontSize: 14, fontWeight: '600', color: '#64748b' }}>
                        {formatMoney(contasPendentesValor)}
                      </Text>
                    </View>
                  </View>
                  
                  <View style={{ height: 1, backgroundColor: '#f1f5f9' }} />
                  
                  <View>
                    <Text style={{ fontSize: 11, color: '#64748b', marginBottom: 8 }}>Contas Vencidas</Text>
                    <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                      <Text style={{ fontSize: 24, fontWeight: '700', color: '#ef4444' }}>
                        {contasVencidasQtd}
                      </Text>
                      <Text style={{ fontSize: 14, fontWeight: '600', color: '#64748b' }}>
                        {formatMoney(contasVencidasValor)}
                      </Text>
                    </View>
                  </View>

                  <View style={{ height: 1, backgroundColor: '#f1f5f9' }} />

                  <View>
                    <Text style={{ fontSize: 11, color: '#64748b', marginBottom: 8 }}>Receita Mensal</Text>
                    <Text style={{ fontSize: 24, fontWeight: '700', color: '#10b981' }}>
                      {formatMoney(receitaMensal)}
                    </Text>
                  </View>
                </View>

                <Pressable 
                  onPress={carregarDadosDashboard}
                  style={{ 
                    marginTop: 20, 
                    padding: 12, 
                    backgroundColor: '#f1f5f9', 
                    borderRadius: 8,
                    alignItems: 'center'
                  }}
                >
                  <Text style={{ fontSize: 12, color: '#3b82f6', fontWeight: '600' }}>
                    Atualizar Dados
                  </Text>
                </Pressable>
              </View>

              {/* Alunos Stats */}
              <View style={{ 
                flex: isSmallScreen ? undefined : 1.2, 
                backgroundColor: '#ffffff', 
                borderRadius: 12, 
                padding: isSmallScreen ? 16 : 24,
                shadowColor: '#000',
                shadowOffset: { width: 0, height: 1 },
                shadowOpacity: 0.05,
                shadowRadius: 3,
                elevation: 2,
              }}>
                <Text style={{ fontSize: 16, fontWeight: '700', color: '#1e293b', marginBottom: 20 }}>
                  Estatísticas de Alunos
                </Text>
                
                {/* Donut Chart Placeholder */}
                <View style={{ alignItems: 'center', marginBottom: 20 }}>
                  <View style={{ 
                    width: isSmallScreen ? 100 : 120, 
                    height: isSmallScreen ? 100 : 120, 
                    borderRadius: isSmallScreen ? 50 : 60,
                    borderWidth: isSmallScreen ? 12 : 16,
                    borderColor: '#3b82f6',
                    borderRightColor: '#cbd5e1',
                    borderBottomColor: '#cbd5e1',
                    alignItems: 'center',
                    justifyContent: 'center',
                    transform: [{ rotate: '-90deg' }]
                  }}>
                    <View style={{ transform: [{ rotate: '90deg' }] }}>
                      <Text style={{ fontSize: isSmallScreen ? 22 : 28, fontWeight: '700', color: '#1e293b' }}>
                        {totalAlunos}
                      </Text>
                    </View>
                  </View>
                </View>

                {/* Stats */}
                <View style={{ gap: 12 }}>
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                      <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: '#3b82f6' }} />
                      <Text style={{ fontSize: 12, color: '#64748b' }}>Alunos Ativos</Text>
                    </View>
                    <Text style={{ fontSize: 16, fontWeight: '700', color: '#1e293b' }}>
                      {alunosAtivos}
                    </Text>
                  </View>

                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                      <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: '#cbd5e1' }} />
                      <Text style={{ fontSize: 12, color: '#64748b' }}>Alunos Inativos</Text>
                    </View>
                    <Text style={{ fontSize: 16, fontWeight: '700', color: '#1e293b' }}>
                      {alunosInativos}
                    </Text>
                  </View>

                  <View style={{ height: 1, backgroundColor: '#f1f5f9', marginVertical: 4 }} />

                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Text style={{ fontSize: 12, color: '#64748b' }}>Novos este mês</Text>
                    <Text style={{ fontSize: 16, fontWeight: '700', color: '#10b981' }}>
                      +{novosAlunosMes}
                    </Text>
                  </View>
                </View>
              </View>
            </View>

            {/* Quick Actions */}
            <View style={{ 
              flexDirection: isSmallScreen ? 'column' : 'row', 
              gap: isSmallScreen ? 12 : 16 
            }}>
              <Pressable 
                onPress={() => router.push('/turmas')}
                style={{ 
                  flex: isSmallScreen ? undefined : 1,
                  backgroundColor: '#3b82f6',
                  borderRadius: 12,
                  padding: isSmallScreen ? 16 : 20,
                  alignItems: 'center',
                  flexDirection: isSmallScreen ? 'row' : 'column',
                  gap: isSmallScreen ? 12 : 0,
                  shadowColor: '#3b82f6',
                  shadowOffset: { width: 0, height: 4 },
                  shadowOpacity: 0.3,
                  shadowRadius: 8,
                  elevation: 4,
                }}
              >
                <Feather name="calendar" size={24} color="#ffffff" />
                <Text style={{ marginTop: isSmallScreen ? 0 : 8, color: '#ffffff', fontSize: 14, fontWeight: '600' }}>
                  Gerenciar Turmas
                </Text>
              </Pressable>

              <Pressable 
                onPress={() => router.push('/usuarios')}
                style={{ 
                  flex: isSmallScreen ? undefined : 1,
                  backgroundColor: '#10b981',
                  borderRadius: 12,
                  padding: isSmallScreen ? 16 : 20,
                  alignItems: 'center',
                  flexDirection: isSmallScreen ? 'row' : 'column',
                  gap: isSmallScreen ? 12 : 0,
                  shadowColor: '#10b981',
                  shadowOffset: { width: 0, height: 4 },
                  shadowOpacity: 0.3,
                  shadowRadius: 8,
                  elevation: 4,
                }}
              >
                <Feather name="users" size={24} color="#ffffff" />
                <Text style={{ marginTop: isSmallScreen ? 0 : 8, color: '#ffffff', fontSize: 14, fontWeight: '600' }}>
                  Ver Alunos
                </Text>
              </Pressable>

              <Pressable 
                onPress={() => router.push('/contratos')}
                style={{ 
                  flex: isSmallScreen ? undefined : 1,
                  backgroundColor: '#f59e0b',
                  borderRadius: 12,
                  padding: isSmallScreen ? 16 : 20,
                  alignItems: 'center',
                  flexDirection: isSmallScreen ? 'row' : 'column',
                  gap: isSmallScreen ? 12 : 0,
                  shadowColor: '#f59e0b',
                  shadowOffset: { width: 0, height: 4 },
                  shadowOpacity: 0.3,
                  shadowRadius: 8,
                  elevation: 4,
                }}
              >
                <Feather name="file-text" size={24} color="#ffffff" />
                <Text style={{ marginTop: isSmallScreen ? 0 : 8, color: '#ffffff', fontSize: 14, fontWeight: '600' }}>
                  Contratos
                </Text>
              </Pressable>
            </View>
          </View>
        )}
      </ScrollView>
    );
  };

  return (
    <LayoutBase title="Dashboard" subtitle="Overview">
      {renderDashboard()}
      {(loadingAcademias || saving) && <LoaderOverlay />}
    </LayoutBase>
  );
}

function BarChart() {
  const max = Math.max(...BAR_VALUES);
  return (
    <View style={styles.chart}>
      <View style={styles.bars}>
        {BAR_VALUES.map((v, i) => (
          <View key={i} style={[styles.bar, { height: `${(v / max) * 100}%` }]} />
        ))}
      </View>
      <View style={styles.months}>
        {MONTHS.map((m) => (
          <Text key={m} style={styles.monthText}>
            {m}
          </Text>
        ))}
      </View>
    </View>
  );
}

function Appt({ title, desc }) {
  return (
    <View style={styles.apptRow}>
      <View style={styles.apptDot} />
      <View style={{ flex: 1 }}>
        <Text style={styles.apptTitle}>{title}</Text>
        <Text style={styles.apptDesc}>{desc}</Text>
      </View>
    </View>
  );
}
