import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  useWindowDimensions,
  TextInput,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import recordeService from '../../services/recordeService';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';

const CATEGORIAS = [
  { value: '', label: 'Todas' },
  { value: 'movimento', label: 'Movimento' },
  { value: 'prova', label: 'Prova' },
  { value: 'workout', label: 'Workout' },
  { value: 'teste_fisico', label: 'Teste Físico' },
];

const CATEGORIA_CORES = {
  movimento: { bg: '#dbeafe', text: '#1e40af' },
  prova: { bg: '#fce7f3', text: '#9d174d' },
  workout: { bg: '#dcfce7', text: '#166534' },
  teste_fisico: { bg: '#fef3c7', text: '#92400e' },
};

const DIRECAO_LABELS = {
  maior_melhor: '↑ Maior melhor',
  menor_melhor: '↓ Menor melhor',
};

export default function RecordeDefinicoesScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [definicoes, setDefinicoes] = useState([]);
  const [definicoesFiltradas, setDefinicoesFiltradas] = useState([]);
  const [modalidades, setModalidades] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchText, setSearchText] = useState('');
  const [filtroCategoria, setFiltroCategoria] = useState('');
  const [filtroModalidade, setFiltroModalidade] = useState('');
  const [mostrarTodas, setMostrarTodas] = useState(false);
  const [confirmAction, setConfirmAction] = useState({ visible: false, id: null, nome: '', ativo: false });

  useEffect(() => {
    carregarDados();
  }, []);

  useEffect(() => {
    filtrarLocal();
  }, [definicoes, searchText, filtroCategoria, filtroModalidade]);

  const carregarDados = async () => {
    try {
      setLoading(true);
      const [defs, mods] = await Promise.all([
        recordeService.listarDefinicoes({ todas: mostrarTodas ? 'true' : undefined }),
        modalidadeService.listar(),
      ]);
      setDefinicoes(defs);
      setModalidades(mods);
    } catch (error) {
      showError('Não foi possível carregar as definições');
    } finally {
      setLoading(false);
    }
  };

  const filtrarLocal = () => {
    let resultado = [...definicoes];

    if (searchText.trim()) {
      const termo = searchText.toLowerCase();
      resultado = resultado.filter(
        (d) =>
          d.nome?.toLowerCase().includes(termo) ||
          d.descricao?.toLowerCase().includes(termo) ||
          d.modalidade_nome?.toLowerCase().includes(termo)
      );
    }

    if (filtroCategoria) {
      resultado = resultado.filter((d) => d.categoria === filtroCategoria);
    }

    if (filtroModalidade) {
      resultado = resultado.filter((d) => String(d.modalidade_id) === filtroModalidade);
    }

    setDefinicoesFiltradas(resultado);
  };

  const handleToggleTodas = async () => {
    const novoValor = !mostrarTodas;
    setMostrarTodas(novoValor);
    try {
      setLoading(true);
      const defs = await recordeService.listarDefinicoes({ todas: novoValor ? 'true' : undefined });
      setDefinicoes(defs);
    } catch (error) {
      showError('Erro ao recarregar definições');
    } finally {
      setLoading(false);
    }
  };

  const handleToggleStatus = (def) => {
    setConfirmAction({
      visible: true,
      id: def.id,
      nome: def.nome,
      ativo: def.ativo,
    });
  };

  const confirmarToggle = async () => {
    try {
      await recordeService.desativarDefinicao(confirmAction.id);
      const acao = confirmAction.ativo ? 'desativada' : 'ativada';
      showSuccess(`Definição ${acao} com sucesso`);
      setConfirmAction({ visible: false, id: null, nome: '', ativo: false });
      carregarDados();
    } catch (error) {
      showError(error.message || 'Erro ao alterar definição');
    }
  };

  const getCategoriaStyle = (cat) => CATEGORIA_CORES[cat] || { bg: '#f1f5f9', text: '#475569' };

  if (loading) {
    return (
      <LayoutBase showSidebar showHeader title="Definições de Recordes">
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={{ marginTop: 12, color: '#64748b' }}>Carregando definições...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase showSidebar showHeader title="Definições de Recordes">
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: isMobile ? 12 : 20 }}>
        {/* Header com botão Nova Definição */}
        <View style={{ flexDirection: isMobile ? 'column' : 'row', justifyContent: 'space-between', alignItems: isMobile ? 'stretch' : 'center', marginBottom: 16, gap: 10 }}>
          <View>
            <Text style={{ fontSize: 20, fontWeight: '700', color: '#0f172a' }}>Definições de Recordes</Text>
            <Text style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>
              Configure os tipos de recorde disponíveis para os alunos
            </Text>
          </View>
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <TouchableOpacity
              onPress={() => router.push('/recordes')}
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
              <Feather name="list" size={16} color="#f97316" />
              <Text style={{ fontSize: 13, fontWeight: '600', color: '#334155' }}>Ver Recordes</Text>
            </TouchableOpacity>
            <TouchableOpacity
              onPress={() => router.push('/recordes/definicoes/novo')}
              style={{
                flexDirection: 'row',
                alignItems: 'center',
                gap: 6,
                backgroundColor: '#f97316',
                paddingHorizontal: 14,
                paddingVertical: 10,
                borderRadius: 10,
              }}
            >
              <Feather name="plus" size={16} color="#fff" />
              <Text style={{ fontSize: 13, fontWeight: '600', color: '#fff' }}>Nova Definição</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Filtros */}
        <View style={{ backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', padding: 14, marginBottom: 16 }}>
          <View style={{ flexDirection: isMobile ? 'column' : 'row', gap: 10 }}>
            {/* Busca */}
            <View style={{ flex: isMobile ? undefined : 2 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', backgroundColor: '#f8fafc', borderRadius: 8, borderWidth: 1, borderColor: '#e2e8f0', paddingHorizontal: 10 }}>
                <Feather name="search" size={16} color="#94a3b8" />
                <TextInput
                  placeholder="Buscar por nome..."
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

            {/* Categoria */}
            <View style={{ flex: 1 }}>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 6 }}>
                {CATEGORIAS.map((cat) => (
                  <TouchableOpacity
                    key={cat.value}
                    onPress={() => setFiltroCategoria(cat.value)}
                    style={{
                      paddingHorizontal: 12,
                      paddingVertical: 8,
                      borderRadius: 8,
                      backgroundColor: filtroCategoria === cat.value ? '#f97316' : '#f8fafc',
                      borderWidth: 1,
                      borderColor: filtroCategoria === cat.value ? '#f97316' : '#e2e8f0',
                    }}
                  >
                    <Text style={{ fontSize: 12, fontWeight: '600', color: filtroCategoria === cat.value ? '#fff' : '#64748b' }}>
                      {cat.label}
                    </Text>
                  </TouchableOpacity>
                ))}
              </ScrollView>
            </View>
          </View>

          {/* Modalidade + Mostrar todas */}
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 10 }}>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 6 }}>
              <TouchableOpacity
                onPress={() => setFiltroModalidade('')}
                style={{
                  paddingHorizontal: 10,
                  paddingVertical: 6,
                  borderRadius: 6,
                  backgroundColor: filtroModalidade === '' ? '#1e293b' : '#f1f5f9',
                }}
              >
                <Text style={{ fontSize: 11, fontWeight: '600', color: filtroModalidade === '' ? '#fff' : '#64748b' }}>
                  Todas Modalidades
                </Text>
              </TouchableOpacity>
              {modalidades.map((m) => (
                <TouchableOpacity
                  key={m.id}
                  onPress={() => setFiltroModalidade(String(m.id))}
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 6,
                    borderRadius: 6,
                    backgroundColor: filtroModalidade === String(m.id) ? '#1e293b' : '#f1f5f9',
                  }}
                >
                  <Text style={{ fontSize: 11, fontWeight: '600', color: filtroModalidade === String(m.id) ? '#fff' : '#64748b' }}>
                    {m.nome}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>

            <TouchableOpacity
              onPress={handleToggleTodas}
              style={{ flexDirection: 'row', alignItems: 'center', gap: 4, marginLeft: 10 }}
            >
              <Feather name={mostrarTodas ? 'eye' : 'eye-off'} size={14} color="#64748b" />
              <Text style={{ fontSize: 11, color: '#64748b' }}>{mostrarTodas ? 'Todas' : 'Só ativas'}</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Contador */}
        <Text style={{ fontSize: 12, color: '#94a3b8', marginBottom: 10 }}>
          {definicoesFiltradas.length} definição(ões) encontrada(s)
        </Text>

        {/* Lista vazia */}
        {definicoesFiltradas.length === 0 ? (
          <View style={{ alignItems: 'center', paddingVertical: 40 }}>
            <Feather name="award" size={48} color="#cbd5e1" />
            <Text style={{ marginTop: 12, fontSize: 15, fontWeight: '600', color: '#64748b' }}>
              Nenhuma definição encontrada
            </Text>
            <Text style={{ marginTop: 4, fontSize: 13, color: '#94a3b8' }}>
              Crie uma nova definição para começar
            </Text>
          </View>
        ) : (
          /* Grid de cards */
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}>
            {definicoesFiltradas.map((def) => {
              const catStyle = getCategoriaStyle(def.categoria);
              return (
                <TouchableOpacity
                  key={def.id}
                  onPress={() => router.push(`/recordes/definicoes/${def.id}`)}
                  activeOpacity={0.7}
                  style={{
                    width: isMobile ? '100%' : 'calc(50% - 6px)',
                    minWidth: isMobile ? undefined : 320,
                    maxWidth: isMobile ? undefined : 500,
                    backgroundColor: '#fff',
                    borderRadius: 12,
                    borderWidth: 1,
                    borderColor: def.ativo ? '#e2e8f0' : '#fecaca',
                    padding: 14,
                    opacity: def.ativo ? 1 : 0.6,
                  }}
                >
                  {/* Header do card */}
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10 }}>
                    <View style={{ flex: 1 }}>
                      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                        <Feather name="award" size={18} color="#f97316" />
                        <Text style={{ fontSize: 15, fontWeight: '700', color: '#0f172a' }} numberOfLines={1}>
                          {def.nome}
                        </Text>
                      </View>
                      {def.descricao ? (
                        <Text style={{ fontSize: 12, color: '#64748b', marginLeft: 26 }} numberOfLines={2}>
                          {def.descricao}
                        </Text>
                      ) : null}
                    </View>

                    <View style={{ flexDirection: 'row', gap: 4 }}>
                      <TouchableOpacity
                        onPress={(e) => {
                          e.stopPropagation();
                          handleToggleStatus(def);
                        }}
                        style={{ padding: 6 }}
                      >
                        <Feather
                          name={def.ativo ? 'toggle-right' : 'toggle-left'}
                          size={18}
                          color={def.ativo ? '#22c55e' : '#94a3b8'}
                        />
                      </TouchableOpacity>
                    </View>
                  </View>

                  {/* Badges */}
                  <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 10 }}>
                    <View style={{ backgroundColor: catStyle.bg, paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                      <Text style={{ fontSize: 11, fontWeight: '600', color: catStyle.text }}>
                        {def.categoria?.replace('_', ' ')}
                      </Text>
                    </View>
                    {def.modalidade_nome ? (
                      <View style={{ backgroundColor: '#f1f5f9', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                        <Text style={{ fontSize: 11, fontWeight: '600', color: '#475569' }}>{def.modalidade_nome}</Text>
                      </View>
                    ) : null}
                    {!def.ativo ? (
                      <View style={{ backgroundColor: '#fef2f2', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 }}>
                        <Text style={{ fontSize: 11, fontWeight: '600', color: '#dc2626' }}>Inativa</Text>
                      </View>
                    ) : null}
                  </View>

                  {/* Métricas */}
                  {def.metricas?.length > 0 ? (
                    <View style={{ backgroundColor: '#f8fafc', borderRadius: 8, padding: 10, gap: 6 }}>
                      <Text style={{ fontSize: 11, fontWeight: '700', color: '#64748b', textTransform: 'uppercase' }}>
                        Métricas
                      </Text>
                      {def.metricas.map((m, idx) => (
                        <View key={idx} style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                            <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: m.ordem_comparacao === 1 ? '#f97316' : '#cbd5e1' }} />
                            <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155' }}>
                              {m.nome}
                            </Text>
                            {m.unidade ? (
                              <Text style={{ fontSize: 11, color: '#94a3b8' }}>({m.unidade})</Text>
                            ) : null}
                          </View>
                          <Text style={{ fontSize: 10, color: m.direcao === 'maior_melhor' ? '#16a34a' : '#2563eb' }}>
                            {DIRECAO_LABELS[m.direcao] || m.direcao}
                          </Text>
                        </View>
                      ))}
                    </View>
                  ) : null}
                </TouchableOpacity>
              );
            })}
          </View>
        )}
      </ScrollView>

      <ConfirmModal
        visible={confirmAction.visible}
        title={`${confirmAction.ativo ? 'Desativar' : 'Ativar'} Definição`}
        message={`Deseja realmente ${confirmAction.ativo ? 'desativar' : 'ativar'} "${confirmAction.nome}"?`}
        onConfirm={confirmarToggle}
        onCancel={() => setConfirmAction({ visible: false, id: null, nome: '', ativo: false })}
        type="warning"
      />
    </LayoutBase>
  );
}
