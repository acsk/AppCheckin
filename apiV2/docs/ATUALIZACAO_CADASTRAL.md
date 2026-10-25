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

## Dispensa opcional por revalidação recente

Na criação ou edição de uma campanha, ative **Dispensar alunos revalidados
recentemente** e informe o **Intervalo entre recadastramentos (meses)**, de 1 a
120 meses inteiros. A flag `dispensar_revalidados` começa desativada, inclusive
nas campanhas existentes. Desativada, `intervalo_meses` é salvo como `null` e o
comportamento anterior permanece: cada aluno elegível responde uma vez.

Ativada, a campanha só é entregue a quem nunca concluiu um recadastramento ou
cuja última conclusão ocorreu há pelo menos X meses. A referência é
`usuarios.cadastro_revalidado_em`, atualizada somente ao concluir uma campanha
com sucesso, independentemente de a flag estar ligada. Confirmar apenas o e-mail,
editar o perfil ou cancelar a tela não marca o cadastro como revalidado.
A marca considera o cadastro único do usuário, inclusive conclusões em outras
academias, e permanece após a exclusão de campanhas.

Exemplo: com prazo de 6 meses, quem concluiu em 24/10 fica dispensado até 24/04.
Quem se matricular depois e nunca concluiu recebe a campanha ativa normalmente.
O cálculo usa meses de calendário: uma conclusão em 31/01 com prazo de 1 mês
vence em 28/02 (29/02 em ano bissexto), no mesmo horário.

A dispensa vale para toda a campanha, inclusive validação forçada de e-mail e
troca de senha. Deixe a flag desativada se precisar exigir essas ações de todos.
Quem já respondeu uma campanha não a recebe novamente depois do prazo: o SA
cria outra campanha. Para quem ainda não respondeu, a dispensa é calculada na
consulta; se o prazo vencer e houver campanha ativa elegível, ela poderá aparecer.
Não são criadas campanhas automaticamente.

A tabela mostra separadamente respostas e dispensados. O progresso soma os dois,
sem duplicar alunos; os dispensados não aparecem em **Pendentes**. Os filtros de
conta, vínculo e matrícula ativa continuam valendo. Os totais refletem o público
e o prazo na data da consulta, inclusive para campanhas encerradas.

Instalação: execute `php artisan migrate --force` antes de publicar a API e o
painel. A migration `2026_10_25_130000_periodo_recadastramento.php` adiciona os
campos e aproveita a última resposta ainda registrada no banco para cada usuário.
Respostas já excluídas antes dessa migration não podem ser recuperadas.
A regra é aplicada no servidor e funciona com o mobile existente, sem novo APK.
