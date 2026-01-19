#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Caminhos
const sourceDir = path.join(__dirname, '../node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts');
const destDir = path.join(__dirname, '../dist/_expo/Fonts');
const distDir = path.join(__dirname, '../dist');
const publicFontsDir = path.join(__dirname, '../public');

console.log('🚀 Iniciando cópia de fonts e injeção no HTML...');

// Criar diretório de destino se não existir
if (!fs.existsSync(destDir)) {
  fs.mkdirSync(destDir, { recursive: true });
  console.log(`📁 Diretório criado: ${destDir}`);
}

// Copiar fonts
try {
  const files = fs.readdirSync(sourceDir);
  
  files.forEach(file => {
    const srcFile = path.join(sourceDir, file);
    const destFile = path.join(destDir, file);
    
    fs.copyFileSync(srcFile, destFile);
  });
  
  console.log(`✅ ${files.length} ícones copiados com sucesso!`);
} catch (error) {
  console.error('❌ Erro ao copiar ícones:', error.message);
  process.exit(1);
}

// Copiar arquivo CSS dos fonts para dist
const fontsCssSource = path.join(publicFontsDir, 'fonts.css');
const fontsCssDest = path.join(distDir, 'fonts.css');

if (fs.existsSync(fontsCssSource)) {
  fs.copyFileSync(fontsCssSource, fontsCssDest);
  console.log(`✅ Arquivo fonts.css copiado para: ${fontsCssDest}`);
} else {
  console.warn(`⚠️  Arquivo fonts.css não encontrado em: ${fontsCssSource}`);
}

// Modificar o index.html para incluir o CSS dos fonts
const indexHtmlPath = path.join(distDir, 'index.html');
if (fs.existsSync(indexHtmlPath)) {
  let htmlContent = fs.readFileSync(indexHtmlPath, 'utf8');
  
  // Se o link para fonts.css não existir, adicionar
  if (!htmlContent.includes('fonts.css')) {
    htmlContent = htmlContent.replace(
      '</head>',
      '  <link rel="stylesheet" href="/fonts.css">\n</head>'
    );
    fs.writeFileSync(indexHtmlPath, htmlContent, 'utf8');
    console.log(`✅ Link para fonts.css adicionado ao index.html`);
  } else {
    // Corrigir path se estiver com /dist/fonts.css
    htmlContent = htmlContent.replace(/href="\/dist\/fonts\.css"/g, 'href="/fonts.css"');
    fs.writeFileSync(indexHtmlPath, htmlContent, 'utf8');
    console.log(`✅ Link para fonts.css já existe e está correto`);
  }
} else {
  console.warn(`⚠️  Arquivo index.html não encontrado em: ${indexHtmlPath}`);
}

console.log('✨ Processo completado!');
