import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import {
  IonHeader, IonToolbar, IonTitle, IonContent, IonItem,
  IonLabel, IonButton, IonButtons, IonBackButton, IonIcon,
  IonCard, IonCardHeader, IonCardTitle, IonCardContent,
  IonInput, IonTextarea, IonSpinner, IonAvatar, IonNote
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { business, save, camera, mail, call, location } from 'ionicons/icons';

interface TenantInfo {
  nome: string;
  email: string;
  telefone: string;
  endereco: string;
  logo_base64: string | null;
}

@Component({
  selector: 'app-perfil-tenant',
  templateUrl: './perfil-tenant.component.html',
  styleUrls: ['./perfil-tenant.component.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    IonHeader, IonToolbar, IonTitle, IonContent, IonItem,
    IonLabel, IonButton, IonButtons, IonBackButton, IonIcon,
    IonCard, IonCardHeader, IonCardTitle, IonCardContent,
    IonInput, IonTextarea, IonSpinner, IonAvatar, IonNote
  ]
})
export class PerfilTenantComponent implements OnInit {
  tenantForm: FormGroup;
  loading = false;
  logoPreview: string | null = null;

  constructor(private fb: FormBuilder) {
    addIcons({ business, save, camera, mail, call, location });

    this.tenantForm = this.fb.group({
      nome: ['Minha Academia', Validators.required],
      email: ['contato@minhaacademia.com', [Validators.required, Validators.email]],
      telefone: ['(11) 98765-4321', Validators.required],
      endereco: ['Rua Exemplo, 123 - São Paulo/SP', Validators.required],
      logo_base64: [null]
    });
  }

  ngOnInit() {
    this.carregarPerfil();
  }

  carregarPerfil() {
    this.loading = true;
    // TODO: Implementar chamada ao backend para buscar dados do tenant
    setTimeout(() => {
      // Dados mockados por enquanto
      this.logoPreview = null;
      this.loading = false;
    }, 1000);
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
      const file = input.files[0];
      
      // Validar tamanho (máximo 5MB)
      if (file.size > 5 * 1024 * 1024) {
        alert('A imagem deve ter no máximo 5MB');
        return;
      }

      // Validar tipo
      if (!file.type.startsWith('image/')) {
        alert('Apenas imagens são permitidas');
        return;
      }

      const reader = new FileReader();
      reader.onload = (e) => {
        const base64 = e.target?.result as string;
        this.logoPreview = base64;
        this.tenantForm.patchValue({ logo_base64: base64 });
      };
      reader.readAsDataURL(file);
    }
  }

  salvarPerfil() {
    if (this.tenantForm.invalid) {
      alert('Preencha todos os campos obrigatórios');
      return;
    }

    this.loading = true;
    const data = this.tenantForm.value;
    
    // TODO: Implementar chamada ao backend para salvar dados do tenant
    console.log('Salvando perfil do tenant:', data);
    
    setTimeout(() => {
      alert('Perfil atualizado com sucesso!');
      this.loading = false;
    }, 1500);
  }

  removerLogo() {
    this.logoPreview = null;
    this.tenantForm.patchValue({ logo_base64: null });
  }
}
