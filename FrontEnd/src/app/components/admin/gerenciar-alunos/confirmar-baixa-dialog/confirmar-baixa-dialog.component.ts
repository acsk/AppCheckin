import { Component, Inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { FormaPagamento } from '../../../../models/api.models';

@Component({
  selector: 'app-confirmar-baixa-dialog',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatSelectModule
  ],
  template: `
    <h2 mat-dialog-title>
      <mat-icon class="success-icon">payments</mat-icon>
      Dar Baixa Imediata
    </h2>

    <mat-dialog-content>
      <p class="message">
        Matrícula realizada com sucesso!
      </p>
      <p class="info">
        <strong>Valor:</strong> R$ {{ valor }}
      </p>
      
      <mat-form-field appearance="outline" class="forma-pagamento-field">
        <mat-label>Forma de Pagamento</mat-label>
        <mat-icon matPrefix>payment</mat-icon>
        <mat-select [(ngModel)]="formaPagamentoSelecionada" required>
          <mat-option *ngFor="let forma of data.formasPagamento" [value]="forma.id">
            {{ forma.nome }}
            <span *ngIf="+forma.percentual_desconto > 0" class="desconto-info">
              ({{ forma.percentual_desconto }}% desconto)
            </span>
          </mat-option>
        </mat-select>
      </mat-form-field>

      <p class="question">
        Deseja dar baixa na primeira mensalidade agora?
      </p>
    </mat-dialog-content>

    <mat-dialog-actions align="end">
      <button mat-button type="button" (click)="fechar()">Não</button>
      <button mat-raised-button color="primary" type="button" (click)="confirmar()" [disabled]="!formaPagamentoSelecionada">
        <mat-icon>check_circle</mat-icon>
        Sim, dar baixa
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
      min-width: 520px;
    }

    .message {
      margin: 0 0 20px;
      font-size: 17px;
      font-weight: 500;
      color: #059669;
    }

    .info {
      margin: 0 0 24px;
      padding: 16px;
      background: #f0fdf4;
      border-left: 4px solid #10b981;
      border-radius: 8px;
      font-size: 16px;
    }

    .forma-pagamento-field {
      width: 100%;
      margin-bottom: 24px;
    }

    .desconto-info {
      color: #059669;
      font-size: 13px;
      margin-left: 6px;
      font-weight: 500;
    }

    .question {
      margin: 0;
      font-size: 16px;
      line-height: 1.6;
    }

    mat-dialog-actions {
      border-top: 1px solid #e0e0e0;
    }
  `]
})
export class ConfirmarBaixaDialogComponent implements OnInit {
  valor: string;
  formaPagamentoSelecionada?: number;

  constructor(
    private dialogRef: MatDialogRef<ConfirmarBaixaDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: { 
      valor: number;
      formasPagamento: FormaPagamento[];
    }
  ) {
    this.valor = this.data.valor.toFixed(2);
  }

  ngOnInit(): void {
    // Seleciona Pix por padrão se existir
    const pix = this.data.formasPagamento.find(f => f.nome.toLowerCase() === 'pix');
    if (pix) {
      this.formaPagamentoSelecionada = pix.id;
    }
  }

  fechar(): void {
    this.dialogRef.close(null);
  }

  confirmar(): void {
    if (!this.formaPagamentoSelecionada) return;
    this.dialogRef.close(this.formaPagamentoSelecionada);
  }
}
