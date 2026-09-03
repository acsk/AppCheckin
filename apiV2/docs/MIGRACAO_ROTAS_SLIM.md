# Migração de rotas Slim → apiV2

Gerado do diff entre `api/routes/api.php` (Slim, 346 rotas) e `php artisan route:list` (apiV2, 96 rotas).

**Faltando na apiV2: ~240 rotas** (snapshot inicial 254; parcial abaixo).

Ordem de migração (waves) e status ficam em `routes/v2/*.php`: cada módulo tem seu próprio
arquivo de rotas, incluído por `routes/api.php`. Módulo sem arquivo = ainda na Slim.

## Status da migração (atualizado)

| Módulo | Rotas v2 | Testes | Painel → v2 |
|--------|----------|--------|-------------|
| Usuários tenant + `/admin/admins` | ✅ `routes/v2/admin/usuarios.php`, `shared/tenant_usuarios.php` | ✅ | ✅ `apiRouting.js` |
| Professores | ✅ `routes/v2/admin/professores.php` | ✅ | ✅ `apiRouting.js` |
| Pagamentos / créditos / descontos | ✅ `routes/v2/admin/pagamentos_creditos.php` | ✅ 9 testes | ✅ `apiRouting.js` |
| Pacotes | ⚠️ `AdminPacoteRepository` criado, **sem controller/rota** | — | ainda Slim |
| Turmas / dias | ⚠️ `TurmaRepository` estendido, **sem controller/rota** | — | ainda Slim |
| Dashboard / assinaturas | ❌ subagentes falharam (`resource_exhausted`) | — | ainda Slim |
| Superadmin | ❌ subagentes falharam | — | ainda Slim |
| WOD / recordes | ❌ subagentes falharam | — | ainda Slim |
| Config / parâmetros / CEP | ❌ subagentes falharam | — | ainda Slim |

Subagentes paralelos esgotaram contexto; módulos marcados ⚠️/❌ precisam continuar **sequencialmente** (um módulo por vez).

## Rotas faltantes por módulo

### `/` — 1 rotas

- `GET /`

### `/admin/admins` — 1 rotas

- `GET /admin/admins`

### `/admin/alunos` — 3 rotas

- `GET /admin/alunos/{id}/creditos`
- `POST /admin/alunos/{id}/creditos`
- `GET /admin/alunos/{id}/creditos/saldo`

### `/admin/assinaturas` — 1 rotas

- `GET /admin/assinaturas`

### `/admin/checkins` — 2 rotas

- `POST /admin/checkins/registrar`
- `PATCH /admin/checkins/{id}/presenca`

### `/admin/contas-receber` — 5 rotas

- `GET /admin/contas-receber`
- `GET /admin/contas-receber/estatisticas`
- `GET /admin/contas-receber/relatorio`
- `POST /admin/contas-receber/{id}/baixa`
- `POST /admin/contas-receber/{id}/cancelar`

### `/admin/creditos` — 1 rotas

- `DELETE /admin/creditos/{id}`

### `/admin/dashboard` — 2 rotas

- `GET /admin/dashboard`
- `GET /admin/dashboard/cards`

### `/admin/dias` — 2 rotas

- `POST /admin/dias/desativar`
- `DELETE /admin/dias/{id}/horarios`

### `/admin/feature-flags` — 2 rotas

- `GET /admin/feature-flags`
- `GET /admin/feature-flags/{id}`

### `/admin/formas-pagamento-config` — 5 rotas

- `GET /admin/formas-pagamento-config`
- `POST /admin/formas-pagamento-config/calcular-parcelas`
- `POST /admin/formas-pagamento-config/calcular-taxas`
- `GET /admin/formas-pagamento-config/{id}`
- `PUT /admin/formas-pagamento-config/{id}`

### `/admin/matricula-descontos` — 3 rotas

- `DELETE /admin/matricula-descontos/{id}`
- `GET /admin/matricula-descontos/{id}`
- `PUT /admin/matricula-descontos/{id}`

### `/admin/matriculas` — 7 rotas

- `POST /admin/matriculas/pacote-contrato/{id}/baixa`
- `POST /admin/matriculas/{id}/cancelar-com-credito`
- `GET /admin/matriculas/{id}/descontos`
- `POST /admin/matriculas/{id}/descontos`
- `GET /admin/matriculas/{id}/pagamentos-plano`
- `POST /admin/matriculas/{id}/pagamentos-plano`
- `GET /admin/matriculas/{id}/simular-cancelamento`

