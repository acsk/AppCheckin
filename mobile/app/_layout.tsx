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
