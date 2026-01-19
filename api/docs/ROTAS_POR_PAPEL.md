# Rotas por Papel (Role)

## Resumo

Rotas organizadas por papel de usuário. Cada seção mostra os endpoints disponíveis para cada tipo de usuário.

---

## 🔐 Rotas Super Admin (role_id = 3)

**Acesso:** `/superadmin/*`  
**Middleware:** SuperAdminMiddleware + AuthMiddleware

### Gerenciamento de Academias
```
GET    /superadmin/academias              - Listar todas as academias
GET    /superadmin/academias/{id}         - Buscar academia específica
POST   /superadmin/academias              - Criar nova academia
PUT    /superadmin/academias/{id}         - Atualizar academia
DELETE /superadmin/academias/{id}         - Excluir academia
POST   /superadmin/academias/{tenantId}/admin - Criar admin para academia
```

### Planos do Sistema
```
GET    /superadmin/planos                 - Listar planos de alunos (todas academias)
GET    /superadmin/planos-sistema         - Listar planos do sistema
GET    /superadmin/planos-sistema/disponiveis
GET    /superadmin/planos-sistema/{id}    - Buscar plano específico
GET    /superadmin/planos-sistema/{id}/academias
POST   /superadmin/planos-sistema         - Criar plano do sistema
PUT    /superadmin/planos-sistema/{id}    - Atualizar plano
POST   /superadmin/planos-sistema/{id}/marcar-historico
DELETE /superadmin/planos-sistema/{id}    - Deletar plano
```

### Contratos (Academia + Plano Sistema)
```
GET    /superadmin/contratos              - Listar contratos
GET    /superadmin/contratos/proximos-vencimento
GET    /superadmin/contratos/vencidos
GET    /superadmin/contratos/{id}         - Buscar contrato
GET    /superadmin/academias/{tenantId}/contratos
GET    /superadmin/academias/{tenantId}/contrato-ativo
POST   /superadmin/academias/{tenantId}/contratos
POST   /superadmin/academias/{tenantId}/trocar-plano
POST   /superadmin/contratos/{id}/renovar
DELETE /superadmin/contratos/{id}         - Cancelar contrato
```

### Pagamentos de Contratos
```
GET    /superadmin/pagamentos             - Listar pagamentos
GET    /superadmin/pagamentos/resumo
GET    /superadmin/contratos/{id}/pagamentos
POST   /superadmin/contratos/{id}/pagamentos
POST   /superadmin/pagamentos/{id}/confirmar
DELETE /superadmin/pagamentos/{id}        - Cancelar pagamento
POST   /superadmin/pagamentos/marcar-atrasados
```

### Usuários (Todos os Tenants)
```
GET    /superadmin/usuarios               - Listar usuários de todos os tenants
GET    /superadmin/usuarios/{id}          - Buscar usuário
PUT    /superadmin/usuarios/{id}          - Atualizar usuário
DELETE /superadmin/usuarios/{id}          - Excluir usuário
```

---

## 👤 Rotas Admin (role_id = 2)

**Acesso:** `/admin/*`  
**Middleware:** AdminMiddleware + AuthMiddleware

### Dashboard
```
GET    /admin/dashboard                                - Todos os contadores
GET    /admin/dashboard/turmas-por-modalidade          - Turmas agrupadas por modalidade
GET    /admin/dashboard/alunos-por-modalidade          - Alunos por modalidade
GET    /admin/dashboard/checkins-últimos-7-dias        - Checkins da última semana
```

### Gestão de Alunos
```
GET    /admin/alunos                      - Listar alunos
GET    /admin/alunos/basico               - Listagem básica
GET    /admin/alunos/{id}                 - Buscar aluno
GET    /admin/alunos/{id}/historico-planos
POST   /admin/alunos                      - Criar aluno
PUT    /admin/alunos/{id}                 - Atualizar aluno
DELETE /admin/alunos/{id}                 - Desativar aluno
```

