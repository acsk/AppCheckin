/**
 * Hook para verificar autenticação em componentes protegidos
 * Se não autenticado, redireciona para login
 */

import AsyncStorage from "@/src/utils/storage";
import { useRouter, useNavigation } from "expo-router";
import { useEffect, useState } from "react";

interface UseProtectedRouteOptions {
  onUnauthorized?: () => void;
  checkFn?: (token: string) => Promise<boolean>;
  delayMs?: number;
}

export function useProtectedRoute(options: UseProtectedRouteOptions = {}) {
  const router = useRouter();
  const navigation = useNavigation();
  const [isLoading, setIsLoading] = useState(true);
  const [isAuthorized, setIsAuthorized] = useState(false);

  const { onUnauthorized, checkFn, delayMs = 0 } = options;

  useEffect(() => {
    const checkAuth = async () => {
      try {
        // Delay para garantir que layout tenha sido carregado
        if (delayMs > 0) {
          await new Promise((resolve) => setTimeout(resolve, delayMs));
        }

        const token = await AsyncStorage.getItem("@appcheckin:token");

        if (!token) {
          console.warn("[useProtectedRoute] Token não encontrado, redirecionando");
          if (onUnauthorized) {
            onUnauthorized();
          }
          setIsAuthorized(false);
          setIsLoading(false);
          router.replace("/(auth)/login");
          return;
        }

        // Se houver função de checagem customizada, executá-la
        if (checkFn) {
          const authorized = await checkFn(token);
          if (!authorized) {
            console.warn("[useProtectedRoute] Autorização customizada falhou");
            if (onUnauthorized) {
              onUnauthorized();
            }
            setIsAuthorized(false);
            setIsLoading(false);
            router.replace("/(auth)/login");
            return;
          }
        }

        console.log("[useProtectedRoute] Usuário autorizado");
        setIsAuthorized(true);
      } catch (error) {
        console.error("[useProtectedRoute] Erro na verificação:", error);
        setIsAuthorized(false);
      } finally {
        setIsLoading(false);
      }
    };

    checkAuth();
  }, [router, onUnauthorized, checkFn, delayMs]);

  return { isLoading, isAuthorized };
}

export default useProtectedRoute;
