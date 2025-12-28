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
import { Feather } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { superAdminService } from '../../services/superAdminService';
import planoService from '../../services/planoService';
import cepService from '../../services/cepService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';
import { 
  validarEmail, 
  validarCNPJ, 
  validarCEP, 
  validarObrigatorio 
} from '../../utils/validators';

export default function EditarAcademiaScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const academiaId = parseInt(id);
  console.log('🏁 EditarAcademiaScreen iniciado, academiaId:', academiaId);
  
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [buscandoCep, setBuscandoCep] = useState(false);
  const [planos, setPlanos] = useState([]);
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState({});
  const [formData, setFormData] = useState({
    nome: '',
    email: '',
    cnpj: '',
    telefone: '',
    cep: '',
    logradouro: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    estado: '',
    plano_id: '',
    ativo: true,
  });

  useEffect(() => {
    console.log('🏋️ EditarAcademiaScreen montado, academiaId:', academiaId);
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      // Carregar dados da academia
      const response = await superAdminService.buscarAcademia(academiaId);
      const academia = response.academia;
      
      console.log('📋 Academia carregada:', academia);
      console.log('📋 Plano ID:', academia.plano_id, 'Tipo:', typeof academia.plano_id);
      
      setFormData({
        nome: academia.nome || '',
        email: academia.email || '',
        cnpj: academia.cnpj || '',
        telefone: academia.telefone || '',
        cep: academia.cep || '',
        logradouro: academia.logradouro || '',
        numero: academia.numero || '',
        complemento: academia.complemento || '',
        bairro: academia.bairro || '',
        cidade: academia.cidade || '',
        estado: academia.estado || '',
        plano_id: academia.plano_id ? Number(academia.plano_id) : '',
        ativo: academia.ativo === 1 || academia.ativo === true,
      });

      // Carregar planos via API
      const planosResponse = await planoService.listar(true);
      setPlanos(planosResponse.planos || []);
      
      console.log('📋 Planos carregados:', planosResponse.planos);
      console.log('📋 FormData plano_id após set:', formData.plano_id);
    } catch (error) {
      showError('Não foi possível carregar os dados da academia');
      router.back();
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (field, value) => {
    setFormData({ ...formData, [field]: value });
    
    // Validar campo em tempo real se já foi tocado
    if (touched[field]) {
      validateField(field, value);
    }
  };

  const handleBlur = (field) => {
    setTouched({ ...touched, [field]: true });
    validateField(field, formData[field]);
  };

  const validateField = (field, value) => {
    let error = '';

    switch (field) {
      case 'nome':
        if (!validarObrigatorio(value)) {
          error = 'Nome é obrigatório';
        }
        break;
      case 'email':
        if (!validarObrigatorio(value)) {
          error = 'E-mail é obrigatório';
        } else if (!validarEmail(value)) {
          error = 'E-mail inválido';
        }
        break;
      case 'cnpj':
        if (value && !validarCNPJ(value)) {
          error = 'CNPJ inválido';
        }
        break;
      case 'cep':
        if (value && !validarCEP(value)) {
          error = 'CEP inválido';
        }
        break;
    }

    setErrors({ ...errors, [field]: error });
    return !error;
  };

  const buscarCep = async (cep) => {
    const cepLimpo = cep.replace(/\D/g, '');
    
    if (cepLimpo.length !== 8) return;
    
    try {
      setBuscandoCep(true);
      const dados = await cepService.buscar(cepLimpo);
      
      // Preencher campos automaticamente
      setFormData({
        ...formData,
        cep: cep,
        logradouro: dados.logradouro || '',
        bairro: dados.bairro || '',
        cidade: dados.cidade || '',
        estado: dados.estado || '',
      });
      
      showSuccess('CEP encontrado! Dados preenchidos automaticamente.');
    } catch (error) {
      showError(error.message || 'CEP não encontrado');
    } finally {
      setBuscandoCep(false);
    }
  };

  const validateForm = () => {
    console.log('🔍 Validando formulário...', formData);
    
    // Marcar todos os campos como tocados para mostrar erros
    const allFields = ['nome', 'email', 'cnpj', 'cep'];
    const newTouched = {};
    allFields.forEach(field => {
      newTouched[field] = true;
    });
    setTouched(newTouched);

    // Validar cada campo
    let hasErrors = false;
    const newErrors = {};

    if (!validarObrigatorio(formData.nome)) {
      newErrors.nome = 'Nome é obrigatório';
      hasErrors = true;
    }

    if (!validarObrigatorio(formData.email)) {
      newErrors.email = 'E-mail é obrigatório';
      hasErrors = true;
    } else if (!validarEmail(formData.email)) {
      newErrors.email = 'E-mail inválido';
      hasErrors = true;
    }

    if (formData.cnpj && !validarCNPJ(formData.cnpj)) {
      newErrors.cnpj = 'CNPJ inválido';
      hasErrors = true;
    }

    if (formData.cep && !validarCEP(formData.cep)) {
      newErrors.cep = 'CEP inválido';
      hasErrors = true;
    }

    setErrors(newErrors);

    if (hasErrors) {
      console.log('❌ Validação falhou:', newErrors);
      showError('Corrija os campos destacados em vermelho');
      return false;
    }
    
    console.log('✅ Validação OK');
    return true;
  };

  const handleSubmit = async () => {
    console.log('🔘 Botão clicado!');
    console.log('📝 Dados do formulário:', formData);
    
    if (!validateForm()) {
      console.log('❌ Validação falhou');
      return;
    }

    console.log('✅ Validação passou');
    setSaving(true);
    
    try {
      // Converter plano_id para número antes de enviar
      const dadosParaEnviar = {
        ...formData,
        plano_id: formData.plano_id ? Number(formData.plano_id) : null,
      };
      
      console.log('📤 Enviando para API:', dadosParaEnviar);
      const result = await superAdminService.atualizarAcademia(academiaId, dadosParaEnviar);
      console.log('✅ Resultado:', result);
      
      showSuccess(result);
      router.push('/academias');
    } catch (error) {
      console.error('❌ Erro completo:', error);
      showError(error.errors?.join('\n') || error.error || 'Não foi possível atualizar a academia');
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

  console.log('🎨 Renderizando EditarAcademiaScreen, saving:', saving);

  return (
    <LayoutBase title="Editar Academia" subtitle="Atualizar informações">
      {saving && <LoadingOverlay message="Atualizando academia..." />}
      
      <ScrollView style={styles.container}>
        {/* Botão Voltar */}
        <View style={styles.headerActions}>
          <TouchableOpacity 
            style={styles.backButton}
            onPress={() => router.back()}
            disabled={saving}
          >
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>
        </View>

        {/* Form */}
        <View style={styles.form}>
          {/* Seção: Dados Gerais */}
          <Text style={styles.sectionTitle}>Dados da Academia</Text>
          
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Nome da Academia *</Text>
            <TextInput
              style={[styles.input, errors.nome && touched.nome && styles.inputError]}
              placeholder="Ex: Academia Fitness Pro"
              placeholderTextColor="#aaa"
              value={formData.nome}
              onChangeText={(value) => handleChange('nome', value)}
              onBlur={() => handleBlur('nome')}
              editable={!saving}
            />
            {errors.nome && touched.nome && (
              <Text style={styles.errorText}>{errors.nome}</Text>
            )}
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>E-mail *</Text>
              <TextInput
                style={[styles.input, errors.email && touched.email && styles.inputError]}
                placeholder="contato@academia.com"
                placeholderTextColor="#aaa"
                value={formData.email}
                onChangeText={(value) => handleChange('email', value)}
                onBlur={() => handleBlur('email')}
                keyboardType="email-address"
                autoCapitalize="none"
                editable={!saving}
              />
              {errors.email && touched.email && (
                <Text style={styles.errorText}>{errors.email}</Text>
              )}
            </View>

            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>CNPJ</Text>
              <TextInput
                style={[styles.input, errors.cnpj && touched.cnpj && styles.inputError]}
                placeholder="00.000.000/0000-00"
                placeholderTextColor="#aaa"
                value={formData.cnpj}
                onChangeText={(value) => handleChange('cnpj', value)}
                onBlur={() => handleBlur('cnpj')}
                keyboardType="numeric"
                editable={!saving}
              />
              {errors.cnpj && touched.cnpj && (
                <Text style={styles.errorText}>{errors.cnpj}</Text>
              )}
            </View>
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Telefone</Text>
              <TextInput
                style={styles.input}
                placeholder="(11) 99999-9999"
                placeholderTextColor="#aaa"
                value={formData.telefone}
                onChangeText={(value) => handleChange('telefone', value)}
                keyboardType="phone-pad"
                editable={!saving}
              />
            </View>
          </View>

          {/* Seção: Endereço */}
          <Text style={styles.sectionTitle}>Endereço</Text>
          
          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>CEP</Text>
              <View style={styles.cepInputContainer}>
                <TextInput
                  style={[styles.input, buscandoCep && styles.inputLoading]}
                  placeholder="00000-000"
                  placeholderTextColor="#aaa"
                  value={formData.cep}
                  onChangeText={(value) => {
                    handleChange('cep', value);
                    if (value.replace(/\D/g, '').length === 8) {
                      buscarCep(value);
                    }
                  }}
                  keyboardType="numeric"
                  editable={!saving}
                  maxLength={9}
                />
                {buscandoCep && (
                  <ActivityIndicator 
                    size="small" 
                    color="#f97316" 
                    style={styles.cepLoader}
                  />
                )}
              </View>
            </View>

            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Logradouro</Text>
              <TextInput
                style={styles.input}
                placeholder="Rua, Av, etc"
                placeholderTextColor="#aaa"
                value={formData.logradouro}
                onChangeText={(value) => handleChange('logradouro', value)}
                editable={!saving}
              />
            </View>
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, { flex: 0.3 }]}>
              <Text style={styles.label}>Número</Text>
              <TextInput
                style={styles.input}
                placeholder="123"
                placeholderTextColor="#aaa"
                value={formData.numero}
                onChangeText={(value) => handleChange('numero', value)}
                keyboardType="numeric"
                editable={!saving}
              />
            </View>

            <View style={[styles.inputGroup, { flex: 0.7 }]}>
              <Text style={styles.label}>Complemento</Text>
              <TextInput
                style={styles.input}
                placeholder="Apto, Sala, Bloco"
                placeholderTextColor="#aaa"
                value={formData.complemento}
                onChangeText={(value) => handleChange('complemento', value)}
                editable={!saving}
              />
            </View>
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Bairro</Text>
            <TextInput
              style={styles.input}
              placeholder="Nome do bairro"
              placeholderTextColor="#aaa"
              value={formData.bairro}
              onChangeText={(value) => handleChange('bairro', value)}
              editable={!saving}
            />
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, { flex: 0.7 }]}>
              <Text style={styles.label}>Cidade</Text>
              <TextInput
                style={styles.input}
                placeholder="Nome da cidade"
                placeholderTextColor="#aaa"
                value={formData.cidade}
                onChangeText={(value) => handleChange('cidade', value)}
                editable={!saving}
              />
            </View>

            <View style={[styles.inputGroup, { flex: 0.3 }]}>
              <Text style={styles.label}>Estado</Text>
              <TextInput
                style={styles.input}
                placeholder="UF"
                placeholderTextColor="#aaa"
                value={formData.estado}
                onChangeText={(value) => handleChange('estado', value.toUpperCase())}
                maxLength={2}
                autoCapitalize="characters"
                editable={!saving}
              />
            </View>
          </View>

          {/* Seção: Plano e Status */}
          <Text style={styles.sectionTitle}>Plano e Status</Text>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Plano</Text>
            <View style={styles.pickerContainer}>
              <Picker
                selectedValue={formData.plano_id}
                onValueChange={(value) => handleChange('plano_id', value)}
                enabled={!saving}
                style={styles.picker}
              >
                <Picker.Item label="Nenhum plano selecionado" value="" />
                {planos.map((plano) => (
                  <Picker.Item
                    key={plano.id}
                    label={`${plano.nome} - R$ ${parseFloat(plano.valor).toFixed(2)}`}
                    value={plano.id}
                  />
                ))}
              </Picker>
            </View>
          </View>

          <View style={styles.inputGroup}>
            <View style={styles.switchContainer}>
              <View>
                <Text style={styles.label}>Status da Academia</Text>
                <Text style={styles.switchSubtext}>
                  {formData.ativo ? 'Academia Ativa' : 'Academia Inativa'}
                </Text>
              </View>
              <Switch
                value={formData.ativo}
                onValueChange={(value) => handleChange('ativo', value)}
                disabled={saving}
                trackColor={{ false: '#ccc', true: '#4ade80' }}
                thumbColor={formData.ativo ? '#16a34a' : '#f4f3f4'}
                ios_backgroundColor="#ccc"
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
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  headerActions: {
    padding: 20,
    paddingBottom: 0,
    flexDirection: 'row',
    justifyContent: 'flex-end',
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 20,
    backgroundColor: '#f97316',
    borderRadius: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  backButtonText: {
    fontSize: 16,
    color: '#fff',
    fontWeight: '600',
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
    paddingTop: 10,
  },
  row: {
    flexDirection: 'row',
    gap: 15,
    marginBottom: 0,
  },
  inputGroup: {
    marginBottom: 20,
  },
  halfWidth: {
    flex: 1,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#f97316',
    marginTop: 10,
    marginBottom: 15,
    borderBottomWidth: 2,
    borderBottomColor: '#f97316',
    paddingBottom: 5,
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
  inputLoading: {
    backgroundColor: 'rgba(249, 249, 249, 0.9)',
  },
  cepInputContainer: {
    position: 'relative',
  },
  cepLoader: {
    position: 'absolute',
    right: 12,
    top: 12,
  },
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  pickerContainer: {
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.2)',
    borderRadius: 10,
    overflow: 'hidden',
  },
  picker: {
    height: 50,
    color: '#2b1a04',
  },
  switchContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.2)',
    borderRadius: 10,
    padding: 15,
  },
  switchSubtext: {
    fontSize: 13,
    color: '#666',
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
