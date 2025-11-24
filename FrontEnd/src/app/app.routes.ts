import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { AdminGuard } from './guards/admin.guard';
import { alunoGuard } from './guards/aluno.guard';
import { dashboardRedirectGuard } from './guards/dashboard-redirect.guard';

export const routes: Routes = [
  {
    path: '',
    redirectTo: '/dashboard',
    pathMatch: 'full'
  },
  {
    path: 'login',
    loadComponent: () => import('./components/login/login.component').then(m => m.LoginComponent)
  },
  {
    path: 'register',
    loadComponent: () => import('./components/register/register.component').then(m => m.RegisterComponent)
  },
  {
    path: 'dashboard',
    loadComponent: () => import('./components/dashboard/dashboard.component').then(m => m.DashboardComponent),
    canActivate: [authGuard, dashboardRedirectGuard]
  },
  {
    path: 'checkin',
    loadComponent: () => import('./components/checkin/checkin.component').then(m => m.CheckinComponent),
    canActivate: [authGuard, alunoGuard]
  },
  {
    path: 'historico',
    loadComponent: () => import('./components/historico/historico.component').then(m => m.HistoricoComponent),
    canActivate: [authGuard, alunoGuard]
  },
  {
    path: 'perfil',
    loadComponent: () => import('./components/perfil/perfil.component').then(m => m.PerfilComponent),
    canActivate: [authGuard]
  },
  {
    path: 'turmas/:id',
    loadComponent: () => import('./components/turma-detail/turma-detail.component').then(m => m.TurmaDetailComponent),
    canActivate: [authGuard]
  },
  {
    path: 'admin',
    canActivate: [authGuard, AdminGuard],
    children: [
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full'
      },
      {
        path: 'dashboard',
        loadComponent: () => import('./components/admin/dashboard-admin/dashboard-admin.component').then(m => m.DashboardAdminComponent)
      },
      {
        path: 'alunos',
        loadComponent: () => import('./components/admin/gerenciar-alunos/gerenciar-alunos.component').then(m => m.GerenciarAlunosComponent)
      },
      {
        path: 'gerenciar-horarios',
        loadComponent: () => import('./components/admin/gerenciar-horarios/gerenciar-horarios.component').then(m => m.GerenciarHorariosComponent)
      },
      {
        path: 'dias',
        loadComponent: () => import('./components/admin/gerenciar-dias/gerenciar-dias.component').then(m => m.GerenciarDiasComponent)
      },
      {
        path: 'turmas',
        loadComponent: () => import('./components/admin/gerenciar-turmas/gerenciar-turmas.component').then(m => m.GerenciarTurmasComponent)
      },
      {
        path: 'perfil-tenant',
        loadComponent: () => import('./components/admin/perfil-tenant/perfil-tenant.component').then(m => m.PerfilTenantComponent)
      },
      {
        path: 'planos',
        loadComponent: () => import('./components/admin/gerenciar-planos/gerenciar-planos.component').then(m => m.GerenciarPlanosComponent)
      },
      {
        path: 'checkin-manual',
        loadComponent: () => import('./components/admin/checkin-manual/checkin-manual.component').then(m => m.CheckinManualComponent)
      }
    ]
  },
  {
    path: '**',
    redirectTo: '/dashboard'
  }
];
