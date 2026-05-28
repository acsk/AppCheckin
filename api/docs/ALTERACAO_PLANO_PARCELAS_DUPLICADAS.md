# Alteração de plano: parcelas duplicadas e baixa MP errada

## Caso real (matrícula #235)

| Parcela | Valor | Origem |
|---------|-------|--------|
| #483 | R$ 70 | Pago (plano anterior) |
| #484 | R$ 70 | `gerarProximoPagamentoAutomatico` após #483, **antes** do upgrade |
| #575 | R$ 120 | Upgrade no app (plano "2x por Semana") |

O PIX **R$ 120** (`160879679884`) foi baixado na **#484** (R$ 70) porque o job/webhook escolhia a parcela **mais antiga por data**, não por **valor**.

## Causa raiz

1. **App (`comprarPlano` reutilizando matrícula vencida)**  
   - Atualizava `matricula.valor` para o novo plano.  
   - **Não cancelava** parcelas abertas do plano antigo (#484 atrasada).  
   - O `INSERT` só evitava duplicata se existisse pendente com `status_pagamento_id = 1` — **atrasadas (3) não bloqueavam**, gerando **duas cobranças** (#484 + #575).

2. **Painel (`alterarPlano`)**  
   - Já cancelava `status IN (1, 3)` — comportamento correto.

3. **Webhook / job MP**  
   - Baixava a parcela pendente **mais antiga**, não a de **valor igual** ao PIX.

## Correções aplicadas

| Arquivo | Mudança |
|---------|---------|
| `PagamentoPlano.php` | `cancelarParcelasAbertas()` centralizado |
| `MobileController.php` | Cancela parcelas abertas no upgrade; cria sempre 1 pendente com valor novo |
| `MatriculaController.php` | Usa `cancelarParcelasAbertas()` |
| `MercadoPagoWebhookController.php` | Baixa por `ABS(valor - transaction_amount)`; `atualizarStatusMatricula` após baixa |
| `atualizar_pagamentos_mp.php` | Match parcela mais recente + `cancelarParcelasDuplicadasAposBaixa` |
| `PagamentoPlano.php` | `cancelarParcelasDuplicadasAposBaixa()` após cada baixa PIX |

## Deploy

Subir para produção os arquivos acima + jobs de correção já usados (`corrigir_baixa_parcela_mp.php`, `cancelar_parcela_plano.php`).

## Teste manual sugerido

1. Matrícula vencida com parcela R$ 70 atrasada.  
2. Upgrade no app para plano R$ 120 + PIX.  
3. Verificar: só **uma** parcela pendente R$ 120; após pagamento, matrícula **ativa**.
