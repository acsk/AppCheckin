// AppCheckin — Splash animada (adaptada de react-native/AnimatedSplash.tsx do design system).
// O sistema mostra a splash nativa (fundo + ícone). Esta tela começa no MESMO quadro e continua:
// o ícone sobe, o nome entra, os recortes chegam dos cantos e a barra carrega. Quando o app
// está pronto (e a animação mínima terminou), sai em fade e chama onFinish.
import { useColorScheme } from "@/hooks/use-color-scheme";
import * as SplashScreen from "expo-splash-screen";
import React, { useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  Animated,
  Easing,
  Image,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from "react-native";
import Svg, { Circle, Defs, LinearGradient, Path, Polygon, Stop } from "react-native-svg";

const ICON_H = 128; // altura do ícone — igual à splash nativa (app.json)
const LIFT = 44; // quanto o ícone sobe
const MIN_MS = 1300; // tempo mínimo da animação antes de sair
const OFFLINE_MS = 8000; // sem "ready" depois disso → "Tentar de novo"
const NATIVE = Platform.OS !== "web"; // useNativeDriver não existe na web

const icons = {
  light: require("../../../assets/splash/splash-icon.png"), // 583 x 596
  dark: require("../../../assets/splash/splash-icon-white.png"), // 584 x 625
};
const words = {
  light: require("../../../assets/splash/wordmark.png"), // 640 x 115
  dark: require("../../../assets/splash/wordmark-white.png"), // 640 x 125
};

const PALETTE = {
  light: { surface: "#ffffff", ink: "#171a21", inkMuted: "#5b6270", line: "#e4e6ea", danger: "#b91c1c", inverse: "#171a21", onInverse: "#ffffff", brand: "#ff7a00" },
  dark: { surface: "#15181e", ink: "#f3f4f6", inkMuted: "#a3aab6", line: "#2a2f38", danger: "#f87171", inverse: "#f3f4f6", onInverse: "#15181e", brand: "#ff8a1a" },
};
const BRAND_YELLOW = "#fdb400";
const BRAND_RED = "#f2400a";

type Props = {
  ready: boolean;
  onFinish: () => void;
  onRetry?: () => void;
  version?: string | null;
  message?: string;
};

export default function AnimatedSplash({ ready, onFinish, onRetry, version, message }: Props) {
  const dark = useColorScheme() === "dark";
  const c = dark ? PALETTE.dark : PALETTE.light;
  const { width: W, height: H } = useWindowDimensions();
  const [offline, setOffline] = useState(false);
  const started = useRef(Date.now());
  const didStart = useRef(false);

  const rise = useRef(new Animated.Value(0)).current; // ícone
  const word = useRef(new Animated.Value(0)).current; // nome + frase
  const deco = useRef(new Animated.Value(0)).current; // recortes e malha
  const foot = useRef(new Animated.Value(0)).current; // barra + versão
  const load = useRef(new Animated.Value(0)).current; // barra indeterminada
  const out = useRef(new Animated.Value(1)).current; // saída

  // 1) Primeiro quadro desenhado → troca a splash nativa por esta (idênticas) e começa.
  const onLayout = async () => {
    if (didStart.current) return;
    didStart.current = true;
    SplashScreen.hideAsync().catch(() => {});
    const reduce = await AccessibilityInfo.isReduceMotionEnabled?.().catch(() => false);
    if (reduce) {
      [rise, word, deco, foot].forEach((v) => v.setValue(1));
      return;
    }
    const ease = Easing.bezier(0.2, 0.8, 0.2, 1);
    Animated.parallel([
      Animated.timing(rise, { toValue: 1, duration: 700, delay: 200, easing: ease, useNativeDriver: NATIVE }),
      Animated.timing(deco, { toValue: 1, duration: 800, delay: 450, easing: ease, useNativeDriver: NATIVE }),
      Animated.timing(word, { toValue: 1, duration: 550, delay: 550, easing: ease, useNativeDriver: NATIVE }),
      Animated.timing(foot, { toValue: 1, duration: 400, delay: 1050, useNativeDriver: NATIVE }),
    ]).start();
    Animated.loop(
      Animated.timing(load, { toValue: 1, duration: 1100, easing: Easing.bezier(0.45, 0, 0.25, 1), useNativeDriver: NATIVE }),
    ).start();
  };

  // 2) App pronto (e animação mínima cumprida) → sai em fade com leve zoom.
  useEffect(() => {
    if (!ready) {
      const t = setTimeout(() => setOffline(true), OFFLINE_MS);
      return () => clearTimeout(t);
    }
    setOffline(false);
    const wait = Math.max(0, MIN_MS - (Date.now() - started.current));
    const t = setTimeout(() => {
      Animated.timing(out, { toValue: 0, duration: 350, easing: Easing.in(Easing.quad), useNativeDriver: NATIVE }).start(() =>
        onFinish(),
      );
    }, wait);
    return () => clearTimeout(t);
  }, [ready]); // eslint-disable-line react-hooks/exhaustive-deps

  const iconW = ICON_H * (dark ? 584 / 625 : 583 / 596);
  const wordH = 36;
  const wordW = wordH * (dark ? 640 / 125 : 640 / 115);
  const iconY = rise.interpolate({ inputRange: [0, 0.35, 1], outputRange: [0, -6, -LIFT] });
  const iconS = rise.interpolate({ inputRange: [0, 0.35, 1], outputRange: [1, 1.06, 1] });
  const slide = (dx: number, dy: number) => ({
    opacity: deco,
    transform: [
      { translateX: deco.interpolate({ inputRange: [0, 1], outputRange: [dx, 0] }) },
      { translateY: deco.interpolate({ inputRange: [0, 1], outputRange: [dy, 0] }) },
    ],
  });

  return (
    <Animated.View
      style={[
        StyleSheet.absoluteFill,
        styles.root,
        {
          backgroundColor: c.surface,
          opacity: out,
          transform: [{ scale: out.interpolate({ inputRange: [0, 1], outputRange: [1.04, 1] }) }],
        },
      ]}
      onLayout={onLayout}
      pointerEvents={ready ? "none" : "auto"}
      accessibilityRole="progressbar"
      accessibilityLabel={offline ? "Sem conexão" : "Carregando"}
    >
      {/* malha de triângulos + recortes nos cantos (iguais ao TechBackground) */}
      <Animated.View style={[StyleSheet.absoluteFill, { opacity: deco }]} pointerEvents="none">
        <TriGrid w={W} h={H} color={c.ink} />
      </Animated.View>
      <Animated.View style={[styles.corner, { top: 0, left: 0 }, slide(-60, -60)]} pointerEvents="none">
        <Facets brand={c.brand} />
      </Animated.View>
      <Animated.View
        style={[styles.corner, { bottom: 0, right: 0 }, slide(60, 60)]}
        pointerEvents="none"
      >
        <View style={{ transform: [{ rotate: "180deg" }] }}>
          <Facets brand={c.brand} />
        </View>
      </Animated.View>

      {/* ícone: começa exatamente no centro, como a splash nativa */}
      <Animated.Image
        source={dark ? icons.dark : icons.light}
        style={{
          position: "absolute",
          top: H / 2 - ICON_H / 2,
          left: W / 2 - iconW / 2,
          width: iconW,
          height: ICON_H,
          transform: [{ translateY: iconY }, { scale: iconS }],
        }}
        resizeMode="contain"
        accessibilityIgnoresInvertColors
      />
      <Animated.View
        style={[
          styles.center,
          {
            top: H / 2 + 34,
            opacity: word,
            transform: [{ translateY: word.interpolate({ inputRange: [0, 1], outputRange: [14, 0] }) }],
          },
        ]}
      >
        <Image
          source={dark ? words.dark : words.light}
          style={{ width: wordW, height: wordH }}
          resizeMode="contain"
          accessibilityLabel="AppCheckin"
        />
        <Text style={[styles.tag, { color: c.inkMuted }]}>SEU CHECK-IN MAIS SIMPLES</Text>
      </Animated.View>

      <Animated.View style={[styles.center, { top: H / 2 + 156, opacity: foot }]}>
        {offline ? (
          <>
            <Text style={[styles.offline, { color: c.danger }]}>Sem conexão com a internet.</Text>
            {onRetry ? (
              <Pressable
                onPress={onRetry}
                accessibilityRole="button"
                style={({ pressed }) => [styles.retry, { backgroundColor: c.inverse, opacity: pressed ? 0.85 : 1 }]}
              >
                <Text style={[styles.retryText, { color: c.onInverse }]}>Tentar de novo</Text>
              </Pressable>
            ) : null}
          </>
        ) : (
          <>
            <View style={[styles.bar, { backgroundColor: c.line }]}>
              <Animated.View
                style={[
                  styles.barFill,
                  { transform: [{ translateX: load.interpolate({ inputRange: [0, 1], outputRange: [-60, 132] }) }] },
                ]}
              >
                <Svg width="100%" height="100%">
                  <Defs>
                    <LinearGradient id="splash-bar" x1="0" y1="0" x2="1" y2="0">
                      <Stop offset="0" stopColor={BRAND_YELLOW} />
                      <Stop offset="0.5" stopColor={c.brand} />
                      <Stop offset="1" stopColor={BRAND_RED} />
                    </LinearGradient>
                  </Defs>
                  <Path d="M3 0h54a3 3 0 010 6H3a3 3 0 010-6z" fill="url(#splash-bar)" />
                </Svg>
              </Animated.View>
            </View>
            <Text style={[styles.msg, { color: c.inkMuted }]}>{message ?? "Carregando sua academia…"}</Text>
          </>
        )}
        {version ? <Text style={[styles.ver, { color: c.inkMuted }]}>Versão {version}</Text> : null}
      </Animated.View>
    </Animated.View>
  );
}

function TriGrid({ w, h, color }: { w: number; h: number; color: string }) {
  const TW = 32;
  const TH = 27.7128;
  let d = "";
  for (let y = 0; y <= h + TH; y += TH) d += `M0 ${y}H${w}`;
  for (let x = -h; x <= w + h; x += TW) d += `M${x} 0L${x + h / Math.sqrt(3)} ${h}M${x} 0L${x - h / Math.sqrt(3)} ${h}`;
  return (
    <Svg width={w} height={h}>
      <Path d={d} stroke={color} strokeOpacity={0.06} strokeWidth={1} fill="none" />
    </Svg>
  );
}

function Facets({ brand }: { brand: string }) {
  return (
    <Svg width={320} height={300} viewBox="0 0 320 300" opacity={0.85}>
      <Polygon points="0,0 168,0 0,146" fill={brand} />
      <Polygon points="168,0 258,0 64,112" fill={BRAND_YELLOW} opacity={0.75} />
      <Polygon points="0,146 64,112 0,236" fill={BRAND_RED} opacity={0.85} />
      <Polygon points="64,112 168,0 132,96" fill={brand} opacity={0.45} />
      <Polygon points="258,0 316,0 204,66" fill={BRAND_YELLOW} opacity={0.3} />
      <Polygon points="0,236 48,190 0,300" fill={BRAND_RED} opacity={0.3} />
      <Polygon points="228,52 300,10 282,88" fill="none" stroke={brand} strokeWidth={1.5} opacity={0.7} />
      <Circle cx={300} cy={10} r={3} fill={brand} />
      <Circle cx={48} cy={190} r={3} fill={brand} />
    </Svg>
  );
}

const styles = StyleSheet.create({
  root: { zIndex: 100, elevation: 100 },
  corner: { position: "absolute", width: 320, height: 300 },
  center: { position: "absolute", left: 0, right: 0, alignItems: "center", gap: 12 },
  tag: { fontSize: 11, lineHeight: 14, fontWeight: "800", letterSpacing: 3.5, marginTop: 6 },
  bar: { width: 132, height: 6, borderRadius: 3, overflow: "hidden" },
  barFill: { width: 60, height: 6 },
  msg: { fontSize: 13, lineHeight: 18, fontWeight: "700" },
  offline: { fontSize: 14, lineHeight: 20, fontWeight: "700" },
  retry: { height: 36, paddingHorizontal: 16, borderRadius: 8, alignItems: "center", justifyContent: "center" },
  retryText: { fontSize: 14, fontWeight: "800" },
  ver: { fontSize: 11, fontWeight: "700", letterSpacing: 0.6, marginTop: 28, opacity: 0.8 },
});
