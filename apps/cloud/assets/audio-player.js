/**
 * CustomAudioPlayer - A lightweight, theme-aware custom audio player
 * No external dependencies. Uses CSS variables for theming.
 *
 * Usage:
 *   const player = new CustomAudioPlayer(containerElement, audioSrc, options);
 *   player.load(audioSrc);
 *   player.destroy();
 */
class CustomAudioPlayer {
  constructor(container, src = '', options = {}) {
    this.container = container;
    this.audio = new Audio();
    this.playing = false;
    this.duration = 0;
    this.currentTime = 0;
    this.volume = 1;
    this.muted = false;
    this.seeking = false;
    this.listeners = {};
    this.waveformBars = [];
    this.waveformReady = false;
    this._waveformRequestId = 0;
    this.options = Object.assign({
      autoplay: false,
      showVolume: true,
      showSpeed: false,
      variant: 'default',
      barCount: 56,
    }, options);

    this._createDOM();
    this._bindAudioEvents();
    this._bindUIEvents();

    if (src) {
      this.load(src);
    }
  }

  load(src) {
    if (!src) return;
    this._waveformRequestId += 1;
    this.waveformReady = false;
    this.audio.src = src;
    this.audio.load();
    this._showState('loading');
    this._resetWaveform();
    this._loadWaveform(src, this._waveformRequestId);
  }

  play() {
    this.audio.play().catch(() => {});
  }

  pause() {
    this.audio.pause();
  }

  togglePlay() {
    if (this.playing) {
      this.pause();
    } else {
      this.play();
    }
  }

  destroy() {
    this.audio.pause();
    this.audio.src = '';
    this._waveformRequestId += 1;
    this._unbindAudioEvents();
    this._unbindUIEvents();
    this.container.innerHTML = '';
  }

  getElement() {
    return this.wrapper;
  }

  /* ---- DOM construction ---- */

  _createDOM() {
    this.container.innerHTML = '';

    this.wrapper = document.createElement('div');
    this.wrapper.className = 'custom-audio-player' + (this.options.variant === 'voice' ? ' cap-voice-message' : '');

    // Play button
    this.btnPlay = document.createElement('button');
    this.btnPlay.className = 'cap-btn cap-play-btn';
    this.btnPlay.setAttribute('aria-label', 'Play');
    this.btnPlay.innerHTML = `
      <svg class="cap-play-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <polygon points="8,5 19,12 8,19"/>
      </svg>
      <svg class="cap-pause-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="display:none">
        <rect x="6" y="4" width="4" height="16"/>
        <rect x="14" y="4" width="4" height="16"/>
      </svg>
    `;
    this.wrapper.appendChild(this.btnPlay);

    // Time current
    this.timeCurrent = document.createElement('span');
    this.timeCurrent.className = 'cap-time cap-time-current';
    this.timeCurrent.textContent = '0:00';
    this.wrapper.appendChild(this.timeCurrent);

    // Seek bar
    this.seekContainer = document.createElement('div');
    this.seekContainer.className = 'cap-seek-container';
    this.seekBar = document.createElement('div');
    this.seekBar.className = 'cap-seek-bar';
    this.waveform = document.createElement('div');
    this.waveform.className = 'cap-waveform';
    this.seekBar.appendChild(this.waveform);
    this.seekProgress = document.createElement('div');
    this.seekProgress.className = 'cap-seek-progress';
    this.seekThumb = document.createElement('div');
    this.seekThumb.className = 'cap-seek-thumb';
    this.seekBar.appendChild(this.seekProgress);
    this.seekBar.appendChild(this.seekThumb);
    this.seekContainer.appendChild(this.seekBar);
    this.wrapper.appendChild(this.seekContainer);

    // Time duration
    this.timeDuration = document.createElement('span');
    this.timeDuration.className = 'cap-time cap-time-duration';
    this.timeDuration.textContent = '0:00';
    this.wrapper.appendChild(this.timeDuration);

    // Volume
    if (this.options.showVolume) {
      this.btnVolume = document.createElement('button');
      this.btnVolume.className = 'cap-btn cap-volume-btn';
      this.btnVolume.setAttribute('aria-label', 'Volume');
      this.btnVolume.innerHTML = `
        <svg class="cap-vol-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/>
          <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
          <path d="M19.07 4.93a10 10 0 0 1 0 14.14" class="cap-vol-high"/>
        </svg>
        <svg class="cap-vol-mute-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:none">
          <polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/>
          <line x1="23" y1="9" x2="17" y2="15"/>
          <line x1="17" y1="9" x2="23" y2="15"/>
        </svg>
      `;
      this.wrapper.appendChild(this.btnVolume);

      this.volSlider = document.createElement('div');
      this.volSlider.className = 'cap-vol-slider';
      this.volBar = document.createElement('div');
      this.volBar.className = 'cap-vol-bar';
      this.volProgress = document.createElement('div');
      this.volProgress.className = 'cap-vol-progress';
      this.volThumb = document.createElement('div');
      this.volThumb.className = 'cap-vol-thumb';
      this.volBar.appendChild(this.volProgress);
      this.volBar.appendChild(this.volThumb);
      this.volSlider.appendChild(this.volBar);
      this.wrapper.appendChild(this.volSlider);
    }

    // Loading indicator
    this.loadingEl = document.createElement('div');
    this.loadingEl.className = 'cap-loading';
    this.loadingEl.innerHTML = `
      <div class="cap-loading-spinner">
        <div class="cap-loading-dot"></div>
        <div class="cap-loading-dot"></div>
        <div class="cap-loading-dot"></div>
      </div>
    `;
    this.loadingEl.style.display = 'none';
    this.wrapper.appendChild(this.loadingEl);

    this.container.appendChild(this.wrapper);

    // Add player styles if not already added
    this._injectStyles();
  }

