import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  useWindowDimensions,
  TextInput,
  Modal,
  Pressable,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import recordeService from '../../services/recordeService';
import modalidadeService from '../../services/modalidadeService';
import alunoService from '../../services/alunoService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

const ORIGEM_LABELS = { aluno: 'Aluno', academia: 'Academia' };
const ORIGEM_CORES = {
  aluno: { bg: '#dbeafe', text: '#1e40af' },
  academia: { bg: '#fef3c7', text: '#92400e' },
};

function formatarTempo(ms) {
  if (!ms) return '--';
  const totalSec = ms / 1000;
  const min = Math.floor(totalSec / 60);
  const sec = Math.floor(totalSec % 60);
  const milli = Math.round(ms % 1000);
  if (min > 0) {
    return `${min}:${String(sec).padStart(2, '0')}.${String(milli).padStart(3, '0')}`;
  }
  return `${sec}.${String(milli).padStart(3, '0')}s`;
}

function hmsParaMs(hms) {
  if (!hms) return 0;
  const partes = hms.split(':').map(Number);
  let h = 0, m = 0, s = 0;
  if (partes.length === 3) { h = partes[0] || 0; m = partes[1] || 0; s = partes[2] || 0; }
  else if (partes.length === 2) { m = partes[0] || 0; s = partes[1] || 0; }
  else { s = partes[0] || 0; }
  return ((h * 3600) + (m * 60) + s) * 1000;
}

