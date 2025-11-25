import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { TurmaService } from '../../services/turma.service';
import { DiaService } from '../../services/dia.service';
import { TurmaDia, Turma, Dia } from '../../models/api.models';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink
  ],
  template: `
    <div class="page-shell">
      <div class="content-shell space-y-12">
        <div class="page-header">
          <div class="page-title">
            <div class="pill-icon">📅</div>
            <div>
              <p class="eyebrow">Dashboard</p>
              <h1>Escolha um dia para ver as turmas</h1>
              <p class="lead">Consulte horários, disponibilidade e veja se você já está inscrito.</p>
            </div>
          </div>
          <button class="btn btn-ghost" (click)="loadDiasProximos()">Atualizar</button>
        </div>

        <div *ngIf="loadingDias" class="card muted-card">Carregando dias...</div>
        <div *ngIf="!loadingDias && diasDisponiveis.length === 0" class="card muted-card">Nenhum dia disponível no momento.</div>

        <div *ngIf="!loadingDias && diasDisponiveis.length > 0" class="card days-card">
          <div class="card-head">
            <div>
              <p class="eyebrow">Dias</p>
              <p class="muted">Selecione um dia para ver as turmas.</p>
            </div>
            <div class="meta">
              <span class="badge">Carregados: {{ diasDisponiveis.length }}</span>
              <span class="badge" [class.success]="selectedDia">Selecionado: {{ selectedDia?.data || '---' }}</span>
            </div>
          </div>

          <div class="days-nav">
            <button class="icon-btn" (click)="carregarDiasAnteriores()" [disabled]="loadingDias">‹</button>
            <div class="days-list">
              <button
                *ngFor="let dia of diasExibicao"
                class="day-tile"
                [class.active]="selectedDia?.data === dia.data"
                [class.disabled]="!dia.disponivel"
                (click)="dia.disponivel ? selecionarDia(dia) : null"
              >
                <span class="muted small">{{ dia.disponivel ? formatarSemanaCurto(dia.data) : '---' }}</span>
                <strong>{{ dia.disponivel ? formatarDiaNumero(dia.data) : '--' }}</strong>
              </button>
            </div>
            <button class="icon-btn" (click)="carregarDiasPosteriores()" [disabled]="loadingDias">›</button>
          </div>
        </div>

        <div *ngIf="loadingTurmas" class="card muted-card">Carregando turmas...</div>

        <div *ngIf="!loadingTurmas && selectedDia && turmasDia" class="card turmas-card">
          <div class="card-head">
            <div>
              <p class="eyebrow">Data</p>
              <h2>{{ formatarData(selectedDia.data) }}</h2>
            </div>
            <div class="meta">
              <span class="badge" [class.success]="turmasDia.dia.ativo">Dia {{ turmasDia.dia.ativo ? 'ativo' : 'inativo' }}</span>
              <span class="badge">Turmas: {{ turmasDia.turmas?.length || 0 }}</span>
              <span class="badge">Ocupação: {{ ocupacaoGeral }}%</span>
            </div>
          </div>

          <div class="turma-list">
            <a
              *ngFor="let turma of turmasDia.turmas"
              [routerLink]="['/turmas', turma.id]"
              [queryParams]="{ data: selectedDia.data, hora: turma.hora }"
              class="turma-card"
              [class.selected]="turma.usuario_registrado"
            >
              <div class="turma-head">
                <div>
                  <p class="eyebrow small">Horário</p>
                  <h3>{{ turma.hora.substring(0,5) }}</h3>
                </div>
                <div class="meta">
                  <span *ngIf="turma.usuario_registrado" class="badge success">Você está nesta turma</span>
                  <span class="badge">{{ turma.alunos_registrados }}/{{ turma.limite_alunos }} alunos</span>
                </div>
              </div>

              <div class="progress">
                <div class="bar" [style.width.%]="turma.percentual_ocupacao || 0"></div>
                <span class="muted small">{{ turma.percentual_ocupacao?.toFixed(0) || 0 }}%</span>
              </div>

              <div class="turma-footer">
                <span>Vagas: {{ turma.vagas_disponiveis }}</span>
                <span>Início {{ turma.horario_inicio.substring(0,5) }}</span>
                <span>Fim {{ turma.horario_fim.substring(0,5) }}</span>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .muted-card {
      padding: 12px 14px;
      color: var(--text-soft);
      background: linear-gradient(120deg, #f8fafc, #eef2ff);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
    }
    .days-card { padding: 18px; background: linear-gradient(145deg, #ffffff, #f5f8ff); }
    .card-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 12px;
    }
    .days-nav {
      display: grid;
      grid-template-columns: auto 1fr auto;
      gap: 12px;
      align-items: center;
    }
    .days-list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
      gap: 10px;
    }
    .day-tile {
      border: 1px solid var(--border);
      border-radius: 14px;
      background: #fff;
      padding: 12px 10px;
      text-align: center;
      transition: var(--transition);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    .day-tile strong { font-size: 18px; color: var(--text-strong); }
    .day-tile.active { border-color: var(--brand-primary); box-shadow: 0 12px 26px rgba(37,99,235,0.18), var(--ring); }
    .day-tile.disabled { opacity: 0.45; cursor: not-allowed; }
    .icon-btn {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: #fff;
      cursor: pointer;
      transition: var(--transition);
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    }
    .icon-btn:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
    .icon-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .turmas-card { padding: 18px; background: linear-gradient(180deg, #ffffff, #f7f9fc); }
    .turma-list { display: flex; flex-direction: column; gap: 12px; }
    .turma-card {
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 16px;
      background: #fff;
      color: var(--text-strong);
      display: block;
      text-decoration: none;
      transition: var(--transition);
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
    }
    .turma-card:hover { border-color: var(--brand-primary); box-shadow: 0 16px 34px rgba(37, 99, 235, 0.16); }
    .turma-card.selected { border-color: rgba(34, 197, 94, 0.4); box-shadow: 0 16px 36px rgba(34, 197, 94, 0.18); background: linear-gradient(135deg, #f8fffb, #ecfdf3); }
    .turma-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
    .turma-head h3 { margin: 0; font-size: 20px; }
    .progress { display: flex; align-items: center; gap: 10px; }
    .progress .bar {
      flex: 1;
      height: 10px;
      border-radius: 999px;
      background: linear-gradient(90deg, var(--brand-primary), var(--brand-accent));
      transition: width 0.2s ease;
    }
    .turma-footer { display: flex; gap: 12px; flex-wrap: wrap; color: var(--text-soft); font-size: 13px; }
    .badge.success { background: #ecfdf3; border-color: rgba(22, 163, 74, 0.3); color: #166534; }
  `]
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
    private diaService: DiaService
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
  get ocupacaoGeral(): string {
    const totalRegistrados = this.turmasDia?.turmas?.reduce((acc, t) => acc + (t.alunos_registrados || 0), 0) || 0;
    const totalLimite = this.turmasDia?.turmas?.reduce((acc, t) => acc + (t.limite_alunos || 0), 0) || 0;
    if (!totalLimite) return '0';
    return ((totalRegistrados / totalLimite) * 100).toFixed(1);
  }
}
