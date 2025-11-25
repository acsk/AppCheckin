import { Component, OnInit, Input, Output, EventEmitter, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { AlunoAdmin, Plano, ContaReceber } from '../../models/api.models';
import { AdminService } from '../../services/admin.service';
import { MatriculaService } from '../../services/matricula.service';

@Component({
  selector: 'app-matricula-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="modal-overlay">
      <div class="modal-card card">
        <div class="modal-header">
          <div class="modal-title-group">
            <div class="pill-icon">🎓</div>
            <div>
              <p class="eyebrow">Nova matrícula</p>
              <h2 class="title">Matricular Aluno</h2>
              <p class="muted">Vincule o aluno a um plano de acesso.</p>
            </div>
          </div>
          <button class="icon-btn" type="button" (click)="fechar()">×</button>
        </div>

        <div class="modal-body form-shell">
          <div *ngIf="erroMensagem" class="banner danger">
            {{ erroMensagem }}
          </div>

          <form [formGroup]="matriculaForm" (ngSubmit)="salvar()" class="form-grid">
            <div class="form-field full">
              <label class="form-label">Aluno</label>
              <div class="aluno-info">
                <div class="aluno-avatar">{{ aluno.nome ? aluno.nome.charAt(0).toUpperCase() : '?' }}</div>
                <div>
                  <strong>{{ aluno.nome }}</strong>
                  <p class="muted">{{ aluno.email }}</p>
                </div>
              </div>
            </div>

            <div class="form-field full">
              <label class="form-label" for="plano">Plano *</label>
              <div class="field-hint" style="margin-bottom: 8px;" *ngIf="carregandoPlanos">
                ⏳ Carregando planos...
              </div>
              <select id="plano" class="form-control" formControlName="plano_id" [disabled]="carregandoPlanos">
                <option [ngValue]="null">{{ carregandoPlanos ? 'Carregando...' : 'Selecione um plano' }}</option>
                <option *ngFor="let plano of planos" [ngValue]="plano.id">
                  {{ plano.nome }} - R$ {{ (+plano.valor).toFixed(2) }} ({{ plano.duracao_dias }} dias)
                </option>
              </select>
              <p class="field-error" *ngIf="matriculaForm.get('plano_id')?.invalid && matriculaForm.get('plano_id')?.touched">
                Selecione um plano
              </p>
            </div>

            <div class="form-field" *ngIf="planoSelecionado">
              <label class="form-label" for="data_inicio">Data de Início</label>
              <input id="data_inicio" class="form-control" formControlName="data_inicio" type="date" />
              <p class="field-hint">Data em que o plano começa a valer</p>
            </div>

            <div class="form-field" *ngIf="planoSelecionado">
              <label class="form-label">Vencimento</label>
              <input class="form-control" [value]="calcularVencimento()" type="text" disabled />
              <p class="field-hint">Calculado automaticamente</p>
            </div>

            <div class="form-field full" *ngIf="planoSelecionado">
              <label class="form-label" for="motivo">Tipo de Matrícula</label>
              <select id="motivo" class="form-control" formControlName="motivo">
                <option value="nova">Nova Matrícula</option>
                <option value="renovacao">Renovação</option>
                <option value="upgrade">Upgrade de Plano</option>
                <option value="downgrade">Downgrade de Plano</option>
              </select>
            </div>

            <div class="form-field full">
              <label class="form-label" for="observacoes">Observações</label>
              <textarea id="observacoes" class="form-control" formControlName="observacoes" rows="3" placeholder="Informações adicionais (opcional)"></textarea>
            </div>

            <div class="form-actions full">
              <button type="button" class="btn btn-ghost" (click)="fechar()">Cancelar</button>
              <button type="submit" class="btn btn-primary" [disabled]="matriculaForm.invalid || salvando">
                <span *ngIf="salvando">Matriculando...</span>
                <span *ngIf="!salvando">✓ Confirmar Matrícula</span>
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
      background: linear-gradient(120deg, #f0fdf4, #dcfce7);
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
      background: #bbf7d0;
      color: #166534;
      font-size: 18px;
      border: 1px solid #86efac;
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
      margin-bottom: 16px;
    }

    .banner.danger {
      background: #fef2f2;
      border-color: rgba(220, 38, 38, 0.25);
      color: #991b1b;
    }

    .aluno-info {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: 12px;
    }

    .aluno-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #8b5cf6);
      color: white;
      display: grid;
      place-items: center;
      font-size: 20px;
      font-weight: bold;
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
export class MatriculaModalComponent implements OnInit {
  @Input() aluno!: AlunoAdmin;
  @Output() closed = new EventEmitter<void>();
  @Output() saved = new EventEmitter<any>();

  private fb = inject(FormBuilder);
  private adminService = inject(AdminService);
  private matriculaService = inject(MatriculaService);

  matriculaForm!: FormGroup;
  baixaForm!: FormGroup;
  planos: Plano[] = [];
  salvando = false;
  carregandoPlanos = false;
  erroMensagem = '';
  planoSelecionado: Plano | null = null;
  
  // Controle de etapas
  etapaAtual: 'matricula' | 'baixa' = 'matricula';
  contaCriada: ContaReceber | null = null;
  dandoBaixa = false;

  ngOnInit() {
    this.inicializarFormulario();
    this.inicializarFormularioBaixa();
    this.observarMudancaPlano();
    this.carregarPlanos();
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
        this.erroMensagem = 'Erro ao carregar planos disponíveis';
        this.carregandoPlanos = false;
      }
    });
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

  inicializarFormularioBaixa(): void {
    const hoje = new Date().toISOString().split('T')[0];
    
    this.baixaForm = this.fb.group({
      data_pagamento: [hoje, Validators.required],
      forma_pagamento: ['dinheiro', Validators.required],
      observacoes: ['']
    });
  }

  observarMudancaPlano(): void {
    this.matriculaForm.get('plano_id')?.valueChanges.subscribe(planoId => {
      this.planoSelecionado = this.planos.find(p => p.id === planoId) || null;
    });
  }

  calcularVencimento(): string {
    if (!this.planoSelecionado) return '';
    
    const dataInicio = this.matriculaForm.get('data_inicio')?.value;
    if (!dataInicio) return '';

    const inicio = new Date(dataInicio + 'T00:00:00');
    const vencimento = new Date(inicio);
    vencimento.setDate(vencimento.getDate() + this.planoSelecionado.duracao_dias);
    
    return vencimento.toLocaleDateString('pt-BR');
  }

  fechar() {
    this.closed.emit();
  }

  salvar() {
    if (this.matriculaForm.invalid) {
      Object.keys(this.matriculaForm.controls).forEach(key => {
        this.matriculaForm.get(key)?.markAsTouched();
      });
      this.erroMensagem = 'Por favor, preencha todos os campos obrigatórios.';
      return;
    }

    const dados = {
      usuario_id: this.aluno.id,
      ...this.matriculaForm.value
    };

    this.saved.emit(dados);
  }

  setErro(mensagem: string) {
    this.erroMensagem = mensagem;
  }

  setSalvando(estado: boolean) {
    this.salvando = estado;
  }
}
