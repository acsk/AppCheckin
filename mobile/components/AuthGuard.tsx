import AsyncStorage from "@/src/utils/storage";
import { useRouter, useSegments } from "expo-router";
import React, { useEffect, useState } from "react";
import { ActivityIndicator, View } from "react-native";

// Rotas que NÃO precisam de autenticação
const PUBLIC_ROUTES = ["(auth)", "index", "modal"];

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

interface AuthGuardProps {
  children: React.ReactNode;
}

/**
 * AuthGuard: Middleware que bloqueia acesso a rotas protegidas quando não há token
 * Impede renderização de conteúdo de rotas públicas enquanto verifica autenticação
 */
export function AuthGuard({ children }: AuthGuardProps) {
  const segments = useSegments();
  const router = useRouter();
  const [isCheckingAuth, setIsCheckingAuth] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    const checkAuthentication = async () => {
      try {
        const token = await AsyncStorage.getItem("@appcheckin:token");
        const authenticated = !!token;
        setIsAuthenticated(authenticated);

        // Obter rota atual
        const currentSegment = segments?.[0];

        // Se tentando acessar rota protegida sem token, redirecionar
        if (
          currentSegment &&
          PROTECTED_ROUTES.includes(currentSegment) &&
          !authenticated
        ) {
          console.log(
            `[AuthGuard] Acesso negado à rota protegida "${currentSegment}" - redirecionando para login`,
          );
          router.replace("/(auth)/login");
          return;
        }

        // Se autenticado mas tentando acessar auth, redirecionar para home
        if (currentSegment && currentSegment === "(auth)" && authenticated) {
          console.log(
            "[AuthGuard] Usuário autenticado, redirecionando de (auth) para home",
          );
          router.replace("/(tabs)");
          return;
        }
      } catch (error) {
        console.error("[AuthGuard] Erro ao verificar autenticação:", error);
      } finally {
        setIsCheckingAuth(false);
      }
    };

    checkAuthentication();
  }, [segments, router]);

  // Enquanto verifica autenticação, mostrar loading
  if (isCheckingAuth) {
    return (
      <View style={{ flex: 1, justifyContent: "center", alignItems: "center" }}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return <>{children}</>;
}

export default AuthGuard;
