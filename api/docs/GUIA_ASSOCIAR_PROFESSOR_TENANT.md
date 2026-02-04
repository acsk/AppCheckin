# Guia: Associar Professor ao Tenant por CPF

**Criado em:** 03 de Fevereiro de 2026  
**Padrão:** Similar ao fluxo de Aluno

---

## 🎯 Arquitetura Implementada

```
┌─────────────┐     ┌──────────────┐     ┌──────────────────┐
│  usuarios   │────▶│ professores  │◀────│ tenant_professor │
│   (CPF)     │     │   (global)   │     │  (vínculo N:M)   │
└─────────────┘     └──────────────┘     └──────────────────┘
                            │
                            ▼
                    ┌─────────────────────┐
                    │tenant_usuario_papel │
                    │  (papel_id=2)       │
                    └─────────────────────┘
```

### Tabelas Envolvidas:

1. **`professores`** - Cadastro global do professor
2. **`tenant_professor`** - Vínculo professor↔tenant + status + plano
3. **`tenant_usuario_papel`** - Permissões (papel_id=2)
4. **`usuarios`** - Dados pessoais (email, CPF, telefone)

---

## 📋 Fluxo de Cadastro/Associação

### Cenário 1: Cadastrar Novo Professor no Tenant

```php
// 1. Buscar professor por CPF (verifica se já existe globalmente)
$cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
$professorExistente = $professorModel->findByCpfGlobal($cpfLimpo);

if ($professorExistente) {
    // Professor já existe globalmente
    $professorId = $professorExistente['id'];
    
    // Verificar se já está associado ao tenant
    if ($professorModel->pertenceAoTenant($professorId, $tenantId)) {
        return ['error' => 'Professor já está cadastrado neste tenant'];
    }
    
    // Associar ao tenant
    $professorModel->associarAoTenant($professorId, $tenantId, 'ativo');
    
} else {
    // Professor não existe, criar novo
    
    // 1. Criar usuário
    $usuarioId = $usuarioModel->create([
        'nome' => $nome,
        'email' => $email,
        'cpf' => $cpfLimpo,
        'telefone' => $telefone,
        'senha' => password_hash($senha, PASSWORD_DEFAULT),
        'ativo' => 1
    ]);
    
    // 2. Criar professor global
    $professorId = $professorModel->create([
        'usuario_id' => $usuarioId,
        'nome' => $nome,
        'foto_url' => $fotoUrl ?? null,
        'ativo' => 1
    ]);
    
    // 3. Associar ao tenant
    $professorModel->associarAoTenant($professorId, $tenantId, 'ativo');
}
```

---

### Cenário 2: Associar Professor Existente a Outro Tenant

```php
// Professor Carlos (CPF: 123.456.789-00) já existe no Tenant A
// Agora precisa ser associado ao Tenant B

$cpfLimpo = '12345678900';
$tenantIdB = 3;

// 1. Buscar professor global por CPF
$professor = $professorModel->findByCpfGlobal($cpfLimpo);

if (!$professor) {
    return ['error' => 'Professor não encontrado'];
}

// 2. Verificar se já está no tenant
if ($professorModel->pertenceAoTenant($professor['id'], $tenantIdB)) {
    return ['error' => 'Professor já está neste tenant'];
}

// 3. Associar ao novo tenant
$resultado = $professorModel->associarAoTenant(
    professorId: $professor['id'],
    tenantId: $tenantIdB,
    status: 'ativo',
    planoId: 2 // opcional
);

if ($resultado) {
    echo "Professor associado ao Tenant B com sucesso!";
}
```

---

### Cenário 3: Desassociar Professor de um Tenant

```php
// Remover professor do tenant (soft delete)
$professorId = 1;
$tenantId = 2;

$resultado = $professorModel->desassociarDoTenant($professorId, $tenantId);

if ($resultado) {
    // Status em tenant_professor: 'ativo' → 'inativo'
    // data_fim: preenchida com data atual
    // Papel em tenant_usuario_papel: ativo=1 → ativo=0
    echo "Professor desassociado do tenant!";
}
```

---

## 🔍 Métodos Disponíveis

### Busca e Consulta:

