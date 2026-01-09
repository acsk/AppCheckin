import React, { useState, useEffect } from 'react';
import { StatusBar } from 'expo-status-bar';
import { 
  View, 
  ActivityIndicator, 
  StyleSheet, 
  Image, 
  Text, 
  TouchableOpacity, 
  Modal, 
  Pressable, 
  ScrollView,
  Dimensions 
} from 'react-native';
import { SafeAreaView, SafeAreaProvider } from 'react-native-safe-area-context';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createDrawerNavigator } from '@react-navigation/drawer';
import { TabView, SceneMap } from 'react-native-tab-view';
import { Feather } from '@expo/vector-icons';
import { setOnUnauthorized } from './src/services/api';
import { authService } from './src/services/authService';
import { colors } from './src/theme/colors';

// Screens
import LoginScreen from './src/screens/LoginScreen';
import CheckinScreen from './src/screens/CheckinScreen';
import WodScreen from './src/screens/WodScreen';
import PerfilScreen from './src/screens/PerfilScreen';
import MinhaContaScreen from './src/screens/MinhaContaScreen';
import HistoricoScreen from './src/screens/HistoricoScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();
const Drawer = createDrawerNavigator();
const { width: SCREEN_WIDTH } = Dimensions.get('window');

// Context para compartilhar user e logout
const AppContext = React.createContext();
export { AppContext };

// CustomDrawerContent para o Drawer Navigator
function CustomDrawerContent({ navigation, user, onLogout }) {
  const menuItems = [
    { icon: 'home', label: 'Início', screen: 'MainTabs', color: colors.primary },
    { icon: 'user', label: 'Meu Perfil', screen: 'Perfil', color: colors.info },
    { icon: 'settings', label: 'Minha Conta', screen: 'MinhaConta', color: colors.success },
    { icon: 'clock', label: 'Histórico', screen: 'Historico', color: '#8B5CF6' },
  ];

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: colors.background }}>
      {/* Header do Drawer */}
      <View style={styles.drawerHeader}>
        <Image
          source={{ uri: `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.nome || 'User')}&background=FF6B35&color=fff&size=100` }}
          style={styles.drawerAvatar}
        />
        <View style={styles.drawerUserInfo}>
          <Text style={styles.drawerUserName}>{user?.nome || 'Usuário'}</Text>
          <Text style={styles.drawerUserEmail}>{user?.email || ''}</Text>
        </View>
      </View>

      {/* Menu Items */}
      <ScrollView style={styles.drawerMenu} showsVerticalScrollIndicator={false}>
        {menuItems.map((item, index) => (
          <TouchableOpacity
            key={index}
            style={styles.drawerMenuItem}
            onPress={() => {
              if (item.screen === 'MainTabs') {
                navigation.reset({
                  index: 0,
                  routes: [{ name: 'MainTabs' }],
                });
              } else {
                navigation.navigate(item.screen);
              }
            }}
            activeOpacity={0.7}
          >
            <View style={[styles.drawerMenuIcon, { backgroundColor: item.color + '15' }]}>
              <Feather name={item.icon} size={20} color={item.color} />
            </View>
            <Text style={styles.drawerMenuText}>{item.label}</Text>
            <Feather name="chevron-right" size={18} color={colors.gray400} />
          </TouchableOpacity>
        ))}
      </ScrollView>

      {/* Logout */}
      <TouchableOpacity 
        style={styles.drawerLogout} 
        onPress={onLogout}
        activeOpacity={0.7}
      >
        <View style={[styles.drawerMenuIcon, { backgroundColor: colors.error + '15' }]}>
          <Feather name="log-out" size={20} color={colors.error} />
        </View>
        <Text style={styles.drawerLogoutText}>Sair da Conta</Text>
      </TouchableOpacity>
    </SafeAreaView>
  );
}

