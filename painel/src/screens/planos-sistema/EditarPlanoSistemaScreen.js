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
  useWindowDimensions,
  Platform,
} from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { Feather } from '@expo/vector-icons';
import planosSistemaService from '../../services/planosSistemaService';
import LayoutBase from '../../components/LayoutBase';
import LoadingOverlay from '../../components/LoadingOverlay';
import { showSuccess, showError } from '../../utils/toast';
import { authService } from '../../services/authService';

export default function EditarPlanoSistemaScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams();
  const { width } = useWindowDimensions();
  const isMobile = width < 768;
  
  // Validar se o ID foi passado corretamente
  const planoId = id ? parseInt(id) : null;
  
  // Se não tiver ID, redirecionar
  useEffect(() => {
    if (!planoId || isNaN(planoId)) {
      console.error('❌ ID do plano não informado');
      router.replace('/planos-sistema');
      return;
    }
    checkAccess();
  }, [planoId]);

  const checkAccess = async () => {
    const user = await authService.getCurrentUser();
    if (!user || user.papel_id !== 4) {
      showError('Acesso negado. Apenas Super Admin pode acessar esta página.');
      router.replace('/');
      return;
    }
  };

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [academias, setAcademias] = useState([]);
  const [loadingAcademias, setLoadingAcademias] = useState(false);
  const [formData, setFormData] = useState({
    nome: '',
    descricao: '',
    valor: '',
    duracao_dias: '30',
    max_alunos: '',
    max_admins: '1',
    ativo: true,
    atual: true,
    ordem: '0',
  });

  useEffect(() => {
    if (planoId && !isNaN(planoId)) {
      loadData();
      loadAcademias();
    }
  }, [planoId]);

  const loadData = async () => {
    try {
      setLoading(true);
      const data = await planosSistemaService.buscarPorId(planoId);

      setFormData({
        nome: data.nome || '',
        descricao: data.descricao || '',
        valor: data.valor ? data.valor.toString() : '',
        duracao_dias: data.duracao_dias ? data.duracao_dias.toString() : '30',
        max_alunos: data.max_alunos ? data.max_alunos.toString() : '',
        max_admins: data.max_admins ? data.max_admins.toString() : '1',
        ativo: data.ativo === 1 || data.ativo === true,
        atual: data.atual === 1 || data.atual === true,
        ordem: data.ordem ? data.ordem.toString() : '0',
      });
    } catch (error) {
      showError('Não foi possível carregar os dados do plano');
      router.back();
    } finally {
      setLoading(false);
    }
  };

  const loadAcademias = async () => {
    // Validar planoId antes de fazer requisição
    if (!planoId || isNaN(planoId)) {
      console.warn('⚠️ Plano ID inválido, pulando carregamento de academias');
      return;
    }
    
    try {
      setLoadingAcademias(true);
      const response = await planosSistemaService.listarAcademias(planoId);
      setAcademias(response.academias || []);
    } catch (error) {
      console.error('❌ Erro ao carregar academias:', error);
      showError('Não foi possível carregar as academias associadas');
    } finally {
      setLoadingAcademias(false);
    }
  };

  const handleChange = (field, value) => {
    setFormData({ ...formData, [field]: value });
    if (errors[field]) {
      setErrors({ ...errors, [field]: null });
    }
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.nome.trim()) {
      newErrors.nome = 'Nome do plano é obrigatório';
    }

    if (!formData.valor || parseFloat(formData.valor) < 0) {
      newErrors.valor = 'Valor deve ser maior ou igual a zero';
    }

    if (!formData.max_alunos || parseInt(formData.max_alunos) < 1) {
      newErrors.max_alunos = 'Capacidade de alunos deve ser maior que zero';
    }

    if (!formData.max_admins || parseInt(formData.max_admins) < 1) {
      newErrors.max_admins = 'Quantidade de admins deve ser maior que zero';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    setSaving(true);

    try {
      const dataToSend = {
        ...formData,
        valor: parseFloat(formData.valor),
        duracao_dias: parseInt(formData.duracao_dias),
        max_alunos: formData.max_alunos ? parseInt(formData.max_alunos) : null,
        max_admins: formData.max_admins ? parseInt(formData.max_admins) : 1,
        ordem: formData.ordem ? parseInt(formData.ordem) : 0,
      };

      const result = await planosSistemaService.atualizar(planoId, dataToSend);
      showSuccess(result.message || 'Plano atualizado com sucesso');
      router.push('/planos-sistema');
    } catch (error) {
      showError(error.message || error.error || 'Não foi possível atualizar o plano');
    } finally {
      setSaving(false);
    }
  };

  const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  };

  const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('pt-BR');
  };

  const getStatusBadgeStyle = (status) => {
    switch (status) {
      case 'ativo':
        return styles.statusActive;
      case 'inativo':
        return styles.statusInactive;
      case 'cancelado':
        return styles.statusCanceled;
      default:
        return styles.statusDefault;
    }
  };

  const getStatusText = (status) => {
    switch (status) {
      case 'ativo':
        return 'Ativo';
      case 'inativo':
        return 'Inativo';
      case 'cancelado':
        return 'Cancelado';
      default:
        return status;
    }
  };

  const renderAcademiasTable = () => {
    if (loadingAcademias) {
      return (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando academias...</Text>
        </View>
      );
    }

    if (academias.length === 0) {
      return (
        <View style={styles.emptyContainer}>
          <Feather name="inbox" size={48} color="#ccc" />
          <Text style={styles.emptyText}>Nenhuma academia associada a este plano</Text>
        </View>
      );
    }

    if (isMobile) {
      return (
        <View style={styles.mobileCards}>
          {academias.map((academia) => (
            <View key={academia.id} style={styles.mobileCard}>
              <View style={styles.mobileCardHeader}>
                <Text style={styles.mobileCardTitle}>{academia.nome}</Text>
                <View style={[styles.statusBadge, getStatusBadgeStyle(academia.status_contrato)]}>
                  <Text style={styles.statusBadgeText}>{getStatusText(academia.status_contrato)}</Text>
                </View>
              </View>
              <View style={styles.mobileCardBody}>
                <View style={styles.mobileCardRow}>
                  <Feather name="mail" size={14} color="#666" />
                  <Text style={styles.mobileCardText}>{academia.email || '-'}</Text>
                </View>
                {academia.cnpj && (
                  <View style={styles.mobileCardRow}>
                    <Feather name="file-text" size={14} color="#666" />
                    <Text style={styles.mobileCardText}>{academia.cnpj}</Text>
                  </View>
                )}
                {/* <View style={styles.mobileCardRow}>
                  <Feather name="calendar" size={14} color="#666" />
                  <Text style={styles.mobileCardText}>
                    {formatDate(academia.data_inicio)} - {formatDate(academia.data_vencimento)}
                  </Text>
                </View> */}
              </View>
            </View>
          ))}
        </View>
      );
    }

    return (
      <View style={styles.tableContainer}>
        <View style={styles.table}>
          <View style={styles.tableHeader}>
            <Text style={[styles.tableHeaderText, { flex: 2 }]}>Academia</Text>
            <Text style={[styles.tableHeaderText, { flex: 1.5 }]}>CNPJ</Text>
            <Text style={[styles.tableHeaderText, { flex: 1.5 }]}>E-mail</Text>
            <Text style={[styles.tableHeaderText, { flex: 1 }]}>Status</Text>
            <Text style={[styles.tableHeaderText, { flex: 1.2 }]}>Início</Text>
            {/* <Text style={[styles.tableHeaderText, { flex: 1.2 }]}>Vencimento</Text> */}
          </View>
          {academias.map((academia, index) => (
            <View 
              key={academia.id} 
              style={[
                styles.tableRow,
                index % 2 === 0 && styles.tableRowEven
              ]}
            >
              <Text style={[styles.tableCell, { flex: 2 }]}>{academia.nome}</Text>
              <Text style={[styles.tableCell, { flex: 1.5 }]}>{academia.cnpj || '-'}</Text>
              <Text style={[styles.tableCell, { flex: 1.5 }]} numberOfLines={1}>{academia.email || '-'}</Text>
              <View style={[styles.tableCell, { flex: 1 }]}>
                <View style={[styles.statusBadge, getStatusBadgeStyle(academia.status_contrato)]}>
                  <Text style={styles.statusBadgeText}>{getStatusText(academia.status_contrato)}</Text>
                </View>
              </View>
              <Text style={[styles.tableCell, { flex: 1.2 }]}>{formatDate(academia.data_inicio)}</Text>
            {/* <Text style={[styles.tableCell, { flex: 1.2 }]}>{formatDate(academia.data_vencimento)}</Text> */}
            </View>
          ))}
        </View>
      </View>
    );
  };

  if (loading) {
    return (
      <LayoutBase title="Editar Plano do Sistema" noPadding>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Editar Plano do Sistema" noPadding>
      {saving && <LoadingOverlay message="Atualizando plano..." />}

      <View style={styles.container}>
        {/* Banner Header */}
        <View style={styles.bannerContainer}>
          <View style={styles.banner}>
            <View style={styles.bannerContent}>
              <TouchableOpacity onPress={() => router.push('/planos-sistema')} style={styles.backButtonBanner}>
                <Feather name="arrow-left" size={24} color="#fff" />
              </TouchableOpacity>
              <View style={styles.bannerIconContainer}>
                <View style={styles.bannerIconOuter}>
                  <View style={styles.bannerIconInner}>
                    <Feather name="edit-3" size={28} color="#fff" />
                  </View>
                </View>
              </View>
              <View style={styles.bannerTextContainer}>
                <Text style={styles.bannerTitle}>Editar Plano</Text>
                <Text style={styles.bannerSubtitle} numberOfLines={1}>
                  {formData.nome || 'Atualizar informações do plano'}
                </Text>
              </View>
            </View>
            <View style={styles.bannerDecoration}>
              <View style={styles.decorCircle1} />
              <View style={styles.decorCircle2} />
              <View style={styles.decorCircle3} />
            </View>
          </View>

          {/* Card de Resumo */}
          <View style={[styles.summaryCard, isMobile && styles.summaryCardMobile]}>
            <View style={styles.summaryCardHeader}>
              <View style={styles.summaryCardInfo}>
                <View style={styles.summaryCardIconContainer}>
                  <Feather name="layers" size={20} color="#f97316" />
                </View>
                <View>
                  <Text style={styles.summaryCardTitle}>Plano #{planoId}</Text>
                  <Text style={styles.summaryCardSubtitle}>
                    {academias.length} {academias.length === 1 ? 'academia' : 'academias'} associadas
                  </Text>
                </View>
              </View>
              <View style={[styles.statusBadgeLarge, { backgroundColor: formData.ativo ? '#10b981' : '#ef4444' }]}>
                <Text style={styles.statusTextLarge}>{formData.ativo ? 'ATIVO' : 'INATIVO'}</Text>
              </View>
            </View>
          </View>
        </View>

        <ScrollView style={styles.scrollContent} contentContainerStyle={styles.scrollContentContainer}>
          {/* Card do Formulário */}
          <View style={styles.card}>
            <View style={styles.cardTitleRow}>
              <View style={styles.cardTitleIcon}>
                <Feather name="edit-3" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Informações do Plano</Text>
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Nome do Plano *</Text>
              <TextInput
                style={[styles.input, errors.nome && styles.inputError]}
                placeholder="Ex: Plano Premium"
                placeholderTextColor="#9ca3af"
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
                placeholderTextColor="#9ca3af"
                value={formData.descricao}
                onChangeText={(value) => handleChange('descricao', value)}
                multiline
                numberOfLines={3}
                editable={!saving}
              />
            </View>

            <View style={styles.row}>
              <View style={[styles.inputGroup, { flex: 1, marginRight: 12 }]}>
                <Text style={styles.label}>Valor Mensal (R$) *</Text>
                <TextInput
                  style={[styles.input, errors.valor && styles.inputError]}
                  placeholder="199.90"
                  placeholderTextColor="#9ca3af"
                  value={formData.valor}
                  onChangeText={(value) => handleChange('valor', value)}
                  keyboardType="decimal-pad"
                  editable={!saving}
                />
                {errors.valor && <Text style={styles.errorText}>{errors.valor}</Text>}
              </View>

              <View style={[styles.inputGroup, { flex: 1 }]}>
                <Text style={styles.label}>Duração (dias)</Text>
                <TextInput
                  style={styles.input}
                  placeholder="30"
                  placeholderTextColor="#9ca3af"
                  value={formData.duracao_dias}
                  onChangeText={(value) => handleChange('duracao_dias', value)}
                  keyboardType="number-pad"
                  editable={!saving}
                />
              </View>
            </View>

            <View style={styles.row}>
              <View style={[styles.inputGroup, { flex: 1, marginRight: 12 }]}>
                <Text style={styles.label}>Capacidade de Alunos *</Text>
                <TextInput
                  style={[styles.input, errors.max_alunos && styles.inputError]}
                  placeholder="Ex: 50, 100, 200"
                  placeholderTextColor="#9ca3af"
                  value={formData.max_alunos}
                  onChangeText={(value) => handleChange('max_alunos', value)}
                  keyboardType="number-pad"
                  editable={!saving}
                />
                {errors.max_alunos && <Text style={styles.errorText}>{errors.max_alunos}</Text>}
              </View>

              <View style={[styles.inputGroup, { flex: 1 }]}>
                <Text style={styles.label}>Quantidade de Admins *</Text>
                <TextInput
                  style={[styles.input, errors.max_admins && styles.inputError]}
                  placeholder="Ex: 1, 2, 5"
                  placeholderTextColor="#9ca3af"
                  value={formData.max_admins}
                  onChangeText={(value) => handleChange('max_admins', value)}
                  keyboardType="number-pad"
                  editable={!saving}
                />
                {errors.max_admins && <Text style={styles.errorText}>{errors.max_admins}</Text>}
              </View>
            </View>

            <View style={styles.inputGroup}>
              <Text style={styles.label}>Ordem de Exibição</Text>
              <TextInput
                style={styles.input}
                placeholder="0"
                placeholderTextColor="#9ca3af"
                value={formData.ordem}
                onChangeText={(value) => handleChange('ordem', value)}
                keyboardType="number-pad"
                editable={!saving}
              />
            </View>

            <View style={styles.switchRowContainer}>
              <View style={[styles.inputGroup, { flex: 1, marginRight: 12 }]}>
                <View style={styles.switchRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.label}>Plano Atual</Text>
                    <Text style={styles.switchSubtext}>
                      {formData.atual ? 'Disponível para novos contratos' : 'Apenas para contratos existentes'}
                    </Text>
                  </View>
                  <Switch
                    value={formData.atual}
                    onValueChange={(value) => handleChange('atual', value)}
                    disabled={saving}
                    trackColor={{ false: '#d1d5db', true: '#10b981' }}
                    thumbColor={formData.atual ? '#fff' : '#f3f4f6'}
                  />
                </View>
              </View>

              <View style={[styles.inputGroup, { flex: 1 }]}>
                <View style={styles.switchRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.label}>Status</Text>
                    <Text style={styles.switchSubtext}>
                      {formData.ativo ? 'Plano ativo no sistema' : 'Plano inativo'}
                    </Text>
                  </View>
                  <Switch
                    value={formData.ativo}
                    onValueChange={(value) => handleChange('ativo', value)}
                    disabled={saving}
                    trackColor={{ false: '#d1d5db', true: '#10b981' }}
                    thumbColor={formData.ativo ? '#fff' : '#f3f4f6'}
                  />
                </View>
              </View>
            </View>

            {/* Submit Button */}
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
                  <Text style={styles.submitButtonText}>Salvar Alterações</Text>
                </>
              )}
            </TouchableOpacity>
          </View>

          {/* Academias Associadas */}
          <View style={styles.card}>
            <View style={styles.cardTitleRow}>
              <View style={styles.cardTitleIcon}>
                <Feather name="briefcase" size={20} color="#f97316" />
              </View>
              <Text style={styles.cardTitle}>Academias Associadas</Text>
              <View style={styles.badgeCount}>
                <Text style={styles.badgeCountText}>{academias.length}</Text>
              </View>
            </View>
            {renderAcademiasTable()}
          </View>
        </ScrollView>
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 100,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#6b7280',
    fontWeight: '500',
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
    gap: 14,
    zIndex: 2,
  },
  backButtonBanner: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    justifyContent: 'center',
    alignItems: 'center',
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
  // Summary Card
  summaryCard: {
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
  summaryCardMobile: {
    marginHorizontal: 16,
    padding: 16,
  },
  summaryCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    flexWrap: 'wrap',
  },
  summaryCardInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  summaryCardIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: 'rgba(249, 115, 22, 0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  summaryCardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1f2937',
  },
  summaryCardSubtitle: {
    fontSize: 13,
    color: '#6b7280',
    marginTop: 2,
  },
  statusBadgeLarge: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
  },
  statusTextLarge: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  // Scroll Content
  scrollContent: {
    flex: 1,
  },
  scrollContentContainer: {
    padding: 20,
    gap: 16,
  },
  // Card Base
  card: {
    backgroundColor: '#ffffff',
    borderRadius: 14,
    padding: 20,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    ...Platform.select({
      web: {
        boxShadow: '0 8px 24px rgba(15,23,42,0.1)',
      },
    }),
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 16,
    paddingBottom: 12,
    borderBottomWidth: 2,
    borderBottomColor: '#f97316',
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#111827',
    flex: 1,
  },
  cardTitleIcon: {
    width: 40,
    height: 40,
    borderRadius: 10,
    backgroundColor: 'rgba(249,115,22,0.12)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeCount: {
    backgroundColor: '#f97316',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 4,
    minWidth: 32,
    alignItems: 'center',
  },
  badgeCountText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '700',
  },
  // Form
  form: {
    backgroundColor: '#ffffff',
    borderRadius: 14,
    padding: 20,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    ...Platform.select({
      web: {
        boxShadow: '0 8px 24px rgba(15,23,42,0.1)',
      },
    }),
  },
  row: {
    flexDirection: 'row',
  },
  inputGroup: {
    marginBottom: 18,
  },
  switchRowContainer: {
    flexDirection: 'row',
    marginBottom: 10,
  },
  label: {
    fontSize: 13,
    fontWeight: '700',
    color: '#374151',
    marginBottom: 8,
    textTransform: 'uppercase',
    letterSpacing: 0.3,
  },
  input: {
    backgroundColor: '#f9fafb',
    borderWidth: 2,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    padding: 14,
    fontSize: 15,
    color: '#1f2937',
  },
  inputError: {
    borderColor: '#ef4444',
  },
  errorText: {
    color: '#ef4444',
    fontSize: 12,
    marginTop: 6,
    fontWeight: '500',
  },
  textArea: {
    minHeight: 90,
    textAlignVertical: 'top',
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    borderRadius: 10,
    padding: 14,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  switchSubtext: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 4,
  },
  submitButton: {
    backgroundColor: '#f97316',
    paddingVertical: 16,
    paddingHorizontal: 24,
    borderRadius: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    marginTop: 8,
  },
  submitButtonDisabled: {
    backgroundColor: '#fdba74',
    opacity: 0.7,
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
  },
  emptyText: {
    marginTop: 10,
    fontSize: 16,
    color: '#9ca3af',
  },
  // Tabela Desktop
  tableContainer: {
    borderRadius: 10,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  table: {
    width: '100%',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f8fafc',
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderBottomWidth: 2,
    borderBottomColor: '#e5e7eb',
  },
  tableHeaderText: {
    color: '#6b7280',
    fontWeight: '700',
    fontSize: 11,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  tableRow: {
    flexDirection: 'row',
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
    alignItems: 'center',
  },
  tableRowEven: {
    backgroundColor: '#f9fafb',
  },
  tableCell: {
    fontSize: 13,
    color: '#1f2937',
  },
  // Cards Mobile
  mobileCards: {
    gap: 12,
  },
  mobileCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  mobileCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  mobileCardTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1f2937',
    flex: 1,
    marginRight: 10,
  },
  mobileCardBody: {
    gap: 8,
  },
  mobileCardRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  mobileCardText: {
    fontSize: 13,
    color: '#6b7280',
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
    alignSelf: 'flex-start',
  },
  statusBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#fff',
  },
  statusActive: {
    backgroundColor: '#10b981',
  },
  statusInactive: {
    backgroundColor: '#6b7280',
  },
  statusCanceled: {
    backgroundColor: '#ef4444',
  },
  statusDefault: {
    backgroundColor: '#94a3b8',
  },
});
