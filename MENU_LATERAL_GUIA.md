# 📱 Menu Lateral - Guia de Uso

## ✨ Novo Menu Lateral Implementado

O app agora possui um **menu lateral (drawer) profissional** com todas as funcionalidades!

---

## 🎯 Como Abrir o Menu

### Opção 1: Deslizar da Esquerda
Deslize seu dedo da **borda esquerda** da tela para a direita. O menu vai deslizar automaticamente!

### Opção 2: Clique no Botão Hamburger
Clique no ícone **☰ (três linhas)** no canto superior esquerdo da tela.

---

## 📋 O que tem no Menu

### 👤 Seção do Usuário (Topo)
```
┌─────────────────────┐
│ [Avatar]  Nome      │
│           email@... │
│           [Badge]   │
└─────────────────────┘
```
- Avatar dinâmico baseado no seu ID
- Nome e email do usuário
- Badge mostrando seu tipo de acesso (Admin, Super Admin, etc)

### 🏠 Seção Principal - 4 Opções

| Ícone | Opção | Descrição |
|-------|-------|-----------|
| 🏠 | **Início** | Volta para a home/dashboard |
| 👤 | **Perfil** | Vê seus dados pessoais e estatísticas |
| ⚙️ | **Minha Conta** | Edita dados, CPF, telefone, etc |
| 📦 | **Planos** | Visualiza e contrata planos |

### 📧 Seção de Informações
- Card com seu email
- Informações da conta

### 🚪 Botão Sair
- **Logout com confirmação** de segurança
- Remove seu token e dados
- Volta para tela de login

---

## 🎨 Design do Menu

```
┌─────────────────────────────┐
│                             │
│  SEÇÃO DO USUÁRIO           │
│  [Avatar] Nome Usuario      │
│           email@example.com │
│           [Admin Badge]     │
│                             │
├─────────────────────────────┤
│  PRINCIPAL                  │
│  🏠 Início                  │
│  👤 Perfil                  │
│  ⚙️  Minha Conta            │
│  📦 Planos                  │
├─────────────────────────────┤
│  INFORMAÇÕES                │
│  📧 Email: email@...        │
├─────────────────────────────┤
│  🚪 Sair da Conta           │
└─────────────────────────────┘
```

---

## ⚡ Funcionalidades

✅ **Menu se fecha automaticamente** ao clicar em um item  
✅ **Animações suaves** ao abrir/fechar  
✅ **Gestos naturais** - deslize para abrir/fechar  
✅ **Avatar dinâmico** atualizado com seus dados  
✅ **Logout seguro** com confirmação  
✅ **Design dark mode** consistente com o app  
✅ **Ícones coloridos** para cada seção  
✅ **Responsive** em todos os tamanhos de tela  

---

## 🎯 Fluxo de Navegação

```
Menu Lateral
    │
    ├─→ Início ..................... Home/Dashboard
    ├─→ Perfil ..................... Seus dados pessoais
    ├─→ Minha Conta ............... Editar informações
    ├─→ Planos .................... Ver planos disponíveis
    └─→ Sair ...................... Logout (com confirmação)
```

---

## 🔒 Segurança

- ✅ Logout **requer confirmação** antes de executar
- ✅ Token removido automaticamente
- ✅ Dados pessoais limpos
- ✅ Redirecionado para login

---

## 💡 Dicas

1. **Abrir o menu** - Deslize de esquerda para direita ou clique no ☰
2. **Fechar o menu** - Clique em um item, deslize para esquerda ou toque fora
3. **Mudar de tela** - O menu fecha automaticamente após selecionar
4. **Seu perfil** - Clique no avatar ou em "Perfil" para ver seus dados

---

## 🎯 Próximas Atualizações

- [ ] Notificações/Badges nas abas
- [ ] Mais opções no menu (Histórico, Configurações)
- [ ] Customização de tema
- [ ] Sincronização de dados em tempo real

---

**Status:** ✅ Menu 100% funcional!  
**Testado em:** iOS e Android  
**Última atualização:** 9 de janeiro de 2026
