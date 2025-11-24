import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import {
  IonHeader, IonToolbar, IonTitle, IonContent, IonList, IonItem,
  IonLabel, IonButton, IonButtons, IonBackButton, IonIcon, IonCard,
  IonCardHeader, IonCardTitle, IonCardContent,
  IonSearchbar, IonAvatar, IonBadge, IonSpinner
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { person, calendar, checkmark, time } from 'ionicons/icons';
import { AdminService } from '../../../services/admin.service';
import { DiaService } from '../../../services/dia.service';
import { AlunoAdmin, Horario, Dia } from '../../../models/api.models';

@Component({
  selector: 'app-checkin-manual',
  templateUrl: './checkin-manual.component.html',
  styleUrls: ['./checkin-manual.component.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    IonHeader, IonToolbar, IonTitle, IonContent, IonList, IonItem,
    IonLabel, IonButton, IonButtons, IonBackButton, IonIcon, IonCard,
    IonCardHeader, IonCardTitle, IonCardContent,
    IonSearchbar, IonAvatar, IonBadge, IonSpinner
  ]
})
export class CheckinManualComponent implements OnInit {
  alunos: AlunoAdmin[] = [];
  alunosFiltrados: AlunoAdmin[] = [];
  dias: Dia[] = [];
  horarios: Horario[] = [];
  
  loading = false;
  loadingHorarios = false;
  
  checkinForm: FormGroup;
  searchTerm = '';
  diaSelecionado: Dia | null = null;

  constructor(
    private adminService: AdminService,
    private diaService: DiaService,
    private fb: FormBuilder
  ) {
    addIcons({ person, calendar, checkmark, time });

    this.checkinForm = this.fb.group({
      usuario_id: ['', Validators.required],
      horario_id: ['', Validators.required]
    });
  }

  ngOnInit() {
    this.carregarAlunos();
    this.carregarDias();
  }

  carregarAlunos() {
    this.loading = true;
    this.adminService.listarAlunos().subscribe({
      next: (response) => {
        // Filtrar apenas alunos ativos (role_id = 1)
        this.alunos = response.alunos.filter(a => a.role_id === 1);
        this.alunosFiltrados = this.alunos;
        this.loading = false;
      },
      error: (error) => {
        console.error('Erro ao carregar alunos:', error);
        this.loading = false;
      }
    });
  }

  carregarDias() {
    this.diaService.getDiasProximos().subscribe({
      next: (response) => {
        this.dias = response.dias;
      },
      error: (error) => console.error('Erro ao carregar dias:', error)
    });
  }

  selecionarDia(dia: Dia) {
    this.diaSelecionado = dia;
    this.carregarHorarios(dia.id);
    this.checkinForm.patchValue({ horario_id: '' });
  }

  carregarHorarios(diaId: number) {
    this.loadingHorarios = true;
    this.diaService.getHorarios(diaId).subscribe({
      next: (response: { dia: Dia; horarios: Horario[] }) => {
        this.horarios = response.horarios;
        this.loadingHorarios = false;
      },
      error: (error: unknown) => {
        console.error('Erro ao carregar horários:', error);
        this.loadingHorarios = false;
      }
    });
  }

  filtrarAlunos(event: any) {
    const termo = event.target.value.toLowerCase();
    this.searchTerm = termo;
    
    if (!termo) {
      this.alunosFiltrados = this.alunos;
    } else {
      this.alunosFiltrados = this.alunos.filter(aluno =>
        aluno.nome.toLowerCase().includes(termo) ||
        aluno.email.toLowerCase().includes(termo)
      );
    }
  }

  selecionarAluno(aluno: AlunoAdmin) {
    this.checkinForm.patchValue({ usuario_id: aluno.id });
  }

  registrarCheckin() {
    if (this.checkinForm.invalid) {
      alert('Selecione um aluno e um horário');
      return;
    }

    const data = this.checkinForm.value;
    
    this.adminService.registrarCheckinAluno(data).subscribe({
      next: (response) => {
        alert('Check-in registrado com sucesso!');
        this.checkinForm.reset();
        this.diaSelecionado = null;
        this.horarios = [];
      },
      error: (error) => {
        const mensagem = error.error?.error || 'Erro ao registrar check-in';
        alert(mensagem);
      }
    });
  }

  getAlunoSelecionado(): AlunoAdmin | null {
    const id = this.checkinForm.get('usuario_id')?.value;
    return this.alunos.find(a => a.id === id) || null;
  }

  getHorarioSelecionado(): Horario | null {
    const id = this.checkinForm.get('horario_id')?.value;
    return this.horarios.find(h => h.id === id) || null;
  }

  formatarData(data: string): string {
    return new Date(data).toLocaleDateString('pt-BR', {
      weekday: 'long',
      day: '2-digit',
      month: 'long'
    });
  }

  getDiaSemanaCurto(data: string): string {
    const dias = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB'];
    return dias[new Date(data + 'T00:00:00').getDay()];
  }

  formatarDiaNumero(data: string): string {
    return new Date(data + 'T00:00:00').getDate().toString().padStart(2, '0');
  }
}
