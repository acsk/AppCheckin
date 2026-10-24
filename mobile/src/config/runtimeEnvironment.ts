import { isDevice } from "expo-device";
import { Platform } from "react-native";

// O simulador usa os mesmos serviços da APK publicada, mesmo com o Metro em desenvolvimento.
export const IS_SIMULATOR = Platform.OS !== "web" && !isDevice;
export const APP_ENV = IS_SIMULATOR
  ? "production"
  : process.env.EXPO_PUBLIC_APP_ENV || "development";
