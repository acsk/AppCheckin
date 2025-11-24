import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const alunoGuard = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const user = authService.currentUserValue;
  
  // Permitir apenas role_id = 1 (aluno)
  if (user && user.role_id === 1) {
    return true;
  }

  // Redirecionar admin para painel admin
  if (user && (user.role_id === 2 || user.role_id === 3)) {
    router.navigate(['/admin']);
    return false;
  }

  router.navigate(['/login']);
  return false;
};
