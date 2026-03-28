import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  useWindowDimensions,
  Modal,
  StyleSheet,
  TextInput,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { auditoriaService } from '../../services/auditoriaService';
import { showError } from '../../utils/toast';

const MESES = [
  '', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

const formatCurrency = (value) => {
  const num = parseFloat(value);
  if (isNaN(num)) return 'R$ 0,00';
  return `R$ ${num.toFixed(2).replace('.', ',')}`;
};

const statusColor = (status) => {
  switch (status?.toLowerCase()) {
    case 'pago':
      return { bg: '#d1fae5', text: '#16a34a' };
    case 'aguardando':
      return { bg: '#fef3c7', text: '#d97706' };
    case 'atrasado':
      return { bg: '#fee2e2', text: '#ef4444' };
    case 'cancelado':
      return { bg: '#e5e7eb', text: '#6b7280' };
    default:
      return { bg: '#e5e7eb', text: '#6b7280' };
  }
};

export default function PagamentosDuplicadosScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [loading, setLoading] = useState(true);
  const [resumo, setResumo] = useState(null);
  const [grupos, setGrupos] = useState([]);

  // Detalhe modal
  const [modalDetalhe, setModalDetalhe] = useState(false);
  const [loadingDetalhe, setLoadingDetalhe] = useState(false);
  const [detalheGrupo, setDetalheGrupo] = useState(null);
  const [pagamentosDetalhe, setPagamentosDetalhe] = useState([]);

  // Filtros
  const [filtroAno, setFiltroAno] = useState('');
  const [filtroMes, setFiltroMes] = useState('');

  useEffect(() => {
    carregarResumo();
  }, []);

  const carregarResumo = async () => {
    try {
      setLoading(true);
      const response = await auditoriaService.pagamentosDuplicados();
      setResumo(response.resumo || { total_grupos_duplicados: 0, total_pagamentos_envolvidos: 0 });
      setGrupos(response.grupos || []);
    } catch (error) {
      console.error('Erro ao carregar auditoria:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao carregar dados de auditoria');
    } finally {
      setLoading(false);
    }
  };

  const abrirDetalhe = async (grupo) => {
    setDetalheGrupo(grupo);
    setModalDetalhe(true);
    try {
      setLoadingDetalhe(true);
      const filtros = {
        aluno_id: grupo.aluno_id,
        matricula_id: grupo.matricula_id,
        ano: grupo.ano,
        mes: grupo.mes,
      };
      const response = await auditoriaService.pagamentosDuplicadosDetalhe(filtros);
      setPagamentosDetalhe(response.pagamentos || []);
    } catch (error) {
      console.error('Erro ao carregar detalhe:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao carregar detalhe');
    } finally {
      setLoadingDetalhe(false);
    }
  };

  const fecharDetalhe = () => {
    setModalDetalhe(false);
    setDetalheGrupo(null);
    setPagamentosDetalhe([]);
  };

  const gruposFiltrados = grupos.filter((g) => {
    if (filtroAno && String(g.ano) !== filtroAno) return false;
    if (filtroMes && String(g.mes) !== filtroMes) return false;
    return true;
  });

  if (loading) {
    return (
      <LayoutBase title="Auditoria" subtitle="Pagamentos duplicados">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando auditoria...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Auditoria" subtitle="Pagamentos duplicados">
      <ScrollView style={styles.container} contentContainerStyle={{ paddingBottom: 40 }}>
        {/* Header */}
        <View style={styles.headerRow}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.push('/auditoria')}>
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.refreshButton} onPress={carregarResumo}>
            <Feather name="refresh-cw" size={16} color="#f97316" />
            {!isMobile && <Text style={styles.refreshButtonText}>Atualizar</Text>}
          </TouchableOpacity>
        </View>

        {/* Cards de Resumo */}
        <View style={[styles.resumoRow, isMobile && { flexDirection: 'column' }]}>
          <View style={[styles.resumoCard, { borderLeftColor: '#ef4444' }]}>
            <Feather name="alert-triangle" size={24} color="#ef4444" />
            <View style={{ flex: 1 }}>
              <Text style={styles.resumoCardLabel}>Grupos Duplicados</Text>
              <Text style={[styles.resumoCardValue, { color: '#ef4444' }]}>
                {resumo?.total_grupos_duplicados || 0}
              </Text>
            </View>
          </View>
          <View style={[styles.resumoCard, { borderLeftColor: '#f59e0b' }]}>
            <Feather name="copy" size={24} color="#f59e0b" />
            <View style={{ flex: 1 }}>
              <Text style={styles.resumoCardLabel}>Pagamentos Envolvidos</Text>
              <Text style={[styles.resumoCardValue, { color: '#f59e0b' }]}>
                {resumo?.total_pagamentos_envolvidos || 0}
              </Text>
            </View>
          </View>
        </View>

        {/* Filtros */}
        <View style={[styles.filtrosRow, isMobile && { flexDirection: 'column' }]}>
          <View style={styles.filtroGroup}>
            <Text style={styles.filtroLabel}>Ano</Text>
            <TextInput
              style={styles.filtroInput}
              value={filtroAno}
              onChangeText={(t) => setFiltroAno(t.replace(/[^0-9]/g, ''))}
              placeholder="Ex: 2026"
              keyboardType="number-pad"
              maxLength={4}
            />
          </View>
          <View style={styles.filtroGroup}>
            <Text style={styles.filtroLabel}>Mês</Text>
            <TextInput
              style={styles.filtroInput}
              value={filtroMes}
              onChangeText={(t) => setFiltroMes(t.replace(/[^0-9]/g, ''))}
              placeholder="1-12"
              keyboardType="number-pad"
              maxLength={2}
            />
          </View>
          {(filtroAno || filtroMes) && (
            <TouchableOpacity
              style={styles.filtroClearBtn}
              onPress={() => { setFiltroAno(''); setFiltroMes(''); }}
            >
              <Feather name="x" size={14} color="#ef4444" />
              <Text style={{ fontSize: 12, color: '#ef4444', fontWeight: '600' }}>Limpar</Text>
            </TouchableOpacity>
          )}
        </View>

        {gruposFiltrados.length === 0 ? (
          <View style={styles.emptyState}>
            <Feather name="check-circle" size={48} color="#16a34a" />
            <Text style={styles.emptyTitle}>Tudo certo!</Text>
            <Text style={styles.emptySubtext}>
              {grupos.length === 0
                ? 'Não foram encontrados pagamentos duplicados'
                : 'Nenhum grupo corresponde aos filtros aplicados'}
            </Text>
          </View>
        ) : (
          <View style={{ gap: 12 }}>
            <Text style={styles.sectionTitle}>
              {gruposFiltrados.length} grupo{gruposFiltrados.length !== 1 ? 's' : ''} encontrado{gruposFiltrados.length !== 1 ? 's' : ''}
            </Text>
            {gruposFiltrados.map((grupo, idx) => {
              const idsArr = grupo.ids_pagamentos?.split(',') || [];
              const valoresArr = grupo.valores?.split(',') || [];
              const statusesArr = grupo.statuses?.split(',') || [];
              return (
                <View key={idx} style={styles.grupoCard}>
                  <TouchableOpacity
                    activeOpacity={0.7}
                    onPress={() => abrirDetalhe(grupo)}
                  >
                    <View style={styles.grupoHeader}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.grupoAluno}>{grupo.aluno_nome || `Aluno #${grupo.aluno_id}`}</Text>
                        <Text style={styles.grupoPlano}>
                          {grupo.plano_nome || `Plano #${grupo.plano_id}`} — Matrícula #{grupo.matricula_id}
                        </Text>
                      </View>
                      <View style={styles.grupoPeriodoBadge}>
                        <Text style={styles.grupoPeriodoText}>
                          {MESES[grupo.mes] || grupo.mes}/{grupo.ano}
                        </Text>
                      </View>
                    </View>
                    <View style={styles.grupoBody}>
                      <View style={styles.grupoInfoRow}>
                        <Feather name="copy" size={14} color="#ef4444" />
                        <Text style={styles.grupoInfoLabel}>
                          {grupo.total_parcelas} parcelas duplicadas
                        </Text>
                      </View>
                      <View style={styles.grupoParcelas}>
                        {idsArr.map((id, i) => {
                          const sc = statusColor(statusesArr[i]?.trim());
                          return (
                            <View key={id} style={styles.grupoParcelaItem}>
                              <Text style={styles.grupoParcelaId}>#{id.trim()}</Text>
                              <Text style={styles.grupoParcelaValor}>{formatCurrency(valoresArr[i]?.trim())}</Text>
                              <View style={[styles.miniStatusBadge, { backgroundColor: sc.bg }]}>
                                <Text style={[styles.miniStatusText, { color: sc.text }]}>
                                  {statusesArr[i]?.trim() || '-'}
                                </Text>
                              </View>
                            </View>
                          );
                        })}
                      </View>
                    </View>
                  </TouchableOpacity>

                  {/* Footer com link para detalhe da matrícula */}
                  <View style={styles.grupoFooter}>
                    <TouchableOpacity
                      style={styles.grupoFooterBtn}
                      onPress={() => abrirDetalhe(grupo)}
                    >
                      <Feather name="eye" size={14} color="#f97316" />
                      <Text style={styles.grupoFooterText}>Ver detalhes</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={styles.grupoFooterBtnMatricula}
                      onPress={() => router.push(`/matriculas/detalhe?id=${grupo.matricula_id}`)}
                    >
                      <Feather name="external-link" size={14} color="#6366f1" />
                      <Text style={styles.grupoFooterTextMatricula}>Ir para Matrícula #{grupo.matricula_id}</Text>
                    </TouchableOpacity>
                  </View>
                </View>
              );
            })}
          </View>
        )}
      </ScrollView>

      {/* Modal de Detalhe */}
      <Modal
        visible={modalDetalhe}
        transparent
        animationType="fade"
        onRequestClose={fecharDetalhe}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContainer, !isMobile && { maxWidth: 680 }]}>
            <View style={styles.modalHeader}>
              <View style={{ flex: 1 }}>
                <Text style={styles.modalTitle}>Detalhe dos Pagamentos Duplicados</Text>
                {detalheGrupo && (
                  <Text style={styles.modalSubtitle}>
                    {detalheGrupo.aluno_nome} — {MESES[detalheGrupo.mes]}/{detalheGrupo.ano}
                  </Text>
                )}
              </View>
              <TouchableOpacity onPress={fecharDetalhe}>
                <Feather name="x" size={22} color="#6b7280" />
              </TouchableOpacity>
            </View>

            {/* Botão ir para matrícula no modal */}
            {detalheGrupo && (
              <TouchableOpacity
                style={styles.modalMatriculaLink}
                onPress={() => {
                  fecharDetalhe();
                  router.push(`/matriculas/detalhe?id=${detalheGrupo.matricula_id}`);
                }}
              >
                <Feather name="external-link" size={16} color="#6366f1" />
                <Text style={styles.modalMatriculaLinkText}>
                  Abrir Matrícula #{detalheGrupo.matricula_id} para correções
                </Text>
                <Feather name="chevron-right" size={16} color="#6366f1" />
              </TouchableOpacity>
            )}

            <ScrollView style={styles.modalBody} contentContainerStyle={{ paddingBottom: 16 }}>
              {loadingDetalhe ? (
                <View style={{ alignItems: 'center', paddingVertical: 32 }}>
                  <ActivityIndicator size="large" color="#f97316" />
                  <Text style={{ marginTop: 12, color: '#6b7280' }}>Carregando...</Text>
                </View>
              ) : pagamentosDetalhe.length === 0 ? (
                <View style={{ alignItems: 'center', paddingVertical: 32 }}>
                  <Feather name="inbox" size={40} color="#d1d5db" />
                  <Text style={{ marginTop: 8, color: '#6b7280' }}>Nenhum pagamento encontrado</Text>
                </View>
              ) : (
                pagamentosDetalhe.map((pag) => {
                  const sc = statusColor(pag.status);
                  return (
                    <View key={pag.id} style={styles.detalheCard}>
                      <View style={styles.detalheCardHeader}>
                        <Text style={styles.detalheId}>Pagamento #{pag.id}</Text>
                        <View style={[styles.miniStatusBadge, { backgroundColor: sc.bg }]}>
                          <Text style={[styles.miniStatusText, { color: sc.text }]}>
                            {pag.status}
                          </Text>
                        </View>
                      </View>
                      <View style={styles.detalheGrid}>
                        <View style={styles.detalheRow}>
                          <Text style={styles.detalheLabel}>Valor:</Text>
                          <Text style={styles.detalheValue}>{formatCurrency(pag.valor)}</Text>
                        </View>
                        <View style={styles.detalheRow}>
                          <Text style={styles.detalheLabel}>Vencimento:</Text>
                          <Text style={styles.detalheValue}>
                            {pag.data_vencimento ? new Date(pag.data_vencimento + 'T12:00:00').toLocaleDateString('pt-BR') : '-'}
                          </Text>
                        </View>
                        {pag.data_pagamento && (
                          <View style={styles.detalheRow}>
                            <Text style={styles.detalheLabel}>Pagamento:</Text>
                            <Text style={[styles.detalheValue, { color: '#16a34a' }]}>
                              {new Date(pag.data_pagamento + 'T12:00:00').toLocaleDateString('pt-BR')}
                            </Text>
                          </View>
                        )}
                        <View style={styles.detalheRow}>
                          <Text style={styles.detalheLabel}>Matrícula:</Text>
                          <Text style={styles.detalheValue}>#{pag.matricula_id}</Text>
                        </View>
                        <View style={styles.detalheRow}>
                          <Text style={styles.detalheLabel}>Plano:</Text>
                          <Text style={styles.detalheValue}>{pag.plano_nome || `#${pag.plano_id}`}</Text>
                        </View>
                        {parseFloat(pag.credito_aplicado) > 0 && (
                          <View style={styles.detalheRow}>
                            <Text style={styles.detalheLabel}>Crédito aplicado:</Text>
                            <Text style={[styles.detalheValue, { color: '#16a34a' }]}>
                              {formatCurrency(pag.credito_aplicado)}
                            </Text>
                          </View>
                        )}
                        {pag.observacoes && (
                          <View style={[styles.detalheRow, { flexDirection: 'column', alignItems: 'flex-start' }]}>
                            <Text style={styles.detalheLabel}>Obs:</Text>
                            <Text style={[styles.detalheValue, { fontStyle: 'italic', marginTop: 2 }]}>
                              {pag.observacoes}
                            </Text>
                          </View>
                        )}
                        <View style={styles.detalheRow}>
                          <Text style={styles.detalheLabel}>Criado em:</Text>
                          <Text style={[styles.detalheValue, { fontSize: 11, color: '#9ca3af' }]}>
                            {pag.created_at ? new Date(pag.created_at).toLocaleString('pt-BR') : '-'}
                          </Text>
                        </View>
                      </View>
                    </View>
                  );
                })
              )}
            </ScrollView>

            <View style={styles.modalFooter}>
              {detalheGrupo && (
                <TouchableOpacity
                  style={styles.modalGoMatriculaBtn}
                  onPress={() => {
                    fecharDetalhe();
                    router.push(`/matriculas/detalhe?id=${detalheGrupo.matricula_id}`);
                  }}
                >
                  <Feather name="external-link" size={14} color="#fff" />
                  <Text style={styles.modalGoMatriculaText}>Corrigir na Matrícula</Text>
                </TouchableOpacity>
              )}
              <TouchableOpacity style={styles.modalCloseButton} onPress={fecharDetalhe}>
                <Text style={styles.modalCloseText}>Fechar</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666',
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: '#f97316',
    borderRadius: 8,
  },
  backButtonText: {
    fontSize: 14,
    color: '#fff',
    fontWeight: '600',
  },
  refreshButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#f97316',
    backgroundColor: '#fff7ed',
  },
  refreshButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#f97316',
  },
  /* Resumo */
  resumoRow: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  resumoCard: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    borderLeftWidth: 4,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  resumoCardLabel: {
    fontSize: 12,
    color: '#6b7280',
    fontWeight: '500',
  },
  resumoCardValue: {
    fontSize: 26,
    fontWeight: '800',
    marginTop: 2,
  },
  /* Filtros */
  filtrosRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 12,
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  filtroGroup: {
    minWidth: 100,
  },
  filtroLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 4,
  },
  filtroInput: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
    fontSize: 14,
    color: '#111827',
    backgroundColor: '#f9fafb',
  },
  filtroClearBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingBottom: 10,
  },
  /* Empty */
  emptyState: {
    alignItems: 'center',
    padding: 48,
  },
  emptyTitle: {
    marginTop: 12,
    fontSize: 18,
    fontWeight: '700',
    color: '#16a34a',
  },
  emptySubtext: {
    marginTop: 4,
    fontSize: 13,
    color: '#6b7280',
    textAlign: 'center',
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6b7280',
    paddingHorizontal: 16,
    marginBottom: 4,
  },
  /* Grupo Card */
  grupoCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#fecaca',
    marginHorizontal: 16,
    overflow: 'hidden',
  },
  grupoHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    backgroundColor: '#fef2f2',
    borderBottomWidth: 1,
    borderBottomColor: '#fecaca',
  },
  grupoAluno: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  grupoPlano: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
  grupoPeriodoBadge: {
    backgroundColor: '#fee2e2',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  grupoPeriodoText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#ef4444',
  },
  grupoBody: {
    padding: 14,
  },
  grupoInfoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 10,
  },
  grupoInfoLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: '#ef4444',
  },
  grupoParcelas: {
    gap: 6,
  },
  grupoParcelaItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 8,
    backgroundColor: '#f9fafb',
  },
  grupoParcelaId: {
    fontSize: 12,
    fontWeight: '600',
    color: '#374151',
    width: 50,
  },
  grupoParcelaValor: {
    fontSize: 13,
    fontWeight: '700',
    color: '#111827',
    flex: 1,
  },
  miniStatusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  miniStatusText: {
    fontSize: 11,
    fontWeight: '700',
  },
  grupoFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderTopWidth: 1,
    borderTopColor: '#f3f4f6',
    gap: 8,
    flexWrap: 'wrap',
  },
  grupoFooterBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  grupoFooterText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#f97316',
  },
  grupoFooterBtnMatricula: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#eef2ff',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
  },
  grupoFooterTextMatricula: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6366f1',
  },
  /* Modal */
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 16,
  },
  modalContainer: {
    backgroundColor: '#fff',
    borderRadius: 16,
    width: '100%',
    maxWidth: 540,
    maxHeight: '85%',
    overflow: 'hidden',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
    gap: 12,
  },
  modalTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  modalSubtitle: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
  modalMatriculaLink: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 16,
    marginTop: 12,
    paddingVertical: 10,
    paddingHorizontal: 14,
    backgroundColor: '#eef2ff',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#c7d2fe',
  },
  modalMatriculaLinkText: {
    flex: 1,
    fontSize: 13,
    fontWeight: '600',
    color: '#6366f1',
  },
  modalBody: {
    paddingHorizontal: 16,
    paddingTop: 12,
  },
  modalFooter: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 10,
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  modalGoMatriculaBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    backgroundColor: '#6366f1',
  },
  modalGoMatriculaText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#fff',
  },
  modalCloseButton: {
    paddingVertical: 10,
    paddingHorizontal: 24,
    borderRadius: 8,
    backgroundColor: '#f3f4f6',
  },
  modalCloseText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
  },
  /* Detalhe Card */
  detalheCard: {
    backgroundColor: '#f9fafb',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    marginBottom: 10,
    overflow: 'hidden',
  },
  detalheCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 12,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  detalheId: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
  },
  detalheGrid: {
    padding: 12,
    gap: 6,
  },
  detalheRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  detalheLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
    width: 110,
  },
  detalheValue: {
    fontSize: 13,
    fontWeight: '600',
    color: '#111827',
    flex: 1,
  },
});
