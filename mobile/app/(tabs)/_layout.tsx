import { useColorScheme } from "@/hooks/use-color-scheme";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import { Tabs } from "expo-router";
import React from "react";
import { Platform, StyleSheet, Text } from "react-native";

export default function TabLayout() {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === "dark";
  const isWeb = Platform.OS === "web";

  return (
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
          href: null,
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
  );
}

const styles = StyleSheet.create({
  tabBarLabel: {
    fontSize: 13,
    fontWeight: "700",
    marginTop: 2,
  },
});
