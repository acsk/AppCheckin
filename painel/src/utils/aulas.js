export const aulaError = (error, fallback) => error?.response?.data?.message || error?.response?.data?.error || error?.message || fallback;
export const isEnabled = value => value === true || value === 1 || value === '1';
export const localISO = (date = new Date()) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
export const dateLabel = value => new Date(`${value}T12:00:00`).toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });
export const weekStart = value => {
  const date = new Date(`${value}T12:00:00`);
  date.setDate(date.getDate() - date.getDay());
  return localISO(date);
};

export function officialAula(row, date) {
  return {
    id: row.id, date: row.dia_data?.slice(0, 10) || row.data?.slice(0, 10) || date,
    modality: { id: String(row.modalidade_id), name: row.modalidade_nome || 'Modalidade', icon: row.modalidade_icone || 'dumbbell', color: row.modalidade_cor },
    teacherId: String(row.professor_id), teacher: row.professor_nome || 'Professor',
    start: row.horario_inicio?.slice(0, 5) || '', end: row.horario_fim?.slice(0, 5) || '',
    capacity: Number(row.limite_alunos), booked: Number(row.alunos_count ?? row.total_checkins ?? 0),
    active: isEnabled(row.ativo), checkinLocked: isEnabled(row.checkin_bloqueado),
    openBefore: row.tolerancia_antes_minutos ?? 480, lateAfter: row.tolerancia_minutos ?? 10,
    closeBefore: row.tolerancia_antes_checkin_minutos ?? '', cancelBefore: row.tolerancia_cancelamento_minutos ?? '',
    api: row,
  };
}

export function aulaPayload(values, modalities, teachers, diaId) {
  const modality = modalities.find(item => String(item.id) === String(values.modalityId));
  const teacher = teachers.find(item => String(item.id) === String(values.teacherId));
  if (!modality || !teacher) throw new Error('Selecione uma modalidade e um professor válidos.');
  const time = /^(?:[01]\d|2[0-3]):[0-5]\d$/;
  if (!time.test(values.start) || !time.test(values.end) || values.end <= values.start) throw new Error('Informe horários válidos, com término após o início.');
  if (!Number.isInteger(values.capacity) || values.capacity < 1) throw new Error('Informe uma quantidade válida de vagas.');
  for (const key of ['openBefore', 'lateAfter', 'closeBefore', 'cancelBefore']) {
    if (values[key] != null && (!Number.isInteger(values[key]) || values[key] < 0 || values[key] > 1440)) throw new Error('As regras de check-in devem ter entre 0 e 1440 minutos.');
  }
  if (values.closeBefore != null && values.closeBefore >= (values.openBefore ?? 0)) throw new Error('O fechamento precisa ser menor que a antecedência de abertura do check-in.');
  if (!values.id && !diaId) throw new Error('Cadastre o dia selecionado antes de criar uma aula.');
  return {
    nome: `${modality.nome} - ${values.start} - ${teacher.nome}`,
    modalidade_id: Number(values.modalityId), professor_id: Number(values.teacherId),
    ...(!values.id ? { dia_id: Number(diaId) } : {}),
    horario_inicio: `${values.start}:00`, horario_fim: `${values.end}:00`,
    limite_alunos: values.capacity, ativo: values.active ? 1 : 0,
    tolerancia_antes_minutos: values.openBefore ?? 0, tolerancia_minutos: values.lateAfter ?? 0,
    tolerancia_antes_checkin_minutos: values.closeBefore, tolerancia_cancelamento_minutos: values.cancelBefore,
  };
}

export function replicationPayload({ mode, modalityId, month, weekdays, sourceWeek, months }, diaId) {
  if (!modalityId) throw new Error('Selecione a modalidade.');
  if (mode === 'semana') {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(sourceWeek) || !months.length) throw new Error('Escolha a semana modelo e pelo menos um mês de destino.');
    return { semana_data: weekStart(sourceWeek), meses_destino: months, modalidade_id: Number(modalityId) };
  }
  if (!diaId) throw new Error('Não foi possível identificar o dia de origem.');
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month) || !weekdays.length) throw new Error('Escolha o mês e pelo menos um dia da semana.');
  return { dia_id: Number(diaId), periodo: 'custom', mes: month, dias_semana: weekdays.map(day => day + 1), modalidade_id: Number(modalityId) };
}