// Wrapper para as tabs
function MainTabsWrapper({ user, navigation }) {
  const [index, setIndex] = useState(0);
  const { width: screenWidth } = Dimensions.get('window');

  const CheckinRoute = () => <CheckinScreen user={user} />;
  const WodRoute = () => <WodScreen user={user} />;

  const routes = [
    { key: 'checkin', title: 'Check-in', icon: 'check-circle' },
    { key: 'wod', title: 'WOD', icon: 'zap' },
  ];

  const renderScene = SceneMap({
    checkin: CheckinRoute,
    wod: WodRoute,
  });

  const renderTabBar = (props) => (
    <View style={styles.tabBarContainer}>
      <View style={styles.tabBar}>
        <TouchableOpacity 
          onPress={() => navigation.openDrawer()}
          style={styles.tab}
        >
          <Feather name="menu" size={24} color={colors.gray400} />
          <Text style={[styles.tabLabel, { color: colors.gray400 }]}>Menu</Text>
        </TouchableOpacity>
        
        {routes.map((route, routeIndex) => {
          const isFocused = index === routeIndex;
          return (
            <TouchableOpacity
              key={route.key}
              style={[styles.tab, isFocused && styles.activeTab]}
              onPress={() => setIndex(routeIndex)}
            >
              <Feather 
                name={route.icon} 
                size={24} 
                color={isFocused ? colors.primary : colors.gray400} 
              />
              <Text 
                style={[
                  styles.tabLabel,
                  { color: isFocused ? colors.primary : colors.gray400 }
                ]}
              >
                {route.title}
              </Text>
            </TouchableOpacity>
          );
        })}
        
        <TouchableOpacity 
          style={styles.tab}
          onPress={() => navigation.navigate('Perfil')}
        >
          <Feather name="user" size={24} color={colors.gray400} />
          <Text style={[styles.tabLabel, { color: colors.gray400 }]}>Perfil</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={styles.headerContainer}>
        <Text style={styles.headerTitle}>
          {index === 0 ? 'Check-in' : 'WOD do Dia'}
        </Text>
      </View>
      <TabView
        navigationState={{ index, routes }}
        renderScene={renderScene}
        renderTabBar={() => null}
        onIndexChange={setIndex}
        initialLayout={{ width: screenWidth }}
        swipeEnabled={true}
        animationEnabled={true}
      />
      {renderTabBar()}
    </SafeAreaView>
  );
}

