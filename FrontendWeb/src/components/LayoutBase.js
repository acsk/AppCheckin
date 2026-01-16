import React, { useState, useEffect, useRef } from 'react';
import { 
  View, 
  Text, 
  Pressable, 
  ScrollView, 
  useWindowDimensions, 
  TouchableOpacity,
  Animated,
  Easing
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import { useRouter, usePathname } from 'expo-router';
import { authService } from '../services/authService';

const MENU = [
  { label: 'Dashboard', path: '/', icon: 'home', roles: [1, 2, 3] }, // Todos
  { label: 'Academias', path: '/academias', icon: 'briefcase', roles: [3] }, // SuperAdmin apenas
  { label: 'Contratos', path: '/contratos', icon: 'file-text', roles: [3] }, // SuperAdmin apenas
  { label: 'Matrículas', path: '/matriculas', icon: 'user-check', roles: [2, 3] }, // Admin e SuperAdmin
  { label: 'Planos', path: '/planos', icon: 'package', roles: [2, 3] }, // Admin e SuperAdmin
  { label: 'Modalidades', path: '/modalidades', icon: 'activity', roles: [2, 3] }, // Admin e SuperAdmin
  { label: 'Professores', path: '/professores', icon: 'user', roles: [2, 3] }, // Admin e SuperAdmin
  { label: 'Aulas', path: '/turmas', icon: 'calendar', roles: [2, 3] }, // Admin e SuperAdmin
  { label: 'WOD', path: '/wods', icon: 'zap', roles: [2, 3] }, // Admin e SuperAdmin
  { label: 'Planos Sistema', path: '/planos-sistema', icon: 'layers', roles: [3] }, // SuperAdmin apenas
  { label: 'Usuários', path: '/usuarios', icon: 'users', roles: [2, 3] }, // Admin e SuperAdmin
  { label: 'Formas de Pagamento', path: '/formas-pagamento', icon: 'credit-card', roles: [2, 3] }, // Admin e SuperAdmin
];

const BREAKPOINT_MOBILE = 768;
const BREAKPOINT_TABLET = 1024;

export default function LayoutBase({ children, title = 'Dashboard', subtitle = 'Overview', noPadding = false }) {
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
    <View className="flex-1 px-4 pb-3 pt-4">
      <View className="mb-3 items-start gap-1">
        <View className="h-7 w-7 items-center justify-center rounded-lg bg-orange-500/15">
          <Feather name="activity" size={16} color="#f97316" />
        </View>
        <Text className="text-[9px] tracking-[3px] text-slate-500">CHECK-IN</Text>
      </View>

      <ScrollView className="flex-1" showsVerticalScrollIndicator={false}>
        <View className="gap-1 pb-4">
          {MENU.filter(item => {
            // Filtrar menu baseado no role do usuário
            const userRole = usuarioInfo?.role_id || 1;
            return item.roles.includes(userRole);
          }).map((item) => {
            // Evita que /planos-sistema ative o menu /planos
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
                  pressed && { opacity: 0.8 }
                ]}
                className={`relative rounded-lg px-3 py-2 ${selected ? 'bg-orange-100 border-l-2 border-orange-500' : 'border-l-2 border-transparent'}`}
              >
                <View className="flex-row items-center justify-between">
                  <View className="flex-row items-center gap-3">
                    <Feather name={item.icon} size={16} color={selected ? '#f97316' : '#94a3b8'} />
                    <Text className={`${selected ? 'text-slate-900 font-semibold' : 'text-slate-500 font-medium'} text-xs`}>{item.label}</Text>
                  </View>
                  {selected && <View className="h-1.5 w-1.5 rounded-full bg-orange-500" />}
                </View>
              </Pressable>
            );
          })}
        </View>
      </ScrollView>

      {/* Footer do Menu */}
      <View className="mt-1 flex-row items-center justify-between border-t border-slate-200 pt-3 pb-2">
        <View className="flex-1 flex-row items-center gap-2">
          <View className="h-9 w-9 items-center justify-center rounded-full bg-orange-500">
            <Text className="text-[12px] font-black text-white">{nome.slice(0, 2).toUpperCase()}</Text>
          </View>
          <View className="flex-1">
            <Text className="text-[12px] font-semibold text-slate-900">{nome}</Text>
            <Text className="text-[10px] text-slate-500">
              {usuarioInfo?.role_id === 3 ? 'Super Admin' : usuarioInfo?.role_id === 2 ? 'Admin' : 'Usuário'}
            </Text>
          </View>
        </View>
        <Pressable className="rounded-md bg-slate-100 p-1.5" onPress={handleLogout}>
          <Feather name="log-out" size={14} color="#94a3b8" />
        </Pressable>
      </View>
    </View>
  );

  return (
    <View className="flex-1 flex-row bg-slate-50">
      {/* Sidebar Desktop/Tablet */}
      {!isMobile && (
        <View className="w-56 border-r border-slate-200 bg-white">
          {renderSidebarContent()}
        </View>
      )}

      {/* Drawer Mobile com Animação */}
      {isMobile && drawerOpen && (
        <View className="absolute inset-0 z-50">
          {/* Overlay com fade */}
          <Animated.View 
            style={[
              { opacity: fadeAnim }
            ]}
            className="absolute inset-0 bg-black/30"
          >
            <TouchableOpacity
              className="flex-1"
              activeOpacity={1}
              onPress={closeDrawer}
            />
          </Animated.View>

          {/* Drawer com slide */}
          <Animated.View
            style={[
              {
                transform: [{ translateX: slideAnim }]
              }
            ]}
            className="absolute bottom-0 left-0 top-0 w-[260px] bg-white px-4 pb-3 pt-4 shadow-2xl"
          >
            {renderSidebarContent()}
            
            {/* Botão fechar */}
            <TouchableOpacity
              className="absolute right-4 top-4 rounded-lg bg-slate-100 p-2"
              onPress={closeDrawer}
            >
              <Feather name="x" size={24} color="#94a3b8" />
            </TouchableOpacity>
          </Animated.View>
        </View>
      )}

      {/* Content Area */}
      <View className="flex-1 bg-slate-50">
        <View className={`flex-row items-center justify-between border-b border-slate-200 bg-white ${isMobile ? 'px-3 py-2.5' : 'px-5 pt-3 pb-2.5'}`}>
          {/* Menu Hamburger - Mobile */}
          {isMobile && (
            <TouchableOpacity
              className="mr-3 rounded-md bg-slate-100 p-1.5"
              onPress={openDrawer}
            >
              <Feather name="menu" size={20} color="#f97316" />
            </TouchableOpacity>
          )}

          <View className="flex-1">
            <Text className={`${isMobile ? 'text-base' : 'text-xl'} font-bold text-slate-900`}>{title}</Text>
            {!isMobile && <Text className="text-[11px] text-slate-500">{subtitle}</Text>}
          </View>

          <View className={`flex-row items-center ${isMobile ? 'gap-1.5' : isTablet ? 'gap-2' : 'gap-2.5'}`}>
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
            <Pressable className="rounded-md bg-slate-100 px-2 py-1" onPress={handleLogout}>
              <Feather name="log-out" size={14} color="#f97316" />
            </Pressable>
            {!isMobile && (
              <>
                <View className="rounded-md bg-slate-100 px-2 py-1">
                  <Text className="text-xs">🇺🇸</Text>
                </View>
                <Text className={`text-xs font-semibold text-slate-800 ${isTablet ? 'hidden' : ''}`}>{nome}</Text>
              </>
            )}
            <View className="h-8 w-8 items-center justify-center rounded-full bg-orange-500">
              <Text className="text-[11px] font-black text-white">{nome.slice(0, 2).toUpperCase()}</Text>
            </View>
          </View>
        </View>

        <ScrollView contentContainerStyle={{ paddingHorizontal: noPadding ? 0 : isMobile ? 12 : 18, paddingTop: noPadding ? 0 : isMobile ? 14 : 16, paddingBottom: noPadding ? 0 : isMobile ? 14 : 16 }}>
          {children}
        </ScrollView>
      </View>
    </View>
  );
}
