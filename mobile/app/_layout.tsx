import { setOnUnauthorized } from "@/src/services/api";
import { handleAuthError } from "@/src/utils/authHelpers";
import AsyncStorage from "@/src/utils/storage";
import {
    DarkTheme,
    DefaultTheme,
    ThemeProvider,
} from "@react-navigation/native";
import { Stack, useRouter, useSegments } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useEffect, useRef, useState } from "react";
import { AppState, Platform } from "react-native";

import { useColorScheme } from "@/hooks/use-color-scheme";

// Importar Reanimated apenas no mobile
if (Platform.OS !== "web") {
  require("react-native-reanimated");
}

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const [isLoggedIn, setIsLoggedIn] = useState<boolean | null>(null);
  const router = useRouter();
  const segments = useSegments();
  const appState = useRef(AppState.currentState);
  const checkAuthTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    // Verificação inicial
    checkAuthStatus();

    // Configurar callback para tratar 401 globalmente
    setOnUnauthorized(async () => {
      await handleAuthError();
      router.replace("/(auth)/login");
    });

    // Listener para mudanças no AsyncStorage (detectar login em outra aba)
    const subscription = AppState.addEventListener(
      "change",
      handleAppStateChange,
    );

    return () => {
      subscription.remove();
      if (checkAuthTimeoutRef.current) {
        clearTimeout(checkAuthTimeoutRef.current);
      }
    };
  }, []);

  const handleAppStateChange = (nextAppState: string) => {
    if (appState.current !== nextAppState) {
      appState.current = nextAppState;
      // Re-verificar quando app volta do background
      if (nextAppState === "active") {
        checkAuthStatus();
      }
    }
  };

  const checkAuthStatus = async () => {
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      const hasToken = !!token;
      console.log("✅ Token check - hasToken:", hasToken);
      setIsLoggedIn(hasToken);

      // NÃO redirecionar aqui - deixar o Expo Router gerenciar as rotas
      // Se não tem token, o usuário verá a tela de login
      // Se tem token, verá a tela autenticada
    } catch (error) {
      console.error("❌ Erro ao verificar token:", error);
      setIsLoggedIn(false);
      // NÃO redirecionar em caso de erro
    }
  };

  // Proteger rotas com base no estado de autenticação
  useEffect(() => {
    if (isLoggedIn === null) return; // Ainda carregando

    const inAuthGroup = segments[0] === "(auth)";
    const isIndexRoute = segments.length === 0;

    console.log(
      "🔐 Route Protection - isLoggedIn:",
      isLoggedIn,
      "inAuthGroup:",
      inAuthGroup,
      "segments:",
      segments,
    );

    // Se não está autenticado
    if (!isLoggedIn) {
      if (!inAuthGroup && !isIndexRoute) {
        console.log("🚀 Redirecionando para login (desautenticado)");
        router.replace("/(auth)/login");
      }
    } else {
      // Se está autenticado
      if (inAuthGroup) {
        console.log("🚀 Redirecionando para tabs (já autenticado)");
        router.replace("/(tabs)");
      }
    }
  }, [isLoggedIn]);

  if (isLoggedIn === null) {
    return null;
  }

  return (
    <ThemeProvider value={colorScheme === "dark" ? DarkTheme : DefaultTheme}>
      <Stack>
        <Stack.Screen name="(auth)" options={{ headerShown: false }} />
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Screen name="planos" options={{ headerShown: false }} />
        <Stack.Screen
          name="matricula-detalhes"
          options={{ headerShown: false }}
        />
        <Stack.Screen name="turma-detalhes" options={{ headerShown: false }} />
      </Stack>
      <StatusBar style="auto" />
    </ThemeProvider>
  );
}
