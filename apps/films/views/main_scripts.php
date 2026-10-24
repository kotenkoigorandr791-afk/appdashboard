<script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<script src="/project/sharepoint/assets/js/qr-code.js?v=5"></script>
<script src="/project/sharepoint/share_i18n.php?lang=<?php echo htmlspecialchars($lang ?? 'en', ENT_QUOTES); ?>"></script>
<script src="/project/sharepoint/assets/js/share-modal.js?v=5"></script>
<script>
function shareFilm(id, title) {
  if (!window.openShareModal) return;
  window.openShareModal('films', 'app', id, { title: title || '', defaultPermissions: ['view'], apiUrl: '/apps/films/api/share.php' });
}
const currentLang = '<?php echo $lang; ?>';
const currentTheme = '<?php echo $theme; ?>';
const currentColor = '<?php echo $color; ?>';
const userAccessLevel = <?php echo $user_access_level; ?>;
const i18n = <?php echo json_encode($t, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
function getTranslation(key, params = {}) {
    let v = i18n[key];
    if (v === undefined) {
        console.warn('Translation missing for key: "' + key + '"');
        v = key;
    }
    if (params) {
        Object.keys(params).forEach(p => {
            v = v.replaceAll('{' + p + '}', params[p]);
        });
    }
    return v;
}

// Apply theme class to body
if (currentTheme === 'dark') {
    document.body.classList.add('dark-theme');
    document.body.classList.remove('light-theme');
} else {
    document.body.classList.add('light-theme');
    document.body.classList.remove('dark-theme');
}


<?php if ($user_access_level >= 1): ?>
const addModal = document.getElementById('addMovieModal');
const closeModal = document.getElementById('closeModal');
const cancelBtn = document.getElementById('cancelBtn');
const movieForm = document.getElementById('movieForm');
const contentTypeSelect = document.getElementById('contentType');
const linkInputGroup = document.getElementById('linkInputGroup');
const fileInputGroup = document.getElementById('fileInputGroup');
const movieLinkInput = document.getElementById('movieLink');
const movieFileInput = document.getElementById('movieFile');
<?php endif; ?>

<?php if ($user_access_level >= 2): ?>
const settingsModal = document.getElementById('settingsModal');
const closeSettingsModal = document.getElementById('closeSettingsModal');
const closeSettingsBtn = document.getElementById('closeSettingsBtn');
const videoSettingsForm = document.getElementById('videoSettingsForm');

// Settings button functionality moved to nav menu
<?php endif; ?>

const videoPlayerModal = document.getElementById('videoPlayerModal');
const commentsModal = document.getElementById('commentsModal');

const closeCommentsModal = document.getElementById('closeCommentsModal');
const closeCommentsBtn = document.getElementById('closeCommentsBtn');
const commentForm = document.getElementById('commentForm');

const loading = document.getElementById('loading');

const closeVideoModal = document.getElementById('closeVideoModal');
const videoInfoOverlay = document.getElementById('videoInfoOverlay');
const videoControlsHint = document.getElementById('videoControlsHint');
const videoContainer = document.getElementById('videoContainer');
const playerContainer = document.getElementById('playerContainer');

const eyeIcon = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
const eyeOffIcon = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>';

let videoPlayer = null;
let currentVideoSettings = { seek_step: 10, volume_step: 10 };
let currentVideoSource = '';
let currentVideoTitle = '';
let hls = null;

// Quality levels for adaptive streaming
const qualityLevels = [
    { label: getTranslation('quality_auto'), value: 'auto' },
    { label: '2160p (4K)', value: '3840x2160' },
    { label: '1440p (2K)', value: '2560x1440' },
    { label: '1080p', value: '1920x1080' },
    { label: '720p', value: '1280x720' },
    { label: '480p', value: '854x480' },
    { label: '360p', value: '640x360' },
    { label: '240p', value: '426x240' }
];

function initVideoSettings() {
    <?php if ($user_access_level >= 2): ?>
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_video_settings' })
    })
    .then(r => r.json())
    .then(r => {
        if (r.success) {
            currentVideoSettings = r.settings;
            document.getElementById('seekStep').value = r.settings.seek_step;
            document.getElementById('volumeStep').value = r.settings.volume_step;
        }
    })
    .catch(e => console.error('Error loading video settings:', e));
    <?php else: ?>
    // Just load settings without UI
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_video_settings' })
    })
    .then(r => r.json())
    .then(r => {
        if (r.success) {
            currentVideoSettings = r.settings;
        }
    })
    .catch(e => console.error('Error loading video settings:', e));
    <?php endif; ?>
}

