# Menu Hamburger - Implementação AppMobile

## ✅ Mudanças Realizadas

### 1. **Menu.js** - Atualizado com novas opções

**Antes:**
- Menu tinha apenas: Início, Treino, Check-in, Perfil

**Depois:**
- **Seção Principal:** Início, Treino, Check-in
- **Divisor Visual:** Linha separadora
- **Seção de Usuário:** Perfil, Minha Conta, Planos
- **Logout:** Botão Sair no footer

**Estrutura do Menu:**
```
┌─────────────────────────────┐
│  👤 Usuário                 │ × (fechar)
├─────────────────────────────┤
│ 🏠 Início                   │
│ 🏃 Treino                   │
│ 📍 Check-in                 │
├─────────────────────────────┤ (divider)
│ 👤 Perfil                   │
│ ⚙️ Minha Conta              │
│ 📦 Planos                   │
├─────────────────────────────┤
│ 🚪 Sair                     │
└─────────────────────────────┘
```

### 2. **Tela Planos** - Criada do zero

Arquivo: `src/screens/Planos/index.js`

**Funcionalidades:**
- Lista de planos em cards
- Indicador de plano ativo
- Benefícios por plano
- Botões "Contratar" ou "Plano Atual"
- Design consistente com o app

**Exemplo de dados:**
```javascript
{
  id: 1,
  nome: 'Plano Básico',
  valor: 'R$ 99,90',
  duracao: 'Mensal',
  beneficios: ['Acesso à academia', 'Check-in ilimitado'],
  ativo: true
}
```

### 3. **Tabs.js** - Atualizado para incluir novas telas

**Imports adicionados:**
- `MinhaConta` 
- `Planos`

**renderScreen() atualizado:**
```javascript
if (active === 'minha-conta') return <MinhaConta baseUrl={baseUrl} />;
if (active === 'planos') return <Planos baseUrl={baseUrl} />;
```

---

## 🎯 Funcionalidades Disponíveis no Menu

| Opção | Tela | Ícone | Descrição |
|-------|------|-------|-----------|
| Início | Home | home | Dashboard principal |
| Treino | Home (mockado) | activity | Histórico de treinos |
| Check-in | Home (mockado) | map-pin | Registrar entrada |
| Perfil | Perfil | user | Dados pessoais e estatísticas |
| Minha Conta | MinhaConta | settings | Editar dados, CPF, telefone |
| Planos | Planos | package | Contratar ou visualizar planos |
| Sair | - | log-out | Fazer logout |

---

## 🔧 Como Funciona

1. **Usuário toca no ícone hambúrguer** (☰) no header
2. **Modal/Drawer abre** com o menu completo
3. **Usuário seleciona uma opção** (ex: "Planos")
4. **Tela é renderizada** e menu fecha automaticamente
5. **Usuário pode voltar** tocando outra opção

---

## 📝 Notas Técnicas

- Menu organizado em **2 seções** (Principal + Usuário)
- Indicador visual de **item ativo** (cor laranja #FF9A3D)
- **Divider** separando as seções
- Logout com **confirmação** (Alert)
- Estilos consistentes com design do app

---

## ⚡ Próximos Passos (Opcional)

1. Integrar Planos com API real
2. Adicionar mais opções ao menu (Histórico, etc)
3. Melhorar animações do drawer
4. Adicionar badges/notificações

---

**Status:** ✅ Pronto para usar!
