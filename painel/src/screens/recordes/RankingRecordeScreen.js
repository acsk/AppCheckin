import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  useWindowDimensions,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import recordeService from '../../services/recordeService';
import LayoutBase from '../../components/LayoutBase';
import { showError } from '../../utils/toast';

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

function formatarValorRanking(item) {
  if (item.metrica_tipo_valor === 'tempo_ms') return formatarTempo(Number(item.melhor_valor));
  const val = Number(item.melhor_valor);
  const formatted = item.metrica_tipo_valor === 'decimal'
    ? val.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 3 })
    : String(val);
  return `${formatted} ${item.metrica_unidade || ''}`.trim();
}

const MEDAL_COLORS = ['#f59e0b', '#94a3b8', '#d97706']; // ouro, prata, bronze

export default function RankingRecordeScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [definicoes, setDefinicoes] = useState([]);
  const [defSelecionada, setDefSelecionada] = useState(null);
  const [ranking, setRanking] = useState([]);
  const [definicaoInfo, setDefinicaoInfo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [loadingRanking, setLoadingRanking] = useState(false);

  useEffect(() => {
    carregarDefinicoes();
  }, []);

  const carregarDefinicoes = async () => {
    try {
      setLoading(true);
      const defs = await recordeService.listarDefinicoes();
      setDefinicoes(defs);
      if (defs.length > 0) {
        selecionarDefinicao(defs[0]);
      }
    } catch (error) {
      showError('Não foi possível carregar as definições');
    } finally {
      setLoading(false);
    }
  };

  const selecionarDefinicao = async (def) => {
    setDefSelecionada(def);
    try {
      setLoadingRanking(true);
      const data = await recordeService.ranking(def.id, { limit: 50 });
      setRanking(data?.ranking || []);
      setDefinicaoInfo(data?.definicao || def);
    } catch (error) {
      showError('Não foi possível carregar o ranking');
      setRanking([]);
    } finally {
      setLoadingRanking(false);
    }
  };

  if (loading) {
    return (
      <LayoutBase showSidebar showHeader title="Ranking">
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={{ marginTop: 12, color: '#64748b' }}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase showSidebar showHeader title="Ranking">
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: isMobile ? 12 : 20 }}>
        {/* Header */}
        <View style={{ flexDirection: isMobile ? 'column' : 'row', justifyContent: 'space-between', alignItems: isMobile ? 'stretch' : 'center', marginBottom: 16, gap: 10 }}>
          <View>
            <Text style={{ fontSize: 20, fontWeight: '700', color: '#0f172a' }}>Ranking de Recordes</Text>
            <Text style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>
              Veja os melhores registros por definição
            </Text>
          </View>
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
            <Feather name="arrow-left" size={16} color="#64748b" />
            <Text style={{ fontSize: 13, fontWeight: '600', color: '#334155' }}>Voltar</Text>
          </TouchableOpacity>
        </View>

        {/* Seletor de definição */}
        <View style={{ marginBottom: 16 }}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
            {definicoes.filter((d) => d.ativo).map((def) => (
              <TouchableOpacity
                key={def.id}
                onPress={() => selecionarDefinicao(def)}
                style={{
                  paddingHorizontal: 16,
                  paddingVertical: 10,
                  borderRadius: 10,
                  backgroundColor: defSelecionada?.id === def.id ? '#f97316' : '#fff',
                  borderWidth: 1,
                  borderColor: defSelecionada?.id === def.id ? '#f97316' : '#e2e8f0',
                }}
              >
                <Text
                  style={{
                    fontSize: 13,
                    fontWeight: '600',
                    color: defSelecionada?.id === def.id ? '#fff' : '#334155',
                  }}
                >
                  {def.nome}
                </Text>
                {def.modalidade_nome ? (
                  <Text
                    style={{
                      fontSize: 10,
                      color: defSelecionada?.id === def.id ? '#fed7aa' : '#94a3b8',
                      marginTop: 1,
                    }}
                  >
                    {def.modalidade_nome}
                  </Text>
                ) : null}
              </TouchableOpacity>
            ))}
          </ScrollView>
        </View>

        {/* Info da definição */}
        {definicaoInfo ? (
          <View style={{ backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', padding: 14, marginBottom: 16 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 6 }}>
              <Feather name="award" size={20} color="#f97316" />
              <Text style={{ fontSize: 16, fontWeight: '700', color: '#0f172a' }}>{definicaoInfo.nome}</Text>
            </View>
            {definicaoInfo.metricas?.length > 0 ? (
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4 }}>
                {definicaoInfo.metricas.map((m, idx) => (
                  <View key={idx} style={{ flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#f8fafc', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6 }}>
                    <Text style={{ fontSize: 11, fontWeight: '600', color: '#334155' }}>{m.nome}</Text>
                    {m.unidade ? <Text style={{ fontSize: 10, color: '#94a3b8' }}>({m.unidade})</Text> : null}
                    <Text style={{ fontSize: 10, color: m.direcao === 'maior_melhor' ? '#16a34a' : '#2563eb' }}>
                      {m.direcao === 'maior_melhor' ? '↑' : '↓'}
                    </Text>
                  </View>
                ))}
              </View>
            ) : null}
          </View>
        ) : null}

        {/* Loading ranking */}
        {loadingRanking ? (
          <View style={{ alignItems: 'center', paddingVertical: 40 }}>
            <ActivityIndicator size="large" color="#f97316" />
            <Text style={{ marginTop: 12, color: '#64748b' }}>Carregando ranking...</Text>
          </View>
        ) : ranking.length === 0 ? (
          <View style={{ alignItems: 'center', paddingVertical: 40 }}>
            <Feather name="bar-chart-2" size={48} color="#cbd5e1" />
            <Text style={{ marginTop: 12, fontSize: 15, fontWeight: '600', color: '#64748b' }}>
              Nenhum recorde registrado
            </Text>
            <Text style={{ marginTop: 4, fontSize: 13, color: '#94a3b8' }}>
              Registre recordes para ver o ranking
            </Text>
          </View>
        ) : (
          /* Lista do ranking */
          <View style={{ gap: 8 }}>
            {ranking.map((item, idx) => {
              const isMedal = idx < 3;
              return (
                <View
                  key={`${item.aluno_id}-${idx}`}
                  style={{
                    backgroundColor: '#fff',
                    borderRadius: 12,
                    borderWidth: isMedal ? 2 : 1,
                    borderColor: isMedal ? (MEDAL_COLORS[idx] + '40') : '#e2e8f0',
                    padding: 14,
                    flexDirection: 'row',
                    alignItems: 'center',
                    gap: 12,
                  }}
                >
                  {/* Posição */}
                  <View
                    style={{
                      width: 36,
                      height: 36,
                      borderRadius: 18,
                      backgroundColor: isMedal ? MEDAL_COLORS[idx] : '#f1f5f9',
                      justifyContent: 'center',
                      alignItems: 'center',
                    }}
                  >
                    {isMedal ? (
                      <Feather name="award" size={18} color="#fff" />
                    ) : (
                      <Text style={{ fontSize: 14, fontWeight: '700', color: '#64748b' }}>
                        {idx + 1}
                      </Text>
                    )}
                  </View>

                  {/* Info */}
                  <View style={{ flex: 1 }}>
                    <Text style={{ fontSize: 14, fontWeight: '700', color: '#0f172a' }}>
                      {item.aluno_nome || 'Academia'}
                    </Text>
                    <Text style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>
                      {item.data_recorde?.split('-').reverse().join('/')} • {item.metrica_nome}
                    </Text>
                  </View>

                  {/* Valor */}
                  <View style={{ alignItems: 'flex-end' }}>
                    <Text style={{ fontSize: 18, fontWeight: '800', color: isMedal ? MEDAL_COLORS[idx] : '#0f172a' }}>
                      {formatarValorRanking(item)}
                    </Text>
                  </View>
                </View>
              );
            })}
          </View>
        )}
      </ScrollView>
    </LayoutBase>
  );
}
