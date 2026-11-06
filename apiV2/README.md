# AppCheckin API v2 (Laravel)

API Laravel em paralelo à API Slim (`api/`), com prefixo **`/v2`** e **JWT compatível** (mesmo `JWT_SECRET`).

## Rotas iniciais

| Método | Rota | Auth |
|--------|------|------|
| GET | `/v2/ping` | Não |
| GET | `/v2/health` | Não |
| GET | `/v2/health/basic` | Não |
| POST | `/v2/auth/login` | Não |
| POST | `/v2/auth/register` | Não |
| POST | `/v2/auth/password-recovery/request` | Não |
| POST | `/v2/auth/password-recovery/validate-token` | Não |
| POST | `/v2/auth/password-recovery/reset` | Não |
| POST | `/v2/auth/select-tenant` | JWT |
| POST | `/v2/auth/select-tenant-initial` | Não |
| POST | `/v2/auth/select-tenant-public` | Não |
| POST | `/v2/auth/logout` | JWT |
| GET | `/v2/auth/tenants` | JWT |
| GET | `/v2/me` | JWT |
| GET | `/v2/mobile/perfil` | JWT (+ `tenant_id` no token) |
| GET | `/v2/mobile/acesso` | JWT |
| GET | `/v2/mobile/tenants` | JWT |
| GET | `/v2/mobile/checkins` | JWT (`limit`, `offset`) |
| GET | `/v2/mobile/checkins/por-modalidade` | JWT |
| GET | `/v2/mobile/ranking/mensal` | JWT (`modalidade_id` opcional) |
| GET | `/v2/mobile/wod/hoje` | JWT |
| GET | `/v2/mobile/wods/hoje` | JWT |
| GET | `/v2/mobile/horarios-disponiveis?data=YYYY-MM-DD` | JWT |
| POST | `/v2/mobile/checkin` | JWT (`turma_id` no body) |
| DELETE | `/v2/mobile/checkin/{checkinId}/desfazer` | JWT |
| GET | `/v2/mobile/planos-disponiveis` | JWT |
| GET | `/v2/mobile/planos` | JWT (`todas=true` opcional) |
| GET | `/v2/mobile/planos/{planoId}` | JWT |
| GET | `/v2/mobile/matriculas/{matriculaId}` | JWT |
| POST | `/v2/mobile/comprar-plano` | JWT |
| POST | `/v2/mobile/pagamento/pix` | JWT (`matricula_id`) |
| POST | `/v2/mobile/verificar-pagamento` | JWT |
| GET | `/v2/mobile/pagamento/reabrir/{matriculaId}` | JWT |
| GET | `/v2/mobile/assinaturas` | JWT |
| GET | `/v2/mobile/assinaturas/aprovadas-hoje` | JWT (`matricula_id`) |
| POST | `/v2/mobile/assinatura/{id}/cancelar` | JWT |
| POST | `/v2/mobile/diaria/{matriculaId}/cancelar` | JWT |

Respostas **mobile** usam `{ "success": true/false, "data"?, "error"? }` (igual à Slim), não o formato `ApiError` da auth.

Pagamentos usam `App\Services\MercadoPagoService` (portado da Slim) com credenciais por tenant ou variáveis `MP_*` / `MP_FAKE_API_URL`.

## Desenvolvimento local (Docker)

### Acesso híbrido às aulas

Consulte a [documentação consolidada de turmas fixas, grade individual, reposições e seed do tenant 5](../TURMAS_FIXAS_E_ACESSO_HIBRIDO.md) para o histórico da implementação, regras operacionais, evidências e roteiro de publicação.

O mesmo tenant pode combinar **horário livre** e **turma fixa**, sem configuração específica para a Aqua4Styles:

