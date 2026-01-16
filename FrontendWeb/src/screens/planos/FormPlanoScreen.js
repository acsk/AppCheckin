import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Switch,
  ScrollView,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { useRouter, useLocalSearchParams } from 'expo-router';
import planoService from '../../services/planoService';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';
import { colors } from '../../styles/globalStyles';

export default function FormPlanoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const isEdit = !!id;
  const planoId = id ? parseInt(id) : null;

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [modalidades, setModalidades] = useState([]);
  const [formData, setFormData] = useState({
    modalidade_id: '',
    nome: '',
    descricao: '',
    valor: '',
    checkins_semanais: '',
    duracao_dias: '30',
    ativo: true,
    atual: true,
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      
      // Buscar modalidades ativas
      const modalidadesResponse = await modalidadeService.listar(true);
      const modalidadesArray = Array.isArray(modalidadesResponse) ? modalidadesResponse : [];
      setModalidades(modalidadesArray);

      // Se for edição, buscar dados do plano
      if (isEdit) {
        console.log('🔍 Buscando plano ID:', planoId);
        const plano = await planoService.buscar(planoId);
        console.log('✅ Plano recebido:', plano);
        
        setFormData({
          modalidade_id: plano.modalidade_id?.toString() || '',
          nome: plano.nome || '',
          descricao: plano.descricao || '',
          valor: plano.valor ? plano.valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '',
          checkins_semanais: plano.checkins_semanais?.toString() || '',
          duracao_dias: plano.duracao_dias ? plano.duracao_dias.toString() : '30',
          ativo: plano.ativo === 1 || plano.ativo === true,
          atual: plano.atual === 1 || plano.atual === true,
        });
      }
    } catch (error) {
      console.error('❌ Erro ao carregar dados:', error);
      showError(isEdit ? 'Não foi possível carregar os dados do plano' : 'Não foi possível carregar as modalidades');
      setModalidades([]); // Garante que sempre seja array
      if (isEdit) {
        router.back();
      }
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

  const formatValorMonetario = (value) => {
    // Remove tudo que não é dígito
    const cleaned = value.replace(/\D/g, '');
    
    if (cleaned === '') return '';
    
    // Converte para número e formata
    const number = parseFloat(cleaned) / 100;
    return number.toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.modalidade_id) {
      newErrors.modalidade_id = 'Selecione uma modalidade';
    }

    if (!formData.nome.trim()) {
      newErrors.nome = 'Nome do plano é obrigatório';
    }

    if (!formData.valor || parseFloat(formData.valor) < 0) {
      newErrors.valor = 'Valor deve ser maior ou igual a zero';
    }

    if (!formData.checkins_semanais || parseInt(formData.checkins_semanais) < 1) {
      newErrors.checkins_semanais = 'Informe os checkins semanais (mínimo 1)';
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
        modalidade_id: parseInt(formData.modalidade_id),
        nome: formData.nome,
        descricao: formData.descricao,
        valor: parseFloat(formData.valor.replace(/\./g, '').replace(',', '.')),
        checkins_semanais: parseInt(formData.checkins_semanais),
        duracao_dias: parseInt(formData.duracao_dias),
        ativo: formData.ativo ? 1 : 0,
        atual: formData.atual ? 1 : 0,
      };

      let result;
      if (isEdit) {
        result = await planoService.atualizar(planoId, dataToSend);
        showSuccess(result.message || 'Plano atualizado com sucesso');
      } else {
        result = await planoService.criar(dataToSend);
        showSuccess(result.message || 'Plano criado com sucesso');
      }
      
      router.push('/planos');
    } catch (error) {
      showError(error.errors?.join('\n') || error.error || `Não foi possível ${isEdit ? 'atualizar' : 'criar'} o plano`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <LayoutBase title={isEdit ? "Editar Plano" : "Novo Plano"}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase 
      title={isEdit ? "Editar Plano" : "Novo Plano"} 
      subtitle="Preencha os campos obrigatórios"
    >
      {saving && <LoadingOverlay message={isEdit ? "Atualizando plano..." : "Criando plano..."} />}

      <ScrollView style={styles.scrollView}>
        <View style={styles.headerActions}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
            disabled={saving}
          >
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>

          <View style={[styles.statusIndicator, formData.ativo ? styles.statusActive : styles.statusInactive]}>
            <View style={[styles.statusDot, formData.ativo ? styles.statusDotActive : styles.statusDotInactive]} />
            <Text style={[styles.statusIndicatorText, formData.ativo ? styles.statusTextActive : styles.statusTextInactive]}>
              {formData.ativo ? 'Plano Ativo' : 'Plano Inativo'}
            </Text>
          </View>
        </View>

        <View style={styles.formContainer}>
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="clipboard" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Dados do Plano</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.inputGroup}>
                <Text style={styles.label}>Modalidade <Text style={styles.required}>*</Text></Text>
                <View style={[styles.pickerContainer, errors.modalidade_id && styles.inputError]}>
                  <Picker
                    selectedValue={formData.modalidade_id}
                    onValueChange={(value) => handleChange('modalidade_id', value)}
                    enabled={!saving}
                    style={styles.picker}
                  >
                    <Picker.Item label="Selecione uma modalidade" value="" />
                    {modalidades.map((mod) => (
                      <Picker.Item key={mod.id} label={mod.nome} value={mod.id.toString()} />
                    ))}
                  </Picker>
                </View>
                {errors.modalidade_id && <Text style={styles.errorText}>{errors.modalidade_id}</Text>}
              </View>

              <View style={styles.inputGroup}>
                <Text style={styles.label}>Nome do Plano <Text style={styles.required}>*</Text></Text>
                <TextInput
                  style={[styles.input, errors.nome && styles.inputError]}
                  placeholder="Ex: 2x por semana"
                  placeholderTextColor={colors.placeholder}
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
                  placeholderTextColor={colors.placeholder}
                  value={formData.descricao}
                  onChangeText={(value) => handleChange('descricao', value)}
                  multiline
                  numberOfLines={3}
                  editable={!saving}
                />
              </View>
            </View>
          </View>

          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="dollar-sign" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Condições do Plano</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.row}>
                <View style={[styles.inputGroup, styles.flex1, styles.marginRight]}>
                  <Text style={styles.label}>Valor Mensal <Text style={styles.required}>*</Text></Text>
                  <View style={styles.inputWithPrefix}>
                    <Text style={styles.prefix}>R$</Text>
                    <TextInput
                      style={[styles.input, styles.inputWithPrefixField, errors.valor && styles.inputError]}
                      placeholder="0,00"
                      placeholderTextColor={colors.placeholder}
                      value={formData.valor}
                      onChangeText={(value) => {
                        const formatted = formatValorMonetario(value);
                        handleChange('valor', formatted);
                      }}
                      keyboardType="numeric"
                      editable={!saving}
                    />
                  </View>
                  {errors.valor && <Text style={styles.errorText}>{errors.valor}</Text>}
                </View>

                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>Checkins/Semana <Text style={styles.required}>*</Text></Text>
                  <TextInput
                    style={[styles.input, errors.checkins_semanais && styles.inputError]}
                    placeholder="Ex: 2, 3, 4"
                    placeholderTextColor={colors.placeholder}
                    value={formData.checkins_semanais}
                    onChangeText={(value) => handleChange('checkins_semanais', value)}
                    keyboardType="number-pad"
                    editable={!saving}
                  />
                  {errors.checkins_semanais && <Text style={styles.errorText}>{errors.checkins_semanais}</Text>}
                </View>
              </View>
              <Text style={styles.helperText}>Use 999 para checkins ilimitados</Text>

              <View style={styles.inputGroup}>
                <Text style={styles.label}>Duração do Plano</Text>
                <View style={styles.pickerContainer}>
                  <Picker
                    selectedValue={formData.duracao_dias}
                    onValueChange={(value) => handleChange('duracao_dias', value)}
                    enabled={!saving}
                    style={styles.picker}
                  >
                    <Picker.Item label="30 dias (Mensal)" value="30" />
                    <Picker.Item label="90 dias (Trimestral)" value="90" />
                    <Picker.Item label="180 dias (Semestral)" value="180" />
                    <Picker.Item label="365 dias (Anual)" value="365" />
                  </Picker>
                </View>
              </View>
            </View>
          </View>

          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="toggle-right" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Status do Plano</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.switchRow}>
                <View style={styles.switchInfo}>
                  <Text style={styles.switchLabel}>Plano Ativo</Text>
                  <Text style={styles.switchDescription}>
                    {formData.ativo ? 'O plano está ativo.' : 'O plano está inativo.'}
                  </Text>
                </View>
                <Switch
                  value={formData.ativo}
                  onValueChange={(value) => handleChange('ativo', value)}
                  disabled={saving}
                  trackColor={{ false: '#d1d5db', true: '#10b981' }}
                  thumbColor={formData.ativo ? '#22c55e' : '#9ca3af'}
                />
              </View>

              <View style={styles.switchRow}>
                <View style={styles.switchInfo}>
                  <Text style={styles.switchLabel}>Disponível para Novos Contratos</Text>
                  <Text style={styles.switchDescription}>
                    {formData.atual
                      ? 'Pode ser usado em novos contratos.'
                      : 'Apenas contratos existentes (histórico).'}
                  </Text>
                </View>
                <Switch
                  value={formData.atual}
                  onValueChange={(value) => handleChange('atual', value)}
                  disabled={saving}
                  trackColor={{ false: '#d1d5db', true: '#3b82f6' }}
                  thumbColor={formData.atual ? '#3b82f6' : '#9ca3af'}
                />
              </View>
            </View>
          </View>
        </View>

        <View style={styles.actionButtons}>
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
              <>
                <Feather name="check" size={18} color="#fff" />
                <Text style={styles.submitButtonText}>
                  {isEdit ? 'Salvar Alterações' : 'Criar Plano'}
                </Text>
              </>
            )}
          </TouchableOpacity>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  scrollView: {
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
  headerActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 24,
    paddingVertical: 16,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 16,
    backgroundColor: '#f97316',
    borderRadius: 8,
  },
  backButtonText: {
    fontSize: 14,
    color: '#fff',
    fontWeight: '600',
  },
  statusIndicator: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 20,
  },
  statusActive: {
    backgroundColor: '#dcfce7',
  },
  statusInactive: {
    backgroundColor: '#fee2e2',
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  statusDotActive: {
    backgroundColor: '#22c55e',
  },
  statusDotInactive: {
    backgroundColor: '#ef4444',
  },
  statusIndicatorText: {
    fontSize: 13,
    fontWeight: '600',
  },
  statusTextActive: {
    color: '#166534',
  },
  statusTextInactive: {
    color: '#991b1b',
  },
  formContainer: {
    paddingHorizontal: 24,
    paddingBottom: 24,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
    overflow: 'hidden',
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
    backgroundColor: '#fff7ed',
  },
  cardHeaderIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#fed7aa',
  },
  cardTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1f2937',
  },
  cardBody: {
    padding: 16,
  },
  row: {
    flexDirection: 'row',
  },
  flex1: {
    flex: 1,
  },
  marginRight: {
    marginRight: 12,
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
  },
  required: {
    color: '#ef4444',
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 10,
    padding: 12,
    fontSize: 14,
    color: '#111827',
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
  pickerContainer: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 10,
    overflow: 'hidden',
  },
  picker: {
    height: 50,
    color: '#111827',
  },
  helperText: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: -12,
    marginBottom: 16,
    marginLeft: 4,
  },
  inputWithPrefix: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 10,
    overflow: 'hidden',
  },
  prefix: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    paddingLeft: 12,
    paddingRight: 8,
  },
  inputWithPrefixField: {
    flex: 1,
    borderWidth: 0,
    paddingLeft: 0,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  switchInfo: {
    flex: 1,
  },
  switchLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
  },
  switchDescription: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 4,
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 24,
    paddingBottom: 40,
  },
  cancelButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: 10,
    backgroundColor: '#f3f4f6',
  },
  cancelButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
  },
  submitButton: {
    backgroundColor: '#f97316',
    padding: 12,
    borderRadius: 10,
    alignItems: 'center',
    flex: 1,
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
