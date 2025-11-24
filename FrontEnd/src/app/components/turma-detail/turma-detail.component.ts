import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { TurmaService } from '../../services/turma.service';
import { CheckinService } from '../../services/checkin.service';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { TurmaAlunosResponse, AlunoTurma } from '../../models/api.models';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-turma-detail',
  standalone: true,
  imports: [CommonModule, RouterLink, MatSnackBarModule],
  template: `
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <button routerLink="/dashboard" class="rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-emerald-400 hover:text-emerald-300">
          ← Voltar
        </button>
        <div class="flex flex-col items-end gap-2 text-right sm:flex-row sm:items-center sm:gap-3">
          <div>
            <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Turma</p>
            <h1 class="text-2xl font-semibold text-slate-50">Horário {{ turmaHora }}</h1>
            <p class="text-sm text-slate-400">Data {{ turmaData }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-50">Alunos</h2>
          <span class="text-sm text-slate-400">{{ alunos.length }} alunos</span>
        </div>

        <div *ngIf="loading" class="rounded-xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-slate-300">
          Carregando alunos...
        </div>

        <div *ngIf="!loading && alunos.length === 0" class="rounded-xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-slate-400">
          Nenhum aluno registrado nesta turma.
        </div>

        <div class="grid gap-3">
          <div *ngFor="let aluno of alunos" class="rounded-xl border border-slate-800 bg-slate-950/60 px-4 py-3">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <img [src]="avatar(aluno)" [alt]="aluno.nome" class="h-10 w-10 rounded-full border border-slate-800 object-cover">
                <div>
                  <p class="text-sm font-semibold text-slate-50">{{ aluno.nome }}</p>
                  <p class="text-xs text-slate-400">{{ aluno.email }}</p>
                </div>
              </div>
              <span class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                Check-in {{ aluno.data_checkin }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="fixed bottom-0 left-0 right-0 border-t border-slate-800/80 bg-slate-950/90 backdrop-blur supports-[backdrop-filter]:backdrop-blur-lg">
      <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-6 py-4">
        <div class="text-xs text-slate-400">
          Turma {{ turmaHora }} · {{ turmaData || 'Data não informada' }}
        </div>
        <div class="flex flex-1 justify-end gap-3">
          <button
            (click)="recarregarAlunos()"
            [disabled]="loading"
            class="rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 px-5 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-cyan-500/25 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ loading ? 'Atualizando...' : 'Atualizar lista' }}
          </button>
          <button
            *ngIf="!myCheckinId"
            (click)="fazerCheckin()"
            [disabled]="checkinLoading"
            class="rounded-xl bg-gradient-to-r from-emerald-400 via-emerald-500 to-lime-400 px-7 py-3 text-base font-extrabold text-slate-950 shadow-lg shadow-emerald-500/30 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ checkinLoading ? 'Enviando...' : 'Fazer check-in' }}
          </button>
          <button
            *ngIf="myCheckinId"
            (click)="cancelarCheckin()"
            [disabled]="checkinLoading"
            class="rounded-xl bg-gradient-to-r from-rose-400 via-rose-500 to-orange-400 px-7 py-3 text-base font-extrabold text-rose-50 shadow-lg shadow-rose-500/30 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ checkinLoading ? 'Cancelando...' : 'Cancelar check-in' }}
          </button>
        </div>
      </div>
    </div>
  `
})
export class TurmaDetailComponent implements OnInit {
  turmaData = '';
  turmaHora = '';
  turmaId = 0;
  alunos: AlunoTurma[] = [];
  loading = false;
  checkinLoading = false;
  myCheckinId: number | null = null;

  constructor(
    private route: ActivatedRoute,
    private turmaService: TurmaService,
    private checkinService: CheckinService,
    private snackBar: MatSnackBar,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    const turmaId = Number(this.route.snapshot.paramMap.get('id'));
    this.turmaId = turmaId;
    this.turmaData = this.route.snapshot.queryParamMap.get('data') || '';
    this.turmaHora = this.route.snapshot.queryParamMap.get('hora')?.substring(0, 5) || '';

    if (turmaId) {
      this.loading = true;
      this.turmaService.getAlunos(turmaId).subscribe({
        next: (resp: TurmaAlunosResponse) => {
          this.alunos = resp.alunos;
          this.turmaData = resp.turma.data;
          this.turmaHora = resp.turma.hora.substring(0, 5);
          this.identificarMeuCheckin();
          this.loading = false;
        },
        error: () => {
          this.loading = false;
        }
      });
    }
  }

  fazerCheckin(): void {
    if (!this.turmaId || this.checkinLoading || this.myCheckinId) return;

    this.checkinLoading = true;

    this.checkinService.realizarCheckin({ horario_id: this.turmaId }).subscribe({
      next: (response) => {
        this.checkinLoading = false;
        this.snackBar.open(response.message || 'Check-in realizado com sucesso!', 'Fechar', { duration: 3000, panelClass: ['snack-success'] });
        this.myCheckinId = response.checkin?.id || null;
        this.recarregarAlunos();
      },
      error: (error) => {
        this.checkinLoading = false;
        const message = error.error?.error || 'Erro ao realizar check-in';
        this.snackBar.open(message, 'Fechar', { duration: 4000, panelClass: ['snack-error'] });
      }
    });
  }

  cancelarCheckin(): void {
    if (!this.myCheckinId || this.checkinLoading) return;
    this.checkinLoading = true;

    this.checkinService.cancelarCheckin(this.myCheckinId).subscribe({
      next: (response) => {
        this.checkinLoading = false;
        this.snackBar.open(response.message || 'Check-in cancelado com sucesso!', 'Fechar', { duration: 3000, panelClass: ['snack-success'] });
        this.myCheckinId = null;
        this.recarregarAlunos();
      },
      error: (error) => {
        this.checkinLoading = false;
        const message = error.error?.error || 'Erro ao cancelar check-in';
        this.snackBar.open(message, 'Fechar', { duration: 4000, panelClass: ['snack-error'] });
      }
    });
  }

  recarregarAlunos(): void {
    if (!this.turmaId) return;
    this.loading = true;
    this.turmaService.getAlunos(this.turmaId).subscribe({
      next: (resp: TurmaAlunosResponse) => {
        this.alunos = resp.alunos;
        this.turmaData = resp.turma.data;
        this.turmaHora = resp.turma.hora.substring(0, 5);
        this.identificarMeuCheckin();
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      }
    });
  }

  private identificarMeuCheckin(): void {
    const email = this.authService.currentUserValue?.email;
    if (!email) {
      this.myCheckinId = null;
      return;
    }
    const match = this.alunos.find((aluno) => aluno.email === email);
    this.myCheckinId = match?.checkin_id ?? null;
  }

  avatar(aluno: AlunoTurma): string {
    const seed = encodeURIComponent(aluno.email || aluno.nome || 'user');
    return `https://i.pravatar.cc/80?u=${seed}`;
  }
}
