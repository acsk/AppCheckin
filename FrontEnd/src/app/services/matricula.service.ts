import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { 
  MatriculaRequest,
  MatriculaResponse,
  MatriculasListResponse,
  BaixaContaRequest,
  ContaReceber
} from '../models/api.models';

@Injectable({
  providedIn: 'root'
})
export class MatriculaService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/admin/matriculas`;

  criar(dados: MatriculaRequest): Observable<MatriculaResponse> {
    return this.http.post<MatriculaResponse>(this.apiUrl, dados);
  }

  listar(params?: {
    usuario_id?: number;
    status?: string;
  }): Observable<MatriculasListResponse> {
    let httpParams = new HttpParams();
    
    if (params?.usuario_id) {
      httpParams = httpParams.set('usuario_id', params.usuario_id.toString());
    }
    if (params?.status) {
      httpParams = httpParams.set('status', params.status);
    }

    return this.http.get<MatriculasListResponse>(this.apiUrl, { params: httpParams });
  }

  cancelar(matriculaId: number, motivoCancelamento?: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(
      `${this.apiUrl}/${matriculaId}/cancelar`,
      { motivo_cancelamento: motivoCancelamento }
    );
  }

  darBaixaConta(contaId: number, dados: BaixaContaRequest): Observable<{ message: string; conta: ContaReceber }> {
    return this.http.post<{ message: string; conta: ContaReceber }>(
      `${this.apiUrl}/contas/${contaId}/baixa`,
      dados
    );
  }
}
