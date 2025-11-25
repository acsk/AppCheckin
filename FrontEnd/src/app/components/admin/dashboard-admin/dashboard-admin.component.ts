import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AdminService } from '../../../services/admin.service';
import { DashboardAdminStats } from '../../../models/api.models';

@Component({
  selector: 'app-dashboard-admin',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    MatCardModule,
    MatIconModule,
    MatButtonModule,
    MatProgressSpinnerModule
  ],
  templateUrl: './dashboard-admin.component.html',
  styleUrls: ['./dashboard-admin.component.scss']
})
export class DashboardAdminComponent implements OnInit {
  stats: DashboardAdminStats = {
    total_alunos: 0,
    alunos_ativos: 0,
    alunos_inativos: 0,
    novos_alunos_mes: 0,
    total_checkins_hoje: 0,
    total_checkins_mes: 0,
    planos_vencendo: 0,
    receita_mensal: 0,
    contas_pendentes_qtd: 0,
    contas_pendentes_valor: 0,
    contas_vencidas_qtd: 0,
    contas_vencidas_valor: 0
  };

  loading = true;

  constructor(private adminService: AdminService) {}

  ngOnInit(): void {
    this.carregarEstatisticas();
  }

  carregarEstatisticas(): void {
    this.loading = true;
    this.adminService.getDashboardStats().subscribe({
      next: (data) => {
        this.stats = data;
        this.loading = false;
      },
      error: (error) => {
        console.error('Erro ao carregar estatísticas:', error);
        this.loading = false;
      }
    });
  }

  formatarMoeda(valor: number): string {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(valor);
  }

  calcularPercentualAtivos(): number {
    if (this.stats.total_alunos === 0) return 0;
    return Math.round((this.stats.alunos_ativos / this.stats.total_alunos) * 100);
  }
}
