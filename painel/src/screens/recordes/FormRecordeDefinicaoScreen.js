import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  useWindowDimensions,
  Switch,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter, useLocalSearchParams } from 'expo-router';
import recordeService from '../../services/recordeService';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import { showSuccess, showError } from '../../utils/toast';

const CATEGORIAS = [
  { value: 'movimento', label: 'Movimento' },
  { value: 'prova', label: 'Prova' },
  { value: 'workout', label: 'Workout' },
  { value: 'teste_fisico', label: 'Teste Físico' },
];

const TIPOS_VALOR = [
  { value: 'inteiro', label: 'Inteiro (reps, rounds)' },
  { value: 'decimal', label: 'Decimal (kg, metros)' },
  { value: 'tempo_ms', label: 'Tempo (milissegundos)' },
];

const DIRECOES = [
  { value: 'maior_melhor', label: '↑ Maior é melhor' },
  { value: 'menor_melhor', label: '↓ Menor é melhor' },
];

const METRICA_VAZIA = {
  codigo: '',
  nome: '',
  tipo_valor: 'decimal',
  unidade: '',
  ordem_comparacao: 1,
  direcao: 'maior_melhor',
  obrigatoria: 1,
};

export default function FormRecordeDefinicaoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const definicaoId = id ? parseInt(id) : null;
  const isEdit = !!definicaoId && id !== 'novo';

  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [modalidades, setModalidades] = useState([]);
  const [errors, setErrors] = useState({});

  const [formData, setFormData] = useState({
    nome: '',
    modalidade_id: '',
    categoria: 'movimento',
    descricao: '',
    ordem: '10',
    ativo: true,
  });

  const [metricas, setMetricas] = useState([{ ...METRICA_VAZIA }]);

  useEffect(() => {
    carregarModalidades();
    if (isEdit) {
      carregarDefinicao();
    }
  }, []);

  const carregarModalidades = async () => {
    try {
      const lista = await modalidadeService.listar();
      setModalidades(lista);
    } catch (error) {
      console.error('Erro ao carregar modalidades:', error);
    }
  };

  const carregarDefinicao = async () => {
    try {
      setLoading(true);
      const response = await recordeService.buscarDefinicao(definicaoId);
      const def = response.definicao || response;

      setFormData({
        nome: def.nome || '',
        modalidade_id: def.modalidade_id ? String(def.modalidade_id) : '',
        categoria: def.categoria || 'movimento',
        descricao: def.descricao || '',
        ordem: String(def.ordem || 10),
        ativo: def.ativo === 1 || def.ativo === true,
      });

      if (def.metricas?.length > 0) {
        setMetricas(
          def.metricas.map((m) => ({
            id: m.id,
            codigo: m.codigo || '',
            nome: m.nome || '',
            tipo_valor: m.tipo_valor || 'decimal',
            unidade: m.unidade || '',
            ordem_comparacao: m.ordem_comparacao || 1,
            direcao: m.direcao || 'maior_melhor',
            obrigatoria: m.obrigatoria ?? 1,
          }))
        );
      }
    } catch (error) {
      showError('Não foi possível carregar a definição');
      router.back();
    } finally {
      setLoading(false);
    }
  };

  const validate = () => {
    const newErrors = {};
    if (!formData.nome?.trim()) newErrors.nome = 'Nome é obrigatório';
    if (!formData.categoria) newErrors.categoria = 'Categoria é obrigatória';

    if (metricas.length === 0) {
      newErrors.metricas = 'Adicione pelo menos uma métrica';
    } else {
      metricas.forEach((m, idx) => {
        if (!m.codigo?.trim()) newErrors[`metrica_${idx}_codigo`] = 'Código é obrigatório';
        if (!m.nome?.trim()) newErrors[`metrica_${idx}_nome`] = 'Nome é obrigatório';
        if (!m.direcao) newErrors[`metrica_${idx}_direcao`] = 'Direção é obrigatória';
      });
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validate()) {
      showError('Corrija os erros no formulário');
      return;
    }

    try {
      setSaving(true);

      const dados = {
        nome: formData.nome.trim(),
        modalidade_id: formData.modalidade_id ? Number(formData.modalidade_id) : null,
        categoria: formData.categoria,
        descricao: formData.descricao.trim() || null,
        ordem: Number(formData.ordem) || 10,
        metricas: metricas.map((m, idx) => ({
          ...(m.id ? { id: m.id } : {}),
          codigo: m.codigo.trim(),
          nome: m.nome.trim(),
          tipo_valor: m.tipo_valor,
          unidade: m.unidade.trim() || null,
          ordem_comparacao: idx + 1,
          direcao: m.direcao,
          obrigatoria: m.obrigatoria ? 1 : 0,
        })),
      };

      if (isEdit) {
        await recordeService.atualizarDefinicao(definicaoId, dados);
        showSuccess('Definição atualizada com sucesso');
      } else {
        await recordeService.criarDefinicao(dados);
        showSuccess('Definição criada com sucesso');
      }

      setTimeout(() => router.push('/recordes/definicoes'), 400);
    } catch (error) {
      showError(error.message || error.error || 'Erro ao salvar definição');
    } finally {
      setSaving(false);
    }
  };

  const adicionarMetrica = () => {
    setMetricas([...metricas, { ...METRICA_VAZIA, ordem_comparacao: metricas.length + 1 }]);
  };

  const removerMetrica = (idx) => {
    if (metricas.length <= 1) {
      showError('É necessário pelo menos uma métrica');
      return;
    }
    setMetricas(metricas.filter((_, i) => i !== idx));
  };

  const atualizarMetrica = (idx, campo, valor) => {
    const novas = [...metricas];
    novas[idx] = { ...novas[idx], [campo]: valor };
    setMetricas(novas);
  };

  if (loading) {
    return (
      <LayoutBase showSidebar showHeader title={isEdit ? 'Editar Definição' : 'Nova Definição'}>
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color="#f97316" />
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase showSidebar showHeader title={isEdit ? 'Editar Definição' : 'Nova Definição'}>
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: isMobile ? 12 : 20 }}>
        {/* Voltar */}
        <TouchableOpacity
          onPress={() => router.push('/recordes/definicoes')}
          style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 16 }}
        >
          <Feather name="arrow-left" size={16} color="#64748b" />
          <Text style={{ fontSize: 13, color: '#64748b' }}>Voltar para definições</Text>
        </TouchableOpacity>

        {/* Card principal */}
        <View style={{ backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', padding: 16, marginBottom: 16 }}>
          <Text style={{ fontSize: 16, fontWeight: '700', color: '#0f172a', marginBottom: 16 }}>
            Dados da Definição
          </Text>

          {/* Nome */}
          <View style={{ marginBottom: 14 }}>
            <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>Nome *</Text>
            <TextInput
              placeholder="Ex: Deadlift, 100m Crawl, AMRAP 12min"
              value={formData.nome}
              onChangeText={(val) => setFormData({ ...formData, nome: val })}
              style={{
                borderWidth: 1,
                borderColor: errors.nome ? '#ef4444' : '#e2e8f0',
                borderRadius: 8,
                paddingHorizontal: 12,
                paddingVertical: 10,
                fontSize: 14,
                color: '#0f172a',
                backgroundColor: '#f8fafc',
              }}
            />
            {errors.nome ? <Text style={{ color: '#ef4444', fontSize: 11, marginTop: 2 }}>{errors.nome}</Text> : null}
          </View>

          {/* Categoria */}
          <View style={{ marginBottom: 14 }}>
            <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>Categoria *</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
              {CATEGORIAS.map((cat) => (
                <TouchableOpacity
                  key={cat.value}
                  onPress={() => setFormData({ ...formData, categoria: cat.value })}
                  style={{
                    paddingHorizontal: 14,
                    paddingVertical: 8,
                    borderRadius: 8,
                    backgroundColor: formData.categoria === cat.value ? '#f97316' : '#f8fafc',
                    borderWidth: 1,
                    borderColor: formData.categoria === cat.value ? '#f97316' : '#e2e8f0',
                  }}
                >
                  <Text style={{ fontSize: 13, fontWeight: '600', color: formData.categoria === cat.value ? '#fff' : '#64748b' }}>
                    {cat.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
            {errors.categoria ? <Text style={{ color: '#ef4444', fontSize: 11, marginTop: 2 }}>{errors.categoria}</Text> : null}
          </View>

          {/* Modalidade */}
          <View style={{ marginBottom: 14 }}>
            <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>Modalidade</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 6 }}>
              <TouchableOpacity
                onPress={() => setFormData({ ...formData, modalidade_id: '' })}
                style={{
                  paddingHorizontal: 12,
                  paddingVertical: 8,
                  borderRadius: 8,
                  backgroundColor: formData.modalidade_id === '' ? '#1e293b' : '#f8fafc',
                  borderWidth: 1,
                  borderColor: formData.modalidade_id === '' ? '#1e293b' : '#e2e8f0',
                }}
              >
                <Text style={{ fontSize: 12, fontWeight: '600', color: formData.modalidade_id === '' ? '#fff' : '#64748b' }}>
                  Nenhuma
                </Text>
              </TouchableOpacity>
              {modalidades.map((m) => (
                <TouchableOpacity
                  key={m.id}
                  onPress={() => setFormData({ ...formData, modalidade_id: String(m.id) })}
                  style={{
                    paddingHorizontal: 12,
                    paddingVertical: 8,
                    borderRadius: 8,
                    backgroundColor: formData.modalidade_id === String(m.id) ? '#1e293b' : '#f8fafc',
                    borderWidth: 1,
                    borderColor: formData.modalidade_id === String(m.id) ? '#1e293b' : '#e2e8f0',
                  }}
                >
                  <Text style={{ fontSize: 12, fontWeight: '600', color: formData.modalidade_id === String(m.id) ? '#fff' : '#64748b' }}>
                    {m.nome}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>
          </View>

          {/* Descrição */}
          <View style={{ marginBottom: 14 }}>
            <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>Descrição</Text>
            <TextInput
              placeholder="Ex: 5 Pull-ups, 10 Push-ups, 15 Squats"
              value={formData.descricao}
              onChangeText={(val) => setFormData({ ...formData, descricao: val })}
              multiline
              numberOfLines={2}
              style={{
                borderWidth: 1,
                borderColor: '#e2e8f0',
                borderRadius: 8,
                paddingHorizontal: 12,
                paddingVertical: 10,
                fontSize: 14,
                color: '#0f172a',
                backgroundColor: '#f8fafc',
                textAlignVertical: 'top',
              }}
            />
          </View>

          {/* Ordem */}
          <View style={{ flexDirection: 'row', gap: 12, marginBottom: 14 }}>
            <View style={{ flex: 1 }}>
              <Text style={{ fontSize: 12, fontWeight: '600', color: '#334155', marginBottom: 4 }}>Ordem</Text>
              <TextInput
                value={formData.ordem}
                onChangeText={(val) => setFormData({ ...formData, ordem: val.replace(/[^0-9]/g, '') })}
                keyboardType="numeric"
                style={{
                  borderWidth: 1,
                  borderColor: '#e2e8f0',
                  borderRadius: 8,
                  paddingHorizontal: 12,
                  paddingVertical: 10,
                  fontSize: 14,
                  color: '#0f172a',
                  backgroundColor: '#f8fafc',
                }}
              />
            </View>
            {isEdit ? (
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, paddingTop: 20 }}>
                <Text style={{ fontSize: 13, color: '#334155' }}>Ativa</Text>
                <Switch
                  value={formData.ativo}
                  onValueChange={(val) => setFormData({ ...formData, ativo: val })}
                  trackColor={{ false: '#cbd5e1', true: '#f97316' }}
                  thumbColor="#fff"
                />
              </View>
            ) : null}
          </View>
        </View>

        {/* Card Métricas */}
        <View style={{ backgroundColor: '#fff', borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', padding: 16, marginBottom: 16 }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
            <View>
              <Text style={{ fontSize: 16, fontWeight: '700', color: '#0f172a' }}>Métricas</Text>
              <Text style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>
                Defina como o recorde será medido
              </Text>
            </View>
            <TouchableOpacity
              onPress={adicionarMetrica}
              style={{
                flexDirection: 'row',
                alignItems: 'center',
                gap: 4,
                backgroundColor: '#f0f9ff',
                paddingHorizontal: 10,
                paddingVertical: 6,
                borderRadius: 8,
                borderWidth: 1,
                borderColor: '#bae6fd',
              }}
            >
              <Feather name="plus" size={14} color="#0284c7" />
              <Text style={{ fontSize: 12, fontWeight: '600', color: '#0284c7' }}>Métrica</Text>
            </TouchableOpacity>
          </View>

          {errors.metricas ? <Text style={{ color: '#ef4444', fontSize: 11, marginBottom: 10 }}>{errors.metricas}</Text> : null}

          {metricas.map((metrica, idx) => (
            <View
              key={idx}
              style={{
                backgroundColor: '#f8fafc',
                borderRadius: 10,
                borderWidth: 1,
                borderColor: '#e2e8f0',
                padding: 14,
                marginBottom: 12,
              }}
            >
              {/* Header métrica */}
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                  <View style={{ width: 22, height: 22, borderRadius: 11, backgroundColor: idx === 0 ? '#f97316' : '#cbd5e1', justifyContent: 'center', alignItems: 'center' }}>
                    <Text style={{ fontSize: 11, fontWeight: '700', color: '#fff' }}>{idx + 1}</Text>
                  </View>
                  <Text style={{ fontSize: 13, fontWeight: '600', color: '#334155' }}>
                    {idx === 0 ? 'Métrica principal' : `Métrica ${idx + 1}`}
                  </Text>
                </View>
                {metricas.length > 1 ? (
                  <TouchableOpacity onPress={() => removerMetrica(idx)} style={{ padding: 4 }}>
                    <Feather name="trash-2" size={16} color="#ef4444" />
                  </TouchableOpacity>
                ) : null}
              </View>

              {/* Código + Nome */}
              <View style={{ flexDirection: isMobile ? 'column' : 'row', gap: 10, marginBottom: 10 }}>
                <View style={{ flex: 1 }}>
                  <Text style={{ fontSize: 11, fontWeight: '600', color: '#64748b', marginBottom: 2 }}>Código *</Text>
                  <TextInput
                    placeholder="peso_kg, tempo_ms, repeticoes"
                    value={metrica.codigo}
                    onChangeText={(val) => atualizarMetrica(idx, 'codigo', val.replace(/[^a-z0-9_]/gi, '').toLowerCase())}
                    style={{
                      borderWidth: 1,
                      borderColor: errors[`metrica_${idx}_codigo`] ? '#ef4444' : '#e2e8f0',
                      borderRadius: 8,
                      paddingHorizontal: 10,
                      paddingVertical: 8,
                      fontSize: 13,
                      backgroundColor: '#fff',
                      color: '#0f172a',
                    }}
                  />
                  {errors[`metrica_${idx}_codigo`] ? (
                    <Text style={{ color: '#ef4444', fontSize: 10, marginTop: 2 }}>{errors[`metrica_${idx}_codigo`]}</Text>
                  ) : null}
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={{ fontSize: 11, fontWeight: '600', color: '#64748b', marginBottom: 2 }}>Nome *</Text>
                  <TextInput
                    placeholder="Carga, Tempo, Repetições"
                    value={metrica.nome}
                    onChangeText={(val) => atualizarMetrica(idx, 'nome', val)}
                    style={{
                      borderWidth: 1,
                      borderColor: errors[`metrica_${idx}_nome`] ? '#ef4444' : '#e2e8f0',
                      borderRadius: 8,
                      paddingHorizontal: 10,
                      paddingVertical: 8,
                      fontSize: 13,
                      backgroundColor: '#fff',
                      color: '#0f172a',
                    }}
                  />
                  {errors[`metrica_${idx}_nome`] ? (
                    <Text style={{ color: '#ef4444', fontSize: 10, marginTop: 2 }}>{errors[`metrica_${idx}_nome`]}</Text>
                  ) : null}
                </View>
              </View>

              {/* Tipo valor + Unidade */}
              <View style={{ flexDirection: isMobile ? 'column' : 'row', gap: 10, marginBottom: 10 }}>
                <View style={{ flex: 1 }}>
                  <Text style={{ fontSize: 11, fontWeight: '600', color: '#64748b', marginBottom: 2 }}>Tipo de valor</Text>
                  <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 4 }}>
                    {TIPOS_VALOR.map((tv) => (
                      <TouchableOpacity
                        key={tv.value}
                        onPress={() => atualizarMetrica(idx, 'tipo_valor', tv.value)}
                        style={{
                          paddingHorizontal: 10,
                          paddingVertical: 6,
                          borderRadius: 6,
                          backgroundColor: metrica.tipo_valor === tv.value ? '#1e293b' : '#fff',
                          borderWidth: 1,
                          borderColor: metrica.tipo_valor === tv.value ? '#1e293b' : '#e2e8f0',
                        }}
                      >
                        <Text style={{ fontSize: 11, fontWeight: '600', color: metrica.tipo_valor === tv.value ? '#fff' : '#64748b' }}>
                          {tv.label}
                        </Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={{ fontSize: 11, fontWeight: '600', color: '#64748b', marginBottom: 2 }}>Unidade</Text>
                  <TextInput
                    placeholder="kg, ms, reps, m"
                    value={metrica.unidade}
                    onChangeText={(val) => atualizarMetrica(idx, 'unidade', val)}
                    style={{
                      borderWidth: 1,
                      borderColor: '#e2e8f0',
                      borderRadius: 8,
                      paddingHorizontal: 10,
                      paddingVertical: 8,
                      fontSize: 13,
                      backgroundColor: '#fff',
                      color: '#0f172a',
                    }}
                  />
                </View>
              </View>

              {/* Direção + Obrigatória */}
              <View style={{ flexDirection: 'row', gap: 10, alignItems: 'center' }}>
                <View style={{ flex: 1 }}>
                  <Text style={{ fontSize: 11, fontWeight: '600', color: '#64748b', marginBottom: 2 }}>Direção *</Text>
                  <View style={{ flexDirection: 'row', gap: 4 }}>
                    {DIRECOES.map((d) => (
                      <TouchableOpacity
                        key={d.value}
                        onPress={() => atualizarMetrica(idx, 'direcao', d.value)}
                        style={{
                          flex: 1,
                          paddingVertical: 8,
                          borderRadius: 6,
                          backgroundColor: metrica.direcao === d.value ? (d.value === 'maior_melhor' ? '#16a34a' : '#2563eb') : '#fff',
                          borderWidth: 1,
                          borderColor: metrica.direcao === d.value ? 'transparent' : '#e2e8f0',
                          alignItems: 'center',
                        }}
                      >
                        <Text style={{ fontSize: 11, fontWeight: '600', color: metrica.direcao === d.value ? '#fff' : '#64748b' }}>
                          {d.label}
                        </Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>
                <View style={{ alignItems: 'center', paddingTop: 16 }}>
                  <Text style={{ fontSize: 10, color: '#64748b', marginBottom: 2 }}>Obrigatória</Text>
                  <Switch
                    value={!!metrica.obrigatoria}
                    onValueChange={(val) => atualizarMetrica(idx, 'obrigatoria', val ? 1 : 0)}
                    trackColor={{ false: '#cbd5e1', true: '#f97316' }}
                    thumbColor="#fff"
                  />
                </View>
              </View>
            </View>
          ))}
        </View>

        {/* Botões */}
        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 30 }}>
          <TouchableOpacity
            onPress={() => router.push('/recordes/definicoes')}
            style={{
              flex: 1,
              paddingVertical: 14,
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
            onPress={handleSubmit}
            disabled={saving}
            style={{
              flex: 2,
              paddingVertical: 14,
              borderRadius: 10,
              backgroundColor: saving ? '#fdba74' : '#f97316',
              alignItems: 'center',
              flexDirection: 'row',
              justifyContent: 'center',
              gap: 6,
            }}
          >
            {saving ? <ActivityIndicator size="small" color="#fff" /> : <Feather name="save" size={16} color="#fff" />}
            <Text style={{ fontSize: 14, fontWeight: '700', color: '#fff' }}>
              {saving ? 'Salvando...' : isEdit ? 'Atualizar' : 'Criar Definição'}
            </Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}