### Gestão de Planos
```
GET    /admin/planos/{id}                 - Buscar plano
POST   /admin/planos                      - Criar plano
PUT    /admin/planos/{id}                 - Atualizar plano
DELETE /admin/planos/{id}                 - Deletar plano
```

### Planejamento de Horários
```
GET    /admin/planejamentos               - Listar planejamentos
GET    /admin/planejamentos/{id}          - Buscar planejamento
POST   /admin/planejamentos               - Criar planejamento
PUT    /admin/planejamentos/{id}          - Atualizar planejamento
DELETE /admin/planejamentos/{id}          - Deletar planejamento
POST   /admin/planejamentos/{id}/gerar-horarios
```

### Check-ins
```
POST   /admin/checkins/registrar          - Registrar check-in para aluno
```

### Contas a Receber
```
GET    /admin/contas-receber              - Listar contas
GET    /admin/contas-receber/relatorio    - Relatório
GET    /admin/contas-receber/estatisticas - Estatísticas
POST   /admin/contas-receber/{id}/baixa   - Marcar como pago
POST   /admin/contas-receber/{id}/cancelar
```

### Matrículas
```
POST   /admin/matriculas                  - Criar matrícula
GET    /admin/matriculas                  - Listar matrículas
GET    /admin/matriculas/{id}             - Buscar matrícula
GET    /admin/matriculas/{id}/pagamentos  - Pagamentos da matrícula
POST   /admin/matriculas/{id}/cancelar    - Cancelar matrícula
POST   /admin/matriculas/contas/{id}/baixa
```

### Pagamentos de Planos/Matrículas
```
GET    /admin/pagamentos-plano            - Listar pagamentos
GET    /admin/pagamentos-plano/resumo
GET    /admin/pagamentos-plano/{id}       - Buscar pagamento
GET    /admin/matriculas/{id}/pagamentos-plano
GET    /admin/usuarios/{id}/pagamentos-plano
POST   /admin/matriculas/{id}/pagamentos-plano
POST   /admin/pagamentos-plano/{id}/confirmar
DELETE /admin/pagamentos-plano/{id}       - Cancelar pagamento
POST   /admin/pagamentos-plano/marcar-atrasados
```

### Modalidades
```
GET    /admin/modalidades                 - Listar
GET    /admin/modalidades/{id}            - Buscar
POST   /admin/modalidades                 - Criar
PUT    /admin/modalidades/{id}            - Atualizar
DELETE /admin/modalidades/{id}            - Deletar
```

### Professores
```
GET    /admin/professores                 - Listar
GET    /admin/professores/{id}            - Buscar
POST   /admin/professores                 - Criar
PUT    /admin/professores/{id}            - Atualizar
DELETE /admin/professores/{id}            - Deletar
```

### Turmas/Aulas
```
GET    /admin/turmas                      - Listar turmas
GET    /admin/turmas/dia/{diaId}          - Listar por dia
GET    /admin/turmas/{id}                 - Buscar turma
GET    /admin/turmas/{id}/vagas           - Verificar vagas
GET    /admin/professores/{professorId}/turmas
POST   /admin/turmas                      - Criar turma
POST   /admin/turmas/replicar             - Replicar turmas (com filtro opcional por modalidade)
POST   /admin/turmas/desativar            - Desativar turmas
PUT    /admin/turmas/{id}                 - Atualizar turma
DELETE /admin/turmas/{id}                 - Deletar turma
DELETE /admin/dias/{diaId}/horarios       - Deletar todos os horários de um dia
```

### Dias
```
GET    /admin/dias                        - Listar dias
POST   /admin/dias/desativar              - Desativar dias (feriados, etc)
```

### Formas de Pagamento
```
GET    /admin/formas-pagamento-config     - Listar
GET    /admin/formas-pagamento-config/{id} - Buscar
PUT    /admin/formas-pagamento-config/{id} - Atualizar
POST   /admin/formas-pagamento-config/calcular-taxas
POST   /admin/formas-pagamento-config/calcular-parcelas
```

