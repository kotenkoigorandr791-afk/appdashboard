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
        var init = {method: req.method, headers: req.headers, credentials: 'include', redirect: 'follow', cache: req.cache};
        if (req.method !== 'GET' && req.method !== 'HEAD') init.body = req.body;
        return fetch(new Request(url.toString(), init));
    })());
});
