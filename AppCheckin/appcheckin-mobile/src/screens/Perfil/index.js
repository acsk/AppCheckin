import React from 'react';
import { View, Text, Image, ImageBackground, StyleSheet } from 'react-native';
import { Feather, MaterialCommunityIcons } from '@expo/vector-icons';

export default function Perfil({ usuario }) {
  const nome = usuario?.nome || 'Jon Silva';
  const user = usuario?.email ? `@${usuario.email.split('@')[0]}` : '@jonsilva';
  return (
    <ImageBackground source={require('../../../assets/img/bg.png')} style={styles.bg} resizeMode="cover">
      <View style={styles.overlay} />
      <View style={styles.content}>
        <View style={styles.avatarWrap}>
          <Image source={{ uri: 'https://i.pravatar.cc/200?img=12' }} style={styles.avatar} />
        </View>
        <Text style={styles.nome}>{nome}</Text>
        <Text style={styles.user}>{user}</Text>

        <View style={styles.statsRow}>
          <Stat label="Check-ins" value="75" icon="check-circle" />
          <Stat label="Treinos" value="230" icon="dumbbell" />
          <Stat label="Total kg" value="76450" icon="weight-kilogram" />
        </View>

        <View style={styles.list}>
          <Row icon={<Feather name="user" size={18} color="#e5e5e5" />} title="Minha conta" />
          <Row icon={<Feather name="target" size={18} color="#e5e5e5" />} title="Minhas metas" progress="9/10" />
          <Row icon={<Feather name="clock" size={18} color="#e5e5e5" />} title="Histórico de treinos" />
          <Row icon={<MaterialCommunityIcons name="trophy-outline" size={18} color="#e5e5e5" />} title="Challenge" />
        </View>
      </View>
    </ImageBackground>
  );
}

function Stat({ label, value, icon }) {
  const Icon = icon === 'dumbbell' ? MaterialCommunityIcons : Feather;
  return (
    <View style={styles.statCard}>
      <Text style={styles.statValue}>{value}</Text>
      <View style={styles.statLabelRow}>
        <Icon name={icon} size={14} color="#cbd5e1" />
        <Text style={styles.statLabel}>{label}</Text>
      </View>
    </View>
  );
}

function Row({ icon, title, progress }) {
  return (
    <View style={styles.row}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
        {icon}
        <Text style={styles.rowTitle}>{title}</Text>
      </View>
      {progress ? <Text style={styles.progress}>{progress}</Text> : <Feather name="chevron-right" size={18} color="#e5e5e5" />}
    </View>
  );
}

const styles = StyleSheet.create({
  bg: { flex: 1 },
  overlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(0,0,0,0.35)' },
  content: { flex: 1, padding: 20, paddingTop: 70, gap: 14 },
  avatarWrap: { alignItems: 'center' },
  avatar: { width: 96, height: 96, borderRadius: 48, borderWidth: 2, borderColor: 'rgba(255,255,255,0.7)' },
  nome: { color: '#fff', fontSize: 22, fontWeight: '800', textAlign: 'center', marginTop: 10 },
  user: { color: 'rgba(255,255,255,0.75)', fontSize: 14, textAlign: 'center' },
  statsRow: { flexDirection: 'row', gap: 10, marginTop: 8 },
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
  list: { marginTop: 6, gap: 10 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: 'rgba(0,0,0,0.45)',
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  rowTitle: { color: '#e5e5e5', fontSize: 15, fontWeight: '600' },
  progress: { color: '#f97316', fontWeight: '700', fontSize: 14 },
});
