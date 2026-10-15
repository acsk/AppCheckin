import { ds } from "../components/ds/tokens";

// Tema de cores alinhado ao design system AppCheckin
// (fonte única: src/components/ds/tokens.ts — mantenha os dois em sincronia).
export const colors = {
  // Laranja da logo (brand). Como fundo, use texto grafite (onPrimary):
  // branco sobre este laranja não passa contraste.
  primary: '#ff7a00',
  primaryLight: '#fdb400',
  primaryDark: '#f2400a',
  onPrimary: '#171a21',
  // Laranja como TEXTO (links, preços, valor selecionado)
  primaryText: ds.brandInk,
  primarySoft: ds.brandSoft,

  // Cores de fundo
  background: ds.surface,
  backgroundSecondary: ds.ground,
  backgroundTertiary: ds.surfaceSunken,

  // Cores de texto
  text: ds.ink,
  textPrimary: ds.ink,
  textSecondary: ds.inkMuted,
  textMuted: ds.inkMuted,
  textLight: '#FFFFFF',

  // Cinzas
  gray50: ds.surfaceSunken,
  gray100: ds.ground,
  gray200: ds.line,
  gray300: '#D1D5DB',
  gray400: ds.lineStrong,
  gray500: ds.inkMuted,
  gray600: '#4B5563',
  gray700: '#374151',
  gray800: '#1F2937',
  gray900: '#171a21',

  // Estados (texto sobre o -soft correspondente)
  success: ds.success,
  warning: ds.warning,
  error: ds.danger,
  info: ds.info,

  // Sombras e bordas
  border: ds.line,
  borderStrong: ds.lineStrong,
  shadow: 'rgba(0, 0, 0, 0.1)',
  overlay: ds.scrim,

  // Tema invertido (escuro) para contraste
  dark: '#171a21',
  darkSecondary: '#2D2D44',
};
