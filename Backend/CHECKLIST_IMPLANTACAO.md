# ✅ Checklist de Implantação

## 🎯 Objetivo
Garantir que todos os componentes do sistema de check-in e limpeza de matrículas estão corretos e prontos para produção.

---

## 📋 1. Verificação de Endpoints (Mobile)

- [ ] **POST `/mobile/checkin`**
  - Teste com dados válidos
  - Comando: `curl -X POST http://localhost:8000/mobile/checkin -H "Authorization: Bearer {token}" -d '{"turma_id":15,"modalidade_id":2}'`

- [ ] **GET `/mobile/horarios-disponiveis`**
  - Verificar se mostra count correto de alunos
  - Comando: `curl http://localhost:8000/mobile/horarios-disponiveis -H "Authorization: Bearer {token}"`

- [ ] **GET `/mobile/turma/{turmaId}/participantes`**
  - Testar com ID válido (ex: 15)
  - Comando: `curl http://localhost:8000/mobile/turma/15/participantes -H "Authorization: Bearer {token}"`

- [ ] **GET `/mobile/turma/{turmaId}/detalhes`**
  - Testar com ID válido (ex: 15)
  - Comando: `curl http://localhost:8000/mobile/turma/15/detalhes -H "Authorization: Bearer {token}"`

---

## 🧹 2. Verificação do Job

- [ ] **Job existe**
  - Caminho: `/var/www/html/jobs/limpar_matriculas_duplicadas.php`
  - Comando: `test -f /var/www/html/jobs/limpar_matriculas_duplicadas.php && echo "✅ Existe"`

- [ ] **Job executa sem erros (dry-run)**
  - Comando: `docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run`
  - Esperado: "Processando X tenant(s)..."

- [ ] **Job identifica matrículas corretamente**
  - Deve mostrar: "Matrículas canceladas: X"
  - Deve diferenciar com/sem pagamento

---

## 🗄️ 3. Verificação do Banco de Dados

- [ ] **Coluna `turma_id` existe em `matriculas`**
  - Comando: `docker exec appcheckin_db mysql -u root -psenha123 appcheckin -e "DESCRIBE matriculas;" | grep turma_id`
  - Esperado: `turma_id` com tipo `int`

- [ ] **Tabela `pagamentos_plano` tem dados**
  - Comando: `docker exec appcheckin_db mysql -u root -psenha123 appcheckin -e "SELECT COUNT(*) as total FROM pagamentos_plano;"`
  - Esperado: COUNT > 0

- [ ] **Relacionamento `checkins` → `turmas` existe**
  - Comando: `docker exec appcheckin_db mysql -u root -psenha123 appcheckin -e "DESCRIBE checkins;" | grep -E "turma_id|horario_id"`
  - Esperado: Ambas as colunas existem

---

## 📚 4. Verificação de Documentação

- [ ] **[RESUMO_FINAL.md](RESUMO_FINAL.md)** exists e completo
- [ ] **[JOB_LIMPEZA_MATRICULAS_DOCUMENTACAO.md](JOB_LIMPEZA_MATRICULAS_DOCUMENTACAO.md)** existe e completo
- [ ] **[configurar_crontab.sh](configurar_crontab.sh)** existe e é executável

---

## 🚀 5. Configuração para Produção

### Opção A: Execução Manual (Recomendado para começar)

- [ ] Testar job manualmente
  ```bash
  docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run
  ```

- [ ] Se tudo ok, executar de verdade
  ```bash
  docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
  ```

### Opção B: Automação com Crontab

- [ ] Configurar crontab
  ```bash
  bash /Users/andrecabral/Projetos/AppCheckin/Backend/configurar_crontab.sh
  ```

- [ ] Verificar se foi adicionado
  ```bash
  crontab -l | grep limpar_matriculas_duplicadas
  ```

- [ ] Criar diretório de logs (se não existir)
  ```bash
  mkdir -p /var/log/appcheck/
  touch /var/log/appcheck/limpar_matriculas.log
  ```

### Opção C: Docker Compose (Se houver)

