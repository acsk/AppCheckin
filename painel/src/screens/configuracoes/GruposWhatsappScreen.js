import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  ActivityIndicator,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import LayoutBase from '../../components/LayoutBase';
import tenantWhatsappService from '../../services/tenantWhatsappService';
import { showSuccess, showError, showLoading, dismissToast } from '../../utils/toast';

export default function GruposWhatsappScreen() {
  const [loading, setLoading] = useState(true);
  const [salvando, setSalvando] = useState(false);
  const [whatsappLinks, setWhatsappLinks] = useState([{ nome: '', url: '' }]);

  useEffect(() => {
    carregarLinks();
  }, []);

  const carregarLinks = async () => {
    try {
      setLoading(true);
      const response = await tenantWhatsappService.obterLinks();

      if (response.success) {
        const links = Array.isArray(response.data?.whatsapp_links) ? response.data.whatsapp_links : [];
        setWhatsappLinks(
          links.length > 0
            ? links.map((link) => ({
                nome: link.nome || '',
                url: link.url || '',
              }))
            : [{ nome: '', url: '' }],
        );
      }
    } catch (error) {
      console.error('Erro ao carregar links WhatsApp:', error);
      showError('Erro ao carregar grupos WhatsApp');
    } finally {
      setLoading(false);
    }
  };

  const handleWhatsappLinkChange = (index, field, value) => {
    setWhatsappLinks((prev) =>
      prev.map((link, i) => (i === index ? { ...link, [field]: value } : link)),
    );
  };

  const addWhatsappLink = () => {
    setWhatsappLinks((prev) => [...prev, { nome: '', url: '' }]);
  };

  const removeWhatsappLink = (index) => {
    setWhatsappLinks((prev) => {
      if (prev.length <= 1) {
        return [{ nome: '', url: '' }];
      }
      return prev.filter((_, i) => i !== index);
    });
  };

  const buildWhatsappLinksPayload = () =>
    whatsappLinks
      .map((link) => ({
        nome: (link.nome || '').trim(),
        url: (link.url || '').trim(),
      }))
      .filter((link) => link.nome !== '' || link.url !== '');

  const handleSalvar = async () => {
    const linksPayload = buildWhatsappLinksPayload();

    for (const link of linksPayload) {
      if (!link.nome || !link.url) {
        showError('Preencha nome e link de todos os grupos ou remova a linha vazia');
        return;
      }
      if (!/^https:\/\/(chat\.)?whatsapp\.com\//i.test(link.url)) {
        showError(`Link inválido em "${link.nome}" — use https://chat.whatsapp.com/...`);
        return;
      }
    }

    const toastId = showLoading('Salvando grupos WhatsApp...');

    try {
      setSalvando(true);
      const response = await tenantWhatsappService.salvarLinks(linksPayload);

      if (response.success) {
        showSuccess(response.message || 'Grupos WhatsApp salvos com sucesso');
        const saved = Array.isArray(response.data?.whatsapp_links) ? response.data.whatsapp_links : linksPayload;
        setWhatsappLinks(
          saved.length > 0
            ? saved.map((link) => ({ nome: link.nome || '', url: link.url || '' }))
            : [{ nome: '', url: '' }],
        );
      } else {
        showError(response.message || 'Erro ao salvar grupos WhatsApp');
      }
    } catch (error) {
      console.error('Erro ao salvar links WhatsApp:', error);
      showError(error.response?.data?.message || 'Erro ao salvar grupos WhatsApp');
    } finally {
      setSalvando(false);
      dismissToast(toastId);
    }
  };

  if (loading) {
    return (
      <LayoutBase title="Grupos WhatsApp">
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#f97316" />
          <Text style={styles.loadingText}>Carregando...</Text>
        </View>
      </LayoutBase>
    );
  }

  return (
    <LayoutBase title="Grupos WhatsApp">
      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <View style={styles.card}>
          <View style={styles.cardHeader}>
            <View style={styles.cardHeaderIcon}>
              <Feather name="message-circle" size={20} color="#f97316" />
            </View>
            <Text style={styles.cardTitle}>Grupos WhatsApp</Text>
            <TouchableOpacity style={styles.addButton} onPress={addWhatsappLink} disabled={salvando}>
              <Feather name="plus" size={16} color="#fff" />
              <Text style={styles.addButtonText}>Link</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.cardBody}>
            <Text style={styles.helperText}>
              Links exibidos na tela inicial do app mobile para o aluno entrar nos grupos da academia.
            </Text>

            {whatsappLinks.map((link, index) => (
              <View key={`whatsapp-${index}`} style={styles.whatsappLinkRow}>
                <View style={styles.inputGroup}>
                  <Text style={styles.label}>Nome do grupo</Text>
                  <TextInput
                    style={styles.input}
                    placeholder="Ex: CN AVISOS"
                    placeholderTextColor="#999"
                    value={link.nome}
                    onChangeText={(value) => handleWhatsappLinkChange(index, 'nome', value)}
                    editable={!salvando}
                  />
                </View>

                <View style={styles.inputGroup}>
                  <Text style={styles.label}>Link do convite</Text>
                  <TextInput
                    style={styles.input}
                    placeholder="https://chat.whatsapp.com/..."
                    placeholderTextColor="#999"
                    value={link.url}
                    onChangeText={(value) => handleWhatsappLinkChange(index, 'url', value)}
                    autoCapitalize="none"
                    editable={!salvando}
                  />
                </View>

                {whatsappLinks.length > 1 && (
                  <TouchableOpacity
                    style={styles.removeWhatsappButton}
                    onPress={() => removeWhatsappLink(index)}
                    disabled={salvando}
                  >
                    <Feather name="trash-2" size={16} color="#ef4444" />
                    <Text style={styles.removeWhatsappText}>Remover</Text>
                  </TouchableOpacity>
                )}
              </View>
            ))}
          </View>
        </View>

        <TouchableOpacity
          style={[styles.saveButton, salvando && styles.saveButtonDisabled]}
          onPress={handleSalvar}
          disabled={salvando}
        >
          {salvando ? (
            <ActivityIndicator size="small" color="#fff" />
          ) : (
            <>
              <Feather name="save" size={18} color="#fff" />
              <Text style={styles.saveButtonText}>Salvar</Text>
            </>
          )}
        </TouchableOpacity>
      </ScrollView>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f3f4f6',
  },
  content: {
    padding: 20,
    paddingBottom: 40,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 12,
  },
  loadingText: {
    fontSize: 14,
    color: '#6b7280',
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    marginBottom: 20,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
    gap: 12,
  },
  cardHeaderIcon: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: '#fff7ed',
    justifyContent: 'center',
    alignItems: 'center',
  },
  cardTitle: {
    flex: 1,
    fontSize: 16,
    fontWeight: '700',
    color: '#111827',
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#f97316',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
  },
  addButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#fff',
  },
  cardBody: {
    padding: 16,
  },
  helperText: {
    fontSize: 13,
    color: '#6b7280',
    marginBottom: 16,
    lineHeight: 18,
  },
  whatsappLinkRow: {
    marginBottom: 16,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  inputGroup: {
    marginBottom: 12,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 6,
  },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#111827',
    backgroundColor: '#fff',
  },
  removeWhatsappButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    alignSelf: 'flex-start',
    marginTop: 4,
  },
  removeWhatsappText: {
    fontSize: 13,
    color: '#ef4444',
    fontWeight: '600',
  },
  saveButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#f97316',
    paddingVertical: 14,
    borderRadius: 10,
  },
  saveButtonDisabled: {
    opacity: 0.7,
  },
  saveButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: '#fff',
  },
});
