import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { User, UsuarioEstatisticas } from '../models/api.models';

@Injectable({
  providedIn: 'root'
})
export class UserService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getMe(): Observable<User> {
    return this.http.get<User>(`${this.apiUrl}/me`);
  }

  updateMe(data: Partial<User>): Observable<{ message: string; user: User }> {
    return this.http.put<{ message: string; user: User }>(`${this.apiUrl}/me`, data);
  }

  getEstatisticas(usuarioId: number): Observable<UsuarioEstatisticas> {
    return this.http.get<UsuarioEstatisticas>(`${this.apiUrl}/usuarios/${usuarioId}/estatisticas`);
  }
}
