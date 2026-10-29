/**
 * 📸 EXEMPLOS DE USO - Compressão de Imagens
 * 
 * Este arquivo contém exemplos práticos de como usar o sistema
 * de compressão de imagens em diferentes cenários
 */

// ============================================
// EXEMPLO 1: Uso Básico
// ============================================
import { compressImage, logCompressionInfo } from '@/src/utils/imageCompression';

async function exemploBasico(imageUri: string) {
  try {
    // Usar configurações padrão
    const result = await compressImage(imageUri);
    
    // Log automático
    logCompressionInfo(result);
    
    return result.uri;
  } catch (error) {
    console.error('Erro:', error);
  }
}

// ============================================
// EXEMPLO 2: Customizar Qualidade
// ============================================
async function exemploQualidadeAlta(imageUri: string) {
  // Para fotos onde qualidade é importante
  const result = await compressImage(imageUri, {
    quality: 0.9, // Qualidade 90%
    maxWidth: 2048,
    maxHeight: 2048,
  });

  return result.uri;
}

// ============================================
// EXEMPLO 3: Compressão Agressiva
// ============================================
async function exemploCompressaoAgressiva(imageUri: string) {
  // Para economizar máxima banda
  const result = await compressImage(imageUri, {
    quality: 0.6, // Qualidade 60%
    maxWidth: 800,
    maxHeight: 800,
  });

  return result.uri;
}

// ============================================
// EXEMPLO 4: Validar Antes de Enviar
// ============================================
async function exemploValidarTamanho(imageUri: string) {
  const maxSizeAllowed = 1024 * 1024; // 1 MB máximo

  const result = await compressImage(imageUri, {
    quality: 0.8,
  });

  if (result.size > maxSizeAllowed) {
    console.warn(`Imagem ainda grande: ${result.size / 1024 / 1024}MB`);
    
    // Tentar com qualidade menor
    const result2 = await compressImage(imageUri, {
      quality: 0.6,
      maxWidth: 800,
      maxHeight: 800,
    });
    
    if (result2.size <= maxSizeAllowed) {
      return result2.uri;
    } else {
      throw new Error('Imagem ainda acima do limite');
    }
  }

  return result.uri;
}

// ============================================
// EXEMPLO 5: Múltiplos Formatos
// ============================================
async function exemploFormatos(imageUri: string) {
  // JPEG (menor tamanho, com perdas)
  const jpeg = await compressImage(imageUri, {
    outputFormat: 'jpeg',
    quality: 0.8,
  });

  // PNG (sem perdas, maior tamanho)
  const png = await compressImage(imageUri, {
    outputFormat: 'png',
    quality: 1.0,
  });

  // WebP (melhor compressão, menos suporte)
  const webp = await compressImage(imageUri, {
    outputFormat: 'webp',
    quality: 0.8,
  });

}

// ============================================
// EXEMPLO 6: Integração com Upload
// ============================================
import * as ImagePicker from 'expo-image-picker';
import { Platform } from 'react-native';

async function exemploUpload() {
  // Selecionar imagem
  const result = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: 'images',
  });

  if (!result.canceled && result.assets.length > 0) {
    // Comprimir
    const compressed = await compressImage(result.assets[0].uri);

    // Criar FormData
    const formData = new FormData();

    if (Platform.OS === 'web') {
      const response = await fetch(compressed.uri);
      const blob = await response.blob();
      formData.append('foto', blob, 'photo.jpg');
    } else {
      formData.append('foto', {
        uri: compressed.uri,
        type: 'image/jpeg',
        name: 'photo.jpg',
      } as any);
    }

    // Enviar
    const uploadResponse = await fetch('/api/upload', {
      method: 'POST',
      body: formData,
    });

  }
}

// ============================================
// EXEMPLO 7: Feedback Visual ao Usuário
// ============================================
import { useState } from 'react';
import { ActivityIndicator, Text, View } from 'react-native';

