import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  DashboardAdminStats,
  AlunoAdmin,
  Plano,
  PlanoRequest,
  Horario,
  Dia,
  PlanejamentoHorario,
  PlanejamentoRequest,
  GerarHorariosRequest,
  GerarHorariosResponse,
  CheckinAdminRequest,
  CheckinAdminResponse
} from '../models/api.models';

@Injectable({
  providedIn: 'root'
})
export class AdminService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  // Dashboard
  getDashboardStats(): Observable<DashboardAdminStats> {
    return this.http.get<DashboardAdminStats>(`${this.apiUrl}/admin/dashboard`);
  }

  // Alunos
  listarAlunos(): Observable<{ alunos: AlunoAdmin[], total: number }> {
    return this.http.get<{ alunos: AlunoAdmin[], total: number }>(`${this.apiUrl}/admin/alunos`);
  }

  criarAluno(data: any): Observable<{ message: string, aluno: AlunoAdmin }> {
    return this.http.post<{ message: string, aluno: AlunoAdmin }>(`${this.apiUrl}/admin/alunos`, data);
  }

  atualizarAluno(id: number, data: any): Observable<{ message: string, aluno: AlunoAdmin }> {
    return this.http.put<{ message: string, aluno: AlunoAdmin }>(`${this.apiUrl}/admin/alunos/${id}`, data);
  }

  desativarAluno(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/admin/alunos/${id}`);
  }

  // Planos
  listarPlanos(apenasAtivos = false): Observable<{ planos: Plano[], total: number }> {
    const url = apenasAtivos ? `${this.apiUrl}/planos?ativos=true` : `${this.apiUrl}/planos`;
    return this.http.get<{ planos: Plano[], total: number }>(url);
  }

  buscarPlano(id: number): Observable<Plano> {
    return this.http.get<Plano>(`${this.apiUrl}/planos/${id}`);
  }

  criarPlano(data: PlanoRequest): Observable<{ message: string, plano: Plano }> {
    return this.http.post<{ message: string, plano: Plano }>(`${this.apiUrl}/admin/planos`, data);
  }

  atualizarPlano(id: number, data: Partial<PlanoRequest>): Observable<{ message: string, plano: Plano }> {
    return this.http.put<{ message: string, plano: Plano }>(`${this.apiUrl}/admin/planos/${id}`, data);
  }

  desativarPlano(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/admin/planos/${id}`);
  }

  // Planejamentos
  listarPlanejamentos(apenasAtivos = true): Observable<{ planejamentos: PlanejamentoHorario[], total: number }> {
    const url = apenasAtivos ? `${this.apiUrl}/admin/planejamentos?ativos=true` : `${this.apiUrl}/admin/planejamentos`;
    return this.http.get<{ planejamentos: PlanejamentoHorario[], total: number }>(url);
  }

  buscarPlanejamento(id: number): Observable<PlanejamentoHorario> {
    return this.http.get<PlanejamentoHorario>(`${this.apiUrl}/admin/planejamentos/${id}`);
  }

  criarPlanejamento(data: PlanejamentoRequest): Observable<{ message: string, id: number }> {
    return this.http.post<{ message: string, id: number }>(`${this.apiUrl}/admin/planejamentos`, data);
  }

  atualizarPlanejamento(id: number, data: Partial<PlanejamentoRequest>): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.apiUrl}/admin/planejamentos/${id}`, data);
  }

  desativarPlanejamento(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/admin/planejamentos/${id}`);
  }

  gerarHorarios(planejamentoId: number, data: GerarHorariosRequest): Observable<GerarHorariosResponse> {
    return this.http.post<GerarHorariosResponse>(`${this.apiUrl}/admin/planejamentos/${planejamentoId}/gerar-horarios`, data);
  }

  // Check-in Manual (Admin registra para aluno)
  registrarCheckinAluno(data: CheckinAdminRequest): Observable<CheckinAdminResponse> {
    return this.http.post<CheckinAdminResponse>(`${this.apiUrl}/admin/checkins/registrar`, data);
  }

  // Horários (removido - agora usa planejamentos)
  listarHorarios(): Observable<{ horarios: Horario[], total: number }> {
    return this.http.get<{ horarios: Horario[], total: number }>(`${this.apiUrl}/horarios`);
  }

  criarHorario(data: any): Observable<{ message: string, horario: Horario }> {
    return this.http.post<{ message: string, horario: Horario }>(`${this.apiUrl}/admin/horarios`, data);
  }

  atualizarHorario(id: number, data: any): Observable<{ message: string, horario: Horario }> {
    return this.http.put<{ message: string, horario: Horario }>(`${this.apiUrl}/admin/horarios/${id}`, data);
  }
}
