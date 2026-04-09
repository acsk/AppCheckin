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

function ViolacaoCard({ v, tipo, onGoAluno }) {
  const limite = tipo === 'mensal' ? v.limite_mensal : v.limite_semanal;
  return (
    <TouchableOpacity
      style={styles.violacaoCard}
      onPress={() => onGoAluno(v.aluno_id)}
      activeOpacity={0.85}
    >
      {/* Linha superior: avatar + nome + excesso */}
      <View style={styles.cardTopRow}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>
            {(v.aluno_nome || '?')[0].toUpperCase()}
          </Text>
        </View>
        <View style={{ flex: 1 }}>
          <View style={styles.nomeRow}>
            <Text style={styles.nomeAluno} numberOfLines={1}>{v.aluno_nome}</Text>
            <Feather name="external-link" size={11} color="#94a3b8" />
          </View>
          {tipo === 'semanal' && (
            <Text style={styles.semanaTag}>
              {formatarData(v.semana_inicio)} – {formatarData(v.semana_fim)}
            </Text>
          )}
          <View style={styles.tagsRow}>
            <View style={styles.tag}><Text style={styles.tagText}>{v.modalidade}</Text></View>
            <View style={[styles.tag, { backgroundColor: '#f0fdf4' }]}>
              <Text style={[styles.tagText, { color: '#15803d' }]}>{v.plano}</Text>
            </View>
          </View>
        </View>
        <View style={styles.excessoBadge}>
          <Text style={styles.excessoNum}>+{v.excesso}</Text>
          <Text style={styles.excessoLabel}>excesso</Text>
        </View>
      </View>

      {/* Linha inferior: limite vs realizados */}
      <View style={styles.statsRow}>
        <View style={styles.statItem}>
          <Text style={styles.statLabel}>Limite {tipo === 'mensal' ? 'mensal' : 'semanal'}</Text>
          <Text style={styles.statValue}>{limite}</Text>
        </View>
        <View style={styles.statDivider} />
        <View style={styles.statItem}>
          <Text style={styles.statLabel}>Realizados</Text>
          <Text style={[styles.statValue, { color: '#ef4444' }]}>{v.total_checkins}</Text>
        </View>
      </View>
    </TouchableOpacity>
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
    <LayoutBase title="Check-ins acima do limite" subtitle="Auditoria">
      <ScrollView contentContainerStyle={{ paddingBottom: 40 }}>

        {/* ── Barra de filtro ───────────────────────────────────────────── */}
        <View style={[styles.filtroBar, isMobile && { flexDirection: 'column' }]}>
          <View style={styles.filtroBarLeft}>
            <View style={styles.filtroField}>
              <Text style={styles.filtroFieldLabel}>Ano</Text>
              <TextInput
                style={styles.filtroInput}
                value={filtroAno}
                onChangeText={setFiltroAno}
                keyboardType="numeric"
                placeholder="2026"
                maxLength={4}
              />
            </View>
            <View style={styles.filtroField}>
              <Text style={styles.filtroFieldLabel}>Mês</Text>
              <View style={styles.pickerWrap}>
                <Picker
                  selectedValue={filtroMes}
                  onValueChange={(val) => setFiltroMes(val)}
                  style={styles.picker}
                >
                  {['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro',
                  ].map((nome, idx) => (
                    <Picker.Item key={idx + 1} label={nome} value={String(idx + 1)} />
                  ))}
                </Picker>
              </View>
            </View>
          </View>
          <View style={styles.filtroBarRight}>
            <TouchableOpacity style={styles.btnFiltrar} onPress={aplicarFiltro}>
              <Feather name="search" size={15} color="#fff" />
              <Text style={styles.btnFiltrarText}>Filtrar</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.btnAtualizar} onPress={carregar}>
              <Feather name="refresh-cw" size={15} color="#f97316" />
            </TouchableOpacity>
          </View>
        </View>

        {loading ? (
          <View style={styles.loadingBox}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={styles.loadingText}>Verificando check-ins...</Text>
          </View>
        ) : (
          <View style={styles.content}>

            {/* ── Barra de resumo ───────────────────────────────────────── */}
            <View style={styles.resumoBar}>
              <View style={styles.resumoItem}>
                <Text style={[styles.resumoNum, { color: totalViolacoes > 0 ? '#ef4444' : '#16a34a' }]}>
                  {totalViolacoes}
                </Text>
                <Text style={styles.resumoLbl}>Total</Text>
              </View>
              <View style={styles.resumoSep} />
              <View style={styles.resumoItem}>
                <Text style={[styles.resumoNum, { color: '#7c3aed' }]}>
                  {resumo?.total_violacoes_mensais ?? 0}
                </Text>
                <Text style={styles.resumoLbl}>Mensais</Text>
              </View>
              <View style={styles.resumoSep} />
              <View style={styles.resumoItem}>
                <Text style={[styles.resumoNum, { color: '#2563eb' }]}>
                  {resumo?.total_violacoes_semanais ?? 0}
                </Text>
                <Text style={styles.resumoLbl}>Semanais</Text>
              </View>
              {periodo?.bonus_cinco_semanas && (
                <>
                  <View style={styles.resumoSep} />
                  <View style={[styles.resumoItem, { flexDirection: 'row', gap: 6, alignItems: 'center' }]}>
                    <Feather name="zap" size={13} color="#d97706" />
                    <Text style={[styles.resumoLbl, { color: '#d97706', fontWeight: '700' }]}>Bônus 5ª semana</Text>
                  </View>
                </>
              )}
              {periodo && (
                <>
                  <View style={styles.resumoSep} />
                  <View style={[styles.resumoItem, { alignItems: 'flex-end' }]}>
                    <Text style={[styles.resumoLbl, { color: '#94a3b8', fontSize: 10 }]}>Período</Text>
                    <Text style={[styles.resumoLbl, { fontWeight: '700', color: '#475569' }]}>
                      {MESES[periodo.mes]} / {periodo.ano}
                    </Text>
                  </View>
                </>
              )}
            </View>

            {/* ── Estado vazio ──────────────────────────────────────────── */}
            {totalViolacoes === 0 ? (
              <View style={styles.emptyState}>
                <View style={styles.emptyIcon}>
                  <Feather name="check-circle" size={32} color="#16a34a" />
                </View>
                <Text style={styles.emptyTitle}>Tudo certo!</Text>
                <Text style={styles.emptySubtext}>
                  Nenhum aluno ultrapassou o limite de check-ins no período.
                </Text>
              </View>
            ) : (
              <View style={{ gap: 28 }}>
                {/* Violações mensais */}
                {violacoesMensais.length > 0 && (
                  <View style={{ gap: 8 }}>
                    <View style={styles.secaoDivider}>
                      <View style={[styles.secaoDot, { backgroundColor: '#7c3aed' }]} />
                      <Text style={[styles.secaoTitulo, { color: '#7c3aed' }]}>
                        Mensais
                      </Text>
                      <View style={[styles.secaoCount, { backgroundColor: '#f3e8ff' }]}>
                        <Text style={[styles.secaoCountText, { color: '#7c3aed' }]}>{violacoesMensais.length}</Text>
                      </View>
                      <Text style={styles.secaoDesc}>planos com reposição</Text>
                    </View>
                    {violacoesMensais.map((v, i) => (
                      <ViolacaoCard key={i} v={v} tipo="mensal" onGoAluno={irParaAluno} />
                    ))}
                  </View>
                )}

                {/* Violações semanais */}
                {violacoesSemanais.length > 0 && (
                  <View style={{ gap: 8 }}>
                    <View style={styles.secaoDivider}>
                      <View style={[styles.secaoDot, { backgroundColor: '#2563eb' }]} />
                      <Text style={[styles.secaoTitulo, { color: '#2563eb' }]}>
                        Semanais
                      </Text>
                      <View style={[styles.secaoCount, { backgroundColor: '#dbeafe' }]}>
                        <Text style={[styles.secaoCountText, { color: '#2563eb' }]}>{violacoesSemanais.length}</Text>
                      </View>
                      <Text style={styles.secaoDesc}>planos sem reposição</Text>
                    </View>
                    {violacoesSemanais.map((v, i) => (
                      <ViolacaoCard key={i} v={v} tipo="semanal" onGoAluno={irParaAluno} />
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
  // ── Filtro ──────────────────────────────────────────────────────────────
  filtroBar: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    marginBottom: 16,
    gap: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  filtroBarLeft: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'flex-end',
    flex: 1,
    flexWrap: 'wrap',
  },
  filtroBarRight: {
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
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
  pickerWrap: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    overflow: 'hidden',
    minWidth: 150,
  },
  picker: {
    height: 36,
    fontSize: 13,
    color: '#111827',
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
    flexWrap: 'wrap',
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
  secaoDesc: {
    fontSize: 11,
    color: '#94a3b8',
    flex: 1,
  },

  // ── Card de violação ─────────────────────────────────────────────────────
  violacaoCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    gap: 12,
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
  },
  semanaTag: {
    fontSize: 11,
    color: '#2563eb',
    fontWeight: '600',
    marginTop: 2,
  },
  tagsRow: {
    flexDirection: 'row',
    gap: 6,
    marginTop: 5,
    flexWrap: 'wrap',
  },
  tag: {
    backgroundColor: '#f1f5f9',
    borderRadius: 6,
    paddingHorizontal: 8,
    paddingVertical: 3,
  },
  tagText: {
    fontSize: 11,
    color: '#475569',
    fontWeight: '600',
  },
  excessoBadge: {
    backgroundColor: '#fef2f2',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#fecaca',
    minWidth: 58,
  },
  excessoNum: {
    fontSize: 20,
    fontWeight: '900',
    color: '#ef4444',
    lineHeight: 24,
  },
  excessoLabel: {
    fontSize: 9,
    color: '#f87171',
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  statsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    paddingVertical: 10,
    paddingHorizontal: 14,
  },
  statItem: {
    flex: 1,
    alignItems: 'center',
    gap: 2,
  },
  statLabel: {
    fontSize: 10,
    color: '#94a3b8',
    fontWeight: '500',
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  statValue: {
    fontSize: 18,
    fontWeight: '800',
    color: '#1e293b',
  },
  statDivider: {
    width: 1,
    height: 32,
    backgroundColor: '#e2e8f0',
    marginHorizontal: 8,
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
    maxWidth: 280,
    lineHeight: 20,
  },
});
