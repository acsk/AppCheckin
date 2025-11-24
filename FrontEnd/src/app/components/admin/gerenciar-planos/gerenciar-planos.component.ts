import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule, ToastController } from '@ionic/angular';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule, FormsModule } from '@angular/forms';
import { AdminService } from '../../../services/admin.service';
import { Plano } from '../../../models/api.models';

@Component({
  selector: 'app-gerenciar-planos',
  standalone: true,
  imports: [CommonModule, IonicModule, ReactiveFormsModule, FormsModule],
  template: `
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-back-button defaultHref="/admin"></ion-back-button>
        </ion-buttons>
        <ion-title>Gerenciar Planos</ion-title>
        <ion-buttons slot="end">
          <ion-button (click)="abrirModal()">
            <ion-icon slot="icon-only" name="add"></ion-icon>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <div class="planos-container">
        <h2>Planos Disponíveis</h2>
        
        <ion-spinner *ngIf="loading" name="crescent"></ion-spinner>

        <ion-list *ngIf="!loading">
          <ion-item *ngFor="let plano of planos" lines="full">
            <ion-label>
              <h3>{{ plano.nome }}</h3>
              <p>{{ plano.descricao }}</p>
              <p class="preco">{{ formatarMoeda(plano.valor) }} / {{ plano.duracao_dias }} dias</p>
              <p class="checkins" *ngIf="plano.checkins_mensais">
                {{ plano.checkins_mensais }} check-ins/mês
              </p>
              <p class="checkins" *ngIf="!plano.checkins_mensais">
                Check-ins ilimitados
              </p>
            </ion-label>

            <ion-note slot="end">
              <ion-badge [color]="plano.ativo ? 'success' : 'medium'">
                {{ plano.ativo ? 'Ativo' : 'Inativo' }}
              </ion-badge>
            </ion-note>

            <ion-buttons slot="end">
              <ion-button (click)="editarPlano(plano)" fill="clear">
                <ion-icon slot="icon-only" name="create"></ion-icon>
              </ion-button>
              <ion-button (click)="desativarPlano(plano)" fill="clear" color="danger">
                <ion-icon slot="icon-only" name="trash"></ion-icon>
              </ion-button>
            </ion-buttons>
          </ion-item>

          <ion-item *ngIf="planos.length === 0" lines="none">
            <ion-label class="ion-text-center">
              <p>Nenhum plano cadastrado</p>
            </ion-label>
          </ion-item>
        </ion-list>
      </div>

      <ion-modal [isOpen]="modalAberto" (didDismiss)="fecharModal()">
        <ng-template>
          <ion-header>
            <ion-toolbar>
              <ion-title>{{ modoEdicao ? 'Editar' : 'Novo' }} Plano</ion-title>
              <ion-buttons slot="end">
                <ion-button (click)="fecharModal()">Fechar</ion-button>
              </ion-buttons>
            </ion-toolbar>
          </ion-header>
          <ion-content class="ion-padding">
            <form [formGroup]="planoForm" (ngSubmit)="salvarPlano()" class="form-panel form-shell">
              <ion-item class="form-item">
                <ion-label position="stacked">Nome do Plano *</ion-label>
                <ion-input formControlName="nome" type="text" placeholder="Ex: Mensal Básico"></ion-input>
              </ion-item>
              <ion-text color="danger" *ngIf="planoForm.get('nome')?.invalid && planoForm.get('nome')?.touched">
                <p class="error-message">Nome é obrigatório</p>
              </ion-text>

              <ion-item class="form-item">
                <ion-label position="stacked">Descrição</ion-label>
                <ion-textarea formControlName="descricao" rows="3" placeholder="Descreva os benefícios do plano"></ion-textarea>
              </ion-item>

              <ion-item class="form-item">
                <ion-label position="stacked">Valor (R$) *</ion-label>
                <ion-input formControlName="valor" type="number" step="0.01" placeholder="0.00"></ion-input>
              </ion-item>
              <ion-text color="danger" *ngIf="planoForm.get('valor')?.invalid && planoForm.get('valor')?.touched">
                <p class="error-message">Valor deve ser maior ou igual a zero</p>
              </ion-text>

              <ion-item class="form-item">
                <ion-label position="stacked">Duração (dias) *</ion-label>
                <ion-input formControlName="duracao_dias" type="number" placeholder="30"></ion-input>
              </ion-item>
              <ion-text color="danger" *ngIf="planoForm.get('duracao_dias')?.invalid && planoForm.get('duracao_dias')?.touched">
                <p class="error-message">Duração deve ser maior que zero</p>
              </ion-text>

              <ion-item class="form-item">
                <ion-label position="stacked">Check-ins mensais (deixe vazio para ilimitado)</ion-label>
                <ion-input formControlName="checkins_mensais" type="number" placeholder="Ex: 12"></ion-input>
              </ion-item>

              <ion-item class="form-item">
                <ion-label>Ativo</ion-label>
                <ion-toggle formControlName="ativo" slot="end"></ion-toggle>
              </ion-item>

              <ion-button 
                expand="block" 
                type="submit"
                [disabled]="planoForm.invalid"
                class="submit-btn form-button-primary"
              >
                {{ modoEdicao ? 'Atualizar' : 'Criar' }} Plano
              </ion-button>
            </form>
          </ion-content>
        </ng-template>
      </ion-modal>
    </ion-content>
  `,
  styles: [`
    .planos-container {
      max-width: 900px;
      margin: 0 auto;
      color: #e2e8f0;

      h2 {
        font-size: 20px;
        margin-bottom: 16px;
        color: #e2e8f0;
      }
    }

    ion-spinner {
      display: block;
      margin: 40px auto;
    }

    ion-item {
      --padding-start: 16px;
      margin-bottom: 12px;

      ion-label {
        h3 {
          font-weight: 600;
          font-size: 16px;
          margin-bottom: 4px;
        }

        p {
          font-size: 13px;
          color: var(--ion-color-medium);
          margin: 2px 0;

          &.preco {
            font-weight: 600;
            font-size: 18px;
            color: var(--ion-color-success);
            margin-top: 8px;
          }

          &.checkins {
            font-style: italic;
            font-size: 12px;
          }
        }
      }

      ion-badge {
        font-size: 11px;
        padding: 4px 8px;
      }
    }

    form {
      .error-message {
        font-size: 12px;
        margin: 4px 0 8px 16px;
      }

      .submit-btn {
        margin-top: 12px;
      }
    }
  `]
})
export class GerenciarPlanosComponent implements OnInit {
  planos: Plano[] = [];
  loading = true;
  salvando = false;
  modalAberto = false;
  modoEdicao = false;
  planoSelecionado: Plano | null = null;
  planoForm!: FormGroup;
  erroMensagem = '';

