import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { DiaService } from '../../services/dia.service';
import { CheckinService } from '../../services/checkin.service';
import { Dia, Horario } from '../../models/api.models';

@Component({
  selector: 'app-checkin',
  standalone: true,
  imports: [
    CommonModule,
    MatSnackBarModule
  ],
  template: `
    <div class="space-y-8">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-emerald-500/5">
        <p class="text-xs uppercase tracking-[0.25em] text-emerald-300">Check-in</p>
        <h1 class="text-3xl font-bold text-slate-50">Escolha um dia e horário</h1>
        <p class="text-slate-400">Selecione um dia ativo, escolha um horário disponível e confirme seu check-in.</p>
      </div>

      <div *ngIf="loading" class="flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-6 text-slate-300">
        <svg class="h-5 w-5 animate-spin text-emerald-300" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 000 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
        </svg>
        Carregando opções...
      </div>

      <div *ngIf="!loading && !diaSelecionado" class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Passo 1</p>
            <h2 class="text-xl font-semibold text-slate-50">Selecione um dia</h2>
          </div>
          <button (click)="carregarDias()" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-emerald-400 hover:text-emerald-300">
            Atualizar
          </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <button *ngFor="let dia of dias" (click)="selecionarDia(dia)" class="group rounded-2xl border border-slate-800 bg-slate-950/60 p-5 text-left transition hover:border-emerald-400/60 hover:bg-slate-900">
            <p class="text-sm uppercase tracking-[0.2em] text-slate-500">{{ getDiaSemana(dia.data) }}</p>
            <p class="text-2xl font-semibold text-slate-50">{{ formatarData(dia.data) }}</p>
            <p class="mt-2 text-xs text-slate-400">Clique para ver horários</p>
          </button>
        </div>
      </div>

      <div *ngIf="!loading && diaSelecionado" class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Passo 2</p>
            <h2 class="text-xl font-semibold text-slate-50">Horários de {{ formatarData(diaSelecionado.data) }}</h2>
          </div>
          <button (click)="voltarParaDias()" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-rose-400 hover:text-rose-200">
            ← Voltar para dias
          </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <button
            *ngFor="let horario of horarios"
            [disabled]="horario.vagas_disponiveis === 0 || loading"
            (click)="realizarCheckin(horario)"
            class="group rounded-2xl border border-slate-800 bg-slate-950/60 p-5 text-left transition hover:border-emerald-400/60 hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm uppercase tracking-[0.2em] text-slate-500">Horário</p>
                <p class="text-2xl font-semibold text-slate-50">{{ horario.hora.substring(0, 5) }}</p>
              </div>
              <span [class]="badgeClass(horario.vagas_disponiveis)" class="rounded-full border px-3 py-1 text-xs font-semibold">
                {{ horario.vagas_disponiveis }} vagas
              </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">
              Janela {{ (horario.horario_inicio || horario.hora).substring(0,5) }} - {{ (horario.horario_fim || horario.hora).substring(0,5) }}
            </p>
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

  constructor(
    private diaService: DiaService,
    private checkinService: CheckinService,
    private snackBar: MatSnackBar
  ) {}

  ngOnInit(): void {
    this.carregarDias();
  }

  carregarDias(): void {
    this.loading = true;
    this.diaService.getDias().subscribe({
      next: (response) => {
        this.dias = response.dias;
        this.loading = false;
      },
      error: (error) => {
        this.loading = false;
        this.snackBar.open('Erro ao carregar dias', 'Fechar', { duration: 3000 });
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
        this.snackBar.open('Erro ao carregar horários', 'Fechar', { duration: 3000 });
      }
    });
  }

  voltarParaDias(): void {
    this.diaSelecionado = null;
    this.horarios = [];
  }

  realizarCheckin(horario: Horario): void {
    if (horario.vagas_disponiveis === 0) {
      this.snackBar.open('Não há vagas disponíveis para este horário', 'Fechar', { duration: 3000 });
      return;
    }

    this.loading = true;
    this.checkinService.realizarCheckin({ horario_id: horario.id }).subscribe({
      next: (response) => {
        this.loading = false;
        this.snackBar.open(response.message, 'Fechar', { duration: 3000 });
        this.voltarParaDias();
        this.carregarDias();
      },
      error: (error) => {
        this.loading = false;
        const message = error.error?.error || 'Erro ao realizar check-in';
        this.snackBar.open(message, 'Fechar', { duration: 5000 });
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

  badgeClass(vagas: number): string {
    if (vagas === 0) return 'border-rose-400/50 bg-rose-400/10 text-rose-200';
    if (vagas < 5) return 'border-amber-400/50 bg-amber-400/10 text-amber-100';
    return 'border-emerald-400/50 bg-emerald-400/10 text-emerald-200';
  }
}
