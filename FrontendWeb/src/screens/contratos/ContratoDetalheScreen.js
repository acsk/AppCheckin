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
    if (!user || user.role_id !== 3) {
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
      const responsePagamentos = await api.get(`/superadmin/contratos/${id}/pagamentos`);
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
      case 1: return '#FFA500'; // Pendente
      case 2: return '#4CAF50'; // Confirmado
      case 3: return '#F44336'; // Cancelado
      case 4: return '#FF9800'; // Atrasado
      default: return '#999';
    }
  };

  const getStatusNome = (statusId) => {
    switch (statusId) {
      case 1: return 'Pendente';
      case 2: return 'Confirmado';
      case 3: return 'Cancelado';
      case 4: return 'Atrasado';
      default: return 'Desconhecido';
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Detalhes do Contrato">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#FF6B00" />
          <Text style={styles.loadingText}>Carregando dados do contrato...</Text>
        </View>
      </LayoutBase>
    );
  }

  if (!contrato) {
    return (
      <LayoutBase title="Detalhes do Contrato">
        <View style={styles.errorContainer}>
          <Text style={styles.errorText}>Contrato não encontrado</Text>
          <TouchableOpacity style={styles.voltarButton} onPress={() => router.back()}>
            <Text style={styles.voltarButtonText}>Voltar</Text>
          </TouchableOpacity>
        </View>
      </LayoutBase>
    );
  }

  const resumo = calcularResumo();

  return (
    <LayoutBase title={`Contrato #${contrato.id}`}>
      <ScrollView style={styles.container}>
      <View style={[styles.content, isDesktop && styles.contentDesktop]}>
        {/* Botão Voltar */}
        <TouchableOpacity style={styles.voltarButton} onPress={() => router.push('/contratos')}>
          <Text style={styles.voltarButtonText}>← Voltar</Text>
        </TouchableOpacity>

        {/* Card com informações do contrato */}
        <View style={styles.card}>
          <View style={styles.cardTitleRow}>
            <View style={styles.cardTitleIcon}>
              <Feather name="info" size={20} color="#f97316" />
            </View>
            <Text style={styles.cardTitle}>Informações do Contrato</Text>
          </View>
          
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Academia</Text>
            <Text style={styles.infoValue}>{contrato.tenant_nome}</Text>
          </View>
          
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Plano</Text>
            <Text style={styles.infoValue}>{contrato.plano_nome}</Text>
          </View>
          
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Valor do Plano</Text>
            <Text style={[styles.infoValue, styles.valorDestaque]}>
              {formatarValorMonetario(contrato.plano_valor)}
            </Text>
          </View>
          
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Status</Text>
            <View style={[styles.statusBadge, { backgroundColor: getStatusColor(contrato.status_id) }]}>
              <Text style={styles.statusText}>{contrato.status_nome}</Text>
            </View>
          </View>
          
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Data de Início</Text>
            <Text style={styles.infoValue}>{formatarData(contrato.data_inicio)}</Text>
          </View>
          
          {contrato.observacoes && (
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Observações</Text>
              <Text style={styles.infoValue}>{contrato.observacoes}</Text>
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
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#ffffffff',
  },
  content: {
    padding: 20,
    gap: 16,
  },
  contentDesktop: {
   
    alignSelf: 'center',
    width: '100%',
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
  voltarButton: {
    alignSelf: 'flex-start',
    marginBottom: 16,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: 'rgba(249,115,22,0.12)',
  },
  voltarButtonText: {
    fontSize: 15,
    color: '#f97316',
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
