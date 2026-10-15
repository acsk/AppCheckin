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
  primaryText: '#c2410c',
  primarySoft: '#fff3e6',

  // Cores de fundo
  background: '#FFFFFF',
  backgroundSecondary: '#f2f3f5',
  backgroundTertiary: '#f7f7f9',

  // Cores de texto
  text: '#171a21',
  textPrimary: '#171a21',
  textSecondary: '#5b6270',
  textMuted: '#5b6270',
  textLight: '#FFFFFF',

  // Cinzas
  gray50: '#f7f7f9',
  gray100: '#f2f3f5',
  gray200: '#e4e6ea',
  gray300: '#D1D5DB',
  gray400: '#8b929e',
  gray500: '#5b6270',
  gray600: '#4B5563',
  gray700: '#374151',
  gray800: '#1F2937',
  gray900: '#171a21',

  // Estados (texto sobre o -soft correspondente)
  success: '#15703a',
  warning: '#9a5b00',
  error: '#b91c1c',
  info: '#1d5fd1',

  // Sombras e bordas
  border: '#e4e6ea',
  borderStrong: '#8b929e',
  shadow: 'rgba(0, 0, 0, 0.1)',
  overlay: 'rgba(18, 21, 26, 0.56)',

  // Tema invertido (escuro) para contraste
  dark: '#171a21',
  darkSecondary: '#2D2D44',
};
