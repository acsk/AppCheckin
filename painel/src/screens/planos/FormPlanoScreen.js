import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Switch,
  ScrollView,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { useRouter, useLocalSearchParams } from 'expo-router';
import planoService from '../../services/planoService';
import modalidadeService from '../../services/modalidadeService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import ConfirmModal from '../../components/ConfirmModal';
import { showSuccess, showError } from '../../utils/toast';
import { colors } from '../../styles/globalStyles';

export default function FormPlanoScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const isEdit = !!id;
  const planoId = id ? parseInt(id) : null;

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [modalidades, setModalidades] = useState([]);
  const [formData, setFormData] = useState({
    modalidade_id: '',
    nome: '',
    descricao: '',
    valor: '',
    checkins_semanais: '',
    duracao_dias: '30',
    ativo: true,
    atual: true,
  });
  const [tiposCiclo, setTiposCiclo] = useState([]);
  const [ciclos, setCiclos] = useState([]);
  const [loadingCiclos, setLoadingCiclos] = useState(false);
  const [savingCiclo, setSavingCiclo] = useState(false);
  const [gerandoCiclos, setGerandoCiclos] = useState(false);
  const [editingCicloId, setEditingCicloId] = useState(null);
  const [confirmDeleteCiclo, setConfirmDeleteCiclo] = useState({ visible: false, ciclo: null });
  const [cicloForm, setCicloForm] = useState({
    tipo_ciclo_id: '',
    valor: '',
    permite_recorrencia: true,
    ativo: true,
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadTiposCiclo = async () => {
    try {
      const response = await planoService.listarTiposCiclo();
      setTiposCiclo(response.data || []);
    } catch (error) {
      console.error('❌ Erro ao carregar tipos de ciclo:', error);
      showError('Não foi possível carregar os tipos de ciclo');
    }
  };

  const loadCiclos = async () => {
    if (!planoId) return;
    try {
      setLoadingCiclos(true);
      const response = await planoService.listarCiclos(planoId);
      const ciclosOrdenados = (response.ciclos || []).sort((a, b) => a.meses - b.meses);
      setCiclos(ciclosOrdenados);
    } catch (error) {
      console.error('❌ Erro ao carregar ciclos:', error);
      showError('Não foi possível carregar os ciclos do plano');
    } finally {
      setLoadingCiclos(false);
    }
  };

  const loadData = async () => {
    try {
      setLoading(true);
      
      // Buscar modalidades ativas
      const modalidadesResponse = await modalidadeService.listar(true);
      const modalidadesArray = Array.isArray(modalidadesResponse) ? modalidadesResponse : [];
      setModalidades(modalidadesArray);

      // Se for edição, buscar dados do plano
      if (isEdit) {
        console.log('🔍 Buscando plano ID:', planoId);
        const plano = await planoService.buscar(planoId);
        console.log('✅ Plano recebido:', plano);
        
        setFormData({
          modalidade_id: plano.modalidade_id?.toString() || '',
          nome: plano.nome || '',
          descricao: plano.descricao || '',
          valor: plano.valor ? parseFloat(plano.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '',
          checkins_semanais: plano.checkins_semanais?.toString() || '',
          duracao_dias: plano.duracao_dias ? plano.duracao_dias.toString() : '30',
          ativo: plano.ativo === 1 || plano.ativo === true,
          atual: plano.atual === 1 || plano.atual === true,
        });

        await Promise.all([loadTiposCiclo(), loadCiclos()]);
      } else {
        await loadTiposCiclo();
      }
    } catch (error) {
      console.error('❌ Erro ao carregar dados:', error);
      showError(isEdit ? 'Não foi possível carregar os dados do plano' : 'Não foi possível carregar as modalidades');
      setModalidades([]); // Garante que sempre seja array
      if (isEdit) {
        router.back();
      }
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

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(Number(value) || 0);
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.modalidade_id) {
      newErrors.modalidade_id = 'Selecione uma modalidade';
    }

    if (!formData.nome.trim()) {
      newErrors.nome = 'Nome do plano é obrigatório';
    }

    if (!formData.valor || parseFloat(formData.valor) < 0) {
      newErrors.valor = 'Valor deve ser maior ou igual a zero';
    }

    if (!formData.checkins_semanais || parseInt(formData.checkins_semanais) < 1) {
      newErrors.checkins_semanais = 'Informe os checkins semanais (mínimo 1)';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const parseValorMonetario = (valorFormatado) => {
    if (!valorFormatado) return 0;
    // Se já for número, retorna direto
    if (typeof valorFormatado === 'number') return valorFormatado;
    // Formato brasileiro: 1.234,56 → remove pontos de milhar, troca vírgula por ponto
    const valorLimpo = valorFormatado.toString().replace(/\./g, '').replace(',', '.');
    return parseFloat(valorLimpo) || 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    setSaving(true);

    try {
      const dataToSend = {
        modalidade_id: parseInt(formData.modalidade_id),
        nome: formData.nome,
        descricao: formData.descricao,
        valor: parseValorMonetario(formData.valor),
        checkins_semanais: parseInt(formData.checkins_semanais),
        duracao_dias: parseInt(formData.duracao_dias),
        ativo: formData.ativo ? 1 : 0,
        atual: formData.atual ? 1 : 0,
      };

      let result;
      if (isEdit) {
        result = await planoService.atualizar(planoId, dataToSend);
        showSuccess(result.message || 'Plano atualizado com sucesso');
      } else {
        result = await planoService.criar(dataToSend);
        showSuccess(result.message || 'Plano criado com sucesso');
      }
      
      router.push('/planos');
    } catch (error) {
      showError(error.errors?.join('\n') || error.error || `Não foi possível ${isEdit ? 'atualizar' : 'criar'} o plano`);
    } finally {
      setSaving(false);
    }
  };

  const resetCicloForm = () => {
    setCicloForm({
      tipo_ciclo_id: '',
      valor: '',
      permite_recorrencia: true,
      ativo: true,
    });
    setEditingCicloId(null);
  };

  const handleEditarCiclo = (ciclo) => {
    setEditingCicloId(ciclo.id);
    // Encontrar o tipo de ciclo correspondente pelo código ou pelo id
    const tipoEncontrado = tiposCiclo.find(t => 
      t.codigo === ciclo.codigo || 
      t.id === ciclo.tipo_ciclo_id || 
      t.id === ciclo.frequencia_id
    );
    setCicloForm({
      tipo_ciclo_id: tipoEncontrado?.id?.toString() || ciclo.tipo_ciclo_id?.toString() || ciclo.frequencia_id?.toString() || '',
      valor: ciclo.valor ? parseFloat(ciclo.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '',
      permite_recorrencia: ciclo.permite_recorrencia === 1 || ciclo.permite_recorrencia === true,
      ativo: ciclo.ativo === 1 || ciclo.ativo === true,
    });
  };

  const handleSalvarCiclo = async () => {
    if (!planoId) return;

    if (!editingCicloId && !cicloForm.tipo_ciclo_id) {
      showError('Selecione um tipo de ciclo');
      return;
    }

    const valorConvertido = parseValorMonetario(cicloForm.valor);
    if (!cicloForm.valor || valorConvertido <= 0) {
      showError('Informe um valor válido para o ciclo');
      return;
    }

    try {
      setSavingCiclo(true);
      const payload = {
        valor: valorConvertido,
        permite_recorrencia: cicloForm.permite_recorrencia ? 1 : 0,
        ativo: cicloForm.ativo ? 1 : 0,
      };

      if (editingCicloId) {
        await planoService.atualizarCiclo(planoId, editingCicloId, payload);
        showSuccess('Ciclo atualizado com sucesso');
      } else {
        await planoService.criarCiclo(planoId, {
          ...payload,
          tipo_ciclo_id: parseInt(cicloForm.tipo_ciclo_id),
        });
        showSuccess('Ciclo criado com sucesso');
      }

      resetCicloForm();
      await loadCiclos();
    } catch (error) {
      showError(error.error || 'Não foi possível salvar o ciclo');
    } finally {
      setSavingCiclo(false);
    }
  };

  const handleGerarCiclos = async () => {
    if (!planoId) return;
    try {
      setGerandoCiclos(true);
      const response = await planoService.gerarCiclos(planoId);
      showSuccess(response.message || 'Ciclos gerados com sucesso');
      await loadCiclos();
    } catch (error) {
      showError(error.error || 'Não foi possível gerar os ciclos');
    } finally {
      setGerandoCiclos(false);
    }
  };

  const handleConfirmDeleteCiclo = async () => {
    if (!confirmDeleteCiclo.ciclo || !planoId) return;
    try {
      await planoService.excluirCiclo(planoId, confirmDeleteCiclo.ciclo.id);
      showSuccess('Ciclo excluído com sucesso');
      setConfirmDeleteCiclo({ visible: false, ciclo: null });
      await loadCiclos();
    } catch (error) {
      showError(error.error || 'Não foi possível excluir o ciclo');
    }
  };

  if (loading) {
    return (
      <LayoutBase title={isEdit ? "Editar Plano" : "Novo Plano"}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase 
      title={isEdit ? "Editar Plano" : "Novo Plano"} 
      subtitle="Preencha os campos obrigatórios"
    >
      <ConfirmModal
        visible={confirmDeleteCiclo.visible}
        title="Excluir ciclo"
        message={`Deseja excluir o ciclo ${confirmDeleteCiclo.ciclo?.nome || ''}?`}
        onConfirm={handleConfirmDeleteCiclo}
        onCancel={() => setConfirmDeleteCiclo({ visible: false, ciclo: null })}
        confirmText="Excluir"
        type="danger"
      />
      {saving && <LoadingOverlay message={isEdit ? "Atualizando plano..." : "Criando plano..."} />}

      <ScrollView style={styles.scrollView}>
        <View style={styles.headerActions}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => router.back()}
            disabled={saving}
          >
            <Feather name="arrow-left" size={18} color="#fff" />
            <Text style={styles.backButtonText}>Voltar</Text>
          </TouchableOpacity>

          <View style={[styles.statusIndicator, formData.ativo ? styles.statusActive : styles.statusInactive]}>
            <View style={[styles.statusDot, formData.ativo ? styles.statusDotActive : styles.statusDotInactive]} />
            <Text style={[styles.statusIndicatorText, formData.ativo ? styles.statusTextActive : styles.statusTextInactive]}>
              {formData.ativo ? 'Plano Ativo' : 'Plano Inativo'}
            </Text>
          </View>
        </View>

        <View style={styles.formContainer}>
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="clipboard" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Dados do Plano</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.inputGroup}>
                <Text style={styles.label}>Modalidade <Text style={styles.required}>*</Text></Text>
                <View style={[styles.pickerContainer, errors.modalidade_id && styles.inputError]}>
                  <Picker
                    selectedValue={formData.modalidade_id}
                    onValueChange={(value) => handleChange('modalidade_id', value)}
                    enabled={!saving}
                    style={styles.picker}
                  >
                    <Picker.Item label="Selecione uma modalidade" value="" />
                    {modalidades.map((mod) => (
                      <Picker.Item key={mod.id} label={mod.nome} value={mod.id.toString()} />
                    ))}
                  </Picker>
                </View>
                {errors.modalidade_id && <Text style={styles.errorText}>{errors.modalidade_id}</Text>}
              </View>

              <View style={styles.inputGroup}>
                <Text style={styles.label}>Nome do Plano <Text style={styles.required}>*</Text></Text>
                <TextInput
                  style={[styles.input, errors.nome && styles.inputError]}
                  placeholder="Ex: 2x por semana"
                  placeholderTextColor={colors.placeholder}
                  value={formData.nome}
                  onChangeText={(value) => handleChange('nome', value)}
                  editable={!saving}
                />
                {errors.nome && <Text style={styles.errorText}>{errors.nome}</Text>}
              </View>

              <View style={styles.inputGroup}>
                <Text style={styles.label}>Descrição</Text>
                <TextInput
                  style={[styles.input, styles.textArea]}
                  placeholder="Descrição do plano"
                  placeholderTextColor={colors.placeholder}
                  value={formData.descricao}
                  onChangeText={(value) => handleChange('descricao', value)}
                  multiline
                  numberOfLines={3}
                  editable={!saving}
                />
              </View>
            </View>
          </View>

          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="dollar-sign" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Condições do Plano</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.row}>
                <View style={[styles.inputGroup, styles.flex1, styles.marginRight]}>
                  <Text style={styles.label}>Valor Mensal <Text style={styles.required}>*</Text></Text>
                  <View style={styles.inputWithPrefix}>
                    <Text style={styles.prefix}>R$</Text>
                    <TextInput
                      style={[styles.input, styles.inputWithPrefixField, errors.valor && styles.inputError]}
                      placeholder="0,00"
                      placeholderTextColor={colors.placeholder}
                      value={formData.valor}
                      onChangeText={(value) => {
                        const formatted = formatValorMonetario(value);
                        handleChange('valor', formatted);
                      }}
                      keyboardType="numeric"
                      editable={!saving}
                    />
                  </View>
                  {errors.valor && <Text style={styles.errorText}>{errors.valor}</Text>}
                </View>

                <View style={[styles.inputGroup, styles.flex1]}>
                  <Text style={styles.label}>Checkins/Semana <Text style={styles.required}>*</Text></Text>
                  <TextInput
                    style={[styles.input, errors.checkins_semanais && styles.inputError]}
                    placeholder="Ex: 2, 3, 4"
                    placeholderTextColor={colors.placeholder}
                    value={formData.checkins_semanais}
                    onChangeText={(value) => handleChange('checkins_semanais', value)}
                    keyboardType="number-pad"
                    editable={!saving}
                  />
                  {errors.checkins_semanais && <Text style={styles.errorText}>{errors.checkins_semanais}</Text>}
                </View>
              </View>
              <Text style={styles.helperText}>Use 999 para checkins ilimitados</Text>

              <View style={styles.inputGroup}>
                <Text style={styles.label}>Duração do Plano</Text>
                <View style={styles.pickerContainer}>
                  <Picker
                    selectedValue={formData.duracao_dias}
                    onValueChange={(value) => handleChange('duracao_dias', value)}
                    enabled={!saving}
                    style={styles.picker}
                  >
                    <Picker.Item label="30 dias (Mensal)" value="30" />
                    <Picker.Item label="90 dias (Trimestral)" value="90" />
                    <Picker.Item label="180 dias (Semestral)" value="180" />
                    <Picker.Item label="365 dias (Anual)" value="365" />
                  </Picker>
                </View>
              </View>
            </View>
          </View>

          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardHeaderIcon}>
                <Feather name="toggle-right" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Status do Plano</Text>
            </View>
            <View style={styles.cardBody}>
              <View style={styles.switchRow}>
                <View style={styles.switchInfo}>
                  <Text style={styles.switchLabel}>Plano Ativo</Text>
                  <Text style={styles.switchDescription}>
                    {formData.ativo ? 'O plano está ativo.' : 'O plano está inativo.'}
                  </Text>
                </View>
                <Switch
                  value={formData.ativo}
                  onValueChange={(value) => handleChange('ativo', value)}
                  disabled={saving}
                  trackColor={{ false: '#d1d5db', true: '#10b981' }}
                  thumbColor={formData.ativo ? '#22c55e' : '#9ca3af'}
                />
              </View>

              <View style={styles.switchRow}>
                <View style={styles.switchInfo}>
                  <Text style={styles.switchLabel}>Disponível para Novos Contratos</Text>
                  <Text style={styles.switchDescription}>
                    {formData.atual
                      ? 'Pode ser usado em novos contratos.'
                      : 'Apenas contratos existentes (histórico).'}
                  </Text>
                </View>
                <Switch
                  value={formData.atual}
                  onValueChange={(value) => handleChange('atual', value)}
                  disabled={saving}
                  trackColor={{ false: '#d1d5db', true: '#3b82f6' }}
                  thumbColor={formData.atual ? '#3b82f6' : '#9ca3af'}
                />
              </View>
            </View>
          </View>

          {isEdit && (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <View style={styles.cardHeaderIcon}>
                  <Feather name="repeat" size={20} color="#f97316" />
                </View>
                <Text style={styles.cardTitle}>Ciclos do Plano</Text>
              </View>
              <View style={styles.cardBody}>
                <View style={styles.ciclosHeaderRow}>
                  <Text style={styles.ciclosSubtitle}>Configure os valores por período</Text>
                  <TouchableOpacity
                    style={[styles.ciclosActionButton, (gerandoCiclos || savingCiclo) && styles.ciclosActionButtonDisabled]}
                    onPress={handleGerarCiclos}
                    disabled={gerandoCiclos || savingCiclo}
                  >
                    {gerandoCiclos ? (
                      <ActivityIndicator size="small" color="#f97316" />
                    ) : (
                      <>
                        <Feather name="zap" size={14} color="#f97316" />
                        <Text style={styles.ciclosActionText}>Gerar Automático</Text>
                      </>
                    )}
                  </TouchableOpacity>
                </View>

                <View style={styles.cicloFormContainer}>
                  <View style={styles.row}>
                    <View style={[styles.inputGroup, styles.flex1, styles.marginRight]}>
                      <Text style={styles.label}>Tipo de Ciclo <Text style={styles.required}>*</Text></Text>
                      <View style={styles.pickerContainer}>
                        <Picker
                          key={`picker-ciclo-${tiposCiclo.length}`}
                          selectedValue={cicloForm.tipo_ciclo_id}
                          onValueChange={(value) => setCicloForm(prev => ({ ...prev, tipo_ciclo_id: value }))}
                          enabled={!savingCiclo && !editingCicloId}
                          style={styles.picker}
                        >
                          <Picker.Item label="Selecione" value="" />
                          {tiposCiclo.map((tipo) => (
                            <Picker.Item
                              key={tipo.id}
                              label={`${tipo.nome} (${tipo.meses} ${tipo.meses > 1 ? 'meses' : 'mês'})`}
                              value={tipo.id.toString()}
                            />
                          ))}
                        </Picker>
                      </View>
                      {editingCicloId && (
                        <Text style={styles.helperText}>Para mudar o tipo, exclua e crie um novo ciclo.</Text>
                      )}
                    </View>

                    <View style={[styles.inputGroup, styles.flex1]}>
                      <Text style={styles.label}>Valor do Ciclo <Text style={styles.required}>*</Text></Text>
                      <View style={styles.inputWithPrefix}>
                        <Text style={styles.prefix}>R$</Text>
                        <TextInput
                          style={[styles.input, styles.inputWithPrefixField]}
                          placeholder="0,00"
                          placeholderTextColor={colors.placeholder}
                          value={cicloForm.valor}
                          onChangeText={(value) => {
                            const formatted = formatValorMonetario(value);
                            setCicloForm(prev => ({ ...prev, valor: formatted }));
                          }}
                          keyboardType="numeric"
                          editable={!savingCiclo}
                        />
                      </View>
                    </View>
                  </View>

                  <View style={styles.row}>
                    <View style={[styles.switchRow, styles.flex1, styles.marginRight]}>
                      <View style={styles.switchInfo}>
                        <Text style={styles.switchLabel}>Recorrência</Text>
                        <Text style={styles.switchDescription}>
                          {cicloForm.permite_recorrencia ? 'Permite assinatura' : 'Sem assinatura'}
                        </Text>
                      </View>
                      <Switch
                        value={cicloForm.permite_recorrencia}
                        onValueChange={(value) => setCicloForm(prev => ({ ...prev, permite_recorrencia: value }))}
                        disabled={savingCiclo}
                        trackColor={{ false: '#d1d5db', true: '#10b981' }}
                        thumbColor={cicloForm.permite_recorrencia ? '#22c55e' : '#9ca3af'}
                      />
                    </View>

                    <View style={[styles.switchRow, styles.flex1]}>
                      <View style={styles.switchInfo}>
                        <Text style={styles.switchLabel}>Ativo</Text>
                        <Text style={styles.switchDescription}>
                          {cicloForm.ativo ? 'Disponível' : 'Indisponível'}
                        </Text>
                      </View>
                      <Switch
                        value={cicloForm.ativo}
                        onValueChange={(value) => setCicloForm(prev => ({ ...prev, ativo: value }))}
                        disabled={savingCiclo}
                        trackColor={{ false: '#d1d5db', true: '#3b82f6' }}
                        thumbColor={cicloForm.ativo ? '#3b82f6' : '#9ca3af'}
                      />
                    </View>
                  </View>

                  <View style={styles.cicloFormActions}>
                    {editingCicloId && (
                      <TouchableOpacity
                        style={styles.cicloCancelButton}
                        onPress={resetCicloForm}
                        disabled={savingCiclo}
                      >
                        <Text style={styles.cicloCancelText}>Cancelar</Text>
                      </TouchableOpacity>
                    )}
                    <TouchableOpacity
                      style={[styles.cicloSaveButton, savingCiclo && styles.cicloSaveButtonDisabled]}
                      onPress={handleSalvarCiclo}
                      disabled={savingCiclo}
                    >
                      {savingCiclo ? (
                        <ActivityIndicator color="#fff" />
                      ) : (
                        <>
                          <Feather name="check" size={16} color="#fff" />
                          <Text style={styles.cicloSaveText}>
                            {editingCicloId ? 'Atualizar Ciclo' : 'Adicionar Ciclo'}
                          </Text>
                        </>
                      )}
                    </TouchableOpacity>
                  </View>
                </View>

                <View style={styles.ciclosList}>
                  {loadingCiclos ? (
                    <View style={styles.loadingInline}>
                      <ActivityIndicator size="small" color="#f97316" />
                      <Text style={styles.loadingInlineText}>Carregando ciclos...</Text>
                    </View>
                  ) : ciclos.length === 0 ? (
                    <Text style={styles.emptyText}>Nenhum ciclo cadastrado ainda.</Text>
                  ) : (
                    ciclos.map((ciclo) => (
                      <View key={ciclo.id} style={styles.cicloItem}>
                        <View style={styles.cicloInfo}>
                          <Text style={styles.cicloTitle}>{ciclo.nome}</Text>
                          <Text style={styles.cicloMeta}>
                            {formatCurrency(ciclo.valor)} • {ciclo.meses} {ciclo.meses > 1 ? 'meses' : 'mês'}
                          </Text>
                          <Text style={styles.cicloMetaMuted}>
                            Mensal equivalente: {ciclo.valor_mensal_formatado || formatCurrency(ciclo.valor_mensal_equivalente)}
                          </Text>
                          <View style={styles.cicloTags}>
                            <View style={[styles.cicloTag, ciclo.permite_recorrencia ? styles.cicloTagActive : styles.cicloTagInactive]}>
                              <Text style={styles.cicloTagText}>{ciclo.permite_recorrencia ? 'Recorrente' : 'Avulso'}</Text>
                            </View>
                            <View style={[styles.cicloTag, ciclo.ativo ? styles.cicloTagActive : styles.cicloTagInactive]}>
                              <Text style={styles.cicloTagText}>{ciclo.ativo ? 'Ativo' : 'Inativo'}</Text>
                            </View>
                            {ciclo.desconto_percentual > 0 && (
                              <View style={styles.cicloTagDiscount}>
                                <Text style={styles.cicloTagDiscountText}>-{ciclo.desconto_percentual}%</Text>
                              </View>
                            )}
                          </View>
                        </View>
                        <View style={styles.cicloActions}>
                          <TouchableOpacity
                            style={styles.cicloActionButton}
                            onPress={() => handleEditarCiclo(ciclo)}
                          >
                            <Feather name="edit-2" size={16} color="#3b82f6" />
                          </TouchableOpacity>
                          <TouchableOpacity
                            style={styles.cicloActionButton}
                            onPress={() => setConfirmDeleteCiclo({ visible: true, ciclo })}
                          >
                            <Feather name="trash-2" size={16} color="#ef4444" />
                          </TouchableOpacity>
                        </View>
                      </View>
                    ))
                  )}
                </View>
              </View>
            </View>
          )}
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
            activeOpacity={0.7}
          >
            {saving ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <>
                <Feather name="check" size={18} color="#fff" />
                <Text style={styles.submitButtonText}>
                  {isEdit ? 'Salvar Alterações' : 'Criar Plano'}
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
  scrollView: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'transparent',
    paddingVertical: 100,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
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
  row: {
    flexDirection: 'row',
  },
  flex1: {
    flex: 1,
  },
  marginRight: {
    marginRight: 12,
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
  },
  required: {
    color: '#ef4444',
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 10,
    padding: 12,
    fontSize: 14,
    color: '#111827',
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
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  pickerContainer: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 10,
    overflow: 'hidden',
  },
  picker: {
    height: 50,
    color: '#111827',
  },
  helperText: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: -12,
    marginBottom: 16,
    marginLeft: 4,
  },
  inputWithPrefix: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 10,
    overflow: 'hidden',
  },
  prefix: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    paddingLeft: 12,
    paddingRight: 8,
  },
  inputWithPrefixField: {
    flex: 1,
    borderWidth: 0,
    paddingLeft: 0,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
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
    fontSize: 12,
    color: '#6b7280',
    marginTop: 4,
  },
  ciclosHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  ciclosSubtitle: {
    fontSize: 12,
    color: '#6b7280',
  },
  ciclosActionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 8,
    backgroundColor: '#fff7ed',
    borderWidth: 1,
    borderColor: '#fed7aa',
  },
  ciclosActionButtonDisabled: {
    opacity: 0.6,
  },
  ciclosActionText: {
    fontSize: 12,
    color: '#f97316',
    fontWeight: '600',
  },
  cicloFormContainer: {
    padding: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    backgroundColor: '#f9fafb',
    marginBottom: 16,
  },
  cicloFormActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 10,
    marginTop: 4,
  },
  cicloSaveButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 14,
    backgroundColor: '#f97316',
    borderRadius: 8,
  },
  cicloSaveButtonDisabled: {
    opacity: 0.7,
  },
  cicloSaveText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '600',
  },
  cicloCancelButton: {
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    backgroundColor: '#e5e7eb',
  },
  cicloCancelText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
  },
  ciclosList: {
    gap: 12,
  },
  cicloItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    backgroundColor: '#fff',
  },
  cicloInfo: {
    flex: 1,
  },
  cicloTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  cicloMeta: {
    fontSize: 12,
    color: '#374151',
  },
  cicloMetaMuted: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
  cicloTags: {
    flexDirection: 'row',
    gap: 6,
    marginTop: 8,
    flexWrap: 'wrap',
  },
  cicloTag: {
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 999,
  },
  cicloTagActive: {
    backgroundColor: '#dcfce7',
  },
  cicloTagInactive: {
    backgroundColor: '#fee2e2',
  },
  cicloTagText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#166534',
  },
  cicloTagDiscount: {
    backgroundColor: '#dbeafe',
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 999,
  },
  cicloTagDiscountText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#1d4ed8',
  },
  cicloActions: {
    justifyContent: 'space-between',
  },
  cicloActionButton: {
    width: 32,
    height: 32,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
  },
  loadingInline: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  loadingInlineText: {
    fontSize: 12,
    color: '#6b7280',
  },
  emptyText: {
    fontSize: 12,
    color: '#6b7280',
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 24,
    paddingBottom: 40,
  },
  cancelButton: {
    flex: 1,
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
    backgroundColor: '#f97316',
    padding: 12,
    borderRadius: 10,
    alignItems: 'center',
    flex: 1,
  },
  submitButtonDisabled: {
    backgroundColor: '#fbbf24',
    opacity: 0.6,
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
