import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterLink,
    MatSnackBarModule
  ],
  template: `
    <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-900">
      <div class="mx-auto grid min-h-screen max-w-6xl grid-cols-1 items-center gap-10 px-6 py-12 lg:grid-cols-2 lg:px-10">
        <div class="hidden space-y-6 lg:block">
          <p class="inline-flex items-center gap-2 rounded-full border border-slate-800 bg-slate-900/70 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">
            Check-in inteligente
          </p>
          <h1 class="text-4xl font-bold text-slate-50 sm:text-5xl">
            Acompanhe turmas, faça check-in e veja ocupação em tempo real.
          </h1>
          <p class="text-lg text-slate-300">
            Autentique-se com email e senha para acessar o dashboard, listar turmas, conferir alunos e registrar presença rapidamente.
          </p>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <p class="text-sm font-semibold text-slate-100">Listagem de turmas</p>
              <p class="text-sm text-slate-400">GET /turmas</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <p class="text-sm font-semibold text-slate-100">Alunos por turma</p>
              <p class="text-sm text-slate-400">GET /turmas/:id/alunos</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <p class="text-sm font-semibold text-slate-100">Check-in rápido</p>
              <p class="text-sm text-slate-400">POST /checkin</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <p class="text-sm font-semibold text-slate-100">Histórico pessoal</p>
              <p class="text-sm text-slate-400">GET /me/checkins</p>
            </div>
          </div>
        </div>

        <div class="w-full lg:justify-self-end">
          <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 shadow-2xl shadow-emerald-500/10">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-400 via-blue-500 to-cyan-400"></div>
            <div class="space-y-8 px-8 py-10">
              <div class="space-y-1">
                <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Acesso</p>
                <h2 class="text-2xl font-semibold text-slate-50">Entre com suas credenciais</h2>
              </div>

              <form [formGroup]="loginForm" (ngSubmit)="onSubmit()" class="space-y-6">
                <div class="space-y-2">
                  <label class="text-sm font-semibold text-slate-100" for="email">Email</label>
                  <input
                    id="email"
                    type="email"
                    formControlName="email"
                    class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-slate-50 outline-none ring-emerald-400/30 transition focus:border-emerald-400 focus:ring-4"
                    placeholder="seu@email.com"
                  />
                  <p class="text-xs text-rose-400" *ngIf="loginForm.get('email')?.touched && loginForm.get('email')?.hasError('required')">
                    Email é obrigatório
                  </p>
                  <p class="text-xs text-rose-400" *ngIf="loginForm.get('email')?.touched && loginForm.get('email')?.hasError('email')">
                    Informe um email válido
                  </p>
                </div>

                <div class="space-y-2">
                  <label class="text-sm font-semibold text-slate-100" for="senha">Senha</label>
                  <input
                    id="senha"
                    type="password"
                    formControlName="senha"
                    class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-slate-50 outline-none ring-emerald-400/30 transition focus:border-emerald-400 focus:ring-4"
                    placeholder="••••••••"
                  />
                  <p class="text-xs text-rose-400" *ngIf="loginForm.get('senha')?.touched && loginForm.get('senha')?.hasError('required')">
                    Senha é obrigatória
                  </p>
                </div>

                <div *ngIf="serverError" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                  {{ serverError }}
                </div>

                <button
                  type="submit"
                  [disabled]="loginForm.invalid || loading"
                  class="group flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-400 via-blue-500 to-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  <svg *ngIf="loading" class="h-5 w-5 animate-spin text-slate-900" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 000 16v-4l-3 3 3 3v-4a8 8 0 01-8-8z"></path>
                  </svg>
                  <span>{{ loading ? 'Entrando...' : 'Entrar' }}</span>
                </button>
              </form>

              <div class="flex items-center justify-between text-sm text-slate-400">
                <span>Primeira vez por aqui?</span>
                <a routerLink="/register" class="font-semibold text-emerald-300 hover:text-emerald-200">Criar conta</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  `
})
export class LoginComponent {
  loginForm: FormGroup;
  loading = false;
  serverError = '';

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private snackBar: MatSnackBar
  ) {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      senha: ['', Validators.required]
    });
  }

  onSubmit(): void {
    if (this.loginForm.valid) {
      this.loading = true;
      this.serverError = '';
      this.authService.login(this.loginForm.value).subscribe({
        next: (response) => {
          this.loading = false;
          this.snackBar.open(response.message, 'Fechar', { duration: 3000 });
          this.router.navigate(['/dashboard']);
        },
        error: (error) => {
          this.loading = false;
          const message = error.error?.error || 'Erro ao fazer login';
          this.serverError = message;
          this.snackBar.open(message, 'Fechar', { duration: 5000 });
        }
      });
    }
  }
}
