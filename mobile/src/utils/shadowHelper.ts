/**
 * Utilitário para converter shadow props do React Native para boxShadow CSS
 * Converte propriedades deprecadas (shadowColor, shadowOffset, etc) para boxShadow
 */

interface ShadowProps {
  shadowColor?: string;
  shadowOffset?: { width: number; height: number };
  shadowOpacity?: number;
  shadowRadius?: number;
}

interface ShadowResult {
  boxShadow?: string;
  elevation?: number;
}

/**
 * Converte shadow props do React Native para boxShadow CSS
 * @param shadow Objeto com propriedades de sombra
 * @returns Objeto com boxShadow CSS ou elevation para mobile
 */
export function convertShadow(shadow: ShadowProps): ShadowResult {
  if (!shadow || Object.keys(shadow).length === 0) {
    return {};
  }

  const {
    shadowColor = "#000",
    shadowOffset = { width: 0, height: 0 },
    shadowOpacity = 1,
    shadowRadius = 0,
  } = shadow;

  // Para web, usar boxShadow
  const offsetX = shadowOffset.width;
  const offsetY = shadowOffset.height;

  // Converter cor hex para rgba com opacidade
  let rgbaColor = shadowColor;
  if (shadowColor.startsWith("#")) {
    const hex = shadowColor.replace("#", "");
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    rgbaColor = `rgba(${r}, ${g}, ${b}, ${shadowOpacity})`;
  }

  const boxShadow = `${offsetX}px ${offsetY}px ${shadowRadius}px ${rgbaColor}`;

  return {
    boxShadow,
    elevation: Math.round(shadowRadius * 1.5), // Para Android
  };
}

/**
 * Exemplo de uso:
 *
 * const shadowStyle = convertShadow({
 *   shadowColor: '#000',
 *   shadowOffset: { width: 0, height: 8 },
 *   shadowOpacity: 0.08,
 *   shadowRadius: 16,
 * });
 *
 * // Resultado:
 * // {
 * //   boxShadow: '0px 8px 16px rgba(0, 0, 0, 0.08)',
 * //   elevation: 24
 * // }
 */