<?php if ($user_access_level >= 2): ?>
videoSettingsForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const seekStep = parseInt(document.getElementById('seekStep').value);
    const volumeStep = parseInt(document.getElementById('volumeStep').value);
    
    if (seekStep < 1 || seekStep > 60) {
        alert(getTranslation('seek_step_range'));
        return;
    }
    
    if (volumeStep < 1 || volumeStep > 50) {
        alert(getTranslation('volume_step_range'));
        return;
    }
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'save_video_settings', 
                seek_step: seekStep, 
                volume_step: volumeStep 
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentVideoSettings = result.settings;
            alert(getTranslation('settings_saved'));
        } else {
            alert(getTranslation('error_prefix') + ' ' + result.error);
        }
    } catch (error) {
        alert(getTranslation('network_error_prefix') + ' ' + error.message);
    }
});
<?php endif; ?>

function setupVideoKeyboardControls() {
    document.addEventListener('keydown', (e) => {
        if (!videoPlayerModal.classList.contains('show') || !videoPlayer) return;
        
        switch(e.key) {
            case ' ':
                e.preventDefault();
                if (videoPlayer.paused) videoPlayer.play(); else videoPlayer.pause();
                showControlsHint(getTranslation('space_play_pause') || 'Space: Play/Pause');
                break;
            case 'ArrowLeft':
                e.preventDefault();
                videoPlayer.currentTime = Math.max(0, videoPlayer.currentTime - currentVideoSettings.seek_step);
                showControlsHint(`← ${currentVideoSettings.seek_step}s`);
                break;
            case 'ArrowRight':
                e.preventDefault();
                videoPlayer.currentTime = Math.min(videoPlayer.duration, videoPlayer.currentTime + currentVideoSettings.seek_step);
                showControlsHint(`→ ${currentVideoSettings.seek_step}s`);
                break;
            case 'ArrowUp':
                e.preventDefault();
                videoPlayer.volume = Math.min(1, videoPlayer.volume + (currentVideoSettings.volume_step / 100));
                showControlsHint(`↑ ${currentVideoSettings.volume_step}%`);
                break;
            case 'ArrowDown':
                e.preventDefault();
                videoPlayer.volume = Math.max(0, videoPlayer.volume - (currentVideoSettings.volume_step / 100));
                showControlsHint(`↓ ${currentVideoSettings.volume_step}%`);
                break;
            case 'Escape':
                e.preventDefault();
                closeVideoPlayer();
                break;
        }
    });
}

function showControlsHint(text) {
    videoControlsHint.textContent = text;
    videoControlsHint.style.display = 'block';
    clearTimeout(videoControlsHint.timeout);
    videoControlsHint.timeout = setTimeout(() => { 
        videoControlsHint.style.display = 'none'; 
    }, 1500);
}

function destroyVideoPlayer() {
    if (hls) {
        hls.destroy();
        hls = null;
    }
    
    if (videoPlayer) {
        try {
            videoPlayer.off();
            videoPlayer.destroy();
        } catch (e) {
            console.error('Error destroying video player:', e);
        }
        videoPlayer = null;
    }
    
    playerContainer.innerHTML = '';
}

