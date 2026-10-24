import * as Clipboard from "expo-clipboard";
import { Platform } from "react-native";

/** Copia no app nativo e mantém o fallback para navegadores sem Clipboard API. */
export async function copyToClipboard(value: string): Promise<boolean> {
  if (!value) return false;
  if (Platform.OS !== "web") {
    try {
      return await Clipboard.setStringAsync(value);
    } catch {
      return false;
    }
  }

  try {
    if (typeof navigator !== "undefined" && navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value);
      return true;
    }
  } catch {
    // Tenta a cópia legada quando a API moderna não está disponível.
  }
  if (typeof document === "undefined") return false;
  const input = document.createElement("textarea");
  input.value = value;
  input.setAttribute("readonly", "true");
  input.style.position = "fixed";
  input.style.left = "-9999px";
  document.body.appendChild(input);
  try {
    input.select();
    return document.execCommand("copy");
  } catch {
    return false;
  } finally {
    document.body.removeChild(input);
  }
}
