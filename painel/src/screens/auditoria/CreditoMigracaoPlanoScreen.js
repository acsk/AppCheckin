import React, { useState, useEffect, useCallback } from 'react';
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
import { showError } from '../../utils/toast';

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

  const irMatricula = (id) => {
    router.push(`/matriculas/detalhe?id=${id}`);
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
            indevido ativo e datas resetadas (início após o último pagamento) com vencimento fora
            do ciclo. Diferença de 1–2 dias por aniversário do mês não entra no relatório.
          </Text>
        </View>

        <View style={[styles.resumoRow, isMobile && { flexDirection: 'column' }]}>
          <View style={[styles.resumoCard, { borderLeftColor: '#ef4444' }]}>
            <Text style={styles.resumoLabel}>Matrículas afetadas</Text>
            <Text style={[styles.resumoValue, { color: '#ef4444' }]}>
              {resumo?.total_matriculas ?? 0}
            </Text>
          </View>
          <View style={[styles.resumoCard, { borderLeftColor: '#f97316' }]}>
            <Text style={styles.resumoLabel}>Vencimento divergente</Text>
            <Text style={[styles.resumoValue, { color: '#f97316' }]}>
              {resumo?.vencimento_divergente ?? 0}
            </Text>
          </View>
          <View style={[styles.resumoCard, { borderLeftColor: '#d97706' }]}>
            <Text style={styles.resumoLabel}>Parcelas fantasma</Text>
            <Text style={[styles.resumoValue, { color: '#d97706' }]}>
              {resumo?.parcela_fantasma_migracao ?? 0}
            </Text>
          </View>
        </View>

        {registros.length === 0 ? (
          <View style={styles.emptyBox}>
            <Feather name="check-circle" size={40} color="#22c55e" />
            <Text style={styles.emptyTitle}>Nenhuma anomalia detectada</Text>
            <Text style={styles.emptyDesc}>
              Não há matrículas com o padrão de bug de migração de plano neste tenant.
            </Text>
          </View>
        ) : (
          registros.map((reg) => (
            <View key={reg.matricula_id} style={styles.card}>
              <View style={styles.cardHeader}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.alunoNome}>{reg.aluno_nome}</Text>
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
                        <Text style={styles.dataLabel}>Esperado</Text>
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
                  return (
                    <View key={idx} style={styles.problemaRow}>
                      <Feather name={cfg.icon} size={13} color={cfg.cor} />
                      <View style={{ flex: 1 }}>
                        <Text style={[styles.problemaTipo, { color: cfg.cor }]}>{cfg.label}</Text>
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
  resumoRow: { flexDirection: 'row', gap: 10, paddingHorizontal: 16, marginBottom: 16 },
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
    borderColor: '#e5e7eb',
  },
  cardHeader: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  alunoNome: { fontSize: 15, fontWeight: '700', color: '#111827' },
  matriculaMeta: { fontSize: 12, color: '#6b7280', marginTop: 2 },
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
    backgroundColor: '#fef2f2',
    padding: 10,
    borderRadius: 8,
  },
  problemaTipo: { fontSize: 11, fontWeight: '700', textTransform: 'uppercase' },
  problemaDesc: { fontSize: 12, color: '#475569', marginTop: 2, lineHeight: 17 },
});