  _injectStyles() {
    if (document.getElementById('cap-styles')) return;

    const style = document.createElement('style');
    style.id = 'cap-styles';
    style.textContent = `
      .custom-audio-player {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: var(--panel, #2a2a2a);
        border: 1px solid var(--border, #444);
        border-radius: 10px;
        transition: border-color 0.2s, box-shadow 0.2s;
        position: relative;
        min-height: 52px;
        box-sizing: border-box;
      }

      .custom-audio-player:hover {
        border-color: var(--accent-border, color-mix(in srgb, #85b503 62%, transparent));
      }

      .custom-audio-player .cap-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 8px;
        background: var(--bg, #1a1a1a);
        color: var(--fg, #fff);
        cursor: pointer;
        flex-shrink: 0;
        transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
      }

      .custom-audio-player .cap-btn:hover {
        background: color-mix(in srgb, var(--accent, #85b503) 18%, var(--bg, #1a1a1a));
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent, #85b503) 20%, transparent);
      }

      .custom-audio-player .cap-btn:active {
        transform: scale(0.92);
      }

      .custom-audio-player .cap-time {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        color: var(--muted, #888);
        min-width: 35px;
        user-select: none;
        flex-shrink: 0;
      }

      .custom-audio-player .cap-time-current {
        text-align: right;
      }

      .custom-audio-player .cap-time-duration {
        text-align: left;
      }

      .custom-audio-player .cap-seek-container {
        flex: 1;
        min-width: 0;
        cursor: pointer;
        padding: 6px 0;
        position: relative;
      }

      .custom-audio-player .cap-seek-bar {
        height: 34px;
        background: color-mix(in srgb, var(--bg, #1a1a1a) 74%, var(--panel, #2a2a2a));
        border: 1px solid color-mix(in srgb, var(--border, #444) 70%, transparent);
        border-radius: 10px;
        position: relative;
        transition: border-color 0.15s, background 0.15s;
        overflow: hidden;
      }

      .custom-audio-player .cap-seek-container:hover .cap-seek-bar {
        border-color: var(--accent-border, color-mix(in srgb, #85b503 62%, transparent));
      }

      .custom-audio-player .cap-seek-progress {
        height: 100%;
        background: var(--accent, #85b503);
        border-radius: 9px 0 0 9px;
        width: 0%;
        transition: width 0.1s linear;
        position: relative;
        opacity: 0.14;
        z-index: 1;
      }

      .custom-audio-player .cap-seek-thumb {
        position: absolute;
        right: -6px;
        top: 50%;
        transform: translateY(-50%);
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--accent, #85b503);
        box-shadow: 0 0 0 2px var(--bg, #1a1a1a);
        opacity: 0;
        transition: opacity 0.15s;
        pointer-events: none;
        z-index: 3;
      }

      .custom-audio-player .cap-seek-container:hover .cap-seek-thumb {
        opacity: 1;
      }

      .custom-audio-player .cap-waveform {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        gap: 2px;
        padding: 6px 8px;
        z-index: 2;
      }

      .custom-audio-player .cap-wave-bar {
        flex: 1 1 0;
        min-width: 2px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--fg, #fff) 22%, transparent);
        transform-origin: center center;
        transition: background 0.12s ease, opacity 0.12s ease, transform 0.12s ease;
        opacity: 0.9;
      }

      .custom-audio-player .cap-wave-bar.is-played {
        background: var(--accent, #85b503);
        opacity: 1;
      }

      .custom-audio-player .cap-wave-bar.is-empty {
        height: 4px !important;
        opacity: 0.35;
      }

      .custom-audio-player.cap-voice-message {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr) auto;
        grid-template-areas: "play wave time";
        gap: 8px;
        align-items: center;
        min-width: 0;
        width: 100%;
        max-width: min(320px, 100%);
        min-height: 48px;
        padding: 7px 10px;
        border: 0;
        border-radius: 18px;
        background: color-mix(in srgb, var(--panel, #2a2a2a) 82%, var(--bg, #1a1a1a));
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--border, #444) 55%, transparent);
        box-sizing: border-box;
        overflow: hidden;
      }

      .custom-audio-player.cap-voice-message .cap-play-btn {
        grid-area: play;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--accent, #85b503);
        color: #fff;
        box-shadow: 0 6px 18px color-mix(in srgb, var(--accent, #85b503) 28%, transparent);
        flex-shrink: 0;
      }

      .custom-audio-player.cap-voice-message .cap-play-btn:hover {
        background: color-mix(in srgb, var(--accent, #85b503) 88%, #fff);
      }

      .custom-audio-player.cap-voice-message .cap-time-current {
        display: none;
      }

      .custom-audio-player.cap-voice-message .cap-time-duration {
        grid-area: time;
        min-width: 34px;
        font-family: inherit;
        font-size: 11px;
        font-weight: 600;
        color: var(--muted, #888);
        text-align: right;
        flex-shrink: 0;
        white-space: nowrap;
      }

      .custom-audio-player.cap-voice-message .cap-seek-container {
        grid-area: wave;
        padding: 4px 0;
        min-width: 0;
        width: 100%;
        overflow: hidden;
      }

      .custom-audio-player.cap-voice-message .cap-seek-bar {
        height: 36px;
        border: 0;
        border-radius: 0;
        background: transparent;
        overflow: hidden;
        width: 100%;
        min-width: 0;
      }

      .custom-audio-player.cap-voice-message .cap-seek-progress {
        display: none;
      }

      .custom-audio-player.cap-voice-message .cap-seek-thumb {
        width: 8px;
        height: 8px;
        right: -4px;
        background: var(--accent, #85b503);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--panel, #2a2a2a) 92%, var(--bg, #1a1a1a));
      }

      .custom-audio-player.cap-voice-message .cap-waveform {
        gap: 2px;
        padding: 4px 0;
        overflow: hidden;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
      }

      .custom-audio-player.cap-voice-message .cap-wave-bar {
        flex: 1 1 0;
        min-width: 0;
        max-width: 100%;
        background: color-mix(in srgb, var(--fg, #fff) 26%, transparent);
        opacity: 1;
      }

      .custom-audio-player.cap-voice-message .cap-wave-bar.is-played {
        background: linear-gradient(180deg, color-mix(in srgb, var(--accent, #85b503) 78%, #fff), var(--accent, #85b503));
      }

      .custom-audio-player .cap-vol-slider {
        display: flex;
        align-items: center;
        width: 0;
        overflow: hidden;
        transition: width 0.25s ease, margin 0.25s ease;
        margin: 0;
        cursor: pointer;
      }

      .custom-audio-player .cap-vol-slider.open {
        width: 70px;
        margin-right: 4px;
      }

      .custom-audio-player .cap-vol-bar {
        width: 100%;
        height: 4px;
        background: var(--border, #444);
        border-radius: 2px;
        position: relative;
      }

      .custom-audio-player .cap-vol-progress {
        height: 100%;
        background: var(--muted, #888);
        border-radius: 2px;
        width: 100%;
        transition: width 0.05s linear;
      }

      .custom-audio-player .cap-vol-thumb {
        position: absolute;
        right: -5px;
        top: 50%;
        transform: translateY(-50%);
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--fg, #fff);
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        opacity: 0;
        transition: opacity 0.15s;
        pointer-events: none;
      }

      .custom-audio-player .cap-vol-bar:hover .cap-vol-thumb {
        opacity: 1;
      }

      .custom-audio-player .cap-loading {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--panel, #2a2a2a);
        border-radius: 10px;
        z-index: 2;
      }

      .custom-audio-player .cap-loading-spinner {
        display: flex;
        gap: 4px;
        align-items: center;
      }

      .custom-audio-player .cap-loading-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--accent, #85b503);
        animation: cap-bounce 0.6s infinite alternate;
      }

      .custom-audio-player .cap-loading-dot:nth-child(2) {
        animation-delay: 0.2s;
      }

      .custom-audio-player .cap-loading-dot:nth-child(3) {
        animation-delay: 0.4s;
      }

      @keyframes cap-bounce {
        from { opacity: 0.3; transform: translateY(0); }
        to   { opacity: 1; transform: translateY(-4px); }
      }

      /* Mobile adjustments */
      @media (max-width: 480px) {
        .custom-audio-player {
          flex-wrap: wrap;
          gap: 6px;
          padding: 8px 10px;
        }
        .custom-audio-player .cap-vol-slider.open {
          width: 50px;
        }
        .custom-audio-player .cap-time {
          font-size: 11px;
          min-width: 30px;
        }
      }
    `;
    document.head.appendChild(style);
  }

