import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  ScrollView,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import SearchableDropdown from '../../components/SearchableDropdown';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError, showWarning } from '../../utils/toast';
import { associarPlano, buscarContratoAtivo } from '../../services/contratoService';
import planosSistemaService from '../../services/planosSistemaService';
import api from '../../services/api';
import { authService } from '../../services/authService';

export default function NovoContratoScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    checkAccess();
  }, []);

  const checkAccess = async () => {
    const user = await authService.getCurrentUser();
    if (!user || user.papel_id !== 4) {
      showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
      router.replace('/');
      return;
    }
  };
  const [planos, setPlanos] = useState([]);
  const [academias, setAcademias] = useState([]);
  const [formasPagamento, setFormasPagamento] = useState([]);
  const [contratoAtivo, setContratoAtivo] = useState(null);
  const [errors, setErrors] = useState({});
  const [showConfirmTrocar, setShowConfirmTrocar] = useState(false);
  
  const [formData, setFormData] = useState({
    academia_id: '',
    plano_sistema_id: '',
    forma_pagamento_id: '',
    data_inicio: '',
    data_vencimento: '',
    observacoes: '',
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      
      // Buscar planos disponíveis (apenas ativos e atuais) via API
      const responsePlanos = await planosSistemaService.listar(true, true); // ativos=true, apenas_atuais=true
      setPlanos(responsePlanos.planos || []);

      // Buscar academias sem contrato ativo via API
      const responseAcademias = await api.get('/superadmin/academias?sem_contrato_ativo=true');
      setAcademias(responseAcademias.data.academias || []);

      // Buscar formas de pagamento via API
      const responseFormasPagamento = await api.get('/formas-pagamento');
      setFormasPagamento(responseFormasPagamento.data.formas || []);
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
      showError('Erro ao carregar dados');
    } finally {
      setLoading(false);
    }
  };

  const checkContratoAtivo = async (academiaId) => {
    try {
      const responseContrato = await buscarContratoAtivo(academiaId);
      if (responseContrato.success) {
        if (responseContrato.data) {
          // Tem contrato ativo
          setContratoAtivo(responseContrato.data);
          showWarning(`Academia possui contrato ativo com ${responseContrato.data.plano_nome}`);
        } else {
          // Não tem contrato ativo - exibir mensagem do backend se houver
          setContratoAtivo(null);
          if (responseContrato.message) {
            if (responseContrato.type === 'warning') {
              showWarning(responseContrato.message);
            } else if (responseContrato.type === 'error') {
              showError(responseContrato.message);
            } else {
              showSuccess(responseContrato.message);
            }
          }
        }
      } else {
        // Erro real na API
        showError(responseContrato.error || 'Erro ao verificar contrato ativo');
        setContratoAtivo(null);
      }
    } catch (error) {
      console.error('Erro ao verificar contrato ativo:', error);
      setContratoAtivo(null);
    }
  };

  const handleChange = (field, value) => {
    setFormData({ ...formData, [field]: value });
    if (errors[field]) {
      setErrors({ ...errors, [field]: null });
    }

    // Verificar contrato ativo quando selecionar academia
    if (field === 'academia_id' && value) {
      checkContratoAtivo(value);
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.academia_id) {
      newErrors.academia_id = 'Selecione uma academia';
    }

    if (!formData.plano_sistema_id) {
      newErrors.plano_sistema_id = 'Selecione um plano';
    }

    if (!formData.forma_pagamento_id) {
      newErrors.forma_pagamento_id = 'Selecione a forma de pagamento';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    if (contratoAtivo) {
      showWarning('Esta academia já possui um contrato ativo. Use "Trocar Plano" para mudar.');
      return;
    }

    setSaving(true);

    try {
      const dataToSend = {
        plano_sistema_id: parseInt(formData.plano_sistema_id),
        forma_pagamento_id: parseInt(formData.forma_pagamento_id),
        data_inicio: formData.data_inicio || undefined,
        data_vencimento: formData.data_vencimento || undefined,
        observacoes: formData.observacoes || null,
      };

      const result = await associarPlano(formData.academia_id, dataToSend);
      
      if (result.success) {
        const message = result.data?.message || 'Contrato criado com sucesso';
        showSuccess(message);
        router.push('/contratos');
      } else {
        if (result.contratoAtivo) {
          showError(`${result.message} - Plano: ${result.contratoAtivo.plano}`);
          setContratoAtivo(result.contratoAtivo);
        } else {
          showError(result.message || 'Erro ao criar contrato');
        }
      }
    } catch (error) {
      showError('Não foi possível criar o contrato');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#f97316" />
        <Text style={styles.loadingText}>Carregando...</Text>
      </View>
    );
  }

  return (
    <LayoutBase 
      title="Novo Contrato" 
      subtitle="Associar plano do sistema a uma academia"
    >
      {saving && <LoadingOverlay message="Criando contrato..." />}

      <ScrollView style={styles.scrollView} showsVerticalScrollIndicator={false}>
        <View style={styles.container}>
          {contratoAtivo && (
            <View style={styles.alertBox}>
              <Feather name="alert-circle" size={20} color="#f97316" />
              <View style={{ flex: 1, marginLeft: 10 }}>
                <Text style={styles.alertTitle}>Contrato Ativo Encontrado</Text>
                <Text style={styles.alertText}>
                  Esta academia já possui um contrato ativo com o plano "{contratoAtivo.plano_nome}".
                  Para criar um novo contrato, use a opção "Trocar Plano".
                </Text>
              </View>
            </View>
          )}

          <View style={styles.form}>
            <View style={styles.inputGroup}>
              <Text style={styles.label}>Academia / Tenant *</Text>
              <SearchableDropdown
                data={academias}
                value={formData.academia_id}
                onChange={(value) => handleChange('academia_id', value)}
                placeholder="Selecione uma academia"
                searchPlaceholder="Buscar por nome ou CNPJ..."
                labelKey="nome"
                valueKey="id"
                subtextKey="cnpj"
                disabled={saving}
                error={!!errors.academia_id}
              />
              {errors.academia_id && <Text style={styles.errorText}>{errors.academia_id}</Text>}
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Plano do Sistema *</Text>
              <View style={[styles.pickerContainer, errors.plano_sistema_id && styles.inputError]}>
                <select
                  style={styles.picker}
                  value={formData.plano_sistema_id}
                  onChange={(e) => handleChange('plano_sistema_id', e.target.value)}
                  disabled={saving || !!contratoAtivo}
                >
                  <option value="">Selecione um plano</option>
                  {planos.map((plano) => (
                    <option key={plano.id} value={plano.id}>
                      {plano.nome} - R$ {parseFloat(plano.valor).toFixed(2)} / {plano.duracao_dias} dias
                    </option>
                  ))}
                </select>
              </View>
              {errors.plano_sistema_id && <Text style={styles.errorText}>{errors.plano_sistema_id}</Text>}
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Forma de Pagamento *</Text>
              <View style={[styles.pickerContainer, errors.forma_pagamento_id && styles.inputError]}>
                <select
                  style={styles.picker}
                  value={formData.forma_pagamento_id}
                  onChange={(e) => handleChange('forma_pagamento_id', e.target.value)}
                  disabled={saving || !!contratoAtivo}
                >
                  <option value="">Selecione uma forma de pagamento</option>
                  {formasPagamento.map((forma) => (
                    <option key={forma.id} value={forma.id}>
                      {forma.nome}
                    </option>
                  ))}
                </select>
              </View>
              {errors.forma_pagamento_id && <Text style={styles.errorText}>{errors.forma_pagamento_id}</Text>}
            </View>

            <View style={styles.row}>
              <View style={[styles.inputGroup, { flex: 1, marginRight: 12 }]}>
                <Text style={styles.label}>Data de Início (opcional)</Text>
                <TextInput
                  style={styles.input}
                  placeholder="AAAA-MM-DD"
                  value={formData.data_inicio}
                  onChangeText={(value) => handleChange('data_inicio', value)}
                  editable={!saving && !contratoAtivo}
                />
                <Text style={styles.helpText}>Deixe vazio para usar a data atual</Text>
              </View>

              <View style={[styles.inputGroup, { flex: 1 }]}>
                <Text style={styles.label}>Data de Vencimento (opcional)</Text>
                <TextInput
                  style={styles.input}
                  placeholder="AAAA-MM-DD"
                  value={formData.data_vencimento}
                  onChangeText={(value) => handleChange('data_vencimento', value)}
                  editable={!saving && !contratoAtivo}
                />
                <Text style={styles.helpText}>Calculado automaticamente se vazio</Text>
              </View>
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Observações</Text>
              <TextInput
                style={[styles.input, styles.textArea]}
                placeholder="Observações sobre o contrato..."
                value={formData.observacoes}
                onChangeText={(value) => handleChange('observacoes', value)}
                multiline
                numberOfLines={3}
                editable={!saving && !contratoAtivo}
              />
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
                style={[styles.submitButton, (saving || contratoAtivo) && styles.submitButtonDisabled]}
                onPress={handleSubmit}
                disabled={saving || !!contratoAtivo}
                activeOpacity={0.7}
              >
                {saving ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.submitButtonText}>Criar Contrato</Text>
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
  alertBox: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: '#fef3c7',
    borderLeftWidth: 4,
    borderLeftColor: '#f97316',
    padding: 16,
    marginHorizontal: 20,
    marginTop: 10,
    marginBottom: 20,
    borderRadius: 8,
  },
  alertTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2b1a04',
    marginBottom: 5,
  },
  alertText: {
    fontSize: 13,
    color: '#666',
    lineHeight: 18,
  },
  form: {
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: 12,
    padding: 20,
    marginHorizontal: 20,
    marginBottom: 20,
  },
  row: {
    flexDirection: 'row',
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
  helpText: {
    color: '#6b7280',
    fontSize: 12,
    marginTop: 4,
    marginLeft: 4,
  },
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 10,
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
    backgroundColor: '#f97316',
    padding: 16,
    borderRadius: 10,
    alignItems: 'center',
  },
  submitButtonDisabled: {
    backgroundColor: '#fbbf24',
    opacity: 0.6,
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
