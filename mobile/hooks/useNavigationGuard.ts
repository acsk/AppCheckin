/**
 * Navigation Guard para Expo Router
 * Bloqueia acesso a rotas protegidas quando não há token autenticado
 */

import AsyncStorage from "@/src/utils/storage";
import { useSegments, useRouter, usePathname } from "expo-router";
import { useEffect, useRef } from "react";

// Rotas públicas (sem autenticação)
const PUBLIC_ROUTES = ["(auth)", "index", "modal", "register-success"];

// Rotas protegidas (requer autenticação)
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

export function useNavigationGuard() {
  const segments = useSegments();
  const router = useRouter();
  const pathname = usePathname();
  const guardTimeoutRef = useRef<NodeJS.Timeout>();

  useEffect(() => {
    // Limpar timeout anterior
    if (guardTimeoutRef.current) {
      clearTimeout(guardTimeoutRef.current);
    }

    const performGuard = async () => {
      try {
        const token = await AsyncStorage.getItem("@appcheckin:token");
        const isAuthenticated = !!token;

        // Obter rota atual a partir de segments
        const currentRoute = segments?.[0];

        // Debug
        console.log("[NavigationGuard]", {
          pathname,
          currentRoute,
          isAuthenticated,
          segments,
        });

        // Se rota protegida e sem autenticação: redirecionar
        if (
          currentRoute &&
          PROTECTED_ROUTES.includes(currentRoute) &&
          !isAuthenticated
        ) {
          console.log(
            `[NavigationGuard] ⚠️ Acesso negado à rota protegida: ${currentRoute}`
          );
          guardTimeoutRef.current = setTimeout(() => {
            router.replace("/(auth)/login");
          }, 100);
          return;
        }

        // Se estiver autenticado em rota de auth: redirecionar
        if (
          currentRoute === "(auth)" &&
          isAuthenticated &&
          !pathname.includes("register")
        ) {
          console.log("[NavigationGuard] Usuário autenticado, redirecionando de (auth)");
          guardTimeoutRef.current = setTimeout(() => {
            router.replace("/(tabs)");
          }, 100);
        }
      } catch (error) {
        console.error("[NavigationGuard] Erro:", error);
      }
    };

    performGuard();

    return () => {
      if (guardTimeoutRef.current) {
        clearTimeout(guardTimeoutRef.current);
      }
    };
  }, [segments, router, pathname]);
}

export default useNavigationGuard;
