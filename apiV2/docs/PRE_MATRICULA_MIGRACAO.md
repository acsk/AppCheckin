# Pré-matrícula de migração

O Super Admin configura o fluxo por academia em **App Mobile →
Pré-matrícula de migração**. A tela permite criar, editar e excluir a
configuração da academia. A configuração define se o fluxo está ativo e se o
envio é obrigatório no app e quais alunos são elegíveis: sem matrícula ativa,
com matrícula ativa ou ambos.
O app lista os planos ativos de valor zero, sem ocultar uma opção cuja
frequência ainda não tenha dias distintos suficientes na grade. Nesse caso,
informa ao aluno que a academia precisa disponibilizar mais horários. Para
ativar o fluxo, ao menos um plano precisa ter uma grade compatível.

O app mostra a descrição e a frequência dos planos elegíveis, sem exibir preço
ou o termo `FREE`. O aluno escolhe uma turma em cada dia da semana, sem repetir
dias, e pode informar o dia de vencimento (1 a 31), que é opcional. O vínculo
ativo de aluno à academia continua obrigatório, mesmo para quem ainda não tem
matrícula.

## Revisão e efeitos

Cada aluno mantém no máximo uma solicitação pendente ou aprovada por academia.
Uma solicitação reprovada pode ser enviada novamente. A fila do painel abre no
filtro **Aguardando aprovação**; filtros adicionais permitem consultar pedidos
aprovados, reprovados ou todos. O Admin da academia pode ajustar plano, grade e
dia de vencimento em uma modal antes de aprovar ou reprovar, com observação
opcional. O ajuste mantém o pedido pendente. Ao aprovar, a API cria a matrícula
com o plano, a grade e o dia de vencimento revisados, reutilizando as validações
e regras do fluxo administrativo de matrículas. A solicitação não reserva vaga;
se a matrícula não puder ser criada (por exemplo, por falta de vaga), a decisão
não é gravada e o pedido continua pendente para revisão. Para os planos de valor
zero, aplicam-se as regras existentes de período de teste, sem cobrança imediata.
O identificador da matrícula criada fica associado ao pedido e é exibido no
painel e no app do aluno. O plano e os dados da turma são registrados como
estavam no envio, ou como ficaram no último ajuste do Admin, para que edições
posteriores do cadastro do plano ou da turma não alterem o pedido já revisado.

## Rotas

As rotas abaixo são autenticadas e usam o tenant selecionado no JWT para o app:

- `GET /v2/mobile/pre-matricula`: retorna a configuração aplicável, os planos e
  turmas elegíveis, ou o estado da solicitação atual.
- `POST /v2/mobile/pre-matricula`: recebe `plano_id`, `grade` (lista de
  `turma_fixa_id` e `dia_semana`) e `dia_vencimento` opcional (`null` ou 1–31).

As rotas do painel exigem autenticação de Super Admin:

- `GET /v2/superadmin/pre-matriculas?tenant_id={id}`: configuração e fila.
- `PUT /v2/superadmin/pre-matriculas/configuracao`: grava `tenant_id`, `ativa`,
  `obrigatoria` e `publico` (`sem_matricula`, `com_matricula` ou `ambos`).
- `DELETE /v2/superadmin/pre-matriculas/configuracao?tenant_id={id}`: remove a
  configuração e desativa novos pedidos, preservando as solicitações recebidas.
- `POST /v2/superadmin/pre-matriculas/{id}/aprovar` e
  `POST /v2/superadmin/pre-matriculas/{id}/reprovar`: recebem `tenant_id` e
  `observacao` opcional.

O Admin da academia acessa a própria fila pelo tenant do token:

- `GET /v2/admin/pre-matriculas`: fila, planos gratuitos e turmas disponíveis.
- `PUT /v2/admin/pre-matriculas/{id}`: ajusta `plano_id`, `grade` e
  `dia_vencimento` de um pedido pendente.
- `POST /v2/admin/pre-matriculas/{id}/aprovar` e
  `POST /v2/admin/pre-matriculas/{id}/reprovar`: revisa o pedido com
  `observacao` opcional.

As migrations `2026_11_06_190000_pre_matriculas_migracao.php` e
`2026_11_06_213500_add_matricula_id_to_pre_matriculas.php` criam as tabelas da
configuração e das solicitações e associam a matrícula criada ao pedido.
Execute `php artisan migrate --force` antes de publicar a API, o painel e o app
móvel.

## Erros

As rotas de pré-matrícula (`/v2/mobile/pre-matricula`, `/v2/admin/pre-matriculas`
e `/v2/superadmin/pre-matriculas`) respondem sempre em JSON, mesmo sem o
cabeçalho `Accept`, no formato `{"message": "..."}` (validações incluem também
`errors`). Falhas inesperadas retornam 500 com mensagem genérica e são
registradas no log. Se as migrations ainda não foram executadas, o app recebe
`pre_matricula: null` (recurso indisponível) e o painel recebe 503 com a
orientação de atualizar o banco.

Testes: `php vendor/bin/phpunit tests/Feature/PreMatriculaMigracaoTest.php`.