function ExemploComFeedback() {
  const [status, setStatus] = useState('');
  const [progress, setProgress] = useState(0);

  const handleCompress = async (imageUri: string) => {
    try {
      setStatus('Comprimindo imagem...');
      setProgress(30);

      const result = await compressImage(imageUri, {
        quality: 0.8,
      });

      setProgress(70);

      const reduction = result.compressionRatio.toFixed(1);
      setStatus(`Reduzido em ${reduction}%`);
      setProgress(100);

      // Reset após 2s
      setTimeout(() => {
        setStatus('');
        setProgress(0);
      }, 2000);

      return result.uri;
    } catch (error) {
      setStatus('Erro ao comprimir');
      setProgress(0);
    }
  };

  return (
    <View>
      <ActivityIndicator animating={progress > 0} />
      <Text>{status}</Text>
      {progress > 0 && (
        <View style={{ height: 4, backgroundColor: '#ddd' }}>
          <View
            style={{
              height: '100%',
              width: `${progress}%`,
              backgroundColor: '#4CAF50',
            }}
          />
        </View>
      )}
    </View>
  );
}

// ============================================
// EXEMPLO 8: Retry com Fallback
// ============================================
async function exemploRetryFallback(imageUri: string) {
  const tentativas = [
    { quality: 0.8, maxWidth: 1080 },
    { quality: 0.6, maxWidth: 800 },
    { quality: 0.4, maxWidth: 600 },
  ];

  for (const opcao of tentativas) {
    try {
      const result = await compressImage(imageUri, opcao);

      if (result.size < 1024 * 1024) { // < 1MB
        return result.uri;
      }
    } catch (error) {
      console.warn(`Tentativa com ${opcao.quality} falhou`);
      continue;
    }
  }

  // Se tudo falhar, retornar original
  console.warn('Usando imagem original');
  return imageUri;
}

// ============================================
// EXEMPLO 9: Compressão em Batch
// ============================================
async function exemploBatch(imageUris: string[]) {
  const results = await Promise.all(
    imageUris.map(uri =>
      compressImage(uri, { quality: 0.8 }).catch(e => ({
        error: e,
        uri,
      }))
    )
  );

  const sucessos = results.filter(r => !r.error);
  const falhas = results.filter(r => r.error);


  return sucessos;
}

// ============================================
// EXEMPLO 10: Monitorar Performance
// ============================================
async function exemploPerformance(imageUri: string) {
  const startTime = Date.now();
  const startMemory = (performance as any).memory?.usedJSHeapSize || 0;

  const result = await compressImage(imageUri);

  const endTime = Date.now();
  const endMemory = (performance as any).memory?.usedJSHeapSize || 0;


  return result.uri;
}

// ============================================
// EXEMPLO 11: Pré-visualizar Redução
// ============================================
async function exemploPreview(imageUri: string) {

  for (let quality = 1.0; quality >= 0.1; quality -= 0.2) {
    const result = await compressImage(imageUri, { quality });
    const sizeMB = (result.size / 1024 / 1024).toFixed(2);
    const reduction = result.compressionRatio.toFixed(1);

  }
}

// ============================================
// EXEMPLO 12: Adaptar Qualidade por Conexão
// ============================================
import { useNetInfo } from '@react-native-community/netinfo';

async function exemploAdaptarConexao(imageUri: string) {
  // Simular verificação de conexão
  const isSlowConnection = true; // Verificar conectividade real

  const quality = isSlowConnection ? 0.5 : 0.8;
  const maxWidth = isSlowConnection ? 640 : 1080;

  const result = await compressImage(imageUri, {
    quality,
    maxWidth,
    maxHeight: maxWidth,
  });


  return result.uri;
}

export {
  exemploBasico,
  exemploQualidadeAlta,
  exemploCompressaoAgressiva,
  exemploValidarTamanho,
  exemploFormatos,
  exemploUpload,
  ExemploComFeedback,
  exemploRetryFallback,
  exemploBatch,
  exemploPerformance,
  exemploPreview,
  exemploAdaptarConexao,
};
