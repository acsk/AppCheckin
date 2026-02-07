import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  ActivityIndicator,
  Pressable,
  useWindowDimensions,
  RefreshControl,
} from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';
import { matriculaService } from '../../services/matriculaService';
import { formatarData, formatarValorMonetario, calcularDiasRestantes } from '../../utils/formatadores';

export default function VencimentosScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isDesktop = width >= 768;

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [vencimentosHoje, setVencimentosHoje] = useState([]);
  const [proximosVencimentos, setProximosVencimentos] = useState([]);
  const [diasFiltro, setDiasFiltro] = useState(7);

  useEffect(() => {
    carregarVencimentos();
  }, [diasFiltro]);

  const carregarVencimentos = async () => {
    try {
      setLoading(true);
      
      const [hojeRes, proximosRes] = await Promise.all([
        matriculaService.listarVencimentosHoje(),
        matriculaService.listarProximosVencimentos(diasFiltro)
      ]);
      
      setVencimentosHoje(hojeRes.vencimentos || []);
      setProximosVencimentos(proximosRes.vencimentos || []);
      
    } catch (error) {
      console.error('Erro ao carregar vencimentos:', error);
    } finally {
      setLoading(false);
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await carregarVencimentos();
    setRefreshing(false);
  };

  const getBadgeVencimento = (diasRestantes) => {
    if (diasRestantes === 0) {
      return { label: 'Vence Hoje', color: '#dc2626' };
    }
    if (diasRestantes <= 3) {
      return { label: `${diasRestantes} dias`, color: '#f59e0b' };
    }
    return { label: `${diasRestantes} dias`, color: '#3b82f6' };
  };

  const renderVencimentoCard = (vencimento, isHoje = false) => {
    const badge = isHoje ? { label: 'Hoje', color: '#dc2626' } : getBadgeVencimento(vencimento.dias_restantes);
    
    return (
      <Pressable
        key={vencimento.id}
        onPress={() => router.push(`/matriculas/${vencimento.id}`)}
        className="mb-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        style={({ pressed }) => [pressed && { opacity: 0.7 }]}
      >
        {/* Header */}
        <View className="flex-row items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3">
          <View className="flex-1 flex-row items-center gap-3">
            <View className="h-10 w-10 items-center justify-center rounded-full bg-orange-100">
              <Feather name="user" size={18} color="#f97316" />
            </View>
            <View className="flex-1">
              <Text className="text-sm font-semibold text-slate-800">{vencimento.aluno_nome}</Text>
              <Text className="text-xs text-slate-500">{vencimento.aluno_email}</Text>
            </View>
          </View>
          <View
            className="rounded-full px-3 py-1"
            style={{ backgroundColor: badge.color }}
          >
            <Text className="text-xs font-bold text-white">{badge.label}</Text>
          </View>
        </View>

        {/* Body */}
        <View className="px-4 py-3">
          <View className="mb-3 flex-row items-center justify-between">
            <View>
              <Text className="text-xs text-slate-500">Plano</Text>
              <Text className="text-sm font-semibold text-slate-800">{vencimento.plano_nome}</Text>
            </View>
            <View className="items-end">
              <Text className="text-xs text-slate-500">Valor</Text>
              <Text className="text-sm font-bold text-emerald-600">
                {formatarValorMonetario(vencimento.valor)}
              </Text>
            </View>
          </View>

          <View className="flex-row items-center gap-4">
            <View className="flex-row items-center gap-1">
              <Feather name="calendar" size={14} color="#64748b" />
              <Text className="text-xs text-slate-600">
                Vence: {formatarData(vencimento.proxima_data_vencimento)}
              </Text>
            </View>
            {vencimento.periodo_teste === 1 && (
              <View className="rounded-md bg-blue-100 px-2 py-1">
                <Text className="text-xs font-semibold text-blue-700">Teste</Text>
              </View>
            )}
          </View>
        </View>
      </Pressable>
    );
  };

  if (loading) {
    return (
      <LayoutBase title="Vencimentos" subtitle="Gerenciamento de vencimentos de matrículas">
        <View className="flex-1 items-center justify-center">
          <ActivityIndicator size="large" color="#f97316" />
          <Text className="mt-4 text-sm text-slate-500">Carregando vencimentos...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Vencimentos" subtitle={`${vencimentosHoje.length} vencimentos hoje • ${proximosVencimentos.length} próximos`}>
      <ScrollView
        className="flex-1 bg-slate-50"
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={['#f97316']} />
        }
      >
        <View className={`py-6 ${isDesktop ? 'px-8' : 'px-4'}`}>
          {/* Vencimentos de Hoje */}
          <View className="mb-6">
            <View className="mb-4 flex-row items-center gap-2">
              <View className="h-10 w-10 items-center justify-center rounded-full bg-red-100">
                <Feather name="alert-circle" size={20} color="#dc2626" />
              </View>
              <View>
                <Text className="text-lg font-bold text-slate-800">Vencimentos de Hoje</Text>
                <Text className="text-sm text-slate-500">{vencimentosHoje.length} matrículas</Text>
              </View>
            </View>

            {vencimentosHoje.length === 0 ? (
              <View className="items-center rounded-xl border border-slate-200 bg-white py-12">
                <Feather name="check-circle" size={48} color="#22c55e" />
                <Text className="mt-3 text-base font-semibold text-slate-800">Nenhum vencimento hoje</Text>
                <Text className="mt-1 text-sm text-slate-500">Tudo em dia!</Text>
              </View>
            ) : (
              <View>
                {vencimentosHoje.map(v => renderVencimentoCard(v, true))}
              </View>
            )}
          </View>

          {/* Próximos Vencimentos */}
          <View>
            <View className="mb-4 flex-row items-center justify-between">
              <View className="flex-row items-center gap-2">
                <View className="h-10 w-10 items-center justify-center rounded-full bg-blue-100">
                  <Feather name="calendar" size={20} color="#3b82f6" />
                </View>
                <View>
                  <Text className="text-lg font-bold text-slate-800">Próximos Vencimentos</Text>
                  <Text className="text-sm text-slate-500">{proximosVencimentos.length} nos próximos {diasFiltro} dias</Text>
                </View>
              </View>
            </View>

            {/* Filtros */}
            <View className="mb-4 flex-row gap-2">
              {[7, 15, 30, 60].map((dias) => (
                <Pressable
                  key={dias}
                  onPress={() => setDiasFiltro(dias)}
                  className={`flex-1 rounded-lg py-2 px-3 ${
                    diasFiltro === dias
                      ? 'bg-orange-500'
                      : 'bg-white border border-slate-200'
                  }`}
                  style={({ pressed }) => [pressed && { opacity: 0.7 }]}
                >
                  <Text
                    className={`text-center text-sm font-semibold ${
                      diasFiltro === dias ? 'text-white' : 'text-slate-600'
                    }`}
                  >
                    {dias}d
                  </Text>
                </Pressable>
              ))}
            </View>

            {proximosVencimentos.length === 0 ? (
              <View className="items-center rounded-xl border border-slate-200 bg-white py-12">
                <Feather name="inbox" size={48} color="#94a3b8" />
                <Text className="mt-3 text-base font-semibold text-slate-800">Nenhum vencimento próximo</Text>
                <Text className="mt-1 text-sm text-slate-500">Nos próximos {diasFiltro} dias</Text>
              </View>
            ) : (
              <View>
                {proximosVencimentos.map(v => renderVencimentoCard(v, false))}
              </View>
            )}
          </View>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}
