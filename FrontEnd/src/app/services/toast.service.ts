import { DOCUMENT } from '@angular/common';
import { Inject, Injectable, Renderer2, RendererFactory2 } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class ToastService {
  private renderer: Renderer2;
  private container?: HTMLElement;

  constructor(
    @Inject(DOCUMENT) private document: Document,
    rendererFactory: RendererFactory2
  ) {
    this.renderer = rendererFactory.createRenderer(null, null);
  }

  private ensureContainer() {
    if (!this.container) {
      this.container = this.renderer.createElement('div');
      this.renderer.addClass(this.container, 'app-toast-stack');
      this.renderer.appendChild(this.document.body, this.container);
    }
  }

  async show(
    message: string,
    color: 'success' | 'danger' | 'warning' | 'medium' = 'medium',
    duration = 3000
  ): Promise<void> {
    this.ensureContainer();

    const toast = this.renderer.createElement('div');
    this.renderer.addClass(toast, 'app-toast');
    this.renderer.addClass(toast, `app-toast--${color === 'medium' ? 'info' : color}`);
    const text = this.renderer.createText(message);
    this.renderer.appendChild(toast, text);
    this.renderer.appendChild(this.container!, toast);

    requestAnimationFrame(() => {
      this.renderer.addClass(toast, 'show');
    });

    setTimeout(() => {
      this.renderer.addClass(toast, 'hide');
      setTimeout(() => {
        if (this.container?.contains(toast)) {
          this.renderer.removeChild(this.container, toast);
        }
      }, 200);
    }, duration);
  }
}
