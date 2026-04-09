import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  useWindowDimensions,
  TextInput,
  StyleSheet,
} from 'react-native';
import { Picker } from '@react-native-picker/picker';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { auditoriaService } from '../../services/auditoriaService';
import { showError } from '../../utils/toast';

const MESES = [
  '', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
  'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez',
];

const formatarData = (iso) => {
  if (!iso) return '-';
  return new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR');
};

function CardResumo({ icon, iconColor, label, value, valueColor }) {
  return (
    <View style={[styles.resumoCard, { borderLeftColor: iconColor }]}>
      <Feather name={icon} size={22} color={iconColor} />
      <View style={{ flex: 1 }}>
        <Text style={styles.resumoLabel}>{label}</Text>
        <Text style={[styles.resumoValue, { color: valueColor || '#334155' }]}>{value ?? 0}</Text>
      </View>
    </View>
  );
}

function ViolacaoMensalCard({ v, onGoAluno }) {
  return (
    <View style={styles.violacaoCard}>
      <View style={styles.violacaoHeaderRow}>
        <View style={{ flex: 1 }}>
          <TouchableOpacity style={styles.nomeRow} onPress={() => onGoAluno(v.aluno_id)}>
            <Feather name="user" size={13} color="#64748b" />
            <Text style={styles.nomeAluno}>{v.aluno_nome}</Text>
            <Feather name="external-link" size={11} color="#9ca3af" />
          </TouchableOpacity>
          <Text style={styles.subInfo}>{v.modalidade} · {v.plano}</Text>
        </View>
        <View style={styles.excessoBadge}>
          <Text style={styles.excessoText}>+{v.excesso}</Text>
          <Text style={styles.excessoLabel}>excesso</Text>
        </View>
      </View>
      <View style={styles.camposGrid}>
        <View style={styles.campoItem}>
          <Text style={styles.campoLabel}>Limite mensal</Text>
          <Text style={styles.campoValue}>{v.limite_mensal}</Text>
        </View>
        <View style={styles.campoItem}>
          <Text style={styles.campoLabel}>Check-ins realizados</Text>
          <Text style={[styles.campoValue, { color: '#ef4444', fontWeight: '700' }]}>{v.total_checkins}</Text>
        </View>
      </View>
    </View>
  );
}

function ViolacaoSemanalCard({ v, onGoAluno }) {
  return (
    <View style={styles.violacaoCard}>
      <View style={styles.violacaoHeaderRow}>
        <View style={{ flex: 1 }}>
          <TouchableOpacity style={styles.nomeRow} onPress={() => onGoAluno(v.aluno_id)}>
            <Feather name="user" size={13} color="#64748b" />
            <Text style={styles.nomeAluno}>{v.aluno_nome}</Text>
            <Feather name="external-link" size={11} color="#9ca3af" />
          </TouchableOpacity>
          <Text style={styles.subInfo}>{v.modalidade} · {v.plano}</Text>
        </View>
        <View style={styles.excessoBadge}>
          <Text style={styles.excessoText}>+{v.excesso}</Text>
          <Text style={styles.excessoLabel}>excesso</Text>
        </View>
      </View>
      <View style={styles.camposGrid}>
        <View style={styles.campoItem}>
          <Text style={styles.campoLabel}>Semana</Text>
          <Text style={styles.campoValue}>
            {formatarData(v.semana_inicio)} – {formatarData(v.semana_fim)}
          </Text>
        </View>
        <View style={styles.campoItem}>
          <Text style={styles.campoLabel}>Limite semanal</Text>
          <Text style={styles.campoValue}>{v.limite_semanal}</Text>
        </View>
        <View style={styles.campoItem}>
          <Text style={styles.campoLabel}>Check-ins realizados</Text>
          <Text style={[styles.campoValue, { color: '#ef4444', fontWeight: '700' }]}>{v.total_checkins}</Text>
        </View>
      </View>
    </View>
  );
}

export default function CheckinsAcimaDoLimiteScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const hoje = new Date();
  const [filtroAno, setFiltroAno] = useState(String(hoje.getFullYear()));
  const [filtroMes, setFiltroMes] = useState(String(hoje.getMonth() + 1));

  const [loading, setLoading] = useState(true);
  const [periodo, setPeriodo] = useState(null);
  const [resumo, setResumo] = useState(null);
  const [violacoesMensais, setViolacoesMensais] = useState([]);
  const [violacoesSemanais, setViolacoesSemanais] = useState([]);

  useEffect(() => {
    carregar();
  }, []);

  const carregar = async () => {
    try {
      setLoading(true);
      const filtros = {};
      if (filtroAno) filtros.ano = filtroAno;
      if (filtroMes) filtros.mes = filtroMes;
      const response = await auditoriaService.checkinsAcimaDoLimite(filtros);
      setPeriodo(response.periodo || null);
      setResumo(response.resumo || { total_violacoes_mensais: 0, total_violacoes_semanais: 0 });
      setViolacoesMensais(response.violacoes_mensais || []);
      setViolacoesSemanais(response.violacoes_semanais || []);
    } catch (error) {
      console.error('Erro ao carregar checkins acima do limite:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao carregar dados');
    } finally {
      setLoading(false);
    }
  };

  const aplicarFiltro = () => carregar();

  const irParaAluno = (id) => router.push(`/alunos/${id}`);

  const totalViolacoes = (resumo?.total_violacoes_mensais ?? 0) + (resumo?.total_violacoes_semanais ?? 0);

  return (
    <LayoutBase title="Auditoria" subtitle="Check-ins acima do limite">
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

        {/* Filtros */}
        <View style={[styles.filtrosRow, isMobile && { flexDirection: 'column' }]}>
          <View style={styles.filtroGroup}>
            <Text style={styles.filtroLabel}>Ano</Text>
            <TextInput
              style={styles.filtroInput}
              value={filtroAno}
              onChangeText={setFiltroAno}
              keyboardType="numeric"
              placeholder="Ex: 2026"
              maxLength={4}
            />
          </View>
          <View style={styles.filtroGroup}>
            <Text style={styles.filtroLabel}>Mês</Text>
            <View style={styles.pickerWrap}>
              <Picker
                selectedValue={filtroMes}
                onValueChange={(val) => setFiltroMes(val)}
                style={styles.picker}
              >
                {['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                  'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'
                ].map((nome, idx) => (
                  <Picker.Item key={idx + 1} label={nome} value={String(idx + 1)} />
                ))}
              </Picker>
            </View>
          </View>
          <TouchableOpacity style={styles.filtroBtn} onPress={aplicarFiltro}>
            <Feather name="search" size={16} color="#fff" />
            <Text style={styles.filtroBtnText}>Filtrar</Text>
          </TouchableOpacity>
        </View>

        {loading ? (
          <View style={styles.loadingBox}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.loadingText}>Verificando check-ins...</Text>
          </View>
        ) : (
          <View style={styles.content}>
            {/* Período */}
            {periodo && (
              <View style={styles.periodoInfo}>
                <Feather name="calendar" size={14} color="#6b7280" />
                <Text style={styles.periodoText}>
                  {MESES[periodo.mes] || ''} / {periodo.ano}
                  {periodo.bonus_cinco_semanas ? '  ·  Bônus de +1 check-in ativo (mês com 5 semanas)' : ''}
                </Text>
              </View>
            )}

            {/* Cards de resumo */}
            <View style={[styles.resumoRow, isMobile && { flexDirection: 'column' }]}>
              <CardResumo
                icon="bar-chart-2"
                iconColor="#64748b"
                label="Total de violações"
                value={totalViolacoes}
                valueColor={totalViolacoes > 0 ? '#ef4444' : '#16a34a'}
              />
              <CardResumo
                icon="calendar"
                iconColor="#7c3aed"
                label="Violações mensais"
                value={resumo?.total_violacoes_mensais}
                valueColor="#7c3aed"
              />
              <CardResumo
                icon="grid"
                iconColor="#2563eb"
                label="Violações semanais"
                value={resumo?.total_violacoes_semanais}
                valueColor="#2563eb"
              />
            </View>

            {/* Estado vazio */}
            {totalViolacoes === 0 ? (
              <View style={styles.emptyState}>
                <Feather name="check-circle" size={52} color="#16a34a" />
                <Text style={styles.emptyTitle}>Nenhuma violação</Text>
                <Text style={styles.emptySubtext}>
                  Todos os alunos respeitaram os limites de check-in no período selecionado.
                </Text>
              </View>
            ) : (
              <View style={{ gap: 24 }}>
                {/* Violações mensais */}
                {violacoesMensais.length > 0 && (
                  <View style={{ gap: 10 }}>
                    <View style={styles.secaoHeader}>
                      <View style={[styles.secaoIconBox, { backgroundColor: '#f3e8ff' }]}>
                        <Feather name="calendar" size={16} color="#7c3aed" />
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.secaoTitulo}>Violações mensais</Text>
                        <Text style={styles.secaoDesc}>Planos com reposição — agrupa por mês inteiro</Text>
                      </View>
                      <View style={[styles.contadorBadge, { backgroundColor: '#f3e8ff' }]}>
                        <Text style={[styles.contadorText, { color: '#7c3aed' }]}>{violacoesMensais.length}</Text>
                      </View>
                    </View>
                    {violacoesMensais.map((v, i) => (
                      <ViolacaoMensalCard key={i} v={v} onGoAluno={irParaAluno} />
                    ))}
                  </View>
                )}

                {/* Violações semanais */}
                {violacoesSemanais.length > 0 && (
                  <View style={{ gap: 10 }}>
                    <View style={styles.secaoHeader}>
                      <View style={[styles.secaoIconBox, { backgroundColor: '#dbeafe' }]}>
                        <Feather name="grid" size={16} color="#2563eb" />
                      </View>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.secaoTitulo}>Violações semanais</Text>
                        <Text style={styles.secaoDesc}>Planos sem reposição — agrupa por semana</Text>
                      </View>
                      <View style={[styles.contadorBadge, { backgroundColor: '#dbeafe' }]}>
                        <Text style={[styles.contadorText, { color: '#2563eb' }]}>{violacoesSemanais.length}</Text>
                      </View>
                    </View>
                    {violacoesSemanais.map((v, i) => (
                      <ViolacaoSemanalCard key={i} v={v} onGoAluno={irParaAluno} />
                    ))}
                  </View>
                )}
              </View>
            )}
          </View>
        )}
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
  filtrosRow: {
    flexDirection: 'row',
    gap: 10,
    paddingHorizontal: 16,
    marginBottom: 16,
    alignItems: 'flex-end',
  },
  filtroGroup: {
    gap: 4,
  },
  filtroLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: '#374151',
  },
  filtroInput: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
    fontSize: 13,
    color: '#111827',
    minWidth: 80,
  },
  pickerWrap: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    overflow: 'hidden',
    minWidth: 150,
  },
  picker: {
    height: 38,
    fontSize: 13,
    color: '#111827',
  },
  filtroBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#f97316',
    paddingVertical: 9,
    paddingHorizontal: 16,
    borderRadius: 8,
  },
  filtroBtnText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#fff',
  },
  loadingBox: {
    alignItems: 'center',
    paddingVertical: 60,
    gap: 12,
  },
  loadingText: {
    fontSize: 15,
    color: '#666',
  },
  content: {
    paddingHorizontal: 16,
    gap: 16,
  },
  periodoInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  periodoText: {
    fontSize: 12,
    color: '#64748b',
    fontWeight: '500',
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
  secaoHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  secaoIconBox: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  secaoTitulo: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
  },
  secaoDesc: {
    fontSize: 11,
    color: '#6b7280',
    marginTop: 1,
  },
  contadorBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 20,
  },
  contadorText: {
    fontSize: 13,
    fontWeight: '800',
  },
  violacaoCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    gap: 10,
  },
  violacaoHeaderRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 10,
  },
  nomeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  nomeAluno: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
  },
  subInfo: {
    fontSize: 11,
    color: '#6b7280',
    marginTop: 3,
  },
  excessoBadge: {
    backgroundColor: '#fee2e2',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 6,
    alignItems: 'center',
    minWidth: 52,
  },
  excessoText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#ef4444',
  },
  excessoLabel: {
    fontSize: 9,
    color: '#ef4444',
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  camposGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#f3f4f6',
  },
  campoItem: {
    minWidth: 120,
    gap: 2,
  },
  campoLabel: {
    fontSize: 10,
    color: '#9ca3af',
    fontWeight: '500',
  },
  campoValue: {
    fontSize: 12,
    fontWeight: '600',
    color: '#374151',
  },
});
