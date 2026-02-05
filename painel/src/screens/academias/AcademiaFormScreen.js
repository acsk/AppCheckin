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
  Modal,
  Pressable,
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
  
  // Estados para gestão de admins
  const [admins, setAdmins] = useState([]);
  const [loadingAdmins, setLoadingAdmins] = useState(false);
  const [showAdminModal, setShowAdminModal] = useState(false);
  const [editingAdmin, setEditingAdmin] = useState(null);
  const [papeisList, setPapeisList] = useState([]);
  const [adminForm, setAdminForm] = useState({
    nome: '',
    email: '',
    senha: '',
    telefone: '',
    cpf: '',
    papeis: [3], // Admin é obrigatório
  });
  const [adminErrors, setAdminErrors] = useState({});
  
  // Estados para modal de desativação de admin
  const [showConfirmDeactivate, setShowConfirmDeactivate] = useState(false);
  const [adminToDeactivate, setAdminToDeactivate] = useState(null);
  const [papelsToDeactivate, setPapelsToDeactivate] = useState([]);
  const [papelsDisponiveisParaDesativar, setPapelsDisponiveisParaDesativar] = useState([]);
  const [deactivatingAdmin, setDeactivatingAdmin] = useState(false);
  
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
    // Carregar papéis padrão PRIMEIRO
    loadPapeisPadrao();
    
    // Depois verificar acesso
    checkAccess();
  }, []);

  const checkAccess = async () => {
    const user = await authService.getCurrentUser();
    if (!user || user.papel_id !== 4) {
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
      
      // Carregar admins da academia
      loadAdmins();
    } catch (error) {
      showError(error.response?.data?.error || 'Erro ao carregar dados da academia');
      router.push('/academias');
    } finally {
      setLoading(false);
    }
  };

  const loadPapeisPadrao = () => {
    // Papéis padrão do sistema
    const papeisPadrao = [
      {
        id: 1,
        nome: 'Aluno',
        descricao: 'Pode acessar o app mobile e fazer check-in'
      },
      {
        id: 2,
        nome: 'Professor',
        descricao: 'Pode marcar presença e gerenciar turmas'
      },
      {
        id: 3,
        nome: 'Admin',
        descricao: 'Pode acessar o painel administrativo'
      }
    ];
    setPapeisList(papeisPadrao);
  };

  const loadPapeis = async () => {
    // Usar papéis padrão ao abrir modal
    loadPapeisPadrao();
  };

  const loadAdmins = async () => {
    if (!academiaId) return;
    
    try {
      setLoadingAdmins(true);
      const response = await superAdminService.listarAdmins(academiaId);
      const adminsList = response.admins || response || [];
      
      // Log para debug
      console.log('👥 Admins carregados:', adminsList);
      console.log('📋 Papéis disponíveis:', papeisList);
      
      setAdmins(adminsList);
    } catch (error) {
      console.error('Erro ao carregar admins:', error);
      showError('Erro ao carregar lista de administradores');
    } finally {
      setLoadingAdmins(false);
    }
  };

  const openAdminModal = (admin = null) => {
    if (admin) {
      setEditingAdmin(admin);
      
      // Extrair IDs dos papéis (se vierem como objetos ou como IDs)
      const papelIds = admin.papeis
        ? admin.papeis.map(p => typeof p === 'object' ? p.id : p)
        : [3];

      if (!papelIds.includes(3)) {
        papelIds.push(3);
      }
      
      setAdminForm({
        nome: admin.nome || '',
        email: admin.email || '',
        senha: '',
        telefone: admin.telefone ? mascaraTelefone(admin.telefone) : '',
        cpf: admin.cpf ? mascaraCPF(admin.cpf) : '',
        papeis: papelIds,
      });
    } else {
      setEditingAdmin(null);
      setAdminForm({
        nome: '',
        email: '',
        senha: '',
        telefone: '',
        cpf: '',
        papeis: [3],
      });
    }
    setAdminErrors({});
    setShowAdminModal(true);
  };

  const closeAdminModal = () => {
    setShowAdminModal(false);
    setEditingAdmin(null);
    setAdminForm({
      nome: '',
      email: '',
      senha: '',
      telefone: '',
      cpf: '',
      papeis: [3],
    });
    setAdminErrors({});
  };

  const validateAdminForm = () => {
    const errors = {};
    
    if (!validarObrigatorio(adminForm.nome)) {
      errors.nome = 'Nome é obrigatório';
    }
    
    if (!validarObrigatorio(adminForm.email)) {
      errors.email = 'E-mail é obrigatório';
    } else if (!validarEmail(adminForm.email)) {
      errors.email = 'E-mail inválido';
    }
    
    if (!editingAdmin && !validarObrigatorio(adminForm.senha)) {
      errors.senha = 'Senha é obrigatória';
    } else if (adminForm.senha && !validarSenha(adminForm.senha, 6)) {
      errors.senha = 'Senha deve ter no mínimo 6 caracteres';
    }
    
    if (adminForm.telefone && apenasNumeros(adminForm.telefone).length < 10) {
      errors.telefone = 'Telefone inválido';
    }
    
    if (adminForm.cpf && !validarCPF(adminForm.cpf)) {
      errors.cpf = 'CPF inválido';
    }
    
    setAdminErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSaveAdmin = async () => {
    if (!validateAdminForm()) {
      showError('Corrija os erros antes de salvar');
      return;
    }
    
    try {
      setSaving(true);
      
      const papeisComAdmin = adminForm.papeis.includes(3)
        ? adminForm.papeis
        : [...adminForm.papeis, 3];

      const payload = {
        nome: adminForm.nome,
        email: adminForm.email,
        telefone: apenasNumeros(adminForm.telefone),
        cpf: apenasNumeros(adminForm.cpf),
        papeis: papeisComAdmin,
      };
      
      if (adminForm.senha) {
        payload.senha = adminForm.senha;
      }
      
      if (editingAdmin) {
        await superAdminService.atualizarAdmin(academiaId, editingAdmin.id, payload);
        showSuccess('Administrador atualizado com sucesso');
      } else {
        await superAdminService.criarAdmin(academiaId, payload);
        showSuccess('Administrador criado com sucesso');
      }
      
      closeAdminModal();
      loadAdmins();
    } catch (error) {
      showError(error.response?.data?.error || 'Erro ao salvar administrador');
    } finally {
      setSaving(false);
    }
  };

  const getPapelNome = (papelId) => {
    if (papeisList.length > 0) {
      const papelObj = papeisList.find(p => p.id === papelId);
      if (papelObj) return papelObj.nome;
    }
    const papelMap = {
      1: 'Aluno',
      2: 'Professor',
      3: 'Admin'
    };
    return papelMap[papelId] || 'Papel desconhecido';
  };

  const closeConfirmDeactivateModal = () => {
    setShowConfirmDeactivate(false);
    setAdminToDeactivate(null);
    setPapelsToDeactivate([]);
    setPapelsDisponiveisParaDesativar([]);
  };

  const handleToggleAdminStatus = async (admin) => {
    if (admin.ativo) {
      // Mostrar modal de confirmação ao desativar
      setAdminToDeactivate(admin);

      const papelIds = admin.papeis
        ? admin.papeis.map(p => typeof p === 'object' ? p.id : p)
        : [];

      const papelIdsUnicos = Array.from(new Set(papelIds));
      if (!papelIdsUnicos.includes(3)) {
        papelIdsUnicos.push(3);
      }

      const papelIdsFinal = papelIdsUnicos.length > 0 ? papelIdsUnicos : [3];

      setPapelsDisponiveisParaDesativar(papelIdsFinal);
      setPapelsToDeactivate(papelIdsFinal);
      setShowConfirmDeactivate(true);
    } else {
      // Reativar direto sem confirmação
      try {
        setDeactivatingAdmin(true);
        await superAdminService.reativarAdmin(academiaId, admin.id);
        showSuccess('Administrador reativado com sucesso');
        loadAdmins();
      } catch (error) {
        showError(error.response?.data?.error || 'Erro ao reativar administrador');
      } finally {
        setDeactivatingAdmin(false);
      }
    }
  };

  const togglePapelToDeactivate = (papelId) => {
    if (papelId === 3) return;

    setPapelsToDeactivate((prev) => {
      if (prev.includes(papelId)) {
        return prev.filter((id) => id !== papelId);
      }
      return [...prev, papelId];
    });
  };

  const handleConfirmDeactivate = async () => {
    if (!adminToDeactivate) return;
    
    try {
      setDeactivatingAdmin(true);
      const papeisSelecionados = papelsToDeactivate.includes(3)
        ? papelsToDeactivate
        : [...papelsToDeactivate, 3];

      await superAdminService.desativarAdmin(academiaId, adminToDeactivate.id, {
        papeis: papeisSelecionados
      });
      showSuccess('Administrador desativado com sucesso');
      loadAdmins();
      closeConfirmDeactivateModal();
    } catch (error) {
      showError(error.response?.data?.error || 'Erro ao desativar administrador');
    } finally {
      setDeactivatingAdmin(false);
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

          {/* Card: Administradores (apenas edição) */}
          {isEdit && (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <View style={styles.cardHeaderIcon}>
                  <Feather name="users" size={20} color="#f97316" />
                </View>
                <Text style={styles.cardTitle}>Administradores</Text>
                <TouchableOpacity
                  style={styles.addButton}
                  onPress={() => openAdminModal()}
                  disabled={saving}
                >
                  <Feather name="plus" size={16} color="#fff" />
                  <Text style={styles.addButtonText}>Novo</Text>
                </TouchableOpacity>
              </View>
            
              <View style={styles.cardBody}>
                {loadingAdmins ? (
                  <View style={styles.loadingAdmins}>
                    <ActivityIndicator size="small" color="#f97316" />
                    <Text style={styles.loadingAdminsText}>Carregando administradores...</Text>
                  </View>
                ) : admins.length === 0 ? (
                  <View style={styles.emptyAdmins}>
                    <Feather name="users" size={32} color="#d1d5db" />
                    <Text style={styles.emptyAdminsText}>Nenhum administrador cadastrado</Text>
                  </View>
                ) : (
                  <View style={styles.adminsList}>
                    {admins.map((admin) => (
                      <View key={admin.id} style={styles.adminCard}>
                        <View style={styles.adminInfo}>
                          <View style={styles.adminAvatar}>
                            <Feather name="user" size={18} color="#f97316" />
                          </View>
                          <View style={styles.adminDetails}>
                            <Text style={styles.adminName}>{admin.nome}</Text>
                            <View style={styles.adminPapeis}>
                              {admin.papeis && admin.papeis.length > 0 ? (
                                admin.papeis.map((papel) => {
                                  // Verificar se papel é um objeto ou um ID
                                  const papelId = typeof papel === 'object' ? papel.id : papel;
                                  const papelNome = typeof papel === 'object' ? papel.nome : null;
                                  
                                  // Se papel é um objeto, usar o nome direto
                                  if (papelNome) {
                                    return (
                                      <View key={papelId} style={styles.adminPapelBadge}>
                                        <Text style={styles.adminPapelText}>{papelNome}</Text>
                                      </View>
                                    );
                                  }
                                  
                                  // Se papel é um ID, procurar no papeisList
                                  if (papeisList.length > 0) {
                                    const papelObj = papeisList.find(p => p.id === papelId);
                                    if (papelObj) {
                                      return (
                                        <View key={papelId} style={styles.adminPapelBadge}>
                                          <Text style={styles.adminPapelText}>{papelObj.nome}</Text>
                                        </View>
                                      );
                                    }
                                  } else {
                                    // Fallback com mapeamento padrão
                                    const papelMap = {
                                      1: 'Aluno',
                                      2: 'Professor',
                                      3: 'Admin'
                                    };
                                    const nomePadrao = papelMap[papelId];
                                    if (nomePadrao) {
                                      return (
                                        <View key={papelId} style={styles.adminPapelBadge}>
                                          <Text style={styles.adminPapelText}>{nomePadrao}</Text>
                                        </View>
                                      );
                                    }
                                  }
                                  return null;
                                })
                              ) : (
                                <View style={styles.adminPapelBadge}>
                                  <Text style={styles.adminPapelText}>Admin</Text>
                                </View>
                              )}
                            </View>
                            <Text style={styles.adminEmail}>{admin.email}</Text>
                            {admin.telefone && (
                              <Text style={styles.adminPhone}>{mascaraTelefone(admin.telefone)}</Text>
                            )}
                          </View>
                          <View style={[styles.adminStatus, admin.ativo ? styles.adminStatusActive : styles.adminStatusInactive]}>
                            <Text style={[styles.adminStatusText, admin.ativo ? styles.adminStatusTextActive : styles.adminStatusTextInactive]}>
                              {admin.ativo ? 'Ativo' : 'Inativo'}
                            </Text>
                          </View>
                        </View>
                        <View style={styles.adminActions}>
                          <TouchableOpacity
                            style={styles.adminEditButton}
                            onPress={() => openAdminModal(admin)}
                          >
                            <Feather name="edit-2" size={16} color="#6366f1" />
                          </TouchableOpacity>
                          <TouchableOpacity
                            style={[styles.adminToggleButton, admin.ativo ? styles.adminDeactivateButton : styles.adminActivateButton]}
                            onPress={() => handleToggleAdminStatus(admin)}
                          >
                            <Feather 
                              name={admin.ativo ? 'user-x' : 'user-check'} 
                              size={16} 
                              color="#fff" 
                            />
                          </TouchableOpacity>
                        </View>
                      </View>
                    ))}
                  </View>
                )}
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

      {/* Modal de Administrador */}
      <Modal
        visible={showAdminModal}
        transparent={true}
        animationType="fade"
        onRequestClose={closeAdminModal}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <View style={styles.modalHeaderIcon}>
                <Feather name="user-plus" size={24} color="#f97316" />
              </View>
              <Text style={styles.modalTitle}>
                {editingAdmin ? 'Editar Administrador' : 'Novo Administrador'}
              </Text>
              <TouchableOpacity style={styles.modalCloseButton} onPress={closeAdminModal}>
                <Feather name="x" size={20} color="#6b7280" />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              {/* Nome */}
              <View style={styles.modalInputGroup}>
                <Text style={styles.modalLabel}>Nome *</Text>
                <TextInput
                  style={[styles.modalInput, adminErrors.nome && styles.modalInputError]}
                  value={adminForm.nome}
                  onChangeText={(text) => setAdminForm({ ...adminForm, nome: text })}
                  placeholder="Nome completo"
                  placeholderTextColor="#9ca3af"
                />
                {adminErrors.nome && (
                  <Text style={styles.modalErrorText}>{adminErrors.nome}</Text>
                )}
              </View>

              {/* Email */}
              <View style={styles.modalInputGroup}>
                <Text style={styles.modalLabel}>E-mail *</Text>
                <TextInput
                  style={[styles.modalInput, adminErrors.email && styles.modalInputError]}
                  value={adminForm.email}
                  onChangeText={(text) => setAdminForm({ ...adminForm, email: text })}
                  placeholder="email@exemplo.com"
                  placeholderTextColor="#9ca3af"
                  keyboardType="email-address"
                  autoCapitalize="none"
                />
                {adminErrors.email && (
                  <Text style={styles.modalErrorText}>{adminErrors.email}</Text>
                )}
              </View>

              {/* Senha */}
              <View style={styles.modalInputGroup}>
                <Text style={styles.modalLabel}>
                  Senha {editingAdmin ? '(deixe em branco para manter)' : '*'}
                </Text>
                <TextInput
                  style={[styles.modalInput, adminErrors.senha && styles.modalInputError]}
                  value={adminForm.senha}
                  onChangeText={(text) => setAdminForm({ ...adminForm, senha: text })}
                  placeholder="Mínimo 6 caracteres"
                  placeholderTextColor="#9ca3af"
                  secureTextEntry
                />
                {adminErrors.senha && (
                  <Text style={styles.modalErrorText}>{adminErrors.senha}</Text>
                )}
              </View>

              {/* Telefone */}
              <View style={styles.modalInputGroup}>
                <Text style={styles.modalLabel}>Telefone</Text>
                <TextInput
                  style={[styles.modalInput, adminErrors.telefone && styles.modalInputError]}
                  value={adminForm.telefone}
                  onChangeText={(text) => setAdminForm({ ...adminForm, telefone: mascaraTelefone(text) })}
                  placeholder="(00) 00000-0000"
                  placeholderTextColor="#9ca3af"
                  keyboardType="phone-pad"
                />
                {adminErrors.telefone && (
                  <Text style={styles.modalErrorText}>{adminErrors.telefone}</Text>
                )}
              </View>

              {/* CPF */}
              <View style={styles.modalInputGroup}>
                <Text style={styles.modalLabel}>CPF</Text>
                <TextInput
                  style={[styles.modalInput, adminErrors.cpf && styles.modalInputError]}
                  value={adminForm.cpf}
                  onChangeText={(text) => setAdminForm({ ...adminForm, cpf: mascaraCPF(text) })}
                  placeholder="000.000.000-00"
                  placeholderTextColor="#9ca3af"
                  keyboardType="numeric"
                />
                {adminErrors.cpf && (
                  <Text style={styles.modalErrorText}>{adminErrors.cpf}</Text>
                )}
              </View>

              {/* Papéis */}
              <View style={styles.modalInputGroup}>
                <Text style={styles.modalLabel}>Papéis</Text>
                <View style={styles.papelCheckboxes}>
                  {papeisList.map((papel) => (
                    <Pressable
                      key={papel.id}
                      style={styles.papelCheckboxWrapper}
                      onPress={() => {
                        if (papel.id === 3) {
                          // Admin é obrigatório, não permitir desmarcar
                          return;
                        }
                        const newPapeis = adminForm.papeis.includes(papel.id)
                          ? adminForm.papeis.filter(p => p !== papel.id)
                          : [...adminForm.papeis, papel.id];
                        setAdminForm({ ...adminForm, papeis: newPapeis });
                      }}
                    >
                      <View
                        style={[
                          styles.papelCheckbox,
                          adminForm.papeis.includes(papel.id) && styles.papelCheckboxChecked,
                            papel.id === 3 && styles.papelCheckboxDisabled,
                          ]}
                        >
                          {adminForm.papeis.includes(papel.id) && (
                            <Feather name="check" size={14} color="#fff" />
                          )}
                        </View>
                        <View style={styles.papelCheckboxLabel}>
                          <Text style={styles.papelCheckboxLabelText}>{papel.nome}</Text>
                          <Text style={styles.papelCheckboxLabelDesc}>{papel.descricao}</Text>
                        </View>
                        {papel.id === 3 && (
                          <Text style={styles.papelRequired}>(obrigatório)</Text>
                        )}
                      </Pressable>
                    ))}
                </View>
              </View>
            </ScrollView>

            <View style={styles.modalFooter}>
              <TouchableOpacity
                style={styles.modalCancelButton}
                onPress={closeAdminModal}
                disabled={saving}
              >
                <Text style={styles.modalCancelButtonText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalSaveButton, saving && styles.modalSaveButtonDisabled]}
                onPress={handleSaveAdmin}
                disabled={saving}
              >
                {saving ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <>
                    <Feather name="check" size={18} color="#fff" />
                    <Text style={styles.modalSaveButtonText}>Salvar</Text>
                  </>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal de Confirmação de Desativação */}
      <Modal
        visible={showConfirmDeactivate}
        transparent={true}
        animationType="fade"
        onRequestClose={closeConfirmDeactivateModal}
      >
        <View style={styles.confirmOverlay}>
          <View style={styles.confirmModal}>
            <View style={styles.confirmHeader}>
              <View style={styles.confirmIconContainer}>
                <Feather name="alert-circle" size={32} color="#dc2626" />
              </View>
              <Text style={styles.confirmTitle}>Desativar Administrador?</Text>
            </View>

            <View style={styles.confirmBody}>
              <Text style={styles.confirmMessage}>
                Você está prestes a desativar o administrador
              </Text>
              
              <View style={styles.adminNameBox}>
                <Text style={styles.adminNameBoxText}>{adminToDeactivate?.nome}</Text>
              </View>

              <Text style={styles.confirmSubMessage}>
                Selecione os papéis que serão desativados:
              </Text>

              <View style={styles.papelsContainer}>
                {papelsDisponiveisParaDesativar.map((papelId) => {
                  const isSelected = papelsToDeactivate.includes(papelId);
                  const papelNome = getPapelNome(papelId);
                  const isAdminRole = papelId === 3;

                  return (
                    <Pressable
                      key={papelId}
                      style={styles.confirmPapelItem}
                      onPress={() => togglePapelToDeactivate(papelId)}
                      disabled={isAdminRole}
                    >
                      <View
                        style={[
                          styles.papelCheckbox,
                          isSelected && styles.papelCheckboxChecked,
                          isAdminRole && styles.papelCheckboxDisabled
                        ]}
                      >
                        {isSelected && <Feather name="check" size={12} color="#fff" />}
                      </View>
                      <View style={styles.confirmPapelTextContainer}>
                        <Text style={styles.papelItemText}>{papelNome}</Text>
                        {isAdminRole && (
                          <Text style={styles.confirmAdminRequiredText}>
                            Admin é obrigatório
                          </Text>
                        )}
                      </View>
                    </Pressable>
                  );
                })}
              </View>

              <View style={styles.confirmWarning}>
                <Feather name="info" size={16} color="#d97706" />
                <Text style={styles.confirmWarningText}>
                  O administrador não poderá acessar o sistema até ser reativado.
                </Text>
              </View>
            </View>

            <View style={styles.confirmFooter}>
              <TouchableOpacity
                style={styles.confirmCancelButton}
                onPress={closeConfirmDeactivateModal}
                disabled={deactivatingAdmin}
              >
                <Text style={styles.confirmCancelButtonText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.confirmDeactivateButton, deactivatingAdmin && styles.confirmDeactivateButtonDisabled]}
                onPress={handleConfirmDeactivate}
                disabled={deactivatingAdmin}
              >
                {deactivatingAdmin ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <>
                    <Feather name="user-x" size={16} color="#fff" />
                    <Text style={styles.confirmDeactivateButtonText}>Desativar</Text>
                  </>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
  // Estilos dos Administradores
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 12,
    backgroundColor: '#f97316',
    borderRadius: 8,
    marginLeft: 'auto',
  },
  addButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#fff',
  },
  loadingAdmins: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 20,
  },
  loadingAdminsText: {
    fontSize: 14,
    color: '#6b7280',
  },
  emptyAdmins: {
    alignItems: 'center',
    padding: 32,
  },
  emptyAdminsText: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 12,
  },
  adminsList: {
    gap: 12,
  },
  adminCard: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#f9fafb',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  adminInfo: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  adminAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#fff7ed',
    alignItems: 'center',
    justifyContent: 'center',
  },
  adminDetails: {
    flex: 1,
  },
  adminName: {
    fontSize: 15,
    fontWeight: '600',
    color: '#111827',
  },
  adminEmail: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  adminPhone: {
    fontSize: 12,
    color: '#9ca3af',
    marginTop: 2,
  },
  adminStatus: {
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 12,
  },
  adminStatusActive: {
    backgroundColor: '#d1fae5',
  },
  adminStatusInactive: {
    backgroundColor: '#fee2e2',
  },
  adminStatusText: {
    fontSize: 11,
    fontWeight: '600',
  },
  adminStatusTextActive: {
    color: '#065f46',
  },
  adminStatusTextInactive: {
    color: '#991b1b',
  },
  adminActions: {
    flexDirection: 'row',
    gap: 8,
    marginLeft: 12,
  },
  adminEditButton: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: '#eef2ff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  adminToggleButton: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  adminDeactivateButton: {
    backgroundColor: '#ef4444',
  },
  adminActivateButton: {
    backgroundColor: '#22c55e',
  },
  // Estilos do Modal
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContainer: {
    width: '100%',
    maxWidth: 500,
    backgroundColor: '#fff',
    borderRadius: 16,
    maxHeight: '90%',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  modalHeaderIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#fff7ed',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  modalTitle: {
    flex: 1,
    fontSize: 18,
    fontWeight: '700',
    color: '#111827',
  },
  modalCloseButton: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#f3f4f6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalBody: {
    padding: 20,
    maxHeight: 400,
  },
  modalInputGroup: {
    marginBottom: 16,
  },
  modalLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
  },
  modalInput: {
    height: 48,
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 10,
    paddingHorizontal: 16,
    fontSize: 15,
    color: '#111827',
    backgroundColor: '#fff',
  },
  modalInputError: {
    borderColor: '#ef4444',
  },
  modalErrorText: {
    fontSize: 12,
    color: '#ef4444',
    marginTop: 4,
  },
  modalFooter: {
    flexDirection: 'row',
    gap: 12,
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  modalCancelButton: {
    flex: 1,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 10,
    alignItems: 'center',
  },
  modalCancelButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#6b7280',
  },
  modalSaveButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    backgroundColor: '#f97316',
    borderRadius: 10,
  },
  modalSaveButtonDisabled: {
    backgroundColor: '#fdba74',
  },
  modalSaveButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#fff',
  },
  // Estilos para Papéis
  adminPapeis: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginTop: 8,
  },
  adminPapelBadge: {
    backgroundColor: '#dbeafe',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  adminPapelText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#0369a1',
  },
  papelCheckboxes: {
    gap: 12,
  },
  papelCheckboxWrapper: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingVertical: 12,
    paddingHorizontal: 12,
    backgroundColor: '#f9fafb',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  papelCheckbox: {
    width: 20,
    height: 20,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: '#d1d5db',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
    marginTop: 2,
  },
  papelCheckboxChecked: {
    backgroundColor: '#f97316',
    borderColor: '#f97316',
  },
  papelCheckboxDisabled: {
    backgroundColor: '#e5e7eb',
    borderColor: '#d1d5db',
  },
  papelCheckboxLabel: {
    flex: 1,
  },
  papelCheckboxLabelText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
  },
  papelCheckboxLabelDesc: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
  papelRequired: {
    fontSize: 11,
    color: '#9ca3af',
    fontStyle: 'italic',
  },
  // Estilos da Modal de Confirmação de Desativação
  confirmOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  confirmModal: {
    width: '100%',
    maxWidth: 520,
    backgroundColor: '#fff',
    borderRadius: 16,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 12,
    elevation: 8,
  },
  confirmHeader: {
    alignItems: 'center',
    paddingTop: 32,
    paddingBottom: 24,
    paddingHorizontal: 24,
  },
  confirmIconContainer: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: '#fee2e2',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  confirmTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111827',
    textAlign: 'center',
  },
  confirmBody: {
    paddingHorizontal: 24,
    paddingBottom: 24,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  confirmMessage: {
    fontSize: 15,
    color: '#6b7280',
    marginBottom: 12,
    textAlign: 'center',
  },
  adminNameBox: {
    backgroundColor: '#fef7f0',
    borderLeftWidth: 4,
    borderLeftColor: '#f97316',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 8,
    marginBottom: 20,
  },
  adminNameBoxText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  confirmSubMessage: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 12,
  },
  papelsContainer: {
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    paddingVertical: 8,
    marginBottom: 20,
  },
  confirmPapelItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 16,
  },
  confirmPapelTextContainer: {
    flex: 1,
  },
  papelItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 16,
  },
  papelItemDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: '#dc2626',
    marginRight: 12,
  },
  papelItemText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
  },
  confirmAdminRequiredText: {
    fontSize: 12,
    color: '#9ca3af',
    marginTop: 2,
  },
  confirmWarning: {
    flexDirection: 'row',
    gap: 12,
    backgroundColor: '#fffbeb',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  confirmWarningText: {
    flex: 1,
    fontSize: 13,
    color: '#92400e',
    lineHeight: 18,
  },
  confirmFooter: {
    flexDirection: 'row',
    gap: 12,
    padding: 24,
  },
  confirmCancelButton: {
    flex: 1,
    paddingVertical: 12,
    backgroundColor: '#f3f4f6',
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  confirmCancelButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#6b7280',
  },
  confirmDeactivateButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    backgroundColor: '#dc2626',
    borderRadius: 10,
  },
  confirmDeactivateButtonDisabled: {
    backgroundColor: '#fecaca',
  },
  confirmDeactivateButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#fff',
  },
});
