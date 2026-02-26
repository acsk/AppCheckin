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
import alunoService from '../../services/alunoService';
import modalidadeService from '../../services/modalidadeService';
import planoService from '../../services/planoService';
import pacoteService from '../../services/pacoteService';

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
  const [ciclosDisponiveis, setCiclosDisponiveis] = useState([]);
  const [loadingCiclos, setLoadingCiclos] = useState(false);
  const [pacotesDisponiveis, setPacotesDisponiveis] = useState([]);
  const [loadingPacotes, setLoadingPacotes] = useState(false);
  const [alunosBasico, setAlunosBasico] = useState([]);
  const [tipoMatricula, setTipoMatricula] = useState('plano');
  const [searchDependentes, setSearchDependentes] = useState('');

  const [formData, setFormData] = useState({
    usuario_id: '',
    modalidade_id: '',
    plano_id: '',
    plano_ciclo_id: '',
    pacote_id: '',
    dependentes: [],
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
      setCiclosDisponiveis([]);
      setFormData((prev) => ({ ...prev, plano_id: '', plano_ciclo_id: '' }));
    }
  }, [formData.modalidade_id]);

  useEffect(() => {
    if (!formData.plano_id) {
      setCiclosDisponiveis([]);
      setFormData((prev) => ({ ...prev, plano_ciclo_id: '' }));
      return;
    }

    const planoSelecionado = planosDisponiveis.find(
      (plano) => plano.id === parseInt(formData.plano_id)
    );

    if (planoSelecionado?.ciclos?.length) {
      const ciclosOrdenados = [...planoSelecionado.ciclos].sort((a, b) => a.meses - b.meses);
      setCiclosDisponiveis(ciclosOrdenados);
      setFormData((prev) => {
        const cicloAtualValido = ciclosOrdenados.some(
          (ciclo) => ciclo.id.toString() === prev.plano_ciclo_id
        );
        return {
          ...prev,
          plano_ciclo_id:
            cicloAtualValido || ciclosOrdenados.length !== 1
              ? prev.plano_ciclo_id
              : ciclosOrdenados[0].id.toString(),
        };
      });
      return;
    }

    carregarCiclos(formData.plano_id);
  }, [formData.plano_id, planosDisponiveis]);

  const carregarDados = async () => {
    setLoading(true);
    try {
      const [usuariosData, modalidadesData, alunosBasicoData, pacotesData] = await Promise.all([
        usuarioService.listar(),
        modalidadeService.listar(),
        alunoService.listarBasico(),
        pacoteService.listar(),
      ]);

      console.log('📊 Usuários recebidos:', usuariosData);
      console.log('📊 Modalidades recebidas:', modalidadesData);

      // Extrair array de usuários (pode vir como array direto ou dentro de um objeto)
      const usuarios = Array.isArray(usuariosData) 
        ? usuariosData 
        : Array.isArray(usuariosData?.usuarios) 
        ? usuariosData.usuarios 
        : [];

      // Filtrar apenas alunos (papel_id = 1 e que estejam ativos)
      const alunosAtivos = usuarios.filter(
        (u) => u.papel_id === 1 && u.ativo === true
      );
      
      console.log('👥 Alunos carregados:', alunosAtivos);
      setAlunos(alunosAtivos);

      // Extrair array de modalidades
      const modalidades = Array.isArray(modalidadesData)
        ? modalidadesData
        : Array.isArray(modalidadesData?.modalidades)
        ? modalidadesData.modalidades
        : [];
      
      console.log('🏋️ Modalidades:', modalidades);
      setModalidades(modalidades);

      const alunosBasicoLista = Array.isArray(alunosBasicoData?.alunos)
        ? alunosBasicoData.alunos
        : Array.isArray(alunosBasicoData)
        ? alunosBasicoData
        : [];
      setAlunosBasico(alunosBasicoLista);

      const pacotesLista = Array.isArray(pacotesData)
        ? pacotesData
        : pacotesData?.pacotes || pacotesData?.data?.pacotes || [];
      setPacotesDisponiveis(pacotesLista);
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

  const carregarPacotes = async () => {
    try {
      setLoadingPacotes(true);
      const response = await pacoteService.listar();
      const lista = Array.isArray(response)
        ? response
        : response?.pacotes || response?.data?.pacotes || [];
      setPacotesDisponiveis(lista);
    } catch (error) {
      console.error('❌ Erro ao carregar pacotes:', error);
      setPacotesDisponiveis([]);
    } finally {
      setLoadingPacotes(false);
    }
  };

  const carregarCiclos = async (planoId) => {
    try {
      setLoadingCiclos(true);
      setCiclosDisponiveis([]);
      const response = await planoService.listarCiclos(planoId);
      const lista = Array.isArray(response)
        ? response
        : response?.ciclos || response?.data?.ciclos || [];
      const ciclosOrdenados = lista.slice().sort((a, b) => a.meses - b.meses);
      setCiclosDisponiveis(ciclosOrdenados);
      setFormData((prev) => ({
        ...prev,
        plano_ciclo_id: ciclosOrdenados.length === 1 ? ciclosOrdenados[0].id.toString() : '',
      }));
    } catch (error) {
      console.error('❌ Erro ao carregar ciclos:', error);
      setCiclosDisponiveis([]);
    } finally {
      setLoadingCiclos(false);
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

    if (tipoMatricula === 'plano') {
      if (!formData.modalidade_id) {
        newErrors.modalidade_id = 'Selecione uma modalidade';
      }
      if (!formData.plano_id) {
        newErrors.plano_id = 'Selecione um plano';
      }
      if (!formData.plano_ciclo_id) {
        newErrors.plano_ciclo_id = 'Selecione um ciclo de pagamento';
      }
    } else {
      if (!formData.pacote_id) {
        newErrors.pacote_id = 'Selecione um pacote';
      }
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
      const payload =
        tipoMatricula === 'pacote'
          ? {
              ...(alunoBasicoSelecionado?.id
                ? { aluno_id: parseInt(alunoBasicoSelecionado.id) }
                : { usuario_id: parseInt(formData.usuario_id) }),
              pacote_id: parseInt(formData.pacote_id),
              dependentes: formData.dependentes.map((id) => parseInt(id)),
            }
          : {
              usuario_id: parseInt(formData.usuario_id),
              plano_id: parseInt(formData.plano_id),
              plano_ciclo_id: parseInt(formData.plano_ciclo_id),
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

  const pacoteSelecionado = pacotesDisponiveis.find(
    (pacote) => pacote.id === parseInt(formData.pacote_id)
  );
  const alunoBasicoSelecionado = alunosBasico.find(
    (aluno) => aluno?.usuario_id?.toString() === formData.usuario_id
  );
  const totalBeneficiariosPermitidos = Number(pacoteSelecionado?.qtd_beneficiarios || 0);
  const maxDependentes = Math.max(totalBeneficiariosPermitidos - 1, 0);

  const dependentesFiltrados = alunosBasico
    .filter((aluno) => aluno?.usuario_id?.toString() !== formData.usuario_id)
    .filter((aluno) => {
      if (!searchDependentes.trim()) return true;
      const termo = searchDependentes.trim().toLowerCase();
      return (
        aluno.nome?.toLowerCase().includes(termo) ||
        aluno.email?.toLowerCase().includes(termo)
      );
    });

  const toggleDependente = (alunoId) => {
    setFormData((prev) => {
      const existe = prev.dependentes.includes(alunoId.toString());
      if (!existe && totalBeneficiariosPermitidos > 0 && prev.dependentes.length >= maxDependentes) {
        showToast(
          `Limite do pacote: ${totalBeneficiariosPermitidos} pessoa(s). Você pode selecionar no máximo ${maxDependentes} dependente(s).`
        );
        return prev;
      }
      return {
        ...prev,
        dependentes: existe
          ? prev.dependentes.filter((id) => id !== alunoId.toString())
          : [...prev.dependentes, alunoId.toString()],
      };
    });
  };

  const getCicloLabel = (ciclo) => {
    if (!ciclo) return '-';
    const meses = Number(ciclo.meses || 0);
    const frequencia = ciclo.frequencia_nome || ciclo.nome || 'Ciclo';
    return `${frequencia} • ${meses} ${meses === 1 ? 'mês' : 'meses'}`;
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
    <LayoutBase title="Nova Matrícula" subtitle="Matricular aluno em um plano ou pacote">
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

            {/* Tipo de Matrícula */}
            <View className="mb-5">
              <Text className="mb-2 text-sm font-semibold text-slate-700">Tipo de Matrícula</Text>
              <View className="flex-row gap-2">
                <Pressable
                  onPress={() => {
                    setTipoMatricula('plano');
                    setFormData((prev) => ({
                      ...prev,
                      pacote_id: '',
                      dependentes: [],
                    }));
                  }}
                  className={`flex-1 rounded-lg border px-4 py-3 ${
                    tipoMatricula === 'plano'
                      ? 'border-emerald-400 bg-emerald-50'
                      : 'border-slate-200 bg-white'
                  }`}
                >
                  <Text
                    className={`text-center text-sm font-semibold ${
                      tipoMatricula === 'plano' ? 'text-emerald-700' : 'text-slate-700'
                    }`}
                  >
                    Plano
                  </Text>
                </Pressable>
                <Pressable
                  onPress={() => {
                    setTipoMatricula('pacote');
                    setFormData((prev) => ({
                      ...prev,
                      modalidade_id: '',
                      plano_id: '',
                      plano_ciclo_id: '',
                    }));
                    if (pacotesDisponiveis.length === 0) {
                      carregarPacotes();
                    }
                  }}
                  className={`flex-1 rounded-lg border px-4 py-3 ${
                    tipoMatricula === 'pacote'
                      ? 'border-emerald-400 bg-emerald-50'
                      : 'border-slate-200 bg-white'
                  }`}
                >
                  <Text
                    className={`text-center text-sm font-semibold ${
                      tipoMatricula === 'pacote' ? 'text-emerald-700' : 'text-slate-700'
                    }`}
                  >
                    Pacote
                  </Text>
                </Pressable>
              </View>
            </View>

            {/* Modalidade */}
            {tipoMatricula === 'plano' && (
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
            )}

            {/* Planos - Botões Card */}
            {tipoMatricula === 'plano' && formData.modalidade_id && (
              <View className="mb-5">
                <View className="mb-3 flex-row items-center justify-between">
                  <Text className="text-sm font-semibold text-slate-700">Plano *</Text>
                  <Text className="text-xs text-slate-500">Escolha um plano</Text>
                </View>
                {planosDisponiveis.length > 0 ? (
                  <>
                    <View className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                      <View className="flex-row flex-wrap gap-3">
                      {planosDisponiveis.map((plano) => (
                        <Pressable
                          key={plano.id}
                          className={`min-w-[190px] flex-1 rounded-2xl border p-4 shadow-sm ${
                            formData.plano_id === plano.id.toString()
                              ? 'border-emerald-400 bg-emerald-50/70'
                              : 'border-slate-200 bg-white'
                          } ${errors.plano_id && !formData.plano_id ? 'border-rose-300' : ''}`}
                          style={({ pressed }) => [
                            { flexBasis: 210, flexGrow: 1, minHeight: 150 },
                            pressed && { opacity: 0.85 }
                          ]}
                          onPress={() => {
                            setFormData((prev) => ({
                              ...prev,
                              plano_id: plano.id.toString(),
                              plano_ciclo_id: '',
                            }));
                            setErrors((prev) => ({ ...prev, plano_id: undefined, plano_ciclo_id: undefined }));
                          }}
                        >
                          <View className="flex-row items-center justify-between">
                            <Text
                              className={`text-[14px] font-semibold ${
                                formData.plano_id === plano.id.toString()
                                  ? 'text-emerald-700'
                                  : 'text-slate-800'
                              }`}
                            >
                              {plano.nome}
                            </Text>
                            {formData.plano_id === plano.id.toString() && (
                              <View className="h-6 w-6 items-center justify-center rounded-full bg-emerald-100">
                                <Feather name="check" size={14} color="#10b981" />
                              </View>
                            )}
                          </View>
                          
                          <Text
                            className={`mt-2 text-lg font-bold ${
                              formData.plano_id === plano.id.toString()
                                ? 'text-emerald-600'
                                : 'text-slate-700'
                            }`}
                          >
                            {formatCurrency(plano.valor)}
                          </Text>

                          <View className="mt-3 gap-2 border-t border-slate-100 pt-3">
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

            {/* Ciclos do Plano */}
            {tipoMatricula === 'plano' && formData.plano_id && (
              <View className="mb-5">
                <View className="mb-3 flex-row items-center justify-between">
                  <Text className="text-sm font-semibold text-slate-700">Ciclo de Pagamento *</Text>
                  <Text className="text-xs text-slate-500">Selecione o ciclo</Text>
                </View>
                {loadingCiclos ? (
                  <View className="items-center rounded-lg border border-slate-200 bg-slate-50 px-6 py-6">
                    <ActivityIndicator size="small" color="#f97316" />
                    <Text className="mt-2 text-xs text-slate-500">Carregando ciclos...</Text>
                  </View>
                ) : ciclosDisponiveis.length > 0 ? (
                  <>
                    <View className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                      <View className="flex-row flex-wrap gap-3">
                        {ciclosDisponiveis.map((ciclo) => {
                          const cicloSelecionado = formData.plano_ciclo_id === ciclo.id.toString();
                          const desconto = Number(ciclo.desconto_percentual || 0);
                          return (
                            <Pressable
                              key={ciclo.id}
                              className={`min-w-[190px] flex-1 rounded-2xl border p-4 shadow-sm ${
                                cicloSelecionado ? 'border-emerald-400 bg-emerald-50/70' : 'border-slate-200 bg-white'
                              } ${errors.plano_ciclo_id && !formData.plano_ciclo_id ? 'border-rose-300' : ''}`}
                              style={({ pressed }) => [
                                { flexBasis: 210, flexGrow: 1, minHeight: 130 },
                                pressed && { opacity: 0.85 }
                              ]}
                              onPress={() => {
                                setFormData((prev) => ({ ...prev, plano_ciclo_id: ciclo.id.toString() }));
                                setErrors((prev) => ({ ...prev, plano_ciclo_id: undefined }));
                              }}
                            >
                              <View className="flex-row items-center justify-between">
                                <Text
                                  className={`text-[14px] font-semibold ${
                                    cicloSelecionado ? 'text-emerald-700' : 'text-slate-800'
                                  }`}
                                >
                                  {getCicloLabel(ciclo)}
                                </Text>
                                {cicloSelecionado && (
                                  <View className="h-6 w-6 items-center justify-center rounded-full bg-emerald-100">
                                    <Feather name="check" size={14} color="#10b981" />
                                  </View>
                                )}
                              </View>

                              <Text
                                className={`mt-2 text-lg font-bold ${
                                  cicloSelecionado ? 'text-emerald-600' : 'text-slate-700'
                                }`}
                              >
                                {formatCurrency(ciclo.valor)}
                              </Text>

                              <View className="mt-2 gap-1 border-t border-slate-100 pt-3">
                                {!!desconto && (
                                  <Text className="text-xs text-emerald-600">
                                    {desconto > 0 ? `${desconto}% de desconto` : `${desconto}% de ajuste`}
                                  </Text>
                                )}
                                {ciclo.permite_recorrencia ? (
                                  <Text className="text-xs text-slate-500">Permite recorrência</Text>
                                ) : (
                                  <Text className="text-xs text-slate-500">Pagamento avulso</Text>
                                )}
                              </View>
                            </Pressable>
                          );
                        })}
                      </View>
                    </View>
                    {errors.plano_ciclo_id && (
                      <View className="mt-2 flex-row items-center gap-2">
                        <Feather name="alert-circle" size={14} color="#ef4444" />
                        <Text className="text-xs font-medium text-rose-500">{errors.plano_ciclo_id}</Text>
                      </View>
                    )}
                  </>
                ) : (
                  <View className="items-center rounded-lg border border-slate-200 bg-slate-50 px-6 py-6">
                    <Feather name="alert-circle" size={28} color="#d1d5db" />
                    <Text className="mt-2 text-sm text-slate-400">
                      Nenhum ciclo disponível para este plano
                    </Text>
                  </View>
                )}
              </View>
            )}

            {tipoMatricula === 'plano' && !formData.modalidade_id && (
              <View className="mt-2 flex-row items-center gap-3 rounded-lg border border-orange-100 bg-orange-50 px-4 py-3">
                <Feather name="info" size={16} color="#f97316" />
                <Text className="flex-1 text-sm text-orange-700">
                  Selecione uma modalidade para ver os planos disponíveis
                </Text>
              </View>
            )}

            {/* Pacotes */}
            {tipoMatricula === 'pacote' && (
              <View className="mb-5">
                <View className="mb-2 flex-row items-center justify-between">
                  <Text className="text-sm font-semibold text-slate-700">Pacote *</Text>
                  {loadingPacotes && <ActivityIndicator size="small" color="#f97316" />}
                </View>
                {pacotesDisponiveis.length > 0 ? (
                  <View className="flex-row flex-wrap gap-3">
                    {pacotesDisponiveis.map((pacote) => {
                      const selected = formData.pacote_id === pacote.id.toString();
                      return (
                        <Pressable
                          key={pacote.id}
                          className={`min-w-[200px] flex-1 rounded-xl border-2 p-4 ${
                            selected ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 bg-white'
                          } ${errors.pacote_id && !formData.pacote_id ? 'border-rose-300' : ''}`}
                          style={({ pressed }) => [pressed && { opacity: 0.8 }]}
                          onPress={() => {
                            setFormData((prev) => ({ ...prev, pacote_id: pacote.id.toString() }));
                            setErrors((prev) => ({ ...prev, pacote_id: undefined }));
                          }}
                        >
                          <View className="flex-row items-center justify-between">
                            <Text className={`text-[15px] font-semibold ${selected ? 'text-emerald-700' : 'text-slate-800'}`}>
                              {pacote.nome}
                            </Text>
                            {selected && <Feather name="check-circle" size={18} color="#10b981" />}
                          </View>
                          <Text className={`mt-2 text-lg font-bold ${selected ? 'text-emerald-600' : 'text-slate-700'}`}>
                            {formatCurrency(pacote.valor_total)}
                          </Text>
                          <View className="mt-3 gap-1">
                            <Text className="text-xs text-slate-500">
                              Beneficiários: {pacote.qtd_beneficiarios || 0}
                            </Text>
                            {!!pacote.plano_nome && (
                              <Text className="text-xs text-slate-500">Plano: {pacote.plano_nome}</Text>
                            )}
                          </View>
                        </Pressable>
                      );
                    })}
                  </View>
                ) : (
                  <View className="items-center rounded-lg border border-slate-200 bg-slate-50 px-6 py-8">
                    <Feather name="alert-circle" size={28} color="#d1d5db" />
                    <Text className="mt-3 text-sm text-slate-400">Nenhum pacote disponível</Text>
                  </View>
                )}
                {errors.pacote_id && (
                  <View className="mt-2 flex-row items-center gap-2">
                    <Feather name="alert-circle" size={14} color="#ef4444" />
                    <Text className="text-xs font-medium text-rose-500">{errors.pacote_id}</Text>
                  </View>
                )}
              </View>
            )}

            {/* Dependentes */}
            {tipoMatricula === 'pacote' && formData.pacote_id && (
              <View className="mb-5">
                <View className="mb-2 flex-row items-center justify-between">
                  <Text className="text-sm font-semibold text-slate-700">Dependentes (opcional)</Text>
                  {pacoteSelecionado?.qtd_beneficiarios ? (
                    <Text className="text-xs text-slate-500">
                      Dependentes: {formData.dependentes.length}/{maxDependentes} • Total: {formData.dependentes.length + 1}/{totalBeneficiariosPermitidos}
                    </Text>
                  ) : null}
                </View>
                <TextInput
                  className="mb-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                  placeholder="Buscar dependente..."
                  value={searchDependentes}
                  onChangeText={setSearchDependentes}
                />
                <View
                  className="rounded-lg border border-slate-200 bg-white"
                  style={{ maxHeight: 220 }}
                >
                  <ScrollView nestedScrollEnabled>
                    {dependentesFiltrados.length > 0 ? (
                      dependentesFiltrados.map((aluno) => {
                        const selected = formData.dependentes.includes(aluno.id.toString());
                        return (
                          <Pressable
                            key={aluno.id}
                            className="flex-row items-center justify-between border-b border-slate-100 px-3 py-2.5"
                            style={({ pressed }) => [
                              pressed && { backgroundColor: '#f8fafc' },
                              selected && { backgroundColor: '#ecfdf5' },
                            ]}
                            onPress={() => toggleDependente(aluno.id)}
                          >
                            <View className="flex-1">
                              <Text className="text-sm font-semibold text-slate-800">{aluno.nome}</Text>
                              <Text className="text-xs text-slate-500">{aluno.email}</Text>
                            </View>
                            {selected ? (
                              <Feather name="check-circle" size={18} color="#10b981" />
                            ) : (
                              <Feather name="circle" size={18} color="#cbd5f5" />
                            )}
                          </Pressable>
                        );
                      })
                    ) : (
                      <View className="items-center px-4 py-6">
                        <Text className="text-xs text-slate-400">Nenhum aluno encontrado</Text>
                      </View>
                    )}
                  </ScrollView>
                </View>

                <View className="mt-3">
                  <Text className="mb-2 text-xs font-semibold text-slate-500">Selecionados</Text>
                  {formData.dependentes.length === 0 ? (
                    <Text className="text-xs text-slate-400">Nenhum dependente selecionado</Text>
                  ) : (
                    <View className="flex-row flex-wrap gap-2">
                      {alunosBasico
                        .filter((aluno) => formData.dependentes.includes(aluno.id.toString()))
                        .map((aluno) => (
                          <View
                            key={aluno.id}
                            className="flex-row items-center gap-2 rounded-full bg-slate-100 px-3 py-1"
                          >
                            <Text className="text-xs text-slate-700">{aluno.nome}</Text>
                            <Pressable
                              onPress={() => toggleDependente(aluno.id)}
                              className="h-5 w-5 items-center justify-center rounded-full bg-white"
                            >
                              <Feather name="x" size={12} color="#ef4444" />
                            </Pressable>
                          </View>
                        ))}
                    </View>
                  )}
                </View>
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
            <View className="items-center border-b border-slate-200 px-6 py-4">
              <View className="mb-2 h-10 w-10 items-center justify-center rounded-full bg-orange-100">
                <Feather name="check-circle" size={20} color="#f97316" />
              </View>
              <Text className="text-base font-semibold text-slate-800">Confirmar Matrícula</Text>
              <Text className="text-xs text-slate-500">
                Revise os dados antes de confirmar
              </Text>
            </View>

            <View className="px-6 py-4">
              {/* Dados do Aluno */}
              <View className="mb-4">
                <Text className="mb-1 text-[10px] font-semibold uppercase text-slate-400">Aluno</Text>
                {alunos
                  .filter((a) => a.id === parseInt(formData.usuario_id))
                  .map((aluno) => (
                    <View key={aluno.id} className="flex-row items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                      <View className="h-9 w-9 items-center justify-center rounded-full bg-orange-100">
                        <Feather name="user" size={16} color="#f97316" />
                      </View>
                      <View className="flex-1">
                        <Text className="text-[13px] font-semibold text-slate-800">{aluno.nome}</Text>
                        <Text className="text-[11px] text-slate-500">{aluno.email}</Text>
                      </View>
                    </View>
                  ))}
              </View>

              {tipoMatricula === 'plano' && (
                <>
                  {/* Dados da Modalidade */}
                  <View className="mb-4">
                    <Text className="mb-1 text-[10px] font-semibold uppercase text-slate-400">Modalidade</Text>
                    {modalidades
                      .filter((m) => m.id === parseInt(formData.modalidade_id))
                      .map((modalidade) => (
                        <View key={modalidade.id} className="flex-row items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                          <View className="h-9 w-9 items-center justify-center rounded-full" style={{ backgroundColor: modalidade.cor || '#f97316' }}>
                            <MaterialCommunityIcons
                              name={modalidade.icone || 'dumbbell'}
                              size={18}
                              color="#fff"
                            />
                          </View>
                          <View className="flex-1">
                            <Text className="text-[13px] font-semibold text-slate-800">{modalidade.nome}</Text>
                          </View>
                        </View>
                      ))}
                  </View>

                  {/* Dados do Plano */}
                  <View className="mb-4">
                    <Text className="mb-1 text-[10px] font-semibold uppercase text-slate-400">Plano Selecionado</Text>
                    {planosDisponiveis
                      .filter((p) => p.id === parseInt(formData.plano_id))
                      .map((plano) => (
                        <View key={plano.id} className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                          <View className="flex-row flex-wrap items-center justify-between gap-2">
                            <Text className="text-[13px] font-semibold text-slate-800">{plano.nome}</Text>
                            <Text className="text-sm font-bold text-slate-700">
                              {formatCurrency(plano.valor)}
                            </Text>
                          </View>
                          <View className="mt-2 flex-row flex-wrap gap-2">
                            <View className="flex-row items-center gap-2 rounded-full bg-white px-2.5 py-1">
                              <Feather name="calendar" size={11} color="#94a3b8" />
                              <Text className="text-[11px] text-slate-500">
                                {plano.checkins_semanais}x por semana
                              </Text>
                            </View>
                            <View className="flex-row items-center gap-2 rounded-full bg-white px-2.5 py-1">
                              <Feather name="clock" size={11} color="#94a3b8" />
                              <Text className="text-[11px] text-slate-500">
                                {plano.duracao_dias} dias
                              </Text>
                            </View>
                          </View>
                        </View>
                      ))}
                  </View>

                  {/* Dados do Ciclo */}
                  <View>
                    <Text className="mb-1 text-[10px] font-semibold uppercase text-slate-400">Ciclo Selecionado</Text>
                    {ciclosDisponiveis
                      .filter((c) => c.id === parseInt(formData.plano_ciclo_id))
                      .map((ciclo) => (
                        <View key={ciclo.id} className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                          <View className="flex-row flex-wrap items-center justify-between gap-2">
                            <Text className="text-[13px] font-semibold text-slate-800">{getCicloLabel(ciclo)}</Text>
                            <Text className="text-sm font-bold text-slate-700">
                              {formatCurrency(ciclo.valor)}
                            </Text>
                          </View>
                          <View className="mt-2 flex-row flex-wrap gap-2">
                            <View className="rounded-full bg-white px-2.5 py-1">
                              <Text className="text-[11px] text-slate-500">
                                {ciclo.permite_recorrencia ? 'Permite recorrência' : 'Pagamento avulso'}
                              </Text>
                            </View>
                            {!!Number(ciclo.desconto_percentual || 0) && (
                              <View className="rounded-full bg-emerald-50 px-2.5 py-1">
                                <Text className="text-[11px] text-emerald-600">
                                  {Number(ciclo.desconto_percentual) > 0
                                    ? `${Number(ciclo.desconto_percentual)}% de desconto`
                                    : `${Number(ciclo.desconto_percentual)}% de ajuste`}
                                </Text>
                              </View>
                            )}
                          </View>
                        </View>
                      ))}
                  </View>
                </>
              )}

              {tipoMatricula === 'pacote' && pacoteSelecionado && (
                <>
                  <View className="mb-4">
                    <Text className="mb-1 text-[10px] font-semibold uppercase text-slate-400">Pacote Selecionado</Text>
                    <View className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                      <View className="flex-row flex-wrap items-center justify-between gap-2">
                        <Text className="text-[13px] font-semibold text-slate-800">{pacoteSelecionado.nome}</Text>
                        <Text className="text-sm font-bold text-slate-700">
                          {formatCurrency(pacoteSelecionado.valor_total)}
                        </Text>
                      </View>
                      <View className="mt-2 flex-row flex-wrap gap-2">
                        <View className="rounded-full bg-white px-2.5 py-1">
                          <Text className="text-[11px] text-slate-500">
                            Beneficiários: {pacoteSelecionado.qtd_beneficiarios || 0}
                          </Text>
                        </View>
                        {!!pacoteSelecionado.plano_nome && (
                          <View className="rounded-full bg-white px-2.5 py-1">
                            <Text className="text-[11px] text-slate-500">
                              Plano: {pacoteSelecionado.plano_nome}
                            </Text>
                          </View>
                        )}
                      </View>
                    </View>
                  </View>

                  <View>
                    <Text className="mb-1 text-[10px] font-semibold uppercase text-slate-400">Dependentes</Text>
                    <View className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                      {formData.dependentes.length === 0 ? (
                        <Text className="text-xs text-slate-500">Nenhum dependente selecionado</Text>
                      ) : (
                        alunosBasico
                          .filter((aluno) => formData.dependentes.includes(aluno.id.toString()))
                          .map((aluno) => (
                            <Text key={aluno.id} className="text-xs text-slate-600">
                              • {aluno.nome}
                            </Text>
                          ))
                      )}
                    </View>
                  </View>
                </>
              )}
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
