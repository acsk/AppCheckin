import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
} from 'react-native';
import { Picker } from '@react-native-picker/picker';
import { useRouter } from 'expo-router';
import { superAdminService } from '../services/superAdminService';
import LayoutBase from '../components/LayoutBase';
import LoadingOverlay from '../components/LoadingOverlay';
import { showSuccess, showError } from '../utils/toast';

export default function CadastrarAcademiaScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [planos, setPlanos] = useState([]);
  const [formData, setFormData] = useState({
    nome: '',
    email: '',
    telefone: '',
    endereco: '',
    plano_id: '',
  });

  useEffect(() => {
    loadPlanos();
  }, []);

  const loadPlanos = async () => {
    try {
      // Por enquanto, vamos usar planos fixos
      // Depois você pode criar um endpoint para listar planos
      setPlanos([
        { id: 1, nome: 'Básico', valor: 'R$ 99,90/mês' },
        { id: 2, nome: 'Profissional', valor: 'R$ 199,90/mês' },
        { id: 3, nome: 'Premium', valor: 'R$ 299,90/mês' },
      ]);
    } catch (error) {
      Alert.alert('Erro', 'Não foi possível carregar os planos');
    }
  };

  const handleChange = (field, value) => {
    setFormData({ ...formData, [field]: value });
  };

  const validateForm = () => {
    if (!formData.nome.trim()) {
      showError('Nome da academia é obrigatório');
      return false;
    }
    if (!formData.email.trim()) {
      showError('E-mail é obrigatório');
      return false;
    }
    if (!formData.email.includes('@')) {
      showError('E-mail inválido');
      return false;
    }
    if (!formData.plano_id) {
      showError('Selecione um plano');
      return false;
    }
    return true;
  };

  const handleSubmit = async () => {
    if (!validateForm()) return;

    setSaving(true);
    try {
      const result = await superAdminService.criarAcademia(formData);
      showSuccess(result);
      router.push('/academias');
    } catch (error) {
      showError(error.response?.data?.error || 'Não foi possível cadastrar a academia');
    } finally {
      setSaving(false);
    }
  };

  return (
    <LayoutBase title="Nova Academia" subtitle="Preencha os campos obrigatórios">
      {saving && <LoadingOverlay message="Cadastrando academia..." />}
      
      <ScrollView style={styles.container}>
        {/* Form */}
        <View style={styles.form}>
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Nome da Academia *</Text>
            <TextInput
              style={styles.input}
              placeholder="Ex: Academia Fitness Pro"
              value={formData.nome}
              onChangeText={(value) => handleChange('nome', value)}
              editable={!saving}
            />
          </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>E-mail *</Text>
          <TextInput
            style={styles.input}
            placeholder="contato@academia.com"
            value={formData.email}
            onChangeText={(value) => handleChange('email', value)}
            keyboardType="email-address"
            autoCapitalize="none"
            editable={!saving}
          />
        </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>Telefone</Text>
          <TextInput
            style={styles.input}
            placeholder="(11) 99999-9999"
            value={formData.telefone}
            onChangeText={(value) => handleChange('telefone', value)}
            keyboardType="phone-pad"
            editable={!saving}
          />
        </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>Endereço</Text>
          <TextInput
            style={[styles.input, styles.textArea]}
            placeholder="Rua, número, bairro, cidade, estado"
            value={formData.endereco}
            onChangeText={(value) => handleChange('endereco', value)}
            multiline
            numberOfLines={3}
            editable={!saving}
          />
        </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>Plano *</Text>
          <View style={styles.pickerContainer}>
            <Picker
              selectedValue={formData.plano_id}
              onValueChange={(value) => handleChange('plano_id', value)}
              enabled={!saving}
              style={styles.picker}
            >
              <Picker.Item label="Selecione um plano" value="" />
              {planos.map((plano) => (
                <Picker.Item
                  key={plano.id}
                  label={`${plano.nome} - ${plano.valor}`}
                  value={plano.id}
                />
              ))}
            </Picker>
          </View>
        </View>

        {/* Info Box */}
        <View style={styles.infoBox}>
          <Text style={styles.infoTitle}>ℹ️ Informações importantes:</Text>
          <Text style={styles.infoText}>
            • A academia será criada com status ativo
          </Text>
          <Text style={styles.infoText}>
            • Um slug único será gerado automaticamente
          </Text>
          <Text style={styles.infoText}>
            • O plano selecionado será associado à academia
          </Text>
          <Text style={styles.infoText}>
            • Você poderá criar um administrador após o cadastro
          </Text>
        </View>

        {/* Submit Button */}
        <TouchableOpacity
          style={[styles.submitButton, saving && styles.submitButtonDisabled]}
          onPress={handleSubmit}
          disabled={saving}
        >
          {saving ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.submitButtonText}>Cadastrar Academia</Text>
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
  },
  form: {
    padding: 20,
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  textArea: {
    minHeight: 80,
    textAlignVertical: 'top',
  },
  pickerContainer: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    overflow: 'hidden',
  },
  picker: {
    height: 50,
  },
  infoBox: {
    backgroundColor: '#E3F2FD',
    padding: 15,
    borderRadius: 8,
    marginBottom: 20,
  },
  infoTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#1976D2',
    marginBottom: 10,
  },
  infoText: {
    fontSize: 13,
    color: '#1565C0',
    marginBottom: 5,
  },
  submitButton: {
    backgroundColor: '#f97316',
    padding: 16,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 10,
  },
  submitButtonDisabled: {
    backgroundColor: '#9ca3af',
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
