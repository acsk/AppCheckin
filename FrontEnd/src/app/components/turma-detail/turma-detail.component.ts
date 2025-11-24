import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { TurmaService } from '../../services/turma.service';
import { CheckinService } from '../../services/checkin.service';
import { UserService } from '../../services/user.service';
import { TurmaAlunosResponse, AlunoTurma, UsuarioEstatisticas } from '../../models/api.models';
import { AuthService } from '../../services/auth.service';
import { IonicModule, AlertController, ActionSheetController } from '@ionic/angular';
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
            (click)="abrirEstatisticasUsuario(aluno)"
            class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-slate-900/50 transition"
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

  constructor(
    private route: ActivatedRoute,
    private turmaService: TurmaService,
    private checkinService: CheckinService,
    private toast: ToastService,
    private authService: AuthService,
    private alertController: AlertController,
    private userService: UserService,
    private actionSheetController: ActionSheetController
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

  abrirEstatisticasUsuario(aluno: AlunoTurma): void {
    const usuarioId = (aluno as any).usuario_id;
    if (!usuarioId) return;

    this.userService.getEstatisticas(usuarioId).subscribe({
      next: async (estatisticas) => {
        const foto = estatisticas.foto_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(estatisticas.nome)}&background=10b981&color=fff&size=200`;
        const actionSheet = await this.actionSheetController.create({
          header: estatisticas.nome,
          cssClass: 'ion-alert-vibrant ion-action-large',
          buttons: [
            { text: `Check-ins: ${estatisticas.total_checkins}`, role: 'info' },
            { text: 'Fechar', role: 'cancel' }
          ],
          translucent: true,
          backdropDismiss: true
        });
        // Override inner html for richer header
        (actionSheet as any).header = '';
        setTimeout(() => {
          const el = document.querySelector('.ion-action-large .action-sheet-group') as HTMLElement;
          if (el) {
            el.insertAdjacentHTML('afterbegin', `
              <div class="action-avatar-block">
                <img src="${foto}" alt="${estatisticas.nome}" class="action-avatar-img"/>
                <p class="name">${estatisticas.nome}</p>
              </div>
            `);
          }
        });
        await actionSheet.present();
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
