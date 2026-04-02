import React, { useState, useEffect } from 'react';
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

const SEVERIDADE_CONFIG = {
  alta: { bg: '#fee2e2', text: '#ef4444', border: '#fca5a5', icon: 'alert-circle' },
  media: { bg: '#fef3c7', text: '#d97706', border: '#fcd34d', icon: 'alert-triangle' },
};

const TIPO_CONFIG = {
  proxima_data_vencimento_null: {
    label: 'Próx. Vencimento NULL',
    icon: 'calendar-x',
    descCurta: 'Matrículas ativas sem próxima data de vencimento',
  },
  proxima_data_vencimento_desatualizada: {
    label: 'Vencimento Desatualizado',
    icon: 'refresh-cw',
    descCurta: 'Próxima data não bate com a proxima parcela pendente',
  },
  ativa_vencimento_expirado: {
    label: 'Ativa com Vencimento Expirado',
    icon: 'clock',
    descCurta: 'Matrícula ativa com vencimento expirado há +5 dias',
  },
  cancelada_com_parcelas_futuras: {
    label: 'Cancelada c/ Parcelas Futuras',
    icon: 'slash',
    descCurta: 'Cancelada/vencida com parcelas futuras pendentes',
  },
  matriculas_duplicadas: {
    label: 'Matrículas Duplicadas',
    icon: 'copy',
    descCurta: 'Mesmo aluno com +1 matrícula ativa na mesma modalidade',
  },
  ativa_sem_parcelas: {
    label: 'Ativa sem Parcelas',
    icon: 'file-minus',
    descCurta: 'Matrícula ativa sem nenhuma parcela associada',
  },
};

const formatarData = (iso) => {
  if (!iso) return '-';
  return new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR');
};

// ─── Renderizador de registros por tipo ────────────────────────────────────────

function RegistrosLista({ tipo, registros, onGoMatricula }) {
  if (!registros?.length) return null;

  if (tipo === 'matriculas_duplicadas') {
    return (
      <View style={styles.registrosContainer}>
        {registros.map((r, i) => (
          <View key={i} style={styles.registroCard}>
            <View style={styles.registroRow}>
              <Feather name="user" size={13} color="#64748b" />
              <Text style={styles.registroNomeAluno}>{r.aluno_nome}</Text>
            </View>
            <View style={[styles.registroRow, { marginTop: 4 }]}>
              <Text style={styles.registroLabel}>Modalidade:</Text>
              <Text style={styles.registroValue}>{r.modalidade_nome}</Text>
            </View>
            <View style={styles.registroRow}>
              <Text style={styles.registroLabel}>Matrículas IDs:</Text>
              <Text style={[styles.registroValue, { color: '#f97316', fontWeight: '700' }]}>
                #{r.matricula_ids?.replace(/,/g, ', #')}
              </Text>
            </View>
            <View style={styles.registroRow}>
              <Text style={styles.registroLabel}>Planos:</Text>
              <Text style={styles.registroValue}>{r.planos}</Text>
            </View>
            <View style={styles.registroRow}>
              <Text style={styles.registroLabel}>Total duplicadas:</Text>
              <Text style={[styles.registroValue, { color: '#ef4444', fontWeight: '700' }]}>{r.total}</Text>
            </View>
          </View>
        ))}
      </View>
    );
  }

  return (
    <View style={styles.registrosContainer}>
      {registros.map((r, i) => (
        <View key={i} style={styles.registroCard}>
          <View style={styles.registroHeaderRow}>
            <View style={{ flex: 1 }}>
              <View style={styles.registroRow}>
                <Feather name="user" size={13} color="#64748b" />
                <Text style={styles.registroNomeAluno}>{r.aluno_nome}</Text>
              </View>
              <Text style={styles.registroPlano}>{r.plano_nome}</Text>
            </View>
            <TouchableOpacity
              style={styles.irMatriculaBtn}
              onPress={() => onGoMatricula(r.matricula_id)}
            >
              <Feather name="external-link" size={12} color="#6366f1" />
              <Text style={styles.irMatriculaText}>#{r.matricula_id}</Text>
            </TouchableOpacity>
          </View>

          {/* Campos variáveis por tipo */}
          {tipo === 'proxima_data_vencimento_null' || tipo === 'ativa_sem_parcelas' ? (
            <View style={styles.registroCampos}>
              <CampoItem label="Vencimento" value={formatarData(r.data_vencimento)} />
              <CampoItem
                label="Próx. Vencimento"
                value={r.proxima_data_vencimento ? formatarData(r.proxima_data_vencimento) : '—'}
                valueStyle={{ color: '#ef4444' }}
              />
              <CampoItem label="Status" value={r.status} />
            </View>
          ) : tipo === 'proxima_data_vencimento_desatualizada' ? (
            <View style={styles.registroCampos}>
              <CampoItem label="Vencimento da Matrícula" value={formatarData(r.vencimento_matricula)} />
              <CampoItem label="Próx. Parcela Pendente" value={formatarData(r.proxima_parcela_pendente)} valueStyle={{ color: '#d97706' }} />
              <CampoItem label="Status" value={r.status} />
            </View>
          ) : tipo === 'ativa_vencimento_expirado' ? (
            <View style={styles.registroCampos}>
              <CampoItem label="Vencimento Efetivo" value={formatarData(r.vencimento_efetivo)} />
              <CampoItem
                label="Dias vencido"
                value={`${r.dias_vencido} dias`}
                valueStyle={{ color: '#ef4444', fontWeight: '700' }}
              />
              <CampoItem label="Status" value={r.status} />
            </View>
          ) : tipo === 'cancelada_com_parcelas_futuras' ? (
            <View style={styles.registroCampos}>
              <CampoItem label="Status" value={r.status} />
              <CampoItem label="Parcelas futuras pendentes" value={String(r.parcelas_futuras_pendentes)} valueStyle={{ color: '#ef4444', fontWeight: '700' }} />
              <CampoItem label="Próxima parcela" value={formatarData(r.proxima_parcela)} />
              <CampoItem label="Vencimento" value={formatarData(r.data_vencimento)} />
            </View>
          ) : null}
        </View>
      ))}
    </View>
  );
}

