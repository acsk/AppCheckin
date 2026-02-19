import React, { useEffect, useMemo, useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Modal,
  ActivityIndicator,
  StyleSheet,
  useWindowDimensions,
  Switch,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import SearchableDropdown from '../../components/SearchableDropdown';
import ConfirmModal from '../../components/ConfirmModal';
import pacoteService from '../../services/pacoteService';
import planoService from '../../services/planoService';
import usuarioService from '../../services/usuarioService';
import alunoService from '../../services/alunoService';
import { showError, showSuccess } from '../../utils/toast';
import { mascaraDinheiro, removerMascaraDinheiro } from '../../utils/masks';

const INITIAL_PACOTE = {
  nome: '',
  descricao: '',
  valor_total: 'R$ 0,00',
  qtd_beneficiarios: '1',
  plano_id: '',
  plano_ciclo_id: '',
  ativo: true,
};

const INITIAL_CONTRATO = {
  pacote_id: '',
  pagante_usuario_id: '',
  beneficiarios: [],
};

const INITIAL_BENEFICIARIOS = {
  contrato_id: '',
  beneficiarios: [],
};

const INITIAL_CONFIRMAR = {
  contrato_id: '',
  pagamento_id: '',
};

export default function PacotesScreen() {
  const { width } = useWindowDimensions();
  const isMobile = width < 768;

  const [loading, setLoading] = useState(true);
  const [pacotes, setPacotes] = useState([]);
  const [contratosPendentes, setContratosPendentes] = useState([]);
  const [contratosAtivos, setContratosAtivos] = useState([]);
  const [searchText, setSearchText] = useState('');
  const [modalNovo, setModalNovo] = useState(false);
  const [modalContratar, setModalContratar] = useState(false);
  const [modalBeneficiarios, setModalBeneficiarios] = useState(false);
  const [modalConfirmar, setModalConfirmar] = useState(false);
  const [modalCalcular, setModalCalcular] = useState(false);
  const [pacoteSimular, setPacoteSimular] = useState(null);
  const [modalExcluir, setModalExcluir] = useState({ visible: false, pacote: null });
  const [isEditando, setIsEditando] = useState(false);
  const [pacoteEditandoId, setPacoteEditandoId] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const [carregandoDados, setCarregandoDados] = useState(false);
  const [tab, setTab] = useState('pacotes'); // pacotes | pendentes | ativos
  const [loadingContratos, setLoadingContratos] = useState(false);
  const [expandedContratos, setExpandedContratos] = useState({});
  const [gerandoMatriculasId, setGerandoMatriculasId] = useState(null);

  const [pacoteForm, setPacoteForm] = useState(INITIAL_PACOTE);
  const [contratoForm, setContratoForm] = useState(INITIAL_CONTRATO);
  const [beneficiariosForm, setBeneficiariosForm] = useState(INITIAL_BENEFICIARIOS);
  const [confirmarForm, setConfirmarForm] = useState(INITIAL_CONFIRMAR);

  const [planos, setPlanos] = useState([]);
  const [ciclos, setCiclos] = useState([]);
  const [usuarios, setUsuarios] = useState([]);
  const [alunos, setAlunos] = useState([]);

  useEffect(() => {
    carregarPacotes();
    carregarDadosBase();
    carregarContratos('pendente');
    carregarContratos('ativo');
  }, []);

  const carregarPacotes = async () => {
    try {
      setLoading(true);
      const response = await pacoteService.listar();
      const lista = Array.isArray(response) ? response : response.pacotes || response.data?.pacotes || [];
      setPacotes(lista);
    } catch (error) {
      console.error('Erro ao carregar pacotes:', error);
      showError(error.error || 'Não foi possível carregar os pacotes');
      setPacotes([]);
    } finally {
      setLoading(false);
    }
  };

  const carregarDadosBase = async () => {
    try {
      setCarregandoDados(true);
      const [planosResp, usuariosResp, alunosResp] = await Promise.all([
        planoService.listar(true),
        usuarioService.listar(true),
        alunoService.listarBasico(),
      ]);

      const listaPlanos = Array.isArray(planosResp) ? planosResp : planosResp.planos || [];
      const listaUsuarios = Array.isArray(usuariosResp) ? usuariosResp : usuariosResp.usuarios || [];
      const listaAlunos = Array.isArray(alunosResp) ? alunosResp : alunosResp.alunos || [];

      setPlanos(listaPlanos);
      setUsuarios(listaUsuarios);
      setAlunos(listaAlunos);
    } catch (error) {
      console.error('Erro ao carregar dados base:', error);
      showError('Erro ao carregar planos/usuários/alunos');
    } finally {
      setCarregandoDados(false);
    }
  };

  const carregarContratos = async (status) => {
    try {
      setLoadingContratos(true);
      const response = await pacoteService.listarContratos(status);
      const lista = Array.isArray(response) ? response : response.contratos || response.data?.contratos || [];
      if (status === 'pendente') {
        setContratosPendentes(lista);
      } else if (status === 'ativo') {
        setContratosAtivos(lista);
      }
    } catch (error) {
      console.error('Erro ao carregar contratos de pacote:', error);
      showError(error.error || 'Não foi possível carregar contratos');
    } finally {
      setLoadingContratos(false);
    }
  };

  const toggleContrato = (contratoId) => {
    setExpandedContratos((prev) => ({
      ...prev,
      [contratoId]: !prev[contratoId],
    }));
  };

  const handleGerarMatriculas = async (contratoId) => {
    try {
      setGerandoMatriculasId(contratoId);
      const response = await pacoteService.gerarMatriculas(contratoId);
      showSuccess(response.message || 'Matrículas geradas com sucesso');
      carregarContratos('ativo');
    } catch (error) {
      console.error('Erro ao gerar matrículas:', error);
      showError(error.error || error.mensagemLimpa || 'Não foi possível gerar as matrículas');
    } finally {
      setGerandoMatriculasId(null);
    }
  };

  const carregarCiclos = async (planoId) => {
    if (!planoId) {
      setCiclos([]);
      return;
    }

    try {
      const response = await planoService.listarCiclos(planoId);
      const lista = Array.isArray(response) ? response : response.ciclos || response.data?.ciclos || [];
      setCiclos(lista);
    } catch (error) {
      console.error('Erro ao carregar ciclos:', error);
      showError('Não foi possível carregar os ciclos');
      setCiclos([]);
    }
  };

  const pacotesFiltrados = useMemo(() => {
    if (!searchText) return pacotes;
    const termo = searchText.toLowerCase();
    return pacotes.filter((pacote) =>
      pacote.nome?.toLowerCase().includes(termo) ||
      pacote.descricao?.toLowerCase().includes(termo) ||
      String(pacote.qtd_beneficiarios || '').includes(termo)
    );
  }, [pacotes, searchText]);

  const pendentesFiltrados = useMemo(() => {
    if (!searchText) return contratosPendentes;
    const termo = searchText.toLowerCase();
    return contratosPendentes.filter((item) =>
      String(item.contrato_id || '').includes(termo) ||
      item.pacote_nome?.toLowerCase().includes(termo) ||
      item.pagante_nome?.toLowerCase().includes(termo)
    );
  }, [contratosPendentes, searchText]);

  const ativosFiltrados = useMemo(() => {
    if (!searchText) return contratosAtivos;
    const termo = searchText.toLowerCase();
    return contratosAtivos.filter((item) =>
      String(item.contrato?.contrato_id || '').includes(termo) ||
      item.contrato?.pacote_nome?.toLowerCase().includes(termo) ||
      item.contrato?.pagante_nome?.toLowerCase().includes(termo)
    );
  }, [contratosAtivos, searchText]);

  const formatCurrency = (value) => {
    if (value == null) return 'R$ 0,00';
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(Number(value) || 0);
  };

  const calcularResumoPacote = (pacote) => {
    const total = Number(pacote.valor_total) || 0;
    const qtdDependentes = Number(pacote.qtd_beneficiarios) || 1;
    const qtdPessoas = qtdDependentes + 1; // dependentes + pagante
    const porBenef = qtdPessoas > 0 ? total / qtdPessoas : total;
    const planoValor = Number(pacote.plano_valor || pacote.plano_preco || pacote.plano_valor_total) || 0;
    const referenciaPlano = planoValor > 0 ? planoValor * qtdPessoas : 0;
    const desconto = referenciaPlano > 0 ? ((1 - total / referenciaPlano) * 100) : null;

    return {
      porBenef,
      planoValor,
      referenciaPlano,
      desconto,
    };
  };

  const parseCurrency = (value) => {
    const numeros = removerMascaraDinheiro(value);
    if (!numeros) return 0;
    return Number(numeros) / 100;
  };

  const resetPacoteForm = () => {
    setPacoteForm(INITIAL_PACOTE);
    setCiclos([]);
    setIsEditando(false);
    setPacoteEditandoId(null);
  };

  const resetContratoForm = () => {
    setContratoForm(INITIAL_CONTRATO);
  };

  const resetBeneficiariosForm = () => {
    setBeneficiariosForm(INITIAL_BENEFICIARIOS);
  };

  const resetConfirmarForm = () => {
    setConfirmarForm(INITIAL_CONFIRMAR);
  };

  const calcularSimulacao = (dados) => {
    const total = dados?.valor_total != null ? Number(dados.valor_total) : parseCurrency(pacoteForm.valor_total);
    const qtdDependentes = Number(dados?.qtd_beneficiarios || pacoteForm.qtd_beneficiarios) || 1;
    const qtdPessoas = qtdDependentes + 1; // dependentes + pagante
    const porBenef = qtdPessoas > 0 ? total / qtdPessoas : total;
    const planoId = dados?.plano_id || pacoteForm.plano_id;
    const planoSelecionado = planos.find((plano) => String(plano.id) === String(planoId));
    const planoValor = Number(dados?.plano_valor || planoSelecionado?.valor) || 0;
    const referenciaPlano = planoValor > 0 ? planoValor * qtdPessoas : 0;
    const desconto = referenciaPlano > 0 ? ((1 - total / referenciaPlano) * 100) : null;

    return {
      total,
      qtd: qtdPessoas,
      porBenef,
      planoValor,
      referenciaPlano,
      desconto,
      planoNome: planoSelecionado?.nome || dados?.plano_nome || null,
    };
  };

  const handleSalvarPacote = async () => {
    if (!pacoteForm.nome?.trim()) {
      showError('Informe o nome do pacote');
      return;
    }
    if (!pacoteForm.plano_id) {
      showError('Selecione um plano');
      return;
    }
    if (!pacoteForm.plano_ciclo_id) {
      showError('Selecione um ciclo');
      return;
    }

    try {
      setSalvando(true);
      const payload = {
        nome: pacoteForm.nome.trim(),
        descricao: pacoteForm.descricao?.trim(),
        valor_total: parseCurrency(pacoteForm.valor_total),
        qtd_beneficiarios: Number(pacoteForm.qtd_beneficiarios) || 1,
        plano_id: Number(pacoteForm.plano_id),
        plano_ciclo_id: Number(pacoteForm.plano_ciclo_id),
        ativo: pacoteForm.ativo ? 1 : 0,
      };

      if (isEditando && pacoteEditandoId) {
        await pacoteService.atualizar(pacoteEditandoId, payload);
        showSuccess('Pacote atualizado com sucesso');
      } else {
        await pacoteService.criar(payload);
        showSuccess('Pacote criado com sucesso');
      }
      setModalNovo(false);
      resetPacoteForm();
      carregarPacotes();
    } catch (error) {
      console.error('Erro ao criar pacote:', error);
      showError(error.error || 'Não foi possível criar o pacote');
    } finally {
      setSalvando(false);
    }
  };

  const handleContratar = async () => {
    if (!contratoForm.pacote_id) {
      showError('Selecione o pacote');
      return;
    }
    if (!contratoForm.pagante_usuario_id) {
      showError('Selecione o pagante');
      return;
    }
    if (!contratoForm.beneficiarios.length) {
      showError('Selecione pelo menos 1 beneficiário');
      return;
    }
    const pacoteSelecionado = pacotes.find((item) => String(item.id) === String(contratoForm.pacote_id));
    if (pacoteSelecionado && contratoForm.beneficiarios.length > Number(pacoteSelecionado.qtd_beneficiarios)) {
      showError(`O pacote permite até ${pacoteSelecionado.qtd_beneficiarios} beneficiários`);
      return;
    }

    try {
      setSalvando(true);
      const payload = {
        pagante_usuario_id: Number(contratoForm.pagante_usuario_id),
        beneficiarios: contratoForm.beneficiarios.map((id) => Number(id)),
      };

      const response = await pacoteService.contratar(contratoForm.pacote_id, payload);
      showSuccess(response?.message || 'Contrato criado com sucesso');
      setModalContratar(false);
      resetContratoForm();
    } catch (error) {
      console.error('Erro ao contratar pacote:', error);
      showError(error.error || 'Não foi possível contratar o pacote');
    } finally {
      setSalvando(false);
    }
  };

  const handleAtualizarBeneficiarios = async () => {
    if (!beneficiariosForm.contrato_id) {
      showError('Informe o contrato');
      return;
    }
    if (!beneficiariosForm.beneficiarios.length) {
      showError('Selecione pelo menos 1 beneficiário');
      return;
    }

    try {
      setSalvando(true);
      await pacoteService.definirBeneficiarios(
        beneficiariosForm.contrato_id,
        beneficiariosForm.beneficiarios.map((id) => Number(id))
      );
      showSuccess('Beneficiários atualizados com sucesso');
      setModalBeneficiarios(false);
      resetBeneficiariosForm();
    } catch (error) {
      console.error('Erro ao atualizar beneficiários:', error);
      showError(error.error || 'Não foi possível atualizar os beneficiários');
    } finally {
      setSalvando(false);
    }
  };

  const handleConfirmarPagamento = async () => {
    if (!confirmarForm.contrato_id) {
      showError('Informe o contrato');
      return;
    }
    if (!confirmarForm.pagamento_id?.trim()) {
      showError('Informe o ID do pagamento');
      return;
    }

    try {
      setSalvando(true);
      await pacoteService.confirmarPagamento(confirmarForm.contrato_id, confirmarForm.pagamento_id.trim());
      showSuccess('Pagamento confirmado e matrículas ativadas');
      setModalConfirmar(false);
      resetConfirmarForm();
    } catch (error) {
      console.error('Erro ao confirmar pagamento:', error);
      showError(error.error || 'Não foi possível confirmar o pagamento');
    } finally {
      setSalvando(false);
    }
  };

  const adicionarBeneficiario = (id, setter, current) => {
    if (!id) return;
    const existe = current.some((item) => String(item) === String(id));
    if (existe) return;
    setter([...current, id]);
  };

  const removerBeneficiario = (id, setter, current) => {
    setter(current.filter((item) => String(item) !== String(id)));
  };

  const getPlanoPorId = (planoId) => planos.find((plano) => String(plano.id) === String(planoId));

  const abrirNovoPacote = () => {
    resetPacoteForm();
    setModalNovo(true);
  };

  const abrirEditarPacote = async (pacote) => {
    setIsEditando(true);
    setPacoteEditandoId(pacote.id);
    setPacoteForm({
      nome: pacote.nome || '',
      descricao: pacote.descricao || '',
      valor_total: formatCurrency(pacote.valor_total),
      qtd_beneficiarios: String(pacote.qtd_beneficiarios || 1),
      plano_id: pacote.plano_id || '',
      plano_ciclo_id: pacote.plano_ciclo_id || '',
      ativo: pacote.ativo ? true : false,
    });
    if (pacote.plano_id) {
      await carregarCiclos(pacote.plano_id);
    }
    setModalNovo(true);
  };

  const confirmarExcluirPacote = async () => {
    try {
      setSalvando(true);
      await pacoteService.excluir(modalExcluir.pacote.id);
      showSuccess('Pacote excluído com sucesso');
      setModalExcluir({ visible: false, pacote: null });
      carregarPacotes();
    } catch (error) {
      console.error('Erro ao excluir pacote:', error);
      showError(error.error || 'Não foi possível excluir o pacote');
    } finally {
      setSalvando(false);
    }
  };

  return (
    <LayoutBase title="Pacotes" subtitle="Gerenciar pacotes e contratos">
      <ScrollView className="flex-1">
        <View className="px-5 pt-5 pb-3">
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ gap: 8, paddingRight: 8 }}
          >
            <TouchableOpacity
              className={`rounded-full px-4 py-2 ${tab === 'pacotes' ? 'bg-orange-500' : 'bg-slate-100'}`}
              onPress={() => setTab('pacotes')}
            >
              <Text className={`text-xs font-semibold ${tab === 'pacotes' ? 'text-white' : 'text-slate-600'}`}>Pacotes</Text>
            </TouchableOpacity>
            <TouchableOpacity
              className={`rounded-full px-4 py-2 ${tab === 'pendentes' ? 'bg-orange-500' : 'bg-slate-100'}`}
              onPress={() => setTab('pendentes')}
            >
              <Text className={`text-xs font-semibold ${tab === 'pendentes' ? 'text-white' : 'text-slate-600'}`}>Contratos Pendentes</Text>
            </TouchableOpacity>
            <TouchableOpacity
              className={`rounded-full px-4 py-2 ${tab === 'ativos' ? 'bg-orange-500' : 'bg-slate-100'}`}
              onPress={() => setTab('ativos')}
            >
              <Text className={`text-xs font-semibold ${tab === 'ativos' ? 'text-white' : 'text-slate-600'}`}>Contratos Ativos</Text>
            </TouchableOpacity>
          </ScrollView>

          <View>
            <Text className="text-sm font-semibold text-slate-700 mb-1">Buscar pacotes</Text>
            <View className="flex-row items-center gap-2">
              <TextInput
                className="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                placeholder="Nome, descrição ou quantidade"
                value={searchText}
                onChangeText={setSearchText}
              />
            </View>
          </View>

          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            className="mt-3"
            contentContainerStyle={{ gap: 8, paddingRight: 8 }}
          >
            <TouchableOpacity
              className="flex-row items-center justify-center gap-2 rounded-lg bg-orange-500 px-4 py-2.5"
              onPress={abrirNovoPacote}
            >
              <Feather name="plus" size={16} color="#fff" />
              <Text className="text-sm font-semibold text-white">Novo Pacote</Text>
            </TouchableOpacity>
            <TouchableOpacity
              className="flex-row items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5"
              onPress={() => setModalContratar(true)}
            >
              <Feather name="shopping-cart" size={16} color="#f97316" />
              <Text className="text-sm font-semibold text-slate-700">Contratar</Text>
            </TouchableOpacity>
            <TouchableOpacity
              className="flex-row items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5"
              onPress={() => setModalBeneficiarios(true)}
            >
              <Feather name="users" size={16} color="#0ea5e9" />
              <Text className="text-sm font-semibold text-slate-700">Beneficiários</Text>
            </TouchableOpacity>
            <TouchableOpacity
              className="flex-row items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5"
              onPress={() => setModalConfirmar(true)}
            >
              <Feather name="check-circle" size={16} color="#10b981" />
              <Text className="text-sm font-semibold text-slate-700">Confirmar Pagamento</Text>
            </TouchableOpacity>
          </ScrollView>
        </View>

        <View className="px-5 pb-8">
          {tab === 'pacotes' && loading ? (
            <View className="items-center justify-center py-10">
              <ActivityIndicator size="large" color="#f97316" />
            </View>
          ) : tab === 'pacotes' && pacotesFiltrados.length === 0 ? (
            <View className="items-center rounded-xl border border-slate-200 bg-white py-12">
              <Feather name="archive" size={44} color="#cbd5f5" />
              <Text className="mt-3 text-sm font-semibold text-slate-600">Nenhum pacote encontrado</Text>
              <Text className="text-xs text-slate-400">Cadastre um novo pacote para começar</Text>
            </View>
          ) : tab === 'pacotes' && isMobile ? (
            <View className="gap-3">
              {pacotesFiltrados.map((pacote) => (
                <View key={pacote.id} className="rounded-xl border border-slate-200 bg-white p-4">
                  {(() => {
                    const planoSelecionado = getPlanoPorId(pacote.plano_id);
                    const pacoteComPlano = {
                      ...pacote,
                      plano_nome: pacote.plano_nome || planoSelecionado?.nome || null,
                      plano_valor: pacote.plano_valor || planoSelecionado?.valor || null,
                    };
                    const resumo = calcularResumoPacote(pacoteComPlano);
                    return (
                      <>
                        <View className="flex-row items-start justify-between gap-3">
                          <View className="flex-1">
                            <Text className="text-base font-semibold text-slate-800" numberOfLines={1}>
                              {pacote.nome}
                            </Text>
                            <Text className="text-xs text-slate-500" numberOfLines={2}>
                              {pacote.descricao || 'Sem descrição'}
                            </Text>
                          </View>
                          <View className="flex-row items-center gap-2">
                            <View className={`rounded-full px-3 py-1 ${pacote.ativo ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                              <Text className={`text-xs font-semibold ${pacote.ativo ? 'text-emerald-700' : 'text-rose-700'}`}>
                                {pacote.ativo ? 'Ativo' : 'Inativo'}
                              </Text>
                            </View>
                            <TouchableOpacity
                              className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white"
                              onPress={() => abrirEditarPacote(pacote)}
                            >
                              <Feather name="edit-2" size={16} color="#3b82f6" />
                            </TouchableOpacity>
                            <TouchableOpacity
                              className="h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white"
                              onPress={() => setModalExcluir({ visible: true, pacote })}
                            >
                              <Feather name="trash-2" size={16} color="#ef4444" />
                            </TouchableOpacity>
                          </View>
                        </View>
                        <View className="mt-3 flex-row flex-wrap gap-3">
                          <View className="rounded-lg bg-slate-50 px-3 py-2">
                            <Text className="text-[10px] font-semibold uppercase text-slate-400">Valor total</Text>
                            <Text className="text-sm font-semibold text-slate-700">{formatCurrency(pacote.valor_total)}</Text>
                          </View>
                          <View className="rounded-lg bg-slate-50 px-3 py-2">
                            <Text className="text-[10px] font-semibold uppercase text-slate-400">Beneficiários</Text>
                            <Text className="text-sm font-semibold text-slate-700">{pacote.qtd_beneficiarios}</Text>
                          </View>
                          <View className="rounded-lg bg-slate-50 px-3 py-2">
                            <Text className="text-[10px] font-semibold uppercase text-slate-400">Plano</Text>
                            <Text className="text-sm font-semibold text-slate-700" numberOfLines={1}>{pacoteComPlano.plano_nome || '-'}</Text>
                          </View>
                          <View className="rounded-lg bg-slate-50 px-3 py-2">
                            <Text className="text-[10px] font-semibold uppercase text-slate-400">Por beneficiário</Text>
                            <Text className="text-sm font-semibold text-slate-700">{formatCurrency(resumo.porBenef)}</Text>
                          </View>
                          {resumo.planoValor > 0 && (
                            <View className="rounded-lg bg-slate-50 px-3 py-2">
                              <Text className="text-[10px] font-semibold uppercase text-slate-400">Plano x {pacote.qtd_beneficiarios}</Text>
                              <Text className="text-sm font-semibold text-slate-700">{formatCurrency(resumo.referenciaPlano)}</Text>
                            </View>
                          )}
                          {resumo.desconto !== null && (
                            <View className="rounded-lg bg-emerald-50 px-3 py-2">
                              <Text className="text-[10px] font-semibold uppercase text-emerald-600">Desconto estimado</Text>
                              <Text className="text-sm font-semibold text-emerald-700">{resumo.desconto.toFixed(1)}%</Text>
                            </View>
                          )}
                        </View>
                        <View className="mt-3 flex-row justify-end">
                          <TouchableOpacity
                            className="flex-row items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2"
                            onPress={() => {
                              setPacoteSimular(pacoteComPlano);
                              setModalCalcular(true);
                            }}
                          >
                      <Feather name="cpu" size={14} color="#0f172a" />
                            <Text className="text-xs font-semibold text-slate-700">Calcular</Text>
                          </TouchableOpacity>
                        </View>
                      </>
                    );
                  })()}
                </View>
              ))}
            </View>
          ) : tab === 'pacotes' ? (
            <View className="rounded-xl border border-slate-200 bg-white overflow-hidden">
              <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-2">
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 220 }}>Pacote</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 120 }}>Valor</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 110 }}>Benef.</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 160 }}>Plano</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Por Benef.</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Plano x N</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Desconto</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 110 }}>Status</Text>
                <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 120, textAlign: 'right' }}>Ações</Text>
              </View>
              {pacotesFiltrados.map((pacote) => {
                const planoSelecionado = getPlanoPorId(pacote.plano_id);
                const pacoteComPlano = {
                  ...pacote,
                  plano_nome: pacote.plano_nome || planoSelecionado?.nome || null,
                  plano_valor: pacote.plano_valor || planoSelecionado?.valor || null,
                };
                const resumo = calcularResumoPacote(pacoteComPlano);

                return (
                  <View key={pacote.id} className="flex-row items-center border-b border-slate-100 px-4 py-2">
                    <View style={{ width: 220 }}>
                      <Text className="text-[12px] font-semibold text-slate-700" numberOfLines={1}>{pacote.nome}</Text>
                      <Text className="text-[10px] text-slate-400" numberOfLines={1}>{pacote.descricao || 'Sem descrição'}</Text>
                    </View>
                    <Text className="text-[12px] text-slate-600" style={{ width: 120 }}>{formatCurrency(pacote.valor_total)}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 110 }}>{pacote.qtd_beneficiarios}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 160 }} numberOfLines={1}>{pacoteComPlano.plano_nome || '-'}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{formatCurrency(resumo.porBenef)}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{resumo.referenciaPlano > 0 ? formatCurrency(resumo.referenciaPlano) : '-'}</Text>
                    <View style={{ width: 140 }}>
                      {resumo.desconto !== null ? (
                        <View className="self-start rounded-full bg-emerald-100 px-2.5 py-1">
                          <Text className="text-[11px] font-semibold text-emerald-700">{resumo.desconto.toFixed(1)}%</Text>
                        </View>
                      ) : (
                        <Text className="text-[12px] text-slate-400">-</Text>
                      )}
                    </View>
                    <View style={{ width: 110 }}>
                      <View className={`self-start rounded-full px-2.5 py-1 ${pacote.ativo ? 'bg-emerald-100' : 'bg-rose-100'}`}>
                        <Text className={`text-[11px] font-semibold ${pacote.ativo ? 'text-emerald-700' : 'text-rose-700'}`}>
                          {pacote.ativo ? 'Ativo' : 'Inativo'}
                        </Text>
                      </View>
                    </View>
                    <View style={{ width: 120 }} className="flex-row items-center justify-end gap-2">
                      <TouchableOpacity
                        className="h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white"
                        onPress={() => {
                          setPacoteSimular(pacoteComPlano);
                          setModalCalcular(true);
                        }}
                      >
                        <Feather name="cpu" size={14} color="#0f172a" />
                      </TouchableOpacity>
                      <TouchableOpacity
                        className="h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white"
                        onPress={() => abrirEditarPacote(pacote)}
                      >
                        <Feather name="edit-2" size={14} color="#3b82f6" />
                      </TouchableOpacity>
                      <TouchableOpacity
                        className="h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white"
                        onPress={() => setModalExcluir({ visible: true, pacote })}
                      >
                        <Feather name="trash-2" size={14} color="#ef4444" />
                      </TouchableOpacity>
                    </View>
                  </View>
                );
              })}
            </View>
          ) : tab === 'pendentes' ? (
            loadingContratos ? (
              <View className="items-center justify-center py-10">
                <ActivityIndicator size="large" color="#f97316" />
              </View>
            ) : pendentesFiltrados.length === 0 ? (
              <View className="items-center rounded-xl border border-slate-200 bg-white py-12">
                <Feather name="inbox" size={44} color="#cbd5f5" />
                <Text className="mt-3 text-sm font-semibold text-slate-600">Nenhum contrato pendente</Text>
                <Text className="text-xs text-slate-400">Aguardando novas contratações</Text>
              </View>
            ) : (
              <View className="rounded-xl border border-slate-200 bg-white overflow-hidden">
                <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-2">
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 110 }}>Contrato</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 200 }}>Pacote</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 200 }}>Pagante</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 120 }}>Benef.</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Valor</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Criado</Text>
                </View>
                {pendentesFiltrados.map((item) => (
                  <View key={item.contrato_id} className="flex-row items-center border-b border-slate-100 px-4 py-2">
                    <Text className="text-[12px] text-slate-600" style={{ width: 110 }}>#{item.contrato_id}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 200 }} numberOfLines={1}>{item.pacote_nome}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 200 }} numberOfLines={1}>{item.pagante_nome}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 120 }}>{item.beneficiarios_adicionados}/{item.qtd_beneficiarios}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{formatCurrency(item.valor_total)}</Text>
                    <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{item.created_at || '-'}</Text>
                  </View>
                ))}
              </View>
            )
          ) : (
            loadingContratos ? (
              <View className="items-center justify-center py-10">
                <ActivityIndicator size="large" color="#f97316" />
              </View>
            ) : ativosFiltrados.length === 0 ? (
              <View className="items-center rounded-xl border border-slate-200 bg-white py-12">
                <Feather name="inbox" size={44} color="#cbd5f5" />
                <Text className="mt-3 text-sm font-semibold text-slate-600">Nenhum contrato ativo</Text>
                <Text className="text-xs text-slate-400">Sem contratos ativos no momento</Text>
              </View>
            ) : (
              <View className="rounded-xl border border-slate-200 bg-white overflow-hidden">
                <View className="flex-row items-center border-b border-slate-200 bg-slate-50 px-4 py-2">
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 110 }}>Contrato</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 200 }}>Pacote</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 170 }}>Plano</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 200 }}>Pagante</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 120 }}>Pessoas</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Faltando</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Valor</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140 }}>Início</Text>
                  <Text className="text-[10px] font-bold uppercase tracking-widest text-slate-500" style={{ width: 140, textAlign: 'right' }}>Ações</Text>
                </View>
                {ativosFiltrados.map((item) => {
                  const contratoId = item.contrato?.contrato_id;
                  const isExpanded = expandedContratos[contratoId];
                  return (
                    <View key={contratoId} className="border-b border-slate-100">
                      <View className="flex-row items-center px-4 py-2">
                        <Text className="text-[12px] text-slate-600" style={{ width: 110 }}>#{contratoId}</Text>
                        <Text className="text-[12px] text-slate-600" style={{ width: 200 }} numberOfLines={1}>{item.contrato?.pacote_nome}</Text>
                        <Text className="text-[12px] text-slate-600" style={{ width: 170 }} numberOfLines={1}>{item.contrato?.plano_nome}</Text>
                        <Text className="text-[12px] text-slate-600" style={{ width: 200 }} numberOfLines={1}>{item.contrato?.pagante_nome}</Text>
                        <Text className="text-[12px] text-slate-600" style={{ width: 120 }}>{item.qtd_pessoas}</Text>
                        <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{item.qtd_matriculas_faltando}</Text>
                        <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{formatCurrency(item.contrato?.valor_total)}</Text>
                        <Text className="text-[12px] text-slate-600" style={{ width: 140 }}>{item.contrato?.data_inicio || '-'}</Text>
                        <View style={{ width: 140 }} className="flex-row items-center justify-end gap-2">
                          <TouchableOpacity
                            className="h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white"
                            onPress={() => toggleContrato(contratoId)}
                          >
                            <Feather name={isExpanded ? 'chevron-up' : 'chevron-down'} size={16} color="#0f172a" />
                          </TouchableOpacity>
                          <TouchableOpacity
                            className="h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-3"
                            onPress={() => handleGerarMatriculas(contratoId)}
                            disabled={gerandoMatriculasId === contratoId}
                          >
                            {gerandoMatriculasId === contratoId ? (
                              <ActivityIndicator size="small" color="#f97316" />
                            ) : (
                              <Text className="text-[11px] font-semibold text-slate-700">Gerar</Text>
                            )}
                          </TouchableOpacity>
                        </View>
                      </View>

                      {isExpanded && (
                        <View className="bg-slate-50/60 px-4 py-3">
                          <View className="flex-row gap-6">
                            <View className="flex-1">
                              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-2">Pagante</Text>
                              <Text className="text-[12px] text-slate-700">{item.pagante?.nome || item.contrato?.pagante_nome || '-'}</Text>
                            </View>
                            <View className="flex-1">
                              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-2">Beneficiários</Text>
                              {item.beneficiarios?.length ? (
                                item.beneficiarios.map((b) => (
                                  <Text key={b.beneficiario_id || b.aluno_id} className="text-[12px] text-slate-700">
                                    {b.nome || '-'}
                                  </Text>
                                ))
                              ) : (
                                <Text className="text-[12px] text-slate-400">Nenhum beneficiário</Text>
                              )}
                            </View>
                            <View className="flex-1">
                              <Text className="text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-2">Matrículas</Text>
                              {item.matriculas_geradas?.length ? (
                                item.matriculas_geradas.map((m) => (
                                  <Text key={m.matricula_id} className="text-[12px] text-slate-700">
                                    {m.aluno_nome || `Aluno ${m.aluno_id}`} • {m.status_codigo || '-'} • {m.data_vencimento || '-'}
                                  </Text>
                                ))
                              ) : (
                                <Text className="text-[12px] text-slate-400">Nenhuma matrícula gerada</Text>
                              )}
                            </View>
                          </View>
                        </View>
                      )}
                    </View>
                  );
                })}
              </View>
            )
          )}
        </View>
      </ScrollView>

      {/* Modal Novo Pacote */}
      <Modal visible={modalNovo} transparent animationType="fade" onRequestClose={() => setModalNovo(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{isEditando ? 'Editar Pacote' : 'Novo Pacote'}</Text>
              <TouchableOpacity onPress={() => setModalNovo(false)}>
                <Feather name="x" size={20} color="#64748b" />
              </TouchableOpacity>
            </View>
            <ScrollView style={{ maxHeight: 520 }}>
              <View style={styles.field}>
                <Text style={styles.label}>Nome *</Text>
                <TextInput
                  style={styles.input}
                  placeholder="Ex: Plano Família"
                  value={pacoteForm.nome}
                  onChangeText={(value) => setPacoteForm({ ...pacoteForm, nome: value })}
                />
              </View>
              <View style={styles.field}>
                <Text style={styles.label}>Descrição</Text>
                <TextInput
                  style={[styles.input, { height: 80 }]}
                  placeholder="Detalhes do pacote"
                  value={pacoteForm.descricao}
                  onChangeText={(value) => setPacoteForm({ ...pacoteForm, descricao: value })}
                  multiline
                />
              </View>
              <View style={styles.row}>
                <View style={[styles.field, { flex: 1 }]}> 
                  <Text style={styles.label}>Valor total *</Text>
                  <TextInput
                    style={styles.input}
                    keyboardType="numeric"
                    value={pacoteForm.valor_total}
                    onChangeText={(value) => setPacoteForm({ ...pacoteForm, valor_total: mascaraDinheiro(value) })}
                  />
                </View>
                <View style={[styles.field, { flex: 1 }]}>
                  <Text style={styles.label}>Qtd. Beneficiários *</Text>
                  <TextInput
                    style={styles.input}
                    keyboardType="numeric"
                    value={pacoteForm.qtd_beneficiarios}
                    onChangeText={(value) => setPacoteForm({ ...pacoteForm, qtd_beneficiarios: value.replace(/\D/g, '') })}
                  />
                </View>
              </View>
              <View style={styles.field}>
                <Text style={styles.label}>Plano *</Text>
                <SearchableDropdown
                  data={planos}
                  value={pacoteForm.plano_id}
                  onChange={(value) => {
                    setPacoteForm({ ...pacoteForm, plano_id: value, plano_ciclo_id: '' });
                    carregarCiclos(value);
                  }}
                  placeholder="Selecione um plano"
                  labelKey="nome"
                  valueKey="id"
                  renderSelected={(item) => (
                    <Text style={styles.dropdownSelectedText} numberOfLines={1}>
                      {item.nome} • {formatCurrency(item.valor)}
                    </Text>
                  )}
                  renderItem={(item, isSelected) => (
                    <View style={styles.dropdownItem}>
                      <Text style={[styles.dropdownItemTitle, isSelected && styles.dropdownItemTitleSelected]}>
                        {item.nome}
                      </Text>
                      <Text style={styles.dropdownItemSub}>
                        {formatCurrency(item.valor)}
                      </Text>
                    </View>
                  )}
                  disabled={carregandoDados}
                />
              </View>
              <View style={styles.field}>
                <Text style={styles.label}>Ciclo do Plano *</Text>
                <SearchableDropdown
                  data={ciclos}
                  value={pacoteForm.plano_ciclo_id}
                  onChange={(value) => setPacoteForm({ ...pacoteForm, plano_ciclo_id: value })}
                  placeholder="Selecione um ciclo"
                  labelKey="nome"
                  valueKey="id"
                  disabled={!pacoteForm.plano_id}
                />
              </View>
              <View style={[styles.row, { alignItems: 'center', justifyContent: 'space-between' }]}> 
                <Text style={styles.label}>Pacote ativo</Text>
                <Switch
                  value={pacoteForm.ativo}
                  onValueChange={(value) => setPacoteForm({ ...pacoteForm, ativo: value })}
                />
              </View>
            </ScrollView>
            {(() => {
              const resumo = calcularSimulacao(pacoteSimular);
              const economiaTotal = resumo.referenciaPlano > 0 ? resumo.referenciaPlano - resumo.total : 0;
              const economiaPorAluno = resumo.qtd > 0 ? economiaTotal / resumo.qtd : 0;
              return (
                <View style={styles.simResumo}>
                  <View style={styles.simResumoRow}>
                    <Text style={styles.simResumoLabel}>Desconto estimado</Text>
                    <Text style={styles.simResumoValue}>
                      {resumo.desconto !== null ? `${resumo.desconto.toFixed(1)}%` : '-'}
                    </Text>
                  </View>
                  <View style={styles.simResumoRow}>
                    <Text style={styles.simResumoLabel}>Mensalidade por aluno</Text>
                    <Text style={styles.simResumoValue}>{formatCurrency(resumo.porBenef)}</Text>
                  </View>
                  <View style={styles.simResumoRow}>
                    <Text style={styles.simResumoLabel}>Economia total</Text>
                    <Text style={styles.simResumoValue}>
                      {resumo.referenciaPlano > 0 ? formatCurrency(economiaTotal) : '-'}
                    </Text>
                  </View>
                  <View style={styles.simResumoRow}>
                    <Text style={styles.simResumoLabel}>Economia por aluno</Text>
                    <Text style={styles.simResumoValue}>
                      {resumo.referenciaPlano > 0 ? formatCurrency(economiaPorAluno) : '-'}
                    </Text>
                  </View>
                </View>
              );
            })()}
            <View style={styles.modalActions}>
              <TouchableOpacity style={[styles.secondaryButton, { flex: 1 }]} onPress={() => { setModalNovo(false); resetPacoteForm(); setPacoteSimular(null); }}>
                <Text style={styles.secondaryButtonText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.secondaryButton, { flex: 1 }]} onPress={() => { setPacoteSimular(null); setModalCalcular(true); }}>
                <Text style={styles.secondaryButtonText}>Calcular</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.primaryButton, { flex: 1 }]} onPress={handleSalvarPacote} disabled={salvando}>
                {salvando ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.primaryButtonText}>{isEditando ? 'Atualizar' : 'Salvar'}</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Calcular */}
      <Modal visible={modalCalcular} transparent animationType="fade" onRequestClose={() => setModalCalcular(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Simulação do Pacote</Text>
              <TouchableOpacity onPress={() => setModalCalcular(false)}>
                <Feather name="x" size={20} color="#64748b" />
              </TouchableOpacity>
            </View>
            {(() => {
              const resumo = calcularSimulacao(pacoteSimular);
              return (
                <View style={{ gap: 12 }}>
                  <View style={styles.simRow}>
                    <Text style={styles.simLabel}>Pacote</Text>
                    <Text style={styles.simValue}>{pacoteSimular?.nome || pacoteForm.nome || 'Novo pacote'}</Text>
                  </View>
                  <View style={styles.simRow}>
                    <Text style={styles.simLabel}>Valor total</Text>
                    <Text style={styles.simValue}>{formatCurrency(resumo.total)}</Text>
                  </View>
                  <View style={styles.simRow}>
                    <Text style={styles.simLabel}>Beneficiários</Text>
                    <Text style={styles.simValue}>{resumo.qtd}</Text>
                  </View>
                  <View style={styles.simRow}>
                    <Text style={styles.simLabel}>Por beneficiário</Text>
                    <Text style={styles.simValue}>{formatCurrency(resumo.porBenef)}</Text>
                  </View>
                  <View style={styles.simRow}>
                    <Text style={styles.simLabel}>Plano selecionado</Text>
                    <Text style={styles.simValue}>{resumo.planoNome || 'Selecione um plano'}</Text>
                  </View>
                  <View style={styles.simRow}>
                    <Text style={styles.simLabel}>Valor do plano</Text>
                    <Text style={styles.simValue}>{resumo.planoValor > 0 ? formatCurrency(resumo.planoValor) : 'Não informado'}</Text>
                  </View>
                  <View style={styles.simRow}>
                    <Text style={styles.simLabel}>Plano x beneficiários</Text>
                    <Text style={styles.simValue}>{resumo.referenciaPlano > 0 ? formatCurrency(resumo.referenciaPlano) : '-'}</Text>
                  </View>
                  <View style={[styles.simRow, resumo.desconto !== null ? styles.simRowHighlight : null]}>
                    <Text style={styles.simLabel}>Desconto estimado</Text>
                    <Text style={styles.simValue}>{resumo.desconto !== null ? `${resumo.desconto.toFixed(1)}%` : '-'}</Text>
                  </View>
                </View>
              );
            })()}
            <View style={styles.modalActions}>
              <TouchableOpacity style={[styles.primaryButton, { flex: 1 }]} onPress={() => { setModalCalcular(false); setPacoteSimular(null); }}>
                <Text style={styles.primaryButtonText}>Fechar</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <ConfirmModal
        visible={modalExcluir.visible}
        title="Excluir Pacote"
        message={`Confirma excluir o pacote \"${modalExcluir.pacote?.nome || ''}\"?`}
        confirmText="Excluir"
        cancelText="Cancelar"
        onCancel={() => setModalExcluir({ visible: false, pacote: null })}
        onConfirm={confirmarExcluirPacote}
        type="danger"
      />

      {/* Modal Contratar Pacote */}
      <Modal visible={modalContratar} transparent animationType="fade" onRequestClose={() => setModalContratar(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Contratar Pacote</Text>
              <TouchableOpacity onPress={() => setModalContratar(false)}>
                <Feather name="x" size={20} color="#64748b" />
              </TouchableOpacity>
            </View>
            <ScrollView style={{ maxHeight: 520 }}>
              <View style={styles.field}>
                <Text style={styles.label}>Pacote *</Text>
                <SearchableDropdown
                  data={pacotes}
                  value={contratoForm.pacote_id}
                  onChange={(value) => setContratoForm({ ...contratoForm, pacote_id: value })}
                  placeholder="Selecione um pacote"
                  labelKey="nome"
                  valueKey="id"
                />
              </View>
              <View style={styles.field}>
                <Text style={styles.label}>Pagante *</Text>
                <SearchableDropdown
                  data={usuarios}
                  value={contratoForm.pagante_usuario_id}
                  onChange={(value) => setContratoForm({ ...contratoForm, pagante_usuario_id: value })}
                  placeholder="Selecione o pagante"
                  labelKey="nome"
                  valueKey="id"
                />
              </View>
              <View style={styles.field}>
                <Text style={styles.label}>Adicionar Beneficiário *</Text>
                <SearchableDropdown
                  data={alunos}
                  value=""
                  onChange={(value) => adicionarBeneficiario(value, (list) => setContratoForm({ ...contratoForm, beneficiarios: list }), contratoForm.beneficiarios)}
                  placeholder="Buscar aluno"
                  labelKey="nome"
                  valueKey="id"
                />
                <View style={styles.beneficiariosList}>
                  {contratoForm.beneficiarios.length === 0 ? (
                    <Text style={styles.emptyInline}>Nenhum beneficiário adicionado</Text>
                  ) : (
                    contratoForm.beneficiarios.map((id) => {
                      const aluno = alunos.find((item) => String(item.id) === String(id));
                      return (
                        <View key={id} style={styles.beneficiarioItem}>
                          <Text style={styles.beneficiarioText}>{aluno?.nome || `Aluno #${id}`}</Text>
                          <TouchableOpacity
                            onPress={() => removerBeneficiario(id, (list) => setContratoForm({ ...contratoForm, beneficiarios: list }), contratoForm.beneficiarios)}
                          >
                            <Feather name="x" size={16} color="#ef4444" />
                          </TouchableOpacity>
                        </View>
                      );
                    })
                  )}
                </View>
              </View>
            </ScrollView>
            <View style={styles.modalActions}>
              <TouchableOpacity style={[styles.secondaryButton, { flex: 1 }]} onPress={() => { setModalContratar(false); resetContratoForm(); }}>
                <Text style={styles.secondaryButtonText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.primaryButton, { flex: 1 }]} onPress={handleContratar} disabled={salvando}>
                {salvando ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.primaryButtonText}>Contratar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Beneficiários */}
      <Modal visible={modalBeneficiarios} transparent animationType="fade" onRequestClose={() => setModalBeneficiarios(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Definir Beneficiários</Text>
              <TouchableOpacity onPress={() => setModalBeneficiarios(false)}>
                <Feather name="x" size={20} color="#64748b" />
              </TouchableOpacity>
            </View>
            <ScrollView style={{ maxHeight: 480 }}>
              <View style={styles.field}>
                <Text style={styles.label}>Contrato *</Text>
                <TextInput
                  style={styles.input}
                  placeholder="ID do contrato"
                  keyboardType="numeric"
                  value={String(beneficiariosForm.contrato_id || '')}
                  onChangeText={(value) => setBeneficiariosForm({ ...beneficiariosForm, contrato_id: value.replace(/\D/g, '') })}
                />
              </View>
              <View style={styles.field}>
                <Text style={styles.label}>Adicionar Beneficiário *</Text>
                <SearchableDropdown
                  data={alunos}
                  value=""
                  onChange={(value) => adicionarBeneficiario(value, (list) => setBeneficiariosForm({ ...beneficiariosForm, beneficiarios: list }), beneficiariosForm.beneficiarios)}
                  placeholder="Buscar aluno"
                  labelKey="nome"
                  valueKey="id"
                />
                <View style={styles.beneficiariosList}>
                  {beneficiariosForm.beneficiarios.length === 0 ? (
                    <Text style={styles.emptyInline}>Nenhum beneficiário adicionado</Text>
                  ) : (
                    beneficiariosForm.beneficiarios.map((id) => {
                      const aluno = alunos.find((item) => String(item.id) === String(id));
                      return (
                        <View key={id} style={styles.beneficiarioItem}>
                          <Text style={styles.beneficiarioText}>{aluno?.nome || `Aluno #${id}`}</Text>
                          <TouchableOpacity
                            onPress={() => removerBeneficiario(id, (list) => setBeneficiariosForm({ ...beneficiariosForm, beneficiarios: list }), beneficiariosForm.beneficiarios)}
                          >
                            <Feather name="x" size={16} color="#ef4444" />
                          </TouchableOpacity>
                        </View>
                      );
                    })
                  )}
                </View>
              </View>
            </ScrollView>
            <View style={styles.modalActions}>
              <TouchableOpacity style={[styles.secondaryButton, { flex: 1 }]} onPress={() => { setModalBeneficiarios(false); resetBeneficiariosForm(); }}>
                <Text style={styles.secondaryButtonText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.primaryButton, { flex: 1 }]} onPress={handleAtualizarBeneficiarios} disabled={salvando}>
                {salvando ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.primaryButtonText}>Salvar</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Confirmar Pagamento */}
      <Modal visible={modalConfirmar} transparent animationType="fade" onRequestClose={() => setModalConfirmar(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Confirmar Pagamento</Text>
              <TouchableOpacity onPress={() => setModalConfirmar(false)}>
                <Feather name="x" size={20} color="#64748b" />
              </TouchableOpacity>
            </View>
            <View style={styles.field}>
              <Text style={styles.label}>Contrato *</Text>
              <TextInput
                style={styles.input}
                placeholder="ID do contrato"
                keyboardType="numeric"
                value={String(confirmarForm.contrato_id || '')}
                onChangeText={(value) => setConfirmarForm({ ...confirmarForm, contrato_id: value.replace(/\D/g, '') })}
              />
            </View>
            <View style={styles.field}>
              <Text style={styles.label}>ID Pagamento *</Text>
              <TextInput
                style={styles.input}
                placeholder="Ex: MP-123456"
                value={confirmarForm.pagamento_id}
                onChangeText={(value) => setConfirmarForm({ ...confirmarForm, pagamento_id: value })}
              />
            </View>
            <View style={styles.modalActions}>
              <TouchableOpacity style={[styles.secondaryButton, { flex: 1 }]} onPress={() => { setModalConfirmar(false); resetConfirmarForm(); }}>
                <Text style={styles.secondaryButtonText}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.primaryButton, { flex: 1 }]} onPress={handleConfirmarPagamento} disabled={salvando}>
                {salvando ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.primaryButtonText}>Confirmar</Text>
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
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContainer: {
    width: '100%',
    maxWidth: 520,
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 18,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  modalTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#0f172a',
  },
  field: {
    marginBottom: 14,
  },
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: '#64748b',
    marginBottom: 6,
  },
  input: {
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#0f172a',
    backgroundColor: '#ffffff',
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  modalActions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  primaryButton: {
    backgroundColor: '#f97316',
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
  },
  primaryButtonText: {
    color: '#ffffff',
    fontWeight: '700',
    fontSize: 14,
  },
  secondaryButton: {
    backgroundColor: '#f1f5f9',
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
  },
  secondaryButtonText: {
    color: '#475569',
    fontWeight: '700',
    fontSize: 14,
  },
  beneficiariosList: {
    marginTop: 10,
    gap: 8,
  },
  beneficiarioItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 10,
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  beneficiarioText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#334155',
  },
  emptyInline: {
    fontSize: 12,
    color: '#94a3b8',
  },
  simRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 10,
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  simRowHighlight: {
    backgroundColor: '#ecfdf3',
    borderColor: '#86efac',
  },
  simLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#64748b',
  },
  simValue: {
    fontSize: 13,
    fontWeight: '700',
    color: '#0f172a',
  },
  simResumo: {
    marginTop: 8,
    padding: 12,
    borderRadius: 12,
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    gap: 8,
  },
  simResumoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  simResumoLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#64748b',
  },
  simResumoValue: {
    fontSize: 13,
    fontWeight: '700',
    color: '#0f172a',
  },
  dropdownItem: {
    paddingVertical: 6,
  },
  dropdownItemTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#0f172a',
  },
  dropdownItemTitleSelected: {
    color: '#0f172a',
  },
  dropdownItemSub: {
    fontSize: 12,
    color: '#64748b',
  },
  dropdownSelectedText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#0f172a',
  },
});
