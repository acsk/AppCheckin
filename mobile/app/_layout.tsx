import { setOnUnauthorized } from '@/src/services/api';
import { handleAuthError } from '@/src/utils/authHelpers';
import AsyncStorage from '@/src/utils/storage';
import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Stack, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { Platform } from 'react-native';

// Importar Reanimated apenas no mobile
if (Platform.OS !== 'web') {
  require('react-native-reanimated');
}

import { useColorScheme } from '@/hooks/use-color-scheme';

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const [isLoggedIn, setIsLoggedIn] = useState<boolean | null>(null);
  const router = useRouter();

  useEffect(() => {
    checkAuthStatus();
    
    // Configurar callback para tratar 401 globalmente
    setOnUnauthorized(async () => {
      await handleAuthError();
      router.replace('/(auth)/login');
    });
  }, []);

  const checkAuthStatus = async () => {
    try {
      const token = await AsyncStorage.getItem('@appcheckin:token');
      setIsLoggedIn(!!token);
    } catch (error) {
      setIsLoggedIn(false);
    }
  };

  if (isLoggedIn === null) {
    return null;
  }

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <Stack>
        <Stack.Screen 
          name="(auth)" 
          options={{ headerShown: false }} 
        />
        <Stack.Screen 
          name="(tabs)" 
          options={{ headerShown: false }} 
        />
        <Stack.Screen 
          name="index" 
          options={{ headerShown: false }} 
        />
        <Stack.Screen 
          name="planos" 
          options={{ headerShown: false }} 
        />
        <Stack.Screen 
          name="matricula-detalhes" 
          options={{ headerShown: false }} 
        />
        <Stack.Screen 
          name="turma-detalhes" 
          options={{ headerShown: false }} 
        />
      </Stack>
      <StatusBar style="auto" />
    </ThemeProvider>
  );
}
