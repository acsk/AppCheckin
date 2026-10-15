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
  // theme-color exige uma cor concreta, nao uma referencia var(--...).
  const probe = document.createElement("span");
  probe.style.color = color;
  probe.style.display = "none";
  document.body.appendChild(probe);
  meta.content = getComputedStyle(probe).color;
  probe.remove();
}

/**
 * Na web, pinta a barra do navegador (Chrome Android, Safari) com a cor do topo da tela
 * enquanto ela está em foco; ao sair, volta ao laranja padrão.
 */
export function useBrowserThemeColor(color: string) {
  useFocusEffect(
    useCallback(() => {
      setThemeColor(color);
      const observer = Platform.OS === "web" && typeof document !== "undefined"
        ? new MutationObserver(() => setThemeColor(color))
        : null;
      observer?.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });
      return () => {
        observer?.disconnect();
        setThemeColor(DEFAULT_BROWSER_THEME);
      };
    }, [color]),
  );
}
