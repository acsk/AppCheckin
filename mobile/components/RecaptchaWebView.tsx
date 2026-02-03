import React, {
    forwardRef,
    useImperativeHandle,
    useRef,
    useState,
} from "react";
import { ActivityIndicator, Modal, StyleSheet, Text, View } from "react-native";
import { WebView } from "react-native-webview";

interface RecaptchaWebViewProps {
  siteKey: string;
  onVerify: (token: string) => void;
  onExpire?: () => void;
  onError?: (error: string) => void;
  theme?: "light" | "dark";
  size?: "normal" | "compact";
}

export interface RecaptchaWebViewRef {
  open: () => void;
  close: () => void;
}

const RecaptchaWebView = forwardRef<RecaptchaWebViewRef, RecaptchaWebViewProps>(
  (
    { siteKey, onVerify, onExpire, onError, theme = "light", size = "normal" },
    ref,
  ) => {
    const [visible, setVisible] = useState(false);
    const [loading, setLoading] = useState(true);
    const webViewRef = useRef<WebView>(null);

    useImperativeHandle(ref, () => ({
      open: () => {
        setVisible(true);
        setLoading(true);
      },
      close: () => {
        setVisible(false);
        setLoading(true);
      },
    }));

    const handleMessage = (event: any) => {
      try {
        const data = JSON.parse(event.nativeEvent.data);

        if (data.type === "success" && data.token) {
          onVerify(data.token);
          setVisible(false);
        } else if (data.type === "expire") {
          onExpire?.();
          setVisible(false);
        } else if (data.type === "error") {
          onError?.(data.message || "reCAPTCHA error");
          setVisible(false);
        } else if (data.type === "loaded") {
          setLoading(false);
        }
      } catch (error) {
        console.error("Error parsing message:", error);
        onError?.("Failed to parse reCAPTCHA response");
      }
    };

    const htmlContent = `
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background-color: ${theme === "dark" ? "#1f2937" : "#f7f4f1"};
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .container {
      text-align: center;
      padding: 20px;
    }
    .title {
      font-size: 18px;
      font-weight: 600;
      color: ${theme === "dark" ? "#f3f4f6" : "#1f2937"};
      margin-bottom: 20px;
    }
    .recaptcha-wrapper {
      display: inline-block;
      transform-origin: center;
    }
    .message {
      margin-top: 16px;
      color: ${theme === "dark" ? "#9ca3af" : "#6b7280"};
      font-size: 14px;
    }
  </style>
  <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>
</head>
<body>
  <div class="container">
    <div class="title">Verificação de Segurança</div>
    <div class="recaptcha-wrapper">
      <div id="recaptcha-container"></div>
    </div>
    <div class="message">Confirme que você não é um robô</div>
  </div>
  
  <script>
    let recaptchaReady = false;
    
    function onRecaptchaLoad() {
      if (recaptchaReady) return;
      recaptchaReady = true;
      
      try {
        grecaptcha.render('recaptcha-container', {
          'sitekey': '${siteKey}',
          'theme': '${theme}',
          'size': '${size}',
          'callback': onRecaptchaSuccess,
          'expired-callback': onRecaptchaExpire,
          'error-callback': onRecaptchaError
        });
        
        window.ReactNativeWebView.postMessage(JSON.stringify({
          type: 'loaded'
        }));
      } catch (error) {
        window.ReactNativeWebView.postMessage(JSON.stringify({
          type: 'error',
          message: error.message || 'Failed to load reCAPTCHA'
        }));
      }
    }
    
    function onRecaptchaSuccess(token) {
      window.ReactNativeWebView.postMessage(JSON.stringify({
        type: 'success',
        token: token
      }));
    }
    
    function onRecaptchaExpire() {
      window.ReactNativeWebView.postMessage(JSON.stringify({
        type: 'expire'
      }));
    }
    
    function onRecaptchaError(error) {
      window.ReactNativeWebView.postMessage(JSON.stringify({
        type: 'error',
        message: 'reCAPTCHA verification failed'
      }));
    }
    
    // Aguardar o carregamento da API
    if (typeof grecaptcha !== 'undefined' && grecaptcha.render) {
      onRecaptchaLoad();
    } else {
      window.addEventListener('load', function() {
        const checkInterval = setInterval(function() {
          if (typeof grecaptcha !== 'undefined' && grecaptcha.render) {
            clearInterval(checkInterval);
            onRecaptchaLoad();
          }
        }, 100);
        
        // Timeout após 10 segundos
        setTimeout(function() {
          clearInterval(checkInterval);
          if (!recaptchaReady) {
            window.ReactNativeWebView.postMessage(JSON.stringify({
              type: 'error',
              message: 'reCAPTCHA loading timeout'
            }));
          }
        }, 10000);
      });
    }
  </script>
</body>
</html>
    `;

    return (
      <Modal
        visible={visible}
        animationType="slide"
        transparent={false}
        onRequestClose={() => setVisible(false)}
      >
        <View style={styles.container}>
          {loading && (
            <View style={styles.loadingOverlay}>
              <ActivityIndicator size="large" color="#ff6b35" />
              <Text style={styles.loadingText}>Carregando verificação...</Text>
            </View>
          )}
          <WebView
            ref={webViewRef}
            source={{ html: htmlContent }}
            onMessage={handleMessage}
            style={styles.webview}
            javaScriptEnabled={true}
            domStorageEnabled={true}
            startInLoadingState={true}
          />
        </View>
      </Modal>
    );
  },
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f7f4f1",
  },
  webview: {
    flex: 1,
    backgroundColor: "transparent",
  },
  loadingOverlay: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: "center",
    alignItems: "center",
    backgroundColor: "#f7f4f1",
    zIndex: 999,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    color: "#6b7280",
  },
});

export default RecaptchaWebView;
