# Solução: Busca Global de Professores por CPF

## Problema Identificado

Ao tentar buscar professores criados pelo seed usando o endpoint:
```
GET /admin/professores/cpf/33344455566
```

O retorno era **404 - Professor não encontrado**, mesmo os professores existindo no banco de dados.

### Causa Raiz

O método `findByCpf()` no model Professor.php usa **INNER JOIN** com a tabela `tenant_usuario_papel`:

```php
$sql = "SELECT p.*, u.telefone, tup.ativo as vinculo_ativo
        FROM professores p
        INNER JOIN usuarios u ON u.id = p.usuario_id
        INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id
        WHERE p.cpf = :cpf 
        AND tup.tenant_id = :tenant_id 
        AND tup.papel_id = 2";
```

Como os professores do seed **não possuem vínculo** em `tenant_usuario_papel`, o INNER JOIN não retorna resultados.

## Solução Implementada

### 1. Novo Endpoint de Busca Global

Criado endpoint que busca professores **independente do tenant**:

```
GET /admin/professores/global/cpf/{cpf}
```

#### Características:
- ✅ Busca em **toda** a tabela `professores` (sem filtro de tenant)
- ✅ Retorna campo adicional `vinculado_ao_tenant_atual` (boolean)
- ✅ Útil para verificar se professor existe **antes** de associá-lo
- ✅ Documentado com OpenAPI/Swagger

### 2. Controller - Novo Método

**Arquivo:** `app/Controllers/ProfessorController.php`

```php
/**
 * GET /admin/professores/global/cpf/{cpf}
 * Busca professor globalmente (sem filtro de tenant)
 */
#[OA\Get(path: "/admin/professores/global/cpf/{cpf}", ...)]
public function getByCpfGlobal(Request $request, Response $response, array $args): Response
{
    $cpf = $args['cpf'] ?? '';
    $tenantId = $request->getAttribute('tenantId');
    
    // Remover formatação
    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
    
    // Validar
    if (strlen($cpfLimpo) !== 11) {
        return $response->withJson([
            'type' => 'error',
            'message' => 'CPF inválido. Deve conter 11 dígitos.'
        ], 400);
    }
    
    // Busca global
    $professor = $this->professorModel->findByCpfGlobal($cpfLimpo);
    
    if (!$professor) {
        return $response->withJson([
            'type' => 'error',
            'message' => 'Professor não encontrado no sistema'
        ], 404);
    }
    
    // Verificar vínculo com tenant atual
    $vinculadoAoTenant = $this->professorModel->pertenceAoTenant(
        $professor['id'], 
        $tenantId
    );
    $professor['vinculado_ao_tenant_atual'] = $vinculadoAoTenant;
    
    return $response->withJson(['professor' => $professor], 200);
}
```

### 3. Rota Adicionada

**Arquivo:** `routes/api.php`

```php
// Professores
$group->get('/professores', [ProfessorController::class, 'index']);
$group->get('/professores/global/cpf/{cpf}', [ProfessorController::class, 'getByCpfGlobal']); // ← NOVO
$group->get('/professores/cpf/{cpf}', [ProfessorController::class, 'getByCpf']); 
$group->get('/professores/{id}', [ProfessorController::class, 'show']);
```

⚠️ **ORDEM IMPORTANTE:** A rota `/global/cpf/{cpf}` deve vir **ANTES** de `/cpf/{cpf}` para evitar conflito de roteamento.

### 4. Model - Método Existente Reutilizado

O método `findByCpfGlobal()` já existia no model Professor.php:

```php
public function findByCpfGlobal(string $cpf)
{
    $sql = "SELECT p.*, u.telefone, u.email as usuario_email
            FROM professores p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.cpf = :cpf AND p.ativo = 1";
            
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['cpf' => $cpf]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
```

**Diferença:** Não faz JOIN com `tenant_usuario_papel`, portanto encontra **qualquer** professor, vinculado ou não.

## Comparação Entre Endpoints

### 🌍 GET /admin/professores/global/cpf/{cpf}
- **Busca:** TODA a tabela professores
- **Filtro:** Nenhum (busca global)
- **Retorna 404:** Apenas se professor não existe no sistema
- **Campo extra:** `vinculado_ao_tenant_atual` (boolean)
- **Uso:** Verificar se professor existe antes de associar

**Exemplo de Response:**
```json
{
  "professor": {
    "id": 103,
    "nome": "Ana Costa",
    "cpf": "33344455566",
    "email": "prof.ana.costa@exemplo.com",
    "usuario_id": 103,
    "ativo": 1,
    "vinculado_ao_tenant_atual": false,  ← INDICA SE JÁ ESTÁ VINCULADO
    "telefone": "11987654323",
    "created_at": "2025-01-09 10:15:00"
  }
}
```

