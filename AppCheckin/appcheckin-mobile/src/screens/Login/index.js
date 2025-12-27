import React, { useMemo, useState } from 'react';
import {
  ImageBackground,
  SafeAreaView,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  TextInput,
  TouchableOpacity,
  View,
  Text,
  Image,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Feather, FontAwesome } from '@expo/vector-icons';
import styles from './styles';

export default function LoginScreen({ baseUrl = 'http://localhost:8080', onSucesso = () => {} }) {
  const [email, setEmail] = useState('teste@exemplo.com');
  const [senha, setSenha] = useState('password123');
  const [secure, setSecure] = useState(true);
  const [mensagem, setMensagem] = useState('');
  const [carregando, setCarregando] = useState(false);

  const isDisabled = useMemo(() => email.trim().length === 0 || senha.trim().length === 0, [email, senha]);

  const onSubmit = async () => {
    if (isDisabled || carregando) return;
    setMensagem('');
    setCarregando(true);
    try {
      const resposta = await fetch(`${baseUrl}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, senha }),
      });
      if (!resposta.ok) {
        throw new Error('Falha ao entrar. Confira email/senha.');
      }
      const dados = await resposta.json();
      setMensagem(dados.message || 'Login realizado com sucesso.');
      onSucesso(dados.user || null);
    } catch (erro) {
      setMensagem(erro.message || 'Erro ao entrar.');
    } finally {
      setCarregando(false);
    }
  };

  return (
    <ImageBackground source={require('../../../assets/img/bg.png')} resizeMode="cover" style={styles.bg}>
      <View style={styles.overlay} />
      <SafeAreaView style={styles.safe}>
        <KeyboardAvoidingView
          style={styles.container}
          behavior={Platform.select({ ios: 'padding', android: undefined })}
        >
          <View style={styles.header}>
            <Image source={require('../../../assets/img/app.png')} style={styles.logoImg} />
          </View>

          <Text style={styles.title}>Faça seu login</Text>

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
              disabled={isDisabled || carregando}
              onPress={onSubmit}
              style={[styles.btnWrap, (isDisabled || carregando) && styles.btnDisabled]}
            >
              <LinearGradient
                colors={['#BF1F5A', '#F25D39', '#FF9A3D']}
                start={{ x: 0, y: 0.5 }}
                end={{ x: 1, y: 0.5 }}
                style={styles.btn}
              >
                <Text style={styles.btnText}>{carregando ? 'Entrando...' : 'ENTRAR'}</Text>
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

            {mensagem ? <Text style={styles.mensagem}>{mensagem}</Text> : null}
          </View>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ImageBackground>
  );
}
