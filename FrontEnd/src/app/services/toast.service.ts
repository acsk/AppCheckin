import { Injectable } from '@angular/core';
import { ToastController } from '@ionic/angular';

@Injectable({
  providedIn: 'root'
})
export class ToastService {
  constructor(private toastController: ToastController) {}

  async show(
    message: string,
    color: 'success' | 'danger' | 'warning' | 'medium' = 'medium',
    duration = 3000,
    position: 'top' | 'bottom' | 'middle' = 'top'
  ): Promise<void> {
    const toast = await this.toastController.create({
      message,
      duration,
      color,
      position,
      cssClass: 'ion-toast-strong'
    });
    await toast.present();
  }
}