### 🏢 GET /admin/professores/cpf/{cpf}
- **Busca:** Apenas professores do tenant (via tenant_usuario_papel)
- **Filtro:** `tenant_id` e `papel_id=2`
- **Retorna 404:** Se professor não está vinculado ao tenant
- **Campo extra:** `vinculo_ativo` (0/1)
- **Uso:** Buscar professor já cadastrado no tenant

**Exemplo de Response:**
```json
{
  "professor": {
    "id": 5,
    "nome": "João Silva",
    "cpf": "12345678901",
    "email": "joao@exemplo.com",
    "vinculo_ativo": 1,  ← STATUS DO VÍNCULO (tenant_usuario_papel.ativo)
    "turmas_count": 3
  }
}
```

## Testes Realizados

### ✅ Teste 1: Método findByCpfGlobal() no Model

**Script:** `test_find_by_cpf_global.php`

**Comando:**
```bash
docker exec appcheckin_php php /var/www/html/test_find_by_cpf_global.php
```

**Resultado:**
```
✅ ENCONTRADO!
   ID: 103
   Nome: Ana Costa
   Email: prof.ana.costa@exemplo.com
   Usuario ID: 103
   Ativo: Sim
   SEM VÍNCULOS com tenants

Comparação: findByCpf() com tenant_id=1
❌ NÃO encontrado no tenant 1 (não vinculado)
```

**Conclusão:** ✅ Model funcionando corretamente

### ⚠️ Teste 2: Endpoint HTTP

**Script:** `test_endpoint_global_cpf.sh`

**Status:** Não foi possível testar completamente devido a credenciais de autenticação não disponíveis.

**Solução:** Para testar o endpoint HTTP completo:
1. Criar usuário admin:
   ```bash
   docker exec appcheckin_php php /var/www/html/database/create_superadmin.php
   ```
2. Executar: `./test_endpoint_global_cpf.sh`

## Workflow de Uso

### Cenário 1: Associar Professor Existente

```bash
# 1. Verificar se professor existe globalmente
GET /admin/professores/global/cpf/33344455566

# Response: {"professor": {..., "vinculado_ao_tenant_atual": false}}
# ↓ Professor existe mas não está vinculado

# 2. Associar ao tenant via POST
POST /admin/professores
{
  "nome": "Ana Costa",
  "cpf": "33344455566",
  "email": "prof.ana.costa@exemplo.com"
}

# Response: {"usuario": {"criado": false}, "professor_existia": true, ...}
# ↓ API detecta que professor já existe e apenas cria vínculo

# 3. Agora pode buscar no tenant
GET /admin/professores/cpf/33344455566
# Response: 200 OK {"professor": {..., "vinculo_ativo": 1}}
```

### Cenário 2: Verificar Se Professor Já Está Vinculado

```bash
# Busca global sempre retorna o campo "vinculado_ao_tenant_atual"
GET /admin/professores/global/cpf/12345678901

# Se vinculado_ao_tenant_atual = true → Já pode usar endpoint do tenant
# Se vinculado_ao_tenant_atual = false → Precisa associar via POST
```

## Arquivos Modificados

### 1. Controller
- **Arquivo:** `app/Controllers/ProfessorController.php`
- **Mudanças:** Adicionado método `getByCpfGlobal()` com anotações OpenAPI

### 2. Rotas
- **Arquivo:** `routes/api.php`
- **Mudanças:** Adicionada rota `GET /professores/global/cpf/{cpf}`

### 3. Model
- **Arquivo:** `app/Models/Professor.php`
- **Mudanças:** Nenhuma (método `findByCpfGlobal()` já existia)

## Próximos Passos

1. ✅ **Implementação** - Concluída
2. ✅ **Teste do Model** - Validado
3. ⏳ **Teste do Endpoint HTTP** - Aguardando credenciais
4. ⏳ **Atualizar Swagger** - Executar geração após testes
5. ⏳ **Documentação API_PROFESSORES.md** - Adicionar novo endpoint

## Comandos Úteis

```bash
# Testar método no model
docker exec appcheckin_php php /var/www/html/test_find_by_cpf_global.php

# Testar endpoint HTTP (requer autenticação)
./test_endpoint_global_cpf.sh

# Criar superadmin
docker exec appcheckin_php php /var/www/html/database/create_superadmin.php

# Verificar professores sem vínculo
docker exec -i appcheckin_mysql mysql -uapp_user -papp_password appcheckin -e "
SELECT p.id, p.nome, p.cpf, 
       COUNT(tup.id) as vinculos
FROM professores p
LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = p.usuario_id AND tup.papel_id = 2
GROUP BY p.id
HAVING vinculos = 0;"
```

## Conclusão

A solução resolve o problema original permitindo:
1. ✅ Buscar professores **independente** do vínculo com tenant
2. ✅ Verificar status de vínculo antes de associar
3. ✅ Manter endpoint existente inalterado (retrocompatibilidade)
4. ✅ Adicionar documentação OpenAPI completa

**Status:** ✅ **Implementado e testado no model** | ⏳ **Aguardando teste HTTP completo**
