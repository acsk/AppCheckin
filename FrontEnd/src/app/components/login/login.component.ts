import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterLink
  ],
  template: `
    <div class="page-shell auth-shell">
      <div class="content-shell auth-grid">
        <section class="hero">
          <span class="chip">Check-in inteligente</span>
          <h1>Acompanhe turmas, faça check-in e veja ocupação em tempo real.</h1>
          <p class="lead">Autentique-se para acessar dashboard, listar turmas, conferir alunos e registrar presença.</p>
          <div class="feature-grid">
            <div class="feature-card">
              <p class="title">Listagem de turmas</p>
              <p class="muted">GET /turmas</p>
            </div>
            <div class="feature-card">
              <p class="title">Alunos por turma</p>
              <p class="muted">GET /turmas/:id/alunos</p>
            </div>
            <div class="feature-card">
              <p class="title">Check-in rápido</p>
              <p class="muted">POST /checkin</p>
            </div>
            <div class="feature-card">
              <p class="title">Histórico pessoal</p>
              <p class="muted">GET /me/checkins</p>
            </div>
          </div>
        </section>

        <section class="card auth-card">
          <div class="card-header">
            <p class="eyebrow">Acesso</p>
            <h2>Entre com suas credenciais</h2>
          </div>

          <form [formGroup]="loginForm" (ngSubmit)="onSubmit()" class="form-shell">
            <div class="form-grid">
              <div class="form-field full">
                <label class="form-label" for="email">Email</label>
                <input
                  id="email"
                  type="email"
                  formControlName="email"
                  class="form-control"
                  placeholder="seu@email.com"
                />
                <p class="field-error" *ngIf="loginForm.get('email')?.touched && loginForm.get('email')?.hasError('required')">
                  Email é obrigatório
                </p>
                <p class="field-error" *ngIf="loginForm.get('email')?.touched && loginForm.get('email')?.hasError('email')">
                  Informe um email válido
                </p>
              </div>

              <div class="form-field full">
                <label class="form-label" for="senha">Senha</label>
                <input
                  id="senha"
                  type="password"
                  formControlName="senha"
                  class="form-control"
                  placeholder="••••••••"
                />
                <p class="field-error" *ngIf="loginForm.get('senha')?.touched && loginForm.get('senha')?.hasError('required')">
                  Senha é obrigatória
                </p>
              </div>
            </div>

            <div *ngIf="serverError" class="banner danger">
              {{ serverError }}
            </div>

            <div class="form-actions full">
              <button
                type="submit"
                [disabled]="loginForm.invalid || loading"
                class="btn btn-primary full"
              >
                <span *ngIf="loading">Entrando...</span>
                <span *ngIf="!loading">Entrar</span>
              </button>
            </div>
          </form>

          <div class="auth-footer">
            <span>Primeira vez por aqui?</span>
            <a routerLink="/register">Criar conta</a>
          </div>
        </section>
      </div>
    </div>
  `,
  styles: [`
    .auth-shell {
      background: radial-gradient(circle at 20% 20%, rgba(37, 99, 235, 0.12), transparent 32%), var(--surface-muted);
    }
    .auth-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 32px;
      align-items: center;
      min-height: 90vh;
    }
    .hero h1 {
      font-size: clamp(28px, 3vw, 38px);
      line-height: 1.2;
      color: var(--text-strong);
      margin: 10px 0;
    }
    .feature-grid {
      margin-top: 16px;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
    }
    .feature-card {
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      background: #fff;
      box-shadow: var(--shadow-soft);
    }
    .feature-card .title {
      font-weight: 700;
      margin: 0 0 4px 0;
      color: var(--text-strong);
    }
    .auth-card {
      padding: 20px 20px 18px;
    }
    .card-header {
      margin-bottom: 10px;
    }
    .card-header h2 {
      margin: 4px 0 0;
      color: var(--text-strong);
    }
    .banner {
      padding: 10px 12px;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      background: #fff;
      font-weight: 600;
    }
    .banner.danger {
      background: #fef2f2;
      border-color: rgba(220, 38, 38, 0.25);
      color: #991b1b;
    }
    .btn.full {
      width: 100%;
    }
    .auth-footer {
      margin-top: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: var(--text-soft);
    }
    .auth-footer a {
      font-weight: 700;
    }
  `]
})
export class LoginComponent {
  loginForm: FormGroup;
  loading = false;
  serverError = '';

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private toast: ToastService
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
          this.toast.show(response.message, 'success');
          this.router.navigate(['/dashboard']);
        },
        error: (error) => {
          this.loading = false;
          const message = error.error?.error || 'Erro ao fazer login';
          this.serverError = message;
          this.toast.show(message, 'danger', 5000);
        }
      });
    }
  }
}
