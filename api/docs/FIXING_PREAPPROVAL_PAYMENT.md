# Fix: Entrega do Preapproval para Planos e Pacotes

## Problema Identificado
- Ao contratar plano ou pacote, o método deveria ser **sempre preapproval** (assinatura recorrente)
- PIX deveria ser opção apenas para pagamentos avulsos
- O botão de finalizar pagamento ficava desabilitado quando não era preapproval

## Causa Raiz
A função `criarPreferenciaAssinatura()` possuía um **fallback automático**: se o preapproval falhava, voltava para `criarPreferenciaPagamento()` (preference normal). Isso causava:
- Tipo de pagamento marcado como "assinatura" no DB
- Mas a resposta era uma preference (checkout) normal
- Frontend recebia dados inconsistentes, desabilitando o botão

## Solução Implementada

### 1. Mudança em `MercadoPagoService.php`

#### `criarPreferenciaAssinatura()` (linhas ~594)
```php
// ANTES: Tentava preapproval com fallback para preference
try {
    return $this->tentarCriarPreapproval(...);
} catch (Exception $e) {
    // Fallback para criarPreferenciaPagamento()
}

// DEPOIS: SEMPRE usa preapproval, sem fallback
public function criarPreferenciaAssinatura(array $data, int $duracaoMeses = 1): array
{
    return $this->tentarCriarPreapproval($data, $duracaoMeses);
}
```

#### `tentarCriarPreapproval()` (linhas ~622)
- Melhorado para sempre retornar preapproval corretamente
- Validação mais rigorosa de resposta
- Logs detalhados para debug
- Lança exceção em caso de erro (sem fallback silencioso)

**Melhorias:**
- ✅ Validação da URL de pagamento (throw se vazia)
- ✅ Melhor tratamento de ambiente sandbox vs produção
- ✅ Logs mais informativos (tipo, frequência, valor, ambiente)
- ✅ Retorna `'tipo' => 'assinatura'` para o frontend saber com certeza

### 2. Mudança em `MobileController.php`

#### `comprarPlano()` (linhas ~5611)
```php
// ANTES: Chamava criarPreferenciaAssinatura com fallback silencioso
$preferencia = $mercadoPago->criarPreferenciaAssinatura(...);

// DEPOIS: Trata exceção explicitamente
try {
    $preferencia = $mercadoPago->criarPreferenciaAssinatura(...);
    $tipoPagamento = 'assinatura';
} catch (\Exception $e) {
    // Retorna erro ao usuário em vez de tentar fallback
    return $response->withStatus(500);
}
```

#### `pagarPacote()` (linhas ~3844)
```php
// ANTES: Usava criarPreferenciaPagamento (payment único)
$preferencia = $mercadoPago->criarPreferenciaPagamento($dadosPagamento);

// DEPOIS: Usa criarPreferenciaAssinatura (preapproval)
$preferencia = $mercadoPago->criarPreferenciaAssinatura($dadosPagamento, 1);
```

## Comportamento Agora

### Fluxo de Pagamento

| Tipo | Método | Gateway | Permite |
|------|--------|---------|---------|
| **Plano (contratação/ciclo)** | `criarPreferenciaAssinatura()` | Preapproval | ❌ Apenas Cartão de Crédito |
| **Pacote** | `criarPreferenciaAssinatura()` | Preapproval | ❌ Apenas Cartão de Crédito |
| **Diária Avulsa** | `criarPagamentoPix()` ou `criarPreferenciaPagamento()` | PIX ou Preference | ✅ PIX, Cartão, Boleto |

### Método Preapproval
- Requer apenas **cartão de crédito**
- Cobrança **recorrente automática** (sem fallback)
- Se falhar, retorna erro 500 → usuário precisa tentar novamente ou contactar suporte
- Botão fica **habilitado no checkout do MP**

### PIX (Pagamentos Avulsos)
- Apenas para pagamentos **únicos** (diárias avulsas)
- **Não funciona** com planos/pacotes (preapproval rejeita PIX)
- É uma **escolha do usuário** na tela de pagamento

## Mensagens de Erro

Se preapproval falhar (plano/pacote):
```json
{
    "success": false,
    "code": "PREAPPROVAL_ERRO",
    "message": "Falha ao processar assinatura. Por favor, tente novamente ou entre em contato com o suporte."
}
```

Se pagamento de pacote falhar:
```json
{
    "success": false,
    "message": "Falha ao processar pagamento do pacote. Por favor, tente novamente."
}
```

## Testes Recomendados

1. **Contratar Plano (Mensal)**
   - Deverá redirecionar para Preapproval do MP
   - Botão de pagamento deverá estar **habilitado**
   - Apenas cartão de crédito disponível

2. **Contratar Pacote**
   - Deverá redirecionar para Preapproval do MP
   - Botão de pagamento deverá estar **habilitado**
   - Apenas cartão de crédito disponível

3. **Comprar Diária com PIX**
   - Deverá gerar QR Code PIX
   - Checkout normal (payment method)

4. **Comprar Diária com Cartão**
   - Deverá redirecionar para Preference do MP
   - Múltiplos métodos de pagamento disponíveis

5. **Erro de Preapproval**
   - Se API do MP estiver indisponível
   - Deverá retornar erro 500 com mensagem clara
   - **Não** deve tentar fallback silencioso

## Logs Importantes

Procurar por:
- `[MercadoPagoService] 🔄 Criando PREAPPROVAL`
- `[MercadoPagoService] ✅ Preapproval criado com sucesso`
- `[MobileController::comprarPlano] Criando ASSINATURA RECORRENTE`
- `[MobileController::pagarPacote] Erro ao criar preapproval`

## Fallback (Quando Usar)

Não há mais fallback automático. Se o preapproval **absolutamente falhar** em produção (credenciais ruins, API indisponível), o usuário deverá:
1. Ver mensagem de erro
2. Tentar novamente
3. Contactar suporte

Isso garante transparência e evita cobranças erradas por uso de método de pagamento incorreto.

## Data da Implementação
**17 de fevereiro de 2026**

## Responsável
André Cabral
