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
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { useRouter, useLocalSearchParams } from 'expo-router';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import { showSuccess, showError } from '../../utils/toast';

// Lista de ícones disponíveis (MaterialCommunityIcons)
const ICONES_DISPONIVEIS = [
  // Musculação e Força
  { value: 'dumbbell', label: 'Musculação' },
  { value: 'weight-lifter', label: 'Levantamento de Peso' },
  { value: 'arm-flex', label: 'Fitness' },
  { value: 'weight', label: 'Peso' },
  { value: 'barbell', label: 'Barra' },
  
  // Cardio e Corrida
  { value: 'run', label: 'Corrida' },
  { value: 'run-fast', label: 'Sprint' },
  { value: 'walk', label: 'Caminhada' },
  { value: 'heart-pulse', label: 'Cardio' },
  { value: 'jump-rope', label: 'Pular Corda' },
  
  // Bicicleta e Spinning
  { value: 'bike', label: 'Ciclismo' },
  { value: 'bicycle', label: 'Bicicleta' },
  
  // Lutas e Artes Marciais
  { value: 'boxing-glove', label: 'Boxe' },
  { value: 'karate', label: 'Artes Marciais' },
  { value: 'kabaddi', label: 'Luta' },
  { value: 'fencing', label: 'Esgrima' },
  
  // Flexibilidade e Bem-estar
  { value: 'yoga', label: 'Yoga' },
  { value: 'meditation', label: 'Meditação' },
  { value: 'human-handsup', label: 'Alongamento' },
  { value: 'spa', label: 'Relaxamento' },
  
  // Aquáticos
  { value: 'swim', label: 'Natação' },
  { value: 'waves', label: 'Hidroginástica' },
  { value: 'pool', label: 'Piscina' },
  
  // Esportes com Bola
  { value: 'tennis', label: 'Tênis' },
  { value: 'tennis-ball', label: 'Tênis de Mesa' },
  { value: 'soccer', label: 'Futebol' },
  { value: 'basketball', label: 'Basquete' },
  { value: 'volleyball', label: 'Vôlei' },
  { value: 'handball', label: 'Handebol' },
  { value: 'golf', label: 'Golfe' },
  { value: 'baseball-bat', label: 'Baseball' },
  { value: 'rugby', label: 'Rugby' },
  { value: 'hockey-sticks', label: 'Hockey' },
  { value: 'badminton', label: 'Badminton' },
  
  // Dança e Ritmo
  { value: 'dance-ballroom', label: 'Dança' },
  { value: 'music', label: 'Ritmos' },
  { value: 'dance-pole', label: 'Pole Dance' },
  
  // Ginástica e Acrobacia
  { value: 'gymnastics', label: 'Ginástica' },
  { value: 'human-greeting-variant', label: 'Calistenia' },
  { value: 'human', label: 'Corpo' },
  
  // Outdoor e Aventura
  { value: 'hiking', label: 'Trilha' },
  { value: 'climbing', label: 'Escalada' },
  { value: 'skateboard', label: 'Skate' },
  { value: 'roller-skate', label: 'Patins' },
  { value: 'ski', label: 'Esqui' },
  { value: 'snowboard', label: 'Snowboard' },
  { value: 'sail-boat', label: 'Vela' },
  { value: 'rowing', label: 'Remo' },
  { value: 'paragliding', label: 'Parapente' },
  
  // Equipamentos
  { value: 'whistle', label: 'Treino' },
  { value: 'timer-outline', label: 'HIIT' },
  { value: 'target', label: 'Tiro' },
  { value: 'bow-arrow', label: 'Arco e Flecha' },
  
  // Genéricos
  { value: 'trophy', label: 'Competição' },
  { value: 'medal', label: 'Performance' },
  { value: 'fire', label: 'Intenso' },
  { value: 'lightning-bolt', label: 'Power' },
  { value: 'star', label: 'Especial' },
  { value: 'account-group', label: 'Grupo' },
  { value: 'account', label: 'Personal' },
  { value: 'bullseye-arrow', label: 'Objetivo' },
];

