import * as SplashScreen from "expo-splash-screen";
import { LinearGradient } from "expo-linear-gradient";
import React, { useEffect, useRef, useState } from "react";
import { AccessibilityInfo, Animated, Easing, Image, Platform, StyleSheet, Text, useColorScheme, useWindowDimensions, View } from "react-native";
import { StatusBar } from "expo-status-bar";
import TechBackground from "./ds/TechBackground";
import { APP_VERSION } from "@/src/config/appVersion";

const MIN_VISIBLE_MS = 1400;
const native = Platform.OS !== "web";
const ICON = require("../../assets/splash/icon.png");
const ICON_WHITE = require("../../assets/splash/icon-white.png");
const WORD = require("../../assets/splash/word.png");
const WORD_WHITE = require("../../assets/splash/word-white.png");

type Props = { done: boolean; onFinish: () => void };

export default function AppSplash({ done, onFinish }: Props) {
  const dark = useColorScheme() === "dark";
  const { height } = useWindowDimensions();
  const compact = height < 600;
  const muted = dark ? "#a3aab6" : "#5b6270";
  const background = dark ? "#15181e" : "#ffffff";
  const intro = useRef(new Animated.Value(0)).current;
  const opacity = useRef(new Animated.Value(1)).current;
  const loading = useRef(new Animated.Value(0)).current;
  const shownAt = useRef(Date.now());
  const [reducedMotion, setReducedMotion] = useState<boolean | null>(null);

  useEffect(() => {
    let active = true;
    void AccessibilityInfo.isReduceMotionEnabled().then(value => { if (active) setReducedMotion(value); });
    const subscription = AccessibilityInfo.addEventListener("reduceMotionChanged", setReducedMotion);
    return () => { active = false; subscription.remove(); };
  }, []);

  useEffect(() => {
    if (reducedMotion === null) return;
    if (reducedMotion) { intro.setValue(1); loading.setValue(0.5); return; }
    const entrance = Animated.timing(intro, { toValue: 1, duration: 1200, easing: Easing.out(Easing.cubic), useNativeDriver: native });
    const loop = Animated.loop(Animated.timing(loading, { toValue: 1, duration: 1100, easing: Easing.inOut(Easing.cubic), useNativeDriver: native }));
    entrance.start(); loop.start();
    return () => { entrance.stop(); loop.stop(); };
  }, [intro, loading, reducedMotion]);

  useEffect(() => {
    if (!done) return;
    const timer = setTimeout(() => {
      Animated.timing(opacity, { toValue: 0, duration: reducedMotion ? 0 : 350, useNativeDriver: native })
        .start(({ finished }) => { if (finished) onFinish(); });
    }, Math.max(0, MIN_VISIBLE_MS - (Date.now() - shownAt.current)));
    return () => { clearTimeout(timer); opacity.stopAnimation(); };
  }, [done, onFinish, opacity, reducedMotion]);

  const reveal = intro.interpolate({ inputRange: [0, 0.3, 1], outputRange: [0, 0, 1] });
  return (
    <Animated.View style={[styles.root, { backgroundColor: background, opacity }]} onLayout={() => {
      if (native) void SplashScreen.hideAsync().catch(() => {});
    }} accessibilityLabel="Carregando AppCheckin" accessibilityRole="progressbar" accessibilityState={{ busy: !done }}>
      <StatusBar style={dark ? "light" : "dark"} />
      <Animated.View style={[StyleSheet.absoluteFill, { opacity: reveal }]}>
        <TechBackground backgroundColor={background} dark={dark} />
      </Animated.View>
      <View style={[styles.center, compact && { marginTop: -90 }]}>
        <Animated.View style={{ transform: [{ translateY: intro.interpolate({ inputRange: [0, 1], outputRange: [0, -44] }) }] }}>
          <Image source={dark ? ICON_WHITE : ICON} style={styles.icon} resizeMode="contain" accessibilityIgnoresInvertColors />
        </Animated.View>
        <Animated.View style={[styles.word, { opacity: reveal }]}>
          <Image source={dark ? WORD_WHITE : WORD} style={{ width: dark ? 184 : 200, height: 36 }} resizeMode="contain" accessibilityLabel="AppCheckin" accessibilityIgnoresInvertColors />
        </Animated.View>
        <Animated.Text style={[styles.tagline, { color: muted, opacity: reveal }]}>SEU CHECK-IN MAIS SIMPLES</Animated.Text>
        <Animated.View style={[styles.footer, { opacity: reveal }]}>
          <View style={[styles.track, { backgroundColor: dark ? "#2a2f38" : "#e4e6ea" }]}>
            <Animated.View style={{ width: 60, height: 6, transform: [{ translateX: loading.interpolate({ inputRange: [0, 1], outputRange: [-60, 192] }) }] }}>
              <LinearGradient colors={["#fdb400", "#ff7a00", "#f2400a"]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.fill} />
            </Animated.View>
          </View>
          <Text style={[styles.message, { color: muted }]}>Carregando sua academia…</Text>
          <Text style={[styles.version, { color: muted }]}>Versão {APP_VERSION}</Text>
        </Animated.View>
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  root: { ...StyleSheet.absoluteFillObject, zIndex: 1000, alignItems: "center", justifyContent: "center" },
  center: { width: "100%", alignItems: "center" },
  icon: { width: 128, height: 128 },
  word: { position: "absolute", top: 98, alignItems: "center" },
  tagline: { position: "absolute", top: 152, fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 3 },
  footer: { position: "absolute", top: 220, alignItems: "center", gap: 12 },
  track: { width: 132, height: 6, borderRadius: 999, overflow: "hidden" },
  fill: { flex: 1, borderRadius: 999 },
  message: { fontSize: 13, lineHeight: 18, fontWeight: "700" },
  version: { marginTop: 24, fontSize: 11, lineHeight: 14, fontWeight: "700", letterSpacing: 0.66, opacity: 0.8 },
});
