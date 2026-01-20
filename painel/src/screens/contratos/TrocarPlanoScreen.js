import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
  Alert,
} from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';
import { trocarPlano, buscarContratoAtivo } from '../../services/contratoService';
import planosSistemaService from '../../services/planosSistemaService';
import { authService } from '../../services/authService';

export default function TrocarPlanoScreen() {
  const router = useRouter();
  const { academiaId, academiaNome } = useLocalSearchParams();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [planos, setPlanos] = useState([]);

  useEffect(() => {
    checkAccess();
  }, []);

  const checkAccess = async () => {
    const user = await authService.getCurrentUser();
    if (!user || user.role_id !== 3) {
      showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
      router.replace('/');
      return;
    }
  };
  const [contratoAtual, setContratoAtual] = useState(null);
  const [errors, setErrors] = useState({});
  
  const [formData, setFormData] = useState({
    plano_sistema_id: '',
    forma_pagamento: 'pix',
    observacoes: '',
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      
      // Buscar planos disponíveis
      const responsePlanos = await planosSistemaService.listar(true);
      setPlanos(responsePlanos.planos || []);

      // Buscar contrato atual
      const responseContrato = await buscarContratoAtivo(academiaId);
      if (responseContrato.success && responseContrato.data) {
        setContratoAtual(responseContrato.data);
        setFormData(prev => ({
          ...prev,
          forma_pagamento: responseContrato.data.forma_pagamento || 'pix'
        }));
      } else {
        Alert.alert(
          'Sem Contrato Ativo',
          'Esta academia não possui um contrato ativo para trocar.',
          [
            {
              text: 'Criar Novo',
              onPress: () => router.replace(`/contratos/novo?academiaId=${academiaId}&academiaNome=${academiaNome}`)
            },
            { text: 'Voltar', onPress: () => router.back() }
          ]
        );
      }
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
      showError('Erro ao carregar dados');
      router.back();
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (field, value) => {
    setFormData({ ...formData, [field]: value });
    if (errors[field]) {
      setErrors({ ...errors, [field]: null });
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.plano_sistema_id) {
      newErrors.plano_sistema_id = 'Selecione um plano';
    }

    if (contratoAtual && parseInt(formData.plano_sistema_id) === contratoAtual.plano_sistema_id) {
      newErrors.plano_sistema_id = 'Selecione um plano diferente do atual';
    }

    if (!formData.forma_pagamento) {
      newErrors.forma_pagamento = 'Selecione a forma de pagamento';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    Alert.alert(
      'Confirmar Troca de Plano',
      `Deseja trocar do plano "${contratoAtual.plano_nome}" para o novo plano?\n\nO contrato atual será desativado e um novo será criado.`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Confirmar',
          style: 'destructive',
          onPress: executarTroca
        }
      ]
    );
  };

  const executarTroca = async () => {
    setSaving(true);

    try {
      const dataToSend = {
        plano_sistema_id: parseInt(formData.plano_sistema_id),
        forma_pagamento: formData.forma_pagamento,
        observacoes: formData.observacoes || null,
      };

      const result = await trocarPlano(academiaId, dataToSend);
      
      if (result.success) {
        showSuccess(result.data.message || 'Plano trocado com sucesso');
        router.back();
      } else {
        showError(result.error);
      }
    } catch (error) {
      showError('Não foi possível trocar o plano');
    } finally {
      setSaving(false);
    }
  };

  const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('pt-BR');
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#f97316" />
        <Text style={styles.loadingText}>Carregando...</Text>
      </View>
    );
  }

  if (!contratoAtual) {
    return null;
  }

  return (
    <LayoutBase 
      title="Trocar Plano" 
      subtitle={`Alterar plano da academia: ${academiaNome}`}
    >
      {saving && <LoadingOverlay message="Trocando plano..." />}

      <ScrollView style={styles.scrollView} showsVerticalScrollIndicator={false}>
        <View style={styles.container}>
          {/* Contrato Atual */}
          <View style={styles.currentContractBox}>
            <View style={styles.boxHeader}>
              <Feather name="file-text" size={20} color="#f97316" />
              <Text style={styles.boxTitle}>Contrato Atual</Text>
            </View>
            <View style={styles.boxContent}>
              <View style={styles.infoRow}>
                <Text style={styles.infoLabel}>Plano:</Text>
                <Text style={styles.infoValue}>{contratoAtual.plano_nome}</Text>
              </View>
              <View style={styles.infoRow}>
                <Text style={styles.infoLabel}>Valor:</Text>
                <Text style={styles.infoValue}>{formatCurrency(contratoAtual.valor)}</Text>
              </View>
              <View style={styles.infoRow}>
                <Text style={styles.infoLabel}>Vencimento:</Text>
                <Text style={styles.infoValue}>{formatDate(contratoAtual.data_vencimento)}</Text>
              </View>
              <View style={styles.infoRow}>
                <Text style={styles.infoLabel}>Forma de Pagamento:</Text>
                <Text style={styles.infoValue}>
                  {contratoAtual.forma_pagamento === 'cartao' ? 'Cartão' : 
                   contratoAtual.forma_pagamento === 'pix' ? 'PIX' : 'Operadora'}
                </Text>
              </View>
            </View>
          </View>

          {/* Formulário de Novo Plano */}
          <View style={styles.form}>
            <View style={styles.formHeader}>
              <Feather name="refresh-cw" size={20} color="#10b981" />
              <Text style={styles.formTitle}>Novo Plano</Text>
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Plano do Sistema *</Text>
              <View style={[styles.pickerContainer, errors.plano_sistema_id && styles.inputError]}>
                <select
                  style={styles.picker}
                  value={formData.plano_sistema_id}
                  onChange={(e) => handleChange('plano_sistema_id', e.target.value)}
                  disabled={saving}
                >
                  <option value="">Selecione um plano</option>
                  {planos.map((plano) => (
                    <option 
                      key={plano.id} 
                      value={plano.id}
                      disabled={plano.id === contratoAtual.plano_sistema_id}
                    >
                      {plano.nome} - {formatCurrency(plano.valor)} / {plano.duracao_dias} dias
                      {plano.id === contratoAtual.plano_sistema_id ? ' (atual)' : ''}
                    </option>
                  ))}
                </select>
              </View>
              {errors.plano_sistema_id && <Text style={styles.errorText}>{errors.plano_sistema_id}</Text>}
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Forma de Pagamento *</Text>
              <View style={[styles.pickerContainer, errors.forma_pagamento && styles.inputError]}>
                <select
                  style={styles.picker}
                  value={formData.forma_pagamento}
                  onChange={(e) => handleChange('forma_pagamento', e.target.value)}
                  disabled={saving}
                >
                  <option value="pix">PIX</option>
                  <option value="cartao">Cartão de Crédito</option>
                  <option value="operadora">Operadora</option>
                </select>
              </View>
              {errors.forma_pagamento && <Text style={styles.errorText}>{errors.forma_pagamento}</Text>}
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Observações</Text>
              <TextInput
                style={[styles.input, styles.textArea]}
                placeholder="Motivo da troca, observações..."
                value={formData.observacoes}
                onChangeText={(value) => handleChange('observacoes', value)}
                multiline
                numberOfLines={3}
                editable={!saving}
              />
            </View>

            <View style={styles.warningBox}>
              <Feather name="alert-triangle" size={16} color="#f59e0b" />
              <Text style={styles.warningText}>
                O contrato atual será desativado e um novo contrato será criado com o plano selecionado.
              </Text>
            </View>

            <View style={styles.buttonRow}>
              <TouchableOpacity
                style={styles.cancelButton}
                onPress={() => router.back()}
                disabled={saving}
              >
                <Text style={styles.cancelButtonText}>Cancelar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.submitButton, saving && styles.submitButtonDisabled]}
                onPress={handleSubmit}
                disabled={saving}
                activeOpacity={0.7}
              >
                {saving ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.submitButtonText}>Trocar Plano</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  scrollView: {
    flex: 1,
  },
  container: {
    flex: 1,
    paddingBottom: 40,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'transparent',
    paddingVertical: 100,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  currentContractBox: {
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: 12,
    padding: 20,
    marginHorizontal: 20,
    marginTop: 10,
    marginBottom: 20,
    borderWidth: 2,
    borderColor: 'rgba(249, 115, 22, 0.3)',
  },
  boxHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 15,
    paddingBottom: 10,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(43,26,4,0.1)',
  },
  boxTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2b1a04',
    marginLeft: 10,
  },
  boxContent: {
    gap: 10,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  infoLabel: {
    fontSize: 14,
    color: '#666',
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2b1a04',
  },
  form: {
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: 12,
    padding: 20,
    marginHorizontal: 20,
    marginBottom: 20,
  },
  formHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 20,
    paddingBottom: 10,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(43,26,4,0.1)',
  },
  formTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2b1a04',
    marginLeft: 10,
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2b1a04',
    marginBottom: 8,
  },
  input: {
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.2)',
    borderRadius: 10,
    padding: 12,
    fontSize: 16,
    color: '#2b1a04',
  },
  pickerContainer: {
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.2)',
    borderRadius: 10,
    overflow: 'hidden',
  },
  picker: {
    padding: 12,
    fontSize: 16,
    color: '#2b1a04',
    border: 'none',
    backgroundColor: 'transparent',
    width: '100%',
    cursor: 'pointer',
  },
  inputError: {
    borderColor: '#ef4444',
    borderWidth: 2,
  },
  errorText: {
    color: '#ef4444',
    fontSize: 12,
    marginTop: 4,
    marginLeft: 4,
  },
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  warningBox: {
    flexDirection: 'row',
    backgroundColor: 'rgba(245, 158, 11, 0.1)',
    borderLeftWidth: 4,
    borderLeftColor: '#f59e0b',
    padding: 12,
    marginBottom: 20,
    borderRadius: 8,
    gap: 10,
  },
  warningText: {
    flex: 1,
    fontSize: 13,
    color: '#92400e',
    lineHeight: 18,
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
  },
  cancelButton: {
    flex: 1,
    backgroundColor: '#e5e7eb',
    padding: 16,
    borderRadius: 10,
    alignItems: 'center',
  },
  cancelButtonText: {
    color: '#374151',
    fontSize: 16,
    fontWeight: 'bold',
  },
  submitButton: {
    flex: 1,
    backgroundColor: '#10b981',
    padding: 16,
    borderRadius: 10,
    alignItems: 'center',
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
