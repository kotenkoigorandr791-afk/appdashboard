        class TranscriptionEditor {
            constructor() {
                this.audioPath = '<?php echo $apiAudioPath; ?>';
                this.user = '<?php echo $user; ?>';
                this.root = '<?php echo $root; ?>';
                this.fileFound = <?php echo ($sourcePath && file_exists($sourcePath)) ? 'true' : 'false'; ?>;
                this.isProcessing = false;
                this.progressInterval = null;
                this.currentTranscriptionFile = null;
                this.translations = <?php echo json_encode($t); ?>;
                this.eventSource = null;
                this.progressId = null;
                this.lastProgressTime = 0;
                this.zeroProgressSince = 0;
                this.currentProgress = 0;
                this.canViewDebugLog = <?php echo $canViewDebugLog ? 'true' : 'false'; ?>;
                this.isDebugScrolledToBottom = true; // Track if user is at bottom of debug log
                this.audioPlayer = null;
                this.fileLoadProgress = null;
                this.taskProgress = null;
                if (!this.fileFound) console.error('Transcription Editor: file not found');

                this.initializeElements();
                this.initProgressOverlays();
                this.bindEvents();
                this.restoreProgressFromStorage();
                this.checkForActiveProgress();
                this.setLanguageSelects();
                this.initializeAudioPlayer();
                this.setupDebugScrollTracking();
                this.setupDebugToggle();
            }
            normalizeProgressId(progressId) {
                return (progressId || '').replace(/^progress_/, '');
            }
            logText(key, fallback) {
                return this.translations[key] || fallback;
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
            translateBackendMessage(message) {
                const map = {
                    'Preparing startup...': this.translations.starting_transcription,
                    'Loading Whisper model...': this.logText('log_model_loading', 'Loading Whisper model...'),
                    'Preparing audio...': this.logText('log_audio_preparing', 'Preparing audio...'),
                    'Transcribing audio...': this.logText('log_transcribing', 'Transcribing audio...'),
                    'Saving result...': this.logText('log_saving_result', 'Saving result...'),
                    'Completed': this.translations.transcription_completed
                };
                return map[message] || message;
            }

            async restoreProgressFromStorage() {
                // Restore progress from localStorage if available
                const storageKey = 'transcription_progress_' + this.audioPath;
                const saved = localStorage.getItem(storageKey);
                
                if (saved) {
                    try {
                        const data = JSON.parse(saved);
                        const timeSinceUpdate = Date.now() - data.timestamp;
                        
                        if (timeSinceUpdate < 600000 && data.progressId && data.progress < 100 && data.progress > 0) {
                            const restoredProgressId = this.normalizeProgressId(data.progressId);
                            const isValid = await this.validateProgressSession(restoredProgressId);
                            if (!isValid) {
                                localStorage.removeItem(storageKey);
                                return;
                            }
                            this.progressId = restoredProgressId;
                            this.isProcessing = true;
                            this.elements.startBtn.disabled = true;
                            this.taskProgress.show();
                            this.updateProgressUI(data.progress, data.message || 'Restoring session...');
                            
                            if (data.transcription) {
                                this.elements.transcriptionText.value = data.transcription;
                                if (this.elements.saveBtn) this.elements.saveBtn.disabled = false;
                            }
                            
                            this.showDebug(this.logText('log_restored_session', 'Restored session from localStorage'));
                            this.showDebug(`${this.logText('log_progress', 'Progress')}: ${data.progress}%`);
                            
                            // Resume SSE streaming
                            setTimeout(() => {
                                this.startSSEStreaming(this.progressId);
                            }, 500);
                        } else {
                            // Clean up old data
                            localStorage.removeItem(storageKey);
                        }
                    } catch (e) {
                        localStorage.removeItem(storageKey);
                    }
                }
            }
            async validateProgressSession(progressId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_progress');
                    formData.append('progress_id', progressId);
                    const response = await fetch('<?= htmlspecialchars($transcriptionApiUrl) ?>', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.progress >= 100) return false;
                    if (data.message && data.message.includes('Error:')) return false;
                    if (!data.isProcessing) return false;
                    return true;
                } catch (error) {
                    return false;
                }
            }

            saveProgressToStorage() {
                const storageKey = 'transcription_progress_' + this.audioPath;
                const data = {
                    progressId: this.progressId,
                    progress: this.currentProgress || 0,
                    message: this.elements.status.textContent,
                    transcription: this.elements.transcriptionText.value,
                    timestamp: Date.now()
                };
                localStorage.setItem(storageKey, JSON.stringify(data));
            }

            setupDebugToggle() {
                if (!this.canViewDebugLog || !this.elements.debugToggle || !this.elements.debugClose || !this.elements.debugOverlay) return;
                this.elements.debugToggle.addEventListener('click', () => {
                    this.toggleDebug();
                });
                this.elements.debugClose.addEventListener('click', () => {
                    this.closeDebug();
                });
            }

            toggleDebug() {
                const isOpen = this.elements.debugOverlay.classList.contains('open');
                if (isOpen) {
                    this.closeDebug();
                } else {
                    this.openDebug();
                }
            }

            openDebug() {
                if (!this.elements.debugOverlay || !this.elements.debugToggle) return;
                this.elements.debugOverlay.classList.add('open');
                this.elements.debugToggle.classList.add('open');
                // Scroll to bottom when opening
                if (this.elements.debugContent) this.elements.debugContent.scrollTop = this.elements.debugContent.scrollHeight;
            }

            closeDebug() {
                if (!this.elements.debugOverlay || !this.elements.debugToggle) return;
                this.elements.debugOverlay.classList.remove('open');
                this.elements.debugToggle.classList.remove('open');
            }

            initProgressOverlays() {
                const noop = { show() {}, reset() {}, set() {}, startCreep() {}, stopCreep() {}, complete() {}, hide() {}, fail() {}, async fetchBlob(url) { const response = await fetch(url); if (!response.ok) throw new Error('Failed to load file'); return response.blob(); } };
                this.fileLoadProgress = noop;
                this.taskProgress = noop;
            }

            initializeElements() {
                this.elements = {
                    startBtn: document.getElementById('btn-start'),
                    stopBtn: document.getElementById('btn-stop'),
                    saveBtn: document.getElementById('btn-save'),
                    transcriptionLanguageSelect: document.getElementById('transcription-language-select'),
                    transcriptionText: document.getElementById('transcription-text'),
                    progressContainer: document.getElementById('progress-container'),
                    progressBar: document.getElementById('progress-bar'),
                    progressText: document.getElementById('progress-text'),
                    status: document.getElementById('status'),
                    fileInfo: document.getElementById('file-info'),
                    audioPlayerContainer: document.getElementById('audio-player-container'),
                    debugOverlay: document.getElementById('debug-overlay'),
                    debugContent: document.getElementById('debug-content'),
                    debugToggle: document.getElementById('debug-toggle-btn'),
                    debugClose: document.getElementById('debug-close-btn')
                };
            }

            bindEvents() {
                this.elements.startBtn.addEventListener('click', () => this.startTranscription());
                this.elements.stopBtn.addEventListener('click', () => this.stopTranscription());
                this.elements.transcriptionText.addEventListener('input', () => this.onTranscriptionChange());
                this.elements.transcriptionLanguageSelect.addEventListener('change', () => this.onTranscriptionLanguageChange());

                // Copy button
                const copyBtn = document.getElementById('copy-btn');
                if (copyBtn) {
                    copyBtn.addEventListener('click', () => this.copyTranscription());
                }

                // Save button
                if (this.elements.saveBtn) {
                    this.elements.saveBtn.addEventListener('click', () => this.saveTranscription());
                }

                // Cleanup on page unload
                window.addEventListener('beforeunload', () => {
                    this.stopProgressTracking();
                    const storageKey = 'transcription_progress_' + this.audioPath;
                    localStorage.removeItem(storageKey);
                });
            }

            setLanguageSelects() {
                const currentTranscriptionLang = '<?php echo $transcriptionLang; ?>';
                this.elements.transcriptionLanguageSelect.value = currentTranscriptionLang;
            }

            async initializeAudioPlayer() {
                const container = this.elements.audioPlayerContainer;
                if (!container) return;
                const audioUrl = container.getAttribute('data-audio-url');
                if (!audioUrl) return;
                try {
                    const blob = await this.fileLoadProgress.fetchBlob(audioUrl, { downloadWeight: 96 });
                    const objUrl = URL.createObjectURL(blob);
                    this.audioPlayer = new CustomAudioPlayer(container, objUrl, {
                        autoplay: false,
                        showVolume: true
                    });
                    this.fileLoadProgress.complete();
                } catch (error) {
                    this.fileLoadProgress.fail(this.logText('error_prefix', 'Error'));
                    this.showDebug(this.logText('error_prefix', 'Error') + ': ' + error.message);
                }
            }

            async checkForActiveProgress() {
                // Check if there's an active transcription session to restore
                try {
                    const formData = new FormData();
                    formData.append('action', 'find_active_progress');
                    formData.append('user', this.user);

                    const response = await fetch('<?= htmlspecialchars($transcriptionApiUrl) ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success && data.active_session) {
                        const session = data.active_session;
                        this.progressId = this.normalizeProgressId(session.progress_id);
                        this.isProcessing = true;
                        this.elements.startBtn.disabled = true;
                        this.taskProgress.show();
                        
                        this.showDebug(this.logText('log_restore_active', 'Restoring active transcription session...'));
                        this.showDebug('ID: ' + session.progress_id);
                        this.showDebug(`${this.logText('log_progress', 'Progress')}: ${session.progress}%`);
                        
                        // Restore progress UI
                        this.updateProgressUI(session.progress, session.message);
                        
                        // If there's partial transcription, show it
                        if (session.transcription) {
                            this.elements.transcriptionText.value = session.transcription;
                            if (this.elements.saveBtn) this.elements.saveBtn.disabled = false;
                            this.showDebug(this.logText('log_partial_restored', 'Partial transcription restored'));
                        }
                        
                        // Continue monitoring via SSE
                        this.startSSEStreaming(this.progressId);
                    }
                } catch (error) {
                    this.showDebug(this.logText('log_no_active', 'No active progress found') + ': ' + error.message);
                }
            }

            updateProgressUI(percentage, message) {
                const progressValue = Number.isFinite(Number(percentage)) ? Number(percentage) : 0;
                this.currentProgress = progressValue;
                if (progressValue > 0) this.taskProgress.stopCreep();
                if (progressValue === 0 && this.isProcessing) this.taskProgress.startCreep(92);
                this.taskProgress.show();
                this.taskProgress.set(progressValue);
                if (this.elements.progressBar) {
                    this.elements.progressBar.style.width = progressValue + '%';
                }
                if (this.elements.progressText) {
                    this.elements.progressText.textContent = Math.round(progressValue) + '%';
                }
                if (this.elements.progressContainer) {
                    this.elements.progressContainer.style.display = progressValue > 0 && progressValue < 100 ? 'inline-flex' : 'none';
                }
                if (message) {
                    const localizedMessage = this.translateBackendMessage(message);
                    this.showStatus(localizedMessage, 'info');
                    this.showDebug(`${this.logText('log_progress', 'Progress')}: ${progressValue}% - ${localizedMessage}`);
                }
            }

            startSSEStreaming(progressId) {
                const normalizedProgressId = this.normalizeProgressId(progressId);
                // Close existing connection if any
                if (this.eventSource) {
                    this.eventSource.close();
                }
                const url = `<?= htmlspecialchars($transcriptionApiUrl) ?>?action=stream_progress&progress_id=${encodeURIComponent(normalizedProgressId)}`;
                this.showDebug(this.logText('log_sse_start', 'Starting SSE stream for progress') + ': ' + normalizedProgressId);

                this.eventSource = new EventSource(url);

                this.eventSource.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);

                        // Debug: log received data
                        this.showDebug('SSE data received');

                        if (data.done) {
                            this.showDebug(this.logText('log_sse_done', 'SSE streaming completed'));
                            this.eventSource.close();
                            if (this.currentProgress >= 100) this.transcriptionComplete();
                            else this.stopProgressTracking();
                            return;
                        }

                        if (data.error) {
                            this.showStatus(this.logText('error_prefix', 'Error') + ': ' + data.error, 'error');
                            this.showDebug(this.logText('log_sse_error', 'SSE error') + ': ' + data.error);
                            this.eventSource.close();
                            this.stopProgressTracking();
                            return;
                        }

                        // Update progress - use percentage if progress is not available
                        const progressValue = data.progress ?? data.percentage ?? 0;
                        this.updateProgressUI(progressValue, data.message);
                        if (progressValue === 0 && data.isProcessing) {
                            if (!this.zeroProgressSince) this.zeroProgressSince = Date.now();
                            if (Date.now() - this.zeroProgressSince > 120000) {
                                this.showStatus(this.logText('error_prefix', 'Error') + ': startup timeout', 'error');
                                this.stopProgressTracking();
                                const storageKey = 'transcription_progress_' + this.audioPath;
                                localStorage.removeItem(storageKey);
                                return;
                            }
                        } else {
                            this.zeroProgressSince = 0;
                        }

                        // Update transcription if available
                        if (data.transcription) {
                            this.elements.transcriptionText.value = data.transcription;
                        }

                        // Save to localStorage for persistence
                        this.saveProgressToStorage();

                        // Check completion
                        if (progressValue >= 100) {
                            this.eventSource.close();
                            this.transcriptionComplete();
                        } else if (progressValue === 0 && data.message && data.message.includes('Error:')) {
                            this.showStatus(this.logText('error_prefix', 'Error') + ': ' + data.message, 'error');
                            this.eventSource.close();
                            this.stopProgressTracking();
                        }
                    } catch (error) {
                        this.showDebug(this.logText('error_prefix', 'Error') + ': ' + error.message);
                    }
                };

                this.eventSource.onerror = (error) => {
                    this.showDebug(this.logText('log_sse_fallback', 'SSE connection error, falling back to polling'));
                    this.eventSource.close();
                    this.eventSource = null;
                    // Fallback to polling
                    this.startProgressTracking();
                };

                this.eventSource.onopen = () => {
                    this.showDebug(this.logText('log_sse_open', 'SSE connection established'));
                };
            }

            onTranscriptionLanguageChange() {
                const newTranscriptionLang = this.elements.transcriptionLanguageSelect.value;
                const url = new URL(window.location.href);
                url.searchParams.set('transcription_lang', newTranscriptionLang);
                window.history.replaceState({}, '', url.toString());
            }



            async startTranscription() {
                if (this.isProcessing) return;

                const transcriptionLanguage = this.elements.transcriptionLanguageSelect.value;

                this.isProcessing = true;
                this.elements.startBtn.disabled = true;
                this.elements.startBtn.style.display = 'none';
                this.elements.stopBtn.style.display = '';
                this.elements.saveBtn.style.display = 'none';
                this.taskProgress.show();
                this.taskProgress.reset();
                this.taskProgress.startCreep(92);
                this.zeroProgressSince = Date.now();
                this.showStatus(this.translations.starting_transcription, 'info');
                this.showDebug(this.logText('log_starting_process', 'Starting transcription process...'));

                try {
                    const formData = new FormData();
                    formData.append('action', 'start_transcription');
                    formData.append('audio_path', this.audioPath);
                    formData.append('language', transcriptionLanguage);
                    formData.append('user', this.user);

                    this.showDebug(this.logText('log_sending_request', 'Sending request to transcription_api.php...'));
                    this.showDebug(this.logText('log_transcription_language', 'Transcription language') + ': ' + transcriptionLanguage);
                    this.showDebug(this.logText('log_audio_file', 'Audio file') + ': ' + this.audioPath.split('/').pop());

                    const response = await fetch('<?= htmlspecialchars($transcriptionApiUrl) ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showStatus(this.translations.transcription_started, 'success');
                        this.showDebug(this.translations.transcription_started + ': ' + JSON.stringify(data, null, 2));
                        this.currentTranscriptionFile = data.transcription_file;
                        this.progressId = this.normalizeProgressId(data.progress_id);
                        
                        // Start SSE streaming for real-time updates
                        this.startSSEStreaming(this.progressId);
                    } else {
                        this.showStatus(this.logText('error_prefix', 'Error') + ': ' + data.error, 'error');
                        this.showDebug(this.logText('error_prefix', 'Error') + ': ' + data.error);
                        this.isProcessing = false;
                        this.elements.startBtn.disabled = false;
                    }
                } catch (error) {
                    this.showStatus(this.logText('network_error_prefix', 'Network error') + ': ' + error.message, 'error');
                    this.showDebug(this.logText('network_error_prefix', 'Network error') + ': ' + error.message);
                    this.isProcessing = false;
                    this.elements.startBtn.disabled = false;
                }
            }

            async stopTranscription() {
                if (!this.progressId) return;

                try {
                    const formData = new FormData();
                    formData.append('action', 'stop_transcription');
                    formData.append('progress_id', this.progressId);

                    const response = await fetch('<?= htmlspecialchars($transcriptionApiUrl) ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showStatus(this.translations.transcription_stopped, 'info');
                        this.showDebug(this.translations.transcription_stopped);
                    } else {
                        this.showStatus(this.logText('error_prefix', 'Error') + ': ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (error) {
                    this.showStatus(this.logText('network_error_prefix', 'Network error') + ': ' + error.message, 'error');
                }

                // Stop SSE and polling
                if (this.eventSource) {
                    this.eventSource.close();
                    this.eventSource = null;
                }
                if (this.progressInterval) {
                    clearInterval(this.progressInterval);
                    this.progressInterval = null;
                }

                // Reset UI
                this.isProcessing = false;
                this.elements.startBtn.disabled = false;
                this.elements.startBtn.style.display = '';
                this.elements.stopBtn.style.display = 'none';
                this.elements.saveBtn.style.display = 'none';
                this.progressId = null;
            }

            startProgressTracking() {
                if (this.progressInterval) {
                    clearInterval(this.progressInterval);
                }

                // Poll every 2 seconds as backup/fallback
                this.progressInterval = setInterval(() => {
                    this.checkProgress();
                }, 2000);
            }

            async checkProgress() {
                if (!this.progressId) return;

                try {
                    const formData = new FormData();
                    formData.append('action', 'get_progress');
                    formData.append('progress_id', this.progressId);

                    const response = await fetch('<?= htmlspecialchars($transcriptionApiUrl) ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    const progressValue = data.progress ?? data.percentage ?? 0;
                    this.updateProgressUI(progressValue, data.message);

                    if (data.transcription) {
                        this.elements.transcriptionText.value = data.transcription;
                    }

                    // Save to localStorage for persistence
                    this.saveProgressToStorage();
                    if (progressValue === 0 && data.stale_seconds >= 45) {
                        this.showStatus(this.logText('error_prefix', 'Error') + ': process not responding', 'error');
                        this.stopProgressTracking();
                        const storageKey = 'transcription_progress_' + this.audioPath;
                        localStorage.removeItem(storageKey);
                        return;
                    }

                    if (data.message && data.message.includes('Error:')) {
                        this.showStatus(this.logText('error_prefix', 'Error') + ': ' + data.message, 'error');
                        this.showDebug(this.logText('error_prefix', 'Error') + ': ' + data.message);
                        this.stopProgressTracking();
                    } else if (progressValue === 100) {
                        this.transcriptionComplete();
                    } else if (progressValue === 0 && !data.isProcessing) {
                        this.showStatus(this.translations.waiting_transcription, 'info');
                    }
                } catch (error) {
                    console.error('Error checking progress:', this.sanitizeMessage(error.message));
                    this.showDebug(this.logText('error_prefix', 'Error') + ': ' + error.message);
                }
            }

            stopProgressTracking() {
                if (this.progressInterval) {
                    clearInterval(this.progressInterval);
                    this.progressInterval = null;
                }

                if (this.eventSource) {
                    this.eventSource.close();
                    this.eventSource = null;
                }

                this.isProcessing = false;
                this.elements.startBtn.disabled = false;
                this.elements.startBtn.style.display = '';
                this.elements.stopBtn.style.display = 'none';
                this.elements.saveBtn.style.display = 'none';
                this.zeroProgressSince = 0;
                this.taskProgress.stopCreep();
                this.taskProgress.hide(0);
            }

            async transcriptionComplete() {
                this.stopProgressTracking();
                this.taskProgress.set(100);
                this.taskProgress.complete();
                this.showStatus(this.translations.transcription_completed, 'success');
                this.showDebug(this.translations.transcription_completed);

                // Show save button in the header
                if (this.elements.saveBtn) {
                    this.elements.saveBtn.style.display = '';
                    this.elements.saveBtn.disabled = false;
                }

                // Clean up localStorage
                const storageKey = 'transcription_progress_' + this.audioPath;
                localStorage.removeItem(storageKey);

                // Delete temporary upload file
                await this.deleteTemporaryFile();

                // Transcription is already on screen from SSE streaming
            }

            async deleteTemporaryFile() {
                try {
                    const response = await fetch('<?= htmlspecialchars($transcriptionApiUrl) ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'delete_temp_file',
                            progress_id: this.progressId
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        this.showDebug('Temporary file deleted');
                    }
                } catch (error) {
                    console.error('Failed to delete temporary file:', this.sanitizeMessage(error.message));
                }
            }

            onTranscriptionChange() {
                // Save button is managed by transcriptionComplete() only
            }

            async saveTranscription() {
                const text = this.elements.transcriptionText.value.trim();
                if (!text) {
                    this.showStatus('Nothing to save', 'error');
                    return;
                }

                try {
                    this.elements.saveBtn.disabled = true;
                    this.elements.saveBtn.innerHTML = '<span>Saving...</span>';
                    this.showStatus('Saving transcription to server...', 'info');

                    const formData = new FormData();
                    formData.append('action', 'save_transcription_to_server');
                    formData.append('audio_path', this.audioPath);
                    formData.append('user', this.user);
                    formData.append('transcription', text);

                    const response = await fetch('<?= htmlspecialchars($transcriptionApiUrl) ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showStatus('Transcription saved', 'success');
                        this.showDebug('Transcription saved');

                        // Notify parent window to refresh file list
                        if (window.parent) {
                            window.parent.postMessage({
                                type: 'fileCreated',
                                path: result.saved_path,
                                root: this.root,
                                name: (result.saved_path || '').split('/').pop()
                            }, '*');
                        }
                    } else {
                        throw new Error(result.error || 'Failed to save transcription');
                    }
                } catch (error) {
                    console.error('Save error:', this.sanitizeMessage(error.message));
                    this.showStatus('Error: ' + this.sanitizeMessage(error.message), 'error');
                    this.showDebug('Save error: ' + this.sanitizeMessage(error.message));
                } finally {
                    this.elements.saveBtn.disabled = false;
                    this.elements.saveBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg><span>Save</span>';
                }
            }

            async copyTranscription() {
                const text = this.elements.transcriptionText.value;
                const copyBtn = document.getElementById('copy-btn');
                const copyBtnText = document.getElementById('copy-btn-text');

                if (!text) {
                    return;
                }

                try {
                    // Check if clipboard API is available
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        // Fallback for older browsers or insecure contexts
                        const textarea = document.createElement('textarea');
                        textarea.value = text;
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                    }

                    // Success animation
                    copyBtn.classList.remove('copy-error');
                    copyBtn.classList.remove('copy-success');
                    void copyBtn.offsetWidth;
                    copyBtn.classList.add('copy-success');
                    copyBtnText.textContent = '✓ Copied';

                    setTimeout(() => {
                        copyBtn.classList.remove('copy-success');
                        copyBtnText.textContent = 'Copy';
                    }, 3000);
                } catch (err) {
                    console.error('Failed to copy:', err);
                    copyBtn.classList.remove('copy-success');
                    copyBtn.classList.remove('copy-error');
                    void copyBtn.offsetWidth;
                    copyBtn.classList.add('copy-error');
                    copyBtnText.textContent = '✗ Failed';
                    setTimeout(() => {
                        copyBtn.classList.remove('copy-error');
                        copyBtnText.textContent = 'Copy';
                    }, 2000);
                }
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

            showDebug(message) {
                if (!this.canViewDebugLog || !this.elements.debugToggle || !this.elements.debugContent) return;
                // Make toggle button visible when there are logs
                this.elements.debugToggle.classList.add('visible');
                const safeMessage = this.sanitizeMessage(message);

                // Parse and highlight the log message
                const timestamp = new Date().toLocaleTimeString();
                const logEntry = document.createElement('div');
                logEntry.className = 'log-entry';

                // Parse the message for different patterns
                let highlightedMessage = safeMessage;

                // Highlight JSON objects
                const jsonMatch = safeMessage.match(/(\{.*\})/);
                if (jsonMatch) {
                    const beforeJson = safeMessage.substring(0, jsonMatch.index);
                    const jsonPart = jsonMatch[1];
                    highlightedMessage = this.highlightText(beforeJson) +
                        '<span class="log-json">' + this.escapeHtml(jsonPart) + '</span>';
                } else {
                    highlightedMessage = this.highlightText(safeMessage);
                }

                logEntry.innerHTML = '<span class="log-time">' + timestamp + '</span>' +
                    '<span class="log-separator">: </span>' + highlightedMessage;

                this.elements.debugContent.appendChild(logEntry);

                // Auto-scroll only if user was at the bottom
                if (this.isDebugScrolledToBottom) {
                    this.elements.debugContent.scrollTop = this.elements.debugContent.scrollHeight;
                }
            }

            setupDebugScrollTracking() {
                if (!this.canViewDebugLog || !this.elements.debugContent) return;
                // Track scroll position to determine if user is at bottom
                this.elements.debugContent.addEventListener('scroll', () => {
                    const element = this.elements.debugContent;
                    const threshold = 50; // pixels from bottom to consider "at bottom"
                    const isAtBottom = element.scrollHeight - element.scrollTop - element.clientHeight < threshold;
                    this.isDebugScrolledToBottom = isAtBottom;
                });
            }

            highlightText(text) {
                let result = this.escapeHtml(text);

                // Highlight progress percentages (e.g., "Progress: 50%")
                result = result.replace(/Progress:\s*(\d+%)/gi,
                    '<span class="log-progress">Progress:</span> <span class="log-percentage">$1</span>');

                // Highlight percentage values (e.g., "50%")
                result = result.replace(/(\d+%)/g, '<span class="log-percentage">$1</span>');

                // Highlight success messages
                result = result.replace(/(successfully|completed|success|started|✓)/gi,
                    '<span class="log-success">$1</span>');

                // Highlight error messages
                result = result.replace(/(error|failed|✗|timeout)/gi,
                    '<span class="log-error">$1</span>');

                // Highlight info keywords
                result = result.replace(/(SSE|Loading|Preparing|Transcribing|Saving|Starting|Sending|Restoring)/gi,
                    '<span class="log-info">$1</span>');

                // Highlight key-value pairs (e.g., "Duration: 457.66")
                result = result.replace(/([A-Za-z\s]+):\s*([0-9.]+)/g,
                    '<span class="log-key">$1:</span> $2');

                return result;
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }

        // Initialize the editor when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            new TranscriptionEditor();
        });
    
