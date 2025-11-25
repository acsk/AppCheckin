import { Component, OnInit, Inject, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { AlunoAdmin, Plano } from '../../models/api.models';
import { AdminService } from '../../services/admin.service';

@Component({
  selector: 'app-matricula-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule
  ],
  template: `
    <h2 mat-dialog-title>
      <mat-icon>school</mat-icon>
      Matricular Aluno
    </h2>

    <mat-dialog-content>
      <div class="aluno-info">
        <div class="aluno-avatar">{{ data.aluno.nome.charAt(0).toUpperCase() }}</div>
        <div class="aluno-details">
          <strong>{{ data.aluno.nome }}</strong>
          <p>{{ data.aluno.email }}</p>
        </div>
      </div>

      <form [formGroup]="matriculaForm" id="matriculaForm">
        <mat-form-field appearance="outline">
          <mat-label>Plano</mat-label>
          <mat-icon matPrefix>credit_card</mat-icon>
          <mat-select formControlName="plano_id" required>
            <mat-option [value]="null" disabled>
              {{ carregandoPlanos ? 'Carregando...' : 'Selecione um plano' }}
            </mat-option>
            <mat-option *ngFor="let plano of planos" [value]="plano.id">
              {{ plano.nome }} - R$ {{ (+plano.valor).toFixed(2) }} ({{ plano.duracao_dias }} dias)
            </mat-option>
          </mat-select>
          <mat-error *ngIf="matriculaForm.get('plano_id')?.hasError('required')">
            Selecione um plano
          </mat-error>
        </mat-form-field>

        <mat-form-field appearance="outline">
          <mat-label>Data de Início</mat-label>
          <mat-icon matPrefix>event</mat-icon>
          <input matInput type="date" formControlName="data_inicio" required>
          <mat-hint>Data em que o plano começa a valer</mat-hint>
        </mat-form-field>

        <mat-form-field appearance="outline" *ngIf="planoSelecionado">
          <mat-label>Vencimento</mat-label>
          <mat-icon matPrefix>event_available</mat-icon>
          <input matInput [value]="calcularVencimento()" disabled>
          <mat-hint>Calculado automaticamente</mat-hint>
        </mat-form-field>

        <mat-form-field appearance="outline">
          <mat-label>Tipo de Matrícula</mat-label>
          <mat-icon matPrefix>label</mat-icon>
          <mat-select formControlName="motivo">
            <mat-option value="nova">Nova Matrícula</mat-option>
            <mat-option value="renovacao">Renovação</mat-option>
            <mat-option value="upgrade">Upgrade de Plano</mat-option>
            <mat-option value="downgrade">Downgrade de Plano</mat-option>
          </mat-select>
        </mat-form-field>

        <mat-form-field appearance="outline">
          <mat-label>Observações</mat-label>
          <mat-icon matPrefix>notes</mat-icon>
          <textarea matInput formControlName="observacoes" rows="3" placeholder="Informações adicionais (opcional)"></textarea>
        </mat-form-field>
      </form>
    </mat-dialog-content>

    <mat-dialog-actions align="end">
      <button mat-button type="button" (click)="fechar()">Cancelar</button>
      <button mat-raised-button color="primary" type="button" (click)="salvar()" [disabled]="matriculaForm.invalid || carregandoPlanos">
        <mat-icon>check</mat-icon>
        Confirmar Matrícula
      </button>
    </mat-dialog-actions>
  `,
  styles: [`
    h2[mat-dialog-title] {
      display: flex;
      align-items: center;
      gap: 16px;
      margin: 0;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    mat-dialog-content {
      min-width: 560px;
      max-height: 70vh;
      overflow-y: auto;
    }

    .aluno-info {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 18px;
      background: #f0fdf4;
      border-left: 4px solid #10b981;
      border-radius: 10px;
      margin-bottom: 28px;
    }

    .aluno-avatar {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: linear-gradient(135deg, #10b981, #059669);
      color: white;
      display: grid;
      place-items: center;
      font-size: 28px;
      font-weight: bold;
      flex-shrink: 0;
    }

    .aluno-details strong {
      display: block;
      font-size: 17px;
      color: #166534;
      margin-bottom: 6px;
      font-weight: 600;
    }

    .aluno-details p {
      margin: 0;
      font-size: 15px;
      color: #15803d;
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
export class MatriculaDialogComponent implements OnInit {
  private fb = inject(FormBuilder);
  private adminService = inject(AdminService);

  matriculaForm!: FormGroup;
  planos: Plano[] = [];
  carregandoPlanos = false;
  planoSelecionado: Plano | null = null;

  constructor(
    private dialogRef: MatDialogRef<MatriculaDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: { aluno: AlunoAdmin }
  ) {}

  ngOnInit(): void {
    this.inicializarFormulario();
    this.observarMudancaPlano();
    this.carregarPlanos();
  }

  inicializarFormulario(): void {
    const hoje = new Date().toISOString().split('T')[0];
    
    this.matriculaForm = this.fb.group({
      plano_id: [null, Validators.required],
      data_inicio: [hoje, Validators.required],
      motivo: ['nova'],
      observacoes: ['']
    });
  }

  observarMudancaPlano(): void {
    this.matriculaForm.get('plano_id')?.valueChanges.subscribe(planoId => {
      this.planoSelecionado = this.planos.find(p => p.id === planoId) || null;
    });
  }

  carregarPlanos(): void {
    this.carregandoPlanos = true;
    this.adminService.listarPlanos(true).subscribe({
      next: (response) => {
        this.planos = response.planos;
        this.carregandoPlanos = false;
      },
      error: (error) => {
        console.error('Erro ao carregar planos:', error);
        this.carregandoPlanos = false;
      }
    });
  }

  calcularVencimento(): string {
    if (!this.planoSelecionado) return '';
    
    const dataInicio = this.matriculaForm.get('data_inicio')?.value;
    if (!dataInicio) return '';

    const inicio = new Date(dataInicio);
    const vencimento = new Date(inicio);
    vencimento.setDate(vencimento.getDate() + this.planoSelecionado.duracao_dias);
    
    return vencimento.toLocaleDateString('pt-BR');
  }

  fechar(): void {
    this.dialogRef.close();
  }

  salvar(): void {
    if (this.matriculaForm.invalid) {
      Object.keys(this.matriculaForm.controls).forEach(key => {
        this.matriculaForm.get(key)?.markAsTouched();
      });
      return;
    }

    const dados = {
      usuario_id: this.data.aluno.id,
      ...this.matriculaForm.value
    };

    this.dialogRef.close(dados);
  }
}
