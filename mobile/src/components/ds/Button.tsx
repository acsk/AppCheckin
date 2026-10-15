import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import React from "react";
import {
  ActivityIndicator,
  StyleProp,
  StyleSheet,
  Text,
  TouchableOpacity,
  TouchableOpacityProps,
  View,
  ViewStyle,
} from "react-native";
import { control, ds, radius, space, type } from "./tokens";

export type ButtonVariant = "primary" | "glow" | "dark" | "secondary" | "ghost" | "danger";

type Props = Omit<TouchableOpacityProps, "style"> & {
  children?: React.ReactNode;
  variant?: ButtonVariant;
  size?: "sm" | "md" | "lg";
  block?: boolean;
  icon?: React.ComponentProps<typeof Feather>["name"];
  iconRight?: React.ComponentProps<typeof Feather>["name"];
  loading?: boolean;
  style?: StyleProp<ViewStyle>;
};

const VARIANT: Record<ButtonVariant, { bg: string; fg: string; border?: string }> = {
  primary: { bg: ds.brand, fg: ds.onBrand },
  glow: { bg: ds.brand, fg: ds.onBrand },
  dark: { bg: ds.inverse, fg: ds.onInverse },
  secondary: { bg: ds.surface, fg: ds.ink, border: ds.lineStrong },
  ghost: { bg: "transparent", fg: ds.ink },
  danger: { bg: ds.dangerSoft, fg: ds.danger },
};

// Botão do design system. Texto sobre brand é sempre grafite (on-brand).
export default function Button({
  children,
  variant = "primary",
  size = "md",
  block,
  icon,
  iconRight,
  loading,
  disabled,
  style,
  ...rest
}: Props) {
  const v = VARIANT[variant];
  const height = size === "lg" ? control.lg : size === "sm" ? control.sm : control.md;
  const iconOnly = !children;
  const content = (
    <>
      {loading ? (
        <ActivityIndicator size="small" color={v.fg} />
      ) : icon ? (
        <Feather name={icon} size={size === "sm" ? 18 : 20} color={v.fg} />
      ) : null}
      {children ? (
        <Text style={[styles.text, { color: v.fg, fontSize: size === "lg" ? 17 : size === "sm" ? 14 : 16 }]}>
          {children}
        </Text>
      ) : null}
      {iconRight && !loading ? <Feather name={iconRight} size={20} color={v.fg} /> : null}
    </>
  );

  return (
    <TouchableOpacity
      accessibilityRole="button"
      activeOpacity={0.85}
      disabled={disabled || loading}
      {...rest}
      style={[
        styles.base,
        {
          height,
          width: iconOnly ? height : undefined,
          paddingHorizontal: iconOnly ? 0 : size === "sm" ? space[3] : space[5],
          borderRadius: size === "sm" ? radius.sm : radius.md,
          backgroundColor: v.bg,
          alignSelf: block ? "stretch" : "auto",
        },
        v.border ? { borderWidth: 1.5, borderColor: v.border } : null,
        variant === "glow" ? styles.glowShadow : null,
        (disabled || loading) && { opacity: loading ? 0.75 : 0.45 },
        style,
      ]}
    >
      {variant === "glow" ? (
        <LinearGradient
          colors={[ds.brandYellow, ds.brand, ds.brandRed]}
          locations={[0, 0.45, 1]}
          start={{ x: 0, y: 0.3 }}
          end={{ x: 1, y: 0.7 }}
          style={[StyleSheet.absoluteFill, { borderRadius: radius.md }]}
        />
      ) : null}
      <View style={styles.row}>{content}</View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  base: {
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  row: { flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[2] },
  text: { ...type.button },
  glowShadow: {
    shadowColor: ds.brandRed,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.35,
    shadowRadius: 16,
    elevation: 6,
  },
});
