import toast from 'react-hot-toast';

// Estilos base para todos os toasts
const baseStyle = {
  minWidth: '320px',
  padding: '18px 24px',
  fontSize: '16px',
  fontWeight: '600',
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Helvetica Neue", "Ubuntu", sans-serif',
  letterSpacing: '0.3px',
  lineHeight: '1.5',
  borderRadius: '14px',
  boxShadow: '0 12px 48px rgba(0, 0, 0, 0.25), 0 4px 16px rgba(0, 0, 0, 0.15)',
  backdropFilter: 'blur(12px)',
  border: '1px solid rgba(255, 255, 255, 0.2)',
  textShadow: '0 1px 2px rgba(0, 0, 0, 0.15)',
};

export const showSuccess = (message) => {
  // Se for um objeto com message, extrair a mensagem
  const text = typeof message === 'object' && message?.message ? message.message : message;
  
  toast.success(text, {
    duration: 4000,
    position: 'top-right',
    style: {
      ...baseStyle,
      background: 'rgba(16, 185, 129, 0.92)',
      color: '#fff',
      zIndex: 99999,
    },
    iconTheme: {
      primary: '#fff',
      secondary: 'rgba(16, 185, 129, 0.92)',
    },
  });
};

export const showError = (message) => {
  // Se for um objeto com error ou message, extrair a mensagem
  const text = typeof message === 'object' 
    ? (message?.error || message?.message || JSON.stringify(message))
    : message;
  
  toast.error(text, {
    duration: 4000,
    position: 'top-right',
    style: {
      ...baseStyle,
      background: 'rgba(239, 68, 68, 0.92)',
      color: '#fff',
      zIndex: 99999,
    },
    iconTheme: {
      primary: '#fff',
      secondary: 'rgba(239, 68, 68, 0.92)',
    },
  });
};

export const showWarning = (message) => {
  const text = typeof message === 'object' 
    ? (message?.message || message?.error || JSON.stringify(message))
    : message;
  
  toast(text, {
    duration: 4000,
    position: 'top-right',
    icon: '⚠️',
    style: {
      ...baseStyle,
      background: 'rgba(245, 158, 11, 0.92)',
      color: '#fff',
      zIndex: 99999,
    },
    iconTheme: {
      primary: '#fff',
      secondary: 'rgba(245, 158, 11, 0.92)',
    },
  });
};

export const showLoading = (message = 'Carregando...') => {
  return toast.loading(message, {
    position: 'top-right',
    style: {
      ...baseStyle,
      background: 'rgba(249, 115, 22, 0.92)',
      color: '#fff',
      zIndex: 99999,
    },
    iconTheme: {
      primary: '#fff',
      secondary: 'rgba(249, 115, 22, 0.92)',
    },
  });
};

export const dismissToast = (toastId) => {
  toast.dismiss(toastId);
};

// Exportar como objeto padrão também para compatibilidade
export default {
  success: showSuccess,
  error: showError,
  warning: showWarning,
  loading: showLoading,
  dismiss: dismissToast,
};
