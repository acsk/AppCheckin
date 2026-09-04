# Uploads de fotos — apiV2 + Slim (Hostinger)

Fotos de perfil ficam na **Slim** em:

```text
/home/u304177849/domains/appcheckin.com.br/public_html/api/public/uploads/fotos/
```

A apiV2 serve as mesmas imagens via symlink + rotas Laravel (`/v2/uploads/fotos/{arquivo}`).

---

## 1. Symlink (recomendado — sem duplicar arquivos)

SSH na Hostinger:

```bash
cd ~/domains/appcheckin.com.br/public_html/apiV2
bash scripts/hostinger-link-uploads.sh
```

Isso cria:

```text
apiV2/public/uploads  →  ../../api/public/uploads
```

Novos uploads pelo mobile (`POST /v2/mobile/perfil/foto`) gravam na **mesma pasta** da Slim.

---

## 2. Variáveis no `.env` da apiV2 (opcional)

Se preferir caminho absoluto em vez de symlink:

```env
UPLOADS_FOTOS_DIR=/home/u304177849/domains/appcheckin.com.br/public_html/api/public/uploads/fotos
UPLOADS_LEGACY_DIR=/home/u304177849/domains/appcheckin.com.br/public_html/api/public/uploads/fotos
```

Depois:

```bash
/opt/alt/php84/usr/bin/php artisan config:clear
```

---

## 3. Verificar / importar fotos do banco

Conferir se cada `aluno.foto_caminho` aponta para arquivo existente:

```bash
cd ~/domains/appcheckin.com.br/public_html/apiV2
/opt/alt/php84/usr/bin/php artisan uploads:verify-fotos
```

Se faltar arquivo no destino mas existir na Slim (sem symlink):

```bash
/opt/alt/php84/usr/bin/php artisan uploads:verify-fotos --copy-missing
/opt/alt/php84/usr/bin/php artisan uploads:verify-fotos --copy-missing --dry-run   # simular
```

---

## 4. Testar URL pública

Substitua `NOME.jpg` por um arquivo real listado em `uploads/fotos/`:

```bash
curl -I "https://apiv2.appcheckin.com.br/v2/uploads/fotos/NOME.jpg"
# Esperado: HTTP/2 200, Content-Type: image/jpeg
```

O mobile monta: `{API_URL}/uploads/fotos/NOME.jpg` → `/v2/uploads/fotos/...`.

---

## 5. Permissões

```bash
chmod -R 755 ~/domains/appcheckin.com.br/public_html/api/public/uploads
```

---

## Docker local (dev)

O `docker-compose.yml` já monta `./api/public/uploads` em `apiV2/public/uploads`.