export default function FormModalidadeScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const modalidadeId = id ? parseInt(id) : null;
  const isEdit = !!modalidadeId && id !== 'novo';
  
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState({
    nome: '',
    descricao: '',
    cor: '#f97316',
    icone: 'dumbbell',
    ativo: true,
  });
  const [planos, setPlanos] = useState([]);
  const [errors, setErrors] = useState({});
  
  // Estados do Modal de Plano
  const [modalVisible, setModalVisible] = useState(false);
  const [planoEditando, setPlanoEditando] = useState(null);
  const [planoEditandoIndex, setPlanoEditandoIndex] = useState(null);
  const [planoForm, setPlanoForm] = useState({
    nome: '',
    checkins_semanais: '',
    valor: '',
    duracao_dias: 30,
    ativo: true,
    atual: true,
  });

  useEffect(() => {
    if (isEdit) {
      loadModalidade();
    }
  }, []);

  const loadModalidade = async () => {
    try {
      setLoading(true);
      const response = await modalidadeService.buscar(modalidadeId);
      const modalidade = response.modalidade;
      
      setFormData({
        nome: modalidade.nome || '',
        descricao: modalidade.descricao || '',
        cor: modalidade.cor || '#f97316',
        icone: modalidade.icone || 'activity',
        ativo: modalidade.ativo === 1,
      });

      // Carregar planos da modalidade
      if (modalidade.planos && modalidade.planos.length > 0) {
        const planosFormatados = modalidade.planos.map(p => ({
          id: p.id,
          nome: p.nome || '',
          checkins_semanais: p.checkins_semanais ? String(p.checkins_semanais) : '',
          valor: p.valor ? p.valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '',
          duracao_dias: p.duracao_dias || 30,
          ativo: p.ativo === 1 || p.ativo === true,
          atual: p.atual === 1 || p.atual === true,
        }));
        setPlanos(planosFormatados);
      }
    } catch (error) {
      console.error('❌ Erro ao carregar modalidade:', error);
      showError(error.message || 'Não foi possível carregar os dados da modalidade');
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

  const handlePlanoChange = (index, field, value) => {
    const novosPlanos = [...planos];
    novosPlanos[index][field] = value;
    setPlanos(novosPlanos);
    
    // Limpar erro do campo específico quando o usuário começar a digitar
    const errorKey = `plano_${index}_${field === 'checkins_semanais' ? 'checkins' : field}`;
    if (errors[errorKey]) {
      const newErrors = { ...errors };
      delete newErrors[errorKey];
      setErrors(newErrors);
    }
  };

  const adicionarPlano = () => {
    setPlanos([...planos, { nome: '', checkins_semanais: '', valor: '', duracao_dias: 30, ativo: true, atual: true }]);
  };

  const removerPlano = (index) => {
    if (planos.length > 1) {
      const novosPlanos = planos.filter((_, i) => i !== index);
      setPlanos(novosPlanos);
    }
  };

  // Funções do Modal de Plano
  const abrirModalNovoPlano = () => {
    setPlanoEditando(null);
    setPlanoEditandoIndex(null);
    setPlanoForm({
      nome: '',
      checkins_semanais: '',
      valor: '',
      duracao_dias: 30,
      ativo: true,
      atual: true,
    });
    setModalVisible(true);
  };

  const abrirModalEditarPlano = (plano, index) => {
    setPlanoEditando(plano);
    setPlanoEditandoIndex(index);
    setPlanoForm({
      ...plano,
      checkins_semanais: String(plano.checkins_semanais),
      valor: plano.valor || '',
    });
    setModalVisible(true);
  };

  const fecharModal = () => {
    setModalVisible(false);
    setPlanoEditando(null);
    setPlanoEditandoIndex(null);
    setPlanoForm({
      nome: '',
      checkins_semanais: '',
      valor: '',
      duracao_dias: 30,
      ativo: true,
      atual: true,
    });
  };

  const handlePlanoFormChange = (field, value) => {
    setPlanoForm({ ...planoForm, [field]: value });
  };

  const salvarPlano = () => {
    // Validar campos obrigatórios
    if (!planoForm.nome?.trim()) {
      showError('Nome do plano é obrigatório');
      return;
    }
    if (!planoForm.checkins_semanais) {
      showError('Checkins semanais é obrigatório');
      return;
    }
    if (!planoForm.valor) {
      showError('Valor é obrigatório');
      return;
    }

    if (planoEditandoIndex !== null) {
      // Editando plano existente
      const novosPlanos = [...planos];
      novosPlanos[planoEditandoIndex] = {
        ...planoForm,
        id: planoEditando?.id,
      };
      setPlanos(novosPlanos);
      showSuccess('Plano atualizado');
    } else {
      // Adicionando novo plano
      setPlanos([...planos, planoForm]);
      showSuccess('Plano adicionado');
    }
    fecharModal();
  };

  const formatarDuracao = (dias) => {
    if (dias === 30) return '30 dias';
    if (dias === 90) return '90 dias';
    if (dias === 180) return '180 dias';
    if (dias === 365) return '365 dias';
    return `${dias} dias`;
  };

  const formatarValorExibicao = (valor) => {
    if (!valor) return 'R$ 0,00';
    if (typeof valor === 'string' && valor.includes(',')) {
      return `R$ ${valor}`;
    }
    return `R$ ${parseFloat(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  const formatValorMonetario = (value) => {
    // Remove tudo que não é dígito
    const cleaned = value.replace(/\D/g, '');
    
    if (cleaned === '') return '';
    
    // Converte para número e formata
    const number = parseFloat(cleaned) / 100;
    return number.toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  };

  const validate = () => {
    const newErrors = {};

    if (!formData.nome?.trim()) {
      newErrors.nome = 'Nome é obrigatório';
    }

    // Validar planos (sempre obrigatório ter pelo menos 1 plano)
    if (planos.length === 0) {
      newErrors.planos = 'Adicione pelo menos um plano';
    } else {
      planos.forEach((plano, index) => {
        if (!plano.nome?.trim()) {
          newErrors[`plano_${index}_nome`] = `Nome do plano ${index + 1} é obrigatório`;
        }
        if (!plano.checkins_semanais) {
          newErrors[`plano_${index}_checkins`] = `Checkins semanais do plano ${index + 1} é obrigatório`;
        }
        if (!plano.valor) {
          newErrors[`plano_${index}_valor`] = `Valor do plano ${index + 1} é obrigatório`;
        }
      });
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validate()) {
      const errorCount = Object.keys(errors).length;
      showError(`Por favor, corrija ${errorCount === 1 ? 'o erro destacado' : `os ${errorCount} erros destacados`} no formulário`);
      return;
    }

    try {
      setSaving(true);

      const dados = {
        nome: formData.nome.trim(),
        descricao: formData.descricao.trim(),
        cor: formData.cor,
        icone: formData.icone,
        ativo: formData.ativo ? 1 : 0,
      };

      // Formatar planos (tanto para criar quanto para editar)
      const planosFormatados = planos.map((plano) => {
        return {
          id: plano.id || null,
          nome: plano.nome.trim(),
          checkins_semanais: parseInt(plano.checkins_semanais),
          valor: parseFloat(plano.valor.replace(/\D/g, '')) / 100,
          duracao_dias: plano.duracao_dias,
          ativo: plano.ativo === true ? 1 : 0,
          atual: plano.atual === true ? 1 : 0,
        };
      });

      dados.planos = planosFormatados;

      if (isEdit) {
        await modalidadeService.atualizar(modalidadeId, dados);
        showSuccess('Modalidade e planos atualizados com sucesso');
      } else {
        await modalidadeService.criar(dados);
        showSuccess('Modalidade e planos criados com sucesso');
      }

      // Pequeno delay para garantir que o toast seja exibido antes do redirecionamento
      setTimeout(() => {
        router.push('/modalidades');
      }, 500);
    } catch (error) {
      console.error('❌ Erro ao salvar modalidade:', error);
      showError(error.message || `Erro ao ${isEdit ? 'atualizar' : 'criar'} modalidade`);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <LayoutBase showSidebar showHeader>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase
      showSidebar
      showHeader
      title={isEdit ? 'Editar Modalidade' : 'Nova Modalidade'}
      subtitle="Preencha os campos obrigatórios"
    >
      <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
        <View style={styles.headerActions}>
          <TouchableOpacity style={styles.backButton} onPress={() => router.back()}>
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>

          <View style={[styles.statusIndicator, formData.ativo ? styles.statusActive : styles.statusInactive]}>
            <View style={[styles.statusDot, formData.ativo ? styles.statusDotActive : styles.statusDotInactive]} />
            <Text style={[styles.statusIndicatorText, formData.ativo ? styles.statusTextActive : styles.statusTextInactive]}>
              {formData.ativo ? 'Modalidade Ativa' : 'Modalidade Inativa'}
            </Text>
          </View>
        </View>

        <View style={styles.formContainer}>
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="activity" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Dados da Modalidade</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.topSection}>
                <View style={styles.topSectionLeft}>
                  <View style={styles.inputGroup}>
                    <Text style={styles.label}>Nome <Text style={styles.required}>*</Text></Text>
                    <TextInput
                      style={[styles.input, errors.nome && styles.inputError]}
                      placeholder="Ex: Musculação"
                      placeholderTextColor="#999"
                      value={formData.nome}
                      onChangeText={(value) => handleChange('nome', value)}
                    />
                    {errors.nome && <Text style={styles.errorText}>{errors.nome}</Text>}
                  </View>

                  <View style={[styles.inputGroup, { marginBottom: 0 }]}>
                    <Text style={styles.label}>Descrição</Text>
                    <TextInput
                      style={[styles.input, styles.textAreaCompact]}
                      placeholder="Descreva a modalidade..."
                      placeholderTextColor="#999"
                      value={formData.descricao}
                      onChangeText={(value) => handleChange('descricao', value)}
                      multiline
                      numberOfLines={2}
                      textAlignVertical="top"
                    />
                  </View>
                </View>

                <View style={styles.topSectionRight}>
                  <Text style={styles.previewLabel}>Prévia</Text>
                  <View style={styles.previewCard}>
                    <View style={[styles.previewIcon, { backgroundColor: formData.cor }]}>
                      <MaterialCommunityIcons name={formData.icone} size={32} color="#fff" />
                    </View>
                    <Text style={styles.previewName}>{formData.nome || 'Nome da Modalidade'}</Text>
                  </View>
                </View>
              </View>
            </View>
          </View>

          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="image" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Aparência</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.appearanceRow}>
                <View style={styles.appearanceCol}>
                  <Text style={styles.labelSmall}>Ícone</Text>
                  <View style={styles.iconesGrid}>
                    {ICONES_DISPONIVEIS.map((icone) => (
                      <TouchableOpacity
                        key={icone.value}
                        style={[
                          styles.iconeButton,
                          formData.icone === icone.value && styles.iconeButtonSelected,
                          formData.icone === icone.value && { borderColor: formData.cor },
                        ]}
                        onPress={() => handleChange('icone', icone.value)}
                        activeOpacity={0.7}
                      >
                        <MaterialCommunityIcons
                          name={icone.value}
                          size={20}
                          color={formData.icone === icone.value ? formData.cor : '#64748b'}
                        />
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>

                <View style={styles.appearanceDivider} />

                <View style={styles.appearanceColSmall}>
                  <Text style={styles.labelSmall}>Cor</Text>
                  <View style={styles.coresGrid}>
                    {['#f97316', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444', '#f59e0b', '#06b6d4', '#ec4899'].map((cor) => (
                      <TouchableOpacity
                        key={cor}
                        style={[
                          styles.corButtonSmall,
                          { backgroundColor: cor },
                          formData.cor === cor && styles.corButtonSmallSelected,
                        ]}
                        onPress={() => handleChange('cor', cor)}
                        activeOpacity={0.7}
                      >
                        {formData.cor === cor && <Feather name="check" size={14} color="#fff" />}
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>
              </View>
            </View>
          </View>

          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="list" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Planos da Modalidade</Text>
              <TouchableOpacity 
                style={styles.addPlanoHeaderButton}
                onPress={abrirModalNovoPlano}
                activeOpacity={0.7}
              >
                <Feather name="plus" size={16} color="#fff" />
                <Text style={styles.addPlanoHeaderButtonText}>Novo Plano</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.cardBody}>
              <Text style={styles.planosSectionSubtitle}>
                {isEdit ? 'Gerencie os planos baseados em checkins semanais' : 'Crie os planos baseados em checkins semanais'}
              </Text>
              <View style={styles.tabelaContainer}>
                {planos.length === 0 ? (
                  <View style={styles.emptyPlanos}>
                    <Feather name="inbox" size={40} color="#cbd5e1" />
                    <Text style={styles.emptyPlanosText}>Nenhum plano cadastrado</Text>
                    <Text style={styles.emptyPlanosSubtext}>Clique em "Novo Plano" para adicionar</Text>
                  </View>
                ) : (
                  <>
                    <View style={styles.tabelaHeader}>
                      <Text style={[styles.tabelaHeaderCell, styles.cellNome]}>Nome</Text>
                      <Text style={[styles.tabelaHeaderCell, styles.cellCheckins]}>Checkins</Text>
                      <Text style={[styles.tabelaHeaderCell, styles.cellValor]}>Valor</Text>
                      <Text style={[styles.tabelaHeaderCell, styles.cellDuracao]}>Duração</Text>
                      <Text style={[styles.tabelaHeaderCell, styles.cellStatus]}>Status</Text>
                      <Text style={[styles.tabelaHeaderCell, styles.cellAcoes]}>Ações</Text>
                    </View>

                    {planos.map((plano, index) => (
                      <View key={index} style={[styles.tabelaRow, index % 2 === 0 && styles.tabelaRowAlt]}>
                        <Text style={[styles.tabelaCell, styles.cellNome]} numberOfLines={1}>{plano.nome || '-'}</Text>
                        <Text style={[styles.tabelaCell, styles.cellCheckins]}>
                          {plano.checkins_semanais === '999' || plano.checkins_semanais === 999 ? 'Ilimitado' : `${plano.checkins_semanais}/sem`}
                        </Text>
                        <Text style={[styles.tabelaCell, styles.cellValor]}>{formatarValorExibicao(plano.valor)}</Text>
                        <Text style={[styles.tabelaCell, styles.cellDuracao]}>{formatarDuracao(plano.duracao_dias)}</Text>
                        <View style={[styles.tabelaCell, styles.cellStatus]}>
                          <View style={styles.statusBadges}>
                            <View style={[styles.statusBadge, plano.ativo ? styles.statusAtivo : styles.statusInativo]}>
                              <Text style={[styles.statusBadgeText, plano.ativo ? styles.statusAtivoText : styles.statusInativoText]}>
                                {plano.ativo ? 'Ativo' : 'Inativo'}
                              </Text>
                            </View>
                            {plano.atual && (
                              <View style={[styles.statusBadge, styles.statusDisponivel]}>
                                <Text style={styles.statusDisponivelText}>Disponível</Text>
                              </View>
                            )}
                          </View>
                        </View>
                        <View style={[styles.tabelaCell, styles.cellAcoes]}>
                          <TouchableOpacity 
                            style={styles.actionButton}
                            onPress={() => abrirModalEditarPlano(plano, index)}
                          >
                            <Feather name="edit-2" size={16} color="#3b82f6" />
                          </TouchableOpacity>
                          {planos.length > 1 && (
                            <TouchableOpacity 
                              style={styles.actionButton}
                              onPress={() => removerPlano(index)}
                            >
                              <Feather name="trash-2" size={16} color="#ef4444" />
                            </TouchableOpacity>
                          )}
                        </View>
                      </View>
                    ))}
                  </>
                )}
              </View>
            </View>
          </View>

            {/* Modal de Plano */}
            <Modal
              animationType="fade"
              transparent={true}
              visible={modalVisible}
              onRequestClose={fecharModal}
            >
              <Pressable style={styles.modalOverlay} onPress={fecharModal}>
                <Pressable style={styles.modalContent} onPress={(e) => e.stopPropagation()}>
                  <View style={styles.modalHeader}>
                    <Text style={styles.modalTitle}>
                      {planoEditandoIndex !== null ? 'Editar Plano' : 'Novo Plano'}
                    </Text>
                    <TouchableOpacity onPress={fecharModal} style={styles.modalCloseButton}>
                      <Feather name="x" size={24} color="#64748b" />
                    </TouchableOpacity>
                  </View>

                  <ScrollView style={styles.modalBody} showsVerticalScrollIndicator={false}>
                    {/* Nome do Plano */}
                    <View style={styles.inputGroup}>
                      <Text style={styles.label}>Nome do Plano *</Text>
                      <TextInput
                        style={styles.input}
                        placeholder="Ex: 2x por semana"
                        value={planoForm.nome}
                        onChangeText={(value) => handlePlanoFormChange('nome', value)}
                      />
                    </View>

                    {/* Checkins e Valor */}
                    <View style={styles.modalRow}>
                      <View style={[styles.inputGroup, styles.modalInputHalf]}>
                        <Text style={styles.label}>Checkins Semanais *</Text>
                        <TextInput
                          style={styles.input}
                          placeholder="Ex: 3"
                          value={planoForm.checkins_semanais}
                          onChangeText={(value) => handlePlanoFormChange('checkins_semanais', value.replace(/\D/g, ''))}
                          keyboardType="numeric"
                        />
                        <Text style={styles.helperText}>999 = ilimitado</Text>
                      </View>

                      <View style={[styles.inputGroup, styles.modalInputHalf]}>
                        <Text style={styles.label}>Valor *</Text>
                        <View style={styles.inputWithPrefix}>
                          <Text style={styles.prefix}>R$</Text>
                          <TextInput
                            style={[styles.input, styles.inputWithPrefixField]}
                            placeholder="0,00"
                            value={planoForm.valor}
                            onChangeText={(value) => {
                              const formatted = formatValorMonetario(value);
                              handlePlanoFormChange('valor', formatted);
                            }}
                            keyboardType="numeric"
                          />
                        </View>
                      </View>
                    </View>

                    {/* Duração */}
                    <View style={styles.inputGroup}>
                      <Text style={styles.label}>Duração</Text>
                      <View style={styles.pickerContainer}>
                        <Picker
                          selectedValue={planoForm.duracao_dias}
                          onValueChange={(value) => handlePlanoFormChange('duracao_dias', value)}
                          style={styles.picker}
                        >
                          <Picker.Item label="30 dias" value={30} />
                          <Picker.Item label="90 dias" value={90} />
                          <Picker.Item label="180 dias" value={180} />
                          <Picker.Item label="365 dias" value={365} />
                        </Picker>
                      </View>
                    </View>

                    {/* Flags */}
                    <View style={styles.modalFlagsContainer}>
                      <View style={styles.modalFlag}>
                        <Text style={styles.modalFlagLabel}>Plano Ativo</Text>
                        <Switch
                          value={planoForm.ativo}
                          onValueChange={(value) => handlePlanoFormChange('ativo', value)}
                          trackColor={{ false: '#d1d5db', true: '#10b981' }}
                          thumbColor={planoForm.ativo ? '#fff' : '#f3f4f6'}
                        />
                      </View>
                      <View style={styles.modalFlag}>
                        <Text style={styles.modalFlagLabel}>Disponível para novos contratos</Text>
                        <Switch
                          value={planoForm.atual}
                          onValueChange={(value) => handlePlanoFormChange('atual', value)}
                          trackColor={{ false: '#d1d5db', true: '#10b981' }}
                          thumbColor={planoForm.atual ? '#fff' : '#f3f4f6'}
                        />
                      </View>
                    </View>
                  </ScrollView>

                  <View style={styles.modalFooter}>
                    <TouchableOpacity 
                      style={styles.modalCancelButton}
                      onPress={fecharModal}
                    >
                      <Text style={styles.modalCancelButtonText}>Cancelar</Text>
                    </TouchableOpacity>
                    <TouchableOpacity 
                      style={styles.modalSaveButton}
                      onPress={salvarPlano}
                    >
                      <Feather name="check" size={16} color="#fff" />
                      <Text style={styles.modalSaveButtonText}>
                        {planoEditandoIndex !== null ? 'Salvar' : 'Adicionar'}
                      </Text>
                    </TouchableOpacity>
                  </View>
                </Pressable>
              </Pressable>
            </Modal>

          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="toggle-right" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Status da Modalidade</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.switchRow}>
                <View style={styles.switchInfo}>
                  <Text style={styles.switchLabel}>Modalidade Ativa</Text>
                  <Text style={styles.switchDescription}>
                    {formData.ativo
                      ? 'A modalidade está ativa e disponível para novas turmas.'
                      : 'A modalidade está inativa e não aparece no catálogo.'}
                  </Text>
                </View>
                <Switch
                  value={formData.ativo}
                  onValueChange={(value) => handleChange('ativo', value)}
                  trackColor={{ false: '#d1d5db', true: '#10b981' }}
                  thumbColor={formData.ativo ? '#22c55e' : '#9ca3af'}
                />
              </View>
            </View>
          </View>
        </View>

        <View style={styles.actionButtons}>
          <TouchableOpacity
            style={styles.cancelButton}
            onPress={() => router.back()}
            disabled={saving}
          >
            <Text style={styles.cancelButtonText}>Cancelar</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.submitButton, saving && styles.submitButtonDisabled]}
            onPress={handleSubmit}
            disabled={saving}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <>
                <Feather name="check" size={18} color="#fff" />
                <Text style={styles.submitButtonText}>
                  {isEdit ? 'Salvar Alterações' : 'Cadastrar Modalidade'}
                </Text>
              </>
            )}
          </TouchableOpacity>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: 'transparent',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    color: '#64748b',
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
    paddingBottom: 24,
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
    gap: 10,
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
    backgroundColor: '#fff7ed',
  },
  cardHeaderIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#fed7aa',
  },
  cardTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1f2937',
  },
  cardBody: {
    padding: 16,
  },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 16,
  },
  switchInfo: {
    flex: 1,
  },
  switchLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
  },
  switchDescription: {
    marginTop: 4,
    fontSize: 12,
    color: '#6b7280',
  },
  actionButtons: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 12,
    paddingHorizontal: 24,
    paddingBottom: 40,
  },
  cancelButton: {
    minWidth: 160,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: 10,
    backgroundColor: '#f3f4f6',
  },
  cancelButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
  },
  submitButton: {
    minWidth: 220,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 10,
    backgroundColor: '#f97316',
  },
  submitButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#fff',
  },
  submitButtonDisabled: {
    opacity: 0.7,
  },
  header: {
    marginBottom: 24,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#1e293b',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 14,
    color: '#64748b',
  },
  form: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: 8,
  },
  input: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    color: '#1e293b',
  },
  inputError: {
    borderColor: '#ef4444',
  },
  textArea: {
    minHeight: 100,
    paddingTop: 12,
  },
  textAreaCompact: {
    minHeight: 60,
    paddingTop: 12,
  },
  // Seção Superior - Layout em duas colunas
  topSection: {
    flexDirection: 'row',
    gap: 24,
    marginBottom: 20,
  },
  topSectionLeft: {
    flex: 1,
  },
  topSectionRight: {
    width: 140,
  },
  previewLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#64748b',
    marginBottom: 8,
    textTransform: 'uppercase',
  },
  previewCard: {
    backgroundColor: '#f8fafc',
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  previewIcon: {
    width: 56,
    height: 56,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 10,
  },
  previewName: {
    fontSize: 12,
    fontWeight: '600',
    color: '#1e293b',
    textAlign: 'center',
  },
  // Seção Aparência
  appearanceSection: {
    backgroundColor: '#f8fafc',
    borderRadius: 10,
    padding: 16,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#475569',
    marginBottom: 12,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  appearanceRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  appearanceCol: {
    flex: 1,
  },
  appearanceColSmall: {
    width: 160,
  },
  appearanceDivider: {
    width: 1,
    backgroundColor: '#e2e8f0',
    marginHorizontal: 16,
    alignSelf: 'stretch',
  },
  labelSmall: {
    fontSize: 12,
    fontWeight: '600',
    color: '#64748b',
    marginBottom: 10,
  },
  iconesGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  iconeButton: {
    width: 40,
    height: 40,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#e2e8f0',
  },
  iconeButtonSelected: {
    backgroundColor: '#f0f9ff',
    borderWidth: 2,
  },
  coresGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  corButtonSmall: {
    width: 36,
    height: 36,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
  },
  corButtonSmallSelected: {
    borderColor: '#fff',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 4,
    transform: [{ scale: 1.1 }],
  },
  inputWithPrefix: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    overflow: 'hidden',
  },
  prefix: {
    paddingHorizontal: 12,
    fontSize: 14,
    fontWeight: '600',
    color: '#64748b',
  },
  inputWithPrefixField: {
    flex: 1,
    backgroundColor: 'transparent',
    borderWidth: 0,
    borderLeftWidth: 1,
    borderLeftColor: '#e2e8f0',
    borderRadius: 0,
  },
  pickerContainer: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 8,
    overflow: 'hidden',
  },
  picker: {
    height: 48,
  },
  iconePreview: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 12,
    gap: 12,
  },
  iconeBadge: {
    width: 48,
    height: 48,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  iconePreviewText: {
    fontSize: 12,
    color: '#64748b',
  },
  coresContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  corButton: {
    width: 48,
    height: 48,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
  },
  corButtonSelected: {
    borderColor: '#fff',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 4,
  },
  switchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  switchLabelCompact: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  switchInline: {
    marginLeft: 'auto',
  },
  statusGroup: {
    marginTop: 24,
    paddingTop: 20,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
  },
  helperText: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 4,
    fontStyle: 'italic',
  },
  errorText: {
    color: '#ef4444',
    fontSize: 12,
    marginTop: 4,
  },
  planosSection: {
    marginTop: 12,
    paddingTop: 20,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
  },
  planosSectionHeader: {
    marginBottom: 16,
  },
  planosTitleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  planosSectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1e293b',
    marginBottom: 4,
  },
  planosSectionSubtitle: {
    fontSize: 13,
    color: '#64748b',
  },
  addPlanoHeaderButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#3b82f6',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 6,
  },
  addPlanoHeaderButtonText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '600',
  },
  tabelaContainer: {
    backgroundColor: '#fff',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    overflow: 'hidden',
  },
  emptyPlanos: {
    padding: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emptyPlanosText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#64748b',
    marginTop: 12,
  },
  emptyPlanosSubtext: {
    fontSize: 12,
    color: '#94a3b8',
    marginTop: 4,
  },
  tabelaHeader: {
    flexDirection: 'row',
    backgroundColor: '#f1f5f9',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  tabelaHeaderCell: {
    fontSize: 12,
    fontWeight: '700',
    color: '#475569',
    textTransform: 'uppercase',
  },
  tabelaRow: {
    flexDirection: 'row',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
    alignItems: 'center',
  },
  tabelaRowAlt: {
    backgroundColor: '#fafafa',
  },
  tabelaCell: {
    fontSize: 14,
    color: '#1e293b',
  },
  cellNome: {
    flex: 2,
    paddingRight: 8,
  },
  cellCheckins: {
    flex: 1,
    textAlign: 'center',
  },
  cellValor: {
    flex: 1.2,
    textAlign: 'right',
    paddingRight: 8,
  },
  cellDuracao: {
    flex: 1,
    textAlign: 'center',
  },
  cellStatus: {
    flex: 1.5,
    flexDirection: 'row',
  },
  cellAcoes: {
    flex: 0.8,
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 8,
  },
  statusBadges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 4,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 4,
  },
  statusAtivo: {
    backgroundColor: '#dcfce7',
  },
  statusAtivoText: {
    color: '#15803d',
  },
  statusInativo: {
    backgroundColor: '#fee2e2',
  },
  statusInativoText: {
    color: '#dc2626',
  },
  statusDisponivel: {
    backgroundColor: '#dbeafe',
  },
  statusDisponivelText: {
    color: '#1d4ed8',
    fontSize: 11,
    fontWeight: '600',
  },
  statusBadgeText: {
    fontSize: 11,
    fontWeight: '600',
  },
  actionButton: {
    padding: 6,
    borderRadius: 4,
    backgroundColor: '#f8fafc',
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
    borderRadius: 12,
    width: '100%',
    maxWidth: 500,
    maxHeight: '90%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 16,
    elevation: 10,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1e293b',
  },
  modalCloseButton: {
    padding: 4,
  },
  modalBody: {
    padding: 20,
    maxHeight: 400,
  },
  modalRow: {
    flexDirection: 'row',
    gap: 16,
  },
  modalInputHalf: {
    flex: 1,
  },
  modalFlagsContainer: {
    marginTop: 8,
    gap: 12,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
  },
  modalFlag: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  modalFlagLabel: {
    fontSize: 14,
    color: '#475569',
    fontWeight: '500',
  },
  modalFooter: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 12,
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
  },
  modalCancelButton: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 6,
    backgroundColor: '#f1f5f9',
  },
  modalCancelButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#64748b',
  },
  modalSaveButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 6,
    backgroundColor: '#3b82f6',
  },
  modalSaveButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#fff',
  },
  planosContainer: {
    backgroundColor: '#f8fafc',
    borderRadius: 10,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  planoCard: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  planoCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  planoCardTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#475569',
  },
  removerPlanoButton: {
    padding: 4,
  },
  planoCardBody: {
    gap: 12,
  },
  planoRow: {
    flexDirection: 'row',
    gap: 12,
  },
  planoInputHalf: {
    flex: 1,
    marginBottom: 0,
  },
  planoInputThird: {
    flex: 1,
    marginBottom: 0,
  },
  planoFlagsRow: {
    flexDirection: 'row',
    gap: 16,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
    marginTop: 8,
  },
  planoFlag: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  planoFlagLabel: {
    fontSize: 12,
    color: '#64748b',
    fontWeight: '500',
    flex: 1,
  },
  addPlanoButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    backgroundColor: 'transparent',
    borderWidth: 2,
    borderColor: '#165ef9ff',
    borderStyle: 'solid',
    marginTop: 4,
  },
  addPlanoButtonIcon: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: '#165ef9ff',
    justifyContent: 'center',
    alignItems: 'center',
  },
  addPlanoButtonText: {
    color: '#165ef9ff',
    fontSize: 14,
    fontWeight: '600',
  },
  submitButton: {
    backgroundColor: '#f97316',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    borderRadius: 8,
    marginBottom: 20,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
