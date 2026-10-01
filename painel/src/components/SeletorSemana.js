import React, { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Feather } from '@expo/vector-icons';

const NOMES_MESES = [
  'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

const paraData = (str) => {
  const [a, m, d] = String(str).split('-').map(Number);
  return new Date(a, m - 1, d);
};

const paraStr = (dt) =>
  `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;

const curta = (dt) => `${String(dt.getDate()).padStart(2, '0')}/${String(dt.getMonth() + 1).padStart(2, '0')}`;

/** Domingo (YYYY-MM-DD) da semana que contém a data. */
export const domingoDaSemana = (dataStr) => {
  if (!dataStr) return '';
  const dt = paraData(dataStr);
  dt.setDate(dt.getDate() - dt.getDay());
  return paraStr(dt);
};

/** "06/09 a 12/09" para a semana que começa no domingo informado. */
export const descreverSemana = (domingoStr) => {
  if (!domingoStr) return '';
  const inicio = paraData(domingoStr);
  const fim = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate() + 6);
  return `${curta(inicio)} a ${curta(fim)}`;
};

/** Semanas (domingo a sábado) que têm pelo menos um dia no mês. */
const semanasDoMes = (ano, mesIndex) => {
  const primeiro = new Date(ano, mesIndex, 1);
  const ultimo = new Date(ano, mesIndex + 1, 0);
  const cursor = new Date(ano, mesIndex, 1 - primeiro.getDay());
  const semanas = [];
  while (cursor <= ultimo) {
    semanas.push(paraStr(cursor));
    cursor.setDate(cursor.getDate() + 7);
  }
  return semanas;
};

/**
 * Seleção de uma semana de domingo a sábado.
 * value/onChange: data do domingo (YYYY-MM-DD).
 */
export default function SeletorSemana({ value, onChange, disabled = false }) {
  const base = value ? paraData(value) : new Date();
  // Mostra o mês onde está a maior parte da semana (quarta-feira)
  const meio = new Date(base.getFullYear(), base.getMonth(), base.getDate() + (value ? 3 : 0));
  const [ano, setAno] = useState(meio.getFullYear());
  const [mes, setMes] = useState(meio.getMonth());

  const navegar = (delta) => {
    const alvo = new Date(ano, mes + delta, 1);
    setAno(alvo.getFullYear());
    setMes(alvo.getMonth());
  };

  return (
    <View style={[styles.container, disabled && styles.disabled]}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navegar(-1)} disabled={disabled} style={styles.navButton}>
          <Feather name="chevron-left" size={18} color="#4b5563" />
        </TouchableOpacity>
        <Text style={styles.titulo}>{NOMES_MESES[mes]} {ano}</Text>
        <TouchableOpacity onPress={() => navegar(1)} disabled={disabled} style={styles.navButton}>
          <Feather name="chevron-right" size={18} color="#4b5563" />
        </TouchableOpacity>
      </View>

      <View style={styles.lista}>
        {semanasDoMes(ano, mes).map((domingo) => {
          const ativo = domingo === value;
          return (
            <TouchableOpacity
              key={domingo}
              style={[styles.semana, ativo && styles.semanaAtiva]}
              onPress={() => onChange(domingo)}
              disabled={disabled}
            >
              <Feather name={ativo ? 'check-circle' : 'circle'} size={16} color={ativo ? '#fff' : '#9ca3af'} />
              <Text style={[styles.semanaTexto, ativo && styles.semanaTextoAtivo]}>
                Dom {descreverSemana(domingo).replace(' a ', ' a Sáb ')}
              </Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    padding: 10,
    backgroundColor: '#fff',
  },
  disabled: {
    opacity: 0.6,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  navButton: {
    padding: 6,
    borderRadius: 8,
    backgroundColor: '#f3f4f6',
  },
  titulo: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1f2937',
  },
  lista: {
    gap: 6,
  },
  semana: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 10,
    backgroundColor: '#f3f4f6',
    borderWidth: 2,
    borderColor: '#e5e7eb',
  },
  semanaAtiva: {
    backgroundColor: '#10b981',
    borderColor: '#059669',
  },
  semanaTexto: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6b7280',
  },
  semanaTextoAtivo: {
    color: '#fff',
  },
});
