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
  useWindowDimensions,
  Platform,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import planosSistemaService from '../../services/planosSistemaService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';
import { authService } from '../../services/authService';

export default function NovoPlanoSistemaScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  useEffect(() => {
    checkAccess();
  }, []);

  const checkAccess = async () => {
    const user = await authService.getCurrentUser();
    if (!user || user.role_id !== 4) {
      showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
      router.replace('/');
      return;
    }
  };

  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [formData, setFormData] = useState({
    nome: '',
    descricao: '',
    valor: '',
    duracao_dias: '30',
    max_alunos: '',
    max_admins: '1',
    ativo: true,
    atual: true,
    ordem: '0',
  });

  const handleChange = (field, value) => {
    setFormData({ ...formData, [field]: value });
    if (errors[field]) {
      setErrors({ ...errors, [field]: null });
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.nome.trim()) {
      newErrors.nome = 'Nome do plano é obrigatório';
    }

    if (!formData.valor.trim()) {
      newErrors.valor = 'Valor mensal é obrigatório';
    } else if (isNaN(parseFloat(formData.valor))) {
      newErrors.valor = 'Valor deve ser um número válido';
    }

    if (formData.max_alunos && isNaN(parseInt(formData.max_alunos))) {
      newErrors.max_alunos = 'Capacidade deve ser um número';
    }

    if (formData.max_admins && isNaN(parseInt(formData.max_admins))) {
      newErrors.max_admins = 'Número de admins deve ser um número';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      showError('Corrija os erros antes de salvar');
      return;
    }

    setSaving(true);
    try {
      const data = {
        nome: formData.nome,
        descricao: formData.descricao || null,
        valor: parseFloat(formData.valor),
        duracao_dias: formData.duracao_dias ? parseInt(formData.duracao_dias) : null,
        max_alunos: formData.max_alunos ? parseInt(formData.max_alunos) : null,
        max_admins: formData.max_admins ? parseInt(formData.max_admins) : 1,
        ativo: formData.ativo ? 1 : 0,
        atual: formData.atual ? 1 : 0,
        ordem: formData.ordem ? parseInt(formData.ordem) : 0,
      };

      const response = await planosSistemaService.criar(data);
      showSuccess(response.message || 'Plano criado com sucesso');
      router.push('/planos-sistema');
    } catch (error) {
      showError(error.message || error.error || 'Não foi possível criar o plano');
    } finally {
      setSaving(false);
    }
  };

  return (
    <LayoutBase title="Novo Plano do Sistema" noPadding={true}>
      {saving && <LoadingOverlay message="Criando plano..." />}
      
      <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
        {/* Banner */}
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <TouchableOpacity 
                onPress={() => router.back()} 
                style={styles.backButtonBanner}
              >
                <Feather name="arrow-left" size={24} color="#fff" />
              </TouchableOpacity>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="plus" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Novo Plano</Text>
                <Text style={styles.bannerSubtitle} numberOfLines={1}>
                  Criar novo plano do sistema
                </Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>
        </View>

        <View style={styles.formContainer}>
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="plus" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Informações do Plano</Text>
            </View>

            <View style={styles.cardBody}>
              {/* Nome */}
              <View style={styles.inputGroup}>
                <Text style={styles.label}>Nome do Plano *</Text>
                <TextInput
                  style={[styles.input, errors.nome && styles.inputError]}
                  placeholder="Ex: Plano Premium"
                  placeholderTextColor="#9ca3af"
                  value={formData.nome}
                  onChangeText={(value) => handleChange('nome', value)}
                  editable={!saving}
                />
                {errors.nome && <Text style={styles.errorText}>{errors.nome}</Text>}
              </View>

              {/* Descrição */}
              <View style={styles.inputGroup}>
                <Text style={styles.label}>Descrição</Text>
                <TextInput
                  style={[styles.input, styles.textArea]}
                  placeholder="Descrição do plano"
                  placeholderTextColor="#9ca3af"
                  value={formData.descricao}
                  onChangeText={(value) => handleChange('descricao', value)}
                  multiline={true}
                  numberOfLines={3}
                  editable={!saving}
                />
              </View>

              {/* Valor e Duração */}
              <View style={styles.row}>
                <View style={[styles.inputGroup, { flex: 1, marginRight: 12 }]}>
                  <Text style={styles.label}>Valor Mensal (R$) *</Text>
                  <TextInput
                    style={[styles.input, errors.valor && styles.inputError]}
                    placeholder="199.90"
                    placeholderTextColor="#9ca3af"
                    value={formData.valor}
                    onChangeText={(value) => handleChange('valor', value)}
                    keyboardType="decimal-pad"
                    editable={!saving}
                  />
                  {errors.valor && <Text style={styles.errorText}>{errors.valor}</Text>}
                </View>

                <View style={[styles.inputGroup, { flex: 1 }]}>
                  <Text style={styles.label}>Duração (dias)</Text>
                  <TextInput
                    style={styles.input}
                    placeholder="30"
                    placeholderTextColor="#9ca3af"
                    value={formData.duracao_dias}
                    onChangeText={(value) => handleChange('duracao_dias', value)}
                    keyboardType="number-pad"
                    editable={!saving}
                  />
                </View>
              </View>

              {/* Capacidades */}
              <View style={styles.row}>
                <View style={[styles.inputGroup, { flex: 1, marginRight: 12 }]}>
                  <Text style={styles.label}>Capacidade de Alunos *</Text>
                  <TextInput
                    style={[styles.input, errors.max_alunos && styles.inputError]}
                    placeholder="Ex: 50, 100, 200"
                    placeholderTextColor="#9ca3af"
                    value={formData.max_alunos}
                    onChangeText={(value) => handleChange('max_alunos', value)}
                    keyboardType="number-pad"
                    editable={!saving}
                  />
                  {errors.max_alunos && (
                    <Text style={styles.errorText}>{errors.max_alunos}</Text>
                  )}
                </View>

                <View style={[styles.inputGroup, { flex: 1 }]}>
                  <Text style={styles.label}>Quantidade de Admins *</Text>
                  <TextInput
                    style={[styles.input, errors.max_admins && styles.inputError]}
                    placeholder="Ex: 1, 2, 5"
                    placeholderTextColor="#9ca3af"
                    value={formData.max_admins}
                    onChangeText={(value) => handleChange('max_admins', value)}
                    keyboardType="number-pad"
                    editable={!saving}
                  />
                  {errors.max_admins && (
                    <Text style={styles.errorText}>{errors.max_admins}</Text>
                  )}
                </View>
              </View>

              {/* Ordem */}
              <View style={styles.inputGroup}>
                <Text style={styles.label}>Ordem de Exibição</Text>
                <TextInput
                  style={styles.input}
                  placeholder="0"
                  placeholderTextColor="#9ca3af"
                  value={formData.ordem}
                  onChangeText={(value) => handleChange('ordem', value)}
                  keyboardType="number-pad"
                  editable={!saving}
                />
              </View>

              {/* Switches */}
              <View style={styles.switchRowContainer}>
                <View style={[styles.inputGroup, { flex: 1, marginRight: 12 }]}>
                  <View style={styles.switchRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.label}>Plano Atual</Text>
                      <Text style={styles.switchSubtext}>
                        {formData.atual
                          ? 'Disponível para novos contratos'
                          : 'Apenas para contratos existentes'}
                      </Text>
                    </View>
                    <Switch
                      value={formData.atual}
                      onValueChange={(value) => handleChange('atual', value)}
                      disabled={saving}
                      trackColor={{ false: '#d1d5db', true: '#10b981' }}
                      thumbColor={formData.atual ? '#fff' : '#f3f4f6'}
                    />
                  </View>
                </View>

                <View style={[styles.inputGroup, { flex: 1 }]}>
                  <View style={styles.switchRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.label}>Status</Text>
                      <Text style={styles.switchSubtext}>
                        {formData.ativo ? 'Plano ativo no sistema' : 'Plano inativo'}
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
                  <>
                    <Feather name="check" size={18} color="#fff" />
                    <Text style={styles.submitButtonText}>Criar Plano</Text>
                  </>
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
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  bannerContainer: {
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  banner: {
    backgroundColor: '#f97316',
    paddingTop: 20,
    paddingBottom: 30,
    position: 'relative',
    overflow: 'hidden',
  },
  bannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    zIndex: 2,
  },
  backButtonBanner: {
    padding: 8,
    marginRight: 12,
  },
  bannerIconContainer: {
    marginRight: 12,
  },
  bannerIconOuter: {
    width: 48,
    height: 48,
    backgroundColor: 'rgba(255,255,255,0.2)',
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  bannerIconInner: {
    width: 40,
    height: 40,
    backgroundColor: 'rgba(255,255,255,0.3)',
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  bannerTextContainer: {
    flex: 1,
  },
  bannerTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: '#fff',
    marginBottom: 4,
  },
  bannerSubtitle: {
    fontSize: 14,
    color: 'rgba(255,255,255,0.8)',
  },
  bannerDecoration: {
    position: 'absolute',
    top: -20,
    right: -20,
    width: 200,
    height: 200,
  },
  decorCircle1: {
    position: 'absolute',
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: 'rgba(255,255,255,0.1)',
    top: 10,
    right: 20,
  },
  decorCircle2: {
    position: 'absolute',
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(255,255,255,0.15)',
    top: 60,
    right: 80,
  },
  decorCircle3: {
    position: 'absolute',
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.2)',
    bottom: 20,
    right: 40,
  },
  formContainer: {
    padding: 16,
    gap: 16,
    paddingBottom: 40,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    backgroundColor: '#fafafa',
  },
  cardHeaderIcon: {
    width: 40,
    height: 40,
    borderRadius: 8,
    backgroundColor: '#fef3c7',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1f2937',
  },
  cardBody: {
    padding: 20,
    gap: 16,
  },
  inputGroup: {
    gap: 8,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
  },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#1f2937',
    backgroundColor: '#fff',
  },
  inputError: {
    borderColor: '#ef4444',
  },
  errorText: {
    fontSize: 12,
    color: '#ef4444',
    marginTop: 4,
  },
  textArea: {
    minHeight: 100,
    paddingTop: 10,
    textAlignVertical: 'top',
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  switchRowContainer: {
    flexDirection: 'row',
    gap: 12,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  switchSubtext: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 4,
  },
  submitButton: {
    backgroundColor: '#f97316',
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 20,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
    marginTop: 8,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 14,
  },
});
