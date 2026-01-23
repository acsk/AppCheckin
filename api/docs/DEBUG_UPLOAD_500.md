# 🔧 Como Corrigir o Erro 500 no Upload

## Passo 1: Verificar Permissões em Produção

```bash
ssh u304177849@appcheckin.com.br
cd /home/u304177849/domains/appcheckin.com.br/public_html/api

# Verificar se pasta de uploads existe e tem permissão
ls -la public/uploads/

# Se não existir, criar:
mkdir -p public/uploads/fotos
chmod -R 755 public/uploads/

# Verificar permissões do arquivo index.php
ls -la public/index.php
```

## Passo 2: Fazer Git Pull

```bash
# Ir para diretório da API
cd /home/u304177849/domains/appcheckin.com.br/public_html/api

# Pull da versão corrigida (sem código duplicado)
git pull origin main

# Verificar status
git status
```

## Passo 3: Testar Upload

```bash
# Gerar token JWT (ou use um existente)
TOKEN="seu_token_aqui"

# Testar upload
curl -X POST "https://api.appcheckin.com.br/mobile/perfil/foto" \
  -H "Authorization: Bearer $TOKEN" \
  -F "foto=@/caminho/para/imagem.jpg"

# Esperado:
# {
#   "success": true,
#   "message": "Foto de perfil atualizada com sucesso",
#   "data": {
#     "compressao": {
#       "reducao_percentual": 45.5
#     }
#   }
# }
```

## Passo 4: Verificar Logs se der Erro

```bash
# Ver últimas linhas do log (se existir)
tail -50 /home/u304177849/domains/appcheckin.com.br/public_html/api/logs/app.log

# Ou verificar via PHP
php -r "error_log('Teste de log'); echo 'Log testado';"
```

## ⚠️ Problemas Comuns

### Problema 1: "Directory not writable"
```bash
chmod -R 755 /home/u304177849/domains/appcheckin.com.br/public_html/api/public/uploads/
chmod -R 755 /home/u304177849/domains/appcheckin.com.br/public_html/api/logs/
```

### Problema 2: "Intervention\Image not found"
```bash
# Verificar se vendor está presente
ls -la /home/u304177849/domains/appcheckin.com.br/public_html/api/vendor/intervention/

# Se não estiver, fazer git pull de novo
git pull origin main
```

### Problema 3: "GD library not available"
- **Causa:** Servidor não tem GD habilitado
- **Solução:** Contatar Hostinger support para ativar extensão GD

### Problema 4: Arquivo muito grande
```bash
# Limite é 5MB - verificar tamanho
ls -lh seu_arquivo.jpg

# Se for > 5MB, redimensionar primeiro
```

## ✅ Checklist de Debug

- [ ] Pasta `public/uploads/fotos/` existe?
- [ ] Permissões 755 ou 777 na pasta?
- [ ] Git pull foi executado com sucesso?
- [ ] vendor/intervention/ existe?
- [ ] GD library está habilitada?
- [ ] Token JWT válido e com tenantId?
- [ ] Arquivo é imagem válida (JPEG/PNG/GIF/WebP)?
- [ ] Arquivo < 5MB?

## 🆘 Última Opção - Redeployar Tudo

Se nada funcionar, deletar API e redeployer:

```bash
ssh u304177849@appcheckin.com.br
cd /home/u304177849/domains/appcheckin.com.br/public_html

# Backup rápido (opcional)
tar -czf api_backup_$(date +%s).tar.gz api/

# Remover e redeployer
rm -rf api/
git clone https://github.com/acsk/AppCheckin.git api
cd api

# Pronto! Tudo sincronizado
```

---

**Última correção:** Commit `8bc357f`
**Data:** 23/01/2026
**Status:** Pronto para deploy
