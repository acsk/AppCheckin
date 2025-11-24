import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet, RouterLink, RouterLinkActive, Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { Observable } from 'rxjs';
import { LoadingService } from './services/loading.service';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
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

      <ion-menu contentId="main-content" *ngIf="authService.isAuthenticated()" class="app-menu">
        <ion-header>
          <ion-toolbar class="app-toolbar">
            <ion-title class="text-sm tracking-wide">Menu</ion-title>
          </ion-toolbar>
        </ion-header>
        <ion-content class="app-menu-content">
          <ion-list class="app-menu-list">
            <ion-item button detail="false" class="menu-item" (click)="navigateAndClose('/dashboard')" routerLinkActive="ion-activated">Dashboard</ion-item>
            <ion-item button detail="false" class="menu-item" (click)="navigateAndClose('/checkin')" routerLinkActive="ion-activated">Check-in</ion-item>
            <ion-item button detail="false" class="menu-item" (click)="navigateAndClose('/historico')" routerLinkActive="ion-activated">Histórico</ion-item>
            <ion-item button detail="false" class="menu-item" (click)="navigateAndClose('/perfil')" routerLinkActive="ion-activated">Perfil</ion-item>
          </ion-list>
          <ion-item button detail="false" class="menu-item text-rose-300" (click)="logout()">Sair</ion-item>
        </ion-content>
      </ion-menu>

      <ion-content id="main-content" [fullscreen]="true">
        <div class="mx-auto flex max-w-6xl flex-1 flex-col px-4 py-10 md:px-6 lg:px-8">
          <router-outlet></router-outlet>
        </div>
        <ion-loading [isOpen]="loading$ | async" message="Carregando..." translucent></ion-loading>
      </ion-content>
    </ion-app>
  `
})
export class AppComponent {
  loading$: Observable<boolean>;

  constructor(
    public authService: AuthService,
    private router: Router,
    private loadingService: LoadingService
  ) {
    this.loading$ = this.loadingService.isLoading$;
  }

  navigateAndClose(path: string): void {
    this.router.navigate([path]);
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
