import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import mobileService from '../services/mobileService';
import { colors } from '../theme/colors';

// Mock de histórico
const getMockHistorico = () => {
  const historico = [];
  const modalidades = ['CrossFit', 'Funcional', 'Yoga', 'Spinning'];
  
  for (let i = 0; i < 20; i++) {
    const date = new Date();
    date.setDate(date.getDate() - i);
    
    if (Math.random() > 0.3) { // 70% de chance de ter treino no dia
      historico.push({
        id: String(i + 1),
        data: date.toISOString().split('T')[0],
        hora: `${String(6 + Math.floor(Math.random() * 14)).padStart(2, '0')}:00`,
        modalidade: modalidades[Math.floor(Math.random() * modalidades.length)],
        instrutor: ['João Silva', 'Maria Santos', 'Pedro Costa'][Math.floor(Math.random() * 3)],
      });
    }
  }
  
  return historico;
};

export default function HistoricoScreen({ user }) {
  const [historico, setHistorico] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filtroMes, setFiltroMes] = useState(null);

  const carregarHistorico = useCallback(async () => {
    try {
      // Tenta carregar da API
      const response = await mobileService.getHistoricoCheckins();
      if (response.success && response.data?.length > 0) {
        setHistorico(response.data);
      } else {
        // Fallback para mock
        setHistorico(getMockHistorico());
      }
    } catch (err) {
      console.log('Usando dados mock para histórico');
      setHistorico(getMockHistorico());
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    carregarHistorico();
  }, [carregarHistorico]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    carregarHistorico();
  }, [carregarHistorico]);

  const formatarData = (dataString) => {
    const data = new Date(dataString);
    const hoje = new Date();
    const ontem = new Date(hoje);
    ontem.setDate(hoje.getDate() - 1);

    if (data.toDateString() === hoje.toDateString()) {
      return 'Hoje';
    }
    if (data.toDateString() === ontem.toDateString()) {
      return 'Ontem';
    }

    return data.toLocaleDateString('pt-BR', { 
      weekday: 'short',
      day: 'numeric', 
      month: 'short' 
    });
  };

  const agruparPorData = () => {
    const grupos = {};
    historico.forEach(item => {
      const data = formatarData(item.data);
      if (!grupos[data]) {
        grupos[data] = [];
      }
      grupos[data].push(item);
    });
    
    return Object.entries(grupos).map(([data, items]) => ({
      data,
      items,
    }));
  };

  const renderCheckinItem = (item) => (
    <View style={styles.checkinItem} key={item.id}>
      <View style={styles.checkinTimeContainer}>
        <Text style={styles.checkinTime}>{item.hora}</Text>
      </View>
      <View style={styles.checkinInfo}>
        <Text style={styles.checkinModalidade}>{item.modalidade}</Text>
        <Text style={styles.checkinInstrutor}>{item.instrutor}</Text>
      </View>
      <View style={styles.checkinBadge}>
        <Feather name="check" size={14} color={colors.success} />
      </View>
    </View>
  );

  const renderGrupo = ({ item }) => (
    <View style={styles.grupo}>
      <Text style={styles.grupoData}>{item.data}</Text>
      <View style={styles.grupoCard}>
        {item.items.map(renderCheckinItem)}
      </View>
    </View>
  );

  // Estatísticas
  const totalCheckins = historico.length;
  const mesesComCheckin = new Set(historico.map(h => new Date(h.data).getMonth())).size;

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Carregando histórico...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Stats Header */}
      <View style={styles.statsHeader}>
        <View style={styles.statItem}>
          <Text style={styles.statValue}>{totalCheckins}</Text>
          <Text style={styles.statLabel}>Check-ins</Text>
        </View>
        <View style={styles.statDivider} />
        <View style={styles.statItem}>
          <Text style={styles.statValue}>{mesesComCheckin}</Text>
          <Text style={styles.statLabel}>Meses ativos</Text>
        </View>
        <View style={styles.statDivider} />
        <View style={styles.statItem}>
          <Text style={styles.statValue}>{Math.round(totalCheckins / Math.max(mesesComCheckin, 1))}</Text>
          <Text style={styles.statLabel}>Média/mês</Text>
        </View>
      </View>

      {/* Lista de Histórico */}
      {historico.length > 0 ? (
        <FlatList
          data={agruparPorData()}
          renderItem={renderGrupo}
          keyExtractor={(item, index) => `grupo-${index}`}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              colors={[colors.primary]}
              tintColor={colors.primary}
            />
          }
        />
      ) : (
        <View style={styles.emptyContainer}>
          <Feather name="calendar" size={64} color={colors.gray300} />
          <Text style={styles.emptyText}>Nenhum check-in encontrado</Text>
          <Text style={styles.emptySubtext}>
            Seus check-ins aparecerão aqui
          </Text>
        </View>
      )}
    </View>
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
  // Stats Header
  statsHeader: {
    flexDirection: 'row',
    backgroundColor: colors.background,
    margin: 16,
    padding: 20,
    borderRadius: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  statItem: {
    flex: 1,
    alignItems: 'center',
  },
  statValue: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.primary,
  },
  statLabel: {
    fontSize: 12,
    color: colors.textSecondary,
    marginTop: 4,
  },
  statDivider: {
    width: 1,
    height: '100%',
    backgroundColor: colors.border,
  },
  // List
  listContent: {
    paddingHorizontal: 16,
    paddingBottom: 20,
  },
  grupo: {
    marginBottom: 20,
  },
  grupoData: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 8,
    textTransform: 'capitalize',
  },
  grupoCard: {
    backgroundColor: colors.background,
    borderRadius: 16,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  checkinItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  checkinTimeContainer: {
    width: 60,
    height: 36,
    borderRadius: 10,
    backgroundColor: colors.primary + '15',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  checkinTime: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.primary,
  },
  checkinInfo: {
    flex: 1,
  },
  checkinModalidade: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.text,
  },
  checkinInstrutor: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 2,
  },
  checkinBadge: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: colors.success + '15',
    alignItems: 'center',
    justifyContent: 'center',
  },
  // Empty State
  emptyContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 40,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textSecondary,
    marginTop: 20,
  },
  emptySubtext: {
    fontSize: 14,
    color: colors.textMuted,
    textAlign: 'center',
    marginTop: 8,
  },
});