function createVideoPlayer() {
    destroyVideoPlayer();

    const videoElement = document.createElement('video');
    videoElement.id = 'videoPlayer';
    videoElement.playsInline = true;
    videoElement.preload = 'metadata';
    videoElement.setAttribute('data-mime-type', 'video/mp4');

    // Create source with streaming URL - use absolute path
    const streamUrl = window.location.origin + window.location.pathname + '?stream=' + encodeURIComponent(currentVideoSource);
    console.log('Creating video player with stream URL:', streamUrl);
    console.log('Video source file:', currentVideoSource);
    
    videoElement.src = streamUrl;

    playerContainer.appendChild(videoElement);

    // Create Plyr instance
    videoPlayer = new Plyr(videoElement, {
        controls: [
            'play-large',
            'rewind',
            'play',
            'fast-forward',
            'progress',
            'current-time',
            'duration',
            'mute',
            'volume',
            'captions',
            'settings',
            'pip',
            'airplay',
            'fullscreen'
        ],
        settings: ['speed'],
        keyboard: { focused: true, global: true },
        seekTime: currentVideoSettings.seek_step,
        volume: 0.5,
        hideControls: false,
        invertTime: false,
        clickToPlay: true,
        disableContextMenu: false,
        fullscreen: {
            enabled: true,
            fallback: true,
            iosNative: true,
            container: null
        },
        storage: { enabled: false },
        tooltips: { controls: true, seek: true },
        i18n: {
            play: getTranslation('video_play'),
            pause: getTranslation('video_pause'),
            rewind: getTranslation('video_rewind'),
            forward: getTranslation('video_forward'),
            mute: getTranslation('video_mute'),
            unmute: getTranslation('video_unmute'),
            volume: getTranslation('video_volume'),
            fullscreen: getTranslation('video_fullscreen'),
            exitFullscreen: getTranslation('video_exit_fullscreen'),
            pip: getTranslation('video_pip'),
            airplay: getTranslation('video_airplay'),
            settings: getTranslation('video_settings'),
            subtitles: getTranslation('video_subtitles'),
            speed: getTranslation('video_speed'),
            loop: getTranslation('video_loop')
        }
    });

    // Add quality selector
    const qualitySelector = document.createElement('select');
    qualitySelector.className = 'quality-selector';
    qualitySelector.setAttribute('aria-label', getTranslation('video_quality'));
    qualitySelector.style.display = 'none'; // Hide for now since we're not using HLS
    qualityLevels.forEach(level => {
        const option = document.createElement('option');
        option.value = level.value;
        option.textContent = level.label;
        qualitySelector.appendChild(option);
    });
    videoContainer.appendChild(qualitySelector);

    setupVideoPlayerEvents();

    videoPlayer.on('ready', () => {
        console.log('Video player ready, duration:', videoPlayer.duration);
        const relativePath = getRelativeFilePath(currentVideoSource);
        const movie = getMovieByFilePath(relativePath);

        if (movie && movie.lastPosition && movie.lastPosition < videoPlayer.duration) {
            videoPlayer.currentTime = movie.lastPosition;
        }

        videoPlayer.play().catch(e => {
            console.log('Autoplay prevented, user interaction required');
            // Show play button overlay when autoplay is blocked
            const playOverlay = document.createElement('div');
            playOverlay.className = 'play-overlay';
            playOverlay.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,0.7);color:#fff;padding:20px 40px;border-radius:8px;text-align:center;z-index:100;cursor:pointer;transition:all 0.3s ease;';
            playOverlay.innerHTML = `<svg style="width:64px;height:64px;fill:#fff" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg><br><span style="font-size:18px">Натисніть для відтворення</span>`;
            playOverlay.addEventListener('mouseenter', () => { playOverlay.style.background = 'rgba(0,0,0,0.9)'; });
            playOverlay.addEventListener('mouseleave', () => { playOverlay.style.background = 'rgba(0,0,0,0.7)'; });
            playOverlay.addEventListener('click', () => {
                videoPlayer.play();
                playOverlay.remove();
            });
            videoContainer.style.position = 'relative';
            const existingOverlay = videoContainer.querySelector('.play-overlay');
            if (existingOverlay) existingOverlay.remove();
            videoContainer.appendChild(playOverlay);
        });

        setupVideoAutosave();

        if (window.innerWidth <= 768) {
            setTimeout(() => {
                try {
                    if (videoPlayer.fullscreen && videoPlayer.fullscreen.enabled) {
                        videoPlayer.fullscreen.enter();
                    }
                } catch (e) {
                    console.log('Auto fullscreen not supported');
                }
            }, 300);
        }
    });

    // Add error handling
    videoElement.addEventListener('error', (e) => {
        console.error('Video error:', e);
        console.error('Video error code:', videoElement.error?.code);
        console.error('Video error message:', videoElement.error?.message);
        console.error('Network state:', videoElement.networkState);
        console.error('Ready state:', videoElement.readyState);
        console.error('Video source:', streamUrl);
        console.error('Current video source:', currentVideoSource);
        
        // Show user-friendly error message
        let errorMsg = 'Відео не вдалося завантажити. ';
        switch (videoElement.error?.code) {
            case 1: errorMsg += 'Помилка завантаження.'; break;
            case 2: errorMsg += 'Помилка мережі.'; break;
            case 3: errorMsg += 'Відео не підтримується або пошкоджено.'; break;
            case 4: errorMsg += 'Формат відео не підтримується.'; break;
            default: errorMsg += 'Невідома помилка.';
        }
        console.error(errorMsg);
        
        // Display error in player
        const errorOverlay = document.createElement('div');
        errorOverlay.className = 'plyr__error';
        errorOverlay.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,0.8);color:#fff;padding:20px;border-radius:8px;text-align:center;z-index:100;';
        errorOverlay.innerHTML = `<svg style="width:48px;height:48px;margin-bottom:10px;fill:#ff4444" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg><br>${errorMsg}`;
        videoContainer.style.position = 'relative';
        const existingError = videoContainer.querySelector('.plyr__error');
        if (existingError) existingError.remove();
        videoContainer.appendChild(errorOverlay);
    });

    // Add loadedmetadata event for debugging
    videoElement.addEventListener('loadedmetadata', () => {
        console.log('Video metadata loaded, duration:', videoElement.duration);
        console.log('Video dimensions:', videoElement.videoWidth, 'x', videoElement.videoHeight);
        // Remove error overlay if present
        const errorOverlay = videoContainer.querySelector('.plyr__error');
        if (errorOverlay) errorOverlay.remove();
    });

    // Add progress event for debugging
    videoElement.addEventListener('progress', () => {
        console.log('Video progress, buffered:', videoElement.buffered.length > 0 ?
            videoElement.buffered.end(videoElement.buffered.length - 1) : 0);
    });
    
    // Add waiting event for buffering indication
    videoElement.addEventListener('waiting', () => {
        console.log('Video buffering...');
    });
    
    // Add playing event
    videoElement.addEventListener('playing', () => {
        console.log('Video playing');
        const errorOverlay = videoContainer.querySelector('.plyr__error');
        if (errorOverlay) errorOverlay.remove();
        const playOverlay = videoContainer.querySelector('.play-overlay');
        if (playOverlay) playOverlay.remove();
    });
}

