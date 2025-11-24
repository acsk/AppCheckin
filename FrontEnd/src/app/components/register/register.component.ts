import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { IonicModule } from '@ionic/angular';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterLink,
    IonicModule
  ],
  template: `
    <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-900">
      <div class="mx-auto flex min-h-screen max-w-5xl flex-col items-center justify-center px-6 py-12">
        <div class="w-full max-w-2xl overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/70 shadow-2xl shadow-blue-500/10">
          <div class="border-b border-slate-800 bg-slate-900/70 px-8 py-6">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Crie sua conta</p>
            <h1 class="text-2xl font-semibold text-slate-50">Cadastre-se no App Check-in</h1>
            <p class="text-sm text-slate-400">Acompanhe turmas, registre presenças e veja ocupação.</p>
          </div>

          <form [formGroup]="registerForm" (ngSubmit)="onSubmit()" class="space-y-6 px-8 py-10">
            <div class="space-y-2">
              <label class="text-sm font-semibold text-slate-100" for="nome">Nome</label>
              <input
                id="nome"
                type="text"
                formControlName="nome"
                class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-slate-50 outline-none ring-blue-400/30 transition focus:border-blue-400 focus:ring-4"
                placeholder="Seu nome completo"
              />
              <p class="text-xs text-rose-400" *ngIf="registerForm.get('nome')?.touched && registerForm.get('nome')?.hasError('required')">
                Nome é obrigatório
              </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2 sm:col-span-2">
                <label class="text-sm font-semibold text-slate-100" for="email">Email</label>
                <input
                  id="email"
                  type="email"
                  formControlName="email"
                  class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-slate-50 outline-none ring-blue-400/30 transition focus:border-blue-400 focus:ring-4"
                  placeholder="seu@email.com"
                />
                <p class="text-xs text-rose-400" *ngIf="registerForm.get('email')?.touched && registerForm.get('email')?.hasError('required')">
                  Email é obrigatório
                </p>
                <p class="text-xs text-rose-400" *ngIf="registerForm.get('email')?.touched && registerForm.get('email')?.hasError('email')">
                  Informe um email válido
                </p>
              </div>

              <div class="space-y-2 sm:col-span-2">
                <label class="text-sm font-semibold text-slate-100" for="senha">Senha</label>
                <input
                  id="senha"
                  type="password"
                  formControlName="senha"
                  class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-slate-50 outline-none ring-blue-400/30 transition focus:border-blue-400 focus:ring-4"
                  placeholder="Mínimo 6 caracteres"
                />
                <p class="text-xs text-rose-400" *ngIf="registerForm.get('senha')?.touched && registerForm.get('senha')?.hasError('required')">
                  Senha é obrigatória
                </p>
                <p class="text-xs text-rose-400" *ngIf="registerForm.get('senha')?.touched && registerForm.get('senha')?.hasError('minlength')">
                  Senha deve ter no mínimo 6 caracteres
                </p>
              </div>
            </div>

            <div *ngIf="serverError" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {{ serverError }}
            </div>

            <button
              type="submit"
              [disabled]="registerForm.invalid || loading"
              class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 via-emerald-400 to-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <svg *ngIf="loading" class="h-5 w-5 animate-spin text-slate-900" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 000 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
              </svg>
              <span>{{ loading ? 'Cadastrando...' : 'Criar conta' }}</span>
            </button>

            <div class="flex items-center justify-between text-sm text-slate-400">
              <span>Já possui login?</span>
              <a routerLink="/login" class="font-semibold text-blue-300 hover:text-blue-200">Fazer login</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  `
})
export class RegisterComponent {
  registerForm: FormGroup;
  loading = false;
  serverError = '';

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private toast: ToastService
  ) {
    this.registerForm = this.fb.group({
      nome: ['', Validators.required],
      email: ['', [Validators.required, Validators.email]],
      senha: ['', [Validators.required, Validators.minLength(6)]]
    });
  }

  onSubmit(): void {
    if (this.registerForm.valid) {
      this.loading = true;
      this.serverError = '';
      this.authService.register(this.registerForm.value).subscribe({
        next: (response) => {
          this.loading = false;
          this.toast.show(response.message, 'success');
          this.router.navigate(['/dashboard']);
        },
        error: (error) => {
          this.loading = false;
          const messages = error.error?.errors || [error.error?.error || 'Erro ao cadastrar'];
          this.serverError = messages.join(', ');
          this.toast.show(messages.join(', '), 'danger', 5000);
        }
      });
    }
  }
}
