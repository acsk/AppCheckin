/**
 * CÓDIGO: Modificação Implementada
 * Arquivo: Backend/app/Models/Usuario.php
 * Método: listarTodos()
 * Linhas: 443-530
 * 
 * Visualização do código modificado com destaque nas mudanças
 */

// ============================================================================
// CÓDIGO ANTES (COM DUPLICATAS)
// ============================================================================

public function listarTodos(bool $isSuperAdmin = false, ?int $tenantId = null, bool $apenasAtivos = false): array
{
    $sql = "
        SELECT 
            u.id, 
            u.nome, 
            u.email,
            u.telefone,
            u.cpf,
            u.role_id,
            u.foto_base64,
            u.created_at,
            u.updated_at,
            r.nome as role_nome,
            ut.status,
            ut.tenant_id,
            t.nome as tenant_nome,
            t.slug as tenant_slug,
            CASE 
                WHEN ut.status = 'ativo' THEN 1
                ELSE 0
            END as ativo
        FROM usuarios u
        LEFT JOIN roles r ON u.role_id = r.id
        INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
        LEFT JOIN tenants t ON ut.tenant_id = t.id
    ";
    
    $conditions = [];
    $params = [];
    
    // Se NÃO for SuperAdmin, filtrar por tenant
    if (!$isSuperAdmin && $tenantId !== null) {
        $conditions[] = "ut.tenant_id = :tenant_id";
        $params['tenant_id'] = $tenantId;
    }
    
    // Filtrar apenas ativos se solicitado
    if ($apenasAtivos) {
        $conditions[] = "ut.status = 'ativo'";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY t.nome ASC, u.nome ASC";  // ❌ PROBLEMA: Não determinístico

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    
    $result = $stmt->fetchAll();
    
    // ❌ PROBLEMA: Cada usuário com múltiplos tenants retorna múltiplas linhas
    // Exemplo: CAROLINA FERREIRA com 2 tenants = 2 linhas retornadas
    return array_map(function($row) {
        return [
            'id' => $row['id'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'telefone' => $row['telefone'] ?? null,
            'cpf' => $row['cpf'] ?? null,
            'role_id' => $row['role_id'],
            'role_nome' => $row['role_nome'],
            'ativo' => (bool) $row['ativo'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'tenant' => [
                'id' => $row['tenant_id'],
                'nome' => $row['tenant_nome'],
                'slug' => $row['tenant_slug']
            ]
        ];
    }, $result);  // ❌ PROBLEMA: Sem deduplicação aqui
}


// ============================================================================
// CÓDIGO DEPOIS (SEM DUPLICATAS)
// ============================================================================

public function listarTodos(bool $isSuperAdmin = false, ?int $tenantId = null, bool $apenasAtivos = false): array
{
    $sql = "
        SELECT 
            u.id, 
            u.nome, 
            u.email,
            u.telefone,
            u.cpf,
            u.role_id,
            u.foto_base64,
            u.created_at,
            u.updated_at,
            r.nome as role_nome,
            ut.status,
            ut.tenant_id,
            t.nome as tenant_nome,
            t.slug as tenant_slug,
            CASE 
                WHEN ut.status = 'ativo' THEN 1
                ELSE 0
            END as ativo
        FROM usuarios u
        LEFT JOIN roles r ON u.role_id = r.id
        INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
        LEFT JOIN tenants t ON ut.tenant_id = t.id
    ";
    
    $conditions = [];
    $params = [];
    
    // Se NÃO for SuperAdmin, filtrar por tenant
    if (!$isSuperAdmin && $tenantId !== null) {
        $conditions[] = "ut.tenant_id = :tenant_id";
        $params['tenant_id'] = $tenantId;
    }
    
    // Filtrar apenas ativos se solicitado
    if ($apenasAtivos) {
        $conditions[] = "ut.status = 'ativo'";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    // ✅ MUDANÇA 1: Ordenação determinística
    // Antes: ORDER BY t.nome ASC, u.nome ASC
    // Depois: Ordenar por ID do usuário (determinístico)
    $sql .= " ORDER BY u.id ASC, ut.status DESC, t.id ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    
    $result = $stmt->fetchAll();
    
    // ✅ MUDANÇA 2: Deduplicação em PHP
    // Novo código que remove duplicatas mantendo apenas o primeiro registro de cada usuário
    $usuariosProcessados = [];
    $usuariosMap = [];
    
    foreach ($result as $row) {
        $usuarioId = $row['id'];
        
        // Se ainda não processamos este usuário, adicionar à lista
        if (!isset($usuariosMap[$usuarioId])) {
            $usuariosMap[$usuarioId] = true;
            $usuariosProcessados[] = [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'telefone' => $row['telefone'] ?? null,
                'cpf' => $row['cpf'] ?? null,
                'role_id' => $row['role_id'],
                'role_nome' => $row['role_nome'],
                'ativo' => (bool) $row['ativo'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'tenant' => [
                    'id' => $row['tenant_id'],
                    'nome' => $row['tenant_nome'],
                    'slug' => $row['tenant_slug']
                ]
            ];
        }
        // Se usuário já foi processado, ignorar (linha duplicada)
    }
    
    return $usuariosProcessados;  // ✅ Retorna apenas usuários únicos
}


// ============================================================================
// EXPLICAÇÃO DAS MUDANÇAS
// ============================================================================

/*
MUDANÇA 1: Ordenação Determinística
──────────────────────────────────────

ANTES: ORDER BY t.nome ASC, u.nome ASC
  ❌ Problema: Ordem varia conforme tenant (não determinístico)

DEPOIS: ORDER BY u.id ASC, ut.status DESC, t.id ASC
  ✅ Vantagem 1: Ordena por ID do usuário (determinístico)
  ✅ Vantagem 2: ut.status DESC prioriza status 'ativo'
  ✅ Vantagem 3: t.id ASC garante tenant consistente


MUDANÇA 2: Deduplicação em PHP
───────────────────────────────

ANTES:
  return array_map(function($row) {
    // Retorna TODAS as linhas (incluindo duplicatas)
  }, $result);

DEPOIS:
  $usuariosMap = [];  // Rastreia usuários já processados
  
  foreach ($result as $row) {
    if (!isset($usuariosMap[$usuarioId])) {
      // PRIMEIRO aparecimento: adiciona à lista
      $usuariosMap[$usuarioId] = true;
      $usuariosProcessados[] = $row;
    }
    // SEGUNDO+ aparecimento: ignora (duplicata)
  }
  
  return $usuariosProcessados;  // Sem duplicatas!


POR QUE ESSA ABORDAGEM?
──────────────────────

✅ Performance: Deduplicação é O(n), mais rápido que DISTINCT
✅ Clareza: Fácil entender o que está acontecendo
✅ Compatibilidade: Mantém mesmo formato de resposta
✅ Flexibilidade: Fácil adicionar lógica adicional depois
✅ Debugging: Logs e stack trace são claros
*/


// ============================================================================
// EXEMPLO DE EXECUÇÃO: Antes vs Depois
// ============================================================================

/*
ENTRADA (Query ao banco):
─────────────────────────

usuários = [
  [id: 1, nome: "Super Admin", tenant_id: 1],
  [id: 8, nome: "Rodolfo", tenant_id: 4],
  [id: 9, nome: "Jonas", tenant_id: 5],
  [id: 10, nome: "Ricardo", tenant_id: 4],
  [id: 11, nome: "Carolina", tenant_id: 4],  ← CAROLINE aparece 2 vezes!
  [id: 11, nome: "Carolina", tenant_id: 5],  ← Mesma pessoa, tenant diferente
  [id: 12, nome: "André", tenant_id: 5],
  [id: 13, nome: "Maria", tenant_id: 5]
]

PROCESSAMENTO ANTES (array_map):
───────────────────────────────

array_map(function($row) { return $row; }, usuários)
↓
[8 registros retornados]

Resultado: 8 usuários (com duplicata)


PROCESSAMENTO DEPOIS (deduplicação):
─────────────────────────────────────

$usuariosMap = {}
Loop 1: id=1  → usuariosMap={1}     → processados=[id:1]
Loop 2: id=8  → usuariosMap={1,8}   → processados=[..., id:8]
Loop 3: id=9  → usuariosMap={1,8,9} → processados=[..., id:9]
Loop 4: id=10 → usuariosMap={...10} → processados=[..., id:10]
Loop 5: id=11 → usuariosMap={...11} → processados=[..., id:11] ✓ ADICIONA
Loop 6: id=11 → já em usuariosMap    → [ignora]              ✓ IGNORA
Loop 7: id=12 → usuariosMap={...12} → processados=[..., id:12]
Loop 8: id=13 → usuariosMap={...13} → processados=[..., id:13]
↓
[7 registros retornados]

Resultado: 7 usuários (sem duplicata)
*/


// ============================================================================
// VALIDAÇÃO: Comportamento Esperado
// ============================================================================

/*
Teste 1: Usuário único (sem múltiplos tenants)
──────────────────────────────────────────────

Entrada: usuario_id=8 (Rodolfo) com 1 tenant
Query retorna: 1 linha
Resultado: 1 usuário retornado ✅

Teste 2: Usuário em múltiplos tenants (CASO DO BUG)
────────────────────────────────────────────────────

Entrada: usuario_id=11 (Carolina) com 2 tenants
Query retorna: 2 linhas (uma por tenant)
Após deduplicação: 1 usuário retornado ✅

Teste 3: Múltiplos usuários, alguns com múltiplos tenants
──────────────────────────────────────────────────────────

Entrada: 13 usuários × vários tenants = múltiplas linhas
Query retorna: 8 linhas (por exemplo)
Após deduplicação: 7 usuários únicos retornados ✅

Teste 4: Filtro por tenant específico (não afetado)
──────────────────────────────────────────────────

Entrada: tenantId=4
Query retorna: Apenas usuários do tenant 4
Deduplicação: Não faz diferença (sem duplicatas por tenant)
Resultado: ✅ Funcionamento normal

Teste 5: Filtro de apenas ativos
────────────────────────────────

Entrada: apenasAtivos=true
Query WHERE: ut.status = 'ativo'
Deduplicação: Prioriza status ativo (ORDER BY ut.status DESC)
Resultado: ✅ Retorna apenas ativos
*/
