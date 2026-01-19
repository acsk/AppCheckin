import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  Modal,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { TIPOS_BLOCO } from '../utils/wodConstants';

export default function WodPreviewModal({ visible, onClose, wodData }) {
  const parseDate = (value) => {
    if (!value) return null;
    if (value.includes('/')) {
      const [day, month, year] = value.split('/');
      if (day?.length === 2 && month?.length === 2 && year?.length === 4) {
        return new Date(`${year}-${month}-${day}T00:00:00`);
      }
      return null;
    }
    return new Date(`${value}T00:00:00`);
  };

  const getTipoLabel = (tipo) => {
    const tipoObj = TIPOS_BLOCO.find(t => t.value === tipo);
    return tipoObj?.label || tipo;
  };

  const getTipoColor = (tipo) => {
    const colorMap = {
      warmup: '#9ca3af',
      strength: '#ef4444',
      skill: '#3b82f6',
      metcon: '#f97316',
      fortime: '#8b5cf6',
      amrap: '#ec4899',
      emom: '#06b6d4',
      accessory: '#9ca3af',
      cooldown: '#9ca3af',
      note: '#6b7280',
    };
    return colorMap[tipo] || '#6b7280';
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent={true}
      onRequestClose={onClose}
    >
      <View style={styles.modalOverlay}>
        <View style={styles.modalContainer}>
          {/* Header */}
          <View style={styles.header}>
            <Text style={styles.headerTitle}>Visão do Aluno</Text>
            <TouchableOpacity onPress={onClose} style={styles.closeButton}>
              <Feather name="x" size={24} color="#fff" />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
            {/* Data */}
            <View style={styles.dateContainer}>
              <Feather name="calendar" size={18} color="#f97316" />
              <Text style={styles.dateText}>
                {parseDate(wodData.data)
                  ? parseDate(wodData.data).toLocaleDateString('pt-BR', {
                      weekday: 'long',
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric'
                    })
                  : 'Data não definida'}
              </Text>
            </View>

          {/* Título */}
          <Text style={styles.titulo}>{wodData.titulo || 'Sem título'}</Text>

          {/* Descrição */}
          {wodData.descricao && (
            <Text style={styles.descricao}>{wodData.descricao}</Text>
          )}

          {/* Blocos */}
          {wodData.blocos && wodData.blocos.length > 0 ? (
            <View style={styles.blocosContainer}>
              {wodData.blocos.map((bloco, index) => (
                <View key={index} style={styles.blocoCard}>
                  <View style={[styles.blocoHeader, { backgroundColor: getTipoColor(bloco.tipo) }]}>
                    <Text style={styles.blocoTipo}>{getTipoLabel(bloco.tipo)}</Text>
                    {bloco.tempo_cap && (
                      <View style={styles.tempoCapBadge}>
                        <Feather name="clock" size={14} color="#fff" />
                        <Text style={styles.tempoCapText}>{bloco.tempo_cap}</Text>
                      </View>
                    )}
                  </View>

                  <View style={styles.blocoContent}>
                    {bloco.titulo && (
                      <Text style={styles.blocoTitulo}>{bloco.titulo}</Text>
                    )}
                    
                    {bloco.conteudo && (
                      <Text style={styles.blocoConteudo}>{bloco.conteudo}</Text>
                    )}
                  </View>
                </View>
              ))}
            </View>
          ) : (
            <View style={styles.emptyState}>
              <Feather name="file-text" size={48} color="#d1d5db" />
              <Text style={styles.emptyText}>Nenhum bloco adicionado</Text>
            </View>
          )}

          {/* Variações */}
          {wodData.variacoes && wodData.variacoes.length > 0 && (
            <View style={styles.variacoesContainer}>
              <Text style={styles.sectionTitle}>Variações</Text>
              {wodData.variacoes.map((variacao, index) => (
                <View key={index} style={styles.variacaoCard}>
                  <View style={styles.variacaoHeader}>
                    <Feather name="zap" size={16} color="#f97316" />
                    <Text style={styles.variacaoNome}>{variacao.nome}</Text>
                  </View>
                  {variacao.descricao && (
                    <Text style={styles.variacaoDescricao}>{variacao.descricao}</Text>
                  )}
                </View>
              ))}
            </View>
          )}
        </ScrollView>
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
  },
  modalContainer: {
    width: '90%',
    maxWidth: 420,
    maxHeight: '85%',
    backgroundColor: '#fff',
    borderRadius: 16,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 10,
  },
  container: {
    flex: 1,
    backgroundColor: '#fff',
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
  closeButton: {
    padding: 4,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  dateContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 12,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  dateText: {
    fontSize: 13,
    color: '#f97316',
    textTransform: 'capitalize',
    fontWeight: '500',
  },
  titulo: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#1f2937',
    marginBottom: 8,
  },
  descricao: {
    fontSize: 14,
    color: '#6b7280',
    lineHeight: 20,
    marginBottom: 16,
  },
  blocosContainer: {
    backgroundColor: '#f3f4f6',
    borderRadius: 10,
    marginBottom: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  blocoHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  blocoTipo: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#fff',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  tempoCapBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    paddingHorizontal: 6,
    paddingVertical: 3,
    borderRadius: 6,
  },
  tempoCapText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#fff',
  },
  blocoContent: {
    backgroundColor: '#fff',
    padding: 12,
  },
  blocoTitulo: {
    fontSize: 15,
    fontWeight: '600',
    color: '#1f2937',
    marginBottom: 6,
  },
  blocoConteudo: {
    fontSize: 14,
    color: '#4b5563',
    lineHeight: 20,
    whiteSpace: 'pre-wrap',
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
  },
  emptyText: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 8,
  },
  variacoesContainer: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#1f2937',
    marginBottom: 10,
  },
  variacaoCard: {
    backgroundColor: '#fff5f0',
    borderRadius: 10,
    padding: 12,
    marginBottom: 10,
    borderLeftWidth: 3,
    borderLeftColor: '#f97316',
  },
  variacaoHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 3,
  },
  variacaoNome: {
    fontSize: 14,
    fontWeight: '600',
    color: '#f97316',
  },
  variacaoDescricao: {
    fontSize: 13,
    color: '#6b7280',
    lineHeight: 18,
    marginTop: 2,
  },
});
