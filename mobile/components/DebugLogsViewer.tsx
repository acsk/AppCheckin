import { debugLogger } from "@/src/utils/debugLogger";
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    ScrollView,
    Share,
    StyleSheet,
    Text,
    TouchableOpacity,
    View
} from "react-native";

export const DebugLogsViewer = () => {
  const [logs, setLogs] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const [showLogs, setShowLogs] = useState(false);

  useEffect(() => {
    loadLogs();
  }, []);

  const loadLogs = async () => {
    setLoading(true);
    try {
      const logsText = await debugLogger.getLogsAsText();
      setLogs(logsText);
    } catch (error) {
      setLogs("Erro ao carregar logs: " + String(error));
    } finally {
      setLoading(false);
    }
  };

  const handleShare = async () => {
    try {
      const exportedLogs = await debugLogger.exportLogs();
      await Share.share({
        message: exportedLogs,
        title: "Logs de Debug",
      });
    } catch (error) {
      Alert.alert("Erro", "Erro ao compartilhar logs");
    }
  };

  const handleClear = async () => {
    Alert.alert("Limpar Logs", "Tem certeza que deseja limpar todos os logs?", [
      { text: "Cancelar", onPress: () => {} },
      {
        text: "Limpar",
        onPress: async () => {
          await debugLogger.clearLogs();
          await loadLogs();
          Alert.alert("Sucesso", "Logs limpados");
        },
      },
    ]);
  };

  if (!showLogs) {
    return (
      <View style={styles.minimized}>
        <TouchableOpacity
          style={styles.button}
          onPress={() => setShowLogs(true)}
        >
          <Text style={styles.buttonText}>🔍 Ver Logs Debug</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Debug Logs</Text>
        <TouchableOpacity
          onPress={() => setShowLogs(false)}
          style={styles.closeButton}
        >
          <Text style={styles.closeButtonText}>×</Text>
        </TouchableOpacity>
      </View>

      {loading ? (
        <ActivityIndicator size="large" color="#0000ff" style={styles.loader} />
      ) : (
        <>
          <ScrollView style={styles.logsContainer}>
            <Text style={styles.logsText} selectable>
              {logs || "Nenhum log registrado"}
            </Text>
          </ScrollView>

          <View style={styles.actions}>
            <TouchableOpacity
              style={[styles.actionButton, styles.refreshButton]}
              onPress={loadLogs}
            >
              <Text style={styles.actionButtonText}>🔄 Recarregar</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.actionButton, styles.shareButton]}
              onPress={handleShare}
            >
              <Text style={styles.actionButtonText}>📤 Compartilhar</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.actionButton, styles.clearButton]}
              onPress={handleClear}
            >
              <Text style={styles.actionButtonText}>🗑️ Limpar</Text>
            </TouchableOpacity>
          </View>
        </>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  minimized: {
    position: "absolute",
    bottom: 20,
    right: 20,
    zIndex: 1000,
  },
  button: {
    backgroundColor: "#007AFF",
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    elevation: 5,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.3,
    shadowRadius: 3,
  },
  buttonText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "bold",
  },
  container: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: "#1a1a1a",
    zIndex: 1000,
    display: "flex",
    flexDirection: "column",
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: "#000",
    borderBottomWidth: 1,
    borderBottomColor: "#333",
  },
  title: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "bold",
  },
  closeButton: {
    width: 32,
    height: 32,
    justifyContent: "center",
    alignItems: "center",
  },
  closeButtonText: {
    color: "#fff",
    fontSize: 24,
    fontWeight: "bold",
  },
  logsContainer: {
    flex: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  logsText: {
    color: "#00ff00",
    fontSize: 11,
    fontFamily: "monospace",
    lineHeight: 16,
  },
  actions: {
    flexDirection: "row",
    justifyContent: "space-around",
    paddingHorizontal: 8,
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: "#333",
    backgroundColor: "#0a0a0a",
  },
  actionButton: {
    flex: 1,
    marginHorizontal: 4,
    paddingVertical: 8,
    borderRadius: 6,
    justifyContent: "center",
    alignItems: "center",
  },
  actionButtonText: {
    color: "#fff",
    fontSize: 12,
    fontWeight: "600",
  },
  refreshButton: {
    backgroundColor: "#007AFF",
  },
  shareButton: {
    backgroundColor: "#34C759",
  },
  clearButton: {
    backgroundColor: "#FF3B30",
  },
  loader: {
    flex: 1,
    justifyContent: "center",
  },
});
