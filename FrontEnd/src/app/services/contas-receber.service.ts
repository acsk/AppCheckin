import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { 
  ContasReceberResponse, 
  DarBaixaRequest, 
  DarBaixaResponse,
  ContasReceberEstatisticas 
} from '../models/api.models';

@Injectable({
  providedIn: 'root'
})
export class ContasReceberService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/admin/contas-receber`;

  listarContas(params?: {
    status?: string;
    usuario_id?: number;
    mes_referencia?: string;
  }): Observable<ContasReceberResponse> {
    let httpParams = new HttpParams();
    
    if (params?.status) {
      httpParams = httpParams.set('status', params.status);
    }
    if (params?.usuario_id) {
      httpParams = httpParams.set('usuario_id', params.usuario_id.toString());
    }
    if (params?.mes_referencia) {
      httpParams = httpParams.set('mes_referencia', params.mes_referencia);
    }

    return this.http.get<ContasReceberResponse>(this.apiUrl, { params: httpParams });
  }

  darBaixa(contaId: number, dados: DarBaixaRequest): Observable<DarBaixaResponse> {
    return this.http.post<DarBaixaResponse>(`${this.apiUrl}/${contaId}/baixa`, dados);
  }

  cancelar(contaId: number, observacoes?: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(
      `${this.apiUrl}/${contaId}/cancelar`, 
      { observacoes }
    );
  }

  getEstatisticas(mesReferencia?: string): Observable<ContasReceberEstatisticas> {
    let params = new HttpParams();
    if (mesReferencia) {
      params = params.set('mes_referencia', mesReferencia);
    }
    return this.http.get<ContasReceberEstatisticas>(`${this.apiUrl}/estatisticas`, { params });
  }
}
