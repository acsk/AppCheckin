import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Feather } from '@expo/vector-icons';

/**
 * Componente para exibir badges de status com cor e ícone
 * 
 * @param {Object} status - Objeto status com: { nome, cor, icone }
 * @param {string} size - Tamanho: 'small', 'medium', 'large' (default: 'medium')
 * @param {boolean} showIcon - Mostrar ícone (default: true)
 * @param {Object} style - Estilos customizados
 */
export default function StatusBadge({ status, size = 'medium', showIcon = true, style }) {
  if (!status) return null;

  const sizes = {
    small: {
      badge: { paddingVertical: 2, paddingHorizontal: 6 },
      text: { fontSize: 10 },
      icon: 10,
    },
    medium: {
      badge: { paddingVertical: 4, paddingHorizontal: 8 },
      text: { fontSize: 11 },
      icon: 12,
    },
    large: {
      badge: { paddingVertical: 6, paddingHorizontal: 12 },
      text: { fontSize: 13 },
      icon: 14,
    },
  };

  const currentSize = sizes[size] || sizes.medium;
  const backgroundColor = status.cor || '#6b7280';
  const iconName = status.icone || 'circle';

  return (
    <View 
      style={[
        styles.badge, 
        currentSize.badge,
        { backgroundColor },
        style
      ]}
    >
      {showIcon && status.icone && (
        <Feather name={iconName} size={currentSize.icon} color="#fff" />
      )}
      <Text style={[styles.text, currentSize.text]}>{status.nome}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  text: {
    fontWeight: '600',
    color: '#fff',
  },
});
