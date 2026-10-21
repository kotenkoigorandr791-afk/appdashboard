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

    // Time current
    this.timeCurrent = document.createElement('span');
    this.timeCurrent.className = 'cap-time cap-time-current';
    this.timeCurrent.textContent = '0:00';

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

    // Time duration
    this.timeDuration = document.createElement('span');
    this.timeDuration.className = 'cap-time cap-time-duration';
    this.timeDuration.textContent = '0:00';

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
    }


    const variant = this.options.variant;
    if (variant === 'voice') {
      // Voice message pill: play + waveform + (name / duration).
      const meta = document.createElement('div');
      meta.className = 'cap-voice-meta';
      const title = document.createElement('span');
      title.className = 'cap-voice-title';
      title.textContent = this.options.title || '';
      meta.appendChild(title);
      meta.appendChild(this.timeDuration);
      this.wrapper.append(this.btnPlay, this.seekContainer, meta);
    } else if (variant === 'line') {
      // Slim pill: play + progress line (+ speed) + name / time range.
      this.wrapper.classList.add('cap-line-player');
      const body = document.createElement('div');
      body.className = 'cap-line-body';
      const row = document.createElement('div');
      row.className = 'cap-line-row';
      row.appendChild(this.seekContainer);
      this.btnSpeed = document.createElement('button');
      this.btnSpeed.type = 'button';
      this.btnSpeed.className = 'cap-speed-btn';
      this.btnSpeed.textContent = '1\u00d7';
      const rates = [1, 1.25, 1.5, 2];
      this.btnSpeed.addEventListener('click', (e) => {
        e.stopPropagation();
        const index = rates.indexOf(this.audio.playbackRate);
        const next = rates[(index + 1) % rates.length];
        this.audio.playbackRate = next;
        this.btnSpeed.textContent = next + '\u00d7';
      });
      row.appendChild(this.btnSpeed);
      const times = document.createElement('div');
      times.className = 'cap-line-times';
      const name = document.createElement('span');
      name.className = 'cap-line-name';
      name.textContent = this.options.title || '';
      const range = document.createElement('span');
      range.className = 'cap-line-range';
      range.append(this.timeCurrent, document.createTextNode(' / '), this.timeDuration);
      times.append(name, range);
      body.append(row, times);
      this.wrapper.append(this.btnPlay, body);
    } else {
      this.wrapper.appendChild(this.btnPlay);
      this.wrapper.appendChild(this.timeCurrent);
      this.wrapper.appendChild(this.seekContainer);
      this.wrapper.appendChild(this.timeDuration);
      if (this.btnVolume && this.options.showVolume) this.wrapper.appendChild(this.btnVolume);
      if (this.volSlider && this.options.showVolume) this.wrapper.appendChild(this.volSlider);
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
      @keyframes cap-spin {
        to { transform: rotate(360deg); }
      }

      @keyframes cap-pulse-glow {
        0%, 100% { box-shadow: 0 4px 20px rgba(108,99,255,0.15), 0 0 0 1px rgba(255,255,255,0.04); }
        50% { box-shadow: 0 8px 32px rgba(108,99,255,0.28), 0 0 0 1px rgba(255,255,255,0.06); }
      }

      @keyframes cap-bounce {
        from { opacity: 0.3; transform: translateY(0); }
        to   { opacity: 1; transform: translateY(-4px); }
      }

      @keyframes cap-ripple {
        0% { transform: scale(0.8); opacity: 0.6; }
        100% { transform: scale(2.4); opacity: 0; }
      }

      .custom-audio-player {
        --cap-bg: #11182d;
        --cap-card: #141e36;
        --cap-gradient-1: #6c63ff;
        --cap-gradient-2: #00d4ff;
        --cap-accent: var(--accent, #6c63ff);
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: var(--cap-card);
        border: 1px solid rgba(108,99,255,0.1);
        border-radius: 16px;
        transition: border-color 0.3s, box-shadow 0.3s, transform 0.2s;
        position: relative;
        min-height: 60px;
        box-sizing: border-box;
        overflow: hidden;
        box-shadow:
          0 4px 20px rgba(0,0,0,0.25),
          inset 0 1px rgba(255,255,255,0.04);
        animation: cap-pulse-glow 4s ease-in-out infinite;
      }

      .custom-audio-player::before {
        content: "";
        position: absolute;
        inset: -120px;
        background: conic-gradient(from 180deg, var(--cap-gradient-1), var(--cap-gradient-2), var(--cap-gradient-1));
        animation: cap-spin 6s linear infinite;
        opacity: 0.07;
        pointer-events: none;
        transition: opacity 0.3s;
      }

      .custom-audio-player:hover::before {
        opacity: 0.12;
      }

      .custom-audio-player:hover {
        border-color: rgba(108,99,255,0.25);
        transform: translateY(-1px);
      }

      .custom-audio-player .cap-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 12px;
        background: #1a2442;
        color: #fff;
        cursor: pointer;
        flex-shrink: 0;
        transition: background 0.25s, transform 0.25s, box-shadow 0.25s;
        position: relative;
        z-index: 1;
      }

      .custom-audio-player .cap-btn:hover {
        background: var(--cap-gradient-1);
        transform: translateY(-3px);
        box-shadow: 0 6px 18px rgba(108,99,255,0.35);
      }

      .custom-audio-player .cap-btn:active {
        transform: scale(0.92) translateY(0);
      }

      .custom-audio-player .cap-play-btn {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--cap-gradient-1), var(--cap-gradient-2));
        box-shadow: 0 4px 16px rgba(108,99,255,0.3);
        font-size: 18px;
      }

      .custom-audio-player .cap-play-btn:hover {
        background: linear-gradient(135deg, #7d75ff, #1ae0ff);
        transform: translateY(-4px) scale(1.04);
        box-shadow: 0 8px 28px rgba(108,99,255,0.45);
      }

      .custom-audio-player .cap-play-btn:active {
        transform: scale(0.94);
      }

      .custom-audio-player .cap-play-btn .cap-play-icon,
      .custom-audio-player .cap-play-btn .cap-pause-icon {
        transition: transform 0.2s ease;
      }

      .custom-audio-player .cap-btn:active .cap-play-icon,
      .custom-audio-player .cap-btn:active .cap-pause-icon {
        transform: scale(0.88);
      }

      .custom-audio-player .cap-time {
        font-family: 'Inter', 'SF Pro Display', -apple-system, sans-serif;
        font-size: 12px;
        font-weight: 500;
        color: rgba(255,255,255,0.45);
        min-width: 38px;
        user-select: none;
        flex-shrink: 0;
        position: relative;
        z-index: 1;
        letter-spacing: 0.02em;
      }

      .custom-audio-player .cap-time-current {
        text-align: right;
        color: rgba(255,255,255,0.6);
      }

      .custom-audio-player .cap-time-duration {
        text-align: left;
      }

      .custom-audio-player .cap-seek-container {
        flex: 1;
        min-width: 0;
        cursor: pointer;
        padding: 8px 0;
        position: relative;
        z-index: 1;
      }

      .custom-audio-player .cap-seek-bar {
        height: 6px;
        background: #1f2947;
        border-radius: 999px;
        position: relative;
        transition: height 0.2s ease, background 0.2s ease;
        overflow: hidden;
        border: none;
      }

      .custom-audio-player .cap-seek-container:hover .cap-seek-bar {
        height: 8px;
        background: #253254;
      }

      .custom-audio-player .cap-seek-progress {
        height: 100%;
        background: linear-gradient(90deg, var(--cap-gradient-1), var(--cap-gradient-2));
        border-radius: 999px;
        width: 0%;
        transition: width 0.1s linear;
        position: relative;
        opacity: 1;
        z-index: 1;
        box-shadow: 0 0 12px rgba(108,99,255,0.4);
      }

      .custom-audio-player .cap-seek-thumb {
        position: absolute;
        right: -7px;
        top: 50%;
        transform: translateY(-50%) scale(0);
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 0 0 3px var(--cap-gradient-1), 0 2px 8px rgba(0,0,0,0.3);
        opacity: 0;
        transition: opacity 0.2s, transform 0.2s;
        pointer-events: none;
        z-index: 3;
      }

      .custom-audio-player .cap-seek-container:hover .cap-seek-thumb {
        opacity: 1;
        transform: translateY(-50%) scale(1);
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
        background: rgba(255,255,255,0.15);
        transform-origin: center center;
        transition: background 0.15s ease, opacity 0.15s ease, transform 0.15s ease;
        opacity: 0.8;
      }

      .custom-audio-player .cap-wave-bar.is-played {
        background: linear-gradient(180deg, rgba(108,99,255,0.8), var(--cap-gradient-1));
        opacity: 1;
      }

      .custom-audio-player .cap-wave-bar.is-empty {
        height: 3px !important;
        opacity: 0.3;
      }

      .custom-audio-player.cap-voice-message {
        --cap-pill-bg: var(--panel, #ffffff);
        --cap-pill-fg: var(--fg, #1a1d21);
        --cap-pill-border: var(--border, #e4e7ec);
        display: flex;
        align-items: center;
        gap: 10px;
        width: auto;
        max-width: min(320px, 100%);
        min-height: 0;
        padding: 8px 14px 8px 8px;
        border-radius: 999px;
        background: var(--cap-pill-bg);
        color: var(--cap-pill-fg);
        border: 1px solid var(--cap-pill-border);
        box-shadow: 0 2px 8px rgba(16, 24, 40, 0.06);
        box-sizing: border-box;
        overflow: hidden;
        animation: none;
      }

      .custom-audio-player.cap-voice-message::before { display: none; }

      .custom-audio-player.cap-voice-message .cap-play-btn {
        width: 34px;
        height: 34px;
        min-width: 34px;
        border-radius: 50%;
        background: var(--cap-pill-fg);
        color: var(--cap-pill-bg);
        box-shadow: none;
        flex: 0 0 auto;
      }

      .custom-audio-player.cap-voice-message .cap-play-btn:hover {
        transform: scale(1.06);
        box-shadow: none;
      }

      .custom-audio-player.cap-voice-message .cap-time-current { display: none; }

      .custom-audio-player.cap-voice-message .cap-seek-container {
        flex: 1 1 auto;
        min-width: 0;
        width: auto;
        height: 26px;
        padding: 0;
        overflow: hidden;
      }

      .custom-audio-player.cap-voice-message .cap-seek-bar {
        height: 26px;
        border: 0;
        border-radius: 0;
        background: transparent;
        overflow: hidden;
        width: 100%;
      }

      .custom-audio-player.cap-voice-message .cap-seek-bar:hover { height: 26px; background: transparent; }
      .custom-audio-player.cap-voice-message .cap-seek-progress,
      .custom-audio-player.cap-voice-message .cap-seek-thumb { display: none; }

      .custom-audio-player.cap-voice-message .cap-waveform {
        position: static;
        inset: auto;
        display: flex;
        align-items: center;
        gap: 2.5px;
        height: 26px;
        padding: 0;
        overflow: hidden;
        min-width: 0;
        width: 100%;
      }

      .custom-audio-player.cap-voice-message .cap-wave-bar {
        flex: 1 1 0;
        min-width: 3px;
        max-width: 3px;
        border-radius: 2px;
        background: currentColor;
        opacity: 0.35;
      }

      .custom-audio-player.cap-voice-message .cap-wave-bar.is-played {
        background: currentColor;
        opacity: 1;
      }

      .custom-audio-player.cap-voice-message .cap-wave-bar.is-empty { opacity: 0.25; }

      .custom-audio-player.cap-voice-message .cap-voice-meta {
        display: flex;
        flex-direction: column;
        gap: 1px;
        align-items: flex-end;
        margin-left: auto;
        flex: 0 0 auto;
        min-width: 0;
      }

      .custom-audio-player.cap-voice-message .cap-voice-title {
        display: block;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        max-width: 110px;
        color: inherit;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .custom-audio-player.cap-voice-message .cap-time-duration {
        grid-area: auto;
        min-width: 0;
        font-size: 11px;
        font-weight: 400;
        color: inherit;
        opacity: 0.6;
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
      }

      .custom-audio-player.cap-line-player {
        --cap-line-bg: var(--panel, #ffffff);
        --cap-line-fg: var(--fg, #1a1d21);
        --cap-line-border: var(--border, #e4e7ec);
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        max-width: min(340px, 100%);
        padding: 8px 16px 8px 8px;
        border-radius: 999px;
        background: var(--cap-line-bg);
        color: var(--cap-line-fg);
        border: 1px solid var(--cap-line-border);
        box-shadow: 0 2px 8px rgba(16, 24, 40, 0.06);
        box-sizing: border-box;
        min-height: 0;
        animation: none;
        overflow: visible;
      }

      .custom-audio-player.cap-line-player::before { display: none; }

      .custom-audio-player.cap-line-player .cap-play-btn {
        width: 34px;
        height: 34px;
        min-width: 34px;
        border-radius: 50%;
        background: var(--cap-line-fg);
        color: var(--cap-line-bg);
        box-shadow: none;
        flex: 0 0 auto;
      }

      .custom-audio-player.cap-line-player .cap-play-btn:hover {
        transform: scale(1.06);
        box-shadow: none;
      }

      .custom-audio-player .cap-line-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
      }

      .custom-audio-player .cap-line-row {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
      }

      .custom-audio-player.cap-line-player .cap-seek-container {
        flex: 1;
        min-width: 0;
        height: 16px;
        padding: 0;
        display: flex;
        align-items: center;
        position: relative;
      }

      .custom-audio-player.cap-line-player .cap-seek-bar {
        width: 100%;
        height: 3px;
        background: var(--cap-line-border);
        border: none;
        border-radius: 2px;
        overflow: visible;
      }

      .custom-audio-player.cap-line-player .cap-seek-container:hover .cap-seek-bar {
        height: 3px;
        background: var(--cap-line-border);
      }

      .custom-audio-player.cap-line-player .cap-waveform { display: none; }

      .custom-audio-player.cap-line-player .cap-seek-progress {
        position: absolute;
        left: 0;
        top: 0;
        height: 3px;
        border-radius: 2px;
        background: var(--cap-line-fg);
        opacity: 1;
      }

      .custom-audio-player.cap-line-player .cap-seek-thumb {
        position: absolute;
        top: 50%;
        right: auto;
        left: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--cap-line-fg);
        border: 2px solid var(--cap-line-bg);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        transform: translate(-50%, -50%) scale(0);
        opacity: 0;
        transition: opacity 0.15s ease;
      }

      .custom-audio-player.cap-line-player:hover .cap-seek-thumb,
      .custom-audio-player.cap-line-player .cap-seek-container:hover .cap-seek-thumb {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
      }

      .custom-audio-player .cap-speed-btn {
        flex: 0 0 auto;
        border: none;
        cursor: pointer;
        padding: 2px 7px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--cap-line-fg) 8%, transparent);
        color: var(--cap-line-fg);
        font-size: 10px;
        font-weight: 600;
        line-height: 1.4;
        opacity: 0.75;
      }

      .custom-audio-player .cap-speed-btn:hover { opacity: 1; }

      .custom-audio-player .cap-line-times {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        font-size: 10px;
        line-height: 1.2;
        opacity: 0.55;
        font-variant-numeric: tabular-nums;
      }

      .custom-audio-player .cap-line-name {
        font-size: 11px;
        font-weight: 600;
        opacity: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .custom-audio-player .cap-line-range { white-space: nowrap; }

      .custom-audio-player .cap-vol-slider {
        display: flex;
        align-items: center;
        width: 0;
        overflow: hidden;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin 0.3s ease;
        margin: 0;
        cursor: pointer;
        position: relative;
        z-index: 1;
      }

      .custom-audio-player .cap-vol-slider.open {
        width: 72px;
        margin-right: 4px;
      }

      .custom-audio-player .cap-vol-bar {
        width: 100%;
        height: 4px;
        background: #1f2947;
        border-radius: 2px;
        position: relative;
      }

      .custom-audio-player .cap-vol-progress {
        height: 100%;
        background: linear-gradient(90deg, var(--cap-gradient-1), var(--cap-gradient-2));
        border-radius: 2px;
        width: 100%;
        transition: width 0.05s linear;
        box-shadow: 0 0 8px rgba(108,99,255,0.3);
      }

      .custom-audio-player .cap-vol-thumb {
        position: absolute;
        right: -5px;
        top: 50%;
        transform: translateY(-50%);
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 0 0 2px var(--cap-gradient-1), 0 2px 6px rgba(0,0,0,0.3);
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
        background: rgba(17,24,45,0.92);
        border-radius: 16px;
        z-index: 2;
        backdrop-filter: blur(4px);
      }

      .custom-audio-player .cap-loading-spinner {
        display: flex;
        gap: 5px;
        align-items: center;
      }

      .custom-audio-player .cap-loading-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--cap-gradient-1), var(--cap-gradient-2));
        animation: cap-bounce 0.6s infinite alternate;
        box-shadow: 0 0 8px rgba(108,99,255,0.5);
      }

      .custom-audio-player .cap-loading-dot:nth-child(2) {
        animation-delay: 0.2s;
      }

      .custom-audio-player .cap-loading-dot:nth-child(3) {
        animation-delay: 0.4s;
      }

      /* Mobile adjustments */
      @media (max-width: 480px) {
        .custom-audio-player {
          flex-wrap: wrap;
          gap: 8px;
          padding: 10px 12px;
          border-radius: 14px;
        }
        .custom-audio-player .cap-vol-slider.open {
          width: 52px;
        }
        .custom-audio-player .cap-time {
          font-size: 11px;
          min-width: 32px;
        }
        .custom-audio-player .cap-play-btn {
          width: 44px;
          height: 44px;
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
    const clamped = Math.min(pct, 100);
    this.seekProgress.style.width = clamped + '%';
    if (this.options.variant === 'line' && this.seekThumb) {
      this.seekThumb.style.left = clamped + '%';
    }
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
