import { Component, OnInit, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { UserService } from '../../services/user.service';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    IonicModule
  ],
  template: `
    <div class="mx-auto max-w-3xl space-y-6">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-6 shadow-lg shadow-emerald-500/5">
        <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Perfil</p>
        <h1 class="text-3xl font-bold text-slate-50">Informações da conta</h1>
        <p class="text-slate-400">Atualize seu nome, email, senha ou foto de perfil.</p>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-950/70 p-6">
        <form [formGroup]="perfilForm" (ngSubmit)="onSubmit()" class="space-y-5">
          <!-- Foto de Perfil -->
          <div class="flex justify-center mb-6">
            <div class="relative">
              <img 
                [src]="fotoPreview || 'assets/default-avatar.png'" 
                alt="Foto de perfil"
                class="w-32 h-32 rounded-full object-cover border-4 border-emerald-400"
              />
              <button 
                type="button"
                (click)="selecionarFoto()"
                class="absolute bottom-0 right-0 bg-emerald-400 text-slate-950 p-2 rounded-full hover:bg-emerald-500 transition"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
              </button>
              <input 
                type="file" 
                #fileInput 
                accept="image/*" 
                (change)="onFotoSelecionada($event)"
                style="display: none"
              />
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2 md:col-span-2">
              <label class="text-sm font-semibold text-slate-100" for="nome">Nome</label>
              <input
                id="nome"
                type="text"
                formControlName="nome"
                class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-slate-50 outline-none ring-emerald-400/30 transition focus:border-emerald-400 focus:ring-4"
              />
              <p class="text-xs text-rose-400" *ngIf="perfilForm.get('nome')?.touched && perfilForm.get('nome')?.hasError('required')">
                Nome é obrigatório
              </p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <label class="text-sm font-semibold text-slate-100" for="email">Email</label>
              <input
                id="email"
                type="email"
                formControlName="email"
                class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-slate-50 outline-none ring-emerald-400/30 transition focus:border-emerald-400 focus:ring-4"
              />
              <p class="text-xs text-rose-400" *ngIf="perfilForm.get('email')?.touched && perfilForm.get('email')?.hasError('required')">
                Email é obrigatório
              </p>
              <p class="text-xs text-rose-400" *ngIf="perfilForm.get('email')?.touched && perfilForm.get('email')?.hasError('email')">
                Email inválido
              </p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <label class="text-sm font-semibold text-slate-100" for="senha">Nova Senha (opcional)</label>
              <input
                id="senha"
                type="password"
                formControlName="senha"
                class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-slate-50 outline-none ring-emerald-400/30 transition focus:border-emerald-400 focus:ring-4"
                placeholder="Mínimo 6 caracteres"
              />
              <p class="text-xs text-rose-400" *ngIf="perfilForm.get('senha')?.touched && perfilForm.get('senha')?.hasError('minlength')">
                Senha deve ter no mínimo 6 caracteres
              </p>
            </div>
          </div>

          <button type="submit" [disabled]="perfilForm.invalid || loading" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-400 via-blue-500 to-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
            <svg *ngIf="loading" class="h-5 w-5 animate-spin text-slate-900" viewBox="0 0 24 24" fill="none">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 000 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
            </svg>
            <span>{{ loading ? 'Salvando...' : 'Salvar alterações' }}</span>
          </button>
        </form>
      </div>
    </div>
  `
})
export class PerfilComponent implements OnInit {
  @ViewChild('fileInput') fileInput!: ElementRef<HTMLInputElement>;
  
  perfilForm: FormGroup;
  loading = false;
  fotoPreview: string | null = null;
  fotoBase64: string | null = null;

  constructor(
    private fb: FormBuilder,
    private userService: UserService,
    private authService: AuthService,
    private toast: ToastService
  ) {
    this.perfilForm = this.fb.group({
      nome: ['', Validators.required],
      email: ['', [Validators.required, Validators.email]],
      senha: ['', Validators.minLength(6)]
    });
  }

  ngOnInit(): void {
    this.carregarDados();
  }

  carregarDados(): void {
    this.userService.getMe().subscribe({
      next: (user) => {
        this.perfilForm.patchValue({
          nome: user.nome,
          email: user.email
        });
        
        // Carregar foto se existir
        if (user.foto_base64) {
          this.fotoPreview = user.foto_base64;
        }
      },
      error: (error) => {
        this.toast.show('Erro ao carregar dados', 'danger');
      }
    });
  }

  selecionarFoto(): void {
    this.fileInput.nativeElement.click();
  }

  onFotoSelecionada(event: Event): void {
    const input = event.target as HTMLInputElement;
    
    if (input.files && input.files[0]) {
      const file = input.files[0];
      
      // Validar tamanho (max 5MB)
      if (file.size > 5 * 1024 * 1024) {
        this.toast.show('A imagem deve ter no máximo 5MB', 'danger');
        return;
      }
      
      // Validar tipo
      if (!file.type.match(/image\/(jpeg|jpg|png|gif|webp)/)) {
        this.toast.show('Formato inválido. Use JPEG, PNG, GIF ou WebP', 'danger');
        return;
      }
      
      // Converter para base64
      const reader = new FileReader();
      reader.onload = (e: ProgressEvent<FileReader>) => {
        if (e.target?.result) {
          this.fotoBase64 = e.target.result as string;
          this.fotoPreview = this.fotoBase64;
        }
      };
      reader.readAsDataURL(file);
    }
  }

  onSubmit(): void {
    if (this.perfilForm.valid) {
      this.loading = true;
      const data: any = {
        nome: this.perfilForm.value.nome,
        email: this.perfilForm.value.email
      };

      if (this.perfilForm.value.senha) {
        data.senha = this.perfilForm.value.senha;
      }

      // Adicionar foto se foi alterada
      if (this.fotoBase64) {
        data.foto_base64 = this.fotoBase64;
      }

      this.userService.updateMe(data).subscribe({
        next: (response) => {
          this.loading = false;
          this.toast.show(response.message, 'success');
          
          // Atualizar dados do usuário no localStorage
          const currentUser = this.authService.currentUserValue;
          if (currentUser) {
            const updatedUser = { ...currentUser, ...response.user };
            localStorage.setItem('currentUser', JSON.stringify(updatedUser));
          }

          // Limpar foto temporária e campo de senha
          this.fotoBase64 = null;
          this.perfilForm.patchValue({ senha: '' });
        },
        error: (error) => {
          this.loading = false;
          const messages = error.error?.errors || [error.error?.error || 'Erro ao atualizar perfil'];
          this.toast.show(messages.join(', '), 'danger', 5000);
        }
      });
    }
  }
}
