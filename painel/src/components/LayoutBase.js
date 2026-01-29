import React, { useState, useEffect, useRef } from 'react';
import { 
  View, 
  Text, 
  Pressable, 
  ScrollView, 
  useWindowDimensions, 
  TouchableOpacity,
  Animated,
  Easing,
  Platform,
  StatusBar
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter, usePathname } from 'expo-router';
import { authService } from '../services/authService';

const MENU = [
  { label: 'Dashboard', path: '/', icon: 'home', roles: [3, 4] },
  { label: 'Academias', path: '/academias', icon: 'briefcase', roles: [4] },
  { label: 'Contratos', path: '/contratos', icon: 'file-text', roles: [4] },
  { label: 'Alunos', path: '/alunos', icon: 'users', roles: [3, 4] },
  { label: 'Matrículas', path: '/matriculas', icon: 'user-check', roles: [3, 4] },
  { label: 'Planos', path: '/planos', icon: 'package', roles: [3, 4] },
  { label: 'Modalidades', path: '/modalidades', icon: 'activity', roles: [3, 4] },
  { label: 'Professores', path: '/professores', icon: 'user', roles: [3, 4] },
  { label: 'Aulas', path: '/turmas', icon: 'calendar', roles: [3, 4] },
  { label: 'WOD', path: '/wods', icon: 'zap', roles: [3, 4] },
  { label: 'Planos Sistema', path: '/planos-sistema', icon: 'layers', roles: [4] },
  { label: 'Usuários', path: '/usuarios', icon: 'users', roles: [3, 4] },
  { label: 'Formas de Pagamento', path: '/formas-pagamento', icon: 'credit-card', roles: [3, 4] },
];

const BREAKPOINT_MOBILE = 768;
const BREAKPOINT_TABLET = 1024;
const DRAWER_WIDTH = 280;

