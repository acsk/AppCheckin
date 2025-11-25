import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';

@Component({
  selector: 'app-cancelar-conta-dialog',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatDialogModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule
  ],
  template: `
    <h2 mat-dialog-title>Cancelar Conta</h2>
    <mat-dialog-content>
      <p class="dialog-message">
        Tem certeza que deseja cancelar a conta de <strong>{{ data.aluno }}</strong>?
      </p>
      <p class="dialog-submessage">
        Valor: <strong>R$ {{ data.valor }}</strong>
      </p>

      <mat-form-field appearance="outline" class="full-width">
        <mat-label>Motivo do Cancelamento</mat-label>
        <textarea matInput [(ngModel)]="observacoes" rows="4" placeholder="Descreva o motivo..." required></textarea>
      </mat-form-field>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button (click)="onCancel()">Não</button>
      <button mat-raised-button color="warn" (click)="onConfirm()" [disabled]="!observacoes.trim()">
        Sim, Cancelar
      </button>
    </mat-dialog-actions>
  `,
  styles: [`
    .dialog-message {
      margin-bottom: 8px;
      font-size: 16px;
    }

    .dialog-submessage {
      margin-bottom: 20px;
      color: #666;
      font-size: 14px;
    }

    .full-width {
      width: 100%;
    }

    mat-dialog-content {
      padding: 20px 24px;
      min-width: 400px;
    }

    mat-dialog-actions {
      padding: 12px 24px;
    }
  `]
})
export class CancelarContaDialogComponent {
  observacoes: string = '';

  constructor(
    private dialogRef: MatDialogRef<CancelarContaDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: { aluno: string; valor: string }
  ) {}

  onCancel(): void {
    this.dialogRef.close();
  }

  onConfirm(): void {
    this.dialogRef.close(this.observacoes);
  }
}
