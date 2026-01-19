import React from 'react';
import { View, Text, TextInput, Pressable, ActivityIndicator } from 'react-native';
import { Feather } from '@expo/vector-icons';
import styles from '../styles';

export default function AcademiaForm({ values, onChange, onSubmit, saving, isEditing }) {
  return (
    <View style={[styles.card, styles.acadForm]}>
      <View style={styles.formGroup}>
        <Text style={styles.formLabel}>Nome *</Text>
        <TextInput
          style={styles.formInput}
          value={values.nome}
          onChangeText={(v) => onChange({ ...values, nome: v })}
          placeholder="Digite o nome"
        />
      </View>
      <View style={styles.formGroup}>
        <Text style={styles.formLabel}>Email *</Text>
        <TextInput
          style={styles.formInput}
          value={values.email}
          onChangeText={(v) => onChange({ ...values, email: v })}
          placeholder="contato@academia.com"
          autoCapitalize="none"
        />
      </View>
      <View style={styles.formGroup}>
        <Text style={styles.formLabel}>Telefone</Text>
        <TextInput
          style={styles.formInput}
          value={values.telefone}
          onChangeText={(v) => onChange({ ...values, telefone: v })}
          placeholder="(11) 99999-9999"
          keyboardType="phone-pad"
        />
      </View>
      <View style={styles.formGroup}>
        <Text style={styles.formLabel}>Endereço</Text>
        <TextInput
          style={[styles.formInput, styles.formArea]}
          value={values.endereco}
          onChangeText={(v) => onChange({ ...values, endereco: v })}
          placeholder="Rua, nº, bairro, cidade"
          multiline
        />
      </View>
      <Pressable
        style={[styles.btnGradient, saving && { opacity: 0.6 }]}
        onPress={onSubmit}
        disabled={saving}
      >
        {saving ? <ActivityIndicator color="#fff" /> : (
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
            <Feather name="save" size={16} color="#fff" />
            <Text style={styles.btnGradientText}>{isEditing ? 'Salvar alterações' : 'Salvar'}</Text>
          </View>
        )}
      </Pressable>
    </View>
  );
}
