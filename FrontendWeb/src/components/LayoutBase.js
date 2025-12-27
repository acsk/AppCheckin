import React from 'react';
import { View, Text, StyleSheet, Pressable, ScrollView, Image } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Feather } from '@expo/vector-icons';
import { useRouter, usePathname } from 'expo-router';
import { authService } from '../services/authService';

const MENU = [
  { label: 'Dashboard', path: '/', icon: 'home' },
  { label: 'Academias', path: '/academias', icon: 'briefcase' },
];

export default function LayoutBase({ children, title = 'Dashboard', subtitle = 'Overview' }) {
  const router = useRouter();
  const pathname = usePathname();
  const [usuarioInfo, setUsuarioInfo] = React.useState(null);

  React.useEffect(() => {
    authService
      .getCurrentUser()
      .then((user) => setUsuarioInfo(user))
      .catch(() => {});
  }, []);

  const handleLogout = async () => {
    await authService.logout();
    router.replace('/login');
  };

  const nome = usuarioInfo?.nome || 'Usuário';

  return (
    <View style={styles.app}>
      {/* Sidebar */}
      <View style={styles.sidebar}>
        <View style={styles.brand}>
          <Image source={require('../../assets/img/logo.png')} style={styles.logo} />
          <Text style={styles.brandSub}>CHECK-IN</Text>
        </View>

        <View style={styles.menu}>
          {MENU.map((item) => {
            const selected = pathname === item.path || (item.path !== '/' && pathname.startsWith(item.path));
            return (
              <Pressable
                key={item.label}
                onPress={() => router.push(item.path)}
                style={[styles.menuItem, selected && styles.menuItemActive]}
              >
                <View style={styles.menuItemLeft}>
                  <Feather name={item.icon} size={16} color={selected ? '#fff' : '#d1d5db'} />
                  <Text style={[styles.menuText, selected && styles.menuTextActive]}>{item.label}</Text>
                </View>
              </Pressable>
            );
          })}
        </View>
      </View>

      {/* Content Area */}
      <LinearGradient
        colors={['#f1e7d9ff', '#a0a0a0ff', '#f3eaf8ff']}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 0 }}
        style={styles.main}
      >
        <View style={styles.topbar}>
          <View>
            <Text style={styles.topTitle}>{title}</Text>
            <Text style={styles.topSubtitle}>{subtitle}</Text>
          </View>
          <View style={styles.topRight}>
            <View style={styles.iconChip}>
              <Feather name="bell" size={16} color="#2b1a04" />
            </View>
            <View style={styles.iconChip}>
              <Feather name="settings" size={16} color="#2b1a04" />
            </View>
            <Pressable style={styles.iconChip} onPress={handleLogout}>
              <Feather name="log-out" size={16} color="#2b1a04" />
            </Pressable>
            <View style={styles.flag}>
              <Text style={styles.flagText}>🇺🇸</Text>
            </View>
            <Text style={styles.profileName}>{nome}</Text>
            <View style={styles.profileAvatar}>
              <Text style={styles.avatarText}>{nome.slice(0, 2).toUpperCase()}</Text>
            </View>
          </View>
        </View>

        <ScrollView contentContainerStyle={styles.content}>
          {children}
        </ScrollView>
      </LinearGradient>
    </View>
  );
}

const BORDER = 'rgba(255,255,255,0.12)';
const MUTED = 'rgba(255,255,255,0.65)';

const styles = StyleSheet.create({
  app: { flex: 1, flexDirection: 'row', backgroundColor: '#0f0f12' },
  sidebar: {
    width: 240,
    backgroundColor: '#111118',
    paddingHorizontal: 16,
    paddingTop: 18,
    paddingBottom: 12,
    borderRightWidth: 1,
    borderRightColor: '#000',
  },
  brand: { alignItems: 'flex-start', gap: 4, marginBottom: 20 },
  logo: { width: 120, height: 40, resizeMode: 'contain' },
  brandSub: { color: MUTED, fontSize: 11, letterSpacing: 3 },
  menu: { gap: 8 },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 10,
  },
  menuItemLeft: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  menuItemActive: { backgroundColor: 'rgba(255,255,255,0.08)' },
  menuText: { color: MUTED, fontWeight: '700', fontSize: 13 },
  menuTextActive: { color: '#fff' },
  main: { flex: 1 },
  topbar: {
    paddingHorizontal: 20,
    paddingTop: 18,
    paddingBottom: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  topTitle: { color: '#2b1a04', fontSize: 22, fontWeight: '900' },
  topSubtitle: { color: '#5b3618', fontSize: 12 },
  topRight: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  iconChip: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: 'rgba(255,255,255,0.25)',
    borderRadius: 10,
  },
  flag: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: 'rgba(255,255,255,0.2)',
    borderRadius: 10,
  },
  flagText: { fontSize: 14 },
  profileName: { color: '#2b1a04', fontWeight: '800' },
  profileAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(255,255,255,0.4)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: '#2b1a04', fontWeight: '900', fontSize: 13 },
  content: { paddingHorizontal: 20, paddingBottom: 20 },
});
