import React from 'react';
import { View, Text, ImageBackground, Image, StyleSheet } from 'react-native';
import { Feather } from '@expo/vector-icons';

export default function Home() {
  return (
    <ImageBackground source={require('../../../assets/img/bg.png')} style={styles.bg} resizeMode="cover">
      <View style={styles.overlay} />
      <View style={styles.content}>
        <View style={styles.header}>
          <Image source={{ uri: 'https://i.pravatar.cc/200?img=32' }} style={styles.avatar} />
          <View>
            <Text style={styles.nome}>Jon Silva</Text>
            <Text style={styles.user}>@jonsilva</Text>
          </View>
        </View>

        <View style={styles.statsRow}>
          <StatCard label="Check-ins" value="75" icon="check-circle" />
          <StatCard label="Treinos" value="230" icon="activity" />
          <StatCard label="Total kg" value="76450" icon="box" />
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Próximos treinos</Text>
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Funcional - 19:00</Text>
            <Text style={styles.cardSub}>Hoje • Sala 2 • Coach Ana</Text>
          </View>
          <View style={styles.card}>
            <Text style={styles.cardTitle}>HIIT - 07:00</Text>
            <Text style={styles.cardSub}>Amanhã • Sala 1 • Coach Bruno</Text>
          </View>
        </View>
      </View>
    </ImageBackground>
  );
}

function StatCard({ label, value, icon }) {
  return (
    <View style={styles.statCard}>
      <Text style={styles.statValue}>{value}</Text>
      <View style={styles.statLabelRow}>
        <Feather name={icon} size={14} color="#cbd5e1" />
        <Text style={styles.statLabel}>{label}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  bg: { flex: 1 },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.35)',
  },
  content: {
    flex: 1,
    padding: 20,
    paddingTop: 60,
    gap: 18,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  avatar: { width: 64, height: 64, borderRadius: 32, borderWidth: 2, borderColor: 'rgba(255,255,255,0.6)' },
  nome: { color: '#fff', fontSize: 22, fontWeight: '700' },
  user: { color: 'rgba(255,255,255,0.75)', fontSize: 14 },
  statsRow: { flexDirection: 'row', gap: 10 },
  statCard: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    borderRadius: 14,
    padding: 12,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  statValue: { color: '#fff', fontSize: 20, fontWeight: '800' },
  statLabelRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4 },
  statLabel: { color: '#cbd5e1', fontSize: 12, fontWeight: '600' },
  section: { gap: 10, marginTop: 6 },
  sectionTitle: { color: '#fff', fontSize: 16, fontWeight: '700' },
  card: {
    backgroundColor: 'rgba(0,0,0,0.45)',
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  cardTitle: { color: '#fff', fontSize: 16, fontWeight: '700' },
  cardSub: { color: '#cbd5e1', marginTop: 4 },
});
