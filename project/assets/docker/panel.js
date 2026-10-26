(function () {
    var root = null;
    var query = '';
    var currentHref = '';
    var endpoint = '/project/docker/';
    function runScripts(container) {
        Array.prototype.slice.call(container.querySelectorAll('script')).forEach(function (old) {
            var s = document.createElement('script');
            if (old.src) { s.src = old.src; s.async = false; } else { s.textContent = old.textContent; }
            old.parentNode.replaceChild(s, old);
        });
    }
    function fragmentUrl(href) {
        var u = new URL(href || (endpoint + '?' + query), window.location.origin);
        new URLSearchParams(query).forEach(function (v, k) { if (!u.searchParams.has(k)) u.searchParams.set(k, v); });
        u.searchParams.set('fragment', '1');
        u.searchParams.set('embed', '1');
        return u.toString();
    }
    function request(href, options) {
        return fetch(fragmentUrl(href), Object.assign({ credentials: 'same-origin' }, options || {})).then(function (r) {
            return r.text().then(function (text) {
                var gate = false;
                try { var j = JSON.parse(text); gate = !!(j && (j.admin_auth_required || j.error === 'admin_auth_required')); } catch (e) {}
                if (gate || r.status === 401) { if (typeof window.apGateShow === 'function') window.apGateShow(''); throw new Error('gate'); }
                return { html: text, url: r.url };
            });
        });
    }
    function setHtml(target, html) {
        target.innerHTML = html;
        runScripts(target);
    }
    function showError(target) {
        target.innerHTML = '<div style="padding:20px;color:#f87171">Docker Manager: request failed</div>';
    }
    window.DockerPanel = {
        mounted: function () { return !!(root && document.body.contains(root) && root.querySelector('#docker-app')); },
        baseUrl: function () { return endpoint + '?' + query; },
        init: function (el) {
            root = el || document.getElementById('ap-docker-root');
            if (!root) return;
            query = root.getAttribute('data-query') || '';
            this.navigate(endpoint + '?' + query);
        },
        navigate: function (href) {
            if (!root) return Promise.resolve();
            currentHref = href;
            return request(href).then(function (res) { setHtml(root, res.html); }).catch(function (e) { if (e.message !== 'gate') showError(root); });
        },
        refresh: function () {
            return this.navigate(endpoint + '?' + query);
        },
        loadInto: function (target, href) {
            return request(href).then(function (res) { setHtml(target, res.html); }).catch(function (e) { if (e.message !== 'gate') showError(target); });
        },
        submitInto: function (target, form) {
            var method = (form.method || 'get').toUpperCase();
            var opts = { method: method };
            var url = form.action;
            if (method === 'POST') opts.body = new FormData(form);
            return request(url, opts).then(function (res) { setHtml(target, res.html); }).catch(function (e) { if (e.message !== 'gate') showError(target); });
        }
    };
})();