- Em **Configurações Gerais → Matrículas**, `tipo_acesso_padrao` pré-seleciona o formulário do painel. A escolha é gravada em `matriculas.tipo_acesso`; mudar o padrão não altera contratos existentes. O cadastro público continua igual.
- A secretaria cadastra turmas com nome, professor, modalidade, dias ISO (segunda=1, domingo=7), horários e capacidade. **Não há planos associados à turma.** O plano, o ciclo e o preço contratado pertencem à matrícula. A grade recorrente materializa ocorrências na agenda existente para os próximos 90 dias.
- A matrícula fixa possui uma **grade individual** (`matricula_grade`) com um ou mais pares turma/dia da semana, inclusive de turmas diferentes da mesma modalidade. Selecionar terça não reserva quinta. Novas grades e trocas de plano respeitam `planos.checkins_semanais` (zero mantém a regra existente de frequência ilimitada). A secretaria vê a grade e os casos legados sinalizados para revisão.
- O painel oferece o menu **Turmas** quando o tenant tem turmas cadastradas ou utiliza `turma_fixa` como tipo padrão, respeitando as permissões da equipe. Tenants de horário livre podem cadastrar a primeira turma pela área de Aulas. O app exibe a grade individual do aluno.
- Matrículas pendentes não reservam vaga: a reserva acontece na ativação/pagamento. Pagamento confirmado sem capacidade fica em **Pago - aguardando vaga**, sem apagar a baixa; o painel permite alterar a grade, sem mudar o preço. A sincronização financeira pode tentar novamente quando houver vaga.
- Cada aula pode habilitar check-in livre. O saldo é `capacidade - alunos fixos vigentes naquele dia - reposições confirmadas - check-ins livres`, contando cada aluno apenas uma vez; ausência de aluno fixo não libera sua reserva. O professor responsável pela aula (ou administrador) pode **Adicionar aluno para reposição**. A inclusão em `aula_reposicoes` reserva apenas aquela ocorrência e autoriza o check-in dentro da janela, sem registrar presença antecipadamente nem alterar grade, plano ou cobrança. O histórico da presença identifica **Reposição**. A inclusão manual regular da equipe continua disponível.
- No app, o aluno vê a próxima aula fixa e a indicação da vaga reservada; o horário livre mantém seus limites de plano. Troca/contratação de plano de matrícula fixa é feita pela secretaria; o pagamento da mensalidade continua no fluxo atual.
- Uma simulação de troca iniciada no app antes de a secretaria converter a matrícula para fixa não pode sobrescrever esse vínculo no retorno do pagamento. A confirmação é recusada explicitamente para conferência da secretaria, sem apagar o pagamento recebido.
- O check-in móvel, a edição de grade e a reposição compartilham locks ordenados dos grupos do tenant, da ocorrência e do aluno. Reservas e limites de presença usam leituras correntes na transação, inclusive no MySQL. Aulas existentes ficam abertas ao horário livre. Diminuir capacidade abaixo das reservas/ocupação é recusado.
- A edição de uma turma recorrente preserva dias, horários, contratos e permissões específicas das aulas. Para outra grade, crie nova turma e transfira os vínculos explicitamente. A edição de ocorrência permite habilitar check-in livre pontualmente.

Rotas administrativas: `GET/POST /v2/admin/turmas-fixas`, `PUT /v2/admin/turmas-fixas/{id}`, `GET /v2/admin/matriculas/{id}/grade` e `PUT /v2/admin/matriculas/{id}/tipo-acesso`. A criação de matrícula e a alteração de acesso recebem `grade: [{turma_fixa_id, dia_semana}]`. Exigem JWT de administrador e tenant selecionado. `POST /v2/mobile/professor/reposicoes` recebe `turma_id` da ocorrência e `aluno_id`, validando tenant, professor responsável, modalidade, vigência e capacidade no backend.

**Publicação:** faça backup e publique a API antes dos clientes. Não foi executada migração em produção nesta implementação.

```bash
/opt/alt/php84/usr/bin/php artisan migrate --path=database/migrations/2026_11_06_100000_acesso_hibrido_aulas.php --force
/opt/alt/php84/usr/bin/php artisan migrate --path=database/migrations/2026_11_06_140000_grade_individual_reposicoes.php --force
/opt/alt/php84/usr/bin/php artisan optimize:clear
```

A migração mantém todos os registros, preços, cobranças e status anteriores; atribui `horario_livre` a matrículas existentes e `checkin_livre=true` às aulas anteriores. É irreversível para evitar apagar contratos/aulas: restauração exige backup validado. O cron Laravel existente deve executar `schedule:run` a cada minuto; `turmas:gerar-aulas-fixas` mantém a grade diariamente. Falhas/conflitos de agenda são explícitos e precisam ser corrigidos.

