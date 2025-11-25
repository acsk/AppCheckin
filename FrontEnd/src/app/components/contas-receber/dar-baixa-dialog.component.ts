import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { ConfigService } from '../../services/config.service';
import { FormaPagamento } from '../../models/api.models';

@Component({
  selector: 'app-dar-baixa-dialog',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatDialogModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule
  ],
  template: `
    <h2 mat-dialog-title>Confirmar Pagamento</h2>
    <mat-dialog-content>
      <p class="dialog-message">
        Confirmar pagamento de <strong>R$ {{ data.valor }}</strong> de <strong>{{ data.aluno }}</strong>?
      </p>

      <div class="form-fields">
        <mat-form-field appearance="outline" class="full-width">
          <mat-label>Forma de Pagamento</mat-label>
          <mat-select [(ngModel)]="formaPagamentoId" required>
            <mat-option *ngFor="let forma of formasPagamento" [value]="forma.id">
              {{ forma.nome }}
              <span *ngIf="parseFloat(forma.percentual_desconto) > 0" class="desconto-info">
                ({{ forma.percentual_desconto }}% desconto)
              </span>
            </mat-option>
          </mat-select>
        </mat-form-field>

        <mat-form-field appearance="outline" class="full-width">
          <mat-label>Data do Pagamento</mat-label>
          <input matInput type="date" [(ngModel)]="dataPagamento" readonly>
          <mat-hint>Data fixada como hoje</mat-hint>
        </mat-form-field>

        <mat-form-field appearance="outline" class="full-width">
          <mat-label>Observações</mat-label>
          <textarea matInput [(ngModel)]="observacoes" rows="3" placeholder="Informações adicionais (opcional)"></textarea>
        </mat-form-field>
      </div>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button (click)="onCancel()">Cancelar</button>
      <button mat-raised-button color="primary" (click)="onConfirm()" [disabled]="!formaPagamentoId">
        Confirmar Pagamento
      </button>
    </mat-dialog-actions>
  `,
  styles: [`
    .dialog-message {
      margin-bottom: 20px;
      font-size: 16px;
    }

    .form-fields {
      display: flex;
      flex-direction: column;
      gap: 16px;
      min-width: 400px;
    }

    .full-width {
      width: 100%;
    }

    .desconto-info {
      color: #f44336;
      font-size: 12px;
      margin-left: 8px;
    }

    mat-dialog-content {
      padding: 20px 24px;
    }

    mat-dialog-actions {
      padding: 12px 24px;
    }
  `]
})
export class DarBaixaDialogComponent {
  formaPagamentoId: number | null = null;
  dataPagamento: string;
  observacoes: string = '';
  formasPagamento: FormaPagamento[] = [];

  constructor(
    private dialogRef: MatDialogRef<DarBaixaDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: { conta: any; valor: string; aluno: string },
    private configService: ConfigService
  ) {
    this.dataPagamento = new Date().toISOString().split('T')[0];
    this.carregarFormasPagamento();
  }

  carregarFormasPagamento(): void {
    this.configService.listarFormasPagamento().subscribe({
      next: (formas) => {
        this.formasPagamento = formas;
        // Selecionar Pix como padrão
        const pix = formas.find(f => f.nome.toLowerCase() === 'pix');
        if (pix) {
          this.formaPagamentoId = pix.id;
        }
      },
      error: (error) => {
        console.error('Erro ao carregar formas de pagamento:', error);
      }
    });
  }

  parseFloat(value: string): number {
    return parseFloat(value);
  }

  onCancel(): void {
    this.dialogRef.close();
  }

  onConfirm(): void {
    this.dialogRef.close({
      forma_pagamento_id: this.formaPagamentoId,
      data_pagamento: this.dataPagamento,
      observacoes: this.observacoes || null
    });
  }
}
