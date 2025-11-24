import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { TurmaService } from '../../services/turma.service';
import { CheckinService } from '../../services/checkin.service';
import { TurmaDia, Turma, AlunoTurma } from '../../models/api.models';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink
  ],
  template: `
    <div class="space-y-8">
      <div *ngIf="loadingTurmas" class="flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-6 text-slate-300">
        <svg class="h-5 w-5 animate-spin text-emerald-300" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 000 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
        </svg>
        Carregando turmas...
      </div>

      <div *ngIf="!loadingTurmas && turmasPorDia.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900/60 px-6 py-10 text-center text-slate-400">
        Nenhuma turma disponível no momento.
      </div>

      <div class="grid gap-6" *ngIf="!loadingTurmas && turmasPorDia.length > 0">
        <div *ngFor="let dia of turmasPorDia" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Data</p>
              <p class="text-xl font-semibold text-slate-50">{{ formatarData(dia.data) }}</p>
            </div>
            <span class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
              {{ dia.dia_ativo ? 'Dia ativo' : 'Inativo' }}
            </span>
          </div>

          <div class="space-y-3">
            <div
              *ngFor="let turma of dia.turmas"
              (click)="selecionarTurma(turma, dia.data)"
              class="group cursor-pointer rounded-xl border p-4 transition"
              [ngClass]="turma.usuario_registrado
                ? 'border-emerald-400/80 bg-emerald-500/10 hover:bg-emerald-500/15 hover:border-emerald-300/80 shadow-[0_8px_30px_rgba(16,185,129,0.18)]'
                : 'border-slate-800/70 bg-slate-950/60 hover:border-emerald-400/60 hover:bg-slate-900'"
            >
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm uppercase tracking-[0.15em] text-slate-500">Horário</p>
                  <p class="text-lg font-semibold text-slate-50">{{ turma.hora.substring(0,5) }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <span *ngIf="turma.usuario_registrado" class="rounded-full border border-emerald-400/50 bg-emerald-400/10 px-3 py-1 text-[11px] font-semibold text-emerald-200">
                    Você já está nesta turma
                  </span>
                  <p class="text-sm font-semibold text-emerald-300">{{ turma.alunos_registrados }}/{{ turma.limite_alunos }} alunos</p>
                </div>
              </div>

              <div class="mt-3 flex items-center gap-3">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-800">
                  <div
                    class="h-full rounded-full transition-all"
                    [ngClass]="turma.usuario_registrado ? 'bg-gradient-to-r from-emerald-300 via-emerald-400 to-cyan-300' : 'bg-gradient-to-r from-emerald-400 to-cyan-400'"
                    [style.width.%]="turma.percentual_ocupacao || 0"
                  ></div>
                </div>
                <span class="text-xs font-semibold text-slate-200">{{ turma.percentual_ocupacao?.toFixed(0) }}%</span>
              </div>

              <div class="mt-3 flex items-center justify-between text-xs text-slate-400">
                <span class="rounded-full border border-slate-800 px-3 py-1">Vagas: {{ turma.vagas_disponiveis }}</span>
                <span class="rounded-full border border-slate-800 px-3 py-1">Início {{ turma.horario_inicio.substring(0,5) }}</span>
                <span class="rounded-full border border-slate-800 px-3 py-1">Fim {{ turma.horario_fim.substring(0,5) }}</span>
              </div>
              <div class="mt-3 flex justify-end">
                <a [routerLink]="['/turmas', turma.id]" [queryParams]="{ data: dia.data, hora: turma.hora }"
                   class="rounded-lg border border-emerald-400/50 px-4 py-2 text-xs font-semibold text-emerald-200 transition hover:bg-emerald-400/10">
                  Ver
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <section *ngIf="selectedTurma" class="grid gap-6 md:grid-cols-3">
        <div class="md:col-span-1 rounded-2xl border border-emerald-500/40 bg-emerald-500/5 p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs uppercase tracking-[0.2em] text-emerald-300">Turma selecionada</p>
                <p class="text-xl font-semibold text-slate-50">{{ selectedTurma!.hora.substring(0,5) }}</p>
                <p class="text-sm text-slate-400">{{ formatarData(selectedTurma!.data || '') }}</p>
              </div>
            <div class="flex gap-2">
              <button
                (click)="fazerCheckin()"
                [disabled]="selectedTurma?.usuario_registrado || checkinLoading"
                class="rounded-lg border px-3 py-1 text-xs font-semibold transition"
                [ngClass]="selectedTurma?.usuario_registrado
                  ? 'border-emerald-400/30 text-emerald-200 opacity-70 cursor-not-allowed'
                  : 'border-emerald-400/70 text-emerald-100 hover:bg-emerald-400/10'"
              >
                {{ checkinLoading ? 'Enviando...' : (selectedTurma?.usuario_registrado ? 'Check-in feito' : 'Fazer check-in') }}
              </button>
              <button (click)="limparSelecao()" class="rounded-lg border border-emerald-400/50 px-3 py-1 text-xs font-semibold text-emerald-200 transition hover:bg-emerald-400/10">
                Limpar
              </button>
            </div>
          </div>
          <div class="mt-5 space-y-2 text-sm text-slate-200">
            <p><span class="text-slate-500">Limite:</span> {{ selectedTurma!.limite_alunos }} alunos</p>
            <p><span class="text-slate-500">Registrados:</span> {{ selectedTurma!.alunos_registrados }}</p>
            <p><span class="text-slate-500">Vagas:</span> {{ selectedTurma!.vagas_disponiveis }}</p>
            <p><span class="text-slate-500">Ocupação:</span> {{ selectedTurma!.percentual_ocupacao?.toFixed(1) }}%</p>
            <p><span class="text-slate-500">Janela:</span> {{ selectedTurma!.horario_inicio.substring(0,5) }} - {{ selectedTurma!.horario_fim.substring(0,5) }}</p>
          </div>
          <div *ngIf="checkinMessage" class="mt-4 rounded-lg border px-3 py-2 text-xs font-semibold"
            [ngClass]="checkinErro ? 'border-rose-400/50 bg-rose-500/10 text-rose-100' : 'border-emerald-400/50 bg-emerald-500/10 text-emerald-100'">
            {{ checkinMessage }}
          </div>
        </div>

        <div class="md:col-span-2 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Alunos na turma</p>
              <h3 class="text-xl font-semibold text-slate-50">Presenças confirmadas</h3>
            </div>
            <div class="text-sm text-slate-400">
              Total: <span class="font-semibold text-slate-100">{{ alunos.length }}</span>
            </div>
          </div>

          <div *ngIf="loadingAlunos" class="rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-6 text-slate-300">
            Buscando alunos...
          </div>

          <div *ngIf="!loadingAlunos && alunos.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900/60 px-6 py-8 text-slate-400">
            Nenhum aluno realizou check-in para este horário ainda.
          </div>

          <div *ngIf="!loadingAlunos && alunos.length > 0" class="grid gap-3">
            <div *ngFor="let aluno of alunos" class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-slate-50">{{ aluno.nome }}</p>
                  <p class="text-xs text-slate-400">{{ aluno.email }}</p>
                </div>
                <span class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                  {{ formatarDataHora(aluno.data_checkin) }}
                </span>
              </div>
              <p class="mt-2 text-xs text-slate-500">Criado em {{ formatarDataHora(aluno.created_at) }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="fixed bottom-0 left-0 right-0 border-t border-slate-800/80 bg-slate-950/90 backdrop-blur supports-[backdrop-filter]:backdrop-blur-lg">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-6 py-4">
        <div class="text-xs text-slate-400">
          {{ selectedTurma ? ('Turma ' + selectedTurma.hora.substring(0,5)) : 'Selecione uma turma para agir' }}
        </div>
        <div class="flex flex-1 justify-end gap-3">
          <button
            (click)="loadTurmasHoje()"
            [disabled]="loadingTurmas"
            class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-xs font-semibold text-slate-200 transition hover:border-emerald-400/70 hover:text-emerald-200 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ loadingTurmas ? 'Atualizando...' : 'Atualizar' }}
          </button>
        </div>
      </div>
    </div>
  `
})
export class DashboardComponent implements OnInit {
  turmasPorDia: TurmaDia[] = [];
  totalTurmas = 0;
  ocupacaoMedia = 0;
  selectedTurma: (Turma & { data?: string }) | null = null;
  alunos: AlunoTurma[] = [];
  loadingTurmas = false;
  loadingAlunos = false;
  checkinLoading = false;
  checkinMessage = '';
  checkinErro = false;

