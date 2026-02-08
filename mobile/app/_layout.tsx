import ErrorBoundary from "@/components/ErrorBoundary";
import { setOnUnauthorized } from "@/src/services/api";
import { handleAuthError } from "@/src/utils/authHelpers";
import {
    DarkTheme,
    DefaultTheme,
    ThemeProvider,
} from "@react-navigation/native";
import { Stack, useRouter } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useEffect, useRef } from "react";
import { AppState, Platform } from "react-native";

import { useColorScheme } from "@/hooks/use-color-scheme";

// Importar Reanimated apenas no mobile
if (Platform.OS !== "web") {
  require("react-native-reanimated");
}

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const router = useRouter();
  const appState = useRef(AppState.currentState);

  useEffect(() => {
    // Configurar callback para tratar 401 globalmente
    setOnUnauthorized(async () => {
      await handleAuthError();
      router.replace("/(auth)/login");
    });

    // Listener para mudanças no AppState
    const subscription = AppState.addEventListener("change", (nextAppState) => {
      appState.current = nextAppState;
    });

    return () => {
      subscription.remove();
    };
  }, []);

  return (
    <ThemeProvider value={colorScheme === "dark" ? DarkTheme : DefaultTheme}>
      <ErrorBoundary>
        <Stack>
          <Stack.Screen name="index" options={{ headerShown: false }} />
          <Stack.Screen name="(auth)" options={{ headerShown: false }} />
          <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
          <Stack.Screen name="planos" options={{ headerShown: false }} />
          <Stack.Screen
            name="plano-detalhes"
            options={{ headerShown: false }}
          />
          <Stack.Screen
            name="minhas-assinaturas"
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
      </ErrorBoundary>
    </ThemeProvider>
  );
}
