# Central de Comunicação

Notificações, comunicados, mensagens e enquetes para os alunos. Comunicação **obrigatória** ativa trava o
check-in de quem ainda não concluiu a ação exigida. O resto do app continua livre.

| Tipo | Ação exigida (quando obrigatória) | Opcional |
|---|---|---|
| Notificação | "Li e estou ciente" | abrir já conta como lida |
| Comunicado / Mensagem | "Li e estou ciente" | botão de confirmação disponível |
| Enquete | enviar as respostas (todas as obrigatórias, validadas) | responder é opcional |

## Vigência e status

`comunicacoes.status` guarda `rascunho | publicada | encerrada | cancelada`. O status mostrado sai da vigência
na hora da consulta (`App\Support\Comunicacao::statusEfetivo`), sem agendador:

- `agendada`: publicada e `inicio_em` no futuro. Não aparece para o aluno e não trava.
- `ativa`: publicada e dentro de `inicio_em`/`termino_em`. Só ela trava o check-in.
- `encerrada`: término passou ou encerramento manual (`termino_em` vira "agora"). Fica no histórico se
  `manter_historico`.
- `cancelada`: some do app e do histórico. Leituras e respostas ficam nos relatórios.

Fuso: `America/Sao_Paulo` (`AcademyDateTime`, o mesmo das aulas). Obrigatória exige término.

## Público

Fixado na publicação (`comunicacao_destinatarios`, uma linha por aluno). "Incluir novos alunos" recalcula e
só acrescenta. Base: papel aluno ativo na academia. Nada de outro tenant entra, mesmo que um id venha no filtro.

- `todos`, `ativos` (matrícula ativa dentro do acesso).
- `segmento` (filtros somados com E): modalidades, planos (matrícula ativa), **turmas** = modalidade +
  horário com check-in nos últimos `dias_checkin` dias (padrão 30), já que turma é a aula do dia, e
  `inadimplentes` (parcela vencida em aberto).
- `alunos`: alunos escolhidos e/ou donos das matrículas informadas.

Depois de publicada, tipo, público, início e perguntas não mudam (a API mantém os gravados). Para mudar,
duplique.

## Trava do check-in

`PendenciaComunicacaoService::verificarPermissaoCheckin` responde **409**:

```json
{ "codigo": "COMUNICACOES_OBRIGATORIAS_PENDENTES", "mensagem": "Existem comunicações obrigatórias pendentes.",
  "checkin_permitido": false, "total_pendencias": 2,
  "pendencias": [{ "id": 15, "tipo": "comunicado", "titulo": "Novas regras", "acao_exigida": "confirmar_leitura" }] }
```

Onde vale:

- API v2: `POST /v2/mobile/checkin` (`MobileCheckinService::registrar`) e o check-in pela equipe
  (`POST /v2/mobile/checkin/manual`, mensagem "O aluno tem N…").
- API Slim (builds antigos): `POST /mobile/checkin`, `POST /mobile/checkin/manual`, `POST /checkin` e
  `POST /checkins/registrar` (`App\Services\ComunicacaoPendenciaService`, mesma consulta). Esses builds
  não têm a Central, então a mensagem pede para atualizar o app.

Concorrência: na v2 a verificação roda de novo **dentro da transação do INSERT**, com `lockForUpdate` nas
linhas do aluno. Uma publicação simultânea que inclua o aluno espera o commit, e não sobra janela entre
verificar e gravar. Na Slim a verificação é feita logo antes do INSERT, sem transação.

Central desligada para a academia (configuração remota `funcionalidade.comunicacao`, kill switch ou rollout)
não trava: o aluno não teria como resolver. A versão do app não entra nessa avaliação, para um build antigo
não escapar.

Confirmação: idempotente (`UPDATE … WHERE confirmada_em IS NULL`), sempre do aluno do token na academia do
token. O id de outra pessoa ou de outra academia responde 404. Vale depois de logout e login (está no banco).

## Enquetes

- O envio é validado por inteiro: perguntas obrigatórias, alternativas da própria pergunta, escolha única com
  uma opção, nota de 1 a 5, texto até 2.000 caracteres. Envio parcial não grava nada.
- `destinatario.concluida_em` (conclusão) fica separado de `enquete_respostas` (uma linha por pergunta, e
  por alternativa na múltipla).