  /* ---- Audio events ---- */

  _bindAudioEvents() {
    this._audioHandlers = {
      play: () => { this.playing = true; this._updatePlayButton(); },
      pause: () => { this.playing = false; this._updatePlayButton(); },
      loadedmetadata: () => {
        this.duration = this.audio.duration || 0;
        this._updateDuration();
        this._showState('ready');
      },
      timeupdate: () => {
        if (this.seeking) return;
        this.currentTime = this.audio.currentTime || 0;
        this._updateProgress();
        this._updateTime();
      },
      ended: () => {
        this.playing = false;
        this._updatePlayButton();
        this.currentTime = 0;
        this.audio.currentTime = 0;
        this._updateProgress();
        this._updateTime();
      },
      waiting: () => { this._showState('loading'); },
      canplay: () => { this._showState('ready'); },
      volumechange: () => {
        this.volume = this.audio.volume;
        this.muted = this.audio.muted;
        this._updateVolumeUI();
      },
      error: () => {
        this._showState('error');
        this.btnPlay.style.opacity = '0.5';
      },
    };

    for (const [ev, fn] of Object.entries(this._audioHandlers)) {
      this.audio.addEventListener(ev, fn);
    }
  }

  _unbindAudioEvents() {
    if (this._audioHandlers) {
      for (const [ev, fn] of Object.entries(this._audioHandlers)) {
        this.audio.removeEventListener(ev, fn);
      }
    }
  }