function setupVideoPlayerEvents() {
    if (!videoPlayer) return;
    
    let lastClickTime = 0;
    let clickCount = 0;
    
    videoContainer.addEventListener('click', (e) => {
        // Don't trigger if clicking on quality selector
        if (e.target.classList.contains('quality-selector')) return;
        
        const currentTime = Date.now();
        const rect = videoContainer.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const containerWidth = rect.width;
        
        if (currentTime - lastClickTime < 300) {
            clickCount++;
            
            if (clickCount === 2) {
                e.preventDefault();
                e.stopPropagation();
                
                const isRightHalf = clickX > containerWidth / 2;
                const isLeftHalf = clickX < containerWidth / 2;
                
                if (isRightHalf && videoPlayer) {
                    videoPlayer.currentTime = Math.min(
                        videoPlayer.duration,
                        videoPlayer.currentTime + 10
                    );
                    showRewindHint('→ 10s');
                } else if (isLeftHalf && videoPlayer) {
                    videoPlayer.currentTime = Math.max(
                        0,
                        videoPlayer.currentTime - 10
                    );
                    showRewindHint('← 10s');
                }
                
                clickCount = 0;
            }
        } else {
            clickCount = 1;
        }
        
        lastClickTime = currentTime;
        
        setTimeout(() => {
            clickCount = 0;
        }, 500);
    });
}

function showRewindHint(text) {
    const hint = document.createElement('div');
    hint.className = 'rewind-hint';
    hint.textContent = text;
    videoContainer.appendChild(hint);
    
    setTimeout(() => {
        if (hint.parentNode) {
            hint.parentNode.removeChild(hint);
        }
    }, 1500);
}

function setupVideoAutosave() {
    if (!videoPlayer) return;
    
    let saveTimeout;
    const savePosition = () => {
        if (currentVideoSource && videoPlayer.currentTime > 0) {
            saveVideoPosition(currentVideoSource, videoPlayer.currentTime);
        }
    };
    
    videoPlayer.on('timeupdate', () => {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(savePosition, 5000);
    });
    
    videoPlayer.on('pause', savePosition);
    videoPlayer.on('seeked', savePosition);
    videoPlayer.on('ended', () => {
        if (currentVideoSource) saveVideoPosition(currentVideoSource, 0);
    });
}

function showVideoPlayer(filePath, title) {
    currentVideoSource = filePath;
    currentVideoTitle = title;
    
    videoPlayerModal.classList.add('show');
    document.body.style.overflow = 'hidden';
    videoInfoOverlay.textContent = title;
    
    createVideoPlayer();
}

function closeVideoPlayer() {
    if (currentVideoSource && videoPlayer && videoPlayer.currentTime > 0) {
        saveVideoPosition(currentVideoSource, videoPlayer.currentTime);
    }
    
    if (videoPlayer) {
        try {
            videoPlayer.pause();
            videoPlayer.currentTime = 0;
            
            if (videoPlayer.fullscreen && videoPlayer.fullscreen.active) {
                videoPlayer.fullscreen.exit().catch(e => {
                    console.log('Error exiting fullscreen:', e);
                });
            }
        } catch (e) {
            console.error('Error closing video player:', e);
        }
    }
    
    videoPlayerModal.classList.remove('show');
    document.body.style.overflow = '';
    
    setTimeout(() => {
        destroyVideoPlayer();
        currentVideoSource = '';
        currentVideoTitle = '';
        videoInfoOverlay.textContent = '';
        videoControlsHint.style.display = 'none';
        
        // Remove quality selector
        const qualitySelector = document.querySelector('.quality-selector');
        if (qualitySelector) qualitySelector.remove();
    }, 300);
}

closeVideoModal.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeVideoPlayer();
});

videoPlayerModal.addEventListener('click', (e) => {
    if (e.target === videoPlayerModal) {
        e.preventDefault();
        closeVideoPlayer();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && videoPlayerModal.classList.contains('show')) {
        e.preventDefault();
        closeVideoPlayer();
    }
});

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).catch(err => {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
    });
}

