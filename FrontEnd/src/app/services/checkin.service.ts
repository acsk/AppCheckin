import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Checkin, CheckinRequest } from '../models/api.models';
import { AuthService } from './auth.service';

@Injectable({
  providedIn: 'root'
})
export class CheckinService {
  private apiUrl = environment.apiUrl;

  constructor(
    private http: HttpClient,
    private authService: AuthService
  ) {}

  realizarCheckin(data: CheckinRequest): Observable<{ message: string; checkin: Checkin }> {
    const token = this.authService.token;
    const headers = token ? new HttpHeaders({ Authorization: `Bearer ${token}` }) : undefined;
    return this.http.post<{ message: string; checkin: Checkin }>(`${this.apiUrl}/checkin`, data, { headers });
  }

  getMeusCheckins(): Observable<{ checkins: Checkin[] }> {
    const token = this.authService.token;
    const headers = token ? new HttpHeaders({ Authorization: `Bearer ${token}` }) : undefined;
    return this.http.get<{ checkins: Checkin[] }>(`${this.apiUrl}/me/checkins`, { headers });
  }

  cancelarCheckin(checkinId: number): Observable<{ message: string }> {
    const token = this.authService.token;
    const headers = token ? new HttpHeaders({ Authorization: `Bearer ${token}` }) : undefined;
    return this.http.delete<{ message: string }>(`${this.apiUrl}/checkin/${checkinId}`, { headers });
  }
}
