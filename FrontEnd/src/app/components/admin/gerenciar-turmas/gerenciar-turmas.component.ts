import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonHeader, IonToolbar, IonTitle, IonContent,
  IonButton, IonButtons, IonBackButton, IonIcon,
  IonList, IonSpinner, IonBadge, IonCard, IonCardContent
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { time, people, informationCircle } from 'ionicons/icons';
import { DiaService } from '../../../services/dia.service';
import { Dia, Horario } from '../../../models/api.models';

interface HorarioExtended extends Horario {
  limite_alunos?: number;
  alunos_registrados?: number;
}

@Component({
  selector: 'app-gerenciar-turmas',
  templateUrl: './gerenciar-turmas.component.html',
  styleUrls: ['./gerenciar-turmas.component.scss'],
  standalone: true,
  imports: [
    CommonModule,
    IonHeader, IonToolbar, IonTitle, IonContent,
    IonButton, IonButtons, IonBackButton, IonIcon,
    IonList, IonSpinner, IonBadge, IonCard, IonCardContent
  ]
})
export class GerenciarTurmasComponent implements OnInit {
  dias: Dia[] = [];
  horarios: HorarioExtended[] = [];
  diaSelecionado: Dia | null = null;
  loading = false;
  loadingHorarios = false;

  constructor(private diaService: DiaService) {
    addIcons({ time, people, informationCircle });
  }

  ngOnInit() {
    this.carregarDias();
  }

  carregarDias() {
    this.loading = true;
    this.diaService.getDiasProximos().subscribe({
      next: (response) => {
        this.dias = response.dias;
        this.loading = false;
        if (this.dias.length > 0) {
          this.selecionarDia(this.dias[0]);
        }
      },
      error: (error) => {
        console.error('Erro ao carregar dias:', error);
        this.loading = false;
      }
    });
  }

  selecionarDia(dia: Dia) {
    this.diaSelecionado = dia;
    this.carregarHorarios(dia.id);
  }

  carregarHorarios(diaId: number) {
    this.loadingHorarios = true;
    this.diaService.getHorarios(diaId).subscribe({
      next: (response) => {
        this.horarios = response.horarios;
        this.loadingHorarios = false;
      },
      error: (error) => {
        console.error('Erro ao carregar horários:', error);
        this.loadingHorarios = false;
      }
    });
  }

  formatarData(data: string): string {
    return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR', {
      weekday: 'short',
      day: '2-digit',
      month: '2-digit'
    });
  }

  getDiaSemana(data: string): string {
    const dias = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB'];
    return dias[new Date(data + 'T00:00:00').getDay()];
  }

  getVagasDisponiveis(horario: HorarioExtended): number {
    if (horario.limite_alunos && horario.alunos_registrados !== undefined) {
      return horario.limite_alunos - horario.alunos_registrados;
    }
    return horario.vagas_disponiveis || 0;
  }

  getPercentualOcupacao(horario: HorarioExtended): number {
    if (horario.limite_alunos && horario.alunos_registrados !== undefined) {
      return (horario.alunos_registrados / horario.limite_alunos) * 100;
    }
    if (horario.vagas && horario.checkins_count) {
      return (horario.checkins_count / horario.vagas) * 100;
    }
    return 0;
  }

  getAlunosRegistrados(horario: HorarioExtended): number {
    return horario.alunos_registrados ?? horario.checkins_count ?? 0;
  }

  getLimiteAlunos(horario: HorarioExtended): number {
    return horario.limite_alunos ?? horario.vagas ?? 0;
  }
}
