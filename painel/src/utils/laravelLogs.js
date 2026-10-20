// Adapta as linhas físicas de /admin/logs ao modelo do LogsViewer.
// Filtros são aplicados depois da junção, para não separar mensagens e traces.
export const LOG_LEVELS = {
  error: { label: 'Erro', plural: 'Erros', tone: 'danger' },
  warning: { label: 'Aviso', plural: 'Avisos', tone: 'warning' },
  info: { label: 'Info', plural: 'Info', tone: 'info' },
  debug: { label: 'Debug', plural: 'Debug', tone: 'outline' },
  other: { label: 'Sem nível', plural: 'Sem nível', tone: 'outline' },
};

export function logLevel(level) {
  const value = String(level || '').toLowerCase();
  if (['critical', 'alert', 'emergency'].includes(value)) return 'error';
  if (value === 'notice') return 'info';
  return Object.prototype.hasOwnProperty.call(LOG_LEVELS, value) ? value : 'other';
}

// Monolog pode escrever quebras de linha literais dentro da string da exceção.
function jsonWithLineBreaks(text) {
  let quoted = false;
  let escaped = false;
  let result = '';
  for (const char of text) {
    if (quoted && (char === '\n' || char === '\r')) {
      result += char === '\n' ? '\\n' : '\\r';
      escaped = false;
      continue;
    }
    result += char;
    if (escaped) escaped = false;
    else if (char === '\\' && quoted) escaped = true;
    else if (char === '"') quoted = !quoted;
  }
  return JSON.parse(result);
}

function contextFromBody(body) {
  for (let start = body.indexOf('{'); start >= 0; start = body.indexOf('{', start + 1)) {
    if (start && !/\s/.test(body[start - 1])) continue;
    try {
      const context = jsonWithLineBreaks(body.slice(start));
      if (context && !Array.isArray(context) && typeof context === 'object') {
        return { message: body.slice(0, start).trim(), context };
      }
    } catch { /* Formato não estruturado: mantém o texto original abaixo. */ }
  }
  return { message: body.split('\n')[0], context: {} };
}

function traceFromText(text) {
  return text.split('\n').flatMap((line) => {
    const match = line.match(/^#(\d+)\s+(.*)$/);
    if (!match) return [];
    const frame = match[2].match(/^(.*?)(?:\((\d+)\)|:(\d+)):\s*(.*)$/);
    const internal = match[2].match(/^(\[internal function\]):\s*(.*)$/);
    const file = frame ? frame[1] : internal ? internal[1] : '';
    return [{ n: Number(match[1]), file, line: frame ? Number(frame[2] || frame[3]) : null,
      call: frame ? frame[4] : internal ? internal[2] : match[2], vendor: /[\\/]vendor[\\/]/.test(file) }];
  });
}

function parseEntry(entry) {
  const header = entry.raw.match(/^\[([^\]]+)\]\s+([^\s]+)\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):\s*([\s\S]*)$/i);
  const body = header ? header[4] : entry.raw;
  const { message, context } = contextFromBody(body);
  const exceptionData = context.exception;
  const exceptionText = typeof exceptionData === 'string' ? exceptionData : '';
  const prefix = message.match(/^([\w\\]*(?:Exception|Error)):\s*/);
  const exception = (exceptionData && typeof exceptionData === 'object' ? exceptionData.class : null)
    || exceptionText.match(/^\[object\]\s+\(([^\s(]+)/)?.[1] || prefix?.[1] || '';
  const location = (exceptionText || body).match(/(?:\bat\s+|\bin\s+)(\/[^\n]+?):(\d+)/);
  const contextOnly = { ...context };
  delete contextOnly.exception;
  const request = context.request && typeof context.request === 'object' ? context.request : null;
  return { ...entry, at: header ? header[1].replace(' ', 'T') : entry.at || '',
    env: header ? header[2] : '', level: header ? header[3].toLowerCase() : entry.level,
    message: message || body.split('\n')[0], context: contextOnly, exception: String(exception),
    file: (exceptionData && typeof exceptionData === 'object' ? exceptionData.file : '') || location?.[1] || '',
    line: (exceptionData && typeof exceptionData === 'object' ? exceptionData.line : null) || (location ? Number(location[2]) : null),
    trace: traceFromText(exceptionText || body),
    request: request && typeof request.path === 'string' ? request : null,
  };
}

export function parseLaravelLines(lines = []) {
  const entries = [];
  for (const row of lines) {
    const text = typeof row === 'string' ? row : String(row.texto ?? '');
    const startsEntry = /^\[[^\]]+\]\s+[^\s]+\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/i.test(text);
    if (startsEntry || !entries.length) {
      entries.push({ id: `log-${entries.length}`, raw: text, level: row.nivel || null, at: row.timestamp?.replace(' ', 'T') || '' });
    } else entries[entries.length - 1].raw += `\n${text}`;
  }
  return entries.map(parseEntry);
}

export function filterLogEntries(entries, level = 'todos', query = '') {
  const term = query.trim().toLocaleLowerCase('pt-BR');
  return entries.filter((entry) => (level === 'todos' || logLevel(entry.level) === level)
    && (!term || entry.raw.toLocaleLowerCase('pt-BR').includes(term)));
}

export function logSignature(entry) {
  // O contexto varia entre eventos. Mensagens e códigos diferentes não são fundidos.
  return JSON.stringify([entry.level, entry.exception, entry.message]);
}

export function groupLogEntries(entries) {
  const groups = new Map();
  entries.forEach((entry) => {
    const signature = logSignature(entry);
    if (!groups.has(signature)) groups.set(signature, { signature, latest: entry, first: entry.at, count: 0 });
    const group = groups.get(signature);
    group.count += 1;
    if (entry.at >= group.latest.at) group.latest = entry;
    if (entry.at && (!group.first || entry.at < group.first)) group.first = entry.at;
  });
  const rank = { error: 0, warning: 1, info: 2, debug: 3, other: 4 };
  return [...groups.values()].sort((a, b) => rank[logLevel(a.latest.level)] - rank[logLevel(b.latest.level)]
    || b.count - a.count || b.latest.at.localeCompare(a.latest.at));
}

export const rawLogText = (entries) => entries.map((entry) => entry.raw).join('\n');
