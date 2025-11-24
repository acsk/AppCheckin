import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { DiaService } from '../../services/dia.service';
import { CheckinService } from '../../services/checkin.service';
import { Dia, Horario } from '../../models/api.models';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-checkin',
  standalone: true,
  imports: [
    CommonModule,
    IonicModule
  ],
  template: `
    <div class="space-y-4">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4 shadow-xl shadow-emerald-500/5">
        <p class="text-lg font-semibold text-slate-100">Check-in</p>
        <p class="text-sm text-slate-400">Escolha o dia e confirme seu horário.</p>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/80">
        <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3 text-slate-200">
          <p class="text-sm font-semibold">Dias</p>
          <button (click)="carregarDias()" class="text-xs text-emerald-300">Atualizar</button>
        </div>
        <div class="flex items-center justify-between gap-2 overflow-x-auto px-3 py-2">
          <button (click)="navegarDias('prev')" class="rounded-full border border-slate-800 bg-slate-900 px-2 py-2 text-xs text-slate-200">‹</button>
          <button
            *ngFor="let dia of dias"
            (click)="selecionarDia(dia)"
            class="min-w-[70px] rounded-xl px-3 py-2 text-center text-xs font-semibold transition"
            [ngClass]="diaSelecionado?.id === dia.id
              ? 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/50'
              : 'bg-slate-900 text-slate-300 border border-slate-800'"
          >
            <div class="text-[11px] uppercase tracking-wide">{{ getDiaSemanaCurto(dia.data) }}</div>
            <div class="text-base font-bold">{{ formatarDiaNumero(dia.data) }}</div>
          </button>
          <button (click)="navegarDias('next')" class="rounded-full border border-slate-800 bg-slate-900 px-2 py-2 text-xs text-slate-200">›</button>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-950/80">
        <div class="border-b border-slate-800 px-4 py-3">
          <p class="text-sm font-semibold text-slate-200">Horários</p>
          <p class="text-xs text-slate-400">{{ diaSelecionado ? formatarData(diaSelecionado.data) : 'Escolha um dia acima' }}</p>
        </div>

        <div *ngIf="loading" class="px-4 py-4 text-slate-300">Carregando opções...</div>
        <div *ngIf="!loading && horarios.length === 0" class="px-4 py-4 text-slate-400">Nenhum horário disponível.</div>

        <div *ngIf="!loading && horarios.length > 0" class="divide-y divide-slate-800">
          <button
            *ngFor="let horario of horarios"
            [disabled]="horario.vagas_disponiveis === 0 || loading"
            (click)="realizarCheckin(horario)"
            class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <div class="text-2xl font-bold text-slate-100 w-16">{{ horario.hora.substring(0,5) }}</div>
            <div class="flex-1">
              <p class="text-sm font-semibold text-slate-200">Aula</p>
              <p class="text-xs text-slate-400">Janela {{ (horario.horario_inicio || horario.hora).substring(0,5) }} - {{ (horario.horario_fim || horario.hora).substring(0,5) }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold text-slate-50">{{ horario.vagas_disponiveis }}/{{ horario.vagas }}</p>
              <p class="text-[11px] text-slate-400">vagas</p>
            </div>
          </button>
        </div>
      </div>
    </div>
  `
})
export class CheckinComponent implements OnInit {
  dias: Dia[] = [];
  horarios: Horario[] = [];
  diaSelecionado: Dia | null = null;
  loading = false;
  anchorDate: string | null = null;

  constructor(
    private diaService: DiaService,
    private checkinService: CheckinService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.carregarDias();
  }

  carregarDias(dataReferencia?: string): void {
    this.loading = true;
    this.diaService.getDiasProximos(dataReferencia || undefined).subscribe({
      next: (response) => {
        this.dias = response.dias;
        this.anchorDate = dataReferencia || this.dias[0]?.data || null;
        this.loading = false;
      },
      error: (error) => {
        this.loading = false;
        this.toast.show('Erro ao carregar dias', 'danger');
      }
    });
  }

  selecionarDia(dia: Dia): void {
    this.diaSelecionado = dia;
    this.loading = true;
    this.diaService.getHorarios(dia.id).subscribe({
      next: (response) => {
        this.horarios = response.horarios;
        this.loading = false;
      },
      error: (error) => {
        this.loading = false;
        this.toast.show('Erro ao carregar horários', 'danger');
      }
    });
  }

  voltarParaDias(): void {
    this.diaSelecionado = null;
    this.horarios = [];
  }

  navegarDias(direcao: 'prev' | 'next'): void {
    if (!this.dias.length) {
      this.carregarDias();
      return;
    }
    const base = direcao === 'prev' ? this.dias[0].data : this.dias[this.dias.length - 1].data;
    const refDate = this.ajustarData(base, direcao === 'prev' ? -1 : 1);
    this.carregarDias(refDate);
  }

  private ajustarData(data: string, deltaDias: number): string {
    const d = new Date(data + 'T00:00:00');
    d.setDate(d.getDate() + deltaDias);
    return d.toISOString().split('T')[0];
  }

  realizarCheckin(horario: Horario): void {
    if (horario.vagas_disponiveis === 0) {
      this.toast.show('Não há vagas disponíveis para este horário', 'warning');
      return;
    }

    this.loading = true;
    this.checkinService.realizarCheckin({ horario_id: horario.id }).subscribe({
      next: (response) => {
        this.loading = false;
        this.toast.show(response.message, 'success');
        this.voltarParaDias();
        this.carregarDias();
      },
      error: (error) => {
        this.loading = false;
        const message = error.error?.error || 'Erro ao realizar check-in';
        this.toast.show(message, 'danger', 5000);
      }
    });
  }

  formatarData(data: string): string {
    const date = new Date(data + 'T00:00:00');
    return date.toLocaleDateString('pt-BR');
  }

  getDiaSemana(data: string): string {
    const date = new Date(data + 'T00:00:00');
    const dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    return dias[date.getDay()];
  }

  getDiaSemanaCurto(data: string): string {
    const date = new Date(data + 'T00:00:00');
    const dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return dias[date.getDay()];
  }

  formatarDiaNumero(data: string): string {
    const d = new Date(data + 'T00:00:00');
    return d.getDate().toString().padStart(2, '0');
  }

  badgeClass(vagas: number): string {
    if (vagas === 0) return 'border-rose-400/50 bg-rose-400/10 text-rose-200';
    if (vagas < 5) return 'border-amber-400/50 bg-amber-400/10 text-amber-100';
    return 'border-emerald-400/50 bg-emerald-400/10 text-emerald-200';
  }
}
