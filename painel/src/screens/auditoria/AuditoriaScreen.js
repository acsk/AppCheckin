import React from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LayoutBase from '../../components/LayoutBase';

export default function AuditoriaScreen() {
  const router = useRouter();

  return (
    <LayoutBase title="Auditoria" subtitle="Verificações e consistência">
      <View style={styles.container}>
        <TouchableOpacity
          style={styles.actionButton}
          activeOpacity={0.7}
          onPress={() => router.push('/auditoria/pagamentos-duplicados')}
        >
          <View style={styles.actionIcon}>
            <Feather name="copy" size={22} color="#ef4444" />
          </View>
          <View style={styles.actionContent}>
            <Text style={styles.actionLabel}>Pagamentos Duplicados</Text>
            <Text style={styles.actionDesc}>Verificar pagamentos gerados em duplicidade</Text>
          </View>
          <Feather name="chevron-right" size={20} color="#9ca3af" />
        </TouchableOpacity>
      </View>
    </LayoutBase>
  );
}

const styles = StyleSheet.create({
  container: {
    padding: 16,
    gap: 12,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  actionIcon: {
    width: 44,
    height: 44,
    borderRadius: 10,
    backgroundColor: '#fef2f2',
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionContent: {
    flex: 1,
  },
  actionLabel: {
    fontSize: 15,
    fontWeight: '700',
    color: '#111827',
  },
  actionDesc: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
});
