import React, { useCallback, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useFocusEffect, useRouter } from 'expo-router';
import api from '@/src/services/api';
import AsyncStorage from '@/src/utils/storage';
import { useParametro } from '@/src/hooks/useConfigRemota';
import { useRefreshOnFocus } from '@/src/hooks/useRefreshOnFocus';
import { abrirPendencias, textoPendencias, useResumoComunicacoes } from '@/src/services/comunicacoes';
import AppHeader from './ds/AppHeader';
import Button from './ds/Button';
import { ds } from './ds/tokens';

/** Verifica também links diretos; o conteúdo de Check-in só monta após a autorização. */
export default function CheckinAccessGate({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const exigirMatricula = useParametro('checkin_exigir_matricula_ativa');
  const [estado, setEstado] = useState<'carregando' | 'permitido' | 'bloqueado' | 'erro'>('carregando');
  const [mensagem, setMensagem] = useState('');
  const requisicao = useRef(0);
  const comunicacoes = useResumoComunicacoes();
  const verificar = useCallback(async () => {
    const id = ++requisicao.current;
    try {
      const academia = await AsyncStorage.getItem('@appcheckin:tenant_id');
      const r: any = await api.get('/mobile/checkin/acesso');
      const academiaAtual = await AsyncStorage.getItem('@appcheckin:tenant_id');
      if (id !== requisicao.current) return;
      if (academia !== academiaAtual) {
        setMensagem('A academia selecionada mudou. Verifique novamente o acesso.');
        setEstado('erro');
        return;
      }
      if (typeof r?.data?.permitido !== 'boolean') throw new Error('Resposta inválida');
      setMensagem(r.data.motivo || 'Você precisa de uma matrícula ativa com plano nesta academia.');
      setEstado(r.data.permitido ? 'permitido' : 'bloqueado');
    } catch {
      if (id !== requisicao.current) return;
      setMensagem('Não foi possível verificar seu acesso ao Check-in. Tente novamente.');
      setEstado('erro');
    }
  }, []);
  useFocusEffect(useCallback(() => {
    setEstado('carregando');
    void verificar();
    return () => { requisicao.current++; };
  }, [verificar, exigirMatricula]));
  useRefreshOnFocus(verificar);

  if (estado === 'permitido') {
    const pend = comunicacoes.total_pendencias;
    return <>
      {children}
      {/* Comunicação obrigatória pendente: a API recusa o check-in; aqui o aluno vê e resolve */}
      {pend ? <Pressable style={styles.pend} onPress={abrirPendencias} accessibilityRole="button" accessibilityLabel={`${textoPendencias(pend)} Check-in indisponível. Toque para resolver.`}>
        <Feather name="lock" size={16} color={ds.warning} />
        <View style={{ flex: 1 }}>
          <Text style={styles.pendTitulo}>Check-in indisponível</Text>
          <Text style={styles.pendTexto} numberOfLines={2}>{textoPendencias(pend)}</Text>
        </View>
        <Text style={styles.pendAcao}>Resolver</Text>
      </Pressable> : null}
    </>;
  }
  return (
    <SafeAreaView style={styles.root} edges={['top', 'left', 'right']}>
      <AppHeader title="Check-in" safeTop={false} />
      <View style={styles.body}>
        {estado === 'carregando' ? <><ActivityIndicator color={ds.brand} /><Text style={styles.text}>Verificando acesso ao Check-in…</Text></>
          : <>
            <Text style={styles.title}>{estado === 'erro' ? 'Não foi possível verificar o acesso' : 'Check-in indisponível'}</Text>
            <Text style={styles.text}>{mensagem}</Text>
            {estado === 'bloqueado' ? <Button onPress={() => router.replace('/(tabs)/planos')}>Ver meus planos</Button> : null}
            <Button variant="secondary" onPress={() => { setEstado('carregando'); void verificar(); }}>Verificar novamente</Button>
            <Button variant="ghost" onPress={() => router.replace('/(tabs)/account')}>Voltar ao início</Button>
          </>}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: ds.ground },
  body: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24, gap: 16 },
  title: { color: ds.ink, fontSize: 22, fontWeight: '700', textAlign: 'center' },
  text: { color: ds.inkMuted, fontSize: 15, textAlign: 'center', maxWidth: 440 },
  pend: { position: 'absolute', left: 12, right: 12, bottom: 96, flexDirection: 'row', alignItems: 'center', gap: 10, padding: 12, borderRadius: 16, backgroundColor: ds.warningSoft, borderWidth: 1, borderColor: ds.warning, shadowColor: '#000', shadowOpacity: 0.12, shadowRadius: 12, shadowOffset: { width: 0, height: 4 }, elevation: 4 },
  pendTitulo: { color: ds.warning, fontSize: 14, fontWeight: '800' },
  pendTexto: { color: ds.ink, fontSize: 13, fontWeight: '600' },
  pendAcao: { color: ds.brandInk, fontSize: 14, fontWeight: '800' },
});
