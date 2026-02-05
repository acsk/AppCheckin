# Resumo das Alterações - Sistema de Múltiplos Papéis para Admins

## 📋 O que foi implementado?

### 1. Sistema de Múltiplos Papéis
- Admins agora podem ter múltiplos papéis simultaneamente:
  - **Admin (3)**: Obrigatório - acesso ao painel administrativo
  - **Professor (2)**: Opcional - pode dar aulas e marcar presenças
  - **Aluno (1)**: Opcional - pode treinar e fazer check-in

### 2. Novo Endpoint: Listar Papéis
- **Rota**: `GET /papeis`
- **Permissão**: Admin (3) ou Super Admin (4)
- **Retorna**: Lista de papéis disponíveis com id, nome e descrição

### 3. Permissões Atualizadas
Todas as rotas de gestão de admins agora aceitam **Admin (3) e Super Admin (4)**:
- ✅ Listar admins da academia
- ✅ Criar admin
- ✅ Atualizar admin
- ✅ Desativar admin
- ✅ Reativar admin

**Regra de Segurança**: Admin (3) só pode gerenciar admins da própria academia.

### 4. Criação Inteligente de Registros
Ao criar/atualizar um admin com múltiplos papéis, o sistema automaticamente:
- Cria registro em `professores` se papel_id = 2
- Cria registro em `alunos` se papel_id = 1
- Desativa registros se papéis forem removidos

---

## 📝 Arquivos Modificados

### 1. `app/Controllers/SuperAdminController.php`
**Alterações:**
- ✅ Adicionado método `listarPapeis()`
- ✅ `listarAdminsAcademia()`: Permissão para Admin (3) + busca papéis de cada admin
- ✅ `criarAdminAcademia()`: Aceita array de papéis + cria registros correspondentes
- ✅ `atualizarAdminAcademia()`: Atualiza papéis + gerencia registros relacionados
- ✅ `desativarAdminAcademia()`: Permissão para Admin (3)
- ✅ `reativarAdminAcademia()`: Permissão para Admin (3)

### 2. `routes/api.php`
**Alterações:**
- ✅ Adicionada rota `GET /papeis` com AuthMiddleware

### 3. Documentação Criada
- ✅ `docs/API_GESTAO_ADMINS.md` - Documentação completa para frontend
- ✅ `docs/EXEMPLO_FRONTEND_ADMINS.js` - Código exemplo JavaScript/React
- ✅ `docs/RESUMO_MULTIPLOS_PAPEIS.md` - Este arquivo

---

## 🔄 Mudanças na API

### Criar Admin - Antes vs Depois

**ANTES:**
```json
POST /superadmin/academias/{tenantId}/admin
{
  "nome": "João Silva",
  "email": "joao@academia.com",
  "senha": "senha123"
}
// Criava apenas Admin (papel_id = 3)
```

**DEPOIS:**
```json
POST /superadmin/academias/{tenantId}/admin
{
  "nome": "João Silva",
  "email": "joao@academia.com",
  "senha": "senha123",
  "papeis": [3, 2]  // Admin + Professor
}
// Cria Admin E Professor simultaneamente
```

### Listar Admins - Antes vs Depois

**ANTES:**
```json
{
  "admins": [
    {
      "id": 10,
      "nome": "João Silva",
      "email": "joao@academia.com"
    }
  ]
}
```

**DEPOIS:**
```json
{
  "admins": [
    {
      "id": 10,
      "nome": "João Silva",
      "email": "joao@academia.com",
      "papeis": [3, 2]  // Mostra todos os papéis
    }
  ]
}
```

---

## 🎯 Casos de Uso

### 1. Dono da Academia (Admin + Aluno)
```json
{
  "nome": "Carlos Oliveira",
  "email": "carlos@academia.com",
  "senha": "senha123",
  "papeis": [3, 1]
}
```
✅ Pode gerenciar a academia  
✅ Pode treinar como aluno

### 2. Professor Administrativo (Admin + Professor)
```json
{
  "nome": "Maria Santos",
  "email": "maria@academia.com",
  "senha": "senha123",
  "papeis": [3, 2]
}
```
✅ Pode gerenciar a academia  
✅ Pode dar aulas e marcar presenças

### 3. Administrador Completo (Admin + Professor + Aluno)
```json
{
  "nome": "João Silva",
  "email": "joao@academia.com",
  "senha": "senha123",
  "papeis": [3, 2, 1]
}
```
✅ Pode gerenciar a academia  
✅ Pode dar aulas  
✅ Pode treinar

---

## 🔒 Regras de Segurança

