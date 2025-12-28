import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Switch,
} from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import planoService from '../../services/planoService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';

export default function EditarPlanoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const planoId = parseInt(id);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [formData, setFormData] = useState({
    nome: '',
    descricao: '',
    valor: '',
    duracao_dias: '30',
    max_alunos: '',
    ativo: true,
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      const plano = await planoService.buscar(planoId);

      setFormData({
        nome: plano.nome || '',
        descricao: plano.descricao || '',
        valor: plano.valor ? plano.valor.toString() : '',
        duracao_dias: plano.duracao_dias ? plano.duracao_dias.toString() : '30',
        max_alunos: plano.max_alunos ? plano.max_alunos.toString() : '',
        ativo: plano.ativo === 1 || plano.ativo === true,
      });
    } catch (error) {
      showError('Não foi possível carregar os dados do plano');
      router.back();
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (field, value) => {
    console.log(`📝 Alterando ${field}:`, value);
    setFormData({ ...formData, [field]: value });
    // Limpar erro do campo ao digitar
    if (errors[field]) {
      setErrors({ ...errors, [field]: null });
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.nome.trim()) {
      newErrors.nome = 'Nome do plano é obrigatório';
    }

    if (!formData.valor || parseFloat(formData.valor) < 0) {
      newErrors.valor = 'Valor deve ser maior ou igual a zero';
    }

    if (!formData.max_alunos || parseInt(formData.max_alunos) < 1) {
      newErrors.max_alunos = 'Capacidade deve ser maior que zero';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    setSaving(true);

    try {
      const dataToSend = {
        ...formData,
        valor: parseFloat(formData.valor),
        duracao_dias: parseInt(formData.duracao_dias),
        max_alunos: formData.max_alunos ? parseInt(formData.max_alunos) : null,
      };

      const result = await planoService.atualizar(planoId, dataToSend);
      showSuccess(result.message || 'Plano atualizado com sucesso');
      router.push('/planos');
    } catch (error) {
      showError(error.errors?.join('\n') || error.error || 'Não foi possível atualizar o plano');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#007AFF" />
        <Text style={styles.loadingText}>Carregando...</Text>
      </View>
    );
  }

  return (
    <LayoutBase title="Editar Plano" subtitle="Atualizar informações do plano">
      {saving && <LoadingOverlay message="Atualizando plano..." />}

      <View style={styles.container}>
        {/* Form */}
        <View style={styles.form}>
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Nome do Plano *</Text>
            <TextInput
              style={[styles.input, errors.nome && styles.inputError]}
              placeholder="Ex: Plano Básico"
              value={formData.nome}
              onChangeText={(value) => handleChange('nome', value)}
              editable={!saving}
            />
            {errors.nome && <Text style={styles.errorText}>{errors.nome}</Text>}
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Descrição</Text>
            <TextInput
              style={[styles.input, styles.textArea]}
              placeholder="Descrição do plano"
              value={formData.descricao}
              onChangeText={(value) => handleChange('descricao', value)}
              multiline
              numberOfLines={3}
              editable={!saving}
            />
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, { flex: 1, marginRight: 12 }]}>
              <Text style={styles.label}>Valor Mensal (R$) *</Text>
              <TextInput
                style={[styles.input, errors.valor && styles.inputError]}
                placeholder="199.90"
                value={formData.valor}
                onChangeText={(value) => handleChange('valor', value)}
                keyboardType="decimal-pad"
                editable={!saving}
              />
              {errors.valor && <Text style={styles.errorText}>{errors.valor}</Text>}
            </View>

            <View style={[styles.inputGroup, { flex: 1 }]}>
              <Text style={styles.label}>Capacidade de Alunos *</Text>
              <TextInput
                style={[styles.input, errors.max_alunos && styles.inputError]}
                placeholder="Ex: 50, 100, 200"
                value={formData.max_alunos}
                onChangeText={(value) => handleChange('max_alunos', value)}
                keyboardType="number-pad"
                editable={!saving}
              />
              {errors.max_alunos && <Text style={styles.errorText}>{errors.max_alunos}</Text>}
            </View>
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Recursos Inclusos</Text>
            <TextInput
              style={[styles.input, styles.textArea]}
              placeholder="Ex: Gestão de turmas, check-in automático, relatórios"
              value={formData.descricao}
              onChangeText={(value) => handleChange('descricao', value)}
              multiline
              numberOfLines={3}
              editable={!saving}
            />
          </View>

          <View style={styles.inputGroup}>
            <View style={styles.switchRow}>
              <View>
                <Text style={styles.label}>Status</Text>
                <Text style={styles.switchSubtext}>
                  {formData.ativo ? 'Plano ativo e disponível' : 'Plano inativo'}
                </Text>
              </View>
              <Switch
                value={formData.ativo}
                onValueChange={(value) => handleChange('ativo', value)}
                disabled={saving}
                trackColor={{ false: '#d1d5db', true: '#10b981' }}
                thumbColor={formData.ativo ? '#fff' : '#f3f4f6'}
              />
            </View>
          </View>

          {/* Submit Button */}
          <TouchableOpacity
            style={[styles.submitButton, saving && styles.submitButtonDisabled]}
            onPress={handleSubmit}
            disabled={saving}
            activeOpacity={0.7}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.submitButtonText}>Salvar Alterações</Text>
            )}
          </TouchableOpacity>
        </View>
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
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
  form: {
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: 12,
    padding: 20,
    marginHorizontal: 20,
    marginVertical: 10,
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
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  switchSubtext: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 4,
  },
  submitButton: {
    backgroundColor: '#f97316',
    padding: 16,
    borderRadius: 10,
    alignItems: 'center',
    marginTop: 10,
    marginBottom: 20,
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
