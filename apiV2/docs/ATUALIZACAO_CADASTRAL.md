# Validação de e-mail nas campanhas cadastrais

Em **App Mobile → Atualização Cadastral**, marque **Validar e-mail**. Por padrão,
o usuário cujo endereço já foi confirmado pula o código.

Para pedir uma confirmação nova, ative **Forçar nova validação de e-mail**
(`forcar_validacao_email`). Essa opção implica `validar_email = true` e exige
um código de seis dígitos vinculado à campanha pendente, mesmo quando
`usuarios.email_verificado_em` está preenchido. Não apaga a confirmação anterior
do cadastro. Códigos anteriores sem vínculo ou de outra campanha não concluem
essa etapa. Os limites de envio, validade e tentativas continuam valendo.

O campo é desativado por padrão nas campanhas existentes. Desmarcar **Validar
e-mail** no painel também desativa a nova validação forçada.

Cada usuário responde uma vez por campanha. Ativar essa opção numa campanha já
respondida não reabre a resposta: crie uma campanha nova para testar novamente
com a mesma pessoa.

## Público e gestão das campanhas

O público inclui somente alunos com matrícula de status `ativa` na mesma academia
do vínculo de aluno ativo. A conta do usuário também deve estar ativa. Professores
sem esse vínculo de aluno, alunos sem matrícula ou com matrícula vencida,
cancelada, pendente ou bloqueada não recebem a campanha nem entram nos totais.
O mesmo filtro vale para público, respostas, pendentes e entrega no app.
Uma pessoa com várias matrículas/academias conta uma vez por campanha.

A listagem tem busca, filtros de situação, progresso e paginação. O botão de
exclusão exige confirmação e chama
`DELETE /v2/superadmin/atualizacoes-cadastrais/{id}`, restrito ao Super Admin.
A operação apaga definitivamente a campanha, vínculos com academias, respostas e
códigos de e-mail vinculados à campanha em uma transação. Preserva os dados
cadastrais, a senha e a confirmação de e-mail do usuário. Funciona para campanhas
em andamento e encerradas; campanhas excluídas deixam de ser entregues no app.

## Contrato e instalação

- A API de campanhas recebe e devolve `forcar_validacao_email` como booleano.
- `GET /mobile/atualizacao-cadastral` inclui o campo na pendência e devolve
  `email_verificado = false` quando a campanha força uma nova confirmação,
  permitindo que clientes anteriores também exibam o código.
- Os endpoints de enviar/validar código resolvem o vínculo com a campanha
  pendente no servidor. Nenhum ID de campanha adicional é necessário no payload.
- A conclusão da campanha verifica esse vínculo no servidor.
- A migration `2026_10_25_110000_forcar_validacao_email_campanha.php` adiciona
  `atualizacoes_cadastrais.forcar_validacao_email` e
  `verificacoes_email.atualizacao_id`. Execute `php artisan migrate --force`
  antes de publicar a API atualizada. Publique também o painel e o mobile.

Testes: `php vendor/bin/phpunit tests/Feature/AtualizacaoCadastralTest.php`
(SQLite em memória).
