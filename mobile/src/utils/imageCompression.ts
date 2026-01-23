import * as FileSystem from 'expo-file-system';
import { Platform } from 'react-native';

/**
 * Interface para configurações de compressão
 */
export interface CompressionOptions {
  maxWidth?: number;
  maxHeight?: number;
  quality?: number;
  outputFormat?: 'jpeg' | 'png' | 'webp';
}

/**
 * Interface para resultado da compressão
 */
export interface CompressionResult {
  uri: string;
  width: number;
  height: number;
  size: number; // tamanho em bytes
  originalSize: number; // tamanho original em bytes
  compressionRatio: number; // porcentagem de redução
}

/**
 * Configurações padrão de compressão
 */
const DEFAULT_COMPRESSION_OPTIONS: CompressionOptions = {
  maxWidth: 1080,
  maxHeight: 1080,
  quality: 0.8,
  outputFormat: 'jpeg',
};

/**
 * Comprime uma imagem antes de enviar
 * Funciona tanto no mobile quanto no web
 *
 * @param imageUri - URI da imagem original
 * @param options - Opções de compressão
 * @returns Resultado da compressão com nova URI
 */
export async function compressImage(
  imageUri: string,
  options: CompressionOptions = {},
): Promise<CompressionResult> {
  const config = { ...DEFAULT_COMPRESSION_OPTIONS, ...options };

  try {
    if (Platform.OS === 'web') {
      // Para web, usar canvas HTML5
      return await compressImageWeb(imageUri, config);
    } else {
      // Para mobile, usar expo-image-manipulator
      return await compressImageMobile(imageUri, config);
    }
  } catch (error) {
    console.error('❌ Erro ao comprimir imagem:', error);
    throw error;
  }
}

/**
 * Comprime imagem no web usando Canvas
 */
async function compressImageWeb(
  imageUri: string,
  options: CompressionOptions,
): Promise<CompressionResult> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';

    img.onload = () => {
      try {
        // Calcular novas dimensões mantendo aspect ratio
        let width = img.naturalWidth;
        let height = img.naturalHeight;

        if (
          options.maxWidth &&
          options.maxHeight &&
          (width > options.maxWidth || height > options.maxHeight)
        ) {
          const ratio = Math.min(
            options.maxWidth / width,
            options.maxHeight / height,
          );
          width = Math.round(width * ratio);
          height = Math.round(height * ratio);
        }

        // Criar canvas
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');
        if (!ctx) {
          throw new Error('Não foi possível obter contexto do canvas');
        }

        // Desenhar imagem no canvas
        ctx.drawImage(img, 0, 0, width, height);

        // Converter para blob
        canvas.toBlob(
          (blob) => {
            if (!blob) {
              throw new Error('Não foi possível converter canvas para blob');
            }

            // Criar URL da imagem comprimida
            const compressedUri = URL.createObjectURL(blob);

            // Obter tamanho original
            fetch(imageUri)
              .then((res) => res.blob())
              .then((originalBlob) => {
                resolve({
                  uri: compressedUri,
                  width,
                  height,
                  size: blob.size,
                  originalSize: originalBlob.size,
                  compressionRatio:
                    ((originalBlob.size - blob.size) /
                      originalBlob.size) *
                    100,
                });
              });
          },
          `image/${options.outputFormat}`,
          options.quality,
        );
      } catch (error) {
        reject(error);
      }
    };

    img.onerror = () => {
      reject(new Error('Não foi possível carregar a imagem'));
    };

    img.src = imageUri;
  });
}

/**
 * Comprime imagem no mobile usando expo-image-manipulator
 */
async function compressImageMobile(
  imageUri: string,
  options: CompressionOptions,
): Promise<CompressionResult> {
  try {
    // Importar dinamicamente para não quebrar no web
    const { manipulateAsync, SaveFormat } = await import(
      'expo-image-manipulator'
    );

    // Obter informações da imagem original
    const originalInfo = await FileSystem.getInfoAsync(imageUri);
    const originalSize = originalInfo.size || 0;

    // Definir formato de saída
    const formatMap: {
      [key: string]: SaveFormat;
    } = {
      jpeg: SaveFormat.JPEG,
      png: SaveFormat.PNG,
      webp: SaveFormat.WEBP,
    };

    const saveFormat =
      formatMap[options.outputFormat || 'jpeg'] || SaveFormat.JPEG;

    // Manipular imagem
    const manipResult = await manipulateAsync(imageUri, [
      {
        resize: {
          width: options.maxWidth || 1080,
          height: options.maxHeight || 1080,
        },
      },
    ]);

    // Salvar imagem comprimida
    const compressResult = await manipulateAsync(
      manipResult.uri,
      [],
      {
        compress: options.quality || 0.8,
        format: saveFormat,
      },
    );

    // Obter informações da imagem comprimida
    const compressedInfo = await FileSystem.getInfoAsync(
      compressResult.uri,
    );
    const compressedSize = compressedInfo.size || 0;

    return {
      uri: compressResult.uri,
      width: manipResult.width,
      height: manipResult.height,
      size: compressedSize,
      originalSize,
      compressionRatio:
        ((originalSize - compressedSize) / originalSize) * 100,
    };
  } catch (error) {
    console.error('❌ Erro ao comprimir imagem no mobile:', error);
    throw error;
  }
}

/**
 * Formata tamanho em bytes para string legível
 */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 Bytes';

  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Log de compressão formatado
 */
export function logCompressionInfo(result: CompressionResult): void {
  console.log('📸 === COMPRESSÃO DE IMAGEM ===');
  console.log(`📏 Dimensões: ${result.width}x${result.height}`);
  console.log(
    `📦 Tamanho original: ${formatFileSize(result.originalSize)}`,
  );
  console.log(`📦 Tamanho comprimido: ${formatFileSize(result.size)}`);
  console.log(
    `📉 Compressão: ${result.compressionRatio.toFixed(2)}% redução`,
  );
  console.log('================================');
}
