# 🎯 Resumo: Como Usar os Jobs de Limpeza de Matrículas

## 🚀 Usar Agora

### Opção 1: Simular (Ver o que será feito, sem alterar)
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run
```

### Opção 2: Executar de Verdade
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
```

### Opção 3: Apenas para Tenant 4
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --tenant=4
```

---

## 📊 O que o Job Faz

### Analisando a Tela que você mostrou:

**CAROLINA FERREIRA** tem 3 matrículas:
1. **2x por Semana - CrossFit** (R$ 130,00) | 11/01 - 10/02 | Status: **Pendente** ✅ VIGENTE
2. 3x por semana - Natação (R$ 150,00) | 09/01 - 08/02 | Status: Pendente
3. 2x por Semana - Natação (R$ 120,00) | 09/01 - 08/02 | Status: Pendente ❌ DUPLICADA

**Ação do Job:**
- ✅ Mantém: #1 (CrossFit) - Começa hoje (11/01)
- ✅ Mantém: #2 (Natação 3x) - Mais recente entre as duas de Natação
- ❌ Cancela: #3 (Natação 2x) - Duplicada

---

## 📅 Configurar para Executar Automaticamente

### Via Cron (Linux/Mac)

```bash
# Editar crontab
crontab -e

# Adicionar uma destas linhas:

# Executar todos os dias às 5 da manhã
0 5 * * * php /path/to/jobs/limpar_matriculas_duplicadas.php >> /var/log/limpar_matriculas.log 2>&1

# Executar a cada 6 horas
0 */6 * * * php /path/to/jobs/limpar_matriculas_duplicadas.php >> /var/log/limpar_matriculas.log 2>&1

# Executar a cada 12 horas
0 */12 * * * php /path/to/jobs/limpar_matriculas_duplicadas.php >> /var/log/limpar_matriculas.log 2>&1
```

### Via Docker Cron

Se usar Docker, adicione ao Dockerfile ou docker-compose.yml:
```dockerfile
# Instalar cron
RUN apt-get install -y cron

# Copiar crontab
COPY crontab /etc/cron.d/app-cron

# Dar permissão
RUN chmod 0644 /etc/cron.d/app-cron

# Registrar
RUN crontab /etc/cron.d/app-cron
```

---

## 📂 Arquivos Criados/Modificados

| Arquivo | O que é |
|---------|---------|
| `jobs/limpar_matriculas_duplicadas.php` | 🆕 Novo job de limpeza |
| `JOB_LIMPAR_MATRICULAS.md` | 📝 Documentação completa |

---

## ✅ Checklist

- [x] Job criado
- [x] Lógica implementada
- [x] Validação de sintaxe
- [x] Documentação escrita
- [ ] **Testar em dry-run**
- [ ] **Validar resultados no admin**
- [ ] **Configurar cron para automático**

---

## 🆘 Dúvidas?

**O que cancela?**
- Matrículas duplicadas (múltiplas da mesma modalidade)
- Matrículas com data vencida

**O que mantém?**
- A matrícula mais recente e vigente de cada modalidade por usuário

**É seguro?**
- Sim! Use `--dry-run` primeiro para testar

**Precisa fazer cron?**
- Não é obrigatório, mas recomendado para automático

---

**Próximo passo:** Execute em dry-run para validar! 🚀