  constructor(
    public authService: AuthService,
    private turmaService: TurmaService,
    private checkinService: CheckinService
  ) {}

  ngOnInit(): void {
    this.loadTurmasHoje();
  }

  loadTurmasHoje(): void {
    this.loadingTurmas = true;
    this.turmaService.getTurmasHoje().subscribe({
      next: (response) => {
        const hoje = response?.turmas?.length
          ? [{ data: response.data, dia_ativo: true, turmas: response.turmas }]
          : [];
        this.turmasPorDia = hoje;
        this.totalTurmas = response?.turmas?.length || 0;
        this.ocupacaoMedia = this.calcularOcupacaoMedia(hoje);
        this.loadingTurmas = false;
      },
      error: (error) => {
        this.loadingTurmas = false;
        console.error('Erro ao carregar turmas de hoje:', error);
      }
    });
  }

  selecionarTurma(turma: Turma, data: string): void {
    this.checkinMessage = '';
    this.checkinErro = false;
    this.selectedTurma = { ...turma, data };
    this.loadingAlunos = true;
    this.alunos = [];

    this.turmaService.getAlunos(turma.id).subscribe({
      next: (response) => {
        this.alunos = response.alunos;
        this.loadingAlunos = false;
      },
      error: (error) => {
        this.loadingAlunos = false;
        console.error('Erro ao carregar alunos:', error);
      }
    });
  }