```php
// Buscar por CPF no tenant específico
$professor = $professorModel->findByCpf('12345678900', $tenantId);

// Buscar por CPF globalmente (sem filtro de tenant)
$professor = $professorModel->findByCpfGlobal('12345678900');

// Buscar por ID com informações do tenant
$professor = $professorModel->findById($professorId, $tenantId);

// Listar professores do tenant
$professores = $professorModel->listarPorTenant($tenantId, apenasAtivos: true);
```

### Associação e Gerenciamento:

```php
// Associar ao tenant
$professorModel->associarAoTenant($professorId, $tenantId, 'ativo', $planoId);

// Verificar se pertence ao tenant
$pertence = $professorModel->pertenceAoTenant($professorId, $tenantId);

// Desassociar do tenant
$professorModel->desassociarDoTenant($professorId, $tenantId);

// Listar todos os tenants do professor
$tenants = $professorModel->listarTenants($professorId, apenasAtivos: true);
```

---

## 📊 Estrutura SQL da Tabela

```sql
CREATE TABLE tenant_professor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professor_id INT NOT NULL,              -- FK para professores.id
    tenant_id INT NOT NULL,                 -- FK para tenants.id
    plano_id INT NULL,                      -- FK para planos.id (opcional)
    status ENUM('ativo','inativo','suspenso','cancelado') DEFAULT 'ativo',
    data_inicio DATE NOT NULL DEFAULT (CURDATE()),
    data_fim DATE NULL,                     -- NULL = ainda ativo
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_tenant_professor (tenant_id, professor_id),
    KEY idx_professor (professor_id),
    KEY idx_tenant (tenant_id),
    KEY idx_status (status)
);
```

---

## 🎓 Exemplo Completo: Endpoint de Associação

```php
// ProfessorController.php

public function associarPorCpf(Request $request, Response $response): Response
{
    $tenantId = $request->getAttribute('tenant_id');
    $data = $request->getParsedBody();
    $cpf = $data['cpf'] ?? '';
    
    // Validar CPF
    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpfLimpo) !== 11) {
        return $response->withJson(['error' => 'CPF inválido'], 400);
    }
    
    // Buscar professor global
    $professor = $this->professorModel->findByCpfGlobal($cpfLimpo);
    
    if (!$professor) {
        return $response->withJson([
            'error' => 'Professor não encontrado',
            'hint' => 'Cadastre o professor primeiro'
        ], 404);
    }
    
    // Verificar se já está no tenant
    if ($this->professorModel->pertenceAoTenant($professor['id'], $tenantId)) {
        return $response->withJson([
            'error' => 'Professor já está neste tenant',
            'professor' => $professor
        ], 409);
    }
    
    // Associar ao tenant
    $resultado = $this->professorModel->associarAoTenant(
        professorId: $professor['id'],
        tenantId: $tenantId,
        status: 'ativo',
        planoId: $data['plano_id'] ?? null
    );
    
    if (!$resultado) {
        return $response->withJson(['error' => 'Erro ao associar professor'], 500);
    }
    
    // Buscar dados completos
    $professorCompleto = $this->professorModel->findById($professor['id'], $tenantId);
    
    return $response->withJson([
        'message' => 'Professor associado com sucesso',
        'professor' => $professorCompleto
    ], 200);
}
```

---

## ✅ Validações Importantes

### 1. CPF Único por Tenant
```php
// Não pode ter 2 professores com mesmo CPF no mesmo tenant
// Garantido por: UNIQUE em tenant_professor (tenant_id, professor_id)
```

### 2. Professor Pode Estar em Múltiplos Tenants
```php
// Mesmo professor pode ter vínculo com N tenants
tenant_professor:
  - professor_id=1, tenant_id=2 (Academia A)
  - professor_id=1, tenant_id=3 (Academia B)
```

### 3. Status Independente por Tenant
```php
// Professor ativo em um tenant, inativo em outro
tenant_professor:
  - professor_id=1, tenant_id=2, status='ativo'
  - professor_id=1, tenant_id=3, status='inativo'
```

---

## 🔗 Referências

- Model: [`app/Models/Professor.php`](../app/Models/Professor.php)
- Migração: [`database/migrations/criar_tenant_professor.sql`](../database/migrations/criar_tenant_professor.sql)
- Arquitetura: [`docs/ARQUITETURA_DUAS_TABELAS.md`](ARQUITETURA_DUAS_TABELAS.md)

---

**Última Atualização:** 03/02/2026  
**Status:** ✅ Implementado e Documentado