  constructor(
    private fb: FormBuilder,
    private adminService: AdminService,
    private toastController: ToastController
  ) {
    this.inicializarForm();
  }

  ngOnInit(): void {
    this.carregarPlanos();
  }

  inicializarForm(): void {
    this.planoForm = this.fb.group({
      nome: ['', Validators.required],
      descricao: [''],
      valor: [0, [Validators.required, Validators.min(0)]],
      duracao_dias: [30, [Validators.required, Validators.min(1)]],
      checkins_mensais: [null],
      ativo: [true]
    });
  }

  carregarPlanos(): void {
    this.loading = true;
    this.adminService.listarPlanos(false).subscribe({
      next: (response) => {
        this.planos = response.planos;
        this.loading = false;
      },
      error: (error) => {
        console.error('Erro ao carregar planos:', error);
        this.loading = false;
      }
    });
  }

  abrirModal(): void {
    this.modoEdicao = false;
    this.planoSelecionado = null;
    this.planoForm.reset({ valor: 0, duracao_dias: 30, ativo: true });
    this.modalAberto = true;
  }

  editarPlano(plano: Plano): void {
    this.modoEdicao = true;
    this.planoSelecionado = plano;
    this.planoForm.patchValue({
      nome: plano.nome,
      descricao: plano.descricao,
      valor: plano.valor,
      duracao_dias: plano.duracao_dias,
      checkins_mensais: plano.checkins_mensais,
      ativo: plano.ativo
    });
    this.modalAberto = true;
  }

  fecharModal(): void {
    this.modalAberto = false;
    this.planoSelecionado = null;
    this.planoForm.reset();
  }

  salvarPlano(): void {
    if (this.planoForm.invalid) {
      Object.keys(this.planoForm.controls).forEach(key => {
        this.planoForm.get(key)?.markAsTouched();
      });
      this.erroMensagem = 'Por favor, preencha todos os campos obrigatórios corretamente.';
      return;
    }

    this.salvando = true;
    this.erroMensagem = '';
    const dados = this.planoForm.value;

    if (this.modoEdicao && this.planoSelecionado) {
      this.adminService.atualizarPlano(this.planoSelecionado.id, dados).subscribe({
        next: async () => {
          this.salvando = false;
          this.fecharModal();
          this.carregarPlanos();
          await this.mostrarToast('Plano atualizado com sucesso!', 'success');
        },
        error: async (error) => {
          this.salvando = false;
          console.error('Erro ao atualizar plano:', error);
          const mensagem = error.error?.error || 'Erro ao atualizar plano';
          this.erroMensagem = mensagem;
          await this.mostrarToast(mensagem, 'danger');
        }
      });
    } else {
      this.adminService.criarPlano(dados).subscribe({
        next: async () => {
          this.salvando = false;
          this.fecharModal();
          this.carregarPlanos();
          await this.mostrarToast('Plano criado com sucesso!', 'success');
        },
        error: async (error) => {
          this.salvando = false;
          console.error('Erro ao criar plano:', error);
          const mensagem = error.error?.error || 'Erro ao criar plano';
          this.erroMensagem = mensagem;
          await this.mostrarToast(mensagem, 'danger');
        }
      });
    }
  }

  desativarPlano(plano: Plano): void {
    const confirmar = confirm(`Deseja realmente desativar o plano ${plano.nome}?`);
    if (!confirmar) return;

    this.adminService.desativarPlano(plano.id).subscribe({
      next: async () => {
        this.carregarPlanos();
        await this.mostrarToast('Plano desativado com sucesso!', 'success');
      },
      error: async (error) => {
        console.error('Erro ao desativar plano:', error);
        const mensagem = error.error?.error || 'Erro ao desativar plano';
        await this.mostrarToast(mensagem, 'danger');
      }
    });
  }

  async mostrarToast(mensagem: string, cor: 'success' | 'danger' | 'warning' = 'success'): Promise<void> {
    const toast = await this.toastController.create({
      message: mensagem,
      duration: 3000,
      position: 'top',
      color: cor,
      buttons: [
        {
          text: 'OK',
          role: 'cancel'
        }
      ]
    });
    await toast.present();
  }

  formatarMoeda(valor: number): string {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(valor);
  }
}
