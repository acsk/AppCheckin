import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  useWindowDimensions,
  StyleSheet,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { auditoriaService } from '../../services/auditoriaService';
import { showError, showSuccess } from '../../utils/toast';

const TIPO_CONFIG = {
  parcela_fantasma_migracao: { label: 'Parcela fantasma', cor: '#ef4444', icon: 'file-minus' },
  pagamento_cancelado_credito: { label: 'MP cancelado', cor: '#dc2626', icon: 'credit-card' },
  credito_indevido_ativo: { label: 'Crédito ativo', cor: '#b91c1c', icon: 'dollar-sign' },
  vencimento_divergente: { label: 'Vencimento errado', cor: '#f97316', icon: 'calendar' },
  assinatura_migracao: { label: 'Assinatura migração', cor: '#d97706', icon: 'link-2' },
  acesso_alem_periodo_pago: { label: 'Acesso além do pago', cor: '#ea580c', icon: 'calendar' },
};

const formatarData = (iso) => {
  if (!iso) return '-';
  return new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR');
};

export default function CreditoMigracaoPlanoScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [loading, setLoading] = useState(true);
  const [resumo, setResumo] = useState(null);
  const [registros, setRegistros] = useState([]);
  const [somenteRevisao, setSomenteRevisao] = useState(true);
  const [reparandoId, setReparandoId] = useState(null);

  const carregar = useCallback(async () => {
    try {
      setLoading(true);
      const response = await auditoriaService.creditoMigracaoPlano();
      setResumo(response.resumo || {});
      setRegistros(response.registros || []);
    } catch (error) {
      console.error('Erro ao auditar crédito/migração:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao carregar auditoria');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    carregar();
  }, [carregar]);

  const registrosVisiveis = useMemo(() => {
    if (!somenteRevisao) return registros;
    return registros.filter((reg) => reg.revisao_manual);
  }, [registros, somenteRevisao]);

  const irMatricula = (id) => {
    router.push(`/matriculas/detalhe?id=${id}`);
  };

  const temVencimentoDivergente = (reg) =>
    (reg.problemas || []).some((p) => p.tipo === 'vencimento_divergente');

  const corrigirVencimento = async (matriculaId) => {
    try {
      setReparandoId(matriculaId);
      const result = await auditoriaService.repararVencimentoMatricula(matriculaId);
      if (result.alterado) {
        showSuccess(result.message || 'Vencimento corrigido');
      } else {
        showSuccess(result.message || 'Vencimento já estava correto');
      }
      await carregar();
    } catch (error) {
      showError(error.mensagemLimpa || error.message || error.error || 'Erro ao corrigir vencimento');
    } finally {
      setReparandoId(null);
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Auditoria" subtitle="Crédito / migração de plano">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Verificando matrículas...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Auditoria" subtitle="Crédito / migração de plano">
      <ScrollView style={styles.container} contentContainerStyle={{ paddingBottom: 40 }}>
        <View style={styles.headerRow}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.push('/auditoria')}>
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.refreshButton} onPress={carregar}>
            <Feather name="refresh-cw" size={16} color="#f97316" />
            {!isMobile && <Text style={styles.refreshButtonText}>Atualizar</Text>}
          </TouchableOpacity>
        </View>

        <View style={styles.infoBox}>
          <Feather name="info" size={16} color="#6366f1" />
          <Text style={styles.infoText}>
            Foco no padrão do bug: parcela fantasma R$ 0, pagamento MP cancelado → crédito, crédito
            indevido ativo e datas resetadas na migração. Planos bimestrais e quadrimestrais entram
            no cálculo do período pago (vencimento + meses do ciclo). Itens marcados como{' '}
            <Text style={styles.infoStrong}>Revisão manual</Text> exigem conferência; assinatura de
            migração sozinha é apenas informativa.
          </Text>
        </View>

        <View style={[styles.resumoRow, isMobile && { flexDirection: 'column' }]}>
          <View style={[styles.resumoCard, { borderLeftColor: '#ef4444' }]}>
            <Text style={styles.resumoLabel}>Revisão manual</Text>
            <Text style={[styles.resumoValue, { color: '#ef4444' }]}>
              {resumo?.revisao_manual ?? 0}
            </Text>
          </View>
          <View style={[styles.resumoCard, { borderLeftColor: '#f97316' }]}>
            <Text style={styles.resumoLabel}>Vencimento divergente</Text>
            <Text style={[styles.resumoValue, { color: '#f97316' }]}>
              {resumo?.vencimento_divergente ?? 0}
            </Text>
          </View>
          <View style={[styles.resumoCard, { borderLeftColor: '#94a3b8' }]}>
            <Text style={styles.resumoLabel}>Informativo</Text>
            <Text style={[styles.resumoValue, { color: '#64748b' }]}>
              {resumo?.informativo ?? 0}
            </Text>
          </View>
        </View>

        {registros.length > 0 && (
          <View style={styles.filtroRow}>
            <TouchableOpacity
              style={[styles.filtroBtn, somenteRevisao && styles.filtroBtnAtivo]}
              onPress={() => setSomenteRevisao(true)}
            >
              <Feather name="alert-triangle" size={14} color={somenteRevisao ? '#fff' : '#ef4444'} />
              <Text style={[styles.filtroBtnText, somenteRevisao && styles.filtroBtnTextAtivo]}>
                Só revisão manual ({resumo?.revisao_manual ?? 0})
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.filtroBtn, !somenteRevisao && styles.filtroBtnAtivoSec]}
              onPress={() => setSomenteRevisao(false)}
            >
              <Feather name="list" size={14} color={!somenteRevisao ? '#fff' : '#64748b'} />
              <Text style={[styles.filtroBtnText, !somenteRevisao && styles.filtroBtnTextAtivo]}>
                Todos ({resumo?.total_matriculas ?? 0})
              </Text>
            </TouchableOpacity>
          </View>
        )}

        {registrosVisiveis.length === 0 ? (
          <View style={styles.emptyBox}>
            <Feather
              name={registros.length === 0 ? 'check-circle' : 'filter'}
              size={40}
              color="#22c55e"
            />
            <Text style={styles.emptyTitle}>
              {registros.length === 0
                ? 'Nenhuma anomalia detectada'
                : 'Nenhum item para revisão manual'}
            </Text>
            <Text style={styles.emptyDesc}>
              {registros.length === 0
                ? 'Não há matrículas com o padrão de bug de migração de plano neste tenant.'
                : 'Todos os registros restantes são informativos (ex.: assinatura recriada na migração).'}
            </Text>
          </View>
        ) : (
          registrosVisiveis.map((reg) => (
            <View
              key={reg.matricula_id}
              style={[
                styles.card,
                reg.revisao_manual ? styles.cardRevisao : styles.cardInformativo,
              ]}
            >
              <View style={styles.cardHeader}>
                <View style={{ flex: 1 }}>
                  <View style={styles.tituloRow}>
                    <Text style={styles.alunoNome}>{reg.aluno_nome}</Text>
                    {reg.revisao_manual ? (
                      <View style={styles.badgeRevisao}>
                        <Feather name="alert-triangle" size={11} color="#fff" />
                        <Text style={styles.badgeRevisaoText}>Revisão manual</Text>
                      </View>
                    ) : (
                      <View style={styles.badgeInfo}>
                        <Text style={styles.badgeInfoText}>Informativo</Text>
                      </View>
                    )}
                  </View>
                  <Text style={styles.matriculaMeta}>
                    Matrícula #{reg.matricula_id}
                    {reg.status ? ` · ${reg.status}` : ''}
                  </Text>
                </View>
                <TouchableOpacity
                  style={styles.linkBtn}
                  onPress={() => irMatricula(reg.matricula_id)}
                >
                  <Feather name="external-link" size={14} color="#6366f1" />
                  <Text style={styles.linkBtnText}>Abrir</Text>
                </TouchableOpacity>
                {temVencimentoDivergente(reg) && (
                  <TouchableOpacity
                    style={[styles.linkBtn, styles.fixBtn]}
                    onPress={() => corrigirVencimento(reg.matricula_id)}
                    disabled={reparandoId === reg.matricula_id}
                  >
                    {reparandoId === reg.matricula_id ? (
                      <ActivityIndicator size="small" color="#16a34a" />
                    ) : (
                      <Feather name="tool" size={14} color="#16a34a" />
                    )}
                    <Text style={styles.fixBtnText}>Corrigir</Text>
                  </TouchableOpacity>
                )}
              </View>

              {(reg.data_vencimento || reg.data_vencimento_esperada) && (
                <View style={styles.datasRow}>
                  <View style={styles.dataItem}>
                    <Text style={styles.dataLabel}>Vencimento atual</Text>
                    <Text style={styles.dataValue}>{formatarData(reg.data_vencimento)}</Text>
                  </View>
                  {reg.data_vencimento_esperada && (
                    <>
                      <Feather name="arrow-right" size={14} color="#94a3b8" style={{ marginTop: 14 }} />
                      <View style={styles.dataItem}>
                        <Text style={styles.dataLabel}>Período pago até</Text>
                        <Text style={[styles.dataValue, { color: '#16a34a' }]}>
                          {formatarData(reg.data_vencimento_esperada)}
                        </Text>
                      </View>
                    </>
                  )}
                </View>
              )}

              <View style={styles.problemasList}>
                {(reg.problemas || []).map((p, idx) => {
                  const cfg = TIPO_CONFIG[p.tipo] || { label: p.tipo, cor: '#6b7280', icon: 'alert-circle' };
                  const revisao = Boolean(p.revisao_manual);
                  return (
                    <View
                      key={idx}
                      style={[styles.problemaRow, revisao ? styles.problemaRowRevisao : styles.problemaRowInfo]}
                    >
                      <Feather name={cfg.icon} size={13} color={cfg.cor} />
                      <View style={{ flex: 1 }}>
                        <View style={styles.problemaHeader}>
                          <Text style={[styles.problemaTipo, { color: cfg.cor }]}>{cfg.label}</Text>
                          {revisao && (
                            <Text style={styles.problemaRevisaoTag}>Revisar</Text>
                          )}
                        </View>
                        <Text style={styles.problemaDesc}>{p.descricao}</Text>
                      </View>
                    </View>
                  );
                })}
              </View>
            </View>
          ))
        )}
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  loadingContainer: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 40 },
  loadingText: { marginTop: 12, color: '#64748b', fontSize: 14 },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    paddingBottom: 8,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#f97316',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
  },
  backButtonText: { color: '#fff', fontWeight: '600', fontSize: 13 },
  refreshButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#fff',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#fed7aa',
  },
  refreshButtonText: { color: '#f97316', fontWeight: '600', fontSize: 13 },
  infoBox: {
    flexDirection: 'row',
    gap: 10,
    marginHorizontal: 16,
    marginBottom: 12,
    padding: 12,
    backgroundColor: '#eef2ff',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#c7d2fe',
  },
  infoText: { flex: 1, fontSize: 12, color: '#4338ca', lineHeight: 18 },
  infoStrong: { fontWeight: '700' },
  resumoRow: { flexDirection: 'row', gap: 10, paddingHorizontal: 16, marginBottom: 12 },
  resumoCard: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 14,
    borderLeftWidth: 4,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  resumoLabel: { fontSize: 11, color: '#6b7280', fontWeight: '600', textTransform: 'uppercase' },
  resumoValue: { fontSize: 26, fontWeight: '800', marginTop: 4 },
  filtroRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  filtroBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  filtroBtnAtivo: { backgroundColor: '#ef4444', borderColor: '#ef4444' },
  filtroBtnAtivoSec: { backgroundColor: '#64748b', borderColor: '#64748b' },
  filtroBtnText: { fontSize: 12, fontWeight: '600', color: '#475569' },
  filtroBtnTextAtivo: { color: '#fff' },
  emptyBox: {
    margin: 16,
    padding: 32,
    backgroundColor: '#fff',
    borderRadius: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  emptyTitle: { fontSize: 16, fontWeight: '700', color: '#111827', marginTop: 12 },
  emptyDesc: { fontSize: 13, color: '#6b7280', textAlign: 'center', marginTop: 6 },
  card: {
    marginHorizontal: 16,
    marginBottom: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
  },
  cardRevisao: { borderColor: '#fecaca', backgroundColor: '#fffbfb' },
  cardInformativo: { borderColor: '#e5e7eb' },
  cardHeader: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  tituloRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 },
  alunoNome: { fontSize: 15, fontWeight: '700', color: '#111827' },
  badgeRevisao: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#ef4444',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },
  badgeRevisaoText: { fontSize: 10, fontWeight: '700', color: '#fff', textTransform: 'uppercase' },
  badgeInfo: {
    backgroundColor: '#f1f5f9',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },
  badgeInfoText: { fontSize: 10, fontWeight: '600', color: '#64748b', textTransform: 'uppercase' },
  matriculaMeta: { fontSize: 12, color: '#6b7280', marginTop: 4 },
  linkBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#eef2ff',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
  },
  linkBtnText: { fontSize: 12, fontWeight: '600', color: '#6366f1' },
  fixBtn: { backgroundColor: '#ecfdf5', borderWidth: 1, borderColor: '#bbf7d0' },
  fixBtnText: { fontSize: 12, fontWeight: '600', color: '#16a34a' },
  datasRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
  },
  dataItem: { flex: 1 },
  dataLabel: { fontSize: 10, color: '#94a3b8', fontWeight: '600', textTransform: 'uppercase' },
  dataValue: { fontSize: 14, fontWeight: '700', color: '#334155', marginTop: 2 },
  problemasList: { marginTop: 12, gap: 8 },
  problemaRow: {
    flexDirection: 'row',
    gap: 8,
    alignItems: 'flex-start',
    padding: 10,
    borderRadius: 8,
  },
  problemaRowRevisao: { backgroundColor: '#fef2f2' },
  problemaRowInfo: { backgroundColor: '#f8fafc' },
  problemaHeader: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  problemaTipo: { fontSize: 11, fontWeight: '700', textTransform: 'uppercase' },
  problemaRevisaoTag: {
    fontSize: 9,
    fontWeight: '700',
    color: '#ef4444',
    textTransform: 'uppercase',
    backgroundColor: '#fee2e2',
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderRadius: 4,
  },
  problemaDesc: { fontSize: 12, color: '#475569', marginTop: 2, lineHeight: 17 },
});
