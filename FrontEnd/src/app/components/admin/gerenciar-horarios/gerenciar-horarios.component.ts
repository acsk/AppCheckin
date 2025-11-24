import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import {
  IonHeader, IonToolbar, IonTitle, IonContent, IonItem,
  IonLabel, IonButton, IonButtons, IonBackButton, IonIcon, IonCard,
  IonCardHeader, IonCardTitle, IonCardContent, IonModal, IonInput,
  IonSelect, IonSelectOption, IonToggle, IonSpinner, IonBadge,
  IonGrid, IonRow, IonCol
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { add, create, trash, calendar, time, people, checkmark, close } from 'ionicons/icons';
import { AdminService } from '../../../services/admin.service';
import { PlanejamentoHorario, PlanejamentoRequest, GerarHorariosRequest } from '../../../models/api.models';

@Component({
  selector: 'app-gerenciar-horarios',
  templateUrl: './gerenciar-horarios.component.html',
  styleUrls: ['./gerenciar-horarios.component.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    IonHeader, IonToolbar, IonTitle, IonContent, IonItem,
    IonLabel, IonButton, IonButtons, IonBackButton, IonIcon, IonCard,
    IonCardHeader, IonCardTitle, IonCardContent, IonModal, IonInput,
    IonSelect, IonSelectOption, IonToggle, IonSpinner, IonBadge,
    IonGrid, IonRow, IonCol
  ]
})
export class GerenciarHorariosComponent implements OnInit {
  planejamentos: PlanejamentoHorario[] = [];
  loading = false;
  modalAberto = false;
  modalGerarAberto = false;
  planejamentoForm: FormGroup;
  gerarHorariosForm: FormGroup;
  planejamentoSelecionado: PlanejamentoHorario | null = null;
  modoEdicao = false;

  diasSemana = [
    { value: 'segunda', label: 'Segunda-feira' },
    { value: 'terca', label: 'Terça-feira' },
    { value: 'quarta', label: 'Quarta-feira' },
    { value: 'quinta', label: 'Quinta-feira' },
    { value: 'sexta', label: 'Sexta-feira' },
    { value: 'sabado', label: 'Sábado' },
    { value: 'domingo', label: 'Domingo' }
  ];

  constructor(
    private adminService: AdminService,
    private fb: FormBuilder
  ) {
    addIcons({ add, create, trash, calendar, time, people, checkmark, close });

    this.planejamentoForm = this.fb.group({
      titulo: ['', Validators.required],
      dia_semana: ['', Validators.required],
      horario_inicio: ['', Validators.required],
      horario_fim: ['', Validators.required],
      vagas: [10, [Validators.required, Validators.min(1)]],
      data_inicio: ['', Validators.required],
      data_fim: [''],
      ativo: [true]
    });

    this.gerarHorariosForm = this.fb.group({
      data_inicio: ['', Validators.required],
      data_fim: ['', Validators.required]
    });
  }

  ngOnInit() {
    this.carregarPlanejamentos();
  }

  carregarPlanejamentos() {
    this.loading = true;
    this.adminService.listarPlanejamentos(false).subscribe({
      next: (response) => {
        this.planejamentos = response.planejamentos;
        this.loading = false;
      },
      error: (error) => {
        console.error('Erro ao carregar planejamentos:', error);
        this.loading = false;
      }
    });
  }

  abrirModal(planejamento?: PlanejamentoHorario) {
    if (planejamento) {
      this.modoEdicao = true;
      this.planejamentoSelecionado = planejamento;
      this.planejamentoForm.patchValue({
        titulo: planejamento.titulo,
        dia_semana: planejamento.dia_semana,
        horario_inicio: planejamento.horario_inicio,
        horario_fim: planejamento.horario_fim,
        vagas: planejamento.vagas,
        data_inicio: planejamento.data_inicio,
        data_fim: planejamento.data_fim,
        ativo: planejamento.ativo
      });
    } else {
      this.modoEdicao = false;
      this.planejamentoSelecionado = null;
      this.planejamentoForm.reset({ vagas: 10, ativo: true });
    }
    this.modalAberto = true;
  }

  fecharModal() {
    this.modalAberto = false;
    this.planejamentoForm.reset({ vagas: 10, ativo: true });
  }

  salvarPlanejamento() {
    if (this.planejamentoForm.invalid) {
      return;
    }

    const data: PlanejamentoRequest = this.planejamentoForm.value;
    
    if (this.modoEdicao && this.planejamentoSelecionado) {
      this.adminService.atualizarPlanejamento(this.planejamentoSelecionado.id, data).subscribe({
        next: () => {
          this.carregarPlanejamentos();
          this.fecharModal();
        },
        error: (error) => console.error('Erro ao atualizar:', error)
      });
    } else {
      this.adminService.criarPlanejamento(data).subscribe({
        next: () => {
          this.carregarPlanejamentos();
          this.fecharModal();
        },
        error: (error) => console.error('Erro ao criar:', error)
      });
    }
  }

  abrirModalGerar(planejamento: PlanejamentoHorario) {
    this.planejamentoSelecionado = planejamento;
    this.gerarHorariosForm.reset();
    this.modalGerarAberto = true;
  }

  fecharModalGerar() {
    this.modalGerarAberto = false;
    this.gerarHorariosForm.reset();
  }

  gerarHorarios() {
    if (this.gerarHorariosForm.invalid || !this.planejamentoSelecionado) {
      return;
    }

    const data: GerarHorariosRequest = this.gerarHorariosForm.value;
    
    this.adminService.gerarHorarios(this.planejamentoSelecionado.id, data).subscribe({
      next: (response) => {
        console.log('Horários gerados:', response);
        alert(`${response.resultado.horarios_gerados} horários gerados com sucesso!`);
        this.fecharModalGerar();
      },
      error: (error) => {
        console.error('Erro ao gerar horários:', error);
        alert('Erro ao gerar horários');
      }
    });
  }

  desativar(planejamento: PlanejamentoHorario) {
    if (confirm(`Desativar planejamento "${planejamento.titulo}"?`)) {
      this.adminService.desativarPlanejamento(planejamento.id).subscribe({
        next: () => this.carregarPlanejamentos(),
        error: (error) => console.error('Erro ao desativar:', error)
      });
    }
  }

  getNomeDiaSemana(dia: string): string {
    return this.diasSemana.find(d => d.value === dia)?.label || dia;
  }

  formatarData(data: string | null): string {
    if (!data) return 'Indeterminado';
    return new Date(data).toLocaleDateString('pt-BR');
  }
}