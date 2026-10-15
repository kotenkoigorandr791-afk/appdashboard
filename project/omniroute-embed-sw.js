self.addEventListener('install', function(){ self.skipWaiting(); });
self.addEventListener('activate', function(event){ event.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function(event){
    var req = event.request;
    var url;
    try { url = new URL(req.url); } catch (e) { return; }
    if (url.origin !== self.location.origin) return;
    if (url.pathname === '/admin-omniroute' || url.pathname.indexOf('/admin-omniroute/') === 0) return;
    if (url.pathname.indexOf('/_next/') === 0) return;
    if (url.pathname.charAt(0) !== '/') return;
    event.respondWith((async function(){
        var client = event.clientId ? await self.clients.get(event.clientId) : null;
        if (!client || client.url.indexOf('/admin-omniroute') === -1) return fetch(req);
        url.pathname = '/admin-omniroute' + url.pathname;
        var init = {method: req.method, headers: req.headers, mode: req.mode, credentials: req.credentials, redirect: req.redirect, referrer: req.referrer, referrerPolicy: req.referrerPolicy, integrity: req.integrity, keepalive: req.keepalive, cache: req.cache === "only-if-cached" && req.mode !== "same-origin" ? "default" : req.cache};
        if (req.method !== "GET" && req.method !== "HEAD") { init.body = req.body; init.duplex = "half"; }
        return fetch(new Request(url.toString(), init));
    })());
});
