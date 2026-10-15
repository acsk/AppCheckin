// Tokens do design system AppCheckin (tema claro).
// Fonte: artefato "AppCheckin" (Design System) — tokens.json.
export const ds = {
  ground: "#f2f3f5",
  surface: "#ffffff",
  surfaceSunken: "#f7f7f9",
  line: "#e4e6ea",
  lineStrong: "#8b929e",
  ink: "#171a21",
  inkMuted: "#5b6270",
  inverse: "#171a21",
  onInverse: "#ffffff",
  brand: "#ff7a00",
  brandYellow: "#fdb400",
  brandRed: "#f2400a",
  onBrand: "#171a21",
  brandInk: "#c2410c",
  brandSoft: "#fff3e6",
  success: "#15703a",
  successSoft: "#e7f6ec",
  onSuccess: "#ffffff",
  warning: "#9a5b00",
  warningSoft: "#fff4db",
  danger: "#b91c1c",
  dangerSoft: "#fdecec",
  info: "#1d5fd1",
  infoSoft: "#e8f0fe",
  medalGold: "#f2b705",
  medalSilver: "#c2cad6",
  medalBronze: "#e09a5e",
  scrim: "rgba(18, 21, 26, 0.56)",
} as const;

export const space = { 1: 4, 2: 8, 3: 12, 4: 16, 5: 20, 6: 24, 8: 32 } as const;
export const radius = { sm: 8, md: 12, lg: 16, full: 999 } as const;
export const control = { sm: 36, md: 48, lg: 56 } as const;

export type Tone = "neutral" | "brand" | "success" | "warning" | "danger" | "info" | "solid";

export const toneColors: Record<Tone, { bg: string; fg: string }> = {
  neutral: { bg: ds.surfaceSunken, fg: ds.inkMuted },
  brand: { bg: ds.brandSoft, fg: ds.brandInk },
  success: { bg: ds.successSoft, fg: ds.success },
  warning: { bg: ds.warningSoft, fg: ds.warning },
  danger: { bg: ds.dangerSoft, fg: ds.danger },
  info: { bg: ds.infoSoft, fg: ds.info },
  solid: { bg: ds.inverse, fg: ds.onInverse },
};

export const type = {
  display: { fontSize: 32, lineHeight: 36, fontWeight: "800" as const, letterSpacing: -0.6 },
  title: { fontSize: 22, lineHeight: 28, fontWeight: "800" as const, letterSpacing: -0.2 },
  numeral: { fontSize: 26, lineHeight: 28, fontWeight: "800" as const, letterSpacing: -0.5, fontVariant: ["tabular-nums" as const] },
  heading: { fontSize: 17, lineHeight: 22, fontWeight: "700" as const },
  body: { fontSize: 15, lineHeight: 22, fontWeight: "500" as const },
  button: { fontSize: 16, lineHeight: 20, fontWeight: "800" as const },
  label: { fontSize: 12, lineHeight: 16, fontWeight: "800" as const, letterSpacing: 0.96, textTransform: "uppercase" as const },
  caption: { fontSize: 13, lineHeight: 18, fontWeight: "600" as const },
};
