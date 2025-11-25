import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../../services/admin.service';
import { MatriculaService } from '../../../services/matricula.service';
import { ConfigService } from '../../../services/config.service';
import { AlunoAdmin, FormaPagamento } from '../../../models/api.models';
import { ToastService } from '../../../services/toast.service';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatChipsModule } from '@angular/material/chips';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatSelectModule } from '@angular/material/select';
import { LoadingController } from '@ionic/angular/standalone';
import { AlunoDialogComponent } from './aluno-dialog/aluno-dialog.component';
import { ConfirmarExclusaoDialogComponent } from './confirmar-exclusao-dialog/confirmar-exclusao-dialog.component';
import { ConfirmarBaixaDialogComponent } from './confirmar-baixa-dialog/confirmar-baixa-dialog.component';
import { MatriculaDialogComponent } from '../../matricula-dialog/matricula-dialog.component';

@Component({
  selector: 'app-gerenciar-alunos',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatIconModule,
    MatButtonModule,
    MatChipsModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatDialogModule,
    MatSelectModule
  ],
  templateUrl: './gerenciar-alunos.component.html',
  styleUrls: ['./gerenciar-alunos.component.scss']
})
export class GerenciarAlunosComponent implements OnInit {
  alunos: AlunoAdmin[] = [];
  alunosFiltrados: AlunoAdmin[] = [];
  searchTerm = '';
  loading = true;
  formasPagamento: FormaPagamento[] = [];

  constructor(
    private adminService: AdminService,
    private matriculaService: MatriculaService,
    private configService: ConfigService,
    private toast: ToastService,
    private loadingController: LoadingController,
    private dialog: MatDialog
  ) {}

  ngOnInit(): void {
    this.carregarAlunos();
    this.carregarFormasPagamento();
  }

  carregarFormasPagamento(): void {
    this.configService.listarFormasPagamento().subscribe({
      next: (formas) => {
        this.formasPagamento = formas;
      },
      error: (error) => {
        console.error('Erro ao carregar formas de pagamento:', error);
      }
    });
  }

  async carregarAlunos(): Promise<void> {
    const loading = await this.loadingController.create({
      message: 'Carregando alunos...',
      spinner: 'crescent'
    });
    await loading.present();
    this.loading = true;

    this.adminService.listarAlunos().subscribe({
      next: async (response) => {
        this.alunos = response.alunos;
        this.filtrarAlunos();
        this.loading = false;
        await loading.dismiss();
      },
      error: async (error) => {
        console.error('Erro ao carregar alunos:', error);
        this.loading = false;
        await loading.dismiss();
        this.toast.show('Erro ao carregar alunos', 'danger');
      }
    });
  }

  filtrarAlunos(): void {
    const term = this.searchTerm.toLowerCase();
    if (!term) {
      this.alunosFiltrados = this.alunos;
      return;
    }
    this.alunosFiltrados = this.alunos.filter(
      a => a.nome.toLowerCase().includes(term) || 
           a.email.toLowerCase().includes(term)
    );
  }

