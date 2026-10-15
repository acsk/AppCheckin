import { useThemePreference } from "@/src/theme/ThemePreference";

/**
 * To support static rendering, this value needs to be re-calculated on the client side for web
 */
export function useColorScheme() {
  return useThemePreference().mode;
}
