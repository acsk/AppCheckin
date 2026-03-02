import React, { useEffect, useMemo, useState } from 'react';
import { View, Text, ScrollView, ActivityIndicator, Switch, TouchableOpacity } from 'react-native';
import { Feather, FontAwesome5 } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import parametrosService from '../../services/parametrosService';
import { showError, showLoading, showSuccess, dismissToast } from '../../utils/toast';

const TRUE_VALUES = new Set(['1', 'true', 'sim', 'yes', 'on']);
const FALSE_VALUES = new Set(['0', 'false', 'nao', 'não', 'no', 'off']);

const parseBoolean = (value) => {
  if (value === true || value === false) return value;
  if (value === 1 || value === 0) return value === 1;
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (TRUE_VALUES.has(normalized)) return true;
    if (FALSE_VALUES.has(normalized)) return false;
  }
  return null;
};

const isBooleanLike = (param, parsed) => {
  if (parsed !== null) return true;
  const tipo = param?.tipo || '';
  return /bool|boolean|flag|ativo|habilitar|enable/i.test(tipo);
};

const getFaIconName = (icone) => {
  if (!icone || typeof icone !== 'string') return 'sliders-h';
  const normalized = icone.trim().toLowerCase();
  if (normalized.startsWith('fa-')) {
    return normalized.replace('fa-', '');
  }
  return normalized;
};

export default function ParametrosScreen() {
  const [loading, setLoading] = useState(true);
  const [categorias, setCategorias] = useState([]);
  const [valores, setValores] = useState({});
  const [meta, setMeta] = useState({});
  const [savingCodigo, setSavingCodigo] = useState(null);

  const carregar = async () => {
    try {
      setLoading(true);
      const response = await parametrosService.listar();
      if (response?.success) {
        const data = Array.isArray(response.data) ? response.data : [];
        const nextValores = {};
        const nextMeta = {};

        data.forEach((categoria) => {
          (categoria.parametros || []).forEach((param) => {
            const codigo = param?.codigo;
            if (!codigo) return;
            const parsed = parseBoolean(param?.valor);
            const boolLike = isBooleanLike(param, parsed);
            const normalized = parsed ?? false;
            nextValores[codigo] = normalized;
            nextMeta[codigo] = {
              original: param?.valor,
              tipo: param?.tipo,
              boolLike,
            };
          });
        });

        setCategorias(data);
        setValores(nextValores);
        setMeta(nextMeta);
      } else {
        showError(response?.message || 'Erro ao carregar parâmetros');
      }
    } catch (error) {
      console.error('Erro ao carregar parâmetros:', error);
      showError('Erro ao carregar parâmetros');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    carregar();
  }, []);

  const categoriasOrdenadas = useMemo(() => {
    return [...categorias]
      .filter((item) => item?.categoria?.ativo !== 0)
      .sort((a, b) => {
        const ordemA = a?.categoria?.ordem ?? 999;
        const ordemB = b?.categoria?.ordem ?? 999;
        return ordemA - ordemB;
      });
  }, [categorias]);

  const handleToggle = async (codigo) => {
    if (savingCodigo) return;
    const currentValue = !!valores[codigo];
    const toastId = showLoading('Atualizando parâmetro...');
    setSavingCodigo(codigo);
    try {
      const response = await parametrosService.toggleParametro(codigo);
      dismissToast(toastId);
      if (response?.success) {
        const nextValue = parseBoolean(response?.valor);
        setValores((prev) => ({
          ...prev,
          [codigo]: nextValue ?? !currentValue,
        }));
        setMeta((prev) => ({
          ...prev,
          [codigo]: {
            ...prev[codigo],
            original: response?.valor,
          },
        }));
        showSuccess(response?.message || 'Parâmetro atualizado');
      } else {
        showError(response?.message || 'Erro ao atualizar parâmetro');
      }
    } catch (error) {
      dismissToast(toastId);
      console.error('Erro ao alternar parâmetro:', error);
      showError('Erro ao atualizar parâmetro');
    } finally {
      setSavingCodigo(null);
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Parâmetros" subtitle="Configurações do sistema">
        <View className="items-center justify-center py-16">
          <ActivityIndicator size="large" color="#f97316" />
          <Text className="mt-3 text-[13px] text-slate-500">Carregando parâmetros...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Parâmetros" subtitle="Ative ou desative parâmetros do sistema">
      <View className="gap-4">
        <View className="flex-row items-center justify-between">
          <View>
            <Text className="text-lg font-bold text-slate-900">Parâmetros do Sistema</Text>
            <Text className="text-[12px] text-slate-500">Alterações são salvas imediatamente</Text>
          </View>
          <View className="flex-row items-center gap-2">
            <TouchableOpacity
              className="flex-row items-center gap-1 rounded-md bg-slate-100 px-3 py-2"
              onPress={carregar}
              disabled={!!savingCodigo}
            >
              <Feather name="refresh-cw" size={14} color="#64748b" />
              <Text className="text-[12px] font-semibold text-slate-700">Recarregar</Text>
            </TouchableOpacity>
          </View>
        </View>

        <ScrollView contentContainerStyle={{ gap: 12, paddingBottom: 8 }}>
          {categoriasOrdenadas.map((categoria, index) => {
            const catInfo = categoria?.categoria || {};
            const catKey = catInfo?.codigo || catInfo?.id || catInfo?.nome || `cat-${index}`;
            return (
              <View key={catKey} className="rounded-xl border border-slate-200 bg-white p-4">
                <View className="flex-row items-start gap-3">
                  <View className="h-9 w-9 items-center justify-center rounded-lg bg-orange-50">
                    <FontAwesome5
                      name={getFaIconName(catInfo?.icone)}
                      size={16}
                      color="#f97316"
                    />
                  </View>
                  <View className="flex-1">
                    <Text className="text-sm font-bold text-slate-900">
                      {catInfo?.nome || catInfo?.descricao || catInfo?.codigo || 'Categoria'}
                    </Text>
                    {catInfo?.descricao && (
                      <Text className="mt-1 text-[11px] text-slate-500">{catInfo.descricao}</Text>
                    )}
                  </View>
                </View>

                <View className="mt-3 gap-3">
                  {(categoria.parametros || []).map((param) => {
                    const codigo = param?.codigo;
                    if (!codigo) return null;
                    const boolLike = meta[codigo]?.boolLike ?? true;
                    const value = !!valores[codigo];
                    const label = param?.nome || param?.descricao || param?.codigo;
                    const descricao = param?.descricao && param?.descricao !== param?.nome ? param?.descricao : null;
                    return (
                      <View key={codigo} className="flex-row items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                        <View className="flex-1 pr-3">
                          <Text className="text-[13px] font-semibold text-slate-800">{label}</Text>
                          <Text className="text-[10px] font-semibold text-slate-400">{codigo}</Text>
                          {descricao && <Text className="text-[11px] text-slate-500">{descricao}</Text>}
                          {!boolLike && (
                            <Text className="text-[11px] text-slate-400">
                              Valor atual: {String(param?.valor ?? '')}
                            </Text>
                          )}
                        </View>
                        <Switch
                          value={value}
                          onValueChange={() => handleToggle(codigo)}
                          disabled={!boolLike || !!savingCodigo}
                          trackColor={{ false: '#e2e8f0', true: '#fdba74' }}
                          thumbColor={value ? '#f97316' : '#ffffff'}
                        />
                      </View>
                    );
                  })}
                </View>
              </View>
            );
          })}
        </ScrollView>
      </View>
    </LayoutBase>
  );
}
