import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule, ToastController, ModalController } from '@ionic/angular';
import { FormsModule } from '@angular/forms';
import { AdminService } from '../../../services/admin.service';
import { AlunoAdmin, Plano } from '../../../models/api.models';
import { AlunoModalComponent } from './aluno-modal/aluno-modal.component';

@Component({
  selector: 'app-gerenciar-alunos',
  standalone: true,
  imports: [CommonModule, IonicModule, FormsModule],
  templateUrl: './gerenciar-alunos.component.html',
  styleUrls: ['./gerenciar-alunos.component.scss']
})
export class GerenciarAlunosComponent implements OnInit {
  alunos: AlunoAdmin[] = [];
  alunosFiltrados: AlunoAdmin[] = [];
  planos: Plano[] = [];
  searchTerm = '';
  loading = true;

  constructor(
    private adminService: AdminService,
    private toastController: ToastController,
    private modalController: ModalController
  ) {}

  ngOnInit(): void {
    this.carregarAlunos();
    this.carregarPlanos();
  }

  carregarAlunos(): void {
    this.loading = true;
    this.adminService.listarAlunos().subscribe({
      next: (response) => {
        this.alunos = response.alunos;
        this.alunosFiltrados = this.alunos;
        this.loading = false;
      },
      error: async (error) => {
        console.error('Erro ao carregar alunos:', error);
        this.loading = false;
        await this.mostrarToast('Erro ao carregar alunos', 'danger');
      }
    });
  }

  carregarPlanos(): void {
    this.adminService.listarPlanos(true).subscribe({
      next: (response) => {
        this.planos = response.planos;
      },
      error: (error) => {
        console.error('Erro ao carregar planos:', error);
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

  async abrirModal(aluno?: AlunoAdmin): Promise<void> {
    const modal = await this.modalController.create({
      component: AlunoModalComponent,
      componentProps: {
        aluno,
        planos: this.planos
      },
      cssClass: 'aluno-modal'
    });

    await modal.present();

    const { data, role } = await modal.onWillDismiss();

    if (role === 'confirm' && data) {
      await this.salvarAluno(data);
    }
  }

  async salvarAluno(modalData: { dados: any; modoEdicao: boolean; alunoId?: number }): Promise<void> {
    const { dados, modoEdicao, alunoId } = modalData;

    if (modoEdicao && alunoId) {
      this.adminService.atualizarAluno(alunoId, dados).subscribe({
        next: async () => {
          this.carregarAlunos();
          await this.mostrarToast('Aluno atualizado com sucesso!', 'success');
        },
        error: async (error) => {
          console.error('Erro ao atualizar aluno:', error);
          const mensagem = error.error?.errors?.join(', ') || error.error?.error || 'Erro ao atualizar aluno';
          await this.mostrarToast(mensagem, 'danger');
        }
      });
    } else {
      this.adminService.criarAluno(dados).subscribe({
        next: async () => {
          this.carregarAlunos();
          await this.mostrarToast('Aluno criado com sucesso!', 'success');
        },
        error: async (error) => {
          console.error('Erro ao criar aluno:', error);
          const mensagem = error.error?.errors?.join(', ') || error.error?.error || 'Erro ao criar aluno';
          await this.mostrarToast(mensagem, 'danger');
        }
      });
    }
  }

  excluirAluno(aluno: AlunoAdmin): void {
    const confirmar = confirm(`Deseja realmente excluir o aluno ${aluno.nome}?`);
    if (!confirmar) return;

    this.adminService.desativarAluno(aluno.id).subscribe({
      next: async () => {
        this.carregarAlunos();
        await this.mostrarToast('Aluno excluído com sucesso!', 'success');
      },
      error: async (error) => {
        console.error('Erro ao excluir aluno:', error);
        const mensagem = error.error?.error || 'Erro ao excluir aluno';
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

  getNomePlano(aluno: AlunoAdmin): string {
    return aluno.plano?.nome || 'Sem plano';
  }

  getStatusPlano(aluno: AlunoAdmin): string {
    if (!aluno.plano_id) return 'sem-plano';
    if (!aluno.data_vencimento_plano) return 'ativo';
    
    const hoje = new Date();
    const vencimento = new Date(aluno.data_vencimento_plano);
    const diffDias = Math.floor((vencimento.getTime() - hoje.getTime()) / (1000 * 60 * 60 * 24));
    
    if (diffDias < 0) return 'vencido';
    if (diffDias <= 7) return 'proximo-vencimento';
    return 'ativo';
  }

  formatarData(data: string | null | undefined): string {
    if (!data) return '-';
    return new Date(data).toLocaleDateString('pt-BR');
  }

  getAvatarUrl(aluno: AlunoAdmin): string {
    return aluno.foto_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(aluno.nome)}&background=6366f1&color=fff`;
  }
}
