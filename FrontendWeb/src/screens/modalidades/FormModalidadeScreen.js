import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
  Switch,
} from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { useRouter, useLocalSearchParams } from 'expo-router';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import { showSuccess, showError } from '../../utils/toast';

// Lista de ícones disponíveis (MaterialCommunityIcons)
const ICONES_DISPONIVEIS = [
  { value: 'dumbbell', label: 'Musculação' },
  { value: 'weight-lifter', label: 'Levantamento de Peso' },
  { value: 'run', label: 'Corrida' },
  { value: 'bike', label: 'Ciclismo' },
  { value: 'boxing-glove', label: 'Boxe' },
  { value: 'yoga', label: 'Yoga' },
  { value: 'karate', label: 'Artes Marciais' },
  { value: 'swim', label: 'Natação' },
  { value: 'tennis', label: 'Tênis' },
  { value: 'soccer', label: 'Futebol' },
  { value: 'basketball', label: 'Basquete' },
  { value: 'volleyball', label: 'Vôlei' },
  { value: 'heart-pulse', label: 'Cardio' },
  { value: 'dance-ballroom', label: 'Dança' },
  { value: 'arm-flex', label: 'Fitness' },
  { value: 'jump-rope', label: 'Funcional' },
];

export default function FormModalidadeScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const modalidadeId = id ? parseInt(id) : null;
  const isEdit = !!modalidadeId && id !== 'novo';
  
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState({
    nome: '',
    descricao: '',
    cor: '#f97316',
    icone: 'dumbbell',
    ativo: true,
  });
  const [planos, setPlanos] = useState([
    { nome: '', checkins_semanais: '', valor: '', duracao_dias: 30, ativo: true, atual: true }
  ]);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (isEdit) {
      loadModalidade();
    }
  }, []);

  const loadModalidade = async () => {
    try {
      setLoading(true);
      const response = await modalidadeService.buscar(modalidadeId);
      const modalidade = response.modalidade;
      
      setFormData({
        nome: modalidade.nome || '',
        descricao: modalidade.descricao || '',
        cor: modalidade.cor || '#f97316',
        icone: modalidade.icone || 'activity',
        ativo: modalidade.ativo === 1,
      });

      // Carregar planos da modalidade
      if (modalidade.planos && modalidade.planos.length > 0) {
        const planosFormatados = modalidade.planos.map(p => ({
          id: p.id,
          nome: p.nome || '',
          checkins_semanais: p.checkins_semanais ? String(p.checkins_semanais) : '',
          valor: p.valor ? p.valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '',
          duracao_dias: p.duracao_dias || 30,
          ativo: p.ativo === 1 || p.ativo === true,
          atual: p.atual === 1 || p.atual === true,
        }));
        setPlanos(planosFormatados);
      }
    } catch (error) {
      console.error('❌ Erro ao carregar modalidade:', error);
      showError(error.message || 'Não foi possível carregar os dados da modalidade');
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

  const handlePlanoChange = (index, field, value) => {
    const novosPlanos = [...planos];
    novosPlanos[index][field] = value;
    setPlanos(novosPlanos);
    
    // Limpar erro do campo específico quando o usuário começar a digitar
    const errorKey = `plano_${index}_${field === 'checkins_semanais' ? 'checkins' : field}`;
    if (errors[errorKey]) {
      const newErrors = { ...errors };
      delete newErrors[errorKey];
      setErrors(newErrors);
    }
  };

  const adicionarPlano = () => {
    setPlanos([...planos, { nome: '', checkins_semanais: '', valor: '', duracao_dias: 30, ativo: true, atual: true }]);
  };

  const removerPlano = (index) => {
    if (planos.length > 1) {
      const novosPlanos = planos.filter((_, i) => i !== index);
      setPlanos(novosPlanos);
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

  const validate = () => {
    const newErrors = {};

    if (!formData.nome?.trim()) {
      newErrors.nome = 'Nome é obrigatório';
    }

    // Validar planos (sempre obrigatório ter pelo menos 1 plano)
    if (planos.length === 0) {
      newErrors.planos = 'Adicione pelo menos um plano';
    } else {
      planos.forEach((plano, index) => {
        if (!plano.nome?.trim()) {
          newErrors[`plano_${index}_nome`] = `Nome do plano ${index + 1} é obrigatório`;
        }
        if (!plano.checkins_semanais) {
          newErrors[`plano_${index}_checkins`] = `Checkins semanais do plano ${index + 1} é obrigatório`;
        }
        if (!plano.valor) {
          newErrors[`plano_${index}_valor`] = `Valor do plano ${index + 1} é obrigatório`;
        }
      });
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validate()) {
      const errorCount = Object.keys(errors).length;
      showError(`Por favor, corrija ${errorCount === 1 ? 'o erro destacado' : `os ${errorCount} erros destacados`} no formulário`);
      return;
    }

    try {
      setSaving(true);

      const dados = {
        nome: formData.nome.trim(),
        descricao: formData.descricao.trim(),
        cor: formData.cor,
        icone: formData.icone,
        ativo: formData.ativo ? 1 : 0,
      };

      // Formatar planos (tanto para criar quanto para editar)
      const planosFormatados = planos.map((plano) => {
        return {
          id: plano.id || null,
          nome: plano.nome.trim(),
          checkins_semanais: parseInt(plano.checkins_semanais),
          valor: parseFloat(plano.valor.replace(/\D/g, '')) / 100,
          duracao_dias: plano.duracao_dias,
          ativo: plano.ativo === true ? 1 : 0,
          atual: plano.atual === true ? 1 : 0,
        };
      });

      dados.planos = planosFormatados;

      if (isEdit) {
        await modalidadeService.atualizar(modalidadeId, dados);
        showSuccess('Modalidade e planos atualizados com sucesso');
      } else {
        await modalidadeService.criar(dados);
        showSuccess('Modalidade e planos criados com sucesso');
      }

      // Pequeno delay para garantir que o toast seja exibido antes do redirecionamento
      setTimeout(() => {
        router.push('/modalidades');
      }, 500);
    } catch (error) {
      console.error('❌ Erro ao salvar modalidade:', error);
      showError(error.message || `Erro ao ${isEdit ? 'atualizar' : 'criar'} modalidade`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <LayoutBase showSidebar showHeader>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase showSidebar showHeader>
      <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
        <View style={styles.headerActions}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.back()}>
            <Feather name="arrow-left" size={20} color="#64748b" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.content}>
          <View style={styles.header}>
            <Text style={styles.title}>
              {isEdit ? 'Editar Modalidade' : 'Nova Modalidade'}
            </Text>
            <Text style={styles.subtitle}>
              {isEdit 
                ? 'Atualize as informações da modalidade' 
                : 'Preencha os dados da nova modalidade'}
            </Text>
          </View>

          <View style={styles.form}>
            {/* Nome */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>Nome *</Text>
              <TextInput
                style={[styles.input, errors.nome && styles.inputError]}
                placeholder="Ex: Musculação"
                value={formData.nome}
                onChangeText={(value) => handleChange('nome', value)}
              />
              {errors.nome && <Text style={styles.errorText}>{errors.nome}</Text>}
            </View>

            {/* Descrição */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>Descrição</Text>
              <TextInput
                style={[styles.input, styles.textArea]}
                placeholder="Descreva a modalidade..."
                value={formData.descricao}
                onChangeText={(value) => handleChange('descricao', value)}
                multiline
                numberOfLines={4}
                textAlignVertical="top"
              />
            </View>

            {/* Ícone */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>Ícone</Text>
              <View style={styles.pickerContainer}>
                <Picker
                  selectedValue={formData.icone}
                  onValueChange={(value) => handleChange('icone', value)}
                  style={styles.picker}
                >
                  {ICONES_DISPONIVEIS.map((icone) => (
                    <Picker.Item key={icone.value} label={icone.label} value={icone.value} />
                  ))}
                </Picker>
              </View>
              <View style={styles.iconePreview}>
                <View style={[styles.iconeBadge, { backgroundColor: formData.cor }]}>
                  <MaterialCommunityIcons name={formData.icone} size={24} color="#fff" />
                </View>
                <Text style={styles.iconePreviewText}>Prévia do ícone</Text>
              </View>
            </View>

            {/* Cor */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>Cor</Text>
              <View style={styles.coresContainer}>
                {['#f97316', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444', '#f59e0b', '#06b6d4', '#ec4899'].map((cor) => (
                  <TouchableOpacity
                    key={cor}
                    style={[
                      styles.corButton,
                      { backgroundColor: cor },
                      formData.cor === cor && styles.corButtonSelected,
                    ]}
                    onPress={() => handleChange('cor', cor)}
                  >
                    {formData.cor === cor && <Feather name="check" size={16} color="#fff" />}
                  </TouchableOpacity>
                ))}
              </View>
            </View>

            {/* Planos da Modalidade */}
            <View style={styles.planosSection}>
              <View style={styles.planosSectionHeader}>
                <Text style={styles.planosSectionTitle}>Planos desta Modalidade</Text>
                <Text style={styles.planosSectionSubtitle}>
                  {isEdit ? 'Gerencie os planos baseados em checkins semanais' : 'Crie os planos baseados em checkins semanais'}
                </Text>
              </View>

              <View style={styles.planosContainer}>
                {planos.map((plano, index) => (
                  <View key={index} style={styles.planoCard}>
                    <View style={styles.planoCardHeader}>
                      <Text style={styles.planoCardTitle}>Plano {index + 1}</Text>
                      {planos.length > 1 && (
                        <TouchableOpacity 
                          onPress={() => removerPlano(index)}
                          style={styles.removerPlanoButton}
                        >
                          <Feather name="x" size={18} color="#ef4444" />
                        </TouchableOpacity>
                      )}
                    </View>

                    <View style={styles.planoCardBody}>
                      {/* Nome do Plano - Linha 1 */}
                      <View style={styles.inputGroup}>
                        <Text style={styles.label}>Nome do Plano *</Text>
                        <TextInput
                          style={[styles.input, errors[`plano_${index}_nome`] && styles.inputError]}
                          placeholder="Ex: 2x por semana"
                          value={plano.nome}
                          onChangeText={(value) => handlePlanoChange(index, 'nome', value)}
                        />
                        {errors[`plano_${index}_nome`] && <Text style={styles.errorText}>{errors[`plano_${index}_nome`]}</Text>}
                      </View>

                      {/* Linha 2: Checkins, Valor e Duração */}
                      <View style={styles.planoRow}>
                        {/* Checkins Semanais */}
                        <View style={[styles.inputGroup, styles.planoInputThird]}>
                          <Text style={styles.label}>Checkins *</Text>
                          <TextInput
                            style={[styles.input, errors[`plano_${index}_checkins`] && styles.inputError]}
                            placeholder="Ex: 3"
                            value={plano.checkins_semanais}
                            onChangeText={(value) => handlePlanoChange(index, 'checkins_semanais', value.replace(/\D/g, ''))}
                            keyboardType="numeric"
                          />
                          {errors[`plano_${index}_checkins`] ? (
                            <Text style={styles.errorText}>{errors[`plano_${index}_checkins`]}</Text>
                          ) : (
                            <Text style={styles.helperText}>999 = ilimitado</Text>
                          )}
                        </View>

                        {/* Valor */}
                        <View style={[styles.inputGroup, styles.planoInputThird]}>
                          <Text style={styles.label}>Valor *</Text>
                          <View style={[styles.inputWithPrefix, errors[`plano_${index}_valor`] && styles.inputError]}>
                            <Text style={styles.prefix}>R$</Text>
                            <TextInput
                              style={[styles.input, styles.inputWithPrefixField]}
                              placeholder="0,00"
                              value={plano.valor}
                              onChangeText={(value) => {
                                const formatted = formatValorMonetario(value);
                                handlePlanoChange(index, 'valor', formatted);
                              }}
                              keyboardType="numeric"
                            />
                          </View>
                          {errors[`plano_${index}_valor`] && <Text style={styles.errorText}>{errors[`plano_${index}_valor`]}</Text>}
                        </View>

                        {/* Duração */}
                        <View style={[styles.inputGroup, styles.planoInputThird]}>
                          <Text style={styles.label}>Duração</Text>
                          <View style={styles.pickerContainer}>
                            <Picker
                              selectedValue={plano.duracao_dias}
                              onValueChange={(value) => handlePlanoChange(index, 'duracao_dias', value)}
                              style={styles.picker}
                            >
                              <Picker.Item label="30 dias" value={30} />
                              <Picker.Item label="90 dias" value={90} />
                              <Picker.Item label="180 dias" value={180} />
                              <Picker.Item label="365 dias" value={365} />
                            </Picker>
                          </View>
                        </View>
                      </View>

                      {/* Flags: Ativo e Atual */}
                      <View style={styles.planoFlagsRow}>
                        <View style={styles.planoFlag}>
                          <Text style={styles.planoFlagLabel}>Ativo</Text>
                          <Switch
                            value={plano.ativo}
                            onValueChange={(value) => handlePlanoChange(index, 'ativo', value)}
                            trackColor={{ false: '#d1d5db', true: '#10b981' }}
                            thumbColor={plano.ativo ? '#fff' : '#f3f4f6'}
                          />
                        </View>
                        <View style={styles.planoFlag}>
                          <Text style={styles.planoFlagLabel}>Disponível para novos contratos</Text>
                          <Switch
                            value={plano.atual}
                            onValueChange={(value) => handlePlanoChange(index, 'atual', value)}
                            trackColor={{ false: '#d1d5db', true: '#10b981' }}
                            thumbColor={plano.atual ? '#fff' : '#f3f4f6'}
                          />
                        </View>
                      </View>
                    </View>
                  </View>
                ))}

                <TouchableOpacity 
                  style={styles.addPlanoButton}
                  onPress={adicionarPlano}
                  activeOpacity={0.7}
                >
                  <View style={styles.addPlanoButtonIcon}>
                    <Feather name="plus" size={16} color="#fff" />
                  </View>
                  <Text style={styles.addPlanoButtonText}>Adicionar Outro Plano</Text>
                </TouchableOpacity>
              </View>
            </View>

            {/* Status */}
            <View style={[styles.inputGroup, styles.statusGroup]}>
              <View style={styles.switchContainer}>
                <View style={styles.switchLabelCompact}>
                  <Feather 
                    name={formData.ativo ? "check-circle" : "x-circle"} 
                    size={20} 
                    color={formData.ativo ? "#10b981" : "#ef4444"} 
                  />
                  <Text style={styles.label}>Modalidade Ativa</Text>
                  <Switch
                    value={formData.ativo}
                    onValueChange={(value) => handleChange('ativo', value)}
                    trackColor={{ false: '#d1d5db', true: '#10b981' }}
                    thumbColor={formData.ativo ? '#fff' : '#f3f4f6'}
                    style={styles.switchInline}
                  />
                </View>
              </View>
            </View>
          </View>

          {/* Botão de Salvar */}
          <TouchableOpacity
            style={[styles.submitButton, saving && styles.submitButtonDisabled]}
            onPress={handleSubmit}
            disabled={saving}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <>
                <Feather name={isEdit ? "save" : "plus"} size={18} color="#fff" />
                <Text style={styles.submitButtonText}>
                  {isEdit ? 'Atualizar Modalidade' : 'Cadastrar Modalidade'}
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
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    color: '#64748b',
  },
  headerActions: {
    padding: 20,
    paddingBottom: 0,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  backButtonText: {
    fontSize: 14,
    color: '#64748b',
  },
  content: {
    padding: 20,
  },
  header: {
    marginBottom: 24,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#1e293b',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 14,
    color: '#64748b',
  },
  form: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: 8,
  },
  input: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    color: '#1e293b',
  },
  inputError: {
    borderColor: '#ef4444',
  },
  textArea: {
    minHeight: 100,
    paddingTop: 12,
  },
  inputWithPrefix: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    overflow: 'hidden',
  },
  prefix: {
    paddingHorizontal: 12,
    fontSize: 14,
    fontWeight: '600',
    color: '#64748b',
  },
  inputWithPrefixField: {
    flex: 1,
    backgroundColor: 'transparent',
    borderWidth: 0,
    borderLeftWidth: 1,
    borderLeftColor: '#e2e8f0',
    borderRadius: 0,
  },
  pickerContainer: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    overflow: 'hidden',
  },
  picker: {
    height: 48,
  },
  iconePreview: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 12,
    gap: 12,
  },
  iconeBadge: {
    width: 48,
    height: 48,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  iconePreviewText: {
    fontSize: 12,
    color: '#64748b',
  },
  coresContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  corButton: {
    width: 48,
    height: 48,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
  },
  corButtonSelected: {
    borderColor: '#fff',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 4,
  },
  switchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  switchLabelCompact: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  switchInline: {
    marginLeft: 'auto',
  },
  statusGroup: {
    marginTop: 24,
    paddingTop: 20,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
  },
  helperText: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 4,
    fontStyle: 'italic',
  },
  errorText: {
    color: '#ef4444',
    fontSize: 12,
    marginTop: 4,
  },
  planosSection: {
    marginTop: 12,
    paddingTop: 20,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
  },
  planosSectionHeader: {
    marginBottom: 16,
  },
  planosSectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: 4,
  },
  planosSectionSubtitle: {
    fontSize: 13,
    color: '#64748b',
  },
  planosContainer: {
    backgroundColor: '#f8fafc',
    borderRadius: 10,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  planoCard: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  planoCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  planoCardTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#475569',
  },
  removerPlanoButton: {
    padding: 4,
  },
  planoCardBody: {
    gap: 12,
  },
  planoRow: {
    flexDirection: 'row',
    gap: 12,
  },
  planoInputHalf: {
    flex: 1,
    marginBottom: 0,
  },
  planoInputThird: {
    flex: 1,
    marginBottom: 0,
  },
  planoFlagsRow: {
    flexDirection: 'row',
    gap: 16,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
    marginTop: 8,
  },
  planoFlag: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  planoFlagLabel: {
    fontSize: 12,
    color: '#64748b',
    fontWeight: '500',
    flex: 1,
  },
  addPlanoButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    backgroundColor: 'transparent',
    borderWidth: 2,
    borderColor: '#165ef9ff',
    borderStyle: 'solid',
    marginTop: 4,
  },
  addPlanoButtonIcon: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: '#165ef9ff',
    justifyContent: 'center',
    alignItems: 'center',
  },
  addPlanoButtonText: {
    color: '#165ef9ff',
    fontSize: 14,
    fontWeight: '600',
  },
  submitButton: {
    backgroundColor: '#f97316',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    borderRadius: 8,
    marginBottom: 20,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