<?php if ($user_access_level >= 1): ?>
const ratingInput = document.getElementById('ratingInput');
const ratingStars = ratingInput.querySelectorAll('.rating-star');
let selectedRating = 0;

ratingStars.forEach((star, index) => {
    star.addEventListener('click', () => { 
        selectedRating = index + 1; 
        updateRatingDisplay(); 
    });
    
    star.addEventListener('mouseover', () => { 
        ratingStars.forEach((s, i) => { 
            s.classList.toggle('hover', i <= index); 
        }); 
    });
    
    star.addEventListener('mouseout', () => { 
        ratingStars.forEach(s => s.classList.remove('hover')); 
    });
});

function updateRatingDisplay() {
    ratingStars.forEach((star, index) => { 
        star.classList.toggle('active', index < selectedRating); 
    });
}

contentTypeSelect.addEventListener('change', () => {
    const isLocal = contentTypeSelect.value === 'local';
    linkInputGroup.style.display = isLocal ? 'none' : 'block';
    fileInputGroup.style.display = isLocal ? 'block' : 'none';
    movieLinkInput.required = !isLocal;
    movieFileInput.required = isLocal;
});

closeModal.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    addModal.classList.remove('show');
    resetForm();
});

cancelBtn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    addModal.classList.remove('show');
    resetForm();
});

addModal.addEventListener('click', (e) => {
    if (e.target === addModal) {
        e.preventDefault();
        addModal.classList.remove('show');
        resetForm();
    }
});

movieForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (selectedRating === 0) { 
        alert(getTranslation('please_select_rating')); 
        return; 
    }
    
    const submitBtn = movieForm.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.classList.add('btn--loading');
    submitBtn.textContent = '<?php echo addslashes($t["saving"] ?? "Saving"); ?>';
    
    const isLocal = contentTypeSelect.value === 'local';
    const formData = new FormData();
    formData.append('action', 'add_movie');
    formData.append('title', document.getElementById('movieTitle').value.trim());
    formData.append('source_lang', document.getElementById('sourceLang').value);
    formData.append('year', parseInt(document.getElementById('movieYear').value));
    formData.append('category', document.getElementById('movieCategory').value);
    formData.append('rating', selectedRating);
    formData.append('isLocal', isLocal);
    
    if (isLocal) {
        const file = movieFileInput.files[0];
        if (!file) {
            alert(getTranslation('please_select_file'));
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn--loading');
            submitBtn.textContent = originalText;
            return;
        }
        formData.append('movieFile', file);
    } else {
        const link = movieLinkInput.value.trim();
        if (!link) {
            alert(getTranslation('please_enter_link'));
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn--loading');
            submitBtn.textContent = originalText;
            return;
        }
        formData.append('link', link);
    }
    
    loading.classList.add('show');
    
    try {
        const response = await fetch(window.location.href, { 
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            // Reload movies list without full page reload
            loadAllMoviesForSearch();
            addModal.classList.remove('show');
            resetForm();
        } else {
            alert(getTranslation('error_prefix') + ' ' + result.error);
        }
    } catch (error) {
        alert(getTranslation('network_error_prefix') + ' ' + error.message);
    } finally {
        loading.classList.remove('show');
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn--loading');
        submitBtn.textContent = originalText;
    }
});

function resetForm() {
    movieForm.reset();
    selectedRating = 0;
    updateRatingDisplay();
    contentTypeSelect.value = 'link';
    linkInputGroup.style.display = 'block';
    fileInputGroup.style.display = 'none';
    movieLinkInput.required = true;
    movieFileInput.required = false;
}
<?php endif; ?>

<?php if ($user_access_level >= 2): ?>
// Settings functionality moved to nav menu

const closeSettings = () => {
    if (settingsModal) {
        settingsModal.classList.remove('show');
        document.body.style.overflow = '';
    }
};

closeSettingsModal.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeSettings();
});

closeSettingsBtn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeSettings();
});

settingsModal.addEventListener('click', (e) => {
    if (e.target === settingsModal) {
        e.preventDefault();
        closeSettings();
    }
});

async function loadSettingsMovies() {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_all_movies' })
        });
        const result = await response.json();
        if (result.success) {
            const tbody = document.getElementById('settingsMoviesList');
            tbody.innerHTML = '';
            result.movies.forEach(movie => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(movie.title[currentLang] || movie.title.en)}</td>
                    <td>${movie.year}</td>
                    <td>${escapeHtml(movie.category)}</td>
                    <td>${movie.is_local ? '<?php echo addslashes($t['local_file']); ?>' : '<?php echo addslashes($t['online_link']); ?>'}</td>
                    <td>
                        <button class="visibility-btn ${movie.visible ? 'visible' : 'hidden'}" onclick="toggleVisibility('${movie.id}', ${!movie.visible})">
                            ${movie.visible ? eyeIcon : eyeOffIcon}
                        </button>
                    </td>
                    <td>
                        <button class="btn btn--danger" onclick="deleteMovie('${movie.id}')"><?php echo addslashes($t['delete']); ?></button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    } catch (error) { 
        console.error('Error loading movies:', error); 
    }
}

