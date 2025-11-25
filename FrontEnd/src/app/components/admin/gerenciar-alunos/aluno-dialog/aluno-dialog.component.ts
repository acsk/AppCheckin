import { Component, Inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { AlunoAdmin } from '../../../../models/api.models';

@Component({
  selector: 'app-aluno-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule
  ],
  template: `
    <h2 mat-dialog-title>
      <mat-icon>{{ modoEdicao ? 'edit' : 'person_add' }}</mat-icon>
      {{ modoEdicao ? 'Editar Aluno' : 'Novo Aluno' }}
    </h2>

    <mat-dialog-content>
      <form [formGroup]="alunoForm" id="alunoForm">
        <mat-form-field appearance="outline">
          <mat-label>Nome</mat-label>
          <mat-icon matPrefix>person</mat-icon>
          <input matInput formControlName="nome" placeholder="Digite o nome completo" required />
          <mat-error *ngIf="alunoForm.get('nome')?.hasError('required')">
            Nome é obrigatório
          </mat-error>
          <mat-error *ngIf="alunoForm.get('nome')?.hasError('minlength')">
            Nome deve ter no mínimo 3 caracteres
          </mat-error>
        </mat-form-field>

        <mat-form-field appearance="outline">
          <mat-label>Email</mat-label>
          <mat-icon matPrefix>email</mat-icon>
          <input matInput formControlName="email" type="email" placeholder="Digite o email" required />
          <mat-error *ngIf="alunoForm.get('email')?.hasError('required')">
            Email é obrigatório
          </mat-error>
          <mat-error *ngIf="alunoForm.get('email')?.hasError('email')">
            Email inválido
          </mat-error>
        </mat-form-field>

        <mat-form-field appearance="outline">
          <mat-label>Senha {{ modoEdicao ? '(deixe em branco para não alterar)' : '' }}</mat-label>
          <mat-icon matPrefix>lock</mat-icon>
          <input matInput formControlName="senha" type="password" placeholder="Digite a senha" [required]="!modoEdicao" />
          <mat-error *ngIf="alunoForm.get('senha')?.hasError('required')">
            Senha é obrigatória
          </mat-error>
          <mat-error *ngIf="alunoForm.get('senha')?.hasError('minlength')">
            Senha deve ter no mínimo 6 caracteres
          </mat-error>
        </mat-form-field>
      </form>
    </mat-dialog-content>

    <mat-dialog-actions align="end">
      <button mat-button type="button" (click)="fechar()">Cancelar</button>
      <button mat-raised-button color="primary" type="button" (click)="salvar()" [disabled]="alunoForm.invalid">
        <mat-icon>{{ modoEdicao ? 'check' : 'save' }}</mat-icon>
        {{ modoEdicao ? 'Atualizar' : 'Criar' }}
      </button>
    </mat-dialog-actions>
  `,
  styles: [`
    h2[mat-dialog-title] {
      display: flex;
      align-items: center;
      gap: 16px;
      margin: 0;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    mat-dialog-content {
      min-width: 500px;
    }

    form {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    mat-form-field {
      width: 100%;
    }

    mat-dialog-actions {
      border-top: 1px solid #e0e0e0;
    }
  `]
})
export class AlunoDialogComponent implements OnInit {
  alunoForm!: FormGroup;
  modoEdicao = false;

  constructor(
    private fb: FormBuilder,
    private dialogRef: MatDialogRef<AlunoDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: { aluno?: AlunoAdmin }
  ) {}

  ngOnInit(): void {
    this.modoEdicao = !!this.data.aluno;
    this.inicializarFormulario();
  }

  inicializarFormulario(): void {
    this.alunoForm = this.fb.group({
      nome: ['', [Validators.required, Validators.minLength(3)]],
      email: ['', [Validators.required, Validators.email]],
      senha: ['', [Validators.minLength(6)]]
    });

    if (this.data.aluno) {
      // Modo edição - senha é opcional
      this.alunoForm.patchValue({
        nome: this.data.aluno.nome,
        email: this.data.aluno.email,
        senha: ''
      });
      this.alunoForm.get('senha')?.clearValidators();
      this.alunoForm.get('senha')?.setValidators([Validators.minLength(6)]);
    } else {
      // Modo criação - senha é obrigatória
      this.alunoForm.get('senha')?.setValidators([Validators.required, Validators.minLength(6)]);
    }
    
    this.alunoForm.get('senha')?.updateValueAndValidity();
  }

  fechar(): void {
    this.dialogRef.close();
  }

  salvar(): void {
    if (this.alunoForm.invalid) {
      Object.keys(this.alunoForm.controls).forEach(key => {
        this.alunoForm.get(key)?.markAsTouched();
      });
      return;
    }

    const dados = { ...this.alunoForm.value };
    
    // Remove senha vazia no modo edição
    if (this.modoEdicao && !dados.senha) {
      delete dados.senha;
    }

    this.dialogRef.close({
      dados,
      modoEdicao: this.modoEdicao,
      alunoId: this.data.aluno?.id
    });
  }
}
