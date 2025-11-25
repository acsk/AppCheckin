import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatDialog } from '@angular/material/dialog';
import { MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { 
  IonHeader, IonToolbar, IonTitle, IonContent, IonButtons, 
  IonButton, IonIcon, IonList, IonItem, IonLabel, IonBadge,
  IonSegment, IonSegmentButton, IonCard, IonCardHeader, IonCardTitle,
  IonCardContent, IonRefresher, IonRefresherContent, IonSearchbar,
  ToastController, LoadingController
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { 
  cashOutline, calendarOutline, checkmarkCircle, closeCircle, 
  timeOutline, personOutline, cardOutline, refreshOutline, filterOutline
} from 'ionicons/icons';
import { ContasReceberService } from '../../services/contas-receber.service';
import { ContaReceber, DarBaixaRequest } from '../../models/api.models';
import { DarBaixaDialogComponent } from './dar-baixa-dialog.component';
import { CancelarContaDialogComponent } from './cancelar-conta-dialog.component';

@Component({
  selector: 'app-contas-receber',
  templateUrl: './contas-receber.component.html',
  styleUrls: ['./contas-receber.component.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatDialogModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    IonHeader, IonToolbar, IonTitle, IonContent, IonButtons,
    IonButton, IonIcon, IonList, IonItem, IonLabel, IonBadge,
    IonSegment, IonSegmentButton, IonCard, IonCardHeader, IonCardTitle,
    IonCardContent, IonRefresher, IonRefresherContent, IonSearchbar
  ]
})
export class ContasReceberComponent implements OnInit {
  private contasService = inject(ContasReceberService);
  private dialog = inject(MatDialog);
  private toastController = inject(ToastController);
  private loadingController = inject(LoadingController);

  contas: ContaReceber[] = [];
  contasFiltradas: ContaReceber[] = [];
  filtroStatus: string = 'todos';
  termoBusca: string = '';
  loading = false;
  mesReferencia: string = '';

  constructor() {
    addIcons({ 
      cashOutline, calendarOutline, checkmarkCircle, closeCircle,
      timeOutline, personOutline, cardOutline, refreshOutline, filterOutline
    });
    
    // Mês atual como padrão
    const hoje = new Date();
    this.mesReferencia = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}`;
  }

  ngOnInit() {
    this.carregarContas();
  }

  async carregarContas(event?: any) {
    if (!event) {
      const loading = await this.loadingController.create({
        message: 'Carregando contas...',
        spinner: 'crescent'
      });
      await loading.present();
      this.loading = true;
    }
    
    const params: any = {};
    if (this.filtroStatus !== 'todos') {
      params.status = this.filtroStatus;
    }
    if (this.mesReferencia) {
      params.mes_referencia = this.mesReferencia;
    }

    this.contasService.listarContas(params).subscribe({
      next: async (response) => {
        this.contas = response.contas;
        this.aplicarFiltros();
        this.loading = false;
        if (event) {
          event.target.complete();
        } else {
          await this.loadingController.dismiss();
        }
      },
      error: async (error) => {
        console.error('Erro ao carregar contas:', error);
        this.loading = false;
        if (event) {
          event.target.complete();
        } else {
          await this.loadingController.dismiss();
        }
        this.mostrarToast('Erro ao carregar contas', 'danger');
      }
    });
  }

  aplicarFiltros() {
    this.contasFiltradas = this.contas.filter(conta => {
      const matchBusca = !this.termoBusca || 
        conta.aluno_nome?.toLowerCase().includes(this.termoBusca.toLowerCase()) ||
        conta.aluno_email?.toLowerCase().includes(this.termoBusca.toLowerCase()) ||
        conta.plano_nome?.toLowerCase().includes(this.termoBusca.toLowerCase());
      
      return matchBusca;
    });
  }

  onBuscar(event: any) {
    this.termoBusca = event.target.value || '';
    this.aplicarFiltros();
  }

  onFiltroChange(event: any) {
    this.filtroStatus = event.detail.value;
    this.carregarContas();
  }

  async darBaixa(conta: ContaReceber) {
    const dialogRef = this.dialog.open(DarBaixaDialogComponent, {
      width: '500px',
      data: {
        conta: conta,
        valor: parseFloat(conta.valor).toFixed(2),
        aluno: conta.aluno_nome
      }
    });

    dialogRef.afterClosed().subscribe(async (dados) => {
      if (dados) {
        await this.confirmarBaixa(conta.id, dados);
      }
    });
  }

  async confirmarBaixa(contaId: number, dados: DarBaixaRequest) {
    const loading = await this.loadingController.create({
      message: 'Processando pagamento...',
      spinner: 'crescent'
    });
    await loading.present();

    this.contasService.darBaixa(contaId, dados).subscribe({
      next: async (response) => {
        await loading.dismiss();
        let message = response.message;
        if (response.proxima_conta_id) {
          message += ` - Próxima cobrança: ${this.formatarData(response.proxima_vencimento!)}`;
        }
        this.mostrarToast(message, 'success');
        this.carregarContas();
      },
      error: async (error) => {
        await loading.dismiss();
        console.error('Erro ao dar baixa:', error);
        this.mostrarToast(error.error?.error || 'Erro ao dar baixa', 'danger');
      }
    });
  }

  async cancelarConta(conta: ContaReceber) {
    const dialogRef = this.dialog.open(CancelarContaDialogComponent, {
      width: '450px',
      data: {
        aluno: conta.aluno_nome,
        valor: parseFloat(conta.valor).toFixed(2)
      }
    });

    dialogRef.afterClosed().subscribe(async (observacoes) => {
      if (observacoes !== undefined) {
        const loading = await this.loadingController.create({
          message: 'Cancelando conta...',
          spinner: 'crescent'
        });
        await loading.present();

        this.contasService.cancelar(conta.id, observacoes).subscribe({
          next: async (response) => {
            await loading.dismiss();
            this.mostrarToast(response.message, 'success');
            this.carregarContas();
          },
          error: async (error) => {
            await loading.dismiss();
            console.error('Erro ao cancelar:', error);
            this.mostrarToast(error.error?.error || 'Erro ao cancelar conta', 'danger');
          }
        });
      }
    });
  }

  getStatusColor(status: string): string {
    const colors: { [key: string]: string } = {
      'pendente': 'warning',
      'pago': 'success',
      'vencido': 'danger',
      'cancelado': 'medium'
    };
    return colors[status] || 'medium';
  }

  formatarData(data: string | null): string {
    if (!data) return '-';
    const dt = new Date(data + 'T00:00:00');
    return dt.toLocaleDateString('pt-BR');
  }

  formatarValor(valor: string | number): string {
    const numValor = typeof valor === 'string' ? parseFloat(valor) : valor;
    return numValor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  async mostrarToast(message: string, color: 'success' | 'danger' | 'warning' = 'success') {
    const toast = await this.toastController.create({
      message,
      duration: 3000,
      color,
      position: 'top'
    });
    await toast.present();
  }

  getTotalPendente(): number {
    return this.contasFiltradas
      .filter(c => c.status === 'pendente')
      .reduce((sum, c) => sum + parseFloat(c.valor), 0);
  }

  getTotalVencido(): number {
    return this.contasFiltradas
      .filter(c => c.status === 'vencido')
      .reduce((sum, c) => sum + parseFloat(c.valor), 0);
  }
}
