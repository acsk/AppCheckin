import { useColorScheme } from "@/hooks/use-color-scheme";
import { colors } from "@/src/theme/colors";
import AsyncStorage from "@/src/utils/storage";
import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
    Platform,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";

// Importar as telas
import AccountScreen from "./(tabs)/account";
import CheckinScreen from "./(tabs)/checkin";

export default function MainApp() {
  const [currentTab, setCurrentTab] = useState("account");
  const [isReady, setIsReady] = useState(false);
  const colorScheme = useColorScheme();
  const isDark = colorScheme === "dark";
  const router = useRouter();

  useEffect(() => {
    // Verificar autenticação ao montar
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const token = await AsyncStorage.getItem("@appcheckin:token");
      if (!token) {
        // Sem token, redireciona para login
        router.replace("/(auth)/login");
        return;
      }
      setIsReady(true);
    } catch (error) {
      // Erro ao verificar, redireciona para login
      router.replace("/(auth)/login");
    }
  };

  if (!isReady) {
    return null;
  }

  const renderScreen = () => {
    switch (currentTab) {
      case "account":
        return <AccountScreen />;
      case "checkin":
        return <CheckinScreen />;
      default:
        return <AccountScreen />;
    }
  };

  return (
    <View
      style={[
        styles.container,
        { backgroundColor: isDark ? "#1a1a1a" : "#f5f5f5" },
      ]}
    >
      {/* Conteúdo */}
      <View style={styles.content}>{renderScreen()}</View>

      {/* Navegação Customizada */}
      <View
        style={[
          styles.customNav,
          {
            backgroundColor: isDark ? "#1a1a1a" : "#fff",
            borderTopColor: isDark ? "#333" : "#e5e5e5",
          },
        ]}
      >
        <TouchableOpacity
          style={[
            styles.navItem,
            currentTab === "account" && styles.navItemActive,
          ]}
          onPress={() => setCurrentTab("account")}
        >
          <Feather
            name="user"
            size={24}
            color={currentTab === "account" ? colors.primary : "#999"}
          />
          <Text
            style={[
              styles.navLabel,
              {
                color: currentTab === "account" ? colors.primary : "#999",
              },
            ]}
          >
            Minha Conta
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[
            styles.navItem,
            currentTab === "checkin" && styles.navItemActive,
          ]}
          onPress={() => setCurrentTab("checkin")}
        >
          <Feather
            name="check-square"
            size={24}
            color={currentTab === "checkin" ? colors.primary : "#999"}
          />
          <Text
            style={[
              styles.navLabel,
              {
                color: currentTab === "checkin" ? colors.primary : "#999",
              },
            ]}
          >
            Checkin
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  content: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  customNav: {
    flexDirection: "row",
    borderTopWidth: 1,
    paddingBottom: Platform.OS === "web" ? 8 : 0,
    paddingTop: Platform.OS === "web" ? 8 : 4,
    paddingHorizontal: 0,
    height: Platform.OS === "web" ? "auto" : 65,
    minHeight: 65,
    overflow: "hidden",
  },
  navItem: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    gap: 4,
    paddingVertical: Platform.OS === "web" ? 4 : 0,
  },
  navItemActive: {
    opacity: 1,
  },
  navLabel: {
    fontSize: 12,
    fontWeight: "500",
    marginTop: 2,
  },
});
