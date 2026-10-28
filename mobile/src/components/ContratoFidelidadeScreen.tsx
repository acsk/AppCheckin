import React from 'react';
import { Modal, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import AppHeader from './ds/AppHeader';
import Button from './ds/Button';
import { ds, space } from './ds/tokens';
import type { Oferta } from '@/src/services/recorrencia';

/** Tela de leitura: exibe o texto exato da oferta, sem gerar ou substituir cláusulas. */
export default function ContratoFidelidadeScreen({ visible, oferta, onClose, onAccept }: {
  visible: boolean; oferta: Oferta | null; onClose: () => void; onAccept: () => void;
}) {
  return <Modal visible={visible} animationType="slide" presentationStyle="fullScreen" onRequestClose={onClose}>
    <SafeAreaView style={styles.screen} edges={["left", "right", "bottom"]}>
      <AppHeader title="Contrato" eyebrow={[oferta?.ciclo_nome, oferta?.plano_nome].filter(Boolean).join(' · ')} onBack={onClose} />
      <ScrollView contentContainerStyle={styles.document}>
        <Text accessibilityRole="header" style={styles.title}>Plano com fidelidade</Text>
        {oferta?.termos_versao ? <Text style={styles.meta}>Versão {oferta.termos_versao}</Text> : null}
        {(oferta?.termos || '').split(/\n\s*\n/).map((paragraph, index) => <Text key={index} selectable style={styles.paragraph}>{paragraph}</Text>)}
      </ScrollView>
      <View style={styles.footer}><Button block size="lg" icon="check" onPress={onAccept}>Aceitar e voltar</Button></View>
    </SafeAreaView>
  </Modal>;
}
const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: ds.ground }, document: { padding: space[5], gap: space[4], width: '100%', maxWidth: 720, alignSelf: 'center' },
  title: { fontSize: 25, lineHeight: 31, fontWeight: '800', color: ds.ink },
  meta: { fontSize: 13, color: ds.inkMuted }, paragraph: { fontSize: 16, lineHeight: 26, color: ds.ink },
  footer: { padding: space[4], borderTopWidth: 1, borderTopColor: ds.line, backgroundColor: ds.surface },
});
