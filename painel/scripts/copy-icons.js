#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Caminho dos fonts de origem
const sourceDir = path.join(__dirname, '../node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/Fonts');

// Caminho de destino
const destDir = path.join(__dirname, '../dist/_expo/Fonts');

// Criar diretório de destino se não existir
if (!fs.existsSync(destDir)) {
  fs.mkdirSync(destDir, { recursive: true });
  console.log(`📁 Diretório criado: ${destDir}`);
}

// Copiar todos os arquivos
try {
  const files = fs.readdirSync(sourceDir);
  
  files.forEach(file => {
    const srcFile = path.join(sourceDir, file);
    const destFile = path.join(destDir, file);
    
    fs.copyFileSync(srcFile, destFile);
    console.log(`✅ Copiado: ${file}`);
  });
  
  console.log(`\n✨ ${files.length} ícones copiados com sucesso!`);
} catch (error) {
  console.error('❌ Erro ao copiar ícones:', error.message);
  process.exit(1);
}
