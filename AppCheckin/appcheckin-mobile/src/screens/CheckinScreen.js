import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  FlatList,
  Alert,
  RefreshControl,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { colors } from '../theme/colors';

// Gerar dias da semana atual
const generateWeekDays = (baseDate = new Date()) => {
  const days = [];
  const startOfWeek = new Date(baseDate);
  const dayOfWeek = startOfWeek.getDay();
  startOfWeek.setDate(startOfWeek.getDate() - dayOfWeek);

  const dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

  for (let i = 0; i < 7; i++) {
    const date = new Date(startOfWeek);
    date.setDate(startOfWeek.getDate() + i);
    days.push({
      id: i.toString(),
      dayName: dayNames[i],
      dayNumber: date.getDate(),
      date: date,
      isToday: date.toDateString() === new Date().toDateString(),
    });
  }
  return days;
};

// Mock de horários de treino
const getMockSchedules = (selectedDate) => {
  const schedules = [
    { id: '1', hora: '06:00', modalidade: 'CrossFit', vagas: 15, ocupadas: 8, instrutor: 'João Silva' },
    { id: '2', hora: '07:00', modalidade: 'CrossFit', vagas: 15, ocupadas: 12, instrutor: 'Maria Santos' },
    { id: '3', hora: '08:00', modalidade: 'Funcional', vagas: 12, ocupadas: 6, instrutor: 'Pedro Costa' },
    { id: '4', hora: '09:00', modalidade: 'CrossFit', vagas: 15, ocupadas: 15, instrutor: 'João Silva' },
    { id: '5', hora: '10:00', modalidade: 'Yoga', vagas: 10, ocupadas: 4, instrutor: 'Ana Lima' },
    { id: '6', hora: '17:00', modalidade: 'CrossFit', vagas: 15, ocupadas: 10, instrutor: 'Maria Santos' },
    { id: '7', hora: '18:00', modalidade: 'CrossFit', vagas: 15, ocupadas: 14, instrutor: 'Pedro Costa' },
    { id: '8', hora: '19:00', modalidade: 'CrossFit', vagas: 15, ocupadas: 11, instrutor: 'João Silva' },
    { id: '9', hora: '20:00', modalidade: 'Funcional', vagas: 12, ocupadas: 7, instrutor: 'Ana Lima' },
  ];
  
  return schedules;
};

