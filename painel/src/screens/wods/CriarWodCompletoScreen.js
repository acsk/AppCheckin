import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  TextInput,
  ActivityIndicator,
  Switch,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { criarWodCompleto, obterWod, atualizarWod } from '../../services/wodService';
import LayoutBase from '../../components/LayoutBase';
import WodPreviewModal from '../../components/WodPreviewModal';
import AdicionarBlocoModal from '../../components/AdicionarBlocoModal';
import AdicionarVariacaoModal from '../../components/AdicionarVariacaoModal';
import { showSuccess, showError } from '../../utils/toast';
import { TIPOS_BLOCO } from '../../utils/wodConstants';
import modalidadeService from '../../services/modalidadeService';

const formatDateBRFromDate = (dateObj) => {
  const day = String(dateObj.getDate()).padStart(2, '0');
  const month = String(dateObj.getMonth() + 1).padStart(2, '0');
  const year = dateObj.getFullYear();
  return `${day}/${month}/${year}`;
};

export default function CriarWodCompletoScreen() {
  const router = useRouter();
  const { id, duplicate } = useLocalSearchParams();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [previewVisible, setPreviewVisible] = useState(false);
  const [blocoModalVisible, setBlocoModalVisible] = useState(false);
  const [variacaoModalVisible, setVariacaoModalVisible] = useState(false);
  const [modalidades, setModalidades] = useState([]);
  const isEditing = !!id;
  const isDuplicating = !!duplicate;

  const [formData, setFormData] = useState({
    titulo: '',
    descricao: '',
    data: formatDateBRFromDate(new Date()),
    status: 'draft',
    modalidade_id: '',
    blocos: [],
    variacoes: [],
  });

  const [editandoBlocoIndex, setEditandoBlocoIndex] = useState(null);
  const [editandoVariacaoIndex, setEditandoVariacaoIndex] = useState(null);

  // Carregar dados do WOD quando estiver editando
  useEffect(() => {
    if (isEditing && id) {
      carregarWod();
    } else if (isDuplicating && duplicate) {
      carregarWodDuplicado();
    }
  }, [id, duplicate]);

  useEffect(() => {
    carregarModalidades();
  }, []);

  const carregarModalidades = async () => {
    try {
      const lista = await modalidadeService.listar(true);
      setModalidades(Array.isArray(lista) ? lista : []);
    } catch (error) {
      console.error('Erro ao carregar modalidades:', error);
      showError('Não foi possível carregar as modalidades');
    }
  };

  const carregarWod = async () => {
    try {
      setIsLoading(true);
      const response = await obterWod(id);
      
      // A resposta vem com response.data contendo o WOD
      const wod = response.data || response;
      
      setFormData({
        titulo: wod.titulo || '',
        descricao: wod.descricao || '',
        data: wod.data ? formatDateBR(wod.data.split('T')[0]) : '',
        status: wod.status || 'draft',
        modalidade_id: wod.modalidade_id ? String(wod.modalidade_id) : '',
        blocos: wod.blocos || [],
        variacoes: wod.variacoes || [],
      });
    } catch (error) {
      console.error('Erro ao carregar WOD:', error);
      const errorMessage = error?.response?.data?.message || error?.message || 'Erro ao carregar WOD';
      showError(errorMessage);
      router.push('/wods');
    } finally {
      setIsLoading(false);
    }
  };

  const carregarWodDuplicado = async () => {
    try {
      setIsLoading(true);
      const response = await obterWod(duplicate);
      
      // A resposta vem com response.data contendo o WOD
      const wod = response.data || response;
      
      setFormData({
        titulo: `${wod.titulo} (Cópia)`,
        descricao: wod.descricao || '',
        data: formatDateBRFromDate(new Date()),
        status: 'draft',
        modalidade_id: wod.modalidade_id ? String(wod.modalidade_id) : '',
        blocos: wod.blocos || [],
        variacoes: wod.variacoes || [],
      });
    } catch (error) {
      console.error('Erro ao carregar WOD duplicado:', error);
      showError('Erro ao duplicar WOD');
      router.push('/wods');
    } finally {
      setIsLoading(false);
    }
  };

  // Handlers para campos principais
  const handleMainChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value,
    }));
  };

  const formatDateBR = (isoDate) => {
    if (!isoDate || isoDate.length !== 10) return '';
    const [year, month, day] = isoDate.split('-');
    return `${day}/${month}/${year}`;
  };

  const parseDateBR = (value) => {
    const clean = value.replace(/\D/g, '').slice(0, 8);
    const day = clean.slice(0, 2);
    const month = clean.slice(2, 4);
    const year = clean.slice(4, 8);
    let result = '';
    if (day) result += day;
    if (month) result += `/${month}`;
    if (year) result += `/${year}`;
    return result;
  };

  const toIsoFromBR = (value) => {
    const parts = value.split('/');
    if (parts.length !== 3) return '';
    const [day, month, year] = parts;
    if (day.length !== 2 || month.length !== 2 || year.length !== 4) return '';
    return `${year}-${month}-${day}`;
  };

  // Handlers para blocos
  const handleBlocoChange = (index, field, value) => {
    const newBlocos = [...formData.blocos];
    newBlocos[index] = {
      ...newBlocos[index],
      [field]: value,
    };
    setFormData(prev => ({
      ...prev,
      blocos: newBlocos,
    }));
  };

  const handleAddBloco = (novoBloco) => {
    if (editandoBlocoIndex !== null) {
      // Atualizando bloco existente
      const blocos = [...formData.blocos];
      blocos[editandoBlocoIndex] = {
        ...blocos[editandoBlocoIndex],
        ...novoBloco,
      };
      setFormData(prev => ({
        ...prev,
        blocos,
      }));
      setEditandoBlocoIndex(null);
      showSuccess('Bloco atualizado com sucesso!');
    } else {
      // Adicionando novo bloco
      const bloco = {
        ordem: formData.blocos.length + 1,
        ...novoBloco,
      };
      setFormData(prev => ({
        ...prev,
        blocos: [...prev.blocos, bloco],
      }));
      showSuccess('Bloco adicionado com sucesso!');
    }
    setBlocoModalVisible(false);
  };

  const removeBloco = (index) => {
    setFormData(prev => ({
      ...prev,
      blocos: prev.blocos.filter((_, i) => i !== index),
    }));
    showSuccess('Bloco removido');
  };

  const handleEditBloco = (index) => {
    setEditandoBlocoIndex(index);
    setBlocoModalVisible(true);
  };

  const moveBlockUp = (index) => {
    if (index === 0) return;
    const newBlocos = [...formData.blocos];
    [newBlocos[index], newBlocos[index - 1]] = [newBlocos[index - 1], newBlocos[index]];
    newBlocos.forEach((bloco, idx) => {
      bloco.ordem = idx + 1;
    });
    setFormData(prev => ({
      ...prev,
      blocos: newBlocos,
    }));
  };

  const moveBlockDown = (index) => {
    if (index === formData.blocos.length - 1) return;
    const newBlocos = [...formData.blocos];
    [newBlocos[index], newBlocos[index + 1]] = [newBlocos[index + 1], newBlocos[index]];
    newBlocos.forEach((bloco, idx) => {
      bloco.ordem = idx + 1;
    });
    setFormData(prev => ({
      ...prev,
      blocos: newBlocos,
    }));
  };

  // Handlers para variações
  const handleVariacaoChange = (index, field, value) => {
    const newVariacoes = [...formData.variacoes];
    newVariacoes[index] = {
      ...newVariacoes[index],
      [field]: value,
    };
    setFormData(prev => ({
      ...prev,
      variacoes: newVariacoes,
    }));
  };

  const handleAddVariacao = (novaVariacao) => {
    if (editandoVariacaoIndex !== null) {
      // Atualizando variação existente
      const variacoes = [...formData.variacoes];
      variacoes[editandoVariacaoIndex] = novaVariacao;
      setFormData(prev => ({
        ...prev,
        variacoes,
      }));
      setEditandoVariacaoIndex(null);
      showSuccess('Variação atualizada com sucesso!');
    } else {
      // Adicionando nova variação
      setFormData(prev => ({
        ...prev,
        variacoes: [...prev.variacoes, novaVariacao],
      }));
      showSuccess('Variação adicionada com sucesso!');
    }
    setVariacaoModalVisible(false);
  };

  const removeVariacao = (index) => {
    setFormData(prev => ({
      ...prev,
      variacoes: prev.variacoes.filter((_, i) => i !== index),
    }));
    showSuccess('Variação removida');
  };

  const handleEditVariacao = (index) => {
    setEditandoVariacaoIndex(index);
    setVariacaoModalVisible(true);
  };

  // Validação
  const validateForm = () => {
    const errors = [];

    if (!formData.titulo.trim()) {
      errors.push('Título é obrigatório');
    }

    if (!formData.data) {
      errors.push('Data é obrigatória');
    }
    if (formData.data && !toIsoFromBR(formData.data)) {
      errors.push('Data inválida (use DD/MM/AAAA)');
    }

    if (!formData.modalidade_id) {
      errors.push('Modalidade é obrigatória');
    }
    if (
      formData.modalidade_id &&
      !modalidades.some((modalidade) => String(modalidade.id) === formData.modalidade_id)
    ) {
      errors.push('Modalidade inválida');
    }

    if (formData.blocos.length === 0) {
      errors.push('É necessário ter pelo menos um bloco');
    }

    formData.blocos.forEach((bloco, index) => {
      if (!bloco.conteudo.trim()) {
        errors.push(`Bloco ${index + 1}: conteúdo é obrigatório`);
      }
    });

    return errors;
  };

  // Submissão
  const handleSubmit = async () => {
    const errors = validateForm();

    if (errors.length > 0) {
      showError(errors.join('\n'));
      return;
    }

    try {
      setIsSubmitting(true);

      const modalidadeId = Number(formData.modalidade_id);
      const modalidadeValida = modalidades.some(
        (modalidade) => String(modalidade.id) === formData.modalidade_id
      );
      if (!Number.isInteger(modalidadeId) || !modalidadeValida) {
        showError('Modalidade é obrigatória');
        return;
      }

      const payload = {
        titulo: formData.titulo,
        descricao: formData.descricao,
        data: toIsoFromBR(formData.data),
        status: formData.status,
        modalidade_id: modalidadeId,
        blocos: formData.blocos,
        variacoes: formData.variacoes,
      };

      if (isEditing) {
        const response = await atualizarWod(id, payload);
        if (response.type === 'success') {
          showSuccess('WOD atualizado com sucesso!');
          router.push('/wods');
        } else {
          const errorMessage = response.message || 'Erro ao atualizar WOD';
          showError(errorMessage);
        }
      } else {
        const response = await criarWodCompleto(payload);
        if (response.type === 'success') {
          showSuccess('WOD criado com sucesso!');
          router.push('/wods');
        } else {
          const errorMessage = response.message || 'Erro ao criar WOD';
          showError(errorMessage);
        }
      }
    } catch (error) {
      console.error(`Erro ao ${isEditing ? 'atualizar' : 'criar'} WOD:`, error);
      const errorMessage = error?.response?.data?.message || error?.message || `Erro ao ${isEditing ? 'atualizar' : 'criar'} WOD`;
      showError(errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  const getTipoLabel = (tipo) => {
    const tipoObj = TIPOS_BLOCO.find(t => t.value === tipo);
    return tipoObj?.label || tipo;
  };

  return (
    <LayoutBase>
      {isLoading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#4CAF50" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      ) : (
        <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
          <View style={styles.headerActions}>
            <TouchableOpacity
              style={styles.backButton}
              onPress={() => router.push('/wods')}
            >
              <Feather name="arrow-left" size={18} color="#fff" />
              <Text style={styles.backButtonText}>Voltar</Text>
            </TouchableOpacity>

            <View style={[styles.statusIndicator, formData.status === 'published' ? styles.statusActive : styles.statusInactive]}>
              <View style={[styles.statusDot, formData.status === 'published' ? styles.statusDotActive : styles.statusDotInactive]} />
              <Text style={[styles.statusIndicatorText, formData.status === 'published' ? styles.statusTextActive : styles.statusTextInactive]}>
                {formData.status === 'published' ? 'WOD Publicado' : 'WOD Rascunho'}
              </Text>
            </View>
          </View>

          <View style={styles.formContainer}>
        {/* Campos Principais */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <View style={styles.sectionHeaderIcon}>
              <Feather name="file-text" size={20} color="#f97316" />
            </View>
            <Text style={styles.sectionTitle}>Informações Gerais</Text>
          </View>
          <View style={styles.sectionBody}>

          {/* Título */}
          <View style={styles.formGroup}>
            <Text style={styles.label}>
              Título <Text style={styles.required}>*</Text>
            </Text>
            <TextInput
              style={styles.input}
              placeholder="Digite o título do WOD"
              placeholderTextColor="#9ca3af"
              value={formData.titulo}
              onChangeText={(value) => handleMainChange('titulo', value)}
              editable={!isSubmitting}
            />
          </View>

          {/* Descrição */}
          <View style={styles.formGroup}>
            <Text style={styles.label}>Descrição</Text>
            <TextInput
              style={[styles.input, styles.multilineInput]}
              placeholder="Descrição do WOD (opcional)"
              placeholderTextColor="#9ca3af"
              value={formData.descricao}
              onChangeText={(value) => handleMainChange('descricao', value)}
              multiline={true}
              numberOfLines={3}
              editable={!isSubmitting}
            />
          </View>

          {/* Modalidade */}
          <View style={styles.formGroup}>
            <Text style={styles.label}>
              Modalidade <Text style={styles.required}>*</Text>
            </Text>
            <View style={styles.modalidadeGrid}>
              {modalidades.map((modalidade) => {
                const isActive = String(modalidade.id) === formData.modalidade_id;
                return (
                  <TouchableOpacity
                    key={modalidade.id}
                    style={[styles.modalidadeButton, isActive && styles.modalidadeButtonActive]}
                    onPress={() => handleMainChange('modalidade_id', String(modalidade.id))}
                    disabled={isSubmitting}
                  >
                    <Text style={[styles.modalidadeButtonText, isActive && styles.modalidadeButtonTextActive]}>
                      {modalidade.nome}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>
          </View>

          <View style={styles.inlineRow}>
            {/* Data */}
            <View style={styles.inlineItem}>
              <Text style={styles.label}>
                Data <Text style={styles.required}>*</Text>
              </Text>
              <TextInput
                style={styles.input}
                placeholder="DD/MM/AAAA"
                placeholderTextColor="#9ca3af"
                value={formData.data}
                onChangeText={(value) => handleMainChange('data', parseDateBR(value))}
                editable={!isSubmitting}
                keyboardType="numeric"
                maxLength={10}
              />
            </View>

          </View>
          </View>
        </View>

        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <View style={styles.sectionHeaderIcon}>
              <Feather name="toggle-right" size={20} color="#f97316" />
            </View>
            <Text style={styles.sectionTitle}>Status do WOD</Text>
          </View>
          <View style={styles.sectionBody}>
            <View style={styles.switchRow}>
              <View style={styles.switchInfo}>
                <Text style={styles.switchLabel}>WOD Publicado</Text>
                <Text style={styles.switchDescription}>
                  {formData.status === 'published'
                    ? 'O WOD está visível para alunos.'
                    : 'O WOD está em rascunho e não aparece para alunos.'}
                </Text>
              </View>
              <Switch
                value={formData.status === 'published'}
                onValueChange={(value) => handleMainChange('status', value ? 'published' : 'draft')}
                disabled={isSubmitting}
                trackColor={{ false: '#d1d5db', true: '#86efac' }}
                thumbColor={formData.status === 'published' ? '#22c55e' : '#9ca3af'}
              />
            </View>
          </View>
        </View>

        {/* Blocos */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <View style={styles.sectionHeaderIcon}>
              <Feather name="layers" size={20} color="#f97316" />
            </View>
            <Text style={styles.sectionTitle}>Blocos</Text>
            <TouchableOpacity
              style={styles.addButton}
              onPress={() => setBlocoModalVisible(true)}
              disabled={isSubmitting}
            >
              <Feather name="plus" size={18} color="#fff" />
              <Text style={styles.addButtonText}>Adicionar</Text>
            </TouchableOpacity>
          </View>
          <View style={styles.sectionBody}>

          <View style={styles.cardsContainer}>
            {formData.blocos.map((bloco, index) => (
              <View key={index} style={styles.cardWrapper}>
                <View style={styles.card}>
                  <View style={styles.cardHeader}>
                    <View style={styles.cardHeaderContent}>
                      <View style={styles.cardHeaderRow}>
                        <View style={styles.cardBadge}>
                          <Text style={styles.cardBadgeText}>{getTipoLabel(bloco.tipo)}</Text>
                        </View>
                        {bloco.tempo_cap && (
                          <View style={styles.timeChip}>
                            <Feather name="clock" size={12} color="#9a3412" />
                            <Text style={styles.timeChipText}>{bloco.tempo_cap}</Text>
                          </View>
                        )}
                        <View style={styles.cardOrderChip}>
                          <Text style={styles.cardOrderText}>Bloco {index + 1}</Text>
                        </View>
                      </View>
                    </View>
                  </View>
                  
                  <View style={styles.cardContent}>
                    {bloco.titulo ? (
                      <View style={styles.cardContentHeader}>
                        <Text style={styles.cardTitle}>{bloco.titulo}</Text>
                      </View>
                    ) : null}
                    <Text style={styles.cardDescription} numberOfLines={3}>
                      {bloco.conteudo}
                    </Text>
                  </View>

                  <View style={styles.cardFooter}>
                    <View style={styles.cardActions}>
                      <TouchableOpacity
                        onPress={() => handleEditBloco(index)}
                        disabled={isSubmitting}
                        style={styles.iconButton}
                      >
                        <Feather name="edit-2" size={16} color="#3b82f6" />
                      </TouchableOpacity>
                      {index > 0 && (
                        <TouchableOpacity
                          onPress={() => moveBlockUp(index)}
                          disabled={isSubmitting}
                          style={styles.iconButton}
                        >
                          <Feather name="arrow-up" size={16} color="#f97316" />
                        </TouchableOpacity>
                      )}
                      {index < formData.blocos.length - 1 && (
                        <TouchableOpacity
                          onPress={() => moveBlockDown(index)}
                          disabled={isSubmitting}
                          style={styles.iconButton}
                        >
                          <Feather name="arrow-down" size={16} color="#f97316" />
                        </TouchableOpacity>
                      )}
                      <TouchableOpacity
                        onPress={() => removeBloco(index)}
                        disabled={isSubmitting}
                        style={styles.iconButton}
                      >
                        <Feather name="trash-2" size={16} color="#ef4444" />
                      </TouchableOpacity>
                    </View>
                  </View>
                </View>
              </View>
            ))}
          </View>
          </View>
        </View>

        {/* Variações */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <View style={styles.sectionHeaderIcon}>
              <Feather name="repeat" size={20} color="#f97316" />
            </View>
            <Text style={styles.sectionTitle}>Variações</Text>
            <TouchableOpacity
              style={styles.addButton}
              onPress={() => setVariacaoModalVisible(true)}
              disabled={isSubmitting}
            >
              <Feather name="plus" size={18} color="#fff" />
              <Text style={styles.addButtonText}>Adicionar</Text>
            </TouchableOpacity>
          </View>
          <View style={styles.sectionBody}>

          <View style={styles.cardsContainer}>
            {formData.variacoes.map((variacao, index) => (
              <View key={index} style={styles.cardWrapper}>
                <View style={styles.card}>
                  <View style={styles.cardHeader}>
                    <Text style={styles.cardHeaderTitle}>
                      {variacao.nome}
                    </Text>
                  </View>

                  <View style={styles.cardContent}>
                    {variacao.descricao && (
                      <Text style={styles.cardDescription} numberOfLines={4}>
                        {variacao.descricao}
                      </Text>
                    )}
                  </View>

                  <View style={styles.cardFooter}>
                    <View style={styles.cardActions}>
                      <TouchableOpacity
                        onPress={() => handleEditVariacao(index)}
                        disabled={isSubmitting}
                        style={styles.iconButton}
                      >
                        <Feather name="edit-2" size={16} color="#3b82f6" />
                      </TouchableOpacity>
                      <TouchableOpacity
                        onPress={() => removeVariacao(index)}
                        disabled={isSubmitting}
                        style={styles.iconButton}
                      >
                        <Feather name="trash-2" size={16} color="#ef4444" />
                      </TouchableOpacity>
                    </View>
                  </View>
                </View>
              </View>
            ))}
          </View>
          </View>
        </View>

        {/* Botões de Ação */}
        <View style={styles.formActions}>
          <TouchableOpacity
            style={[styles.button, styles.cancelButton]}
            onPress={() => router.push('/wods')}
            disabled={isSubmitting}
          >
            <Text style={styles.buttonText}>Cancelar</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.button, styles.submitButton, isSubmitting && { opacity: 0.6 }]}
            onPress={handleSubmit}
            disabled={isSubmitting}
          >
            {isSubmitting ? (
              <ActivityIndicator color="#fff" size="small" />
            ) : (
              <Text style={styles.submitButtonText}>
                {isEditing ? 'Atualizar WOD' : 'Criar WOD'}
              </Text>
            )}
          </TouchableOpacity>
        </View>
        </View>
      </ScrollView>
      )}

      {/* Modal de Preview */}
      <WodPreviewModal
        visible={previewVisible}
        onClose={() => setPreviewVisible(false)}
        wodData={formData}
      />

      {/* Modal de Adicionar Bloco */}
      <AdicionarBlocoModal
        visible={blocoModalVisible}
        onClose={() => {
          setBlocoModalVisible(false);
          setEditandoBlocoIndex(null);
        }}
        onAdd={handleAddBloco}
        blocoExistente={editandoBlocoIndex !== null ? formData.blocos[editandoBlocoIndex] : null}
      />

      {/* Modal de Adicionar Variação */}
      <AdicionarVariacaoModal
        visible={variacaoModalVisible}
        onClose={() => {
          setVariacaoModalVisible(false);
          setEditandoVariacaoIndex(null);
        }}
        onAdd={handleAddVariacao}
        variacaoExistente={editandoVariacaoIndex !== null ? formData.variacoes[editandoVariacaoIndex] : null}
      />
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f3f4f6',
  },
  loadingText: {
    marginTop: 16,
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
    paddingBottom: 32,
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
  formGroup: {
    marginBottom: 16,
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
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 14,
    color: '#1f2937',
    backgroundColor: '#fff',
  },
  multilineInput: {
    paddingTop: 8,
    textAlignVertical: 'top',
  },
  inlineRow: {
    flexDirection: 'row',
    gap: 12,
  },
  inlineItem: {
    flex: 1,
  },
  modalidadeGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  modalidadeButton: {
    paddingHorizontal: 14,
    paddingVertical: 9,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    backgroundColor: '#f1f5f9',
  },
  modalidadeButtonActive: {
    borderColor: '#f97316',
    backgroundColor: '#f97316',
    shadowColor: '#9a3412',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.12,
    shadowRadius: 6,
    elevation: 3,
  },
  modalidadeButtonText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#64748b',
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  modalidadeButtonTextActive: {
    color: '#fff',
  },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
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
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f97316',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 6,
    gap: 6,
    marginLeft: 'auto',
  },
  addButtonText: {
    color: '#fff',
    fontWeight: '500',
    fontSize: 13,
  },
  card: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#f1f5f9',
    borderRadius: 16,
    padding: 16,
    overflow: 'hidden',
    height: '100%',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.08,
    shadowRadius: 18,
    elevation: 4,
  },
  cardWrapper: {
    flex: 1,
    minWidth: '32%',
    flexBasis: '32%',
    marginBottom: 0,
  },
  cardsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    columnGap: 16,
    rowGap: 16,
  },
  cardBadge: {
    backgroundColor: '#0f172a',
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
  },
  cardBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#fff7ed',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  cardContent: {
    flex: 1,
    marginBottom: 0,
    backgroundColor: '#f8fafc',
    marginHorizontal: -16,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  cardContentHeader: {
    paddingHorizontal: 0,
    paddingVertical: 0,
    marginBottom: 8,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 2,
    lineHeight: 22,
  },
  timeChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    alignSelf: 'flex-start',
    backgroundColor: '#fff7ed',
    borderWidth: 1,
    borderColor: '#fed7aa',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  timeChipText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#9a3412',
  },
  cardDescription: {
    fontSize: 13,
    color: '#374151',
    lineHeight: 19,
  },
  cardHeader: {
    marginHorizontal: -16,
    marginTop: -16,
    marginBottom: 0,
    paddingHorizontal: 16,
    paddingTop: 14,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
    backgroundColor: '#ffffff',
  },
  cardHeaderContent: {
    gap: 6,
  },
  sectionCard: {
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
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
    backgroundColor: '#fff7ed',
  },
  sectionHeaderIcon: {
    width: 36,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#fed7aa',
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1f2937',
  },
  sectionBody: {
    padding: 16,
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  cardHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  cardHeaderTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1f2937',
  },
  cardFooter: {
    marginHorizontal: -16,
    marginBottom: -16,
    marginTop: 0,
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 12,
    borderTopWidth: 1,
    borderTopColor: '#e2e8f0',
    backgroundColor: '#ffffff',
  },
  cardActions: {
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'flex-end',
  },
  iconButton: {
    padding: 9,
    borderRadius: 12,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 6,
    elevation: 2,
  },
  cardOrderChip: {
    backgroundColor: '#fff7ed',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderWidth: 1,
    borderColor: '#fed7aa',
  },
  cardOrderText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#9a3412',
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  cardMetaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  tipoScroll: {
    marginHorizontal: -12,
    paddingHorizontal: 12,
  },
  tipoButton: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 6,
    marginRight: 8,
    backgroundColor: '#fff',
  },
  tipoButtonActive: {
    backgroundColor: '#f97316',
    borderColor: '#f97316',
  },
  tipoButtonText: {
    fontSize: 12,
    fontWeight: '500',
    color: '#6b7280',
  },
  tipoButtonTextActive: {
    color: '#fff',
  },
  formActions: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 16,
    paddingBottom: 32,
  },
  button: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelButton: {
    backgroundColor: '#f3f4f6',
  },
  buttonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
  },
  submitButton: {
    backgroundColor: '#f97316',
  },
  submitButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#fff',
  },
});