- **Anônima:** a resposta não guarda aluno nem horário, e as linhas são embaralhadas. A participação fica só no
  destinatário. Alterar a resposta fica desligado, e os relatórios/CSV nunca trazem nomes.
- Reenviar sem "permitir alterar" responde `ja_respondida: true` sem duplicar.

## Rotas

Painel (`/v2/admin/comunicacoes`, permissões em `PermissaoRotas`):

| Rota | Permissão |
|---|---|
| `GET /comunicacoes`, `/opcoes`, `/{id}` | `comunicacoes.ver` |
| `POST /comunicacoes` (rascunho), `/{id}/duplicar`, `/previa-publico`, `/anexos` | `comunicacoes.criar` (+ `publicar` com `publicar: true`) |
| `PUT`/`DELETE /comunicacoes/{id}` | `comunicacoes.editar` |
| `POST /{id}/publicar`, `/{id}/incluir-destinatarios` | `comunicacoes.publicar` |
| `POST /{id}/encerrar`, `/{id}/cancelar` | `comunicacoes.encerrar` |
| `GET /indicadores`, `/{id}/destinatarios`, `/{id}/resultados` | `comunicacoes.relatorios` |
| `GET /{id}/respostas` (respostas por aluno) | `comunicacoes.respostas` |

App (`/v2/mobile/comunicacoes`, funcionalidade `comunicacao`): `GET /resumo` (sino, pendências e destaques),
`GET /?filtro=todas|nao_lidas|obrigatorias|enquetes`, `GET /{id}` (marca como visualizada),
`POST /{id}/confirmar`, `POST /{id}/responder` `{respostas: [{pergunta_id, alternativas?, valor?}]}`.

Anexos (imagem e PDF até 10 MB): `public/uploads/comunicacoes/{tenant}/`, servidos por
`GET /v2/uploads/comunicacoes/{tenant}/{arquivo}` com nome aleatório.

## Auditoria

`comunicacao_auditoria` registra: criada, editada (campos alterados), publicada (total), destinatarios_incluidos,
encerrada, cancelada, excluida, leitura_confirmada e enquete_respondida.

## Preparado para a fase 2

- **Multicanal:** `configuracoes.canais` (hoje `["app"]`). O app continua sendo a fonte oficial das
  confirmações. Envio por e-mail/push/WhatsApp entraria como canal extra, sem mudar a trava.
- **Responsável legal:** `destinatario.confirmada_por_usuario_id` já guarda quem confirmou (hoje, o próprio
  aluno).
- **Recorrentes:** "Duplicar" já copia tudo como rascunho. Uma recorrência automática criaria a cópia e a
  publicaria no agendador.

## Arquivos

- `database/migrations/2026_10_27_120000_central_comunicacao.php`
- `app/Support/Comunicacao.php` (vigência e status), `app/Services/Comunicacao/*`
- `app/Http/Controllers/Api/V2/Admin/ComunicacaoController.php`, `MobileComunicacaoController.php`
- `routes/v2/admin/comunicacoes.php`; rotas do app em `routes/api.php`
- Slim: `api/app/Services/ComunicacaoPendenciaService.php`
- Painel: `src/screens/comunicacao/*`, `src/services/comunicacaoService.js`, `src/theme/ck-comunicacao.css`
- App: `app/comunicacoes.tsx`, `app/comunicacao/[id].tsx`, `src/components/ComunicacaoGate.tsx`,
  `src/services/comunicacoes.ts`
- Testes: `tests/Feature/CentralComunicacaoTest.php` (os 16 cenários obrigatórios e mais as regras de
  anonimato, relatórios, kill switch e edição após publicar)

## Editor no painel

A modal de cadastro/edição segue `Nova-comunicacao.html`, com as etapas
**Conteúdo**, **Para quem** e **Quando e como**, prévia ao lado em telas largas e
resumo antes de publicar. Os campos são validados antes de continuar e, ao salvar,
a modal abre a etapa do primeiro erro. O alcance e a amostra de destinatários
continuam sendo calculados pela API; a amostra não representa a lista completa.
Os limites, os cinco tipos de resposta de enquete e as restrições de edição após
publicar permanecem os mesmos. Comunicações obrigatórias exigem término e
continuam forçando a exibição em destaque no aplicativo.
