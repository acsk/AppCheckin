import React, { useMemo, useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  Image,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Feather, FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import toast from 'react-hot-toast';
import { authService } from '../../services/authService';
import styles from './styles';

export default function LoginScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('superadmin@appcheckin.com');
  const [senha, setSenha] = useState('SuperAdmin@2025');
  const [loading, setLoading] = useState(false);
  const [secure, setSecure] = useState(true);

  const isDisabled = useMemo(() => email.trim().length === 0 || senha.trim().length === 0, [email, senha]);

  const handleLogin = async () => {
    if (isDisabled) {
      toast.error('Preencha email e senha', {
        style: {
          background: '#ef4444',
          color: '#fff',
          padding: '16px',
          borderRadius: '8px',
          fontSize: '14px',
          fontWeight: '500',
          fontFamily: 'system-ui, -apple-system, sans-serif',
        },
      });
      return;
    }

    setLoading(true);
    try {
      const response = await authService.login(email, senha);

      // Pequeno delay para garantir que o token foi salvo
      setTimeout(() => {
        router.replace('/');
      }, 100);
    } catch (error) {
      const mensagemErro = error.error || error.message || 'Credenciais inválidas';
      toast.error(mensagemErro, {
        duration: 5000,
        position: 'top-center',
        style: {
          background: '#ef4444',
          color: '#fff',
          padding: '16px',
          borderRadius: '8px',
          fontSize: '14px',
          fontWeight: '500',
          fontFamily: 'system-ui, -apple-system, sans-serif',
        },
        iconTheme: {
          primary: '#fff',
          secondary: '#ef4444',
        },
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <LinearGradient colors={['#0b0f10', '#1a1d24']} style={StyleSheet.absoluteFill}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.container}
      >
        <View style={styles.card}>
          <View style={styles.header}>
            <Image source={require('../../../assets/img/logo.png')} style={styles.logo} />
            <Text style={styles.title}>Faça seu login</Text>
          </View>

          <View style={styles.form}>
            <View style={styles.inputWrap}>
              <Feather name="mail" size={18} color="#dcdcdc" />
              <TextInput
                value={email}
                onChangeText={setEmail}
                placeholder="E-mail"
                placeholderTextColor="rgba(255,255,255,0.65)"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                style={styles.input}
              />
            </View>

            <View style={styles.inputWrap}>
              <Feather name="lock" size={18} color="#dcdcdc" />
              <TextInput
                value={senha}
                onChangeText={setSenha}
                placeholder="Senha"
                placeholderTextColor="rgba(255,255,255,0.65)"
                secureTextEntry={secure}
                autoCapitalize="none"
                autoCorrect={false}
                style={styles.input}
              />
              <Pressable onPress={() => setSecure((v) => !v)} hitSlop={10}>
                <Feather name={secure ? 'eye-off' : 'eye'} size={18} color="rgba(255,255,255,0.7)" />
              </Pressable>
            </View>

            <TouchableOpacity
              activeOpacity={0.9}
              disabled={isDisabled || loading}
              onPress={handleLogin}
              style={[styles.btnWrap, (isDisabled || loading) && styles.btnDisabled]}
            >
              <LinearGradient
                colors={['#BF1F5A', '#F25D39', '#FF9A3D']}
                start={{ x: 0, y: 0.5 }}
                end={{ x: 1, y: 0.5 }}
                style={styles.btn}
              >
                {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.btnText}>ENTRAR</Text>}
              </LinearGradient>
            </TouchableOpacity>

            <Pressable onPress={() => {}} hitSlop={10}>
              <Text style={styles.link}>Esqueceu sua senha?</Text>
            </Pressable>

            <View style={styles.dividerRow}>
              <View style={styles.dividerLine} />
              <Text style={styles.dividerText}>ou continue com</Text>
              <View style={styles.dividerLine} />
            </View>

            <View style={styles.socialRow}>
              <Pressable onPress={() => {}} style={styles.socialBtn}>
                <FontAwesome name="facebook-f" size={18} color="#fff" />
              </Pressable>
              <Pressable onPress={() => {}} style={styles.socialBtn}>
                <FontAwesome name="google" size={18} color="#fff" />
              </Pressable>
            </View>

            <View style={styles.footer}>
              <Text style={styles.footerText}>Não tem conta? </Text>
              <Pressable onPress={() => {}} hitSlop={10}>
                <Text style={[styles.footerText, styles.footerLink]}>Cadastre-se</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </KeyboardAvoidingView>
    </LinearGradient>
  );
}
