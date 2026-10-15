import { useFocusEffect } from "@react-navigation/native";
import { useCallback } from "react";
import { Platform } from "react-native";

// Cor padrão da barra do navegador: o laranja do cabeçalho (brand).
export const DEFAULT_BROWSER_THEME = "#ff7a00";

function setThemeColor(color: string) {
  if (Platform.OS !== "web" || typeof document === "undefined") return;
  let meta = document.querySelector('meta[name="theme-color"]') as HTMLMetaElement | null;
  if (!meta) {
    meta = document.createElement("meta");
    meta.name = "theme-color";
    document.head.appendChild(meta);
  }
  meta.content = color;
}

/**
 * Na web, pinta a barra do navegador (Chrome Android, Safari) com a cor do topo da tela
 * enquanto ela está em foco; ao sair, volta ao laranja padrão.
 */
export function useBrowserThemeColor(color: string) {
  useFocusEffect(
    useCallback(() => {
      setThemeColor(color);
      return () => setThemeColor(DEFAULT_BROWSER_THEME);
    }, [color]),
  );
}