function msParaHms(ms) {
  if (!ms && ms !== 0) return '';
  const totalSec = Math.floor(ms / 1000);
  const h = Math.floor(totalSec / 3600);
  const m = Math.floor((totalSec % 3600) / 60);
  const s = totalSec % 60;
  return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function aplicarMascaraTempo(raw) {
  const digits = raw.replace(/\D/g, '').slice(0, 6); // max 6 dígitos (h:mm:ss)
  const padded = digits.padStart(6, '0');
  const hh = parseInt(padded.slice(0, 2), 10);
  const mm = padded.slice(2, 4);
  const ss = padded.slice(4, 6);
  if (digits.length === 0) return '';
  return `${hh}:${mm}:${ss}`;
}

function formatarValorMetrica(valor) {
  if (valor.valor_tempo_ms != null) return formatarTempo(valor.valor_tempo_ms);
  if (valor.valor_decimal != null) return `${Number(valor.valor_decimal).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 3 })} ${valor.unidade || ''}`.trim();
  if (valor.valor_int != null) return `${valor.valor_int} ${valor.unidade || ''}`.trim();
  return '--';
}

export default function RecordesScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [recordes, setRecordes] = useState([]);
  const [definicoes, setDefinicoes] = useState([]);
  const [modalidades, setModalidades] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchText, setSearchText] = useState('');
  const [filtroDefinicao, setFiltroDefinicao] = useState('');
  const [filtroOrigem, setFiltroOrigem] = useState('');
  const [filtroModalidade, setFiltroModalidade] = useState('');
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null });

  // Modal recorde (novo + editar)
  const [modalVisible, setModalVisible] = useState(false);
  const [modoModal, setModoModal] = useState('novo'); // 'novo' | 'editar'
  const [recordeEditandoId, setRecordeEditandoId] = useState(null);
  const [defSelecionada, setDefSelecionada] = useState(null);
  const [formRecorde, setFormRecorde] = useState({
    aluno_id: '',
    data_recorde: new Date().toISOString().split('T')[0],
    observacoes: '',
    origem: 'academia',
    valores: [],
  });
  const [savingRecorde, setSavingRecorde] = useState(false);

  // Lista de alunos para seleção
  const [alunos, setAlunos] = useState([]);
  const [buscaAluno, setBuscaAluno] = useState('');
  const [alunoSelecionado, setAlunoSelecionado] = useState(null);
  const [dropdownAlunoAberto, setDropdownAlunoAberto] = useState(false);

  // Calendário
  const [calendarVisible, setCalendarVisible] = useState(false);
  const [mesCalendario, setMesCalendario] = useState(new Date());

  useEffect(() => {
    carregarDados();
  }, []);

  const carregarDados = async () => {
    try {
      setLoading(true);
      const [recData, defs, mods, alunosData] = await Promise.all([
        recordeService.listarRecordes(),
        recordeService.listarDefinicoes(),
        modalidadeService.listar(),
        alunoService.listarBasico(),
      ]);
      setRecordes(recData?.recordes || recData || []);
      setDefinicoes(defs);
      setModalidades(mods);
      const listaAlunos = Array.isArray(alunosData?.alunos) ? alunosData.alunos : Array.isArray(alunosData) ? alunosData : [];
      setAlunos(listaAlunos);
    } catch (error) {
      showError('Não foi possível carregar os recordes');
    } finally {
      setLoading(false);
    }
  };

  const recordesFiltrados = React.useMemo(() => {
    let resultado = Array.isArray(recordes) ? [...recordes] : [];

    if (searchText.trim()) {
      const termo = searchText.toLowerCase();
      resultado = resultado.filter(
        (r) =>
          r.aluno_nome?.toLowerCase().includes(termo) ||
          r.definicao_nome?.toLowerCase().includes(termo)
      );
    }

    if (filtroDefinicao) {
      resultado = resultado.filter((r) => String(r.definicao_id) === filtroDefinicao);
    }

    if (filtroOrigem) {
      resultado = resultado.filter((r) => r.origem === filtroOrigem);
    }

    if (filtroModalidade) {
      resultado = resultado.filter((r) => String(r.modalidade_id) === filtroModalidade);
    }

    return resultado;
  }, [recordes, searchText, filtroDefinicao, filtroOrigem, filtroModalidade]);

  const handleExcluir = (recorde) => {
    setConfirmDelete({ visible: true, id: recorde.id });
  };

  const confirmarExclusao = async () => {
    try {
      await recordeService.excluirRecorde(confirmDelete.id);
      showSuccess('Recorde excluído com sucesso');
      setConfirmDelete({ visible: false, id: null });
      carregarDados();
    } catch (error) {
      showError(error.message || 'Erro ao excluir recorde');
    }
  };

  // --- Helpers calendário ---
  const formatarDataBR = (dataISO) => {
    if (!dataISO) return '';
    const [y, m, d] = dataISO.split('-');
    return `${d}/${m}/${y}`;
  };

  const gerarDiasDoMes = (data) => {
    const ano = data.getFullYear();
    const mes = data.getMonth();
    const primeiroDia = new Date(ano, mes, 1);
    const ultimoDia = new Date(ano, mes + 1, 0);
    const diasNoMes = ultimoDia.getDate();
    const diaInicio = primeiroDia.getDay();
    const dias = [];
    for (let i = 0; i < diaInicio; i++) dias.push(null);
    for (let i = 1; i <= diasNoMes; i++) dias.push(new Date(ano, mes, i));
    return dias;
  };

  const navMesCalendario = (dir) => {
    setMesCalendario((prev) => {
      const d = new Date(prev);
      d.setMonth(d.getMonth() + dir);
      return d;
    });
  };

  const selecionarDataCalendario = (dia) => {
    if (!dia) return;
    const iso = `${dia.getFullYear()}-${String(dia.getMonth() + 1).padStart(2, '0')}-${String(dia.getDate()).padStart(2, '0')}`;
    setFormRecorde((prev) => ({ ...prev, data_recorde: iso }));
    setCalendarVisible(false);
  };

  // --- Helpers aluno ---
  const alunosFiltrados = React.useMemo(() => {
    if (!buscaAluno.trim()) return alunos;
    const termo = buscaAluno.toLowerCase();
    return alunos.filter((a) =>
      a.nome?.toLowerCase().includes(termo) ||
      String(a.id).includes(termo) ||
      a.cpf?.includes(termo)
    );
  }, [alunos, buscaAluno]);

  const selecionarAluno = (aluno) => {
    setAlunoSelecionado(aluno);
    setFormRecorde((prev) => ({ ...prev, aluno_id: String(aluno.id) }));
    setBuscaAluno('');
    setDropdownAlunoAberto(false);
  };

  const limparAluno = () => {
    setAlunoSelecionado(null);
    setFormRecorde((prev) => ({ ...prev, aluno_id: '' }));
    setBuscaAluno('');
  };

  // --- Abrir Modal (Novo ou Editar) ---
  const abrirNovoRecorde = (def) => {
    setModoModal('novo');
    setRecordeEditandoId(null);
    setDefSelecionada(def);
    setAlunoSelecionado(null);
    setBuscaAluno('');
    setDropdownAlunoAberto(false);
    const hoje = new Date();
    const hojeISO = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(hoje.getDate()).padStart(2, '0')}`;
    setMesCalendario(hoje);
    setFormRecorde({
      aluno_id: '',
      data_recorde: hojeISO,
      observacoes: '',
      origem: 'academia',
      valores: (def.metricas || []).map((m) => ({
        metrica_id: m.id,
        codigo: m.codigo,
        nome: m.nome,
        tipo_valor: m.tipo_valor,
        unidade: m.unidade,
        valor: '',
      })),
    });
    setModalVisible(true);
  };

  const abrirEditarRecorde = async (rec) => {
    try {
      const detalhes = await recordeService.buscarRecorde(rec.id);
      const recorde = detalhes?.recorde || detalhes;

      setModoModal('editar');
      setRecordeEditandoId(recorde.id);

      const def = definicoes.find((d) => d.id === recorde.definicao_id);
      setDefSelecionada(def || null);

      const aluno = recorde.aluno_id ? alunos.find((a) => a.id === recorde.aluno_id) : null;
      setAlunoSelecionado(aluno || (recorde.aluno_id ? { id: recorde.aluno_id, nome: recorde.aluno_nome || `Aluno #${recorde.aluno_id}` } : null));
      setBuscaAluno('');
      setDropdownAlunoAberto(false);

      const dataRec = recorde.data_recorde?.split('T')[0] || recorde.data_recorde || '';
      setMesCalendario(dataRec ? new Date(dataRec + 'T00:00:00') : new Date());

      const metricas = def?.metricas || [];
      const valoresExistentes = recorde.valores || [];

      const valores = metricas.map((m) => {
        const vExistente = valoresExistentes.find((v) => v.metrica_id === m.id);
        let valorStr = '';
        if (vExistente) {
          if (m.tipo_valor === 'inteiro' && vExistente.valor_int != null) valorStr = String(vExistente.valor_int);
          else if (m.tipo_valor === 'decimal' && vExistente.valor_decimal != null) valorStr = String(vExistente.valor_decimal);
          else if (m.tipo_valor === 'tempo_ms' && vExistente.valor_tempo_ms != null) valorStr = msParaHms(vExistente.valor_tempo_ms);
        }
        return {
          metrica_id: m.id,
          codigo: m.codigo,
          nome: m.nome,
          tipo_valor: m.tipo_valor,
          unidade: m.unidade,
          valor: valorStr,
        };
      });

      setFormRecorde({
        aluno_id: recorde.aluno_id ? String(recorde.aluno_id) : '',
        data_recorde: dataRec,
        observacoes: recorde.observacoes || '',
        origem: recorde.origem || 'academia',
        valores,
      });

      setModalVisible(true);
    } catch (error) {
      showError('Erro ao carregar dados do recorde');
    }
  };

  const handleSalvarRecorde = async () => {
    if (!defSelecionada) return;

    if (!formRecorde.data_recorde) {
      showError('Data do recorde é obrigatória');
      return;
    }

    const valoresPreenchidos = formRecorde.valores.filter((v) => v.valor !== '' && v.valor != null);
    if (valoresPreenchidos.length === 0) {
      showError('Preencha pelo menos um valor');
      return;
    }

    try {
      setSavingRecorde(true);

      const valores = valoresPreenchidos.map((v) => {
        const obj = { metrica_id: v.metrica_id };
        if (v.tipo_valor === 'inteiro') obj.valor_int = Number(v.valor);
        else if (v.tipo_valor === 'decimal') obj.valor_decimal = Number(String(v.valor).replace(',', '.'));
        else if (v.tipo_valor === 'tempo_ms') obj.valor_tempo_ms = hmsParaMs(v.valor);
        return obj;
      });

      const payload = {
        definicao_id: defSelecionada.id,
        aluno_id: alunoSelecionado ? alunoSelecionado.id : null,
        data_recorde: formRecorde.data_recorde,
        observacoes: formRecorde.observacoes || null,
        origem: formRecorde.origem,
        valores,
      };

      if (modoModal === 'editar' && recordeEditandoId) {
        await recordeService.atualizarRecorde(recordeEditandoId, payload);
        showSuccess('Recorde atualizado com sucesso!');
      } else {
        await recordeService.criarRecorde(payload);
        showSuccess('Recorde registrado com sucesso!');
      }

      setModalVisible(false);
      carregarDados();
    } catch (error) {
      showError(error.message || error.error || 'Erro ao salvar recorde');
    } finally {
      setSavingRecorde(false);
    }
  };

  const atualizarValorForm = (idx, valor) => {
    const novos = [...formRecorde.valores];
    novos[idx] = { ...novos[idx], valor };
    setFormRecorde({ ...formRecorde, valores: novos });
  };

  if (loading) {
    return (
      <LayoutBase showSidebar showHeader title="Recordes">
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={{ marginTop: 12, color: '#64748b' }}>Carregando recordes...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase showSidebar showHeader title="Recordes">
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: isMobile ? 12 : 20 }}>
        {/* Header */}
        <View style={{ flexDirection: isMobile ? 'column' : 'row', justifyContent: 'space-between', alignItems: isMobile ? 'stretch' : 'center', marginBottom: 16, gap: 10 }}>
          <View>
            <Text style={{ fontSize: 20, fontWeight: '700', color: '#0f172a' }}>Recordes / PRs</Text>
            <Text style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>
              Registre e acompanhe os recordes dos alunos
            </Text>
          </View>
          <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap' }}>
            <TouchableOpacity
              onPress={() => router.push('/recordes/definicoes')}
              style={{
                flexDirection: 'row',
                alignItems: 'center',
                gap: 6,
                backgroundColor: '#fff',
                borderWidth: 1,
                borderColor: '#e2e8f0',
                paddingHorizontal: 14,
                paddingVertical: 10,
                borderRadius: 10,
              }}
            >
              <Feather name="settings" size={16} color="#64748b" />
              <Text style={{ fontSize: 13, fontWeight: '600', color: '#334155' }}>Definições</Text>
            </TouchableOpacity>
            <TouchableOpacity
              onPress={() => router.push('/recordes/ranking')}
              style={{
                flexDirection: 'row',
                alignItems: 'center',
                gap: 6,
                backgroundColor: '#fff',
                borderWidth: 1,
                borderColor: '#e2e8f0',
                paddingHorizontal: 14,
                paddingVertical: 10,
                borderRadius: 10,
              }}
            >
              <Feather name="bar-chart-2" size={16} color="#f97316" />
              <Text style={{ fontSize: 13, fontWeight: '600', color: '#334155' }}>Ranking</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Seletor de definição para novo recorde */}
        <View style={{ backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', padding: 14, marginBottom: 16 }}>
          <Text style={{ fontSize: 14, fontWeight: '700', color: '#0f172a', marginBottom: 10 }}>
            Registrar Novo Recorde
          </Text>
          <Text style={{ fontSize: 12, color: '#64748b', marginBottom: 10 }}>
            Selecione a definição para registrar um recorde:
          </Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
            {definicoes.filter((d) => d.ativo).map((def) => (
              <TouchableOpacity
                key={def.id}
                onPress={() => abrirNovoRecorde(def)}
                style={{
                  paddingHorizontal: 14,
                  paddingVertical: 10,
                  borderRadius: 10,
                  backgroundColor: '#f97316',
                  flexDirection: 'row',
                  alignItems: 'center',
                  gap: 6,
                }}
              >
                <Feather name="plus-circle" size={14} color="#fff" />
                <Text style={{ fontSize: 12, fontWeight: '600', color: '#fff' }}>{def.nome}</Text>
              </TouchableOpacity>
            ))}
          </ScrollView>
        </View>

        {/* Filtros */}
        <View style={{ backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', padding: 14, marginBottom: 16 }}>
          <View style={{ flexDirection: isMobile ? 'column' : 'row', gap: 10 }}>
            {/* Busca */}
            <View style={{ flex: isMobile ? undefined : 2 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: '#f8fafc', borderRadius: 8, borderWidth: 1, borderColor: '#e2e8f0', paddingHorizontal: 10 }}>
                <Feather name="search" size={16} color="#94a3b8" />
                <TextInput
                  placeholder="Buscar por aluno ou definição..."
                  value={searchText}
                  onChangeText={setSearchText}
                  style={{ flex: 1, paddingVertical: 10, paddingHorizontal: 8, fontSize: 13, color: '#0f172a' }}
                />
                {searchText ? (
                  <TouchableOpacity onPress={() => setSearchText('')}>
                    <Feather name="x" size={16} color="#94a3b8" />
                  </TouchableOpacity>
                ) : null}
              </View>
            </View>

            {/* Origem */}
            <View style={{ flexDirection: 'row', gap: 4 }}>
              {[{ value: '', label: 'Todas' }, { value: 'aluno', label: 'Aluno' }, { value: 'academia', label: 'Academia' }].map((o) => (
                <TouchableOpacity
                  key={o.value}
                  onPress={() => setFiltroOrigem(o.value)}
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 8,
                    borderRadius: 8,
                    backgroundColor: filtroOrigem === o.value ? '#f97316' : '#f8fafc',
                    borderWidth: 1,
                    borderColor: filtroOrigem === o.value ? '#f97316' : '#e2e8f0',
                  }}
                >
                  <Text style={{ fontSize: 12, fontWeight: '600', color: filtroOrigem === o.value ? '#fff' : '#64748b' }}>
                    {o.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>

          {/* Definição */}
          <View style={{ marginTop: 10 }}>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 6 }}>
              <TouchableOpacity
                onPress={() => setFiltroDefinicao('')}
                style={{
                  paddingHorizontal: 10,
                  paddingVertical: 6,
                  borderRadius: 6,
                  backgroundColor: filtroDefinicao === '' ? '#1e293b' : '#f1f5f9',
                }}
              >
                <Text style={{ fontSize: 11, fontWeight: '600', color: filtroDefinicao === '' ? '#fff' : '#64748b' }}>
                  Todas definições
                </Text>
              </TouchableOpacity>
              {definicoes.map((d) => (
                <TouchableOpacity
                  key={d.id}
                  onPress={() => setFiltroDefinicao(String(d.id))}
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 6,
                    borderRadius: 6,
                    backgroundColor: filtroDefinicao === String(d.id) ? '#1e293b' : '#f1f5f9',
                  }}
                >
                  <Text style={{ fontSize: 11, fontWeight: '600', color: filtroDefinicao === String(d.id) ? '#fff' : '#64748b' }}>
                    {d.nome}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>
          </View>
        </View>

        {/* Contador */}
        <Text style={{ fontSize: 12, color: '#94a3b8', marginBottom: 10 }}>
          {recordesFiltrados.length} recorde(s) encontrado(s)
        </Text>

        {/* Lista vazia */}
        {recordesFiltrados.length === 0 ? (
          <View style={{ alignItems: 'center', paddingVertical: 40 }}>
            <Feather name="award" size={48} color="#cbd5e1" />
            <Text style={{ marginTop: 12, fontSize: 15, fontWeight: '600', color: '#64748b' }}>
              Nenhum recorde encontrado
            </Text>
            <Text style={{ marginTop: 4, fontSize: 13, color: '#94a3b8' }}>
              Registre o primeiro recorde usando os botões acima
            </Text>
          </View>
        ) : (
          /* Lista de recordes */
          <View style={{ gap: 10 }}>
            {recordesFiltrados.map((rec) => {
              const origemStyle = ORIGEM_CORES[rec.origem] || { bg: '#f1f5f9', text: '#475569' };
              return (
                <View
                  key={rec.id}
                  style={{
                    backgroundColor: '#fff',
                    borderRadius: 12,
                    borderWidth: 1,
                    borderColor: '#e2e8f0',
                    padding: 14,
                  }}
                >
                  {/* Header */}
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 }}>
                    <View style={{ flex: 1 }}>
                      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                        <Feather name="award" size={16} color="#f97316" />
                        <Text style={{ fontSize: 14, fontWeight: '700', color: '#0f172a' }}>
                          {rec.definicao_nome}
                        </Text>
                      </View>
                      {rec.aluno_nome ? (
                        <Text style={{ fontSize: 13, color: '#334155', marginLeft: 24 }}>
                          {rec.aluno_nome}
                        </Text>
                      ) : (
                        <Text style={{ fontSize: 13, color: '#94a3b8', marginLeft: 24, fontStyle: 'italic' }}>
                          Recorde da academia
                        </Text>
                      )}
                    </View>

                    {/* Ações */}
                    <View style={{ flexDirection: 'row', gap: 6 }}>
                      <TouchableOpacity
                        onPress={() => abrirEditarRecorde(rec)}
                        style={{ padding: 6 }}
                      >
                        <Feather name="edit-2" size={16} color="#3b82f6" />
                      </TouchableOpacity>
                      <TouchableOpacity
                        onPress={() => handleExcluir(rec)}
                        style={{ padding: 6 }}
                      >
                        <Feather name="trash-2" size={16} color="#ef4444" />
                      </TouchableOpacity>
                    </View>
                  </View>

                  {/* Badges */}
                  <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 8 }}>
                    <View style={{ backgroundColor: origemStyle.bg, paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                      <Text style={{ fontSize: 11, fontWeight: '600', color: origemStyle.text }}>
                        {ORIGEM_LABELS[rec.origem] || rec.origem}
                      </Text>
                    </View>
                    {rec.modalidade_nome ? (
                      <View style={{ backgroundColor: '#f1f5f9', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                        <Text style={{ fontSize: 11, fontWeight: '600', color: '#475569' }}>{rec.modalidade_nome}</Text>
                      </View>
                    ) : null}
                    {rec.categoria ? (
                      <View style={{ backgroundColor: '#f1f5f9', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                        <Text style={{ fontSize: 11, fontWeight: '600', color: '#475569' }}>{rec.categoria}</Text>
                      </View>
                    ) : null}
                    <View style={{ backgroundColor: '#f1f5f9', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                      <Text style={{ fontSize: 11, fontWeight: '600', color: '#475569' }}>
                        {rec.data_recorde?.split('-').reverse().join('/')}
                      </Text>
                    </View>
                    {rec.valido === 0 ? (
                      <View style={{ backgroundColor: '#fef2f2', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                        <Text style={{ fontSize: 11, fontWeight: '600', color: '#dc2626' }}>Inválido</Text>
                      </View>
                    ) : null}
                  </View>

                  {/* Valores */}
                  {rec.valores?.length > 0 ? (
                    <View style={{ backgroundColor: '#f8fafc', borderRadius: 8, padding: 10, gap: 4 }}>
                      {rec.valores.map((v, idx) => (
                        <View key={idx} style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                          <Text style={{ fontSize: 12, color: '#64748b' }}>{v.metrica_nome || v.codigo}</Text>
                          <Text style={{ fontSize: 14, fontWeight: '700', color: '#0f172a' }}>
                            {formatarValorMetrica(v)}
                          </Text>
                        </View>
                      ))}
                    </View>
                  ) : null}

                  {/* Observações */}
                  {rec.observacoes ? (
                    <Text style={{ fontSize: 12, color: '#64748b', marginTop: 6, fontStyle: 'italic' }}>
                      {rec.observacoes}
                    </Text>
                  ) : null}
                </View>
              );
            })}
          </View>
        )}
      </ScrollView>

      {/* Modal Recorde (Novo + Editar) */}
      <Modal visible={modalVisible} transparent animationType="fade" onRequestClose={() => setModalVisible(false)}>
        <Pressable style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', alignItems: 'center' }} onPress={() => setModalVisible(false)}>
          <Pressable
            onPress={(e) => e.stopPropagation()}
            style={{
              backgroundColor: '#fff',
              borderRadius: 16,
              width: isMobile ? '92%' : 480,
              maxHeight: '85%',
              padding: 20,
            }}
          >
            <ScrollView showsVerticalScrollIndicator={false}>
              {/* Header modal */}
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                <View>
                  <Text style={{ fontSize: 18, fontWeight: '700', color: '#0f172a' }}>
                    {modoModal === 'editar' ? 'Editar Recorde' : 'Novo Recorde'}
                  </Text>
                  {defSelecionada ? (
                    <Text style={{ fontSize: 13, color: '#f97316', fontWeight: '600', marginTop: 2 }}>
                      {defSelecionada.nome}
                    </Text>
                  ) : null}
                </View>
                <TouchableOpacity onPress={() => setModalVisible(false)}>
                  <Feather name="x" size={22} color="#64748b" />
                </TouchableOpacity>
              </View>

              {/* Aluno */}
              <View style={{ marginBottom: 14 }}>
                <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>
                  Aluno (vazio = recorde da academia)
                </Text>

                {alunoSelecionado ? (
                  <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: '#f0fdf4', borderWidth: 1, borderColor: '#bbf7d0', borderRadius: 8, paddingHorizontal: 12, paddingVertical: 10, gap: 8 }}>
                    <Feather name="user" size={16} color="#16a34a" />
                    <View style={{ flex: 1 }}>
                      <Text style={{ fontSize: 14, fontWeight: '600', color: '#0f172a' }}>{alunoSelecionado.nome}</Text>
                      <Text style={{ fontSize: 11, color: '#64748b' }}>ID: {alunoSelecionado.id}</Text>
                    </View>
                    <TouchableOpacity onPress={limparAluno} style={{ padding: 4 }}>
                      <Feather name="x-circle" size={18} color="#94a3b8" />
                    </TouchableOpacity>
                  </View>
                ) : (
                  <View>
                    <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: '#f8fafc', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 8, paddingHorizontal: 10 }}>
                      <Feather name="search" size={16} color="#94a3b8" />
                      <TextInput
                        placeholder="Buscar aluno por nome, ID ou CPF..."
                        value={buscaAluno}
                        onChangeText={(val) => { setBuscaAluno(val); setDropdownAlunoAberto(true); }}
                        onFocus={() => setDropdownAlunoAberto(true)}
                        style={{ flex: 1, paddingVertical: 10, paddingHorizontal: 8, fontSize: 14, color: '#0f172a' }}
                      />
                      {buscaAluno ? (
                        <TouchableOpacity onPress={() => { setBuscaAluno(''); setDropdownAlunoAberto(false); }}>
                          <Feather name="x" size={16} color="#94a3b8" />
                        </TouchableOpacity>
                      ) : null}
                    </View>

                    {dropdownAlunoAberto && (
                      <View style={{ maxHeight: 180, backgroundColor: '#fff', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 8, marginTop: 4 }}>
                        <ScrollView nestedScrollEnabled keyboardShouldPersistTaps="handled">
                          {alunosFiltrados.length === 0 ? (
                            <View style={{ padding: 14, alignItems: 'center' }}>
                              <Text style={{ fontSize: 12, color: '#94a3b8' }}>Nenhum aluno encontrado</Text>
                            </View>
                          ) : (
                            alunosFiltrados.slice(0, 30).map((a) => (
                              <TouchableOpacity
                                key={a.id}
                                onPress={() => selecionarAluno(a)}
                                style={{ flexDirection: 'row', alignItems: 'center', paddingVertical: 10, paddingHorizontal: 12, borderBottomWidth: 1, borderBottomColor: '#f1f5f9', gap: 8 }}
                              >
                                <View style={{ width: 28, height: 28, borderRadius: 14, backgroundColor: '#f1f5f9', justifyContent: 'center', alignItems: 'center' }}>
                                  <Text style={{ fontSize: 11, fontWeight: '700', color: '#64748b' }}>{a.id}</Text>
                                </View>
                                <View style={{ flex: 1 }}>
                                  <Text style={{ fontSize: 13, fontWeight: '600', color: '#0f172a' }} numberOfLines={1}>{a.nome}</Text>
                                  {a.cpf ? <Text style={{ fontSize: 11, color: '#94a3b8' }}>{a.cpf}</Text> : null}
                                </View>
                              </TouchableOpacity>
                            ))
                          )}
                        </ScrollView>
                      </View>
                    )}
                  </View>
                )}
              </View>

              {/* Data com calendário */}
              <View style={{ marginBottom: 14 }}>
                <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>Data do recorde *</Text>
                <TouchableOpacity
                  onPress={() => { setMesCalendario(formRecorde.data_recorde ? new Date(formRecorde.data_recorde + 'T00:00:00') : new Date()); setCalendarVisible(true); }}
                  style={{
                    flexDirection: 'row',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    borderWidth: 1,
                    borderColor: '#e2e8f0',
                    borderRadius: 8,
                    paddingHorizontal: 12,
                    paddingVertical: 12,
                    backgroundColor: '#f8fafc',
                  }}
                >
                  <Text style={{ fontSize: 14, color: formRecorde.data_recorde ? '#0f172a' : '#94a3b8', fontWeight: formRecorde.data_recorde ? '600' : '400' }}>
                    {formRecorde.data_recorde ? formatarDataBR(formRecorde.data_recorde) : 'dd/mm/aaaa'}
                  </Text>
                  <Feather name="calendar" size={18} color="#f97316" />
                </TouchableOpacity>
              </View>

              {/* Origem */}
              <View style={{ marginBottom: 14 }}>
                <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>Origem</Text>
                <View style={{ flexDirection: 'row', gap: 6 }}>
                  {[{ value: 'academia', label: 'Academia' }, { value: 'aluno', label: 'Aluno' }].map((o) => (
                    <TouchableOpacity
                      key={o.value}
                      onPress={() => setFormRecorde({ ...formRecorde, origem: o.value })}
                      style={{
                        flex: 1,
                        paddingVertical: 10,
                        borderRadius: 8,
                        backgroundColor: formRecorde.origem === o.value ? '#f97316' : '#f8fafc',
                        borderWidth: 1,
                        borderColor: formRecorde.origem === o.value ? '#f97316' : '#e2e8f0',
                        alignItems: 'center',
                      }}
                    >
                      <Text style={{ fontSize: 13, fontWeight: '600', color: formRecorde.origem === o.value ? '#fff' : '#64748b' }}>
                        {o.label}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
              </View>

              {/* Valores das métricas */}
              <View style={{ marginBottom: 14 }}>
                <Text style={{ fontSize: 14, fontWeight: '700', color: '#0f172a', marginBottom: 10 }}>Valores</Text>
                {formRecorde.valores.map((v, idx) => (
                  <View key={idx} style={{ marginBottom: 12 }}>
                    <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>
                      {v.nome} {v.unidade ? `(${v.unidade})` : ''}
                      {v.tipo_valor === 'tempo_ms' ? ' — h:mm:ss' : ''}
                    </Text>
                    <TextInput
                      placeholder={
                        v.tipo_valor === 'inteiro'
                          ? 'Ex: 15'
                          : v.tipo_valor === 'decimal'
                          ? 'Ex: 180.5'
                          : 'Ex: 1:30:00 (h:mm:ss)'
                      }
                      value={v.valor}
                      onChangeText={(val) => {
                        if (v.tipo_valor === 'tempo_ms') {
                          atualizarValorForm(idx, aplicarMascaraTempo(val));
                        } else {
                          atualizarValorForm(idx, val);
                        }
                      }}
                      keyboardType={v.tipo_valor === 'tempo_ms' ? 'number-pad' : 'decimal-pad'}
                      style={{
                        borderWidth: 1,
                        borderColor: '#e2e8f0',
                        borderRadius: 8,
                        paddingHorizontal: 12,
                        paddingVertical: 10,
                        fontSize: 16,
                        fontWeight: '700',
                        backgroundColor: '#f8fafc',
                        color: '#0f172a',
                      }}
                    />
                    {v.tipo_valor === 'tempo_ms' && v.valor ? (
                      <Text style={{ fontSize: 11, color: '#64748b', marginTop: 2 }}>
                        = {hmsParaMs(v.valor).toLocaleString('pt-BR')} ms
                      </Text>
                    ) : null}
                  </View>
                ))}
              </View>

              {/* Observações */}
              <View style={{ marginBottom: 20 }}>
                <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>Observações</Text>
                <TextInput
                  placeholder="Observações opcionais..."
                  value={formRecorde.observacoes}
                  onChangeText={(val) => setFormRecorde({ ...formRecorde, observacoes: val })}
                  multiline
                  numberOfLines={2}
                  style={{
                    borderWidth: 1,
                    borderColor: '#e2e8f0',
                    borderRadius: 8,
                    paddingHorizontal: 12,
                    paddingVertical: 10,
                    fontSize: 14,
                    backgroundColor: '#f8fafc',
                    color: '#0f172a',
                    textAlignVertical: 'top',
                  }}
                />
              </View>

              {/* Botões */}
              <View style={{ flexDirection: 'row', gap: 10 }}>
                <TouchableOpacity
                  onPress={() => setModalVisible(false)}
                  style={{
                    flex: 1,
                    paddingVertical: 12,
                    borderRadius: 10,
                    borderWidth: 1,
                    borderColor: '#e2e8f0',
                    backgroundColor: '#fff',
                    alignItems: 'center',
                  }}
                >
                  <Text style={{ fontSize: 14, fontWeight: '600', color: '#64748b' }}>Cancelar</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  onPress={handleSalvarRecorde}
                  disabled={savingRecorde}
                  style={{
                    flex: 2,
                    paddingVertical: 12,
                    borderRadius: 10,
                    backgroundColor: savingRecorde ? '#fdba74' : '#f97316',
                    alignItems: 'center',
                    flexDirection: 'row',
                    justifyContent: 'center',
                    gap: 6,
                  }}
                >
                  {savingRecorde ? <ActivityIndicator size="small" color="#fff" /> : <Feather name="save" size={16} color="#fff" />}
                  <Text style={{ fontSize: 14, fontWeight: '700', color: '#fff' }}>
                    {savingRecorde ? 'Salvando...' : modoModal === 'editar' ? 'Salvar Alterações' : 'Registrar Recorde'}
                  </Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </Pressable>
        </Pressable>
      </Modal>

      <ConfirmModal
        visible={confirmDelete.visible}
        title="Excluir Recorde"
        message="Tem certeza que deseja excluir este recorde?"
        onConfirm={confirmarExclusao}
        onCancel={() => setConfirmDelete({ visible: false, id: null })}
        type="danger"
      />

      {/* Modal Calendário */}
      <Modal visible={calendarVisible} transparent animationType="fade" onRequestClose={() => setCalendarVisible(false)}>
        <Pressable onPress={() => setCalendarVisible(false)} style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.4)' }}>
          <Pressable onPress={(e) => e.stopPropagation()} style={{ backgroundColor: '#fff', borderRadius: 16, padding: 20, width: isMobile ? '90%' : 340 }}>
            {/* Navegação mês */}
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
              <TouchableOpacity onPress={() => navMesCalendario(-1)} style={{ padding: 6 }}>
                <Feather name="chevron-left" size={20} color="#334155" />
              </TouchableOpacity>
              <Text style={{ fontSize: 15, fontWeight: '700', color: '#0f172a' }}>
                {mesCalendario.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}
              </Text>
              <TouchableOpacity onPress={() => navMesCalendario(1)} style={{ padding: 6 }}>
                <Feather name="chevron-right" size={20} color="#334155" />
              </TouchableOpacity>
            </View>

            {/* Cabeçalho dias da semana */}
            <View style={{ flexDirection: 'row', marginBottom: 6 }}>
              {['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'].map((d) => (
                <View key={d} style={{ flex: 1, alignItems: 'center' }}>
                  <Text style={{ fontSize: 11, fontWeight: '600', color: '#94a3b8' }}>{d}</Text>
                </View>
              ))}
            </View>

            {/* Grid dias */}
            <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
              {gerarDiasDoMes(mesCalendario).map((dia, idx) => {
                if (!dia) return <View key={`e-${idx}`} style={{ width: '14.28%', height: 36 }} />;
                const diaISO = `${dia.getFullYear()}-${String(dia.getMonth() + 1).padStart(2, '0')}-${String(dia.getDate()).padStart(2, '0')}`;
                const selecionado = formRecorde.data_recorde === diaISO;
                const hoje = new Date();
                const ehHoje = dia.getFullYear() === hoje.getFullYear() && dia.getMonth() === hoje.getMonth() && dia.getDate() === hoje.getDate();
                return (
                  <TouchableOpacity
                    key={diaISO}
                    onPress={() => selecionarDataCalendario(dia)}
                    style={{
                      width: '14.28%',
                      height: 36,
                      justifyContent: 'center',
                      alignItems: 'center',
                    }}
                  >
                    <View
                      style={{
                        width: 30,
                        height: 30,
                        borderRadius: 15,
                        justifyContent: 'center',
                        alignItems: 'center',
                        backgroundColor: selecionado ? '#f97316' : ehHoje ? '#fff7ed' : 'transparent',
                        borderWidth: ehHoje && !selecionado ? 1 : 0,
                        borderColor: '#fdba74',
                      }}
                    >
                      <Text style={{ fontSize: 13, fontWeight: selecionado || ehHoje ? '700' : '400', color: selecionado ? '#fff' : '#0f172a' }}>
                        {dia.getDate()}
                      </Text>
                    </View>
                  </TouchableOpacity>
                );
              })}
            </View>

            {/* Botão fechar */}
            <TouchableOpacity onPress={() => setCalendarVisible(false)} style={{ marginTop: 14, paddingVertical: 10, borderRadius: 8, backgroundColor: '#f1f5f9', alignItems: 'center' }}>
              <Text style={{ fontSize: 14, fontWeight: '600', color: '#64748b' }}>Fechar</Text>
            </TouchableOpacity>
          </Pressable>
        </Pressable>
      </Modal>
    </LayoutBase>
  );
}
