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

      // Filtrar apenas alunos (papel_id = 1)
      const alunosAtivos = usuarios.filter(
        (u) => u.papel_id === 1 && u.status === 'ativo'
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
      console.log('📋 Estrutura do erro:', JSON.stringify(error, null, 2));
      setShowConfirmModal(false);
      // Usar mensagemLimpa se disponível, senão usar error/message padrão
      const mensagemErro = error.mensagemLimpa || error.message || error.error || 'Não foi possível realizar a matrícula';
      console.log('📢 Mensagem a exibir:', mensagemErro);
      showToast(mensagemErro);
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
    } else if (Platform.OS === 'web') {
      // Criar toast customizado para web
      const toast = document.createElement('div');
      toast.textContent = message;
      toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #333;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        z-index: 10000;
        font-size: 14px;
        max-width: 80%;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      `;
      document.body.appendChild(toast);
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => document.body.removeChild(toast), 300);
      }, 3000);
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
        <View className="flex-1 items-center justify-center px-10">
          <ActivityIndicator size="large" color="#f97316" />
          <Text className="mt-4 text-sm text-slate-500">Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Nova Matrícula" subtitle="Matricular aluno em um plano">
      <ScrollView className="flex-1 bg-slate-50" showsVerticalScrollIndicator={false}>
        <View className="px-6 py-6">
          <View className="mb-4">
            <Pressable
              onPress={() => router.back()}
              className="flex-row items-center gap-2"
              style={({ pressed }) => [pressed && { opacity: 0.7 }]}
            >
              <Feather name="arrow-left" size={18} color="#64748b" />
              <Text className="text-sm font-medium text-slate-500">Voltar</Text>
            </Pressable>
          </View>

          <View className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <Text className="text-lg font-semibold text-slate-800">Nova Matrícula</Text>
            <Text className="mb-6 text-sm text-slate-500">Preencha os dados abaixo</Text>

            {/* Campo de Busca de Aluno */}
            <View className="mb-5">
              <Text className="mb-2 text-sm font-semibold text-slate-700">Aluno *</Text>
              <View style={{ position: 'relative', zIndex: 1000 }}>
                <TextInput
                  className={`h-12 rounded-lg border px-4 text-sm text-slate-800 ${errors.usuario_id ? 'border-rose-400' : 'border-slate-200'}`}
                  placeholder="Buscar aluno por nome ou email..."
                  value={searchText}
                  onChangeText={(text) => {
                    setSearchText(text);
                    if (text.trim().length >= 3) {
                      setShowAlunosList(true);
                    } else {
                      setShowAlunosList(false);
                    }
                  }}
                />
                {formData.usuario_id && (
                  <Pressable
                    onPress={limparAluno}
                    className="absolute right-3 top-0 h-12 w-8 items-center justify-center"
                  >
                    <Feather name="x" size={18} color="#6b7280" />
                  </Pressable>
                )}

                {/* Lista de alunos filtrados */}
                {showAlunosList && !formData.usuario_id && searchText.trim().length >= 3 && (
                  <View
                    className="mt-2"
                    style={{
                      maxHeight: 240,
                      backgroundColor: '#ffffff',
                      borderRadius: 8,
                      borderWidth: 1,
                      borderColor: '#e2e8f0',
                      shadowColor: '#000',
                      shadowOffset: { width: 0, height: 4 },
                      shadowOpacity: 0.2,
                      shadowRadius: 12,
                      elevation: 10,
                    }}
                  >
                    <ScrollView style={{ maxHeight: 240 }} nestedScrollEnabled>
                      {alunosFiltrados.length > 0 ? (
                        alunosFiltrados.map((aluno) => (
                          <Pressable
                            key={aluno.id}
                            className="flex-row items-center justify-between border-b border-slate-100 px-4 py-3"
                            style={({ pressed }) => [
                              { backgroundColor: '#ffffff' },
                              pressed && { backgroundColor: '#f8fafc' }
                            ]}
                            onPress={() => selecionarAluno(aluno.id)}
                          >
                            <View className="flex-1">
                              <Text className="text-sm font-semibold text-slate-800">{aluno.nome}</Text>
                              <Text className="text-xs text-slate-500">{aluno.email}</Text>
                            </View>
                            <Feather name="check-circle" size={20} color="#10b981" />
                          </Pressable>
                        ))
                      ) : (
                        <View className="items-center px-4 py-6" style={{ backgroundColor: '#ffffff' }}>
                          <Text className="text-xs text-slate-400">Nenhum aluno encontrado</Text>
                        </View>
                      )}
                    </ScrollView>
                  </View>
                )}
              </View>

              {errors.usuario_id && (
                <View className="mt-2 flex-row items-center gap-2">
                  <Feather name="alert-circle" size={14} color="#ef4444" />
                  <Text className="text-xs font-medium text-rose-500">{errors.usuario_id}</Text>
                </View>
              )}

              {/* Aluno selecionado */}
              {formData.usuario_id && !showAlunosList && (
                <View className="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                  {alunos
                    .filter((a) => a.id === parseInt(formData.usuario_id))
                    .map((aluno) => (
                      <View key={aluno.id} className="flex-row items-center gap-3">
                        <View className="h-10 w-10 items-center justify-center rounded-full bg-orange-100">
                          <Feather name="user" size={18} color="#f97316" />
                        </View>
                        <View className="flex-1">
                          <Text className="text-sm font-semibold text-slate-800">{aluno.nome}</Text>
                          <Text className="text-xs text-slate-500">{aluno.email}</Text>
                        </View>
                      </View>
                    ))}
                </View>
              )}

              {alunos.length === 0 && (
                <Text className="mt-2 text-xs text-slate-400">Nenhum aluno disponível</Text>
              )}
            </View>

            {/* Modalidade */}
            <View className="mb-5">
              <Text className="mb-2 text-sm font-semibold text-slate-700">Modalidade *</Text>
              <View className={`overflow-hidden rounded-lg border ${errors.modalidade_id ? 'border-rose-400' : 'border-slate-200'} bg-white`}>
                <Picker
                  selectedValue={formData.modalidade_id}
                  onValueChange={(value) => {
                    setFormData((prev) => ({ ...prev, modalidade_id: value, plano_id: '' }));
                    setErrors((prev) => ({ ...prev, modalidade_id: undefined }));
                  }}
                  style={{ height: 50 }}
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
                <View className="mt-2 flex-row items-center gap-2">
                  <Feather name="alert-circle" size={14} color="#ef4444" />
                  <Text className="text-xs font-medium text-rose-500">{errors.modalidade_id}</Text>
                </View>
              )}
              {formData.modalidade_id && (
                <View className="mt-3">
                  {modalidades
                    .filter((m) => m.id === parseInt(formData.modalidade_id))
                    .map((m) => (
                      <View key={m.id} className="flex-row items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <View
                          className="h-10 w-10 items-center justify-center rounded-full"
                          style={{ backgroundColor: m.cor || '#f97316' }}
                        >
                          <MaterialCommunityIcons
                            name={m.icone || 'dumbbell'}
                            size={20}
                            color="#fff"
                          />
                        </View>
                        <Text className="text-sm font-semibold text-slate-800">{m.nome}</Text>
                      </View>
                    ))}
                </View>
              )}
            </View>

            {/* Planos - Botões Card */}
            {formData.modalidade_id && (
              <View className="mb-5">
                <Text className="mb-2 text-sm font-semibold text-slate-700">Plano *</Text>
                {planosDisponiveis.length > 0 ? (
                  <>
                    <View className="flex-row flex-wrap gap-3">
                      {planosDisponiveis.map((plano) => (
                        <Pressable
                          key={plano.id}
                          className={`min-w-[200px] flex-1 rounded-xl border-2 p-4 ${formData.plano_id === plano.id.toString() ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 bg-white'} ${errors.plano_id && !formData.plano_id ? 'border-rose-300' : ''}`}
                          style={({ pressed }) => [pressed && { opacity: 0.8 }]}
                          onPress={() => {
                            setFormData((prev) => ({ ...prev, plano_id: plano.id.toString() }));
                            setErrors((prev) => ({ ...prev, plano_id: undefined }));
                          }}
                        >
                          <View className="flex-row items-center justify-between">
                            <Text className={`text-[15px] font-semibold ${formData.plano_id === plano.id.toString() ? 'text-emerald-700' : 'text-slate-800'}`}>
                              {plano.nome}
                            </Text>
                            {formData.plano_id === plano.id.toString() && (
                              <Feather name="check-circle" size={20} color="#10b981" />
                            )}
                          </View>
                          
                          <Text className={`mt-2 text-xl font-bold ${formData.plano_id === plano.id.toString() ? 'text-emerald-600' : 'text-slate-700'}`}>
                            {formatCurrency(plano.valor)}
                          </Text>

                          <View className="mt-3 gap-2">
                            <View className="flex-row items-center gap-2">
                              <Feather name="calendar" size={14} color="#94a3b8" />
                              <Text className="text-xs text-slate-500">
                                {plano.checkins_semanais}x por semana
                              </Text>
                            </View>
                            <View className="flex-row items-center gap-2">
                              <Feather name="clock" size={14} color="#94a3b8" />
                              <Text className="text-xs text-slate-500">
                                {plano.duracao_dias} dias
                              </Text>
                            </View>
                          </View>
                        </Pressable>
                      ))}
                    </View>
                    {errors.plano_id && (
                      <View className="mt-2 flex-row items-center gap-2">
                        <Feather name="alert-circle" size={14} color="#ef4444" />
                        <Text className="text-xs font-medium text-rose-500">{errors.plano_id}</Text>
                      </View>
                    )}
                  </>
                ) : (
                  <View className="items-center rounded-lg border border-slate-200 bg-slate-50 px-6 py-8">
                    <Feather name="alert-circle" size={32} color="#d1d5db" />
                    <Text className="mt-3 text-sm text-slate-400">
                      Nenhum plano disponível para esta modalidade
                    </Text>
                  </View>
                )}
              </View>
            )}

            {!formData.modalidade_id && (
              <View className="mt-2 flex-row items-center gap-3 rounded-lg border border-orange-100 bg-orange-50 px-4 py-3">
                <Feather name="info" size={16} color="#f97316" />
                <Text className="flex-1 text-sm text-orange-700">
                  Selecione uma modalidade para ver os planos disponíveis
                </Text>
              </View>
            )}

            {/* Ações */}
            <View className="mt-8 flex-row gap-3">
              <Pressable
                onPress={() => router.back()}
                className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white py-3"
                style={({ pressed }) => [pressed && { opacity: 0.7 }]}
                disabled={saving}
              >
                <Text className="text-sm font-semibold text-slate-600">Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={handleSubmit}
                className="flex-1 flex-row items-center justify-center gap-2 rounded-lg bg-orange-500 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }, saving && { opacity: 0.6 }]}
                disabled={saving}
              >
                {saving ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <>
                    <Feather name="check" size={20} color="#fff" />
                    <Text className="text-sm font-semibold text-white">Matricular</Text>
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
        <View className="flex-1 items-center justify-center bg-black/40 px-4">
          <View className="w-full max-w-lg rounded-2xl bg-white shadow-xl">
            <View className="items-center border-b border-slate-200 px-6 py-5">
              <View className="mb-3 h-14 w-14 items-center justify-center rounded-full bg-orange-100">
                <Feather name="check-circle" size={28} color="#f97316" />
              </View>
              <Text className="text-lg font-semibold text-slate-800">Confirmar Matrícula</Text>
              <Text className="text-sm text-slate-500">
                Revise os dados antes de confirmar
              </Text>
            </View>

            <View className="max-h-[420px] px-6 py-5">
              {/* Dados do Aluno */}
              <View className="mb-5">
                <Text className="mb-2 text-[11px] font-semibold uppercase text-slate-500">Aluno</Text>
                {alunos
                  .filter((a) => a.id === parseInt(formData.usuario_id))
                  .map((aluno) => (
                    <View key={aluno.id} className="flex-row items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                      <View className="h-10 w-10 items-center justify-center rounded-full bg-orange-100">
                        <Feather name="user" size={18} color="#f97316" />
                      </View>
                      <View className="flex-1">
                        <Text className="text-sm font-semibold text-slate-800">{aluno.nome}</Text>
                        <Text className="text-xs text-slate-500">{aluno.email}</Text>
                      </View>
                    </View>
                  ))}
              </View>

              {/* Dados da Modalidade */}
              <View className="mb-5">
                <Text className="mb-2 text-[11px] font-semibold uppercase text-slate-500">Modalidade</Text>
                {modalidades
                  .filter((m) => m.id === parseInt(formData.modalidade_id))
                  .map((modalidade) => (
                    <View key={modalidade.id} className="flex-row items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                      <View className="h-10 w-10 items-center justify-center rounded-full" style={{ backgroundColor: modalidade.cor || '#f97316' }}>
                        <MaterialCommunityIcons
                          name={modalidade.icone || 'dumbbell'}
                          size={20}
                          color="#fff"
                        />
                      </View>
                      <View className="flex-1">
                        <Text className="text-sm font-semibold text-slate-800">{modalidade.nome}</Text>
                      </View>
                    </View>
                  ))}
              </View>

              {/* Dados do Plano */}
              <View>
                <Text className="mb-2 text-[11px] font-semibold uppercase text-slate-500">Plano Selecionado</Text>
                {planosDisponiveis
                  .filter((p) => p.id === parseInt(formData.plano_id))
                  .map((plano) => (
                    <View key={plano.id} className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                      <View className="flex-row items-center justify-between">
                        <Text className="text-sm font-semibold text-slate-800">{plano.nome}</Text>
                        <Text className="text-base font-bold text-slate-700">
                          {formatCurrency(plano.valor)}
                        </Text>
                      </View>
                      <View className="mt-3 gap-2">
                        <View className="flex-row items-center gap-2">
                          <Feather name="calendar" size={14} color="#94a3b8" />
                          <Text className="text-xs text-slate-500">
                            {plano.checkins_semanais}x por semana
                          </Text>
                        </View>
                        <View className="flex-row items-center gap-2">
                          <Feather name="clock" size={14} color="#94a3b8" />
                          <Text className="text-xs text-slate-500">
                            {plano.duracao_dias} dias de duração
                          </Text>
                        </View>
                      </View>
                    </View>
                  ))}
              </View>
            </View>

            <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => setShowConfirmModal(false)}
                className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white py-3"
                style={({ pressed }) => [pressed && { opacity: 0.7 }]}
                disabled={saving}
              >
                <Text className="text-sm font-semibold text-slate-600">Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={confirmarMatricula}
                className="flex-1 flex-row items-center justify-center gap-2 rounded-lg bg-orange-500 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }, saving && { opacity: 0.6 }]}
                disabled={saving}
              >
                {saving ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <>
                    <Feather name="check" size={18} color="#fff" />
                    <Text className="text-sm font-semibold text-white">Confirmar Matrícula</Text>
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
