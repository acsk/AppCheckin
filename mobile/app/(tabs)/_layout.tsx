import { useColorScheme } from '@/hooks/use-color-scheme';
import { colors } from '@/src/theme/colors';
import { Feather } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import React from 'react';
import { Platform } from 'react-native';

export default function TabLayout() {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const isWeb = Platform.OS === 'web';

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: '#999',
        tabBarStyle: {
          borderTopWidth: 0,
          backgroundColor: isWeb ? '#fff' : (isDark ? '#1a1a1a' : '#fff'),
          paddingBottom: isWeb ? 12 : 8,
          paddingTop: isWeb ? 12 : 8,
          height: isWeb ? 75 : 70,
          shadowColor: '#000',
          shadowOffset: { width: 0, height: -1 },
          shadowOpacity: 0.1,
          shadowRadius: 3,
          elevation: 8,
        },
        tabBarItemStyle: {
          marginHorizontal: 8,
        },
        headerStyle: {
          backgroundColor: isDark ? '#1a1a1a' : '#fff',
          borderBottomColor: isDark ? '#333' : '#e5e5e5',
          borderBottomWidth: 1,
        },
        headerTintColor: isDark ? '#fff' : '#000',
        headerTitleStyle: {
          fontWeight: '600',
          fontSize: 18,
        },
      }}>
      <Tabs.Screen
        name="account"
        options={{
          title: 'Minha Conta',
          tabBarIcon: ({ color, size }) => (
            <Feather name="user" size={size} color={color} />
          ),
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="wod"
        options={{
          title: 'WOD',
          tabBarIcon: ({ color, size }) => (
            <Feather name="target" size={size} color={color} />
          ),
          headerShown: false,
        }}
      />
      <Tabs.Screen
        name="checkin"
        options={{
          title: 'Checkin',
          tabBarIcon: ({ color, size }) => (
            <Feather name="check-square" size={size} color={color} />
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
    </Tabs>
  );
}