async function loadMovieSelect() {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_all_movies' })
        });
        const result = await response.json();
        if (result.success) {
            const select = document.getElementById('commentMovieSelect');
            select.innerHTML = '<option value=""><?php echo addslashes($t["select_movie"] ?? "Select a movie..."); ?></option>';
            result.movies.forEach(movie => {
                const option = document.createElement('option');
                option.value = movie.id;
                option.textContent = movie.title[currentLang] || movie.title.en;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading movies for select:', error);
    }
}

async function loadAdminComments() {
    const movieId = document.getElementById('commentMovieSelect').value;
    if (!movieId) {
        document.getElementById('adminCommentsList').innerHTML = '';
        return;
    }
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_comments', movie_id: movieId })
        });
        const result = await response.json();
        if (result.success) {
            displayComments(result.comments, true);
        }
    } catch (error) {
        console.error('Error loading admin comments:', error);
    }
}

function switchAdminTab(tab) {
    const tabs = document.querySelectorAll('.admin-tab');
    const panels = document.querySelectorAll('.admin-panel');
    
    tabs.forEach(t => t.classList.remove('active'));
    panels.forEach(p => p.classList.remove('show'));
    
    document.querySelector(`.admin-tab[onclick*="${tab}"]`).classList.add('active');
    document.getElementById(tab + 'Panel').classList.add('show');
}

async function toggleVisibility(movieId, visible) {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'toggle_visibility', 
                movie_id: movieId, 
                visible 
            })
        });
        const result = await response.json();
        if (result.success) { 
            loadSettingsMovies(); 
        } else { 
            alert(getTranslation('error_prefix') + ' ' + result.error); 
        }
    } catch (error) { 
        alert(getTranslation('network_error_prefix') + ' ' + error.message); 
    }
}

async function deleteMovie(movieId) {
    if (!confirm(getTranslation('confirm_delete_movie'))) return;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'delete_movie', 
                movie_id: movieId 
            })
        });
        const result = await response.json();
        if (result.success) { 
            loadSettingsMovies(); 
        } else { 
            alert(getTranslation('error_prefix') + ' ' + result.error); 
        }
    } catch (error) { 
        alert(getTranslation('network_error_prefix') + ' ' + error.message); 
    }
}

async function deleteComment(commentId) {
    if (!confirm(getTranslation('confirm_delete_comment'))) return;
    
    const movieId = document.getElementById('commentMovieSelect').value;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'delete_comment', 
                movie_id: movieId,
                comment_id: commentId
            })
        });
        
        const result = await response.json();
        if (result.success) {
            loadAdminComments();
        } else {
            alert(getTranslation('error_prefix') + ' ' + result.error);
        }
    } catch (error) {
        alert(getTranslation('network_error_prefix') + ' ' + error.message);
    }
}
<?php endif; ?>

const commentRatingInput = document.getElementById('commentRatingInput');
const commentRatingStars = commentRatingInput.querySelectorAll('.comment-rating-star');
const commentRatingValue = document.getElementById('commentRatingValue');
let selectedCommentRating = 0;

commentRatingStars.forEach((star, index) => {
    star.addEventListener('click', () => { 
        selectedCommentRating = index + 1; 
        updateCommentRatingDisplay(); 
    });
    
    star.addEventListener('mouseover', () => { 
        commentRatingStars.forEach((s, i) => { 
            s.classList.toggle('hover', i <= index); 
        }); 
    });
    
    star.addEventListener('mouseout', () => { 
        commentRatingStars.forEach(s => s.classList.remove('hover')); 
    });
});

function updateCommentRatingDisplay() {
    commentRatingStars.forEach((star, index) => { 
        star.classList.toggle('active', index < selectedCommentRating); 
    });
    commentRatingValue.textContent = selectedCommentRating;
}

function showComments(movieId, movieTitle) {
    document.getElementById('commentMovieId').value = movieId;
    document.getElementById('commentsModalTitle').textContent = `${getTranslation('comments')} - ${movieTitle}`;
    loadComments(movieId);
    commentsModal.classList.add('show');
}

async function loadComments(movieId) {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_comments', movie_id: movieId })
        });
        const result = await response.json();
        if (result.success) { 
            displayComments(result.comments, false, result.user_access_level >= 2); 
        } else { 
            console.error('Error loading comments:', result.error); 
        }
    } catch (error) { 
        console.error('Error loading comments:', error); 
    }
}

