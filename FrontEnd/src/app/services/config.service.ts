import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { FormaPagamento, StatusConta } from '../models/api.models';

@Injectable({
  providedIn: 'root'
})
export class ConfigService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/config`;

  listarFormasPagamento(): Observable<FormaPagamento[]> {
    return this.http.get<FormaPagamento[]>(`${this.apiUrl}/formas-pagamento`);
  }

  listarStatusConta(): Observable<StatusConta[]> {
    return this.http.get<StatusConta[]>(`${this.apiUrl}/status-conta`);
  }
}
