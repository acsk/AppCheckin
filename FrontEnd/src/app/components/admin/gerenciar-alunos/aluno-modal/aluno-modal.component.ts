import { Component, OnInit, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule, ModalController } from '@ionic/angular';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { AlunoAdmin, Plano } from '../../../../models/api.models';

@Component({
  selector: 'app-aluno-modal',
  standalone: true,
  imports: [CommonModule, IonicModule, ReactiveFormsModule],
  template: `
    <ion-header class="modal-header">
      <ion-toolbar>
        <div class="modal-title-group">
          <div class="pill-icon">
            <ion-icon name="people"></ion-icon>
          </div>
          <div>
            <p class="eyebrow">{{ modoEdicao ? 'Editar aluno' : 'Cadastro rápido' }}</p>
            <ion-title>{{ modoEdicao ? 'Editar Aluno' : 'Novo Aluno' }}</ion-title>
          </div>
        </div>
        <ion-buttons slot="end">
          <ion-button fill="clear" (click)="fechar()">
            <ion-icon name="close"></ion-icon>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content class="modal-body">
      <div class="modal-shell form-shell">
        <div *ngIf="erroMensagem" class="error-alert">
          <ion-icon name="alert-circle"></ion-icon>
          {{ erroMensagem }}
        </div>

        <form [formGroup]="alunoForm" (ngSubmit)="salvar()" class="form-panel form-grid">
          <ion-list>
            <ion-item class="form-item">
              <ion-label position="stacked">Nome *</ion-label>
              <ion-input formControlName="nome" type="text" placeholder="Digite o nome completo"></ion-input>
            </ion-item>
            <ion-text color="danger" *ngIf="alunoForm.get('nome')?.invalid && alunoForm.get('nome')?.touched">
              <p class="error-message">Nome é obrigatório (mínimo 3 caracteres)</p>
            </ion-text>

            <ion-item class="form-item">
              <ion-label position="stacked">Email *</ion-label>
              <ion-input formControlName="email" type="email" placeholder="Digite o email"></ion-input>
            </ion-item>
            <ion-text color="danger" *ngIf="alunoForm.get('email')?.invalid && alunoForm.get('email')?.touched">
              <p class="error-message">Email inválido</p>
            </ion-text>

            <ion-item class="form-item">
              <ion-label position="stacked">Senha {{ modoEdicao ? '(deixe em branco para não alterar)' : '*' }}</ion-label>
              <ion-input formControlName="senha" type="password" placeholder="Digite a senha"></ion-input>
            </ion-item>
            <ion-text color="danger" *ngIf="alunoForm.get('senha')?.invalid && alunoForm.get('senha')?.touched">
              <p class="error-message">Senha deve ter no mínimo 6 caracteres</p>
            </ion-text>

            <ion-item class="form-item">
              <ion-label position="stacked">Plano</ion-label>
              <ion-select formControlName="plano_id" placeholder="Selecione um plano">
                <ion-select-option [value]="null">Sem plano</ion-select-option>
                <ion-select-option *ngFor="let plano of planos" [value]="plano.id">
                  {{ plano.nome }} - R$ {{ plano.valor }}
                </ion-select-option>
              </ion-select>
            </ion-item>

            <ion-item class="form-item" *ngIf="alunoForm.get('plano_id')?.value">
              <ion-label position="stacked">Data de vencimento</ion-label>
              <ion-input formControlName="data_vencimento_plano" type="date"></ion-input>
            </ion-item>
          </ion-list>

          <ion-button 
            class="submit-btn form-button-primary" 
            expand="block" 
            type="submit" 
            [disabled]="alunoForm.invalid || salvando"
          >
            <ion-spinner *ngIf="salvando" name="crescent"></ion-spinner>
            <span *ngIf="!salvando">{{ modoEdicao ? 'Atualizar' : 'Criar' }} Aluno</span>
          </ion-button>
        </form>
      </div>
    </ion-content>
  `,
  styles: [`
    :host {
      color: #e7ecf5;
    }

    .modal-header {
      --background: transparent;
      --color: #e2e8f0;
      padding: 6px 10px 0 10px;

      ion-toolbar {
        --background: #0c1429;
        --min-height: 60px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        border-radius: 14px;
        padding: 8px 12px;
      }

      ion-button {
        --color: #e2e8f0;
      }
    }

    .modal-title-group {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .pill-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      background: rgba(37, 99, 235, 0.16);
      border: 1px solid rgba(37, 99, 235, 0.35);
      color: #bfdbfe;
    }

    .eyebrow {
      margin: 0;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      font-size: 10px;
      color: #94a3b8;
    }

    ion-title {
      padding-inline-start: 0;
      font-weight: 800;
      font-size: 20px;
      color: #e2e8f0;
    }

    .modal-body {
      --background: #0b1224;
      --padding-bottom: 0;
      padding: 0 10px 6px 10px;
    }

    .modal-shell {
      margin: 10px 4px 4px 4px;
      padding: 12px 12px 12px 12px;
      background: rgba(12, 18, 36, 0.9);
      border: 1px solid rgba(148, 163, 184, 0.16);
      border-radius: 14px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.38);
      backdrop-filter: blur(10px);
      color: #e2e8f0;
    }

    form.form-grid {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    ion-list {
      background: transparent;
      padding: 2px 0 0 0;
      display: grid;
      gap: 6px;
    }

    ion-item {
      --color: #e2e8f0;
      --padding-top: 8px;
      --padding-bottom: 8px;
      border-radius: 10px;
      backdrop-filter: blur(6px);
    }

    ion-item:hover {
      border-color: rgba(59, 130, 246, 0.4);
      --background: rgba(59, 130, 246, 0.04);
    }

    ion-item ion-label {
      color: #cbd5e1;
      letter-spacing: 0.01em;
      font-weight: 600;
      font-size: 13px;
    }

    ion-item ion-input,
    ion-item ion-select {
      color: #e7ecf5;
      --placeholder-color: #9ca3af;
      font-size: 15px;
    }

    ion-item ion-input {
      --highlight-color-focused: rgba(59, 130, 246, 0.8);
      --highlight-height: 2px;
    }

    ion-select {
      --placeholder-color: #9ca3af;
    }

    .error-alert {
      background: rgba(239, 68, 68, 0.92);
      color: #fff;
      padding: 10px 12px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 10px;
      box-shadow: 0 10px 28px rgba(239, 68, 68, 0.25);
    }

    .error-message {
      font-size: 12px;
      margin: 3px 0 2px 12px;
      color: #fca5a5;
    }

    .submit-btn {
      margin-top: 8px;
      --background: linear-gradient(120deg, #0ea5e9, #2563eb);
      --background-activated: linear-gradient(120deg, #0284c7, #1d4ed8);
      --border-radius: 10px;
      --padding-top: 12px;
      --padding-bottom: 12px;
      letter-spacing: 0.08em;
      font-weight: 700;
    }

    ion-spinner {
      width: 16px;
      height: 16px;
      --color: #e2e8f0;
      margin-right: 6px;
    }
  `]
})
export class AlunoModalComponent implements OnInit {
  @Input() aluno?: AlunoAdmin;
  @Input() planos: Plano[] = [];