function displayComments(comments, showAdminControls = false, canDelete = false) {
    const commentsList = document.getElementById(showAdminControls ? 'adminCommentsList' : 'commentsList');
    commentsList.innerHTML = '';
    
    if (comments.length === 0) {
        commentsList.innerHTML = '<p>' + (getTranslation('no_comments') || 'No comments yet. Be the first to comment!') + '</p>';
        return;
    }
    
    comments.sort((a, b) => new Date(b.date) - new Date(a.date));
    
    comments.forEach(comment => {
        const commentItem = document.createElement('div');
        commentItem.className = 'comment-item';
        
        let adminControls = '';
        if (showAdminControls && canDelete) {
            adminControls = `
                <div class="comment-actions">
                    <button class="btn btn--danger" onclick="deleteComment('${comment.id}')">${getTranslation('delete')}</button>
                </div>
            `;
        }
        
        commentItem.innerHTML = `
            <div class="comment-header">
                <span class="comment-user">${escapeHtml(comment.user_name)}</span>
                <span class="comment-date">${new Date(comment.date).toLocaleDateString()}</span>
            </div>
            <div class="comment-rating">
                ${Array.from({length: 5}, (_, i) => `<span class="star">${i < comment.rating ? '★' : '☆'}</span>`).join('')}
            </div>
            <div class="comment-text">${escapeHtml(comment.comment)}</div>
            ${adminControls}
        `;
        commentsList.appendChild(commentItem);
    });
}

closeCommentsModal.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    commentsModal.classList.remove('show');
    resetCommentForm();
});

closeCommentsBtn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    commentsModal.classList.remove('show');
    resetCommentForm();
});

commentsModal.addEventListener('click', (e) => {
    if (e.target === commentsModal) {
        e.preventDefault();
        commentsModal.classList.remove('show');
        resetCommentForm();
    }
});

commentForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const movieId = document.getElementById('commentMovieId').value;
    const userName = document.getElementById('commentUserName').value.trim();
    const commentText = document.getElementById('commentText').value.trim();
    
    if (selectedCommentRating === 0) { 
        alert(getTranslation('please_select_rating')); 
        return; 
    }
    
    if (!userName || !commentText) { 
        alert(getTranslation('fill_all_fields')); 
        return; 
    }
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_comment',
                movie_id: movieId,
                user_name: userName,
                comment_text: commentText,
                rating: selectedCommentRating
            })
        });

        const result = await response.json();

        if (result.success) {
            loadComments(movieId);
            resetCommentForm();
        } else {
            alert(getTranslation('error_prefix') + ' ' + result.error);
        }
    } catch (error) {
        alert(getTranslation('network_error_prefix') + ' ' + error.message);
    }
});

function resetCommentForm() { 
    commentForm.reset(); 
    selectedCommentRating = 0; 
    updateCommentRatingDisplay(); 
}

function getRelativeFilePath(fullPath) {
    if (fullPath.startsWith('uploads/')) { return fullPath; }
    const filename = fullPath.split('/').pop();
    return 'uploads/' + filename;
}

async function saveVideoPosition(filePath, position) {
    try {
        const relativePath = getRelativeFilePath(filePath);
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_position',
                filePath: relativePath,
                position: position
            })
        });
        const result = await response.json();
        if (!result.success) { console.error('Failed to save position:', result.error); }
    } catch (error) { console.error('Error saving position:', error); }
}

function getMovieByFilePath(filePath) {
    return window.allMovies?.find(movie => {
        return movie.file_path === filePath || 
               movie.file_path === filePath.replace('uploads/', '') ||
               basename(movie.file_path) === basename(filePath);
    });
}

function basename(path) { return path.split('/').pop(); }

let currentSearchTerm = '';
let currentSortCategory = 'all';
let allMoviesData = [];

function initSearchAndSort() {
    const searchInput = document.getElementById('searchInput');
    const sortBtn = document.getElementById('sortBtn');
    const sortDropdown = document.getElementById('sortDropdown');
    const sortOptions = sortDropdown.querySelectorAll('.sort-option');
    
    searchInput.addEventListener('input', (e) => { 
        currentSearchTerm = e.target.value.toLowerCase().trim(); 
        filterAndRenderMovies(); 
    });
    
    sortBtn.addEventListener('click', (e) => { 
        e.stopPropagation(); 
        sortDropdown.style.display = sortDropdown.style.display === 'none' ? 'block' : 'none'; 
    });
    
    sortOptions.forEach(option => {
        option.addEventListener('click', () => {
            sortOptions.forEach(opt => opt.classList.remove('active'));
            option.classList.add('active');
            currentSortCategory = option.dataset.sort;
            sortDropdown.style.display = 'none';
            filterAndRenderMovies();
        });
    });
    
    document.addEventListener('click', () => { 
        sortDropdown.style.display = 'none'; 
    });
    
    loadAllMoviesForSearch();
}

