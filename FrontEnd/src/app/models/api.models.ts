export interface User {
  id: number;
  nome: string;
  email: string;
  role: 'aluno' | 'admin' | 'super_admin';
  role_id?: number | null;
  plano_id?: number | null;
  data_vencimento_plano?: string | null;
  foto_base64?: string | null;
  foto_url?: string | null;
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

export interface Plano {
  id: number;
  tenant_id: number;
  nome: string;
  descricao: string | null;
  valor: number;
  duracao_dias: number;
  checkins_mensais: number | null;
  ativo: boolean;
  created_at: string;
  updated_at: string;
}

export interface PlanoRequest {
  nome: string;
  descricao?: string;
  valor: number;
  duracao_dias: number;
  checkins_mensais?: number | null;
  ativo?: boolean;
}

export interface AlunoAdmin extends User {
  plano?: Plano | null;
  total_checkins?: number;
  ultimo_checkin?: string | null;
}

export interface DashboardAdminStats {
  total_alunos: number;
  alunos_ativos: number;
  alunos_inativos: number;
  novos_alunos_mes: number;
  total_checkins_hoje: number;
  total_checkins_mes: number;
  planos_vencendo: number;
  receita_mensal: number;
}

export interface Role {
  id: number;
  nome: string;
  descricao: string;
}

export interface PlanejamentoHorario {
  id: number;
  tenant_id: number;
  titulo: string;
  dia_semana: 'segunda' | 'terca' | 'quarta' | 'quinta' | 'sexta' | 'sabado' | 'domingo';
  horario_inicio: string;
  horario_fim: string;
  vagas: number;
  data_inicio: string;
  data_fim: string | null;
  ativo: boolean;
  created_at: string;
  updated_at: string;
}

export interface PlanejamentoRequest {
  titulo: string;
  dia_semana: 'segunda' | 'terca' | 'quarta' | 'quinta' | 'sexta' | 'sabado' | 'domingo';
  horario_inicio: string;
  horario_fim: string;
  vagas: number;
  data_inicio: string;
  data_fim?: string | null;
  ativo?: boolean;
}

export interface GerarHorariosRequest {
  data_inicio: string;
  data_fim: string;
}

export interface GerarHorariosResponse {
  message: string;
  resultado: {
    dias_gerados: number;
    horarios_gerados: number;
    detalhes: {
      dias: string[];
      horarios: string[];
    };
  };
}

export interface CheckinAdminRequest {
  usuario_id: number;
  horario_id: number;
}

export interface CheckinAdminResponse {
  message: string;
  checkin: Checkin & {
    registrado_por_admin: boolean;
    admin_id: number | null;
  };
}
