import React from "react";
import { Platform, Text, TouchableOpacity, View } from "react-native";

type Props = {
  children: React.ReactNode;
};

type State = {
  hasError: boolean;
  error?: Error | null;
  errorInfo?: React.ErrorInfo | null;
};

export default class ErrorBoundary extends React.Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false, error: null, errorInfo: null };
  }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error, errorInfo: null };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    // Log detalhado para diagnóstico (inclusive em produção)
    console.error("🛑 ErrorBoundary capturou um erro:", {
      message: error?.message,
      name: error?.name,
      stack: error?.stack,
      componentStack: errorInfo?.componentStack,
      platform: Platform.OS,
    });
    this.setState({ errorInfo });
  }

  handleReload = () => {
    if (Platform.OS === "web") {
      window.location.reload();
    }
  };

  render() {
    if (this.state.hasError) {
      return (
        <View
          style={{
            flex: 1,
            alignItems: "center",
            justifyContent: "center",
            padding: 24,
          }}
        >
          <Text style={{ fontSize: 18, fontWeight: "700", marginBottom: 12 }}>
            Ocorreu um erro inesperado
          </Text>
          <Text style={{ textAlign: "center", marginBottom: 16 }}>
            Se persistir, tente atualizar a página. Os detalhes foram
            registrados no console para diagnóstico.
          </Text>
          {Platform.OS === "web" ? (
            <TouchableOpacity
              onPress={this.handleReload}
              style={{
                backgroundColor: "#0b5cff",
                paddingHorizontal: 12,
                paddingVertical: 10,
                borderRadius: 8,
              }}
            >
              <Text style={{ color: "#fff", fontWeight: "700" }}>
                Atualizar
              </Text>
            </TouchableOpacity>
          ) : null}
        </View>
      );
    }
    return this.props.children;
  }
}
