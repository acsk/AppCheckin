import { useColorScheme } from "@/hooks/use-color-scheme";
import BirthdayModal from "@/src/components/BirthdayModal";
import { useBirthdayGreeting } from "@/src/hooks/useBirthdayGreeting";
import { colors } from "@/src/theme/colors";
import { Feather } from "@expo/vector-icons";
import { Tabs } from "expo-router";
import React from "react";
import { Platform, StyleSheet, Text, View } from "react-native";

export default function TabLayout() {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === "dark";
  const isWeb = Platform.OS === "web";
  const birthday = useBirthdayGreeting(true);

  return (
    <>
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: NAV.ink,
        tabBarInactiveTintColor: NAV.inkMuted,
        tabBarStyle: {
          borderTopWidth: 1,
          borderTopColor: NAV.line,
          backgroundColor: NAV.surface,
          paddingBottom: isWeb ? 10 : 8,
          paddingTop: 8,
          height: isWeb ? 76 : 72,
          shadowColor: "#171a21",
          shadowOffset: { width: 0, height: -8 },
          shadowOpacity: 0.07,
          shadowRadius: 24,
          elevation: 12,
        },
        tabBarItemStyle: {
          paddingTop: 2,
        },
        tabBarLabel: ({ focused, color, children }) => (
          <Text
            style={[styles.tabBarLabel, { color, fontWeight: focused ? "800" : "700" }]}
            numberOfLines={1}
          >
            {children}
          </Text>
        ),
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
      {/* Ordem: Início · WOD · Check-in (centro) · Planos · Conta */}
      <Tabs.Screen
        name="account"
        options={{
          title: "Início",
          tabBarIcon: ({ focused }) => <TabIcon name="home" focused={focused} />,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="wod"
        options={{
          title: "WOD",
          tabBarIcon: ({ focused }) => <TabIcon name="target" focused={focused} />,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="checkin"
        options={{
          title: "Check-in",
          tabBarIcon: ({ focused }) => <TabIcon name="check-square" focused={focused} />,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="planos"
        options={{
          title: "Planos",
          tabBarIcon: ({ focused }) => <TabIcon name="shopping-cart" focused={focused} />,
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="conta"
        options={{
          title: "Conta",
          tabBarIcon: ({ focused }) => <TabIcon name="user" focused={focused} />,
          headerShown: false,
        }}
      />
      {/* Sair não é aba: fica no fim da tela Conta. A rota continua existindo. */}
      <Tabs.Screen
        name="logout"
        options={{
          href: null,
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
      <Tabs.Screen
        name="checkin-turma"
        options={{
          href: null,
          headerShown: false,
        }}
      />
    </Tabs>
    <BirthdayModal
      visible={birthday.visible}
      nome={birthday.nome}
      idade={birthday.idade}
      onClose={birthday.dismiss}
    />
    </>
  );
}

// Tokens do design system AppCheckin para a barra inferior.
const NAV = {
  surface: "#ffffff",
  line: "#e4e6ea",
  ink: "#171a21",
  inkMuted: "#5b6270",
};

function TabIcon({
  name,
  focused,
}: {
  name: React.ComponentProps<typeof Feather>["name"];
  focused: boolean;
}) {
  return (
    <View style={[styles.pill, focused && styles.pillActive]}>
      <Feather name={name} size={22} color={focused ? NAV.ink : NAV.inkMuted} />
    </View>
  );
}

const styles = StyleSheet.create({
  tabBarLabel: {
    fontSize: 11,
    lineHeight: 14,
    marginTop: 4,
  },
  pill: {
    width: 52,
    height: 30,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  pillActive: {
    backgroundColor: colors.primary,
  },
});
