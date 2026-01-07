import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  ActivityIndicator,
  Pressable,
  Platform,
  useWindowDimensions,
  Alert,
  ToastAndroid,
} from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import BaixaPagamentoPlanoModal from '../../components/BaixaPagamentoPlanoModal';
import { matriculaService } from '../../services/matriculaService';

export default function MatriculaDetalheScreen() {
  const { id } = useLocalSearchParams();
  const { width } = useWindowDimensions();
  const isDesktop = width >= 768;

  const [loading, setLoading] = useState(true);
  const [matricula, setMatricula] = useState(null);
  const [pagamentos, setPagamentos] = useState([]);
  const [modalVisible, setModalVisible] = useState(false);
  const [pagamentoSelecionado, setPagamentoSelecionado] = useState(null);

  useEffect(() => {
    carregarDados();
  }, [id]);

  const carregarDados = async () => {
    try {
      setLoading(true);

      // Buscar dados da matrícula
      const responseMatricula = await matriculaService.buscar(id);
      const dadosMatricula = responseMatricula?.matricula || responseMatricula;
      setMatricula(dadosMatricula);

      // Buscar histórico de pagamentos (se existir endpoint)
      try {
        const responsePagamentos = await matriculaService.buscarPagamentos(id);
        const dadosPagamentos = responsePagamentos?.pagamentos || responsePagamentos?.data || [];
        setPagamentos(dadosPagamentos);
      } catch (error) {
        console.log('Endpoint de pagamentos não disponível ainda');
        setPagamentos([]);
      }
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
      showAlert('Erro', 'Não foi possível carregar os dados da matrícula');
    } finally {
      setLoading(false);
    }
  };

  const handleBaixaPagamento = (pagamento) => {
    setPagamentoSelecionado(pagamento);
    setModalVisible(true);
  };

  const handleBaixaSuccess = () => {
    setModalVisible(false);
    setPagamentoSelecionado(null);
    showToast('Pagamento confirmado! Próximo pagamento gerado automaticamente.');
    carregarDados();
  };

  const showAlert = (title, message) => {
    Alert.alert(title, message);
  };

  const showToast = (message) => {
    if (Platform.OS === 'android') {
      ToastAndroid.show(message, ToastAndroid.SHORT);
    } else {
      Alert.alert('', message);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'ativa':
        return '#10b981';
      case 'vencida':
        return '#f59e0b';
      case 'cancelada':
        return '#ef4444';
      case 'finalizada':
        return '#6b7280';
      default:
        return '#6b7280';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'ativa':
        return 'Ativa';
      case 'vencida':
        return 'Vencida';
      case 'cancelada':
        return 'Cancelada';
      case 'finalizada':
        return 'Finalizada';
      default:
        return status;
    }
  };

  const getPagamentoStatusColor = (statusId) => {
    switch (statusId) {
      case 1:
        return '#f59e0b'; // Pendente
      case 2:
        return '#10b981'; // Confirmado
      case 3:
        return '#ef4444'; // Cancelado
      case 4:
        return '#dc2626'; // Atrasado
      default:
        return '#6b7280';
    }
  };

  const getPagamentoStatusLabel = (statusId) => {
    switch (statusId) {
      case 1:
        return 'Pendente';
      case 2:
        return 'Confirmado';
      case 3:
        return 'Cancelado';
      case 4:
        return 'Atrasado';
      default:
        return 'Desconhecido';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value || 0);
  };

  const calcularResumo = () => {
    const total = pagamentos.reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    const pago = pagamentos
      .filter((p) => p.status_pagamento_id === 2)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    const pendente = total - pago;

    return { total, pago, pendente };
  };

  if (loading) {
    return (
      <LayoutBase title="Detalhes da Matrícula">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#3b82f6" />
          <Text style={styles.loadingText}>Carregando dados da matrícula...</Text>
        </View>
      </LayoutBase>
    );
  }

  if (!matricula) {
    return (
      <LayoutBase title="Detalhes da Matrícula">
        <View style={styles.errorContainer}>
          <Feather name="alert-circle" size={48} color="#ef4444" />
          <Text style={styles.errorText}>Matrícula não encontrada</Text>
          <Pressable
            style={styles.voltarButton}
            onPress={() => router.push('/matriculas')}
          >
            <Text style={styles.voltarButtonText}>Voltar</Text>
          </Pressable>
        </View>
      </LayoutBase>
    );
  }

  const resumo = calcularResumo();

  return (
    <LayoutBase title={`Matrícula #${matricula.id}`}>
      <ScrollView style={styles.container}>
        <View style={[styles.content, isDesktop && styles.contentDesktop]}>
          {/* Botão Voltar */}
          <Pressable
            style={({ pressed }) => [
              styles.voltarButton,
              pressed && { opacity: 0.7 },
            ]}
            onPress={() => router.push('/matriculas')}
          >
            <Feather name="arrow-left" size={20} color="#3b82f6" />
            <Text style={styles.voltarButtonText}>Voltar</Text>
          </Pressable>

          {/* Card com informações da matrícula */}
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardTitleRow}>
                <View style={styles.cardTitleIcon}>
                  <Feather name="file-text" size={20} color="#3b82f6" />
                </View>
                <Text style={styles.cardTitle}>Informações da Matrícula</Text>
              </View>
              <View
                style={[
                  styles.statusBadge,
                  { backgroundColor: getStatusColor(matricula.status) },
                ]}
              >
                <Text style={styles.statusText}>
                  {getStatusLabel(matricula.status)}
                </Text>
              </View>
            </View>

            <View style={styles.infoGrid}>
              <View style={styles.infoRow}>
                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Aluno</Text>
                  <Text style={styles.infoValue}>{matricula.usuario_nome}</Text>
                  <Text style={styles.infoSubtext}>{matricula.usuario_email}</Text>
                </View>
              </View>

              <View style={styles.infoRow}>
                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Modalidade</Text>
                  <View style={styles.modalidadeInfo}>
                    {matricula.modalidade_icone && (
                      <View
                        style={[
                          styles.modalidadeIcon,
                          {
                            backgroundColor:
                              matricula.modalidade_cor || '#3b82f6',
                          },
                        ]}
                      >
                        <MaterialCommunityIcons
                          name={matricula.modalidade_icone}
                          size={16}
                          color="#fff"
                        />
                      </View>
                    )}
                    <Text style={styles.infoValue}>
                      {matricula.modalidade_nome}
                    </Text>
                  </View>
                </View>

                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Plano</Text>
                  <Text style={styles.infoValue}>{matricula.plano_nome}</Text>
                </View>
              </View>

              <View style={styles.infoRow}>
                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Valor Mensal</Text>
                  <Text style={styles.infoValueHighlight}>
                    {formatCurrency(matricula.valor)}
                  </Text>
                </View>

                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Check-ins Semanais</Text>
                  <Text style={styles.infoValue}>
                    {matricula.checkins_semanais}x por semana
                  </Text>
                </View>
              </View>

              <View style={styles.infoRow}>
                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Data de Início</Text>
                  <Text style={styles.infoValue}>
                    {formatDate(matricula.data_inicio)}
                  </Text>
                </View>

                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Data de Vencimento</Text>
                  <Text style={styles.infoValue}>
                    {formatDate(matricula.data_vencimento)}
                  </Text>
                </View>
              </View>

              <View style={styles.infoRow}>
                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Duração do Plano</Text>
                  <Text style={styles.infoValue}>
                    {matricula.duracao_dias} dias
                  </Text>
                </View>

                <View style={styles.infoItem}>
                  <Text style={styles.infoLabel}>Data de Cadastro</Text>
                  <Text style={styles.infoValue}>
                    {formatDate(matricula.created_at)}
                  </Text>
                </View>
              </View>
            </View>
          </View>

          {/* Card de resumo de pagamentos */}
          {pagamentos.length > 0 && (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <View style={styles.cardTitleRow}>
                  <View
                    style={[styles.cardTitleIcon, { backgroundColor: '#dcfce7' }]}
                  >
                    <Feather name="dollar-sign" size={20} color="#10b981" />
                  </View>
                  <Text style={styles.cardTitle}>Resumo Financeiro</Text>
                </View>
              </View>

              <View style={styles.resumoGrid}>
                <View style={styles.resumoItem}>
                  <Text style={styles.resumoLabel}>Total</Text>
                  <Text style={[styles.resumoValue, { color: '#6b7280' }]}>
                    {formatCurrency(resumo.total)}
                  </Text>
                </View>
                <View style={styles.resumoItem}>
                  <Text style={styles.resumoLabel}>Pago</Text>
                  <Text style={[styles.resumoValue, { color: '#10b981' }]}>
                    {formatCurrency(resumo.pago)}
                  </Text>
                </View>
                <View style={styles.resumoItem}>
                  <Text style={styles.resumoLabel}>Pendente</Text>
                  <Text style={[styles.resumoValue, { color: '#f59e0b' }]}>
                    {formatCurrency(resumo.pendente)}
                  </Text>
                </View>
              </View>
            </View>
          )}

          {/* Lista de pagamentos */}
          {pagamentos.length > 0 ? (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <View style={styles.cardTitleRow}>
                  <View
                    style={[styles.cardTitleIcon, { backgroundColor: '#dbeafe' }]}
                  >
                    <Feather name="list" size={20} color="#3b82f6" />
                  </View>
                  <Text style={styles.cardTitle}>Histórico de Pagamentos</Text>
                </View>
              </View>

              <View style={styles.pagamentosContainer}>
                {pagamentos.map((pagamento, index) => (
                  <View key={pagamento.id || index} style={styles.pagamentoItem}>
                    <View style={styles.pagamentoHeader}>
                      <View style={styles.pagamentoInfo}>
                        <Text style={styles.pagamentoNumero}>
                          Parcela {pagamento.numero_parcela || index + 1}
                        </Text>
                        <Text style={styles.pagamentoVencimento}>
                          Venc: {formatDate(pagamento.data_vencimento)}
                        </Text>
                      </View>
                      <View
                        style={[
                          styles.pagamentoStatus,
                          {
                            backgroundColor: getPagamentoStatusColor(
                              pagamento.status_pagamento_id
                            ),
                          },
                        ]}
                      >
                        <Text style={styles.pagamentoStatusText}>
                          {getPagamentoStatusLabel(pagamento.status_pagamento_id)}
                        </Text>
                      </View>
                    </View>

                    <View style={styles.pagamentoDetails}>
                      <Text style={styles.pagamentoValor}>
                        {formatCurrency(pagamento.valor)}
                      </Text>
                      {pagamento.data_pagamento && (
                        <Text style={styles.pagamentoData}>
                          Pago em: {formatDate(pagamento.data_pagamento)}
                        </Text>
                      )}
                    </View>

                    {pagamento.status_pagamento_id === 1 && (
                      <Pressable
                        style={({ pressed }) => [
                          styles.btnBaixaPagamento,
                          pressed && { opacity: 0.7 },
                        ]}
                        onPress={() => handleBaixaPagamento(pagamento)}
                      >
                        <Feather name="check-circle" size={16} color="#10b981" />
                        <Text style={styles.btnBaixaPagamentoText}>
                          Confirmar Pagamento
                        </Text>
                      </Pressable>
                    )}
                  </View>
                ))}
              </View>
            </View>
          ) : (
            <View style={styles.card}>
              <View style={styles.emptyState}>
                <Feather name="inbox" size={48} color="#d1d5db" />
                <Text style={styles.emptyStateText}>
                  Nenhum pagamento registrado ainda
                </Text>
              </View>
            </View>
          )}
        </View>
      </ScrollView>

      {/* Modal de Baixa de Pagamento */}
      <BaixaPagamentoPlanoModal
        visible={modalVisible}
        onClose={() => setModalVisible(false)}
        pagamento={pagamentoSelecionado}
        onSuccess={handleBaixaSuccess}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  content: {
    padding: 16,
  },
  contentDesktop: {
    maxWidth: 1200,
    alignSelf: 'center',
    width: '100%',
    padding: 24,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 14,
    color: '#6b7280',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  errorText: {
    fontSize: 16,
    color: '#ef4444',
    marginTop: 16,
    marginBottom: 24,
  },
  voltarButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: '#eff6ff',
    borderRadius: 8,
    alignSelf: 'flex-start',
    marginBottom: 16,
  },
  voltarButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#3b82f6',
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  cardTitleIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#dbeafe',
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#fff',
  },
  infoGrid: {
    gap: 16,
  },
  infoRow: {
    flexDirection: 'row',
    gap: 16,
  },
  infoItem: {
    flex: 1,
  },
  infoLabel: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '500',
    marginBottom: 4,
  },
  infoValue: {
    fontSize: 15,
    fontWeight: '600',
    color: '#111827',
  },
  infoValueHighlight: {
    fontSize: 20,
    fontWeight: '700',
    color: '#3b82f6',
  },
  infoSubtext: {
    fontSize: 13,
    color: '#9ca3af',
    marginTop: 2,
  },
  modalidadeInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  modalidadeIcon: {
    width: 32,
    height: 32,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
  },
  resumoGrid: {
    flexDirection: 'row',
    gap: 16,
  },
  resumoItem: {
    flex: 1,
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#f9fafb',
    borderRadius: 8,
  },
  resumoLabel: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '500',
    marginBottom: 8,
  },
  resumoValue: {
    fontSize: 20,
    fontWeight: '700',
  },
  pagamentosContainer: {
    gap: 12,
  },
  pagamentoItem: {
    padding: 16,
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
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
  pagamentoNumero: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  pagamentoVencimento: {
    fontSize: 12,
    color: '#6b7280',
  },
  pagamentoStatus: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  pagamentoStatusText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#fff',
  },
  pagamentoDetails: {
    marginBottom: 12,
  },
  pagamentoValor: {
    fontSize: 18,
    fontWeight: '700',
    color: '#3b82f6',
    marginBottom: 4,
  },
  pagamentoData: {
    fontSize: 12,
    color: '#10b981',
    fontWeight: '500',
  },
  btnBaixaPagamento: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 10,
    backgroundColor: '#f0fdf4',
    borderRadius: 6,
    borderWidth: 1,
    borderColor: '#bbf7d0',
  },
  btnBaixaPagamentoText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#10b981',
  },
  emptyState: {
    alignItems: 'center',
    padding: 40,
  },
  emptyStateText: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 16,
  },
});
