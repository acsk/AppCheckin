import React, { useEffect, useState } from 'react';
import { View, Text, ScrollView, TextInput, TouchableOpacity, ActivityIndicator } from 'react-native';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import mercadoPagoService from '../../services/mercadoPagoService';
import { showError, showSuccess } from '../../utils/toast';

export default function PagamentosMPScreen() {
  const [paymentId, setPaymentId] = useState('');
  const [loading, setLoading] = useState(false);
  const [consultando, setConsultando] = useState(false);
  const [reprocessando, setReprocessando] = useState(false);
  const [reprocessandoWebhook, setReprocessandoWebhook] = useState(null);
  const [webhooks, setWebhooks] = useState([]);
  const [resultado, setResultado] = useState(null);

  useEffect(() => {
    carregarWebhooks();
  }, []);

  const carregarWebhooks = async () => {
    try {
      setLoading(true);
      const response = await mercadoPagoService.listarWebhooks();
      const lista = Array.isArray(response) ? response : response.webhooks || response.data?.webhooks || [];
      setWebhooks(lista);
    } catch (error) {
      console.error('Erro ao carregar webhooks MP:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao carregar webhooks');
      setWebhooks([]);
    } finally {
      setLoading(false);
    }
  };

  const handleConsultar = async () => {
    if (!paymentId.trim()) {
      showError('Informe o payment_id');
      return;
    }
    try {
      setConsultando(true);
      const response = await mercadoPagoService.consultarPagamento(paymentId.trim());
      setResultado(response);
      showSuccess('Pagamento consultado');
    } catch (error) {
      console.error('Erro ao consultar pagamento MP:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao consultar pagamento');
    } finally {
      setConsultando(false);
    }
  };

  const handleReprocessar = async () => {
    if (!paymentId.trim()) {
      showError('Informe o payment_id');
      return;
    }
    try {
      setReprocessando(true);
      const response = await mercadoPagoService.reprocessarPagamento(paymentId.trim());
      setResultado(response);
      showSuccess('Reprocessamento iniciado');
    } catch (error) {
      console.error('Erro ao reprocessar pagamento MP:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao reprocessar pagamento');
    } finally {
      setReprocessando(false);
    }
  };

  const handleVerWebhook = async (webhookId) => {
    try {
      setConsultando(true);
      const response = await mercadoPagoService.buscarWebhook(webhookId);
      setResultado(response);
    } catch (error) {
      console.error('Erro ao buscar webhook MP:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao buscar webhook');
    } finally {
      setConsultando(false);
    }
  };

  const handleReprocessarWebhook = async (webhookId) => {
    try {
      setReprocessandoWebhook(webhookId);
      const response = await mercadoPagoService.reprocessarWebhook(webhookId);
      setResultado(response);
      showSuccess('Webhook reprocessado');
      carregarWebhooks();
    } catch (error) {
      console.error('Erro ao reprocessar webhook MP:', error);
      showError(error.mensagemLimpa || error.error || 'Erro ao reprocessar webhook');
    } finally {
      setReprocessandoWebhook(null);
    }
  };

  return (
    <LayoutBase title="Pagamentos MP" subtitle="Consultar pagamentos do Mercado Pago">
      <ScrollView className="flex-1">
        <View className="px-5 pt-5 pb-3">
          <Text className="text-sm font-semibold text-slate-700 mb-1">Payment ID</Text>
          <View className="flex-row items-center gap-2">
            <TextInput
              className="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
              placeholder="Ex: 146749614928"
              value={paymentId}
              onChangeText={setPaymentId}
            />
            <TouchableOpacity
              className="flex-row items-center justify-center gap-2 rounded-lg bg-orange-500 px-4 py-2.5"
              onPress={handleConsultar}
              disabled={consultando}
            >
              {consultando ? (
                <ActivityIndicator size="small" color="#fff" />
              ) : (
                <>
                  <Feather name="search" size={16} color="#fff" />
                  <Text className="text-sm font-semibold text-white">Consultar</Text>
                </>
              )}
            </TouchableOpacity>
            <TouchableOpacity
              className="flex-row items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5"
              onPress={handleReprocessar}
              disabled={reprocessando}
            >
              {reprocessando ? (
                <ActivityIndicator size="small" color="#f97316" />
              ) : (
                <>
                  <Feather name="refresh-ccw" size={16} color="#f97316" />
                  <Text className="text-sm font-semibold text-slate-700">Reprocessar</Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </View>

        {resultado && (
          <View className="px-5 pb-4">
            <View className="rounded-xl border border-slate-200 bg-white p-4">
              <Text className="text-sm font-semibold text-slate-700 mb-2">Resultado</Text>
              <Text className="text-xs text-slate-500" selectable>
                {JSON.stringify(resultado, null, 2)}
              </Text>
            </View>
          </View>
        )}

        <View className="px-5 pb-6">
          <View className="flex-row items-center justify-between mb-3">
            <Text className="text-sm font-semibold text-slate-700">Webhooks recebidos</Text>
            <TouchableOpacity
              className="flex-row items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2"
              onPress={carregarWebhooks}
              disabled={loading}
            >
              <Feather name="refresh-cw" size={14} color="#0f172a" />
              <Text className="text-xs font-semibold text-slate-700">Atualizar</Text>
            </TouchableOpacity>
          </View>

          {loading ? (
            <View className="items-center justify-center py-10">
              <ActivityIndicator size="large" color="#f97316" />
            </View>
          ) : webhooks.length === 0 ? (
            <View className="items-center rounded-xl border border-slate-200 bg-white py-10">
              <Feather name="inbox" size={42} color="#cbd5f5" />
              <Text className="mt-3 text-sm font-semibold text-slate-600">Nenhum webhook encontrado</Text>
              <Text className="text-xs text-slate-400">Aguardando notificações do MP</Text>
            </View>
          ) : (
            <View className="rounded-xl border border-slate-200 bg-white overflow-hidden">
              <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-2">
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>ID</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Tipo</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 200 }}>Data</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 1 }}>Status</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 120, textAlign: 'right' }}>Ações</Text>
              </View>
              {webhooks.map((item) => (
                <View key={item.id || item.webhook_id} className="flex-row items-center border-b border-slate-100 px-4 py-2">
                  <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{item.id || item.webhook_id}</Text>
                  <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{item.type || item.tipo || '-'}</Text>
                  <Text className="text-[12px] text-slate-600" style={{ width: 200 }}>{item.created_at || item.data_criacao || '-'}</Text>
                  <Text className="text-[12px] text-slate-600" style={{ flex: 1 }}>{item.status || '-'}</Text>
                  <View style={{ width: 120 }} className="flex-row items-center justify-end gap-2">
                    <TouchableOpacity
                      className="h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white"
                      onPress={() => handleVerWebhook(item.id || item.webhook_id)}
                    >
                      <Feather name="eye" size={14} color="#0f172a" />
                    </TouchableOpacity>
                    <TouchableOpacity
                      className="h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white"
                      onPress={() => handleReprocessarWebhook(item.id || item.webhook_id)}
                      disabled={reprocessandoWebhook === (item.id || item.webhook_id)}
                    >
                      {reprocessandoWebhook === (item.id || item.webhook_id) ? (
                        <ActivityIndicator size="small" color="#f97316" />
                      ) : (
                        <Feather name="refresh-ccw" size={14} color="#f97316" />
                      )}
                    </TouchableOpacity>
                  </View>
                </View>
              ))}
            </View>
          )}
        </View>
      </ScrollView>
    </LayoutBase>
  );
}
