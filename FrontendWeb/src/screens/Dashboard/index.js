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
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { authService } from '../../services/authService';
import { superAdminService } from '../../services/superAdminService';
import styles, { MENU, KPI, BAR_VALUES, MONTHS, FEED, CAL_DAYS, CAL_NUMS } from './styles';
import LoaderOverlay from './components/LoaderOverlay';
import AcademiaList from './components/AcademiaList';
import AcademiaForm from './components/AcademiaForm';

export default function Dashboard() {
  const router = useRouter();
  const [usuarioInfo, setUsuarioInfo] = useState(null);
  const nome = usuarioInfo?.nome || 'Usuário';
  const email = usuarioInfo?.email || '';

  const [active, setActive] = useState('dashboard');
  const [showForm, setShowForm] = useState(false);
  const [academias, setAcademias] = useState([]);
  const [loadingAcademias, setLoadingAcademias] = useState(false);
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

  const renderDashboard = () => (
    <>
      <View style={styles.kpiRow}>
        {KPI.map((k) => (
          <View key={k.title} style={[styles.card, styles.kpiCard]}>
            <View>
              <Text style={styles.kpiTitle}>{k.title}</Text>
              <Text style={styles.kpiSub}>{k.subtitle}</Text>
              {k.delta ? (
                <View style={styles.deltaPill}>
                  <Text style={styles.deltaText}>{k.delta}</Text>
                </View>
              ) : null}
            </View>
            <Feather name={k.icon} size={28} color="#2b1a04" />
          </View>
        ))}
      </View>

      <View style={styles.rowTwo}>
        <View style={[styles.card, styles.chartCard]}>
          <View style={styles.cardHead}>
            <Text style={styles.cardHeadTitle}>Sales / Revenue</Text>
            <Feather name="more-horizontal" size={18} color="#2b1a04" />
          </View>
          <BarChart />
        </View>

        <View style={[styles.card, styles.feedCard]}>
          <View style={styles.cardHead}>
            <Text style={styles.cardHeadTitle}>Daily feed</Text>
            <View style={styles.arrows}>
              <View style={styles.iconChip}>
                <Feather name="chevron-left" size={16} color="#2b1a04" />
              </View>
              <View style={styles.iconChip}>
                <Feather name="chevron-right" size={16} color="#2b1a04" />
              </View>
            </View>
          </View>
          <View style={styles.feed}>
            {FEED.map((f) => (
              <View key={f.line} style={styles.feedItem}>
                <View style={styles.feedAvatar}>
                  <Text style={styles.feedAvatarText}>{f.avatar}</Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.feedLine}>{f.line}</Text>
                  {f.note ? <Text style={styles.feedNote}>{f.note}</Text> : null}
                  <Text style={styles.feedTime}>{f.time}</Text>
                </View>
                <Text style={styles.feedRight}>{f.right}</Text>
              </View>
            ))}
            <Pressable style={styles.btnGradient}>
              <Text style={styles.btnGradientText}>Load more</Text>
            </Pressable>
          </View>
        </View>
      </View>

      <View style={styles.rowThree}>
        <View style={[styles.card, styles.calendarCard]}>
          <View style={styles.cardHead}>
            <Text style={styles.cardHeadTitle}>Calendar</Text>
            <Feather name="more-horizontal" size={18} color="#2b1a04" />
          </View>
          <View style={styles.calendar}>
            <View style={styles.calHeader}>
              <Text style={styles.calMonth}>November 2020</Text>
            </View>
            <View style={styles.calGrid}>
              {CAL_DAYS.map((d) => (
                <Text key={d} style={styles.calDay}>
                  {d}
                </Text>
              ))}
              {CAL_NUMS.map((n, idx) => (
                <Text key={idx} style={[styles.calNum, idx < 1 || idx >= 30 ? styles.calMuted : null]}>
                  {n}
                </Text>
              ))}
            </View>
          </View>
        </View>

        <View style={[styles.card, styles.donutCard]}>
          <View style={styles.cardHead}>
            <Text style={styles.cardHeadTitle}>Weekly sales</Text>
            <Feather name="more-horizontal" size={18} color="#2b1a04" />
          </View>
          <View style={styles.donutWrap}>
            <View style={styles.donut}>
              <View style={styles.donutHole}>
                <Text style={styles.donutValue}>68%</Text>
                <Text style={styles.donutMuted}>Target</Text>
              </View>
            </View>
          </View>
        </View>

        <View style={[styles.card, styles.apptCard]}>
          <View style={styles.cardHead}>
            <Text style={styles.cardHeadTitle}>Appointments</Text>
            <Feather name="more-horizontal" size={18} color="#2b1a04" />
          </View>
          <View style={styles.apptList}>
            <Appt title="Chat with Carl and Ashley" desc="Nam premium turiple et, omcu. Duis arcu tortor..." />
            <Appt title="The Big launch" desc="Nam a consed sidan ceèl, imperta tacilus..." />
          </View>
        </View>
      </View>
    </>
  );

  return (
    <View style={styles.app}>
      <View style={styles.sidebar}>
        <View style={styles.brand}>
          <Image source={require('../../../assets/img/logo.png')} style={styles.logo} />
          <Text style={styles.brandSub}>CHECK-IN</Text>
        </View>

        <View style={styles.menu}>
          {MENU.map((item) => {
            const selected = active === item.key;
            return (
              <Pressable
                key={item.label}
                onPress={() => {
                  setShowForm(false);
                  if (item.key === 'academias') {
                    router.push('/academias');
                  } else {
                    router.push('/');
                  }
                }}
                style={[styles.menuItem, selected && styles.menuItemActive]}
              >
                <View style={styles.menuItemLeft}>
                  <Feather name={item.icon} size={16} color={selected ? '#fff' : '#d1d5db'} />
                  <Text style={[styles.menuText, selected && styles.menuTextActive]}>{item.label}</Text>
                </View>
                {item.badge ? (
                  <View style={styles.badge}>
                    <Text style={styles.badgeText}>{item.badge}</Text>
                  </View>
                ) : null}
              </Pressable>
            );
          })}
        </View>

    </View>

      <LinearGradient
        colors={['#f1e7d9ff', '#a0a0a0ff', '#f3eaf8ff']}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 0 }}
        style={styles.main}
      >
        <View style={styles.topbar}>
          <View>
            <Text style={styles.topTitle}>Dashboard</Text>
            <Text style={styles.topSubtitle}>Overview</Text>
          </View>
          <View style={styles.topRight}>
            <View style={styles.iconChip}>
              <Feather name="bell" size={16} color="#2b1a04" />
            </View>
            <View style={styles.iconChip}>
              <Feather name="settings" size={16} color="#2b1a04" />
            </View>
            <Pressable style={styles.iconChip} onPress={handleLogout}>
              <Feather name="log-out" size={16} color="#2b1a04" />
            </Pressable>
            <View style={styles.flag}>
              <Text style={styles.flagText}>🇺🇸</Text>
            </View>
            <Text style={styles.profileName}>{nome}</Text>
            <View style={styles.profileAvatar}>
              <Text style={styles.avatarText}>{nome.slice(0, 2).toUpperCase()}</Text>
            </View>
          </View>
        </View>

        <ScrollView contentContainerStyle={styles.content}>
          {active === 'academias' ? (showForm ? renderAcademiaForm() : renderAcademias()) : renderDashboard()}
        </ScrollView>
        {(loadingAcademias || saving) && <LoaderOverlay />}
      </LinearGradient>
    </View>
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