  /* ---- UI events ---- */

  _bindUIEvents() {
    this._uiHandlers = [];

    // Play button
    const onPlayClick = () => this.togglePlay();
    this.btnPlay.addEventListener('click', onPlayClick);
    this._uiHandlers.push(() => this.btnPlay.removeEventListener('click', onPlayClick));

    // Seek bar
    const onSeekDown = (e) => {
      this.seeking = true;
      this._seekFromEvent(e);                    const onMove = (ev) => {
                        lastMoveEvent = ev;
                        this._seekFromEvent(ev);
                    };
                    const onUp = () => {
                        this.seeking = false;
                        const rect = this.seekBar.getBoundingClientRect();
                        const ratio = Math.max(0, Math.min(1, (this._getEventX(lastMoveEvent) - rect.left) / rect.width));
                        if (this.duration) {
                            this.audio.currentTime = ratio * this.duration;
                        }
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        document.removeEventListener('touchmove', onMove);
                        document.removeEventListener('touchend', onUp);
                    };
                    let lastMoveEvent = e;
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
      document.addEventListener('touchmove', onMove, { passive: false });
      document.addEventListener('touchend', onUp);
      this._uiHandlers.push(() => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('touchend', onUp);
      });
    };
    this.seekContainer.addEventListener('mousedown', onSeekDown);
    this.seekContainer.addEventListener('touchstart', (e) => { e.preventDefault(); onSeekDown(e); }, { passive: false });
    this._uiHandlers.push(() => {
      this.seekContainer.removeEventListener('mousedown', onSeekDown);
    });