### `/admin/pacote-contratos` — 2 rotas

- `GET /admin/pacote-contratos`
- `POST /admin/pacote-contratos/{id}/gerar-matriculas`

### `/admin/pacotes` — 7 rotas

- `GET /admin/pacotes`
- `POST /admin/pacotes`
- `DELETE /admin/pacotes/contratos/{id}`
- `POST /admin/pacotes/contratos/{id}/beneficiarios`
- `POST /admin/pacotes/contratos/{id}/confirmar-pagamento`
- `PUT /admin/pacotes/{id}`
- `POST /admin/pacotes/{id}/contratar`

### `/admin/pagamentos-plano` — 8 rotas

- `GET /admin/pagamentos-plano`
- `POST /admin/pagamentos-plano/marcar-atrasados`
- `GET /admin/pagamentos-plano/resumo`
- `DELETE /admin/pagamentos-plano/{id}`
- `GET /admin/pagamentos-plano/{id}`
- `PUT /admin/pagamentos-plano/{id}`
- `POST /admin/pagamentos-plano/{id}/confirmar`
- `DELETE /admin/pagamentos-plano/{id}/excluir`

### `/admin/parametros` — 9 rotas

- `GET /admin/parametros`
- `PUT /admin/parametros`
- `GET /admin/parametros/categorias`
- `GET /admin/parametros/pagamentos/resumo`
- `GET /admin/parametros/valor/{id}`
- `GET /admin/parametros/{id}`
- `PATCH /admin/parametros/{id}`
- `PUT /admin/parametros/{id}`
- `PATCH /admin/parametros/{id}/toggle`

### `/admin/payment-credentials` — 3 rotas

- `GET /admin/payment-credentials`
- `POST /admin/payment-credentials`
- `POST /admin/payment-credentials/test`

### `/admin/professores` — 8 rotas

- `GET /admin/professores`
- `POST /admin/professores`
- `GET /admin/professores/cpf/{id}`
- `GET /admin/professores/global/cpf/{id}`
- `DELETE /admin/professores/{id}`
- `GET /admin/professores/{id}`
- `PUT /admin/professores/{id}`
- `GET /admin/professores/{id}/turmas`

### `/admin/recordes` — 11 rotas

- `GET /admin/recordes`
- `POST /admin/recordes`
- `GET /admin/recordes/definicoes`
- `POST /admin/recordes/definicoes`
- `DELETE /admin/recordes/definicoes/{id}`
- `GET /admin/recordes/definicoes/{id}`
- `PUT /admin/recordes/definicoes/{id}`
- `GET /admin/recordes/ranking/{id}`
- `DELETE /admin/recordes/{id}`
- `GET /admin/recordes/{id}`
- `PUT /admin/recordes/{id}`

### `/admin/relatorios` — 1 rotas

- `GET /admin/relatorios/planos-ciclos`

### `/admin/turmas` — 15 rotas

- `GET /admin/turmas`
- `POST /admin/turmas`
- `POST /admin/turmas/desativar`
- `GET /admin/turmas/dia/{id}`
- `POST /admin/turmas/replicar`
- `POST /admin/turmas/replicar-semana`
- `DELETE /admin/turmas/{id}`
- `GET /admin/turmas/{id}`
- `PUT /admin/turmas/{id}`
- `POST /admin/turmas/{id}/bloquear-checkin`
- `POST /admin/turmas/{id}/desbloquear-checkin`
- `DELETE /admin/turmas/{id}/permanente`
- `GET /admin/turmas/{id}/presencas`
- `POST /admin/turmas/{id}/presencas/lote`
- `GET /admin/turmas/{id}/vagas`

### `/admin/usuarios` — 1 rotas

- `GET /admin/usuarios/{id}/pagamentos-plano`

### `/admin/wods` — 22 rotas

