import AsyncStorage from '@react-native-async-storage/async-storage';
import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import 'react-native-reanimated';

import { useColorScheme } from '@/hooks/use-color-scheme';

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const [isLoggedIn, setIsLoggedIn] = useState<boolean | null>(null);

  useEffect(() => {
    checkAuthStatus();
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
      <Stack screenOptions={{ animationEnabled: false }}>
        {!isLoggedIn ? (
          <Stack.Screen 
            name="(auth)" 
            options={{ headerShown: false }} 
          />
        ) : (
          <Stack.Screen 
            name="index" 
            options={{ headerShown: false }} 
          />
        )}
        <Stack.Screen 
          name="planos" 
          options={{ headerShown: false }} 
        />
        <Stack.Screen 
          name="matricula-detalhes" 
          options={{ headerShown: false }} 
        />
      </Stack>
      <StatusBar style="auto" />
    </ThemeProvider>
  );
}
