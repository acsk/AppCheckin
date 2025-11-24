import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { TurmaService } from '../../services/turma.service';
import { CheckinService } from '../../services/checkin.service';
import { UserService } from '../../services/user.service';
import { TurmaAlunosResponse, AlunoTurma, UsuarioEstatisticas } from '../../models/api.models';
import { AuthService } from '../../services/auth.service';
import { IonicModule, AlertController } from '@ionic/angular';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-turma-detail',
  standalone: true,
  imports: [CommonModule, RouterLink, IonicModule],
  template: `
    <div class="space-y-4">
      <div class="flex items-center justify-between text-slate-200">
        <button routerLink="/dashboard" class="flex items-center gap-2 text-sm font-semibold text-slate-200">
          <ion-icon name="chevron-back" class="text-lg"></ion-icon>
          Voltar
        </button>
        <ion-icon name="refresh" (click)="recarregarAlunos()" class="text-xl cursor-pointer"></ion-icon>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4 shadow-lg shadow-emerald-500/10">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="rounded-2xl bg-slate-800 px-3 py-2 text-center text-slate-100">
              <p class="text-[11px] uppercase tracking-wide">{{ formatarDataParaDia(turmaData).mes }}</p>
              <p class="text-2xl font-bold leading-tight">{{ formatarDataParaDia(turmaData).dia }}</p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Horário</p>
              <p class="text-2xl font-bold text-slate-50">{{ turmaHora }}</p>
              <p class="text-sm text-slate-300">{{ formatarDataParaDia(turmaData).semana }}</p>
            </div>
          </div>
          <div class="rounded-full border border-slate-700 px-4 py-2 text-center">
            <p class="text-xs text-slate-400">Registrados</p>
            <p class="text-lg font-semibold text-slate-50">{{ alunos.length }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-950/80 shadow-xl shadow-emerald-500/5">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-3 text-slate-200">
          <p class="text-xs font-semibold tracking-[0.18em] text-slate-400">Lista de presença</p>
          <span class="text-xs text-slate-400">{{ alunos.length }} alunos</span>
        </div>

        <div *ngIf="loading" class="px-5 py-4 text-slate-300">Carregando alunos...</div>
        <div *ngIf="!loading && alunos.length === 0" class="px-5 py-4 text-slate-400">Nenhum aluno registrado nesta turma.</div>

        <div *ngIf="!loading && alunos.length > 0" class="divide-y divide-slate-800">
          <div 
            *ngFor="let aluno of alunos" 
            (click)="abrirEstatisticasUsuario(aluno.usuario_id!)"
            class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-slate-900/50 transition"
          >
            <img [src]="avatar(aluno)" [alt]="aluno.nome" class="h-10 w-10 rounded-full border border-slate-800 object-cover">
            <div class="flex-1">
              <p class="text-sm font-semibold text-slate-50">{{ aluno.nome }}</p>
              <p class="text-xs text-slate-400">{{ aluno.email }}</p>
            </div>
            <span class="text-[11px] font-semibold text-emerald-300">Check-in {{ aluno.data_checkin }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Estatísticas do Usuário -->
    <div 
      *ngIf="mostrarModalEstatisticas" 
      (click)="fecharModalEstatisticas()"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm"
    >
      <div 
        (click)="$event.stopPropagation()"
        class="w-full max-w-lg rounded-t-3xl bg-gradient-to-b from-slate-900 to-slate-950 pb-8 shadow-2xl"
      >
        <!-- Botão Fechar -->
        <button 
          (click)="fecharModalEstatisticas()"
          class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-slate-800/50 text-slate-300 hover:bg-slate-700"
        >
          <ion-icon name="close" class="text-2xl"></ion-icon>
        </button>

        <div *ngIf="loadingEstatisticas" class="flex items-center justify-center py-20">
          <ion-spinner name="crescent" class="text-emerald-400"></ion-spinner>
        </div>

        <div *ngIf="!loadingEstatisticas && usuarioEstatisticas" class="space-y-6 px-6 pt-12">
          <!-- Foto e Nome -->
          <div class="flex flex-col items-center gap-4">
            <div class="relative">
              <img 
                [src]="usuarioEstatisticas.foto_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(usuarioEstatisticas.nome) + '&background=10b981&color=fff&size=200'"
                [alt]="usuarioEstatisticas.nome"
                class="h-32 w-32 rounded-full border-4 border-emerald-500/30 object-cover shadow-lg shadow-emerald-500/20"
              >
            </div>
            <h2 class="text-center text-2xl font-bold uppercase tracking-wide text-white">
              {{ usuarioEstatisticas.nome }}
            </h2>
          </div>

          <!-- Estatísticas -->
          <div class="space-y-3">
            <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/50 px-6 py-4">
              <span class="text-sm text-slate-400">Check-ins</span>
              <span class="text-3xl font-bold text-emerald-400">{{ usuarioEstatisticas.total_checkins }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/50 px-6 py-4">
              <span class="text-sm text-slate-400">PR's</span>
              <span class="text-3xl font-bold text-cyan-400">{{ usuarioEstatisticas.total_prs }}</span>
            </div>
          </div>

          <!-- Botão Fechar -->
          <button
            (click)="fecharModalEstatisticas()"
            class="w-full rounded-full bg-slate-800 py-3 text-sm font-bold text-slate-200 transition hover:bg-slate-700"
          >
            Fechar
          </button>
        </div>
      </div>
    </div>

    <div class="fixed bottom-0 left-0 right-0 border-t border-slate-800/80 bg-slate-950/95 backdrop-blur supports-[backdrop-filter]:backdrop-blur-lg">
      <div class="mx-auto flex max-w-5xl items-center justify-center px-6 py-4">
        <button
          *ngIf="!myCheckinId"
          (click)="abrirConfirmacaoCheckin()"
          [disabled]="checkinLoading"
          class="w-full max-w-md rounded-full bg-gradient-to-r from-emerald-400 via-emerald-500 to-lime-400 px-8 py-3 text-base font-extrabold text-slate-950 shadow-lg shadow-emerald-500/30 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ checkinLoading ? 'Enviando...' : 'Fazer check-in' }}
        </button>
        <button
          *ngIf="myCheckinId"
          (click)="cancelarCheckin()"
          [disabled]="checkinLoading"
          class="w-full max-w-md rounded-full bg-gradient-to-r from-rose-400 via-rose-500 to-orange-400 px-8 py-3 text-base font-extrabold text-rose-50 shadow-lg shadow-rose-500/30 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ checkinLoading ? 'Cancelando...' : 'Cancelar check-in' }}
        </button>
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
  mostrarModalEstatisticas = false;
  loadingEstatisticas = false;
  usuarioEstatisticas: UsuarioEstatisticas | null = null;

  constructor(
    private route: ActivatedRoute,
    private turmaService: TurmaService,
    private checkinService: CheckinService,
    private toast: ToastService,
    private authService: AuthService,
    private alertController: AlertController,
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

  async abrirConfirmacaoCheckin(): Promise<void> {
    if (!this.turmaId || this.checkinLoading || this.myCheckinId) return;

    const alert = await this.alertController.create({
      header: 'Confirmar check-in',
      message: `Deseja fazer check-in na turma das ${this.turmaHora}?`,
      cssClass: 'ion-alert-vibrant',
      buttons: [
        {
          text: 'Cancelar',
          role: 'cancel'
        },
        {
          text: 'Confirmar',
          role: 'confirm',
          handler: () => this.fazerCheckin()
        }
      ]
    });

    await alert.present();
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

  abrirEstatisticasUsuario(usuarioId: number): void {
    if (!usuarioId) return;
    
    this.mostrarModalEstatisticas = true;
    this.loadingEstatisticas = true;
    this.usuarioEstatisticas = null;

    this.userService.getEstatisticas(usuarioId).subscribe({
      next: (estatisticas) => {
        this.usuarioEstatisticas = estatisticas;
        this.loadingEstatisticas = false;
      },
      error: (error) => {
        this.loadingEstatisticas = false;
        this.toast.show('Erro ao carregar estatísticas do usuário', 'danger');
        this.mostrarModalEstatisticas = false;
      }
    });
  }

  fecharModalEstatisticas(): void {
    this.mostrarModalEstatisticas = false;
    this.usuarioEstatisticas = null;
  }

  encodeURIComponent(str: string): string {
    return encodeURIComponent(str);
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
