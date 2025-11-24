import { Injectable } from '@angular/core';
import { CanActivate, Router, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root'
})
export class AdminGuard implements CanActivate {
  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): boolean {
    const user = this.authService.currentUserValue;
    
    // role_id: 1 = aluno, 2 = admin, 3 = super_admin
    if (user && (user.role_id === 2 || user.role_id === 3)) {
      return true;
    }
    
    // Não é admin, redirecionar para dashboard
    this.router.navigate(['/dashboard']);
    return false;
  }
}
