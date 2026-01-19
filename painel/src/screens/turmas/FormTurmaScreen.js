import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, TextInput, ActivityIndicator, Switch, Modal } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';
import { turmaService } from '../../services/turmaService';
import { professorService } from '../../services/professorService';
import { modalidadeService } from '../../services/modalidadeService';
import { showSuccess, showError } from '../../utils/toast';
import LayoutBase from '../../components/LayoutBase';

// Simular dias da semana e horários (você pode buscar da API se tiver)
const DIAS = [
  { id: 1, nome: 'Segunda-feira' },
  { id: 2, nome: 'Terça-feira' },
  { id: 3, nome: 'Quarta-feira' },
  { id: 4, nome: 'Quinta-feira' },
  { id: 5, nome: 'Sexta-feira' },
  { id: 6, nome: 'Sábado' }
];

const HORARIOS = [
  { id: 1, hora: '06:00:00' },
  { id: 2, hora: '07:00:00' },
  { id: 3, hora: '08:00:00' },
  { id: 4, hora: '09:00:00' },
  { id: 5, hora: '10:00:00' },
  { id: 6, hora: '11:00:00' },
  { id: 7, hora: '14:00:00' },
  { id: 8, hora: '15:00:00' },
  { id: 9, hora: '16:00:00' },
  { id: 10, hora: '17:00:00' },
  { id: 11, hora: '18:00:00' },
  { id: 12, hora: '19:00:00' },
  { id: 13, hora: '20:00:00' }
];

