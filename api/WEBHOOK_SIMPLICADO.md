# 🎯 Simplificação: Webhook para Atualizar Pacote

## ✅ O Que Mudou

**Antes**: Estava tentando fazer muita coisa (criar matrículas, pagamentos, etc) e silenciosamente falhava
**Agora**: Apenas UMA coisa - UPDATE do `pacote_contratos.status` para 'ativo'

```php
private function ativarPacoteContrato(int $contratoId, array $pagamento): void
{
    try {
        $stmt = $this->db->prepare("
            UPDATE pacote_contratos
            SET status = 'ativo'
            WHERE id = ?
        ");
        
        $stmt->execute([$contratoId]);
        $rowsAffected = $stmt->rowCount();
        
        if ($rowsAffected > 0) {
            error_log("[Webhook MP] ✅ Contrato #{$contratoId} atualizado para status 'ativo'");
        } else {
            error_log("[Webhook MP] ⚠️ Contrato não encontrado");
        }
    } catch (\Exception $e) {
        error_log("[Webhook MP] ❌ Erro: " . $e->getMessage());
    }
}
```

## 📝 Credenciais do Banco Remoto

```
Host:     srv1314.hstgr.io (ou 193.203.175.71)
Porta:    3306
Banco:    u304177849_api
Usuário:  u304177849_api
Senha:    +DEEJ&7t
```

## 🧪 Testar Conexão

Existe um script simples criado para testar a conexão:

```bash
php test_remote_db.php
```

**O que ele faz:**
1. ✅ Conecta ao banco remoto
2. ✅ Lista os pacote_contratos existentes
3. ✅ Faz um UPDATE de teste
4. ✅ Verifica se funcionou

## 🔍 Query Manual para Testar (via PhpMyAdmin)

```sql
-- Ver contratos pendentes
SELECT id, status, assinatura_id, valor_total FROM pacote_contratos;

-- Atualizar um contrato de teste
UPDATE pacote_contratos SET status = 'ativo' WHERE id = 1;

-- Verificar se atualizou
SELECT id, status FROM pacote_contratos WHERE id = 1;
```

## 📊 Fluxo Atual do Webhook

```
Pagamento aprovado no Mercado Pago
    ↓
Webhook recebe notificação
    ↓
Detecta: external_reference = "PAC-{contratoId}-..."
    ↓
Chama: ativarPacoteContrato(contratoId)
    ↓
UPDATE pacote_contratos SET status = 'ativo' WHERE id = contratoId
    ↓
✅ PRONTO!
```

## 📋 Próximos Passos Depois (Matrículas e Pagamentos)

Quando o UPDATE básico estiver funcionando, então adicionamos:
1. ✅ Criar matrículas para pagante e beneficiários
2. ✅ Rateio de valor entre todos
3. ✅ Criar pagamentos já como PAGO

Mas PRIMEIRO, vamos garantir que o UPDATE simples funciona! 🎯

## ✏️ Alterações no Código

- Commit: `5ff27af` - refactor: simplificar ativarPacoteContrato para apenas UPDATE status
- Removidos: `criarPagamentoPacote()` e `atualizarMatriculasDoPackge()`
- Resultado: Código muito mais simples e testável
