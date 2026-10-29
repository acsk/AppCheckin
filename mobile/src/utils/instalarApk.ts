import * as FileSystem from "expo-file-system/legacy";
import * as IntentLauncher from "expo-intent-launcher";

const FLAG_GRANT_READ_URI_PERMISSION = 1;

/**
 * Baixa o APK publicado para o cache e abre o instalador do Android. Na primeira vez o Android
 * pede para permitir "instalar apps desconhecidos" para o AppCheckin.
 * `onProgresso` recebe 0..1 (ou null quando o servidor não informa o tamanho).
 */
export async function baixarEInstalarApk(url: string, versao: string, onProgresso: (p: number | null) => void): Promise<void> {
  const destino = `${FileSystem.cacheDirectory}appcheckin-${versao.replace(/[^\w.-]/g, "")}.apk`;
  await FileSystem.deleteAsync(destino, { idempotent: true });
  const download = FileSystem.createDownloadResumable(url, destino, {}, (p) => {
    onProgresso(p.totalBytesExpectedToWrite > 0 ? p.totalBytesWritten / p.totalBytesExpectedToWrite : null);
  });
  const resultado = await download.downloadAsync();
  if (!resultado || resultado.status !== 200) {
    await FileSystem.deleteAsync(destino, { idempotent: true });
    throw new Error(`Download do APK falhou (HTTP ${resultado?.status ?? "?"}).`);
  }
  const contentUri = await FileSystem.getContentUriAsync(resultado.uri);
  await IntentLauncher.startActivityAsync("android.intent.action.VIEW", {
    data: contentUri,
    flags: FLAG_GRANT_READ_URI_PERMISSION,
    type: "application/vnd.android.package-archive",
  });
}