export default function LayoutBase({ children, title = 'Dashboard', subtitle = 'Overview', noPadding = false }) {
  const router = useRouter();
  const pathname = usePathname();
  const { width, height } = useWindowDimensions();
  const [usuarioInfo, setUsuarioInfo] = React.useState(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [drawerVisible, setDrawerVisible] = useState(false);
  
  // Animações
  const slideAnim = useRef(new Animated.Value(-DRAWER_WIDTH)).current;
  const fadeAnim = useRef(new Animated.Value(0)).current;
  const scaleAnim = useRef(new Animated.Value(0.95)).current;

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
      setDrawerVisible(true);
      Animated.parallel([
        Animated.spring(slideAnim, {
          toValue: 0,
          tension: 65,
          friction: 11,
          useNativeDriver: true,
        }),
        Animated.timing(fadeAnim, {
          toValue: 1,
          duration: 250,
          useNativeDriver: true,
        }),
        Animated.spring(scaleAnim, {
          toValue: 1,
          tension: 65,
          friction: 11,
          useNativeDriver: true,
        }),
      ]).start();
    } else {
      Animated.parallel([
        Animated.timing(slideAnim, {
          toValue: -DRAWER_WIDTH,
          duration: 200,
          easing: Easing.in(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(fadeAnim, {
          toValue: 0,
          duration: 200,
          useNativeDriver: true,
        }),
        Animated.timing(scaleAnim, {
          toValue: 0.95,
          duration: 200,
          useNativeDriver: true,
        }),
      ]).start(() => {
        setDrawerVisible(false);
      });
    }
  }, [drawerOpen]);

  const openDrawer = () => setDrawerOpen(true);
  const closeDrawer = () => setDrawerOpen(false);

  const handleLogout = async () => {
    await authService.logout();
    router.replace('/login');
  };

  const nome = usuarioInfo?.nome || 'Usuário';

  const renderSidebarContent = (isMobileDrawer = false) => (
    <View className="flex-1">
      {/* Header do Sidebar */}
      <View className={`flex-row items-center justify-between ${isMobileDrawer ? 'px-5 pt-4 pb-3' : 'px-4 pt-4 pb-3'}`}>
        <View className="flex-row items-center gap-2.5">
          <View className="h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-orange-600" style={{ backgroundColor: '#f97316' }}>
            <Feather name="activity" size={18} color="#fff" />
          </View>
          <View>
            <Text className="text-sm font-bold text-slate-900">CHECK-IN</Text>
            <Text className="text-[10px] text-slate-400">Painel Admin</Text>
          </View>
        </View>
        {isMobileDrawer && (
          <TouchableOpacity
            className="h-8 w-8 items-center justify-center rounded-full bg-slate-100"
            onPress={closeDrawer}
            activeOpacity={0.7}
          >
            <Feather name="x" size={18} color="#64748b" />
          </TouchableOpacity>
        )}
      </View>

      {/* Divider */}
      <View className={`h-px bg-slate-100 ${isMobileDrawer ? 'mx-5' : 'mx-4'}`} />

      {/* Menu Items */}
      <ScrollView 
        className="flex-1" 
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingVertical: 8, paddingHorizontal: isMobileDrawer ? 12 : 10 }}
      >
        <View className="gap-0">
          {MENU.filter(item => {
            const userRole = usuarioInfo?.role_id || 1;
            return item.roles.includes(userRole);
          }).map((item) => {
            const selected = pathname === item.path || 
              (item.path !== '/' && pathname.startsWith(item.path + '/'));

            return (
              <Pressable
                key={item.label}
                onPress={() => {
                  router.push(item.path);
                  if (isMobile) closeDrawer();
                }}
                style={({ pressed }) => [
                  { opacity: pressed ? 0.7 : 1, transform: [{ scale: pressed ? 0.98 : 1 }] }
                ]}
                className={`flex-row items-center gap-3 rounded-xl px-3.5 py-2 ${
                  selected 
                    ? 'bg-orange-500' 
                    : 'bg-transparent'
                }`}
              >
                <View className={`h-8 w-8 items-center justify-center rounded-lg ${
                  selected ? 'bg-white/20' : 'bg-slate-100'
                }`}>
                  <Feather 
                    name={item.icon} 
                    size={16} 
                    color={selected ? '#ffffff' : '#64748b'} 
                  />
                </View>
                <Text className={`flex-1 text-[13px] font-medium ${
                  selected ? 'text-white' : 'text-slate-600'
                }`}>
                  {item.label}
                </Text>
                {selected && (
                  <View className="h-2 w-2 rounded-full bg-white" />
                )}
              </Pressable>
            );
          })}
        </View>
      </ScrollView>

      {/* Footer do Menu */}
      <View className={`border-t border-slate-100 ${isMobileDrawer ? 'mx-5 px-0 py-4' : 'mx-4 px-0 py-3'}`}>
        <View className="flex-row items-center gap-3">
          <View className="h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-orange-600" style={{ backgroundColor: '#f97316' }}>
            <Text className="text-sm font-black text-white">{nome.slice(0, 2).toUpperCase()}</Text>
          </View>
          <View className="flex-1">
            <Text className="text-sm font-semibold text-slate-900" numberOfLines={1}>{nome}</Text>
            <Text className="text-[11px] text-slate-400">
              {usuarioInfo?.role_id === 4 ? 'Super Admin' : usuarioInfo?.role_id === 3 ? 'Admin' : 'Usuário'}
            </Text>
          </View>
          <TouchableOpacity 
            className="h-9 w-9 items-center justify-center rounded-lg bg-red-50"
            onPress={handleLogout}
            activeOpacity={0.7}
          >
            <Feather name="log-out" size={16} color="#ef4444" />
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );

  return (
    <View className="flex-1 flex-row bg-slate-50">
      {/* Sidebar Desktop/Tablet */}
      {!isMobile && (
        <View className="w-60 border-r border-slate-200 bg-white">
          {renderSidebarContent(false)}
        </View>
      )}

      {/* Drawer Mobile com Animação */}
      {isMobile && drawerVisible && (
        <View 
          className="absolute inset-0 z-50"
          style={{ 
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
          }}
        >
          {/* Overlay com blur effect */}
          <Animated.View 
            style={{ 
              position: 'absolute',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              backgroundColor: 'rgba(15, 23, 42, 0.6)',
              opacity: fadeAnim 
            }}
          >
            <TouchableOpacity
              style={{ flex: 1 }}
              activeOpacity={1}
              onPress={closeDrawer}
            />
          </Animated.View>

          {/* Drawer Panel */}
          <Animated.View
            style={{
              position: 'absolute',
              top: 0,
              left: 0,
              bottom: 0,
              width: DRAWER_WIDTH,
              backgroundColor: '#ffffff',
              transform: [
                { translateX: slideAnim },
                { scale: scaleAnim }
              ],
              borderTopRightRadius: 24,
              borderBottomRightRadius: 24,
              shadowColor: '#000',
              shadowOffset: { width: 4, height: 0 },
              shadowOpacity: 0.15,
              shadowRadius: 20,
              elevation: 20,
            }}
          >
            {renderSidebarContent(true)}
          </Animated.View>
        </View>
      )}

      {/* Content Area */}
      <View className="flex-1 bg-slate-50">
        {/* Header Bar - Redesigned for Mobile */}
        <View 
          className={`flex-row items-center justify-between border-b border-slate-200 bg-white ${isMobile ? 'px-4 py-3' : 'px-5 pt-3 pb-2.5'}`}
          style={isMobile ? { 
            shadowColor: '#000',
            shadowOffset: { width: 0, height: 2 },
            shadowOpacity: 0.05,
            shadowRadius: 4,
            elevation: 3,
          } : {}}
        >
          {/* Menu Hamburger - Mobile */}
          {isMobile && (
            <TouchableOpacity
              className="mr-3 items-center justify-center rounded-xl bg-gradient-to-r from-orange-500 to-orange-400 p-2.5"
              onPress={openDrawer}
              style={{
                backgroundColor: '#f97316',
                shadowColor: '#f97316',
                shadowOffset: { width: 0, height: 2 },
                shadowOpacity: 0.3,
                shadowRadius: 4,
                elevation: 4,
              }}
            >
              <Feather name="menu" size={20} color="#ffffff" />
            </TouchableOpacity>
          )}

          <View className="flex-1">
            <Text className={`${isMobile ? 'text-lg' : 'text-xl'} font-bold text-slate-900`}>{title}</Text>
            {!isMobile && <Text className="text-[11px] text-slate-500">{subtitle}</Text>}
          </View>

          <View className={`flex-row items-center ${isMobile ? 'gap-2' : isTablet ? 'gap-2' : 'gap-2.5'}`}>
            {!isMobile && (
              <>
                <View className="rounded-md bg-slate-100 px-2 py-1">
                  <Feather name="bell" size={14} color="#f97316" />
                </View>
                <View className="rounded-md bg-slate-100 px-2 py-1">
                  <Feather name="settings" size={14} color="#f97316" />
                </View>
              </>
            )}
            {isMobile && (
              <TouchableOpacity 
                className="items-center justify-center rounded-xl bg-slate-100 p-2"
                onPress={() => {}}
              >
                <Feather name="bell" size={18} color="#64748b" />
              </TouchableOpacity>
            )}
            {!isMobile && (
              <Pressable className="rounded-md bg-slate-100 px-2 py-1" onPress={handleLogout}>
                <Feather name="log-out" size={14} color="#f97316" />
              </Pressable>
            )}
            {!isMobile && (
              <>
                <View className="rounded-md bg-slate-100 px-2 py-1">
                  <Text className="text-xs">🇺🇸</Text>
                </View>
                <Text className={`text-xs font-semibold text-slate-800 ${isTablet ? 'hidden' : ''}`}>{nome}</Text>
              </>
            )}
            <TouchableOpacity 
              className="h-9 w-9 items-center justify-center rounded-full bg-orange-500"
              style={{
                shadowColor: '#f97316',
                shadowOffset: { width: 0, height: 2 },
                shadowOpacity: 0.3,
                shadowRadius: 4,
                elevation: 4,
              }}
            >
              <Text className="text-xs font-black text-white">{nome.slice(0, 2).toUpperCase()}</Text>
            </TouchableOpacity>
          </View>
        </View>

        <ScrollView contentContainerStyle={{ paddingHorizontal: noPadding ? 0 : isMobile ? 12 : 18, paddingTop: noPadding ? 0 : isMobile ? 14 : 16, paddingBottom: noPadding ? 0 : isMobile ? 14 : 16 }}>
          {children}
        </ScrollView>
      </View>
    </View>
  );
}
