import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { IonicModule, ActionSheetController } from '@ionic/angular';
import { AuthService } from '../../services/auth.service';
import { TurmaService } from '../../services/turma.service';
import { DiaService } from '../../services/dia.service';
import { TurmaDia, Turma, Dia } from '../../models/api.models';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    IonicModule
  ],
  template: `
    <div class="space-y-8">
      <div *ngIf="loadingDias" class="flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-6 text-slate-300">
        <svg class="h-5 w-5 animate-spin text-emerald-300" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 000 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
        </svg>
        Carregando dias...
      </div>

      <div *ngIf="!loadingDias && diasDisponiveis.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900/60 px-6 py-10 text-center text-slate-400">
        Nenhum dia disponível no momento.
      </div>

      <div *ngIf="!loadingDias && diasDisponiveis.length > 0" class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <p class="text-sm font-semibold text-slate-200">Dias</p>
          <div class="flex items-center gap-3">
            <button (click)="abrirStats()" class="text-xs font-semibold text-emerald-200">Estatísticas</button>
            <button (click)="loadDiasProximos()" class="text-xs text-emerald-300">Atualizar</button>
          </div>
        </div>
        <div class="flex items-center gap-2 py-3">
          <!-- Seta para dias anteriores -->
          <button
            (click)="carregarDiasAnteriores()"
            [disabled]="loadingDias"
            class="flex h-12 w-10 flex-shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-emerald-400/70 hover:text-emerald-200 disabled:cursor-not-allowed disabled:opacity-40"
            title="Dias anteriores"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
          </button>

          <!-- Lista de dias -->
          <div class="flex flex-1 items-center justify-center gap-2">
            <button
              *ngFor="let dia of diasExibicao"
              (click)="dia.disponivel ? selecionarDia(dia) : null"
              [disabled]="!dia.disponivel"
              class="w-[80px] flex-shrink-0 rounded-xl px-3 py-2 text-center text-xs font-semibold transition"
              [ngClass]="dia.disponivel ? (selectedDia?.data === dia.data
                ? 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/50'
                : 'bg-slate-900 text-slate-300 border border-slate-800 hover:border-slate-700')
                : 'bg-slate-950 text-slate-600 border border-slate-900 cursor-not-allowed opacity-50'"
            >
              <div class="text-[11px] uppercase tracking-wide">{{ dia.disponivel ? formatarSemanaCurto(dia.data) : '---' }}</div>
              <div class="text-base font-bold">{{ dia.disponivel ? formatarDiaNumero(dia.data) : '--' }}</div>
            </button>
          </div>

          <!-- Seta para dias posteriores -->
          <button
            (click)="carregarDiasPosteriores()"
            [disabled]="loadingDias"
            class="flex h-12 w-10 flex-shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-emerald-400/70 hover:text-emerald-200 disabled:cursor-not-allowed disabled:opacity-40"
            title="Dias posteriores"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>
        </div>
      </div>

      <div *ngIf="loadingTurmas" class="flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-6 text-slate-300">
        <svg class="h-5 w-5 animate-spin text-emerald-300" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 000 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
        </svg>
        Carregando turmas...
      </div>

      <div class="grid gap-6" *ngIf="!loadingTurmas && selectedDia && turmasDia">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Data</p>
              <p class="text-xl font-semibold text-slate-50">{{ formatarData(selectedDia.data) }}</p>
            </div>
            <span class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
              {{ turmasDia.dia.ativo ? 'Dia ativo' : 'Inativo' }}
            </span>
          </div>

          <div class="space-y-3">
            <a
              *ngFor="let turma of turmasDia.turmas"
              [routerLink]="['/turmas', turma.id]"
              [queryParams]="{ data: selectedDia.data, hora: turma.hora }"
              class="group block cursor-pointer rounded-xl border p-4 transition"
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
            </a>
          </div>
        </div>
      </div>
    </div>
  `
})
export class DashboardComponent implements OnInit {
  diasDisponiveis: Dia[] = [];
  diasExibicao: (Dia & { disponivel: boolean })[] = [];
  turmasDia: TurmaDia | null = null;
  totalTurmas = 0;
  ocupacaoMedia = 0;
  selectedDia: Dia | null = null;
  loadingDias = false;
  loadingTurmas = false;

  constructor(
    public authService: AuthService,
    private turmaService: TurmaService,
    private diaService: DiaService,
    private actionSheetCtrl: ActionSheetController
  ) {}

  ngOnInit(): void {
    this.loadDiasProximos();
  }

