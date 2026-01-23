import AsyncStorage from './storage';

const LOG_KEY = '@appcheckin:debug_logs';
const MAX_LOGS = 500; // Máximo de logs para manter em memória

interface LogEntry {
  timestamp: string;
  level: 'log' | 'warn' | 'error';
  message: string;
  data?: any;
}

class DebugLogger {
  private logs: LogEntry[] = [];

  async initialize() {
    try {
      const stored = await AsyncStorage.getItem(LOG_KEY);
      if (stored) {
        this.logs = JSON.parse(stored);
      }
    } catch (error) {
      console.error('Erro ao inicializar DebugLogger:', error);
    }
  }

  private async saveLogs() {
    try {
      // Manter apenas os últimos MAX_LOGS
      if (this.logs.length > MAX_LOGS) {
        this.logs = this.logs.slice(-MAX_LOGS);
      }
      await AsyncStorage.setItem(LOG_KEY, JSON.stringify(this.logs));
    } catch (error) {
      // Silenciosamente ignorar erros de storage
      console.error('Erro ao salvar logs:', error);
    }
  }

  private addLog(level: 'log' | 'warn' | 'error', message: string, data?: any) {
    const now = new Date();
    const timestamp = `${now.toLocaleTimeString()}.${now.getMilliseconds()}`;

    const entry: LogEntry = {
      timestamp,
      level,
      message,
      ...(data && { data }),
    };

    this.logs.push(entry);

    // Também logar no console se disponível
    if (level === 'log') {
      console.log(`[${timestamp}] ${message}`, data || '');
    } else if (level === 'warn') {
      console.warn(`[${timestamp}] ${message}`, data || '');
    } else if (level === 'error') {
      console.error(`[${timestamp}] ${message}`, data || '');
    }

    // Salvar de forma assíncrona
    this.saveLogs().catch(() => {
      // Ignorar erros de storage
    });
  }

  log(message: string, data?: any) {
    this.addLog('log', message, data);
  }

  warn(message: string, data?: any) {
    this.addLog('warn', message, data);
  }

  error(message: string, data?: any) {
    this.addLog('error', message, data);
  }

  async getLogs(): Promise<LogEntry[]> {
    return this.logs;
  }

  async clearLogs() {
    this.logs = [];
    await AsyncStorage.removeItem(LOG_KEY);
  }

  async getLogsAsText(): Promise<string> {
    const logs = await this.getLogs();
    return logs
      .map((log) => {
        let text = `[${log.timestamp}] [${log.level.toUpperCase()}] ${log.message}`;
        if (log.data) {
          text += ` | ${JSON.stringify(log.data)}`;
        }
        return text;
      })
      .join('\n');
  }

  async exportLogs(): Promise<string> {
    const logs = await this.getLogs();
    const text = await this.getLogsAsText();
    return `
╔════════════════════════════════════════════════════════════╗
║                    DEBUG LOGS EXPORT                       ║
║                 ${new Date().toLocaleString()}             ║
╚════════════════════════════════════════════════════════════╝

Total de logs: ${logs.length}

${text}
`;
  }
}

export const debugLogger = new DebugLogger();
