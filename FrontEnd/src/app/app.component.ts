import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLinkActive, Router, RouterModule } from '@angular/router';
import { IonicModule, MenuController } from '@ionic/angular';
import { Observable } from 'rxjs';
import { LoadingService } from './services/loading.service';
import { AuthService } from './services/auth.service';
import { addIcons } from 'ionicons';
import { 
  home, checkmarkCircle, time, person, speedometer, people, 
  calendar, today, pricetag, checkmarkDone, business, shieldCheckmark 
} from 'ionicons/icons';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    RouterLinkActive,
    RouterModule,
    IonicModule
  ],
  template: `
    <ion-app>
      <ion-header *ngIf="authService.isAuthenticated()">
        <ion-toolbar class="app-toolbar">
          <ion-buttons slot="start">
            <ion-menu-button autoHide="false"></ion-menu-button>
          </ion-buttons>
          <ion-title class="text-base font-semibold">App Check-in</ion-title>
        </ion-toolbar>
      </ion-header>

      <ion-menu type="overlay" contentId="main-content" menuId="main-menu" *ngIf="authService.isAuthenticated()" class="app-menu">
        <ion-header>
          <ion-toolbar class="app-toolbar">
            <ion-title class="text-sm tracking-wide">Menu</ion-title>
          </ion-toolbar>
        </ion-header>
        <ion-content class="app-menu-content">
          <div class="user-profile-header">
            <div class="avatar-wrapper">
              <img
                [src]="avatarUrl()"
                alt="Foto do usuário"
                class="user-avatar"
              />
              <div class="status-indicator"></div>
            </div>
            <div class="user-info">
              <p class="user-name">{{ authService.currentUserValue?.nome }}</p>
              <p class="user-email">{{ authService.currentUserValue?.email }}</p>
              <span class="user-badge" *ngIf="authService.currentUserValue?.role_id === 2 || authService.currentUserValue?.role_id === 3">
                <ion-icon name="shield-checkmark"></ion-icon>
                Admin
              </span>
            </div>
          </div>
          <ion-list class="app-menu-list">
            <!-- Menu para Alunos -->
            <ng-container *ngIf="authService.currentUserValue?.role_id === 1">
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/dashboard'" routerDirection="root" (click)="navigateFromMenu('/dashboard')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="home"></ion-icon>
                  <ion-label>Dashboard</ion-label>
                </ion-item>
              </ion-menu-toggle>
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/checkin'" routerDirection="root" (click)="navigateFromMenu('/checkin')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="checkmark-circle"></ion-icon>
                  <ion-label>Check-in</ion-label>
                </ion-item>
              </ion-menu-toggle>
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/historico'" routerDirection="root" (click)="navigateFromMenu('/historico')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="time"></ion-icon>
                  <ion-label>Histórico</ion-label>
                </ion-item>
              </ion-menu-toggle>
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/perfil'" routerDirection="root" (click)="navigateFromMenu('/perfil')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="person"></ion-icon>
                  <ion-label>Perfil</ion-label>
                </ion-item>
              </ion-menu-toggle>
            </ng-container>

            <!-- Menu para Administradores -->
            <ng-container *ngIf="authService.currentUserValue?.role_id === 2 || authService.currentUserValue?.role_id === 3">
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/admin/dashboard'" routerDirection="root" (click)="navigateFromMenu('/admin/dashboard')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="speedometer"></ion-icon>
                  <ion-label>Dashboard Admin</ion-label>
                </ion-item>
              </ion-menu-toggle>
              
              <ion-item-divider class="menu-divider">
                <ion-label class="text-xs font-semibold text-slate-400">GESTÃO</ion-label>
              </ion-item-divider>
              
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/admin/alunos'" routerDirection="root" (click)="navigateFromMenu('/admin/alunos')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="people"></ion-icon>
                  <ion-label>Alunos</ion-label>
                </ion-item>
              </ion-menu-toggle>
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/admin/gerenciar-horarios'" routerDirection="root" (click)="navigateFromMenu('/admin/gerenciar-horarios')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="calendar"></ion-icon>
                  <ion-label>Planejamento</ion-label>
                </ion-item>
              </ion-menu-toggle>
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/admin/dias'" routerDirection="root" (click)="navigateFromMenu('/admin/dias')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="today"></ion-icon>
                  <ion-label>Dias</ion-label>
                </ion-item>
              </ion-menu-toggle>
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/admin/turmas'" routerDirection="root" (click)="navigateFromMenu('/admin/turmas')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="time"></ion-icon>
                  <ion-label>Turmas/Horários</ion-label>
                </ion-item>
              </ion-menu-toggle>
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/admin/planos'" routerDirection="root" (click)="navigateFromMenu('/admin/planos')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="pricetag"></ion-icon>
                  <ion-label>Planos</ion-label>
                </ion-item>
              </ion-menu-toggle>
              
              <ion-item-divider class="menu-divider">
                <ion-label class="text-xs font-semibold text-slate-400">AÇÕES</ion-label>
              </ion-item-divider>
              
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/admin/checkin-manual'" routerDirection="root" (click)="navigateFromMenu('/admin/checkin-manual')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="checkmark-done"></ion-icon>
                  <ion-label>Check-in Manual</ion-label>
                </ion-item>
              </ion-menu-toggle>
              
              <ion-item-divider class="menu-divider">
                <ion-label class="text-xs font-semibold text-slate-400">CONFIGURAÇÕES</ion-label>
              </ion-item-divider>
              
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/admin/perfil-tenant'" routerDirection="root" (click)="navigateFromMenu('/admin/perfil-tenant')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="business"></ion-icon>
                  <ion-label>Perfil do Tenant</ion-label>
                </ion-item>
              </ion-menu-toggle>
              <ion-menu-toggle auto-hide="true">
                <ion-item button detail="false" class="menu-item" [routerLink]="'/perfil'" routerDirection="root" (click)="navigateFromMenu('/perfil')" routerLinkActive="ion-activated">
                  <ion-icon slot="start" name="person"></ion-icon>
                  <ion-label>Meu Perfil</ion-label>
                </ion-item>
              </ion-menu-toggle>
            </ng-container>
          </ion-list>
          <ion-item button detail="false" class="menu-item text-rose-300" (click)="logout()">Sair</ion-item>
        </ion-content>
      </ion-menu>

      <ion-router-outlet id="main-content"></ion-router-outlet>

      <ion-loading [isOpen]="loading$ | async" message="Carregando..." translucent></ion-loading>
    </ion-app>
  `,
  styles: [`
    .app-menu {
      --width: 250px;
      --background: #0b1224;
    }
    .app-menu::part(backdrop) {
      background: rgba(0, 0, 0, 0.55);
    }
    .app-menu-content {
      --background: #0b1224;
      padding: 12px 10px 16px 10px;
    }
    .app-toolbar {
      --background: #0f172a;
      --color: #e2e8f0;
      --min-height: 48px;
      padding-inline-start: 10px;
    }
    .user-profile-header {
      display: flex;
      gap: 10px;
      align-items: center;
      padding: 6px 8px 12px 8px;
      border-bottom: 1px solid rgba(148, 163, 184, 0.12);
      margin-bottom: 10px;
    }
    .avatar-wrapper {
      position: relative;
      width: 42px;
      height: 42px;
    }
    .user-avatar {
      width: 100%;
      height: 100%;
      border-radius: 12px;
      object-fit: cover;
      border: 1px solid rgba(59, 130, 246, 0.3);
      background: rgba(59, 130, 246, 0.08);
    }
    .status-indicator {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 12px;
      height: 12px;
      background: #22d3ee;
      border: 2px solid #0b1224;
      border-radius: 50%;
      box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.2);
    }
    .user-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .user-name {
      margin: 0;
      font-weight: 700;
      color: #e2e8f0;
      font-size: 14px;
    }
    .user-email {
      margin: 0;
      font-size: 12px;
      color: #94a3b8;
    }
    .user-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 8px;
      border-radius: 10px;
      background: rgba(59, 130, 246, 0.12);
      color: #93c5fd;
      font-size: 11px;
      font-weight: 600;
      width: fit-content;
    }
    .app-menu-list {
      padding: 0;
    }
    .menu-divider {
      margin: 8px 0 6px 0;
      --background: transparent;
      --color: #94a3b8;
    }
    .menu-item {
      --background: transparent;
      --color: #e2e8f0;
      --padding-start: 10px;
      --padding-end: 10px;
      --min-height: 44px;
      border-radius: 10px;
      margin: 2px 2px;
      font-size: 14px;
    }
    .menu-item ion-icon {
      color: #93c5fd;
    }
    .menu-item.ion-activated,
    .menu-item:hover {
      --background: rgba(59, 130, 246, 0.12);
    }
  `]
})
export class AppComponent {
  loading$: Observable<boolean>;

  constructor(
    public authService: AuthService,
    private router: Router,
    private loadingService: LoadingService,
    private menuCtrl: MenuController
  ) {
    this.loading$ = this.loadingService.isLoading$;
    
    // Registrar ícones
    addIcons({ 
      home, 
      checkmarkCircle, 
      time, 
      person, 
      speedometer, 
      people, 
      calendar, 
      today, 
      pricetag, 
      checkmarkDone, 
      business,
      shieldCheckmark
    });
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  async navigateFromMenu(path: string): Promise<void> {
    await this.menuCtrl.close('main-menu');
    await this.router.navigateByUrl(path);
  }

  avatarUrl(): string {
    const user = this.authService.currentUserValue;
    const name = user?.nome || 'Usuario';
    const fallback = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=0f172a&color=f8fafc&size=128`;
    return (user as any)?.foto_url || user?.foto_base64 || fallback;
  }
}
