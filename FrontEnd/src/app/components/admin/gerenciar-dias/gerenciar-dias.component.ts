import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import {
  IonHeader, IonToolbar, IonTitle, IonContent, IonItem,
  IonLabel, IonButton, IonButtons, IonBackButton, IonIcon,
  IonModal, IonInput,
  IonToggle, IonSpinner, IonBadge, IonList
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { add, create, trash, calendar, checkmark, close } from 'ionicons/icons';
import { DiaService } from '../../../services/dia.service';
import { Dia } from '../../../models/api.models';

@Component({
  selector: 'app-gerenciar-dias',
  templateUrl: './gerenciar-dias.component.html',
  styleUrls: ['./gerenciar-dias.component.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    IonHeader, IonToolbar, IonTitle, IonContent, IonItem,
    IonLabel, IonButton, IonButtons, IonBackButton, IonIcon,
    IonModal, IonInput,
    IonToggle, IonSpinner, IonBadge, IonList
  ]
})
export class GerenciarDiasComponent implements OnInit {
  dias: Dia[] = [];
  loading = false;
  modalAberto = false;
  diaForm: FormGroup;
  diaSelecionado: Dia | null = null;
  modoEdicao = false;

  constructor(
    private diaService: DiaService,
    private fb: FormBuilder
  ) {
    addIcons({ add, create, trash, calendar, checkmark, close });

    this.diaForm = this.fb.group({
      data: ['', Validators.required],
      ativo: [true]
    });
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
      },
      error: (error) => {
        console.error('Erro ao carregar dias:', error);
        this.loading = false;
      }
    });
  }

  abrirModal(dia?: Dia) {
    if (dia) {
      this.modoEdicao = true;
      this.diaSelecionado = dia;
      this.diaForm.patchValue({
        data: dia.data,
        ativo: dia.ativo
      });
    } else {
      this.modoEdicao = false;
      this.diaSelecionado = null;
      this.diaForm.reset({ ativo: true });
    }
    this.modalAberto = true;
  }

  fecharModal() {
    this.modalAberto = false;
    this.diaForm.reset({ ativo: true });
  }

  salvarDia() {
    if (this.diaForm.invalid) {
      return;
    }

    const data = this.diaForm.value;
    
    if (this.modoEdicao && this.diaSelecionado) {
      // TODO: Implementar atualização de dia
      console.log('Atualizar dia:', data);
      alert('Funcionalidade de edição será implementada');
      this.fecharModal();
    } else {
      // TODO: Implementar criação de dia
      console.log('Criar dia:', data);
      alert('Funcionalidade de criação será implementada');
      this.fecharModal();
    }
  }

  desativar(dia: Dia) {
    if (confirm(`Desativar dia ${this.formatarData(dia.data)}?`)) {
      // TODO: Implementar desativação de dia
      console.log('Desativar dia:', dia);
      alert('Funcionalidade de desativação será implementada');
    }
  }

  formatarData(data: string): string {
    return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR', {
      weekday: 'long',
      day: '2-digit',
      month: 'long',
      year: 'numeric'
    });
  }

  getDiaSemana(data: string): string {
    const dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    return dias[new Date(data + 'T00:00:00').getDay()];
  }
}
