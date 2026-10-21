self.addEventListener('install', function(){ self.skipWaiting(); });
self.addEventListener('activate', function(event){ event.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function(event){
    var req = event.request;
    var url;
    try { url = new URL(req.url); } catch (e) { return; }
    if (url.origin !== self.location.origin) return;
    if (url.pathname === '/admin-omniroute' || url.pathname.indexOf('/admin-omniroute/') === 0) return;
    if (url.pathname.charAt(0) !== '/') return;
    var rewritePrefixes = ['/_next/', '/api/', '/v1/', '/dashboard', '/home', '/login', '/offline', '/manifest.webmanifest', '/sw.js', '/a2a', '/chat/', '/providers/'];
    var needsRewrite = false;
    for (var i = 0; i < rewritePrefixes.length; i++) {
        var p = rewritePrefixes[i];
        if (url.pathname === p.replace(/\/$/, '') || url.pathname.indexOf(p) === 0) { needsRewrite = true; break; }
    }
    if (!needsRewrite) return;
    event.respondWith((async function(){
        var client = event.clientId ? await self.clients.get(event.clientId) : null;
        if (!client && event.resultingClientId) client = await self.clients.get(event.resultingClientId);
        if (!client || client.url.indexOf('/admin-omniroute') === -1) return fetch(req);
        url.pathname = '/admin-omniroute' + url.pathname;
        var init = {method: req.method, headers: req.headers, mode: req.mode, credentials: req.credentials, redirect: req.redirect, referrer: req.referrer, referrerPolicy: req.referrerPolicy, integrity: req.integrity, keepalive: req.keepalive, cache: req.cache === "only-if-cached" && req.mode !== "same-origin" ? "default" : req.cache};
        if (req.method !== "GET" && req.method !== "HEAD") { init.body = req.body; init.duplex = "half"; }
        return fetch(new Request(url.toString(), init));
    })());
});
