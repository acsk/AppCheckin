import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet, RouterLink, RouterLinkActive, Router } from '@angular/router';
import { AuthService } from './services/auth.service';
import { LoadingService } from './services/loading.service';
import { Observable } from 'rxjs';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive
  ],
  template: `
    <div class="min-h-screen bg-slate-900 text-slate-100">
      <header *ngIf="authService.isAuthenticated()" class="sticky top-0 z-20 border-b border-slate-800 bg-slate-900/80 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-blue-500 text-lg font-semibold text-slate-900">
              AC
            </div>
            <div>
              <p class="text-sm uppercase tracking-[0.2em] text-slate-400">App Check-in</p>
              <p class="text-base font-semibold text-slate-100">Gestão de Turmas</p>
            </div>
          </div>

          <button
            class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-800 bg-slate-900 text-slate-100 transition hover:border-emerald-400 hover:text-emerald-300"
            (click)="toggleMenu()"
            aria-label="Abrir menu"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
              <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
            </svg>
          </button>
        </div>
      </header>

      <div
        *ngIf="authService.isAuthenticated()"
        [class.opacity-0]="!menuOpen"
        [class.pointer-events-none]="!menuOpen"
        class="fixed inset-0 z-30 flex transition-opacity duration-200"
      >
        <div class="h-full w-64 bg-slate-950 border-r border-slate-800 p-6 shadow-2xl shadow-black/50">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Usuário</p>
              <p class="text-sm font-semibold text-slate-100">{{ authService.currentUserValue?.nome }}</p>
            </div>
            <button (click)="toggleMenu()" aria-label="Fechar menu" class="rounded-lg border border-slate-800 p-2 text-slate-300 hover:border-rose-400 hover:text-rose-200">
              ✕
            </button>
          </div>

          <nav class="mt-8 space-y-2">
            <a (click)="navigateAndClose('/dashboard')" routerLinkActive="bg-emerald-500/10 text-emerald-200 border-emerald-500/40"
               class="block rounded-xl border border-transparent px-4 py-3 text-sm font-semibold text-slate-100 hover:border-emerald-400/50 hover:bg-emerald-400/5 hover:text-emerald-200">Dashboard</a>
            <a (click)="navigateAndClose('/checkin')" routerLinkActive="bg-emerald-500/10 text-emerald-200 border-emerald-500/40"
               class="block rounded-xl border border-transparent px-4 py-3 text-sm font-semibold text-slate-100 hover:border-emerald-400/50 hover:bg-emerald-400/5 hover:text-emerald-200">Check-in</a>
            <a (click)="navigateAndClose('/historico')" routerLinkActive="bg-emerald-500/10 text-emerald-200 border-emerald-500/40"
               class="block rounded-xl border border-transparent px-4 py-3 text-sm font-semibold text-slate-100 hover:border-emerald-400/50 hover:bg-emerald-400/5 hover:text-emerald-200">Histórico</a>
            <a (click)="navigateAndClose('/perfil')" routerLinkActive="bg-emerald-500/10 text-emerald-200 border-emerald-500/40"
               class="block rounded-xl border border-transparent px-4 py-3 text-sm font-semibold text-slate-100 hover:border-emerald-400/50 hover:bg-emerald-400/5 hover:text-emerald-200">Perfil</a>
          </nav>

          <div class="mt-auto pt-8">
            <button (click)="logout()" class="w-full rounded-xl border border-rose-400/50 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-100 transition hover:bg-rose-500/20">
              Sair
            </button>
          </div>
        </div>

        <div class="flex-1 bg-black/50" (click)="toggleMenu()"></div>
      </div>

      <main class="mx-auto flex max-w-6xl flex-1 flex-col px-4 py-10 md:px-6 lg:px-8">
        <router-outlet></router-outlet>
      </main>

      <div
        *ngIf="loading$ | async"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur"
      >
        <div class="relative flex h-28 w-28 items-center justify-center rounded-3xl bg-slate-900/90 border border-slate-700 shadow-2xl shadow-emerald-500/20">
          <div class="absolute h-24 w-24 rounded-full border-4 border-emerald-400/30"></div>
          <div class="absolute h-24 w-24 rounded-full border-4 border-transparent border-t-emerald-400 animate-spin"></div>
          <div class="absolute h-20 w-20 rounded-2xl bg-gradient-to-br from-emerald-500 to-cyan-400 opacity-20 blur-lg"></div>
          <div class="relative text-center">
            <p class="text-xs uppercase tracking-[0.25em] text-emerald-200">carregando</p>
            <p class="text-sm font-semibold text-slate-100">aguarde...</p>
          </div>
        </div>
      </div>
    </div>
  `
})
export class AppComponent {
  menuOpen = false;
  loading$: Observable<boolean>;

  constructor(
    public authService: AuthService,
    private router: Router,
    private loadingService: LoadingService
  ) {
    this.loading$ = this.loadingService.isLoading$;
  }

  toggleMenu(): void {
    this.menuOpen = !this.menuOpen;
  }

  navigateAndClose(path: string): void {
    this.menuOpen = false;
    this.router.navigate([path]);
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
