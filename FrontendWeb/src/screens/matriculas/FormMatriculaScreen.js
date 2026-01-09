import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  ActivityIndicator,
  Alert,
  Platform,
  ToastAndroid,
  StyleSheet,
  TextInput,
  Modal,
} from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';
import LayoutBase from '../../components/LayoutBase';
import { matriculaService } from '../../services/matriculaService';
import usuarioService from '../../services/usuarioService';
import modalidadeService from '../../services/modalidadeService';
import planoService from '../../services/planoService';

export default function FormMatriculaScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [errors, setErrors] = useState({});

  const [alunos, setAlunos] = useState([]);
  const [alunosFiltrados, setAlunosFiltrados] = useState([]);
  const [searchText, setSearchText] = useState('');
  const [showAlunosList, setShowAlunosList] = useState(false);
  
  const [modalidades, setModalidades] = useState([]);
  const [planosDisponiveis, setPlanosDisponiveis] = useState([]);

  const [formData, setFormData] = useState({
    usuario_id: '',
    modalidade_id: '',
    plano_id: '',
  });

  useEffect(() => {
    carregarDados();
  }, []);

  useEffect(() => {
    if (searchText.trim() === '') {
      setAlunosFiltrados(alunos);
    } else {
      const filtrados = alunos.filter(aluno =>
        aluno.nome.toLowerCase().includes(searchText.toLowerCase()) ||
        aluno.email.toLowerCase().includes(searchText.toLowerCase())
      );
      setAlunosFiltrados(filtrados);
    }
  }, [searchText, alunos]);

  useEffect(() => {
    if (formData.modalidade_id) {
      carregarPlanos(formData.modalidade_id);
    } else {
      setPlanosDisponiveis([]);
      setFormData((prev) => ({ ...prev, plano_id: '' }));
    }
  }, [formData.modalidade_id]);

  const carregarDados = async () => {
    setLoading(true);
    try {
      const [usuariosData, modalidadesData] = await Promise.all([
        usuarioService.listar(),
        modalidadeService.listar(),
      ]);

      console.log('📊 Usuários recebidos:', usuariosData);
      console.log('📊 Modalidades recebidas:', modalidadesData);

      // Extrair array de usuários (pode vir como array direto ou dentro de um objeto)
      const usuarios = Array.isArray(usuariosData) 
        ? usuariosData 
        : Array.isArray(usuariosData?.usuarios) 
        ? usuariosData.usuarios 
        : [];

      // Filtrar apenas alunos (role_id = 1)
      const alunosAtivos = usuarios.filter(
        (u) => u.role_id === 1 && u.status === 'ativo'
      );
      
      console.log('👥 Alunos ativos:', alunosAtivos);
      setAlunos(alunosAtivos);

      // Extrair array de modalidades
      const modalidades = Array.isArray(modalidadesData)
        ? modalidadesData
        : Array.isArray(modalidadesData?.modalidades)
        ? modalidadesData.modalidades
        : [];
      
      console.log('🏋️ Modalidades:', modalidades);
      setModalidades(modalidades);
    } catch (error) {
      console.error('❌ Erro ao carregar dados:', error);
      showAlert('Erro', 'Não foi possível carregar os dados');
    } finally {
      setLoading(false);
    }
  };

  const carregarPlanos = async (modalidadeId) => {
    try {
      console.log('🔄 Carregando planos para modalidade:', modalidadeId);
      const response = await planoService.listar(true); // apenas ativos
      console.log('📦 Resposta planos:', response);
      
      // Extrair array de planos
      const todosPlanos = Array.isArray(response)
        ? response
        : Array.isArray(response?.planos)
        ? response.planos
        : [];
      
      // Filtrar planos pela modalidade selecionada e que estejam ativos e atuais
      const planosDaModalidade = todosPlanos.filter(
        (p) => p.modalidade_id === parseInt(modalidadeId) && p.ativo && p.atual
      );
      
      console.log('✅ Planos filtrados:', planosDaModalidade);
      setPlanosDisponiveis(planosDaModalidade);
    } catch (error) {
      console.error('❌ Erro ao carregar planos:', error);
      setPlanosDisponiveis([]);
    }
  };

  const selecionarAluno = (alunoId) => {
    setFormData((prev) => ({ ...prev, usuario_id: alunoId.toString() }));
    setErrors((prev) => ({ ...prev, usuario_id: undefined }));
    const aluno = alunos.find(a => a.id === alunoId);
    if (aluno) {
      setSearchText(aluno.nome);
    }
    setShowAlunosList(false);
  };

  const limparAluno = () => {
    setFormData((prev) => ({ ...prev, usuario_id: '' }));
    setSearchText('');
    setShowAlunosList(false);
  };

  const handleSubmit = async () => {
    console.log('🔵 handleSubmit chamado');
    console.log('📝 formData:', formData);
    
    // Limpar erros anteriores
    setErrors({});
    const newErrors = {};
    
    // Validações
    if (!formData.usuario_id) {
      newErrors.usuario_id = 'Selecione um aluno';
    }

    if (!formData.modalidade_id) {
      newErrors.modalidade_id = 'Selecione uma modalidade';
    }

    if (!formData.plano_id) {
      newErrors.plano_id = 'Selecione um plano';
    }

    // Se houver erros, mostrar e não prosseguir
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      showToast('Preencha todos os campos obrigatórios antes de continuar');
      return;
    }

    console.log('✅ Validações passaram');
    
    // Mostrar modal de confirmação
    setShowConfirmModal(true);
  };

  const confirmarMatricula = async () => {
    console.log('🚀 Iniciando criação de matrícula...');
    setSaving(true);
    try {
      const payload = {
        usuario_id: parseInt(formData.usuario_id),
        plano_id: parseInt(formData.plano_id),
      };
      console.log('📤 Payload:', payload);
      
      const result = await matriculaService.criar(payload);
      console.log('✅ Matrícula criada:', result);
      
      setShowConfirmModal(false);
      showToast('Matrícula realizada com sucesso');
      router.push('/matriculas');
    } catch (error) {
      console.error('❌ Erro ao criar matrícula:', error);
      setShowConfirmModal(false);
      // Usar mensagemLimpa se disponível, senão usar error/message padrão
      const mensagemErro = error.mensagemLimpa || error.error || error.message || 'Não foi possível realizar a matrícula';
      Alert.alert('Erro', mensagemErro);
    } finally {
      setSaving(false);
    }
  };

  const showAlert = (title, message) => {
    Alert.alert(title, message);
  };

  const showToast = (message) => {
    if (Platform.OS === 'android') {
      ToastAndroid.show(message, ToastAndroid.SHORT);
    } else {
      Alert.alert('', message);
    }
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value || 0);
  };

  if (loading) {
    return (
      <LayoutBase title="Nova Matrícula" subtitle="Carregando...">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#3b82f6" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Nova Matrícula" subtitle="Matricular aluno em um plano">
      <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
        <View style={styles.content}>
          <View style={styles.header}>
            <Pressable
              onPress={() => router.back()}
              style={({ pressed }) => [styles.btnBack, pressed && { opacity: 0.7 }]}
            >
              <Feather name="arrow-left" size={20} color="#6b7280" />
              <Text style={styles.btnBackText}>Voltar</Text>
            </Pressable>
          </View>

          <View style={styles.formCard}>
            <Text style={styles.formTitle}>Nova Matrícula</Text>
            <Text style={styles.formSubtitle}>Preencha os dados abaixo</Text>

            {/* Campo de Busca de Aluno */}
            <View style={styles.formGroup}>
              <Text style={styles.label}>Aluno *</Text>
              <View style={styles.searchContainer}>
                <TextInput
                  style={[
                    styles.searchInput,
                    errors.usuario_id && styles.inputError
                  ]}
                  placeholder="Buscar aluno por nome ou email..."
                  value={searchText}
                  onChangeText={(text) => {
                    setSearchText(text);
                    if (text.trim() !== '') {
                      setShowAlunosList(true);
                    } else {
                      setShowAlunosList(false);
                      if (formData.usuario_id) {
                        limparAluno();
                      }
                    }
                  }}
                  onFocus={() => setShowAlunosList(true)}
                />
                {formData.usuario_id && (
                  <Pressable
                    onPress={limparAluno}
                    style={styles.searchClearBtn}
                  >
                    <Feather name="x" size={18} color="#6b7280" />
                  </Pressable>
                )}
              </View>

              {errors.usuario_id && (
                <View style={styles.errorContainer}>
                  <Feather name="alert-circle" size={14} color="#ef4444" />
                  <Text style={styles.errorText}>{errors.usuario_id}</Text>
                </View>
              )}

              {/* Lista de alunos filtrados */}
              {showAlunosList && !formData.usuario_id && (
                <View style={styles.alunosList}>
                  {alunosFiltrados.length > 0 ? (
                    alunosFiltrados.map((aluno) => (
                      <Pressable
                        key={aluno.id}
                        style={({ pressed }) => [
                          styles.alunoItem,
                          pressed && { backgroundColor: '#f3f4f6' }
                        ]}
                        onPress={() => selecionarAluno(aluno.id)}
                      >
                        <View style={styles.alunoInfo}>
                          <Text style={styles.alunoNome}>{aluno.nome}</Text>
                          <Text style={styles.alunoEmail}>{aluno.email}</Text>
                        </View>
                        <Feather name="check-circle" size={20} color="#10b981" />
                      </Pressable>
                    ))
                  ) : (
                    <View style={styles.emptyList}>
                      <Text style={styles.emptyText}>Nenhum aluno encontrado</Text>
                    </View>
                  )}
                </View>
              )}

              {/* Aluno selecionado */}
              {formData.usuario_id && !showAlunosList && (
                <View style={styles.selectedAluno}>
                  {alunos
                    .filter((a) => a.id === parseInt(formData.usuario_id))
                    .map((aluno) => (
                      <View key={aluno.id} style={styles.selectedAlunoContent}>
                        <View style={styles.selectedAlunoIcon}>
                          <Feather name="user" size={20} color="#3b82f6" />
                        </View>
                        <View style={styles.selectedAlunoInfo}>
                          <Text style={styles.selectedAlunoNome}>{aluno.nome}</Text>
                          <Text style={styles.selectedAlunoEmail}>{aluno.email}</Text>
                        </View>
                      </View>
                    ))}
                </View>
              )}

              {alunos.length === 0 && (
                <Text style={styles.helperText}>Nenhum aluno disponível</Text>
              )}
            </View>

            {/* Modalidade */}
            <View style={styles.formGroup}>
              <Text style={styles.label}>Modalidade *</Text>
              <View style={[
                styles.pickerContainer,
                errors.modalidade_id && styles.inputError
              ]}>
                <Picker
                  selectedValue={formData.modalidade_id}
                  onValueChange={(value) => {
                    setFormData((prev) => ({ ...prev, modalidade_id: value, plano_id: '' }));
                    setErrors((prev) => ({ ...prev, modalidade_id: undefined }));
                  }}
                  style={styles.picker}
                >
                  <Picker.Item label="Selecione uma modalidade" value="" />
                  {modalidades
                    .filter((m) => m.ativo)
                    .map((modalidade) => (
                      <Picker.Item
                        key={modalidade.id}
                        label={modalidade.nome}
                        value={modalidade.id.toString()}
                      />
                    ))}
                </Picker>
              </View>
              {errors.modalidade_id && (
                <View style={styles.errorContainer}>
                  <Feather name="alert-circle" size={14} color="#ef4444" />
                  <Text style={styles.errorText}>{errors.modalidade_id}</Text>
                </View>
              )}
              {formData.modalidade_id && (
                <View style={styles.modalidadeBadge}>
                  {modalidades
                    .filter((m) => m.id === parseInt(formData.modalidade_id))
                    .map((m) => (
                      <View key={m.id} style={styles.modalidadeInfo}>
                        <View
                          style={[
                            styles.modalidadeIcon,
                            { backgroundColor: m.cor || '#3b82f6' },
                          ]}
                        >
                          <MaterialCommunityIcons
                            name={m.icone || 'dumbbell'}
                            size={20}
                            color="#fff"
                          />
                        </View>
                        <Text style={styles.modalidadeNome}>{m.nome}</Text>
                      </View>
                    ))}
                </View>
              )}
            </View>

            {/* Planos - Botões Card */}
            {formData.modalidade_id && (
              <View style={styles.formGroup}>
                <Text style={styles.label}>Plano *</Text>
                {planosDisponiveis.length > 0 ? (
                  <>
                    <View style={styles.planosGrid}>
                      {planosDisponiveis.map((plano) => (
                        <Pressable
                          key={plano.id}
                          style={({ pressed }) => [
                            styles.planoCard,
                            formData.plano_id === plano.id.toString() && styles.planoCardSelected,
                            errors.plano_id && !formData.plano_id && styles.planoCardError,
                            pressed && { opacity: 0.8 }
                          ]}
                          onPress={() => {
                            setFormData((prev) => ({ ...prev, plano_id: plano.id.toString() }));
                            setErrors((prev) => ({ ...prev, plano_id: undefined }));
                          }}
                        >
                          <View style={styles.planoCardHeader}>
                            <Text style={[
                              styles.planoCardNome,
                              formData.plano_id === plano.id.toString() && styles.planoCardNomeSelected
                            ]}>
                              {plano.nome}
                            </Text>
                            {formData.plano_id === plano.id.toString() && (
                              <Feather name="check-circle" size={20} color="#10b981" />
                            )}
                          </View>
                          
                          <Text style={[
                            styles.planoCardValor,
                            formData.plano_id === plano.id.toString() && styles.planoCardValorSelected
                          ]}>
                            {formatCurrency(plano.valor)}
                          </Text>

                          <View style={styles.planoCardDetails}>
                            <View style={styles.planoCardDetail}>
                              <Feather name="calendar" size={14} color="#6b7280" />
                              <Text style={styles.planoCardDetailText}>
                                {plano.checkins_semanais}x por semana
                              </Text>
                            </View>
                            <View style={styles.planoCardDetail}>
                              <Feather name="clock" size={14} color="#6b7280" />
                              <Text style={styles.planoCardDetailText}>
                                {plano.duracao_dias} dias
                              </Text>
                            </View>
                          </View>
                        </Pressable>
                      ))}
                    </View>
                    {errors.plano_id && (
                      <View style={styles.errorContainer}>
                        <Feather name="alert-circle" size={14} color="#ef4444" />
                        <Text style={styles.errorText}>{errors.plano_id}</Text>
                      </View>
                    )}
                  </>
                ) : (
                  <View style={styles.emptyPlanos}>
                    <Feather name="alert-circle" size={32} color="#d1d5db" />
                    <Text style={styles.emptyPlanosText}>
                      Nenhum plano disponível para esta modalidade
                    </Text>
                  </View>
                )}
              </View>
            )}

            {!formData.modalidade_id && (
              <View style={styles.infoBox}>
                <Feather name="info" size={16} color="#3b82f6" />
                <Text style={styles.infoText}>
                  Selecione uma modalidade para ver os planos disponíveis
                </Text>
              </View>
            )}

            {/* Ações */}
            <View style={styles.actions}>
              <Pressable
                onPress={() => router.back()}
                style={({ pressed }) => [
                  styles.btnSecondary,
                  pressed && { opacity: 0.7 },
                ]}
                disabled={saving}
              >
                <Text style={styles.btnSecondaryText}>Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={handleSubmit}
                style={({ pressed }) => [
                  styles.btnPrimary,
                  pressed && { opacity: 0.8 },
                  saving && { opacity: 0.6 },
                ]}
                disabled={saving}
              >
                {saving ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <>
                    <Feather name="check" size={20} color="#fff" />
                    <Text style={styles.btnPrimaryText}>Matricular</Text>
                  </>
                )}
              </Pressable>
            </View>
          </View>
        </View>
      </ScrollView>

      {/* Modal de Confirmação */}
      <Modal
        visible={showConfirmModal}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setShowConfirmModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View style={styles.modalIconContainer}>
                <Feather name="check-circle" size={32} color="#3b82f6" />
              </View>
              <Text style={styles.modalTitle}>Confirmar Matrícula</Text>
              <Text style={styles.modalSubtitle}>
                Revise os dados antes de confirmar
              </Text>
            </View>

            <View style={styles.modalBody}>
              {/* Dados do Aluno */}
              <View style={styles.modalSection}>
                <Text style={styles.modalSectionLabel}>Aluno</Text>
                {alunos
                  .filter((a) => a.id === parseInt(formData.usuario_id))
                  .map((aluno) => (
                    <View key={aluno.id} style={styles.modalInfoCard}>
                      <View style={styles.modalInfoIcon}>
                        <Feather name="user" size={20} color="#3b82f6" />
                      </View>
                      <View style={styles.modalInfoText}>
                        <Text style={styles.modalInfoName}>{aluno.nome}</Text>
                        <Text style={styles.modalInfoDetail}>{aluno.email}</Text>
                      </View>
                    </View>
                  ))}
              </View>

              {/* Dados da Modalidade */}
              <View style={styles.modalSection}>
                <Text style={styles.modalSectionLabel}>Modalidade</Text>
                {modalidades
                  .filter((m) => m.id === parseInt(formData.modalidade_id))
                  .map((modalidade) => (
                    <View key={modalidade.id} style={styles.modalInfoCard}>
                      <View
                        style={[
                          styles.modalInfoIcon,
                          { backgroundColor: modalidade.cor || '#3b82f6' },
                        ]}
                      >
                        <MaterialCommunityIcons
                          name={modalidade.icone || 'dumbbell'}
                          size={20}
                          color="#fff"
                        />
                      </View>
                      <View style={styles.modalInfoText}>
                        <Text style={styles.modalInfoName}>{modalidade.nome}</Text>
                      </View>
                    </View>
                  ))}
              </View>

              {/* Dados do Plano */}
              <View style={styles.modalSection}>
                <Text style={styles.modalSectionLabel}>Plano Selecionado</Text>
                {planosDisponiveis
                  .filter((p) => p.id === parseInt(formData.plano_id))
                  .map((plano) => (
                    <View key={plano.id} style={styles.modalPlanoCard}>
                      <View style={styles.modalPlanoHeader}>
                        <Text style={styles.modalPlanoNome}>{plano.nome}</Text>
                        <Text style={styles.modalPlanoValor}>
                          {formatCurrency(plano.valor)}
                        </Text>
                      </View>
                      <View style={styles.modalPlanoDetails}>
                        <View style={styles.modalPlanoDetail}>
                          <Feather name="calendar" size={14} color="#6b7280" />
                          <Text style={styles.modalPlanoDetailText}>
                            {plano.checkins_semanais}x por semana
                          </Text>
                        </View>
                        <View style={styles.modalPlanoDetail}>
                          <Feather name="clock" size={14} color="#6b7280" />
                          <Text style={styles.modalPlanoDetailText}>
                            {plano.duracao_dias} dias de duração
                          </Text>
                        </View>
                      </View>
                    </View>
                  ))}
              </View>
            </View>

            <View style={styles.modalActions}>
              <Pressable
                onPress={() => setShowConfirmModal(false)}
                style={({ pressed }) => [
                  styles.modalBtnSecondary,
                  pressed && { opacity: 0.7 },
                ]}
                disabled={saving}
              >
                <Text style={styles.modalBtnSecondaryText}>Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={confirmarMatricula}
                style={({ pressed }) => [
                  styles.modalBtnPrimary,
                  pressed && { opacity: 0.8 },
                  saving && { opacity: 0.6 },
                ]}
                disabled={saving}
              >
                {saving ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <>
                    <Feather name="check" size={18} color="#fff" />
                    <Text style={styles.modalBtnPrimaryText}>Confirmar Matrícula</Text>
                  </>
                )}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 14,
    color: '#6b7280',
  },
  content: {
    padding: 24,
  },
  header: {
    marginBottom: 24,
  },
  btnBack: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  btnBackText: {
    fontSize: 14,
    color: '#6b7280',
    fontWeight: '500',
  },
  formCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 24,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  formTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  formSubtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginBottom: 24,
  },
  formGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
  },
  pickerContainer: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    overflow: 'hidden',
    backgroundColor: '#fff',
  },
  picker: {
    height: 50,
  },
  helperText: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 6,
    fontStyle: 'italic',
  },
  modalidadeBadge: {
    marginTop: 12,
  },
  modalidadeInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    backgroundColor: '#f9fafb',
    borderRadius: 8,
  },
  modalidadeIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalidadeNome: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
  },
  planoDetalhes: {
    marginTop: 24,
    padding: 16,
    backgroundColor: '#eff6ff',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  planoDetalhesTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: '#1e40af',
    marginBottom: 12,
  },
  detalhesGrid: {
    gap: 12,
  },
  detalheItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  detalheLabel: {
    fontSize: 14,
    color: '#1e40af',
    fontWeight: '500',
  },
  detalheValue: {
    fontSize: 14,
    fontWeight: '700',
    color: '#1e3a8a',
  },
  searchContainer: {
    position: 'relative',
  },
  searchInput: {
    height: 50,
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 16,
    paddingRight: 40,
    fontSize: 14,
    color: '#111827',
    backgroundColor: '#fff',
  },
  searchClearBtn: {
    position: 'absolute',
    right: 12,
    top: 0,
    bottom: 0,
    justifyContent: 'center',
    alignItems: 'center',
    width: 30,
    height: 50,
  },
  alunosList: {
    marginTop: 8,
    maxHeight: 240,
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    backgroundColor: '#fff',
    overflow: 'hidden',
  },
  alunoItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  alunoInfo: {
    flex: 1,
  },
  alunoNome: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  alunoEmail: {
    fontSize: 12,
    color: '#6b7280',
  },
  emptyList: {
    padding: 24,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 14,
    color: '#9ca3af',
    fontStyle: 'italic',
  },
  selectedAluno: {
    marginTop: 12,
    padding: 12,
    backgroundColor: '#eff6ff',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  selectedAlunoContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  selectedAlunoIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#dbeafe',
    justifyContent: 'center',
    alignItems: 'center',
  },
  selectedAlunoInfo: {
    flex: 1,
  },
  selectedAlunoNome: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1e40af',
    marginBottom: 2,
  },
  selectedAlunoEmail: {
    fontSize: 12,
    color: '#3b82f6',
  },
  planosGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  planoCard: {
    flex: 1,
    minWidth: 200,
    padding: 16,
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#e5e7eb',
    borderRadius: 12,
  },
  planoCardSelected: {
    borderColor: '#10b981',
    backgroundColor: '#f0fdf4',
  },
  planoCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  planoCardNome: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  planoCardNomeSelected: {
    color: '#059669',
  },
  planoCardValor: {
    fontSize: 24,
    fontWeight: '700',
    color: '#3b82f6',
    marginBottom: 12,
  },
  planoCardValorSelected: {
    color: '#10b981',
  },
  planoCardDetails: {
    gap: 8,
  },
  planoCardDetail: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  planoCardDetailText: {
    fontSize: 13,
    color: '#6b7280',
  },
  emptyPlanos: {
    padding: 40,
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  emptyPlanosText: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 12,
    textAlign: 'center',
  },
  infoBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 16,
    backgroundColor: '#eff6ff',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#bfdbfe',
    marginTop: 12,
  },
  infoText: {
    flex: 1,
    fontSize: 14,
    color: '#1e40af',
  },
  actions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 32,
  },
  btnSecondary: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#d1d5db',
    backgroundColor: '#fff',
    alignItems: 'center',
  },
  btnSecondaryText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
  },
  btnPrimary: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#3b82f6',
    paddingVertical: 14,
    borderRadius: 8,
  },
  btnPrimaryText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  // Error Styles
  inputError: {
    borderColor: '#ef4444',
    borderWidth: 2,
  },
  errorContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 6,
  },
  errorText: {
    fontSize: 12,
    color: '#ef4444',
    fontWeight: '500',
  },
  planoCardError: {
    borderColor: '#fca5a5',
  },
  // Modal Styles
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 16,
    width: '100%',
    maxWidth: 500,
    maxHeight: '90%',
  },
  modalHeader: {
    padding: 24,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
    alignItems: 'center',
  },
  modalIconContainer: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#dbeafe',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  modalSubtitle: {
    fontSize: 14,
    color: '#6b7280',
    textAlign: 'center',
  },
  modalBody: {
    padding: 24,
    maxHeight: 400,
  },
  modalSection: {
    marginBottom: 20,
  },
  modalSectionLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
    textTransform: 'uppercase',
    marginBottom: 8,
  },
  modalInfoCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  modalInfoIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#dbeafe',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalInfoText: {
    flex: 1,
  },
  modalInfoName: {
    fontSize: 15,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  modalInfoDetail: {
    fontSize: 13,
    color: '#6b7280',
  },
  modalPlanoCard: {
    padding: 16,
    backgroundColor: '#eff6ff',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  modalPlanoHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  modalPlanoNome: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1e40af',
  },
  modalPlanoValor: {
    fontSize: 20,
    fontWeight: '700',
    color: '#3b82f6',
  },
  modalPlanoDetails: {
    gap: 8,
  },
  modalPlanoDetail: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  modalPlanoDetailText: {
    fontSize: 13,
    color: '#374151',
  },
  modalActions: {
    flexDirection: 'row',
    gap: 12,
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  modalBtnSecondary: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#d1d5db',
    backgroundColor: '#fff',
    alignItems: 'center',
  },
  modalBtnSecondaryText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
  },
  modalBtnPrimary: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#3b82f6',
    paddingVertical: 12,
    borderRadius: 8,
  },
  modalBtnPrimaryText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
});
