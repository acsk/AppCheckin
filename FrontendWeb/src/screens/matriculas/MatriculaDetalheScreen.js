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
  const [modalConfirmVisible, setModalConfirmVisible] = useState(false);
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
    // Status: 1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado
    switch (statusId) {
      case 1:
        return '#3B82F6'; // Aguardando (azul)
      case 2:
        return '#10b981'; // Pago (verde)
      case 3:
        return '#dc2626'; // Atrasado (vermelho escuro)
      case 4:
        return '#6B7280'; // Cancelado (cinza)
      default:
        return '#6b7280';
    }
  };

  const getPagamentoStatusLabel = (statusId) => {
    // Status: 1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado
    switch (statusId) {
      case 1:
        return 'Aguardando';
      case 2:
        return 'Pago';
      case 3:
        return 'Atrasado';
      case 4:
        return 'Cancelado';
      default:
        return 'Desconhecido';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Evita problema de timezone criando a data diretamente com os valores
    const [year, month, day] = dateString.split('-');
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('pt-BR');
  };

  const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return '-';
    try {
      // Trata tanto datas simples quanto timestamps completos
      const date = new Date(dateTimeString);
      if (isNaN(date.getTime())) return '-';
      return date.toLocaleDateString('pt-BR');
    } catch (error) {
      return '-';
    }
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value || 0);
  };

  const calcularResumo = () => {
    // Excluir pagamentos cancelados (status 4) do total
    const pagamentosAtivos = pagamentos.filter((p) => p.status_pagamento_id !== 4);
    
    const total = pagamentosAtivos.reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    
    // Pago = status 2
    const pago = pagamentos
      .filter((p) => p.status_pagamento_id === 2)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    
    // Atrasado = status 3
    const atrasado = pagamentos
      .filter((p) => p.status_pagamento_id === 3)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    
    // Pendente = status 1 (aguardando)
    const pendente = pagamentos
      .filter((p) => p.status_pagamento_id === 1)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);

    return { total, pago, atrasado, pendente };
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
          {/* Header Compacto */}
          <View style={styles.headerCompacto}>
            <Pressable
              style={({ pressed }) => [
                styles.voltarButton,
                pressed && { opacity: 0.7 },
              ]}
              onPress={() => router.push('/matriculas')}
            >
              <Feather name="arrow-left" size={18} color="#3b82f6" />
              <Text style={styles.voltarButtonText}>Voltar</Text>
            </Pressable>
            
            <View style={styles.headerInfo}>
              <View>
                <Text style={styles.headerTitulo}>{matricula.usuario_nome}</Text>
                <Text style={styles.headerSubtitulo}>
                  Contrato #{matricula.id} • {matricula.modalidade_nome} - {matricula.plano_nome}
                </Text>
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
          </View>

          {/* Card com informações da matrícula - Design Compacto */}
          <View style={styles.matriculaInfoCard}>
            {/* Header do Card */}
            <View style={styles.matriculaInfoHeader}>
              <View style={styles.matriculaInfoHeaderLeft}>
                {matricula.modalidade_icone && (
                  <View
                    style={[
                      styles.matriculaInfoModalidadeIcon,
                      { backgroundColor: matricula.modalidade_cor || '#3b82f6' },
                    ]}
                  >
                    <MaterialCommunityIcons
                      name={matricula.modalidade_icone}
                      size={24}
                      color="#fff"
                    />
                  </View>
                )}
                <View>
                  <Text style={styles.matriculaInfoModalidadeNome}>{matricula.modalidade_nome}</Text>
                  <Text style={styles.matriculaInfoPlanoNome}>{matricula.plano_nome} • Matrícula #{matricula.id}</Text>
                </View>
              </View>
              <View
                style={[
                  styles.matriculaInfoStatusBadge,
                  { backgroundColor: getStatusColor(matricula.status) },
                ]}
              >
                <Text style={styles.matriculaInfoStatusText}>
                  {getStatusLabel(matricula.status)}
                </Text>
              </View>
            </View>

            {/* Grid de Informações Compacto */}
            <View style={styles.matriculaInfoGridCompacto}>
              <View style={styles.matriculaInfoItemCompacto}>
                <Feather name="calendar" size={16} color="#3b82f6" />
                <Text style={styles.matriculaInfoItemLabelCompacto}>Início</Text>
                <Text style={styles.matriculaInfoItemValueCompacto}>{formatDate(matricula.data_inicio)}</Text>
              </View>

              <View style={styles.matriculaInfoDividerVertical} />

              <View style={styles.matriculaInfoItemCompacto}>
                <Feather name="clock" size={16} color="#f59e0b" />
                <Text style={styles.matriculaInfoItemLabelCompacto}>Vencimento</Text>
                <Text style={styles.matriculaInfoItemValueCompacto}>{formatDate(matricula.data_vencimento)}</Text>
              </View>

              <View style={styles.matriculaInfoDividerVertical} />

              <View style={styles.matriculaInfoItemCompacto}>
                <MaterialCommunityIcons name="dumbbell" size={16} color="#9333ea" />
                <Text style={styles.matriculaInfoItemLabelCompacto}>Check-ins</Text>
                <Text style={styles.matriculaInfoItemValueCompacto}>{matricula.checkins_semanais}x/sem</Text>
              </View>

              <View style={styles.matriculaInfoDividerVertical} />

              <View style={styles.matriculaInfoItemCompacto}>
                <Feather name="hash" size={16} color="#6b7280" />
                <Text style={styles.matriculaInfoItemLabelCompacto}>Duração</Text>
                <Text style={styles.matriculaInfoItemValueCompacto}>{matricula.duracao_dias} dias</Text>
              </View>
            </View>

            {/* Footer com Valor */}
            <View style={styles.matriculaInfoFooterCompacto}>
              <Text style={styles.matriculaInfoFooterLabelCompacto}>Valor Mensal</Text>
              <Text style={styles.matriculaInfoFooterValueCompacto}>{formatCurrency(matricula.valor)}</Text>
            </View>
          </View>

          {/* Parcela Pendente - Design Moderno */}
          {matricula.status !== 'cancelada' && matricula.status !== 'finalizada' && 
           pagamentos.filter(p => p.status_pagamento_id === 1).map((pagamento, index) => (
            <View key={pagamento.id || index} style={styles.parcelaPendenteCard}>
              {/* Header com gradiente visual */}
              <View style={styles.parcelaPendenteHeader}>
                <View style={styles.parcelaPendenteIconContainer}>
                  <MaterialCommunityIcons name="calendar-clock" size={28} color="#fff" />
                </View>
                <View style={styles.parcelaPendenteInfo}>
                  <Text style={styles.parcelaPendenteLabel}>PRÓXIMO PAGAMENTO</Text>
                  <Text style={styles.parcelaPendenteTitulo}>
                    Parcela {pagamento.numero_parcela || index + 1}
                  </Text>
                </View>
                <View style={styles.parcelaPendenteBadge}>
                  <MaterialCommunityIcons name="clock-outline" size={14} color="#f59e0b" />
                  <Text style={styles.parcelaPendenteBadgeText}>Aguardando</Text>
                </View>
              </View>

              {/* Conteúdo Principal */}
              <View style={styles.parcelaPendenteBody}>
                <View style={styles.parcelaPendenteValorContainer}>
                  <Text style={styles.parcelaPendenteValorLabel}>Valor a pagar</Text>
                  <Text style={styles.parcelaPendenteValor}>
                    {formatCurrency(pagamento.valor)}
                  </Text>
                </View>
                
                <View style={styles.parcelaPendenteDivider} />
                
                <View style={styles.parcelaPendenteVencimentoContainer}>
                  <View style={styles.parcelaPendenteVencimentoIcon}>
                    <Feather name="calendar" size={18} color="#6b7280" />
                  </View>
                  <View>
                    <Text style={styles.parcelaPendenteVencimentoLabel}>Vencimento</Text>
                    <Text style={styles.parcelaPendenteVencimentoData}>
                      {formatDate(pagamento.data_vencimento)}
                    </Text>
                  </View>
                </View>
              </View>

              {/* Botão de Ação */}
              <Pressable
                style={({ pressed }) => [
                  styles.parcelaPendenteBotao,
                  pressed && styles.parcelaPendenteBotaoPressed,
                ]}
                onPress={() => handleBaixaPagamento(pagamento)}
              >
                <View style={styles.parcelaPendenteBotaoIconContainer}>
                  <Feather name="check" size={20} color="#fff" />
                </View>
                <Text style={styles.parcelaPendenteBotaoText}>Confirmar Pagamento</Text>
                <Feather name="arrow-right" size={18} color="#fff" style={{ opacity: 0.7 }} />
              </Pressable>
            </View>
          ))}

          {/* Tabela de Histórico (Pagamentos Confirmados) */}
          {pagamentos.filter(p => p.status_pagamento_id !== 1).length > 0 && (
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

              <View style={styles.tabelaContainer}>
                <View style={styles.tabelaHeader}>
                  <Text style={[styles.tabelaHeaderText, { flex: 0.6 }]}>ID</Text>
                  <Text style={[styles.tabelaHeaderText, { flex: 0.8 }]}>Parcela</Text>
                  <Text style={[styles.tabelaHeaderText, { flex: 1.2 }]}>Vencimento</Text>
                  <Text style={[styles.tabelaHeaderText, { flex: 1.2 }]}>Pagamento</Text>
                  <Text style={[styles.tabelaHeaderText, { flex: 1.5 }]}>Baixado por</Text>
                  <Text style={[styles.tabelaHeaderText, { flex: 1, textAlign: 'right' }]}>Valor</Text>
                  <Text style={[styles.tabelaHeaderText, { flex: 1, textAlign: 'center' }]}>Status</Text>
                </View>
                
                {pagamentos
                  .filter(p => p.status_pagamento_id !== 1)
                  .sort((a, b) => new Date(b.data_vencimento) - new Date(a.data_vencimento))
                  .map((pagamento, index) => (
                  <View key={pagamento.id || index} style={styles.tabelaLinha}>
                    <Text style={[styles.tabelaCelula, { flex: 0.6, fontSize: 12, color: '#6b7280' }]}>#{pagamento.id}</Text>
                    <Text style={[styles.tabelaCelula, { flex: 0.8 }]}>
                      {pagamento.numero_parcela || index + 1}
                    </Text>
                    <Text style={[styles.tabelaCelula, { flex: 1.2 }]}>
                      {formatDate(pagamento.data_vencimento)}
                    </Text>
                    <Text style={[styles.tabelaCelula, { flex: 1.2, color: '#10b981' }]}>
                      {pagamento.data_pagamento ? formatDate(pagamento.data_pagamento) : '-'}
                    </Text>
                    <View style={{ flex: 1.5 }}>
                      <Text style={[styles.tabelaCelula, { fontSize: 13, color: '#374151', fontWeight: '500' }]}>
                        {pagamento.baixado_por_nome || '-'}
                      </Text>
                      {pagamento.tipo_baixa_nome && (
                        <Text style={[styles.tabelaCelula, { fontSize: 11, color: '#6b7280', marginTop: 2 }]}>
                          {pagamento.tipo_baixa_nome}
                        </Text>
                      )}
                    </View>
                    <Text style={[styles.tabelaCelula, { flex: 1, textAlign: 'right', fontWeight: '600' }]}>
                      {formatCurrency(pagamento.valor)}
                    </Text>
                    <View style={{ flex: 1, alignItems: 'center' }}>
                      <View
                        style={[
                          styles.tabelaStatusBadge,
                          {
                            backgroundColor: getPagamentoStatusColor(
                              pagamento.status_pagamento_id
                            ),
                          },
                        ]}
                      >
                        <Text style={styles.tabelaStatusText}>
                          {getPagamentoStatusLabel(pagamento.status_pagamento_id)}
                        </Text>
                      </View>
                    </View>
                  </View>
                ))}
              </View>
            </View>
          )}

          {/* Card de Resumo Financeiro - Design Moderno */}
          {pagamentos.length > 0 && (
            <View style={styles.resumoFinanceiroCard}>
              {/* Header com progresso */}
              <View style={styles.resumoFinanceiroHeader}>
                <View style={styles.resumoFinanceiroTitleRow}>
                  <View style={styles.resumoFinanceiroIconContainer}>
                    <MaterialCommunityIcons name="chart-donut" size={24} color="#fff" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.resumoFinanceiroTitle}>Resumo Financeiro</Text>
                    <Text style={styles.resumoFinanceiroSubtitle}>
                      {Math.round((resumo.pago / resumo.total) * 100)}% pago do total
                    </Text>
                  </View>
                </View>
                
                {/* Barra de Progresso */}
                <View style={styles.progressBarContainer}>
                  <View style={styles.progressBarBackground}>
                    {/* Pago - Verde */}
                    <View 
                      style={[
                        styles.progressBarSegment, 
                        { 
                          width: `${(resumo.pago / resumo.total) * 100}%`,
                          backgroundColor: '#10b981',
                          borderTopLeftRadius: 8,
                          borderBottomLeftRadius: 8,
                        }
                      ]} 
                    />
                    {/* Atrasado - Vermelho */}
                    {resumo.atrasado > 0 && (
                      <View 
                        style={[
                          styles.progressBarSegment, 
                          { 
                            width: `${(resumo.atrasado / resumo.total) * 100}%`,
                            backgroundColor: '#ef4444',
                          }
                        ]} 
                      />
                    )}
                    {/* Pendente - Amarelo */}
                    <View 
                      style={[
                        styles.progressBarSegment, 
                        { 
                          width: `${(resumo.pendente / resumo.total) * 100}%`,
                          backgroundColor: '#f59e0b',
                          borderTopRightRadius: 8,
                          borderBottomRightRadius: 8,
                        }
                      ]} 
                    />
                  </View>
                </View>
              </View>

              {/* Grid de valores */}
              <View style={styles.resumoFinanceiroGrid}>
                {/* Pago */}
                <View style={[styles.resumoFinanceiroItem, styles.resumoItemPago]}>
                  <View style={styles.resumoItemIconContainer}>
                    <Feather name="check-circle" size={20} color="#10b981" />
                  </View>
                  <View style={styles.resumoItemContent}>
                    <Text style={styles.resumoItemLabel}>Pago</Text>
                    <Text style={[styles.resumoItemValue, { color: '#10b981' }]}>
                      {formatCurrency(resumo.pago)}
                    </Text>
                  </View>
                </View>

                {/* Atrasado */}
                {resumo.atrasado > 0 && (
                  <View style={[styles.resumoFinanceiroItem, styles.resumoItemAtrasado]}>
                    <View style={styles.resumoItemIconContainer}>
                      <Feather name="alert-triangle" size={20} color="#ef4444" />
                    </View>
                    <View style={styles.resumoItemContent}>
                      <Text style={styles.resumoItemLabel}>Atrasado</Text>
                      <Text style={[styles.resumoItemValue, { color: '#ef4444' }]}>
                        {formatCurrency(resumo.atrasado)}
                      </Text>
                    </View>
                  </View>
                )}

                {/* Pendente */}
                <View style={[styles.resumoFinanceiroItem, styles.resumoItemPendente]}>
                  <View style={styles.resumoItemIconContainer}>
                    <Feather name="clock" size={20} color="#f59e0b" />
                  </View>
                  <View style={styles.resumoItemContent}>
                    <Text style={styles.resumoItemLabel}>Pendente</Text>
                    <Text style={[styles.resumoItemValue, { color: '#f59e0b' }]}>
                      {formatCurrency(resumo.pendente)}
                    </Text>
                  </View>
                </View>
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
  headerCompacto: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  headerInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#f3f4f6',
  },
  headerTitulo: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  headerSubtitulo: {
    fontSize: 13,
    color: '#6b7280',
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
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 10,
    backgroundColor: '#eff6ff',
    borderRadius: 6,
    alignSelf: 'flex-start',
  },
  voltarButtonText: {
    fontSize: 13,
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
  // Novos estilos do Card de Informações da Matrícula
  matriculaInfoCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    marginBottom: 20,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 3,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  matriculaInfoHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    backgroundColor: '#f8fafc',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  matriculaInfoHeaderLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  matriculaInfoModalidadeIcon: {
    width: 44,
    height: 44,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  matriculaInfoModalidadeNome: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  matriculaInfoPlanoNome: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 1,
  },
  matriculaInfoStatusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 16,
  },
  matriculaInfoStatusText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#fff',
    textTransform: 'capitalize',
  },
  // Estilos Compactos
  matriculaInfoGridCompacto: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  matriculaInfoItemCompacto: {
    flex: 1,
    alignItems: 'center',
    gap: 4,
  },
  matriculaInfoItemLabelCompacto: {
    fontSize: 10,
    color: '#9ca3af',
    fontWeight: '500',
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  matriculaInfoItemValueCompacto: {
    fontSize: 13,
    fontWeight: '600',
    color: '#111827',
  },
  matriculaInfoDividerVertical: {
    width: 1,
    height: 32,
    backgroundColor: '#e5e7eb',
  },
  matriculaInfoFooterCompacto: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 12,
    backgroundColor: '#f0fdf4',
    borderBottomLeftRadius: 16,
    borderBottomRightRadius: 16,
  },
  matriculaInfoFooterLabelCompacto: {
    fontSize: 12,
    color: '#6b7280',
    fontWeight: '500',
  },
  matriculaInfoFooterValueCompacto: {
    fontSize: 18,
    fontWeight: '800',
    color: '#10b981',
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
  // Estilos da Tabela de Informações
  infoTable: {
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    overflow: 'hidden',
  },
  infoTableRow: {
    flexDirection: 'row',
    backgroundColor: '#fff',
  },
  infoTableRowBorder: {
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  infoTableCell: {
    flex: 1,
    padding: 14,
    backgroundColor: '#fff',
  },
  infoTableCellFull: {
    flex: 1,
    padding: 14,
    backgroundColor: '#f9fafb',
  },
  infoTableCellBorder: {
    borderRightWidth: 1,
    borderRightColor: '#e5e7eb',
  },
  infoTableLabel: {
    fontSize: 11,
    color: '#6b7280',
    fontWeight: '600',
    textTransform: 'uppercase',
    marginBottom: 6,
    letterSpacing: 0.5,
  },
  infoTableValue: {
    fontSize: 15,
    fontWeight: '600',
    color: '#111827',
  },
  infoTableValueBold: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  infoTableValueSmall: {
    fontSize: 13,
    color: '#6b7280',
  },
  infoTableValueHighlight: {
    fontSize: 20,
    fontWeight: '700',
    color: '#3b82f6',
  },
  // Estilos antigos mantidos para compatibilidade
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
  // Novos estilos do Resumo Financeiro
  resumoFinanceiroCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  resumoFinanceiroHeader: {
    backgroundColor: '#f8fafc',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  resumoFinanceiroTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  resumoFinanceiroIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: '#3b82f6',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  resumoFinanceiroTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 2,
  },
  resumoFinanceiroSubtitle: {
    fontSize: 13,
    color: '#6b7280',
  },
  resumoTotalBadge: {
    backgroundColor: '#111827',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
  },
  resumoTotalBadgeText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '700',
  },
  progressBarContainer: {
    marginTop: 4,
  },
  progressBarBackground: {
    height: 10,
    backgroundColor: '#e5e7eb',
    borderRadius: 8,
    flexDirection: 'row',
    overflow: 'hidden',
  },
  progressBarSegment: {
    height: '100%',
  },
  resumoFinanceiroGrid: {
    flexDirection: 'row',
    padding: 16,
    gap: 12,
  },
  resumoFinanceiroItem: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: 12,
    gap: 12,
  },
  resumoItemPago: {
    backgroundColor: '#ecfdf5',
  },
  resumoItemAtrasado: {
    backgroundColor: '#fef2f2',
  },
  resumoItemPendente: {
    backgroundColor: '#fffbeb',
  },
  resumoItemIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#fff',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 1,
  },
  resumoItemContent: {
    flex: 1,
  },
  resumoItemLabel: {
    fontSize: 12,
    color: '#6b7280',
    fontWeight: '500',
    marginBottom: 2,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  resumoItemValue: {
    fontSize: 18,
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
  parcelaAtualCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
    marginBottom: 20,
    shadowColor: '#f59e0b',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 6,
    borderWidth: 2,
    borderColor: '#fef3c7',
  },
  parcelaAtualHeader: {
    marginBottom: 16,
  },
  parcelaAtualTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  parcelaAtualTitulo: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  parcelaAtualVencimento: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 2,
  },
  parcelaAtualStatusBadge: {
    backgroundColor: '#fef3c7',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#fde68a',
  },
  parcelaAtualStatusText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#f59e0b',
  },
  parcelaAtualValor: {
    backgroundColor: '#f9fafb',
    padding: 16,
    borderRadius: 8,
    marginBottom: 16,
    alignItems: 'center',
  },
  parcelaAtualValorLabel: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '500',
    marginBottom: 4,
  },
  parcelaAtualValorTexto: {
    fontSize: 32,
    fontWeight: '800',
    color: '#f59e0b',
  },
  btnConfirmarDestaque: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    paddingVertical: 16,
    backgroundColor: '#10b981',
    borderRadius: 8,
    shadowColor: '#10b981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 6,
    elevation: 8,
  },
  btnConfirmarDestaqueText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#fff',
    letterSpacing: 0.5,
  },
  // Novos estilos da Parcela Pendente
  parcelaPendenteCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    marginBottom: 20,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 6,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  parcelaPendenteHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#fefce8',
    borderBottomWidth: 1,
    borderBottomColor: '#fef08a',
  },
  parcelaPendenteIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 12,
    backgroundColor: '#f59e0b',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  parcelaPendenteInfo: {
    flex: 1,
  },
  parcelaPendenteLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: '#92400e',
    letterSpacing: 1,
    marginBottom: 2,
  },
  parcelaPendenteTitulo: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  parcelaPendenteBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#fff',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#fde68a',
  },
  parcelaPendenteBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#f59e0b',
  },
  parcelaPendenteBody: {
    padding: 20,
  },
  parcelaPendenteValorContainer: {
    alignItems: 'center',
    marginBottom: 16,
  },
  parcelaPendenteValorLabel: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '500',
    marginBottom: 4,
  },
  parcelaPendenteValor: {
    fontSize: 36,
    fontWeight: '800',
    color: '#111827',
    letterSpacing: -1,
  },
  parcelaPendenteDivider: {
    height: 1,
    backgroundColor: '#e5e7eb',
    marginVertical: 16,
  },
  parcelaPendenteVencimentoContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  parcelaPendenteVencimentoIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#f3f4f6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  parcelaPendenteVencimentoLabel: {
    fontSize: 12,
    color: '#6b7280',
    fontWeight: '500',
  },
  parcelaPendenteVencimentoData: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  parcelaPendenteBotao: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    paddingVertical: 16,
    paddingHorizontal: 20,
    backgroundColor: '#10b981',
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 12,
  },
  parcelaPendenteBotaoPressed: {
    backgroundColor: '#059669',
    transform: [{ scale: 0.98 }],
  },
  parcelaPendenteBotaoIconContainer: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: 'rgba(255,255,255,0.2)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  parcelaPendenteBotaoText: {
    flex: 1,
    fontSize: 16,
    fontWeight: '700',
    color: '#fff',
  },
  tabelaContainer: {
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    overflow: 'hidden',
  },
  tabelaHeader: {
    flexDirection: 'row',
    backgroundColor: '#f3f4f6',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 2,
    borderBottomColor: '#e5e7eb',
  },
  tabelaHeaderText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#374151',
    textTransform: 'uppercase',
  },
  tabelaLinha: {
    flexDirection: 'row',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
  },
  tabelaCelula: {
    fontSize: 14,
    color: '#111827',
  },
  tabelaStatusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 10,
  },
  tabelaStatusText: {
    fontSize: 10,
    fontWeight: '600',
    color: '#fff',
  },
  modalOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 9999,
  },
  modalConfirmContainer: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 24,
    width: '90%',
    maxWidth: 400,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.3,
    shadowRadius: 12,
    elevation: 10,
  },
  modalConfirmHeader: {
    alignItems: 'center',
    marginBottom: 24,
  },
  modalConfirmTitulo: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111827',
    marginTop: 12,
  },
  modalConfirmResumo: {
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    padding: 16,
    marginBottom: 20,
    gap: 12,
  },
  modalConfirmItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  modalConfirmLabel: {
    fontSize: 14,
    color: '#6b7280',
    fontWeight: '500',
  },
  modalConfirmValor: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  modalConfirmTexto: {
    fontSize: 14,
    color: '#4b5563',
    textAlign: 'center',
    marginBottom: 24,
    lineHeight: 20,
  },
  modalConfirmBotoes: {
    flexDirection: 'row',
    gap: 12,
  },
  modalConfirmBotaoCancelar: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: '#f3f4f6',
    alignItems: 'center',
  },
  modalConfirmBotaoCancelarText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#6b7280',
  },
  modalConfirmBotaoConfirmar: {
    flex: 1,
    flexDirection: 'row',
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: '#10b981',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  modalConfirmBotaoConfirmarText: {
    fontSize: 15,
    fontWeight: '700',
    color: '#fff',
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