- `GET /admin/wods`
- `POST /admin/wods`
- `GET /admin/wods/buscar`
- `POST /admin/wods/completo`
- `GET /admin/wods/modalidades`
- `DELETE /admin/wods/{id}`
- `GET /admin/wods/{id}`
- `PUT /admin/wods/{id}`
- `PATCH /admin/wods/{id}/archive`
- `GET /admin/wods/{id}/blocos`
- `POST /admin/wods/{id}/blocos`
- `DELETE /admin/wods/{id}/blocos/{id}`
- `PUT /admin/wods/{id}/blocos/{id}`
- `PATCH /admin/wods/{id}/publish`
- `GET /admin/wods/{id}/resultados`
- `POST /admin/wods/{id}/resultados`
- `DELETE /admin/wods/{id}/resultados/{id}`
- `PUT /admin/wods/{id}/resultados/{id}`
- `GET /admin/wods/{id}/variacoes`
- `POST /admin/wods/{id}/variacoes`
- `DELETE /admin/wods/{id}/variacoes/{id}`
- `PUT /admin/wods/{id}/variacoes/{id}`

### `/api` — 10 rotas

- `POST /api/webhooks/mercadopago`
- `GET /api/webhooks/mercadopago/cobrancas`
- `GET /api/webhooks/mercadopago/list`
- `GET /api/webhooks/mercadopago/payment/{id}`
- `POST /api/webhooks/mercadopago/payment/{id}/reprocess`
- `POST /api/webhooks/mercadopago/recuperar-assinatura`
- `POST /api/webhooks/mercadopago/reprocess/{id}`
- `GET /api/webhooks/mercadopago/show/{id}`
- `GET /api/webhooks/mercadopago/test`
- `POST /api/webhooks/mercadopago/v2`

### `/auth` — 3 rotas

- `POST /auth/diagnose`
- `POST /auth/register-mobile`
- `GET /auth/tenants-public`

### `/cep` — 1 rotas

- `GET /cep/{id}`

### `/checkin` — 3 rotas

- `POST /checkin`
- `DELETE /checkin/{id}`
- `DELETE /checkin/{id}/desfazer`

### `/config` — 3 rotas

- `GET /config/formas-pagamento`
- `GET /config/formas-pagamento-ativas`
- `GET /config/status-conta`

### `/dias` — 6 rotas

- `GET /dias`
- `GET /dias/horarios`
- `GET /dias/periodo`
- `GET /dias/por-data`
- `GET /dias/proximos`
- `GET /dias/{id}/horarios`

### `/feature-flags` — 1 rotas

- `GET /feature-flags/{id}`

### `/formas-pagamento` — 1 rotas

- `GET /formas-pagamento`

### `/me` — 2 rotas

- `PUT /me`
- `GET /me/checkins`

### `/mobile/alunos` — 2 rotas

- `GET /mobile/alunos/buscar`
- `GET /mobile/alunos/{id}/resumo-financeiro`

### `/mobile/assinatura` — 1 rotas

- `POST /mobile/assinatura/criar`

### `/mobile/checkin` — 2 rotas

- `POST /mobile/checkin/manual`
- `DELETE /mobile/checkin/manual/{id}/desfazer`

### `/mobile/pacotes` — 3 rotas

- `GET /mobile/pacotes/contratos`
- `POST /mobile/pacotes/contratos/{id}/pagar`
- `GET /mobile/pacotes/pendentes`

### `/mobile/perfil` — 2 rotas

- `GET /mobile/perfil/foto`
- `POST /mobile/perfil/foto`

### `/mobile/recordes` — 7 rotas

- `POST /mobile/recordes`
- `GET /mobile/recordes/academia`
- `GET /mobile/recordes/definicoes`
- `GET /mobile/recordes/meus`
- `GET /mobile/recordes/ranking/{id}`
- `DELETE /mobile/recordes/{id}`
- `PUT /mobile/recordes/{id}`

### `/mobile/turma` — 5 rotas

- `POST /mobile/turma/{id}/bloquear-checkin`
- `POST /mobile/turma/{id}/confirmar-presenca`
- `POST /mobile/turma/{id}/desbloquear-checkin`
- `GET /mobile/turma/{id}/detalhes`
- `GET /mobile/turma/{id}/participantes`

### `/mobile/turmas` — 1 rotas

- `GET /mobile/turmas`

### `/ok` — 1 rotas

- `GET /ok`

### `/php-test` — 1 rotas

- `GET /php-test`

### `/professor` — 5 rotas

- `GET /professor/dashboard`
- `GET /professor/turmas/pendentes`
- `GET /professor/turmas/{id}/checkins`
- `POST /professor/turmas/{id}/confirmar-presenca`
- `DELETE /professor/turmas/{id}/faltantes`

