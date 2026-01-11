import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  ActivityIndicator,
  Pressable,
  Platform,
  useWindowDimensions,
  Alert,
  ToastAndroid,
} from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import BaixaPagamentoPlanoModal from '../../components/BaixaPagamentoPlanoModal';
import { matriculaService } from '../../services/matriculaService';

export default function MatriculaDetalheScreen() {
  const { id } = useLocalSearchParams();
  const { width } = useWindowDimensions();
  const isDesktop = width >= 768;

  const [loading, setLoading] = useState(true);
  const [matricula, setMatricula] = useState(null);
  const [pagamentos, setPagamentos] = useState([]);
  const [modalVisible, setModalVisible] = useState(false);
  const [modalConfirmVisible, setModalConfirmVisible] = useState(false);
  const [pagamentoSelecionado, setPagamentoSelecionado] = useState(null);

  useEffect(() => {
    carregarDados();
  }, [id]);

  const carregarDados = async () => {
    try {
      setLoading(true);

      // Buscar dados da matrícula
      const responseMatricula = await matriculaService.buscar(id);
      const dadosMatricula = responseMatricula?.matricula || responseMatricula;
      setMatricula(dadosMatricula);
      setAjustePlano(responseMatricula?.ajuste_plano || null);

      // Buscar histórico de pagamentos (se existir endpoint)
      try {
        const responsePagamentos = await matriculaService.buscarPagamentos(id);
        const dadosPagamentos = responsePagamentos?.pagamentos || responsePagamentos?.data || [];
        setPagamentos(dadosPagamentos);
      } catch (error) {
        console.log('Endpoint de pagamentos não disponível ainda');
        setPagamentos([]);
      }
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
      showAlert('Erro', 'Não foi possível carregar os dados da matrícula');
    } finally {
      setLoading(false);
    }
  };

  const handleBaixaPagamento = (pagamento) => {
    setPagamentoSelecionado(pagamento);
    setModalVisible(true);
  };

  const handleBaixaSuccess = () => {
    setModalVisible(false);
    setPagamentoSelecionado(null);
    showToast('Pagamento confirmado! Próximo pagamento gerado automaticamente.');
    carregarDados();
  };

  const showAlert = (title, message) => {
    Alert.alert(title, message);
  };

  const showToast = (message) => {
    if (Platform.OS === 'android') {
      ToastAndroid.show(message, ToastAndroid.SHORT);
    } else {
      Alert.alert('', message);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'ativa':
        return '#10b981';
      case 'vencida':
        return '#f59e0b';
      case 'cancelada':
        return '#ef4444';
      case 'finalizada':
        return '#6b7280';
      default:
        return '#6b7280';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'ativa':
        return 'Ativa';
      case 'vencida':
        return 'Vencida';
      case 'cancelada':
        return 'Cancelada';
      case 'finalizada':
        return 'Finalizada';
      default:
        return status;
    }
  };

  const getPagamentoStatusColor = (statusId) => {
    const normalizedStatusId = Number(statusId);
    // Status: 1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado
    switch (normalizedStatusId) {
      case 1:
        return '#3B82F6'; // Aguardando (azul)
      case 2:
        return '#10b981'; // Pago (verde)
      case 3:
        return '#dc2626'; // Atrasado (vermelho escuro)
      case 4:
        return '#6B7280'; // Cancelado (cinza)
      default:
        return '#6b7280';
    }
  };

  const getPagamentoStatusLabel = (statusId) => {
    const normalizedStatusId = Number(statusId);
    // Status: 1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado
    switch (normalizedStatusId) {
      case 1:
        return 'Aguardando';
      case 2:
        return 'Pago';
      case 3:
        return 'Atrasado';
      case 4:
        return 'Cancelado';
      default:
        return 'Desconhecido';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Evita problema de timezone criando a data diretamente com os valores
    const [year, month, day] = dateString.split('-');
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('pt-BR');
  };

  const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return '-';
    try {
      // Trata tanto datas simples quanto timestamps completos
      const date = new Date(dateTimeString);
      if (isNaN(date.getTime())) return '-';
      return date.toLocaleDateString('pt-BR');
    } catch (error) {
      return '-';
    }
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value || 0);
  };

  const calcularResumo = () => {
    const getStatusId = (pagamento) => Number(pagamento.status_pagamento_id);
    // Excluir pagamentos cancelados (status 4) do total
    const pagamentosAtivos = pagamentos.filter((p) => getStatusId(p) !== 4);
    
    const total = pagamentosAtivos.reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    
    // Pago = status 2
    const pago = pagamentos
      .filter((p) => getStatusId(p) === 2)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    
    // Atrasado = status 3
    const atrasado = pagamentos
      .filter((p) => getStatusId(p) === 3)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);
    
    // Pendente = status 1 (aguardando)
    const pendente = pagamentos
      .filter((p) => getStatusId(p) === 1)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);

    return { total, pago, atrasado, pendente };
  };

  if (loading) {
    return (
      <LayoutBase title="Detalhes da Matrícula">
        <View className="flex-1 items-center justify-center px-10">
          <ActivityIndicator size="large" color="#f97316" />
          <Text className="mt-4 text-sm text-slate-500">Carregando dados da matrícula...</Text>
        </View>
      </LayoutBase>
    );
  }

  if (!matricula) {
    return (
      <LayoutBase title="Detalhes da Matrícula">
        <View className="flex-1 items-center justify-center px-10">
          <Feather name="alert-circle" size={48} color="#ef4444" />
          <Text className="mt-4 text-base font-semibold text-rose-500">Matrícula não encontrada</Text>
          <Pressable
            className="mt-6 rounded-lg border border-slate-200 bg-white px-4 py-2"
            onPress={() => router.push('/matriculas')}
          >
            <Text className="text-sm font-semibold text-slate-600">Voltar</Text>
          </Pressable>
        </View>
      </LayoutBase>
    );
  }

  const pagamentosPendentes = pagamentos.filter((p) => Number(p.status_pagamento_id) === 1);
  const pagamentosNaoPendentes = pagamentos.filter((p) => Number(p.status_pagamento_id) !== 1);

  const resumo = calcularResumo();

  return (
    <LayoutBase title={`Matrícula #${matricula.id}`}>
      <ScrollView className="flex-1 bg-slate-50">
        <View className={`mx-auto w-full ${isDesktop ? 'max-w-6xl px-6 py-6' : 'px-4 py-5'}`}>
          {/* Header */}
          <View className="mb-4 rounded-2xl border border-slate-100 bg-white px-5 py-4 shadow-sm">
            <Pressable
              className="flex-row items-center gap-2"
              style={({ pressed }) => [pressed && { opacity: 0.7 }]}
              onPress={() => router.push('/matriculas')}
            >
              <Feather name="arrow-left" size={18} color="#94a3b8" />
              <Text className="text-sm font-semibold text-slate-500">Voltar</Text>
            </Pressable>
            
            <View className="mt-4 flex-row items-center justify-between border-t border-slate-100 pt-4">
              <View>
                <Text className="text-lg font-semibold text-slate-800">{matricula.usuario_nome}</Text>
                <Text className="text-xs text-slate-500">
                  Contrato #{matricula.id} • {matricula.modalidade_nome} - {matricula.plano_nome}
                </Text>
              </View>
              <View
                className="self-start rounded-full px-2.5 py-1"
                style={{ backgroundColor: getStatusColor(matricula.status) }}
              >
                <Text className="text-[11px] font-bold text-white">
                  {getStatusLabel(matricula.status)}
                </Text>
              </View>
            </View>
          </View>

          {/* Card com informações da matrícula - Design Compacto */}
          <View className="mb-5 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
            {/* Header do Card */}
            <View className="flex-row items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-4">
              <View className="flex-1 flex-row items-center gap-3">
                {matricula.modalidade_icone && (
                  <View
                    className="h-11 w-11 items-center justify-center rounded-xl"
                    style={{ backgroundColor: matricula.modalidade_cor || '#f97316' }}
                  >
                    <MaterialCommunityIcons
                      name={matricula.modalidade_icone}
                      size={24}
                      color="#fff"
                    />
                  </View>
                )}
                <View className="flex-1">
                  <Text className="text-[15px] font-semibold text-slate-800">{matricula.modalidade_nome}</Text>
                  <Text className="text-xs text-slate-500">{matricula.plano_nome} • Matrícula #{matricula.id}</Text>
                </View>
              </View>
              <View
                className="self-start rounded-full px-2.5 py-1"
                style={{ backgroundColor: getStatusColor(matricula.status) }}
              >
                <Text className="text-[11px] font-bold text-white">{getStatusLabel(matricula.status)}</Text>
              </View>
            </View>

            {/* Grid de Informações Compacto */}
            <View className="flex-row items-center border-b border-slate-100 px-4 py-4">
              <View className="flex-1 items-center gap-1">
                <Feather name="calendar" size={16} color="#f97316" />
                <Text className="text-[10px] font-semibold uppercase text-slate-400">Início</Text>
                <Text className="text-[13px] font-semibold text-slate-700">{formatDate(matricula.data_inicio)}</Text>
              </View>

              <View className="h-8 w-px bg-slate-200" />

              <View className="flex-1 items-center gap-1">
                <Feather name="clock" size={16} color="#f59e0b" />
                <Text className="text-[10px] font-semibold uppercase text-slate-400">Vencimento</Text>
                <Text className="text-[13px] font-semibold text-slate-700">{formatDate(matricula.data_vencimento)}</Text>
              </View>

              <View className="h-8 w-px bg-slate-200" />

              <View className="flex-1 items-center gap-1">
                <MaterialCommunityIcons name="dumbbell" size={16} color="#f97316" />
                <Text className="text-[10px] font-semibold uppercase text-slate-400">Check-ins</Text>
                <Text className="text-[13px] font-semibold text-slate-700">{matricula.checkins_semanais}x/sem</Text>
              </View>

              <View className="h-8 w-px bg-slate-200" />

              <View className="flex-1 items-center gap-1">
                <Feather name="hash" size={16} color="#94a3b8" />
                <Text className="text-[10px] font-semibold uppercase text-slate-400">Duração</Text>
                <Text className="text-[13px] font-semibold text-slate-700">{matricula.duracao_dias} dias</Text>
              </View>
            </View>

            {/* Footer com Valor */}
            <View className="flex-row items-center justify-between bg-emerald-50 px-5 py-4">
              <Text className="text-xs font-semibold text-emerald-700">Valor Mensal</Text>
              <Text className="text-lg font-extrabold text-emerald-600">{formatCurrency(matricula.valor)}</Text>
            </View>
          </View>

          {/* Parcela Pendente - Design Moderno */}
          {matricula.status !== 'cancelada' && matricula.status !== 'finalizada' && 
           pagamentosPendentes.map((pagamento, index) => (
            <View key={pagamento.id || index} className="mb-5 overflow-hidden rounded-2xl border border-orange-100 bg-white shadow-sm">
              <View className="flex-row items-center justify-between border-b border-orange-100 bg-orange-50 px-5 py-4">
                <View className="flex-row items-center gap-3">
                  <View className="h-10 w-10 items-center justify-center rounded-full bg-orange-500">
                    <MaterialCommunityIcons name="calendar-clock" size={20} color="#fff" />
                  </View>
                  <View>
                    <Text className="text-[10px] font-semibold uppercase text-orange-600">
                      Próximo pagamento
                    </Text>
                    <Text className="text-sm font-semibold text-slate-800">
                      Parcela {pagamento.numero_parcela || index + 1}
                    </Text>
                  </View>
                </View>
                <View className="flex-row items-center gap-1 rounded-full bg-white px-2.5 py-1">
                  <MaterialCommunityIcons name="clock-outline" size={14} color="#f59e0b" />
                  <Text className="text-[11px] font-semibold text-amber-600">
                    Aguardando
                  </Text>
                </View>
              </View>

              <View className="flex-row items-center justify-between px-5 py-4">
                <View>
                  <Text className="text-xs text-slate-500">Valor a pagar</Text>
                  <Text className="text-xl font-extrabold text-slate-800">
                    {formatCurrency(pagamento.valor)}
                  </Text>
                </View>
                <View className="h-10 w-px bg-slate-200" />
                <View className="flex-row items-center gap-3">
                  <View className="h-9 w-9 items-center justify-center rounded-full bg-slate-100">
                    <Feather name="calendar" size={16} color="#94a3b8" />
                  </View>
                  <View>
                    <Text className="text-xs text-slate-500">Vencimento</Text>
                    <Text className="text-sm font-semibold text-slate-700">
                      {formatDate(pagamento.data_vencimento)}
                    </Text>
                  </View>
                </View>
              </View>

              <Pressable
                className="flex-row items-center justify-between bg-orange-500 px-5 py-3"
                  style={({ pressed }) => [pressed && { opacity: 0.8 }]}
                  onPress={() => handleBaixaPagamento(pagamento)}
                >
                  <View className="flex-row items-center gap-2">
                    <View className="h-8 w-8 items-center justify-center rounded-full bg-white/20">
                      <Feather name="check" size={16} color="#fff" />
                    </View>
                    <Text className="text-sm font-semibold text-white">Confirmar Pagamento</Text>
                  </View>
                  <View className="opacity-70">
                    <Feather name="arrow-right" size={18} color="#fff" />
                  </View>
                </Pressable>
            </View>
          ))}

          {matricula.status !== 'cancelada' && matricula.status !== 'finalizada' && pagamentosPendentes.length === 0 && (
            <View className="mb-5 overflow-hidden rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
              <View className="flex-row items-center gap-3">
                <View className="h-10 w-10 items-center justify-center rounded-full bg-slate-100">
                  <Feather name="info" size={18} color="#94a3b8" />
                </View>
                <View className="flex-1">
                  <Text className="text-sm font-semibold text-slate-700">Nenhum pagamento pendente</Text>
                  <Text className="mt-1 text-xs text-slate-500">
                    A matrícula ainda não gerou cobranças ou o backend não retornou parcelas.
                  </Text>
                </View>
              </View>
              <Pressable
                className="mt-4 items-center rounded-lg border border-slate-200 bg-white px-4 py-2"
                style={({ pressed }) => [pressed && { opacity: 0.8 }]}
                onPress={carregarDados}
              >
                <Text className="text-xs font-semibold text-slate-600">Recarregar pagamentos</Text>
              </Pressable>
            </View>
          )}

          {/* Tabela de Histórico (Pagamentos Confirmados) */}
          {pagamentosNaoPendentes.length > 0 && (
            <View className="mb-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
              <View className="flex-row items-center gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                <View className="h-9 w-9 items-center justify-center rounded-full bg-orange-100">
                  <Feather name="list" size={18} color="#f97316" />
                </View>
                <Text className="text-sm font-semibold text-slate-800">Histórico de Pagamentos</Text>
              </View>

              <View className="px-4 py-3">
                <View className="flex-row items-center border-b border-slate-200 pb-2">
                  <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 0.6 }}>ID</Text>
                  <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 0.8 }}>Parcela</Text>
                  <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 1.2 }}>Vencimento</Text>
                  <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 1.2 }}>Pagamento</Text>
                  <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 1.5 }}>Baixado por</Text>
                  <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-right" style={{ flex: 1 }}>Valor</Text>
                  <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-center" style={{ flex: 1 }}>Status</Text>
                </View>
                
                {pagamentos
                  .filter(p => Number(p.status_pagamento_id) !== 1)
                  .sort((a, b) => new Date(b.data_vencimento) - new Date(a.data_vencimento))
                  .map((pagamento, index) => (
                  <View key={pagamento.id || index} className="flex-row items-center border-b border-slate-100 py-3">
                    <Text className="text-[12px] text-slate-400" style={{ flex: 0.6 }}>#{pagamento.id}</Text>
                    <Text className="text-[13px] text-slate-600" style={{ flex: 0.8 }}>
                      {pagamento.numero_parcela || index + 1}
                    </Text>
                    <Text className="text-[13px] text-slate-600" style={{ flex: 1.2 }}>
                      {formatDate(pagamento.data_vencimento)}
                    </Text>
                    <Text className="text-[13px] text-emerald-600" style={{ flex: 1.2 }}>
                      {pagamento.data_pagamento ? formatDate(pagamento.data_pagamento) : '-'}
                    </Text>
                    <View style={{ flex: 1.5 }}>
                      <Text className="text-[13px] font-medium text-slate-700">
                        {pagamento.baixado_por_nome || '-'}
                      </Text>
                      {pagamento.tipo_baixa_nome && (
                        <Text className="mt-0.5 text-[11px] text-slate-500">
                          {pagamento.tipo_baixa_nome}
                        </Text>
                      )}
                    </View>
                    <Text className="text-[13px] font-semibold text-slate-700" style={{ flex: 1, textAlign: 'right' }}>
                      {formatCurrency(pagamento.valor)}
                    </Text>
                    <View style={{ flex: 1, alignItems: 'center' }}>
                      <View
                        className="rounded-full px-2.5 py-1"
                        style={{ backgroundColor: getPagamentoStatusColor(pagamento.status_pagamento_id) }}
                      >
                        <Text className="text-[11px] font-bold text-white">
                          {getPagamentoStatusLabel(pagamento.status_pagamento_id)}
                        </Text>
                      </View>
                    </View>
                  </View>
                ))}
              </View>
            </View>
          )}

          {/* Card de Resumo Financeiro - Design Moderno */}
          {pagamentos.length > 0 && (
            <View className="mb-10 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
              <View className="border-b border-slate-200 bg-slate-50 px-5 py-4">
                <View className="flex-row items-center gap-3">
                  <View className="h-10 w-10 items-center justify-center rounded-full bg-orange-500">
                    <MaterialCommunityIcons name="chart-donut" size={20} color="#fff" />
                  </View>
                  <View className="flex-1">
                    <Text className="text-sm font-semibold text-slate-800">Resumo Financeiro</Text>
                    <Text className="text-xs text-slate-500">
                      {Math.round((resumo.pago / resumo.total) * 100)}% pago do total
                    </Text>
                  </View>
                </View>

                <View className="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
                  <View
                    className="h-2 bg-emerald-500"
                    style={{ width: `${(resumo.pago / resumo.total) * 100}%` }}
                  />
                  {resumo.atrasado > 0 && (
                    <View
                      className="h-2 bg-rose-500"
                      style={{ width: `${(resumo.atrasado / resumo.total) * 100}%` }}
                    />
                  )}
                  <View
                    className="h-2 bg-amber-400"
                    style={{ width: `${(resumo.pendente / resumo.total) * 100}%` }}
                  />
                </View>
              </View>

              <View className="flex-row flex-wrap gap-3 px-5 py-4">
                <View className="min-w-[220px] flex-1 rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                  <View className="flex-row items-center gap-2">
                    <Feather name="check-circle" size={18} color="#10b981" />
                    <Text className="text-xs font-semibold text-emerald-700">Pago</Text>
                  </View>
                  <Text className="mt-2 text-lg font-bold text-emerald-600">
                    {formatCurrency(resumo.pago)}
                  </Text>
                </View>

                {resumo.atrasado > 0 && (
                  <View className="min-w-[220px] flex-1 rounded-xl border border-rose-100 bg-rose-50 p-4">
                    <View className="flex-row items-center gap-2">
                      <Feather name="alert-triangle" size={18} color="#ef4444" />
                      <Text className="text-xs font-semibold text-rose-600">Atrasado</Text>
                    </View>
                    <Text className="mt-2 text-lg font-bold text-rose-600">
                      {formatCurrency(resumo.atrasado)}
                    </Text>
                  </View>
                )}

                <View className="min-w-[220px] flex-1 rounded-xl border border-amber-100 bg-amber-50 p-4">
                  <View className="flex-row items-center gap-2">
                    <Feather name="clock" size={18} color="#f59e0b" />
                    <Text className="text-xs font-semibold text-amber-700">Pendente</Text>
                  </View>
                  <Text className="mt-2 text-lg font-bold text-amber-600">
                    {formatCurrency(resumo.pendente)}
                  </Text>
                </View>
              </View>
            </View>
          )}
        </View>
      </ScrollView>

      {/* Modal de Baixa de Pagamento */}
      <BaixaPagamentoPlanoModal
        visible={modalVisible}
        onClose={() => setModalVisible(false)}
        pagamento={pagamentoSelecionado}
        onSuccess={handleBaixaSuccess}
      />
    </LayoutBase>
  );
}
