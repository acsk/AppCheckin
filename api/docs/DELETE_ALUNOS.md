# Delete de Alunos - Guia Completo

## Tipos de Exclusão

Existem dois tipos de exclusão de alunos:

### 1. **Soft Delete** (Desativação) - ✅ RECOMENDADO

**Endpoint:** `DELETE /admin/alunos/{id}`

- ✅ Reversível
- ✅ Mantém histórico e integridade de dados
- ✅ Não quebra referências internas
- ✅ Seguro para produção

**O que acontece:**
- Marca aluno como `ativo = 0`
- Desativa papel no tenant
- Desativa vínculo com tenant
- Mantém todos os registros de histórico (checkins, matrículas, pagamentos, etc.)

**Response (201):**
```json
{
  "type": "success",
  "message": "Aluno desativado com sucesso"
}
```

### 2. **Hard Delete** (Exclusão Completa) - ⚠️ CUIDADO

**Endpoint:** `DELETE /admin/alunos/{id}/hard`

- ❌ IRREVERSÍVEL
- ❌ Remove dados de histórico
- ⚠️ Use apenas em exceções

**O que é deletado:**
- ✂️ Aluno
- ✂️ Usuário associado
- ✂️ Vínculo com tenant
- ✂️ Papéis do usuário
- ✂️ Checkins
- ✂️ Matrículas
- ✂️ Pagamentos
- ✂️ WOD Resultados
- ✂️ Logs de email

**Response (200):**
```json
{
  "type": "success",
  "message": "Aluno e dados associados deletados permanentemente",
  "warning": "Esta operação é irreversível"
}
```

## Fluxo Recomendado

```
1. [PADRÃO] Aluno sai → DELETE /alunos/{id} (soft delete)
   ├─ Fácil de reverter
   └─ Mantém histórico para auditoria

2. [RARO] Dados duplicados ou testes → DELETE /alunos/{id}/hard
   ├─ Remove completamente
   └─ ⚠️ Irreversível - confirmação obrigatória
```

## Integridade de Dados

Ambas operações respeitam:
- ✅ Transações ACID (tudo ou nada)
- ✅ Foreign Keys em cascata
- ✅ Rollback automático em caso de erro
- ✅ Registros órfãos são evitados

## Exemplos cURL

### Soft Delete (Desativar)
```bash
curl -X DELETE https://api.appcheckin.com.br/admin/alunos/5 \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json"
```

### Hard Delete (Deletar Completamente)
```bash
curl -X DELETE https://api.appcheckin.com.br/admin/alunos/5/hard \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json"
```

## Restaurar Aluno Desativado

Se um aluno foi desativado (soft delete) e você quer reativá-lo:

```bash
curl -X PUT https://api.appcheckin.com.br/admin/alunos/5 \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"ativo": true}'
```

## Checklist de Segurança

- [ ] Usar soft delete como padrão
- [ ] Hard delete apenas para testes/dados duplicados
- [ ] Fazer backup antes de hard delete em produção
- [ ] Registrar motivo da exclusão para auditoria
- [ ] Avisar o aluno antes de desativar conta
- [ ] Verificar se existem matrículas ativas antes de deletar
