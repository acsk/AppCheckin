# Correção: Usuários Duplicados na API `/superadmin/usuarios`

## Problema
A rota GET `/superadmin/usuarios` estava retornando usuários duplicados. Por exemplo, o usuário "CAROLINA FERREIRA" (ID 11) aparecia duas vezes na resposta - uma para cada tenant ao qual estava vinculado.

### Causa
A query SQL no método `listarTodos()` do modelo `Usuario` realizava um `INNER JOIN` com a tabela `usuario_tenant`. Quando um usuário estava associado a múltiplos tenants, a query retornava uma linha para cada associação, causando duplicatas na resposta da API.

```sql
-- Antes (com problema)
SELECT ... FROM usuarios u
INNER JOIN usuario_tenant ut ON u.id = ut.usuario_id
-- Retornava 8 registros quando havia duplicatas
```

## Solução Implementada

### Local da Correção
**Arquivo:** [Backend/app/Models/Usuario.php](Backend/app/Models/Usuario.php#L443)

**Método:** `listarTodos(bool $isSuperAdmin = false, ?int $tenantId = null, bool $apenasAtivos = false)`

### O que foi alterado

1. **Adicionado sorting determinístico** para garantir que o primeiro resultado de cada usuário seja sempre o mesmo:
   ```php
   ORDER BY u.id ASC, ut.status DESC, t.id ASC
   ```

2. **Implementado deduplicação em PHP** após a query:
   - Iteramos sobre os resultados
   - Mantemos um mapa (`$usuariosMap`) para rastrear quais usuários já foram processados
   - Apenas o primeiro registro de cada usuário é adicionado à lista final
   - Outros registros (associações com tenants adicionais) são ignorados

```php
// Remover duplicatas: manter apenas o primeiro registro de cada usuário
$usuariosProcessados = [];
$usuariosMap = [];

foreach ($result as $row) {
    $usuarioId = $row['id'];
    
    // Se ainda não processamos este usuário, adicionar à lista
    if (!isset($usuariosMap[$usuarioId])) {
        $usuariosMap[$usuarioId] = true;
        // Processar e adicionar à lista...
    }
}
```

## Resultado
- ✅ Usuários são retornados uma única vez cada
- ✅ Mantém compatibilidade com o formato de resposta existente
- ✅ Cada usuário retorna seu primeiro tenant (ordenado por ID)
- ✅ Total de registros agora reflete o número real de usuários únicos

## Resposta da API (Após Correção)

```json
{
    "total": 5,  // Agora apenas usuários únicos (antes era 8)
    "usuarios": [
        {
            "id": 1,
            "nome": "Super Administrador",
            "email": "superadmin@appcheckin.com",
            "role_id": 3,
            "role_nome": "super_admin",
            "ativo": false,
            "status": "inativo",
            "tenant": {
                "id": 1,
                "nome": "Sistema AppCheckin",
                "slug": "sistema-appcheckin"
            }
        },
        // ... mais usuários sem duplicatas
    ]
}
```

## Testando a Correção

### Via cURL com Token
```bash
curl -X GET http://localhost:8080/superadmin/usuarios \
  -H "Authorization: Bearer SEU_TOKEN_JWT" \
  -H "Content-Type: application/json"
```

### Via Docker
```bash
docker-compose exec php php artisan tinker
# Ou acessar via navegador se houver interface
```

## Notas Importantes

- ✅ A correção mantém o tenant_id ordenado para consistência
- ✅ O primeiro tenant retornado é sempre o mesmo para cada usuário
- ✅ Se necessário retornar todos os tenants de um usuário, há o método `getTenantsByUsuario()`
- ✅ O método `listarPorTenant()` não foi afetado (filtra por tenant específico no WHERE)

## Próximos Passos (Opcional)

Se desejar retornar **todos os tenants** de um usuário na listagem superadmin:

1. Modificar o response para incluir um array `tenants` ao invés de um único `tenant`
2. Usar `getTenantsByUsuario()` para popular esse array
3. Ajustar a contagem de "total" conforme necessário

Mas a solução atual é mais simples e respeita o padrão existente da API.
