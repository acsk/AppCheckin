import AsyncStorage from "@/src/utils/storage";
import { Redirect } from "expo-router";
import { useEffect, useState } from "react";
import { ActivityIndicator, Text, View } from "react-native";

export default function Index() {
  const [isLoading, setIsLoading] = useState(true);
  const [hasToken, setHasToken] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    console.log("📍 Index - Iniciando verificação de token");
    checkToken();
  }, []);

  const checkToken = async () => {
    try {
      console.log("📍 Index - Buscando token...");
      const token = await AsyncStorage.getItem("@appcheckin:token");
      console.log("📍 Index - Token encontrado:", !!token);
      setHasToken(!!token);
    } catch (err) {
      console.error("📍 Index - Erro ao buscar token:", err);
      setError(String(err));
      setHasToken(false);
    } finally {
      console.log("📍 Index - Finalizando verificação");
      setIsLoading(false);
    }
  };

  console.log(
    "📍 Index - Render: isLoading=",
    isLoading,
    "hasToken=",
    hasToken,
  );

  if (error) {
    return (
      <View
        style={{
          flex: 1,
          justifyContent: "center",
          alignItems: "center",
          backgroundColor: "#fff",
        }}
      >
        <Text style={{ color: "red" }}>Erro: {error}</Text>
      </View>
    );
  }

  if (isLoading) {
    return (
      <View
        style={{
          flex: 1,
          justifyContent: "center",
          alignItems: "center",
          backgroundColor: "#fff",
        }}
      >
        <ActivityIndicator size="large" color="#FF6B35" />
        <Text style={{ marginTop: 10 }}>Carregando...</Text>
      </View>
    );
  }

  if (hasToken) {
    console.log("📍 Index - Redirecionando para /(tabs)");
    return <Redirect href="/(tabs)" />;
  }

  console.log("📍 Index - Redirecionando para /(auth)/login");
  return <Redirect href="/(auth)/login" />;
}
