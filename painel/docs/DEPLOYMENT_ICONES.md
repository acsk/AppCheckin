# Guia de Implantação - Ícones do Expo Web

## Resumo Executivo

Os ícones do Expo agora funcionam automaticamente. O sistema foi configurado para:
1. ✅ Carregar todos os 19 tipos de ícones (@expo/vector-icons)
2. ✅ Injetar o CSS automaticamente no index.html
3. ✅ Funcionar em qualquer baseUrl configurado

## Configuração no app.json

```json
{
  "expo": {
    "web": {
      "baseUrl": "/dist/",
      "favicon": "./assets/favicon.png"
    }
  }
}
```

**Nota**: O `baseUrl` é importante! Determina onde todos os assets são servidos.

## Deployment em Servidor Web Real

### Nginx

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    root /var/www/seu-app/dist;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ \.(js|css|ttf|woff|woff2|png|jpg|jpeg|gif|svg|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Apache

```apache
<Directory /var/www/seu-app/dist>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </IfModule>
</Directory>

<FilesMatch "\.(js|css|ttf|woff|woff2|png|jpg|jpeg|gif|svg|ico)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
```

### Node.js / Express

```javascript
const express = require('express');
const path = require('path');
const app = express();

// Servir arquivos estáticos com cache
app.use(express.static(path.join(__dirname, 'dist'), {
    maxAge: '1y',
    etag: false
}));

// SPA routing
app.get('*', (req, res) => {
    res.sendFile(path.join(__dirname, 'dist/index.html'));
});

app.listen(3000, () => {
    console.log('Servidor rodando em http://localhost:3000');
});
```

## Verificação de Deployment

1. **Verificar baseUrl correto**:
   ```bash
   curl https://seu-dominio.com/dist/fonts.css | head -3
   ```

2. **Verificar fonts.css é servido com HTTP 200**:
   ```bash
   curl -I https://seu-dominio.com/dist/fonts.css
   ```

3. **Verificar fonts TTF são acessíveis**:
   ```bash
   curl -I https://seu-dominio.com/dist/_expo/Fonts/Feather.ttf
   ```

4. **No navegador, abrir DevTools**:
   - F12 → Network
   - Procure por `fonts.css` - Status 200
   - Procure por `*.ttf` - Status 200
   - Não deve haver 404s

## Estrutura de Diretórios no Servidor

```
/var/www/seu-app/
└── dist/
    ├── index.html
    ├── fonts.css          ← Novo: CSS dos fonts
    ├── favicon.ico
    ├── _expo/
    │   ├── Fonts/         ← Novo: 19 arquivos .ttf
    │   └── static/
    │       ├── css/
    │       ├── js/
    │       └── ...
    └── ...
```

## Build e Deploy

### 1. Build Local
```bash
npm run copy-icons  # Copiar fonts
# Expo gera automaticamente dist/ durante start --web
```

### 2. Copiar para Servidor
```bash
scp -r dist/* usuario@seu-dominio.com:/var/www/seu-app/dist/
```

### 3. Verificar
```bash
curl https://seu-dominio.com/test-icons.html
# Deve exibir página de teste com fonts carregados
```

## Troubleshooting em Produção

### Fonts retornam 404
- ❌ Verificar se `dist/_expo/Fonts/` tem 19 arquivos .ttf
- ❌ Verificar permissões de arquivo (755)
- ❌ Verificar se servidor web está configurado para servir .ttf

**Solução**:
```bash
ls -la /var/www/seu-app/dist/_expo/Fonts/
chmod 755 /var/www/seu-app/dist/_expo/Fonts/*
```

### CSS retorna 404
- ❌ Verificar se `dist/fonts.css` existe
- ❌ Verificar HTML tem `<link rel="stylesheet" href="/dist/fonts.css">`

**Solução**:
```bash
ls -la /var/www/seu-app/dist/fonts.css
grep fonts.css /var/www/seu-app/dist/index.html
```

### Ícones aparecem como símbolos estranhos
- ❌ Fonts carregados mas caracteres mapeados incorretamente
- ❌ Versão de @expo/vector-icons diferente
- ❌ Cache do navegador antigo

**Solução**:
```bash
npm install @expo/vector-icons@latest
npm run copy-icons
# Usuários: Limpar cache (Ctrl+Shift+Del ou incógnito)
```

### CORS issues
Se o servidor está em domínio diferente:

**Nginx**:
```nginx
add_header Access-Control-Allow-Origin "*";
```

**Apache**:
```apache
Header set Access-Control-Allow-Origin "*"
```

## Monitoramento

### Verificar logs de erro
```bash
# Nginx
tail -f /var/log/nginx/error.log

# Apache
tail -f /var/log/apache2/error.log

# Node.js
# Verificar console.log/console.error na aplicação
```

### Verificar tamanho dos fonts
```bash
du -sh dist/_expo/Fonts/
# Esperado: ~3-5MB para todos os 19 fonts
```

## Performance

### Cache de Fonts
Os fonts são raramente atualizados. Configurar cache agressivo:

```nginx
location ~ /\.ttf$ {
    expires 365d;
    add_header Cache-Control "public, immutable";
}

location ~ /fonts\.css$ {
    expires 30d;
    add_header Cache-Control "public";
}
```

### Compressão
Ativar gzip para CSS:

```nginx
gzip on;
gzip_types text/css font/ttf font/woff font/woff2;
gzip_min_length 1000;
```

## Migração de Baseurl

Se você mudar o `baseUrl` no `app.json`, regenerar:

```bash
# Parar servidor
# Modificar app.json
# Limpar dist/
rm -rf dist/

# Regenerar
npm run copy-icons

# Iniciar novamente
npm run web
```

O arquivo `fonts.css` usa paths relativos (`./_expo/Fonts/`), então funciona com qualquer baseUrl.

---

**Última atualização**: 19 de Janeiro de 2025
**Status**: ✅ Produção Ready