- [ ] Adicionar em docker-compose.yml (opcional)
  ```yaml
  jobs:
    image: your-php-image
    volumes:
      - /var/log/appcheck:/var/log/appcheck
    entrypoint: >
      sh -c "
      while true; do
        php /var/www/html/jobs/limpar_matriculas_duplicadas.php >> /var/log/appcheck/limpar_matriculas.log 2>&1
        sleep 86400  # 24 horas
      done
      "
  ```

---

## 🧪 6. Testes Finais

### Teste 1: Check-in Básico
```bash
# Variáveis
USUARIO_ID=11
TURMA_ID=15
MODALIDADE_ID=2
TOKEN="seu_jwt_token_aqui"

# Request
curl -X POST http://localhost:8000/mobile/checkin \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"turma_id\":$TURMA_ID,\"modalidade_id\":$MODALIDADE_ID}"
```

**Esperado:** Status 200, `"success": true`

---

### Teste 2: Job em Dry-Run
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run
```

**Esperado:**
```
========================================
LIMPEZA DE MATRÍCULAS DUPLICADAS
...
✅ CONCLUÍDO
Usuários processados: X
Matrículas canceladas: 0
⚠️ Modo DRY-RUN: Nenhuma alteração foi feita
========================================
```

---

### Teste 3: Monitorar Job em Crontab
```bash
# Aguarde até 5 horas da manhã do próximo dia
# Ou rode manualmente para testar:
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php

# Ver logs
tail -f /var/log/appcheck/limpar_matriculas.log
```

---

## 🔍 7. Troubleshooting

### Problema: Job não executa
```bash
# Verificar permissões
ls -la /var/www/html/jobs/limpar_matriculas_duplicadas.php

# Testar manualmente
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
```

### Problema: Crontab não executa
```bash
# Verificar se crontab está rodando
ps aux | grep cron

# Ver logs do crontab
# Linux: /var/log/syslog ou /var/log/cron
# macOS: log stream --process cron

# Testar comando manualmente
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
```

### Problema: Erro "Cannot add or update a child row"
- Verificar se `turma_id` existe em `matriculas`
- Verificar se `plano_id` referenciado existe em `planos`

### Problema: Job cancela matrículas erradas
- Rodar em dry-run primeiro: `--dry-run`
- Verificar logs em `/var/log/appcheck/limpar_matriculas.log`
- Verificar tabela `pagamentos_plano` tem dados corretos

---

## 📊 8. Monitoramento Pós-Implantação

### Acompanhamento Inicial (Primeiro Mês)

- [ ] **Semana 1:** Executar job manualmente diariamente
  - Monitorar matrículas sendo canceladas
  - Verificar se clientes reclamam

- [ ] **Semana 2-4:** Se tudo ok, adicionar ao crontab
  - Executar uma vez por dia automaticamente
  - Monitorar logs

- [ ] **Mês 2+:** Se estável, deixar rodando
  - Verificar logs 1x/semana
  - Criar alertas se necessário

### Métricas a Monitorar

- Quantas matrículas foram canceladas por semana
- Quais usuarios foram afetados
- Se há reclamações de clientes
- Se o job roda rápido (< 1s esperado)

### Criar Alertas (Opcional)

```bash
# Adicionar email quando cancelar matrículas
# Modificar limpar_matriculas_duplicadas.php linha ~150:
if ($matriculasCanceladas > 0) {
    mail('admin@appcheckin.com', 
         "⚠️ Job Limpeza: $matriculasCanceladas matrículas canceladas",
         "Veja logs em /var/log/appcheck/limpar_matriculas.log");
}
```

---

## ✅ Status Final

### Antes de Ir para Produção

Certifique-se de marcar TODOS estes checkboxes:

- [ ] Todos os 4 endpoints foram testados
- [ ] Job executa sem erros
- [ ] Banco de dados tem as colunas necessárias
- [ ] Documentação foi lida e entendida
- [ ] Crontab foi configurado (ou manual agendado)
- [ ] Logs estão sendo salvos em `/var/log/appcheck/`
- [ ] Alguém acompanhará primeira semana
- [ ] Há plano de rollback se algo der errado

---

## 🎉 Pronto!

Se todos os checkboxes estão marcados, o sistema está **PRONTO PARA PRODUÇÃO**. 

Boa sorte! 🚀

---

**Data:** 11 de janeiro de 2026  
**Versão:** 1.0  
**Status:** ✅ PRONTO