export default function App() {
  const [isLoading, setIsLoading] = useState(true);
  const [userToken, setUserToken] = useState(null);
  const [user, setUser] = useState(null);

  useEffect(() => {
    checkToken();
    
    setOnUnauthorized(() => {
      console.log('🔒 Token expirado, fazendo logout...');
      handleLogout();
    });

    return () => {
      setOnUnauthorized(null);
    };
  }, []);

  const checkToken = async () => {
    try {
      const token = await authService.getToken();
      const userData = await authService.getCurrentUser();
      
      if (token && userData) {
        setUserToken(token);
        setUser(userData);
      }
    } catch (error) {
      console.error('Erro ao verificar token:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleLogin = (token, userData) => {
    setUserToken(token);
    setUser(userData);
  };

  const handleLogout = async () => {
    try {
      await authService.logout();
      setUserToken(null);
      setUser(null);
    } catch (error) {
      console.error('Erro ao fazer logout:', error);
    }
  };

  if (isLoading) {
    return (
      <SafeAreaProvider>
        <View style={styles.loadingContainer}>
          <View style={styles.loadingLogoContainer}>
            <Feather name="check-circle" size={60} color={colors.primary} />
          </View>
          <Text style={styles.loadingTitle}>AppCheckin</Text>
          <ActivityIndicator size="large" color={colors.primary} style={{ marginTop: 30 }} />
        </View>
      </SafeAreaProvider>
    );
  }

  return (
    <SafeAreaProvider>
      <AppContext.Provider value={{ user, onLogout: handleLogout }}>
        <NavigationContainer>
          <StatusBar style="dark" />
          {userToken ? (
            <Drawer.Navigator
              drawerContent={(props) => <CustomDrawerContent {...props} user={user} onLogout={handleLogout} />}
              screenOptions={{
                headerShown: false,
                drawerType: 'slide',
                drawerStyle: {
                  width: SCREEN_WIDTH * 0.65,
                },
              }}
            >
              <Drawer.Screen name="MainTabs" options={{ title: 'Início' }}>
                {(props) => <MainTabsWrapper {...props} user={user} />}
              </Drawer.Screen>
              <Drawer.Screen 
                name="Perfil"
                options={{ title: 'Meu Perfil' }}
              >
                {(props) => <PerfilScreen {...props} user={user} />}
              </Drawer.Screen>
              <Drawer.Screen 
                name="MinhaConta"
                options={{ title: 'Minha Conta' }}
              >
                {(props) => <MinhaContaScreen {...props} user={user} onLogout={handleLogout} />}
              </Drawer.Screen>
              <Drawer.Screen 
                name="Historico"
                options={{ title: 'Histórico' }}
              >
                {(props) => <HistoricoScreen {...props} user={user} />}
              </Drawer.Screen>
            </Drawer.Navigator>
          ) : (
            <Stack.Navigator screenOptions={{ headerShown: false }}>
              <Stack.Screen name="Login">
                {(props) => <LoginScreen {...props} onLogin={handleLogin} />}
              </Stack.Screen>
            </Stack.Navigator>
          )}
        </NavigationContainer>
      </AppContext.Provider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    backgroundColor: colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingLogoContainer: {
    width: 100,
    height: 100,
    borderRadius: 25,
    backgroundColor: colors.primary + '15',
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.text,
    marginTop: 16,
  },
  // Drawer Styles
  drawerOverlay: {
    flex: 1,
    flexDirection: 'row',
    backgroundColor: colors.overlay,
  },
  drawerBackdrop: {
    flex: 1,
  },
  drawerContainer: {
    width: SCREEN_WIDTH * 0.65,
    maxWidth: 280,
    backgroundColor: colors.background,
    shadowColor: '#000',
    shadowOffset: { width: 2, height: 0 },
    shadowOpacity: 0.25,
    shadowRadius: 10,
    elevation: 20,
  },
  drawerSafeArea: {
    flex: 1,
  },
  drawerHeader: {
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
  },
  drawerUserSection: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  drawerAvatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
    borderWidth: 3,
    borderColor: colors.primary,
  },
  drawerUserInfo: {
    flex: 1,
    marginLeft: 14,
  },
  drawerUserName: {
    color: colors.text,
    fontSize: 17,
    fontWeight: '700',
  },
  drawerUserEmail: {
    color: colors.textSecondary,
    fontSize: 13,
    marginTop: 3,
  },
  closeButton: {
    padding: 4,
  },
  drawerMenu: {
    flex: 1,
    paddingVertical: 16,
  },
  drawerMenuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 14,
    paddingHorizontal: 20,
    marginHorizontal: 12,
    borderRadius: 12,
    marginBottom: 4,
  },
  drawerMenuIcon: {
    width: 42,
    height: 42,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  drawerMenuText: {
    flex: 1,
    color: colors.text,
    fontSize: 15,
    fontWeight: '500',
    marginLeft: 14,
  },
  drawerLogout: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    marginHorizontal: 12,
  },
  drawerLogoutText: {
    color: colors.error,
    fontSize: 15,
    fontWeight: '600',
    marginLeft: 14,
  },
  // TabView Styles
  headerContainer: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: colors.background,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  headerTitle: {
    color: colors.text,
    fontSize: 18,
    fontWeight: '600',
    textAlign: 'center',
  },
  tabBarContainer: {
    backgroundColor: colors.background,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  tabBar: {
    flexDirection: 'row',
    height: 85,
    paddingVertical: 10,
    alignItems: 'center',
    justifyContent: 'space-around',
    paddingHorizontal: 8,
  },
  tab: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 6,
  },
  activeTab: {
    borderBottomWidth: 3,
    borderBottomColor: colors.primary,
  },
  tabLabel: {
    fontSize: 12,
    fontWeight: '600',
    marginTop: 4,
  },
});
