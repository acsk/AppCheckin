import * as SplashScreen from "expo-splash-screen";
import React, { useEffect, useRef } from "react";
import { Animated, Easing, Image, Platform, StyleSheet, View } from "react-native";
import { ds } from "@/src/components/ds/tokens";

const LOGO = require("../../assets/images/appcheckin-logo.png");
const BAR_WIDTH = 180;
// Tempo mínimo na tela para a barra não "piscar" quando o app abre rápido.
const MIN_VISIBLE_MS = 900;
const native = Platform.OS !== "web";

type Props = {
  /** true quando o app terminou de carregar: a barra completa e a splash some. */
  done: boolean;
  onFinish: () => void;
};

/**
 * Splash do app: logo original e barra de carregamento. Fica por cima da splash nativa
 * (mesmo fundo branco) e a esconde assim que é desenhada, sem salto visual.
 */
export default function AppSplash({ done, onFinish }: Props) {
  const progress = useRef(new Animated.Value(0)).current;
  const opacity = useRef(new Animated.Value(1)).current;
  const shownAt = useRef(Date.now());

  // Avança devagar até 85% enquanto carrega: dá sensação de progresso sem prometer o fim.
  useEffect(() => {
    Animated.timing(progress, {
      toValue: 0.85,
      duration: 2400,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: false,
    }).start();
  }, [progress]);

  useEffect(() => {
    if (!done) return;
    const wait = Math.max(0, MIN_VISIBLE_MS - (Date.now() - shownAt.current));
    const timer = setTimeout(() => {
      progress.stopAnimation();
      Animated.sequence([
        Animated.timing(progress, { toValue: 1, duration: 250, easing: Easing.out(Easing.quad), useNativeDriver: false }),
        Animated.timing(opacity, { toValue: 0, duration: 250, useNativeDriver: native }),
      ]).start(() => onFinish());
    }, wait);
    return () => clearTimeout(timer);
  }, [done, onFinish, opacity, progress]);

  const hideNativeSplash = () => {
    if (native) void SplashScreen.hideAsync().catch(() => {});
  };

  return (
    <Animated.View
      style={[styles.root, { opacity }]}
      onLayout={hideNativeSplash}
      accessibilityRole="progressbar"
      accessibilityLabel="Carregando AppCheckin"
    >
      <Image source={LOGO} style={styles.logo} resizeMode="contain" accessibilityIgnoresInvertColors />
      <View style={styles.track}>
        <Animated.View
          style={[
            styles.fill,
            { width: progress.interpolate({ inputRange: [0, 1], outputRange: [0, BAR_WIDTH] }) },
          ]}
        />
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  root: {
    ...StyleSheet.absoluteFillObject,
    zIndex: 1000,
    // Branco fixo, igual à splash nativa: a logo tem partes pretas que somem no fundo escuro.
    backgroundColor: "#ffffff",
    alignItems: "center",
    justifyContent: "center",
    gap: 36,
  },
  logo: { width: 180, height: 200 },
  track: {
    width: BAR_WIDTH,
    height: 6,
    borderRadius: 3,
    backgroundColor: "#f1e4da",
    overflow: "hidden",
  },
  fill: { height: "100%", borderRadius: 3, backgroundColor: ds.brand },
});
