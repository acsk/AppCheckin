import { StyleSheet } from 'react-native';

/**
 * Estilos globais compartilhados entre todas as telas
 */
const globalStyles = StyleSheet.create({
  // Estilos de Input com placeholder claro
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    backgroundColor: '#fff',
    color: '#111827', // Texto escuro
  },
  
  inputError: {
    borderColor: '#ef4444',
  },
  
  inputDisabled: {
    backgroundColor: '#f3f4f6',
    color: '#6b7280',
  },

  // TextInput com foco
  inputFocused: {
    borderColor: '#3b82f6',
    borderWidth: 2,
  },

  // Labels
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 6,
  },

  // Mensagens de erro
  errorText: {
    color: '#ef4444',
    fontSize: 12,
    marginTop: 4,
  },

  // Container de input
  inputGroup: {
    marginBottom: 16,
  },

  // Picker container
  pickerContainer: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    backgroundColor: '#fff',
    overflow: 'hidden',
  },

  picker: {
    height: 48,
    color: '#111827',
  },

  textArea: {
    height: 80,
    textAlignVertical: 'top',
  },
});

/**
 * Cores globais do tema
 */
const colors = {
  // Primary
  primary: '#3b82f6',
  primaryDark: '#2563eb',
  primaryLight: '#60a5fa',
  
  // Text
  textPrimary: '#111827',
  textSecondary: '#6b7280',
  textTertiary: '#9ca3af',
  textDisabled: '#d1d5db',
  
  // Placeholder
  placeholder: '#9ca3af',
  
  // Borders
  border: '#d1d5db',
  borderFocus: '#3b82f6',
  borderError: '#ef4444',
  
  // Backgrounds
  bgWhite: '#ffffff',
  bgGray50: '#f9fafb',
  bgGray100: '#f3f4f6',
  bgGray200: '#e5e7eb',
  
  // Status
  success: '#10b981',
  error: '#ef4444',
  warning: '#f59e0b',
  info: '#3b82f6',
};

/**
 * Função helper para aplicar estilos de input com placeholder
 */
const getInputProps = (error = false, disabled = false, focused = false) => ({
  style: [
    globalStyles.input,
    error && globalStyles.inputError,
    disabled && globalStyles.inputDisabled,
    focused && globalStyles.inputFocused,
  ],
  placeholderTextColor: colors.placeholder,
});

export { globalStyles, colors, getInputProps };
