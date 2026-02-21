import ErrorBoundary from "@/components/ErrorBoundary";
import { setOnUnauthorized as setOnUnauthorizedClient } from "@/src/api/client";
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
import React, { useEffect, useRef, useState } from "react";
import { AppState, Platform, StyleSheet, View } from "react-native";
import {
    SafeAreaProvider,
    useSafeAreaInsets,
} from "react-native-safe-area-context";

import { useColorScheme } from "@/hooks/use-color-scheme";
import { colors } from "@/src/theme/colors";

// Importar Reanimated apenas no mobile
if (Platform.OS !== "web") {
  require("react-native-reanimated");
}

// Rotas que NÃO precisam de autenticação
const PUBLIC_ROUTES = ["(auth)", "index"];

// Rotas que PRECISAM de autenticação
const PROTECTED_ROUTES = [
  "(tabs)",
  "planos",
  "plano-detalhes",
  "minhas-assinaturas",
  "matricula",
  "matricula-detalhes",
  "turma-detalhes",
  "checkin",
  "checkin-detalhes",
];

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const router = useRouter();
  const appState = useRef(AppState.currentState);
  const segments = useSegments();
  const [isTokenChecked, setIsTokenChecked] = useState(false);
  const [hasToken, setHasToken] = useState(false);

  // Guard: verificar token ao iniciar e redirecionar rotas protegidas
  useEffect(() => {
    const checkTokenAndGuard = async () => {
      console.log(
        "[RootLayout] Verificando autenticação... Segments:",
        segments,
      );

      const token = await AsyncStorage.getItem("@appcheckin:token");
      const authenticated = !!token;
      setHasToken(authenticated);

      // Obter rota atual (primeiro segment após root)
      const currentSegment = segments?.[0];

      // Se tentando acessar rota protegida sem token, redirecionar IMEDIATAMENTE
      if (
        currentSegment &&
        PROTECTED_ROUTES.includes(currentSegment) &&
        !authenticated
      ) {
        console.warn(
          `[RootLayout] ❌ Acesso negado à rota protegida: ${currentSegment} - redirecionando para login`,
        );
        // Usar setTimeout para garantir que o redirect aconteça
        setTimeout(() => {
          router.replace("/(auth)/login");
        }, 50);
      } else if (authenticated && currentSegment === "(auth)") {
        // Se autenticado tentando acessar (auth), redirecionar para home
        console.log(
          "[RootLayout] Usuário autenticado em (auth), redirecionando para home",
        );
        setTimeout(() => {
          router.replace("/(tabs)");
        }, 50);
      }

      setIsTokenChecked(true);
    };

    checkTokenAndGuard();
  }, [segments, router]);

  useEffect(() => {
    if (Platform.OS !== "web") return;
    if (typeof document === "undefined") return;

    const firstSegment = segments?.[0] || "";
    const secondSegment = segments?.[1] || "";

    const isLoginRoute =
      (firstSegment === "(auth)" || firstSegment === "auth") &&
      secondSegment === "login";

    const isRegisterRoute =
      (firstSegment === "(auth)" || firstSegment === "auth") &&
      secondSegment === "register-mobile";

    const shouldShowBadge = isLoginRoute || isRegisterRoute;

    document.body?.setAttribute(
      "data-recaptcha-visible",
      shouldShowBadge ? "true" : "false",
    );
  }, [segments]);

  useEffect(() => {
    // Configurar callback para tratar 401 globalmente
    setOnUnauthorized(async () => {
      console.log(
        "[RootLayout:setOnUnauthorized] Token inválido, redirecionando...",
      );
      await handleAuthError();
      console.log("[RootLayout:setOnUnauthorized] Executando router.replace");
      router.replace("/(auth)/login");
    });

    setOnUnauthorizedClient(async () => {
      console.log(
        "[RootLayout:setOnUnauthorizedClient] Token inválido, redirecionando...",
      );
      await handleAuthError();
      console.log(
        "[RootLayout:setOnUnauthorizedClient] Executando router.replace",
      );
      router.replace("/(auth)/login");
    });

    // Listener para mudanças no AppState
    const subscription = AppState.addEventListener("change", (nextAppState) => {
      appState.current = nextAppState;
    });

    return () => {
      subscription.remove();
    };
  }, [router]);

  // Não renderizar nada até verificar autenticação
  if (!isTokenChecked) {
    return (
      <SafeAreaProvider>
        <ThemeProvider
          value={colorScheme === "dark" ? DarkTheme : DefaultTheme}
        >
          <View style={styles.root} />
        </ThemeProvider>
      </SafeAreaProvider>
    );
  }

  return (
    <SafeAreaProvider>
      <ThemeProvider value={colorScheme === "dark" ? DarkTheme : DefaultTheme}>
        <ErrorBoundary>
          <View style={styles.root}>
            <StatusBarBackground />
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
          </View>
          <StatusBar style="light" backgroundColor={colors.primary} />
        </ErrorBoundary>
      </ThemeProvider>
    </SafeAreaProvider>
  );
}

function StatusBarBackground() {
  const insets = useSafeAreaInsets();
  if (!insets.top) return null;
  return (
    <View
      pointerEvents="none"
      style={[styles.statusBarFill, { height: insets.top }]}
    />
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
  statusBarFill: {
    backgroundColor: colors.primary,
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    zIndex: 10,
  },
});
