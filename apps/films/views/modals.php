<?php if ($user_access_level >= 1): ?>
<div class="modal" id="addMovieModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo $t['add_new_movie']; ?></h2>
            <button class="modal-close" id="closeModal">&times;</button>
        </div>
        <form id="movieForm" enctype="multipart/form-data">
            <div class="form-group">
                <label><?php echo $t['movie_title']; ?></label>
                <input type="text" class="form-control" id="movieTitle" required placeholder="<?php echo addslashes($t['movie_title'] ?? 'Movie Title'); ?>">
            </div>
            <div class="form-group">
                <label><?php echo $t['source_language']; ?></label>
                <select class="form-control" id="sourceLang" required>
                    <option value="en">English</option>
                    <option value="ru">Русский</option>
                    <option value="uk">Українська</option>
                    <option value="be">Беларуская</option>
                </select>
            </div>
            <div class="form-group">
                <label><?php echo $t['year']; ?></label>
                <input type="number" class="form-control" id="movieYear" min="1900" max="<?php echo date('Y') + 5; ?>" required placeholder="e.g., 2024">
            </div>
            <div class="form-group">
                <label><?php echo $t['content_type']; ?></label>
                <select class="form-control" id="contentType" required>
                    <option value="link"><?php echo $t['online_link']; ?></option>
                    <option value="local"><?php echo $t['local_file']; ?></option>
                </select>
            </div>
            <div class="form-group" id="linkInputGroup">
                <label><?php echo $t['link']; ?></label>
                <input type="url" class="form-control" id="movieLink" placeholder="https://...">
            </div>
            <div class="form-group" id="fileInputGroup" style="display: none;">
                <label><?php echo $t['upload_file']; ?> (MP4, WebM, or OGG only)</label>
                <input type="file" class="form-control" id="movieFile" accept="video/mp4,video/webm,video/ogg">
            </div>
            <div class="form-group">
                <label><?php echo $t['category']; ?></label>
                <select class="form-control" id="movieCategory" required>
                    <option value=""><?php echo $t['select_category']; ?></option>
                    <?php foreach (array_keys($t['categories']) as $category): ?>
                    <option value="<?php echo $category; ?>"><?php echo $t['categories'][$category]; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?php echo $t['rating']; ?></label>
                <div class="rating-input" id="ratingInput">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="rating-star" data-rating="<?php echo $i; ?>">★</span>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--outline" id="cancelBtn"><?php echo $t['cancel']; ?></button>
                <button type="submit" class="btn btn--primary" id="saveMovieBtn"><?php echo $t['save']; ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($user_access_level >= 2): ?>
<div class="modal" id="settingsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo $t['settings']; ?></h2>
            <button class="modal-close" id="closeSettingsModal">&times;</button>
        </div>

        <div class="admin-tabs">
            <div class="admin-tab active" onclick="switchAdminTab('movies')"><?php echo $t['manage_movies']; ?></div>
            <div class="admin-tab" onclick="switchAdminTab('comments')"><?php echo $t['manage_comments'] ?? 'Manage Comments'; ?></div>
            <div class="admin-tab" onclick="switchAdminTab('video')"><?php echo $t['video_settings'] ?? 'Video Settings'; ?></div>
        </div>

        <div id="moviesPanel" class="admin-panel show">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th><?php echo $t['title']; ?></th>
                        <th><?php echo $t['year']; ?></th>
                        <th><?php echo $t['category']; ?></th>
                        <th><?php echo $t['type']; ?></th>
                        <th><?php echo $t['visible']; ?></th>
                        <th><?php echo $t['actions']; ?></th>
                    </tr>
                </thead>
                <tbody id="settingsMoviesList"></tbody>
            </table>
        </div>

        <div id="commentsPanel" class="admin-panel">
            <div class="form-group">
                <label><?php echo $t['select_movie'] ?? 'Select Movie'; ?></label>
                <select class="form-control" id="commentMovieSelect" onchange="loadAdminComments()">
                    <option value=""><?php echo $t['select_movie'] ?? 'Select a movie...'; ?></option>
                </select>
            </div>
            <div id="adminCommentsList" class="comments-list"></div>
        </div>

        <div id="videoPanel" class="admin-panel">
            <form id="videoSettingsForm">
                <div class="form-group">
                    <label><?php echo $t['video_seek_step_seconds'] ?? 'Seek Step (seconds)'; ?></label>
                    <input type="number" class="form-control" id="seekStep" min="1" max="60" value="10" required>
                    <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;"><?php echo $t['seek_step_description'] ?? 'Left/Right arrow keys will jump this many seconds'; ?></small>
                </div>
                <div class="form-group">
                    <label><?php echo $t['volume_step_percent'] ?? 'Volume Step (%)'; ?></label>
                    <input type="number" class="form-control" id="volumeStep" min="1" max="50" value="10" required>
                    <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;"><?php echo $t['volume_step_description'] ?? 'Up/Down arrow keys will adjust volume by this percentage'; ?></small>
                </div>
                <button type="submit" class="btn btn--primary"><?php echo $t['save_settings'] ?? 'Save Video Settings'; ?></button>
            </form>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn--outline" id="closeSettingsBtn"><?php echo $t['close']; ?></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal video-player-modal" id="videoPlayerModal">
    <div class="modal-content">
        <button class="video-player-close" id="closeVideoModal">&times;</button>
        <div class="video-info-overlay" id="videoInfoOverlay"></div>
        <div class="video-controls-hint" id="videoControlsHint" style="display: none;">
            <?php echo $t['keyboard_controls'] ?? 'Keyboard: Space=Play/Pause, ←/→=Seek, ↑/↓=Volume'; ?>
        </div>
        <div class="video-container" id="videoContainer">
            <div id="playerContainer"></div>
        </div>
    </div>
</div>

<div class="modal" id="commentsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="commentsModalTitle"><?php echo $t['comments'] ?? 'Comments'; ?></h2>
            <button class="modal-close" id="closeCommentsModal">&times;</button>
        </div>
        <div class="comments-section">
            <div class="comments-header">
                <h3><?php echo $t['user_comments'] ?? 'User Comments'; ?></h3>
            </div>
            <div class="comments-list" id="commentsList"></div>
            <div class="comment-form">
                <h4><?php echo $t['add_comment'] ?? 'Add Comment'; ?></h4>
                <form id="commentForm">
                    <input type="hidden" id="commentMovieId">
                    <div class="form-group">
                        <label for="commentUserName"><?php echo $t['your_name'] ?? 'Your Name'; ?></label>
                        <input type="text" class="form-control" id="commentUserName" required placeholder="<?php echo addslashes($t['your_name'] ?? 'Your Name'); ?>">
                    </div>
                    <div class="comment-rating-input">
                        <label><?php echo $t['your_rating'] ?? 'Your Rating'; ?>:</label>
                        <div class="comment-rating-stars" id="commentRatingInput">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="comment-rating-star" data-rating="<?php echo $i; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <span id="commentRatingValue" style="color: var(--primary-light); font-weight: 600;">0</span>
                    </div>
                    <div class="form-group">
                        <label for="commentText"><?php echo $t['your_comment'] ?? 'Your Comment'; ?></label>
                        <textarea class="form-control" id="commentText" rows="3" required placeholder="<?php echo addslashes($t['your_comment'] ?? 'Write your comment...'); ?>"></textarea>
                    </div>
                    <button type="submit" class="btn btn--primary"><?php echo $t['submit_comment'] ?? 'Submit Comment'; ?></button>
                </form>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn--outline" id="closeCommentsBtn"><?php echo $t['close']; ?></button>
        </div>
    </div>
</div>