A segunda migração preserva **todos os dias** da turma em cada vínculo fixo antigo e marca `grade_revisao=true` quando excedem a frequência do plano (ou quando ela não pode ser identificada). Não corta dias nem muda plano, valor, status ou histórico de check-ins. As referências singulares e a pivot antiga permanecem apenas para auditoria; a autorização/reserva nova utiliza a grade individual. Matrículas legadas acima da frequência continuam com sua grade até revisão explícita da secretaria; alterações devem respeitar o limite.

**Reajuste:** editar preço de tabela não reajusta contratos, parcelas ou recorrências. O agendamento automático `reajuste:aplicar-renovacoes` foi removido. Marcações antigas em `valor_renovacao` são preservadas, mas não são aplicadas sem `reajuste_autorizado_em`. Para contratos avulsos, o operador pode simular e confirmar explicitamente, por tenant/ciclo/preço anterior:

```bash
php artisan planos:reajustar-contratos --tenant=ID --ciclo=ID --valor-anterior=100
php artisan planos:reajustar-contratos --tenant=ID --ciclo=ID --valor-anterior=100 --confirmar
```

Valores negociados, pacotes e contratos com fidelização não são abrangidos por essa ação. Em produção, use o executável PHP 8.4 acima. A ação confirmada usa as regras de renovação do serviço existente; uma simples edição de tabela nunca executa essa ação.

Testes da funcionalidade:

```bash
php vendor/bin/phpunit --filter 'AcessoHibrido|GradeIndividual'
```

`AcessoHibridoConcurrencyTest` é opt-in e exige um MySQL **descartável e isolado**, sem portas expostas. Ele usa somente o banco `appcheckin_hybrid_test`, cria/remove suas tabelas e inicia processos PHP simultâneos. Nunca execute em um banco da aplicação.

```bash
# Terminal 1 (senha exclusivamente de teste; container descartado ao parar):
docker run --rm --name appcheckin-hybrid-test -e MYSQL_ROOT_PASSWORD=local-test-only -e MYSQL_DATABASE=appcheckin_hybrid_test mysql:8.4
# Terminal 2, na raiz do repositório, depois de "ready for connections":
docker run --rm --network container:appcheckin-hybrid-test -e HYBRID_MYSQL_TEST=1 -e HYBRID_MYSQL_PASSWORD=local-test-only -v "$PWD/apiV2:/app" -w /app appcheckin-php-v2 php vendor/bin/phpunit --filter AcessoHibridoConcurrencyTest
```

A suíte cobre migração/legados, autenticação/tenant, planos distintos na mesma turma, grade individual entre turmas/dias, frequência semanal, reservas com ausências (12/10/2), janela, duplicidade, reposição pontual e histórico, pagamento sem vaga, prorrogação manual, novas contratações/preços antigos e simulação/confirmação de reajustes. No MySQL, verifica a última vaga, ativações/grades concorrentes, limite diário entre aulas diferentes, duas reposições simultâneas e reposição disputando a última vaga com check-in livre. Os testes isolam comunicação e regras auxiliares com fixtures; o MySQL executa locks, consultas e inserts reais. Android/iOS e gateways de pagamento em produção exigem homologação após publicação.

### Persistência de recordes

`GET /v2/admin/recordes` lista todos os recordes válidos do tenant, de alunos
e da academia. Aceita `origem=aluno|academia`, `aluno_id`, `definicao_id` e
`modalidade_id`. Quando apenas `aluno_id` é informado, lista os recordes de
origem aluno desse aluno.

A tabela `recorde_valores` do schema compartilhado com a API Slim possui
`created_at`, mas não `updated_at`. A criação e a substituição das métricas
devem respeitar esse schema; não é necessária uma migração para registrar recordes.

Os testes de regressão usam SQLite em memória, sem gravar no banco da aplicação:

```bash
docker exec -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= appcheckin_php_v2 php vendor/bin/phpunit tests/Feature/RecordePessoalPersistenceTest.php tests/Feature/V2AdminRecordeRoutesTest.php
```

### Subindo a API

Com o stack principal no ar:

```bash
docker compose up -d mysql php-v2
```

- **API Slim:** http://localhost:8080  
- **API v2:** http://localhost:9090  

Exemplo de login:

