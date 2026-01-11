import { colors } from '@/src/theme/colors';
import { Feather } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function MatriculaDetalhesScreen() {
  const router = useRouter();
  const { matriculaId } = useLocalSearchParams();
  const [matricula, setMatricula] = useState(null);
  const [pagamentos, setPagamentos] = useState([]);
  const [resumo, setResumo] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadMatriculaDetails();
  }, [matriculaId]);

  const loadMatriculaDetails = async () => {
    try {
      const token = await AsyncStorage.getItem('@appcheckin:token');
      
      if (!token) {
        router.replace('/(auth)/login');
        return;
      }

      const response = await fetch(`http://localhost:8080/mobile/matriculas/${matriculaId}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });

      if (!response.ok) {
        if (response.status === 401) {
          await AsyncStorage.removeItem('@appcheckin:token');
          router.replace('/(auth)/login');
          return;
        }
        throw new Error(`Erro ao carregar matrícula: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        setMatricula(data.data.matricula);
        setPagamentos(data.data.pagamentos || []);
        setResumo(data.data.resumo_financeiro);
      } else {
        Alert.alert('Erro', data.error || 'Não foi possível carregar a matrícula');
      }
    } catch (error: any) {
      Alert.alert('Erro', 'Erro ao carregar dados da matrícula');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      </SafeAreaView>
    );
  }

  if (!matricula) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <Text style={styles.errorText}>Matrícula não encontrada</Text>
          <TouchableOpacity 
            style={styles.retryButton}
            onPress={loadMatriculaDetails}
          >
            <Text style={styles.retryButtonText}>Tentar Novamente</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'Pago':
        return '#4CAF50';
      case 'Aguardando':
        return '#FF9800';
      case 'Vencido':
        return '#f44336';
      default:
        return '#999';
    }
  };

  const getDaysUntilExpiry = (vencimento: string) => {
    const today = new Date();
    const expiry = new Date(vencimento);
    const diff = Math.ceil((expiry.getTime() - today.getTime()) / (1000 * 3600 * 24));
    return diff;
  };

  const isExpiringSoon = (vencimento: string) => {
    const days = getDaysUntilExpiry(vencimento);
    return days <= 7 && days > 0;
  };

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.headerTop}>
        <TouchableOpacity onPress={() => router.back()}>
          <Feather name="chevron-left" size={24} color="#000" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Detalhes da Matrícula</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView 
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Matrícula Header */}
        <View style={styles.matriculaHeader}>
          <View style={[
            styles.modalidadeColor,
            { backgroundColor: matricula.plano.modalidade?.cor || colors.primary }
          ]} />
          
          <View style={styles.headerContent}>
            <Text style={styles.usuario}>{matricula.usuario}</Text>
            <Text style={styles.planoNome}>{matricula.plano.nome}</Text>
            <Text style={styles.modalidade}>{matricula.plano.modalidade?.nome}</Text>
          </View>

          <View style={[
            styles.statusBadge,
            { backgroundColor: matricula.status === 'ativa' ? '#4CAF50' : '#f44336' }
          ]}>
            <Text style={styles.statusText}>
              {matricula.status === 'ativa' ? 'Ativa' : 'Inativa'}
            </Text>
          </View>
        </View>

        {/* Datas */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Período da Matrícula</Text>
          
          <View style={styles.dateItem}>
            <Feather name="calendar" size={18} color={colors.primary} />
            <View style={styles.dateContent}>
              <Text style={styles.dateLabel}>Matrícula</Text>
              <Text style={styles.dateValue}>
                {new Date(matricula.datas.matricula).toLocaleDateString('pt-BR')}
              </Text>
            </View>
          </View>

          <View style={styles.dateItem}>
            <Feather name="play-circle" size={18} color={colors.primary} />
            <View style={styles.dateContent}>
              <Text style={styles.dateLabel}>Início</Text>
              <Text style={styles.dateValue}>
                {new Date(matricula.datas.inicio).toLocaleDateString('pt-BR')}
              </Text>
            </View>
          </View>

          <View style={styles.dateItem}>
            <Feather name="alert-circle" size={18} color="#FF9800" />
            <View style={styles.dateContent}>
              <Text style={styles.dateLabel}>Vencimento</Text>
              <Text style={[styles.dateValue, { color: '#FF9800' }]}>
                {new Date(matricula.datas.vencimento).toLocaleDateString('pt-BR')}
              </Text>
            </View>
          </View>
        </View>

        {/* Plano Info */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Informações do Plano</Text>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Valor</Text>
            <Text style={styles.infoValue}>
              R$ {typeof matricula.plano.valor === 'number' ? matricula.plano.valor.toFixed(2).replace('.', ',') : matricula.plano.valor}
            </Text>
          </View>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Duração</Text>
            <Text style={styles.infoValue}>{matricula.plano.duracao_dias} dias</Text>
          </View>

          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Check-ins por Semana</Text>
            <Text style={styles.infoValue}>{matricula.plano.checkins_semanais}x</Text>
          </View>
        </View>

        {/* Resumo Financeiro */}
        {resumo && (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Resumo Financeiro</Text>

            <View style={styles.resumoGrid}>
              <View style={styles.resumoItem}>
                <Text style={styles.resumoLabel}>Total Previsto</Text>
                <Text style={styles.resumoValue}>
                  R$ {typeof resumo.total_previsto === 'number' ? resumo.total_previsto.toFixed(2).replace('.', ',') : resumo.total_previsto}
                </Text>
              </View>

              <View style={[styles.resumoItem, { borderColor: '#4CAF50' }]}>
                <Text style={styles.resumoLabel}>Pago</Text>
                <Text style={[styles.resumoValue, { color: '#4CAF50' }]}>
                  R$ {typeof resumo.total_pago === 'number' ? resumo.total_pago.toFixed(2).replace('.', ',') : resumo.total_pago}
                </Text>
              </View>

              <View style={[styles.resumoItem, { borderColor: '#FF9800' }]}>
                <Text style={styles.resumoLabel}>Pendente</Text>
                <Text style={[styles.resumoValue, { color: '#FF9800' }]}>
                  R$ {typeof resumo.total_pendente === 'number' ? resumo.total_pendente.toFixed(2).replace('.', ',') : resumo.total_pendente}
                </Text>
              </View>
            </View>

            {/* Progress Bar */}
            <View style={styles.progressContainer}>
              <View style={[
                styles.progressBar,
                { width: `${(resumo.total_pago / resumo.total_previsto) * 100}%` }
              ]} />
            </View>
            <Text style={styles.progressText}>
              {resumo.pagamentos_realizados} de {resumo.quantidade_pagamentos} pagamentos realizados
            </Text>
          </View>
        )}

        {/* Pagamentos */}
        {pagamentos.length > 0 && (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Histórico de Pagamentos</Text>
            
            {pagamentos.map((pagamento, idx) => {
              const diasRestantes = getDaysUntilExpiry(pagamento.data_vencimento);
              const vencidoOuProximo = isExpiringSoon(pagamento.data_vencimento);
              
              return (
                <View key={idx} style={[
                  styles.pagamentoItem,
                  vencidoOuProximo && pagamento.status !== 'Pago' && { backgroundColor: '#FFF3E0' }
                ]}>
                  <View style={styles.pagamentoHeader}>
                    <View style={styles.pagamentoInfo}>
                      <Text style={styles.pagamentoData}>
                        Vencimento: {new Date(pagamento.data_vencimento).toLocaleDateString('pt-BR')}
                      </Text>
                      <Text style={styles.pagamentoValor}>
                        R$ {typeof pagamento.valor === 'number' ? pagamento.valor.toFixed(2).replace('.', ',') : pagamento.valor}
                      </Text>
                    </View>
                    <View style={[
                      styles.statusBadge,
                      { backgroundColor: getStatusColor(pagamento.status) }
                    ]}>
                      <Text style={styles.statusText}>{pagamento.status}</Text>
                    </View>
                  </View>

                  {pagamento.status === 'Pago' && pagamento.data_pagamento && (
                    <View style={styles.pagamentoDetails}>
                      <Feather name="check-circle" size={14} color="#4CAF50" />
                      <Text style={styles.pagamentoDetail}>
                        Pago em {new Date(pagamento.data_pagamento).toLocaleDateString('pt-BR')}
                      </Text>
                      {pagamento.forma_pagamento && (
                        <Text style={styles.pagamentoDetail}>
                          • {pagamento.forma_pagamento}
                        </Text>
                      )}
                    </View>
                  )}

                  {pagamento.status === 'Aguardando' && (
                    <View style={styles.pagamentoDetails}>
                      {vencidoOuProximo ? (
                        <>
                          <Feather name="alert-triangle" size={14} color="#FF9800" />
                          <Text style={[styles.pagamentoDetail, { color: '#FF9800' }]}>
                            Vence em {diasRestantes} dia{diasRestantes !== 1 ? 's' : ''}
                          </Text>
                        </>
                      ) : (
                        <>
                          <Feather name="clock" size={14} color="#666" />
                          <Text style={styles.pagamentoDetail}>
                            Aguardando pagamento
                          </Text>
                        </>
                      )}
                    </View>
                  )}
                </View>
              );
            })}
          </View>
        )}

        {/* Action Button */}
        <TouchableOpacity 
          style={styles.actionButton}
          onPress={() => Alert.alert('Pagamento', 'Funcionalidade de pagamento em breve!')}
        >
          <Feather name="credit-card" size={20} color="#fff" />
          <Text style={styles.actionButtonText}>Realizar Pagamento</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
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
  scrollContent: {
    padding: 16,
    paddingBottom: 40,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 50,
  },
  errorText: {
    fontSize: 16,
    color: '#666',
    marginBottom: 16,
  },
  retryButton: {
    backgroundColor: colors.primary,
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
  },
  retryButtonText: {
    color: '#fff',
    fontWeight: '600',
  },
  matriculaHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    gap: 12,
  },
  modalidadeColor: {
    width: 12,
    height: 80,
    borderRadius: 6,
  },
  headerContent: {
    flex: 1,
  },
  usuario: {
    fontSize: 16,
    fontWeight: '700',
    color: '#000',
    marginBottom: 4,
  },
  planoNome: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.primary,
  },
  modalidade: {
    fontSize: 12,
    color: '#999',
    marginTop: 2,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 6,
  },
  statusText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 11,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: '#000',
    marginBottom: 12,
  },
  dateItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
    gap: 12,
  },
  dateContent: {
    flex: 1,
  },
  dateLabel: {
    fontSize: 12,
    color: '#999',
    marginBottom: 2,
  },
  dateValue: {
    fontSize: 14,
    fontWeight: '600',
    color: '#000',
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  infoLabel: {
    fontSize: 13,
    color: '#666',
    fontWeight: '500',
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.primary,
  },
  resumoGrid: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 12,
  },
  resumoItem: {
    flex: 1,
    borderLeftWidth: 3,
    borderLeftColor: '#ddd',
    paddingLeft: 12,
    paddingVertical: 8,
  },
  resumoLabel: {
    fontSize: 11,
    color: '#999',
    marginBottom: 4,
  },
  resumoValue: {
    fontSize: 16,
    fontWeight: '700',
    color: '#000',
  },
  progressContainer: {
    width: '100%',
    height: 8,
    backgroundColor: '#f0f0f0',
    borderRadius: 4,
    marginBottom: 8,
    overflow: 'hidden',
  },
  progressBar: {
    height: '100%',
    backgroundColor: '#4CAF50',
  },
  progressText: {
    fontSize: 12,
    color: '#999',
    textAlign: 'center',
  },
  pagamentoItem: {
    backgroundColor: '#f9f9f9',
    borderRadius: 8,
    padding: 12,
    marginVertical: 8,
    borderLeftWidth: 3,
    borderLeftColor: '#ddd',
  },
  pagamentoHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  pagamentoInfo: {
    flex: 1,
  },
  pagamentoData: {
    fontSize: 12,
    color: '#666',
    marginBottom: 2,
  },
  pagamentoValor: {
    fontSize: 14,
    fontWeight: '700',
    color: '#000',
  },
  pagamentoDetails: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
  },
  pagamentoDetail: {
    fontSize: 11,
    color: '#666',
    fontWeight: '500',
  },
  actionButton: {
    backgroundColor: colors.primary,
    borderRadius: 12,
    paddingVertical: 16,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 4,
    marginBottom: 20,
  },
  actionButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
