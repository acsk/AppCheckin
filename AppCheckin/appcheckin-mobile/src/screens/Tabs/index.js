import React, { useState } from 'react';
import { View, TouchableOpacity, Text, StyleSheet } from 'react-native';
import { Feather } from '@expo/vector-icons';
import Home from '../Home';
import Perfil from '../Perfil';

const tabs = [
  { key: 'home', label: 'Início', icon: 'home' },
  { key: 'treino', label: 'Treino', icon: 'activity' },
  { key: 'checkin', label: 'Check-in', icon: 'map-pin' },
  { key: 'perfil', label: 'Perfil', icon: 'user' },
];

export default function Tabs({ usuario }) {
  const [active, setActive] = useState('perfil');

  const renderScreen = () => {
    if (active === 'home') return <Home />;
    if (active === 'perfil') return <Perfil usuario={usuario} />;
    // telas mockadas para treino / check-in
    return <Home />;
  };

  return (
    <View style={styles.container}>
      <View style={styles.screen}>{renderScreen()}</View>
      <View style={styles.bar}>
        {tabs.map((tab) => {
          const focused = tab.key === active;
          return (
            <TouchableOpacity key={tab.key} style={styles.tab} onPress={() => setActive(tab.key)}>
              <Feather name={tab.icon} size={20} color={focused ? '#f97316' : '#e2e8f0'} />
              <Text style={[styles.label, focused && styles.labelActive]}>{tab.label}</Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  screen: { flex: 1 },
  bar: {
    flexDirection: 'row',
    backgroundColor: '#0b0f19',
    paddingVertical: 10,
    borderTopWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  tab: { flex: 1, alignItems: 'center', gap: 4 },
  label: { color: '#e2e8f0', fontSize: 12 },
  labelActive: { color: '#f97316', fontWeight: '700' },
});