async function loadAllMoviesForSearch() {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_all_movies' })
        });
        const result = await response.json();
        if (result.success) {
            allMoviesData = result.movies.filter(movie => movie.visible);
            window.allMovies = result.movies;
            filterAndRenderMovies();
        }
    } catch (error) { 
        console.error('Error loading movies for search:', error); 
    }
}

function filterAndRenderMovies() {
    const moviesGrid = document.getElementById('moviesGrid');
    let filteredMovies = allMoviesData.filter(movie => {
        const titleMatches = Object.values(movie.title).some(title => 
            title.toLowerCase().includes(currentSearchTerm)
        );
        const categoryMatches = currentSortCategory === 'all' || movie.category === currentSortCategory;
        return titleMatches && categoryMatches;
    });
    
    moviesGrid.innerHTML = '';
    
    if (filteredMovies.length === 0) {
        const noResults = document.createElement('div');
        noResults.className = 'no-results';
        noResults.innerHTML = `<h3>${getTranslation('no_movies_found')}</h3><p>${getTranslation('try_different_search')}</p>`;
        moviesGrid.appendChild(noResults);
        return;
    }
    
    filteredMovies.forEach(movie => { 
        const movieCard = createMovieCard(movie); 
        moviesGrid.appendChild(movieCard); 
    });
}

function createMovieCard(movie) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    const isLocal = movie.is_local && movie.file_path;
    const displayTitle = movie.title[currentLang] || movie.title.en;
    const idArg = escapeAttr(JSON.stringify(String(movie.id)));
    const titleArg = escapeAttr(JSON.stringify(String(displayTitle)));

    card.innerHTML = `
        <div class="content-type-icon" title="${isLocal ? getTranslation('local_file') : getTranslation('online_link')}">
            ${isLocal ?
                '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>' :
                '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.22-1.78L9 14v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1h-6v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>'
            }
        </div>
        <h3 class="movie-title">${escapeHtml(displayTitle)}</h3>
        <div class="overall-rating">
            <span class="overall-rating-text">${getTranslation('overall_rating') || 'Overall Rating'}:</span>
            <div class="rating">
                ${Array.from({length: 5}, (_, i) => `<span class="star">${i < Math.round(movie.overall_rating) ? '★' : '☆'}</span>`).join('')}
            </div>
            <span class="rating-value">(${movie.overall_rating.toFixed(1)})</span>
        </div>
        <div class="movie-info">
            <span style="color: var(--text-secondary);">${movie.year}</span>
            <span class="movie-category">${escapeHtml(getTranslation('categories')[movie.category] || movie.category)}</span>
        </div>
        <div class="rating">
            ${Array.from({length: 5}, (_, i) => `<span class="star">${i < movie.rating ? '★' : '☆'}</span>`).join('')}
        </div>
        <div class="movie-actions">
            ${isLocal ?
                `<button class="watch-btn" onclick="showVideoPlayer('${escapeHtml(basename(movie.file_path))}', '${escapeHtml(displayTitle)}')">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="margin-right: 6px;">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    ${getTranslation('watch')}
                </button>` :
                `<button class="watch-btn" onclick="window.open('${escapeHtml(movie.link)}', '_blank')">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="margin-right: 6px;">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    ${getTranslation('watch')}
                </button>
                 <button class="btn btn--icon copy-btn" onclick="copyToClipboard('${escapeHtml(movie.link)}')" title="${getTranslation('copy_link')}">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                    </svg>
                 </button>`
            }
            <button class="comments-btn" onclick="showComments(${idArg}, ${titleArg})">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="margin-right: 4px;">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                </svg>
                ${getTranslation('comments') || 'Comments'}
            </button>
            <button class="comments-btn" onclick="shareFilm(${idArg}, ${titleArg})">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="margin-right: 4px;"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11A2.99 2.99 0 1 0 15 5c0 .24.04.47.09.7L8.04 9.81A3 3 0 1 0 8.04 14.19l7.12 4.16c-.05.21-.08.43-.08.65A2.92 2.92 0 1 0 18 16.08z"/></svg>
                ${getTranslation('share') || 'Share'}
            </button>
        </div>
    `;
    return card;
}

function escapeHtml(text) { 
    const div = document.createElement('div'); 
    div.textContent = text; 
    return div.innerHTML; 
}

function escapeAttr(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

window.addEventListener('DOMContentLoaded', async () => {
    await loadAllMoviesForSearch();
    initSearchAndSort();
    initVideoSettings();
    setupVideoKeyboardControls();
});

</script>
