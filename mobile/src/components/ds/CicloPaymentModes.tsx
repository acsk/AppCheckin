import { Feather } from '@expo/vector-icons';
import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { ds, radius, space } from './tokens';

export default function CicloPaymentModes({ value, onChange, total, mensal, meses, bloqueio }: {
  value: 'avista' | 'assinatura'; onChange: (value: 'avista' | 'assinatura') => void;
  total: string; mensal: string; meses: number; bloqueio?: string | null;
}) {
  return <View style={styles.group} accessibilityRole="radiogroup" accessibilityLabel="Como pagar este ciclo">
    {([
      { id: 'avista', title: 'À vista', price: total, description: `${meses} meses de acesso, uma cobrança` },
      { id: 'assinatura', title: 'Mensal', price: `${mensal}/mês`, description: bloqueio || 'Uma cobrança por mês, com fidelidade' },
    ] as const).map(option => {
      const disabled = option.id === 'assinatura' && !!bloqueio;
      const selected = value === option.id;
      return <Pressable key={option.id} disabled={disabled} onPress={() => onChange(option.id)}
        accessibilityRole="radio" accessibilityState={{ checked: selected, disabled }}
        accessibilityLabel={`${option.title}, ${option.price}. ${option.description}`}
        style={[styles.option, selected && styles.selected, disabled && styles.disabled]}>
        <Feather name={selected ? 'check-circle' : 'circle'} size={18} color={selected ? ds.brandInk : ds.inkMuted} />
        <View style={styles.text}><Text style={styles.title}>{option.title}</Text><Text style={styles.description}>{option.description}</Text></View>
        <Text style={styles.price}>{option.price}</Text>
      </Pressable>;
    })}
  </View>;
}
const styles = StyleSheet.create({
  group: { gap: space[2], padding: space[3], backgroundColor: ds.brandSoft, borderRadius: radius.lg, marginTop: -4 },
  option: { flexDirection: 'row', gap: space[2], alignItems: 'center', padding: space[3], borderWidth: 1, borderColor: ds.line, borderRadius: radius.md, backgroundColor: ds.surface },
  selected: { borderColor: ds.brand }, disabled: { opacity: .55 }, text: { flex: 1 },
  title: { fontSize: 14, fontWeight: '800', color: ds.ink }, description: { fontSize: 12, lineHeight: 17, color: ds.inkMuted },
  price: { fontSize: 14, fontWeight: '800', color: ds.ink, maxWidth: '40%', textAlign: 'right' },
});
