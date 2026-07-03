async function getAudioFileInfo(path, rootOverride) {
    try {
        const params = { path: path };
        if (rootOverride) params.root = rootOverride;
        const response = await api('audio_info', params, 'GET');
        if (response && (response.size || response.duration)) {
            return {
                size: response.size || 0,
                duration: response.duration || 0,
                bitrate: response.bitrate || 0,
                format: response.format || 'audio'
            };
        }
        return await getAudioDurationViaAPI(path);
    } catch (err) {
        console.error('Failed to get audio info:', err);
        try {
            const fileResp = await api('open', { path }, 'GET');
            return {
                size: fileResp.size || 0,
                duration: 0,
                bitrate: 0,
                format: path.split('.').pop().toLowerCase()
            };
        } catch (e) {
            return { size: 0, duration: 0, bitrate: 0, format: 'audio' };
        }
    }
}
async function getAudioDurationViaAPI(path) {
    return new Promise((resolve) => {
        const audioUrl = `api.php?action=raw_audio&path=${encodeURIComponent(path)}&root=${getActiveTabRoot()}`;
        const audio = new Audio();
        audio.addEventListener('loadedmetadata', () => {
            resolve({
                size: 0,
                duration: audio.duration || 0,
                bitrate: 0,
                format: path.split('.').pop().toLowerCase()
            });
        });
        audio.addEventListener('error', () => {
            resolve({ size: 0, duration: 0, bitrate: 0, format: 'audio' });
        });
        setTimeout(() => {
            resolve({ size: 0, duration: 0, bitrate: 0, format: 'audio' });
        }, 3000);
        audio.src = audioUrl;
    });
}
function formatTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
function initHowlerPlayer(playerElement) {
    const audioUrl = playerElement.dataset.audioUrl;
    const audioPath = playerElement.dataset.audioPath;
    const filename = playerElement.dataset.filename;
    if (typeof Howl === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js';
        script.onload = () => createHowlerInstance();
        document.head.appendChild(script);
    } else {
        createHowlerInstance();
    }
    function createHowlerInstance() {
        const sound = new Howl({
            src: [audioUrl],
            html5: true,
            preload: true,
            format: audioPath.split('.').pop().toLowerCase(),
            onload: function() {
                const duration = sound.duration();
                const durationElement = playerElement.querySelector('.duration');
                if (durationElement) {
                    durationElement.textContent = formatTime(duration);
                }
                const infoElement = playerElement.querySelector('.audio-file-info');
                if (infoElement && duration > 0) {
                    fetch(`api.php?action=file_info&path=${encodeURIComponent(audioPath)}`)
                        .then(res => res.json())
                        .then(fileInfo => {
                            infoElement.textContent = `${formatBytes(fileInfo.size || 0)} • ${formatTime(duration)}`;
                        })
                        .catch(() => {
                            infoElement.textContent = `${formatTime(duration)}`;
                        });
                }
                initMediaSession(sound, filename, duration);
            },
            onloaderror: function(id, error) {
                console.error('Error loading audio:', error);
                flashStatus('Failed to load audio file');
            },
            onplay: function() {
                updateMediaSessionPosition(sound);
            },
            onpause: function() {
                updateMediaSessionPosition(sound);
            },
            onstop: function() {
                updateMediaSessionPosition(sound);
            },
            onseek: function() {
                updateMediaSessionPosition(sound);
            }
        });
        const playPauseBtn = playerElement.querySelector('.play-pause');
        const rewindBtn = playerElement.querySelector('.rewind');
        const forwardBtn = playerElement.querySelector('.forward');
        const progressBar = playerElement.querySelector('.audio-progress-bar');
        const progressHandle = playerElement.querySelector('.audio-progress-handle');
        const progressBg = playerElement.querySelector('.audio-progress-bar').parentElement;
        const currentTimeEl = playerElement.querySelector('.current-time');
        const volumeSlider = playerElement.querySelector('.volume-slider');
        let isPlaying = false;
        let updateInterval;
        function initMediaSession(sound, title, duration) {
            if ('mediaSession' in navigator) {
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: title,
                    artist: 'Audio Player',
                    artwork: [
                        { src: 'data:image/svg+xml;base64,' + btoa(`
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                                <rect width="100" height="100" fill="#${window.APP_CONFIG.color ? window.APP_CONFIG.color.replace('#', '') : '6200ee'}"/>
                                <path d="M30 30 L70 50 L30 70 Z" fill="white"/>
                            </svg>
                        `), sizes: '100x100', type: 'image/svg+xml' }
                    ]
                });
                navigator.mediaSession.setActionHandler('play', function() {
                    sound.play();
                    updatePlayPauseState(true);
                });
                navigator.mediaSession.setActionHandler('pause', function() {
                    sound.pause();
                    updatePlayPauseState(false);
                });
                navigator.mediaSession.setActionHandler('seekbackward', function(details) {
                    const seekTime = details.seekOffset || 10;
                    const newTime = Math.max(0, sound.seek() - seekTime);
                    sound.seek(newTime);
                    updateProgress();
                    updateMediaSessionPosition(sound);
                });
                navigator.mediaSession.setActionHandler('seekforward', function(details) {
                    const seekTime = details.seekOffset || 10;
                    const duration = sound.duration();
                    const newTime = Math.min(duration, sound.seek() + seekTime);
                    sound.seek(newTime);
                    updateProgress();
                    updateMediaSessionPosition(sound);
                });
                navigator.mediaSession.setActionHandler('seekto', function(details) {
                    if (details.fastSeek && 'fastSeek' in sound) {
                        sound.fastSeek(details.seekTime);
                    } else {
                        sound.seek(details.seekTime);
                    }
                    updateProgress();
                    updateMediaSessionPosition(sound);
                });
            }
        }
        function updateMediaSessionPosition(sound) {
            if ('mediaSession' in navigator) {
                const duration = sound.duration();
                const currentTime = sound.seek();
                navigator.mediaSession.setPositionState({
                    duration: duration,
                    playbackRate: sound.rate(),
                    position: currentTime
                });
            }
        }
        function updatePlayPauseState(playing) {
            isPlaying = playing;
            if (playing) {
                playPauseBtn.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="6" y="5" width="4" height="14" fill="currentColor"/>
                        <rect x="14" y="5" width="4" height="14" fill="currentColor"/>
                    </svg>
                `;
                if (updateInterval) clearInterval(updateInterval);
                updateInterval = setInterval(updateProgress, 100);
            } else {
                playPauseBtn.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 5V19L19 12L8 5Z" fill="currentColor"/>
                    </svg>
                `;
                if (updateInterval) {
                    clearInterval(updateInterval);
                    updateInterval = null;
                }
            }
            if ('mediaSession' in navigator) {
                navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
            }
        }
        function updateProgress() {
            if (!sound || !sound.playing()) return;
            const currentTime = sound.seek();
            const duration = sound.duration();
            const progressPercent = duration > 0 ? (currentTime / duration) * 100 : 0;
            progressBar.style.width = `${progressPercent}%`;
            progressHandle.style.left = `${progressPercent}%`;
            progressHandle.style.display = 'block';
            if (currentTimeEl) {
                currentTimeEl.textContent = formatTime(currentTime);
            }
            updateMediaSessionPosition(sound);
        }
        playPauseBtn.addEventListener('click', function() {
            if (!isPlaying) {
                sound.play();
                updatePlayPauseState(true);
            } else {
                sound.pause();
                updatePlayPauseState(false);
            }
        });
        rewindBtn.addEventListener('click', function() {
            if (sound) {
                const newTime = Math.max(0, sound.seek() - 10);
                sound.seek(newTime);
                updateProgress();
                updateMediaSessionPosition(sound);
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
            }
        });
        forwardBtn.addEventListener('click', function() {
            if (sound) {
                const duration = sound.duration();
                const newTime = Math.min(duration, sound.seek() + 10);
                sound.seek(newTime);
                updateProgress();
                updateMediaSessionPosition(sound);
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
            }
        });
        progressBg.addEventListener('click', function(e) {
            if (!sound) return;
            const rect = this.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const percent = Math.min(Math.max(clickX / rect.width, 0), 1);
            const duration = sound.duration();
            const newTime = percent * duration;
            sound.seek(newTime);
            updateProgress();
            updateMediaSessionPosition(sound);
        });
        progressHandle.addEventListener('mousedown', startDrag);
        progressHandle.addEventListener('touchstart', startDrag);
        let isDragging = false;
        function startDrag(e) {
            isDragging = true;
            document.addEventListener('mousemove', handleDrag);
            document.addEventListener('touchmove', handleDrag);
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchend', stopDrag);
            e.preventDefault();
        }
        function handleDrag(e) {
            if (!isDragging || !sound) return;
            const clientX = e.clientX || (e.touches && e.touches[0].clientX);
            const rect = progressBg.getBoundingClientRect();
            const clickX = clientX - rect.left;
            const percent = Math.min(Math.max(clickX / rect.width, 0), 1);
            progressBar.style.width = `${percent * 100}%`;
            progressHandle.style.left = `${percent * 100}%`;
            const duration = sound.duration();
            const currentTime = percent * duration;
            if (currentTimeEl) {
                currentTimeEl.textContent = formatTime(currentTime);
            }
            e.preventDefault();
        }
        function stopDrag(e) {
            if (!isDragging || !sound) return;
            isDragging = false;
            document.removeEventListener('mousemove', handleDrag);
            document.removeEventListener('touchmove', handleDrag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchend', stopDrag);
            const rect = progressBg.getBoundingClientRect();
            const clientX = e.clientX || (e.changedTouches && e.changedTouches[0].clientX);
            const clickX = clientX - rect.left;
            const percent = Math.min(Math.max(clickX / rect.width, 0), 1);
            const duration = sound.duration();
            const newTime = percent * duration;
            sound.seek(newTime);
            updateProgress();
            updateMediaSessionPosition(sound);
            e.preventDefault();
        }
        volumeSlider.addEventListener('input', function() {
            if (sound) {
                const volume = parseInt(this.value, 10) / 100;
                sound.volume(volume);
            }
        });
        function adaptPlayerLayout() {
            const playerWrapper = playerElement.closest('.audio-player-wrapper');
            if (!playerWrapper) return;
            const content = document.querySelector('.content');
            const width = content ? content.getBoundingClientRect().width : window.innerWidth;
            playerWrapper.classList.toggle('audio-player-compact', width <= 420);
        }
        adaptPlayerLayout();
        window.addEventListener('resize', adaptPlayerLayout);
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            new MutationObserver(adaptPlayerLayout).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        }
        sound.on('end', function() {
            updatePlayPauseState(false);
            progressBar.style.width = '0%';
            progressHandle.style.left = '0%';
            if (currentTimeEl) {
                currentTimeEl.textContent = '0:00';
            }
            if ('mediaSession' in navigator) {
                navigator.mediaSession.playbackState = 'none';
                navigator.mediaSession.setPositionState(null);
            }
        });
        window.addEventListener('beforeunload', function() {
            if ('mediaSession' in navigator) {
                navigator.mediaSession.metadata = null;
                navigator.mediaSession.setActionHandler('play', null);
                navigator.mediaSession.setActionHandler('pause', null);
                navigator.mediaSession.setActionHandler('seekbackward', null);
                navigator.mediaSession.setActionHandler('seekforward', null);
                navigator.mediaSession.setActionHandler('seekto', null);
            }
        });
    }
}
