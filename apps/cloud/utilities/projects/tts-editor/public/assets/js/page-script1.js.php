        class TTSEditor {
            constructor() {
                this.textPath = '<?php echo $textPath; ?>';
                this.apiTextPath = '<?php echo $apiTextPath; ?>';
                this.user = '<?php echo $user; ?>';
                this.root = '<?php echo $root; ?>';
                this.fileFound = <?php echo $sourcePath ? 'true' : 'false'; ?>;
                this.isProcessing = false;
                this.progressId = null;
                this.translations = <?php echo json_encode($t); ?>;
                this.audioUrl = null;
                this.audioBlobUrl = null;
                this.audioPlayer = null;
                this.taskProgress = null;
                if (!this.fileFound) console.error('TTS Editor: file not found');

                this.initializeElements();
                this.initTaskProgress();
                this.bindEvents();
            }

            initTaskProgress() {
                const accent = (document.body.dataset.accentColor || '85b503').replace(/^#/, '');
                const theme = document.body.dataset.theme || 'dark';
                this.taskProgress = new FileLoadProgress({ theme, color: '#' + accent });
            }

            sanitizeMessage(message) {
                if (!message) return '';
                return String(message)
                    .replace(/\b[a-zA-Z]:[\\/][^\s"'<>)]*/g, '[hidden-path]')
                    .replace(/(?:^|[\s(])\/(?:[^/\s"'<>)]*\/)*[^/\s"'<>)]*/g, (match) => {
                        const prefix = match.startsWith(' ') || match.startsWith('(') ? match.charAt(0) : '';
                        return prefix + '[hidden-path]';
                    });
            }

            initializeElements() {
                this.elements = {
                    generateBtn: document.getElementById('btn-generate'),
                    stopBtn: document.getElementById('btn-stop'),
                    saveServerBtn: document.getElementById('btn-save-server'),
                    textContent: document.getElementById('text-content'),
                    audioContainer: document.getElementById('audio-container'),
                    progressContainer: document.getElementById('progress-container'),
                    progressBar: document.getElementById('progress-bar'),
                    status: document.getElementById('status'),
                    ttsLanguage: document.getElementById('tts-language'),
                    ttsSpeed: document.getElementById('tts-speed')
                };
            }

            bindEvents() {
                this.elements.generateBtn.addEventListener('click', () => this.generateAudio());
                this.elements.stopBtn.addEventListener('click', () => this.stopGeneration());
                this.elements.saveServerBtn.addEventListener('click', () => this.saveToServer());

                // Cleanup on page unload
                window.addEventListener('beforeunload', () => {
                    if (this.progressId) {
                        navigator.sendBeacon(`<?= htmlspecialchars($ttsApiUrl) ?>?action=cleanup_audio&progress_id=${this.progressId}`);
                    }
                    if (this.audioBlobUrl) {
                        URL.revokeObjectURL(this.audioBlobUrl);
                    }
                    if (this.audioPlayer) {
                        this.audioPlayer.destroy();
                        this.audioPlayer = null;
                    }
                });
            }

            async generateAudio() {
                const text = this.elements.textContent.value.trim();
                if (!text) {
                    this.showStatus(this.translations.error_prefix + ': No text to generate', 'error');
                    return;
                }

                const language = this.elements.ttsLanguage.value;
                const speed = parseFloat(this.elements.ttsSpeed.value);

                this.isProcessing = true;
                this.elements.generateBtn.disabled = true;
                this.elements.stopBtn.style.display = 'inline-block';
                this.taskProgress.show();
                this.taskProgress.reset();
                this.taskProgress.set(8);
                this.taskProgress.startCreep(88);

                this.showStatus(this.translations.starting_generation, 'info');
                this.updateProgressUI(10, this.translations.starting_generation);

                try {
                    const formData = new FormData();
                    formData.append('action', 'generate_tts');
                    formData.append('text', text);
                    formData.append('language', language);
                    formData.append('speed', speed);
                    formData.append('text_path', this.apiTextPath);
                    formData.append('user', this.user);

                    const response = await fetch('<?= htmlspecialchars($ttsApiUrl) ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.progressId = result.progress_id;
                        this.showStatus(this.translations.generation_started, 'success');
                        this.trackProgress();
                    } else {
                        throw new Error(result.error || 'Generation failed');
                    }
                } catch (error) {
                    console.error('Generation error:', error);
                    this.showStatus(this.translations.error_prefix + ': ' + error.message, 'error');
                    this.resetUI();
                }
            }

            async trackProgress() {
                const checkProgress = async () => {
                    if (!this.isProcessing) return;

                    try {
                        const response = await fetch(`<?= htmlspecialchars($ttsApiUrl) ?>?action=get_progress&progress_id=${this.progressId}`);
                        const data = await response.json();

                        this.updateProgressUI(data.progress, data.message);

                        if (data.progress >= 100 && data.status === 'completed') {
                            this.generationComplete(data.audio_url, data.audio_data);
                        } else if (data.status === 'error') {
                            throw new Error(data.error || 'Generation failed');
                        } else {
                            setTimeout(checkProgress, 1000);
                        }
                    } catch (error) {
                        console.error('Progress check error:', error);
                        this.showStatus(this.translations.error_prefix + ': ' + error.message, 'error');
                        this.resetUI();
                    }
                };

                checkProgress();
            }

            generationComplete(audioUrl, audioData) {
                this.isProcessing = false;
                this.audioUrl = audioUrl;
                this.elements.generateBtn.disabled = false;
                this.elements.stopBtn.style.display = 'none';
                this.elements.saveServerBtn.style.display = 'inline-block';
                this.taskProgress.set(100);
                this.taskProgress.complete();

                this.showStatus(this.translations.generation_completed, 'success');

                // Create blob URL from base64 data
                if (audioData) {
                    const binaryData = atob(audioData);
                    const bytes = new Uint8Array(binaryData.length);
                    for (let i = 0; i < binaryData.length; i++) {
                        bytes[i] = binaryData.charCodeAt(i);
                    }
                    const blob = new Blob([bytes], { type: 'audio/mpeg' });
                    this.audioBlobUrl = URL.createObjectURL(blob);
                } else {
                    this.audioBlobUrl = audioUrl;
                }

                // Display custom audio player
                this.elements.audioContainer.innerHTML = `<div class="audio-player-container" id="tts-audio-player"></div>`;
                const playerContainer = document.getElementById('tts-audio-player');
                if (this.audioPlayer) {
                    this.audioPlayer.destroy();
                }
                this.audioPlayer = new CustomAudioPlayer(playerContainer, this.audioBlobUrl, {
                    autoplay: false,
                    showVolume: true
                });

                if (window.parent) {
                    window.parent.postMessage({
                        type: 'ttsGenerated',
                        textPath: this.textPath,
                        audioUrl: this.audioBlobUrl
                    }, '*');
                }

                // Auto-cleanup temporary files after generation completes
                setTimeout(() => {
                    this.cleanupTempFiles();
                }, 2000);
            }

            stopGeneration() {
                this.isProcessing = false;
                this.showStatus(this.translations.generation_stopped, 'info');

                // Cleanup temporary files when stopping
                if (this.progressId) {
                    this.cleanupTempFiles();
                }

                this.resetUI();
            }

            async saveToServer() {
                if (!this.progressId) return;

                try {
                    this.elements.saveServerBtn.disabled = true;
                    this.showStatus('Saving to server...', 'info');

                    const formData = new FormData();
                    formData.append('action', 'save_audio_to_server');
                    formData.append('progress_id', this.progressId);
                    formData.append('text_path', this.apiTextPath);
                    formData.append('user', this.user);

                    const response = await fetch('<?= htmlspecialchars($ttsApiUrl) ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showStatus('Audio saved', 'success');

                        // Notify parent window to refresh file list
                        if (window.parent) {
                            window.parent.postMessage({
                                type: 'fileCreated',
                                path: result.saved_path,
                                root: this.root,
                                name: (result.saved_path || '').split('/').pop()
                            }, '*');
                        }

                        // Cleanup temporary files after successful save
                        await this.cleanupTempFiles();
                    } else {
                        throw new Error(result.error || 'Failed to save audio');
                    }
                } catch (error) {
                    console.error('Save error:', error);
                    this.showStatus(this.translations.error_prefix + ': ' + error.message, 'error');
                } finally {
                    this.elements.saveServerBtn.disabled = false;
                }
            }

            async cleanupTempFiles() {
                if (!this.progressId) return;

                try {
                    await fetch(`<?= htmlspecialchars($ttsApiUrl) ?>?action=cleanup_audio&progress_id=${this.progressId}`);
                } catch (error) {
                    console.error('Cleanup error:', error);
                }
            }

            updateProgressUI(progress, message) {
                const progressValue = Number.isFinite(Number(progress)) ? Number(progress) : 0;
                if (progressValue > 0) this.taskProgress.stopCreep();
                this.taskProgress.show();
                this.taskProgress.set(progressValue);
                if (this.elements.progressBar) {
                    this.elements.progressBar.style.width = progressValue + '%';
                    this.elements.progressBar.textContent = Math.round(progressValue) + '%';
                }
                if (message) {
                    this.showStatus(message, 'info');
                }
            }

            resetUI() {
                this.elements.generateBtn.disabled = false;
                this.elements.stopBtn.style.display = 'none';
                this.elements.saveServerBtn.style.display = this.audioBlobUrl ? 'inline-block' : 'none';
                this.taskProgress.stopCreep();
                this.taskProgress.hide(0);
            }

            showStatus(message, type) {
                const status = this.elements.status;
                status.textContent = this.sanitizeMessage(message);
                status.className = 'status ' + type;
                status.style.display = 'block';

                if (type === 'success') {
                    setTimeout(() => {
                        status.style.display = 'none';
                    }, 3000);
                }
            }
        }

        const editor = new TTSEditor();
    
