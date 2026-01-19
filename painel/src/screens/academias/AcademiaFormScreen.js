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
import cepService from '../../services/cepService';
import estadosService from '../../services/estadosService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';
import { authService } from '../../services/authService';
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

export default function AcademiaFormScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const isEdit = !!id;
  const academiaId = id ? parseInt(id) : null;
  
  const [loading, setLoading] = useState(isEdit);
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
    ativo: true,
  });

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
    
    if (isEdit) {
      loadData();
    } else {
      loadEstados();
    }
  };

  const loadEstados = () => {
    const estadosList = estadosService.listarEstados();
    setEstados(estadosList);
  };

  const loadData = async () => {
    try {
      setLoading(true);
      
      // Carregar lista de estados
      loadEstados();
      
      // Carregar dados da academia
      const response = await superAdminService.buscarAcademia(academiaId);
      const academia = response.academia;
      
      setFormData({
        nome: academia.nome || '',
        email: academia.email || '',
        senha_admin: '', // Não carregar senha
        cnpj: academia.cnpj ? mascaraCNPJ(academia.cnpj) : '',
        telefone: academia.telefone ? mascaraTelefone(academia.telefone) : '',
        responsavel_nome: academia.responsavel_nome || '',
        responsavel_cpf: academia.responsavel_cpf ? mascaraCPF(academia.responsavel_cpf) : '',
        responsavel_telefone: academia.responsavel_telefone ? mascaraTelefone(academia.responsavel_telefone) : '',
        responsavel_email: academia.responsavel_email || '',
        cep: academia.cep || '',
        logradouro: academia.logradouro || '',
        numero: academia.numero || '',
        complemento: academia.complemento || '',
        bairro: academia.bairro || '',
        cidade: academia.cidade || '',
        estado: academia.estado || '',
        ativo: academia.ativo !== false,
      });
    } catch (error) {
      showError(error.response?.data?.error || 'Erro ao carregar dados da academia');
      router.push('/academias');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (field, value) => {
    // Aplicar máscaras
    let maskedValue = value;
    if (field === 'cnpj') maskedValue = mascaraCNPJ(value);
    if (field === 'telefone') maskedValue = mascaraTelefone(value);
    if (field === 'responsavel_cpf') maskedValue = mascaraCPF(value);
    if (field === 'responsavel_telefone') maskedValue = mascaraTelefone(value);
    
    setFormData({ ...formData, [field]: maskedValue });
    
    // Validar campo em tempo real se já foi tocado
    if (touched[field]) {
      validateField(field, maskedValue);
    }
  };

  const handleBlur = (field) => {
    setTouched({ ...touched, [field]: true });
    validateField(field, formData[field]);
    
    // Buscar CEP ao sair do campo
    if (field === 'cep' && formData.cep) {
      buscarCep(formData.cep);
    }
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
        if (!isEdit && !validarObrigatorio(value)) {
          error = 'Senha é obrigatória';
        } else if (value && !validarSenha(value, 6)) {
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
    if (!validarEmail(formData.email)) {
      showError('E-mail inválido');
      return false;
    }
    if (!isEdit && (!formData.senha_admin || formData.senha_admin.length < 6)) {
      showError('Senha do administrador é obrigatória (mínimo 6 caracteres)');
      return false;
    }
    if (formData.cnpj && !validarCNPJ(formData.cnpj)) {
      showError('CNPJ inválido');
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
      
      // Remover senha se estiver vazia na edição
      if (isEdit && !dadosParaEnviar.senha_admin) {
        delete dadosParaEnviar.senha_admin;
      }
      
      if (isEdit) {
        const result = await superAdminService.atualizarAcademia(academiaId, dadosParaEnviar);
        showSuccess(result);
      } else {
        const result = await superAdminService.criarAcademia(dadosParaEnviar);
        showSuccess(result);
      }
      
      router.push('/academias');
    } catch (error) {
      showError(error.response?.data?.error || `Não foi possível ${isEdit ? 'atualizar' : 'cadastrar'} a academia`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <LayoutBase title={isEdit ? "Editar Academia" : "Nova Academia"}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title={isEdit ? "Editar Academia" : "Nova Academia"} subtitle="Preencha os campos obrigatórios">
      {saving && <LoadingOverlay message={`${isEdit ? 'Atualizando' : 'Cadastrando'} academia...`} />}
      
      <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
        {/* Header com botão voltar e status */}
        <View style={styles.headerActions}>
          <TouchableOpacity 
            style={styles.backButton}
            onPress={() => router.push('/academias')}
            disabled={saving}
          >
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>

          {isEdit && (
            <View style={[styles.statusIndicator, formData.ativo ? styles.statusActive : styles.statusInactive]}>
              <View style={[styles.statusDot, formData.ativo ? styles.statusDotActive : styles.statusDotInactive]} />
              <Text style={[styles.statusIndicatorText, formData.ativo ? styles.statusTextActive : styles.statusTextInactive]}>
                {formData.ativo ? 'Academia Ativa' : 'Academia Inativa'}
              </Text>
            </View>
          )}
        </View>

        {/* Form Container */}
        <View style={styles.formContainer}>
          {/* Card: Dados da Academia */}
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="home" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Dados da Academia</Text>
            </View>
          
            <View style={styles.cardBody}>
              <View style={styles.inputGroup}>
                <Text style={styles.label}>Nome da Academia <Text style={styles.required}>*</Text></Text>
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
                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>E-mail <Text style={styles.required}>*</Text></Text>
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

                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>
                    {isEdit ? 'Nova Senha' : 'Senha do Admin'} {!isEdit && <Text style={styles.required}>*</Text>}
                  </Text>
                  <TextInput
                    style={[styles.input, errors.senha_admin && touched.senha_admin && styles.inputError]}
                    placeholder={isEdit ? "Deixe vazio para manter" : "Mínimo 6 caracteres"}
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
                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>CNPJ</Text>
                  <TextInput
                    style={[styles.input, errors.cnpj && touched.cnpj && styles.inputError]}
                    placeholder="00.000.000/0000-00"
                    placeholderTextColor="#999"
                    value={formData.cnpj}
                    onChangeText={(value) => handleChange('cnpj', value)}
                    onBlur={() => handleBlur('cnpj')}
                    keyboardType="numeric"
                    maxLength={18}
                    editable={!saving}
                  />
                  {errors.cnpj && touched.cnpj && (
                    <Text style={styles.errorText}>{errors.cnpj}</Text>
                  )}
                </View>

                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>Telefone</Text>
                  <TextInput
                    style={styles.input}
                    placeholder="(00) 00000-0000"
                    placeholderTextColor="#999"
                    value={formData.telefone}
                    onChangeText={(value) => handleChange('telefone', value)}
                    keyboardType="phone-pad"
                    maxLength={15}
                    editable={!saving}
                  />
                </View>
              </View>
            </View>
          </View>

          {/* Card: Dados do Responsável */}
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="user" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Dados do Responsável</Text>
            </View>
          
            <View style={styles.cardBody}>
              <View style={styles.inputGroup}>
                <Text style={styles.label}>Nome Completo <Text style={styles.required}>*</Text></Text>
                <TextInput
                  style={[styles.input, errors.responsavel_nome && touched.responsavel_nome && styles.inputError]}
                  placeholder="Nome do responsável pela academia"
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
                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>CPF <Text style={styles.required}>*</Text></Text>
                  <TextInput
                    style={[styles.input, errors.responsavel_cpf && touched.responsavel_cpf && styles.inputError]}
                    placeholder="000.000.000-00"
                    placeholderTextColor="#999"
                    value={formData.responsavel_cpf}
                    onChangeText={(value) => handleChange('responsavel_cpf', value)}
                    onBlur={() => handleBlur('responsavel_cpf')}
                    keyboardType="numeric"
                    maxLength={14}
                    editable={!saving}
                  />
                  {errors.responsavel_cpf && touched.responsavel_cpf && (
                    <Text style={styles.errorText}>{errors.responsavel_cpf}</Text>
                  )}
                </View>

                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>Telefone <Text style={styles.required}>*</Text></Text>
                  <TextInput
                    style={[styles.input, errors.responsavel_telefone && touched.responsavel_telefone && styles.inputError]}
                    placeholder="(00) 00000-0000"
                    placeholderTextColor="#999"
                    value={formData.responsavel_telefone}
                    onChangeText={(value) => handleChange('responsavel_telefone', value)}
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
                <Text style={styles.label}>E-mail</Text>
                <TextInput
                  style={[styles.input, errors.responsavel_email && touched.responsavel_email && styles.inputError]}
                  placeholder="email@responsavel.com"
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
            </View>
          </View>

          {/* Card: Endereço */}
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="map-pin" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Endereço</Text>
            </View>
          
            <View style={styles.cardBody}>
              <View style={styles.row}>
                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>CEP</Text>
                  <View style={styles.cepInputContainer}>
                    <TextInput
                      style={[styles.input, buscandoCep && styles.inputLoading, errors.cep && touched.cep && styles.inputError]}
                      placeholder="00000-000"
                      placeholderTextColor="#999"
                      value={formData.cep}
                      onChangeText={(value) => handleChange('cep', value)}
                      onBlur={() => handleBlur('cep')}
                      keyboardType="numeric"
                      maxLength={9}
                      editable={!saving && !buscandoCep}
                    />
                    {buscandoCep && (
                      <ActivityIndicator 
                        size="small" 
                        color="#f97316" 
                        style={styles.cepLoader}
                      />
                    )}
                  </View>
                  {errors.cep && touched.cep && (
                    <Text style={styles.errorText}>{errors.cep}</Text>
                  )}
                </View>

                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>Estado</Text>
                  <View style={styles.pickerContainer}>
                    <Picker
                      selectedValue={formData.estado}
                      onValueChange={(value) => handleChange('estado', value)}
                      style={styles.picker}
                      enabled={!saving}
                    >
                      <Picker.Item label="Selecione" value="" />
                      {estados.map((estado) => (
                        <Picker.Item 
                          key={estado.sigla} 
                          label={`${estado.sigla} - ${estado.nome}`} 
                          value={estado.sigla} 
                        />
                      ))}
                    </Picker>
                  </View>
                </View>
              </View>

              <View style={styles.inputGroup}>
                <Text style={styles.label}>Logradouro</Text>
                <TextInput
                  style={[styles.input, buscandoCep && styles.inputDisabled]}
                  placeholder="Rua, Avenida, etc."
                  placeholderTextColor="#999"
                  value={formData.logradouro}
                  onChangeText={(value) => handleChange('logradouro', value)}
                  editable={!saving && !buscandoCep}
                />
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
                    placeholder="Apto, Sala, etc."
                    placeholderTextColor="#999"
                    value={formData.complemento}
                    onChangeText={(value) => handleChange('complemento', value)}
                    editable={!saving}
                  />
                </View>
              </View>

              <View style={styles.row}>
                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>Bairro</Text>
                  <TextInput
                    style={[styles.input, buscandoCep && styles.inputDisabled]}
                    placeholder="Nome do bairro"
                    placeholderTextColor="#999"
                    value={formData.bairro}
                    onChangeText={(value) => handleChange('bairro', value)}
                    editable={!saving && !buscandoCep}
                  />
                </View>

                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>Cidade</Text>
                  <TextInput
                    style={[styles.input, buscandoCep && styles.inputDisabled]}
                    placeholder="Nome da cidade"
                    placeholderTextColor="#999"
                    value={formData.cidade}
                    onChangeText={(value) => handleChange('cidade', value)}
                    editable={!saving && !buscandoCep}
                  />
                </View>
              </View>
            </View>
          </View>

          {/* Card: Status (apenas edição) */}
          {isEdit && (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <View style={styles.cardHeaderIcon}>
                  <Feather name="toggle-right" size={20} color="#f97316" />
                </View>
                <Text style={styles.cardTitle}>Status da Academia</Text>
              </View>
            
              <View style={styles.cardBody}>
                <View style={styles.switchRow}>
                  <View style={styles.switchInfo}>
                    <Text style={styles.switchLabel}>Academia Ativa</Text>
                    <Text style={styles.switchDescription}>
                      {formData.ativo 
                        ? 'A academia está ativa e funcionando normalmente.' 
                        : 'A academia está desativada e não pode ser acessada.'}
                    </Text>
                  </View>
                  <Switch
                    value={formData.ativo}
                    onValueChange={(value) => handleChange('ativo', value)}
                    trackColor={{ false: '#d1d5db', true: '#86efac' }}
                    thumbColor={formData.ativo ? '#22c55e' : '#9ca3af'}
                    disabled={saving}
                  />
                </View>
              </View>
            </View>
          )}

          {/* Botões de Ação */}
          <View style={styles.actionButtons}>
            <TouchableOpacity
              style={styles.cancelButton}
              onPress={() => router.push('/academias')}
              disabled={saving}
            >
              <Text style={styles.cancelButtonText}>Cancelar</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.submitButton, saving && styles.submitButtonDisabled]}
              onPress={handleSubmit}
              disabled={saving}
            >
              <Feather name={saving ? 'loader' : 'check'} size={18} color="#fff" />
              <Text style={styles.submitButtonText}>
                {saving ? 'Salvando...' : isEdit ? 'Salvar Alterações' : 'Cadastrar Academia'}
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  container: {
    flex: 1,
    backgroundColor: 'transparent',
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
    paddingBottom: 40,
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
    gap: 12,
    paddingVertical: 14,
    paddingHorizontal: 20,
    backgroundColor: '#fef7f0',
    borderBottomWidth: 1,
    borderBottomColor: '#fed7aa',
  },
  cardHeaderIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: '#fff',
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  cardBody: {
    padding: 20,
  },
  row: {
    flexDirection: 'row',
    gap: 16,
  },
  flex1: {
    flex: 1,
  },
  inputGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 6,
  },
  required: {
    color: '#ef4444',
  },
  input: {
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    padding: 12,
    fontSize: 15,
    color: '#111827',
  },
  inputError: {
    borderColor: '#ef4444',
    borderWidth: 2,
    backgroundColor: '#fef2f2',
  },
  inputDisabled: {
    backgroundColor: '#f3f4f6',
    color: '#6b7280',
  },
  inputLoading: {
    backgroundColor: '#f9fafb',
  },
  errorText: {
    color: '#ef4444',
    fontSize: 12,
    marginTop: 4,
  },
  cepInputContainer: {
    position: 'relative',
  },
  cepLoader: {
    position: 'absolute',
    right: 12,
    top: 12,
  },
  pickerContainer: {
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    overflow: 'hidden',
  },
  picker: {
    height: 48,
    color: '#111827',
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  switchInfo: {
    flex: 1,
    marginRight: 16,
  },
  switchLabel: {
    fontSize: 15,
    fontWeight: '600',
    color: '#111827',
  },
  switchDescription: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 4,
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  cancelButton: {
    flex: 1,
    paddingVertical: 14,
    paddingHorizontal: 20,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    alignItems: 'center',
  },
  cancelButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#6b7280',
  },
  submitButton: {
    flex: 2,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    paddingHorizontal: 20,
    backgroundColor: '#f97316',
    borderRadius: 10,
  },
  submitButtonDisabled: {
    backgroundColor: '#fdba74',
  },
  submitButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: '#fff',
  },
});