export default function CheckinScreen({ user }) {
  const [weekDays, setWeekDays] = useState([]);
  const [selectedDay, setSelectedDay] = useState(null);
  const [schedules, setSchedules] = useState([]);
  const [refreshing, setRefreshing] = useState(false);
  const flatListRef = useRef(null);

  useEffect(() => {
    const days = generateWeekDays();
    setWeekDays(days);
    
    // Selecionar o dia atual por padrão
    const today = days.find(d => d.isToday);
    if (today) {
      setSelectedDay(today);
    }
  }, []);

  useEffect(() => {
    if (selectedDay) {
      loadSchedules(selectedDay.date);
    }
  }, [selectedDay]);

  const loadSchedules = (date) => {
    // Aqui você conectaria com a API real
    const data = getMockSchedules(date);
    setSchedules(data);
  };

  const onRefresh = () => {
    setRefreshing(true);
    if (selectedDay) {
      loadSchedules(selectedDay.date);
    }
    setTimeout(() => setRefreshing(false), 1000);
  };

  const handleDaySelect = (day) => {
    setSelectedDay(day);
  };

  const handleCheckin = (schedule) => {
    if (schedule.ocupadas >= schedule.vagas) {
      Alert.alert('Turma Lotada', 'Esta turma já está completa. Tente outro horário.');
      return;
    }

    Alert.alert(
      'Confirmar Check-in',
      `Deseja fazer check-in para:\n\n${schedule.modalidade}\nHorário: ${schedule.hora}\nInstrutor: ${schedule.instrutor}`,
      [
        { text: 'Cancelar', style: 'cancel' },
        { 
          text: 'Confirmar', 
          onPress: () => {
            Alert.alert(
              '✅ Check-in Realizado!',
              `Seu check-in para ${schedule.modalidade} às ${schedule.hora} foi confirmado.\n\nBom treino, ${user?.nome?.split(' ')[0] || 'atleta'}!`
            );
          }
        },
      ]
    );
  };

  const renderDayItem = ({ item }) => {
    const isSelected = selectedDay?.id === item.id;
    
    return (
      <TouchableOpacity
        style={[
          styles.dayItem,
          isSelected && styles.dayItemSelected,
          item.isToday && !isSelected && styles.dayItemToday,
        ]}
        onPress={() => handleDaySelect(item)}
        activeOpacity={0.7}
      >
        <Text style={[
          styles.dayName,
          isSelected && styles.dayNameSelected,
        ]}>
          {item.dayName}
        </Text>
        <Text style={[
          styles.dayNumber,
          isSelected && styles.dayNumberSelected,
        ]}>
          {item.dayNumber}
        </Text>
        {item.isToday && (
          <View style={[styles.todayDot, isSelected && styles.todayDotSelected]} />
        )}
      </TouchableOpacity>
    );
  };

  const renderScheduleItem = (schedule) => {
    const vagasRestantes = schedule.vagas - schedule.ocupadas;
    const isLotado = vagasRestantes === 0;
    const isPoucasVagas = vagasRestantes <= 3 && vagasRestantes > 0;

    return (
      <TouchableOpacity
        key={schedule.id}
        style={[styles.scheduleCard, isLotado && styles.scheduleCardLotado]}
        onPress={() => handleCheckin(schedule)}
        activeOpacity={isLotado ? 1 : 0.7}
        disabled={isLotado}
      >
        <View style={styles.scheduleTime}>
          <Feather name="clock" size={16} color={isLotado ? colors.gray400 : colors.primary} />
          <Text style={[styles.scheduleTimeText, isLotado && styles.textDisabled]}>
            {schedule.hora}
          </Text>
        </View>

        <View style={styles.scheduleInfo}>
          <Text style={[styles.scheduleModalidade, isLotado && styles.textDisabled]}>
            {schedule.modalidade}
          </Text>
          <Text style={[styles.scheduleInstrutor, isLotado && styles.textDisabled]}>
            {schedule.instrutor}
          </Text>
        </View>

        <View style={styles.scheduleVagas}>
          <View style={[
            styles.vagasTag,
            isLotado && styles.vagasTagLotado,
            isPoucasVagas && styles.vagasTagPoucas,
          ]}>
            <Text style={[
              styles.vagasText,
              isLotado && styles.vagasTextLotado,
              isPoucasVagas && styles.vagasTextPoucas,
            ]}>
              {isLotado ? 'Lotado' : `${vagasRestantes} vagas`}
            </Text>
          </View>
        </View>

        {!isLotado && (
          <Feather name="chevron-right" size={20} color={colors.gray400} />
        )}
      </TouchableOpacity>
    );
  };

  const formatSelectedDate = () => {
    if (!selectedDay) return '';
    const options = { weekday: 'long', day: 'numeric', month: 'long' };
    return selectedDay.date.toLocaleDateString('pt-BR', options);
  };

  return (
    <View style={styles.container}>
      {/* Calendário Horizontal */}
      <View style={styles.calendarContainer}>
        <FlatList
          ref={flatListRef}
          data={weekDays}
          renderItem={renderDayItem}
          keyExtractor={(item) => item.id}
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.calendarList}
        />
      </View>

      {/* Data selecionada */}
      <View style={styles.selectedDateContainer}>
        <Text style={styles.selectedDateText}>{formatSelectedDate()}</Text>
      </View>

      {/* Lista de Horários */}
      <ScrollView
        style={styles.scheduleList}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            colors={[colors.primary]}
            tintColor={colors.primary}
          />
        }
      >
        <Text style={styles.sectionTitle}>Horários Disponíveis</Text>
        
        {schedules.length > 0 ? (
          schedules.map(renderScheduleItem)
        ) : (
          <View style={styles.emptyState}>
            <Feather name="calendar" size={48} color={colors.gray300} />
            <Text style={styles.emptyText}>Nenhum horário disponível</Text>
            <Text style={styles.emptySubtext}>Selecione outro dia</Text>
          </View>
        )}

        <View style={{ height: 20 }} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
  },
  // Calendar Styles
  calendarContainer: {
    backgroundColor: colors.background,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  calendarList: {
    paddingHorizontal: 16,
    gap: 8,
  },
  dayItem: {
    width: 52,
    height: 72,
    borderRadius: 16,
    backgroundColor: colors.gray100,
    alignItems: 'center',
    justifyContent: 'center',
    marginHorizontal: 4,
  },
  dayItemSelected: {
    backgroundColor: colors.primary,
  },
  dayItemToday: {
    borderWidth: 2,
    borderColor: colors.primary,
  },
  dayName: {
    fontSize: 12,
    fontWeight: '500',
    color: colors.textSecondary,
    marginBottom: 4,
  },
  dayNameSelected: {
    color: colors.textLight,
  },
  dayNumber: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.text,
  },
  dayNumberSelected: {
    color: colors.textLight,
  },
  todayDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: colors.primary,
    position: 'absolute',
    bottom: 8,
  },
  todayDotSelected: {
    backgroundColor: colors.textLight,
  },
  // Selected Date
  selectedDateContainer: {
    backgroundColor: colors.background,
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  selectedDateText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
    textTransform: 'capitalize',
  },
  // Schedule List
  scheduleList: {
    flex: 1,
    paddingHorizontal: 16,
    paddingTop: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  scheduleCard: {
    backgroundColor: colors.background,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  scheduleCardLotado: {
    opacity: 0.6,
  },
  scheduleTime: {
    flexDirection: 'row',
    alignItems: 'center',
    width: 70,
  },
  scheduleTimeText: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.text,
    marginLeft: 6,
  },
  scheduleInfo: {
    flex: 1,
    marginLeft: 12,
  },
  scheduleModalidade: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.text,
  },
  scheduleInstrutor: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 2,
  },
  scheduleVagas: {
    marginRight: 8,
  },
  vagasTag: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    backgroundColor: colors.success + '15',
  },
  vagasTagLotado: {
    backgroundColor: colors.gray200,
  },
  vagasTagPoucas: {
    backgroundColor: colors.warning + '15',
  },
  vagasText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.success,
  },
  vagasTextLotado: {
    color: colors.gray500,
  },
  vagasTextPoucas: {
    color: colors.warning,
  },
  textDisabled: {
    color: colors.gray400,
  },
  // Empty State
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textSecondary,
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 4,
  },
});