1. **Papel Admin (3) é Obrigatório**
   - Todo usuário deve ter pelo menos o papel 3
   - Tentativa de criar sem papel 3 → Erro 422

2. **Permissões por Nível**
   - **Admin (3)**: Gerencia apenas sua academia
   - **Super Admin (4)**: Gerencia todas as academias

3. **Proteção contra Remoção**
   - Não é possível desativar o último admin ativo
   - Validação → Erro 400

4. **Validação de Papéis**
   - Apenas papéis válidos: 1, 2, 3
   - Papel inválido → Erro 422

---

## 📊 Estrutura de Dados

### Tabelas Afetadas

1. **`usuarios`**
   - Registro único por usuário
   - Contém dados de autenticação

2. **`tenant_usuario_papel`**
   - **MÚLTIPLOS** registros por usuário (um por papel)
   - Exemplo: João com papéis [3, 2] → 2 registros
   ```sql
   (tenant_id=1, usuario_id=10, papel_id=3, ativo=1)
   (tenant_id=1, usuario_id=10, papel_id=2, ativo=1)
   ```

3. **`professores`**
   - Criado automaticamente se papel_id = 2
   - Desativado se papel for removido

4. **`alunos`**
   - Criado automaticamente se papel_id = 1
   - Desativado se papel for removido

---

## ✅ Validações Implementadas

### No Backend (SuperAdminController)

1. ✅ Email válido e único
2. ✅ Senha mínima 6 caracteres
3. ✅ Papéis devem incluir 3 (Admin)
4. ✅ Papéis válidos: 1, 2, 3
5. ✅ Admin (3) só pode gerenciar própria academia
6. ✅ Não pode desativar último admin ativo
7. ✅ Telefone e CPF sanitizados (apenas números)

### Exemplo de Resposta de Erro

```json
{
  "errors": [
    "Email já cadastrado",
    "Usuário deve ter pelo menos o papel de Admin",
    "Papel inválido: 5. Valores válidos: 1 (aluno), 2 (professor), 3 (admin)"
  ]
}
```

---

## 🧪 Como Testar

### 1. Listar Papéis Disponíveis
```bash
curl -X GET http://localhost:8080/papeis \
  -H "Authorization: Bearer {token}"
```

### 2. Criar Admin com Múltiplos Papéis
```bash
curl -X POST http://localhost:8080/superadmin/academias/1/admin \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João Silva",
    "email": "joao@teste.com",
    "senha": "senha123",
    "telefone": "11987654321",
    "papeis": [3, 2]
  }'
```

### 3. Listar Admins com Papéis
```bash
curl -X GET http://localhost:8080/superadmin/academias/1/admins \
  -H "Authorization: Bearer {token}"
```

### 4. Atualizar Papéis de um Admin
```bash
curl -X PUT http://localhost:8080/superadmin/academias/1/admins/10 \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "papeis": [3, 2, 1]
  }'
```

---

## 📚 Documentação para o Frontend

Criamos 2 arquivos na pasta `docs/`:

1. **API_GESTAO_ADMINS.md**
   - Documentação completa da API
   - Todos os endpoints com exemplos
   - Casos de uso
   - Códigos de resposta

2. **EXEMPLO_FRONTEND_ADMINS.js**
   - Funções JavaScript prontas para uso
   - Componente React completo
   - Tratamento de erros
   - Exemplos práticos

---

## 🚀 Próximos Passos (Sugeridos)

1. **Frontend**
   - Implementar interface de gestão de admins
   - Usar checkbox para múltiplos papéis
   - Mostrar badges com papéis de cada admin

2. **Validações Extras**
   - Validar CPF no backend (Luhn algorithm)
   - Validar formato de telefone
   - Adicionar confirmação ao desativar admin

3. **Melhorias**
   - Paginação na lista de admins
   - Filtro por papel
   - Histórico de alterações

---

## 🐛 Possíveis Problemas

### 1. Admin não consegue gerenciar admins
**Causa**: Papel não está sendo identificado corretamente  
**Solução**: Verificar se o token JWT contém o papel_id correto

### 2. Erro ao criar admin com múltiplos papéis
**Causa**: Papel 3 (Admin) não está no array  
**Solução**: Sempre incluir papel_id = 3 no array de papéis

### 3. Registro de professor/aluno não criado
**Causa**: Array de papéis não está sendo passado  
**Solução**: Enviar papéis no body da requisição

---

## 📞 Suporte

Para dúvidas sobre esta implementação, consulte:
- `docs/API_GESTAO_ADMINS.md` - Documentação completa
- `docs/EXEMPLO_FRONTEND_ADMINS.js` - Código exemplo
- SuperAdminController.php - Implementação backend