function CampoItem({ label, value, valueStyle }) {
  return (
    <View style={styles.campoRow}>
      <Text style={styles.campoLabel}>{label}:</Text>
      <Text style={[styles.campoValue, valueStyle]}>{value ?? '-'}</Text>
    </View>
  );
}

// ─── Tela principal ────────────────────────────────────────────────────────────

export default function AnomaliasDatasScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [loading, setLoading] = useState(true);
  const [resumo, setResumo] = useState(null);
  const [anomalias, setAnomalias] = useState([]);
  const [expandidos, setExpandidos] = useState({});

  useEffect(() => {
    carregar();
  }, []);

  const carregar = async () => {
    try {
      setLoading(true);
      const response = await auditoriaService.anomaliasDatas();
      setResumo(response.resumo || { total_anomalias: 0, tipos_encontrados: 0 });
      const lista = response.anomalias || [];
      setAnomalias(lista);
      // Expande automaticamente os de severidade alta
      const inicialExpand = {};
      lista.forEach((a) => {
        if (a.severidade === 'alta') inicialExpand[a.tipo] = true;
      });
      setExpandidos(inicialExpand);
    } catch (error) {
      console.error('Erro ao carregar anomalias:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao carregar dados de auditoria');
    } finally {
      setLoading(false);
    }
  };

  const toggleExpandir = (tipo) => {
    setExpandidos((prev) => ({ ...prev, [tipo]: !prev[tipo] }));
  };

  const irParaMatricula = (id) => router.push(`/matriculas/detalhe?id=${id}`);

  if (loading) {
    return (
      <LayoutBase title="Auditoria" subtitle="Anomalias de datas">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Verificando anomalias...</Text>
        </View>
      </LayoutBase>
    );
  }

  const totalAlta = anomalias.filter((a) => a.severidade === 'alta').reduce((s, a) => s + a.total, 0);
  const totalMedia = anomalias.filter((a) => a.severidade === 'media').reduce((s, a) => s + a.total, 0);

  return (
    <LayoutBase title="Auditoria" subtitle="Anomalias de datas">
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }}>
        {/* Cabeçalho */}
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

        {/* Cards de resumo */}
        <View style={[styles.resumoRow, isMobile && { flexDirection: 'column' }]}>
          <View style={[styles.resumoCard, { borderLeftColor: '#64748b' }]}>
            <Feather name="activity" size={24} color="#64748b" />
            <View style={{ flex: 1 }}>
              <Text style={styles.resumoLabel}>Total de Anomalias</Text>
              <Text style={[styles.resumoValue, { color: '#334155' }]}>
                {resumo?.total_anomalias ?? 0}
              </Text>
            </View>
          </View>
          <View style={[styles.resumoCard, { borderLeftColor: '#ef4444' }]}>
            <Feather name="alert-circle" size={24} color="#ef4444" />
            <View style={{ flex: 1 }}>
              <Text style={styles.resumoLabel}>Severidade Alta</Text>
              <Text style={[styles.resumoValue, { color: '#ef4444' }]}>{totalAlta}</Text>
            </View>
          </View>
          <View style={[styles.resumoCard, { borderLeftColor: '#d97706' }]}>
            <Feather name="alert-triangle" size={24} color="#d97706" />
            <View style={{ flex: 1 }}>
              <Text style={styles.resumoLabel}>Severidade Média</Text>
              <Text style={[styles.resumoValue, { color: '#d97706' }]}>{totalMedia}</Text>
            </View>
          </View>
        </View>

        {/* Conteúdo */}
        <View style={styles.content}>
          {anomalias.length === 0 ? (
            <View style={styles.emptyState}>
              <Feather name="check-circle" size={52} color="#16a34a" />
              <Text style={styles.emptyTitle}>Tudo certo!</Text>
              <Text style={styles.emptySubtext}>
                Nenhuma anomalia de datas foi encontrada nas matrículas.
              </Text>
            </View>
          ) : (
            <View style={{ gap: 12 }}>
              <Text style={styles.sectionTitle}>
                {anomalias.length} tipo{anomalias.length !== 1 ? 's' : ''} de anomalia encontrado{anomalias.length !== 1 ? 's' : ''}
              </Text>

              {anomalias.map((anomalia) => {
                const sev = SEVERIDADE_CONFIG[anomalia.severidade] || SEVERIDADE_CONFIG.media;
                const tipoConf = TIPO_CONFIG[anomalia.tipo] || {};
                const isExpand = !!expandidos[anomalia.tipo];

                return (
                  <View key={anomalia.tipo} style={[styles.anomaliaCard, { borderLeftColor: sev.border }]}>
                    {/* Header expandível */}
                    <TouchableOpacity
                      activeOpacity={0.75}
                      style={styles.anomaliaHeader}
                      onPress={() => toggleExpandir(anomalia.tipo)}
                    >
                      <View style={[styles.anomaliaIconBox, { backgroundColor: sev.bg }]}>
                        <Feather name={tipoConf.icon || 'alert-circle'} size={18} color={sev.text} />
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.anomaliaTipoLabel}>
                          {tipoConf.label || anomalia.tipo}
                        </Text>
                        <Text style={styles.anomaliaDescCurta} numberOfLines={2}>
                          {anomalia.descricao || tipoConf.descCurta}
                        </Text>
                      </View>
                      <View style={{ alignItems: 'flex-end', gap: 6 }}>
                        <View style={[styles.sevBadge, { backgroundColor: sev.bg }]}>
                          <Feather name={sev.icon} size={11} color={sev.text} />
                          <Text style={[styles.sevBadgeText, { color: sev.text }]}>
                            {anomalia.severidade}
                          </Text>
                        </View>
                        <View style={styles.totalBadge}>
                          <Text style={styles.totalBadgeText}>{anomalia.total} registro{anomalia.total !== 1 ? 's' : ''}</Text>
                        </View>
                      </View>
                      <Feather
                        name={isExpand ? 'chevron-up' : 'chevron-down'}
                        size={18}
                        color="#9ca3af"
                        style={{ marginLeft: 8 }}
                      />
                    </TouchableOpacity>

                    {/* Registros */}
                    {isExpand && (
                      <View style={styles.anomaliaBody}>
                        <RegistrosLista
                          tipo={anomalia.tipo}
                          registros={anomalia.registros}
                          onGoMatricula={irParaMatricula}
                        />
                      </View>
                    )}
                  </View>
                );
              })}
            </View>
          )}
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
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
  resumoRow: {
    flexDirection: 'row',
    gap: 10,
    paddingHorizontal: 16,
    marginBottom: 16,
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
  content: {
    paddingHorizontal: 16,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#374151',
    marginBottom: 4,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 60,
    gap: 12,
  },
  emptyTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#111827',
  },
  emptySubtext: {
    fontSize: 14,
    color: '#6b7280',
    textAlign: 'center',
    maxWidth: 300,
  },
  // Anomalia card
  anomaliaCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderLeftWidth: 4,
    overflow: 'hidden',
  },
  anomaliaHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
  },
  anomaliaIconBox: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  anomaliaTipoLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: '#111827',
  },
  anomaliaDescCurta: {
    fontSize: 11,
    color: '#6b7280',
    marginTop: 2,
  },
  sevBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 20,
  },
  sevBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  totalBadge: {
    backgroundColor: '#f3f4f6',
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 20,
  },
  totalBadgeText: {
    fontSize: 10,
    fontWeight: '600',
    color: '#374151',
  },
  anomaliaBody: {
    borderTopWidth: 1,
    borderTopColor: '#f3f4f6',
  },
  // Registros
  registrosContainer: {
    padding: 10,
    gap: 8,
  },
  registroCard: {
    backgroundColor: '#f9fafb',
    borderRadius: 10,
    padding: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    gap: 4,
  },
  registroHeaderRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  registroRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  registroNomeAluno: {
    fontSize: 13,
    fontWeight: '700',
    color: '#111827',
  },
  registroPlano: {
    fontSize: 11,
    color: '#6b7280',
    marginTop: 2,
  },
  registroLabel: {
    fontSize: 11,
    color: '#6b7280',
    fontWeight: '500',
  },
  registroValue: {
    fontSize: 11,
    color: '#374151',
    fontWeight: '600',
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
  registroCampos: {
    gap: 3,
    marginTop: 4,
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
    width: 160,
  },
  campoValue: {
    fontSize: 11,
    fontWeight: '600',
    color: '#374151',
    flex: 1,
  },
});
