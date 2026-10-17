import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  ActivityIndicator,
  Pressable,
  Platform,
  useWindowDimensions,
  Modal,
  ToastAndroid,
  TextInput,
  TouchableOpacity,
} from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import LayoutBase from '../../components/LayoutBase';
import BaixaPagamentoPlanoModal from '../../components/BaixaPagamentoPlanoModal';
import { matriculaService } from '../../services/matriculaService';
import mercadoPagoService from '../../services/mercadoPagoService';
import { pagamentoPlanoService } from '../../services/pagamentoPlanoService';
import planoService from '../../services/planoService';
import { creditoService } from '../../services/creditoService';
import { formatarDataParaInput, calcularDiasRestantes } from '../../utils/formatadores';
import { mascaraData } from '../../utils/masks';
import { obterMensagemErro } from '../../utils/errorHandler';
import { showError } from '../../utils/toast';
import { authService } from '../../services/authService';
import descontoMatriculaService from '../../services/descontoMatriculaService';
import {
  Matricula,
  DescontoModal,
  Button as CkButton,
  Callout as CkCallout,
  Card as CkCard,
  Badge as CkBadge,
  Icon as CkIcon,
} from '../../components/ck';

export default function MatriculaDetalheScreen() {
  const { id } = useLocalSearchParams();
  const { width } = useWindowDimensions();
  const isDesktop = width >= 768;

  const [loading, setLoading] = useState(true);
  const [matricula, setMatricula] = useState(null);
  const [outrasMatriculas, setOutrasMatriculas] = useState([]);
  const [pagamentos, setPagamentos] = useState([]);
  const [mostrarCancelados, setMostrarCancelados] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [modalConfirmVisible, setModalConfirmVisible] = useState(false);
  const [pagamentoSelecionado, setPagamentoSelecionado] = useState(null);
  const [ajustePlano, setAjustePlano] = useState(null);
  const [modalEditarVencimento, setModalEditarVencimento] = useState(false);
  const [novaDataVencimento, setNovaDataVencimento] = useState('');
  const [salvandoData, setSalvandoData] = useState(false);
  const [reprocessandoPagamentoId, setReprocessandoPagamentoId] = useState(null);
  const [modalEditarPagamentoVisible, setModalEditarPagamentoVisible] = useState(false);
  const [modalExcluirPagamentoVisible, setModalExcluirPagamentoVisible] = useState(false);
  const [salvandoPagamento, setSalvandoPagamento] = useState(false);
  const [excluindoPagamento, setExcluindoPagamento] = useState(false);
  const [modalAlterarPlanoVisible, setModalAlterarPlanoVisible] = useState(false);
  const [loadingPlanosAlteracao, setLoadingPlanosAlteracao] = useState(false);
  const [loadingCiclosAlteracao, setLoadingCiclosAlteracao] = useState(false);
  const [salvandoAlteracaoPlano, setSalvandoAlteracaoPlano] = useState(false);
  const [planosAlteracao, setPlanosAlteracao] = useState([]);
  const [ciclosAlteracao, setCiclosAlteracao] = useState([]);
  const [pagamentoEditando, setPagamentoEditando] = useState(null);
  const [pagamentoExcluindo, setPagamentoExcluindo] = useState(null);
  const [formAlterarPlano, setFormAlterarPlano] = useState({
    plano_id: '',
    plano_ciclo_id: '',
    data_inicio: formatarDataParaInput(new Date()),
    dia_vencimento: '',
    observacoes: '',
    tipo_credito: 'nenhum',
    usar_credito_existente: false,
    credito: '',
    motivo_credito: '',
  });
  const [saldoCreditosAluno, setSaldoCreditosAluno] = useState(null);
  const [loadingSaldoCreditos, setLoadingSaldoCreditos] = useState(false);
  const [etapaAlterarPlano, setEtapaAlterarPlano] = useState('cancelamento'); // 'cancelamento' | 'plano'
  const [simulacaoCancelamento, setSimulacaoCancelamento] = useState(null);
  const [loadingSimulacao, setLoadingSimulacao] = useState(false);
  const [gerarCreditoCancelamento, setGerarCreditoCancelamento] = useState(true);
  const [cancelandoMatricula, setCancelandoMatricula] = useState(false);
  const [formEditarPagamento, setFormEditarPagamento] = useState({
    valor: '',
    desconto: '',
    motivo_desconto: '',
    data_vencimento: '',
    data_pagamento: '',
    status_pagamento_id: '1',
    observacoes: '',
  });
  const [modalConfirmCancelamentoVisible, setModalConfirmCancelamentoVisible] = useState(false);
  const [modalConfirmAlteracaoVisible, setModalConfirmAlteracaoVisible] = useState(false);
  const [alterarPlanoErro, setAlterarPlanoErro] = useState('');
  const [modalBaixaPacoteVisible, setModalBaixaPacoteVisible] = useState(false);
  const [baixaPacoteLoading, setBaixaPacoteLoading] = useState(false);
  const [errorModal, setErrorModal] = useState({ visible: false, title: '', message: '' });
  const [currentUserId, setCurrentUserId] = useState(null);
  const [modalBloquearVisible, setModalBloquearVisible] = useState(false);
  const [motivoBloqueio, setMotivoBloqueio] = useState('');
  const [bloqueandoMatricula, setBloqueandoMatricula] = useState(false);
  const [desbloqueandoMatricula, setDesbloqueandoMatricula] = useState(false);

  // Descontos
  const [descontos, setDescontos] = useState([]);
  const [modalDescontoVisible, setModalDescontoVisible] = useState(false);
  const [descontoEditando, setDescontoEditando] = useState(null); // null = novo, obj = editar
  const [adminsLista, setAdminsLista] = useState([]);

  useEffect(() => {
    authService.getCurrentUser().then((user) => {
      if (user?.id) setCurrentUserId(Number(user.id));
    });
  }, []);

  useEffect(() => {
    carregarDados();
  }, [id]);

  useEffect(() => {
    if (!modalAlterarPlanoVisible) return;

    if (!formAlterarPlano.plano_id) {
      setCiclosAlteracao([]);
      setFormAlterarPlano((prev) => ({ ...prev, plano_ciclo_id: '' }));
      return;
    }

    const planoSelecionado = planosAlteracao.find(
      (plano) => plano.id === Number(formAlterarPlano.plano_id)
    );

    if (planoSelecionado?.ciclos?.length) {
      const ciclosOrdenados = [...planoSelecionado.ciclos].sort((a, b) => a.meses - b.meses);
      setCiclosAlteracao(ciclosOrdenados);
      setFormAlterarPlano((prev) => {
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

    carregarCiclosAlteracao(formAlterarPlano.plano_id);
  }, [formAlterarPlano.plano_id, modalAlterarPlanoVisible, planosAlteracao]);

  const carregarDados = async () => {
    try {
      setLoading(true);

      // Buscar dados da matrícula
      const responseMatricula = await matriculaService.buscar(id);
      const dadosMatricula = responseMatricula?.matricula || responseMatricula;
      
      if (!dadosMatricula) {
        throw new Error('Dados da matrícula inválidos ou vazios');
      }
      
      const pagamentosDaMatricula = responseMatricula?.pagamentos || dadosMatricula?.pagamentos || [];
      console.log('📌 Pagamentos da matrícula:', pagamentosDaMatricula);
      setMatricula(dadosMatricula);
      setOutrasMatriculas(responseMatricula?.outras_matriculas || []);
      setAjustePlano(responseMatricula?.ajuste_plano || null);

      // Buscar histórico de pagamentos (se existir endpoint)
      try {
        const responsePagamentos = await matriculaService.buscarPagamentos(id);
        const dadosPagamentos = responsePagamentos?.pagamentos || responsePagamentos?.data || [];
        setPagamentos(dadosPagamentos.length > 0 ? dadosPagamentos : pagamentosDaMatricula);
      } catch (error) {
        console.log('Endpoint de pagamentos não disponível ainda');
        setPagamentos(pagamentosDaMatricula);
      }

      // Buscar descontos da matrícula
      try {
        const responseDescontos = await descontoMatriculaService.listar(id);
        setDescontos(responseDescontos?.descontos || []);
      } catch (error) {
        console.log('Endpoint de descontos não disponível ainda');
        setDescontos([]);
      }

      // Buscar admins para o select de autorizado_por
      try {
        const responseAdmins = await descontoMatriculaService.listarAdmins();
        setAdminsLista(responseAdmins?.admins || []);
      } catch (error) {
        console.log('Endpoint de admins não disponível ainda');
      }
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
      const mensagem = obterMensagemErro(error, 'Não foi possível carregar os dados da matrícula');
      showAlert('Erro', mensagem);
    } finally {
      setLoading(false);
    }
  };

  const recarregarFaturas = async () => {
    try {
      await matriculaService.sincronizarAsaas(id);
    } catch (error) {
      console.log('Sincronização Asaas:', error);
    }
    await carregarDados();
  };

  const handleBaixaPagamento = (pagamento) => {
    if (pagamento?.pacote_contrato_id) {
      showAlert(
        'Baixa individual indisponível',
        'Esta parcela tem valor rateado do pacote. Use a baixa do pacote. Depois da vigência do desconto, a próxima parcela pode ser cobrada no valor individual.'
      );
      return;
    }
    setPagamentoSelecionado(pagamento);
    setModalVisible(true);
  };

  const handleBaixaSuccess = () => {
    setModalVisible(false);
    setPagamentoSelecionado(null);
    carregarDados();
  };

  const handleConfirmarBaixaPacote = async () => {
    if (!matricula?.pacote_contrato_id) return;
    try {
      setBaixaPacoteLoading(true);
      const response = await matriculaService.baixarPacoteContrato(matricula.pacote_contrato_id);
      showToast(response?.message || 'Baixa do pacote realizada com sucesso!');
      setModalBaixaPacoteVisible(false);
      await carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Não foi possível baixar o pacote');
      showAlert('Erro', mensagem);
    } finally {
      setBaixaPacoteLoading(false);
    }
  };

  const handleConfirmarBloqueio = async () => {
    if (!matricula?.id) return;
    try {
      setBloqueandoMatricula(true);
      await matriculaService.bloquear(matricula.id, motivoBloqueio.trim() || 'Bloqueado pelo administrador');
      setModalBloquearVisible(false);
      setMotivoBloqueio('');
      showToast('Matrícula bloqueada. O aluno não poderá fazer check-in nem usar o app.');
      await carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Não foi possível bloquear a matrícula');
      setErrorModal({ visible: true, title: 'Erro ao bloquear', message: mensagem });
    } finally {
      setBloqueandoMatricula(false);
    }
  };

  const handleDesbloquearMatricula = async () => {
    if (!matricula?.id) return;
    try {
      setDesbloqueandoMatricula(true);
      await matriculaService.desbloquear(matricula.id);
      showToast('Matrícula desbloqueada. O aluno pode voltar a usar o app e fazer check-in.');
      await carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Não foi possível desbloquear a matrícula');
      setErrorModal({ visible: true, title: 'Erro ao desbloquear', message: mensagem });
    } finally {
      setDesbloqueandoMatricula(false);
    }
  };

  const handleAbrirModalEditarVencimento = () => {
    if (!matricula) {
      showAlert('Erro', 'Matrícula não carregada');
      return;
    }
    
    const dataAtual = matricula.proxima_data_vencimento || matricula.data_vencimento;
    
    if (!dataAtual) {
      showAlert('Erro', 'Data de vencimento não disponível');
      return;
    }
    
    // Formatar data de YYYY-MM-DD para DD/MM/YYYY
    const [ano, mes, dia] = dataAtual.split('-');
    if (!dia || !mes || !ano) {
      showAlert('Erro', 'Formato de data inválido');
      return;
    }
    
    const dataFormatada = `${dia}/${mes}/${ano}`;
    setNovaDataVencimento(dataFormatada);
    setModalEditarVencimento(true);
  };

  const handleAtualizarVencimento = async () => {
    if (!novaDataVencimento) {
      showAlert('Erro', 'Por favor, selecione uma data');
      return;
    }

    try {
      setSalvandoData(true);
      
      // Converter de DD/MM/YYYY para YYYY-MM-DD
      const numeros = novaDataVencimento.replace(/\D/g, '');
      if (numeros.length !== 8) {
        showAlert('Erro', 'Data inválida. Use o formato DD/MM/YYYY');
        return;
      }
      
      const dia = numeros.substring(0, 2);
      const mes = numeros.substring(2, 4);
      const ano = numeros.substring(4, 8);
      const dataFormatada = `${ano}-${mes}-${dia}`;
      
      const response = await matriculaService.atualizarProximaDataVencimento(id, dataFormatada);
      
      showToast(response.message || 'Data de vencimento atualizada com sucesso!');
      setModalEditarVencimento(false);
      await carregarDados();
      
    } catch (error) {
      const errorMsg = obterMensagemErro(error, 'Erro ao atualizar data');
      showAlert('Erro', errorMsg);
    } finally {
      setSalvandoData(false);
    }
  };

  const getCicloLabel = (ciclo) => {
    if (!ciclo) return '-';
    const meses = Number(ciclo.meses || 0);
    const frequencia = ciclo.frequencia_nome || ciclo.nome || 'Ciclo';
    return `${frequencia}${meses ? ` • ${meses} ${meses === 1 ? 'mês' : 'meses'}` : ''}`;
  };

  const carregarPlanosAlteracao = async () => {
    try {
      setLoadingPlanosAlteracao(true);
      const response = await planoService.listar(true);
      const todosPlanos = Array.isArray(response)
        ? response
        : Array.isArray(response?.planos)
        ? response.planos
        : [];

      const planosAtivos = todosPlanos.filter((plano) => plano.ativo && plano.atual);
      const planosDaModalidade = matricula?.modalidade_id
        ? planosAtivos.filter((plano) => plano.modalidade_id === Number(matricula.modalidade_id))
        : [];

      setPlanosAlteracao(planosDaModalidade.length > 0 ? planosDaModalidade : planosAtivos);
    } catch (error) {
      setPlanosAlteracao([]);
      const mensagem = obterMensagemErro(error, 'Não foi possível carregar os planos disponíveis');
      showAlert('Erro', mensagem);
    } finally {
      setLoadingPlanosAlteracao(false);
    }
  };

  const carregarCiclosAlteracao = async (planoId) => {
    if (!planoId) {
      setCiclosAlteracao([]);
      return;
    }

    try {
      setLoadingCiclosAlteracao(true);
      setCiclosAlteracao([]);
      const response = await planoService.listarCiclos(planoId);
      const lista = Array.isArray(response)
        ? response
        : response?.ciclos || response?.data?.ciclos || [];
      const ciclosOrdenados = lista.slice().sort((a, b) => a.meses - b.meses);
      setCiclosAlteracao(ciclosOrdenados);
      setFormAlterarPlano((prev) => ({
        ...prev,
        plano_ciclo_id: ciclosOrdenados.some((ciclo) => ciclo.id.toString() === prev.plano_ciclo_id)
          ? prev.plano_ciclo_id
          : ciclosOrdenados.length === 1
          ? ciclosOrdenados[0].id.toString()
          : '',
      }));
    } catch (error) {
      setCiclosAlteracao([]);
      const mensagem = obterMensagemErro(error, 'Não foi possível carregar os ciclos do plano');
      showAlert('Erro', mensagem);
    } finally {
      setLoadingCiclosAlteracao(false);
    }
  };

  const abrirFluxoRenovacaoMatricula = async (planoAtualId, planoCicloAtualId, diaVencimentoAtual) => {
    setEtapaAlterarPlano('plano');
    setSimulacaoCancelamento(null);
    setModalAlterarPlanoVisible(true);
    setAlterarPlanoErro('');

    setFormAlterarPlano({
      plano_id: planoAtualId,
      plano_ciclo_id: planoCicloAtualId,
      data_inicio: formatarDataParaInput(new Date()),
      dia_vencimento: diaVencimentoAtual,
      observacoes: '',
      tipo_credito: 'nenhum',
      usar_credito_existente: false,
      credito: '',
      motivo_credito: '',
    });
    setCiclosAlteracao([]);
    await carregarPlanosAlteracao();
    if (planoAtualId) {
      await carregarCiclosAlteracao(Number(planoAtualId));
    }

    if (matricula?.aluno_id) {
      try {
        setLoadingSaldoCreditos(true);
        const saldoResp = await creditoService.consultarSaldo(matricula.aluno_id);
        setSaldoCreditosAluno(saldoResp);
        if (saldoResp?.saldo_total > 0) {
          setFormAlterarPlano((prev) => ({ ...prev, usar_credito_existente: true }));
        }
      } catch (err) {
        console.log('Saldo de créditos não disponível:', err);
      } finally {
        setLoadingSaldoCreditos(false);
      }
    }
  };

  const handleAbrirAlterarPlano = async () => {
    if (!matricula) {
      showAlert('Erro', 'Matrícula não carregada');
      return;
    }

    const dataVencimentoAtual = matricula.proxima_data_vencimento || matricula.data_vencimento;
    const planoAtualId = matricula?.plano_id ? String(matricula.plano_id) : '';
    const planoCicloAtualId = matricula?.plano_ciclo_id
      ? String(matricula.plano_ciclo_id)
      : cicloInfo?.ciclo?.id
      ? String(cicloInfo.ciclo.id)
      : '';
    const diaVencimentoAtual = dataVencimentoAtual
      ? String(Number(dataVencimentoAtual.split('-')[2] || 0) || '')
      : String(new Date().getDate());

    // Cancelada ou vencida: rematrícula com o mesmo plano permitida
    if ([2, 3].includes(Number(matricula?.status_id))) {
      await abrirFluxoRenovacaoMatricula(planoAtualId, planoCicloAtualId, diaVencimentoAtual);
      return;
    }

    // Etapa 1: Simular cancelamento
    setEtapaAlterarPlano('cancelamento');
    setSimulacaoCancelamento(null);
    setGerarCreditoCancelamento(true);
    setCancelandoMatricula(false);
    setModalAlterarPlanoVisible(true);
    setAlterarPlanoErro('');

    try {
      setLoadingSimulacao(true);
      const simulacao = await matriculaService.simularCancelamento(id);
      setSimulacaoCancelamento(simulacao);
    } catch (err) {
      console.error('Erro ao simular cancelamento:', err);
      const msg = obterMensagemErro(err, 'Não foi possível simular o cancelamento');
      showAlert('Erro', msg);
      setModalAlterarPlanoVisible(false);
    } finally {
      setLoadingSimulacao(false);
    }
  };

  const handleConfirmarCancelamentoEAvancar = async () => {
    try {
      setCancelandoMatricula(true);
      const resultado = await matriculaService.cancelarComCredito(id, {
        gerar_credito: gerarCreditoCancelamento,
        motivo_cancelamento: 'Cancelado para alteração de plano',
      });

      const creditoMsg = resultado?.credito_gerado
        ? ` Crédito de ${formatCurrency(resultado.credito_gerado.valor)} gerado.`
        : '';
      showToast(`Matrícula cancelada.${creditoMsg} Selecione o novo plano.`);

      // Carregar dados atualizados e avançar para etapa 2
      await carregarDados();
      setEtapaAlterarPlano('plano');

      // Preparar formulário do plano
      const dataVencimentoAtual = matricula.proxima_data_vencimento || matricula.data_vencimento;
      const diaVencimentoAtual = dataVencimentoAtual
        ? String(Number(dataVencimentoAtual.split('-')[2] || 0) || '')
        : String(new Date().getDate());

      setFormAlterarPlano({
        plano_id: '',
        plano_ciclo_id: '',
        data_inicio: formatarDataParaInput(new Date()),
        dia_vencimento: diaVencimentoAtual,
        observacoes: '',
        tipo_credito: 'nenhum',
        usar_credito_existente: resultado?.saldo_creditos_total > 0,
        credito: '',
        motivo_credito: '',
      });
      setCiclosAlteracao([]);
      setSaldoCreditosAluno(
        resultado?.saldo_creditos_total != null
          ? { saldo_total: resultado.saldo_creditos_total }
          : null
      );
      await carregarPlanosAlteracao();

      // Buscar saldo de créditos atualizado
      if (matricula?.aluno_id) {
        try {
          setLoadingSaldoCreditos(true);
          const saldoResp = await creditoService.consultarSaldo(matricula.aluno_id);
          setSaldoCreditosAluno(saldoResp);
        } catch (err) {
          console.log('Saldo de créditos não disponível:', err);
        } finally {
          setLoadingSaldoCreditos(false);
        }
      }
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Não foi possível cancelar a matrícula');
      showAlert('Erro', mensagem);
    } finally {
      setCancelandoMatricula(false);
    }
  };

  const handleConfirmarAlteracaoPlano = async () => {
    if (!formAlterarPlano.plano_id) {
      showAlert('Erro', 'Selecione um plano');
      return;
    }

    const planoRequerCiclo = ciclosAlteracao.length > 0;

    if (planoRequerCiclo && !formAlterarPlano.plano_ciclo_id) {
      showAlert('Erro', 'Selecione um ciclo de pagamento');
      return;
    }

    if (!formAlterarPlano.data_inicio || !/^\d{4}-\d{2}-\d{2}$/.test(formAlterarPlano.data_inicio)) {
      showAlert('Erro', 'Informe uma data de início válida no formato YYYY-MM-DD');
      return;
    }

    const diaVencimentoInformado = Number(formAlterarPlano.dia_vencimento);
    const diaVencimentoDataInicio = Number((formAlterarPlano.data_inicio || '').split('-')[2] || 0);
    const diaVencimento =
      Number.isInteger(diaVencimentoInformado) && diaVencimentoInformado >= 1 && diaVencimentoInformado <= 31
        ? diaVencimentoInformado
        : diaVencimentoDataInicio;

    if (!Number.isInteger(diaVencimento) || diaVencimento < 1 || diaVencimento > 31) {
      showAlert('Erro', 'Não foi possível determinar o dia de vencimento pela data de início');
      return;
    }

    try {
      setSalvandoAlteracaoPlano(true);
      setAlterarPlanoErro('');
      const payload = {
        plano_id: Number(formAlterarPlano.plano_id),
        data_inicio: formAlterarPlano.data_inicio,
        dia_vencimento: diaVencimento,
        observacoes: formAlterarPlano.observacoes?.trim() || null,
      };

      if (formAlterarPlano.plano_ciclo_id) {
        payload.plano_ciclo_id = Number(formAlterarPlano.plano_ciclo_id);
      }

      if (formAlterarPlano.tipo_credito === 'abater_plano') {
        payload.abater_plano_anterior = true;
      } else if (formAlterarPlano.tipo_credito === 'abater') {
        payload.abater_pagamento_anterior = true;
      } else if (formAlterarPlano.tipo_credito === 'manual') {
        const creditoVal = parseFloat(formAlterarPlano.credito);
        if (creditoVal > 0) {
          payload.credito = creditoVal;
          if (formAlterarPlano.motivo_credito?.trim()) {
            payload.motivo_credito = formAlterarPlano.motivo_credito.trim();
          }
        }
      }

      if (formAlterarPlano.usar_credito_existente) {
        payload.usar_credito_existente = true;
      }

      const response = await matriculaService.alterarPlano(id, payload);

      let msg = response?.message || 'Plano alterado com sucesso';
      if (
        response?.plano_anterior &&
        response?.plano_novo &&
        response.plano_anterior === response.plano_novo
      ) {
        msg += ' (renovação do mesmo plano — confira datas e pagamento pendente)';
      }
      if (response?.credito && (response.credito.total_aplicado > 0 || response.credito.valor_aplicado > 0)) {
        const totalAplicado = response.credito.total_aplicado || response.credito.valor_aplicado || 0;
        msg += ` — Crédito de ${formatCurrency(totalAplicado)} aplicado. Parcela: ${formatCurrency(response.valor_parcela)}`;
        const saldoRestante = response.credito.saldo_creditos_restante ?? response.credito.saldo_restante ?? 0;
        if (saldoRestante > 0) {
          msg += ` (saldo restante: ${formatCurrency(saldoRestante)})`;
        }
      }
      showToast(msg);
      setModalAlterarPlanoVisible(false);
      await carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Não foi possível alterar o plano da matrícula');
      setAlterarPlanoErro(mensagem);
      showError(mensagem);
      showAlert('Erro', mensagem);
    } finally {
      setSalvandoAlteracaoPlano(false);
    }
  };

  const showAlert = (title, message) => {
    setErrorModal({ visible: true, title, message });
  };

  const showToast = (message) => {
    if (Platform.OS === 'android') {
      ToastAndroid.show(message, ToastAndroid.SHORT);
    } else if (Platform.OS === 'web') {
      // Toast customizado para web
      const toast = document.createElement('div');
      toast.textContent = message;
      toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #10b981;
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
    }
  };

  // Status de Matrícula: 1=Ativa, 2=Vencida, 3=Cancelada, 4=Finalizada, 5=Pendente, 6=Bloqueado, 7=Concluída
  const getStatusColor = (statusId) => {
    const id = Number(statusId);
    switch (id) {
      case 1:
        return '#10b981'; // Ativa (verde)
      case 2:
        return '#f97316'; // Vencida (laranja)
      case 3:
        return '#ef4444'; // Cancelada (vermelho)
      case 4:
        return '#6b7280'; // Finalizada (cinza)
      case 5:
        return '#f59e0b'; // Pendente (amarelo)
      case 6:
        return '#8b5cf6'; // Bloqueado (roxo)
      case 7:
        return '#22c55e'; // Concluída (verde)
      default:
        return '#6b7280';
    }
  };

  const getStatusLabel = (statusId) => {
    const id = Number(statusId);
    if (id === 5 && matricula?.motivo_status === 'limite_checkins') {
      return 'Limite esgotado';
    }
    switch (id) {
      case 1:
        return 'Ativa';
      case 2:
        return 'Vencida';
      case 3:
        return 'Cancelada';
      case 4:
        return 'Finalizada';
      case 5:
        return 'Pendente';
      case 6:
        return 'Bloqueado';
      case 7:
        return 'Concluída';
      default:
        return 'Desconhecido';
    }
  };

  const getPagamentoStatusColor = (statusId) => {
    const normalizedStatusId = Number(statusId);
    // Status: 1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado
    switch (normalizedStatusId) {
      case 1:
        return '#3B82F6'; // Aguardando (azul)
      case 2:
        return '#10b981'; // Pago (verde)
      case 3:
        return '#dc2626'; // Atrasado (vermelho escuro)
      case 4:
        return '#6B7280'; // Cancelado (cinza)
      default:
        return '#6b7280';
    }
  };

  const getPagamentoStatusLabel = (statusId) => {
    const normalizedStatusId = Number(statusId);
    // Status: 1=Aguardando, 2=Pago, 3=Atrasado, 4=Cancelado
    switch (normalizedStatusId) {
      case 1:
        return 'Aguardando';
      case 2:
        return 'Pago';
      case 3:
        return 'Atrasado';
      case 4:
        return 'Cancelado';
      default:
        return 'Desconhecido';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Evita problema de timezone criando a data diretamente com os valores
    const [year, month, day] = dateString.split('-');
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('pt-BR');
  };

  const isProximoVencer = (dateString) => {
    if (!dateString) return false;
    const [year, month, day] = dateString.split('-');
    const vencimento = new Date(year, month - 1, day);
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);
    vencimento.setHours(0, 0, 0, 0);
    const diffDias = Math.ceil((vencimento.getTime() - hoje.getTime()) / (1000 * 60 * 60 * 24));
    return diffDias >= 0 && diffDias <= 3;
  };

  const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return '-';
    try {
      // Trata tanto datas simples quanto timestamps completos
      const date = new Date(dateTimeString);
      if (isNaN(date.getTime())) return '-';
      return date.toLocaleDateString('pt-BR');
    } catch (error) {
      return '-';
    }
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value || 0);
  };

  const getCicloInfo = () => {
    const ciclo = matricula?.plano_ciclo;
    if (!ciclo) return null;
    const frequencia = ciclo.frequencia?.nome || ciclo.frequencia_nome || ciclo.frequencia?.codigo || 'Ciclo';
    const meses = Number(ciclo.meses || ciclo.frequencia_meses || 0);
    return {
      ciclo,
      frequencia,
      meses,
    };
  };

  const isVencido = (dataVencimento) => {
    if (!dataVencimento) return false;
    const [year, month, day] = dataVencimento.split('-');
    const vencimento = new Date(year, month - 1, day);
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);
    vencimento.setHours(0, 0, 0, 0);
    return vencimento < hoje;
  };

  const isVencimentoAtingido = (dataVencimento) => {
    if (!dataVencimento) return false;
    const [year, month, day] = dataVencimento.split('-');
    const vencimento = new Date(year, month - 1, day);
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);
    vencimento.setHours(0, 0, 0, 0);
    return vencimento <= hoje;
  };

  const calcularResumo = () => {
    const getStatusId = (pagamento) => Number(pagamento.status_pagamento_id);
    // Excluir pagamentos cancelados (status 4) do total
    const pagamentosAtivos = pagamentos.filter((p) => getStatusId(p) !== 4);

    const total = pagamentosAtivos.reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);

    // Pago = status 2
    const pago = pagamentos
      .filter((p) => getStatusId(p) === 2)
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);

    // Atrasado = status 3 OU aguardando vencido
    const atrasado = pagamentos
      .filter((p) => {
        const statusId = getStatusId(p);
        if (statusId === 3) return true;
        return statusId === 1 && isVencido(p.data_vencimento);
      })
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);

    // Aguardando = status 1 e ainda não venceu
    const aguardando = pagamentos
      .filter((p) => getStatusId(p) === 1 && !isVencido(p.data_vencimento))
      .reduce((sum, p) => sum + parseFloat(p.valor || 0), 0);

    return { total, pago, atrasado, aguardando };
  };

  if (loading) {
    return (
      <LayoutBase title="Detalhes da Matrícula">
        <View className="flex-1 items-center justify-center px-10">
          <ActivityIndicator size="large" color="#f97316" />
          <Text className="mt-4 text-sm text-slate-500">Carregando dados da matrícula...</Text>
        </View>
      </LayoutBase>
    );
  }

  if (!matricula) {
    return (
      <LayoutBase title="Detalhes da Matrícula">
        <View className="flex-1 items-center justify-center px-10">
          <Feather name="alert-circle" size={48} color="#ef4444" />
          <Text className="mt-4 text-base font-semibold text-rose-500">Matrícula não encontrada</Text>
          <Pressable
            className="mt-6 rounded-lg border border-slate-200 bg-white px-4 py-2"
            onPress={() => router.push('/matriculas')}
          >
            <Text className="text-sm font-semibold text-slate-600">Voltar</Text>
          </Pressable>
        </View>
      </LayoutBase>
    );
  }

  const pagamentosPendentes = pagamentos.filter((p) => {
    const statusId = Number(p.status_pagamento_id);
    if (statusId === 3) return true;
    return statusId === 1 && isVencido(p.data_vencimento);
  });
  const isPacote = Boolean(matricula?.pacote_contrato_id);
  const contratoPacotePendente =
    String(matricula?.pacote?.contrato_status || '').toLowerCase() === 'pendente';
  const temParcelaPacoteVencida = pagamentos.some((p) => {
    const statusId = Number(p.status_pagamento_id);
    if (statusId === 4 || p.data_pagamento) return false;
    if (statusId === 3) return true;
    return statusId === 1 && isVencido(p.data_vencimento);
  });
  const podeDarBaixaPacote = isPacote && (contratoPacotePendente || temParcelaPacoteVencida);
  const qtdPessoasPacote = Array.isArray(matricula?.pacote?.beneficiarios)
    ? matricula.pacote.beneficiarios.length
    : (matricula?.pacote?.pacote_qtd_beneficiarios ?? 0);
  const isMatriculaCancelada = Number(matricula?.status_id) === 3;
  const isMatriculaVencida = Number(matricula?.status_id) === 2;
  const isMatriculaRenovavel = isMatriculaCancelada || isMatriculaVencida;
  const isMatriculaBloqueada = Number(matricula?.status_id) === 6;
  const podeBloquearMatricula = matricula && [1, 2, 5].includes(Number(matricula.status_id));
  const podeDesbloquearMatricula = isMatriculaBloqueada;
  const podeExibirAlterarPlano = (!isPacote || isMatriculaRenovavel) && !isMatriculaBloqueada;
  const isPagamentoPendente = (pagamento) => {
    const statusId = Number(pagamento.status_pagamento_id);
    if (statusId === 3) return true;
    return statusId === 1 && isVencido(pagamento.data_vencimento);
  };
  const isPagamentoBaixavel = (pagamento) => {
    if (pagamento?.pacote_contrato_id) return false;
    const statusId = Number(pagamento?.status_pagamento_id);
    return statusId !== 4 && !pagamento?.data_pagamento;
  };
  const hasMercadoPagoIds =
    Array.isArray(matricula?.mercadopago_payment_ids) &&
    matricula.mercadopago_payment_ids.length > 0 &&
    Boolean(matricula?.mercadopago_last_payment_id);
  const isPagamentoNaoPago = (pagamento) => Number(pagamento?.status_pagamento_id) !== 2;

  const handleReprocessarPagamentoMP = async () => {
    const paymentId =
      matricula?.mercadopago_last_payment_id ||
      (Array.isArray(matricula?.mercadopago_payment_ids)
        ? matricula.mercadopago_payment_ids[0]
        : null);

    if (!paymentId) {
      showAlert('Atenção', 'Payment ID do Mercado Pago não encontrado para reprocessamento.');
      return;
    }

    try {
      setReprocessandoPagamentoId(paymentId);
      const response = await mercadoPagoService.reprocessarPagamento(String(paymentId));
      showToast(response?.message || 'Reprocessamento iniciado com sucesso.');
      await carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Não foi possível reprocessar o pagamento no Mercado Pago');
      showAlert('Erro', mensagem);
    } finally {
      setReprocessandoPagamentoId(null);
    }
  };

  const getPagamentoId = (pagamento) => pagamento?.id || pagamento?.pagamento_id || pagamento?.conta_id;

  /**
   * Fallback local: numera pela ordem de vencimento (igual ao backend).
   * A lista exibida pode estar em outra ordem (ex.: ID decrescente) — isso não altera o número.
   */
  const buildNumeroParcelaMap = (listaPagamentos) => {
    const porVencimento = listaPagamentos
      .slice()
      .sort((a, b) => {
        const diff = new Date(a.data_vencimento || 0) - new Date(b.data_vencimento || 0);
        if (diff !== 0) return diff;
        return Number(getPagamentoId(a) || 0) - Number(getPagamentoId(b) || 0);
      });

    return porVencimento.reduce((acc, pagamento, idx) => {
      const id = getPagamentoId(pagamento);
      if (id != null && id !== '') {
        acc.set(String(id), idx + 1);
      }
      return acc;
    }, new Map());
  };

  const resolveNumeroParcela = (pagamento, numeroParcelaMap) => {
    if (pagamento.numero_parcela) return pagamento.numero_parcela;
    const id = getPagamentoId(pagamento);
    if (id == null || id === '') return null;
    return numeroParcelaMap.get(String(id)) ?? null;
  };

  const formatarMoedaInput = (valor) => {
    const numero = Number(valor || 0);
    if (!Number.isFinite(numero)) return '';
    return numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const aplicarMascaraDesconto = (texto) => {
    const digitos = String(texto || '').replace(/\D/g, '');
    if (!digitos) return '';
    const numero = Number(digitos) / 100;
    return numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const parseMoeda = (valor) => {
    const raw = String(valor || '').trim();
    if (!raw) return 0;

    let normalizado = raw;
    if (normalizado.includes(',')) {
      normalizado = normalizado.replace(/\./g, '').replace(',', '.');
    }

    normalizado = normalizado.replace(/[^\d.-]/g, '');
    return Number(normalizado);
  };

  // --- Descontos (DescontoModal do design system) ---
  // API: tipo 'recorrente' | 'primeira_mensalidade', valor OU percentual, autorizado_por (id do admin).
  const descontoParaDs = (desc) => ({
    id: desc.id,
    type: desc.tipo === 'primeira_mensalidade' ? 'primeira' : 'recorrente',
    mode: desc.percentual ? 'percentual' : 'fixo',
    value: Number(desc.percentual || desc.valor || 0),
    reason: desc.motivo || '',
    authorizedBy: desc.autorizado_por != null ? String(desc.autorizado_por) : '',
    authorizedByName: desc.autorizado_por_nome || '',
    start: desc.vigencia_inicio ? String(desc.vigencia_inicio).slice(0, 10) : '',
    end: desc.vigencia_fim ? String(desc.vigencia_fim).slice(0, 10) : null,
    remaining: desc.parcelas_restantes != null ? Number(desc.parcelas_restantes) : null,
    active: Number(desc.ativo) === 1,
  });

  const abrirNovoDesconto = () => {
    setDescontoEditando(null);
    setModalDescontoVisible(true);
  };

  const abrirEditarDesconto = (desc) => {
    setDescontoEditando(desc);
    setModalDescontoVisible(true);
  };

  // O DescontoModal já valida motivo, valor, percentual e datas; aqui só monta o payload da API.
  const handleSalvarDesconto = async (data) => {
    const payload = {
      tipo: data.type === 'primeira' ? 'primeira_mensalidade' : 'recorrente',
      motivo: data.reason,
    };
    if (data.mode === 'percentual') payload.percentual = data.value;
    else payload.valor = data.value;
    if (data.start) payload.vigencia_inicio = data.start;
    if (data.end) payload.vigencia_fim = data.end;
    if (data.type === 'recorrente' && data.remaining > 0) payload.parcelas_restantes = data.remaining;
    if (data.authorizedBy) payload.autorizado_por = Number(data.authorizedBy);

    try {
      if (descontoEditando) {
        await descontoMatriculaService.atualizar(descontoEditando.id, payload);
        showToast('Desconto atualizado com sucesso!');
      } else {
        await descontoMatriculaService.criar(id, payload);
        showToast('Desconto criado com sucesso!');
      }
      setModalDescontoVisible(false);
      carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Erro ao salvar desconto');
      showAlert('Erro', mensagem);
    }
  };

  const handleDesativarDesconto = async (descontoId) => {
    try {
      await descontoMatriculaService.desativar(descontoId);
      showToast('Desconto desativado.');
      carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Erro ao desativar desconto');
      showAlert('Erro', mensagem);
    }
  };

  const handleAbrirEditarPagamento = (pagamento) => {
    setPagamentoEditando(pagamento);
    setFormEditarPagamento({
      valor: formatarMoedaInput(pagamento?.valor || 0),
      desconto: formatarMoedaInput(pagamento?.desconto || 0),
      motivo_desconto: pagamento?.motivo_desconto || '',
      data_vencimento: pagamento?.data_vencimento || '',
      data_pagamento: pagamento?.data_pagamento || '',
      status_pagamento_id: String(pagamento?.status_pagamento_id || 1),
      observacoes: pagamento?.observacoes || '',
    });
    setModalEditarPagamentoVisible(true);
  };

  const handleSalvarEdicaoPagamento = async () => {
    const pagamentoId = getPagamentoId(pagamentoEditando);
    if (!pagamentoId) {
      showAlert('Erro', 'Pagamento inválido para edição');
      return;
    }

    const valorNormalizado = parseMoeda(formEditarPagamento.valor);
    if (!Number.isFinite(valorNormalizado) || valorNormalizado <= 0) {
      showAlert('Erro', 'Informe um valor válido');
      return;
    }

    const descontoNormalizado = parseMoeda(formEditarPagamento.desconto);
    if (!Number.isFinite(descontoNormalizado) || descontoNormalizado < 0) {
      showAlert('Erro', 'Informe um desconto válido');
      return;
    }

    if (descontoNormalizado > valorNormalizado) {
      showAlert('Erro', 'Desconto não pode ser maior que o valor do pagamento');
      return;
    }

    if (!formEditarPagamento.data_vencimento) {
      showAlert('Erro', 'Data de vencimento é obrigatória');
      return;
    }

    try {
      setSalvandoPagamento(true);

      const payload = {
        valor: valorNormalizado,
        desconto: descontoNormalizado,
        motivo_desconto: formEditarPagamento.motivo_desconto || null,
        data_vencimento: formEditarPagamento.data_vencimento,
        data_pagamento: formEditarPagamento.data_pagamento || null,
        status_pagamento_id: Number(formEditarPagamento.status_pagamento_id || 1),
        observacoes: formEditarPagamento.observacoes || null,
      };

      const response = await pagamentoPlanoService.atualizar(pagamentoId, payload);
      showToast(response?.message || 'Pagamento atualizado com sucesso');
      setModalEditarPagamentoVisible(false);
      setPagamentoEditando(null);
      await carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Não foi possível atualizar o pagamento');
      showAlert('Erro', mensagem);
    } finally {
      setSalvandoPagamento(false);
    }
  };

  const handleAbrirExcluirPagamento = (pagamento) => {
    setPagamentoExcluindo(pagamento);
    setModalExcluirPagamentoVisible(true);
  };

  const handleConfirmarExcluirPagamento = async () => {
    const pagamentoId = getPagamentoId(pagamentoExcluindo);
    if (!pagamentoId) {
      showAlert('Erro', 'Pagamento inválido para exclusão');
      return;
    }

    try {
      setExcluindoPagamento(true);
      const response = await pagamentoPlanoService.excluir(pagamentoId);
      showToast(response?.message || 'Pagamento excluído com sucesso');
      setModalExcluirPagamentoVisible(false);
      setPagamentoExcluindo(null);
      await carregarDados();
    } catch (error) {
      const mensagem = obterMensagemErro(error, 'Não foi possível excluir o pagamento');
      showAlert('Erro', mensagem);
    } finally {
      setExcluindoPagamento(false);
    }
  };

  const resumo = calcularResumo();
  const cicloInfo = getCicloInfo();
  const ultimoPagamentoPago = [...pagamentos]
    .filter((p) => Number(p.status_pagamento_id) === 2 && p.data_vencimento)
    .sort((a, b) => String(b.data_vencimento).localeCompare(String(a.data_vencimento)))[0];
  // Vigência = fim do período pago (data_vencimento), não o vencimento da última fatura.
  const acessoAte =
    matricula.data_vencimento ||
    matricula.proxima_data_vencimento ||
    ultimoPagamentoPago?.data_vencimento;
  const proximaParcelaAberta = [...pagamentos]
    .filter((p) => [1, 3].includes(Number(p.status_pagamento_id)) && !p.data_pagamento && p.data_vencimento)
    .sort((a, b) => String(a.data_vencimento).localeCompare(String(b.data_vencimento)))[0];
  const proximoVencimentoExibicao =
    proximaParcelaAberta?.data_vencimento ||
    matricula.proxima_data_vencimento ||
    acessoAte;
  const duracaoLabel = cicloInfo?.meses
    ? `${cicloInfo.meses} ${cicloInfo.meses === 1 ? 'mês' : 'meses'}`
    : matricula.data_inicio && acessoAte
      ? `${Math.max(
          1,
          Math.round(
            (new Date(`${acessoAte}T00:00:00`).getTime() -
              new Date(`${matricula.data_inicio}T00:00:00`).getTime()) /
              (1000 * 60 * 60 * 24)
          )
        )} dias`
      : `${matricula.duracao_dias} dias`;
  // ---- Dados no formato do design system (Matricula / PacoteMatricula / DescontoModal) ----
  const isoDia = (v) => (v ? String(v).slice(0, 10) : null);
  const agora = new Date();
  const hojeISO = `${agora.getFullYear()}-${String(agora.getMonth() + 1).padStart(2, '0')}-${String(agora.getDate()).padStart(2, '0')}`;

  // Status: 1=Ativa, 2=Vencida, 3=Cancelada, 4=Finalizada, 5=Pendente, 6=Bloqueado, 7=Concluída
  const STATUS_DS = {
    1: ['ativa', 'success', 'Ativa'],
    2: ['pendente', 'danger', 'Vencida'],
    3: ['cancelada', 'neutral', 'Cancelada'],
    4: ['cancelada', 'neutral', 'Finalizada'],
    5: ['pendente', 'warning', 'Pagamento pendente'],
    6: ['bloqueada', 'danger', 'Bloqueada'],
    7: ['ativa', 'success', 'Concluída'],
  };
  const statusDs = STATUS_DS[Number(matricula.status_id)] || ['ativa', 'neutral', getStatusLabel(matricula.status_id)];
  const limiteEsgotado = Number(matricula.status_id) === 5 && matricula.motivo_status === 'limite_checkins';
  const mesesCiclo = cicloInfo?.meses || 0;
  const iconeModalidade = matricula.modalidade_icone || matricula.modalidade?.icone || matricula.plano?.modalidade?.icone || 'dumbbell';
  const corModalidade = matricula.modalidade_cor || matricula.modalidade?.cor || matricula.plano?.modalidade?.cor;

  const enrollmentDs = {
    id: `#${matricula.id}`,
    status: statusDs[0],
    statusTone: statusDs[1],
    statusLabel: limiteEsgotado ? 'Limite esgotado' : statusDs[2],
    student: { name: matricula.usuario_nome || matricula.aluno_nome || 'Aluno' },
    modality: matricula.modalidade_nome || matricula.plano_nome || 'Plano',
    modalityIcon: iconeModalidade,
    modalityColor: corModalidade,
    frequency:
      matricula.plano_nome ||
      (matricula.checkins_semanais ? `${matricula.checkins_semanais}x por semana` : ''),
    startDate: isoDia(matricula.data_inicio),
    accessUntil: isoDia(acessoAte) || isoDia(matricula.data_inicio),
    duration: duracaoLabel,
    cycle: {
      label: cicloInfo?.frequencia || null,
      amount: Number(matricula.valor) || 0,
      monthlyEquivalent: mesesCiclo > 1 ? (Number(matricula.valor) || 0) / mesesCiclo : undefined,
      nextDue: isoDia(proximoVencimentoExibicao),
      per: mesesCiclo > 1 ? `a cada ${mesesCiclo} meses` : 'por mês',
    },
  };

  const numeroParcelaMap = buildNumeroParcelaMap(pagamentos);
  const invoicesDs = [...pagamentos]
    .sort((a, b) => {
      const idDiff = Number(getPagamentoId(b) || 0) - Number(getPagamentoId(a) || 0);
      if (idDiff !== 0) return idDiff;
      return String(b.data_vencimento || '').localeCompare(String(a.data_vencimento || ''));
    })
    .map((pg) => {
      const statusId = Number(pg.status_pagamento_id);
      const status =
        statusId === 2
          ? 'pago'
          : statusId === 4
            ? 'cancelado'
            : statusId === 3 || isVencido(pg.data_vencimento)
              ? 'atrasado'
              : 'aguardando';
      const pacoteSemBaixaIndividual = Boolean(pg.pacote_contrato_id);
      return {
        raw: pg,
        id: `#${getPagamentoId(pg) ?? '-'}`,
        number: resolveNumeroParcela(pg, numeroParcelaMap) ?? '-',
        dueDate: isoDia(pg.data_vencimento),
        paidDate: isoDia(pg.data_pagamento),
        paidBy: pg.baixado_por_nome || pg.criado_por_nome || undefined,
        method: pg.forma_pagamento_nome || undefined,
        channel: pg.tipo_baixa_nome ? String(pg.tipo_baixa_nome).toLowerCase() : undefined,
        amount: parseFloat(pg.valor || 0),
        net:
          pg.valor_liquido != null && pg.valor_liquido !== ''
            ? Number(pg.valor_liquido)
            : undefined,
        credit: Number(pg.credito_aplicado) > 0 ? Number(pg.credito_aplicado) : undefined,
        discount: Number(pg.desconto) > 0 ? Number(pg.desconto) : undefined,
        discountNote: pg.motivo_desconto || undefined,
        status,
        canSettle: !pacoteSemBaixaIndividual,
        settleNote: pacoteSemBaixaIndividual ? 'Baixa só no pacote' : undefined,
      };
    });
  const countsDs = {
    overdue: pagamentosPendentes.length,
    active: pagamentos.filter((pg) => Number(pg.status_pagamento_id) !== 4).length,
    cancelled: pagamentos.filter((pg) => Number(pg.status_pagamento_id) === 4).length,
  };

  const descontosDs = descontos.map((desc) => ({
    ...descontoParaDs(desc),
    authorizedBy: desc.autorizado_por_nome || undefined,
    raw: desc,
  }));

  const pacote = isPacote ? matricula.pacote : null;
  const pacoteDs = pacote
    ? {
        contract: `#${pacote.contrato_id}`,
        name: pacote.pacote_nome || 'Pacote',
        status: ['encerrado', 'cancelado', 'finalizado', 'expirado'].includes(
          String(pacote.contrato_status || '').toLowerCase()
        )
          ? 'encerrado'
          : 'ativo',
        startDate: isoDia(pacote.contrato_data_inicio),
        endDate: isoDia(pacote.contrato_data_fim),
        duration:
          pacote.contrato_data_inicio && pacote.contrato_data_fim
            ? `${Math.max(
                1,
                Math.round(
                  (new Date(pacote.contrato_data_fim).getTime() - new Date(pacote.contrato_data_inicio).getTime()) /
                    (1000 * 60 * 60 * 24)
                ) + 1
              )} dias`
            : '-',
        total: Number(pacote.contrato_valor_total || pacote.pacote_valor_total) || 0,
        perPerson: matricula.valor_rateado != null ? Number(matricula.valor_rateado) : undefined,
        payer: pacote.pagante_nome || undefined,
        settled: !podeDarBaixaPacote,
        members: (Array.isArray(pacote.beneficiarios) ? pacote.beneficiarios : []).map((b) => ({
          name: b.aluno_nome || '-',
          role: b.is_pagante ? 'Pagante' : 'Dependente',
          status: b.status || undefined,
          amount: Number(b.valor_rateado) || 0,
        })),
      }
    : undefined;

  // Ações do cabeçalho, conforme o estado da matrícula.
  const acoesCabecalho =
    podeExibirAlterarPlano || podeBloquearMatricula || podeDesbloquearMatricula ? (
      <>
        {podeExibirAlterarPlano && !(matricula?.valor > 0) ? (
          <CkButton variant="ghost" icon="calendar" onClick={handleAbrirModalEditarVencimento}>
            Alterar vencimento
          </CkButton>
        ) : null}
        {podeBloquearMatricula ? (
          <CkButton variant="ghost" icon="lock" onClick={() => setModalBloquearVisible(true)}>
            Bloquear matrícula
          </CkButton>
        ) : null}
        {podeDesbloquearMatricula ? (
          <CkButton
            variant="secondary"
            icon="unlock"
            loading={desbloqueandoMatricula}
            onClick={handleDesbloquearMatricula}
          >
            {desbloqueandoMatricula ? 'Desbloqueando…' : 'Desbloquear matrícula'}
          </CkButton>
        ) : null}
        {podeExibirAlterarPlano ? (
          <CkButton variant="secondary" icon="repeat" onClick={handleAbrirAlterarPlano}>
            {isMatriculaRenovavel ? 'Renovar matrícula' : 'Alterar plano'}
          </CkButton>
        ) : null}
      </>
    ) : null;

  const avisosMatricula =
    limiteEsgotado || isMatriculaBloqueada || (isPacote && isMatriculaRenovavel) ? (
      <>
        {limiteEsgotado ? (
          <CkCallout tone="warning" icon="alert-triangle" title="Limite de check-ins do ciclo esgotado">
            O período pago segue até {formatDate(matricula.proxima_data_vencimento || matricula.data_vencimento)}, mas o
            check-in foi bloqueado porque o aluno usou todos os check-ins do ciclo
            {matricula.limite_ciclo?.checkins_mes != null && matricula.limite_ciclo?.limite_mensal != null
              ? ` (${matricula.limite_ciclo.checkins_mes}/${matricula.limite_ciclo.limite_mensal}${
                  matricula.limite_ciclo?.mes_referencia ? ` em ${matricula.limite_ciclo.mes_referencia}` : ''
                })`
              : ''}
            . A renovação libera o próximo ciclo.
          </CkCallout>
        ) : null}
        {isMatriculaBloqueada ? (
          <CkCallout tone="danger" icon="lock" title="Matrícula bloqueada">
            Check-in e app do aluno estão suspensos. A matrícula não foi excluída.
          </CkCallout>
        ) : null}
        {isPacote && isMatriculaRenovavel ? (
          <CkCallout tone="info" icon="info" title="Renovação fora do pacote">
            A renovação sai do pacote, encerra o desconto e cobra o valor individual do plano.
          </CkCallout>
        ) : null}
      </>
    ) : null;

  const planoSelecionadoAlteracao = planosAlteracao.find(
    (plano) => plano.id === Number(formAlterarPlano.plano_id)
  );

  return (
    <LayoutBase title={`Matrícula #${matricula.id}`} pageHeader={false}>
      {/* O conteúdo já rola dentro do AppShell */}
      <div className="ck-mt-page">
          <Matricula
            enrollment={enrollmentDs}
            pkg={pacoteDs}
            invoices={invoicesDs}
            counts={countsDs}
            discounts={descontosDs}
            today={hojeISO}
            backLabel="Matrículas"
            onBack={() => router.push('/matriculas')}
            onSettle={(inv) => handleBaixaPagamento(inv.raw)}
            invoiceMenu={currentUserId === 3}
            onEditInvoice={(inv) => handleAbrirEditarPagamento(inv.raw)}
            onDeleteInvoice={(inv) => handleAbrirExcluirPagamento(inv.raw)}
            onSettlePackage={() => setModalBaixaPacoteVisible(true)}
            onAddDiscount={abrirNovoDesconto}
            onEditDiscount={(d) => abrirEditarDesconto(d.raw)}
            onRemoveDiscount={(d) => handleDesativarDesconto(d.id)}
            onReload={recarregarFaturas}
            actions={acoesCabecalho}
            notices={avisosMatricula}
          >
            {outrasMatriculas.length > 0 ? (
              <CkCard
                title="Outras matrículas do aluno"
                description="Inclui diárias e planos paralelos deste aluno"
                leading={<CkIcon name="layers" size={20} />}
              >
                <ul className="ck-mt-others">
                  {outrasMatriculas.map((outra) => (
                    <li key={outra.id}>
                      <button type="button" onClick={() => router.push(`/matriculas/detalhe?id=${outra.id}`)}>
                        <span className="ck-mt-others__main">
                          <strong>
                            {outra.plano_nome || 'Plano'}
                            {outra.modalidade_nome ? `, ${outra.modalidade_nome}` : ''}
                          </strong>
                          <span>
                            Matrícula #{outra.id}
                            {Number(outra.duracao_dias) === 1 ? ', diária' : ''}
                            {' · '}
                            {formatDate(outra.data_inicio)} a {formatDate(outra.data_vencimento)}
                          </span>
                        </span>
                        <span className="ck-mt-others__value">{formatCurrency(outra.valor)}</span>
                        <CkBadge>{outra.status_nome || outra.status_codigo || '-'}</CkBadge>
                        <CkIcon name="chevron-right" size={16} />
                      </button>
                    </li>
                  ))}
                </ul>
              </CkCard>
            ) : null}
          </Matricula>
      </div>

      {/* Desconto (novo + editar) */}
      <DescontoModal
        key={modalDescontoVisible ? descontoEditando?.id || 'novo' : 'fechado'}
        open={modalDescontoVisible}
        discount={descontoEditando ? descontoParaDs(descontoEditando) : { start: hojeISO }}
        planAmount={Number(matricula.valor) || 0}
        studentName={matricula.usuario_nome}
        authorizers={adminsLista.map((adm) => ({ value: String(adm.id), label: adm.nome }))}
        today={hojeISO}
        onCancel={() => setModalDescontoVisible(false)}
        onSave={handleSalvarDesconto}
        onRemove={(d) => {
          setModalDescontoVisible(false);
          handleDesativarDesconto(d.id);
        }}
      />

      {/* Modal de Baixa de Pagamento */}
      <BaixaPagamentoPlanoModal
        visible={modalVisible}
        onClose={() => setModalVisible(false)}
        pagamento={pagamentoSelecionado}
        student={matricula?.usuario_nome || matricula?.aluno_nome}
        plan={matricula?.plano_nome}
        onSuccess={handleBaixaSuccess}
      />

      {/* Modal de Baixa do Pacote */}
      <Modal
        visible={modalBaixaPacoteVisible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setModalBaixaPacoteVisible(false)}
      >
        <View className="flex-1 items-center justify-center bg-black/40 px-4">
          <View className="w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <View className="items-center border-b border-slate-200 px-6 py-5">
              <View className="mb-3 h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                <Feather name="check-circle" size={28} color="#10b981" />
              </View>
              <Text className="text-lg font-bold text-slate-800">Baixar Pacote</Text>
              <Text className="text-sm text-slate-500">Confirme a baixa do pacote pendente</Text>
            </View>

            <View className="px-6 py-5">
              <Text className="text-center text-sm leading-6 text-slate-600">
                Esta ação confirma o pagamento do pacote e libera as matrículas vinculadas.
              </Text>

              <View className="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                <Text className="text-xs text-slate-500">Valor total a baixar</Text>
                <Text className="text-base font-bold text-slate-800">
                  {formatCurrency(matricula?.pacote?.contrato_valor_total || matricula?.pacote?.pacote_valor_total)}
                </Text>
                {(matricula?.proxima_data_vencimento || matricula?.data_vencimento) && (
                  <Text className="mt-2 text-[12px] text-slate-500">
                    Vigência até {formatDate(matricula?.proxima_data_vencimento || matricula?.data_vencimento)}
                  </Text>
                )}
              </View>

              {Array.isArray(matricula?.pacote?.beneficiarios) && matricula.pacote.beneficiarios.length > 0 && (
                <View className="mt-4">
                  <Text className="mb-2 text-xs font-semibold text-slate-500">Beneficiários afetados</Text>
                  <View className="rounded-lg border border-slate-200 bg-white">
                    {matricula.pacote.beneficiarios.map((beneficiario, idx) => (
                      <View
                        key={`${beneficiario.aluno_id}-${idx}`}
                        className="flex-row items-center justify-between border-b border-slate-100 px-4 py-3"
                      >
                        <View className="flex-1">
                          <Text className="text-sm font-semibold text-slate-700">
                            {beneficiario.aluno_nome || '-'}
                          </Text>
                          <Text className="text-[11px] text-slate-500">
                            {beneficiario.is_pagante ? 'Pagante' : 'Dependente'} • {beneficiario.status || '-'}
                          </Text>
                        </View>
                        <Text className="text-sm font-semibold text-slate-700">
                          {formatCurrency(beneficiario.valor_rateado)}
                        </Text>
                      </View>
                    ))}
                  </View>
                </View>
              )}
            </View>

            <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => setModalBaixaPacoteVisible(false)}
                disabled={baixaPacoteLoading}
                className="flex-1 items-center justify-center rounded-lg bg-slate-200 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.7 }]}
              >
                <Text className="text-sm font-semibold text-slate-700">Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={handleConfirmarBaixaPacote}
                disabled={baixaPacoteLoading}
                className="flex-1 items-center justify-center rounded-lg bg-emerald-600 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }, baixaPacoteLoading && { opacity: 0.6 }]}
              >
                {baixaPacoteLoading ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text className="text-sm font-semibold text-white">Confirmar baixa</Text>
                )}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal de Editar Data de Vencimento */}
      <Modal
        visible={modalEditarVencimento}
        transparent={true}
        animationType="slide"
        onRequestClose={() => setModalEditarVencimento(false)}
      >
        <View className="flex-1 items-center justify-center bg-black/50 px-4">
          <View className="w-full max-w-md rounded-2xl bg-white shadow-2xl">
            {/* Header */}
            <View className="border-b border-slate-200 px-6 py-5">
              <View className="flex-row items-center gap-3">
                <View className="h-12 w-12 items-center justify-center rounded-full bg-orange-100">
                  <Feather name="calendar" size={24} color="#f97316" />
                </View>
                <View className="flex-1">
                  <Text className="text-lg font-bold text-slate-800">Alterar Data de Vencimento</Text>
                  <Text className="text-sm text-slate-500">Atualize quando o acesso expira</Text>
                </View>
              </View>
            </View>
            
            {/* Body */}
            <View className="px-6 py-5">
              <View className="mb-4">
                <Text className="mb-2 text-sm font-semibold text-slate-700">Data Atual</Text>
                <View className="rounded-lg bg-slate-50 px-4 py-3">
                  <Text className="text-sm text-slate-600">
                    {formatDate(matricula?.proxima_data_vencimento || matricula?.data_vencimento)}
                    {matricula?.proxima_data_vencimento && (
                      <Text className="text-slate-500">
                        {' '}• {calcularDiasRestantes(matricula.proxima_data_vencimento) >= 0 
                          ? `${calcularDiasRestantes(matricula.proxima_data_vencimento)} dias restantes`
                          : 'Vencido'}
                      </Text>
                    )}
                  </Text>
                </View>
              </View>

              <View className="mb-2">
                <Text className="mb-2 text-sm font-semibold text-slate-700">Nova Data de Vencimento</Text>
                <TextInput
                  className="rounded-lg border-2 border-slate-300 bg-white px-4 py-3 text-sm text-slate-800"
                  value={novaDataVencimento}
                  onChangeText={(text) => {
                    // Apenas aplicar máscara enquanto digita, sem tentar converter
                    setNovaDataVencimento(mascaraData(text));
                  }}
                  placeholder="DD/MM/YYYY"
                  placeholderTextColor="#94a3b8"
                  keyboardType="numeric"
                  maxLength={10}
                />
              </View>
              <Text className="text-xs text-slate-500">
                Formato: DD/MM/YYYY (ex: 15/02/2026)
              </Text>
            </View>

            {/* Footer */}
            <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => setModalEditarVencimento(false)}
                disabled={salvandoData}
                className="flex-1 items-center justify-center rounded-lg bg-slate-200 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.7 }]}
              >
                <Text className="text-sm font-semibold text-slate-700">Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={handleAtualizarVencimento}
                disabled={salvandoData}
                className="flex-1 items-center justify-center rounded-lg bg-orange-500 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }, salvandoData && { opacity: 0.5 }]}
              >
                {salvandoData ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text className="text-sm font-semibold text-white">Salvar</Text>
                )}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>

      <Modal
        visible={modalAlterarPlanoVisible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setModalAlterarPlanoVisible(false)}
      >
        <View className="flex-1 items-center justify-center bg-black/50 px-4">
          <View className="w-full max-w-3xl rounded-2xl bg-white shadow-2xl">

            {/* ===== ETAPA 1: Cancelamento com crédito ===== */}
            {etapaAlterarPlano === 'cancelamento' && (
              <>
                <View className="border-b border-slate-100 px-5 py-3">
                  <View className="flex-row items-center gap-2">
                    <Feather name="alert-circle" size={16} color="#94a3b8" />
                    <View className="flex-1">
                      <Text className="text-sm font-semibold text-slate-700">Cancelar matrícula atual</Text>
                      <Text className="text-[10px] text-slate-400">
                        A matrícula atual será cancelada para permitir a troca de plano.
                      </Text>
                    </View>
                  </View>
                </View>

                <ScrollView
                  className="px-5 py-3"
                  style={{ maxHeight: isDesktop ? 700 : 500 }}
                  showsVerticalScrollIndicator={false}
                >
                  {loadingSimulacao ? (
                    <View className="items-center py-8">
                      <ActivityIndicator size="large" color="#f97316" />
                      <Text className="mt-2 text-xs text-slate-500">Simulando cancelamento...</Text>
                    </View>
                  ) : simulacaoCancelamento ? (
                    <View className="gap-3">
                      {/* Info do plano atual */}
                      <View className="flex-row items-center justify-between rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2">
                        <View>
                          <Text className="text-[10px] text-slate-400">Plano atual</Text>
                          <Text className="text-xs font-medium text-slate-700">
                            {simulacaoCancelamento.plano_nome}
                          </Text>
                        </View>
                        <Text className="text-xs font-medium text-slate-500">
                          {formatCurrency(simulacaoCancelamento.valor_plano)}
                        </Text>
                      </View>

                      {/* Dias - inline */}
                      <View className="flex-row items-center gap-4 px-1">
                        <Text className="text-[10px] text-slate-400">
                          Utilizados: <Text className="font-medium text-slate-600">{simulacaoCancelamento.dias_utilizados}</Text> de {simulacaoCancelamento.dias_totais} dias
                        </Text>
                        <Text className="text-[10px] text-slate-400">
                          Restantes: <Text className="font-medium text-slate-600">{simulacaoCancelamento.dias_restantes}</Text>
                        </Text>
                      </View>

                      {/* Crédito proporcional - destaque principal */}
                      <View className="items-center rounded-lg border border-slate-200 bg-white px-4 py-4">
                        <Text className="text-[10px] uppercase tracking-wide text-slate-400">Crédito proporcional</Text>
                        <Text className="mt-1 text-2xl font-bold text-slate-800">
                          {formatCurrency(simulacaoCancelamento.valor_proporcional_credito)}
                        </Text>
                        <Text className="mt-0.5 text-[10px] text-slate-400">
                          {simulacaoCancelamento.dias_restantes} dias restantes
                        </Text>
                      </View>

                      {/* Info adicional */}
                      {simulacaoCancelamento.parcelas_pendentes > 0 && (
                        <Text className="px-1 text-[10px] text-slate-400">
                          {simulacaoCancelamento.parcelas_pendentes} parcela(s) pendente(s) serão cancelada(s).
                        </Text>
                      )}

                      {simulacaoCancelamento.saldo_creditos_atual > 0 && (
                        <Text className="px-1 text-[10px] text-slate-400">
                          Saldo de créditos existente: {formatCurrency(simulacaoCancelamento.saldo_creditos_atual)}
                        </Text>
                      )}

                      {/* Checkbox gerar crédito */}
                      <Pressable
                        className={`flex-row items-center gap-2 rounded-lg border px-3 py-2 ${
                          gerarCreditoCancelamento ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'
                        }`}
                        onPress={() => setGerarCreditoCancelamento((prev) => !prev)}
                        style={({ pressed }) => [pressed && { opacity: 0.85 }]}
                      >
                        <View
                          className={`h-4 w-4 items-center justify-center rounded border ${
                            gerarCreditoCancelamento ? 'border-emerald-500 bg-emerald-500' : 'border-slate-300 bg-white'
                          }`}
                        >
                          {gerarCreditoCancelamento ? <Feather name="check" size={10} color="#fff" /> : null}
                        </View>
                        <View className="flex-1">
                          <Text className="text-xs font-medium text-slate-600">Gerar crédito proporcional</Text>
                          <Text className="text-[10px] text-slate-400">
                            {formatCurrency(simulacaoCancelamento.valor_proporcional_credito)} será creditado ao aluno
                          </Text>
                        </View>
                      </Pressable>
                    </View>
                  ) : null}
                </ScrollView>

                <View className="flex-row gap-3 border-t border-slate-100 px-5 py-3">
                  <Pressable
                    onPress={() => setModalAlterarPlanoVisible(false)}
                    disabled={cancelandoMatricula}
                    className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white py-2"
                    style={({ pressed }) => [pressed && { opacity: 0.8 }]}
                  >
                    <Text className="text-xs font-medium text-slate-500">Voltar</Text>
                  </Pressable>
                  <Pressable
                    onPress={() => setModalConfirmCancelamentoVisible(true)}
                    disabled={cancelandoMatricula || loadingSimulacao || !simulacaoCancelamento}
                    className="flex-1 items-center justify-center rounded-lg bg-slate-800 py-2"
                    style={({ pressed }) => [
                      pressed && { opacity: 0.8 },
                      (cancelandoMatricula || loadingSimulacao || !simulacaoCancelamento) && { opacity: 0.6 },
                    ]}
                  >
                    {cancelandoMatricula ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <Text className="text-xs font-medium text-white">Cancelar e continuar</Text>
                    )}
                  </Pressable>
                </View>
              </>
            )}

            {/* ===== ETAPA 2: Selecionar novo plano ===== */}
            {etapaAlterarPlano === 'plano' && (
              <>
            {!!alterarPlanoErro && (
              <View className="mx-5 mt-3 flex-row items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2">
                <Feather name="alert-circle" size={16} color="#e11d48" />
                <Text className="flex-1 text-xs leading-5 text-rose-800">{alterarPlanoErro}</Text>
              </View>
            )}
            <View className="border-b border-slate-100 px-5 py-3">
              <View className="flex-row items-center gap-2">
                <Feather name="repeat" size={16} color="#64748b" />
                <View className="flex-1">
                  <Text className="text-sm font-semibold text-slate-700">Selecionar novo plano</Text>
                  <Text className="text-[10px] text-slate-400">
                    Atualize plano, ciclo e regra de vencimento.
                  </Text>
                </View>
              </View>
            </View>

            <ScrollView
              className="px-5 py-3"
              style={{ maxHeight: isDesktop ? 700 : 500 }}
              showsVerticalScrollIndicator={false}
            >
              <View className="mb-2 flex-row items-center justify-between rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2">
                <View>
                  <Text className="text-[10px] text-slate-400">Plano atual</Text>
                  <Text className="text-xs font-medium text-slate-600">{matricula?.plano_nome || '-'}</Text>
                </View>
                <Text className="text-[10px] text-slate-400">
                  {matricula?.modalidade_nome || '-'}
                  {cicloInfo ? ` • ${cicloInfo.frequencia}` : ''}
                </Text>
              </View>

              <View className="mb-2">
                <Text className="mb-1 text-[10px] font-medium text-slate-500">Plano</Text>
                {loadingPlanosAlteracao ? (
                  <View className="items-center rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-3">
                    <ActivityIndicator size="small" color="#94a3b8" />
                    <Text className="mt-1 text-[10px] text-slate-400">Carregando planos...</Text>
                  </View>
                ) : planosAlteracao.length > 0 ? (
                  <View className="rounded-lg border border-slate-200 bg-white">
                    <Picker
                      selectedValue={formAlterarPlano.plano_id}
                      onValueChange={(value) => {
                        setFormAlterarPlano((prev) => ({
                          ...prev,
                          plano_id: value,
                          plano_ciclo_id: '',
                        }));
                      }}
                      style={{ height: 40, fontSize: 13 }}
                    >
                      <Picker.Item label="Selecione um plano" value="" />
                      {planosAlteracao.map((plano) => (
                        <Picker.Item
                          key={plano.id}
                          label={`${plano.nome} • ${formatCurrency(plano.valor)}`}
                          value={plano.id.toString()}
                        />
                      ))}
                    </Picker>
                  </View>
                ) : (
                  <View className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <Text className="text-sm text-amber-700">Nenhum plano disponível para alteração.</Text>
                  </View>
                )}
              </View>

              {planoSelecionadoAlteracao ? (
                <View className="mb-2 flex-row items-center justify-between rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2">
                  <View className="flex-1">
                    <Text className="text-xs font-medium text-slate-600">{planoSelecionadoAlteracao.nome}</Text>
                    <Text className="text-[10px] text-slate-400">
                      {planoSelecionadoAlteracao.checkins_semanais || 0}x por semana • {planoSelecionadoAlteracao.duracao_dias || 0} dias
                    </Text>
                  </View>
                  <Text className="text-xs font-semibold text-slate-700">
                    {formatCurrency(planoSelecionadoAlteracao.valor)}
                  </Text>
                </View>
              ) : null}

              <View className="mb-2">
                <Text className="mb-1 text-[10px] font-medium text-slate-500">Ciclo de pagamento</Text>
                {loadingCiclosAlteracao ? (
                  <View className="items-center rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-3">
                    <ActivityIndicator size="small" color="#94a3b8" />
                    <Text className="mt-1 text-xs text-slate-500">Carregando ciclos...</Text>
                  </View>
                ) : ciclosAlteracao.length > 0 ? (
                  <View className="flex-row flex-wrap gap-2">
                    {ciclosAlteracao.map((ciclo) => {
                      const selecionado = formAlterarPlano.plano_ciclo_id === ciclo.id.toString();
                      return (
                        <Pressable
                          key={ciclo.id}
                          className={`min-w-[140px] flex-1 rounded-lg border px-3 py-2 ${
                            selecionado ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 bg-white'
                          }`}
                          style={({ pressed }) => [
                            { flexBasis: isDesktop ? 160 : 'auto' },
                            pressed && { opacity: 0.85 },
                          ]}
                          onPress={() => {
                            setFormAlterarPlano((prev) => ({ ...prev, plano_ciclo_id: ciclo.id.toString() }));
                          }}
                        >
                          <View className="flex-row items-center justify-between gap-1">
                            <Text className={`text-[10px] font-medium ${selecionado ? 'text-emerald-700' : 'text-slate-500'}`}>
                              {getCicloLabel(ciclo)}
                            </Text>
                            {selecionado ? <Feather name="check" size={12} color="#059669" /> : null}
                          </View>
                          <Text className={`mt-0.5 text-xs font-semibold ${selecionado ? 'text-emerald-800' : 'text-slate-600'}`}>
                            {formatCurrency(ciclo.valor)}
                          </Text>
                          {!!Number(ciclo.desconto_percentual || 0) && (
                            <Text className="mt-0.5 text-[10px] text-slate-400">
                              {Number(ciclo.desconto_percentual)}% de desconto
                            </Text>
                          )}
                        </Pressable>
                      );
                    })}
                  </View>
                ) : formAlterarPlano.plano_id ? (
                  <View className="rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2">
                    <Text className="text-[10px] text-slate-400">
                      Plano sem ciclo de recorrência — será usado o valor único do plano.
                    </Text>
                  </View>
                ) : (
                  <View className="rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2">
                    <Text className="text-[10px] text-slate-400">Selecione um plano para ver os ciclos.</Text>
                  </View>
                )}
              </View>

              <View className="mb-2">
                <Text className="mb-1 text-[10px] font-medium text-slate-500">Data de início</Text>
                {Platform.OS === 'web' ? (
                  <input
                    type="date"
                    style={{
                      borderWidth: 1,
                      borderColor: '#cbd5e1',
                      borderRadius: 8,
                      padding: 10,
                      fontSize: 14,
                      width: '100%',
                    }}
                    value={formAlterarPlano.data_inicio}
                    onChange={(e) =>
                      setFormAlterarPlano((prev) => ({ ...prev, data_inicio: e.target.value }))
                    }
                  />
                ) : (
                  <TextInput
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                    value={formAlterarPlano.data_inicio}
                    onChangeText={(text) =>
                      setFormAlterarPlano((prev) => ({ ...prev, data_inicio: text }))
                    }
                    placeholder="YYYY-MM-DD"
                    autoCapitalize="none"
                  />
                )}
              </View>

              {/* Crédito na primeira parcela */}
              {(() => {
                const ultimoPagamentoPago = [...pagamentos]
                  .filter((p) => Number(p.status_pagamento_id) === 2)
                  .sort((a, b) => new Date(b.data_pagamento || b.data_vencimento) - new Date(a.data_pagamento || a.data_vencimento))[0];
                const valorUltimoPago = ultimoPagamentoPago ? parseFloat(ultimoPagamentoPago.valor || 0) : 0;
                const valorPlanoAtual = parseFloat(matricula?.valor || 0);

                const cicloSelecionado = ciclosAlteracao.find(
                  (c) => c.id.toString() === formAlterarPlano.plano_ciclo_id
                );
                const valorNovoParcela = cicloSelecionado
                  ? parseFloat(cicloSelecionado.valor || 0)
                  : parseFloat(planoSelecionadoAlteracao?.valor || matricula?.valor || 0);

                const creditoEstimado =
                  formAlterarPlano.tipo_credito === 'abater_plano'
                    ? valorPlanoAtual
                    : formAlterarPlano.tipo_credito === 'abater'
                    ? valorUltimoPago
                    : formAlterarPlano.tipo_credito === 'manual'
                    ? parseFloat(formAlterarPlano.credito) || 0
                    : 0;
                const saldoExistente = formAlterarPlano.usar_credito_existente
                  ? parseFloat(saldoCreditosAluno?.saldo_total || 0)
                  : 0;
                const totalCredito = creditoEstimado + saldoExistente;
                const valorPrimeiraParcela = Math.max(0, valorNovoParcela - totalCredito);
                const saldoRestanteEstimado = Math.max(0, totalCredito - valorNovoParcela);

                const opcoes = [
                  { key: 'nenhum', label: 'Sem crédito', icon: 'x-circle' },
                  ...(valorPlanoAtual > 0 && ultimoPagamentoPago && !isMatriculaCancelada
                    ? [{ key: 'abater_plano', label: `Valor cheio do plano (${formatCurrency(valorPlanoAtual)})`, icon: 'dollar-sign' }]
                    : []),
                  ...(ultimoPagamentoPago && !isMatriculaCancelada
                    ? [{ key: 'abater', label: `Proporcional (dias restantes)`, icon: 'refresh-cw' }]
                    : []),
                  { key: 'manual', label: 'Crédito manual', icon: 'edit-3' },
                ];

                return (
                  <View className="mb-2">
                    <Text className="mb-1 text-[10px] font-medium text-slate-500">Crédito na primeira parcela</Text>
                    <View className="flex-row flex-wrap gap-2">
                      {opcoes.map((opcao) => {
                        const selecionado = formAlterarPlano.tipo_credito === opcao.key;
                        return (
                          <Pressable
                            key={opcao.key}
                            className={`min-w-[100px] flex-1 rounded-lg border px-2 py-1.5 ${
                              selecionado ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 bg-white'
                            }`}
                            style={({ pressed }) => [pressed && { opacity: 0.85 }]}
                            onPress={() =>
                              setFormAlterarPlano((prev) => ({
                                ...prev,
                                tipo_credito: opcao.key,
                                ...(opcao.key !== 'manual' ? { credito: '', motivo_credito: '' } : {}),
                              }))
                            }
                          >
                            <View className="flex-row items-center gap-1">
                              <Feather name={opcao.icon} size={10} color={selecionado ? '#059669' : '#94a3b8'} />
                              <Text className={`text-[10px] font-medium ${selecionado ? 'text-emerald-700' : 'text-slate-500'}`}>
                                {opcao.label}
                              </Text>
                              {selecionado ? <Feather name="check" size={10} color="#475569" /> : null}
                            </View>
                          </Pressable>
                        );
                      })}
                    </View>

                    {formAlterarPlano.tipo_credito === 'abater_plano' && (
                      <View className="mt-1 px-1">
                        <Text className="text-[10px] text-slate-400">
                          Crédito de {formatCurrency(valorPlanoAtual)} (valor cheio do plano/ciclo atual) será aplicado na primeira parcela.
                        </Text>
                      </View>
                    )}

                    {formAlterarPlano.tipo_credito === 'abater' && ultimoPagamentoPago && (
                      <View className="mt-1 px-1">
                        <Text className="text-[10px] text-slate-400">
                          Crédito proporcional será calculado com base nos dias restantes. Último pagamento: {formatCurrency(valorUltimoPago)}.
                        </Text>
                      </View>
                    )}

                    {formAlterarPlano.tipo_credito === 'manual' && (
                      <View className="mt-1.5 gap-1.5">
                        <View>
                          <Text className="mb-0.5 text-[10px] font-medium text-slate-500">Valor do crédito (R$)</Text>
                          <TextInput
                            className="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700"
                            value={formAlterarPlano.credito}
                            onChangeText={(text) =>
                              setFormAlterarPlano((prev) => ({ ...prev, credito: text.replace(/[^0-9.,]/g, '') }))
                            }
                            placeholder="Ex: 150.00"
                            keyboardType="decimal-pad"
                          />
                        </View>
                        <View>
                          <Text className="mb-0.5 text-[10px] font-medium text-slate-500">Motivo do crédito</Text>
                          <TextInput
                            className="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700"
                            value={formAlterarPlano.motivo_credito}
                            onChangeText={(text) =>
                              setFormAlterarPlano((prev) => ({ ...prev, motivo_credito: text }))
                            }
                            placeholder="Ex: Acordo comercial"
                          />
                        </View>
                      </View>
                    )}

                    {/* Checkbox: Usar créditos existentes */}
                    {(() => {
                      const saldoDisponivel = parseFloat(saldoCreditosAluno?.saldo_total || 0);
                      if (loadingSaldoCreditos) {
                        return (
                          <View className="mt-2 flex-row items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <ActivityIndicator size="small" color="#f97316" />
                            <Text className="text-xs text-slate-500">Verificando créditos do aluno...</Text>
                          </View>
                        );
                      }
                      if (saldoDisponivel <= 0) return null;
                      return (
                        <Pressable
                          className={`mt-1.5 flex-row items-center gap-2 rounded-lg border px-2.5 py-1.5 ${
                            formAlterarPlano.usar_credito_existente
                              ? 'border-emerald-400 bg-emerald-50'
                              : 'border-slate-200 bg-white'
                          }`}
                          style={({ pressed }) => [pressed && { opacity: 0.85 }]}
                          onPress={() =>
                            setFormAlterarPlano((prev) => ({
                              ...prev,
                              usar_credito_existente: !prev.usar_credito_existente,
                            }))
                          }
                        >
                          <View
                            className={`h-4 w-4 items-center justify-center rounded border ${
                              formAlterarPlano.usar_credito_existente
                                ? 'border-emerald-500 bg-emerald-500'
                                : 'border-slate-300 bg-white'
                            }`}
                          >
                            {formAlterarPlano.usar_credito_existente && (
                              <Feather name="check" size={10} color="#fff" />
                            )}
                          </View>
                          <View className="flex-1">
                            <Text className={`text-[10px] font-medium ${
                              formAlterarPlano.usar_credito_existente ? 'text-slate-700' : 'text-slate-500'
                            }`}>
                              Usar créditos existentes do aluno
                            </Text>
                            <Text className="text-[10px] text-slate-400">
                              Saldo disponível: {formatCurrency(saldoDisponivel)}
                            </Text>
                          </View>
                        </Pressable>
                      );
                    })()}

                    {(formAlterarPlano.tipo_credito !== 'nenhum' || formAlterarPlano.usar_credito_existente) && valorNovoParcela > 0 && totalCredito > 0 && (
                      <View className="mt-2 rounded-lg border border-slate-200 bg-slate-50/50 p-2.5">
                        <Text className="text-[10px] font-medium uppercase text-slate-400">Preview da 1ª parcela</Text>
                        <View className="mt-1 flex-row items-center justify-between">
                          <Text className="text-xs text-slate-600">Valor do ciclo</Text>
                          <Text className="text-xs font-semibold text-slate-800">{formatCurrency(valorNovoParcela)}</Text>
                        </View>
                        {creditoEstimado > 0 && (
                          <View className="mt-1 flex-row items-center justify-between">
                            <Text className="text-xs text-slate-600">
                              {formAlterarPlano.tipo_credito === 'abater_plano'
                                ? 'Crédito (plano anterior)'
                                : formAlterarPlano.tipo_credito === 'abater'
                                ? 'Crédito (proporcional)'
                                : 'Crédito manual'}
                            </Text>
                            <Text className="text-xs font-medium text-slate-500">
                              - {formatCurrency(Math.min(creditoEstimado, valorNovoParcela))}
                            </Text>
                          </View>
                        )}
                        {saldoExistente > 0 && (
                          <View className="mt-1 flex-row items-center justify-between">
                            <Text className="text-xs text-slate-600">Créditos existentes</Text>
                            <Text className="text-xs font-medium text-slate-500">
                              - {formatCurrency(Math.min(saldoExistente, Math.max(0, valorNovoParcela - creditoEstimado)))}
                            </Text>
                          </View>
                        )}
                        <View className="mt-1 border-t border-slate-200 pt-1 flex-row items-center justify-between">
                          <Text className="text-xs font-semibold text-slate-700">1ª Parcela</Text>
                          <Text className="text-sm font-bold text-slate-800">{formatCurrency(valorPrimeiraParcela)}</Text>
                        </View>
                        {saldoRestanteEstimado > 0 && (
                          <View className="mt-1.5 px-1">
                            <Text className="text-[10px] text-slate-400">
                              Saldo restante de {formatCurrency(saldoRestanteEstimado)} ficará como crédito ativo do aluno.
                            </Text>
                          </View>
                        )}
                      </View>
                    )}
                  </View>
                );
              })()}

              <View>
                <Text className="mb-1 text-[10px] font-medium text-slate-500">Observações</Text>
                <TextInput
                  className="min-h-[48px] rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700"
                  value={formAlterarPlano.observacoes}
                  onChangeText={(text) =>
                    setFormAlterarPlano((prev) => ({ ...prev, observacoes: text }))
                  }
                  placeholder="Ex: Troca de plano solicitada pelo aluno"
                  multiline
                  textAlignVertical="top"
                />
              </View>
            </ScrollView>

            <View className="flex-row gap-3 border-t border-slate-100 px-5 py-2.5">
              <Pressable
                onPress={() => setModalAlterarPlanoVisible(false)}
                disabled={salvandoAlteracaoPlano}
                className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white py-2"
                style={({ pressed }) => [pressed && { opacity: 0.8 }]}
              >
                <Text className="text-xs font-medium text-slate-500">Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={() => setModalConfirmAlteracaoVisible(true)}
                disabled={salvandoAlteracaoPlano || loadingPlanosAlteracao || loadingCiclosAlteracao}
                className="flex-1 items-center justify-center rounded-lg bg-slate-800 py-2"
                style={({ pressed }) => [
                  pressed && { opacity: 0.8 },
                  (salvandoAlteracaoPlano || loadingPlanosAlteracao || loadingCiclosAlteracao) && { opacity: 0.6 },
                ]}
              >
                {salvandoAlteracaoPlano ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text className="text-xs font-medium text-white">Confirmar alteração</Text>
                )}
              </Pressable>
            </View>
              </>
            )}

          </View>
        </View>
      </Modal>

      {/* Modal de Confirmação - Cancelar e continuar */}
      <Modal
        visible={modalConfirmCancelamentoVisible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setModalConfirmCancelamentoVisible(false)}
      >
        <View className="flex-1 items-center justify-center bg-black/40 px-4">
          <View className="w-full max-w-sm rounded-2xl bg-white shadow-2xl">
            <View className="items-center border-b border-slate-200 px-6 py-5">
              <View className="mb-3 h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                <Feather name="alert-triangle" size={24} color="#f59e0b" />
              </View>
              <Text className="text-base font-bold text-slate-800">Confirmar cancelamento</Text>
              <Text className="mt-1 text-center text-xs text-slate-500">
                A matrícula atual será cancelada e um crédito proporcional será gerado. Deseja continuar?
              </Text>
            </View>
            <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => setModalConfirmCancelamentoVisible(false)}
                className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white py-2.5"
                style={({ pressed }) => [pressed && { opacity: 0.7 }]}
              >
                <Text className="text-xs font-semibold text-slate-600">Não, voltar</Text>
              </Pressable>
              <Pressable
                onPress={() => {
                  setModalConfirmCancelamentoVisible(false);
                  handleConfirmarCancelamentoEAvancar();
                }}
                className="flex-1 items-center justify-center rounded-lg bg-slate-800 py-2.5"
                style={({ pressed }) => [pressed && { opacity: 0.8 }]}
              >
                <Text className="text-xs font-semibold text-white">Sim, cancelar e continuar</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal de Confirmação - Alterar plano */}
      <Modal
        visible={modalConfirmAlteracaoVisible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setModalConfirmAlteracaoVisible(false)}
      >
        <View className="flex-1 items-center justify-center bg-black/40 px-4">
          <View className="w-full max-w-sm rounded-2xl bg-white shadow-2xl">
            <View className="items-center border-b border-slate-200 px-6 py-5">
              <View className="mb-3 h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                <Feather name="check-circle" size={24} color="#059669" />
              </View>
              <Text className="text-base font-bold text-slate-800">Confirmar alteração de plano</Text>
              <Text className="mt-1 text-center text-xs text-slate-500">
                O plano da matrícula será alterado. Essa ação não pode ser desfeita. Deseja continuar?
              </Text>
            </View>
            <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => setModalConfirmAlteracaoVisible(false)}
                className="flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white py-2.5"
                style={({ pressed }) => [pressed && { opacity: 0.7 }]}
              >
                <Text className="text-xs font-semibold text-slate-600">Não, voltar</Text>
              </Pressable>
              <Pressable
                onPress={() => {
                  setModalConfirmAlteracaoVisible(false);
                  handleConfirmarAlteracaoPlano();
                }}
                className="flex-1 items-center justify-center rounded-lg bg-emerald-600 py-2.5"
                style={({ pressed }) => [pressed && { opacity: 0.8 }]}
              >
                <Text className="text-xs font-semibold text-white">Sim, alterar plano</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal de Editar Pagamento */}
      <Modal
        visible={modalEditarPagamentoVisible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setModalEditarPagamentoVisible(false)}
      >
        <View className="flex-1 items-center justify-center bg-black/50 px-4">
          <View className="w-full max-w-xl rounded-2xl bg-white shadow-2xl">
            <View className="border-b border-slate-200 px-6 py-5">
              <Text className="text-lg font-bold text-slate-800">Editar pagamento</Text>
              <Text className="text-sm text-slate-500">
                Parcela #{getPagamentoId(pagamentoEditando) || '-'}
              </Text>
            </View>

            <ScrollView
              className="px-6 py-4"
              style={{ maxHeight: isDesktop ? 430 : 360 }}
              showsVerticalScrollIndicator={false}
            >
              {pagamentoEditando?.valor_original && (
                <View className="mb-3 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2.5 flex-row items-center justify-between">
                  <Text className="text-xs font-semibold text-blue-700">Valor original da mensalidade</Text>
                  <Text className="text-sm font-bold text-blue-800">{formatCurrency(pagamentoEditando.valor_original)}</Text>
                </View>
              )}

              <View className="mb-3 flex-row gap-3">
                <View className="flex-1">
                  <Text className="mb-1 text-xs font-semibold uppercase text-slate-500">Valor</Text>
                  <TextInput
                    className="rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-500"
                    value={formEditarPagamento.valor}
                    editable={false}
                    selectTextOnFocus={false}
                  />
                </View>

                <View className="flex-1">
                  <Text className="mb-1 text-xs font-semibold uppercase text-slate-500">Desconto</Text>
                  <TextInput
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                    value={formEditarPagamento.desconto}
                    onChangeText={(text) =>
                      setFormEditarPagamento((prev) => ({ ...prev, desconto: aplicarMascaraDesconto(text) }))
                    }
                    keyboardType="decimal-pad"
                    placeholder="0,00"
                  />
                </View>
              </View>

              <View className="mb-3 flex-row gap-3">
                <View className="flex-1">
                  <Text className="mb-1 text-xs font-semibold uppercase text-slate-500">Data de vencimento</Text>
                  <input
                    type="date"
                    style={{
                      borderWidth: 1,
                      borderColor: '#cbd5e1',
                      borderRadius: 8,
                      padding: 10,
                      fontSize: 14,
                      width: '100%',
                    }}
                    value={formEditarPagamento.data_vencimento}
                    onChange={(e) => setFormEditarPagamento((prev) => ({ ...prev, data_vencimento: e.target.value }))}
                  />
                </View>

                <View className="flex-1">
                  <Text className="mb-1 text-xs font-semibold uppercase text-slate-500">Data de pagamento</Text>
                  <input
                    type="date"
                    style={{
                      borderWidth: 1,
                      borderColor: '#cbd5e1',
                      borderRadius: 8,
                      padding: 10,
                      fontSize: 14,
                      width: '100%',
                    }}
                    value={formEditarPagamento.data_pagamento || ''}
                    onChange={(e) => setFormEditarPagamento((prev) => ({ ...prev, data_pagamento: e.target.value }))}
                  />
                </View>
              </View>

              <View className="mb-3">
                <Text className="mb-1 text-xs font-semibold uppercase text-slate-500">Motivo do desconto</Text>
                <TextInput
                  className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                  value={formEditarPagamento.motivo_desconto}
                  onChangeText={(text) => setFormEditarPagamento((prev) => ({ ...prev, motivo_desconto: text }))}
                  placeholder="Ex: desconto promocional"
                />
              </View>

              <View className="mb-3">
                <Text className="mb-1 text-xs font-semibold uppercase text-slate-500">Status</Text>
                <View className="flex-row flex-wrap gap-2">
                  {[
                    { id: '1', label: 'Aguardando', color: '#3B82F6' },
                    { id: '2', label: 'Pago', color: '#10b981' },
                    { id: '3', label: 'Atrasado', color: '#dc2626' },
                    { id: '4', label: 'Cancelado', color: '#6B7280' },
                  ].map((status) => {
                    const ativo = formEditarPagamento.status_pagamento_id === status.id;
                    return (
                      <Pressable
                        key={status.id}
                        className="rounded-full border px-3 py-1"
                        style={{
                          borderColor: ativo ? status.color : '#cbd5e1',
                          backgroundColor: ativo ? status.color : '#fff',
                        }}
                        onPress={() => setFormEditarPagamento((prev) => ({ ...prev, status_pagamento_id: status.id }))}
                      >
                        <Text
                          className="text-xs font-semibold"
                          style={{ color: ativo ? '#fff' : '#475569' }}
                        >
                          {status.label}
                        </Text>
                      </Pressable>
                    );
                  })}
                </View>
              </View>

              <View>
                <Text className="mb-1 text-xs font-semibold uppercase text-slate-500">Observações</Text>
                <TextInput
                  className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                  value={formEditarPagamento.observacoes}
                  onChangeText={(text) => setFormEditarPagamento((prev) => ({ ...prev, observacoes: text }))}
                  multiline
                  numberOfLines={2}
                />
              </View>
            </ScrollView>

            <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => setModalEditarPagamentoVisible(false)}
                disabled={salvandoPagamento}
                className="flex-1 items-center justify-center rounded-lg bg-slate-200 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }]}
              >
                <Text className="text-sm font-semibold text-slate-700">Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={handleSalvarEdicaoPagamento}
                disabled={salvandoPagamento}
                className="flex-1 items-center justify-center rounded-lg bg-orange-500 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }, salvandoPagamento && { opacity: 0.6 }]}
              >
                {salvandoPagamento ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text className="text-sm font-semibold text-white">Salvar alterações</Text>
                )}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal de Excluir Pagamento */}
      <Modal
        visible={modalExcluirPagamentoVisible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setModalExcluirPagamentoVisible(false)}
      >
        <View className="flex-1 items-center justify-center bg-black/50 px-4">
          <View className="w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <View className="items-center border-b border-slate-200 px-6 py-5">
              <View className="mb-3 h-14 w-14 items-center justify-center rounded-full bg-rose-100">
                <Feather name="trash-2" size={24} color="#dc2626" />
              </View>
              <Text className="text-lg font-bold text-slate-800">Excluir pagamento</Text>
              <Text className="text-sm text-slate-500">Esta ação não pode ser desfeita</Text>
            </View>

            <View className="px-6 py-5">
              <Text className="text-center text-sm text-slate-700">
                Confirma excluir o pagamento #{getPagamentoId(pagamentoExcluindo) || '-'}?
              </Text>
            </View>

            <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => setModalExcluirPagamentoVisible(false)}
                disabled={excluindoPagamento}
                className="flex-1 items-center justify-center rounded-lg bg-slate-200 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }]}
              >
                <Text className="text-sm font-semibold text-slate-700">Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={handleConfirmarExcluirPagamento}
                disabled={excluindoPagamento}
                className="flex-1 items-center justify-center rounded-lg bg-rose-600 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }, excluindoPagamento && { opacity: 0.6 }]}
              >
                {excluindoPagamento ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text className="text-sm font-semibold text-white">Excluir</Text>
                )}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal de Erro */}
      <Modal
        visible={errorModal.visible}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setErrorModal({ visible: false, title: '', message: '' })}
      >
        <View className="flex-1 items-center justify-center bg-black/50 px-4">
          <View className="w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <View className="items-center border-b border-slate-200 px-6 py-5">
              <View className="mb-3 h-14 w-14 items-center justify-center rounded-full bg-rose-100">
                <Feather name="alert-circle" size={28} color="#ef4444" />
              </View>
              <Text className="text-lg font-bold text-slate-800">{errorModal.title}</Text>
            </View>
            
            <View className="px-6 py-5">
              <Text className="text-center text-sm leading-6 text-slate-600">
                {errorModal.message}
              </Text>
            </View>

            <View className="border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => setErrorModal({ visible: false, title: '', message: '' })}
                className="items-center justify-center rounded-lg bg-slate-800 py-3"
                style={({ pressed }) => [pressed && { opacity: 0.8 }]}
              >
                <Text className="text-sm font-semibold text-white">Fechar</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Bloquear Matrícula */}
      <Modal
        visible={modalBloquearVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setModalBloquearVisible(false)}
      >
        <View className="flex-1 items-center justify-center bg-black/50 px-4">
          <View className="w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <View className="items-center border-b border-slate-200 px-6 py-5">
              <View className="mb-3 h-14 w-14 items-center justify-center rounded-full bg-violet-100">
                <Feather name="lock" size={24} color="#7c3aed" />
              </View>
              <Text className="text-lg font-bold text-slate-800">Bloquear matrícula</Text>
              <Text className="mt-1 text-center text-xs text-slate-500">
                O aluno não poderá fazer check-in nem usar o app. A matrícula permanece no sistema.
              </Text>
            </View>
            <View className="px-6 py-4">
              <Text className="mb-1 text-xs font-semibold text-slate-600">Motivo (opcional)</Text>
              <TextInput
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800"
                placeholder="Ex.: inadimplência, comportamento..."
                value={motivoBloqueio}
                onChangeText={setMotivoBloqueio}
                multiline
                numberOfLines={3}
              />
            </View>
            <View className="flex-row gap-3 border-t border-slate-200 px-6 py-4">
              <Pressable
                onPress={() => {
                  setModalBloquearVisible(false);
                  setMotivoBloqueio('');
                }}
                disabled={bloqueandoMatricula}
                className="flex-1 items-center justify-center rounded-lg bg-slate-200 py-3"
              >
                <Text className="text-sm font-semibold text-slate-700">Cancelar</Text>
              </Pressable>
              <Pressable
                onPress={handleConfirmarBloqueio}
                disabled={bloqueandoMatricula}
                className="flex-1 items-center justify-center rounded-lg bg-violet-600 py-3"
                style={bloqueandoMatricula && { opacity: 0.6 }}
              >
                {bloqueandoMatricula ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text className="text-sm font-semibold text-white">Confirmar bloqueio</Text>
                )}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </LayoutBase>
  );
}
