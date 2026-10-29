import * as FileSystem from 'expo-file-system';
import { Platform } from 'react-native';
import { debugLogger } from './debugLogger';

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
  
  debugLogger.log('🎨 [compressImage] ===== INICIANDO COMPRESSÃO =====');
  debugLogger.log('📸 URI da imagem:', { uri: imageUri });
  debugLogger.log('⚙️ Plataforma:', { platform: Platform.OS });
  
  const config = { ...DEFAULT_COMPRESSION_OPTIONS, ...options };
  debugLogger.log('⚙️ Configuração final:', config);

  try {
    if (Platform.OS === 'web') {
      debugLogger.log('🌐 Usando compressão WEB (Canvas)');
      const result = await compressImageWeb(imageUri, config);
      debugLogger.log('✅ Compressão WEB concluída:', {
        originalSize: result.originalSize,
        newSize: result.size,
        ratio: `${(result.compressionRatio * 100).toFixed(1)}%`,
      });
      return result;
    } else {
      debugLogger.log('📱 Usando compressão MOBILE (expo-image-manipulator)');
      debugLogger.log('🔍 Chamando compressImageMobile com URI:', { uri: imageUri });
      const result = await compressImageMobile(imageUri, config);
      debugLogger.log('✅ Compressão MOBILE concluída:', {
        originalSize: result.originalSize,
        newSize: result.size,
        ratio: `${(result.compressionRatio * 100).toFixed(1)}%`,
      });
      return result;
    }
  } catch (error) {
    console.error('❌ [compressImage] ERRO FATAL:', error);
    console.error('❌ [compressImage] Stack:', (error as any).stack);
    debugLogger.error('❌ [compressImage] ERRO FATAL:', error);
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
    debugLogger.log('🌐 [compressImageWeb] Iniciando compressão web com Canvas');
    debugLogger.log('📸 [compressImageWeb] URI:', { uri: imageUri });
    
    const img = new Image();
    img.crossOrigin = 'anonymous';

    img.onload = () => {
      try {
        debugLogger.log('🌐 [compressImageWeb] Imagem carregada:', {
          naturalWidth: img.naturalWidth,
          naturalHeight: img.naturalHeight,
        });

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
          debugLogger.log('🌐 [compressImageWeb] Dimensões ajustadas:', {
            newWidth: width,
            newHeight: height,
            ratio,
          });
        }

        // Criar canvas
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');
        if (!ctx) {
          throw new Error('Não foi possível obter contexto do canvas');
        }

        debugLogger.log('🌐 [compressImageWeb] Canvas criado:', {
          width: canvas.width,
          height: canvas.height,
        });

        // Desenhar imagem no canvas
        ctx.drawImage(img, 0, 0, width, height);
        debugLogger.log('🌐 [compressImageWeb] Imagem desenhada no canvas');

        // Converter para blob
        canvas.toBlob(
          (blob) => {
            if (!blob) {
              const error = new Error('Não foi possível converter canvas para blob');
              debugLogger.error('❌ [compressImageWeb] Erro ao converter:', error);
              reject(error);
              return;
            }

            debugLogger.log('🌐 [compressImageWeb] Canvas convertido para blob:', {
              blobSize: blob.size,
            });

            // Criar URL da imagem comprimida
            const compressedUri = URL.createObjectURL(blob);
            debugLogger.log('🌐 [compressImageWeb] Object URL criado:', {
              uri: compressedUri,
            });

            // Obter tamanho original
            fetch(imageUri)
              .then((res) => {
                debugLogger.log('🌐 [compressImageWeb] Fetch da imagem original retornou status:', res.status);
                return res.blob();
              })
              .then((originalBlob) => {
                const compressionRatio =
                  ((originalBlob.size - blob.size) / originalBlob.size) * 100;

                const result = {
                  uri: compressedUri,
                  width,
                  height,
                  size: blob.size,
                  originalSize: originalBlob.size,
                  compressionRatio,
                };

                debugLogger.log('🌐 [compressImageWeb] Compressão concluída:', {
                  originalSize: originalBlob.size,
                  newSize: blob.size,
                  ratio: `${compressionRatio.toFixed(1)}%`,
                });

                resolve(result);
              })
              .catch((error) => {
                debugLogger.error('❌ [compressImageWeb] Erro ao fetch da imagem original:', error);
                reject(error);
              });
          },
          `image/${options.outputFormat}`,
          options.quality,
        );
      } catch (error) {
        debugLogger.error('❌ [compressImageWeb] Erro durante processamento:', error);
        reject(error);
      }
    };

    img.onerror = () => {
      const error = new Error('Não foi possível carregar a imagem');
      debugLogger.error('❌ [compressImageWeb] Erro ao carregar imagem:', error);
      reject(error);
    };

    debugLogger.log('🌐 [compressImageWeb] Iniciando carregamento da imagem');
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

    // Manipular imagem - REDIMENSIONAR
    const resizeWidth = options.maxWidth || 800;
    const resizeHeight = options.maxHeight || 800;
    const manipResult = await manipulateAsync(imageUri, [
      {
        resize: {
          width: resizeWidth,
          height: resizeHeight,
        },
      },
    ]);

    // Salvar imagem comprimida
    const quality = options.quality || 0.8;
    const compressResult = await manipulateAsync(
      manipResult.uri,
      [],
      {
        compress: quality,
        format: saveFormat,
      },
    );

    // Obter informações da imagem comprimida
    const compressedInfo = await FileSystem.getInfoAsync(
      compressResult.uri,
    );
    const compressedSize = compressedInfo.size || 0;
    const compressionPercentage = ((originalSize - compressedSize) / originalSize) * 100;
    

    const result = {
      uri: compressResult.uri,
      width: manipResult.width,
      height: manipResult.height,
      size: compressedSize,
      originalSize,
      compressionRatio: compressionPercentage,
    };

    return result;
  } catch (error) {
    console.error('❌ [compressImageMobile] ERRO FATAL:', error);
    console.error('❌ [compressImageMobile] Stack:', (error as any).stack);
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
}
