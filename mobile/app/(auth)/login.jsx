import { Feather } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { authService } from '../../src/services/authService';
import { colors } from '../../src/theme/colors';

export default function LoginScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('carolina.ferreira@tenant4.com');
  const [senha, setSenha] = useState('123456');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    if (!email.trim() || !senha.trim()) {
      Alert.alert('Atenção', 'Preencha email e senha');
      return;
    }

    setLoading(true);
    try {
      const response = await authService.login(email, senha);
      
      // authService já salva o token automaticamente
      if (response.token || response.data?.token) {
        router.replace('/(tabs)');
      } else {
        Alert.alert('Erro', 'Não foi possível fazer login');
      }
    } catch (error) {
      const mensagem = error?.error || error?.message || 'Email ou senha incorretos';
      Alert.alert('Erro', mensagem);
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.keyboardView}
      >
        <ScrollView 
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {/* Logo */}
          <View style={styles.logoContainer}>
            <View style={styles.logoCircle}>
              <Feather name="check-circle" size={60} color={colors.primary} />
            </View>
            <Text style={styles.appName}>AppCheckin</Text>
            <Text style={styles.tagline}>Registre sua presença</Text>
          </View>

          {/* Formulário */}
          <View style={styles.formContainer}>
            <View style={styles.formCard}>
            <Text style={styles.welcomeText}>Bem-vindo de volta!</Text>
            <Text style={styles.welcomeSubtext}>Faça login para continuar</Text>

            {/* Email Input */}
            <View style={styles.inputWrapper}>
              <Feather 
                name="mail" 
                size={20} 
                color={colors.primary} 
                style={styles.inputIcon} 
              />
              <TextInput
                style={styles.input}
                placeholder="Email"
                placeholderTextColor="#999"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                value={email}
                onChangeText={setEmail}
                editable={!loading}
              />
            </View>

            {/* Senha Input */}
            <View style={styles.inputWrapper}>
              <Feather 
                name="lock" 
                size={20} 
                color={colors.primary}
                style={styles.inputIcon} 
              />
              <TextInput
                style={[styles.input, { paddingRight: 50 }]}
                placeholder="Senha"
                placeholderTextColor="#999"
                secureTextEntry={!showPassword}
                value={senha}
                onChangeText={setSenha}
                editable={!loading}
              />
              <TouchableOpacity 
                style={styles.eyeIcon}
                onPress={() => setShowPassword(!showPassword)}
              >
                <Feather 
                  name={showPassword ? 'eye-off' : 'eye'} 
                  size={20} 
                  color={colors.primary}
                />
              </TouchableOpacity>
            </View>

            {/* Login Button */}
            <TouchableOpacity 
              style={[
                styles.loginButton,
                loading && { opacity: 0.7 }
              ]}
              onPress={handleLogin}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <Text style={styles.loginButtonText}>Entrar</Text>
              )}
            </TouchableOpacity>
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f6f7fb',
  },
  keyboardView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: 20,
    paddingVertical: 24,
  },
  logoContainer: {
    alignItems: 'center',
    marginBottom: 28,
  },
  logoCircle: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: '#ffffff',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    elevation: 6,
  },
  appName: {
    fontSize: 32,
    fontWeight: 'bold',
    color: colors.primary,
    marginBottom: 4,
  },
  tagline: {
    fontSize: 16,
    color: '#8b91a1',
  },
  formContainer: {
    width: '100%',
  },
  formCard: {
    backgroundColor: '#fff',
    borderRadius: 20,
    paddingVertical: 24,
    paddingHorizontal: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.08,
    shadowRadius: 20,
    elevation: 6,
  },
  welcomeText: {
    fontSize: 24,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 8,
  },
  welcomeSubtext: {
    fontSize: 14,
    color: '#6b7280',
    marginBottom: 24,
  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 14,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 12,
    paddingHorizontal: 14,
    backgroundColor: '#f9fafb',
    height: 52,
  },
  inputIcon: {
    marginRight: 12,
  },
  input: {
    flex: 1,
    height: 52,
    fontSize: 16,
    color: '#111827',
  },
  eyeIcon: {
    padding: 10,
  },
  loginButton: {
    backgroundColor: colors.primary,
    borderRadius: 14,
    height: 52,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 18,
    shadowColor: colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 5,
  },
  loginButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