export default function FormTurmaScreen({ turmaId }) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [turma, setTurma] = useState(null);
  const [professores, setProfessores] = useState([]);
  const [modalidades, setModalidades] = useState([]);
  const [showModalContinuar, setShowModalContinuar] = useState(false);
  
  const [formData, setFormData] = useState({
    nome: '',
    professor_id: '',
    modalidade_id: '',
    dia_id: '',
    horario_id: '',
    limite_alunos: '20',
    ativo: true
  });

  useEffect(() => {
    carregarDadosIniciais();
  }, []);

  useEffect(() => {
    if (turmaId) {
      carregarTurma();
    }
  }, [turmaId]);

  useEffect(() => {
    console.log('🔍 Estado do modal atualizado:', { showModalContinuar, submitting });
  }, [showModalContinuar]);

  const carregarDadosIniciais = async () => {
    try {
      setLoading(true);
      const [professoresData, modalidadesData] = await Promise.all([
        professorService.listar(),
        modalidadeService.listar()
      ]);
      
      setProfessores(professoresData);
      setModalidades(modalidadesData);
    } catch (error) {
      showError('Erro ao carregar dados iniciais');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const carregarTurma = async () => {
    try {
      const data = await turmaService.buscarPorId(turmaId);
      setTurma(data);
      setFormData({
        nome: data.nome || '',
        professor_id: String(data.professor_id || ''),
        modalidade_id: String(data.modalidade_id || ''),
        dia_id: String(data.dia_id || ''),
        horario_id: String(data.horario_id || ''),
        limite_alunos: String(data.limite_alunos || '20'),
        ativo: data.ativo ?? true
      });
    } catch (error) {
      showError('Erro ao carregar turma');
      console.error(error);
      setTimeout(() => router.back(), 1500);
    }
  };

  const validarFormulario = () => {
    if (!formData.nome.trim()) {
      showError('Nome da turma é obrigatório');
      return false;
    }

    if (!formData.professor_id) {
      showError('Selecione um professor');
      return false;
    }

    if (!formData.modalidade_id) {
      showError('Selecione uma modalidade');
      return false;
    }

    if (!formData.dia_id) {
      showError('Selecione um dia');
      return false;
    }

    if (!formData.horario_id) {
      showError('Selecione um horário');
      return false;
    }

    const limite = parseInt(formData.limite_alunos);
    if (isNaN(limite) || limite <= 0) {
      showError('Limite de alunos deve ser um número maior que 0');
      return false;
    }

    return true;
  };

  const incrementarProximaHora = () => {
    const horarioAtualId = parseInt(formData.horario_id);
    const proximoHorarioId = horarioAtualId < HORARIOS.length ? horarioAtualId + 1 : horarioAtualId;
    return String(proximoHorarioId);
  };

  const handleContinuarCriando = () => {
    const novoHorarioId = incrementarProximaHora();
    console.log('🔄 Continuando criação - novo horário:', novoHorarioId);
    setShowModalContinuar(false);
    setFormData({
      ...formData,
      horario_id: novoHorarioId
    });
    setSubmitting(false);
  };

  const handleVoltarListagem = () => {
    console.log('🔙 Voltando para listagem');
    setShowModalContinuar(false);
    setTimeout(() => router.back(), 300);
  };

  const handleSubmit = async () => {
    if (!validarFormulario()) return;

    try {
      setSubmitting(true);
      const dados = {
        ...formData,
        professor_id: parseInt(formData.professor_id),
        modalidade_id: parseInt(formData.modalidade_id),
        dia_id: parseInt(formData.dia_id),
        horario_id: parseInt(formData.horario_id),
        limite_alunos: parseInt(formData.limite_alunos)
      };

      if (turmaId) {
        await turmaService.atualizar(turmaId, dados);
        showSuccess('Turma atualizada com sucesso');
        setTimeout(() => router.back(), 1500);
      } else {
        await turmaService.criar(dados);
        showSuccess('Turma criada com sucesso');
        console.log('✅ Turma criada, mostrando modal...');
        setSubmitting(false);
        // Mostrar modal para perguntar se quer criar outra - com pequeno delay para o toast aparecer
        setTimeout(() => {
          console.log('📱 Acionando modal de confirmação');
          setShowModalContinuar(true);
        }, 300);
        return;
      }
    } catch (error) {
      console.error('Erro ao salvar:', error);
      let mensagemErro = 'Erro ao salvar turma';
      
      if (error.response?.data) {
        const data = error.response.data;
        if (data.message) {
          const match = data.message.match(/\d+ (.*?)$/) || data.message.match(/: (.*)/);
          mensagemErro = match ? match[1] : data.message;
        } else if (data.error) {
          mensagemErro = data.error;
        }
      }
      
      showError(mensagemErro);
    } finally {
      // Não fechar o submitting aqui para turmas novas se o modal vai aparecer
      if (turmaId) {
        setSubmitting(false);
      }
    }
  };

  if (loading) {
    return (
      <LayoutBase title={turmaId ? 'Editar Turma' : 'Nova Turma'}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#3b82f6" />
        </View>
      </LayoutBase>
    );
  }

  return (
    <>
      <LayoutBase title={turmaId ? 'Editar Turma' : 'Nova Turma'}>
        <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
        {/* Formulário */}
        <View style={styles.formContainer}>
          {/* Nome */}
          <View style={styles.field}>
            <Text style={styles.label}>Nome da Turma *</Text>
            <View style={styles.inputContainer}>
              <Feather name="edit-3" size={18} color="#6b7280" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="Ex: Pilates Segunda 9h"
                value={formData.nome}
                onChangeText={(text) => setFormData({ ...formData, nome: text })}
                editable={!submitting}
              />
            </View>
          </View>

          {/* Professor */}
          <View style={styles.field}>
            <Text style={styles.label}>Professor *</Text>
            <View style={styles.pickerContainer}>
              <Feather name="user" size={18} color="#6b7280" style={styles.pickerIcon} />
              <Picker
                selectedValue={formData.professor_id}
                onValueChange={(value) => setFormData({ ...formData, professor_id: value })}
                style={styles.picker}
                enabled={!submitting}
              >
                <Picker.Item label="Selecione um professor" value="" />
                {professores.map((prof) => (
                  <Picker.Item key={prof.id} label={prof.nome} value={String(prof.id)} />
                ))}
              </Picker>
            </View>
          </View>

          {/* Modalidade */}
          <View style={styles.field}>
            <Text style={styles.label}>Modalidade *</Text>
            <View style={styles.pickerContainer}>
              <Feather name="activity" size={18} color="#6b7280" style={styles.pickerIcon} />
              <Picker
                selectedValue={formData.modalidade_id}
                onValueChange={(value) => setFormData({ ...formData, modalidade_id: value })}
                style={styles.picker}
                enabled={!submitting}
              >
                <Picker.Item label="Selecione uma modalidade" value="" />
                {modalidades.map((mod) => (
                  <Picker.Item key={mod.id} label={mod.nome} value={String(mod.id)} />
                ))}
              </Picker>
            </View>
          </View>

          {/* Dia */}
          <View style={styles.field}>
            <Text style={styles.label}>Dia *</Text>
            <View style={styles.pickerContainer}>
              <Feather name="calendar" size={18} color="#6b7280" style={styles.pickerIcon} />
              <Picker
                selectedValue={formData.dia_id}
                onValueChange={(value) => setFormData({ ...formData, dia_id: value })}
                style={styles.picker}
                enabled={!submitting}
              >
                <Picker.Item label="Selecione um dia" value="" />
                {DIAS.map((dia) => (
                  <Picker.Item key={dia.id} label={dia.nome} value={String(dia.id)} />
                ))}
              </Picker>
            </View>
          </View>

          {/* Horário */}
          <View style={styles.field}>
            <Text style={styles.label}>Horário *</Text>
            <View style={styles.pickerContainer}>
              <Feather name="clock" size={18} color="#6b7280" style={styles.pickerIcon} />
              <Picker
                selectedValue={formData.horario_id}
                onValueChange={(value) => setFormData({ ...formData, horario_id: value })}
                style={styles.picker}
                enabled={!submitting}
              >
                <Picker.Item label="Selecione um horário" value="" />
                {HORARIOS.map((hor) => (
                  <Picker.Item key={hor.id} label={hor.hora.substring(0, 5)} value={String(hor.id)} />
                ))}
              </Picker>
            </View>
          </View>

          {/* Limite de Alunos */}
          <View style={styles.field}>
            <Text style={styles.label}>Limite de Alunos *</Text>
            <View style={styles.inputContainer}>
              <Feather name="users" size={18} color="#6b7280" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="20"
                value={formData.limite_alunos}
                onChangeText={(text) => setFormData({ ...formData, limite_alunos: text })}
                keyboardType="number-pad"
                editable={!submitting}
              />
            </View>
          </View>

          {/* Status */}
          <View style={styles.statusField}>
            <View style={styles.statusContent}>
              <Feather name="check-circle" size={18} color="#6b7280" />
              <Text style={styles.statusLabel}>Turma Ativa</Text>
            </View>
            <Switch
              value={formData.ativo}
              onValueChange={(value) => setFormData({ ...formData, ativo: value })}
              disabled={submitting}
              trackColor={{ false: '#d1d5db', true: '#86efac' }}
              thumbColor={formData.ativo ? '#10b981' : '#9ca3af'}
            />
          </View>

          {/* Botões */}
          <View style={styles.buttonContainer}>
            <TouchableOpacity
              style={[styles.button, styles.buttonCancel]}
              onPress={() => router.back()}
              disabled={submitting}
            >
              <Feather name="x" size={18} color="#6b7280" />
              <Text style={styles.buttonCancelText}>Cancelar</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.button, styles.buttonSubmit, submitting && styles.buttonDisabled]}
              onPress={handleSubmit}
              disabled={submitting}
            >
              {submitting ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <>
                  <Feather name="save" size={18} color="#fff" />
                  <Text style={styles.buttonSubmitText}>
                    {turmaId ? 'Atualizar' : 'Criar'} Turma
                  </Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </LayoutBase>

    {/* Modal fora do LayoutBase para evitar fechar junto */}
    <Modal
      visible={showModalContinuar}
      transparent
      animationType="fade"
      onDismiss={() => {
        console.log('❌ Modal tentou fechar via onDismiss');
      }}
    >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Feather name="check-circle" size={32} color="#10b981" />
              <Text style={styles.modalTitle}>Turma Criada!</Text>
            </View>

            <Text style={styles.modalMessage}>
              Deseja criar outra turma similar? Os dados serão mantidos e o horário será incrementado para a próxima hora.
            </Text>

            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalButton, styles.modalButtonSecondary]}
                onPress={handleVoltarListagem}
              >
                <Feather name="arrow-left" size={18} color="#6b7280" />
                <Text style={styles.modalButtonSecondaryText}>Voltar à Listagem</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.modalButton, styles.modalButtonPrimary]}
                onPress={handleContinuarCriando}
              >
                <Feather name="plus" size={18} color="#fff" />
                <Text style={styles.modalButtonPrimaryText}>Criar Outra</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb'
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center'
  },
  formContainer: {
    padding: 20,
    gap: 20
  },
  field: {
    gap: 8
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151'
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    paddingHorizontal: 12,
    backgroundColor: '#fff'
  },
  inputIcon: {
    marginRight: 8
  },
  input: {
    flex: 1,
    paddingVertical: 12,
    fontSize: 14,
    color: '#111827'
  },
  pickerContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    backgroundColor: '#fff',
    overflow: 'hidden'
  },
  pickerIcon: {
    marginLeft: 12,
    marginRight: 8,
    zIndex: 10
  },
  picker: {
    flex: 1,
    height: 50
  },
  statusField: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 12,
    backgroundColor: '#f9fafb',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb'
  },
  statusContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12
  },
  statusLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: '#374151'
  },
  buttonContainer: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 20,
    marginBottom: 40
  },
  button: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1
  },
  buttonCancel: {
    backgroundColor: '#f3f4f6',
    borderColor: '#d1d5db'
  },
  buttonCancelText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280'
  },
  buttonSubmit: {
    backgroundColor: '#3b82f6',
    borderColor: '#3b82f6'
  },
  buttonSubmitText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#fff'
  },
  buttonDisabled: {
    opacity: 0.7
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 24,
    width: '100%',
    maxWidth: 400,
    alignItems: 'center',
    gap: 16
  },
  modalHeader: {
    alignItems: 'center',
    gap: 12,
    marginBottom: 8
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111827'
  },
  modalMessage: {
    fontSize: 14,
    color: '#4b5563',
    textAlign: 'center',
    lineHeight: 20
  },
  modalButtons: {
    width: '100%',
    gap: 12,
    marginTop: 12
  },
  modalButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1
  },
  modalButtonSecondary: {
    backgroundColor: '#f3f4f6',
    borderColor: '#d1d5db'
  },
  modalButtonSecondaryText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280'
  },
  modalButtonPrimary: {
    backgroundColor: '#10b981',
    borderColor: '#10b981'
  },
  modalButtonPrimaryText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#fff'
  }
});
