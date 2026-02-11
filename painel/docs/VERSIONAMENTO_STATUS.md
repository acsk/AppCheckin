# ✅ Status de Versionamento

## Distribuição (dist/)

Todos os arquivos da pasta `dist/` já estão versionados no git:

```
✅ 26 arquivos em dist/
   ├── dist/index.html
   ├── dist/fonts.css
   ├── dist/favicon.ico
   ├── dist/_expo/Fonts/ (19 arquivos .ttf)
   ├── dist/_expo/static/css/
   ├── dist/_expo/static/js/
   └── dist/assets/
```

### Arquivos Versionados

**CSS/HTML/Assets:**
- `dist/index.html` ✅
- `dist/fonts.css` ✅
- `dist/favicon.ico` ✅
- `dist/metadata.json` ✅

**Fonts (19 arquivos):**
- `dist/_expo/Fonts/AntDesign.ttf` ✅
- `dist/_expo/Fonts/Entypo.ttf` ✅
- `dist/_expo/Fonts/Feather.ttf` ✅
- `dist/_expo/Fonts/Ionicons.ttf` ✅
- `dist/_expo/Fonts/MaterialCommunityIcons.ttf` ✅
- `dist/_expo/Fonts/MaterialIcons.ttf` ✅
- ... e mais 13 ✅

**Assets (CSS/JS do Expo):**
- `dist/_expo/static/css/web-*.css` ✅
- `dist/_expo/static/js/web/index-*.js` ✅

---

## 🚀 Deploy

### Opção 1: Push para GitLab/GitHub
```bash
git push origin main
```

### Opção 2: Deploy Direto (SCP)
```bash
scp -r dist/* usuario@seu-servidor:/var/www/painel/
```

### Opção 3: Clone no Servidor
```bash
# No servidor
cd /var/www
git clone https://seu-repo.git painel
cd painel
chmod -R 755 dist/
sudo chown -R www-data:www-data dist/
```

---

## 📋 Próximas Ações

1. **Se for fazer push:**
   ```bash
   git push origin main
   ```

2. **Se for fazer pull no servidor:**
   ```bash
   # SSH no servidor
   ssh usuario@seu-servidor
   cd /var/www/painel
   git pull origin main
   ```

3. **Se for fazer SCP:**
   ```bash
   scp -r dist/* usuario@seu-servidor:/var/www/painel/
   ```

---

## ✨ Vantagens

✅ **Todo o dist/ está versionado** - Facilita rollback se necessário  
✅ **Sem duplicação** - Git usa compressão para economizar espaço  
✅ **CI/CD fácil** - Deploy automático via git  
✅ **Backup** - Histórico completo de todas as versões

---

**Status**: ✅ Pronto para deploy
