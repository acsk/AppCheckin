import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  Modal,
  TextInput,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { TIPOS_BLOCO } from '../utils/wodConstants';

export default function AdicionarBlocoModal({ visible, onClose, onAdd, blocoExistente = null }) {
  const [formData, setFormData] = useState(
    blocoExistente || {
      tipo: 'metcon',
      titulo: '',
      conteudo: '',
      tempo_cap: '10 min',
    }
  );

  React.useEffect(() => {
    if (blocoExistente) {
      setFormData(blocoExistente);
    } else {
      setFormData({
        tipo: 'metcon',
        titulo: '',
        conteudo: '',
        tempo_cap: '10 min',
      });
    }
  }, [blocoExistente, visible]);

  const handleAdd = () => {
    if (!formData.conteudo.trim()) {
      alert('Conteúdo é obrigatório');
      return;
    }

    onAdd({
      ordem: formData.ordem || 0,
      ...formData,
    });

    // Reset form
    setFormData({
      tipo: 'metcon',
      titulo: '',
      conteudo: '',
      tempo_cap: '10 min',
    });
  };

  return (
    <Modal
      visible={visible}
      animationType="fade"
      transparent={true}
      onRequestClose={onClose}
    >
      <View style={styles.modalOverlay}>
        <View style={styles.modalContainer}>
          {/* Header */}
          <View style={styles.header}>
            <Text style={styles.headerTitle}>
              {blocoExistente ? 'Editar Bloco' : 'Novo Bloco'}
            </Text>
            <TouchableOpacity onPress={onClose}>
              <Feather name="x" size={24} color="#fff" />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
            {/* Tipo */}
            <View style={styles.formGroup}>
              <Text style={styles.label}>Tipo</Text>
              <ScrollView
                horizontal
                showsHorizontalScrollIndicator={false}
                style={styles.tipoScroll}
              >
                {TIPOS_BLOCO.map((tipo) => (
                  <TouchableOpacity
                    key={tipo.value}
                    style={[
                      styles.tipoButton,
                      formData.tipo === tipo.value && styles.tipoButtonActive,
                    ]}
                    onPress={() => setFormData(prev => ({ ...prev, tipo: tipo.value }))}
                  >
                    <Text
                      style={[
                        styles.tipoButtonText,
                        formData.tipo === tipo.value && styles.tipoButtonTextActive,
                      ]}
                    >
                      {tipo.label}
                    </Text>
                  </TouchableOpacity>
                ))}
              </ScrollView>
            </View>

            {/* Título */}
            <View style={styles.formGroup}>
              <Text style={styles.label}>Título (opcional)</Text>
              <TextInput
                style={styles.input}
                placeholder="Ex: Aquecimento"
                placeholderTextColor="#9ca3af"
                value={formData.titulo}
                onChangeText={(value) => setFormData(prev => ({ ...prev, titulo: value }))}
              />
            </View>

            {/* Tempo */}
            <View style={styles.formGroup}>
              <Text style={styles.label}>Tempo (Cap)</Text>
              <TextInput
                style={styles.input}
                placeholder="Ex: 5 min, 20 min"
                placeholderTextColor="#9ca3af"
                value={formData.tempo_cap}
                onChangeText={(value) => setFormData(prev => ({ ...prev, tempo_cap: value }))}
              />
            </View>

            {/* Conteúdo */}
            <View style={styles.formGroup}>
              <Text style={styles.label}>Conteúdo *</Text>
              <TextInput
                style={[styles.input, styles.multilineInput]}
                placeholder="Descrição detalhada do bloco..."
                placeholderTextColor="#9ca3af"
                value={formData.conteudo}
                onChangeText={(value) => setFormData(prev => ({ ...prev, conteudo: value }))}
                multiline={true}
                numberOfLines={5}
                textAlignVertical="top"
              />
            </View>
          </ScrollView>

          {/* Botões */}
          <View style={styles.buttonContainer}>
            <TouchableOpacity
              style={[styles.button, styles.cancelButton]}
              onPress={onClose}
            >
              <Text style={styles.buttonText}>Cancelar</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.button, styles.addButton]}
              onPress={handleAdd}
            >
              <Feather name="plus" size={18} color="#fff" />
              <Text style={styles.addButtonText}>
                {blocoExistente ? 'Salvar' : 'Adicionar'}
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 16,
  },
  modalContainer: {
    width: '50%',
    maxHeight: '70%',
    backgroundColor: '#fff',
    borderRadius: 16,
    overflow: 'hidden',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#f97316',
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#fff',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  formGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1f2937',
    marginBottom: 8,
  },
  input: {
    backgroundColor: '#f3f4f6',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#1f2937',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  multilineInput: {
    minHeight: 100,
    textAlignVertical: 'top',
  },
  tipoScroll: {
    flexGrow: 0,
  },
  tipoButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    backgroundColor: '#f3f4f6',
    marginRight: 8,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  tipoButtonActive: {
    backgroundColor: '#f97316',
    borderColor: '#f97316',
  },
  tipoButtonText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#6b7280',
  },
  tipoButtonTextActive: {
    color: '#fff',
  },
  buttonContainer: {
    flexDirection: 'row',
    gap: 12,
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: '#e5e7eb',
  },
  button: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    flexDirection: 'row',
    gap: 6,
  },
  cancelButton: {
    backgroundColor: '#e5e7eb',
  },
  addButton: {
    backgroundColor: '#f97316',
  },
  buttonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
  },
  addButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#fff',
  },
});