```bash
curl -s -X POST http://localhost:9090/v2/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"teste@exemplo.com","senha":"password123"}'
```

## Telefones internacionais

Novos telefones são validados com `giggsey/libphonenumber-for-php` e armazenados
em E.164 (`+` e DDI). A validação Angular legada não participa dos fluxos atuais:
painel/mobile/site React reutilizam `packages/phone` com `libphonenumber-js/max`.
Brasil é o padrão para **novas entradas nacionais** de clientes antigos; a
migração **não** atribui país a números existentes sem DDI.

Campos: `telefone`, `whatsapp`, `emergencia_telefone`, `responsavel_telefone`,
inclusive em objetos aninhados. Enviar `null` limpa um campo opcional; omiti-lo
preserva o valor. Entradas inválidas retornam HTTP 422 com `errors` por campo.
Webhooks não são normalizados pelo middleware cadastral.

### Publicação e auditoria segura

Faça backup do banco e instale as dependências com `composer install` antes dos
comandos abaixo. Nenhuma conversão deve ser feita automaticamente no deploy.
A auditoria é somente leitura por padrão e não imprime números pessoais:

```bash
php artisan telefones:normalizar --detalhes
```

Ela inventaria as colunas existentes em `usuarios`, `alunos`, `professores` e
`tenants`, verifica índices e classifica números como normalizados, conversíveis,
sem DDI ou inválidos. Não utiliza tabelas de histórico como fonte cadastral.

Ordem de implantação:

1. Criar somente o histórico dos originais:
   `php artisan migrate --path=database/migrations/2026_11_03_200000_create_telefone_normalizacoes_table.php --force`.
2. Auditar os registros e índices no banco de destino.
3. Converter os números com DDI explícito e válido:
   `php artisan telefones:normalizar --aplicar`.
4. Confirmar o país dos registros sem DDI antes de converter. Exemplo restrito:
   `php artisan telefones:normalizar --aplicar --pais-legado=BR --tabela=usuarios --id=123`.
   Não usar `--pais-legado=BR` globalmente sem verificar a origem dos dados.
5. Resolver manualmente valores maiores que 20 caracteres e casos ambíguos,
   sem apagá-los. A migração de schema recusa truncamento antes de alterar colunas.
6. Executar
   `php artisan migrate --path=database/migrations/2026_11_03_201000_telefones_e164_varchar20.php --force`
   e publicar as interfaces junto da API.

O original de cada conversão é preservado em `telefone_normalizacoes` na mesma
transação da atualização. Restrinja acesso a essa tabela: ela contém dados pessoais.
Reexecutar o comando não reconverte números E.164. Os métodos `down` não removem
o histórico nem reduzem a coluna, pois isso poderia destruir números internacionais.

Telefones compartilhados por familiares continuam permitidos: não foi adicionada
unicidade global. Índices existentes são preservados; conversões com colisão
normalizada em índice único são bloqueadas para revisão (a verificação de índices
compostos é conservadora). Buscas aceitam E.164 e os formatos legados equivalentes.

WhatsApp usa `https://wa.me/<E164 sem +>`. Os gateways brasileiros Asaas/Mercado Pago
continuam recebendo somente o número nacional brasileiro em seus campos opcionais:
telefones estrangeiros não são enviados como falsos números brasileiros, mas
permanecem íntegros no cadastro.

### Testes

```bash
php vendor/bin/phpunit tests/Unit/TelefoneTest.php tests/Feature/TelefonesInternacionaisTest.php
# A partir da raiz:
node --test packages/phone/phone.test.mjs painel/scripts/whatsapp.test.mjs
```

Os testes de banco usam SQLite em memória. Execute a auditoria no MySQL de destino
antes da migração; os testes não inspecionam nem modificam o banco de produção.

## Variáveis de ambiente

Copie `.env.example` para `.env` e alinhe com a API Slim:

```bash
cp .env.example .env
php artisan key:generate
```

- `APP_KEY` — obrigatório (`php artisan key:generate`); o Docker usa `apiV2/.env` via `env_file`
- `DB_*` — mesmo banco `appcheckin`
- `JWT_SECRET` — **obrigatório**, mesmo valor da API Slim
- `JWT_EXPIRATION` — padrão `86400`

## Servidor embutido (sem Docker)

```bash
cd apiV2
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=9090
```
