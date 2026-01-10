import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, ActivityIndicator, useWindowDimensions, TextInput, Modal, FlatList, Pressable, Switch } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { turmaService } from '../../services/turmaService';
import modalidadeService from '../../services/modalidadeService';
import { professorService } from '../../services/professorService';
import { horarioService } from '../../services/horarioService';
import LayoutBase from '../../components/LayoutBase';
import ConfirmModal from '../../components/ConfirmModal';
import SearchableDropdown from '../../components/SearchableDropdown';
import { showSuccess, showError } from '../../utils/toast';
import { mascaraHora } from '../../utils/masks';

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
      
      setTurmas(turmasData);
      setTurmasFiltradas(turmasData);
      setSearchText('');
      
      // Capturar dia_id do objeto dia retornado
      if (turmasData.dia && turmasData.dia.id) {
        setDiaId(turmasData.dia.id);
        console.log('✅ [carregarDados] dia_id atualizado:', turmasData.dia.id);
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
    const icosMaterialCommunity = ['weight-lifter', 'yoga', 'swimming', 'dumbbell', 'bicycle'];
    
    if (icosMaterialCommunity.includes(nomeIcone)) {
      return <MaterialCommunityIcons name={nomeIcone || 'circle'} size={20} color={corIcon} />;
    }
    
    return <Feather name={nomeIcone || 'circle'} size={20} color={corIcon} />;
  };

  const handleNova = async () => {
    console.log('🔴 [handleNova] Iniciando...');
    setDropdownAberto(null);
    setFormData({ modalidade_id: '', horario_inicio: '', horario_fim: '', professor_id: '', limite_alunos: '', ativo: true });
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

      // Buscar dados da turma
      const turma = turmasFiltradas.find(t => t.id === turmaId);
      if (turma) {
        console.log('✅ [handleEditar] Turma encontrada:', turma);
        setFormData({
          modalidade_id: String(turma.modalidade_id) || '',
          horario_inicio: turma.horario_inicio ? turma.horario_inicio.substring(0, 5) : '',
          horario_fim: turma.horario_fim ? turma.horario_fim.substring(0, 5) : '',
          professor_id: String(turma.professor_id) || '',
          limite_alunos: String(turma.limite_alunos) || '',
          ativo: turma.ativo,
        });
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
      };
      
      console.log('📤 [criarTurma] Enviando:', novaData);

      await turmaService.criar(novaData);
      showSuccess('Turma criada com sucesso!');
      setModalCriarVisible(false);
      setErrors({});
      setFormData({
        modalidade_id: '',
        horario_inicio: '',
        horario_fim: '',
        professor_id: '',
        limite_alunos: '',
        ativo: true,
      });
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
                <Feather name="chevron-left" size={20} color="#fff" />
              </TouchableOpacity>

              <TouchableOpacity 
                style={styles.dateSelectorContent}
                onPress={() => setCalendarVisible(true)}
              >
                <Feather name="calendar" size={16} color="#fff" style={{ marginRight: 8 }} />
                <Text style={styles.dateDisplay}>
                  {obterDiaSemana(dataSelecionada)} • {formatarDataExibicao(dataSelecionada)}
                </Text>
              </TouchableOpacity>

              <TouchableOpacity 
                style={[styles.dateSelectorButton, dataSelecionada !== obterHoje() && styles.hojeButton]}
                onPress={voltarParaHoje}
              >
                <Feather name="home" size={18} color="#fff" />
              </TouchableOpacity>

              <TouchableOpacity 
                style={styles.dateSelectorButton}
                onPress={() => irParaData(1)}
              >
                <Feather name="chevron-right" size={20} color="#fff" />
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
          <ScrollView style={styles.tableContainer}>
            {/* Header da Tabela */}
            <View style={styles.tableHeader}>
              <Text style={[styles.tableHeaderText, { flex: 0.3 }]}>ID</Text>
              <Text style={[styles.tableHeaderText, { flex: 1.5 }]}>MODALIDADE</Text>
              <Text style={[styles.tableHeaderText, { flex: 1.2 }]}>HORÁRIO</Text>
              <Text style={[styles.tableHeaderText, { flex: 1.5 }]}>PROFESSOR</Text>
              <Text style={[styles.tableHeaderText, { flex: 1 }]}>VAGAS</Text>
              <Text style={[styles.tableHeaderText, { flex: 0.8 }]}>STATUS</Text>
              <Text style={[styles.tableHeaderText, { flex: 0.6, textAlign: 'center' }]}>AÇÕES</Text>
            </View>

            {/* Linhas da Tabela */}
            {turmasFiltradas.map((turma) => (
              <TouchableOpacity
                key={turma.id}
                style={styles.tableRow}
                onPress={() => handleEditar(turma.id)}
                activeOpacity={0.7}
              >
                <View style={styles.idCell}>
                  <Text style={styles.idText}>{turma.id}</Text>
                </View>
                <View style={styles.modalidadeCell}>
                  <View style={styles.modalidadeContent}>
                    {renderizarIconeModalidade(turma.modalidade_icone, turma.modalidade_cor)}
                    <Text style={styles.modalidadeText} numberOfLines={1}>
                      {turma.modalidade_nome}
                    </Text>
                  </View>
                </View>
                <View style={styles.horarioCell}>
                  <View style={styles.horarioContent}>
                    <Feather name="clock" size={14} color="#9ca3af" style={{ marginRight: 6 }} />
                    <Text style={styles.horarioText} numberOfLines={1}>
                      {formatarHorarioIntervalo(turma.horario_inicio, turma.horario_fim)}
                    </Text>
                  </View>
                </View>
                <Text style={styles.professorCell} numberOfLines={1}>
                  {turma.professor_nome || '-'}
                </Text>
                <View style={styles.vagasCell}>
                  <Text style={styles.vagasText}>
                    {turma.alunos_count || 0}/{turma.limite_alunos}
                  </Text>
                </View>
                <View style={styles.statusCell}>
                  <View
                    style={[
                      styles.statusBadge,
                      turma.ativo ? styles.statusAtivo : styles.statusInativo,
                    ]}
                  >
                    <Text style={styles.statusText}>
                      {turma.ativo ? 'Ativo' : 'Inativo'}
                    </Text>
                  </View>
                </View>
                <View style={styles.acoesCell}>
                  <TouchableOpacity
                    onPress={() => handleEditar(turma.id)}
                    style={styles.actionIconButton}
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
          <View style={styles.calendarOverlay}>
            <View style={styles.calendarContent}>
              <View style={styles.calendarHeader}>
                <TouchableOpacity onPress={() => irParaData(-30)}>
                  <Feather name="chevron-left" size={24} color="#f97316" />
                </TouchableOpacity>
                <Text style={styles.calendarTitle}>
                  {new Date(dataSelecionada).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}
                </Text>
                <TouchableOpacity onPress={() => irParaData(30)}>
                  <Feather name="chevron-right" size={24} color="#f97316" />
                </TouchableOpacity>
              </View>

              <View style={styles.calendarDaysOfWeek}>
                {['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'].map((dia) => (
                  <Text key={dia} style={styles.calendarDayOfWeekText}>{dia}</Text>
                ))}
              </View>

              <View style={styles.calendarGrid}>
                {gerarDiasDoMes(new Date(dataSelecionada)).map((dia, index) => (
                  <TouchableOpacity
                    key={index}
                    style={[
                      styles.calendarDay,
                      !dia && styles.calendarDayEmpty,
                      dia && dia.toISOString().split('T')[0] === dataSelecionada && styles.calendarDaySelected,
                    ]}
                    onPress={() => handleSelecionarDataCalendar(dia)}
                    disabled={!dia}
                  >
                    {dia && <Text style={[styles.calendarDayText, dia.toISOString().split('T')[0] === dataSelecionada && styles.calendarDayTextSelected]}>{dia.getDate()}</Text>}
                  </TouchableOpacity>
                ))}
              </View>

              <TouchableOpacity
                style={styles.calendarCloseButton}
                onPress={() => setCalendarVisible(false)}
              >
                <Text style={styles.calendarCloseButtonText}>Fechar</Text>
              </TouchableOpacity>
            </View>
          </View>
        </Modal>

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
            style={styles.criarTurmaOverlay} 
            onPress={() => {
              setModalCriarVisible(false);
              setIsEditando(false);
              setTurmaEditandoId(null);
            }}
          >
            <Pressable 
              style={styles.criarTurmaContent}
              onPress={(e) => e.stopPropagation()}
            >
              <View style={styles.criarTurmaHeader}>
                <Text style={styles.criarTurmaTitle}>{isEditando ? 'Editar Aula' : 'Nova Aula'}</Text>
                <TouchableOpacity onPress={() => {
                  setModalCriarVisible(false);
                  setIsEditando(false);
                  setTurmaEditandoId(null);
                }}>
                  <Feather name="x" size={28} color="#6b7280" />
                </TouchableOpacity>
              </View>

              <ScrollView style={styles.criarTurmaForm} showsVerticalScrollIndicator={false}>
                {loadingDropdowns ? (
                  <View style={{ alignItems: 'center', paddingVertical: 40 }}>
                    <ActivityIndicator size="large" color="#f97316" />
                    <Text style={{ marginTop: 16, color: '#6b7280', fontSize: 14 }}>Carregando opções...</Text>
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

        {/* Loading Overlay */}
        {loading && (
          <View style={styles.loadingOverlay}>
            <View style={styles.loadingBox}>
              <ActivityIndicator size="large" color="#f97316" />
              <Text style={styles.loadingText}>Carregando aulas...</Text>
            </View>
          </View>
        )}
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
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

  // Banner Header
  bannerContainer: {
    backgroundColor: '#f8fafc',
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
    color: '#1f2937',
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
    color: '#1f2937',
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

  // Desktop Table
  tableContainer: {
    margin: 20,
    backgroundColor: '#fff',
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
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
  },
  modalidadeText: {
    fontSize: 13,
    fontWeight: '500',
    color: '#1f2937',
  },
  horarioCell: {
    flex: 1.2,
    fontSize: 13,
    color: '#4b5563',
  },
  horarioContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  horarioText: {
    fontSize: 13,
    color: '#4b5563',
  },
  professorCell: {
    flex: 1.5,
    fontSize: 13,
    color: '#4b5563',
  },
  vagasCell: {
    flex: 1,
    alignItems: 'flex-start',
  },
  vagasText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#1f2937',
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
  dateSelector: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 10,
    backgroundColor: '#f97316',
    borderBottomWidth: 0,
  },
  dateSelectorButton: {
    padding: 8,
    borderRadius: 8,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    justifyContent: 'center',
    alignItems: 'center',
    minWidth: 44,
    minHeight: 44,
  },
  hojeButton: {
    backgroundColor: 'rgba(255, 255, 255, 0.35)',
  },
  dateSelectorContent: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 4,
  },
  dateDisplay: {
    fontSize: 15,
    fontWeight: '600',
    color: '#fff',
  },
  acoesCell: {
    flex: 0.6,
    alignItems: 'center',
  },
  actionIconButton: {
    padding: 6,
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
    color: '#1f2937',
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
    color: '#1f2937',
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
    color: '#1f2937',
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
    color: '#1f2937',
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
    color: '#1f2937',
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
    color: '#374151',
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
    color: '#1f2937',
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
    color: '#374151',
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
});
