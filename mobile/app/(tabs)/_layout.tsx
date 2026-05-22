import { useColorScheme } from "@/hooks/use-color-scheme";
import { useMatriculaAcesso } from "@/src/hooks/useMatriculaAcesso";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import { Tabs } from "expo-router";
import React from "react";
import {
  ActivityIndicator,
  Platform,
  StyleSheet,
  Text,
  View,
} from "react-native";

export default function TabLayout() {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === "dark";
  const isWeb = Platform.OS === "web";
  const { loading: acessoLoading, bloqueado, mensagem } = useMatriculaAcesso();

  return (
    <>
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: "#fff",
        tabBarInactiveTintColor: "rgba(255,255,255,0.65)",
        tabBarStyle: {
          borderTopWidth: 0,
          backgroundColor: colors.primary,
          paddingBottom: isWeb ? 14 : 10,
          paddingTop: isWeb ? 14 : 10,
          height: isWeb ? 82 : 76,
          shadowColor: "#000",
          shadowOffset: { width: 0, height: -1 },
          shadowOpacity: 0.2,
          shadowRadius: 6,
          elevation: 10,
        },
        tabBarItemStyle: {
          marginHorizontal: 6,
        },
        tabBarLabelStyle: {
          fontSize: 13,
          fontWeight: "700",
        },
        headerStyle: {
          backgroundColor: isDark ? "#1a1a1a" : "#fff",
          borderBottomColor: isDark ? "#333" : "#e5e5e5",
          borderBottomWidth: 1,
        },
        headerTintColor: isDark ? "#fff" : "#000",
        headerTitleStyle: {
          fontWeight: "600",
          fontSize: 18,
        },
      }}
    >
      <Tabs.Screen
        name="account"
        options={{
          title: "Minha Conta",
          tabBarLabel: ({ color }) => (
            <Text style={[styles.tabBarLabel, { color }]} numberOfLines={1}>
              Minha Conta
            </Text>
          ),
          tabBarIcon: ({ color, size }) => (
            <Feather name="user" size={size + 6} color={color} />
          ),
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="wod"
        options={{
          title: "WOD",
          tabBarIcon: ({ color, size }) => (
            <Feather name="target" size={size + 6} color={color} />
          ),
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="checkin"
        options={{
          title: "Checkin",
          tabBarIcon: ({ color, size }) => (
            <Feather name="check-square" size={size + 6} color={color} />
          ),
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="logout"
        options={{
          title: "Sair",
          tabBarLabel: ({ color }) => (
            <Text style={[styles.tabBarLabel, { color }]} numberOfLines={1}>
              Sair
            </Text>
          ),
          tabBarIcon: ({ color, size }) => (
            <Feather name="log-out" size={size + 6} color={color} />
          ),
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="index"
        options={{
          href: null,
        }}
      />
      <Tabs.Screen
        name="planos"
        options={{
          title: "Planos",
          tabBarIcon: ({ color, size }) => (
            <Feather name="shopping-cart" size={size + 6} color={color} />
          ),
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="plano-detalhes"
        options={{
          href: null,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="minhas-assinaturas"
        options={{
          href: null,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="matricula"
        options={{
          href: null,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="matricula-detalhes"
        options={{
          href: null,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="turma-detalhes"
        options={{
          href: null,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="checkin-detalhes"
        options={{
          href: null,
          headerShown: false,
        }}
      />
    </Tabs>

    {!acessoLoading && bloqueado && (
      <View style={[styles.bloqueioOverlay, { bottom: isWeb ? 82 : 76 }]}>
        <View style={styles.bloqueioCard}>
          <View style={styles.bloqueioIconWrap}>
            <Feather name="lock" size={32} color="#7c3aed" />
          </View>
          <Text style={styles.bloqueioTitle}>Acesso bloqueado</Text>
          <Text style={styles.bloqueioMessage}>
            {mensagem ||
              "Sua matrícula está bloqueada. Entre em contato com a academia."}
          </Text>
          <Text style={styles.bloqueioHint}>
            Você ainda pode sair da conta pela aba Sair.
          </Text>
        </View>
      </View>
    )}

    {acessoLoading && (
      <View style={styles.acessoLoadingOverlay}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    )}
    </>
  );
}

const styles = StyleSheet.create({
  tabBarLabel: {
    fontSize: 13,
    fontWeight: "700",
    marginTop: 2,
  },
  bloqueioOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(15, 23, 42, 0.92)",
    justifyContent: "center",
    alignItems: "center",
    padding: 24,
    zIndex: 1000,
  },
  bloqueioCard: {
    maxWidth: 360,
    width: "100%",
    backgroundColor: "#fff",
    borderRadius: 16,
    padding: 28,
    alignItems: "center",
  },
  bloqueioIconWrap: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: "#ede9fe",
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 16,
  },
  bloqueioTitle: {
    fontSize: 20,
    fontWeight: "700",
    color: "#0f172a",
    marginBottom: 8,
    textAlign: "center",
  },
  bloqueioMessage: {
    fontSize: 15,
    color: "#475569",
    textAlign: "center",
    lineHeight: 22,
  },
  bloqueioHint: {
    fontSize: 12,
    color: "#94a3b8",
    textAlign: "center",
    marginTop: 16,
  },
  acessoLoadingOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(255,255,255,0.6)",
    justifyContent: "center",
    alignItems: "center",
    zIndex: 999,
  },
});
