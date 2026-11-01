# Créditos e troca de plano

Regras de crédito do aluno e de troca de plano depois das correções de 31/10/2026. Motivo: um aluno ganhou
crédito do valor cheio de um plano quase todo usado (matrícula 55). Além disso, matrículas canceladas
voltavam a ficar ativas sozinhas.

## Regras em vigor

| Regra | Onde |
|---|---|
| Crédito só sai de **plano vigente, em dia e pago**, e é **proporcional ao uso**: o consumo é o maior entre os dias decorridos e os check-ins feitos no ciclo. Plano cancelado, vencido, em atraso ou com o ciclo encerrado não gera crédito | `MatriculaMigracaoService::calcularCreditoMigracao` (régua única) |
| O ciclo vigente começa em vencimento − duração do ciclo, nunca antes de `data_inicio`. Mensalidades antigas não entram na soma | `inicioDoCicloVigente` |
| Valor pago no ciclo = valor − tarifa do cartão − sobretaxa + crédito abatido. A cobrança de migração ainda não confirmada não conta. Pagamento que já virou crédito também não conta, para não somar o mesmo dinheiro duas vezes | `PagamentoPlanoService::somarPagamentosPagosNoCiclo` |
| O painel (prévia do cancelamento, cancelar com crédito, alterar plano) usa a mesma régua. A opção **"Valor cheio do plano"** saiu: `abater_plano_anterior` agora credita o proporcional. O crédito manual continua | `AdminMatriculaService::creditoProporcionalAoUso` |
| Quando a migração paga é confirmada, vale **o crédito que a simulação cobrou**, nunca um recálculo. Se o recálculo der diferente, fica só um aviso no log | `MatriculaMigracaoService::aplicarTrocaAposPagamento` |
| Matrícula **cancelada no ciclo atual** (`data_cancelamento >= data_inicio`) não é reativada pelo recálculo de status, mesmo com o período pago correndo | `PagamentoPlanoService::atualizarStatusMatricula` |
| Contrato de recorrência cancelado ou encerrado + matrícula reaproveitada numa compra avulsa: o status segue a regra do plano avulso | `RecorrenciaAcesso::atualizar` |
| **Simulação de migração não é dívida**: não é marcada como "Atrasado" e não bloqueia a troca de plano | `marcarAtrasados`, `temParcelaAbertaAtrasada`, `MatriculaMigracaoAptidaoService::temParcelaAtrasada` |
| Vencimento de ciclo em meses não estoura o fim do mês (31/10 + 6 meses = 30/04) | `MatriculaMigracaoService::somarMesesSemEstouro` |
| Cancelar com crédito confere o status de novo, com trava, dentro da transação: dois cancelamentos simultâneos não geram dois créditos | `AdminMatriculaService::cancelarComCredito` |

## Troca de plano pela academia (parâmetro `troca_plano_app`)

Parâmetro da configuração remota do app, por academia. Ele começa **desligado**, o que é uma exceção à regra
do catálogo. Liga-se na tela de configuração do app do super admin.

**Desligado:**

1. O app não migra de plano (`simular-migracao`, `migrar-plano`, compra de outro plano com matrícula ativa)
   e não cancela a assinatura do plano. A API responde 403 com `TROCA_PLANO_PELA_ACADEMIA` e a mensagem para
   o aluno procurar a academia. Isso vale também para versões antigas do app.
2. O admin cancela no painel. O modal mostra **a simulação do crédito antes de confirmar** e o
   **responsável**. Quem confirma fica gravado como autor do cancelamento (`cancelado_por`) e do crédito
   (`creditos_aluno.criado_por`). O detalhe da matrícula mostra os dois.
3. O aluno contrata o novo plano pelo app. O saldo de crédito é **abatido obrigatoriamente**:
   - a parcela cobra a diferença e o crédito fica **reservado** em `pagamentos_plano.credito_reservado`.
     Ele só é consumido quando a parcela é paga (do crédito mais antigo para o mais novo; a reserva vira
     `credito_aplicado`/`credito_id`). Parcela cancelada ou abandonada não consome nada;
   - se o crédito cobre o plano inteiro, a matrícula é ativada na hora, com uma parcela paga de R$ 0, sem
     passar pelo provedor;
   - se sobrar menos de R$ 0,50 para cobrar, cobra-se R$ 0,50 e o restante do crédito fica para depois.

