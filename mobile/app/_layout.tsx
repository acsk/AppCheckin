import ErrorBoundary from "@/components/ErrorBoundary";
import { setOnUnauthorized as setOnUnauthorizedClient } from "@/src/api/client";
import SessionExpiredModal from "@/src/components/SessionExpiredModal";
import { setOnUnauthorized } from "@/src/services/api";
import { clearAuthStorage, handleAuthError } from "@/src/utils/authHelpers";
import {
  dismissSessionExpired,
  isSessionExpiredVisible,
  subscribeSessionExpired,
} from "@/src/utils/sessionExpired";
import AsyncStorage from "@/src/utils/storage";
import {
    DarkTheme,
    DefaultTheme,
    ThemeProvider,
} from "@react-navigation/native";
import { Stack, useRouter, useSegments } from "expo-router";
import { StatusBar } from "expo-status-bar";
import React, { useCallback, useEffect, useRef, useState } from "react";
import { ActivityIndicator, AppState, Platform, StyleSheet, View } from "react-native";
import {
    SafeAreaProvider,
    useSafeAreaInsets,
} from "react-native-safe-area-context";

import { useColorScheme } from "@/hooks/use-color-scheme";
import AppSplash from "@/src/components/AppSplash";
import AtualizacaoCadastralGate from "@/src/components/AtualizacaoCadastralGate";
import ForceUpdateGate from "@/src/components/ForceUpdateGate";
import * as SplashScreen from "expo-splash-screen";
import { colors } from "@/src/theme/colors";
import { ThemePreferenceProvider } from "@/src/theme/ThemePreference";
import { ds } from "@/src/components/ds/tokens";
import { GatewayPickerProvider } from "@/src/hooks/useGatewayPicker";
import { atualizarConfigRemota, trocarContextoConfigRemota } from "@/src/services/remoteConfig";

// Importar Reanimated apenas no mobile
if (Platform.OS !== "web") {
  require("react-native-reanimated");
  // A splash nativa fica até a AppSplash (logo + barra) ser desenhada por cima.
  void SplashScreen.preventAutoHideAsync().catch(() => {});
}

// Web: estilos globais — sem contorno azul nos campos em foco e selo do reCAPTCHA controlado.
// O foco continua visível pela borda do próprio campo (TextField do design system).
if (Platform.OS === "web" && typeof document !== "undefined") {
  const STYLE_ID = "ac-no-input-outline";
  if (!document.getElementById(STYLE_ID)) {
    const style = document.createElement("style");
    style.id = STYLE_ID;
    style.textContent = [
      "input:focus, textarea:focus, select:focus, input:focus-visible, textarea:focus-visible { outline: none !important; box-shadow: none; }",
      // Selo do reCAPTCHA: só aparece no login e no cadastro (data-recaptcha-visible),
      // telas sem a barra inferior. Nas demais fica oculto para não cobrir a barra.
      ".grecaptcha-badge { visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }",
      'body[data-recaptcha-visible="true"] .grecaptcha-badge { visibility: visible !important; opacity: 1 !important; pointer-events: auto !important; bottom: 16px !important; z-index: 9999 !important; }',
    ].join("\n");
    document.head.appendChild(style);
  }
}

// Tema claro com o fundo ground do design system (as telas não ficam brancas).
const APP_THEME = {
  ...DefaultTheme,
  colors: { ...DefaultTheme.colors, background: ds.ground, card: ds.surface, text: ds.ink, border: ds.line, primary: ds.brand },
};

const APP_DARK_THEME = {
  ...DarkTheme,
  colors: { ...DarkTheme.colors, ...APP_THEME.colors },
};

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
  "checkin-turma",
  "recordes",
  "recorde-prova",
];

export default function RootLayout() {
  return (
    <ThemePreferenceProvider>
      <RootContent />
    </ThemePreferenceProvider>
  );
}