### `/signin` — 1 rotas

- `POST /signin`

### `/status` — 4 rotas

- `GET /status`
- `GET /status/{id}`
- `GET /status/{id}/codigo/{id}`
- `GET /status/{id}/{id}`

### `/superadmin/academias` — 14 rotas

- `GET /superadmin/academias`
- `POST /superadmin/academias`
- `DELETE /superadmin/academias/{id}`
- `GET /superadmin/academias/{id}`
- `PUT /superadmin/academias/{id}`
- `POST /superadmin/academias/{id}/admin`
- `GET /superadmin/academias/{id}/admins`
- `DELETE /superadmin/academias/{id}/admins/{id}`
- `PUT /superadmin/academias/{id}/admins/{id}`
- `POST /superadmin/academias/{id}/admins/{id}/reativar`
- `GET /superadmin/academias/{id}/contrato-ativo`
- `GET /superadmin/academias/{id}/contratos`
- `POST /superadmin/academias/{id}/contratos`
- `POST /superadmin/academias/{id}/trocar-plano`

### `/superadmin/contratos` — 8 rotas

- `GET /superadmin/contratos`
- `GET /superadmin/contratos/proximos-vencimento`
- `GET /superadmin/contratos/vencidos`
- `DELETE /superadmin/contratos/{id}`
- `GET /superadmin/contratos/{id}`
- `GET /superadmin/contratos/{id}/pagamentos-contrato`
- `POST /superadmin/contratos/{id}/pagamentos-contrato`
- `POST /superadmin/contratos/{id}/renovar`

### `/superadmin/env` — 1 rotas

- `GET /superadmin/env`

### `/superadmin/pagamentos-contrato` — 5 rotas

- `GET /superadmin/pagamentos-contrato`
- `POST /superadmin/pagamentos-contrato/marcar-atrasados`
- `GET /superadmin/pagamentos-contrato/resumo`
- `DELETE /superadmin/pagamentos-contrato/{id}`
- `POST /superadmin/pagamentos-contrato/{id}/confirmar`

### `/superadmin/papeis` — 1 rotas

- `GET /superadmin/papeis`

### `/superadmin/planos` — 1 rotas

- `GET /superadmin/planos`

### `/superadmin/planos-sistema` — 8 rotas

- `GET /superadmin/planos-sistema`
- `POST /superadmin/planos-sistema`
- `GET /superadmin/planos-sistema/disponiveis`
- `DELETE /superadmin/planos-sistema/{id}`
- `GET /superadmin/planos-sistema/{id}`
- `PUT /superadmin/planos-sistema/{id}`
- `GET /superadmin/planos-sistema/{id}/academias`
- `POST /superadmin/planos-sistema/{id}/marcar-historico`

### `/superadmin/usuarios` — 4 rotas

- `GET /superadmin/usuarios`
- `DELETE /superadmin/usuarios/{id}`
- `GET /superadmin/usuarios/{id}`
- `PUT /superadmin/usuarios/{id}`

### `/tenant/usuarios` — 7 rotas

- `GET /tenant/usuarios`
- `POST /tenant/usuarios`
- `POST /tenant/usuarios/associar`
- `GET /tenant/usuarios/buscar-cpf/{id}`
- `DELETE /tenant/usuarios/{id}`
- `GET /tenant/usuarios/{id}`
- `PUT /tenant/usuarios/{id}`

### `/turmas` — 3 rotas

- `GET /turmas`
- `GET /turmas/dia/{id}`
- `GET /turmas/{id}/vagas`

### `/uploads` — 1 rotas

- `GET /uploads/fotos/{id}`

### `/usuarios` — 1 rotas

- `GET /usuarios/{id}/estatisticas`

### `/v1` — 13 rotas

- `POST /v1/auth/login`
- `GET /v1/bootstrap`
- `GET /v1/notificacoes`
- `POST /v1/notificacoes`
- `POST /v1/notificacoes/read-all`
- `GET /v1/notificacoes/unread`
- `GET /v1/notificacoes/{id}`
- `POST /v1/notificacoes/{id}/read`
- `GET /v1/ok`
- `GET /v1/ping`
- `GET /v1/profile`
- `GET /v1/status`
- `GET /v1/tenants`

