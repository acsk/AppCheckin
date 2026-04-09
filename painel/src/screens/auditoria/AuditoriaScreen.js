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
          onPress={() => router.push('/auditoria/anomalias-datas')}
        >
          <View style={[styles.actionIcon, { backgroundColor: '#fff7ed' }]}>
            <Feather name="alert-triangle" size={22} color="#f97316" />
          </View>
          <View style={styles.actionContent}>
            <Text style={styles.actionLabel}>Anomalias de Datas</Text>
            <Text style={styles.actionDesc}>Inconsistências em matrículas: vencimentos, duplicatas e cancelamentos indevidos</Text>
          </View>
          <Feather name="chevron-right" size={20} color="#9ca3af" />
        </TouchableOpacity>

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

        <TouchableOpacity
          style={styles.actionButton}
          activeOpacity={0.7}
          onPress={() => router.push('/auditoria/reparar-proxima-data-vencimento')}
        >
          <View style={[styles.actionIcon, { backgroundColor: '#eef2ff' }]}>
            <Feather name="tool" size={22} color="#6366f1" />
          </View>
          <View style={styles.actionContent}>
            <Text style={styles.actionLabel}>Reparar Próxima Data de Vencimento</Text>
            <Text style={styles.actionDesc}>Corrigir matrículas com proxima_data_vencimento divergente da próxima parcela</Text>
          </View>
          <Feather name="chevron-right" size={20} color="#9ca3af" />
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.actionButton}
          activeOpacity={0.7}
          onPress={() => router.push('/auditoria/checkins-acima-do-limite')}
        >
          <View style={[styles.actionIcon, { backgroundColor: '#f3e8ff' }]}>
            <Feather name="trending-up" size={22} color="#7c3aed" />
          </View>
          <View style={styles.actionContent}>
            <Text style={styles.actionLabel}>Check-ins Acima do Limite</Text>
            <Text style={styles.actionDesc}>Alunos que excederam o limite de check-ins do plano no período</Text>
          </View>
          <Feather name="chevron-right" size={20} color="#9ca3af" />
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.actionButton}
          activeOpacity={0.7}
          onPress={() => router.push('/auditoria/checkins-multiplos-no-dia')}
        >
          <View style={[styles.actionIcon, { backgroundColor: '#fef3c7' }]}>
            <Feather name="repeat" size={22} color="#d97706" />
          </View>
          <View style={styles.actionContent}>
            <Text style={styles.actionLabel}>Check-ins Múltiplos no Dia</Text>
            <Text style={styles.actionDesc}>Detectar alunos com mais de 1 check-in no mesmo dia (possível fraude ou erro)</Text>
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
