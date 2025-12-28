import React, { useState, useEffect, useRef } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  Pressable, 
  ScrollView, 
  Image, 
  useWindowDimensions, 
  TouchableOpacity,
  Animated,
  Easing
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Feather } from '@expo/vector-icons';
import { useRouter, usePathname } from 'expo-router';
import { authService } from '../services/authService';

const MENU = [
  { label: 'Dashboard', path: '/', icon: 'home' },
  { label: 'Academias', path: '/academias', icon: 'briefcase' },
  { label: 'Contratos', path: '/contratos', icon: 'file-text' },
  { label: 'Planos', path: '/planos', icon: 'package' },
  { label: 'Usuários', path: '/usuarios', icon: 'users' },
];

const BREAKPOINT_MOBILE = 768;
const BREAKPOINT_TABLET = 1024;

export default function LayoutBase({ children, title = 'Dashboard', subtitle = 'Overview' }) {
  const router = useRouter();
  const pathname = usePathname();
  const { width } = useWindowDimensions();
  const [usuarioInfo, setUsuarioInfo] = React.useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  
  // Animações
  const slideAnim = useRef(new Animated.Value(-280)).current;
  const fadeAnim = useRef(new Animated.Value(0)).current;

  const isMobile = width < BREAKPOINT_MOBILE;
  const isTablet = width >= BREAKPOINT_MOBILE && width < BREAKPOINT_TABLET;

  React.useEffect(() => {
    authService
      .getCurrentUser()
      .then((user) => setUsuarioInfo(user))
      .catch(() => {});
  }, []);

  // Fechar drawer ao mudar de rota no mobile
  React.useEffect(() => {
    if (isMobile && drawerOpen) {
      closeDrawer();
    }
  }, [pathname]);

  // Animação de abertura/fechamento
  useEffect(() => {
    if (drawerOpen) {
      Animated.parallel([
        Animated.timing(slideAnim, {
          toValue: 0,
          duration: 300,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(fadeAnim, {
          toValue: 1,
          duration: 300,
          useNativeDriver: true,
        }),
      ]).start();
    } else {
      Animated.parallel([
        Animated.timing(slideAnim, {
          toValue: -280,
          duration: 250,
          easing: Easing.in(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(fadeAnim, {
          toValue: 0,
          duration: 250,
          useNativeDriver: true,
        }),
      ]).start();
    }
  }, [drawerOpen]);

  const openDrawer = () => setDrawerOpen(true);
  const closeDrawer = () => setDrawerOpen(false);

  const handleLogout = async () => {
    await authService.logout();
    router.replace('/login');
  };

  const nome = usuarioInfo?.nome || 'Usuário';

  const renderSidebarContent = () => (
    <View style={styles.sidebarContainer}>
      <View style={styles.brand}>
        <Image source={require('../../assets/img/logo.png')} style={styles.logo} />
        <Text style={styles.brandSub}>CHECK-IN</Text>
      </View>

      <ScrollView style={styles.menuScroll} showsVerticalScrollIndicator={false}>
        <View style={styles.menu}>
          {MENU.map((item) => {
            const selected = pathname === item.path || (item.path !== '/' && pathname.startsWith(item.path));
            const scaleAnim = useRef(new Animated.Value(1)).current;

            const handlePressIn = () => {
              Animated.spring(scaleAnim, {
                toValue: 0.95,
                useNativeDriver: true,
                speed: 50,
                bounciness: 4,
              }).start();
            };

            const handlePressOut = () => {
              Animated.spring(scaleAnim, {
                toValue: 1,
                useNativeDriver: true,
                speed: 50,
                bounciness: 8,
              }).start();
            };

            return (
              <Pressable
                key={item.label}
                onPress={() => {
                  router.push(item.path);
                  if (isMobile) closeDrawer();
                }}
                onPressIn={handlePressIn}
                onPressOut={handlePressOut}
                style={[styles.menuItem, selected && styles.menuItemActive]}
              >
                <Animated.View 
                  style={[
                    styles.menuItemContent,
                    { transform: [{ scale: scaleAnim }] }
                  ]}
                >
                  <View style={styles.menuItemLeft}>
                    <Feather name={item.icon} size={18} color={selected ? '#fff' : '#d1d5db'} />
                    <Text style={[styles.menuText, selected && styles.menuTextActive]}>{item.label}</Text>
                  </View>
                  {selected && <View style={styles.menuItemIndicator} />}
                </Animated.View>
              </Pressable>
            );
          })}
        </View>
      </ScrollView>

      {/* Footer do Menu */}
      <View style={styles.menuFooter}>
        <View style={styles.userInfo}>
          <View style={styles.userAvatar}>
            <Text style={styles.userAvatarText}>{nome.slice(0, 2).toUpperCase()}</Text>
          </View>
          <View style={styles.userDetails}>
            <Text style={styles.userName}>{nome}</Text>
            <Text style={styles.userRole}>Super Admin</Text>
          </View>
        </View>
        <Pressable style={styles.logoutButton} onPress={handleLogout}>
          <Feather name="log-out" size={16} color="#d1d5db" />
        </Pressable>
      </View>
    </View>
  );

  return (
    <View style={styles.app}>
      {/* Sidebar Desktop/Tablet */}
      {!isMobile && (
        <View style={styles.sidebar}>
          {renderSidebarContent()}
        </View>
      )}

      {/* Drawer Mobile com Animação */}
      {isMobile && drawerOpen && (
        <View style={styles.drawerContainer}>
          {/* Overlay com fade */}
          <Animated.View 
            style={[
              styles.drawerOverlay,
              { opacity: fadeAnim }
            ]}
          >
            <TouchableOpacity
              style={styles.overlayTouchable}
              activeOpacity={1}
              onPress={closeDrawer}
            />
          </Animated.View>

          {/* Drawer com slide */}
          <Animated.View
            style={[
              styles.drawerContent,
              {
                transform: [{ translateX: slideAnim }]
              }
            ]}
          >
            {renderSidebarContent()}
            
            {/* Botão fechar */}
            <TouchableOpacity
              style={styles.closeDrawerButton}
              onPress={closeDrawer}
            >
              <Feather name="x" size={24} color="#fff" />
            </TouchableOpacity>
          </Animated.View>
        </View>
      )}

      {/* Content Area */}
      <LinearGradient
        colors={['#f1e7d9ff', '#a0a0a0ff', '#f3eaf8ff']}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 0 }}
        style={styles.main}
      >
        <View style={[styles.topbar, isMobile && styles.topbarMobile]}>
          {/* Menu Hamburger - Mobile */}
          {isMobile && (
            <TouchableOpacity
              style={styles.hamburgerButton}
              onPress={openDrawer}
            >
              <Feather name="menu" size={24} color="#ffffff" />
            </TouchableOpacity>
          )}

          <View style={styles.topLeft}>
            <Text style={[styles.topTitle, isMobile && styles.topTitleMobile]}>{title}</Text>
            {!isMobile && <Text style={styles.topSubtitle}>{subtitle}</Text>}
          </View>

          <View style={[styles.topRight, isMobile && styles.topRightMobile, isTablet && styles.topRightTablet]}>
            {!isMobile && (
              <>
                <View style={styles.iconChip}>
                  <Feather name="bell" size={16} color="#ffffff" />
                </View>
                <View style={styles.iconChip}>
                  <Feather name="settings" size={16} color="#ffffff" />
                </View>
              </>
            )}
            <Pressable style={styles.iconChip} onPress={handleLogout}>
              <Feather name="log-out" size={16} color="#ffffff" />
            </Pressable>
            {!isMobile && (
              <>
                <View style={styles.flag}>
                  <Text style={styles.flagText}>🇺🇸</Text>
                </View>
                <Text style={[styles.profileName, isTablet && styles.profileNameHidden]}>{nome}</Text>
              </>
            )}
            <View style={styles.profileAvatar}>
              <Text style={styles.avatarText}>{nome.slice(0, 2).toUpperCase()}</Text>
            </View>
          </View>
        </View>

        <ScrollView contentContainerStyle={[styles.content, isMobile && styles.contentMobile]}>
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
  
  // Sidebar Desktop/Tablet
  sidebar: {
    width: 240,
    backgroundColor: '#111118',
    borderRightWidth: 1,
    borderRightColor: '#000',
  },
  sidebarContainer: {
    flex: 1,
    paddingHorizontal: 16,
    paddingTop: 18,
    paddingBottom: 12,
  },
  
  // Drawer Mobile
  drawerContainer: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    zIndex: 1000,
  },
  drawerOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0,0,0,0.6)',
  },
  overlayTouchable: {
    flex: 1,
  },
  drawerContent: {
    position: 'absolute',
    top: 0,
    left: 0,
    bottom: 0,
    width: 280,
    backgroundColor: '#111118',
    shadowColor: '#000',
    shadowOffset: { width: 4, height: 0 },
    shadowOpacity: 0.5,
    shadowRadius: 12,
    elevation: 16,
    paddingHorizontal: 16,
    paddingTop: 18,
    paddingBottom: 12,
  },
  closeDrawerButton: {
    position: 'absolute',
    top: 16,
    right: 16,
    padding: 8,
    backgroundColor: 'rgba(255,255,255,0.15)',
    borderRadius: 8,
    zIndex: 10,
  },
  
  // Menu
  brand: { alignItems: 'flex-start', gap: 4, marginBottom: 20 },
  logo: { width: 120, height: 40, resizeMode: 'contain' },
  brandSub: { color: MUTED, fontSize: 11, letterSpacing: 3 },
  menuScroll: { flex: 1 },
  menu: { gap: 6, paddingBottom: 20 },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 12,
    position: 'relative',
  },
  menuItemContent: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    flex: 1,
  },
  menuItemLeft: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  menuItemActive: { 
    backgroundColor: 'rgba(249,115,22,0.15)',
    borderLeftWidth: 3,
    borderLeftColor: '#f97316',
  },
  menuItemIndicator: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: '#f97316',
  },
  menuText: { color: MUTED, fontWeight: '600', fontSize: 14 },
  menuTextActive: { color: '#fff', fontWeight: '700' },
  
  // Footer do Menu
  menuFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingTop: 16,
    paddingBottom: 8,
    borderTopWidth: 1,
    borderTopColor: 'rgba(255,255,255,0.1)',
    marginTop: 8,
  },
  userInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  userAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#f97316',
    alignItems: 'center',
    justifyContent: 'center',
  },
  userAvatarText: {
    color: '#fff',
    fontWeight: '900',
    fontSize: 14,
  },
  userDetails: {
    flex: 1,
  },
  userName: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 13,
  },
  userRole: {
    color: MUTED,
    fontSize: 11,
  },
  logoutButton: {
    padding: 8,
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderRadius: 8,
  },
  
  // Main Content
  main: { flex: 1 },
  topbar: {
    paddingHorizontal: 20,
    paddingTop: 18,
    paddingBottom: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#111118',
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255,255,255,0.08)',
  },
  topbarMobile: {
    paddingHorizontal: 12,
    paddingTop: 12,
    paddingBottom: 12,
  },
  topLeft: {
    flex: 1,
  },
  hamburgerButton: {
    padding: 8,
    marginRight: 12,
    backgroundColor: 'rgba(255,255,255,0.15)',
    borderRadius: 8,
  },
  topTitle: { color: '#ffffff', fontSize: 22, fontWeight: '900' },
  topTitleMobile: { fontSize: 18 },
  topSubtitle: { color: 'rgba(255,255,255,0.7)', fontSize: 12 },
  topRight: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  topRightMobile: { gap: 6 },
  topRightTablet: { gap: 8 },
  iconChip: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderRadius: 10,
  },
  flag: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderRadius: 10,
  },
  flagText: { fontSize: 14 },
  profileName: { color: '#ffffff', fontWeight: '800' },
  profileNameHidden: { display: 'none' },
  profileAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#f97316',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: '#ffffff', fontWeight: '900', fontSize: 13 },
  content: { paddingHorizontal: 20, paddingTop: 20, paddingBottom: 20 },
  contentMobile: { paddingHorizontal: 12, paddingTop: 16, paddingBottom: 16 },
});
