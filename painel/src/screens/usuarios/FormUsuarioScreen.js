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
import { useRouter, useLocalSearchParams } from 'expo-router';
import usuarioService from '../../services/usuarioService';
import estadosService from '../../services/estadosService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';
import { mascaraTelefone } from '../../utils/masks';
import api from '../../services/api';
import BuscarUsuarioCpfModal from '../../components/BuscarUsuarioCpfModal';

export default function FormUsuarioScreen() {
  const router = useRouter();
  const { id, cpf: cpfParam } = useLocalSearchParams();
  const usuarioId = id ? parseInt(id) : null;
  const isEdit = !!usuarioId;
  
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [estados, setEstados] = useState([]);
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);
  const [buscarCpfModalVisible, setBuscarCpfModalVisible] = useState(!isEdit); // Abre automaticamente no modo de criação
  const [usuarioEncontrado, setUsuarioEncontrado] = useState(null); // Armazena dados do usuário encontrado
  const [confirmarAssociacao, setConfirmarAssociacao] = useState(false);
  const [formData, setFormData] = useState({
    nome: '',
    email: '',
    senha: '',
    telefone: '',
    cpf: cpfParam || '',
    cep: '',
    logradouro: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    estado: '',
    papel_id: 1,
  });
  const [errors, setErrors] = useState({});
  const [loadingCep, setLoadingCep] = useState(false);

  useEffect(() => {
    const estadosList = estadosService.listarEstados();
    setEstados(estadosList);
    if (isEdit) {
      loadUsuario();
    }
  }, []);

  const loadUsuario = async () => {
    try {
      setLoading(true);
      
      // Verificar role do usuário logado
      let isSuperAdminUser = false;
      try {
        const meResponse = await api.get('/me');
        isSuperAdminUser = meResponse.data?.papel_id === 4;
        setIsSuperAdmin(isSuperAdminUser);
      } catch (error) {
        console.log('Não foi possível verificar role do usuário');
      }
      
      // Tentar buscar com rota SuperAdmin primeiro, se falhar usa rota normal
      let responseUsuario;
      try {
        responseUsuario = await usuarioService.buscar(usuarioId, isSuperAdminUser);
      } catch (error) {
        if (isSuperAdminUser) {
          responseUsuario = await usuarioService.buscar(usuarioId, false);
        } else {
          throw error;
        }
      }
      
      const usuario = responseUsuario;
      
      setFormData({
        nome: usuario.nome || '',
        email: usuario.email || '',
        senha: '',
        telefone: usuario.telefone || '',
        cpf: usuario.cpf ? formatCPF(usuario.cpf) : '',
        cep: usuario.cep ? formatCEP(usuario.cep) : '',
        logradouro: usuario.logradouro || '',
        numero: usuario.numero || '',
        complemento: usuario.complemento || '',
        bairro: usuario.bairro || '',
        cidade: usuario.cidade || '',
        estado: usuario.estado || '',
        papel_id: usuario.papel_id || 1,
      });
    } catch (error) {
      console.error('❌ Erro ao carregar usuário:', error);
      showError(error.error || error.message || 'Não foi possível carregar os dados do usuário');
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

  const formatCPF = (value) => {
    const cleaned = value.replace(/\D/g, '');
    const match = cleaned.match(/^(\d{0,3})(\d{0,3})(\d{0,3})(\d{0,2})$/);
    if (match) {
      return !match[2] ? match[1] : `${match[1]}.${match[2]}${match[3] ? `.${match[3]}` : ''}${match[4] ? `-${match[4]}` : ''}`;
    }
    return value;
  };

  const formatCEP = (value) => {
    const cleaned = value.replace(/\D/g, '');
    const match = cleaned.match(/^(\d{0,5})(\d{0,3})$/);
    if (match) {
      return !match[2] ? match[1] : `${match[1]}-${match[2]}`;
    }
    return value;
  };

  const handleCPFChange = (value) => {
    const formatted = formatCPF(value);
    handleChange('cpf', formatted);
  };

  const handleCEPChange = async (value) => {
    const formatted = formatCEP(value);
    handleChange('cep', formatted);

    const cleaned = value.replace(/\D/g, '');
    if (cleaned.length === 8) {
      await buscarCEP(cleaned);
    }
  };

  const buscarCEP = async (cep) => {
    try {
      setLoadingCep(true);
      const response = await api.get(`/cep/${cep}`);
      
      if (response.data) {
        // Verificar se é um warning (CEP válido mas sem dados)
        if (response.data.type === 'warning') {
          showError(response.data.message || 'CEP válido, mas não há dados disponíveis');
          return;
        }
        
        // Verificar se tem dados válidos
        if (response.data.data && !response.data.data.erro) {
          const dados = response.data.data;
          
          // Verificar se os campos essenciais estão preenchidos
          if (!dados.logradouro && !dados.bairro && !dados.cidade) {
            showError('CEP encontrado, mas sem dados de endereço disponíveis');
            return;
          }
          
          setFormData(prev => ({
            ...prev,
            logradouro: dados.logradouro || '',
            bairro: dados.bairro || '',
            cidade: dados.cidade || '',
            estado: dados.estado || '',
          }));
          showSuccess('CEP encontrado!');
        }
      }
    } catch (error) {
      showError('CEP não encontrado');
    } finally {
      setLoadingCep(false);
    }
  };

  const validateCPF = (cpf) => {
    const cleaned = cpf.replace(/\D/g, '');
    
    if (cleaned.length !== 11) return false;
    
    // Verifica se todos os dígitos são iguais
    if (/^(\d)\1{10}$/.test(cleaned)) return false;
    
    // Valida primeiro dígito verificador
    let sum = 0;
    for (let i = 0; i < 9; i++) {
      sum += parseInt(cleaned.charAt(i)) * (10 - i);
    }
    let digit = 11 - (sum % 11);
    if (digit >= 10) digit = 0;
    if (digit !== parseInt(cleaned.charAt(9))) return false;
    
    // Valida segundo dígito verificador
    sum = 0;
    for (let i = 0; i < 10; i++) {
      sum += parseInt(cleaned.charAt(i)) * (11 - i);
    }
    digit = 11 - (sum % 11);
    if (digit >= 10) digit = 0;
    if (digit !== parseInt(cleaned.charAt(10))) return false;
    
    return true;
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.nome.trim()) {
      newErrors.nome = 'Nome é obrigatório';
    }

    if (!formData.email.trim()) {
      newErrors.email = 'E-mail é obrigatório';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'E-mail inválido';
    }

    if (!formData.telefone.trim()) {
      newErrors.telefone = 'Telefone é obrigatório';
    }

    if (!formData.cpf.trim()) {
      newErrors.cpf = 'CPF é obrigatório';
    } else if (!validateCPF(formData.cpf)) {
      newErrors.cpf = 'CPF inválido';
    }

    if (!formData.cep.trim()) {
      newErrors.cep = 'CEP é obrigatório';
    }

    if (!formData.logradouro.trim()) {
      newErrors.logradouro = 'Logradouro é obrigatório';
    }

    if (!formData.numero.trim()) {
      newErrors.numero = 'Número é obrigatório';
    }

    if (!formData.bairro.trim()) {
      newErrors.bairro = 'Bairro é obrigatório';
    }

    if (!formData.cidade.trim()) {
      newErrors.cidade = 'Cidade é obrigatória';
    }

    if (!formData.estado.trim()) {
      newErrors.estado = 'Estado é obrigatório';
    }

    if (!isEdit && !formData.senha.trim()) {
      newErrors.senha = 'Senha é obrigatória';
    } else if (formData.senha && formData.senha.length < 6) {
      newErrors.senha = 'Senha deve ter no mínimo 6 caracteres';
    }

    setErrors(newErrors);
    
    if (Object.keys(newErrors).length > 0) {
      showError('Preencha todos os campos obrigatórios corretamente');
      return false;
    }
    
    return true;
  };

  const handleUsuarioEncontrado = (usuario) => {
    console.log('📥 handleUsuarioEncontrado - Dados recebidos:', usuario);
    // Preencher formulário com dados do usuário encontrado
    setUsuarioEncontrado(usuario);
    console.log('✅ Estado usuarioEncontrado setado:', usuario);
    setFormData({
      nome: usuario.nome || '',
      email: usuario.email || '',
      senha: '',
      telefone: usuario.telefone || '',
      cpf: usuario.cpf ? formatCPF(usuario.cpf) : '',
      cep: usuario.cep ? formatCEP(usuario.cep) : '',
      logradouro: usuario.logradouro || '',
      numero: usuario.numero || '',
      complemento: usuario.complemento || '',
      bairro: usuario.bairro || '',
      cidade: usuario.cidade || '',
      estado: usuario.estado || '',
      papel_id: usuario.papel_id || 1,
    });
    setBuscarCpfModalVisible(false);
    showSuccess('Usuário encontrado! Dados preenchidos automaticamente.');
  };

  const handleCriarNovoUsuario = () => {
    // Fecha a modal e permite criar um novo usuário
    setUsuarioEncontrado(null);
    setBuscarCpfModalVisible(false);
  };

  const handleAssociarAluno = () => {
    console.log('🔗 handleAssociarAluno chamado');
    console.log('🔗 Estado usuarioEncontrado atual:', usuarioEncontrado);
    if (!usuarioEncontrado) {
      console.error('❌ usuarioEncontrado está vazio!');
      showError('Erro: Dados do usuário não encontrados');
      return;
    }
    console.log('✅ Abrindo modal de confirmação');
    setConfirmarAssociacao(true);
  };

  const confirmarAssociacaoAluno = async () => {
    console.log('✅ Confirmando associação do aluno:', usuarioEncontrado);
    setConfirmarAssociacao(false);
    setSaving(true);
    try {
      console.log('📤 Enviando requisição POST /tenant/usuarios/associar');
      const response = await api.post('/tenant/usuarios/associar', {
        usuario_id: usuarioEncontrado.id,
      });

      console.log('📥 Resposta da API:', response.data);
      if (response.data) {
        showSuccess('Aluno associado com sucesso!');
        router.push('/usuarios');
      }
    } catch (error) {
      console.error('❌ Erro ao associar:', error);
      showError(error.response?.data?.error || 'Não foi possível associar o aluno');
    } finally {
      setSaving(false);
    }
  };

  const handleSubmit = async () => {
    if (!validateForm()) return;

    setSaving(true);
    try {
      const payload = { ...formData };
      
      if (isEdit) {
        // Remover senha do payload se estiver vazia
        if (!payload.senha) {
          delete payload.senha;
        }
        await usuarioService.atualizar(usuarioId, payload, isSuperAdmin);
        showSuccess('Usuário atualizado com sucesso');
      } else {
        await usuarioService.criar(payload);
        showSuccess('Usuário cadastrado com sucesso');
      }
      
      router.push('/usuarios');
    } catch (error) {
      showError(error.errors?.join('\n') || error.error || `Não foi possível ${isEdit ? 'atualizar' : 'cadastrar'} o usuário`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <LayoutBase title={isEdit ? "Editar Usuário" : "Novo Usuário"} subtitle="Carregando...">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando dados...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase 
      title={isEdit ? "Editar Usuário" : "Novo Usuário"} 
      subtitle={isEdit ? "Atualizar informações" : "Cadastrar novo usuário"}
    >
      {saving && <LoadingOverlay message={`${isEdit ? 'Atualizando' : 'Cadastrando'} usuário...`} />}
      
      <ScrollView style={styles.container}>
        {/* Botão Voltar */}
        <View style={styles.headerActions}>
          <TouchableOpacity 
            style={styles.backButton}
            onPress={() => router.push('/usuarios')}
            disabled={saving}
          >
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>
        </View>

        {/* Form */}
        <View style={styles.form}>
          {/* Seção: Dados do Usuário */}
          <Text style={styles.sectionTitle}>Dados do Usuário</Text>
          
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Nome Completo *</Text>
            <TextInput
              style={[styles.input, errors.nome && styles.inputError]}
              placeholder="Ex: João Silva"
              placeholderTextColor="#aaa"
              value={formData.nome}
              onChangeText={(value) => handleChange('nome', value)}
              editable={!saving}
            />
            {errors.nome && <Text style={styles.errorText}>{errors.nome}</Text>}
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.thirdWidth]}>
              <Text style={styles.label}>E-mail *</Text>
              <TextInput
                style={[styles.input, errors.email && styles.inputError]}
                placeholder="email@exemplo.com"
                placeholderTextColor="#aaa"
                value={formData.email}
                onChangeText={(value) => handleChange('email', value)}
                keyboardType="email-address"
                autoCapitalize="none"
                editable={!saving}
              />
              {errors.email && <Text style={styles.errorText}>{errors.email}</Text>}
            </View>

            <View style={[styles.inputGroup, styles.thirdWidth]}>
              <Text style={styles.label}>Telefone *</Text>
              <TextInput
                style={[styles.input, errors.telefone && styles.inputError]}
                placeholder="(11) 99999-9999"
                placeholderTextColor="#aaa"
                value={formData.telefone}
                onChangeText={(value) => handleChange('telefone', mascaraTelefone(value))}
                keyboardType="phone-pad"
                editable={!saving}
              />
              {errors.telefone && <Text style={styles.errorText}>{errors.telefone}</Text>}
            </View>

            <View style={[styles.inputGroup, styles.thirdWidth]}>
              <Text style={styles.label}>CPF *</Text>
              <TextInput
                style={[styles.input, errors.cpf && styles.inputError]}
                placeholder="000.000.000-00"
                placeholderTextColor="#aaa"
                value={formData.cpf}
                onChangeText={handleCPFChange}
                keyboardType="numeric"
                maxLength={14}
                editable={!saving}
              />
              {errors.cpf && <Text style={styles.errorText}>{errors.cpf}</Text>}
            </View>
          </View>

          {/* Seção: Endereço */}
          <Text style={styles.sectionTitle}>Endereço</Text>

          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>CEP *</Text>
              <View style={styles.inputWithIcon}>
                <TextInput
                  style={[styles.input, errors.cep && styles.inputError]}
                  placeholder="00000-000"
                  placeholderTextColor="#aaa"
                  value={formData.cep}
                  onChangeText={handleCEPChange}
                  keyboardType="numeric"
                  maxLength={9}
                  editable={!saving}
                />
                {loadingCep && (
                  <ActivityIndicator 
                    size="small" 
                    color="#f97316" 
                    style={styles.inputIcon}
                  />
                )}
              </View>
              {errors.cep && <Text style={styles.errorText}>{errors.cep}</Text>}
            </View>

            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Número *</Text>
              <TextInput
                style={[styles.input, errors.numero && styles.inputError]}
                placeholder="123"
                placeholderTextColor="#aaa"
                value={formData.numero}
                onChangeText={(value) => handleChange('numero', value)}
                editable={!saving}
              />
              {errors.numero && <Text style={styles.errorText}>{errors.numero}</Text>}
            </View>
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Logradouro *</Text>
            <TextInput
              style={[styles.input, errors.logradouro && styles.inputError, styles.inputDisabled]}
              placeholder="Rua, Avenida, etc."
              placeholderTextColor="#aaa"
              value={formData.logradouro}
              onChangeText={(value) => handleChange('logradouro', value)}
              editable={false}
            />
            {errors.logradouro && <Text style={styles.errorText}>{errors.logradouro}</Text>}
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Complemento</Text>
            <TextInput
              style={styles.input}
              placeholder="Apartamento, Bloco, etc."
              placeholderTextColor="#aaa"
              value={formData.complemento}
              onChangeText={(value) => handleChange('complemento', value)}
              editable={!saving}
            />
          </View>

          <View style={styles.row}>
            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Bairro *</Text>
              <TextInput
                style={[styles.input, errors.bairro && styles.inputError, styles.inputDisabled]}
                placeholder="Nome do bairro"
                placeholderTextColor="#aaa"
                value={formData.bairro}
                onChangeText={(value) => handleChange('bairro', value)}
                editable={false}
              />
              {errors.bairro && <Text style={styles.errorText}>{errors.bairro}</Text>}
            </View>

            <View style={[styles.inputGroup, styles.halfWidth]}>
              <Text style={styles.label}>Cidade *</Text>
              <TextInput
                style={[styles.input, errors.cidade && styles.inputError, styles.inputDisabled]}
                placeholder="Nome da cidade"
                placeholderTextColor="#aaa"
                value={formData.cidade}
                onChangeText={(value) => handleChange('cidade', value)}
                editable={false}
              />
              {errors.cidade && <Text style={styles.errorText}>{errors.cidade}</Text>}
            </View>
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Estado (UF) *</Text>
            <View style={[styles.pickerContainer, errors.estado && styles.inputError, styles.inputDisabled]}>
              <Picker
                selectedValue={formData.estado}
                onValueChange={(value) => handleChange('estado', value)}
                enabled={false}
                style={styles.picker}
              >
                <Picker.Item label="Selecione o estado" value="" />
                {estados.map((estado) => (
                  <Picker.Item
                    key={estado.sigla}
                    label={`${estado.sigla} - ${estado.nome}`}
                    value={estado.sigla}
                  />
                ))}
              </Picker>
            </View>
            {errors.estado && <Text style={styles.errorText}>{errors.estado}</Text>}
          </View>

          {/* Seção: Acesso */}
          <Text style={styles.sectionTitle}>Acesso</Text>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>
              {isEdit ? 'Nova Senha (deixe em branco para manter)' : 'Senha *'}
            </Text>
            <TextInput
              style={[styles.input, errors.senha && styles.inputError]}
              placeholder="Mínimo 6 caracteres"
              placeholderTextColor="#aaa"
              value={formData.senha}
              onChangeText={(value) => handleChange('senha', value)}
              secureTextEntry
              editable={!saving}
            />
            {errors.senha && <Text style={styles.errorText}>{errors.senha}</Text>}
          </View>

          {/* Submit Button */}
          <TouchableOpacity
            style={[styles.submitButton, saving && styles.submitButtonDisabled]}
            onPress={usuarioEncontrado ? handleAssociarAluno : handleSubmit}
            disabled={saving}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <>
                <Feather name={usuarioEncontrado ? "user-check" : (isEdit ? "save" : "user-plus")} size={18} color="#fff" />
                <Text style={styles.submitButtonText}>
                  {usuarioEncontrado ? 'Associar Aluno' : (isEdit ? 'Atualizar Usuário' : 'Cadastrar Usuário')}
                </Text>
              </>
            )}
          </TouchableOpacity>
        </View>
      </ScrollView>

      {/* Modal de busca por CPF */}
      <BuscarUsuarioCpfModal
        visible={buscarCpfModalVisible}
        onClose={() => setBuscarCpfModalVisible(false)}
        onUsuarioEncontrado={handleUsuarioEncontrado}
        onCriarNovoUsuario={handleCriarNovoUsuario}
      />

      {/* Modal de confirmação de associação */}
      <ConfirmModal
        visible={confirmarAssociacao}
        title="Confirmar Associação"
        message={`Deseja associar o aluno ${usuarioEncontrado?.nome || ''} a esta academia?`}
        onConfirm={confirmarAssociacaoAluno}
        onCancel={() => setConfirmarAssociacao(false)}
      />
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
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    paddingHorizontal: 16,
    backgroundColor: '#f97316',
    borderRadius: 8,
    alignSelf: 'flex-start',
  },
  backButtonText: {
    fontSize: 14,
    color: '#fff',
    fontWeight: '600',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666',
  },
  form: {
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderRadius: 12,
    padding: 20,
    paddingTop: 10,
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
  thirdWidth: {
    flex: 1,
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
  },
  inputDisabled: {
    backgroundColor: '#f3f4f6',
    opacity: 0.7,
  },
  errorText: {
    color: '#ef4444',
    fontSize: 12,
    marginTop: 4,
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
  inputWithIcon: {
    position: 'relative',
  },
  inputIcon: {
    position: 'absolute',
    right: 12,
    top: 15,
  },
  submitButton: {
    backgroundColor: '#f97316',
    padding: 16,
    borderRadius: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginTop: 30,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  submitButtonDisabled: {
    backgroundColor: '#9ca3af',
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
});
