import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  Platform,
  StyleSheet,
} from 'react-native';
import { Feather } from '@expo/vector-icons';

let DateTimePicker = null;
if (Platform.OS !== 'web') {
  DateTimePicker = require('@react-native-community/datetimepicker').default;
}

const formatarExibicao = (iso) => {
  if (!iso) return null;
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
};

/**
 * DatePickerInput
 *
 * Props:
 *   value      – string YYYY-MM-DD ou ''
 *   onChange   – (isoString: string) => void
 *   label      – string  (opcional)
 *   placeholder – string (opcional)
 *   style      – ViewStyle adicional no container externo
 */
export default function DatePickerInput({ value, onChange, label, placeholder, style }) {
  const [showPicker, setShowPicker] = useState(false);

  const parsedDate = value ? new Date(value + 'T12:00:00') : new Date();

  // ── WEB ──────────────────────────────────────────────────────────────────
  if (Platform.OS === 'web') {
    return (
      <View style={[styles.wrapper, style]}>
        {label ? <Text style={styles.label}>{label}</Text> : null}
        <input
          type="date"
          value={value || ''}
          onChange={(e) => onChange(e.target.value)}
          style={webInputCSS}
        />
      </View>
    );
  }

  // ── NATIVE ───────────────────────────────────────────────────────────────
  return (
    <View style={[styles.wrapper, style]}>
      {label ? <Text style={styles.label}>{label}</Text> : null}
      <TouchableOpacity
        style={styles.nativeBtn}
        onPress={() => setShowPicker(true)}
        activeOpacity={0.7}
      >
        <Text style={[styles.nativeText, !value && styles.nativePlaceholder]}>
          {formatarExibicao(value) || placeholder || 'Selecionar data'}
        </Text>
        <Feather name="calendar" size={15} color="#f97316" />
      </TouchableOpacity>

      {showPicker && DateTimePicker ? (
        Platform.OS === 'ios' ? (
          <Modal transparent animationType="slide" visible={showPicker}>
            <View style={styles.iosOverlay}>
              <View style={styles.iosSheet}>
                <View style={styles.iosToolbar}>
                  <TouchableOpacity onPress={() => setShowPicker(false)}>
                    <Text style={styles.iosDismiss}>Fechar</Text>
                  </TouchableOpacity>
                </View>
                <DateTimePicker
                  value={parsedDate}
                  mode="date"
                  display="spinner"
                  locale="pt-BR"
                  onChange={(event, selectedDate) => {
                    if (selectedDate) {
                      const y = selectedDate.getFullYear();
                      const m = String(selectedDate.getMonth() + 1).padStart(2, '0');
                      const d = String(selectedDate.getDate()).padStart(2, '0');
                      onChange(`${y}-${m}-${d}`);
                    }
                  }}
                />
              </View>
            </View>
          </Modal>
        ) : (
          <DateTimePicker
            value={parsedDate}
            mode="date"
            display="calendar"
            onChange={(event, selectedDate) => {
              setShowPicker(false);
              if (selectedDate && event.type !== 'dismissed') {
                const y = selectedDate.getFullYear();
                const m = String(selectedDate.getMonth() + 1).padStart(2, '0');
                const d = String(selectedDate.getDate()).padStart(2, '0');
                onChange(`${y}-${m}-${d}`);
              }
            }}
          />
        )
      ) : null}
    </View>
  );
}

// ── Estilos ──────────────────────────────────────────────────────────────────

const webInputCSS = {
  backgroundColor: '#fff',
  border: '1px solid #d1d5db',
  borderRadius: '8px',
  padding: '8px 10px',
  fontSize: '13px',
  color: '#111827',
  fontFamily: 'inherit',
  outline: 'none',
  cursor: 'pointer',
  minWidth: '130px',
  height: '34px',
  boxSizing: 'border-box',
};

const styles = StyleSheet.create({
  wrapper: {},
  label: {
    fontSize: 11,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 4,
  },
  webInputWrap: {},
  nativeBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 9,
    minWidth: 120,
  },
  nativeText: {
    flex: 1,
    fontSize: 13,
    color: '#111827',
  },
  nativePlaceholder: {
    color: '#9ca3af',
  },
  // iOS sheet
  iosOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.4)',
  },
  iosSheet: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    paddingBottom: 30,
  },
  iosToolbar: {
    alignItems: 'flex-end',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  iosDismiss: {
    fontSize: 15,
    fontWeight: '600',
    color: '#f97316',
  },
});