**Ligado:** o comportamento anterior. Migração pelo app com crédito proporcional e cancelamento pelo app.

Não entram no parâmetro: o cancelamento da **assinatura com fidelização** (os termos aceitos prometem o
cancelamento pelo app, e o arrependimento de 7 dias é garantido pelo CDC) e o cancelamento de **diária**.

Código: `app/Support/TrocaPlanoApp.php`, `MobileCompraPlanoService::ativarComCredito`,
`PagamentoPlanoService::consumirCreditoReservado`, `painel/src/components/ck/CancelarMatriculaModal.js`.

## Casos que originaram as correções

| Matrícula | O que aconteceu | Situação |
|---|---|---|
| 55 (ANA PAULA FERREIRA) | Upgrade no fim do bimestre: a simulação deu R$ 240 de crédito (o certo eram R$ 27,54) e a confirmação recalculou R$ 604,44, contando a própria cobrança da migração | Dados corrigidos: parcela #1545 de R$ 212,46 criada, pagamento #46 de volta para Pago, vencimento em 30/04/2027, origem do crédito #28 → #1192 |
| 359 (ARTHUR MARCELINO) | Desistiu da assinatura com fidelização e comprou o plano avulso na mesma matrícula; o contrato cancelado deixava a matrícula cancelada mesmo com o pagamento feito | Corrigido no código (`RecorrenciaAcesso`). O status volta ao normal no próximo recálculo depois do deploy |
| 189 (sandbox) | Cancelada pelo admin e reativada pelo recálculo de status; crédito do cancelamento ativo junto com o plano; simulação #1531 bloqueava a troca como "atraso" | Correções no código. Falta voltar o status para cancelada (SQL abaixo) |

```sql
-- Sandbox: matrícula 189 de volta para cancelada (cancelamento do admin em 28/10)
UPDATE matriculas SET status_id = 3, updated_at = NOW()
WHERE id = 189 AND tenant_id = 3 AND aluno_id = 72 AND status_id = 1 AND data_cancelamento = '2026-10-28';
```

## Deploy

1. `php artisan migrate`: cria `pagamentos_plano.credito_reservado`
   (`2026_11_05_100000_credito_reservado_pagamentos_plano`). A compra com crédito depende dela.
2. Publicar a API e o `painel/dist` (já buildado nos commits).
3. Build novo do app: aviso de crédito na lista de planos, mensagem da troca pela academia, "Plano ativado"
   quando o crédito cobre tudo e botão de cancelar escondido. O bloqueio já vale antes, pela API.
4. A limpeza diária (`cobrancas:limpar-pendentes`, 04:30) encerra as simulações de migração com mais de
   3 dias. Para não esperar, rode-a logo após o deploy.

## Pontos em aberto

- **Pedido de cancelamento pelo app:** hoje a mensagem só orienta o aluno a ir à recepção. Um pedido pelo
  app, com fila no painel, seria a próxima etapa.
- **Apps antigos:** quando o crédito cobre o plano inteiro, mostram "Link de pagamento não foi gerado",
  mas a matrícula já foi ativada.
- **Créditos por modalidade:** o saldo é da academia inteira. A renovação de uma matrícula ainda ativa em
  outra modalidade não abate o crédito.
- **Testes:** `MobilePerfilServiceTest::test_perfil_inclui_campos_de_aniversario` falha perto da meia-noite
  UTC (depende da data). No app, `tsc` não checa os tipos por causa de erros de sintaxe que já existiam em
  `src/utils/imageCompression.examples.ts`.

## Testes

`tests/Unit/MatriculaMigracaoRegrasTest.php`, `CreditoCompraAppTest.php`, `AdminMatriculaCreditoTest.php`,
`PagamentoPlanoStatusCanceladaTest.php`, `SimulacaoMigracaoNaoEhDebitoTest.php` e
`tests/Feature/RecorrenciaFidelizacaoTest.php` (compra avulsa depois de contrato cancelado).

```bash
docker exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: appcheckin_php_v2 \
  php -d memory_limit=1G vendor/bin/phpunit --filter 'Migra|Credito|PagamentoPlano|SimulacaoMigracao'
```
