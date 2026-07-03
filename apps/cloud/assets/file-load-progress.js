(function (global) {
    const FileBlobCache = {
        _map: new Map(),
        _key(url) {
            return String(url || '');
        },
        get(url) {
            return this._map.get(this._key(url)) || null;
        },
        fetch(url, onProgress) {
            const key = this._key(url);
            let entry = this._map.get(key);
            if (!entry) {
                entry = { blob: null, objectUrl: null, promise: null };
                this._map.set(key, entry);
            }
            if (entry.blob) {
                return Promise.resolve(entry.blob);
            }
            if (entry.promise) {
                return entry.promise;
            }
            entry.promise = new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.responseType = 'blob';
                xhr.onprogress = (e) => {
                    if (onProgress && e.lengthComputable && e.total > 0) {
                        onProgress(e.loaded, e.total);
                    }
                };
                xhr.onload = () => {
                    entry.promise = null;
                    if (xhr.status >= 200 && xhr.status < 300) {
                        entry.blob = xhr.response;
                        entry.objectUrl = URL.createObjectURL(entry.blob);
                        resolve(entry.blob);
                    } else {
                        this._map.delete(key);
                        reject(new Error('HTTP ' + xhr.status));
                    }
                };
                xhr.onerror = () => {
                    entry.promise = null;
                    this._map.delete(key);
                    reject(new Error('Network error'));
                };
                xhr.send();
            });
            return entry.promise;
        },
        getObjectUrl(url) {
            const entry = this.get(url);
            return entry && entry.objectUrl ? entry.objectUrl : null;
        }
    };
    global.FileBlobCache = FileBlobCache;

    function FileLoadProgress(options) {
        options = options || {};
        this.color = (options.color || '#85b503').replace(/^#/, '');
        this.theme = options.theme === 'light' ? 'light' : 'dark';
        this.mount = options.mount || document.body;
        this.display = 0;
        this.target = 0;
        this.raf = null;
        this.creepTimer = null;
        this.visible = false;
        if (options.barEl && options.textEl) {
            this.root = options.trackEl || options.barEl.parentElement;
            this.bar = options.barEl;
            this.text = options.textEl;
        } else {
            this._build();
        }
    }
    FileLoadProgress.prototype._build = function () {
        const light = this.theme === 'light';
        const wrap = document.createElement('div');
        wrap.className = 'file-load-overlay';
        wrap.setAttribute('aria-hidden', 'true');
        wrap.style.cssText = 'position:fixed;inset:0;z-index:100000;pointer-events:none;opacity:0;transition:opacity 0.3s ease;';
        const barTrack = document.createElement('div');
        barTrack.style.cssText = 'position:fixed;top:0;left:0;right:0;height:2px;background:#1a1a1a;overflow:hidden;';
        const barFill = document.createElement('div');
        barFill.className = 'file-load-bar';
        barFill.style.cssText = 'height:100%;width:0%;background:#' + this.color + ';box-shadow:0 0 3px #' + this.color + ';transition:none;';
        barTrack.appendChild(barFill);
        const text = document.createElement('div');
        text.className = 'file-load-text';
        text.textContent = '0%';
        text.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);font-size:36px;font-weight:300;font-style:italic;opacity:0.8;z-index:1;padding:8px 20px;border-radius:10px;pointer-events:none;color:' + (light ? '#333' : '#fff') + ';background:' + (light ? 'rgba(255,255,255,0.65)' : 'rgba(0,0,0,0.5)') + ';text-shadow:0 2px 4px rgba(0,0,0,0.2);';
        wrap.appendChild(barTrack);
        wrap.appendChild(text);
        this.mount.appendChild(wrap);
        this.root = wrap;
        this.bar = barFill;
        this.text = text;
    };
    FileLoadProgress.prototype._paint = function () {
        const p = Math.round(this.display);
        this.bar.style.width = p + '%';
        this.text.textContent = p + '%';
    };
    FileLoadProgress.prototype._animate = function () {
        const diff = this.target - this.display;
        if (Math.abs(diff) < 0.4) {
            this.display = this.target;
        } else {
            this.display += diff * 0.18;
        }
        this._paint();
        if (Math.abs(this.display - this.target) >= 0.4 || (this.visible && this.target < 100)) {
            this.raf = requestAnimationFrame(this._animate.bind(this));
        } else {
            this.raf = null;
        }
    };
    FileLoadProgress.prototype._kickAnim = function () {
        if (!this.raf) this.raf = requestAnimationFrame(this._animate.bind(this));
    };
    FileLoadProgress.prototype.set = function (percent) {
        const p = Math.max(0, Math.min(100, Number(percent) || 0));
        this.target = Math.max(this.target, p);
        this._kickAnim();
    };
    FileLoadProgress.prototype.show = function () {
        this.visible = true;
        this.root.style.opacity = '1';
        this.root.style.pointerEvents = 'none';
    };
    FileLoadProgress.prototype.hide = function (delay) {
        const ms = delay == null ? 300 : delay;
        const done = () => {
            this.visible = false;
            this.root.style.opacity = '0';
            this.stopCreep();
            if (this.raf) {
                cancelAnimationFrame(this.raf);
                this.raf = null;
            }
        };
        if (ms > 0) setTimeout(done, ms);
        else done();
    };
    FileLoadProgress.prototype.reset = function () {
        this.display = 0;
        this.target = 0;
        this._paint();
    };
    FileLoadProgress.prototype.complete = function () {
        this.set(100);
        const finish = () => this.hide(280);
        if (this.raf) {
            const wait = setInterval(() => {
                if (!this.raf || this.display >= 99.5) {
                    clearInterval(wait);
                    finish();
                }
            }, 50);
            setTimeout(finish, 1200);
        } else {
            finish();
        }
    };
    FileLoadProgress.prototype.fail = function (message) {
        this.stopCreep();
        this.bar.style.background = '#ff4444';
        if (message) this.text.textContent = message;
        setTimeout(() => this.hide(0), 2000);
    };
    FileLoadProgress.prototype.startCreep = function (maxPercent, step) {
        this.stopCreep();
        const cap = maxPercent == null ? 88 : maxPercent;
        const inc = step == null ? 0.35 : step;
        this.creepTimer = setInterval(() => {
            if (this.target >= cap) return;
            this.set(Math.min(cap, this.target + inc));
        }, 120);
    };
    FileLoadProgress.prototype.stopCreep = function () {
        if (this.creepTimer) {
            clearInterval(this.creepTimer);
            this.creepTimer = null;
        }
    };
    FileLoadProgress.prototype.fetchBlob = function (url, options) {
        const self = this;
        options = options || {};
        const downloadWeight = options.downloadWeight == null ? 100 : options.downloadWeight;
        const cached = FileBlobCache.get(url);
        if (cached && cached.blob) {
            self.set(downloadWeight);
            return Promise.resolve(cached.blob);
        }
        self.show();
        self.reset();
        self.startCreep(Math.min(downloadWeight - 2, 85));
        return FileBlobCache.fetch(url, function (loaded, total) {
            self.stopCreep();
            const pct = (loaded / total) * downloadWeight;
            self.set(pct);
        }).then(function (blob) {
            self.set(downloadWeight);
            return blob;
        }).catch(function (err) {
            self.fail('Error');
            throw err;
        });
    };
    global.FileLoadProgress = FileLoadProgress;
})(typeof window !== 'undefined' ? window : globalThis);
