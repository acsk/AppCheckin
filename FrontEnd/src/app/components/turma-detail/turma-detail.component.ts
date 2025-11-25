import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { TurmaService } from '../../services/turma.service';
import { CheckinService } from '../../services/checkin.service';
import { UserService } from '../../services/user.service';
import { TurmaAlunosResponse, AlunoTurma } from '../../models/api.models';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-turma-detail',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="page-shell">
      <div class="content-shell space-y-10">
        <div class="page-header">
          <div class="page-title">
            <div class="pill-icon">📘</div>
            <div>
              <p class="eyebrow">Turma</p>
              <h1>Detalhes da turma</h1>
              <p class="lead">Veja os alunos registrados e faça ou cancele seu check-in.</p>
            </div>
          </div>
          <div class="actions">
            <a routerLink="/dashboard" class="btn btn-ghost">Voltar</a>
            <button class="btn btn-ghost" (click)="recarregarAlunos()">Recarregar</button>
          </div>
        </div>

        <div class="card resumo">
          <div class="resumo-date">
            <div class="box">
              <span class="muted small">{{ formatarDataParaDia(turmaData).mes }}</span>
              <strong>{{ formatarDataParaDia(turmaData).dia }}</strong>
            </div>
            <div>
              <p class="eyebrow">Horário</p>
              <h2>{{ turmaHora }}</h2>
              <p class="muted">{{ formatarDataParaDia(turmaData).semana }}</p>
            </div>
          </div>
          <div class="badges">
            <span class="badge success">Registrados: {{ alunos.length }}</span>
            <span class="badge">ID: {{ turmaId }}</span>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <p class="eyebrow">Lista de presença</p>
              <p class="muted">{{ alunos.length }} aluno(s)</p>
            </div>
          </div>

          <div *ngIf="loading" class="muted-card">Carregando alunos...</div>
          <div *ngIf="!loading && alunos.length === 0" class="muted-card">Nenhum aluno registrado nesta turma.</div>

          <div *ngIf="!loading && alunos.length > 0" class="aluno-list">
            <div 
              *ngFor="let aluno of alunos" 
              (click)="abrirEstatisticasUsuario(aluno)"
              class="aluno-row"
            >
              <img [src]="avatar(aluno)" [alt]="aluno.nome">
              <div class="info">
                <p class="nome">{{ aluno.nome }}</p>
                <p class="muted small">{{ aluno.email }}</p>
              </div>
              <span class="badge success">Check-in {{ aluno.data_checkin }}</span>
            </div>
          </div>
        </div>

        <div class="cta">
          <button
            *ngIf="!myCheckinId"
            (click)="abrirConfirmacaoCheckin()"
            [disabled]="checkinLoading"
            class="btn btn-primary full"
          >
            {{ checkinLoading ? 'Enviando...' : 'Fazer check-in' }}
          </button>
          <button
            *ngIf="myCheckinId"
            (click)="cancelarCheckin()"
            [disabled]="checkinLoading"
            class="btn danger full"
          >
            {{ checkinLoading ? 'Cancelando...' : 'Cancelar check-in' }}
          </button>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .resumo { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .resumo-date { display: flex; align-items: center; gap: 14px; }
    .resumo .box { width: 68px; height: 68px; border-radius: var(--radius-md); border: 1px solid var(--border); display: grid; place-items: center; background: #fff; box-shadow: var(--shadow-soft); }
    .resumo .box strong { font-size: 22px; color: var(--text-strong); }
    .badges { display: flex; gap: 10px; flex-wrap: wrap; }
    .card { padding: 16px; }
    .card-head { display: flex; justify-content: space-between; align-items: center; }
    .aluno-list { display: flex; flex-direction: column; gap: 8px; }
    .aluno-row { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--border); border-radius: var(--radius-md); background: #fff; cursor: pointer; transition: var(--transition); }
    .aluno-row:hover { border-color: var(--brand-primary); box-shadow: var(--shadow-soft); }
    .aluno-row img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); }
    .aluno-row .nome { margin: 0; font-weight: 700; color: var(--text-strong); }
    .cta .btn { max-width: 420px; }
    .btn.danger { border-color: rgba(220,38,38,0.25); background: #fef2f2; color: #b91c1c; }
    .btn.danger:hover { border-color: #dc2626; }
  `]
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
    private toast: ToastService,
    private authService: AuthService,
    private userService: UserService
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
        this.toast.show(response.message || 'Check-in realizado com sucesso!', 'success');
        this.myCheckinId = response.checkin?.id || null;
        this.recarregarAlunos();
      },
      error: (error) => {
        this.checkinLoading = false;
        const message = error.error?.error || 'Erro ao realizar check-in';
        this.toast.show(message, 'danger', 4000);
      }
    });
  }

  abrirConfirmacaoCheckin(): void {
    if (!this.turmaId || this.checkinLoading || this.myCheckinId) return;
    const ok = confirm(`Deseja fazer check-in na turma das ${this.turmaHora}?`);
    if (ok) this.fazerCheckin();
  }

  cancelarCheckin(): void {
    if (!this.myCheckinId || this.checkinLoading) return;
    this.checkinLoading = true;

    this.checkinService.cancelarCheckin(this.myCheckinId).subscribe({
      next: (response) => {
        this.checkinLoading = false;
        this.toast.show(response.message || 'Check-in cancelado com sucesso!', 'success');
        this.myCheckinId = null;
        this.recarregarAlunos();
      },
      error: (error) => {
        this.checkinLoading = false;
        const message = error.error?.error || 'Erro ao cancelar check-in';
        this.toast.show(message, 'danger', 4000);
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

  abrirEstatisticasUsuario(aluno: AlunoTurma): void {
    const usuarioId = (aluno as any).usuario_id;
    if (!usuarioId) return;

    this.userService.getEstatisticas(usuarioId).subscribe({
      next: async (estatisticas) => {
        alert(`${estatisticas.nome}\nCheck-ins: ${estatisticas.total_checkins}`);
      },
      error: () => {
        this.toast.show('Erro ao carregar estatísticas do usuário', 'danger');
      }
    });
  }

  formatarDataParaDia(data: string) {
    if (!data) return { dia: '--', mes: '--', semana: '' };
    const d = new Date(data + 'T00:00:00');
    const dia = d.getDate().toString().padStart(2, '0');
    const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    const semanas = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    return { dia, mes: meses[d.getMonth()], semana: semanas[d.getDay()] };
  }
}
