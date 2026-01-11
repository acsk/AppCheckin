import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { colors } from '../theme/colors';

// Mock de WOD (Workout of the Day)
const getMockWod = () => {
  return {
    data: new Date().toLocaleDateString('pt-BR', { 
      weekday: 'long', 
      day: 'numeric', 
      month: 'long',
      year: 'numeric'
    }),
    tipo: 'AMRAP 20min',
    nome: 'Murph Modificado',
    descricao: 'As Many Rounds As Possible em 20 minutos',
    exercicios: [
      { nome: '400m Run', quantidade: '1', unidade: '' },
      { nome: 'Pull-ups', quantidade: '10', unidade: 'reps' },
      { nome: 'Push-ups', quantidade: '20', unidade: 'reps' },
      { nome: 'Air Squats', quantidade: '30', unidade: 'reps' },
    ],
    notas: 'Escale os movimentos conforme necessário. Puxadas podem ser substituídas por ring rows ou band assisted.',
    nivel: 'Intermediário',
    coach: 'João Silva',
  };
};

export default function WodScreen({ user }) {
  const [wod, setWod] = useState(null);
  const [refreshing, setRefreshing] = useState(false);
  const [showDetails, setShowDetails] = useState(true);

  useEffect(() => {
    loadWod();
  }, []);

  const loadWod = () => {
    const data = getMockWod();
    setWod(data);
  };

  const onRefresh = () => {
    setRefreshing(true);
    loadWod();
    setTimeout(() => setRefreshing(false), 1000);
  };

  if (!wod) {
    return (
      <View style={styles.loadingContainer}>
        <Feather name="zap" size={48} color={colors.gray300} />
        <Text style={styles.loadingText}>Carregando WOD...</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
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
      {/* Header Card */}
      <View style={styles.headerCard}>
        <View style={styles.headerTop}>
          <View style={styles.tipoTag}>
            <Feather name="zap" size={14} color={colors.textLight} />
            <Text style={styles.tipoText}>{wod.tipo}</Text>
          </View>
          <View style={styles.nivelTag}>
            <Text style={styles.nivelText}>{wod.nivel}</Text>
          </View>
        </View>
        
        <Text style={styles.wodNome}>{wod.nome}</Text>
        <Text style={styles.wodDescricao}>{wod.descricao}</Text>
        
        <View style={styles.headerMeta}>
          <View style={styles.metaItem}>
            <Feather name="calendar" size={14} color={colors.textSecondary} />
            <Text style={styles.metaText}>{wod.data}</Text>
          </View>
          <View style={styles.metaItem}>
            <Feather name="user" size={14} color={colors.textSecondary} />
            <Text style={styles.metaText}>Coach: {wod.coach}</Text>
          </View>
        </View>
      </View>

      {/* Exercícios */}
      <View style={styles.section}>
        <TouchableOpacity 
          style={styles.sectionHeader}
          onPress={() => setShowDetails(!showDetails)}
          activeOpacity={0.7}
        >
          <Text style={styles.sectionTitle}>Exercícios</Text>
          <Feather 
            name={showDetails ? 'chevron-up' : 'chevron-down'} 
            size={20} 
            color={colors.textSecondary} 
          />
        </TouchableOpacity>

        {showDetails && (
          <View style={styles.exerciciosList}>
            {wod.exercicios.map((exercicio, index) => (
              <View key={index} style={styles.exercicioItem}>
                <View style={styles.exercicioNumber}>
                  <Text style={styles.exercicioNumberText}>{index + 1}</Text>
                </View>
                <View style={styles.exercicioInfo}>
                  <Text style={styles.exercicioNome}>{exercicio.nome}</Text>
                  {exercicio.quantidade && (
                    <Text style={styles.exercicioQuantidade}>
                      {exercicio.quantidade} {exercicio.unidade}
                    </Text>
                  )}
                </View>
              </View>
            ))}
          </View>
        )}
      </View>

      {/* Notas do Coach */}
      {wod.notas && (
        <View style={styles.notasCard}>
          <View style={styles.notasHeader}>
            <Feather name="message-circle" size={18} color={colors.primary} />
            <Text style={styles.notasTitle}>Notas do Coach</Text>
          </View>
          <Text style={styles.notasText}>{wod.notas}</Text>
        </View>
      )}

      {/* Dicas */}
      <View style={styles.dicasSection}>
        <Text style={styles.dicasTitle}>💡 Dicas para hoje</Text>
        <View style={styles.dicasList}>
          <View style={styles.dicaItem}>
            <Feather name="check" size={16} color={colors.success} />
            <Text style={styles.dicaText}>Mantenha um ritmo constante</Text>
          </View>
          <View style={styles.dicaItem}>
            <Feather name="check" size={16} color={colors.success} />
            <Text style={styles.dicaText}>Hidrate-se bem antes do treino</Text>
          </View>
          <View style={styles.dicaItem}>
            <Feather name="check" size={16} color={colors.success} />
            <Text style={styles.dicaText}>Escale conforme necessário</Text>
          </View>
        </View>
      </View>

      {/* Botão de Registrar */}
      <TouchableOpacity style={styles.registrarButton} activeOpacity={0.8}>
        <Feather name="edit-3" size={20} color={colors.textLight} />
        <Text style={styles.registrarButtonText}>Registrar Resultado</Text>
      </TouchableOpacity>

      <View style={{ height: 40 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
  },
  loadingContainer: {
    flex: 1,
    backgroundColor: colors.backgroundSecondary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingText: {
    fontSize: 16,
    color: colors.textSecondary,
    marginTop: 16,
  },
  // Header Card
  headerCard: {
    backgroundColor: colors.background,
    margin: 16,
    borderRadius: 20,
    padding: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 4,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  tipoTag: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.primary,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
    gap: 6,
  },
  tipoText: {
    color: colors.textLight,
    fontSize: 13,
    fontWeight: '600',
  },
  nivelTag: {
    backgroundColor: colors.gray100,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  nivelText: {
    color: colors.textSecondary,
    fontSize: 13,
    fontWeight: '500',
  },
  wodNome: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 8,
  },
  wodDescricao: {
    fontSize: 15,
    color: colors.textSecondary,
    lineHeight: 22,
    marginBottom: 16,
  },
  headerMeta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 16,
  },
  metaItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  metaText: {
    fontSize: 13,
    color: colors.textSecondary,
  },
  // Section
  section: {
    backgroundColor: colors.background,
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 20,
    overflow: 'hidden',
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '600',
    color: colors.text,
  },
  // Exercícios
  exerciciosList: {
    padding: 16,
    gap: 12,
  },
  exercicioItem: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  exercicioNumber: {
    width: 32,
    height: 32,
    borderRadius: 10,
    backgroundColor: colors.primary + '15',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 14,
  },
  exercicioNumberText: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.primary,
  },
  exercicioInfo: {
    flex: 1,
  },
  exercicioNome: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.text,
  },
  exercicioQuantidade: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 2,
  },
  // Notas
  notasCard: {
    backgroundColor: colors.primary + '08',
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 16,
    padding: 16,
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
  },
  notasHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 10,
  },
  notasTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.primary,
  },
  notasText: {
    fontSize: 14,
    color: colors.text,
    lineHeight: 22,
  },
  // Dicas
  dicasSection: {
    backgroundColor: colors.background,
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 16,
    padding: 16,
  },
  dicasTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  dicasList: {
    gap: 10,
  },
  dicaItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  dicaText: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  // Botão Registrar
  registrarButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: colors.primary,
    marginHorizontal: 16,
    paddingVertical: 16,
    borderRadius: 14,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 4,
  },
  registrarButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textLight,
  },
});
