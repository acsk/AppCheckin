
  var RC_CAT = { prova: ['Prova', 'timer'], movimento: ['Movimento', 'dumbbell'], workout: ['Workout', 'flame'], teste: ['Teste físico', 'activity'] };
  var RC_TYPES = {
    tempo: { label: 'Tempo', unit: 'tempo', better: 'menor', icon: 'timer', hint: 'Minutos, segundos e centésimos' },
    carga: { label: 'Carga', unit: 'kg', better: 'maior', icon: 'dumbbell', hint: 'Peso levantado, em kg' },
    reps: { label: 'Repetições', unit: 'reps', better: 'maior', icon: 'repeat', hint: 'Quantas vezes, número inteiro' },
    distancia: { label: 'Distância', unit: 'm', better: 'maior', icon: 'footprints', hint: 'Em metros' },
    numero: { label: 'Outro número', unit: '', better: 'maior', icon: 'activity', hint: 'Pontos, rounds, calorias' } };
  function fmtNum(v, dec) { return Number(v).toLocaleString('pt-BR', { minimumFractionDigits: dec || 0, maximumFractionDigits: dec == null ? 2 : dec }); }
  function fmtTime(ms, sign) {
    if (ms == null || isNaN(ms)) return '—';
    var neg = ms < 0; ms = Math.abs(ms);
    var cs = Math.round(ms / 10), h2 = Math.floor(cs / 360000), m = Math.floor(cs % 360000 / 6000), s = Math.floor(cs % 6000 / 100), c = cs % 100;
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var t = h2 ? h2 + ':' + pad(m) + ':' + pad(s) : m ? m + ':' + pad(s) + ',' + pad(c) : s + ',' + pad(c) + ' s';
    return (sign ? (neg ? '−' : '+') : neg ? '−' : '') + t;
  }
  function parseTime(value) {
    const text = String(value || '').trim().replace(/\s*s$/, '').replace(',', '.');
    if (!/^\d+(?::\d{1,2}){0,2}(?:\.\d{1,3})?$/.test(text)) return NaN;
    const parts = text.split(':').map(Number);
    if (parts.slice(1).some(n => n >= 60)) return NaN;
    return Math.round(parts.reduce((sum,n) => sum * 60 + n, 0) * 1000);
  }
  function fmtMetric(v, m, sign) {
    if (v == null) return '—';
    if (!m || m.type === 'tempo') return fmtTime(v, sign);
    var pre = sign ? (v > 0 ? '+' : v < 0 ? '−' : '') : '';
    var a = sign ? Math.abs(v) : v;
    if (m.type === 'carga') return pre + fmtNum(a, a % 1 ? 1 : 0) + ' kg';
    if (m.type === 'reps') return pre + fmtNum(a, 0) + (Math.abs(a) === 1 ? ' rep' : ' reps');
    if (m.type === 'distancia') return pre + fmtNum(a) + ' m';
    return pre + fmtNum(a) + (m.unit ? ' ' + m.unit : '');
  }
  function betterOf(m) { return (m && m.better) || (m && m.type === 'tempo' ? 'menor' : 'maior'); }
  function isBetter(a, b, m) { return betterOf(m) === 'menor' ? a < b : a > b; }
  function rankRecords(records, def) {
    var best = Object.create(null);
    records.filter(function (r) { return r.defId === def.id && r.value != null && Number.isFinite(r.value); }).forEach(function (r) {
      var k = r.student ? 's' + r.student.id : 'academia';
      if (!best[k] || isBetter(r.value, best[k].value, def.metric) || (r.value === best[k].value && r.date < best[k].date)) best[k] = r;
    });
    return Object.keys(best).map(function (k) { return best[k]; }).sort(function (a, b) { return a.value === b.value ? (a.date.localeCompare(b.date) || Number(a.id) - Number(b.id)) : (isBetter(a.value, b.value, def.metric) ? -1 : 1); });
  }
  function metricLabel(m) { var t = RC_TYPES[m.type] || RC_TYPES.numero; return (m.name || t.label) + ', ' + (betterOf(m) === 'menor' ? 'menor é melhor' : 'maior é melhor'); }


export { RC_CAT, RC_TYPES, fmtMetric, fmtTime, parseTime, betterOf, isBetter, rankRecords, metricLabel };

// Adaptadores: mantêm IDs, unidades e todas as métricas usadas pela API.
const activeValue = v => v === true || v === 1 || v === '1';
export function metricFromApi(m) {
  const unit = String(m.unidade || '');
  const type = m.tipo_valor === 'tempo_ms' ? 'tempo' : /^(kg|quilogramas?)$/i.test(unit) ? 'carga'
    : m.tipo_valor === 'inteiro' && /rep/i.test(`${unit} ${m.nome}`) ? 'reps' : unit === 'm' ? 'distancia' : 'numero';
  return { type, name: m.nome, unit, better: m.direcao === 'menor_melhor' ? 'menor' : 'maior', code: m.codigo, api: m };
}
export function officialDefinition(d) {
  const metrics = [...(d.metricas || [])].sort((a,b) => Number(a.ordem_comparacao) - Number(b.ordem_comparacao));
  return { id: Number(d.id), name: d.nome, category: d.categoria === 'teste_fisico' ? 'teste' : d.categoria,
    modality: d.modalidade_nome || '', modalityId: d.modalidade_id, description: d.descricao || '', active: activeValue(d.ativo),
    metric: metrics[0] ? metricFromApi(metrics[0]) : {type:'numero',name:'Sem métrica',unit:'',better:'maior'},
    extraMetrics: metrics.slice(1), metrics, count:d.count, api: d };
}
export function officialRecord(r, definitions) {
  const def = definitions.find(d => Number(d.id) === Number(r.definicao_id));
  const values = r.valores || [];
  const first = values.find(v => Number(v.metrica_id) === Number(def?.metrics[0]?.id));
  const raw = first ? first.valor_tempo_ms ?? first.valor_decimal ?? first.valor_int : null;
  return { id:Number(r.id),defId:Number(r.definicao_id),student:r.aluno_id ? {id:Number(r.aluno_id),name:r.aluno_nome || `Aluno #${r.aluno_id}`} : null,
    value:raw == null ? null : Number(raw),date:String(r.data_recorde || '').slice(0,10),origin:r.origem,notes:r.observacoes || '',values,api:r };
}
export function definitionStats(definitions, records, available = true) {
  return definitions.map(d => { const list = records.filter(r => r.defId === d.id); const leader = rankRecords(list,d)[0];
    return {...d,count:available?list.length:null,top:leader?{value:leader.value,who:leader.student?.name || 'Marca da academia'}:null}; });
}
export function parseMark(value, metric) {
  const str = String(value ?? '').trim();
  if (!str) return null;
  if (metric.tipo_valor === 'tempo_ms') return parseTime(str);
  if (!/^\d+(?:[.,]\d+)?$/.test(str)) return NaN;
  const parsed = Number(str.replace(',','.'));
  return metric.tipo_valor === 'inteiro' && !Number.isInteger(parsed) ? NaN : parsed;
}
export function timeInput(ms) {
  if (ms == null) return '';
  const seconds = Number(ms) / 1000;
  const minutes = Math.floor(seconds / 60);
  return `${minutes}:${(seconds % 60).toFixed(3).padStart(6,'0')}`;
}
export function localToday() { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
