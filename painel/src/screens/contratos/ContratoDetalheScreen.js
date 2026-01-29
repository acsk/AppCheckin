import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator, TouchableOpacity, Platform, useWindowDimensions, Alert } from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import api from '../../services/api';
import { formatarData, formatarValorMonetario } from '../../utils/formatadores';
import BaixaPagamentoModal from '../../components/BaixaPagamentoModal';
import LayoutBase from '../../components/LayoutBase';
import { showSuccess, showError } from '../../utils/toast';
import { Feather } from '@expo/vector-icons';
import { authService } from '../../services/authService';

export default function ContratoDetalheScreen() {
  const { id } = useLocalSearchParams();
  const { width } = useWindowDimensions();
  const isDesktop = width >= 768;

  const [loading, setLoading] = useState(true);
  const [contrato, setContrato] = useState(null);
  const [pagamentos, setPagamentos] = useState([]);
  const [modalVisible, setModalVisible] = useState(false);
  const [pagamentoSelecionado, setPagamentoSelecionado] = useState(null);

  useEffect(() => {
    checkAccess();
  }, []);

  const checkAccess = async () => {
    const user = await authService.getCurrentUser();
    if (!user || user.papel_id !== 4) {
      showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
      router.replace('/');
      return;
    }
    carregarDados();
  };

  const carregarDados = async () => {
    try {
      setLoading(true);
      
      // Buscar dados do contrato
      const responseContrato = await api.get(`/superadmin/contratos/${id}`);
      const dadosContrato = responseContrato.data.contrato || responseContrato.data;
      setContrato(dadosContrato);

      // Buscar histórico de pagamentos
      const responsePagamentos = await api.get(`/superadmin/contratos/${id}/pagamentos-contrato`);
      const dadosPagamentos = responsePagamentos.data.pagamentos || responsePagamentos.data.data || [];
      setPagamentos(dadosPagamentos);
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
      showError('Não foi possível carregar os dados do contrato');
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
    carregarDados();
  };

  const calcularResumo = () => {
    const total = pagamentos.reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    const pago = pagamentos
      .filter(p => p.status_pagamento_id === 2)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    const pendente = total - pago;

    return { total, pago, pendente };
  };

  const getStatusColor = (statusId) => {
    switch (statusId) {
      case 1: return '#3B82F6'; // Aguardando (azul)
      case 2: return '#4CAF50'; // Pago (verde)
      case 3: return '#F44336'; // Atrasado (vermelho)
      case 4: return '#6B7280'; // Cancelado (cinza)
      default: return '#999';
    }
  };

  const getStatusNome = (statusId) => {
    switch (statusId) {
      case 1: return 'Aguardando';
      case 2: return 'Pago';
      case 3: return 'Atrasado';
      case 4: return 'Cancelado';
      default: return 'Desconhecido';
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Detalhes do Contrato" noPadding>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#FF6B00" />
          <Text style={styles.loadingText}>Carregando dados do contrato...</Text>
        </View>
      </LayoutBase>
    );
  }

  if (!contrato) {
    return (
      <LayoutBase title="Detalhes do Contrato" noPadding>
        <View style={styles.errorContainer}>
          <Text style={styles.errorText}>Contrato não encontrado</Text>
          <TouchableOpacity style={styles.voltarButtonError} onPress={() => router.back()}>
            <Text style={styles.voltarButtonErrorText}>Voltar</Text>
          </TouchableOpacity>
        </View>
      </LayoutBase>
    );
  }

  const resumo = calcularResumo();

  return (
    <LayoutBase title={`Contrato #${contrato.id}`} noPadding>
      <View style={styles.container}>
        {/* Banner Header */}
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <TouchableOpacity onPress={() => router.push('/contratos')} style={styles.backButtonBanner}>
                <Feather name="arrow-left" size={24} color="#fff" />
              </TouchableOpacity>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="file-text" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Contrato #{contrato.id}</Text>
                <Text style={styles.bannerSubtitle} numberOfLines={1}>
                  {contrato.tenant_nome} • {contrato.plano_nome}
                </Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>

          {/* Card de Resumo Rápido */}
          <View style={[styles.summaryCard, !isDesktop && styles.summaryCardMobile]}>
            <View style={styles.summaryCardHeader}>
              <View style={styles.summaryCardInfo}>
                <View style={styles.summaryCardIconContainer}>
                  <Feather name="info" size={20} color="#f97316" />
                </View>
                <View>
                  <Text style={styles.summaryCardTitle}>Detalhes do Contrato</Text>
                  <Text style={styles.summaryCardSubtitle}>
                    Status: {contrato.status_nome}
                  </Text>
                </View>
              </View>
              <View style={[styles.statusBadgeLarge, { backgroundColor: getStatusColor(contrato.status_id) }]}>
                <Text style={styles.statusTextLarge}>{contrato.status_nome?.toUpperCase()}</Text>
              </View>
            </View>
            
            {/* Botão de Ações Rápidas */}
            {contrato.status_nome?.toLowerCase() === 'ativo' && pagamentos.some(p => p.status_pagamento_id === 1) && (
              <View style={styles.quickActionsRow}>
                <TouchableOpacity 
                  style={styles.quickActionButton}
                  onPress={() => {
                    const pendente = pagamentos.find(p => p.status_pagamento_id === 1);
                    if (pendente) handleBaixaPagamento(pendente);
                  }}
                >
                  <Feather name="check-circle" size={18} color="#fff" />
                  <Text style={styles.quickActionText}>Baixa Manual</Text>
                </TouchableOpacity>
              </View>
            )}
          </View>
        </View>

        <ScrollView style={styles.scrollContent} contentContainerStyle={styles.scrollContentContainer}>

        {/* Card com informações do contrato - Layout Compacto */}
        <View style={styles.card}>
          <View style={styles.cardTitleRow}>
            <View style={styles.cardTitleIcon}>
              <Feather name="info" size={20} color="#f97316" />
            </View>
            <Text style={styles.cardTitle}>Informações do Contrato</Text>
          </View>
          
          <View style={styles.infoGrid}>
            <View style={styles.infoGridItem}>
              <Feather name="home" size={16} color="#6b7280" />
              <View style={styles.infoGridContent}>
                <Text style={styles.infoGridLabel}>Academia</Text>
                <Text style={styles.infoGridValue}>{contrato.tenant_nome}</Text>
              </View>
            </View>
            
            <View style={styles.infoGridItem}>
              <Feather name="package" size={16} color="#6b7280" />
              <View style={styles.infoGridContent}>
                <Text style={styles.infoGridLabel}>Plano</Text>
                <Text style={styles.infoGridValue}>{contrato.plano_nome}</Text>
              </View>
            </View>
            
            <View style={styles.infoGridItem}>
              <Feather name="dollar-sign" size={16} color="#10b981" />
              <View style={styles.infoGridContent}>
                <Text style={styles.infoGridLabel}>Valor</Text>
                <Text style={[styles.infoGridValue, styles.infoGridValueDestaque]}>
                  {formatarValorMonetario(contrato.plano_valor)}
                </Text>
              </View>
            </View>
            
            <View style={styles.infoGridItem}>
              <Feather name="calendar" size={16} color="#6b7280" />
              <View style={styles.infoGridContent}>
                <Text style={styles.infoGridLabel}>Início</Text>
                <Text style={styles.infoGridValue}>{formatarData(contrato.data_inicio)}</Text>
              </View>
            </View>
          </View>
          
          {contrato.observacoes && (
            <View style={styles.obsContainer}>
              <Feather name="message-square" size={14} color="#6b7280" />
              <Text style={styles.obsText}>{contrato.observacoes}</Text>
            </View>
          )}
        </View>

        {/* Tabela de histórico de pagamentos */}
        <View style={styles.card}>
          <View style={styles.cardTitleRow}>
            <View style={styles.cardTitleIcon}>
              <Feather name="file-text" size={20} color="#f97316" />
            </View>
            <Text style={styles.cardTitle}>Histórico de Pagamentos</Text>
          </View>
          
          {isDesktop ? (
            <View style={styles.table}>
              {/* Cabeçalho da tabela */}
              <View style={styles.tableHeader}>
                <Text style={[styles.tableHeaderText, { flex: 0.5 }]}>ID</Text>
                <Text style={[styles.tableHeaderText, { flex: 1, textAlign: 'center' }]}>Vencimento</Text>
                <Text style={[styles.tableHeaderText, { flex: 1 }]}>Valor</Text>
                <Text style={[styles.tableHeaderText, { flex: 1 }]}>Status</Text>
                <Text style={[styles.tableHeaderText, { flex: 1, textAlign: 'center' }]}>Pagamento</Text>
                <Text style={[styles.tableHeaderText, { flex: 1 }]}>Forma</Text>
                <Text style={[styles.tableHeaderText, { flex: 1 }]}>Ações</Text>
              </View>

              {/* Linhas da tabela */}
              {pagamentos.length > 0 ? (
                pagamentos.map((pagamento) => (
                  <View key={pagamento.id} style={styles.tableRow}>
                    <Text style={[styles.tableCellText, { flex: 0.5 }]}>#{pagamento.id}</Text>
                    <Text style={[styles.tableCellText, { flex: 1, textAlign: 'center' }]}>
                      {formatarData(pagamento.data_vencimento)}
                    </Text>
                    <Text style={[styles.tableCellText, { flex: 1 }]}>
                      {formatarValorMonetario(pagamento.valor)}
                    </Text>
                    <View style={[styles.tableCellText, { flex: 1 }]}>
                      <View style={[styles.statusBadge, { backgroundColor: getStatusColor(pagamento.status_pagamento_id) }]}>
                        <Text style={styles.statusText}>{getStatusNome(pagamento.status_pagamento_id)}</Text>
                      </View>
                    </View>
                    <Text style={[styles.tableCellText, { flex: 1, textAlign: 'center' }]}>
                      {pagamento.data_pagamento ? formatarData(pagamento.data_pagamento) : '-'}
                    </Text>
                    <Text style={[styles.tableCellText, { flex: 1 }]}>
                      {pagamento.forma_pagamento_nome || '-'}
                    </Text>
                    <View style={[styles.tableCellText, { flex: 1 }]}>
                      {pagamento.status_pagamento_id === 1 && (
                        <TouchableOpacity 
                          style={styles.actionButton}
                          onPress={() => handleBaixaPagamento(pagamento)}
                        >
                          <Text style={styles.actionButtonText}>Baixa Manual</Text>
                        </TouchableOpacity>
                      )}
                    </View>
                  </View>
                ))
              ) : (
                <View style={styles.emptyState}>
                  <Text style={styles.emptyStateText}>Nenhum pagamento registrado</Text>
                </View>
              )}
            </View>
          ) : (
            // Layout mobile - cards
            <View style={styles.mobileList}>
              {pagamentos.length > 0 ? (
                pagamentos.map((pagamento) => (
                  <View key={pagamento.id} style={styles.mobileCard}>
                    <View style={styles.mobileCardHeader}>
                      <Text style={styles.mobileCardId}>Pagamento #{pagamento.id}</Text>
                      <View style={[styles.statusBadge, { backgroundColor: getStatusColor(pagamento.status_pagamento_id) }]}>
                        <Text style={styles.statusText}>{getStatusNome(pagamento.status_pagamento_id)}</Text>
                      </View>
                    </View>
                    <View style={styles.mobileCardRow}>
                      <Text style={styles.mobileCardLabel}>Vencimento:</Text>
                      <Text style={[styles.mobileCardValue, styles.centerText]}>{formatarData(pagamento.data_vencimento)}</Text>
                    </View>
                    <View style={styles.mobileCardRow}>
                      <Text style={styles.mobileCardLabel}>Valor:</Text>
                      <Text style={styles.mobileCardValue}>{formatarValorMonetario(pagamento.valor)}</Text>
                    </View>
                    {pagamento.data_pagamento && (
                      <View style={styles.mobileCardRow}>
                        <Text style={styles.mobileCardLabel}>Pagamento:</Text>
                        <Text style={[styles.mobileCardValue, styles.centerText]}>{formatarData(pagamento.data_pagamento)}</Text>
                      </View>
                    )}
                    <View style={styles.mobileCardRow}>
                      <Text style={styles.mobileCardLabel}>Forma:</Text>
                      <Text style={styles.mobileCardValue}>{pagamento.forma_pagamento_nome || '-'}</Text>
                    </View>
                    {pagamento.status_pagamento_id === 1 && (
                      <TouchableOpacity 
                        style={[styles.actionButton, { marginTop: 10, width: '100%' }]}
                        onPress={() => handleBaixaPagamento(pagamento)}
                      >
                        <Text style={styles.actionButtonText}>Baixa Manual</Text>
                      </TouchableOpacity>
                    )}
                  </View>
                ))
              ) : (
                <View style={styles.emptyState}>
                  <Text style={styles.emptyStateText}>Nenhum pagamento registrado</Text>
                </View>
              )}
            </View>
          )}
        </View>

        {/* Card com resumo financeiro */}
        <View style={styles.card}>
          <View style={styles.cardTitleRow}>
            <View style={styles.cardTitleIcon}>
              <Feather name="dollar-sign" size={20} color="#f97316" />
            </View>
            <Text style={styles.cardTitle}>Resumo Financeiro</Text>
          </View>
          <View style={styles.resumoRow}>
            <Text style={styles.resumoLabel}>Total do Contrato</Text>
            <Text style={styles.resumoValue}>{formatarValorMonetario(resumo.total)}</Text>
          </View>
          <View style={styles.resumoRow}>
            <Text style={styles.resumoLabel}>Valor Pago</Text>
            <Text style={[styles.resumoValue, { color: '#4CAF50' }]}>
              {formatarValorMonetario(resumo.pago)}
            </Text>
          </View>
          <View style={styles.resumoRow}>
            <Text style={styles.resumoLabel}>Valor Pendente</Text>
            <Text style={[styles.resumoValue, { color: '#d97706' }]}>
              {formatarValorMonetario(resumo.pendente)}
            </Text>
          </View>
        </View>

      {/* Modal de baixa manual */}
      {pagamentoSelecionado && (
        <BaixaPagamentoModal
          visible={modalVisible}
          onClose={() => {
            setModalVisible(false);
            setPagamentoSelecionado(null);
          }}
          pagamento={pagamentoSelecionado}
          onSuccess={handleBaixaSuccess}
        />
      )}
        </ScrollView>
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  // Banner Header
  bannerContainer: {
    backgroundColor: '#f8fafc',
  },
  banner: {
    backgroundColor: '#f97316',
    paddingVertical: 28,
    paddingHorizontal: 24,
    position: 'relative',
    overflow: 'hidden',
  },
  bannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    zIndex: 2,
  },
  backButtonBanner: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  bannerIconContainer: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconOuter: {
    width: 64,
    height: 64,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconInner: {
    width: 48,
    height: 48,
    borderRadius: 14,
    backgroundColor: 'rgba(255, 255, 255, 0.25)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerTextContainer: {
    flex: 1,
  },
  bannerTitle: {
    fontSize: 26,
    fontWeight: '800',
    color: '#fff',
    letterSpacing: -0.5,
  },
  bannerSubtitle: {
    fontSize: 14,
    color: 'rgba(255, 255, 255, 0.85)',
    marginTop: 4,
    lineHeight: 20,
  },
  bannerDecoration: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    width: 200,
    zIndex: 1,
  },
  decorCircle1: {
    position: 'absolute',
    top: -30,
    right: -30,
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
  },
  decorCircle2: {
    position: 'absolute',
    top: 40,
    right: 60,
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  decorCircle3: {
    position: 'absolute',
    bottom: -20,
    right: 20,
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(255, 255, 255, 0.06)',
  },
  // Summary Card
  summaryCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 20,
    marginHorizontal: 20,
    marginTop: -24,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
    zIndex: 10,
  },
  summaryCardMobile: {
    marginHorizontal: 16,
    padding: 16,
  },
  summaryCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    flexWrap: 'wrap',
  },
  summaryCardInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  summaryCardIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  summaryCardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1f2937',
  },
  summaryCardSubtitle: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  quickActionsRow: {
    flexDirection: 'row',
    marginTop: 16,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
    gap: 12,
  },
  quickActionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#10b981',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 10,
    gap: 8,
  },
  quickActionText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '700',
  },
  statusBadgeLarge: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
  },
  statusTextLarge: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  // Scroll Content
  scrollContent: {
    flex: 1,
  },
  scrollContentContainer: {
    padding: 20,
    gap: 16,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  errorText: {
    fontSize: 18,
    color: '#F44336',
    marginBottom: 20,
  },
  voltarButtonError: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: '#f97316',
  },
  voltarButtonErrorText: {
    fontSize: 15,
    color: '#fff',
    fontWeight: '700',
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: 14,
    padding: 20,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.1,
    shadowRadius: 18,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 12 },
        shadowOpacity: 0.1,
        shadowRadius: 18,
      },
      android: {
        elevation: 4,
      },
      web: {
        boxShadow: '0 12px 28px rgba(15,23,42,0.12)',
      },
    }),
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 14,
    alignSelf: 'stretch',
    paddingBottom: 10,
    borderBottomWidth: 2,
    borderBottomColor: '#f97316',
  },
  cardTitle: {
    fontSize: 20,
    fontWeight: '900',
    color: '#111827',
    letterSpacing: 0.3,
  },
  cardTitleIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(249,115,22,0.14)',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(249,115,22,0.32)',
  },
  infoRow: {
    flexDirection: 'row',
    marginBottom: 12,
    alignItems: 'center',
    gap: 10,
  },
  infoLabel: {
    fontSize: 14,
    color: '#6b7280',
    fontWeight: '700',
    letterSpacing: 0.2,
  },
  infoValue: {
    fontSize: 15,
    color: '#111827',
    flex: 1,
    fontWeight: '600',
  },
  // Grid Compacto de Informações
  infoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  infoGridItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#f9fafb',
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 10,
    minWidth: '48%',
    flex: 1,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  infoGridContent: {
    flex: 1,
  },
  infoGridLabel: {
    fontSize: 11,
    color: '#6b7280',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  infoGridValue: {
    fontSize: 14,
    color: '#111827',
    fontWeight: '700',
    marginTop: 2,
  },
  infoGridValueDestaque: {
    color: '#10b981',
    fontSize: 16,
    fontWeight: '800',
  },
  obsContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  obsText: {
    flex: 1,
    fontSize: 13,
    color: '#6b7280',
    lineHeight: 18,
  },
  valorDestaque: {
    fontWeight: '800',
    color: '#f97316',
    fontSize: 18,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    backgroundColor: 'rgba(249,115,22,0.12)',
  },
  statusText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.3,
  },
  table: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 12,
    overflow: 'hidden',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f8fafc',
    padding: 14,
  },
  tableHeaderText: {
    fontSize: 12,
    fontWeight: '800',
    color: '#374151',
    letterSpacing: 0.4,
  },
  tableRow: {
    flexDirection: 'row',
    padding: 14,
    alignItems: 'center',
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  tableCellText: {
    fontSize: 13,
    color: '#111827',
  },
  mobileList: {
    marginTop: 10,
  },
  mobileCard: {
    backgroundColor: '#ffffff',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  mobileCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  mobileCardId: {
    fontSize: 15,
    fontWeight: '800',
    color: '#f97316',
  },
  mobileCardRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  mobileCardLabel: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '700',
  },
  mobileCardValue: {
    fontSize: 13,
    color: '#111827',
    fontWeight: '600',
  },
  centerText: {
    textAlign: 'center',
  },
  actionButton: {
    backgroundColor: '#22c55e',
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    alignItems: 'center',
  },
  actionButtonText: {
    color: '#0b1224',
    fontSize: 12,
    fontWeight: '800',
  },
  emptyState: {
    padding: 40,
    alignItems: 'center',
  },
  emptyStateText: {
    fontSize: 14,
    color: '#6b7280',
  },
  resumoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 10,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  resumoLabel: {
    fontSize: 14,
    color: '#4b5563',
    fontWeight: '700',
  },
  resumoValue: {
    fontSize: 15,
    color: '#e5e7eb',
    fontWeight: '800',
  },
});
