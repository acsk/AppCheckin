import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { AlunoAdmin } from '../../../../models/api.models';

@Component({
  selector: 'app-confirmar-exclusao-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule
  ],
  template: `
    <h2 mat-dialog-title>
      <mat-icon class="warning-icon">warning</mat-icon>
      Confirmar Exclusão
    </h2>

    <mat-dialog-content>
      <p class="message">
        Deseja realmente excluir o aluno <strong>{{ data.aluno.nome }}</strong>?
      </p>
      <p class="warning">
        Esta ação não poderá ser desfeita.
      </p>
    </mat-dialog-content>

    <mat-dialog-actions align="end">
      <button mat-button type="button" (click)="fechar()">Cancelar</button>
      <button mat-raised-button color="warn" type="button" (click)="confirmar()">
        <mat-icon>delete</mat-icon>
        Excluir
      </button>
    </mat-dialog-actions>
  `,
  styles: [`
    h2[mat-dialog-title] {
      display: flex;
      align-items: center;
      gap: 16px;
      margin: 0;
      background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
      color: white;
    }

    mat-dialog-content {
      min-width: 480px;
    }

    .message {
      margin: 0 0 20px;
      font-size: 17px;
      line-height: 1.6;
    }

    .warning {
      margin: 0;
      padding: 16px;
      background: #fef2f2;
      border-left: 4px solid #ef4444;
      border-radius: 8px;
      color: #991b1b;
      font-size: 15px;
      line-height: 1.5;
    }

    mat-dialog-actions {
      border-top: 1px solid #e0e0e0;
    }
  `]
})
export class ConfirmarExclusaoDialogComponent {
  constructor(
    private dialogRef: MatDialogRef<ConfirmarExclusaoDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: { aluno: AlunoAdmin }
  ) {}

  fechar(): void {
    this.dialogRef.close(false);
  }

  confirmar(): void {
    this.dialogRef.close(true);
  }
}
