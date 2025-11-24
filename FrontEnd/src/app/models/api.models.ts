export interface User {
  id: number;
  nome: string;
  email: string;
  foto_base64?: string | null;
  created_at: string;
  updated_at: string;
}

export interface LoginRequest {
  email: string;
  senha: string;
}

export interface RegisterRequest {
  nome: string;
  email: string;
  senha: string;
}

export interface AuthResponse {
  message: string;
  token: string;
  user: User;
}

export interface Dia {
  id: number;
  data: string;
  ativo: boolean;
  created_at: string;
  updated_at: string;
}

export interface Horario {
  id: number;
  dia_id: number;
  hora: string;
  horario_inicio?: string;
  horario_fim?: string;
  vagas: number;
  ativo: boolean;
  checkins_count: number;
  vagas_disponiveis: number;
  created_at: string;
  updated_at: string;
}

export interface Checkin {
  id: number;
  usuario_id: number;
  horario_id: number;
  data_checkin: string;
  hora: string;
  data: string;
  data_hora_completa: string;
  created_at: string;
  updated_at: string;
}

export interface CheckinRequest {
  horario_id: number;
}

export interface Turma {
  id: number;
  data?: string;
  hora: string;
  horario_inicio: string;
  horario_fim: string;
  limite_alunos: number;
  alunos_registrados: number;
  vagas_disponiveis: number;
  percentual_ocupacao?: number;
  usuario_registrado?: boolean;
  ativo: boolean;
}

export interface TurmaDia {
  dia: Dia;
  turmas: Turma[];
}

export interface TurmasResponse {
  turmas_por_dia: TurmaDia[];
  total_turmas: number;
}

export interface AlunoTurma {
  id: number;
  usuario_id?: number;
  checkin_id?: number;
  nome: string;
  email: string;
  data_checkin: string;
  created_at: string;
}

export interface TurmaAlunosResponse {
  turma: Turma & { data: string };
  alunos: AlunoTurma[];
  total_alunos: number;
}

export interface TurmasHojeResponse {
  data: string;
  turmas: Turma[];
}

export interface UsuarioEstatisticas {
  id: number;
  nome: string;
  email: string;
  foto_url: string | null;
  total_checkins: number;
  total_prs: number;
  created_at: string;
  updated_at: string;
}
