import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { TurmasResponse, TurmaAlunosResponse, TurmasHojeResponse } from '../models/api.models';

@Injectable({
  providedIn: 'root'
})
export class TurmaService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getTurmas(): Observable<TurmasResponse> {
    return this.http.get<TurmasResponse>(`${this.apiUrl}/turmas`);
  }

  getTurmasHoje(): Observable<TurmasHojeResponse> {
    return this.http.get<TurmasHojeResponse>(`${this.apiUrl}/turmas/hoje`);
  }

  getAlunos(turmaId: number): Observable<TurmaAlunosResponse> {
    return this.http.get<TurmaAlunosResponse>(`${this.apiUrl}/turmas/${turmaId}/alunos`);
  }
}
