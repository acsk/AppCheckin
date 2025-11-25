import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { AlunoAdmin, Plano } from '../../../../models/api.models';

@Component({
  selector: 'app-aluno-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="modal-overlay">
      <div class="modal-card card">
        <div class="modal-header">
          <div class="modal-title-group">
            <div class="pill-icon">👥</div>
            <div>
              <p class="eyebrow">{{ modoEdicao ? 'Editar aluno' : 'Cadastro rápido' }}</p>
              <h2 class="title">{{ modoEdicao ? 'Editar Aluno' : 'Novo Aluno' }}</h2>
              <p class="muted">Preencha os dados do aluno e salve para continuar.</p>
            </div>
          </div>
          <button class="icon-btn" type="button" (click)="fechar()">×</button>
        </div>

        <div class="modal-body form-shell">
          <div *ngIf="erroMensagem" class="banner danger">
            {{ erroMensagem }}
          </div>

          <form [formGroup]="alunoForm" (ngSubmit)="salvar()" class="form-grid">
            <div class="form-field full">
              <label class="form-label" for="nome">Nome *</label>
              <input id="nome" class="form-control" formControlName="nome" type="text" placeholder="Digite o nome completo" />
              <p class="field-error" *ngIf="alunoForm.get('nome')?.invalid && alunoForm.get('nome')?.touched">
                Nome é obrigatório (mínimo 3 caracteres)
              </p>
            </div>

            <div class="form-field full">
              <label class="form-label" for="email">Email *</label>
              <input id="email" class="form-control" formControlName="email" type="email" placeholder="Digite o email" />
              <p class="field-error" *ngIf="alunoForm.get('email')?.invalid && alunoForm.get('email')?.touched">
                Email inválido
              </p>
            </div>

            <div class="form-field full">
              <label class="form-label" for="senha">Senha {{ modoEdicao ? '(deixe em branco para não alterar)' : '*' }}</label>
              <input id="senha" class="form-control" formControlName="senha" type="password" placeholder="Digite a senha" />
              <p class="field-error" *ngIf="alunoForm.get('senha')?.invalid && alunoForm.get('senha')?.touched">
                Senha deve ter no mínimo 6 caracteres
              </p>
            </div>

            <div class="form-actions full">
              <button type="button" class="btn btn-ghost" (click)="fechar()">Cancelar</button>
              <button type="submit" class="btn btn-primary" [disabled]="alunoForm.invalid || salvando">
                <span *ngIf="salvando">Salvando...</span>
                <span *ngIf="!salvando">{{ modoEdicao ? 'Atualizar' : 'Criar' }} Aluno</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  `,
  styles: [`
    :host {
      position: fixed;
      inset: 0;
      display: block;
      z-index: 5000;
    }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.45);
      display: grid;
      place-items: center;
      padding: 18px;
    }

    .modal-card {
      width: min(720px, 100%);
      border-radius: 18px;
      padding: 0;
      overflow: hidden;
    }

    .modal-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      padding: 16px 18px 10px;
      border-bottom: 1px solid var(--border);
      background: linear-gradient(120deg, #f1f5f9, #e0f2fe);
    }

    .modal-title-group {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .pill-icon {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: #dbeafe;
      color: #1d4ed8;
      font-size: 18px;
      border: 1px solid #bfdbfe;
    }

    .eyebrow {
      margin: 0;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-size: 11px;
      color: var(--text-soft);
    }

    .title {
      margin: 0;
      font-size: 22px;
      line-height: 1.2;
      color: var(--text-strong);
    }

    .icon-btn {
      background: transparent;
      border: 1px solid var(--border);
      width: 36px;
      height: 36px;
      border-radius: 12px;
      font-size: 20px;
      color: var(--text-strong);
      cursor: pointer;
      transition: var(--transition);
    }

    .icon-btn:hover {
      border-color: var(--brand-primary);
      color: var(--brand-primary);
    }

    .modal-body {
      padding: 16px 18px 20px;
      background: var(--surface);
    }

    .banner {
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: #fff;
      font-weight: 600;
    }

    .banner.danger {
      background: #fef2f2;
      border-color: rgba(220, 38, 38, 0.25);
      color: #991b1b;
    }

    .form-field.full {
      grid-column: 1 / -1;
    }

    .form-actions.full {
      grid-column: 1 / -1;
    }

    .field-hint {
      margin: 4px 0 0;
      font-size: 12px;
      color: var(--text-soft);
    }
  `]
})
export class AlunoModalComponent implements OnInit {
  @Input() aluno?: AlunoAdmin;
  @Output() closed = new EventEmitter<void>();
  @Output() saved = new EventEmitter<{ dados: any; modoEdicao: boolean; alunoId?: number }>();

  alunoForm!: FormGroup;
  modoEdicao = false;
  salvando = false;
  erroMensagem = '';

  constructor(private fb: FormBuilder) {}

  ngOnInit() {
    this.modoEdicao = !!this.aluno;
    this.inicializarFormulario();
  }

  inicializarFormulario(): void {
    this.alunoForm = this.fb.group({
      nome: ['', [Validators.required, Validators.minLength(3)]],
      email: ['', [Validators.required, Validators.email]],
      senha: ['', [Validators.minLength(6)]]
    });

    if (this.aluno) {
      // Modo edição - senha é opcional
      this.alunoForm.patchValue({
        nome: this.aluno.nome,
        email: this.aluno.email,
        senha: ''
      });
      this.alunoForm.get('senha')?.clearValidators();
    } else {
      // Modo criação - senha é obrigatória
      this.alunoForm.get('senha')?.setValidators([Validators.required, Validators.minLength(6)]);
    }
    
    this.alunoForm.get('senha')?.updateValueAndValidity();
  }

  fechar() {
    this.closed.emit();
  }

  salvar() {
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

    this.saved.emit({
      dados,
      modoEdicao: this.modoEdicao,
      alunoId: this.aluno?.id
    });
  }

  setErro(mensagem: string) {
    this.erroMensagem = mensagem;
  }

  setSalvando(estado: boolean) {
    this.salvando = estado;
  }
}