  alunoForm!: FormGroup;
  modoEdicao = false;
  salvando = false;
  erroMensagem = '';

  constructor(
    private modalController: ModalController,
    private fb: FormBuilder
  ) {}

  ngOnInit() {
    this.modoEdicao = !!this.aluno;
    this.inicializarFormulario();
  }

  inicializarFormulario(): void {
    this.alunoForm = this.fb.group({
      nome: ['', [Validators.required, Validators.minLength(3)]],
      email: ['', [Validators.required, Validators.email]],
      senha: ['', [Validators.minLength(6)]],
      plano_id: [null],
      data_vencimento_plano: ['']
    });

    if (this.aluno) {
      // Modo edição - senha é opcional
      this.alunoForm.patchValue({
        nome: this.aluno.nome,
        email: this.aluno.email,
        senha: '',
        plano_id: this.aluno.plano_id,
        data_vencimento_plano: this.aluno.data_vencimento_plano || ''
      });
      this.alunoForm.get('senha')?.clearValidators();
    } else {
      // Modo criação - senha é obrigatória
      this.alunoForm.get('senha')?.setValidators([Validators.required, Validators.minLength(6)]);
    }
    
    this.alunoForm.get('senha')?.updateValueAndValidity();
  }

  async fechar() {
    await this.modalController.dismiss();
  }

  async salvar() {
    if (this.alunoForm.invalid) {
      Object.keys(this.alunoForm.controls).forEach(key => {
        this.alunoForm.get(key)?.markAsTouched();
      });
      this.erroMensagem = 'Por favor, preencha todos os campos obrigatórios corretamente.';
      return;
    }

    const dados = this.alunoForm.value;
    
    // Remove senha vazia no modo edição
    if (this.modoEdicao && !dados.senha) {
      delete dados.senha;
    }

    // Remove data de vencimento vazia
    if (!dados.data_vencimento_plano) {
      delete dados.data_vencimento_plano;
    }

    await this.modalController.dismiss({
      dados,
      modoEdicao: this.modoEdicao,
      alunoId: this.aluno?.id
    }, 'confirm');
  }

  setErro(mensagem: string) {
    this.erroMensagem = mensagem;
  }

  setSalvando(estado: boolean) {
    this.salvando = estado;
  }
}
