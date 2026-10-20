export const RECORD_CATEGORIES = [
  { value: 'movimento', label: 'Movimento' },
  { value: 'prova', label: 'Prova' },
  { value: 'workout', label: 'Workout' },
  { value: 'teste_fisico', label: 'Teste físico' },
];
export const METRIC_TYPES = [
  { value: 'decimal', label: 'Decimal' },
  { value: 'inteiro', label: 'Inteiro' },
  { value: 'tempo_ms', label: 'Tempo (milissegundos)' },
];
export const METRIC_DIRECTIONS = [
  { value: 'maior_melhor', label: '↑ Maior é melhor' },
  { value: 'menor_melhor', label: '↓ Menor é melhor' },
];
export const recordActive = (value) => value === true || value === 1 || value === '1';
export const normalizeRecordText = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
export const emptyRecordMetric = () => ({ codigo: '', nome: '', tipo_valor: 'decimal', unidade: '', direcao: 'maior_melhor', obrigatoria: 1 });
export const emptyRecordDefinition = () => ({ nome: '', descricao: '', modalidade_id: '', categoria: 'movimento', ordem: '10', ativo: true, metricas: [emptyRecordMetric()] });

export function definitionFromApi(definition) {
  return {
    ...definition,
    nome: definition.nome || '', descricao: definition.descricao || '',
    modalidade_id: definition.modalidade_id == null ? '' : String(definition.modalidade_id),
    categoria: definition.categoria || 'movimento', ordem: String(definition.ordem ?? 10),
    ativo: recordActive(definition.ativo),
    metricas: [...(definition.metricas || [])].sort((a, b) => Number(a.ordem_comparacao) - Number(b.ordem_comparacao)).map((metric) => ({ ...metric, codigo: metric.codigo || '', nome: metric.nome || '', tipo_valor: metric.tipo_valor || 'decimal', direcao: metric.direcao || 'maior_melhor', unidade: metric.unidade || '', obrigatoria: recordActive(metric.obrigatoria ?? 1) ? 1 : 0 })),
  };
}

export function filterDefinitions(rows, { query = '', category = '', modality = '', status = 'all' } = {}) {
  const term = normalizeRecordText(query);
  return rows.filter((row) => (!category || row.categoria === category)
    && (!modality || (modality === 'none' ? !row.modalidade_id : String(row.modalidade_id) === modality))
    && (status === 'all' || recordActive(row.ativo) === (status === 'active'))
    && (!term || normalizeRecordText([row.nome, row.descricao, row.modalidade_nome, ...(row.metricas || []).flatMap((metric) => [metric.nome, metric.codigo, metric.unidade])].join(' ')).includes(term)));
}

export function definitionErrors(form) {
  const errors = {};
  if (!form.nome.trim()) errors.nome = 'Informe o nome da definição.';
  if (!RECORD_CATEGORIES.some((category) => category.value === form.categoria)) errors.categoria = 'Escolha uma categoria.';
  if (form.ordem === '' || !Number.isInteger(Number(form.ordem)) || Number(form.ordem) < 0) errors.ordem = 'Informe uma ordem inteira maior ou igual a zero.';
  if (!form.metricas.length) errors.metricas = 'Adicione pelo menos uma métrica.';
  const codes = new Map();
  form.metricas.forEach((metric, index) => {
    if (!metric.nome?.trim()) errors[`metric_${index}_nome`] = 'Informe o nome da métrica.';
    const code = metric.codigo?.trim();
    if (!code) errors[`metric_${index}_codigo`] = 'Informe o código da métrica.';
    else if (codes.has(code)) {
      errors[`metric_${index}_codigo`] = 'Use um código diferente para cada métrica.';
      errors[`metric_${codes.get(code)}_codigo`] = 'Use um código diferente para cada métrica.';
    } else codes.set(code, index);
    if (!METRIC_TYPES.some((type) => type.value === metric.tipo_valor)) errors[`metric_${index}_tipo_valor`] = 'Escolha o tipo de valor.';
    if (!METRIC_DIRECTIONS.some((direction) => direction.value === metric.direcao)) errors[`metric_${index}_direcao`] = 'Escolha a direção de comparação.';
  });
  return errors;
}

export function definitionPayload(form, editing = false) {
  return {
    nome: form.nome.trim(), categoria: form.categoria,
    modalidade_id: form.modalidade_id ? Number(form.modalidade_id) : null,
    descricao: form.descricao.trim() || null, ordem: Number(form.ordem),
    ...(editing ? { ativo: recordActive(form.ativo) ? 1 : 0 } : {}),
    metricas: form.metricas.map((metric, index) => ({
      ...(metric.id ? { id: metric.id } : {}), codigo: metric.codigo.trim(), nome: metric.nome.trim(),
      tipo_valor: metric.tipo_valor, unidade: metric.unidade.trim() || null,
      ordem_comparacao: index + 1, direcao: metric.direcao, obrigatoria: recordActive(metric.obrigatoria) ? 1 : 0,
    })),
  };
}