  loadDiasProximos(): void {
    this.loadingDias = true;
    this.diaService.getDiasProximos().subscribe({
      next: (response) => {
        this.diasDisponiveis = response.dias || [];
        this.prepararDiasExibicao();
        if (this.diasDisponiveis.length > 0) {
          const hoje = new Date().toISOString().split('T')[0];
          const diaHoje = this.diasDisponiveis.find(d => d.data === hoje);
          this.selecionarDia(diaHoje || this.diasDisponiveis[0]);
        }
        this.loadingDias = false;
      },
      error: (error) => {
        this.loadingDias = false;
        console.error('Erro ao carregar dias próximos:', error);
      }
    });
  }

  prepararDiasExibicao(): void {
    const TOTAL_SLOTS = 5;
    this.diasExibicao = [];
    
    // Preenche com os dias disponíveis
    for (let i = 0; i < this.diasDisponiveis.length && i < TOTAL_SLOTS; i++) {
      this.diasExibicao.push({
        ...this.diasDisponiveis[i],
        disponivel: true
      });
    }
    
    // Preenche os slots vazios restantes
    const slotsVazios = TOTAL_SLOTS - this.diasExibicao.length;
    for (let i = 0; i < slotsVazios; i++) {
      this.diasExibicao.push({
        id: 0,
        data: '',
        ativo: false,
        created_at: '',
        updated_at: '',
        disponivel: false
      });
    }
  }

  loadTurmasDia(data: string): void {
    this.loadingTurmas = true;
    this.diaService.getHorariosPorData(data).subscribe({
      next: (response) => {
        this.turmasDia = response;
        this.totalTurmas = response.turmas?.length || 0;
        this.loadingTurmas = false;
      },
      error: (error) => {
        this.loadingTurmas = false;
        console.error('Erro ao carregar turmas do dia:', error);
      }
    });
  }

  carregarDiasAnteriores(): void {
    if (this.diasDisponiveis.length === 0) return;
    
    // Pega o primeiro dia da lista e busca dias anteriores a ele
    const primeiroDia = this.diasDisponiveis[0];
    this.loadingDias = true;
    
    this.diaService.getDiasProximos(primeiroDia.data).subscribe({
      next: (response) => {
        this.diasDisponiveis = response.dias || [];
        this.prepararDiasExibicao();
        this.loadingDias = false;
      },
      error: (error) => {
        this.loadingDias = false;
        console.error('Erro ao carregar dias anteriores:', error);
      }
    });
  }

  carregarDiasPosteriores(): void {
    if (this.diasDisponiveis.length === 0) return;
    
    // Pega o último dia da lista e busca dias posteriores a ele
    const ultimoDia = this.diasDisponiveis[this.diasDisponiveis.length - 1];
    this.loadingDias = true;
    
    this.diaService.getDiasProximos(ultimoDia.data).subscribe({
      next: (response) => {
        this.diasDisponiveis = response.dias || [];
        this.prepararDiasExibicao();
        this.loadingDias = false;
      },
      error: (error) => {
        this.loadingDias = false;
        console.error('Erro ao carregar dias posteriores:', error);
      }
    });
  }

  selecionarDia(dia: Dia): void {
    this.selectedDia = dia;
    this.turmasDia = null;
    
    // Carregar turmas do dia selecionado
    this.loadTurmasDia(dia.data);
  }

  formatarData(data: string): string {
    const date = new Date(data + 'T00:00:00');
    return date.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' });
  }

  formatarSemanaCurto(data: string): string {
    const date = new Date(data + 'T00:00:00');
    const dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return dias[date.getDay()].toUpperCase();
  }

  formatarDiaNumero(data: string): string {
    const d = new Date(data + 'T00:00:00');
    return d.getDate().toString().padStart(2, '0');
  }

  async abrirStats(): Promise<void> {
    const diasCount = this.diasDisponiveis.length;
    const turmasCount = this.turmasDia?.turmas?.length || 0;
    const totalRegistrados = this.turmasDia?.turmas?.reduce((acc, t) => acc + (t.alunos_registrados || 0), 0) || 0;
    const totalLimite = this.turmasDia?.turmas?.reduce((acc, t) => acc + (t.limite_alunos || 0), 0) || 0;
    const ocupacaoPct = totalLimite ? ((totalRegistrados / totalLimite) * 100).toFixed(1) : '0';

    const actionSheet = await this.actionSheetCtrl.create({
      header: 'Estatísticas do dia',
      cssClass: 'ion-alert-vibrant',
      buttons: [
        { text: `Dias carregados: ${diasCount}`, role: 'info' },
        { text: `Turmas no dia: ${turmasCount}`, role: 'info' },
        { text: `Total registrados: ${totalRegistrados}/${totalLimite || '?'}`, role: 'info' },
        { text: `Ocupação geral: ${ocupacaoPct}%`, role: 'info' },
        { text: 'Fechar', role: 'cancel' }
      ]
    });

    await actionSheet.present();
  }
}
