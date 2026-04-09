import React, { useState } from 'react';
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

const formatarData = (iso) => {
  if (!iso) return '-';
  return new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR');
};

export default function RepararProximaDataScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [loading, setLoading] = useState(false);
  const [resultado, setResultado] = useState(null);
  const [fase, setFase] = useState('idle'); // 'idle' | 'preview' | 'executado'

  const executar = async (dryRun) => {
    try {
      setLoading(true);
      const response = await auditoriaService.repararProximaDataVencimento(dryRun);
      setResultado(response);
      setFase(dryRun ? 'preview' : 'executado');
      if (!dryRun) {
        showSuccess(`${response.total_reparados} matrícula(s) reparada(s) com sucesso`);
      }
    } catch (error) {
      console.error('Erro ao reparar:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao executar reparo');
    } finally {
      setLoading(false);
    }
  };

  const resetar = () => {
    setResultado(null);
    setFase('idle');
  };

  return (
    <LayoutBase title="Auditoria" subtitle="Reparar próxima data de vencimento">
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }}>
        {/* Cabeçalho */}
        <View style={styles.headerRow}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.push('/auditoria')}>
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>
          {fase !== 'idle' && (
            <TouchableOpacity style={styles.resetButton} onPress={resetar}>
              <Feather name="refresh-cw" size={16} color="#6366f1" />
              {!isMobile && <Text style={styles.resetButtonText}>Nova verificação</Text>}
            </TouchableOpacity>
          )}
        </View>

        <View style={styles.content}>
          {/* Descrição */}
          <View style={styles.infoCard}>
            <Feather name="info" size={18} color="#6366f1" />
            <View style={{ flex: 1, gap: 4 }}>
              <Text style={styles.infoTitle}>O que este reparo faz?</Text>
              <Text style={styles.infoText}>
                Corrige matrículas ativas onde o campo{' '}
                <Text style={styles.infoCode}>proxima_data_vencimento</Text> diverge da data real
                da próxima parcela pendente. Use "Simular" para verificar os casos sem alterar dados,
                ou "Executar" para aplicar as correções.
              </Text>
            </View>
          </View>

          {/* Estado idle: botões de ação */}
          {fase === 'idle' && !loading && (
            <View style={[styles.botoesRow, isMobile && { flexDirection: 'column' }]}>
              <TouchableOpacity
                style={[styles.btnAcao, styles.btnSimular]}
                activeOpacity={0.8}
                onPress={() => executar(true)}
              >
                <Feather name="eye" size={18} color="#6366f1" />
                <View>
                  <Text style={[styles.btnAcaoLabel, { color: '#6366f1' }]}>Simular</Text>
                  <Text style={styles.btnAcaoDesc}>Mostra divergências sem alterar dados</Text>
                </View>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.btnAcao, styles.btnExecutar]}
                activeOpacity={0.8}
                onPress={() => executar(false)}
              >
                <Feather name="tool" size={18} color="#fff" />
                <View>
                  <Text style={[styles.btnAcaoLabel, { color: '#fff' }]}>Executar</Text>
                  <Text style={[styles.btnAcaoDesc, { color: '#fde68a' }]}>Aplica as correções nos dados</Text>
                </View>
              </TouchableOpacity>
            </View>
          )}

          {/* Loading */}
          {loading && (
            <View style={styles.loadingBox}>
              <ActivityIndicator size="large" color="#f97316" />
              <Text style={styles.loadingText}>Processando...</Text>
            </View>
          )}

          {/* Resultado */}
          {resultado && !loading && (
            <View style={{ gap: 16 }}>
              {/* Badge de modo */}
              <View style={[styles.modoBadge, fase === 'preview' ? styles.modoBadgeDry : styles.modoBadgeReal]}>
                <Feather
                  name={fase === 'preview' ? 'eye' : 'check-circle'}
                  size={14}
                  color={fase === 'preview' ? '#6366f1' : '#16a34a'}
                />
                <Text style={[styles.modoBadgeText, { color: fase === 'preview' ? '#6366f1' : '#16a34a' }]}>
                  {fase === 'preview' ? 'Modo simulação — nenhum dado foi alterado' : 'Reparo executado com sucesso'}
                </Text>
              </View>

              {/* Cards resumo */}
              <View style={[styles.resumoRow, isMobile && { flexDirection: 'column' }]}>
                <View style={[styles.resumoCard, { borderLeftColor: '#d97706' }]}>
                  <Feather name="alert-triangle" size={22} color="#d97706" />
                  <View style={{ flex: 1 }}>
                    <Text style={styles.resumoLabel}>Divergências encontradas</Text>
                    <Text style={[styles.resumoValue, { color: '#d97706' }]}>
                      {resultado.total_divergentes ?? 0}
                    </Text>
                  </View>
                </View>
                <View style={[styles.resumoCard, { borderLeftColor: '#16a34a' }]}>
                  <Feather name="check-circle" size={22} color="#16a34a" />
                  <View style={{ flex: 1 }}>
                    <Text style={styles.resumoLabel}>
                      {fase === 'preview' ? 'Seriam reparadas' : 'Reparadas'}
                    </Text>
                    <Text style={[styles.resumoValue, { color: '#16a34a' }]}>
                      {fase === 'preview' ? resultado.total_divergentes ?? 0 : resultado.total_reparados ?? 0}
                    </Text>
                  </View>
                </View>
              </View>

              {/* Botão executar após preview */}
              {fase === 'preview' && resultado.total_divergentes > 0 && (
                <TouchableOpacity
                  style={[styles.btnAcao, styles.btnExecutar, { alignSelf: 'stretch' }]}
                  activeOpacity={0.8}
                  onPress={() => executar(false)}
                >
                  <Feather name="tool" size={18} color="#fff" />
                  <View>
                    <Text style={[styles.btnAcaoLabel, { color: '#fff' }]}>Executar reparo</Text>
                    <Text style={[styles.btnAcaoDesc, { color: '#fde68a' }]}>
                      Corrigir as {resultado.total_divergentes} divergência(s) encontradas
                    </Text>
                  </View>
                </TouchableOpacity>
              )}

              {/* Lista de casos */}
              {resultado.casos?.length > 0 ? (
                <View style={{ gap: 8 }}>
                  <Text style={styles.sectionTitle}>
                    {resultado.casos.length} caso{resultado.casos.length !== 1 ? 's' : ''} encontrado{resultado.casos.length !== 1 ? 's' : ''}
                  </Text>
                  {resultado.casos.map((caso, i) => (
                    <View key={i} style={styles.casoCard}>
                      <View style={styles.casoHeaderRow}>
                        <View style={{ flex: 1 }}>
                          <View style={styles.casoRow}>
                            <Feather name="user" size={13} color="#64748b" />
                            <Text style={styles.casoNomeAluno}>{caso.aluno_nome}</Text>
                          </View>
                        </View>
                        <TouchableOpacity
                          style={styles.irMatriculaBtn}
                          onPress={() => router.push(`/matriculas/detalhe?id=${caso.matricula_id}`)}
                        >
                          <Feather name="external-link" size={12} color="#6366f1" />
                          <Text style={styles.irMatriculaText}>#{caso.matricula_id}</Text>
                        </TouchableOpacity>
                      </View>
                      <View style={styles.casoCampos}>
                        <View style={styles.campoRow}>
                          <Text style={styles.campoLabel}>Valor atual:</Text>
                          <Text style={[styles.campoValue, { color: '#ef4444' }]}>
                            {formatarData(caso.valor_atual)}
                          </Text>
                        </View>
                        <View style={styles.campoRow}>
                          <Text style={styles.campoLabel}>Valor correto:</Text>
                          <Text style={[styles.campoValue, { color: '#16a34a', fontWeight: '700' }]}>
                            {formatarData(caso.valor_correto)}
                          </Text>
                        </View>
                      </View>
                    </View>
                  ))}
                </View>
              ) : (
                <View style={styles.emptyState}>
                  <Feather name="check-circle" size={48} color="#16a34a" />
                  <Text style={styles.emptyTitle}>Nenhuma divergência</Text>
                  <Text style={styles.emptySubtext}>
                    Todas as matrículas estão com a próxima data de vencimento correta.
                  </Text>
                </View>
              )}
            </View>
          )}
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
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
  resetButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#6366f1',
    backgroundColor: '#eef2ff',
  },
  resetButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6366f1',
  },
  content: {
    paddingHorizontal: 16,
    gap: 16,
  },
  infoCard: {
    flexDirection: 'row',
    gap: 12,
    backgroundColor: '#eef2ff',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#c7d2fe',
  },
  infoTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#3730a3',
  },
  infoText: {
    fontSize: 12,
    color: '#4338ca',
    lineHeight: 18,
  },
  infoCode: {
    fontFamily: 'monospace',
    backgroundColor: '#c7d2fe',
    paddingHorizontal: 3,
    borderRadius: 3,
  },
  botoesRow: {
    flexDirection: 'row',
    gap: 12,
  },
  btnAcao: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 16,
    borderRadius: 12,
    borderWidth: 1,
  },
  btnSimular: {
    backgroundColor: '#eef2ff',
    borderColor: '#a5b4fc',
  },
  btnExecutar: {
    backgroundColor: '#f97316',
    borderColor: '#ea580c',
  },
  btnAcaoLabel: {
    fontSize: 15,
    fontWeight: '700',
  },
  btnAcaoDesc: {
    fontSize: 11,
    color: '#6b7280',
    marginTop: 2,
  },
  loadingBox: {
    alignItems: 'center',
    paddingVertical: 40,
    gap: 12,
  },
  loadingText: {
    fontSize: 15,
    color: '#666',
  },
  modoBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  modoBadgeDry: {
    backgroundColor: '#eef2ff',
    borderColor: '#a5b4fc',
  },
  modoBadgeReal: {
    backgroundColor: '#dcfce7',
    borderColor: '#86efac',
  },
  modoBadgeText: {
    fontSize: 13,
    fontWeight: '600',
  },
  resumoRow: {
    flexDirection: 'row',
    gap: 10,
  },
  resumoCard: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    borderLeftWidth: 4,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  resumoLabel: {
    fontSize: 11,
    color: '#6b7280',
    fontWeight: '500',
  },
  resumoValue: {
    fontSize: 22,
    fontWeight: '800',
    marginTop: 2,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#374151',
  },
  casoCard: {
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    gap: 4,
  },
  casoHeaderRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  casoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  casoNomeAluno: {
    fontSize: 13,
    fontWeight: '700',
    color: '#111827',
  },
  casoCampos: {
    gap: 4,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  campoRow: {
    flexDirection: 'row',
    gap: 6,
  },
  campoLabel: {
    fontSize: 11,
    color: '#9ca3af',
    width: 100,
  },
  campoValue: {
    fontSize: 11,
    fontWeight: '600',
    color: '#374151',
    flex: 1,
  },
  irMatriculaBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#eef2ff',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  irMatriculaText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6366f1',
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 50,
    gap: 10,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#111827',
  },
  emptySubtext: {
    fontSize: 13,
    color: '#6b7280',
    textAlign: 'center',
    maxWidth: 300,
  },
});
