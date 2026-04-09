import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  useWindowDimensions,
  TextInput,
  Switch,
  StyleSheet,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import DatePickerInput from '../../components/DatePickerInput';
import { auditoriaService } from '../../services/auditoriaService';
import { showError } from '../../utils/toast';

const hoje = new Date();
const primeiroDiaMes = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-01`;
const hojeStr = hoje.toISOString().slice(0, 10);

const formatarData = (iso) => {
  if (!iso) return '-';
  return new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR');
};

function RegistroCard({ r, onGoAluno, onGoMatricula }) {
  const modalidadeStr = r.modalidade || r.modalidades_do_dia || null;
  return (
    <TouchableOpacity
      style={styles.registroCard}
      onPress={() => onGoAluno(r.aluno_id)}
      activeOpacity={0.85}
    >
      {/* Linha superior: avatar + nome + badge */}
      <View style={styles.cardTopRow}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>
            {(r.aluno_nome || '?')[0].toUpperCase()}
          </Text>
        </View>

        <View style={{ flex: 1, gap: 4 }}>
          <View style={styles.nomeRow}>
            <Text style={styles.nomeAluno} numberOfLines={1}>{r.aluno_nome}</Text>
            <Feather name="external-link" size={11} color="#94a3b8" />
          </View>

          {/* IDs: aluno + matrícula */}
          <View style={styles.idsChipRow}>
            <View style={styles.idChip}>
              <Feather name="user" size={10} color="#64748b" />
              <Text style={styles.idChipText}>Aluno #{r.aluno_id}</Text>
            </View>
            {r.matricula_id ? (
              <TouchableOpacity
                style={[styles.idChip, styles.idChipLink]}
                onPress={(e) => { e.stopPropagation?.(); onGoMatricula(r.matricula_id); }}
                activeOpacity={0.7}
              >
                <Feather name="file-text" size={10} color="#2563eb" />
                <Text style={[styles.idChipText, { color: '#2563eb' }]}>Matrícula #{r.matricula_id}</Text>
                <Feather name="external-link" size={9} color="#2563eb" />
              </TouchableOpacity>
            ) : null}
          </View>

          {modalidadeStr && (
            <View style={styles.tagsRow}>
              <View style={styles.tag}>
                <Feather name="layers" size={10} color="#475569" />
                <Text style={styles.tagText}>{modalidadeStr}</Text>
              </View>
            </View>
          )}
        </View>

        {/* Badge: total + data */}
        <View style={styles.totalBadge}>
          <Text style={styles.totalNum}>{r.total_checkins}</Text>
          <Text style={styles.totalSub}>check-ins</Text>
          <Text style={styles.totalData}>{formatarData(r.data)}</Text>
        </View>
      </View>

      {/* Linha inferior: IDs dos check-ins */}
      <View style={styles.checkinIdsBar}>
        <Text style={styles.checkinIdsLabel}>IDs dos check-ins</Text>
        <Text style={styles.checkinIdsValue}>{r.checkin_ids}</Text>
      </View>
    </TouchableOpacity>
  );
}

export default function CheckinsMultiplosNoDiaScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [filtroDataInicio, setFiltroDataInicio] = useState(primeiroDiaMes);
  const [filtroDataFim, setFiltroDataFim] = useState(hojeStr);
  const [filtroAlunoId, setFiltroAlunoId] = useState('');
  const [filtroModalidadeId, setFiltroModalidadeId] = useState('');
  const [mesmaModalidade, setMesmaModalidade] = useState(false);

  const [loading, setLoading] = useState(true);
  const [filtrosAplicados, setFiltrosAplicados] = useState(null);
  const [total, setTotal] = useState(0);
  const [registros, setRegistros] = useState([]);

  useEffect(() => {
    carregar();
  }, []);

  const carregar = async () => {
    try {
      setLoading(true);
      const filtros = {
        data_inicio: filtroDataInicio || undefined,
        data_fim: filtroDataFim || undefined,
        mesma_modalidade: mesmaModalidade,
      };
      if (filtroAlunoId) filtros.aluno_id = filtroAlunoId;
      if (filtroModalidadeId) filtros.modalidade_id = filtroModalidadeId;

      const response = await auditoriaService.checkinsMultiplosNoDia(filtros);
      setFiltrosAplicados(response.filtros || null);
      setTotal(response.total ?? 0);
      setRegistros(response.registros || []);
    } catch (error) {
      console.error('Erro ao carregar checkins múltiplos:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao carregar dados');
    } finally {
      setLoading(false);
    }
  };

  const irParaAluno = (id) => router.push(`/alunos/${id}`);
  const irParaMatricula = (id) => router.push(`/matriculas/${id}`);

  return (
    <LayoutBase title="Check-ins Múltiplos no Dia" subtitle="Auditoria">
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }}>

        {/* ── Filtros ──────────────────────────────────────────────────── */}
        <View style={styles.filtroCard}>
          <View style={[styles.filtroTopRow, isMobile && { flexDirection: 'column' }]}>
            <DatePickerInput
              label="Data início"
              value={filtroDataInicio}
              onChange={setFiltroDataInicio}
              placeholder="Selecionar data"
            />
            <DatePickerInput
              label="Data fim"
              value={filtroDataFim}
              onChange={setFiltroDataFim}
              placeholder="Selecionar data"
            />
            <View style={styles.filtroField}>
              <Text style={styles.filtroFieldLabel}>Aluno ID</Text>
              <TextInput
                style={styles.filtroInput}
                value={filtroAlunoId}
                onChangeText={setFiltroAlunoId}
                keyboardType="numeric"
                placeholder="Opcional"
              />
            </View>
            <View style={styles.filtroField}>
              <Text style={styles.filtroFieldLabel}>Modalidade ID</Text>
              <TextInput
                style={styles.filtroInput}
                value={filtroModalidadeId}
                onChangeText={setFiltroModalidadeId}
                keyboardType="numeric"
                placeholder="Opcional"
              />
            </View>
          </View>

          <View style={styles.filtroBottomRow}>
            <View style={styles.switchGroup}>
              <Switch
                value={mesmaModalidade}
                onValueChange={setMesmaModalidade}
                trackColor={{ false: '#d1d5db', true: '#fed7aa' }}
                thumbColor={mesmaModalidade ? '#f97316' : '#9ca3af'}
              />
              <View style={{ flex: 1 }}>
                <Text style={styles.switchLabel}>Mesma modalidade</Text>
                <Text style={styles.switchDesc}>
                  Detectar duplicatas apenas quando o aluno fizer check-in na mesma modalidade no mesmo dia
                </Text>
              </View>
            </View>
            <View style={styles.filtroActions}>
              <TouchableOpacity style={styles.btnFiltrar} onPress={carregar}>
                <Feather name="search" size={15} color="#fff" />
                <Text style={styles.btnFiltrarText}>Filtrar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.btnAtualizar} onPress={carregar}>
                <Feather name="refresh-cw" size={15} color="#f97316" />
              </TouchableOpacity>
            </View>
          </View>
        </View>

        {loading ? (
          <View style={styles.loadingBox}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.loadingText}>Verificando check-ins...</Text>
          </View>
        ) : (
          <View style={styles.content}>

            {/* ── Barra de resumo ─────────────────────────────────────── */}
            <View style={styles.resumoBar}>
              <View style={styles.resumoItem}>
                <Text style={[styles.resumoNum, { color: total > 0 ? '#ef4444' : '#16a34a' }]}>
                  {total}
                </Text>
                <Text style={styles.resumoLbl}>Ocorrências</Text>
              </View>
              {filtrosAplicados && (
                <>
                  <View style={styles.resumoSep} />
                  <View style={[styles.resumoItem, { alignItems: 'flex-start', flex: 1 }]}>
                    <Text style={[styles.resumoLbl, { color: '#94a3b8', fontSize: 10 }]}>Período</Text>
                    <Text style={[styles.resumoLbl, { fontWeight: '700', color: '#475569' }]}>
                      {formatarData(filtrosAplicados.data_inicio)} – {formatarData(filtrosAplicados.data_fim)}
                    </Text>
                    <Text style={[styles.resumoLbl, { color: filtrosAplicados.mesma_modalidade ? '#f97316' : '#94a3b8' }]}>
                      {filtrosAplicados.mesma_modalidade ? 'Por modalidade' : 'Qualquer duplicata'}
                    </Text>
                  </View>
                </>
              )}
            </View>

            {/* ── Estado vazio ────────────────────────────────────────── */}
            {total === 0 ? (
              <View style={styles.emptyState}>
                <View style={styles.emptyIcon}>
                  <Feather name="check-circle" size={32} color="#16a34a" />
                </View>
                <Text style={styles.emptyTitle}>Nenhuma ocorrência</Text>
                <Text style={styles.emptySubtext}>
                  Não foram encontrados check-ins múltiplos no mesmo dia para o período selecionado.
                </Text>
              </View>
            ) : (
              <View style={{ gap: 8 }}>
                <View style={styles.secaoDivider}>
                  <View style={[styles.secaoDot, { backgroundColor: '#ef4444' }]} />
                  <Text style={[styles.secaoTitulo, { color: '#ef4444' }]}>Registros</Text>
                  <View style={[styles.secaoCount, { backgroundColor: '#fee2e2' }]}>
                    <Text style={[styles.secaoCountText, { color: '#ef4444' }]}>{total}</Text>
                  </View>
                </View>
                {registros.map((r, i) => (
                  <RegistroCard key={i} r={r} onGoAluno={irParaAluno} onGoMatricula={irParaMatricula} />
                ))}
              </View>
            )}
          </View>
        )}
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  // ── Filtro ──────────────────────────────────────────────────────────────
  filtroCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    marginBottom: 16,
    gap: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  filtroTopRow: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'flex-end',
    flexWrap: 'wrap',
  },
  filtroBottomRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
    flexWrap: 'wrap',
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
    paddingTop: 12,
  },
  filtroField: {
    gap: 4,
  },
  filtroFieldLabel: {
    fontSize: 10,
    fontWeight: '600',
    color: '#94a3b8',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  filtroInput: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 7,
    fontSize: 13,
    color: '#111827',
    minWidth: 70,
  },
  switchGroup: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    flex: 1,
  },
  switchLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: '#111827',
  },
  switchDesc: {
    fontSize: 11,
    color: '#6b7280',
    marginTop: 2,
    maxWidth: 280,
  },
  filtroActions: {
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
  },
  btnFiltrar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#f97316',
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 8,
  },
  btnFiltrarText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#fff',
  },
  btnAtualizar: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    backgroundColor: '#fff',
  },

  // ── Loading ──────────────────────────────────────────────────────────────
  loadingBox: {
    alignItems: 'center',
    paddingVertical: 60,
    gap: 12,
  },
  loadingText: {
    fontSize: 14,
    color: '#94a3b8',
  },

  // ── Conteúdo ─────────────────────────────────────────────────────────────
  content: {
    gap: 16,
  },

  // ── Barra de resumo ──────────────────────────────────────────────────────
  resumoBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderRadius: 12,
    paddingVertical: 14,
    paddingHorizontal: 18,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    gap: 4,
  },
  resumoItem: {
    alignItems: 'center',
    paddingHorizontal: 16,
  },
  resumoNum: {
    fontSize: 26,
    fontWeight: '800',
    lineHeight: 30,
  },
  resumoLbl: {
    fontSize: 11,
    color: '#94a3b8',
    fontWeight: '500',
    marginTop: 2,
  },
  resumoSep: {
    width: 1,
    height: 36,
    backgroundColor: '#e5e7eb',
  },

  // ── Seção ────────────────────────────────────────────────────────────────
  secaoDivider: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 4,
  },
  secaoDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  secaoTitulo: {
    fontSize: 13,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  secaoCount: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 20,
  },
  secaoCountText: {
    fontSize: 12,
    fontWeight: '800',
  },

  // ── Card ─────────────────────────────────────────────────────────────────
  registroCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    gap: 10,
  },
  cardTopRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#f1f5f9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#475569',
  },
  nomeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  nomeAluno: {
    fontSize: 15,
    fontWeight: '700',
    color: '#0f172a',
    flex: 1,
  },
  idsChipRow: {
    flexDirection: 'row',
    gap: 6,
    flexWrap: 'wrap',
  },
  idChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#f1f5f9',
    borderRadius: 6,
    paddingHorizontal: 7,
    paddingVertical: 3,
  },
  idChipLink: {
    backgroundColor: '#eff6ff',
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  idChipText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#475569',
  },
  tagsRow: {
    flexDirection: 'row',
    gap: 6,
    flexWrap: 'wrap',
  },
  tag: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#f1f5f9',
    borderRadius: 6,
    paddingHorizontal: 7,
    paddingVertical: 3,
  },
  tagText: {
    fontSize: 11,
    color: '#475569',
    fontWeight: '600',
  },
  totalBadge: {
    backgroundColor: '#fef2f2',
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 6,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#fecaca',
    minWidth: 58,
    gap: 1,
  },
  totalNum: {
    fontSize: 20,
    fontWeight: '900',
    color: '#ef4444',
    lineHeight: 24,
  },
  totalSub: {
    fontSize: 9,
    color: '#f87171',
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  totalData: {
    fontSize: 10,
    color: '#94a3b8',
    fontWeight: '500',
    marginTop: 2,
  },
  checkinIdsBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#faf5ff',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderWidth: 1,
    borderColor: '#e9d5ff',
  },
  checkinIdsLabel: {
    fontSize: 10,
    color: '#7c3aed',
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.4,
    minWidth: 80,
  },
  checkinIdsValue: {
    fontSize: 12,
    color: '#6d28d9',
    fontWeight: '600',
    flex: 1,
  },

  // ── Empty state ──────────────────────────────────────────────────────────
  emptyState: {
    alignItems: 'center',
    paddingVertical: 60,
    gap: 12,
  },
  emptyIcon: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#f0fdf4',
    alignItems: 'center',
    justifyContent: 'center',
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
    lineHeight: 20,
  },
});
