import { Component, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLinkActive, Router, RouterModule } from '@angular/router';
import { Observable } from 'rxjs';
import { LoadingService } from './services/loading.service';
import { AuthService } from './services/auth.service';
import { MatSidenav, MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatListModule } from '@angular/material/list';
import { MatIconModule } from '@angular/material/icon';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressBarModule } from '@angular/material/progress-bar';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    RouterLinkActive,
    RouterModule,
    MatSidenavModule,
    MatToolbarModule,
    MatButtonModule,
    MatListModule,
    MatIconModule,
    MatDividerModule,
    MatProgressBarModule
  ],
  template: `
    <mat-sidenav-container class="app-layout">
      <mat-sidenav
        #drawer
        mode="side"
        class="app-sidenav"
        [opened]="isAuthenticated"
        *ngIf="isAuthenticated"
      >
        <div class="user-card" *ngIf="authService.currentUserValue">
          <img [src]="avatarUrl()" alt="Foto do usuário" class="user-avatar" />
          <div class="user-info">
            <p class="user-name">{{ authService.currentUserValue?.nome }}</p>
            <p class="user-email">{{ authService.currentUserValue?.email }}</p>
            <span class="user-role" *ngIf="authService.currentUserValue?.role_id === 2 || authService.currentUserValue?.role_id === 3">
              <mat-icon>admin_panel_settings</mat-icon>
              Admin
            </span>
          </div>
        </div>

        <mat-divider class="section-divider"></mat-divider>

        <mat-nav-list>
          <a mat-list-item routerLink="/dashboard" routerLinkActive="active-link" (click)="closeDrawer()" *ngIf="authService.currentUserValue?.role_id === 1">
            <mat-icon matListItemIcon>dashboard</mat-icon>
            <span matLine>Dashboard</span>
          </a>
          <a mat-list-item routerLink="/checkin" routerLinkActive="active-link" (click)="closeDrawer()" *ngIf="authService.currentUserValue?.role_id === 1">
            <mat-icon matListItemIcon>task_alt</mat-icon>
            <span matLine>Check-in</span>
          </a>
          <a mat-list-item routerLink="/historico" routerLinkActive="active-link" (click)="closeDrawer()" *ngIf="authService.currentUserValue?.role_id === 1">
            <mat-icon matListItemIcon>history</mat-icon>
            <span matLine>Histórico</span>
          </a>
          <a mat-list-item routerLink="/perfil" routerLinkActive="active-link" (click)="closeDrawer()" *ngIf="authService.currentUserValue?.role_id === 1">
            <mat-icon matListItemIcon>account_circle</mat-icon>
            <span matLine>Perfil</span>
          </a>

          <ng-container *ngIf="authService.currentUserValue?.role_id === 2 || authService.currentUserValue?.role_id === 3">
            <mat-divider class="section-divider"></mat-divider>
            <a mat-list-item routerLink="/admin/dashboard" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>speed</mat-icon>
              <span matLine>Dashboard Admin</span>
            </a>
            <mat-divider class="section-divider"></mat-divider>
            <a mat-list-item routerLink="/admin/alunos" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>group</mat-icon>
              <span matLine>Alunos</span>
            </a>
            <a mat-list-item routerLink="/admin/gerenciar-horarios" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>event_note</mat-icon>
              <span matLine>Planejamento</span>
            </a>
            <a mat-list-item routerLink="/admin/dias" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>today</mat-icon>
              <span matLine>Dias</span>
            </a>
            <a mat-list-item routerLink="/admin/turmas" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>schedule</mat-icon>
              <span matLine>Turmas/Horários</span>
            </a>
            <a mat-list-item routerLink="/admin/planos" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>sell</mat-icon>
              <span matLine>Planos</span>
            </a>
            <a mat-list-item routerLink="/admin/checkin-manual" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>check_circle</mat-icon>
              <span matLine>Check-in Manual</span>
            </a>
            <mat-divider class="section-divider"></mat-divider>
            <a mat-list-item routerLink="/admin/perfil-tenant" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>apartment</mat-icon>
              <span matLine>Perfil do Tenant</span>
            </a>
            <a mat-list-item routerLink="/perfil" routerLinkActive="active-link" (click)="closeDrawer()">
              <mat-icon matListItemIcon>person</mat-icon>
              <span matLine>Meu Perfil</span>
            </a>
          </ng-container>
        </mat-nav-list>

        <div class="sidenav-footer">
          <button mat-stroked-button color="warn" (click)="logout()">Sair</button>
        </div>
      </mat-sidenav>

      <mat-sidenav-content>
        <mat-toolbar color="primary" *ngIf="isAuthenticated">
          <button mat-icon-button type="button" aria-label="Abrir menu" (click)="toggleDrawer()">
            <mat-icon>menu</mat-icon>
          </button>
          <span class="toolbar-title">App Check-in</span>
        </mat-toolbar>

        <div class="content">
          <router-outlet></router-outlet>
        </div>
      </mat-sidenav-content>
    </mat-sidenav-container>

    <mat-progress-bar *ngIf="loading$ | async" mode="indeterminate" color="accent"></mat-progress-bar>
  `,
  styles: [`
    :host {
      display: block;
      height: 100%;
    }
    .app-layout {
      min-height: 100vh;
    }
    .app-sidenav {
      width: 260px;
    }
    .user-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px;
    }
    .user-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
    }
    .user-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .user-name {
      margin: 0;
      font-weight: 600;
    }
    .user-email {
      margin: 0;
      font-size: 13px;
      color: rgba(0, 0, 0, 0.6);
    }
    .user-role {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 12px;
      color: rgba(0, 0, 0, 0.6);
    }
    .toolbar-title {
      margin-left: 8px;
      font-weight: 600;
    }
    .content {
      padding: 16px;
      min-height: 100vh;
    }
    .sidenav-footer {
      padding: 12px 16px;
    }
    .active-link {
      font-weight: 600;
    }
    .section-divider {
      margin: 4px 0;
    }
  `]
})
export class AppComponent {
  @ViewChild('drawer') sidenav?: MatSidenav;
  loading$: Observable<boolean>;

  constructor(
    public authService: AuthService,
    private router: Router,
    private loadingService: LoadingService
  ) {
    this.loading$ = this.loadingService.isLoading$;
  }

  get isAuthenticated(): boolean {
    return this.authService.isAuthenticated();
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  async toggleDrawer(): Promise<void> {
    if (this.sidenav) {
      await this.sidenav.toggle();
    }
  }

  async closeDrawer(): Promise<void> {
    if (this.sidenav?.opened) {
      await this.sidenav.close();
    }
  }

  avatarUrl(): string {
    const user = this.authService.currentUserValue;
    const name = user?.nome || 'Usuario';
    const fallback = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=0f172a&color=f8fafc&size=128`;
    return (user as any)?.foto_url || user?.foto_base64 || fallback;
  }
}
