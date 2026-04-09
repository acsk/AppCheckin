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

function RegistroCard({ r, onGoAluno }) {
  return (
    <View style={styles.registroCard}>
      <View style={styles.registroHeaderRow}>
        <View style={{ flex: 1 }}>
          <TouchableOpacity style={styles.nomeRow} onPress={() => onGoAluno(r.aluno_id)}>
            <Feather name="user" size={13} color="#64748b" />
            <Text style={styles.nomeAluno}>{r.aluno_nome}</Text>
            <Feather name="external-link" size={11} color="#9ca3af" />
          </TouchableOpacity>
          <Text style={styles.dataInfo}>{formatarData(r.data)}</Text>
        </View>
        <View style={styles.totalBadge}>
          <Text style={styles.totalNum}>{r.total_checkins}</Text>
          <Text style={styles.totalSub}>check-ins</Text>
        </View>
      </View>

      {r.modalidade && (
        <View style={styles.modalidadeRow}>
          <Feather name="layers" size={12} color="#6b7280" />
          <Text style={styles.modalidadeText}>{r.modalidade}</Text>
        </View>
      )}
      {r.modalidades_do_dia && !r.modalidade && (
        <View style={styles.modalidadeRow}>
          <Feather name="layers" size={12} color="#6b7280" />
          <Text style={styles.modalidadeText}>{r.modalidades_do_dia}</Text>
        </View>
      )}

      <View style={styles.idsRow}>
        <Text style={styles.idsLabel}>IDs dos check-ins:</Text>
        <Text style={styles.idsValue}>{r.checkin_ids}</Text>
      </View>
    </View>
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

  return (
    <LayoutBase title="Auditoria" subtitle="Check-ins múltiplos no dia">
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
        <View style={styles.filtrosContainer}>
          <View style={[styles.filtrosRow, isMobile && { flexDirection: 'column' }]}>
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
            <View style={styles.filtroGroup}>
              <Text style={styles.filtroLabel}>Aluno ID</Text>
              <TextInput
                style={[styles.filtroInput, { minWidth: 70 }]}
                value={filtroAlunoId}
                onChangeText={setFiltroAlunoId}
                keyboardType="numeric"
                placeholder="Opcional"
              />
            </View>
            <View style={styles.filtroGroup}>
              <Text style={styles.filtroLabel}>Modalidade ID</Text>
              <TextInput
                style={[styles.filtroInput, { minWidth: 70 }]}
                value={filtroModalidadeId}
                onChangeText={setFiltroModalidadeId}
                keyboardType="numeric"
                placeholder="Opcional"
              />
            </View>
          </View>

          <View style={styles.filtrosSwitchRow}>
            <View style={styles.switchGroup}>
              <Switch
                value={mesmaModalidade}
                onValueChange={setMesmaModalidade}
                trackColor={{ false: '#d1d5db', true: '#fed7aa' }}
                thumbColor={mesmaModalidade ? '#f97316' : '#9ca3af'}
              />
              <View>
                <Text style={styles.switchLabel}>Mesma modalidade</Text>
                <Text style={styles.switchDesc}>
                  Detectar duplicatas apenas quando o aluno fizer check-in na mesma modalidade no mesmo dia
                </Text>
              </View>
            </View>

            <TouchableOpacity style={styles.filtroBtn} onPress={carregar}>
              <Feather name="search" size={16} color="#fff" />
              <Text style={styles.filtroBtnText}>Filtrar</Text>
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
            {/* Info dos filtros aplicados */}
            {filtrosAplicados && (
              <View style={styles.filtrosInfo}>
                <Feather name="filter" size={13} color="#6b7280" />
                <Text style={styles.filtrosInfoText}>
                  {formatarData(filtrosAplicados.data_inicio)}
                  {' '} – {' '}
                  {formatarData(filtrosAplicados.data_fim)}
                  {filtrosAplicados.mesma_modalidade ? '  ·  Por modalidade' : '  ·  Qualquer duplicata no dia'}
                </Text>
              </View>
            )}

            {/* Resumo */}
            <View style={[styles.resumoCard, { borderLeftColor: total > 0 ? '#ef4444' : '#16a34a' }]}>
              <Feather
                name={total > 0 ? 'alert-triangle' : 'check-circle'}
                size={22}
                color={total > 0 ? '#ef4444' : '#16a34a'}
              />
              <View style={{ flex: 1 }}>
                <Text style={styles.resumoLabel}>Registros encontrados</Text>
                <Text style={[styles.resumoValue, { color: total > 0 ? '#ef4444' : '#16a34a' }]}>
                  {total}
                </Text>
              </View>
            </View>

            {/* Conteúdo */}
            {total === 0 ? (
              <View style={styles.emptyState}>
                <Feather name="check-circle" size={52} color="#16a34a" />
                <Text style={styles.emptyTitle}>Nenhuma ocorrência</Text>
                <Text style={styles.emptySubtext}>
                  Não foram encontrados check-ins múltiplos no mesmo dia para o período selecionado.
                </Text>
              </View>
            ) : (
              <View style={{ gap: 8 }}>
                <Text style={styles.sectionTitle}>
                  {total} ocorrência{total !== 1 ? 's' : ''} encontrada{total !== 1 ? 's' : ''}
                </Text>
                {registros.map((r, i) => (
                  <RegistroCard key={i} r={r} onGoAluno={irParaAluno} />
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
  filtrosContainer: {
    paddingHorizontal: 16,
    marginBottom: 16,
    gap: 12,
  },
  filtrosRow: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'flex-end',
    flexWrap: 'wrap',
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
    minWidth: 110,
  },
  filtrosSwitchRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
    flexWrap: 'wrap',
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
  filtroBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#f97316',
    paddingVertical: 9,
    paddingHorizontal: 16,
    borderRadius: 8,
    alignSelf: 'flex-start',
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
  filtrosInfo: {
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
  filtrosInfoText: {
    fontSize: 12,
    color: '#64748b',
    fontWeight: '500',
  },
  resumoCard: {
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
    fontSize: 26,
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
    maxWidth: 320,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#374151',
  },
  registroCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    gap: 8,
  },
  registroHeaderRow: {
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
  dataInfo: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 3,
  },
  totalBadge: {
    backgroundColor: '#fee2e2',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 6,
    alignItems: 'center',
    minWidth: 50,
  },
  totalNum: {
    fontSize: 16,
    fontWeight: '800',
    color: '#ef4444',
  },
  totalSub: {
    fontSize: 9,
    color: '#ef4444',
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  modalidadeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingTop: 4,
    borderTopWidth: 1,
    borderTopColor: '#f3f4f6',
  },
  modalidadeText: {
    fontSize: 12,
    color: '#374151',
    fontWeight: '500',
    flex: 1,
  },
  idsRow: {
    flexDirection: 'row',
    gap: 6,
    alignItems: 'flex-start',
  },
  idsLabel: {
    fontSize: 10,
    color: '#9ca3af',
    fontWeight: '500',
    paddingTop: 1,
  },
  idsValue: {
    fontSize: 11,
    color: '#6366f1',
    fontWeight: '600',
    flex: 1,
  },
});
