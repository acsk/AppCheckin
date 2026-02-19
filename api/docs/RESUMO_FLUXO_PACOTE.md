# ⚡ RESUMO EXECUTIVO: Fluxo Completo de Pagamento de Pacote

## 📱 O Que Acontece

```
Cliente clica: "Pagar Pacote" (ID=4)
        ↓
POST /mobile/pacotes/contratos/4/pagar
        ↓
Backend:
  1. Valida contrato (é seu? está pendente?)
  2. Pergunta: Pacote recorrente? SIM → Assinatura | NÃO → Pagamento único
  3. Chama API Mercado Pago
  4. Salva URL no banco
        ↓
Retorna URL de pagamento
        ↓
Frontend redireciona cliente
        ↓
Cliente no Mercado Pago:
  - Escolhe forma de pagamento (cartão, PIX, etc)
  - Insere dados
  - Paga
  - Autoriza assinatura recorrente (se aplicável)
        ↓
Mercado Pago envia webhooks automaticamente:
  
  Webhook #1: "Assinatura aprovada"
    → API cria matrícula do PAGANTE
    → Cria ASSINATURA (para cobranças futuras)
  
  Webhook #2: "Primeiro pagamento aprovado"
    → API cria matrículas dos 3 BENEFICIÁRIOS
    → Marca 4 pagamentos como "pago"
    → Marca contrato como "ativo"
        ↓
✅ PRONTO!
   - 4 matrículas ativas (1 pagante + 3 beneficiários)
   - Próximas cobranças automáticas todo mês (se recorrente)
```

---

## 🔍 Detalhes Críticos

| Elemento | Detalhe |
|----------|---------|
| **URL Endpoint** | `POST /mobile/pacotes/contratos/{contratoId}/pagar` |
| **Controller** | `MobileController::pagarPacote()` |
| **Decisão Chave** | `permite_recorrencia = true` → Assinatura / false → Pagamento único |
| **Webhook #1** | `type: 'subscription_preapproval'` → `criarMatriculaPagantePacote()` |
| **Webhook #2** | `type: 'payment'` → `processarPagamentoPacote()` |
| **Armazenamento** | `assinaturas.pacote_contrato_id = 4` (ponte entre webhooks) |
| **Metadata** | Salvo em `pacote_contratos.payment_url` e `.payment_preference_id` |

---

## 💡 A Genialidade da Solução

**Problema:** Webhook de pagamento às vezes chega sem metadata
```
❌ Antes: "Não consigo saber que contrato é este" → Falha silenciosa
```

**Solução:** Webhooks em 2 etapas
```
✅ Webhook de assinatura:     Cria pagante + armazena pacote_contrato_id
✅ Webhook de pagamento:      Busca assinatura anterior → recupera pacote
```

**Resultado:** Funciona **mesmo que os dados chegarem incompletos**

---

## 📊 Timeline Visual

```
t=0s:     Cliente clica "Pagar"
          └─→ POST /pagar
              └─→ Retorna payment_url

t=5s:     Cliente no Mercado Pago
          └─→ Preenche dados

t=15s:    Cliente clica "Confirmar Pagamento"
          └─→ Mercado Pago processa

t=16s:    ✅ Pagamento aprovado
          └─→ Envia Webhook #1

t=17s:    API processa Webhook #1
          └─→ Matrícula pagante criada
          └─→ Assinatura criada (com pacote_id)

t=18s:    Mercado Pago faz cobrança
          └─→ Envia Webhook #2

t=19s:    API processa Webhook #2
          └─→ Matrículas beneficiários criadas
          └─→ Pagamentos marcados como "pago"
          └─→ Contrato marcado como "ativo"

t=20s:    🎉 PACOTE TOTALMENTE ATIVO!
          └─→ Cliente pode usar
          └─→ Próximas cobranças: automáticas
```

---

## 🗂️ Estado do Banco de Dados

### Após Webhook #1
```sql
matriculas:
  500 | aluno_id=72  | pacote_id=4 | status=ativa

assinaturas:
  300 | matricula_id=500 | pacote_contrato_id=4 | status=ativa
```

### Após Webhook #2
```sql
matriculas:
  500 | aluno_id=72  | pacote_id=4 | status=ativa    (pagante)
  501 | aluno_id=94  | pacote_id=4 | status=ativa    (benef 1)
  502 | aluno_id=95  | pacote_id=4 | status=ativa    (benef 2)
  503 | aluno_id=96  | pacote_id=4 | status=ativa    (benef 3)

pagamentos_plano:
  X | matricula_id=500 | valor=2.00 | status=pago
  Y | matricula_id=501 | valor=0.50 | status=pago
  Z | matricula_id=502 | valor=0.50 | status=pago
  W | matricula_id=503 | valor=0.50 | status=pago

pacote_contratos:
  id=4 | status=ativo | pagamento_id=146079536501
```

---

## 🔄 Fluxo Recorrente (Meses Posteriores)

```
MÊS 1: Pagamento inicial (cliente autoriza)
MÊS 2: Cobrança automática (MP cobra cartão)
       └─→ Novo webhook de pagamento chega
       └─→ Novo registro em pagamentos_plano
       └─→ Matrículas continuam ativas

MÊS 3: Cobrança automática
       └─→ (repetir)

MÊS N: Cliente cancela
       └─→ Webhook de cancelamento
       └─→ Matrículas marcadas como canceladas
       └─→ Cobranças futuras: paradas
```

---

## ✅ Checklist Final

- [ ] Cliente criou pacote com 3 beneficiários
- [ ] Pacote tem `permite_recorrencia = true`
- [ ] Cliente faz POST `/pagar` com seu token
- [ ] API retorna `payment_url`
- [ ] Cliente vai para Mercado Pago
- [ ] Cliente paga e autoriza
- [ ] Webhook #1 chega: matrícula pagante criada ✅
- [ ] Webhook #2 chega: matrículas beneficiários criadas ✅
- [ ] Banco mostra 4 matrículas ativas ✅
- [ ] Próximas cobranças acontecem automaticamente ✅

---

## 📚 Documentação Completa

Para mais detalhes, veja:
- [FLUXO_COMPLETO_PAGAR_PACOTE.md](FLUXO_COMPLETO_PAGAR_PACOTE.md) - Explicação detalhada
- [DIAGRAMA_VISUAL_FLUXO_PACOTE.md](DIAGRAMA_VISUAL_FLUXO_PACOTE.md) - Diagramas ASCII
- [NOVO_FLUXO_PACOTES_WEBHOOKS.md](NOVO_FLUXO_PACOTES_WEBHOOKS.md) - Implementação técnica
- [TESTE_NOVO_FLUXO_WEBHOOKS.md](TESTE_NOVO_FLUXO_WEBHOOKS.md) - Como testar
