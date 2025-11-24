import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { CheckinService } from '../../services/checkin.service';
import { Checkin } from '../../models/api.models';

@Component({
  selector: 'app-historico',
  standalone: true,
  imports: [
    CommonModule,
    MatSnackBarModule
  ],
  template: `
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Histórico</p>
          <h1 class="text-3xl font-bold text-slate-50">Check-ins realizados</h1>
          <p class="text-slate-400">Veja suas presenças e cancele se necessário.</p>
        </div>
        <button (click)="carregarCheckins()" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-emerald-400 hover:text-emerald-300" [disabled]="loading">
          {{ loading ? 'Atualizando...' : 'Recarregar' }}
        </button>
      </div>

      <div *ngIf="loading" class="rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-6 text-slate-300">
        Carregando check-ins...
      </div>

      <div *ngIf="!loading && checkins.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900/60 px-6 py-10 text-center text-slate-400">
        Você ainda não tem check-ins realizados.
      </div>

      <div *ngIf="!loading && checkins.length > 0" class="grid gap-4">
        <div *ngFor="let checkin of checkins" class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 shadow-lg shadow-emerald-500/5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm uppercase tracking-[0.2em] text-slate-500">Data</p>
              <p class="text-xl font-semibold text-slate-50">{{ formatarData(checkin.data) }}</p>
            </div>
            <div>
              <p class="text-sm uppercase tracking-[0.2em] text-slate-500">Horário</p>
              <p class="text-xl font-semibold text-emerald-300">{{ checkin.hora.substring(0, 5) }}</p>
            </div>
            <span class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
              Confirmado
            </span>
            <button (click)="cancelarCheckin(checkin)" class="rounded-lg border border-rose-400/40 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-400/10">
              Cancelar
            </button>
          </div>
          <p class="mt-2 text-xs text-slate-500">Registrado em {{ formatarDataHora(checkin.data_checkin || checkin.data_hora_completa || checkin.created_at) }}</p>
        </div>
      </div>
    </div>
  `
})
export class HistoricoComponent implements OnInit {
  checkins: Checkin[] = [];
  loading = false;

  constructor(
    private checkinService: CheckinService,
    private snackBar: MatSnackBar
  ) {}

  ngOnInit(): void {
    this.carregarCheckins();
  }

  carregarCheckins(): void {
    this.loading = true;
    this.checkinService.getMeusCheckins().subscribe({
      next: (response) => {
        this.checkins = response.checkins;
        this.loading = false;
      },
      error: (error) => {
        this.loading = false;
        this.snackBar.open('Erro ao carregar check-ins', 'Fechar', { duration: 3000 });
      }
    });
  }

  cancelarCheckin(checkin: Checkin): void {
    if (confirm(`Deseja realmente cancelar o check-in do dia ${this.formatarData(checkin.data)} às ${checkin.hora.substring(0, 5)}?`)) {
      this.checkinService.cancelarCheckin(checkin.id).subscribe({
        next: (response) => {
          this.snackBar.open(response.message, 'Fechar', { duration: 3000 });
          this.carregarCheckins();
        },
        error: (error) => {
          const message = error.error?.error || 'Erro ao cancelar check-in';
          this.snackBar.open(message, 'Fechar', { duration: 5000 });
        }
      });
    }
  }

  formatarData(data: string): string {
    const date = new Date(data + 'T00:00:00');
    return date.toLocaleDateString('pt-BR');
  }

  formatarDataHora(data: string): string {
    const normalized = data?.includes('T') ? data : data?.replace(' ', 'T');
    const date = normalized ? new Date(normalized) : null;
    return date ? date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
  }
}
