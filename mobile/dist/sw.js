/* AppCheckin PWA — CACHE_VERSION é gravado no build (increment-version.js). */
const CACHE_VERSION = "v1.31";
const CACHE_NAME = "appcheckin-" + CACHE_VERSION;

self.addEventListener("install", (event) => {
  self.skipWaiting();
  event.waitUntil(Promise.resolve());
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith("appcheckin-") && key !== CACHE_NAME)
            .map((key) => caches.delete(key)),
        ),
      )
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("message", (event) => {
  if (event.data === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const path = url.pathname;
  const accept = request.headers.get("accept") || "";
  const isDocument =
    request.mode === "navigate" ||
    accept.includes("text/html") ||
    path.endsWith(".html") ||
    path === "/" ||
    path === "/index.html";
  const isSwOrManifest =
    path.endsWith("/sw.js") || path.endsWith("site.webmanifest");

  if (isDocument || isSwOrManifest) {
    event.respondWith(networkOnly(request));
    return;
  }

  event.respondWith(cacheFirst(request));
});

async function networkOnly(request) {
  try {
    return await fetch(request, { cache: "no-store" });
  } catch {
    const cached = await caches.match(request);
    if (cached) return cached;
    return Response.error();
  }
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  const fresh = await fetch(request);
  if (fresh && fresh.ok) {
    const cache = await caches.open(CACHE_NAME);
    cache.put(request, fresh.clone());
  }
  return fresh;
}
