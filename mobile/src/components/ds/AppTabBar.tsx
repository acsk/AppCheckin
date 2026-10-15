// AppCheckin — barra inferior flutuante (grafite) com pílula laranja que desliza até a aba ativa.
// Adaptação do `react-native/AppTabBar.tsx` do design system para o Bottom Tabs (expo-router Tabs).
import type { BottomTabBarProps } from "@react-navigation/bottom-tabs";
import React, { useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  Animated,
  LayoutChangeEvent,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { TabIcon, TabIconName } from "./TabIcon";
import { ds } from "./tokens";

const nav = {
  bg: "#171a21",
  onNavMuted: "#a3aab6",
  border: "rgba(255, 255, 255, 0.06)",
  height: 64,
  margin: 12,
  padding: 6,
  radius: 22,
  pillRadius: 16,
};

/** Rotas que aparecem na barra, na ordem, com o ícone de cada uma. */
export const TAB_ICONS: Record<string, TabIconName> = {
  account: "home",
  wod: "target",
  checkin: "check-square",
  planos: "cart",
  conta: "user",
};

export default function AppTabBar({ state, descriptors, navigation }: BottomTabBarProps) {
  const insets = useSafeAreaInsets();
  const [inner, setInner] = useState(0);
  const [reduceMotion, setReduceMotion] = useState(false);

  // Só as rotas da barra (as demais telas do grupo ficam ocultas).
  const routes = state.routes.filter((r) => TAB_ICONS[r.name]);
  const activeRoute = state.routes[state.index];
  const activeIdx = Math.max(0, routes.findIndex((r) => r.key === activeRoute?.key));
  const count = routes.length;
  const tabW = count ? inner / count : 0;

  const x = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    AccessibilityInfo.isReduceMotionEnabled?.().then(setReduceMotion).catch(() => {});
  }, []);

  useEffect(() => {
    if (!tabW) return;
    const to = activeIdx * tabW;
    if (reduceMotion) {
      x.setValue(to);
      return;
    }
    // Mola de ~420 ms, como no design system.
    Animated.spring(x, { toValue: to, useNativeDriver: true, damping: 18, stiffness: 180, mass: 0.9 }).start();
  }, [activeIdx, tabW, reduceMotion, x]);

  const onLayout = (e: LayoutChangeEvent) => setInner(e.nativeEvent.layout.width - nav.padding * 2);

  // Em uma tela oculta (ex.: detalhe do plano) a pílula some.
  const onHiddenRoute = !TAB_ICONS[activeRoute?.name ?? ""];

  return (
    <View
      style={[
        styles.wrap,
        { backgroundColor: ds.ground, paddingBottom: Math.max(insets.bottom, nav.margin) },
      ]}
    >
      <View onLayout={onLayout} accessibilityRole="tablist" style={styles.bar}>
        {inner > 0 && !onHiddenRoute ? (
          <Animated.View
            pointerEvents="none"
            style={[styles.indicator, { width: tabW, transform: [{ translateX: x }] }]}
          >
            <View style={styles.pill} />
          </Animated.View>
        ) : null}

        {routes.map((route) => {
          const { options } = descriptors[route.key];
          const label =
            typeof options.tabBarLabel === "string"
              ? options.tabBarLabel
              : (options.title ?? route.name);
          const focused = activeRoute?.key === route.key;
          const color = focused ? ds.onBrand : nav.onNavMuted;
          const badge = options.tabBarBadge;

          const onPress = () => {
            const event = navigation.emit({ type: "tabPress", target: route.key, canPreventDefault: true });
            if (!focused && !event.defaultPrevented) navigation.navigate(route.name, route.params);
          };
          const onLongPress = () => navigation.emit({ type: "tabLongPress", target: route.key });

          return (
            <Pressable
              key={route.key}
              onPress={onPress}
              onLongPress={onLongPress}
              accessibilityRole="tab"
              accessibilityState={{ selected: focused }}
              accessibilityLabel={options.tabBarAccessibilityLabel ?? label}
              style={({ pressed }) => [styles.item, pressed && !reduceMotion && styles.pressed]}
            >
              <View>
                <TabIcon name={TAB_ICONS[route.name]} color={color} />
                {badge != null && badge !== "" ? (
                  <View style={styles.badge}>
                    <Text style={styles.badgeText}>{String(badge)}</Text>
                  </View>
                ) : null}
              </View>
              <Text numberOfLines={1} style={[styles.label, { color, fontWeight: focused ? "900" : "700" }]}>
                {label}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: nav.margin, paddingTop: 4 },
  bar: {
    flexDirection: "row",
    height: nav.height,
    padding: nav.padding,
    borderRadius: nav.radius,
    backgroundColor: nav.bg,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: nav.border,
    shadowColor: "#171a21",
    shadowOpacity: 0.35,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 10 },
    elevation: 12,
  },
  indicator: { position: "absolute", top: nav.padding, bottom: nav.padding, left: nav.padding },
  pill: {
    flex: 1,
    marginHorizontal: 3,
    borderRadius: nav.pillRadius,
    backgroundColor: ds.brand,
    shadowColor: ds.brandRed,
    shadowOpacity: 0.55,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 6 },
    elevation: 6,
  },
  item: { flex: 1, alignItems: "center", justifyContent: "center", gap: 3 },
  pressed: { transform: [{ scale: 0.92 }] },
  label: { fontSize: 11, lineHeight: 13 },
  badge: {
    position: "absolute",
    top: -5,
    right: -9,
    minWidth: 17,
    height: 17,
    paddingHorizontal: 4,
    borderRadius: 9,
    borderWidth: 2,
    borderColor: nav.bg,
    backgroundColor: ds.brandRed,
    alignItems: "center",
    justifyContent: "center",
  },
  badgeText: { color: ds.onBrand, fontSize: 10, lineHeight: 12, fontWeight: "900" },
});