function RootContent() {
  const colorScheme = useColorScheme();
  const router = useRouter();
  const appState = useRef(AppState.currentState);
  const segments = useSegments();
  const [isTokenChecked, setIsTokenChecked] = useState(false);
  const [splashVisible, setSplashVisible] = useState(Platform.OS !== "web");
  const hideSplash = useCallback(() => setSplashVisible(false), []);
  const [hasToken, setHasToken] = useState(false);
  const [sessionExpiredVisible, setSessionExpiredVisible] = useState(false);
  const [sessionExpiredMessage, setSessionExpiredMessage] = useState(
    "Sua sessão expirou. Por favor, faça login novamente.",
  );

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

      // Enquanto a modal de sessão expirada está aberta, não redirecionar
      // automaticamente — o usuário confirma e vai para o login.
      // Usa o flag do módulo (síncrono) para evitar race com o setState do React.
      if (sessionExpiredVisible || isSessionExpiredVisible()) {
        setIsTokenChecked(true);
        return;
      }

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
  }, [segments, router, sessionExpiredVisible]);

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
    const unsubscribe = subscribeSessionExpired((message) => {
      setHasToken(false);
      setSessionExpiredMessage(message);
      setSessionExpiredVisible(true);
    });
    return unsubscribe;
  }, []);

  useEffect(() => {
    // Configurar callback para tratar 401 globalmente (abre modal, sem redirect imediato)
    setOnUnauthorized(async () => {
      console.log(
        "[RootLayout:setOnUnauthorized] Token inválido — abrindo modal de sessão expirada",
      );
      await handleAuthError();
    });

    setOnUnauthorizedClient(async () => {
      console.log(
        "[RootLayout:setOnUnauthorizedClient] Token inválido — abrindo modal de sessão expirada",
      );
      await handleAuthError();
    });

    // Listener para mudanças no AppState
    const subscription = AppState.addEventListener("change", (nextAppState) => {
      // Voltou para o app: busca a configuração remota se o intervalo dela venceu
      if (nextAppState === "active" && appState.current !== "active") void atualizarConfigRemota();
      appState.current = nextAppState;
    });

    return () => {
      subscription.remove();
    };
  }, []);

  // Configuração remota: ao abrir, no login e no logout (cada contexto tem a sua)
  useEffect(() => {
    if (isTokenChecked) void trocarContextoConfigRemota();
  }, [hasToken, isTokenChecked]);

  const handleSessionExpiredConfirm = useCallback(async () => {
    dismissSessionExpired();
    setSessionExpiredVisible(false);
    setHasToken(false);
    await clearAuthStorage();
    router.replace("/(auth)/login");
  }, [router]);

  // No app nativo a AppSplash cobre o carregamento; na web segue o indicador simples.
  const bootScreen = Platform.OS !== "web" ? null : (
    <View style={[styles.root, styles.bootSplash]}>
      <ActivityIndicator size="large" color={colors.primary} />
    </View>
  );

  return (
    <SafeAreaProvider>
      <ThemeProvider value={colorScheme === "dark" ? (Platform.OS === "web" ? APP_DARK_THEME : DarkTheme) : APP_THEME}>
        <ErrorBoundary>
          <GatewayPickerProvider>
          <ForceUpdateGate>
          {!isTokenChecked ? bootScreen : (
          <View style={styles.root}>
            <StatusBarBackground />
            <Stack screenOptions={{ contentStyle: { backgroundColor: ds.ground } }}>
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
                name="recordes"
                options={{ headerShown: false }}
              />
              <Stack.Screen
                name="recorde-prova"
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
              <Stack.Screen
                name="checkin-turma"
                options={{ headerShown: false }}
              />
              <Stack.Screen
                name="checkin-detalhes"
                options={{ headerShown: false }}
              />
            </Stack>
            <AtualizacaoCadastralGate enabled={hasToken && segments[0] !== "(auth)" && !sessionExpiredVisible} />
            <SessionExpiredModal
              visible={sessionExpiredVisible}
              message={sessionExpiredMessage}
              onConfirm={handleSessionExpiredConfirm}
            />
          </View>
          )}
          </ForceUpdateGate>
          </GatewayPickerProvider>
          <StatusBar style="light" backgroundColor={colors.primary} />
          {splashVisible ? <AppSplash done={isTokenChecked} onFinish={hideSplash} /> : null}
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
  bootSplash: {
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: ds.surface,
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
