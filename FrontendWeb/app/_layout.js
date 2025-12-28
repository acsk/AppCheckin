import { Stack } from 'expo-router';
import React, { useEffect } from 'react';
import { useRouter, usePathname } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Toaster } from 'react-hot-toast';

export default function RootLayout() {
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    // Verificar autenticação na inicialização
    const checkInitialAuth = async () => {
      try {
        const token = await AsyncStorage.getItem('@appcheckin:token');
        console.log('🔑 Token encontrado:', token ? 'Sim' : 'Não');
        
        // Se não tem token e não está na tela de login, redirecionar
        if (!token && pathname !== '/login') {
          console.log('⚠️ Sem token, redirecionando para login...');
          router.replace('/login');
        }
      } catch (error) {
        console.error('❌ Erro ao verificar token inicial:', error);
      }
    };

    checkInitialAuth();
  }, []);

  return (
    <>
      <Toaster />
      <Stack
        screenOptions={{
          headerShown: false,
        }}
      >
        <Stack.Screen name="login" />
        <Stack.Screen name="index" />
      </Stack>
    </>
  );
}