### Feature Flags
```
GET    /admin/feature-flags               - Listar
GET    /admin/feature-flags/{key}         - Buscar
```

---

## 🔓 Rotas Públicas (Autenticado)

**Acesso:** `/` ou rotas sem prefixo  
**Middleware:** AuthMiddleware

### Usuário
```
GET    /me                                - Perfil do usuário autenticado
PUT    /me                                - Atualizar perfil
GET    /usuarios/{id}/estatisticas        - Estatísticas do usuário
```

### Dias Disponíveis
```
GET    /dias                              - Listar dias
GET    /dias/proximos                     - Próximos dias
GET    /dias/horarios                     - Horários por data
GET    /dias/{id}/horarios                - Horários de um dia
```

### Check-ins
```
POST   /checkin                           - Registrar check-in
GET    /me/checkins                       - Meus check-ins
DELETE /checkin/{id}                      - Cancelar check-in
DELETE /checkin/{id}/desfazer              - Desfazer check-in
```

### Turmas
```
GET    /turmas                            - Listar turmas
GET    /turmas/dia/{diaId}                - Turmas de um dia
GET    /turmas/{id}/vagas                 - Vagas disponíveis
```

### Planos
```
GET    /planos                            - Listar planos
GET    /planos/{id}                       - Buscar plano
```

### Configurações
```
GET    /config/formas-pagamento           - Formas de pagamento
GET    /config/formas-pagamento-ativas    - Formas ativas
GET    /config/status-conta               - Status de conta
```

---

## 📱 Rotas Mobile (role_id = 1 - Aluno)

**Acesso:** `/mobile/*`  
**Middleware:** AuthMiddleware

```
GET    /mobile/perfil                     - Perfil completo
GET    /mobile/tenants                    - Tenants do usuário
GET    /mobile/planos                     - Planos disponíveis
GET    /mobile/matriculas/{matriculaId}   - Detalhes matrícula
POST   /mobile/checkin                    - Registrar check-in
GET    /mobile/checkins                   - Histórico
GET    /mobile/turma/{turmaId}/participantes
GET    /mobile/turma/{turmaId}/detalhes
GET    /mobile/horarios                   - Hoje
GET    /mobile/horarios/proximos
GET    /mobile/horarios/{diaId}
GET    /mobile/horarios-disponiveis
```

---

## Tabela Resumida

| Funcionalidade | Super Admin | Admin | Aluno | Público |
|---|---|---|---|---|
| Dashboard | ❌ | ✅ | ❌ | ❌ |
| Gerenciar Academias | ✅ | ❌ | ❌ | ❌ |
| Gerenciar Planos Sistema | ✅ | ❌ | ❌ | ❌ |
| Gerenciar Contratos | ✅ | ❌ | ❌ | ❌ |
| Gerenciar Alunos | ❌ | ✅ | ❌ | ❌ |
| Gerenciar Professores | ❌ | ✅ | ❌ | ❌ |
| Gerenciar Turmas | ❌ | ✅ | ❌ | ❌ |
| Registrar Check-in | ❌ | ✅ (para outros) | ✅ (próprio) | ❌ |
| Ver Planos | ❌ | ✅ | ✅ | ❌ |
| Ver Turmas | ❌ | ✅ | ✅ | ❌ |
| Ver Horários | ❌ | ✅ | ✅ | ❌ |

---

## Notas Importantes

1. **Tenant**: Admin tem acesso apenas aos dados de seu tenant (filtrado automaticamente)
2. **Super Admin**: Tem acesso a dados de todas as academias
3. **Autenticação**: Todos os endpoints requerem Bearer token no header `Authorization`
4. **CORS**: Verificar configuração CORS para requisições cross-origin
5. **Rate Limiting**: Alguns endpoints podem ter rate limiting (confirmar com backend)
