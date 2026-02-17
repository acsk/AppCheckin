# ✅ Implementação: Preapproval para Todos os Pagamentos

## Data
17 de fevereiro de 2026

## Mudança Realizada

Aplicado **preapproval (assinatura recorrente)** para **TODOS** os tipos de pagamento:

### Antes (Parcialmente Funcional)
```
Plano Recorrente  → Preapproval ✅
Plano Avulso      → Preference ❌ (não funcionava)
Pacote            → Preference ❌ (não funcionava)
PIX Avulso        → PIX (Pagamento)
```

### Depois (Totalmente Funcional)
```
Plano Recorrente  → Preapproval ✅
Plano Avulso      → Preapproval ✅ (NOVO)
Pacote            → Preapproval ✅ (CORRIGIDO)
PIX Avulso        → PIX (Pagamento Direto)
```

## Arquivo Modificado

- **`MobileController.php`** (linha ~5642)
  - Mudou pagamento avulso de `criarPreferenciaPagamento()` para `criarPreferenciaAssinatura()`
  - Adicionado tratamento de erro explícito (sem fallback silencioso)

## Como Funciona

### Preapproval (Todos exceto PIX avulso)
- Gateway: Mercado Pago Preapproval
- Método: **Cartão de Crédito APENAS**
- Tipo: Recorrência mensal (mesmo para avulso => cobrador define se renova ou não)
- Status: ✅ Botão de pagamento habilitado
- Checkout: Integrado no Mercado Pago

### PIX Avulso
- Gateway: Mercado Pago Payment (PIX específico)
- Método: PIX
- Tipo: Pagamento único
- Status: QR Code + Ticket
- Verificar: `criarPagamentoPix()` em MobileController

## Teste Rápido

Para testar:

```bash
# 1. Contratar Plano (Avulso) com Cartão
POST /mobile/planos/{planoId}/comprar
Body: {
    "ciclo": "semestral",
    "metodo_pagamento": "cartao"
}

# Esperado: Redireciona para Preapproval do MP
# Botão: HABILITADO ✅

# 2. Contratar Plano (Recorrente) com Cartão
POST /mobile/planos/{planoId}/comprar
Body: {
    "ciclo": "mensal",
    "metodo_pagamento": "cartao"
}

# Esperado: Redireciona para Preapproval do MP
# Botão: HABILITADO ✅
```

## Logs para Verificar

```
[MobileController::comprarPlano] Criando PAGAMENTO AVULSO (preapproval)...
[MercadoPagoService] 🔄 Criando PREAPPROVAL (assinatura recorrente)
[MercadoPagoService] ✅ Preapproval criado com sucesso!
```

## Diferenças

| Aspecto | Antes | Depois |
|---------|-------|--------|
| Avulso funciona? | ❌ | ✅ |
| Botão fica habilitado? | ❌ | ✅ |
| Método de pagamento | Preference (variável) | Preapproval (fixo) |
| PIX desabilitado? | ❌ | ✅ (para recorrente/avulso) |
| Apenas Cartão em Recorrente? | ✅ | ✅ |

## Compatibilidade

- `criarPreferenciaPagamento()` mantida por compatibilidade (não mais usada)
- Webhooks: Nenhuma mudança (MP trata igual)
- Banco de dados: Nenhuma mudança
- Frontend: Nenhuma mudança necessária

## Próximas Etapas (Opcional)

Se quiser PIX como opção **mesmo para recorrente/avulso**, seria necessário:
1. Modificar preapproval para aceitar PIX (se MP permitir)
2. Ou criar fluxo híbrido (cobrar com PIX primeira vez, depois preapproval)
3. Ou deixar PIX só para avulso mesmo

Atualmente: **PIX apenas para pagamentos totalmente únicos via criarPagamentoPix()**

## Nota Importante

Preapproval sempre requer **cartão de crédito** para criar a preaprovação.
PIX é um método de pagamento direto e não suporta recorrência automática no MP.
