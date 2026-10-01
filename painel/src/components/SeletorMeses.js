import React, { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Feather } from '@expo/vector-icons';

const NOMES_MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

const chaveMes = (ano, mesIndex) => `${ano}-${String(mesIndex + 1).padStart(2, '0')}`;

export const formatarMesAno = (chave) => {
  const [ano, mes] = String(chave).split('-');
  const idx = parseInt(mes, 10) - 1;
  return NOMES_MESES[idx] ? `${NOMES_MESES[idx]}/${ano}` : chave;
};

/**
 * Seleção de mês(es) no formato YYYY-MM.
 * - multiplo: permite marcar vários meses (value = array); senão value = string.
 * - mesMinimo: YYYY-MM; meses anteriores ficam desabilitados.
 */
export default function SeletorMeses({ value, onChange, multiplo = false, mesMinimo = null, disabled = false }) {
  const selecionados = multiplo ? (Array.isArray(value) ? value : []) : value ? [value] : [];
  const anoInicial = parseInt((selecionados[0] || mesMinimo || new Date().toISOString().slice(0, 7)).slice(0, 4), 10);
  const [ano, setAno] = useState(anoInicial);

  const alternar = (chave) => {
    if (multiplo) {
      const proximo = selecionados.includes(chave)
        ? selecionados.filter((m) => m !== chave)
        : [...selecionados, chave].sort();
      onChange(proximo);
    } else {
      onChange(selecionados.includes(chave) ? '' : chave);
    }
  };

  return (
    <View style={[styles.container, disabled && styles.disabled]}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => setAno(ano - 1)} disabled={disabled} style={styles.navButton}>
          <Feather name="chevron-left" size={18} color="#4b5563" />
        </TouchableOpacity>
        <Text style={styles.ano}>{ano}</Text>
        <TouchableOpacity onPress={() => setAno(ano + 1)} disabled={disabled} style={styles.navButton}>
          <Feather name="chevron-right" size={18} color="#4b5563" />
        </TouchableOpacity>
      </View>

      <View style={styles.grid}>
        {NOMES_MESES.map((nome, idx) => {
          const chave = chaveMes(ano, idx);
          const ativo = selecionados.includes(chave);
          const bloqueado = disabled || (mesMinimo && chave < mesMinimo);
          return (
            <TouchableOpacity
              key={chave}
              style={[styles.mes, ativo && styles.mesAtivo, bloqueado && styles.mesBloqueado]}
              onPress={() => alternar(chave)}
              disabled={bloqueado}
            >
              <Text style={[styles.mesTexto, ativo && styles.mesTextoAtivo, bloqueado && styles.mesTextoBloqueado]}>
                {nome}
              </Text>
            </TouchableOpacity>
          );
        })}
      </View>

      {selecionados.length > 0 && (
        <Text style={styles.resumo}>
          Selecionado{selecionados.length > 1 ? 's' : ''}: {selecionados.map(formatarMesAno).join(', ')}
        </Text>
      )}
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
  ano: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1f2937',
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  mes: {
    width: '22%',
    flexGrow: 1,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: '#f3f4f6',
    borderWidth: 2,
    borderColor: '#e5e7eb',
    alignItems: 'center',
  },
  mesAtivo: {
    backgroundColor: '#10b981',
    borderColor: '#059669',
  },
  mesBloqueado: {
    backgroundColor: '#f9fafb',
    borderColor: '#f3f4f6',
  },
  mesTexto: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6b7280',
  },
  mesTextoAtivo: {
    color: '#fff',
  },
  mesTextoBloqueado: {
    color: '#d1d5db',
  },
  resumo: {
    marginTop: 8,
    fontSize: 12,
    fontWeight: '600',
    color: '#059669',
  },
});
