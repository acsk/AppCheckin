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
  const planoId = parseInt(id);

  useEffect(() => {
    checkAccess();
  }, []);

  const checkAccess = async () => {
    const user = await authService.getCurrentUser();
    if (!user || user.role_id !== 3) {
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
    loadData();
    loadAcademias();
  }, []);

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
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#f97316" />
        <Text style={styles.loadingText}>Carregando...</Text>
      </View>
    );
  }

  return (
    <LayoutBase title="Editar Plano do Sistema" subtitle="Atualizar informações do plano">
      {saving && <LoadingOverlay message="Atualizando plano..." />}

      <ScrollView style={styles.scrollView} showsVerticalScrollIndicator={false}>
        <View style={styles.container}>
          {/* Form */}
          <View style={styles.form}>
            <View style={styles.inputGroup}>
              <Text style={styles.label}>Nome do Plano *</Text>
              <TextInput
                style={[styles.input, errors.nome && styles.inputError]}
                placeholder="Ex: Plano Premium"
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
                <Text style={styles.submitButtonText}>Salvar Alterações</Text>
              )}
            </TouchableOpacity>
          </View>

          {/* Academias Associadas */}
          <View style={styles.academiasSection}>
            <View style={styles.sectionHeader}>
              <Feather name="briefcase" size={20} color="#f97316" />
              <Text style={styles.sectionTitle}>Academias Associadas</Text>
              <View style={styles.badge}>
                <Text style={styles.badgeText}>{academias.length}</Text>
              </View>
            </View>
            {renderAcademiasTable()}
          </View>
        </View>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  scrollView: {
    flex: 1,
  },
  container: {
    flex: 1,
    paddingBottom: 40,
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
  form: {
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: 12,
    padding: 20,
    marginHorizontal: 20,
    marginTop: 10,
    marginBottom: 20,
  },
  row: {
    flexDirection: 'row',
  },
  inputGroup: {
    marginBottom: 20,
  },
  switchRowContainer: {
    flexDirection: 'row',
    marginBottom: 10,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2b1a04',
    marginBottom: 8,
  },
  input: {
    backgroundColor: 'rgba(255,255,255,0.9)',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.2)',
    borderRadius: 10,
    padding: 12,
    fontSize: 16,
    color: '#2b1a04',
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
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  switchSubtext: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 4,
  },
  submitButton: {
    backgroundColor: '#f97316',
    padding: 16,
    borderRadius: 10,
    alignItems: 'center',
    marginTop: 10,
    marginBottom: 20,
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
  academiasSection: {
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: 12,
    padding: 20,
    marginHorizontal: 20,
    marginBottom: 20,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 20,
    paddingBottom: 15,
    borderBottomWidth: 2,
    borderBottomColor: 'rgba(43,26,4,0.1)',
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2b1a04',
    marginLeft: 10,
    flex: 1,
  },
  badge: {
    backgroundColor: '#f97316',
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 4,
    minWidth: 30,
    alignItems: 'center',
  },
  badgeText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: 'bold',
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
  },
  emptyText: {
    marginTop: 10,
    fontSize: 16,
    color: '#999',
  },
  // Tabela Desktop
  tableContainer: {
    borderRadius: 8,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.15)',
  },
  table: {
    width: '100%',
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: '#f97316',
    paddingVertical: 12,
    paddingHorizontal: 10,
  },
  tableHeaderText: {
    color: '#fff',
    fontWeight: 'bold',
    fontSize: 13,
    textAlign: 'left',
  },
  tableRow: {
    flexDirection: 'row',
    paddingVertical: 12,
    paddingHorizontal: 10,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(43,26,4,0.1)',
    alignItems: 'center',
  },
  tableRowEven: {
    backgroundColor: 'rgba(249, 115, 22, 0.05)',
  },
  tableCell: {
    fontSize: 13,
    color: '#2b1a04',
    textAlign: 'left',
  },
  // Cards Mobile
  mobileCards: {
    gap: 12,
  },
  mobileCard: {
    backgroundColor: 'rgba(255, 255, 255, 0.95)',
    borderRadius: 10,
    padding: 15,
    borderWidth: 1,
    borderColor: 'rgba(43,26,4,0.15)',
  },
  mobileCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(43,26,4,0.1)',
  },
  mobileCardTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2b1a04',
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
    color: '#666',
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
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
