import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Dia, Horario, TurmaDia } from '../models/api.models';

@Injectable({
  providedIn: 'root'
})
export class DiaService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getDias(): Observable<{ dias: Dia[] }> {
    return this.http.get<{ dias: Dia[] }>(`${this.apiUrl}/dias`);
  }

  /**
   * Retorna 5 dias ao redor de uma data específica (2 antes, atual, 2 depois)
   */
  getDiasProximos(dataReferencia?: string): Observable<{ dias: Dia[]; total: number }> {
    const url = dataReferencia 
      ? `${this.apiUrl}/dias/proximos?data=${dataReferencia}`
      : `${this.apiUrl}/dias/proximos`;
    return this.http.get<{ dias: Dia[]; total: number }>(url);
  }

  getHorarios(diaId: number): Observable<{ dia: Dia; horarios: Horario[] }> {
    return this.http.get<{ dia: Dia; horarios: Horario[] }>(`${this.apiUrl}/dias/${diaId}/horarios`);
  }

  /**
   * Retorna os horários de um dia específico pela data
   */
  getHorariosPorData(data: string): Observable<TurmaDia> {
    return this.http.get<TurmaDia>(`${this.apiUrl}/dias/horarios`, { params: { data } });
  }
}
