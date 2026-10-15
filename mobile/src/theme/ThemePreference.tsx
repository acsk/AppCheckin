import React, { createContext, useContext, useEffect, useState } from "react";
import { Platform } from "react-native";
import "./themeStyles";

export type ThemePreference = "light" | "dark" | "system";
type ThemeMode = "light" | "dark";
const STORAGE_KEY = "@appcheckin:theme";

type ThemeContextValue = {
  preference: ThemePreference;
  mode: ThemeMode;
  error: string | null;
  setPreference: (preference: ThemePreference) => void;
};

const ThemeContext = createContext<ThemeContextValue>({
  preference: "system",
  mode: "light",
  error: null,
  setPreference: () => {},
});

function isPreference(value: string | null): value is ThemePreference {
  return value === "light" || value === "dark" || value === "system";
}

export function ThemePreferenceProvider({ children }: React.PropsWithChildren) {
  const [preference, setPreferenceState] = useState<ThemePreference>("system");
  const [systemDark, setSystemDark] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const mode = preference === "system" ? (systemDark ? "dark" : "light") : preference;

  useEffect(() => {
    if (Platform.OS !== "web") return;
    const media = window.matchMedia("(prefers-color-scheme: dark)");
    setSystemDark(media.matches);
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (isPreference(saved)) setPreferenceState(saved);
    } catch (cause) {
      console.warn("Não foi possível ler a preferência de tema:", cause);
      setError("Não foi possível recuperar o tema salvo neste navegador.");
    }
    const onSystemChange = (event: MediaQueryListEvent) => setSystemDark(event.matches);
    const onStorage = (event: StorageEvent) => {
      if (event.key === STORAGE_KEY || event.key === null) {
        setPreferenceState(isPreference(event.newValue) ? event.newValue : "system");
      }
    };
    media.addEventListener("change", onSystemChange);
    window.addEventListener("storage", onStorage);
    return () => {
      media.removeEventListener("change", onSystemChange);
      window.removeEventListener("storage", onStorage);
    };
  }, []);

  useEffect(() => {
    if (Platform.OS === "web") document.documentElement.dataset.theme = mode;
  }, [mode]);

  const setPreference = (next: ThemePreference) => {
    setPreferenceState(next);
    setError(null);
    if (Platform.OS !== "web") return;
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch (cause) {
      console.warn("Não foi possível salvar a preferência de tema:", cause);
      setError("O tema foi aplicado, mas não pôde ser salvo neste navegador.");
    }
  };

  return (
    <ThemeContext.Provider value={{ preference, mode, error, setPreference }}>
      {children}
    </ThemeContext.Provider>
  );
}

export const useThemePreference = () => useContext(ThemeContext);
