export function isInstagramProfileUrl(value) {
  if (!value?.trim()) return true;
  try {
    const url = new URL(value.trim());
    return value.trim().length <= 2048 && url.protocol === 'https:'
      && ['instagram.com', 'www.instagram.com'].includes(url.hostname)
      && !url.username && !url.password && !url.port
      && /^\/[A-Za-z0-9_.]{1,30}\/?$/.test(url.pathname)
      && !['p', 'reel', 'reels', 'stories', 'explore', 'accounts', 'direct'].includes(url.pathname.replaceAll('/', '').toLowerCase());
  } catch {
    return false;
  }
}
