import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, ActivityIndicator, useWindowDimensions, TextInput, Modal, FlatList, Pressable, Switch, Alert } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { turmaService } from '../../services/turmaService';
import { diaService } from '../../services/diaService';
import modalidadeService from '../../services/modalidadeService';
import { professorService } from '../../services/professorService';
import { horarioService } from '../../services/horarioService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import SearchableDropdown from '../../components/SearchableDropdown';
import { showSuccess, showError } from '../../utils/toast';
import { mascaraHora } from '../../utils/masks';
import { buscarWodPorDataModalidade } from '../../services/wodService';
import WodPreviewModal from '../../components/WodPreviewModal';

export default function TurmasScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  const [turmas, setTurmas] = useState([]);
  const [turmasFiltradas, setTurmasFiltradas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState({ visible: false, id: null, nome: '' });
  const [searchText, setSearchText] = useState('');
  const [dataSelecionada, setDataSelecionada] = useState(obterHoje());
  const [calendarVisible, setCalendarVisible] = useState(false);
  const [modalCriarVisible, setModalCriarVisible] = useState(false);
  const [isEditando, setIsEditando] = useState(false);
  const [turmaEditandoId, setTurmaEditandoId] = useState(null);
  const [formData, setFormData] = useState({
    modalidade_id: '',
    horario_inicio: '',
    horario_fim: '',
    professor_id: '',
    limite_alunos: '',
    tolerancia_antes_minutos: '',
    tolerancia_minutos: '',
    ativo: true,
  });
  const [submitting, setSubmitting] = useState(false);
  const [modalidades, setModalidades] = useState([]);
  const [professores, setProfessores] = useState([]);
  const [horarios, setHorarios] = useState([]);
  const [loadingDropdowns, setLoadingDropdowns] = useState(false);
  const [dropdownAberto, setDropdownAberto] = useState(null);
  const [diaId, setDiaId] = useState(17); // TODO: Obter do turmasData quando carregar
  const [errors, setErrors] = useState({});
  const [modalReplicarVisible, setModalReplicarVisible] = useState(false);
  const [periodoReplicacao, setPeriodoReplicacao] = useState('custom');
  const [diasSemanaSelecionados, setDiasSemanaSelecionados] = useState([]);
  const [mesReplicacao, setMesReplicacao] = useState('');
  const [modalidadeReplicarId, setModalidadeReplicarId] = useState('');
  const [replicando, setReplicando] = useState(false);
  const [deletandoHorarios, setDeletandoHorarios] = useState(false);
  const [modalDeletarVisible, setModalDeletarVisible] = useState(false);
  const [showConfirmeContinuar, setShowConfirmeContinuar] = useState(false);
  const [dadosAnteriores, setDadosAnteriores] = useState(null);
  const [modalDesativarVisible, setModalDesativarVisible] = useState(false);
  const [turmaDesativar, setTurmaDesativar] = useState(null);
  const [periodoDesativacao, setPeriodoDesativacao] = useState('apenas_esta');
  const [desativando, setDesativando] = useState(false);
  const [modalWodSelectVisible, setModalWodSelectVisible] = useState(false);
  const [modalWodVisible, setModalWodVisible] = useState(false);
  const [modalidadeWodId, setModalidadeWodId] = useState('');
  const [wodData, setWodData] = useState(null);
  const [loadingWod, setLoadingWod] = useState(false);

  function obterHoje() {
    const hoje = new Date();
    const ano = hoje.getFullYear();
    const mes = String(hoje.getMonth() + 1).padStart(2, '0');
    const dia = String(hoje.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
  }

  function formatarDataExibicao(dataStr) {
    const date = new Date(dataStr + 'T00:00:00');
    const opcoes = { day: '2-digit', month: 'short', year: 'numeric' };
    return date.toLocaleDateString('pt-BR', opcoes);
  }

  function obterDiaSemana(dataStr) {
    const date = new Date(dataStr + 'T00:00:00');
    const dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
    return dias[date.getDay()];
  }

  function irParaData(dias) {
    const data = new Date(dataSelecionada + 'T00:00:00');
    data.setDate(data.getDate() + dias);
    const novaData = data.toISOString().split('T')[0];
    setDataSelecionada(novaData);
  }

  function voltarParaHoje() {
    setDataSelecionada(obterHoje());
  }

  const formatarHorarioIntervalo = (horaInicio, horaFim) => {
    if (!horaInicio || !horaFim) return '-';
    
    // Se vier no formato HH:MM:SS, pega apenas HH:MM
    const [hora1] = horaInicio.split(':');
    const [minuto1] = horaInicio.split(':').slice(1);
    
    const [hora2] = horaFim.split(':');
    const [minuto2] = horaFim.split(':').slice(1);
    
    const horaInicioFormatada = `${hora1}:${minuto1}`;
    const horaFimFormatada = `${hora2}:${minuto2}`;
    
    return `${horaInicioFormatada} - ${horaFimFormatada}`;
  };

  const gerarDiasDoMes = (data) => {
    const ano = data.getFullYear();
    const mes = data.getMonth();
    
    const primeiroDia = new Date(ano, mes, 1);
    const ultimoDia = new Date(ano, mes + 1, 0);
    const diasNoMes = ultimoDia.getDate();
    const diaInicio = primeiroDia.getDay();
    
    const dias = [];
    
    // Dias vazios no início
    for (let i = 0; i < diaInicio; i++) {
      dias.push(null);
    }
    
    // Dias do mês
    for (let i = 1; i <= diasNoMes; i++) {
      dias.push(new Date(ano, mes, i));
    }
    
    return dias;
  };

  const handleSelecionarDataCalendar = (dia) => {
    if (dia) {
      const dataFormatada = dia.toISOString().split('T')[0];
      setDataSelecionada(dataFormatada);
      setCalendarVisible(false);
    }
  };

  useEffect(() => {
    carregarDados();
  }, [dataSelecionada]);

  const carregarDados = async () => {
    try {
      setLoading(true);
      const turmasData = await turmaService.listar(dataSelecionada);
      
      console.log('📋 [carregarDados] Turmas recebidas:', turmasData);
      if (turmasData && turmasData.length > 0) {
        console.log('🎨 [carregarDados] Primeira turma:', {
          id: turmasData[0].id,
          modalidade_nome: turmasData[0].modalidade_nome,
          modalidade_icone: turmasData[0].modalidade_icone,
          modalidade_cor: turmasData[0].modalidade_cor,
        });
      }
      
      setTurmas(turmasData);
      setTurmasFiltradas(turmasData);
      setSearchText('');
      
      // Extrair dia_id das turmas, se existirem
      if (turmasData && turmasData.length > 0 && turmasData[0].dia_id) {
        setDiaId(turmasData[0].dia_id);
        console.log('✅ [carregarDados] dia_id extraído das turmas:', turmasData[0].dia_id);
      } else {
        console.log('⚠️ [carregarDados] Sem turmas - dia_id não pode ser definido');
      }
    } catch (error) {
      showError('Erro ao carregar turmas');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await carregarDados();
    setRefreshing(false);
  };

  const renderizarIconeModalidade = (nomeIcone, cor) => {
    const corIcon = cor || '#3b82f6';
    const iconeParaUsar = nomeIcone && nomeIcone.trim() ? nomeIcone : 'dumbbell';
    
    // Tenta usar diretamente no MaterialCommunityIcons
    return <MaterialCommunityIcons name={iconeParaUsar} size={20} color={corIcon} />;
  };

  const obterModalidadesDoDia = () => {
    const mapa = new Map();
    turmas.forEach((turma) => {
      if (turma.modalidade_id) {
        mapa.set(String(turma.modalidade_id), turma.modalidade_nome || 'Modalidade');
      }
    });
    return Array.from(mapa.entries()).map(([id, nome]) => ({ id, nome }));
  };

  const carregarWodDoDia = async (modalidadeId) => {
    try {
      setLoadingWod(true);
      const response = await buscarWodPorDataModalidade(dataSelecionada, modalidadeId);
      if (response?.type === 'error') {
        showError(response.message || 'Erro ao buscar WOD');
        return;
      }
      const wod = response?.data || response;
      if (!wod || !wod.id) {
        showError('Nenhum WOD encontrado para essa modalidade');
        return;
      }
      setWodData(wod);
      setModalWodVisible(true);
    } catch (error) {
      console.error('Erro ao buscar WOD:', error);
      showError('Erro ao buscar WOD');
    } finally {
      setLoadingWod(false);
    }
  };

  const handleAbrirWod = () => {
    const modalidadesDoDia = obterModalidadesDoDia();
    if (modalidadesDoDia.length === 0) {
      showError('Nenhuma modalidade encontrada para o dia selecionado');
      return;
    }
    if (modalidadesDoDia.length === 1) {
      const { id } = modalidadesDoDia[0];
      setModalidadeWodId(id);
      carregarWodDoDia(id);
      return;
    }
    setModalidadeWodId('');
    setModalWodSelectVisible(true);
  };

  const handleNova = async () => {
    console.log('🔴 [handleNova] Iniciando...');
    setDropdownAberto(null);
    setFormData({ modalidade_id: '', horario_inicio: '', horario_fim: '', professor_id: '', limite_alunos: '', tolerancia_antes_minutos: '', tolerancia_minutos: '', ativo: true });
    setLoadingDropdowns(true);
    
    try {
      // Teste 1: Modalidades
      console.log('🔵 [handleNova] Chamando modalidadeService.listar(true)...');
      let modalities = [];
      try {
        modalities = await modalidadeService.listar(true);
        console.log('✅ [handleNova] Modalidades:', modalities);
        console.log('   Tipo:', typeof modalities);
        console.log('   É array?', Array.isArray(modalities));
        console.log('   Tamanho:', modalities?.length);
      } catch (err) {
        console.error('❌ [handleNova] Erro ao carregar modalidades:', err);
        console.error('   Status:', err.response?.status);
        console.error('   Data:', err.response?.data);
      }

      // Teste 2: Professores
      console.log('🔵 [handleNova] Chamando professorService.listar(true)...');
      let teachers = [];
      try {
        teachers = await professorService.listar(true);
        console.log('✅ [handleNova] Professores:', teachers);
        console.log('   Tipo:', typeof teachers);
        console.log('   É array?', Array.isArray(teachers));
        console.log('   Tamanho:', teachers?.length);
      } catch (err) {
        console.error('❌ [handleNova] Erro ao carregar professores:', err);
        console.error('   Status:', err.response?.status);
        console.error('   Data:', err.response?.data);
      }

      // Teste 3: Horários
      console.log('🔵 [handleNova] Chamando horarioService.listarPorDia(' + diaId + ')...');
      let horariosDia = [];
      try {
        horariosDia = await horarioService.listarPorDia(diaId);
        console.log('✅ [handleNova] Horários:', horariosDia);
        console.log('   Tipo:', typeof horariosDia);
        console.log('   É array?', Array.isArray(horariosDia));
        console.log('   Tamanho:', horariosDia?.length);
      } catch (err) {
        console.error('❌ [handleNova] Erro ao carregar horários:', err);
        console.error('   Status:', err.response?.status);
        console.error('   Data:', err.response?.data);
      }
      
      console.log('🔵 [handleNova] Atualizando estado...');
      setModalidades(modalities || []);
      setProfessores(teachers || []);
      setHorarios(horariosDia || []);
      setErrors({});
      setIsEditando(false);
      setTurmaEditandoId(null);
      
      console.log('🔵 [handleNova] Abrindo modal...');
      setModalCriarVisible(true);
      
      if ((!modalities || modalities.length === 0) && (!teachers || teachers.length === 0)) {
        console.warn('⚠️ [handleNova] Nenhum dado carregado!');
        showError('Nenhum dado carregado. Verifique a API.');
      }
    } catch (error) {
      console.error('💥 [handleNova] Erro geral:', error);
      showError('Erro ao carregar dados do formulário');
    } finally {
      console.log('🔴 [handleNova] Finalizando...');
      setLoadingDropdowns(false);
    }
  };

  const handleEditar = async (turmaId) => {
    try {
      console.log('🔴 [handleEditar] Iniciando...');
      setLoadingDropdowns(true);

      let modalities = [];
      let teachers = [];
      let horariosDia = [];

      try {
        console.log('🔵 [handleEditar] Chamando modalidadeService.listar(true)...');
        const modalidadesResp = await modalidadeService.listar(true);
        modalities = Array.isArray(modalidadesResp) ? modalidadesResp : [];
        console.log('✅ [handleEditar] Modalidades:', modalities);
      } catch (err) {
        console.error('❌ [handleEditar] Erro ao carregar modalidades:', err);
      }

      try {
        console.log('🔵 [handleEditar] Chamando professorService.listar(true)...');
        const professoresResp = await professorService.listar(true);
        teachers = Array.isArray(professoresResp) ? professoresResp : [];
        console.log('✅ [handleEditar] Professores:', teachers);
      } catch (err) {
        console.error('❌ [handleEditar] Erro ao carregar professores:', err);
      }

      try {
        console.log('🔵 [handleEditar] Chamando horarioService.listarPorDia(' + diaId + ')...');
        const horariosResp = await horarioService.listarPorDia(diaId);
        horariosDia = Array.isArray(horariosResp) ? horariosResp : [];
        console.log('✅ [handleEditar] Horários:', horariosDia);
      } catch (err) {
        console.error('❌ [handleEditar] Erro ao carregar horários:', err);
      }

      // Buscar dados completos da turma pela API
      try {
        const turma = await turmaService.buscarPorId(turmaId);
        
        setFormData({
          modalidade_id: String(turma.modalidade_id) || '',
          horario_inicio: turma.horario_inicio ? turma.horario_inicio.substring(0, 5) : '',
          horario_fim: turma.horario_fim ? turma.horario_fim.substring(0, 5) : '',
          professor_id: String(turma.professor_id) || '',
          limite_alunos: String(turma.limite_alunos) || '',
          tolerancia_antes_minutos: turma.tolerancia_antes_minutos !== undefined && turma.tolerancia_antes_minutos !== null ? String(turma.tolerancia_antes_minutos) : '',
          tolerancia_minutos: turma.tolerancia_minutos !== undefined && turma.tolerancia_minutos !== null ? String(turma.tolerancia_minutos) : '',
          ativo: turma.ativo,
        });
      } catch (err) {
        console.error('Erro ao buscar turma:', err);
        showError('Erro ao carregar dados da turma');
      }

      console.log('🔵 [handleEditar] Atualizando estado...');
      setModalidades(modalities || []);
      setProfessores(teachers || []);
      setHorarios(horariosDia || []);
      setErrors({});
      setIsEditando(true);
      setTurmaEditandoId(turmaId);

      console.log('🔵 [handleEditar] Abrindo modal...');
      setModalCriarVisible(true);

      if ((!modalities || modalities.length === 0) && (!teachers || teachers.length === 0)) {
        console.warn('⚠️ [handleEditar] Nenhum dado carregado!');
        showError('Nenhum dado carregado. Verifique a API.');
      }
    } catch (error) {
      console.error('💥 [handleEditar] Erro geral:', error);
      showError('Erro ao carregar dados do formulário');
    } finally {
      console.log('🔴 [handleEditar] Finalizando...');
      setLoadingDropdowns(false);
    }
  };

  const handleReplicar = async () => {
    try {
      if (periodoReplicacao === 'custom' && diasSemanaSelecionados.length === 0) {
        showError('Selecione pelo menos um dia da semana');
        return;
      }

      setReplicando(true);
      const mes = mesReplicacao || `${dataSelecionada.substring(0, 7)}`;
      
      console.log('🔵 [handleReplicar] Chamando API:', {
        diaId,
        periodoReplicacao,
        diasSemanaSelecionados,
        mes,
        modalidadeId: modalidadeReplicarId
      });
      
      const resultado = await turmaService.replicar(diaId, periodoReplicacao, diasSemanaSelecionados, mes, modalidadeReplicarId);

      console.log('✅ [handleReplicar] Resposta da API:', resultado);

      if (resultado.type === 'success') {
        // Extrair informações dos detalhes
        const totalCriadas = resultado.turmas_criadas?.length || 0;
        
        let mensagem = `✅ ${totalCriadas} turmas criadas com sucesso!`;
        
        showSuccess(mensagem);
        setModalReplicarVisible(false);
        setDiasSemanaSelecionados([]);
        setMesReplicacao('');
        setPeriodoReplicacao('custom');
        setModalidadeReplicarId('');
        carregarDados();
      } else {
        console.warn('⚠️ [handleReplicar] Resposta sem sucesso:', resultado);
        showError(resultado.message || 'Erro ao replicar turmas');
      }
    } catch (error) {
      console.error('❌ [handleReplicar] Erro completo:', error);
      console.error('   Response:', error.response?.data);
      console.error('   Status:', error.response?.status);
      showError(error.response?.data?.message || 'Erro ao replicar turmas');
    } finally {
      setReplicando(false);
    }
  };

  const toggleDiaSemana = (dia) => {
    if (diasSemanaSelecionados.includes(dia)) {
      setDiasSemanaSelecionados(diasSemanaSelecionados.filter(d => d !== dia));
    } else {
      setDiasSemanaSelecionados([...diasSemanaSelecionados, dia]);
    }
  };

  const handleAbrirReplicar = async () => {
    console.log('🟣 [handleAbrirReplicar] Iniciando...');
    console.log('🟣 [handleAbrirReplicar] Modalidades atuais:', modalidades.length);
    
    // Carregar modalidades se ainda não foram carregadas
    if (modalidades.length === 0) {
      console.log('🟣 [handleAbrirReplicar] Modalidades vazias, carregando...');
      try {
        setLoadingDropdowns(true);
        console.log('🟣 [handleAbrirReplicar] Chamando modalidadeService.listar(true)...');
        const modalities = await modalidadeService.listar(true);
        console.log('🟣 [handleAbrirReplicar] Modalidades recebidas:', modalities);
        console.log('🟣 [handleAbrirReplicar] Tipo:', typeof modalities);
        console.log('🟣 [handleAbrirReplicar] É array?', Array.isArray(modalities));
        console.log('🟣 [handleAbrirReplicar] Tamanho:', modalities?.length);
        setModalidades(modalities || []);
        console.log('🟣 [handleAbrirReplicar] setModalidades chamado');
      } catch (error) {
        console.error('❌ [handleAbrirReplicar] Erro ao carregar modalidades:', error);
        console.error('   Status:', error.response?.status);
        console.error('   Data:', error.response?.data);
        console.error('   Message:', error.message);
      } finally {
        setLoadingDropdowns(false);
        console.log('🟣 [handleAbrirReplicar] Loading finalizado');
      }
    } else {
      console.log('🟣 [handleAbrirReplicar] Modalidades já carregadas, usando cache');
    }
    
    console.log('🟣 [handleAbrirReplicar] Abrindo modal de replicar');
    setModalReplicarVisible(true);
  };

  const handleDeletarHorariosDia = async () => {
    try {
      console.log('🔴 [handleDeletarHorariosDia] === INÍCIO ===');
      
      // Obter dia_id das turmas filtradas
      const diaIdAtual = turmasFiltradas.length > 0 && turmasFiltradas[0].dia_id 
        ? turmasFiltradas[0].dia_id 
        : diaId;
      
      console.log('🔴 [handleDeletarHorariosDia] Dia ID a ser usado:', diaIdAtual);
      console.log('🔴 [handleDeletarHorariosDia] Tipo do dia ID:', typeof diaIdAtual);
      console.log('🔴 [handleDeletarHorariosDia] Turmas filtradas:', turmasFiltradas.length);
      
      if (!diaIdAtual) {
        showError('Não foi possível identificar o dia para deletar');
        return;
      }
      
      setDeletandoHorarios(true);
      
      console.log('🔴 [handleDeletarHorariosDia] Chamando turmaService.deletarHorariosDia...');
      const resultado = await turmaService.deletarHorariosDia(diaIdAtual);

      console.log('✅ [handleDeletarHorariosDia] Resposta da API:', resultado);

      if (resultado.type === 'success') {
        const totalDeletados = resultado.summary?.total_deletados || 0;
        const data = resultado.summary?.data || '';
        const diaSemana = resultado.summary?.dia_semana || '';
        
        // Montar lista de turmas deletadas
        const turmasDeletadas = resultado.turmas_deletadas || [];
        const listaTurmas = turmasDeletadas
          .map(t => `• ${t.nome} (${t.horario_inicio?.substring(0,5)} - ${t.horario_fim?.substring(0,5)})`)
          .join('\n');
        
        const mensagemDetalhada = `
${totalDeletados} turma(s) deletada(s) com sucesso!

📅 Data: ${data} (${diaSemana})

${listaTurmas ? `Turmas deletadas:\n${listaTurmas}` : ''}
        `.trim();
        
        Alert.alert(
          '✅ Sucesso',
          mensagemDetalhada,
          [{ text: 'OK' }]
        );
        
        carregarDados();
      } else {
        showError(resultado.message || 'Erro ao deletar horários');
      }
    } catch (error) {
      console.error('❌ [handleDeletarHorariosDia] Erro completo:', error);
      console.error('   Response:', error.response?.data);
      console.error('   Status:', error.response?.status);
      showError(error.response?.data?.message || 'Erro ao deletar horários do dia');
    } finally {
      setDeletandoHorarios(false);
    }
  };

  const diasSemanaLabels = [
    'Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'
  ];

  const validarHora = (hora) => {
    // Validar formato HH:MM
    if (!hora || !/^\d{2}:\d{2}$/.test(hora)) {
      return { valida: false, erro: 'Formato inválido. Use HH:MM' };
    }

    const [horas, minutos] = hora.split(':').map(Number);

    // Validar intervalo de horas (0-23)
    if (horas < 0 || horas > 23) {
      return { valida: false, erro: 'Hora deve estar entre 00 e 23' };
    }

    // Validar intervalo de minutos (0-59)
    if (minutos < 0 || minutos > 59) {
      return { valida: false, erro: 'Minutos deve estar entre 00 e 59' };
    }

    return { valida: true };
  };

  const criarTurma = async () => {
    try {
      const newErrors = {};

      if (!formData.modalidade_id) {
        newErrors.modalidade_id = 'Selecione uma modalidade';
      }

      if (!formData.professor_id) {
        newErrors.professor_id = 'Selecione um professor';
      }

      if (!formData.horario_inicio) {
        newErrors.horario_inicio = 'Informe o horário de início';
      } else {
        const validacaoInicio = validarHora(formData.horario_inicio);
        if (!validacaoInicio.valida) {
          newErrors.horario_inicio = validacaoInicio.erro;
        }
      }

      if (!formData.horario_fim) {
        newErrors.horario_fim = 'Informe o horário de término';
      } else {
        const validacaoFim = validarHora(formData.horario_fim);
        if (!validacaoFim.valida) {
          newErrors.horario_fim = validacaoFim.erro;
        }
      }

      if (!formData.limite_alunos) {
        newErrors.limite_alunos = 'Informe o limite de alunos';
      }

      // Validar se horário de fim é após horário de início
      if (formData.horario_inicio && formData.horario_fim && !newErrors.horario_inicio && !newErrors.horario_fim) {
        const [horaI, minI] = formData.horario_inicio.split(':').map(Number);
        const [horaF, minF] = formData.horario_fim.split(':').map(Number);
        const tempoInicio = horaI * 60 + minI;
        const tempoFim = horaF * 60 + minF;

        if (tempoFim <= tempoInicio) {
          newErrors.horario_fim = 'Horário Fim deve ser após Horário Início';
        }
      }

      setErrors(newErrors);

      if (Object.keys(newErrors).length > 0) {
        return;
      }

      setSubmitting(true);

      const novaData = {
        nome: `${modalidades.find(m => String(m.id) === String(formData.modalidade_id))?.nome} - ${formData.horario_inicio} - ${professores.find(p => String(p.id) === String(formData.professor_id))?.nome}`,
        modalidade_id: parseInt(formData.modalidade_id),
        professor_id: parseInt(formData.professor_id),
        dia_id: diaId,
        horario_inicio: formData.horario_inicio + ':00',
        horario_fim: formData.horario_fim + ':00',
        limite_alunos: parseInt(formData.limite_alunos),
        tolerancia_antes_minutos: parseInt(formData.tolerancia_antes_minutos) || 0,
        tolerancia_minutos: parseInt(formData.tolerancia_minutos) || 0,
      };
      
      console.log('📤 [criarTurma] Enviando:', novaData);

      await turmaService.criar(novaData);
      showSuccess('Turma criada com sucesso!');
      
      // Manter dados anteriores em memória
      setDadosAnteriores(formData);
      
      // Fechar a modal de criação
      setModalCriarVisible(false);
      
      // Mostrar modal de confirmação
      setTimeout(() => {
        setShowConfirmeContinuar(true);
      }, 300);
      
      carregarDados();
    } catch (error) {
      console.error('Erro ao criar turma:', error);
      let mensagemErro = 'Erro ao criar turma';
      
      if (error.response?.data?.message) {
        mensagemErro = error.response.data.message;
      } else if (error.message) {
        mensagemErro = error.message;
      }
      
      showError(mensagemErro);
    } finally {
      setSubmitting(false);
    }
  };

  const handleCriarOutra = () => {
    // Restaurar dados anteriores
    if (dadosAnteriores) {
      setFormData(dadosAnteriores);
    }
    
    // Fechar modal de confirmação
    setShowConfirmeContinuar(false);
    
    // Abrir modal de criação novamente
    setTimeout(() => {
      setModalCriarVisible(true);
    }, 300);
  };

  const handleFecharConfirmacao = () => {
    setShowConfirmeContinuar(false);
    setDadosAnteriores(null);
    setFormData({
      modalidade_id: '',
      horario_inicio: '',
      horario_fim: '',
      professor_id: '',
      limite_alunos: '',
      tolerancia_antes_minutos: '',
      tolerancia_minutos: '',
      ativo: true,
    });
    setErrors({});
  };

  const handleDesativarTurma = async () => {
    if (!turmaDesativar) return;
    
    try {
      setDesativando(true);
      
      const mes = mesReplicacao || new Date().toISOString().slice(0, 7);
      
      const response = await turmaService.desativar(
        turmaDesativar.id,
        periodoDesativacao,
        mes
      );
      
      showSuccess(response.message || 'Turma(s) desativada(s) com sucesso!');
      setModalDesativarVisible(false);
      setTurmaDesativar(null);
      setPeriodoDesativacao('apenas_esta');
      
      carregarDados();
    } catch (error) {
      console.error('Erro ao desativar turma:', error);
      let mensagem = 'Erro ao desativar turma';
      if (error.response?.data?.message) {
        mensagem = error.response.data.message;
      }
      showError(mensagem);
    } finally {
      setDesativando(false);
    }
  };

  const atualizarTurma = async (turmaId) => {
    try {
      const newErrors = {};

      if (!formData.modalidade_id) {
        newErrors.modalidade_id = 'Selecione uma modalidade';
      }

      if (!formData.professor_id) {
        newErrors.professor_id = 'Selecione um professor';
      }

      if (!formData.horario_inicio) {
        newErrors.horario_inicio = 'Informe o horário de início';
      } else {
        const validacaoInicio = validarHora(formData.horario_inicio);
        if (!validacaoInicio.valida) {
          newErrors.horario_inicio = validacaoInicio.erro;
        }
      }

      if (!formData.horario_fim) {
        newErrors.horario_fim = 'Informe o horário de término';
      } else {
        const validacaoFim = validarHora(formData.horario_fim);
        if (!validacaoFim.valida) {
          newErrors.horario_fim = validacaoFim.erro;
        }
      }

      if (!formData.limite_alunos) {
        newErrors.limite_alunos = 'Informe o limite de alunos';
      }

      // Validar se horário de fim é após horário de início
      if (formData.horario_inicio && formData.horario_fim && !newErrors.horario_inicio && !newErrors.horario_fim) {
        const [horaI, minI] = formData.horario_inicio.split(':').map(Number);
        const [horaF, minF] = formData.horario_fim.split(':').map(Number);
        const tempoInicio = horaI * 60 + minI;
        const tempoFim = horaF * 60 + minF;

        if (tempoFim <= tempoInicio) {
          newErrors.horario_fim = 'Horário Fim deve ser após Horário Início';
        }
      }

      setErrors(newErrors);

      if (Object.keys(newErrors).length > 0) {
        return;
      }

      setSubmitting(true);

      const dadosAtualizados = {
        nome: `${modalidades.find(m => String(m.id) === String(formData.modalidade_id))?.nome} - ${formData.horario_inicio} - ${professores.find(p => String(p.id) === String(formData.professor_id))?.nome}`,
        modalidade_id: parseInt(formData.modalidade_id),
        professor_id: parseInt(formData.professor_id),
        horario_inicio: formData.horario_inicio + ':00',
        horario_fim: formData.horario_fim + ':00',
        limite_alunos: parseInt(formData.limite_alunos),
        tolerancia_antes_minutos: parseInt(formData.tolerancia_antes_minutos) || 0,
        tolerancia_minutos: parseInt(formData.tolerancia_minutos) || 0,
      };
      
      console.log('📤 [atualizarTurma] Enviando:', dadosAtualizados);

      await turmaService.atualizar(turmaId, dadosAtualizados);
      showSuccess('Turma atualizada com sucesso!');
      setModalCriarVisible(false);
      setErrors({});
      setFormData({
        modalidade_id: '',
        horario_inicio: '',
        horario_fim: '',
        professor_id: '',
        limite_alunos: '',
        tolerancia_antes_minutos: '',
        tolerancia_minutos: '',
        ativo: true,
      });
      carregarDados();
    } catch (error) {
      console.error('Erro ao atualizar turma:', error);
      let mensagemErro = 'Erro ao atualizar turma';
      
      if (error.response?.data?.message) {
        mensagemErro = error.response.data.message;
      } else if (error.message) {
        mensagemErro = error.message;
      }
      
      showError(mensagemErro);
    } finally {
      setSubmitting(false);
    }
  };

  const deletarTurmaConfirmado = async (turmaId) => {
    try {
      setSubmitting(true);
      await turmaService.deletar(turmaId);
      showSuccess('Turma deletada com sucesso!');
      setConfirmDelete({ visible: false, id: null, nome: '' });
      carregarDados();
    } catch (error) {
      console.error('Erro ao deletar turma:', error);
      showError(error.error || 'Erro ao deletar turma');
    } finally {
      setSubmitting(false);
    }
  };

  const handleSearchChange = (termo) => {
    setSearchText(termo);
    const termoLower = termo.toLowerCase();
    const filtradas = turmas.filter(turma => 
      turma.nome?.toLowerCase().includes(termoLower) ||
      turma.modalidade_nome?.toLowerCase().includes(termoLower) ||
      turma.professor_nome?.toLowerCase().includes(termoLower)
    );
    setTurmasFiltradas(filtradas);
  };

  const handleDelete = (id, nome) => {
    setConfirmDelete({ visible: true, id, nome });
  };

  const confirmarDelete = async () => {
    try {
      await turmaService.deletar(confirmDelete.id);
      const updated = turmas.filter(t => t.id !== confirmDelete.id);
      setTurmas(updated);
      setTurmasFiltradas(updated.filter(t => {
        const termoLower = searchText.toLowerCase();
        return (
          t.nome?.toLowerCase().includes(termoLower) ||
          t.modalidade_nome?.toLowerCase().includes(termoLower) ||
          t.professor_nome?.toLowerCase().includes(termoLower)
        );
      }));
      showSuccess('Turma deletada com sucesso');
      setConfirmDelete({ visible: false, id: null, nome: '' });
    } catch (error) {
      console.error('Erro ao deletar:', error);
      showError('Erro ao deletar turma');
    }
  };

  return (
    <LayoutBase noPadding>
      <View style={styles.container}>
        {/* Banner Header */}
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="calendar" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Aulas (Turmas)</Text>
                <Text style={styles.bannerSubtitle}>
                  Gerencie todas as turmas de seu negócio
                </Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>

          {/* Card de Busca e Ações */}
          <View style={[styles.searchCard, isMobile && styles.searchCardMobile]}>
            <View style={styles.searchCardHeader}>
              <View style={styles.searchCardInfo}>
                <View style={styles.searchCardIconContainer}>
                  <Feather name="search" size={20} color="#f97316" />
                </View>
                <View>
                  <Text style={styles.searchCardTitle}>Buscar Aulas</Text>
                  <Text style={styles.searchCardSubtitle}>
                    {turmas.length} {turmas.length === 1 ? 'aula' : 'aulas'} cadastrada(s)
                  </Text>
                </View>
              </View>
              <TouchableOpacity
                style={[styles.addButton, isMobile && styles.addButtonMobile]}
                onPress={handleNova}
                activeOpacity={0.8}
              >
                <Feather name="plus" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>Nova Aula</Text>}
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.replicarButton, isMobile && styles.addButtonMobile]}
                onPress={handleAbrirReplicar}
                activeOpacity={0.8}
              >
                <Feather name="copy" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>Replicar</Text>}
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.wodButton, isMobile && styles.addButtonMobile]}
                onPress={handleAbrirWod}
                activeOpacity={0.8}
              >
                <Feather name="book-open" size={18} color="#fff" />
                {!isMobile && <Text style={styles.addButtonText}>WOD</Text>}
              </TouchableOpacity>
            </View>

            <View style={styles.searchInputContainer}>
              <Feather name="search" size={20} color="#9ca3af" style={styles.searchInputIcon} />
              <TextInput
                style={styles.searchInput}
                placeholder="Buscar por modalidade ou professor..."
                placeholderTextColor="#9ca3af"
                value={searchText}
                onChangeText={handleSearchChange}
              />
              {searchText.length > 0 && (
                <TouchableOpacity onPress={() => handleSearchChange('')} style={styles.clearButton}>
                  <Feather name="x-circle" size={20} color="#9ca3af" />
                </TouchableOpacity>
              )}
            </View>

            {/* Seletor de Data */}
            <View style={styles.dateSelector}>
              <TouchableOpacity 
                style={styles.dateSelectorButton}
                onPress={() => irParaData(-1)}
              >
                <Feather name="chevron-left" size={20} color="#f97316" />
              </TouchableOpacity>

              <TouchableOpacity 
                style={styles.dateSelectorContent}
                onPress={() => setCalendarVisible(true)}
              >
                <Feather name="calendar" size={16} color="#f97316" style={{ marginRight: 8 }} />
                <Text style={styles.dateDisplay}>
                  {obterDiaSemana(dataSelecionada)} • {formatarDataExibicao(dataSelecionada)}
                </Text>
              </TouchableOpacity>

              <TouchableOpacity 
                style={[styles.dateSelectorButton, dataSelecionada !== obterHoje() && styles.hojeButton]}
                onPress={voltarParaHoje}
              >
                <Feather name="home" size={18} color="#f97316" />
              </TouchableOpacity>

              <TouchableOpacity 
                style={styles.dateSelectorButton}
                onPress={() => irParaData(1)}
              >
                <Feather name="chevron-right" size={20} color="#f97316" />
              </TouchableOpacity>

              <TouchableOpacity 
                style={[styles.dateSelectorButton, styles.deleteButton]}
                onPress={() => {
                  console.log('🗑️ [Botão Delete] Pressionado');
                  console.log('🗑️ [Botão Delete] Turmas filtradas:', turmasFiltradas.length);
                  
                  if (turmasFiltradas.length === 0) {
                    showError('Não há turmas neste dia para deletar');
                    return;
                  }
                  
                  console.log('🗑️ [Botão Delete] Abrindo modal de confirmação');
                  setModalDeletarVisible(true);
                }}
                disabled={deletandoHorarios}
              >
                {deletandoHorarios ? (
                  <ActivityIndicator size="small" color="#ef4444" />
                ) : (
                  <Feather name="trash-2" size={18} color="#ef4444" />
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>

        {/* Conteúdo */}
        {turmasFiltradas.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Feather name="calendar" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>
              {searchText ? 'Nenhuma aula encontrada' : 'Nenhuma aula cadastrada'}
            </Text>
          </View>
        ) : (
          <ScrollView className="m-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
            {/* Header da Tabela */}
            <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-3">
              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 0.3 }}>ID</Text>
              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 1.5 }}>MODALIDADE</Text>
              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 1.2 }}>HORÁRIO</Text>
              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 1.5 }}>PROFESSOR</Text>
              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 1 }}>VAGAS</Text>
              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500" style={{ flex: 0.8 }}>STATUS</Text>
              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 text-center" style={{ flex: 0.8 }}>AÇÕES</Text>
            </View>

            {/* Linhas da Tabela */}
            {turmasFiltradas.map((turma) => (
              <TouchableOpacity
                key={turma.id}
                className={`flex-row items-center border-b border-slate-100 px-4 py-3 ${turma.ativo ? 'bg-white' : 'bg-slate-50/60 opacity-70'}`}
                onPress={() => handleEditar(turma.id)}
                activeOpacity={0.7}
              >
                <View style={{ flex: 0.3 }}>
                  <Text className="text-[12px] font-semibold text-slate-400">{turma.id}</Text>
                </View>
                <View style={{ flex: 1.5 }}>
                  <View className="flex-row items-center gap-2 rounded-lg bg-slate-100 px-3 py-2">
                    {renderizarIconeModalidade(turma.modalidade_icone, turma.modalidade_cor)}
                    <Text className="text-[13px] font-semibold text-slate-700" numberOfLines={1}>
                      {turma.modalidade_nome}
                    </Text>
                  </View>
                </View>
                <View style={{ flex: 1.2 }}>
                  <View className="flex-row items-center">
                    <Feather name="clock" size={14} color="#9ca3af" style={{ marginRight: 6 }} />
                    <Text className="text-[13px] text-slate-500" numberOfLines={1}>
                      {formatarHorarioIntervalo(turma.horario_inicio, turma.horario_fim)}
                    </Text>
                  </View>
                </View>
                <Text className="text-[13px] text-slate-500" style={{ flex: 1.5 }} numberOfLines={1}>
                  {turma.professor_nome || '-'}
                </Text>
                <View style={{ flex: 1 }}>
                  <Text className="text-[13px] font-semibold text-slate-700">
                    {turma.alunos_count || 0}/{turma.limite_alunos}
                  </Text>
                </View>
                <View style={{ flex: 1 }}>
                  <View
                    className={`self-start rounded-full px-2.5 py-1 ${turma.ativo ? 'bg-emerald-100' : 'bg-rose-100'}`}
                  >
                    <Text className={`text-[11px] font-bold ${turma.ativo ? 'text-emerald-700' : 'text-rose-700'}`}>
                      {turma.ativo ? 'Ativo' : 'Inativo'}
                    </Text>
                  </View>
                </View>
                <View className="flex-row justify-end gap-2" style={{ flex: 0.8 }}>
                  <TouchableOpacity
                    onPress={() => {
                      setTurmaDesativar(turma);
                      setModalDesativarVisible(true);
                    }}
                    className="h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                  >
                    <MaterialCommunityIcons name="pause-circle" size={18} color="#ef4444" />
                  </TouchableOpacity>
                  <TouchableOpacity
                    onPress={() => handleEditar(turma.id)}
                    className="h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                  >
                    <Feather name="arrow-right" size={18} color="#f97316" />
                  </TouchableOpacity>
                </View>
              </TouchableOpacity>
            ))}
          </ScrollView>
        )}

        {/* Modal Calendário */}
        <Modal
          visible={calendarVisible}
          transparent
          animationType="fade"
          onRequestClose={() => setCalendarVisible(false)}
        >
          <View className="flex-1 items-center justify-center bg-black/40 px-4">
            <View className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
              <View className="flex-row items-center justify-between border-b border-slate-200 pb-3">
                <TouchableOpacity onPress={() => irParaData(-30)}>
                  <Feather name="chevron-left" size={24} color="#f97316" />
                </TouchableOpacity>
                <Text className="text-[15px] font-semibold capitalize text-slate-700">
                  {new Date(dataSelecionada).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}
                </Text>
                <TouchableOpacity onPress={() => irParaData(30)}>
                  <Feather name="chevron-right" size={24} color="#f97316" />
                </TouchableOpacity>
              </View>

              <View className="flex-row justify-between pt-3">
                {['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'].map((dia) => (
                  <Text key={dia} className="w-[14.28%] text-center text-[11px] font-semibold text-slate-500">{dia}</Text>
                ))}
              </View>

              <View className="mt-3 flex-row flex-wrap">
                {gerarDiasDoMes(new Date(dataSelecionada)).map((dia, index) => (
                  <TouchableOpacity
                    key={index}
                    className={`mb-2 w-[14.28%] items-center justify-center rounded-lg ${!dia ? 'bg-transparent' : 'bg-white'} ${dia && dia.toISOString().split('T')[0] === dataSelecionada ? 'bg-orange-500' : ''}`}
                    onPress={() => handleSelecionarDataCalendar(dia)}
                    disabled={!dia}
                  >
                    {dia && (
                      <Text className={`text-[13px] font-semibold ${dia.toISOString().split('T')[0] === dataSelecionada ? 'text-white' : 'text-slate-700'}`}>
                        {dia.getDate()}
                      </Text>
                    )}
                  </TouchableOpacity>
                ))}
              </View>

              <TouchableOpacity
                className="mt-2 items-center rounded-lg bg-orange-500 py-3"
                onPress={() => setCalendarVisible(false)}
              >
                <Text className="text-sm font-semibold text-white">Fechar</Text>
              </TouchableOpacity>
            </View>
          </View>
        </Modal>

        {/* Modal Selecionar Modalidade WOD */}
        <Modal
          visible={modalWodSelectVisible}
          transparent
          animationType="fade"
          onRequestClose={() => setModalWodSelectVisible(false)}
        >
          <View style={styles.wodModalOverlay}>
            <View style={styles.wodModalContainer}>
              <View style={styles.wodModalHeader}>
                <Text style={styles.wodModalTitle}>WOD do Dia</Text>
                <TouchableOpacity onPress={() => setModalWodSelectVisible(false)}>
                  <Feather name="x" size={20} color="#64748b" />
                </TouchableOpacity>
              </View>
              <Text style={styles.wodModalSubtitle}>
                Selecione a modalidade para {formatarDataExibicao(dataSelecionada)}
              </Text>
              <View style={styles.wodModalOptions}>
                {obterModalidadesDoDia().map((modalidade) => {
                  const isActive = modalidadeWodId === modalidade.id;
                  return (
                    <TouchableOpacity
                      key={modalidade.id}
                      style={[styles.wodOptionButton, isActive && styles.wodOptionButtonActive]}
                      onPress={() => setModalidadeWodId(modalidade.id)}
                    >
                      <Text style={[styles.wodOptionText, isActive && styles.wodOptionTextActive]}>
                        {modalidade.nome}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
              <TouchableOpacity
                style={[styles.wodModalAction, !modalidadeWodId && styles.wodModalActionDisabled]}
                onPress={() => {
                  if (!modalidadeWodId) return;
                  setModalWodSelectVisible(false);
                  carregarWodDoDia(modalidadeWodId);
                }}
                disabled={!modalidadeWodId || loadingWod}
              >
                {loadingWod ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.wodModalActionText}>Abrir WOD</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </Modal>

        <WodPreviewModal
          visible={modalWodVisible}
          onClose={() => setModalWodVisible(false)}
          wodData={wodData || {}}
        />

        {/* Modal Criar Turma */}
        <Modal
          visible={modalCriarVisible}
          transparent
          animationType="fade"
          onRequestClose={() => {
            setModalCriarVisible(false);
            setIsEditando(false);
            setTurmaEditandoId(null);
          }}
        >
          <Pressable 
            className="flex-1 items-center justify-center bg-black/40 px-4" 
            onPress={() => {
              setModalCriarVisible(false);
              setIsEditando(false);
              setTurmaEditandoId(null);
            }}
          >
            <Pressable 
              className="max-h-[90%] w-full max-w-2xl rounded-2xl bg-white shadow-xl"
              onPress={(e) => e.stopPropagation()}
            >
              <View className="flex-row items-center justify-between border-b border-slate-200 px-6 py-4">
                <Text className="text-[18px] font-semibold text-slate-800">{isEditando ? 'Editar Aula' : 'Nova Aula'}</Text>
                <TouchableOpacity onPress={() => {
                  setModalCriarVisible(false);
                  setIsEditando(false);
                  setTurmaEditandoId(null);
                }}>
                  <Feather name="x" size={24} color="#94a3b8" />
                </TouchableOpacity>
              </View>

              <ScrollView className="px-6 py-4" showsVerticalScrollIndicator={false}>
                {loadingDropdowns ? (
                  <View className="items-center py-10">
                    <ActivityIndicator size="large" color="#f97316" />
                    <Text className="mt-4 text-sm text-slate-500">Carregando opções...</Text>
                  </View>
                ) : (
                  <>
                    <View style={styles.formGroup}>
                      <Text style={styles.formLabel}>
                        Modalidade
                        <Text style={styles.required}>*</Text>
                      </Text>
                      <SearchableDropdown
                        data={modalidades}
                        value={formData.modalidade_id}
                        onChange={(id) => setFormData({ ...formData, modalidade_id: id })}
                        placeholder="Selecione uma modalidade"
                        style={styles.searchableDropdown}
                      />
                      {errors.modalidade_id && <Text style={styles.errorText}>{errors.modalidade_id}</Text>}
                    </View>

                    <View style={styles.formGroup}>
                      <Text style={styles.formLabel}>
                        Professor
                        <Text style={styles.required}>*</Text>
                      </Text>
                      <SearchableDropdown
                        data={professores}
                        value={formData.professor_id}
                        onChange={(id) => setFormData({ ...formData, professor_id: id })}
                        placeholder="Selecione um professor"
                        style={styles.searchableDropdown}
                      />
                      {errors.professor_id && <Text style={styles.errorText}>{errors.professor_id}</Text>}
                    </View>

                    <View style={styles.horarioRow}>
                      <View style={[styles.formGroup, styles.horarioFormGroup]}>
                        <Text style={styles.formLabel}>
                          Horário Início
                          <Text style={styles.required}>*</Text>
                        </Text>
                        <TextInput
                          style={[
                            styles.formInput,
                            errors.horario_inicio && styles.inputError
                          ]}
                          placeholder="Ex: 06:00"
                          placeholderTextColor="#9ca3af"
                          value={formData.horario_inicio}
                          onChangeText={(text) => {
                            const mascado = mascaraHora(text);
                            setFormData({ ...formData, horario_inicio: mascado });
                            if (errors.horario_inicio) {
                              setErrors({ ...errors, horario_inicio: null });
                            }
                          }}
                          keyboardType="numeric"
                          maxLength={5}
                          editable={!submitting}
                        />
                        {errors.horario_inicio && (
                          <Text style={styles.errorText}>{errors.horario_inicio}</Text>
                        )}
                      </View>

                      <View style={[styles.formGroup, styles.horarioFormGroup]}>
                        <Text style={styles.formLabel}>
                          Horário Fim
                          <Text style={styles.required}>*</Text>
                        </Text>
                        <TextInput
                          style={[
                            styles.formInput,
                            errors.horario_fim && styles.inputError
                          ]}
                          placeholder="Ex: 07:00"
                          placeholderTextColor="#9ca3af"
                          value={formData.horario_fim}
                          onChangeText={(text) => {
                            const mascado = mascaraHora(text);
                            setFormData({ ...formData, horario_fim: mascado });
                            if (errors.horario_fim) {
                              setErrors({ ...errors, horario_fim: null });
                            }
                          }}
                          keyboardType="numeric"
                          maxLength={5}
                          editable={!submitting}
                        />
                        {errors.horario_fim && (
                          <Text style={styles.errorText}>{errors.horario_fim}</Text>
                        )}
                      </View>
                    </View>

                    <View style={styles.formGroup}>
                      <Text style={styles.formLabel}>
                        Limite de Alunos
                        <Text style={styles.required}>*</Text>
                      </Text>
                      <TextInput
                        style={[styles.formInput, errors.limite_alunos && styles.inputError]}
                        placeholder="Quantidade máxima de alunos"
                        placeholderTextColor="#9ca3af"
                        value={formData.limite_alunos}
                        onChangeText={(text) => {
                          setFormData({ ...formData, limite_alunos: text });
                          if (errors.limite_alunos) {
                            setErrors({ ...errors, limite_alunos: null });
                          }
                        }}
                        keyboardType="numeric"
                        editable={!submitting}
                      />
                      {errors.limite_alunos && <Text style={styles.errorText}>{errors.limite_alunos}</Text>}
                    </View>

                    <View style={styles.horarioRow}>
                      <View style={[styles.formGroup, styles.horarioFormGroup]}>
                        <Text style={styles.formLabel}>
                          Tolerância antes (min)
                        </Text>
                        <TextInput
                          style={styles.formInput}
                          placeholder="Ex: 480"
                          placeholderTextColor="#9ca3af"
                          value={formData.tolerancia_antes_minutos}
                          onChangeText={(text) => {
                            const numerico = text.replace(/[^0-9]/g, '');
                            setFormData({ ...formData, tolerancia_antes_minutos: numerico });
                          }}
                          keyboardType="numeric"
                          editable={!submitting}
                        />
                        {formData.tolerancia_antes_minutos && (
                          <Text style={styles.fieldHint}>
                            {(() => {
                              const mins = parseInt(formData.tolerancia_antes_minutos);
                              const horas = Math.floor(mins / 60);
                              const minutosRestantes = mins % 60;
                              if (horas > 0 && minutosRestantes > 0) {
                                return `= ${horas}h ${minutosRestantes}min antes do início`;
                              } else if (horas > 0) {
                                return `= ${horas}h antes do início`;
                              } else {
                                return `${minutosRestantes}min antes do início`;
                              }
                            })()}
                          </Text>
                        )}
                      </View>

                      <View style={[styles.formGroup, styles.horarioFormGroup]}>
                        <Text style={styles.formLabel}>
                          Tolerância após (min)
                        </Text>
                        <TextInput
                          style={styles.formInput}
                          placeholder="Ex: 10"
                          placeholderTextColor="#9ca3af"
                          value={formData.tolerancia_minutos}
                          onChangeText={(text) => {
                            const numerico = text.replace(/[^0-9]/g, '');
                            setFormData({ ...formData, tolerancia_minutos: numerico });
                          }}
                          keyboardType="numeric"
                          editable={!submitting}
                        />
                        {formData.tolerancia_minutos && (
                          <Text style={styles.fieldHint}>
                            {(() => {
                              const mins = parseInt(formData.tolerancia_minutos);
                              const horas = Math.floor(mins / 60);
                              const minutosRestantes = mins % 60;
                              if (horas > 0 && minutosRestantes > 0) {
                                return `= ${horas}h ${minutosRestantes}min após o início`;
                              } else if (horas > 0) {
                                return `= ${horas}h após o início`;
                              } else {
                                return `${minutosRestantes}min após o início`;
                              }
                            })()}
                          </Text>
                        )}
                      </View>
                    </View>

                    <View style={styles.formGroup}>
                      <View style={styles.switchContainer}>
                        <View style={styles.switchLabelCompact}>
                          <Feather 
                            name={formData.ativo ? "check-circle" : "x-circle"} 
                            size={20} 
                            color={formData.ativo ? "#10b981" : "#ef4444"} 
                          />
                          <Text style={styles.formLabel}>Turma Ativa</Text>
                          <Switch
                            value={formData.ativo}
                            onValueChange={(value) => setFormData({ ...formData, ativo: value })}
                            trackColor={{ false: '#d1d5db', true: '#10b981' }}
                            thumbColor={formData.ativo ? '#fff' : '#f3f4f6'}
                            style={styles.switchInline}
                            disabled={submitting}
                          />
                        </View>
                      </View>
                    </View>

                    <View style={styles.formButtonsContainer}>
                      <TouchableOpacity
                        style={[styles.formButton, styles.formButtonCancel]}
                        onPress={() => setModalCriarVisible(false)}
                        disabled={submitting}
                      >
                        <Text style={styles.formButtonText}>Cancelar</Text>
                      </TouchableOpacity>
                      <TouchableOpacity
                        style={[styles.formButton, styles.formButtonSubmit, submitting && styles.formButtonDisabled]}
                        onPress={() => isEditando ? atualizarTurma(turmaEditandoId) : criarTurma()}
                        disabled={submitting}
                      >
                        {submitting ? (
                          <ActivityIndicator size="small" color="#fff" />
                        ) : (
                          <Text style={styles.formButtonTextSubmit}>{isEditando ? 'Salvar' : 'Criar'}</Text>
                        )}
                      </TouchableOpacity>
                    </View>
                  </>
                )}
              </ScrollView>
            </Pressable>
          </Pressable>
        </Modal>

        {/* Modal de Confirmação */}
        <ConfirmModal
          visible={confirmDelete.visible}
          title="Deletar Aula"
          message={`Deseja realmente deletar a aula de ${confirmDelete.nome}?`}
          onConfirm={confirmarDelete}
          onCancel={() => setConfirmDelete({ visible: false, id: null, nome: '' })}
        />

        {/* Modal Replicar Turmas */}
        <Modal
          visible={modalReplicarVisible}
          transparent
          animationType="fade"
          onRequestClose={() => setModalReplicarVisible(false)}
        >
          <Pressable 
            className="flex-1 items-center justify-center bg-black/40 px-4" 
            onPress={() => setModalReplicarVisible(false)}
          >
            <Pressable 
              className="max-h-[90%] w-full max-w-2xl rounded-2xl bg-white shadow-xl"
              onPress={(e) => e.stopPropagation()}
            >
              <View className="flex-row items-center justify-between border-b border-slate-200 px-6 py-4">
                <Text className="text-[18px] font-semibold text-slate-800">Replicar Horários por Modalidade</Text>
                <TouchableOpacity onPress={() => setModalReplicarVisible(false)}>
                  <Feather name="x" size={24} color="#94a3b8" />
                </TouchableOpacity>
              </View>

              <ScrollView className="px-6 py-4" showsVerticalScrollIndicator={false}>
                <View style={styles.formGroup}>
                  <Text style={styles.formLabel}>Data de Origem</Text>
                  <Text style={styles.replicarDataTexto}>
                    {formatarDataExibicao(dataSelecionada)} ({obterDiaSemana(dataSelecionada)})
                  </Text>
                </View>

                <View style={styles.formGroup}>
                  <Text style={styles.formLabel}>
                    Modalidade (Opcional)
                  </Text>
                  <SearchableDropdown
                    data={modalidades}
                    value={modalidadeReplicarId}
                    onChange={setModalidadeReplicarId}
                    placeholder="Todas as modalidades"
                    labelKey="nome"
                    valueKey="id"
                  />
                  <Text style={styles.helperText}>Deixe vazio para replicar todas as modalidades</Text>
                </View>

                <View style={styles.formGroup}>
                  <Text style={styles.formLabel}>
                    Tipo de Replicação
                    <Text style={styles.required}>*</Text>
                  </Text>
                  <View style={styles.toggleContainer}>
                    <TouchableOpacity
                      style={[
                        styles.toggleButton,
                        periodoReplicacao === 'proxima_semana' && styles.toggleButtonActive
                      ]}
                      onPress={() => setPeriodoReplicacao('proxima_semana')}
                      disabled={replicando}
                    >
                      <Text
                        style={[
                          styles.toggleText,
                          periodoReplicacao === 'proxima_semana' && styles.toggleTextActive
                        ]}
                      >
                        Próxima Semana
                      </Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[
                        styles.toggleButton,
                        periodoReplicacao === 'mes_todo' && styles.toggleButtonActive
                      ]}
                      onPress={() => setPeriodoReplicacao('mes_todo')}
                      disabled={replicando}
                    >
                      <Text
                        style={[
                          styles.toggleText,
                          periodoReplicacao === 'mes_todo' && styles.toggleTextActive
                        ]}
                      >
                        Mês Inteiro
                      </Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[
                        styles.toggleButton,
                        periodoReplicacao === 'custom' && styles.toggleButtonActive
                      ]}
                      onPress={() => setPeriodoReplicacao('custom')}
                      disabled={replicando}
                    >
                      <Text
                        style={[
                          styles.toggleText,
                          periodoReplicacao === 'custom' && styles.toggleTextActive
                        ]}
                      >
                        Customizado
                      </Text>
                    </TouchableOpacity>
                  </View>
                </View>

                {periodoReplicacao === 'custom' && (
                  <View style={styles.formGroup}>
                    <Text style={styles.formLabel}>
                      Dias da Semana
                      <Text style={styles.required}>*</Text>
                    </Text>
                    <View style={styles.diasSemanaContainer}>
                      {diasSemanaLabels.map((label, index) => (
                        <TouchableOpacity
                          key={index + 1}
                          style={[
                            styles.diaButton,
                            diasSemanaSelecionados.includes(index + 1) && styles.diaButtonAtivo
                          ]}
                          onPress={() => toggleDiaSemana(index + 1)}
                          disabled={replicando}
                        >
                          <Text
                            style={[
                              styles.diaButtonText,
                              diasSemanaSelecionados.includes(index + 1) && styles.diaButtonTextoAtivo
                            ]}
                          >
                            {label}
                          </Text>
                        </TouchableOpacity>
                      ))}
                    </View>
                  </View>
                )}

                {(periodoReplicacao === 'mes_todo' || periodoReplicacao === 'custom') && (
                  <View style={styles.formGroup}>
                    <Text style={styles.formLabel}>Mês (Opcional)</Text>
                    <TextInput
                      style={styles.formInput}
                      placeholder="YYYY-MM (ex: 2026-02)"
                      placeholderTextColor="#9ca3af"
                      value={mesReplicacao}
                      onChangeText={setMesReplicacao}
                      editable={!replicando}
                    />
                    <Text style={styles.helperText}>Deixe vazio para usar o mês atual ({dataSelecionada.substring(0, 7)})</Text>
                  </View>
                )}

                <View style={styles.formButtonRow}>
                  <TouchableOpacity
                    style={[styles.formButton, replicando && styles.formButtonDisabled]}
                    onPress={() => setModalReplicarVisible(false)}
                    disabled={replicando}
                  >
                    <Text style={styles.formButtonText}>Cancelar</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.formButton, styles.formButtonSubmit, replicando && styles.formButtonDisabled]}
                    onPress={handleReplicar}
                    disabled={replicando}
                  >
                    {replicando ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <Text style={styles.formButtonTextSubmit}>Replicar</Text>
                    )}
                  </TouchableOpacity>
                </View>
              </ScrollView>
            </Pressable>
          </Pressable>
        </Modal>

        {/* Loading Overlay */}
        {loading && (
          <View style={styles.loadingOverlay}>
            <View style={styles.loadingBox}>
              <ActivityIndicator size="large" color="#f97316" />
              <Text style={styles.loadingText}>Carregando aulas...</Text>
            </View>
          </View>
        )}

        {/* Modal Confirmação Criar Outra Turma */}
        <Modal
          visible={showConfirmeContinuar}
          transparent
          animationType="fade"
          onRequestClose={handleFecharConfirmacao}
        >
          <View className="flex-1 items-center justify-center bg-black/40 px-4">
            <View className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
              <View className="mb-3">
                <Text className="text-center text-[18px] font-semibold text-slate-800">Turma criada!</Text>
              </View>
              
              <Text className="mb-5 text-center text-sm text-slate-500">
                Deseja criar outra turma com os mesmos dados?
              </Text>
              
              <View className="flex-row gap-3">
                <TouchableOpacity 
                  className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 py-3"
                  onPress={handleFecharConfirmacao}
                >
                  <Text className="text-sm font-semibold text-slate-600">Voltar</Text>
                </TouchableOpacity>
                
                <TouchableOpacity 
                  className="flex-1 items-center justify-center rounded-lg bg-orange-500 py-3"
                  onPress={handleCriarOutra}
                >
                  <Text className="text-sm font-semibold text-white">Criar Outra</Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        </Modal>

        {/* Modal Desativar Turma */}
        <Modal
          visible={modalDesativarVisible}
          transparent
          animationType="fade"
          onRequestClose={() => setModalDesativarVisible(false)}
        >
          <Pressable 
            className="flex-1 items-center justify-center bg-black/40 px-4"
            onPress={() => setModalDesativarVisible(false)}
          >
            <Pressable className="max-h-[80%] w-full max-w-lg rounded-2xl bg-white shadow-xl" onPress={(e) => e.stopPropagation()}>
              <View className="border-b border-slate-200 px-6 py-4">
                <Text className="text-[17px] font-semibold text-slate-800">Desativar Aula</Text>
              </View>

              {turmaDesativar && (
                <View className="mx-6 mt-4 rounded-lg bg-orange-50 px-4 py-3">
                  <Text className="text-[12px] font-medium text-orange-700">
                    {turmaDesativar.nome}
                  </Text>
                </View>
              )}

              <ScrollView className="px-6 py-4" showsVerticalScrollIndicator={false}>
                {/* Seleção de Período */}
                <View style={styles.formGroup}>
                  <Text style={styles.formLabel}>Período de Desativação</Text>
                  <View style={styles.periodoOptions}>
                    {[
                      { label: 'Apenas Esta', value: 'apenas_esta' },
                      { label: 'Próxima Semana', value: 'proxima_semana' },
                      { label: 'Mês Inteiro', value: 'mes_todo' },
                    ].map((option) => (
                      <TouchableOpacity
                        key={option.value}
                        style={[
                          styles.periodoButton,
                          periodoDesativacao === option.value && styles.periodoButtonActive
                        ]}
                        onPress={() => setPeriodoDesativacao(option.value)}
                      >
                        <Text style={[
                          styles.periodoButtonText,
                          periodoDesativacao === option.value && styles.periodoButtonTextActive
                        ]}>
                          {option.label}
                        </Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>

                {/* Seleção de Mês */}
                {(periodoDesativacao === 'mes_todo') && (
                  <View style={styles.formGroup}>
                    <Text style={styles.formLabel}>Mês</Text>
                    <TextInput
                      style={styles.formInput}
                      placeholder="2026-01"
                      placeholderTextColor="#9ca3af"
                      value={mesReplicacao}
                      onChangeText={setMesReplicacao}
                    />
                  </View>
                )}
              </ScrollView>

              <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
                <TouchableOpacity
                  className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 py-3"
                  onPress={() => setModalDesativarVisible(false)}
                  disabled={desativando}
                >
                  <Text className="text-sm font-semibold text-slate-600">Cancelar</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  className="flex-1 items-center justify-center rounded-lg bg-orange-500 py-3"
                  onPress={handleDesativarTurma}
                  disabled={desativando}
                >
                  {desativando ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Text className="text-sm font-semibold text-white">Desativar</Text>
                  )}
                </TouchableOpacity>
              </View>
            </Pressable>
          </Pressable>
        </Modal>

        {/* Modal Deletar Horários do Dia */}
        <Modal
          visible={modalDeletarVisible}
          transparent
          animationType="fade"
          onRequestClose={() => setModalDeletarVisible(false)}
        >
          <Pressable 
            className="flex-1 items-center justify-center bg-black/40 px-4"
            onPress={() => setModalDeletarVisible(false)}
          >
            <Pressable className="w-full max-w-lg rounded-2xl bg-white shadow-xl" onPress={(e) => e.stopPropagation()}>
              <View className="border-b border-slate-200 px-6 py-4">
                <Text className="text-[17px] font-semibold text-red-600">⚠️ Confirmar Exclusão</Text>
              </View>

              <View className="px-6 py-4">
                <Text className="mb-4 text-[15px] text-slate-700">
                  Você está prestes a deletar <Text className="font-bold">TODAS as {turmasFiltradas.length} turma(s)</Text> do dia:
                </Text>
                
                <View className="mb-4 rounded-lg bg-orange-50 px-4 py-3">
                  <Text className="text-[13px] font-semibold text-orange-700">
                    📅 {formatarDataExibicao(dataSelecionada)} ({obterDiaSemana(dataSelecionada)})
                  </Text>
                </View>

                <Text className="mb-2 text-[13px] font-semibold text-slate-600">Turmas que serão deletadas:</Text>
                <ScrollView className="max-h-64 rounded-lg bg-slate-50 px-4 py-3" showsVerticalScrollIndicator={true}>
                  {turmasFiltradas.map((t, index) => (
                    <Text key={index} className="mb-2 text-[13px] text-slate-700">
                      • {t.modalidade_nome || 'Sem modalidade'} - {t.horario_inicio?.substring(0,5)} às {t.horario_fim?.substring(0,5)} (Prof. {t.professor_nome || 'N/A'})
                    </Text>
                  ))}
                </ScrollView>

                <View className="mt-4 rounded-lg bg-red-50 px-4 py-3">
                  <Text className="text-center text-[13px] font-semibold text-red-600">
                    ⚠️ Esta ação não pode ser desfeita!
                  </Text>
                </View>
              </View>

              <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
                <TouchableOpacity
                  className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 py-3"
                  onPress={() => {
                    console.log('🗑️ [Modal] Exclusão cancelada');
                    setModalDeletarVisible(false);
                  }}
                  disabled={deletandoHorarios}
                >
                  <Text className="text-sm font-semibold text-slate-600">Cancelar</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  className="flex-1 items-center justify-center rounded-lg bg-red-500 py-3"
                  onPress={() => {
                    console.log('🗑️ [Modal] Confirmado - executando exclusão');
                    setModalDeletarVisible(false);
                    handleDeletarHorariosDia();
                  }}
                  disabled={deletandoHorarios}
                >
                  {deletandoHorarios ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Text className="text-sm font-semibold text-white">Deletar Tudo</Text>
                  )}
                </TouchableOpacity>
              </View>
            </Pressable>
          </Pressable>
        </Modal>
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f9fafb' },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  loadingOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.4)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 999,
  },
  loadingBox: {
    backgroundColor: '#fff',
    borderRadius: 16,
    paddingVertical: 32,
    paddingHorizontal: 40,
    alignItems: 'center',
    gap: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 12,
    elevation: 8,
  },
  loadingText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
    marginTop: 8,
  },

  // Confirmação Modal Styles
  confirmModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 20,
  },
  confirmModalContent: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 28,
    maxWidth: 420,
    width: '100%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 20,
    elevation: 12,
  },
  confirmModalHeader: {
    marginBottom: 16,
  },
  confirmModalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#4b5563',
    textAlign: 'center',
  },
  confirmModalMessage: {
    fontSize: 15,
    color: '#6b7280',
    textAlign: 'center',
    marginBottom: 24,
    lineHeight: 22,
  },
  confirmModalButtons: {
    flexDirection: 'row',
    gap: 12,
  },
  confirmModalButton: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1.5,
  },
  confirmModalButtonPrimary: {
    backgroundColor: '#10b981',
    borderColor: '#10b981',
  },
  confirmModalButtonPrimaryText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#ffffff',
  },
  confirmModalButtonSecondary: {
    backgroundColor: '#f3f4f6',
    borderColor: '#d1d5db',
  },
  confirmModalButtonSecondaryText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#4b5563',
  },

  // Desativar Modal Styles
  desativarModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 20,
  },
  desativarModalContent: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    maxWidth: 500,
    width: '100%',
    maxHeight: '80%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 20,
    elevation: 12,
  },
  desativarModalHeader: {
    paddingVertical: 16,
    paddingHorizontal: 24,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  desativarModalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#4b5563',
  },
  desativarModalInfo: {
    paddingHorizontal: 24,
    paddingVertical: 12,
    backgroundColor: '#fef3c7',
    borderRadius: 8,
    marginHorizontal: 24,
    marginTop: 16,
  },
  desativarModalInfoText: {
    fontSize: 13,
    color: '#92400e',
    fontWeight: '500',
  },
  desativarModalForm: {
    paddingHorizontal: 24,
    paddingVertical: 16,
  },
  desativarModalButtons: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 24,
    paddingVertical: 16,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  desativarModalButton: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1.5,
  },
  desativarModalButtonPrimary: {
    backgroundColor: '#ef4444',
    borderColor: '#ef4444',
  },
  desativarModalButtonPrimaryText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#ffffff',
  },
  desativarModalButtonSecondary: {
    backgroundColor: '#f3f4f6',
    borderColor: '#d1d5db',
  },
  desativarModalButtonSecondaryText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#4b5563',
  },
  periodoOptions: {
    flexDirection: 'column',
    gap: 10,
  },
  periodoButton: {
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    borderWidth: 1.5,
    borderColor: '#d1d5db',
    backgroundColor: '#f9fafb',
  },
  periodoButtonActive: {
    backgroundColor: '#fef3c7',
    borderColor: '#f59e0b',
  },
  periodoButtonText: {
    fontSize: 14,
    color: '#6b7280',
    fontWeight: '500',
    textAlign: 'center',
  },
  periodoButtonTextActive: {
    color: '#92400e',
    fontWeight: '600',
  },

  // Banner Header
  bannerContainer: {
    backgroundColor: '#f9fafb',
  },
  banner: {
    backgroundColor: '#f97316',
    paddingVertical: 28,
    paddingHorizontal: 24,
    position: 'relative',
    overflow: 'hidden',
  },
  bannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 18,
    zIndex: 2,
  },
  bannerIconContainer: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconOuter: {
    width: 64,
    height: 64,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerIconInner: {
    width: 48,
    height: 48,
    borderRadius: 14,
    backgroundColor: 'rgba(255, 255, 255, 0.25)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  bannerTextContainer: {
    flex: 1,
  },
  bannerTitle: {
    fontSize: 26,
    fontWeight: '800',
    color: '#fff',
    letterSpacing: -0.5,
  },
  bannerSubtitle: {
    fontSize: 14,
    color: 'rgba(255, 255, 255, 0.85)',
    marginTop: 4,
    lineHeight: 20,
  },
  bannerDecoration: {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    width: 200,
    zIndex: 1,
  },
  decorCircle1: {
    position: 'absolute',
    top: -30,
    right: -30,
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
  },
  decorCircle2: {
    position: 'absolute',
    top: 40,
    right: 60,
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  decorCircle3: {
    position: 'absolute',
    bottom: -20,
    right: 20,
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(255, 255, 255, 0.06)',
  },
  // Search Card
  searchCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 20,
    marginHorizontal: 20,
    marginTop: -24,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
    zIndex: 10,
  },
  searchCardMobile: {
    marginHorizontal: 16,
    padding: 16,
  },
  searchCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 16,
    gap: 12,
    flexWrap: 'wrap',
  },
  searchCardInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  searchCardIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  searchCardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#4b5563',
  },
  searchCardSubtitle: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e5e7eb',
    paddingHorizontal: 14,
    height: 52,
  },
  searchInputIcon: {
    marginRight: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 16,
    color: '#4b5563',
    outlineStyle: 'none',
    height: '100%',
  },
  clearButton: {
    padding: 6,
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f97316',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
    gap: 8,
  },
  addButtonMobile: {
    paddingVertical: 10,
    paddingHorizontal: 10,
    borderRadius: 50,
  },
  addButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  wodButton: {
    backgroundColor: '#0ea5e9',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginLeft: 12,
  },

  // Desktop Table
  tableContainer: {
    margin: 12,
    backgroundColor: '#fff',
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f9fafb',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 2,
    borderBottomColor: '#e5e5e5',
  },
  tableRow: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
    backgroundColor: '#fff',
  },
  tableRowInativo: {
    opacity: 0.6,
    backgroundColor: '#f9fafb',
  },
  tableHeaderText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  idCell: {
    flex: 0.3,
  },
  idText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#9ca3af',
  },
  modalidadeCell: {
    flex: 1.5,
  },
  modalidadeContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: '#f3f4f6',
    alignSelf: 'flex-start',
  },
  modalidadeText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#4b5563',
  },
  horarioCell: {
    flex: 1.2,
    fontSize: 13,
    color: '#6b7280',
  },
  horarioContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  horarioText: {
    fontSize: 13,
    color: '#6b7280',
  },
  professorCell: {
    flex: 1.5,
    fontSize: 13,
    color: '#6b7280',
  },
  vagasCell: {
    flex: 1,
    alignItems: 'flex-start',
  },
  vagasText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#4b5563',
  },
  statusCell: {
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 20,
    alignSelf: 'flex-start',
  },
  statusAtivo: {
    backgroundColor: '#d1fae5',
  },
  statusInativo: {
    backgroundColor: '#fee2e2',
  },
  statusText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#047857',
  },
  statusInativoText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#991b1b',
  },
  dateSelector: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 10,
    gap: 10,
    backgroundColor: '#fff7ed',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#fed7aa',
    marginTop: 12,
  },
  dateSelectorButton: {
    padding: 8,
    borderRadius: 10,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#fed7aa',
    justifyContent: 'center',
    alignItems: 'center',
    minWidth: 40,
    minHeight: 40,
  },
  hojeButton: {
    backgroundColor: '#ffedd5',
    borderColor: '#fdba74',
  },
  deleteButton: {
    backgroundColor: '#fee2e2',
    borderColor: '#fecaca',
    marginLeft: 8,
  },
  dateSelectorContent: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 6,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#fed7aa',
    paddingVertical: 8,
    borderRadius: 999,
  },
  dateDisplay: {
    fontSize: 14,
    fontWeight: '600',
    color: '#9a3412',
  },
  acoesCell: {
    flex: 0.8,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'flex-end',
  },
  actionIconButton: {
    padding: 8,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    minWidth: 40,
    minHeight: 40,
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },

  // Empty State
  emptyContainer: {
    padding: 80,
    alignItems: 'center',
    backgroundColor: '#fff',
    margin: 20,
    borderRadius: 12,
  },
  emptyText: {
    fontSize: 15,
    color: '#9ca3af',
    marginTop: 16,
    textAlign: 'center',
  },

  // Modal
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 16,
    width: '90%',
    maxWidth: 400,
    paddingVertical: 20,
    paddingHorizontal: 24,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#4b5563',
  },
  modalOptions: {
    gap: 12,
  },
  modalOption: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
    paddingHorizontal: 16,
    paddingVertical: 16,
    borderRadius: 12,
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  modalOptionText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#4b5563',
  },

  // Calendar
  calendarOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  calendarContent: {
    backgroundColor: '#fff',
    borderRadius: 16,
    width: '90%',
    maxWidth: 400,
    padding: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 12,
    elevation: 8,
  },
  calendarHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  calendarTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#4b5563',
    textTransform: 'capitalize',
  },
  calendarDaysOfWeek: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  calendarDayOfWeekText: {
    width: '14.28%',
    textAlign: 'center',
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
  },
  calendarGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginBottom: 20,
  },
  calendarDay: {
    width: '14.28%',
    aspectRatio: 1,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 8,
    marginBottom: 8,
  },
  calendarDayEmpty: {
    backgroundColor: 'transparent',
  },
  calendarDaySelected: {
    backgroundColor: '#f97316',
  },
  calendarDayText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#4b5563',
  },
  calendarDayTextSelected: {
    color: '#fff',
  },
  calendarCloseButton: {
    backgroundColor: '#f97316',
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  calendarCloseButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },

  // Criar Turma Modal
  criarTurmaOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 9999,
  },
  criarTurmaContent: {
    backgroundColor: '#fff',
    borderRadius: 24,
    width: 700,
    maxHeight: '90%',
    paddingTop: 0,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 10,
    zIndex: 10000,
  },
  criarTurmaHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 24,
    paddingVertical: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  criarTurmaTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#4b5563',
  },
  criarTurmaForm: {
    paddingHorizontal: 24,
    paddingVertical: 20,
  },
  formGroup: {
    marginBottom: 12,
  },
  horarioRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  horarioFormGroup: {
    flex: 1,
    marginBottom: 0,
  },
  formButtonsContainer: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 16,
  },
  formButton: {
    flex: 1,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  formButtonCancel: {
    backgroundColor: '#f3f4f6',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  formButtonSubmit: {
    backgroundColor: '#f97316',
  },
  formButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6b7280',
  },
  formButtonTextSubmit: {
    fontSize: 13,
    fontWeight: '600',
    color: '#fff',
  },
  formButtonDisabled: {
    opacity: 0.6,
  },
  formLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#4b5563',
    marginBottom: 8,
  },
  required: {
    color: '#ef4444',
  },
  formInput: {
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    color: '#4b5563',
    fontWeight: '500',
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
  fieldHint: {
    fontSize: 11,
    color: '#9ca3af',
    marginTop: 4,
    marginLeft: 4,
    fontStyle: 'italic',
  },
  fieldLabel: {
    fontSize: 13,
    color: '#6b7280',
    fontWeight: '600',
    marginTop: 6,
    textAlign: 'center',
  },
  switchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  switchLabelCompact: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  switchInline: {
    marginLeft: 'auto',
  },
  toggleContainer: {
    flexDirection: 'row',
    gap: 12,
  },
  toggleButton: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    backgroundColor: '#f9fafb',
    alignItems: 'center',
  },
  toggleButtonActive: {
    borderColor: '#f97316',
    backgroundColor: '#f97316',
  },
  toggleText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
  },
  toggleTextActive: {
    color: '#fff',
  },

  // Dropdown
  dropdownTrigger: {
    backgroundColor: '#f9fafb',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  dropdownMenu: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    borderTopWidth: 0,
    marginTop: -1,
    zIndex: 100,
    maxHeight: 200,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 5,
  },
  dropdownItem: {
    paddingHorizontal: 14,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  dropdownItemSelected: {
    backgroundColor: '#fff7ed',
  },
  dropdownItemText: {
    fontSize: 14,
    color: '#4b5563',
    fontWeight: '500',
  },
  dropdownItemTextSelected: {
    color: '#f97316',
    fontWeight: '600',
  },
  searchableDropdown: {
    zIndex: 1000,
    position: 'relative',
  },

  // Replicar
  replicarButton: {
    backgroundColor: '#10b981',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginLeft: 12,
  },
  wodModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.45)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 16,
  },
  wodModalContainer: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.2,
    shadowRadius: 16,
    elevation: 8,
  },
  wodModalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  wodModalTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#0f172a',
  },
  wodModalSubtitle: {
    marginTop: 6,
    fontSize: 12,
    color: '#64748b',
  },
  wodModalOptions: {
    marginTop: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  wodOptionButton: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    backgroundColor: '#f8fafc',
  },
  wodOptionButtonActive: {
    borderColor: '#0ea5e9',
    backgroundColor: '#e0f2fe',
  },
  wodOptionText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#475569',
  },
  wodOptionTextActive: {
    color: '#0ea5e9',
  },
  wodModalAction: {
    marginTop: 16,
    backgroundColor: '#0ea5e9',
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  wodModalActionDisabled: {
    opacity: 0.5,
  },
  wodModalActionText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 13,
  },
  replicarDataTexto: {
    fontSize: 15,
    color: '#4b5563',
    fontWeight: '600',
    paddingVertical: 12,
    paddingHorizontal: 14,
    backgroundColor: '#f0fdf4',
    borderRadius: 10,
    borderLeftWidth: 4,
    borderLeftColor: '#10b981',
  },
  diasSemanaContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 8,
    marginBottom: 8,
  },
  diaButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    backgroundColor: '#f3f4f6',
    borderWidth: 2,
    borderColor: '#e5e7eb',
    alignItems: 'center',
  },
  diaButtonAtivo: {
    backgroundColor: '#10b981',
    borderColor: '#059669',
  },
  diaButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6b7280',
  },
  diaButtonTextoAtivo: {
    color: '#fff',
  },
  helperText: {
    fontSize: 12,
    color: '#9ca3af',
    marginTop: 6,
    fontWeight: '500',
  },
  formButtonRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 16,
  },
});