    // Volume toggle + slider
    if (this.options.showVolume && this.btnVolume) {
      let volTimeout = null;
      const openVol = () => {
        this.volSlider.classList.add('open');
        clearTimeout(volTimeout);
      };
      const closeVol = () => {
        volTimeout = setTimeout(() => this.volSlider.classList.remove('open'), 1200);
      };

      this.btnVolume.addEventListener('click', () => {
        if (this.volSlider.classList.contains('open')) {
          // Toggle mute on click when slider is open
          this.audio.muted = !this.audio.muted;
        } else {
          openVol();
          closeVol();
        }
      });

      this.btnVolume.addEventListener('mouseenter', openVol);
      this.volSlider.addEventListener('mouseenter', openVol);
      this.volSlider.addEventListener('mouseleave', closeVol);

      const onVolDown = (e) => {
        this._volFromEvent(e);
        const onMove = (ev) => this._volFromEvent(ev);
        const onUp = () => {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
          document.removeEventListener('touchmove', onMove);
          document.removeEventListener('touchend', onUp);
        };
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('touchend', onUp);
        this._uiHandlers.push(() => {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
          document.removeEventListener('touchmove', onMove);
          document.removeEventListener('touchend', onUp);
        });
      };
      this.volBar.addEventListener('mousedown', onVolDown);
      this.volBar.addEventListener('touchstart', (e) => { e.preventDefault(); onVolDown(e); }, { passive: false });
      this._uiHandlers.push(() => {
        this.volBar.removeEventListener('mousedown', onVolDown);
      });
    }
  }

  _unbindUIEvents() {
    for (const cleanup of this._uiHandlers) {
      cleanup();
    }
    this._uiHandlers = [];
  }

  /* ---- Helpers ---- */

  _getEventX(e) {
    if (e.touches && e.touches.length > 0) return e.touches[0].clientX;
    if (e.changedTouches && e.changedTouches.length > 0) return e.changedTouches[0].clientX;
    return e.clientX;
  }

  _seekFromEvent(e) {
    const rect = this.seekBar.getBoundingClientRect();
    const x = this._getEventX(e) - rect.left;
    const ratio = Math.max(0, Math.min(1, x / rect.width));
    if (this.duration) {
      this.currentTime = ratio * this.duration;
      this._updateProgress();
      this._updateTime();
    }
  }

  _volFromEvent(e) {
    const rect = this.volBar.getBoundingClientRect();
    const x = this._getEventX(e) - rect.left;
    const ratio = Math.max(0, Math.min(1, x / rect.width));
    this.audio.muted = false;
    this.audio.volume = ratio;
    this._updateVolumeUI();
  }

  _formatTime(seconds) {
    if (!seconds || !isFinite(seconds)) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
  }

  _updatePlayButton() {
    this.btnPlay.querySelector('.cap-play-icon').style.display = this.playing ? 'none' : '';
    this.btnPlay.querySelector('.cap-pause-icon').style.display = this.playing ? '' : 'none';
    this.btnPlay.setAttribute('aria-label', this.playing ? 'Pause' : 'Play');
  }

  _updateProgress() {
    const pct = this.duration ? (this.currentTime / this.duration * 100) : 0;
    this.seekProgress.style.width = Math.min(pct, 100) + '%';
    this._updateWaveformProgress();
  }

  _updateTime() {
    this.timeCurrent.textContent = this._formatTime(this.currentTime);
  }

  _updateDuration() {
    this.timeDuration.textContent = this._formatTime(this.duration);
  }

  _updateVolumeUI() {
    if (!this.volProgress) return;
    const vol = this.muted ? 0 : this.volume;
    this.volProgress.style.width = (vol * 100) + '%';
    this.btnVolume.querySelector('.cap-vol-icon').style.display = this.muted ? 'none' : '';
    this.btnVolume.querySelector('.cap-vol-mute-icon').style.display = this.muted ? '' : 'none';
  }

  _showState(state) {
    this.loadingEl.style.display = state === 'loading' ? 'flex' : 'none';
    if (state === 'error') {
      this.btnPlay.style.opacity = '0.4';
    } else {
      this.btnPlay.style.opacity = '';
    }
  }

  async _loadWaveform(src, requestId) {
    if (!src || typeof window === 'undefined') return;
    const AudioContextCtor = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextCtor || typeof fetch !== 'function') return;
    try {
      const response = await fetch(src);
      const audioBufferRaw = await response.arrayBuffer();
      if (requestId !== this._waveformRequestId) return;

      const context = new AudioContextCtor();
      try {
        const buffer = await context.decodeAudioData(audioBufferRaw.slice(0));
        if (requestId !== this._waveformRequestId) return;
        const samples = this._extractWaveformSamples(buffer, this.options.barCount || 56);
        this._renderWaveform(samples);
      } finally {
        if (context.state !== 'closed') {
          context.close().catch(() => {});
        }
      }
    } catch (_) {
      this._resetWaveform();
    }
  }

  _extractWaveformSamples(buffer, barCount) {
    const raw = buffer.getChannelData(0);
    const samples = [];
    const blockSize = Math.max(1, Math.floor(raw.length / barCount));
    for (let i = 0; i < barCount; i += 1) {
      const start = i * blockSize;
      const end = Math.min(raw.length, start + blockSize);
      let peak = 0;
      for (let j = start; j < end; j += 1) {
        const value = Math.abs(raw[j] || 0);
        if (value > peak) peak = value;
      }
      samples.push(peak);
    }
    const max = Math.max(...samples, 0.0001);
    return samples.map((value) => {
      const normalized = Math.pow(value / max, 0.68);
      return Math.max(0.08, normalized);
    });
  }

  _renderWaveform(samples) {
    this.waveform.innerHTML = '';
    this.waveformBars = [];
    if (!Array.isArray(samples) || !samples.length) {
      this._resetWaveform();
      return;
    }
    const fragment = document.createDocumentFragment();
    for (const sample of samples) {
      const bar = document.createElement('span');
      bar.className = 'cap-wave-bar';
      const height = Math.max(4, Math.round(sample * 100));
      bar.style.height = `${Math.min(100, height)}%`;
      if (sample <= 0.1) bar.classList.add('is-empty');
      fragment.appendChild(bar);
      this.waveformBars.push(bar);
    }
    this.waveform.appendChild(fragment);
    this.waveformReady = true;
    this._updateWaveformProgress();
  }

  _resetWaveform() {
    if (!this.waveform) return;
    this.waveform.innerHTML = '';
    this.waveformBars = [];
    this.waveformReady = false;
    const fallbackCount = this.options.barCount || 40;
    const fragment = document.createDocumentFragment();
    for (let i = 0; i < fallbackCount; i += 1) {
      const bar = document.createElement('span');
      bar.className = 'cap-wave-bar is-empty';
      bar.style.height = `${4 + (i % 3) * 2}px`;
      fragment.appendChild(bar);
      this.waveformBars.push(bar);
    }
    this.waveform.appendChild(fragment);
    this._updateWaveformProgress();
  }

  _updateWaveformProgress() {
    if (!this.waveformBars || !this.waveformBars.length) return;
    const ratio = this.duration ? Math.max(0, Math.min(1, this.currentTime / this.duration)) : 0;
    const playedBars = Math.round(this.waveformBars.length * ratio);
    this.waveformBars.forEach((bar, index) => {
      bar.classList.toggle('is-played', index < playedBars);
    });
  }
}