  limparSelecao(): void {
    this.selectedTurma = null;
    this.alunos = [];
  }

  fazerCheckin(): void {
    if (!this.selectedTurma || this.selectedTurma.usuario_registrado) return;

    this.checkinLoading = true;
    this.checkinMessage = '';
    this.checkinErro = false;

    this.checkinService.realizarCheckin({ horario_id: this.selectedTurma.id }).subscribe({
      next: (response) => {
        this.checkinLoading = false;
        this.checkinMessage = response.message || 'Check-in realizado com sucesso!';
        this.selectedTurma = {
          ...this.selectedTurma!,
          usuario_registrado: true,
          alunos_registrados: (this.selectedTurma!.alunos_registrados || 0) + 1,
          vagas_disponiveis: Math.max(0, (this.selectedTurma!.vagas_disponiveis || 0) - 1)
        };
        this.selecionarTurma(this.selectedTurma, this.selectedTurma.data || '');
        this.loadTurmasHoje();
      },
      error: (error) => {
        this.checkinLoading = false;
        this.checkinErro = true;
        this.checkinMessage = error.error?.error || 'Erro ao realizar check-in';
      }
    });
  }

  calcularOcupacaoMedia(dias: TurmaDia[]): number {
    const turmas = dias.flatMap(dia => dia.turmas);
    if (!turmas.length) return 0;
    const soma = turmas.reduce((acc, turma) => acc + (turma.percentual_ocupacao || 0), 0);
    return soma / turmas.length;
  }

  formatarData(data: string): string {
    const date = new Date(data + 'T00:00:00');
    return date.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' });
  }

  formatarDataHora(data: string): string {
    const date = new Date(data.replace(' ', 'T'));
    return date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }
}
