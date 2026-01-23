# 🚀 Deploy para Produção

## Opção 1: SSH (Recomendado - mais rápido)

```bash
ssh u304177849@appcheckin.com.br

# Ir para o diretório da API
cd /home/u304177849/domains/appcheckin.com.br/public_html/api

# Fazer git pull (inclui vendor com todas as dependências)
git pull origin main

# Pronto! Sem necessidade de composer - vendor já vem no Git
```

## Opção 2: Sem SSH (via SFTP/File Manager do Hostinger)

1. **Conectar via SFTP**
   - Host: appcheckin.com.br
   - Usuário: u304177849
   - Pasta: /home/u304177849/domains/appcheckin.com.br/public_html/api

2. **Deletar pasta antiga (opcional)**
   - Remover: `/api` inteira

3. **Upload da pasta nova**
   - Fazer upload de toda a pasta `/api` localmente (leva ~2-3 minutos)

4. **Pronto!**

## ✅ Verificação Pós-Deploy

```bash
# Testar upload de foto
curl -X POST https://api.appcheckin.com.br/mobile/perfil/foto \
  -H "Authorization: Bearer seu_token_aqui" \
  -F "foto=@/caminho/para/imagem.jpg"

# Esperado: 
# {
#   "success": true,
#   "message": "Foto de perfil atualizada com sucesso",
#   "data": {
#     "compressao": {
#       "reducao_percentual": 45.5,
#       ...
#     }
#   }
# }
```

## 📦 O que está no vendor

- ✅ `intervention/image` v3.11.6 - Compressão de imagens
- ✅ `intervention/gif` v4.2.4 - Suporte para GIF animados  
- ✅ `sendgrid/sendgrid` v8.1 - Envio de emails
- ✅ `firebase/php-jwt` v6.10 - Autenticação JWT
- ✅ Todas as dependências sub-dependentes

## 🔄 Sincronização de Código

```bash
# Se quiser sincronizar tudo (código + vendor)
git pull origin main

# Se houver conflitos
git status
git reset --hard origin/main
git pull origin main

# Limpar cache (se houver problemas)
rm -rf vendor/autoload.php
```

## ⚠️ IMPORTANTE

- **Nunca** delete apenas a pasta vendor - ela tem todas as libs necessárias
- **Git pull** é suficiente - não precisa de composer
- Se receber erro, faça `git status` para ver o problema
- Logs estão em: `/home/u304177849/domains/appcheckin.com.br/public_html/api/logs/`

## 🆘 Troubleshooting

**Problema:** `Fatal error: Class 'Intervention\Image\ImageManager' not found`
- **Solução:** Fazer `git pull origin main` de novo (vendor não sincronizou)

**Problema:** Upload retorna 500
- **Solução:** Verificar permissões da pasta uploads: `chmod -R 755 public/uploads/`

**Problema:** Imagens não comprimem (redução 0%)
- **Solução:** GD library pode não estar ativada. Contatar Hostinger support.

---

**Commit mais recente:** `feat: Restaurar ImageCompressionService com intervention/image`
**Data:** 23/01/2026
**Status:** ✅ Pronto para deploy
