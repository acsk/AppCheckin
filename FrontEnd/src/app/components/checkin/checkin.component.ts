import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DiaService } from '../../services/dia.service';
import { CheckinService } from '../../services/checkin.service';
import { Dia, Horario } from '../../models/api.models';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-checkin',
  standalone: true,
  imports: [
    CommonModule
  ],
  template: `
    <div class="page-shell">
      <div class="content-shell space-y-10">
        <div class="page-header">
          <div class="page-title">
            <div class="pill-icon">✅</div>
            <div>
              <p class="eyebrow">Check-in</p>
              <h1>Escolha o dia e confirme seu horário</h1>
              <p class="lead">Veja vagas disponíveis e confirme sua presença rapidamente.</p>
            </div>
          </div>
          <button class="btn btn-ghost" (click)="carregarDias()">Atualizar</button>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <p class="eyebrow">Dias</p>
              <p class="muted">Navegue entre as datas disponíveis.</p>
            </div>
            <div class="meta">
              <span class="badge">Total: {{ dias.length }}</span>
              <span class="badge" [class.success]="diaSelecionado">Selecionado: {{ diaSelecionado?.data || '---' }}</span>
            </div>
          </div>
          <div class="days-nav">
            <button class="icon-btn" (click)="navegarDias('prev')">‹</button>
            <div class="days-list">
              <button
                *ngFor="let dia of dias"
                (click)="selecionarDia(dia)"
                class="day-tile"
                [class.active]="diaSelecionado?.id === dia.id"
              >
                <span class="muted small">{{ getDiaSemanaCurto(dia.data) }}</span>
                <strong>{{ formatarDiaNumero(dia.data) }}</strong>
              </button>
            </div>
            <button class="icon-btn" (click)="navegarDias('next')">›</button>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <p class="eyebrow">Horários</p>
              <p class="muted">{{ diaSelecionado ? formatarData(diaSelecionado.data) : 'Escolha um dia acima' }}</p>
            </div>
          </div>

          <div *ngIf="loading" class="muted-card">Carregando opções...</div>
          <div *ngIf="!loading && horarios.length === 0" class="muted-card">Nenhum horário disponível.</div>

          <div *ngIf="!loading && horarios.length > 0" class="horario-list">
            <button
              *ngFor="let horario of horarios"
              [disabled]="horario.vagas_disponiveis === 0 || loading"
              (click)="realizarCheckin(horario)"
              class="horario-card"
              [class.disabled]="horario.vagas_disponiveis === 0"
            >
              <div class="hora">{{ horario.hora.substring(0,5) }}</div>
              <div class="info">
                <p class="title">Aula</p>
                <p class="muted small">Janela {{ (horario.horario_inicio || horario.hora).substring(0,5) }} - {{ (horario.horario_fim || horario.hora).substring(0,5) }}</p>
              </div>
              <div class="vagas" [ngClass]="badgeClass(horario.vagas_disponiveis)">
                {{ horario.vagas_disponiveis }}/{{ horario.vagas }} vagas
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .card { padding: 16px; }
    .card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
    .muted-card { color: var(--text-soft); padding: 10px 0; }
    .days-nav { display: grid; grid-template-columns: auto 1fr auto; gap: 12px; align-items: center; }
    .days-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 8px; }
    .day-tile { border: 1px solid var(--border); border-radius: var(--radius-md); background: #fff; padding: 10px 8px; text-align: center; transition: var(--transition); }
    .day-tile.active { border-color: var(--brand-primary); box-shadow: var(--ring); }
    .icon-btn { width: 38px; height: 38px; border: 1px solid var(--border); border-radius: 12px; background: #fff; }
    .horario-list { display: flex; flex-direction: column; gap: 10px; }
    .horario-card { display: grid; grid-template-columns: 70px 1fr auto; gap: 12px; align-items: center; padding: 12px; border: 1px solid var(--border); border-radius: var(--radius-md); background: #fff; transition: var(--transition); text-align: left; }
    .horario-card:hover { border-color: var(--brand-primary); box-shadow: var(--shadow-soft); }
    .horario-card.disabled { opacity: 0.6; cursor: not-allowed; }
    .hora { font-size: 20px; font-weight: 800; color: var(--text-strong); }
    .title { margin: 0; font-weight: 700; }
    .vagas { font-weight: 700; padding: 6px 10px; border-radius: 999px; border: 1px solid var(--border); background: #f8fafc; color: var(--text-strong); }
    .border-rose-400\\/50 { border-color: rgba(244,63,94,0.5) !important; color: #9f1239 !important; background: #fff1f2; }
    .border-amber-400\\/50 { border-color: rgba(251,191,36,0.5) !important; color: #92400e !important; background: #fffbeb; }
    .border-emerald-400\\/50 { border-color: rgba(52,211,153,0.5) !important; color: #065f46 !important; background: #ecfdf3; }
  `]
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
