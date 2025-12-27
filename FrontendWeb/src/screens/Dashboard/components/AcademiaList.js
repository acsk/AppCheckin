import React from 'react';
import { View, Text, Pressable, ActivityIndicator } from 'react-native';
import { Feather } from '@expo/vector-icons';
import styles from '../styles';

export default function AcademiaList({ academias, loading, onNovo, onEditar, onExcluir }) {
  return (
    <View style={[styles.card, styles.acadList]}>
      <View style={styles.cardHead}>
        <Text style={styles.cardHeadTitle}>Academias</Text>
        <Pressable style={styles.btnNew} onPress={onNovo}>
          <Feather name="plus" size={16} color="#fff" />
          <Text style={styles.btnNewText}>Nova</Text>
        </Pressable>
      </View>

      {loading ? (
        <ActivityIndicator color="#2b1a04" />
      ) : academias.length === 0 ? (
        <Text style={styles.muted}>Nenhuma academia cadastrada.</Text>
      ) : (
        <View style={styles.table}>
          <View style={[styles.tableRow, styles.tableHead]}>
            <Text style={[styles.tableCell, styles.tableHeadText, styles.flex2]}>Nome</Text>
            <Text style={[styles.tableCell, styles.tableHeadText, styles.flex2]}>Email</Text>
            <Text style={[styles.tableCell, styles.tableHeadText, styles.flex1]}>Telefone</Text>
            <Text style={[styles.tableCell, styles.tableHeadText, styles.flex1, styles.right]}>Ações</Text>
          </View>
          {academias.map((a) => (
            <View key={a.id || a.nome} style={styles.tableRow}>
              <Text style={[styles.tableCell, styles.flex2]}>{a.nome}</Text>
              <Text style={[styles.tableCell, styles.flex2]}>{a.email}</Text>
              <Text style={[styles.tableCell, styles.flex1]}>{a.telefone || '-'}</Text>
              <View style={[styles.tableCell, styles.flex1, styles.actions]}>
                <Pressable style={styles.btnGhost} onPress={() => onEditar(a)}>
                  <Feather name="edit-2" size={14} color="#2b1a04" />
                  <Text style={styles.btnGhostText}>Editar</Text>
                </Pressable>
                <Pressable style={styles.btnGhost} onPress={() => onExcluir(a)}>
                  <Feather name="trash-2" size={14} color="#b91c1c" />
                  <Text style={[styles.btnGhostText, { color: '#b91c1c' }]}>Excluir</Text>
                </Pressable>
              </View>
            </View>
          ))}
        </View>
      )}
    </View>
  );
}
