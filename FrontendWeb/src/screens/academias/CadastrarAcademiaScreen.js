import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { useRouter } from 'expo-router';
import { superAdminService } from '../../services/superAdminService';
import cepService from '../../services/cepService';
import estadosService from '../../services/estadosService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';
import { 
  validarEmail, 
  validarCNPJ, 
  validarCEP, 
  validarSenha, 
  validarObrigatorio 
} from '../../utils/validators';
import { 
  mascaraCNPJ, 
  mascaraCPF,
  mascaraTelefone, 
  apenasNumeros,
  validarCPF 
} from '../../utils/masks';

export default function CadastrarAcademiaScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [buscandoCep, setBuscandoCep] = useState(false);
  const [estados, setEstados] = useState([]);
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState({});
  const [formData, setFormData] = useState({
    nome: '',
    email: '',
    senha_admin: '',
    cnpj: '',
    telefone: '',
    responsavel_nome: '',
    responsavel_cpf: '',
    responsavel_telefone: '',
    responsavel_email: '',
    cep: '',
    logradouro: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    estado: '',
  });

  useEffect(() => {
    const estadosList = estadosService.listarEstados();
    setEstados(estadosList);
  }, []);

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
      case 'senha_admin':
        if (!validarObrigatorio(value)) {
          error = 'Senha é obrigatória';
        } else if (!validarSenha(value, 6)) {
          error = 'Senha deve ter no mínimo 6 caracteres';
        }
        break;
      case 'cnpj':
        if (value && !validarCNPJ(value)) {
          error = 'CNPJ inválido';
        }
        break;
      case 'responsavel_nome':
        if (!validarObrigatorio(value)) {
          error = 'Nome do responsável é obrigatório';
        }
        break;
      case 'responsavel_cpf':
        if (!validarObrigatorio(value)) {
          error = 'CPF do responsável é obrigatório';
        } else if (!validarCPF(value)) {
          error = 'CPF inválido';
        }
        break;
      case 'responsavel_telefone':
        if (!validarObrigatorio(value)) {
          error = 'Telefone do responsável é obrigatório';
        }
        break;
      case 'responsavel_email':
        if (value && !validarEmail(value)) {
          error = 'E-mail inválido';
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
    if (!formData.nome.trim()) {
      showError('Nome da academia é obrigatório');
      return false;
    }
    if (!formData.email.trim()) {
      showError('E-mail é obrigatório');
      return false;
    }
    if (!formData.email.includes('@')) {
      showError('E-mail inválido');
      return false;
    }
    if (!formData.senha_admin || formData.senha_admin.length < 6) {
      showError('Senha do administrador é obrigatória (mínimo 6 caracteres)');
      return false;
    }
    if (formData.cnpj && formData.cnpj.replace(/\D/g, '').length !== 14) {
      showError('CNPJ inválido. Deve conter 14 dígitos');
      return false;
    }
    if (!formData.responsavel_nome.trim()) {
      showError('Nome do responsável é obrigatório');
      return false;
    }
    if (!formData.responsavel_cpf.trim()) {
      showError('CPF do responsável é obrigatório');
      return false;
    }
    if (!validarCPF(formData.responsavel_cpf)) {
      showError('CPF do responsável inválido');
      return false;
    }
    if (!formData.responsavel_telefone.trim()) {
      showError('Telefone do responsável é obrigatório');
      return false;
    }
    if (formData.responsavel_email && !validarEmail(formData.responsavel_email)) {
      showError('E-mail do responsável inválido');
      return false;
    }
    return true;
  };

  const handleSubmit = async () => {
    if (!validateForm()) return;

    setSaving(true);
    try {
      // Remover máscaras antes de enviar
      const dadosParaEnviar = {
        ...formData,
        cnpj: apenasNumeros(formData.cnpj),
        telefone: apenasNumeros(formData.telefone),
        responsavel_cpf: apenasNumeros(formData.responsavel_cpf),
        responsavel_telefone: apenasNumeros(formData.responsavel_telefone),
      };
      
      const result = await superAdminService.criarAcademia(dadosParaEnviar);
      showSuccess(result);
      router.push('/academias');
    } catch (error) {
      showError(error.response?.data?.error || 'Não foi possível cadastrar a academia');
    } finally {
      setSaving(false);
    }
  };

  return (
    <LayoutBase title="Nova Academia" subtitle="Preencha os campos obrigatórios">
      {saving && <LoadingOverlay message="Cadastrando academia..." />}
      
      <ScrollView style={styles.container}>
        {/* Botão Voltar */}
        <View style={styles.headerActions}>
          <TouchableOpacity 
            style={styles.backButton}
            onPress={() => router.push('/academias')}
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
              placeholderTextColor="#999"
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
                placeholderTextColor="#999"
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
              <Text style={styles.label}>Senha do Admin *</Text>
              <TextInput
                style={[styles.input, errors.senha_admin && touched.senha_admin && styles.inputError]}
                placeholder="Mínimo 6 caracteres"
                placeholderTextColor="#999"
                value={formData.senha_admin}
                onChangeText={(value) => handleChange('senha_admin', value)}
                onBlur={() => handleBlur('senha_admin')}
                secureTextEntry
                editable={!saving}
              />
              {errors.senha_admin && touched.senha_admin && (
                <Text style={styles.errorText}>{errors.senha_admin}</Text>
              )}
            </View>
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>CNPJ</Text>
              <TextInput
                style={[styles.input, errors.cnpj && touched.cnpj && styles.inputError]}
                placeholder="00.000.000/0000-00"
                placeholderTextColor="#999"
                value={formData.cnpj}
                onChangeText={(value) => handleChange('cnpj', mascaraCNPJ(value))}
                onBlur={() => handleBlur('cnpj')}
                keyboardType="numeric"
                maxLength={18}
                editable={!saving}
              />
              {errors.cnpj && touched.cnpj && (
                <Text style={styles.errorText}>{errors.cnpj}</Text>
              )}
            </View>

            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Telefone</Text>
              <TextInput
                style={styles.input}
                placeholder="(11) 99999-9999"
                placeholderTextColor="#999"
                value={formData.telefone}
                onChangeText={(value) => handleChange('telefone', mascaraTelefone(value))}
                keyboardType="phone-pad"
                maxLength={15}
                editable={!saving}
              />
            </View>
          </View>

          {/* Seção: Dados do Responsável */}
          <Text style={styles.sectionTitle}>Dados do Responsável</Text>
          
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Nome do Responsável *</Text>
            <TextInput
              style={[styles.input, errors.responsavel_nome && touched.responsavel_nome && styles.inputError]}
              placeholder="Nome completo do responsável"
              placeholderTextColor="#999"
              value={formData.responsavel_nome}
              onChangeText={(value) => handleChange('responsavel_nome', value)}
              onBlur={() => handleBlur('responsavel_nome')}
              editable={!saving}
            />
            {errors.responsavel_nome && touched.responsavel_nome && (
              <Text style={styles.errorText}>{errors.responsavel_nome}</Text>
            )}
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>CPF do Responsável *</Text>
              <TextInput
                style={[styles.input, errors.responsavel_cpf && touched.responsavel_cpf && styles.inputError]}
                placeholder="000.000.000-00"
                placeholderTextColor="#999"
                value={formData.responsavel_cpf}
                onChangeText={(value) => handleChange('responsavel_cpf', mascaraCPF(value))}
                onBlur={() => handleBlur('responsavel_cpf')}
                keyboardType="numeric"
                maxLength={14}
                editable={!saving}
              />
              {errors.responsavel_cpf && touched.responsavel_cpf && (
                <Text style={styles.errorText}>{errors.responsavel_cpf}</Text>
              )}
            </View>

            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Telefone do Responsável *</Text>
              <TextInput
                style={[styles.input, errors.responsavel_telefone && touched.responsavel_telefone && styles.inputError]}
                placeholder="(11) 99999-9999"
                placeholderTextColor="#999"
                value={formData.responsavel_telefone}
                onChangeText={(value) => handleChange('responsavel_telefone', mascaraTelefone(value))}
                onBlur={() => handleBlur('responsavel_telefone')}
                keyboardType="phone-pad"
                maxLength={15}
                editable={!saving}
              />
              {errors.responsavel_telefone && touched.responsavel_telefone && (
                <Text style={styles.errorText}>{errors.responsavel_telefone}</Text>
              )}
            </View>
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>E-mail do Responsável (Opcional)</Text>
            <TextInput
              style={[styles.input, errors.responsavel_email && touched.responsavel_email && styles.inputError]}
              placeholder="responsavel@email.com"
              placeholderTextColor="#999"
              value={formData.responsavel_email}
              onChangeText={(value) => handleChange('responsavel_email', value)}
              onBlur={() => handleBlur('responsavel_email')}
              keyboardType="email-address"
              autoCapitalize="none"
              editable={!saving}
            />
            {errors.responsavel_email && touched.responsavel_email && (
              <Text style={styles.errorText}>{errors.responsavel_email}</Text>
            )}
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
                  placeholderTextColor="#999"
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
                style={[styles.input, styles.inputDisabled]}
                placeholder="Preenchido automaticamente pelo CEP"
                placeholderTextColor="#999"
                value={formData.logradouro}
                editable={false}
              />
            </View>
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, { flex: 0.3 }]}>
              <Text style={styles.label}>Número</Text>
              <TextInput
                style={styles.input}
                placeholder="123"
                placeholderTextColor="#999"
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
                placeholderTextColor="#999"
                value={formData.complemento}
                onChangeText={(value) => handleChange('complemento', value)}
                editable={!saving}
              />
            </View>
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Bairro</Text>
            <TextInput
              style={[styles.input, styles.inputDisabled]}
              placeholder="Preenchido automaticamente pelo CEP"
              placeholderTextColor="#999"
              value={formData.bairro}
              editable={false}
            />
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, { flex: 0.7 }]}>
              <Text style={styles.label}>Cidade</Text>
              <TextInput
                style={[styles.input, styles.inputDisabled]}
                placeholder="Preenchido automaticamente pelo CEP"
                placeholderTextColor="#999"
                value={formData.cidade}
                editable={false}
              />
            </View>

            <View style={[styles.inputGroup, { flex: 0.3 }]}>
              <Text style={styles.label}>Estado</Text>
              <View style={[styles.pickerContainer, styles.inputDisabled]}>
                <Picker
                  selectedValue={formData.estado}
                  onValueChange={(value) => handleChange('estado', value)}
                  enabled={false}
                  style={styles.picker}
                >
                  <Picker.Item label="UF" value="" />
                  {estados.map((estado) => (
                    <Picker.Item
                      key={estado.sigla}
                      label={estado.sigla}
                      value={estado.sigla}
                    />
                  ))}
                </Picker>
              </View>
            </View>
          </View>

          {/* Info Box */}
        <View style={styles.infoBox}>
          <Text style={styles.infoTitle}>ℹ️ Informações importantes:</Text>
          <Text style={styles.infoText}>
            • A academia será criada com status ativo
          </Text>
          <Text style={styles.infoText}>
            • Um slug único será gerado automaticamente
          </Text>
          <Text style={styles.infoText}>
            • O usuário administrador será criado automaticamente
          </Text>
          <Text style={styles.infoText}>
            • O admin poderá fazer login com o e-mail e senha cadastrados
          </Text>
          <Text style={styles.infoText}>
            • O CNPJ deve conter 14 dígitos (apenas números)
          </Text>
          <Text style={styles.infoText}>
            • A associação ao plano do sistema será feita posteriormente
          </Text>
        </View>

        {/* Submit Button */}
        <TouchableOpacity
          style={[styles.submitButton, saving && styles.submitButtonDisabled]}
          onPress={handleSubmit}
          disabled={saving}
        >
          {saving ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.submitButtonText}>Cadastrar Academia</Text>
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
  inputDisabled: {
    backgroundColor: 'rgba(240, 240, 240, 0.9)',
    color: '#666',
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
  infoBox: {
    backgroundColor: '#E3F2FD',
    padding: 15,
    borderRadius: 8,
    marginBottom: 20,
  },
  infoTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1976D2',
    marginBottom: 10,
  },
  infoText: {
    fontSize: 13,
    color: '#1565C0',
    marginBottom: 5,
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
