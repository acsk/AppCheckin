import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useFocusEffect, useRouter } from 'expo-router';
import api from '@/src/services/api';
import AsyncStorage from '@/src/utils/storage';
import { useParametro } from '@/src/hooks/useConfigRemota';
import { useRefreshOnFocus } from '@/src/hooks/useRefreshOnFocus';
import { abrirPendencias, textoPendencias, useResumoComunicacoes } from '@/src/services/comunicacoes';
import { apiAlcancavel, esperar } from '@/src/utils/conexao';
import AppHeader from './ds/AppHeader';
import Button from './ds/Button';
import { ds } from './ds/tokens';

/** Verifica também links diretos; o conteúdo de Check-in só monta após a autorização. */
export default function CheckinAccessGate({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const exigirMatricula = useParametro('checkin_exigir_matricula_ativa');
  const [estado, setEstadoBruto] = useState<'carregando' | 'permitido' | 'bloqueado' | 'erro' | 'offline'>('carregando');
  const [mensagem, setMensagem] = useState('');
  const requisicao = useRef(0);
  const estadoRef = useRef(estado);
  const setEstado = useCallback((e: typeof estado) => { estadoRef.current = e; setEstadoBruto(e); }, []);
  const comunicacoes = useResumoComunicacoes();
  const verificar = useCallback(async () => {
    const id = ++requisicao.current;
    const vigente = () => id === requisicao.current;
    // Já dentro do Check-in, uma oscilação de rede na atualização automática não tira o aluno da tela
    const falhaDeRede = () => {
      if (!vigente() || estadoRef.current === 'permitido') return;
      setMensagem('Não conseguimos falar com o servidor. Confira sua internet; verificamos de novo assim que a conexão voltar.');
      setEstado('offline');
    };
    const consultar = async () => {
      const r: any = await api.get('/mobile/checkin/acesso');
      if (typeof r?.data?.permitido !== 'boolean') throw new Error('Resposta inválida');
      return r.data as { permitido: boolean; motivo?: string | null };
    };
    try {
      // 1. Teste de comunicação antes: sem internet nunca vira "sem matrícula"
      if (!(await apiAlcancavel())) return falhaDeRede();
      const academia = await AsyncStorage.getItem('@appcheckin:tenant_id');
      let d = await consultar();
      // 2. "Sem acesso" só depois de confirmar: comunicação de pé e a segunda consulta igual
      if (!d.permitido) {
        await esperar(800);
        if (!(await apiAlcancavel())) return falhaDeRede();
        d = await consultar();
      }
      const academiaAtual = await AsyncStorage.getItem('@appcheckin:tenant_id');
      if (!vigente()) return;
      if (academia !== academiaAtual) {
        setMensagem('A academia selecionada mudou. Verifique novamente o acesso.');
        setEstado('erro');
        return;
      }
      setMensagem(d.motivo || 'Você precisa de uma matrícula ativa com plano nesta academia.');
      setEstado(d.permitido ? 'permitido' : 'bloqueado');
    } catch (e: any) {
      if (!vigente()) return;
      // Caiu no meio da consulta: se a API não responde ao ping, é conexão, não pendência
      if (e?.isNetworkError || !(await apiAlcancavel())) return falhaDeRede();
      if (!vigente() || estadoRef.current === 'permitido') return;
      setMensagem('Não foi possível verificar seu acesso ao Check-in. Tente novamente.');
      setEstado('erro');
    }
  }, [setEstado]);

  // Sem conexão: tenta de novo sozinho (a cada 5 s e quando o navegador avisa que voltou)
  useEffect(() => {
    if (estado !== 'offline') return;
    const t = setInterval(() => { void verificar(); }, 5000);
    const voltou = () => { void verificar(); };
    if (typeof window !== 'undefined' && window.addEventListener) window.addEventListener('online', voltou);
    return () => {
      clearInterval(t);
      if (typeof window !== 'undefined' && window.removeEventListener) window.removeEventListener('online', voltou);
    };
  }, [estado, verificar]);
  useFocusEffect(useCallback(() => {
    setEstado('carregando');
    void verificar();
    return () => { requisicao.current++; };
  }, [verificar, exigirMatricula, setEstado]));
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
            {estado === 'offline' ? <Feather name="wifi-off" size={36} color={ds.inkMuted} /> : null}
            <Text style={styles.title}>{estado === 'offline' ? 'Sem conexão' : estado === 'erro' ? 'Não foi possível verificar o acesso' : 'Check-in indisponível'}</Text>
            <Text style={styles.text}>{mensagem}</Text>
            {estado === 'bloqueado' ? <Button onPress={() => router.replace('/(tabs)/planos')}>Ver meus planos</Button> : null}
            <Button variant="secondary" onPress={() => { setEstado('carregando'); void verificar(); }}>{estado === 'offline' ? 'Tentar novamente' : 'Verificar novamente'}</Button>
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
