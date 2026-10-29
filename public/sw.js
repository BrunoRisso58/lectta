// Service worker mínimo: existe para tornar o app instalável (o Chrome exige
// um SW com handler de "fetch" pra oferecer "Adicionar à tela inicial") e pra
// servir os estáticos da primeira pintura sem depender da rede.
// Não cacheia páginas nem respostas de API -- os dados são por usuário e
// sempre precisam vir da rede.
//
// v3: entraram o app.js e a pasta partials/ no redesign mobile-first, e o
// style.css saiu da lista de pré-cache. Ele nunca era usado dali: a página
// pede "style.css?v=<filemtime>" e o caches.match casa pela URL INTEIRA, com
// query e tudo -- o "style.css" cru nunca batia. Agora ele é cacheado na
// primeira visita, já com a query certa, e a mudança do filemtime a cada
// deploy invalida sozinha.
const CACHE_NAME = 'lectta-static-v4';

// Só o que é pedido SEM query string entra aqui (senão cai no mesmo problema).
const STATIC_ASSETS = [
    // Primeira pintura: sem estas, o texto aparece na fonte de fallback e
    // "pula" quando a real chega.
    'fonts/atkinson-400-latin.woff2',
    'fonts/atkinson-700-latin.woff2',
    'fonts/crimson-400-700-latin.woff2',
    'img/favicon-32.png',
    'img/favicon-64.png',
    'img/favicon-256.png',
    'img/apple-touch-icon.png',
    'img/logo-icon-1024.png',
    'img/icon-192.png',
    'img/icon-512.png',
    'img/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    // Um addAll() falha por inteiro se UM arquivo der 404 -- e aí nada fica em
    // cache. Como o deploy é manual (FTP), um arquivo esquecido é plausível
    // demais pra derrubar o cache inteiro por causa dele: cada asset entra por
    // conta própria, e o que faltar só não é pré-cacheado.
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => Promise.all(
                STATIC_ASSETS.map((asset) => cache.add(asset).catch(() => null))
            ))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

// Remove versões antigas do MESMO arquivo (style.css?v=1 quando chega
// style.css?v=2). Sem isto o cache junta um CSS morto a cada deploy.
function dropOlderVersions(cache, url) {
    return cache.keys().then((keys) => Promise.all(
        keys
            .filter((req) => {
                const other = new URL(req.url);
                return other.origin === url.origin
                    && other.pathname === url.pathname
                    && other.search !== url.search;
            })
            .map((req) => cache.delete(req))
    ));
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    const isStaticAsset = /\.(?:css|js|png|jpg|jpeg|svg|ico|webp|woff2?)$/.test(url.pathname);
    if (!isStaticAsset) return; // páginas PHP e chamadas de API vão direto pra rede

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;
            return fetch(request).then((response) => {
                // Não cacheia erro nem resposta parcial: um 404 cacheado é
                // muito pior que um 404 que se resolve no próximo deploy.
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, copy);
                    if (url.search) dropOlderVersions(cache, url);
                });
                return response;
            });
        })
    );
});
