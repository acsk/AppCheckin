import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Dia, Horario } from '../models/api.models';

@Injectable({
  providedIn: 'root'
})
export class DiaService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getDias(): Observable<{ dias: Dia[] }> {
    return this.http.get<{ dias: Dia[] }>(`${this.apiUrl}/dias`);
  }

  getHorarios(diaId: number): Observable<{ dia: Dia; horarios: Horario[] }> {
    return this.http.get<{ dia: Dia; horarios: Horario[] }>(`${this.apiUrl}/dias/${diaId}/horarios`);
  }
}