  abrirModal(aluno?: AlunoAdmin): void {
    const dialogRef = this.dialog.open(AlunoDialogComponent, {
      width: '500px',
      data: { aluno }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.salvarAluno(result);
      }
    });
  }

  async salvarAluno(modalData: { dados: any; modoEdicao: boolean; alunoId?: number }): Promise<void> {
    const { dados, modoEdicao, alunoId } = modalData;

    if (modoEdicao && alunoId) {
      this.adminService.atualizarAluno(alunoId, dados).subscribe({
        next: async () => {
          this.carregarAlunos();
          this.toast.show('Aluno atualizado com sucesso!', 'success');
        },
        error: async (error) => {
          console.error('Erro ao atualizar aluno:', error);
          const mensagem = error.error?.errors?.join(', ') || error.error?.error || 'Erro ao atualizar aluno';
          this.toast.show(mensagem, 'danger');
        }
      });
    } else {
      this.adminService.criarAluno(dados).subscribe({
        next: async () => {
          this.carregarAlunos();
          this.toast.show('Aluno criado com sucesso!', 'success');
        },
        error: async (error) => {
          console.error('Erro ao criar aluno:', error);
          const mensagem = error.error?.errors?.join(', ') || error.error?.error || 'Erro ao criar aluno';
          this.toast.show(mensagem, 'danger');
        }
      });
    }
  }

  excluirAluno(aluno: AlunoAdmin): void {
    const dialogRef = this.dialog.open(ConfirmarExclusaoDialogComponent, {
      width: '450px',
      data: { aluno }
    });

    dialogRef.afterClosed().subscribe(confirmar => {
      if (!confirmar) return;

      this.adminService.desativarAluno(aluno.id).subscribe({
        next: async () => {
          this.carregarAlunos();
          this.toast.show('Aluno excluído com sucesso!', 'success');
        },
        error: async (error) => {
          console.error('Erro ao excluir aluno:', error);
          const mensagem = error.error?.error || 'Erro ao excluir aluno';
          this.toast.show(mensagem, 'danger');
        }
      });
    });
  }

  getNomePlano(aluno: AlunoAdmin): string {
    return aluno.plano?.nome || '';
  }

  getStatusPlano(aluno: AlunoAdmin): string {
    // Verifica se tem pagamento ativo (lógica de negócio correta)
    if (aluno.status_ativo) {
      return 'ativo';
    }
    
    // Se tem plano mas não tem pagamento
    if (aluno.plano_id) {
      return 'vencido';
    }
    
    // Sem plano
    return 'inativo';
  }

  formatarData(data: string | null | undefined): string {
    if (!data) return '-';
    return new Date(data).toLocaleDateString('pt-BR');
  }

  getAvatarUrl(aluno: AlunoAdmin): string {
    return aluno.foto_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(aluno.nome)}&background=6366f1&color=fff`;
  }

  abrirModalMatricula(aluno: AlunoAdmin): void {
    const dialogRef = this.dialog.open(MatriculaDialogComponent, {
      width: '600px',
      data: { aluno }
    });

    dialogRef.afterClosed().subscribe(dados => {
      if (dados) {
        this.salvarMatricula(dados);
      }
    });
  }

  async salvarMatricula(dados: any): Promise<void> {
    const loading = await this.loadingController.create({
      message: 'Criando matrícula...',
      spinner: 'crescent'
    });
    await loading.present();

    this.matriculaService.criar(dados).subscribe({
      next: async (response) => {
        await loading.dismiss();
        
        // Pergunta se quer dar baixa imediatamente usando dialog
        if (response.conta_criada) {
          this.toast.show(
            'Matrícula realizada com sucesso!',
            'success',
            3000
          );
          
          const dialogRef = this.dialog.open(ConfirmarBaixaDialogComponent, {
            width: '500px',
            data: { 
              valor: +response.conta_criada.valor,
              formasPagamento: this.formasPagamento
            }
          });

          const contaId = response.conta_criada.id;
          dialogRef.afterClosed().subscribe(formaPagamentoId => {
            if (formaPagamentoId) {
              this.darBaixaImediata(contaId, formaPagamentoId);
            } else {
              this.carregarAlunos();
            }
          });
        } else {
          this.carregarAlunos();
        }
      },
      error: async (error) => {
        await loading.dismiss();
        console.error('Erro ao criar matrícula:', error);
        const mensagem = error.error?.errors?.join(', ') || error.error?.error || 'Erro ao criar matrícula';
        this.toast.show(mensagem, 'danger');
      }
    });
  }

  darBaixaImediata(contaId: number, formaPagamentoId: number): void {
    this.processarBaixaImediata(contaId, formaPagamentoId);
  }

  private async processarBaixaImediata(contaId: number, formaPagamentoId: number): Promise<void> {
    const hoje = new Date().toISOString().split('T')[0];

    const loading = await this.loadingController.create({
      message: 'Processando pagamento...',
      spinner: 'crescent'
    });
    await loading.present();

    this.matriculaService.darBaixaConta(contaId, {
      data_pagamento: hoje,
      forma_pagamento_id: formaPagamentoId
    }).subscribe({
      next: async () => {
        await loading.dismiss();
        this.carregarAlunos();
        this.toast.show('Matrícula realizada e primeira mensalidade paga com sucesso!', 'success');
      },
      error: async (error) => {
        await loading.dismiss();
        console.error('Erro ao dar baixa:', error);
        this.carregarAlunos();
        this.toast.show('Matrícula criada, mas erro ao dar baixa: ' + (error.error?.error || 'Erro desconhecido'), 'warning');
      }
    });
  }
}
