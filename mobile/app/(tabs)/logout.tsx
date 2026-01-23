import AsyncStorage from "@/src/utils/storage";
import { useRouter } from "expo-router";
import React, { useCallback, useEffect } from "react";
import { View } from "react-native";

export default function LogoutScreen() {
  const router = useRouter();

  const handleLogout = useCallback(async () => {
    try {
      // Remover dados de autenticação
      await AsyncStorage.removeItem("@appcheckin:token");
      await AsyncStorage.removeItem("@appcheckin:user");
      await AsyncStorage.removeItem("@appcheckin:tenants");
      await AsyncStorage.removeItem("@appcheckin:tenant");
      await AsyncStorage.removeItem("@appcheckin:current_tenant");

      // Redirecionar para login
      router.replace("/(auth)/login");
    } catch (error) {
      console.error("Erro ao fazer logout:", error);
      router.replace("/(auth)/login");
    }
  }, [router]);

  useEffect(() => {
    handleLogout();
  }, [handleLogout]);

  return <View />;
}
