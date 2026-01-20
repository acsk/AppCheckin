# Scripts de Deploy

## 📝 Disponíveis

### 1. **deploy.sh** - Deploy Completo (Recomendado)

Faz tudo de uma vez:
- ✅ Export do Expo Web
- ✅ Copia fonts dos ícones
- ✅ Copia fonts.css
- ✅ Injeta link no HTML
- ✅ Verifica estrutura final

**Uso:**
```bash
./deploy.sh
```

**Resultado:**
- Pasta `dist/` pronta para upload
- Todos os fonts em `dist/_expo/Fonts/`
- CSS injetado em `dist/index.html`

---

### 2. **copy-fonts-only.sh** - Apenas Copiar Fonts

Use este se você já fez o export manualmente:

**Uso:**
```bash
# Primeiro faça o export
npx expo export --platform web

# Depois execute o script
./copy-fonts-only.sh
```

**O que faz:**
- Copia fonts para `dist/_expo/Fonts/`
- Copia `fonts.css`
- Injeta link no HTML

---

## 🚀 Fluxo de Trabalho Recomendado

### Desenvolvimento Local
```bash
npm run web
# Seu app roda em http://localhost:8081
```

### Preparar para Deploy
```bash
# Opção 1: Deploy automático (recomendado)
./deploy.sh

# Opção 2: Manual
npx expo export --platform web
./copy-fonts-only.sh
```

### Upload para Servidor

**Via SCP (SSH):**
```bash
scp -r dist/* usuario@seu-servidor:/var/www/painel/
```

**Via FTP:**
```bash
# Usar cliente FTP (FileZilla, WinSCP, etc)
# Fazer upload de dist/* para /painel/
```

### Verificação em Produção

```bash
# Testar CSS
curl https://seu-dominio.com/_expo/static/css/web-*.css

# Testar fonts
curl https://seu-dominio.com/_expo/Fonts/Feather.ttf

# Testar fonts.css
curl https://seu-dominio.com/fonts.css
```

---

## 🔧 Configuração do Servidor

### Nginx

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    root /var/www/painel;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Cache para fonts
    location ~ \.(ttf|woff|woff2|css)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Apache

Verificar se `.htaccess` existe em `dist/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.html$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.html [L]
</IfModule>

<FilesMatch "\.(ttf|woff|woff2|css)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
```

---

## 📋 Checklist de Deploy

- [ ] Executou `./deploy.sh` ou `npx expo export --platform web` + `./copy-fonts-only.sh`
- [ ] Pasta `dist/` foi criada
- [ ] `dist/_expo/Fonts/` tem 19+ arquivos .ttf
- [ ] `dist/fonts.css` existe
- [ ] `dist/index.html` tem `<link rel="stylesheet" href="/fonts.css">`
- [ ] Servidor web está configurado para servir SPA (rewrite para index.html)
- [ ] Fez upload de `dist/*` para servidor
- [ ] Testou em navegador - sem erros 404
- [ ] Ícones aparecem corretamente

---

## 🆘 Troubleshooting

### Fonts retornam 404
```bash
# Verificar se existem
ls -la dist/_expo/Fonts/

# Verificar permissões
chmod 755 dist/_expo/Fonts/*

# Verificar tamanho
du -sh dist/_expo/Fonts/
```

### CSS retorna 404
```bash
# Verificar arquivo
ls -la dist/fonts.css

# Verificar no HTML
grep fonts.css dist/index.html
```

### Ícones não aparecem mesmo com 200 OK
```bash
# Limpar cache do navegador
# Ou teste em incógnito
```

### Path duplicado no HTML
```bash
# Verificar
grep "href=" dist/index.html | head -5

# Corrigir manualmente se necessário
sed -i '' 's|href="/dist/|href="/|g' dist/index.html
```

---

## 📊 Estrutura Final

```
dist/
├── index.html            ← Arquivo principal
├── fonts.css             ← Novo: CSS dos fonts
├── favicon.ico
├── _expo/
│   ├── Fonts/            ← Novo: 19 arquivos .ttf
│   │   ├── Feather.ttf
│   │   ├── Ionicons.ttf
│   │   ├── MaterialIcons.ttf
│   │   └── ...
│   └── static/
│       ├── css/          ← CSS do Expo
│       ├── js/           ← JS do Expo
│       └── ...
└── ...
```

---

## 💡 Dicas

1. **Rápido**: Use `./deploy.sh` - faz tudo em um comando
2. **Incremental**: Use `./copy-fonts-only.sh` se já tem dist gerado
3. **Testar localmente**: `cd dist && python3 -m http.server 3000`
4. **Cache**: Os fonts usam cache de 1 ano em produção
5. **Tamanho**: Fonts ocupam ~3-5MB, não é problema para a maioria dos servidores

---

**Última atualização**: 19 de Janeiro de 2026
