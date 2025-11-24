import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const dashboardRedirectGuard = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isAuthenticated()) {
    router.navigate(['/login']);
    return false;
  }

  const currentUser = authService.currentUserValue;
  
  // Se for admin (role_id 2 ou 3), redireciona para /admin/dashboard
  if (currentUser?.role_id === 2 || currentUser?.role_id === 3) {
    router.navigate(['/admin/dashboard']);
    return false;
  }

  // Se for aluno (role_id 1), permite acesso ao dashboard padrão
  return true;
};
