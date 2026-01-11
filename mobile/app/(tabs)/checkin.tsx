import { Feather } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useEffect, useRef, useState } from 'react';
import {
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors } from '../../src/theme/colors';
import { normalizeUtf8 } from '../../src/utils/utf8';

export default function CheckinScreen() {
  const [selectedDate, setSelectedDate] = useState<Date>(new Date());
  const [availableSchedules, setAvailableSchedules] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [calendarDays, setCalendarDays] = useState<Date[]>([]);
  const [participantsLoading, setParticipantsLoading] = useState(false);
  const [participants, setParticipants] = useState<any[]>([]);
  const [participantsTurma, setParticipantsTurma] = useState<any | null>(null);
  const [checkinLoading, setCheckinLoading] = useState(false);
  const [checkinsRecentes, setCheckinsRecentes] = useState<any[]>([]);
  const [resumoTurma, setResumoTurma] = useState<any | null>(null);
  const [alunosTotal, setAlunosTotal] = useState<number>(0);
  const [toastVisible, setToastVisible] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: 'info' | 'success' | 'error' | 'warning' }>({ message: '', type: 'info' });
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const mergeTurmaFromList = (turmaId: number | string, fallback: any = null) => {
    const fromList = availableSchedules.find((t) => String(t.id) === String(turmaId));
    if (fromList && fallback) return { ...fallback, ...fromList };
    if (fromList) return fromList;
    return fallback;
  };

  const getInitials = (nome: string = '') => {
    const parts = normalizeUtf8(nome).split(' ').filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  };

  const showToast = (message: string, type: 'info' | 'success' | 'error' | 'warning' = 'info', duration = 3500) => {
    const msg = normalizeUtf8(String(message || ''));
    if (toastTimer.current) {
      clearTimeout(toastTimer.current);
      toastTimer.current = null;
    }
    setToast({ message: msg, type });
    setToastVisible(true);
    toastTimer.current = setTimeout(() => {
      setToastVisible(false);
      toastTimer.current = null;
    }, duration);
  };

  useEffect(() => {
    console.log('\n🚀 CHECKIN SCREEN MONTADO');
    generateCalendarDays();
  }, []);

  useEffect(() => {
    return () => {
      if (toastTimer.current) {
        clearTimeout(toastTimer.current);
      }
    };
  }, []);

  useEffect(() => {
    console.log('📅 DATA SELECIONADA MUDOU:', selectedDate);
    // Quando a data muda, limpa os detalhes primeiro
    setParticipantsTurma(null);
    setParticipants([]);
    setCheckinsRecentes([]);
    setResumoTurma(null);
    setAlunosTotal(0);
    // Depois carrega os horários
    if (selectedDate) {
      fetchAvailableSchedules(selectedDate);
    }
  }, [selectedDate]);

  const generateCalendarDays = () => {
    console.log('📅 GERANDO CALENDÁRIO');
    const today = new Date();
    console.log('   Data hoje:', today);
    const days: Date[] = [];
    
    for (let i = 0; i < 7; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);
      days.push(date);
    }
    console.log('   Dias gerados:', days.length);
    setCalendarDays(days);
  };

  const formatDateParam = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const fetchAvailableSchedules = async (date: Date) => {
    setLoading(true);
    try {
      console.log('\n🔄 INICIANDO CARREGAMENTO DE HORÁRIOS');
      
      const token = await AsyncStorage.getItem('@appcheckin:token');
      if (!token) {
        console.error('❌ Token não encontrado');
        showToast('Token não encontrado', 'error');
        return;
      }
      console.log('✅ Token encontrado:', token.substring(0, 20) + '...');

      const formattedDate = formatDateParam(date);
      console.log('📅 Data formatada:', formattedDate);

      const url = `http://localhost:8080/mobile/horarios-disponiveis?data=${formattedDate}`;
      console.log('📍 URL:', url);

      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      console.log('📡 RESPOSTA DO SERVIDOR');
      console.log('   Status:', response.status);
      console.log('   Status Text:', response.statusText);
      console.log('   Content-Type:', response.headers.get('content-type'));

      const responseText = await response.text();
      console.log('   Response Text (primeiros 500 chars):', responseText.substring(0, 500));

      if (!response.ok) {
        console.error('❌ ERRO NA REQUISIÇÃO');
        console.error('   Status:', response.status);
        console.error('   Body completo:', responseText);
        throw new Error(`HTTP ${response.status}: ${responseText}`);
      }

      let data;
      try {
        data = JSON.parse(responseText);
        console.log('✅ JSON parseado com sucesso');
      } catch (parseError) {
        console.error('❌ ERRO AO FAZER PARSE DO JSON');
        if (parseError instanceof Error) {
          console.error('   Erro:', parseError.message);
        }
        console.error('   Response recebida:', responseText.substring(0, 200));
        throw parseError;
      }

      console.log('   Response completa:', JSON.stringify(data, null, 2));

      if (data.success && data.data?.turmas) {
        console.log('✅ Turmas carregadas com sucesso');
        console.log('   Quantidade:', data.data.turmas.length);
        console.log('   Total de turmas:', data.data.total);
        data.data.turmas.forEach((turma: any, index: number) => {
          console.log(`   [${index + 1}] ${turma.nome}`);
          console.log(`       Modalidade: ${turma.modalidade?.nome}`);
          console.log(`       Horário: ${turma.horario.inicio} - ${turma.horario.fim}`);
          console.log(`       Vagas: ${turma.alunos_inscritos}/${turma.limite_alunos}`);
        });
        setAvailableSchedules(data.data.turmas);
      } else {
        console.warn('⚠️ Resposta inválida ou sem turmas');
        console.log('   success:', data.success);
        console.log('   turmas:', data.data?.turmas);
        setAvailableSchedules([]);
      }
    } catch (error) {
      console.error('❌ ERRO AO CARREGAR HORÁRIOS');
      if (error instanceof Error) {
        console.error('   Nome do erro:', error.name);
        console.error('   Mensagem:', error.message);
        console.error('   Stack:', error.stack);
      } else {
        console.error('   Erro:', error);
      }
      showToast('Falha ao carregar horários disponíveis', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleCheckin = async (turma: any) => {
    if (!turma?.id) return;
    if (isTurmaDisabled(turma, selectedDate)) {
      showToast('Este horário já foi encerrado.', 'warning');
      return;
    }

    setCheckinLoading(true);
    try {
      const token = await AsyncStorage.getItem('@appcheckin:token');
      if (!token) {
        showToast('Token não encontrado', 'error');
        return;
      }

      const payload: any = {
        turma_id: turma.id,
        data: formatDateParam(selectedDate),
      };

      if (turma.horario?.id) {
        payload.horario_id = turma.horario.id;
      }

      const response = await fetch('http://localhost:8080/mobile/checkin', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const text = await response.text();
      let data: any = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch (e) {
        data = {};
      }

      if (!response.ok) {
        const apiMessage = data?.message || data?.error || text || 'Não foi possível realizar o check-in.';
        console.warn('Erro ao registrar check-in:', response.status, apiMessage || '');

        if (String(apiMessage).toLowerCase().includes('já realizou check-in')) {
          showToast('Você já realizou check-in nesta turma.', 'warning');
        } else {
          showToast(normalizeUtf8(String(apiMessage)), 'error');
        }
        return;
      }

      if (data.success) {
        showToast(`Check-in realizado para ${normalizeUtf8(turma.nome)}`, 'success');
        await Promise.all([
          fetchAvailableSchedules(selectedDate),
          openParticipants(mergeTurmaFromList(turma.id, turma)),
        ]);
      } else {
        showToast(normalizeUtf8(data?.message || data?.error || 'Não foi possível realizar o check-in.'), 'error');
      }
    } catch (error) {
      console.error('Erro check-in:', error);
      showToast('Falha ao realizar o check-in.', 'error');
    } finally {
      setCheckinLoading(false);
    }
  };

  const openParticipants = async (turma: any) => {
    if (!turma?.id) return;
    setParticipantsLoading(true);
    setParticipants([]);
    setCheckinsRecentes([]);
    setResumoTurma(null);
    setAlunosTotal(0);
    setParticipantsTurma(mergeTurmaFromList(turma.id, turma));

    try {
      const token = await AsyncStorage.getItem('@appcheckin:token');
      if (!token) {
        showToast('Token não encontrado', 'error');
        return;
      }

      const url = `http://localhost:8080/mobile/turma/${turma.id}/detalhes`;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      const text = await response.text();
      console.log('🔎 detalhes turma raw:', text.substring(0, 400));
      if (!response.ok) {
        console.error('Erro ao carregar participantes:', response.status, text);
        showToast('Falha ao carregar participantes', 'error');
        return;
      }

      const data = JSON.parse(text);

      const payload = data.data || data; // suportar formato com ou sem wrapper
      const turmaApi = payload.turma || turma;
      const alunosLista = payload.alunos?.lista || payload.participantes || [];
      const checkinsLista = payload.checkins_recentes?.lista || [];
      const resumoData = payload.resumo || null;
      const alunosCount = payload.alunos?.total ?? alunosLista.length ?? turmaApi.alunos_inscritos ?? 0;

      console.log('🔎 detalhes turma parse:', {
        turmaId: turmaApi.id,
        alunosCount,
        alunosListaLen: alunosLista.length,
        checkinsRecentes: checkinsLista.length,
        resumoKeys: resumoData ? Object.keys(resumoData) : [],
      });

      setParticipants(alunosLista);
      setCheckinsRecentes(checkinsLista);
      setResumoTurma(resumoData);
      setAlunosTotal(alunosCount);
      setParticipantsTurma(mergeTurmaFromList(turmaApi.id, turmaApi));
    } catch (error) {
      console.error('Erro participantes:', error);
      showToast('Não foi possível carregar participantes', 'error');
    } finally {
      setParticipantsLoading(false);
    }
  };

  // Helpers para cálculo de disponibilidade por horário
  const sameDay = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();

  const combineDateTime = (date: Date, timeHHMMSS: string) => {
    const [hh, mm, ss] = (timeHHMMSS || '00:00:00').split(':').map((n) => parseInt(n, 10) || 0);
    const d = new Date(date);
    d.setHours(hh, mm, ss || 0, 0);
    return d;
  };

  const isTurmaDisabled = (turma: any, refDate: Date = selectedDate): boolean => {
    try {
      const now = new Date();
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const ref = new Date(refDate);
      ref.setHours(0, 0, 0, 0);

      // Se a data selecionada já passou, tudo desabilitado
      if (ref < today) return true;
      // Se é uma data futura, tudo habilitado
      if (ref > today) return false;

      // Mesmo dia: comparar horário atual com horário de fim da turma
      if (!turma?.horario?.fim) return false;
      const end = combineDateTime(refDate, turma?.horario?.fim ?? '00:00:00');
      return now > end;
    } catch (e) {
      console.warn('Falha ao calcular disponibilidade da turma:', e);
      return false;
    }
  };

  const isCheckinDisabled = (turma: any): boolean => {
    if (!turma) return true;
    if (isTurmaDisabled(turma, selectedDate)) return true;
    const hasVagasByField =
      typeof turma.vagas_disponiveis === 'number'
        ? turma.vagas_disponiveis > 0
        : true;

    const hasVagasByCount =
      typeof turma.limite_alunos === 'number' && typeof turma.alunos_inscritos === 'number'
        ? turma.alunos_inscritos < turma.limite_alunos
        : true;

    if (!hasVagasByField && !hasVagasByCount) return true;
    return false;
  };

  const formatDateDisplay = (date: Date) => {
    console.log('📅 Formatando data:', date);
    const day = date.getDate();
    const dayName = date.toLocaleDateString('pt-BR', { weekday: 'short' });
    return { day, dayName: dayName.toUpperCase() };
  };

  const getHoraInicio = (turma: any) => turma?.hora_inicio || turma?.horario?.inicio;
  const getHoraFim = (turma: any) => turma?.hora_fim || turma?.horario?.fim;

  return (
    <>
      <SafeAreaView style={styles.container} edges={['top']}>
        {/* Header com Botão Recarregar */}
        <View style={styles.headerTop}>
          <Text style={styles.headerTitle}>Checkin</Text>
          <TouchableOpacity 
            style={styles.refreshButton}
            onPress={generateCalendarDays}
          >
            <Feather name="refresh-cw" size={20} color={colors.primary} />
          </TouchableOpacity>
        </View>

        <ScrollView 
          contentContainerStyle={[styles.scrollContent, styles.scrollGrow]}
          showsVerticalScrollIndicator={false}
        >
        {/* Calendar */}
        <View style={styles.calendarSection}>
          <ScrollView 
            horizontal 
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.calendarContainer}
          >
            {calendarDays.map((date, index) => {
              const { day, dayName } = formatDateDisplay(date);
              const isSelected = selectedDate && 
                selectedDate.toDateString() === date.toDateString();
              
              return (
                <TouchableOpacity
                  key={index}
                  style={[
                    styles.calendarDay,
                    isSelected && styles.calendarDaySelected
                  ]}
                  onPress={() => setSelectedDate(new Date(date))}
                >
                  <Text style={[
                    styles.calendarDayName,
                    isSelected && styles.calendarDayNameSelected
                  ]}>
                    {dayName}
                  </Text>
                  <Text style={[
                    styles.calendarDayNumber,
                    isSelected && styles.calendarDayNumberSelected
                  ]}>
                    {day}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        </View>

        {/* Available Schedules */}
        <View style={styles.schedulesSection}>
          {participantsTurma ? (
            <View style={styles.participantsWrapper}>
              <View style={styles.participantsHeaderRow}>
                <TouchableOpacity onPress={() => { setParticipantsTurma(null); setParticipants([]); }} style={styles.backButtonInline}>
                  <Feather name="arrow-left" size={20} color={colors.primary} />
                </TouchableOpacity>
                <View style={{ flex: 1 }}>
                  <Text style={styles.participantsTitle}>{normalizeUtf8(participantsTurma.nome || 'Turma')}</Text>
                  <Text style={styles.participantsSubtitle}>
                    {participantsTurma.professor?.nome
                      ? `Professor: ${normalizeUtf8(participantsTurma.professor.nome)}`
                      : participantsTurma.professor
                        ? `Professor: ${normalizeUtf8(String(participantsTurma.professor))}`
                        : ''}
                  </Text>
                </View>
              </View>

              <View style={styles.participantsMetaRow}>
                <View style={styles.metaChip}>
                  <Feather name="clock" size={14} color={colors.primary} />
                  <Text style={styles.metaChipText}>
                    {getHoraInicio(participantsTurma)?.slice(0, 5)} - {getHoraFim(participantsTurma)?.slice(0, 5)}
                  </Text>
                </View>
                <View style={styles.metaChip}>
                  <Feather name="users" size={14} color={colors.primary} />
                  <Text style={styles.metaChipText}>
                    {(alunosTotal || participants?.length || participantsTurma.alunos_inscritos || 0)}/{participantsTurma.limite_alunos || '--'} inscritos
                  </Text>
                </View>
              </View>

              <View style={styles.participantsContent}>
                {participantsLoading ? (
                  <Text style={styles.loadingText}>Carregando...</Text>
                ) : (
                  <>
                    {participants.length > 0 ? (
                      <View style={styles.participantsListContainer}>
                        {participants.map((p, idx) => (
                          <View key={p.usuario_id || p.checkin_id || idx} style={styles.participantItem}>
                            <View style={styles.participantAvatar}>
                              <Text style={styles.participantAvatarText}>{getInitials(p.nome || p.usuario_nome)}</Text>
                            </View>
                            <View style={styles.participantInfo}>
                              <Text style={styles.participantName}>{normalizeUtf8(p.nome || p.usuario_nome || 'Aluno')}</Text>
                            </View>
                          </View>
                        ))}
                      </View>
                    ) : (
                      <Text style={styles.loadingText}>Nenhum participante ainda</Text>
                    )}
                  </>
                )}

                <TouchableOpacity
                  style={[styles.checkinButton, (checkinLoading || isCheckinDisabled(participantsTurma)) && styles.checkinButtonDisabled]}
                  onPress={() => handleCheckin(participantsTurma)}
                  disabled={checkinLoading || isCheckinDisabled(participantsTurma)}
                >
                  {checkinLoading ? (
                    <Text style={styles.checkinButtonText}>Enviando...</Text>
                  ) : (
                    <Text style={styles.checkinButtonText}>Fazer check-in</Text>
                  )}
                </TouchableOpacity>
              </View>
            </View>
          ) : loading ? (
            <Text style={styles.loadingText}>Carregando...</Text>
          ) : availableSchedules.length > 0 ? (
            <View style={styles.schedulesList}>
              {availableSchedules.map((turma) => {
                const disabled = isTurmaDisabled(turma, selectedDate);
                return (
                <TouchableOpacity
                  key={turma.id}
                  disabled={disabled}
                  style={[
                    styles.scheduleItem,
                    disabled && styles.scheduleItemDisabled,
                    { borderLeftColor: disabled ? '#cccccc' : (turma.modalidade?.cor || colors.primary) }
                  ]}
                  onPress={() => openParticipants(turma)}
                >
                  <View style={styles.scheduleContent}>
                    <View style={styles.scheduleHeader}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.scheduleTimeText}>
                          {turma.horario.inicio.slice(0, 5)} - {turma.horario.fim.slice(0, 5)}
                        </Text>
                        <Text style={styles.scheduleName}>{normalizeUtf8(turma.nome)}</Text>
                      </View>
                      {turma.modalidade && (
                        <View style={[
                          styles.modalidadeBadge,
                          { backgroundColor: turma.modalidade.cor + '20' }
                        ]}>
                          <Text style={[
                            styles.modalidadeText,
                            { color: turma.modalidade.cor }
                          ]}>
                            {normalizeUtf8(turma.modalidade.nome)}
                          </Text>
                        </View>
                      )}
                    </View>
                    <View style={styles.scheduleInfo}>
                      <View style={styles.infoItem}>
                        <Feather name="user" size={14} color="#999" />
                        <Text style={styles.infoText}>
                          {turma.alunos_inscritos}/{turma.limite_alunos}
                        </Text>
                      </View>
                      <View style={styles.infoItem}>
                        <Feather name="check-circle" size={14} color="#4CAF50" />
                        <Text style={styles.infoText}>
                          {disabled ? 'Encerrado' : `${turma.vagas_disponiveis} vaga${turma.vagas_disponiveis !== 1 ? 's' : ''}`}
                        </Text>
                      </View>
                    </View>
                  </View>
                  <Feather name="chevron-right" size={20} color={disabled ? '#cccccc' : colors.primary} />
                </TouchableOpacity>
                );
              })}
            </View>
          ) : (
            <View style={styles.emptyState}>
              <View style={styles.emptyIconCircle}>
                <Feather name="moon" size={48} color={colors.primary} />
              </View>
              <Text style={styles.emptyTitle}>Rest Day</Text>
              <Text style={styles.emptySubtitle}>Sem turmas disponíveis neste dia</Text>
            </View>
          )}
        </View>
      </ScrollView>
      </SafeAreaView>
      {toastVisible && (
        <View style={[styles.toastContainer, styles[`toast_${toast.type}`]]}>
          <Feather
            name={toast.type === 'success' ? 'check-circle' : toast.type === 'error' ? 'alert-circle' : toast.type === 'warning' ? 'alert-triangle' : 'info'}
            size={16}
            color={toast.type === 'success' ? '#0a7f3c' : toast.type === 'error' ? '#b3261e' : toast.type === 'warning' ? '#b26a00' : '#0b5cff'}
          />
          <Text style={styles.toastText}>{toast.message}</Text>
        </View>
      )}
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#000',
  },
  refreshButton: {
    padding: 8,
  },
  scrollContent: {
    paddingBottom: 40,
    paddingTop: 0,
  },
  scrollGrow: {
    flexGrow: 1,
  },
  calendarSection: {
    paddingVertical: 0,
    paddingHorizontal: 0,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  calendarContainer: {
    paddingHorizontal: 12,
    paddingVertical: 12,
    gap: 8,
  },
  calendarDay: {
    backgroundColor: '#fff',
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 16,
    alignItems: 'center',
    minWidth: 70,
    borderWidth: 1,
    borderColor: '#e0e0e0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 2,
  },
  calendarDaySelected: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  calendarDayName: {
    fontSize: 12,
    color: '#999',
    marginBottom: 4,
    fontWeight: '500',
  },
  calendarDayNameSelected: {
    color: '#fff',
  },
  calendarDayNumber: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.primary,
  },
  calendarDayNumberSelected: {
    color: '#fff',
  },
  schedulesSection: {
    paddingHorizontal: 16,
    paddingVertical: 16,
  },
  schedulesList: {
    gap: 8,
  },
  scheduleItem: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    marginBottom: 0,
  },
  scheduleContent: {
    flex: 1,
    marginRight: 12,
  },
  scheduleItemDisabled: {
    opacity: 0.5,
  },
  scheduleHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
    gap: 12,
  },
  scheduleTimeText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#000',
    marginBottom: 4,
  },
  scheduleName: {
    fontSize: 13,
    color: '#666',
    fontWeight: '500',
  },
  modalidadeBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    justifyContent: 'center',
  },
  modalidadeText: {
    fontSize: 11,
    fontWeight: '600',
  },
  scheduleInfo: {
    flexDirection: 'row',
    gap: 16,
  },
  infoItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  infoText: {
    fontSize: 12,
    color: '#999',
    fontWeight: '500',
  },
  participantsWrapper: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    gap: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 3,
  },
  participantsHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  backButtonInline: {
    padding: 8,
    borderRadius: 8,
    backgroundColor: '#f4f4f4',
  },
  participantsTitle: {
    fontSize: 17,
    fontWeight: '700',
    color: '#000',
  },
  participantsSubtitle: {
    fontSize: 12,
    color: '#666',
    marginTop: 2,
  },
  participantsListContainer: {
    borderTopWidth: 1,
    borderTopColor: '#f0f0f0',
    paddingTop: 8,
    gap: 4,
  },
  participantsContent: {
    gap: 12,
  },
  participantsMetaRow: {
    flexDirection: 'row',
    gap: 8,
    flexWrap: 'wrap',
  },
  metaChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#f7f7f7',
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  metaChipText: {
    fontSize: 12,
    color: '#333',
    fontWeight: '500',
  },
  participantsContent: {
    gap: 12,
  },
  checkinButton: {
    marginTop: 12,
    backgroundColor: colors.primary,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 3,
  },
  checkinButtonDisabled: {
    opacity: 0.6,
  },
  checkinButtonText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 15,
  },
  loadingText: {
    textAlign: 'center',
    color: '#999',
    fontSize: 14,
    marginVertical: 20,
  },
  noSchedulesText: {
    textAlign: 'center',
    color: '#999',
    fontSize: 14,
    marginVertical: 20,
  },
  participantItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
    gap: 12,
  },
  participantAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#e8e8e8',
    alignItems: 'center',
    justifyContent: 'center',
  },
  participantAvatarText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#555',
  },
  participantInfo: {
    flex: 1,
  },
  participantName: {
    fontSize: 15,
    fontWeight: '600',
    color: '#000',
  },
  participantTime: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  participantTimeText: {
    fontSize: 12,
    color: '#333',
  },
  statsRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 4,
  },
  statCard: {
    flex: 1,
    backgroundColor: '#f8f8f8',
    borderRadius: 10,
    padding: 10,
  },
  statLabel: {
    fontSize: 12,
    color: '#666',
    marginBottom: 4,
  },
  statValue: {
    fontSize: 16,
    fontWeight: '700',
    color: '#000',
  },
  checkinsList: {
    borderTopWidth: 1,
    borderTopColor: '#f0f0f0',
    paddingTop: 8,
    gap: 8,
  },
  checkinItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  participantAvatarSmall: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#e8e8e8',
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkinHourChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: '#eef5ff',
    borderRadius: 10,
  },
  checkinHourText: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: '600',
  },
  toastContainer: {
    position: 'absolute',
    bottom: 24,
    left: 16,
    right: 16,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.12,
    shadowRadius: 6,
    elevation: 5,
  },
  toastText: {
    color: '#1c1c1c',
    fontSize: 13,
    flex: 1,
    fontWeight: '600',
  },
  toast_success: {
    backgroundColor: '#e6f4ec',
  },
  toast_error: {
    backgroundColor: '#fdecea',
  },
  toast_warning: {
    backgroundColor: '#fff4e5',
  },
  toast_info: {
    backgroundColor: '#e7f1ff',
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
    gap: 12,
  },
  emptyIconCircle: {
    width: 96,
    height: 96,
    borderRadius: 48,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#eee',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
    marginBottom: 8,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#000',
  },
  emptySubtitle: {
    fontSize: 13,
    color: '#777',
  },
});
