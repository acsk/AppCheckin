import { Stack } from 'expo-router';
import React, { useEffect } from 'react';
import { useRouter, usePathname } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Toaster } from 'react-hot-toast';
import { setOnUnauthorized } from '../src/services/api';

export default function RootLayout() {
  const router = useRouter();
  const pathname = usePathname();

  // Função para redirecionar para login
  const redirectToLogin = () => {
    console.log('🔄 Redirecionando para tela de login...');
    router.replace('/login');
  };

  useEffect(() => {
    // Configurar callback para quando a API retornar 401
    setOnUnauthorized(redirectToLogin);

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
        router.replace('/login');
      }
    };

    checkInitialAuth();

    // Cleanup
    return () => {
      setOnUnauthorized(null);
    };
  }, []);

  // Verificar token a cada mudança de rota
  useEffect(() => {
    const checkAuth = async () => {
      if (pathname !== '/login') {
        const token = await AsyncStorage.getItem('@appcheckin:token');
        if (!token) {
          console.log('⚠️ Token não existe, voltando para login...');
          router.replace('/login');
        }
      }
    };
    
    checkAuth();
  }, [pathname]);

  return (
    <>
      <Toaster 
        containerStyle={{
          zIndex: 999999,
        }}
        toastOptions={{
          style: {
            zIndex: 999999,
          },
        }}
      />
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
