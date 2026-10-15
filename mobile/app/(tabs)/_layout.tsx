import BirthdayModal from "@/src/components/BirthdayModal";
import { useBirthdayGreeting } from "@/src/hooks/useBirthdayGreeting";
import AppTabBar from "@/src/components/ds/AppTabBar";
import { ds } from "@/src/components/ds/tokens";
import { Tabs } from "expo-router";
import React from "react";

export default function TabLayout() {
  const birthday = useBirthdayGreeting(true);

  return (
    <>
    <Tabs
      tabBar={(props) => <AppTabBar {...props} />}
      screenOptions={{
        // Fundo das telas: ground (cinza claro do design system), nunca branco.
        sceneStyle: { backgroundColor: ds.ground },
        // Troca de aba com um leve deslocamento (Bottom Tabs v7).
        animation: "shift",
        headerShown: false,
      }}
    >
      {/* Ordem: Início · WOD · Check-in (centro) · Planos · Conta */}
      <Tabs.Screen
        name="account"
        options={{
          title: "Início",
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="wod"
        options={{
          title: "WOD",
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="checkin"
        options={{
          title: "Check-in",
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="planos"
        options={{
          title: "Planos",
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="conta"
        options={{
          title: "Conta",
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
