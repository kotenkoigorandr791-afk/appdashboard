<script>
// Support Chat functionality
(function() {
    const chatModal = document.getElementById('support-chat-modal');
    const chatBtn = document.getElementById('support-chat-btn');
    const closeChatBtn = document.getElementById('close-chat-modal');
    const chatInput = document.getElementById('chat-input');
    const chatSendBtn = document.getElementById('chat-send-btn');
    const chatMessages = document.getElementById('chat-messages');
    const chatUploadBtn = document.getElementById('chat-upload-btn');
    const chatFileInput = document.getElementById('chat-file-input');
    const chatMessagesArea = document.getElementById('chat-messages-area');
    const adminChatList = document.getElementById('admin-chat-list');
    const chatListContainer = document.getElementById('chat-list-container');
    const chatModalTitle = document.getElementById('chat-modal-title');
    const chatHeaderAvatar = document.getElementById('chat-header-avatar');
    const contextMenu = document.getElementById('chat-context-menu');
    const contextEdit = document.getElementById('context-edit');
    const contextDelete = document.getElementById('context-delete');
    const deleteChatBtn = document.getElementById('delete-chat-btn');
    const blockChatBtn = document.getElementById('block-chat-btn');
    const newChatBtn = document.getElementById('new-chat-btn');
    const supportChatDeleteModal = document.getElementById('support-chat-delete-modal');
    const supportChatDeleteEmail = document.getElementById('support-chat-delete-email');
    const supportChatDeleteCard = document.getElementById('support-chat-delete-card');
    const supportChatDeleteCancel = document.getElementById('support-chat-delete-cancel');
    const supportChatDeleteConfirm = document.getElementById('support-chat-delete-confirm');
    let chatSendBtnHtml = '';
    if (chatSendBtn) {
        chatSendBtnHtml = chatSendBtn.innerHTML;
    }
    if (!chatModal) {
        return;
    }

    let currentChatUser = null;
    let currentChatType = 'support';
    let currentPeerEmail = '';
    let chatPendingPlan = null;
    let chatPendingPlanCount = 0;
    let currentChatTitle = '';
    let currentPeerBlocked = false;
    let isAdmin = <?php echo json_encode(isset($_SESSION['user']) && (int)($_SESSION['user']['role'] ?? 0) >= 2); ?>;
    let currentUserEmail = <?php echo json_encode($_SESSION['user']['email'] ?? '', JSON_HEX_TAG); ?>;
    let chatPollInterval = null;
    let lastMessagesHash = '';
    let editingMessageId = null;
    let contextMessageId = null;
    let messagesData = [];
    let lastAdminChatList = [];
    let chatOldestId = null;
    let chatMaxId = 0;
    let chatHasMoreOlder = false;
    let chatLoadingOlder = false;
    let chatScrollWired = false;
    let chatLightboxWired = false;
    let chatNavKeysWired = false;
    const adminDraftEdits = {};
    const userSearchModal = document.getElementById('chat-user-search-modal');
    const userSearchInput = document.getElementById('chat-user-search-input');
    const userSearchResults = document.getElementById('chat-user-search-results');
    const userSearchCancel = document.getElementById('chat-user-search-cancel');
    const chatTypeModal = document.getElementById('chat-type-modal');
    const chatTypeSupport = document.getElementById('chat-type-support');
    const chatTypePeer = document.getElementById('chat-type-peer');
    const chatTypeCancel = document.getElementById('chat-type-cancel');
    let userSearchTimer = null;
    let pendingNewChatType = 'peer';

    function chatSetPendingPlan(plan, count) {
        if (!plan || !Array.isArray(plan.elements)) return;
        chatPendingPlan = plan;
        chatPendingPlanCount = typeof count === 'number' ? count : (Array.isArray(plan.elements) ? plan.elements.length : 0);
        const chip = document.getElementById('chat-pending-plan');
        const label = document.getElementById('chat-pending-plan-label');
        if (chip) {
            if (label) {
                label.textContent = (T.support_chat_plan_attached || 'Floor plan attached') + ' · ' + chatPendingPlanCount;
            }
            chip.style.display = 'flex';
        }
        if (chatInput) {
            chatInput.focus();
        }
    }
    function chatClearPendingPlan() {
        chatPendingPlan = null;
        chatPendingPlanCount = 0;
        const chip = document.getElementById('chat-pending-plan');
        if (chip) {
            chip.style.display = 'none';
        }
    }
    function chatOpenPendingPlanChat() {
        if (chatModal && chatModal.style.display !== 'flex') {
            if (isAdmin) {
                window.location.hash = '#support-chat';
            } else {
                chatModal.style.display = 'flex';
                if (adminChatList) {
                    adminChatList.style.display = 'flex';
                }
                adminMobilePhase = currentChatUser && isChatMobileMatch() ? 'chat' : 'list';
                syncAdminMobileLayout();
                loadChatList();
            }
        }
        if (typeof showTempNotification === 'function') {
            showTempNotification(T.support_chat_plan_attached_hint || 'Floor plan attached — select a recipient and send', 'success');
        }
    }
    window.addEventListener('message', function(e) {
        const d = e.data;
        if (!d || d.type !== 'smart_home_send_floor_plan') return;
        chatSetPendingPlan(d.plan, d.count);
        chatOpenPendingPlanChat();
    });
    function openChatAlertModal(msg) {
        const md = document.getElementById('fm-alert-modal');
        const msgEl = document.getElementById('fm-alert-msg');
        const ok = document.getElementById('fm-alert-ok');
        if (!md || !msgEl || !ok) {
            return;
        }
        msgEl.textContent = msg;
        md.style.display = 'flex';
        md.classList.add('fm-alert-open');
        md.setAttribute('aria-hidden', 'false');
        const close = function() {
            md.classList.remove('fm-alert-open');
            md.style.display = 'none';
            md.setAttribute('aria-hidden', 'true');
            ok.onclick = null;
            md.onclick = null;
        };
        ok.onclick = close;
        md.onclick = function(e) {
            if (e.target === md) close();
        };
        ok.focus();
    }
    function chatAlert(msg) {
        if (typeof fmAlert === 'function') {
            fmAlert(msg);
        } else {
            openChatAlertModal(msg);
        }
    }
    function escChat(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function escAttrChat(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
    }
    const supportChatLightbox = document.getElementById('support-chat-lightbox');
    const supportChatLightboxInner = document.getElementById('support-chat-lightbox-inner');
    const supportChatLightboxClose = document.getElementById('support-chat-lightbox-close');
    const chatCancelEditBtn = document.getElementById('chat-cancel-edit-btn');
    const chatAdminMobileBack = document.getElementById('chat-admin-mobile-back');
    const adminChatListCloseBtn = document.getElementById('admin-chat-list-close');
    let adminMobilePhase = 'chat';
    let chatMediaEditorOpen = false;
    function isAdminMobileMatch() {
        return isAdmin && typeof window.matchMedia === 'function' && window.matchMedia('(max-width:768px)').matches;
    }
    function syncAdminMobileLayout() {
        var cont = document.getElementById('chat-modal-container');
        if (!cont || !chatModal) {
            return;
        }
        if (!isChatMobileMatch()) {
            cont.classList.remove('support-chat-admin-mobile', 'chat-mobile-phase-list', 'chat-mobile-phase-chat');
            return;
        }
        cont.classList.add('support-chat-admin-mobile');
        if (adminMobilePhase === 'list') {
            cont.classList.remove('chat-mobile-phase-chat');
            cont.classList.add('chat-mobile-phase-list');
        } else {
            cont.classList.remove('chat-mobile-phase-list');
            cont.classList.add('chat-mobile-phase-chat');
        }
    }
    function isChatMobileMatch() {
        return typeof window.matchMedia === 'function' && window.matchMedia('(max-width:768px)').matches;
    }
    function currentChatQuery() {
        if (currentChatType === 'peer') {
            return '&chat_type=peer&peer_email=' + encodeURIComponent(currentPeerEmail);
        }
        return isAdmin ? '&chat_type=support&user_email=' + encodeURIComponent(currentChatUser || '') : '&chat_type=support';
    }
    function currentChatKey() {
        return currentChatType + ':' + (currentChatType === 'peer' ? currentPeerEmail : currentChatUser || currentUserEmail);
    }
    function setHeaderActions() {
        if (deleteChatBtn) {
            deleteChatBtn.style.display = currentChatUser ? 'inline-flex' : 'none';
        }
        if (blockChatBtn) {
            blockChatBtn.style.display = (!isAdmin && currentChatType === 'peer' && currentPeerEmail) ? 'block' : 'none';
            blockChatBtn.textContent = currentPeerBlocked ? 'Unblock' : 'Block';
        }
        var blocked = currentChatType === 'peer' && currentPeerBlocked;
        if (chatInput) chatInput.disabled = blocked;
        if (chatSendBtn) chatSendBtn.disabled = blocked;
        if (chatUploadBtn) chatUploadBtn.disabled = blocked;
    }
    function openChatMediaEditor(file, onDone) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            onDone(file);
            return;
        }
        var modal = document.getElementById('chat-media-editor-modal');
        var base = document.getElementById('chat-media-editor-base');
        var layer = document.getElementById('chat-media-editor-layer');
        var btnCrop = document.getElementById('chat-media-tool-crop');
        var btnArrow = document.getElementById('chat-media-tool-arrow');
        var btnDraw = document.getElementById('chat-media-tool-draw');
        var btnClear = document.getElementById('chat-media-tool-clear');
        var btnApplyCrop = document.getElementById('chat-media-apply-crop');
        var btnCancel = document.getElementById('chat-media-editor-cancel');
        var btnSend = document.getElementById('chat-media-editor-send');
        if (!modal || !base || !layer || !btnApplyCrop) {
            onDone(file);
            return;
        }
        var bctx = base.getContext('2d');
        var lctx = layer.getContext('2d');
        var ann = [];
        var cropMode = false;
        var crop = { x: 0, y: 0, w: 0, h: 0 };
        var tool = 'draw';
        var drag = null;
        var tmpArrow = null;
        var img = new Image();
        var blobUrl = URL.createObjectURL(file);
        function cleanup() {
            chatMediaEditorOpen = false;
            modal.style.display = 'none';
            document.body.style.overflow = '';
            URL.revokeObjectURL(blobUrl);
            img.onload = null;
            img.onerror = null;
            img.src = '';
            layer.removeEventListener('pointerdown', onPtrDown);
            layer.removeEventListener('pointermove', onPtrMove);
            layer.removeEventListener('pointerup', onPtrUp);
            layer.removeEventListener('pointercancel', onPtrUp);
        }
        function setToolActive() {
            [btnCrop, btnArrow, btnDraw].forEach(function(b) {
                if (b) {
                    b.classList.remove('chat-media-tool-active');
                }
            });
            if (cropMode && btnCrop) {
                btnCrop.classList.add('chat-media-tool-active');
            } else if (tool === 'arrow' && btnArrow) {
                btnArrow.classList.add('chat-media-tool-active');
            } else if (btnDraw) {
                btnDraw.classList.add('chat-media-tool-active');
            }
        }
        function drawCropChrome() {
            var cw = layer.width;
            var ch = layer.height;
            lctx.clearRect(0, 0, cw, ch);
            lctx.fillStyle = 'rgba(0,0,0,0.55)';
            lctx.fillRect(0, 0, cw, ch);
            lctx.globalCompositeOperation = 'destination-out';
            lctx.fillRect(crop.x, crop.y, crop.w, crop.h);
            lctx.globalCompositeOperation = 'source-over';
            lctx.strokeStyle = '#fff';
            lctx.lineWidth = 2;
            lctx.strokeRect(crop.x, crop.y, crop.w, crop.h);
            var H = 14;
            [[crop.x, crop.y], [crop.x + crop.w, crop.y], [crop.x, crop.y + crop.h], [crop.x + crop.w, crop.y + crop.h]].forEach(function(pt) {
                lctx.fillStyle = '#fff';
                lctx.fillRect(pt[0] - H / 2, pt[1] - H / 2, H, H);
            });
        }
        function drawAnn() {
            if (cropMode) {
                drawCropChrome();
                return;
            }
            var cw = layer.width;
            var ch = layer.height;
            lctx.clearRect(0, 0, cw, ch);
            lctx.lineCap = 'round';
            lctx.lineJoin = 'round';
            ann.forEach(function(p) {
                if (p.t === 'arrow') {
                    lctx.strokeStyle = '#ff3b30';
                    lctx.lineWidth = 4;
                    lctx.beginPath();
                    lctx.moveTo(p.x1, p.y1);
                    lctx.lineTo(p.x2, p.y2);
                    lctx.stroke();
                    var ang = Math.atan2(p.y2 - p.y1, p.x2 - p.x1);
                    var L = 16;
                    lctx.fillStyle = '#ff3b30';
                    lctx.beginPath();
                    lctx.moveTo(p.x2, p.y2);
                    lctx.lineTo(p.x2 - L * Math.cos(ang - Math.PI / 6), p.y2 - L * Math.sin(ang - Math.PI / 6));
                    lctx.lineTo(p.x2 - L * Math.cos(ang + Math.PI / 6), p.y2 - L * Math.sin(ang + Math.PI / 6));
                    lctx.closePath();
                    lctx.fill();
                } else if (p.t === 'path' && p.pts && p.pts.length > 1) {
                    lctx.strokeStyle = '#ffcc00';
                    lctx.lineWidth = 4;
                    lctx.beginPath();
                    lctx.moveTo(p.pts[0][0], p.pts[0][1]);
                    for (var i = 1; i < p.pts.length; i++) {
                        lctx.lineTo(p.pts[i][0], p.pts[i][1]);
                    }
                    lctx.stroke();
                }
            });
            if (tmpArrow) {
                lctx.strokeStyle = '#ff3b30';
                lctx.lineWidth = 4;
                lctx.setLineDash([6, 4]);
                lctx.beginPath();
                lctx.moveTo(tmpArrow.x1, tmpArrow.y1);
                lctx.lineTo(tmpArrow.x2, tmpArrow.y2);
                lctx.stroke();
                lctx.setLineDash([]);
            }
            if (drag && drag.k === 'draw' && drag.pts && drag.pts.length > 1) {
                lctx.strokeStyle = '#ffcc00';
                lctx.lineWidth = 4;
                lctx.lineCap = 'round';
                lctx.beginPath();
                lctx.moveTo(drag.pts[0][0], drag.pts[0][1]);
                for (var j = 1; j < drag.pts.length; j++) {
                    lctx.lineTo(drag.pts[j][0], drag.pts[j][1]);
                }
                lctx.stroke();
            }
        }
        function clampCrop() {
            var cw = base.width;
            var ch = base.height;
            crop.w = Math.max(24, Math.min(crop.w, cw));
            crop.h = Math.max(24, Math.min(crop.h, ch));
            crop.x = Math.max(0, Math.min(crop.x, cw - crop.w));
            crop.y = Math.max(0, Math.min(crop.y, ch - crop.h));
        }
        function hitCrop(mx, my) {
            var H = 18;
            if (Math.abs(mx - crop.x) <= H && Math.abs(my - crop.y) <= H) {
                return 'nw';
            }
            if (Math.abs(mx - (crop.x + crop.w)) <= H && Math.abs(my - crop.y) <= H) {
                return 'ne';
            }
            if (Math.abs(mx - crop.x) <= H && Math.abs(my - (crop.y + crop.h)) <= H) {
                return 'sw';
            }
            if (Math.abs(mx - (crop.x + crop.w)) <= H && Math.abs(my - (crop.y + crop.h)) <= H) {
                return 'se';
            }
            if (mx > crop.x && mx < crop.x + crop.w && my > crop.y && my < crop.y + crop.h) {
                return 'move';
            }
            return null;
        }
        function clientToCanvas(e) {
            var r = layer.getBoundingClientRect();
            var sx = layer.width / r.width;
            var sy = layer.height / r.height;
            return { x: (e.clientX - r.left) * sx, y: (e.clientY - r.top) * sy };
        }
        function applyCrop() {
            if (crop.w < 16 || crop.h < 16) {
                return;
            }
            var tmp = document.createElement('canvas');
            tmp.width = crop.w;
            tmp.height = crop.h;
            tmp.getContext('2d').drawImage(base, crop.x, crop.y, crop.w, crop.h, 0, 0, crop.w, crop.h);
            base.width = crop.w;
            base.height = crop.h;
            bctx.drawImage(tmp, 0, 0);
            layer.width = crop.w;
            layer.height = crop.h;
            ann = [];
            cropMode = false;
            btnApplyCrop.style.display = 'none';
            var pad = Math.round(Math.min(base.width, base.height) * 0.06);
            crop = { x: pad, y: pad, w: base.width - 2 * pad, h: base.height - 2 * pad };
            drawAnn();
            setToolActive();
        }
        function onPtrDown(e) {
            if (e.button !== 0) {
                return;
            }
            e.preventDefault();
            var p = clientToCanvas(e);
            if (cropMode) {
                var h = hitCrop(p.x, p.y);
                if (!h) {
                    return;
                }
                drag = { k: 'crop', h: h, sx: p.x, sy: p.y, o: { x: crop.x, y: crop.y, w: crop.w, h: crop.h } };
                layer.setPointerCapture(e.pointerId);
                return;
            }
            if (tool === 'arrow') {
                tmpArrow = { x1: p.x, y1: p.y, x2: p.x, y2: p.y };
                layer.setPointerCapture(e.pointerId);
                return;
            }
            if (tool === 'draw') {
                drag = { k: 'draw', pts: [[p.x, p.y]] };
                layer.setPointerCapture(e.pointerId);
            }
        }
        function onPtrMove(e) {
            if (!drag && !tmpArrow) {
                return;
            }
            e.preventDefault();
            var p = clientToCanvas(e);
            if (drag && drag.k === 'crop') {
                var dx = p.x - drag.sx;
                var dy = p.y - drag.sy;
                var o = drag.o;
                if (drag.h === 'move') {
                    crop.x = o.x + dx;
                    crop.y = o.y + dy;
                } else if (drag.h === 'se') {
                    crop.w = o.w + dx;
                    crop.h = o.h + dy;
                } else if (drag.h === 'nw') {
                    crop.x = o.x + dx;
                    crop.y = o.y + dy;
                    crop.w = o.w - dx;
                    crop.h = o.h - dy;
                } else if (drag.h === 'ne') {
                    crop.y = o.y + dy;
                    crop.w = o.w + dx;
                    crop.h = o.h - dy;
                } else if (drag.h === 'sw') {
                    crop.x = o.x + dx;
                    crop.w = o.w - dx;
                    crop.h = o.h + dy;
                }
                clampCrop();
                drawAnn();
                return;
            }
            if (tmpArrow) {
                tmpArrow.x2 = p.x;
                tmpArrow.y2 = p.y;
                drawAnn();
                return;
            }
            if (drag && drag.k === 'draw') {
                drag.pts.push([p.x, p.y]);
                drawAnn();
            }
        }
        function onPtrUp(e) {
            if (drag && drag.k === 'crop') {
                drag = null;
                try {
                    layer.releasePointerCapture(e.pointerId);
                } catch (er) {}
                return;
            }
            if (tmpArrow) {
                var p = clientToCanvas(e);
                var d = Math.hypot(p.x - tmpArrow.x1, p.y - tmpArrow.y1);
                if (d > 6) {
                    ann.push({ t: 'arrow', x1: tmpArrow.x1, y1: tmpArrow.y1, x2: p.x, y2: p.y });
                }
                tmpArrow = null;
                drawAnn();
                try {
                    layer.releasePointerCapture(e.pointerId);
                } catch (er2) {}
                return;
            }
            if (drag && drag.k === 'draw') {
                if (drag.pts.length > 1) {
                    ann.push({ t: 'path', pts: drag.pts.slice() });
                }
                drag = null;
                drawAnn();
                try {
                    layer.releasePointerCapture(e.pointerId);
                } catch (er3) {}
            }
        }
        chatMediaEditorOpen = true;
        modal.style.display = 'flex';
        modal.style.flexDirection = 'column';
        modal.style.alignItems = 'stretch';
        document.body.style.overflow = 'hidden';
        img.onload = function() {
            var maxW = Math.min(window.innerWidth * 0.92, img.naturalWidth);
            var maxH = Math.min(window.innerHeight * 0.56, img.naturalHeight);
            var ratio = Math.min(maxW / img.naturalWidth, maxH / img.naturalHeight, 1);
            var dw = Math.max(1, Math.round(img.naturalWidth * ratio));
            var dh = Math.max(1, Math.round(img.naturalHeight * ratio));
            base.width = dw;
            base.height = dh;
            layer.width = dw;
            layer.height = dh;
            bctx.drawImage(img, 0, 0, dw, dh);
            var pad = Math.round(Math.min(dw, dh) * 0.06);
            crop = { x: pad, y: pad, w: dw - 2 * pad, h: dh - 2 * pad };
            ann = [];
            cropMode = false;
            tool = 'draw';
            btnApplyCrop.style.display = 'none';
            drawAnn();
            setToolActive();
            layer.addEventListener('pointerdown', onPtrDown);
            layer.addEventListener('pointermove', onPtrMove);
            layer.addEventListener('pointerup', onPtrUp);
            layer.addEventListener('pointercancel', onPtrUp);
        };
        img.onerror = function() {
            chatAlert(T.support_chat_unsupported_type || 'Invalid image');
            cleanup();
            onDone(file);
        };
        img.src = blobUrl;
        if (btnCrop) {
            btnCrop.onclick = function() {
                cropMode = !cropMode;
                if (cropMode) {
                    ann = [];
                    btnApplyCrop.style.display = 'inline-block';
                } else {
                    btnApplyCrop.style.display = 'none';
                }
                drawAnn();
                setToolActive();
            };
        }
        if (btnArrow) {
            btnArrow.onclick = function() {
                cropMode = false;
                btnApplyCrop.style.display = 'none';
                tool = 'arrow';
                drawAnn();
                setToolActive();
            };
        }
        if (btnDraw) {
            btnDraw.onclick = function() {
                cropMode = false;
                btnApplyCrop.style.display = 'none';
                tool = 'draw';
                drawAnn();
                setToolActive();
            };
        }
        if (btnClear) {
            btnClear.onclick = function() {
                if (!cropMode) {
                    ann = [];
                }
                tmpArrow = null;
                drawAnn();
            };
        }
        btnApplyCrop.onclick = function() {
            applyCrop();
        };
        if (btnCancel) {
            btnCancel.onclick = function() {
                cleanup();
                onDone(null);
            };
        }
        if (btnSend) {
            btnSend.onclick = function() {
                if (cropMode) {
                    chatAlert(T.support_chat_editor_finish_crop || 'Apply or cancel crop first');
                    return;
                }
                var out = document.createElement('canvas');
                out.width = base.width;
                out.height = base.height;
                var ox = out.getContext('2d');
                ox.drawImage(base, 0, 0);
                ox.drawImage(layer, 0, 0);
                out.toBlob(function(blob) {
                    cleanup();
                    if (!blob) {
                        onDone(file);
                        return;
                    }
                    var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    onDone(new File([blob], name, { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.92);
            };
        }
    }
    let lightboxZoomState = null;
    function openSupportChatLightbox(url, mediaType) {
        if (!supportChatLightbox || !supportChatLightboxInner || !url) {
            return;
        }
        supportChatLightboxInner.innerHTML = '';
        var mt = mediaType === 'video' ? 'video' : 'image';
        if (mt === 'video') {
            var v = document.createElement('video');
            v.setAttribute('controls', '');
            v.setAttribute('playsinline', '');
            v.src = url;
            supportChatLightboxInner.appendChild(v);
        } else {
            var wrapper = document.createElement('div');
            wrapper.style.cssText = 'position:relative;max-width:96vw;max-height:86vh;overflow:hidden;';
            var im = document.createElement('img');
            im.src = url;
            im.alt = '';
            im.style.cssText = 'max-width:100%;max-height:86vh;width:auto;height:auto;object-fit:contain;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.5);cursor:default;transform-origin:center center;user-select:none;-webkit-user-select:none;';
            wrapper.appendChild(im);

            var minimap = document.createElement('div');
            minimap.style.cssText = 'display:none;position:absolute;bottom:16px;right:16px;width:120px;height:120px;border:2px solid rgba(255,255,255,0.5);border-radius:8px;overflow:hidden;background:rgba(0,0,0,0.7);box-shadow:0 4px 12px rgba(0,0,0,0.6);';
            var minimapImg = document.createElement('img');
            minimapImg.src = url;
            minimapImg.style.cssText = 'width:100%;height:100%;object-fit:contain;opacity:0.6;';
            minimap.appendChild(minimapImg);

            var minimapViewport = document.createElement('div');
            minimapViewport.style.cssText = 'position:absolute;border:2px solid rgba(255,255,255,0.9);background:rgba(255,255,255,0.2);pointer-events:none;';
            minimap.appendChild(minimapViewport);
            wrapper.appendChild(minimap);

            lightboxZoomState = {
                scale: 1,
                translateX: 0,
                translateY: 0,
                isDragging: false,
                startX: 0,
                startY: 0,
                img: im,
                wrapper: wrapper,
                minimap: minimap,
                minimapViewport: minimapViewport
            };

            im.addEventListener('wheel', function(e) {
                e.preventDefault();
                var delta = e.deltaY > 0 ? -0.3 : 0.3;
                lightboxZoomState.scale = Math.max(1, Math.min(5, lightboxZoomState.scale + delta));
                if (lightboxZoomState.scale === 1) {
                    lightboxZoomState.translateX = 0;
                    lightboxZoomState.translateY = 0;
                    im.style.cursor = 'default';
                    minimap.style.display = 'none';
                } else {
                    im.style.cursor = 'grab';
                    minimap.style.display = 'block';
                }
                updateImageTransform();
                updateMinimapViewport();
            }, {passive: false});

            im.addEventListener('mousedown', function(e) {
                if (lightboxZoomState.scale > 1) {
                    e.preventDefault();
                    lightboxZoomState.isDragging = true;
                    lightboxZoomState.startX = e.clientX - lightboxZoomState.translateX;
                    lightboxZoomState.startY = e.clientY - lightboxZoomState.translateY;
                    im.style.cursor = 'grabbing';
                }
            });

            document.addEventListener('mousemove', function(e) {
                if (lightboxZoomState && lightboxZoomState.isDragging) {
                    lightboxZoomState.translateX = e.clientX - lightboxZoomState.startX;
                    lightboxZoomState.translateY = e.clientY - lightboxZoomState.startY;
                    updateImageTransform();
                    updateMinimapViewport();
                }
            });

            document.addEventListener('mouseup', function() {
                if (lightboxZoomState && lightboxZoomState.isDragging) {
                    lightboxZoomState.isDragging = false;
                    if (lightboxZoomState.img && lightboxZoomState.scale > 1) {
                        lightboxZoomState.img.style.cursor = 'grab';
                    }
                }
            });

            document.addEventListener('keydown', function(e) {
                if (!lightboxZoomState || lightboxZoomState.scale <= 1 || supportChatLightbox.style.display !== 'flex') {
                    return;
                }
                var step = 30;
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    lightboxZoomState.translateX += step;
                    updateImageTransform();
                    updateMinimapViewport();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    lightboxZoomState.translateX -= step;
                    updateImageTransform();
                    updateMinimapViewport();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    lightboxZoomState.translateY += step;
                    updateImageTransform();
                    updateMinimapViewport();
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    lightboxZoomState.translateY -= step;
                    updateImageTransform();
                    updateMinimapViewport();
                }
            });

            supportChatLightboxInner.appendChild(wrapper);
        }
        supportChatLightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function updateImageTransform() {
        if (lightboxZoomState && lightboxZoomState.img) {
            lightboxZoomState.img.style.transform = 'scale(' + lightboxZoomState.scale + ') translate(' + (lightboxZoomState.translateX / lightboxZoomState.scale) + 'px, ' + (lightboxZoomState.translateY / lightboxZoomState.scale) + 'px)';
        }
    }

    function updateMinimapViewport() {
        if (!lightboxZoomState || !lightboxZoomState.minimapViewport) return;
        var viewportWidth = 100 / lightboxZoomState.scale;
        var viewportHeight = 100 / lightboxZoomState.scale;
        var viewportX = 50 - (lightboxZoomState.translateX / lightboxZoomState.scale / lightboxZoomState.img.offsetWidth * 100) - viewportWidth / 2;
        var viewportY = 50 - (lightboxZoomState.translateY / lightboxZoomState.scale / lightboxZoomState.img.offsetHeight * 100) - viewportHeight / 2;
        lightboxZoomState.minimapViewport.style.width = viewportWidth + '%';
        lightboxZoomState.minimapViewport.style.height = viewportHeight + '%';
        lightboxZoomState.minimapViewport.style.left = viewportX + '%';
        lightboxZoomState.minimapViewport.style.top = viewportY + '%';
    }
    function closeSupportChatLightbox() {
        if (!supportChatLightbox) {
            return;
        }
        supportChatLightbox.style.display = 'none';
        if (supportChatLightboxInner) {
            supportChatLightboxInner.innerHTML = '';
        }
        lightboxZoomState = null;
        document.body.style.overflow = '';
    }
    function clearAdminEditDraftForCurrentChat() {
        var key = currentChatKey();
        if (isAdmin && currentChatUser && adminDraftEdits[key]) {
            delete adminDraftEdits[key];
        }
    }
    function cancelEditUI() {
        editingMessageId = null;
        if (chatInput) {
            chatInput.value = '';
        }
        if (chatFileInput) {
            chatFileInput.value = '';
        }
        var preview = document.getElementById('edit-media-preview');
        if (preview) {
            preview.remove();
        }
        if (chatCancelEditBtn) {
            chatCancelEditBtn.style.display = 'none';
        }
        if (chatSendBtn) {
            chatSendBtn.innerHTML = chatSendBtnHtml;
            chatSendBtn.style.width = '';
            chatSendBtn.style.minWidth = '';
            chatSendBtn.style.height = '';
            chatSendBtn.style.padding = '';
            chatSendBtn.style.borderRadius = '';
            chatSendBtn.style.fontSize = '';
            chatSendBtn.style.fontWeight = '';
            chatSendBtn.style.background = 'linear-gradient(135deg, var(--chat-color) 0%, var(--chat-color)dd 100%)';
            chatSendBtn.style.boxShadow = '0 4px 12px var(--chat-color-shadow)';
        }
        if (chatUploadBtn) {
            chatUploadBtn.style.display = 'flex';
        }
    }
    function wireChatLightbox() {
        if (!chatMessages || chatLightboxWired) {
            return;
        }
        chatLightboxWired = true;
        chatMessages.addEventListener('click', function(e) {
            var img = e.target.closest('img.chat-msg-media-open');
            if (img) {
                openSupportChatLightbox(img.getAttribute('data-full-src') || img.src, 'image');
                e.preventDefault();
            }
        });
    }
    function wireChatNavKeys() {
        if (!chatModal || chatNavKeysWired) {
            return;
        }
        chatNavKeysWired = true;
        chatModal.addEventListener('keydown', function(e) {
            if (chatModal.style.display === 'none') {
                return;
            }
            var lbOpen = supportChatLightbox && supportChatLightbox.style.display === 'flex';
            if (e.key === 'Escape') {
                if (chatMediaEditorOpen) {
                    var cbtn = document.getElementById('chat-media-editor-cancel');
                    if (cbtn) {
                        cbtn.click();
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                if (lbOpen) {
                    closeSupportChatLightbox();
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                if (editingMessageId) {
                    clearAdminEditDraftForCurrentChat();
                    cancelEditUI();
                    e.preventDefault();
                    e.stopPropagation();
                }
                return;
            }
            if (lbOpen) {
                return;
            }
            var ae = document.activeElement;
            var inComposer = ae === chatInput || (ae && ae.getAttribute && ae.getAttribute('id') === 'chat-user-search-input');
            if (inComposer) {
                return;
            }
            if (ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA' || ae.tagName === 'SELECT')) {
                return;
            }
            if (e.key === 'ArrowUp' && chatMessages) {
                chatMessages.scrollTop -= 88;
                e.preventDefault();
                return;
            }
            if (e.key === 'ArrowDown' && chatMessages) {
                chatMessages.scrollTop += 88;
                e.preventDefault();
                return;
            }
            if (!isAdmin || !lastAdminChatList.length || !currentChatUser) {
                return;
            }
            var emails = lastAdminChatList.map(function(c) {
                return c.user_email;
            });
            var idx = emails.indexOf(currentChatUser);
            if (idx < 0) {
                return;
            }
            if (e.key === 'ArrowRight' && idx < emails.length - 1) {
                window.selectChat(emails[idx + 1]);
                e.preventDefault();
            } else if (e.key === 'ArrowLeft' && idx > 0) {
                window.selectChat(emails[idx - 1]);
                e.preventDefault();
            }
        }, true);
    }
    wireChatNavKeys();
    if (supportChatLightbox) {
        supportChatLightbox.addEventListener('click', function(e) {
            if (e.target === supportChatLightbox) {
                closeSupportChatLightbox();
            }
        });
    }
    if (supportChatLightboxClose) {
        supportChatLightboxClose.addEventListener('click', function(e) {
            e.stopPropagation();
            closeSupportChatLightbox();
        });
    }
    if (chatCancelEditBtn) {
        chatCancelEditBtn.addEventListener('click', function() {
            if (editingMessageId) {
                clearAdminEditDraftForCurrentChat();
                cancelEditUI();
            }
        });
    }
    function scrollChatToBottom(aggressive) {
        if (!chatMessages) {
            return;
        }
        const run = function() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        };
        run();
        requestAnimationFrame(function() {
            run();
            requestAnimationFrame(run);
        });
        if (aggressive) {
            [40, 120, 300].forEach(function(t) {
                setTimeout(run, t);
            });
            setTimeout(function() {
                chatMessages.querySelectorAll('img,video').forEach(function(el) {
                    el.addEventListener('load', run, { once: true });
                    el.addEventListener('loadeddata', run, { once: true });
                });
            }, 0);
        }
    }
    if (chatBtn) {
        chatBtn.addEventListener('click', function() {
            if (isAdmin) {
                window.location.hash = '#support-chat';
            } else {
                chatModal.style.display = 'flex';
                if (adminChatList) {
                    adminChatList.style.display = 'flex';
                }
                adminMobilePhase = currentChatUser && isChatMobileMatch() ? 'chat' : 'list';
                syncAdminMobileLayout();
                loadChatList();
            }
        });
    }

    // Handle hash-based navigation for admin
    function handleHash() {
        const hash = window.location.hash;
        if (hash === '#support-chat' && isAdmin) {
            // Show fullscreen admin chat
            chatModal.style.display = 'flex';
            const container = document.getElementById('chat-modal-container');
            if (container) {
                container.style.width = '100%';
                container.style.height = '100%';
                container.style.maxWidth = 'none';
                container.style.maxHeight = 'none';
                container.style.minHeight = '0';
                container.style.borderRadius = '0';
                container.style.flexDirection = 'row';
            }
            if (adminChatList) {
                adminChatList.style.display = 'flex';
            }
            if (isAdminMobileMatch()) {
                adminMobilePhase = currentChatUser ? 'chat' : 'list';
            } else {
                adminMobilePhase = 'chat';
            }
            syncAdminMobileLayout();
            loadChatList();
        } else if (hash === '#news-workflow') {
            if (typeof window.openNewsPortal === 'function') window.openNewsPortal();
        } else if (hash !== '#support-chat') {
            chatModal.style.display = 'none';
            stopPolling();
        }
    }

    // Listen for hash changes
    window.addEventListener('hashchange', handleHash);
    handleHash(); // Check on load

    if (closeChatBtn) {
        closeChatBtn.addEventListener('click', function() {
            if (isAdmin) {
                if (currentChatUser && editingMessageId) {
adminDraftEdits[currentChatKey()] = { id: editingMessageId, text: chatInput ? chatInput.value : '' };
                    cancelEditUI();
                }
                window.location.hash = '';
            } else {
                chatModal.style.display = 'none';
                stopPolling();
            }
        });
    }

    if (chatModal) {
        chatModal.addEventListener('click', function(e) {
            if (e.target === chatModal) {
                if (isAdmin) {
                    if (currentChatUser && editingMessageId) {
adminDraftEdits[currentChatKey()] = { id: editingMessageId, text: chatInput ? chatInput.value : '' };
                        cancelEditUI();
                    }
                    window.location.hash = '';
                } else {
                    chatModal.style.display = 'none';
                    stopPolling();
                }
            }
        });
    }

    if (chatSendBtn) {
        chatSendBtn.addEventListener('click', () => editingMessageId ? saveEdit() : sendMessage());
    }

    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                editingMessageId ? saveEdit() : sendMessage();
            }
        });
    }

    if (chatUploadBtn) {
        chatUploadBtn.addEventListener('click', function() {
            chatFileInput.click();
        });
    }

    const chatPendingPlanRemove = document.getElementById('chat-pending-plan-remove');
    if (chatPendingPlanRemove) {
        chatPendingPlanRemove.addEventListener('click', chatClearPendingPlan);
    }

    // Voice recording button
    const chatVoiceBtn = document.getElementById('chat-voice-btn');
    if (chatVoiceBtn) {
        let voicePressTimer = null;
        let isRecording = false;

        // Desktop and mobile: press and hold to record
        chatVoiceBtn.addEventListener('mousedown', function(e) {
            e.preventDefault();
            startVoiceRecording();
        });

        chatVoiceBtn.addEventListener('mouseup', function(e) {
            e.preventDefault();
            if (isRecording) {
                stopVoiceRecording();
            }
        });

        chatVoiceBtn.addEventListener('mouseleave', function(e) {
            if (isRecording) {
                cancelVoiceRecording();
            }
        });

        // Touch events for mobile
        chatVoiceBtn.addEventListener('touchstart', function(e) {
            e.preventDefault();
            startVoiceRecording();
        });

        chatVoiceBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            if (isRecording) {
                stopVoiceRecording();
            }
        });

        chatVoiceBtn.addEventListener('touchcancel', function(e) {
            if (isRecording) {
                cancelVoiceRecording();
            }
        });

        function resetVoiceRecordingUI() {
            chatVoiceBtn.style.background = 'linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%)';
            chatVoiceBtn.style.transform = 'scale(1)';
            chatVoiceBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';
            if (chatInput) chatInput.placeholder = T.support_chat_placeholder || 'Type your message...';
        }
        function getMicErrorMessage(err) {
            const name = err && err.name ? err.name : '';
            if (!window.isSecureContext) return 'Voice recording requires HTTPS (or localhost).';
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') return 'Microphone permission is blocked for this site. Allow microphone in browser settings and reload the page.';
            if (name === 'NotFoundError' || name === 'DevicesNotFoundError') return 'No microphone detected. Connect a microphone and try again.';
            if (name === 'NotReadableError' || name === 'TrackStartError') return 'Microphone is busy in another app or tab. Close other apps using mic and try again.';
            if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') return 'Microphone constraints are not supported on this device.';
            return 'Could not start voice recording. Please try again.';
        }
        function startVoiceRecording() {
            if (isRecording) return;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (typeof showTempNotification === 'function') {
                    showTempNotification('Your browser does not support audio recording', 'error');
                } else {
                    chatAlert(T.browser_no_audio_recording);
                }
                return;
            }

            const preferredMime = typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : '';
            const recorderOpts = preferredMime ? { mimeType: preferredMime } : undefined;
            navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } })
                .then(function(stream) {
                    isRecording = true;
                    mediaRecorder = recorderOpts ? new MediaRecorder(stream, recorderOpts) : new MediaRecorder(stream);
                    audioChunks = [];

                    mediaRecorder.ondataavailable = function(e) {
                        audioChunks.push(e.data);
                    };

                    mediaRecorder.onstop = function() {
                        stream.getTracks().forEach(track => track.stop());
                        if (animationFrame) cancelAnimationFrame(animationFrame);
                        if (audioContext) audioContext.close();
                    };

                    mediaRecorder.start();
                    recordingStartTime = Date.now();

                    // Visual feedback
                    chatVoiceBtn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                    chatVoiceBtn.style.transform = 'scale(1.1)';
                    chatVoiceBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" stroke="#fff" fill="#ef4444"/></svg>';

                    // Show recording indicator
                    if (chatInput) {
                        chatInput.placeholder = T.recording_release_to_send;
                    }

                    // Visualize audio (optional - can add waveform to button)
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    analyser = audioContext.createAnalyser();
                    const source = audioContext.createMediaStreamSource(stream);
                    source.connect(analyser);
                    analyser.fftSize = 256;
                })
                .catch(function(err) {
                    console.error('Microphone access denied:', err);
                    const msg = getMicErrorMessage(err);
                    if (typeof showTempNotification === 'function') {
                        showTempNotification(msg, 'error');
                    } else {
                        chatAlert(msg);
                    }
                    isRecording = false;
                });
        }

        function stopVoiceRecording() {
            if (!isRecording || !mediaRecorder) return;

            mediaRecorder.stop();
            isRecording = false;

            resetVoiceRecordingUI();
            // Send voice message
            setTimeout(function() {
                if (audioChunks.length > 0) {
                    const recorderMime = mediaRecorder && mediaRecorder.mimeType ? mediaRecorder.mimeType : 'audio/webm';
                    const uploadMime = recorderMime.split(';')[0] || 'audio/webm';
                    const audioBlob = new Blob(audioChunks, { type: uploadMime });
                    const audioFile = new File([audioBlob], 'voice-' + Date.now() + '.weba', { type: uploadMime });
                    sendMessage(audioFile);
                    audioChunks = [];
                }
            }, 100);
        }

        function cancelVoiceRecording() {
            if (!isRecording || !mediaRecorder) return;

            mediaRecorder.stop();
            isRecording = false;
            audioChunks = [];

            resetVoiceRecordingUI();
        }
    }

    // Text formatting context menu
    const chatFormatMenu = document.getElementById('chat-format-menu');
    if (chatInput && chatFormatMenu) {
        let formatMenuTimeout;
        let touchStartTime;
        let touchStartPos;

        // Desktop: right-click
        chatInput.addEventListener('contextmenu', function(e) {
            if (this.selectionStart !== this.selectionEnd) {
                e.preventDefault();
                showFormatMenu(e.pageX, e.pageY);
            }
        });

        // Mobile: long press
        chatInput.addEventListener('touchstart', function(e) {
            touchStartTime = Date.now();
            touchStartPos = { x: e.touches[0].clientX, y: e.touches[0].clientY };
            formatMenuTimeout = setTimeout(function() {
                if (chatInput.selectionStart !== chatInput.selectionEnd) {
                    const touch = e.touches[0];
                    showFormatMenu(touch.pageX, touch.pageY);
                }
            }, 500);
        });

        chatInput.addEventListener('touchmove', function(e) {
            if (touchStartPos) {
                const dx = e.touches[0].clientX - touchStartPos.x;
                const dy = e.touches[0].clientY - touchStartPos.y;
                if (Math.abs(dx) > 10 || Math.abs(dy) > 10) {
                    clearTimeout(formatMenuTimeout);
                }
            }
        });

        chatInput.addEventListener('touchend', function() {
            clearTimeout(formatMenuTimeout);
        });

        // Format menu options
        document.querySelectorAll('.chat-format-option').forEach(function(opt) {
            opt.addEventListener('click', function(e) {
                e.stopPropagation();
                const format = this.dataset.format;
                applyTextFormat(format);
                chatFormatMenu.style.display = 'none';
            });
        });

        document.addEventListener('click', function() {
            if (chatFormatMenu) chatFormatMenu.style.display = 'none';
        });
    }

    function showFormatMenu(x, y) {
        if (!chatFormatMenu) return;
        chatFormatMenu.style.display = 'block';
        chatFormatMenu.style.left = x + 'px';
        chatFormatMenu.style.top = y + 'px';

        // Adjust if off-screen
        const rect = chatFormatMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            chatFormatMenu.style.left = (x - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            chatFormatMenu.style.top = (y - rect.height) + 'px';
        }
    }

    function applyTextFormat(format) {
        if (!chatInput) return;

        const start = chatInput.selectionStart;
        const end = chatInput.selectionEnd;
        const selectedText = chatInput.value.substring(start, end);

        if (!selectedText) return;

        let formattedText = '';
        switch (format) {
            case 'bold':
                formattedText = '**' + selectedText + '**';
                break;
            case 'italic':
                formattedText = '*' + selectedText + '*';
                break;
            case 'strikethrough':
                formattedText = '~~' + selectedText + '~~';
                break;
            case 'code':
                formattedText = '`' + selectedText + '`';
                break;
            case 'quote':
                formattedText = '> ' + selectedText;
                break;
            case 'monospace':
                formattedText = '```\n' + selectedText + '\n```';
                break;
        }

        const newValue = chatInput.value.substring(0, start) + formattedText + chatInput.value.substring(end);
        chatInput.value = newValue;
        chatInput.focus();
        chatInput.setSelectionRange(start, start + formattedText.length);
    }

    // Monaco editor modal handlers
    const textEditorModal = document.getElementById('chat-text-editor-modal');
    const textEditorClose = document.getElementById('chat-text-editor-close');

    if (textEditorClose) {
        textEditorClose.addEventListener('click', function() {
            if (textEditorModal) textEditorModal.style.display = 'none';
            if (monacoEditor) {
                monacoEditor.dispose();
                monacoEditor = null;
            }
        });
    }

    // Voice recording modal - removed, now using press-and-hold on button
    let mediaRecorder = null;
    let audioChunks = [];
    let recordingStartTime = 0;
    let audioContext = null;
    let analyser = null;
    let animationFrame = null;

    if (chatFileInput) {
        chatFileInput.addEventListener('change', function() {
            if (this.files.length === 0) {
                return;
            }
            var f = this.files[0];
            function putFile(file) {
                var dt = new DataTransfer();
                dt.items.add(file);
                chatFileInput.files = dt.files;
            }
            if (editingMessageId) {
                if (f.type.indexOf('image/') === 0) {
                    openChatMediaEditor(f, function(out) {
                        chatFileInput.value = '';
                        if (!out) {
                            return;
                        }
                        putFile(out);
                    });
                } else {
                    putFile(f);
                }
                return;
            }
            if (f.type.indexOf('image/') === 0) {
                openChatMediaEditor(f, function(out) {
                    chatFileInput.value = '';
                    if (!out) {
                        return;
                    }
                    sendMessage(out);
                });
            } else {
                sendMessage(f);
                chatFileInput.value = '';
            }
        });
    }
    if (chatAdminMobileBack) {
        chatAdminMobileBack.addEventListener('click', function() {
            adminMobilePhase = 'list';
            syncAdminMobileLayout();
        });
    }
    if (adminChatListCloseBtn) {
        adminChatListCloseBtn.addEventListener('click', function() {
            if (isAdmin) {
                if (currentChatUser && editingMessageId) {
adminDraftEdits[currentChatKey()] = { id: editingMessageId, text: chatInput ? chatInput.value : '' };
                    cancelEditUI();
                }
                window.location.hash = '';
            }
        });
    }
    window.addEventListener('resize', function() {
        syncAdminMobileLayout();
    });

    // Context menu
    document.addEventListener('click', () => {
        if (contextMenu) contextMenu.style.display = 'none';
    });

    if (contextEdit) {
        contextEdit.addEventListener('click', function(e) {
            e.stopPropagation();
            startEdit(contextMessageId);
            contextMenu.style.display = 'none';
        });
    }

    if (contextDelete) {
        contextDelete.addEventListener('click', function(e) {
            e.stopPropagation();
            deleteMessage(contextMessageId);
            contextMenu.style.display = 'none';
        });
    }

    function loadChatList() {
        fetch('?api=support_chat&action=get_chat_list')
            .then(r => {
                if (!r.ok || r.headers.get('content-length') === '0') {
                    throw new Error('Server returned ' + r.status + ' with empty body');
                }
                return r.json();
            })
            .then(data => {
                if (data.ok) {
                    var arr = isAdmin ? (data.chats || []) : [].concat((data.sections && data.sections.support) || [], (data.sections && data.sections.people) || []);
                    if (arr.length) {
                        arr.sort(function(a, b) {
                            return (b.last_message_time || 0) - (a.last_message_time || 0);
                        });
                    }
                    renderChatList(isAdmin ? arr : data.sections);
                    if (!isAdmin && !currentChatUser && !isChatMobileMatch() && ((data.sections && data.sections.support && data.sections.support[0]) || arr[0])) {
                        window.selectChat((data.sections && data.sections.support && data.sections.support[0]) || arr[0]);
                    }
                    if (isAdmin && arr.length) {
                        var keep = currentChatUser && arr.some(function(c) {
                            return c.user_email === currentChatUser;
                        });
                        if (!keep) {
                            window.selectChat(arr[0]);
                        } else {
                            loadMessages(false);
                            startPolling();
                        }
                    }
                } else {
                    console.error('Failed to load chat list:', data.error);
                }
            })
            .catch(err => console.error('Error loading chat list:', err));
    }

    function renderChatList(chats) {
        if (!chatListContainer) {
            return;
        }
        var sections = Array.isArray(chats) ? { support: chats.filter(function(c) { return (c.chat_type || 'support') === 'support'; }), people: chats.filter(function(c) { return c.chat_type === 'peer'; }) } : (chats || { support: [], people: [] });
        var allChats = [].concat(sections.support || [], sections.people || []);
        lastAdminChatList = allChats;
        if (!allChats.length) {
            chatListContainer.innerHTML = '<p style="text-align:center;color:var(--text-color);opacity:0.6;">' + (T.support_chat_no_chats || 'No active chats') + '</p>';
            return;
        }
        chatListContainer.innerHTML = '';
        function addSection(label, rows) {
            rows = rows || [];
            var sep = document.createElement('div');
            sep.style.cssText = 'display:flex;align-items:center;gap:10px;margin:12px 0;color:var(--text-color);opacity:.62;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;';
            sep.innerHTML = '<span style="height:1px;background:var(--border-color);flex:1;"></span><span>' + escChat(label) + '</span><span style="height:1px;background:var(--border-color);flex:1;"></span>';
            chatListContainer.appendChild(sep);
            rows.forEach(addRow);
        }
        function addRow(chat) {
            var row = document.createElement('div');
            row.className = 'chat-list-row';
            var rowPeer = chat.chat_type === 'peer' ? (chat.peer_email || '') : '';
            if (currentChatType === (chat.chat_type || 'support') && (currentChatType === 'peer' ? currentPeerEmail === rowPeer : currentChatUser === chat.user_email)) {
                row.classList.add('chat-list-row-active');
            }
            row.style.cssText = 'padding:18px;background:linear-gradient(135deg,rgba(99,102,241,.05) 0%,rgba(168,85,247,.05) 100%);border:1px solid var(--border-color);border-radius:16px;cursor:pointer;margin-bottom:12px;transition:transform .2s,box-shadow .2s,border-color .2s,background .2s;';
            row.addEventListener('mouseenter', function() {
                row.style.transform = 'translateY(-2px)';
                row.style.boxShadow = '0 4px 12px rgba(99,102,241,.2)';
            });
            row.addEventListener('mouseleave', function() {
                row.style.transform = 'translateY(0)';
                row.style.boxShadow = 'none';
            });
            var avatarWrap = document.createElement('div');
            avatarWrap.style.cssText = 'display:flex;align-items:center;gap:10px;';
            if (chat.user_photo) {
                var avatarImg = document.createElement('img');
                avatarImg.src = chat.user_photo;
                avatarImg.style.cssText = 'width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;';
                avatarImg.onerror = function() { this.src = 'default_avatar.png'; };
                avatarWrap.appendChild(avatarImg);
            }
            var title = document.createElement('div');
            title.style.cssText = 'font-weight:600;color:var(--text-color);margin-bottom:6px;font-size:15px;line-height:1.35;';
            var nm = chat.chat_type === 'support' && !isAdmin ? 'Support' : ((chat.user_name && String(chat.user_name).trim()) ? String(chat.user_name).trim() : '');
            var emailText = chat.chat_type === 'peer' ? (chat.peer_email || chat.recipient_email || chat.user_email) : (isAdmin ? chat.user_email : '');
            if (nm) {
                title.appendChild(document.createTextNode(nm + ' '));
                if (emailText) {
                    var emSpan = document.createElement('span');
                    emSpan.style.cssText = 'font-weight:500;opacity:.78;font-size:13px;word-break:break-all;';
                    emSpan.textContent = emailText;
                    title.appendChild(emSpan);
                }
            } else {
                title.textContent = emailText;
            }
            if (chat.user_role >= 2 && (isAdmin || chat.chat_type !== 'support')) {
                var adm = document.createElement('span');
                adm.style.cssText = 'font-size:11px;opacity:.6;';
                adm.textContent = ' (Admin)';
                title.appendChild(adm);
            }
            avatarWrap.appendChild(title);
            var meta = document.createElement('div');
            meta.style.cssText = 'font-size:13px;color:var(--text-color);opacity:.6;';
            meta.textContent = (chat.message_count || 0) + ' messages' + (chat.last_message_time ? ' • ' + new Date(chat.last_message_time * 1000).toLocaleString() : '');
            row.appendChild(avatarWrap);
            row.appendChild(meta);
            if (!isAdmin) {
                var actions = document.createElement('div');
                actions.style.cssText = 'display:flex;gap:8px;margin-top:10px;';
                var del = document.createElement('button');
                del.type = 'button';
                del.textContent = 'Delete';
                del.style.cssText = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(239,68,68,.28);background:rgba(239,68,68,.08);color:#ef4444;cursor:pointer;font-size:12px;';
                del.addEventListener('click', function(e) {
                    e.stopPropagation();
                    window.selectChat(chat);
                    deleteChat();
                });
                actions.appendChild(del);
                if (chat.chat_type === 'peer') {
                    var blk = document.createElement('button');
                    blk.type = 'button';
                    blk.textContent = chat.is_blocked_by_me ? 'Unblock' : 'Block';
                    blk.style.cssText = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(239,68,68,.22);background:transparent;color:#ef4444;cursor:pointer;font-size:12px;';
                    blk.addEventListener('click', function(e) {
                        e.stopPropagation();
                        window.selectChat(chat);
                        if (blockChatBtn) blockChatBtn.click();
                    });
                    actions.appendChild(blk);
                }
                row.appendChild(actions);
            }
            row.addEventListener('click', function() {
                window.selectChat(chat);
            });
            chatListContainer.appendChild(row);
        }
        addSection('Priority support', sections.support || []);
        addSection('People', sections.people || []);
    }

    function openUserSearchModal() {
        if (!userSearchModal) {
            return;
        }
        userSearchModal.style.display = 'flex';
        if (userSearchInput) {
            userSearchInput.value = '';
            userSearchInput.type = isAdmin ? 'text' : 'email';
            userSearchInput.placeholder = isAdmin ? 'Name or email' : 'Full email address';
            userSearchInput.focus();
        }
        if (userSearchResults) {
            userSearchResults.innerHTML = '<p style="text-align:center;padding:20px;opacity:0.55;color:var(--text-color);font-size:14px;">' + (isAdmin ? 'Type a name or email' : 'Enter a full email address') + '</p>';
        }
        runUserSearch('');
    }
    function closeUserSearchModal() {
        if (userSearchModal) {
            userSearchModal.style.display = 'none';
        }
    }
    function runUserSearch(q) {
        if (!userSearchResults) {
            return;
        }
        fetch('?api=support_chat&action=search_users&q=' + encodeURIComponent(q))
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (!data.ok) {
                    userSearchResults.innerHTML = '<p style="padding:16px;color:#ef4444;">' + escChat(data.error || 'Error') + '</p>';
                    return;
                }
                if (!data.users || data.users.length === 0) {
                    userSearchResults.innerHTML = '<p style="text-align:center;padding:20px;opacity:0.55;color:var(--text-color);">' + escChat(T.support_chat_no_search_results || 'No users found') + '</p>';
                    return;
                }
                userSearchResults.innerHTML = '';
                data.users.forEach(function(u) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.style.cssText = 'display:flex;align-items:center;width:100%;text-align:left;padding:12px 14px;margin:0 0 6px 0;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-color);color:var(--text-color);cursor:pointer;font-size:14px;gap:10px;';
                    if (u.photo) {
                        var img = document.createElement('img');
                        img.src = u.photo;
                        img.style.cssText = 'width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;';
                        img.onerror = function() { this.src = 'default_avatar.png'; };
                        btn.appendChild(img);
                    }
                    var info = document.createElement('div');
                    if (u.name && String(u.name).trim()) {
                        info.innerHTML = escChat(u.name) + '<br><span style="font-size:12px;opacity:0.65;">' + escChat(u.email) + '</span>';
                    } else {
                        info.textContent = u.email;
                    }
                    btn.appendChild(info);
                    var em = u.email;
                    btn.addEventListener('click', function() {
                        closeUserSearchModal();
                        if (isAdmin) {
                            if (pendingNewChatType === 'peer') {
                                window.selectChat({ chat_type: 'peer', peer_email: em, user_email: currentUserEmail, recipient_email: em, user_name: u.name || '', user_photo: u.photo || '', is_blocked_by_me: false });
                            } else {
                                window.selectChat({ chat_type: 'support', peer_email: '', user_email: em, recipient_email: '', user_name: u.name || '', user_photo: u.photo || '', is_blocked_by_me: false });
                            }
                        } else {
                            window.selectChat({ chat_type: 'peer', peer_email: em, user_email: currentUserEmail, recipient_email: em, user_name: u.name || '', user_photo: u.photo || '', is_blocked_by_me: false });
                        }
                    });
                    userSearchResults.appendChild(btn);
                });
            })
            .catch(function() {
                userSearchResults.innerHTML = '<p style="padding:16px;color:#ef4444;">' + escChat(T.network_error || 'Network error') + '</p>';
            });
    }
    if (newChatBtn) {
        newChatBtn.addEventListener('click', function() {
            if (isAdmin && chatTypeModal) {
                chatTypeModal.style.display = 'flex';
            } else {
                pendingNewChatType = 'peer';
                openUserSearchModal();
            }
        });
    }
    function chooseNewChatType(type) {
        pendingNewChatType = type === 'support' ? 'support' : 'peer';
        if (chatTypeModal) chatTypeModal.style.display = 'none';
        openUserSearchModal();
    }
    if (chatTypeSupport) {
        chatTypeSupport.addEventListener('click', function() {
            chooseNewChatType('support');
        });
    }
    if (chatTypePeer) {
        chatTypePeer.addEventListener('click', function() {
            chooseNewChatType('peer');
        });
    }
    if (chatTypeCancel) {
        chatTypeCancel.addEventListener('click', function() {
            if (chatTypeModal) chatTypeModal.style.display = 'none';
        });
    }
    if (chatTypeModal) {
        chatTypeModal.addEventListener('click', function(e) {
            if (e.target === chatTypeModal) {
                chatTypeModal.style.display = 'none';
            }
        });
    }
    if (userSearchCancel) {
        userSearchCancel.addEventListener('click', closeUserSearchModal);
    }
    if (userSearchModal) {
        userSearchModal.addEventListener('click', function(e) {
            if (e.target === userSearchModal) {
                closeUserSearchModal();
            }
        });
    }
    if (userSearchInput) {
        userSearchInput.addEventListener('input', function() {
            var v = userSearchInput.value;
            if (userSearchTimer) {
                clearTimeout(userSearchTimer);
            }
            userSearchTimer = setTimeout(function() {
                runUserSearch(v.trim());
            }, 280);
        });
    }

    window.selectChat = function(chat) {
        if (typeof chat === 'string') {
            chat = { chat_type: 'support', user_email: chat };
        }
        var nextType = chat.chat_type || 'support';
        var nextPeer = nextType === 'peer' ? (chat.peer_email || chat.recipient_email || '') : '';
        if (nextType === 'peer') {
            var peerNorm = String(nextPeer || '').toLowerCase().trim();
            var selfNorm = String(currentUserEmail || '').toLowerCase().trim();
            // A peer chat needs a peer; self or empty would be rejected as
            // invalid_peer by the API.
            if (!peerNorm || peerNorm === selfNorm) {
                nextType = 'support';
                nextPeer = '';
            }
        }
        var nextUser = nextType === 'support' ? (chat.user_email || currentUserEmail) : (chat.user_email || currentUserEmail);
        if (isAdmin && currentChatUser && currentChatUser !== nextUser && editingMessageId) {
            adminDraftEdits[currentChatKey()] = { id: editingMessageId, text: chatInput ? chatInput.value : '' };
            cancelEditUI();
        }
        currentChatType = nextType;
        currentPeerEmail = nextPeer;
        currentChatUser = nextUser;
        currentPeerBlocked = !!chat.is_blocked_by_me;
        currentChatTitle = nextType === 'peer' ? ((chat.user_name && String(chat.user_name).trim()) ? String(chat.user_name).trim() : nextPeer) : (isAdmin ? 'Chat: ' + nextUser : 'Support');
        if (chatMessagesArea) {
            chatMessagesArea.style.display = 'flex';
        }
        if (chatModalTitle) {
            chatModalTitle.textContent = currentChatTitle;
        }
        if (chatHeaderAvatar) {
            chatHeaderAvatar.src = chat.user_photo || 'default_avatar.png';
            chatHeaderAvatar.style.display = 'block';
        }
        setHeaderActions();
        lastMessagesHash = '';
        messagesData = [];
        chatOldestId = null;
        chatMaxId = 0;
        chatHasMoreOlder = false;
        if (chatMessages) {
            chatMessages.innerHTML = '<p style="text-align:center;color:var(--text-color);opacity:0.7;padding:40px;">' + (T.support_chat_loading || 'Loading...') + '</p>';
        }
        if (lastAdminChatList.length) {
            renderChatList(lastAdminChatList);
        }
        loadMessages(false);
        startPolling();
        adminMobilePhase = 'chat';
        syncAdminMobileLayout();
    };

    function wireChatScroll() {
        if (!chatMessages || chatScrollWired) {
            return;
        }
        chatScrollWired = true;
        chatMessages.addEventListener('scroll', function() {
            if (!chatHasMoreOlder || chatLoadingOlder || !currentChatUser) {
                return;
            }
            if (chatMessages.scrollTop < 120 || shouldPrefetchOlderByPosition()) {
                loadOlderChunk();
            }
        });
    }
    function shouldPrefetchOlderByPosition() {
        if (!chatMessages) {
            return false;
        }
        var bubbles = chatMessages.querySelectorAll('.chat-msg-bubble');
        if (!bubbles.length) {
            return chatMessages.scrollTop < 120;
        }
        var wrapRect = chatMessages.getBoundingClientRect();
        var firstVisible = bubbles.length;
        for (var i = 0; i < bubbles.length; i++) {
            var b = bubbles[i];
            if (b.getBoundingClientRect().bottom >= wrapRect.top + 4) {
                firstVisible = i + 1;
                break;
            }
        }
        return firstVisible <= 10;
    }

    function loadOlderChunk() {
        if (!currentChatUser || !chatOldestId || chatLoadingOlder || !chatHasMoreOlder || !chatMessages) {
            return;
        }
        chatLoadingOlder = true;
        var prevH = chatMessages.scrollHeight;
        var prevT = chatMessages.scrollTop;
        var url = '?api=support_chat&action=get_messages' + currentChatQuery() + '&before_id=' + chatOldestId + '&limit=30';
        fetch(url)
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                chatLoadingOlder = false;
                if (!data.ok || data.mode !== 'older') {
                    return;
                }
                var byId = {};
                messagesData.forEach(function(m) {
                    byId[m.id] = m;
                });
                (data.messages || []).forEach(function(m) {
                    byId[m.id] = m;
                });
                messagesData = Object.keys(byId).map(function(k) {
                    return byId[k];
                }).sort(function(a, b) {
                    return a.id - b.id;
                });
                chatHasMoreOlder = !!data.has_more_older;
                chatOldestId = messagesData.length ? messagesData[0].id : null;
                lastMessagesHash = JSON.stringify(messagesData);
                renderMessages(messagesData, { preserveScroll: true, prevHeight: prevH, prevTop: prevT });
            })
            .catch(function() {
                chatLoadingOlder = false;
            });
    }

    function currentChatIsPeerWithoutEmail() {
        return currentChatType === 'peer' && !currentPeerEmail;
    }
    function loadMessages(silent, opts) {
        if (!currentChatUser) {
            return;
        }
        if (currentChatIsPeerWithoutEmail()) {
            return;
        }
        if (currentChatType === 'peer' && currentPeerBlocked) {
            return;
        }
        silent = !!silent;
        opts = opts || {};
        var forceScrollBottom = !!opts.forceScrollBottom;
        var full = !!opts.full;
        var base = '?api=support_chat&action=get_messages' + currentChatQuery();
        var url = base;
        if (full) {
            url += '&full=1';
        } else if (silent && chatMaxId > 0) {
            url += '&since_id=' + chatMaxId;
        } else if (!silent) {
            url += '&limit=30';
        } else {
            return;
        }
        fetch(url)
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (!data.ok) {
                    if (data.error === 'peer_blocked') {
                        currentPeerBlocked = true;
                        setHeaderActions();
                        stopPolling();
                        return;
                    }
                    if (data.error === 'invalid_peer') {
                        stopPolling();
                        return;
                    }
                    console.error('Failed to load messages:', data.error);
                    return;
                }
                wireChatScroll();
                wireChatLightbox();
                if (full || data.mode === 'full') {
                    messagesData = data.messages || [];
                    chatOldestId = messagesData.length ? messagesData[0].id : null;
                    chatMaxId = 0;
                    messagesData.forEach(function(m) {
                        chatMaxId = Math.max(chatMaxId, m.id);
                    });
                    chatHasMoreOlder = false;
                    lastMessagesHash = JSON.stringify(messagesData);
                    renderMessages(messagesData, { forceScrollBottom: forceScrollBottom });
                    maybeRestoreAdminEditDraft();
                    if (!silent) {
                        markAsRead();
                    }
                    return;
                }
                if (silent && data.mode === 'delta') {
                    if (!data.messages || !data.messages.length) {
                        if (forceScrollBottom) {
                            scrollChatToBottom(true);
                        }
                        return;
                    }
                    var wb = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 80;
                    var seen = {};
                    messagesData.forEach(function(m) {
                        seen[m.id] = true;
                    });
                    data.messages.forEach(function(m) {
                        if (!seen[m.id]) {
                            messagesData.push(m);
                            seen[m.id] = true;
                        }
                    });
                    messagesData.sort(function(a, b) {
                        return a.id - b.id;
                    });
                    chatMaxId = 0;
                    messagesData.forEach(function(m) {
                        chatMaxId = Math.max(chatMaxId, m.id);
                    });
                    lastMessagesHash = JSON.stringify(messagesData);
                    renderMessages(messagesData, { forceScrollBottom: wb || forceScrollBottom });
                    if (wb || forceScrollBottom) {
                        markAsRead();
                    }
                    return;
                }
                if (!silent && data.mode === 'initial') {
                    messagesData = data.messages || [];
                    chatOldestId = data.min_id != null ? data.min_id : null;
                    chatMaxId = data.max_id != null ? data.max_id : 0;
                    chatHasMoreOlder = !!data.has_more_older;
                    if (!messagesData.length) {
                        chatOldestId = null;
                        chatMaxId = 0;
                        chatHasMoreOlder = false;
                    }
                    lastMessagesHash = JSON.stringify(messagesData);
                    renderMessages(messagesData, { forceScrollBottom: forceScrollBottom });
                    maybeRestoreAdminEditDraft();
                    markAsRead();
                }
            })
            .catch(function(err) {
                console.error('Error loading messages:', err);
            });
    }

    function maybeRestoreAdminEditDraft() {
        if (!isAdmin || !currentChatUser) {
            return;
        }
var d = adminDraftEdits[currentChatKey()];
        if (!d || !d.id) {
            return;
        }
        var exists = messagesData.some(function(m) {
            return m.id === d.id && !m.is_deleted;
        });
        if (!exists) {
delete adminDraftEdits[currentChatKey()];
            return;
        }
delete adminDraftEdits[currentChatKey()];
        startEdit(d.id);
        if (chatInput) {
            chatInput.value = d.text != null ? d.text : '';
        }
    }

    function parseCloudAttachmentMessage(message) {
        if (!message || message.charAt(0) !== '{') return null;
        try {
            var data = JSON.parse(message);
            if (!data || data.type !== 'cloud_attachment' || !data.share_id) return null;
            return data;
        } catch (_) {
            return null;
        }
    }

    function formatChatFileSize(bytes) {
        bytes = Number(bytes || 0);
        if (!bytes) return '';
        var units = ['B', 'KB', 'MB', 'GB'];
        var index = 0;
        while (bytes >= 1024 && index < units.length - 1) {
            bytes /= 1024;
            index++;
        }
        return (index === 0 ? bytes : bytes.toFixed(bytes >= 10 ? 0 : 1)) + ' ' + units[index];
    }

    function getChatCloudFileIconMarkup(name) {
        var ext = String(name || '').split('.').pop().toLowerCase();
        if (!ext || ext === String(name || '').toLowerCase()) ext = 'txt';
        var src = '/apps/cloud/icons/' + encodeURIComponent(ext) + '.svg';
        return '<span style="width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;"><img src="' + escAttrChat(src) + '" alt="" loading="lazy" style="width:30px;height:30px;object-fit:contain;"></span>';
    }

    function renderCloudAttachmentCard(att, isOwn) {
        var name = String(att.name || 'File');
        var previewType = String(att.preview_type || 'file');
        var preview = String(att.preview || '');
        var size = formatChatFileSize(att.size);
        var previewHtml = '';
        if (previewType === 'image' && preview.indexOf('data:image/') === 0) {
            previewHtml = '<img src="' + escAttrChat(preview) + '" alt="" style="width:100%;max-height:140px;object-fit:cover;border-radius:8px;margin-top:8px;background:rgba(0,0,0,.08);">';
        } else if (previewType === 'text' && preview) {
            previewHtml = '<pre style="margin:8px 0 0;max-height:96px;overflow:hidden;white-space:pre-wrap;word-break:break-word;font:12px/1.35 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;background:rgba(0,0,0,.10);border-radius:8px;padding:8px;color:inherit;">' + escChat(preview) + '</pre>';
        }
        return '<button type="button" class="chat-cloud-attachment" data-share-id="' + escAttrChat(att.share_id) + '" onclick="openChatCloudAttachment(this.dataset.shareId)" style="width:min(320px,100%);margin-left:' + (isOwn ? 'auto' : '0') + ';border:1px solid rgba(255,255,255,.24);background:rgba(255,255,255,.12);color:inherit;border-radius:10px;padding:10px;text-align:left;cursor:pointer;display:block;">' +
            '<span style="display:flex;align-items:center;gap:10px;min-width:0;">' +
            getChatCloudFileIconMarkup(name) +
            '<span style="display:flex;flex-direction:column;gap:3px;min-width:0;flex:1;">' +
            '<span style="font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escChat(name) + '</span>' +
            '<span style="font-size:11px;opacity:.75;">Cloud attachment' + (size ? ' · ' + escChat(size) : '') + '</span>' +
            '</span>' +
            '</span>' +
            previewHtml +
            '</button>';
    }

    window.__fpShares = window.__fpShares || {};
    function parseFloorPlanMessage(message) {
        if (!message || message.charAt(0) !== '{') return null;
        try {
            var data = JSON.parse(message);
            if (!data || data.type !== 'floor_plan_share' || !data.plan) return null;
            return data;
        } catch (_) {
            return null;
        }
    }

    function renderFloorPlanShareCard(data, isOwn, msgId) {
        var count = data.plan.elements ? data.plan.elements.length : (Array.isArray(data.plan) ? data.plan.length : 0);
        var note = data.note ? '<div style="font-size:13px;line-height:1.5;opacity:.92;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.15);word-wrap:break-word;white-space:pre-wrap;">' + formatChatMessage(String(data.note)) + '</div>' : '';
        window.__fpShares[msgId] = data.plan;
        return '<div class="chat-floor-plan-card" style="width:min(300px,100%);margin-left:' + (isOwn ? 'auto' : '0') + ';border:1px solid rgba(255,255,255,.24);background:rgba(255,255,255,.12);color:inherit;border-radius:10px;padding:10px;text-align:left;display:block;">' +
            note +
            '<span style="display:flex;align-items:center;gap:10px;min-width:0;">' +
            '<span style="width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;border-radius:8px;background:rgba(255,255,255,.14);">' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15v-3"/><path d="M11 15V9"/><path d="M15 15v-5"/><path d="M19 15v-2"/></svg>' +
            '</span>' +
            '<span style="display:flex;flex-direction:column;gap:3px;min-width:0;flex:1;">' +
            '<span style="font-size:13px;font-weight:700;">' + escChat(T.support_chat_floor_plan || 'Floor plan') + '</span>' +
            '<span style="font-size:11px;opacity:.75;">' + escChat(T.support_chat_floor_plan_share || 'Shared from Smart Home') + ' · ' + count + '</span>' +
            '</span>' +
            '</span>' +
            '<button type="button" onclick="chatImportFloorPlanCached(' + msgId + ')" style="width:100%;margin-top:10px;padding:9px 0;border-radius:9px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);font-size:13px;font-weight:600;cursor:pointer;">' + escChat(T.support_chat_import_plan || 'Import') + '</button>' +
            '</div>';
    }

    window.chatImportFloorPlanCached = function(msgId) {
        var p = (window.__fpShares || {})[msgId];
        if (p) chatImportFloorPlan(p);
    };

    function chatCloseChatModal() {
        if (isAdmin) {
            window.location.hash = '';
        } else if (chatModal) {
            chatModal.style.display = 'none';
            stopPolling();
        }
    }

    function chatImportFloorPlan(plan) {
        try {
            localStorage.setItem('fp_import_pending', JSON.stringify({ ts: Date.now(), plan: plan || null }));
        } catch (_) {}
        var frame = document.getElementById('app-iframe');
        if (frame && /smart_home/.test(frame.src || '') && frame.contentWindow) {
            try {
                frame.contentWindow.postMessage({ type: 'fp_import_pending', plan: plan || null }, '*');
            } catch (_) {}
            if (typeof showTempNotification === 'function') {
                showTempNotification(T.support_chat_import_sent || 'Smart Home: floor plan ready for import', 'success');
            }
            chatCloseChatModal();
        } else {
            window.location.href = '/main?app=smart_home&fp_import=1';
        }
    }

    window.openChatCloudAttachment = function(shareId) {
        if (!shareId) return;
        var formData = new FormData();
        formData.append('action', 'import_internal_share');
        formData.append('share_id', shareId);
        fetch('/apps/cloud/api.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.ok) throw new Error(data.error || 'open failed');
                sessionStorage.setItem('cloudOpenAfterLoad', JSON.stringify({ path: data.path, root: data.root || 'temp' }));
                window.location.href = '/main?app=cloud';
            })
            .catch(function(err) {
                chatAlert('Error: ' + err.message);
            });
    };

    function renderMessages(messages, opts) {
        if (!chatMessages) {
            return;
        }
        opts = opts || {};
        var forceScrollBottom = !!opts.forceScrollBottom;
        var preserveScroll = !!opts.preserveScroll;
        var prevHeight = opts.prevHeight;
        var prevTop = opts.prevTop;

        if (messages.length === 0) {
            chatMessages.innerHTML = '<p style="text-align:center;color:var(--text-color);opacity:0.6;padding:40px;">' + (T.support_chat_no_messages || 'No messages yet') + '</p>';
            if (forceScrollBottom) {
                scrollChatToBottom(false);
            }
            return;
        }

        const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 80;

        chatMessages.innerHTML = messages.map(msg => {
            const isOwn = msg.sender_email === currentUserEmail;
            const date = new Date(msg.created_at * 1000);
            const timeStr = date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

            let content = '';
            if (msg.is_deleted) {
                content = '<em style="opacity:0.5;font-size:13px;">🗑️ ' + (T.support_chat_deleted || 'Message deleted') + '</em>';
            } else {
                if (msg.media_path) {
                    const u = escAttrChat(msg.media_path);
                    if (msg.media_type === 'image') {
                        content = '<div class="chat-msg-media-wrap"><img class="chat-msg-media-open" data-full-src="' + u + '" src="' + u + '" alt=""></div>';
                    } else if (msg.media_type === 'video') {
                        content = '<div class="chat-msg-media-wrap"><video controls playsinline><source src="' + u + '"></video></div>';
                    } else if (msg.media_type === 'audio') {
                        const audioId = 'audio-' + msg.id;
                        content = '<div class="chat-msg-audio-wrap" data-audio-src="' + u + '" data-audio-id="' + audioId + '" data-own="' + (isOwn ? '1' : '0') + '">' +
                            '<button class="chat-msg-audio-play" onclick="toggleAudioPlay(\'' + audioId + '\', this)">' +
                            '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>' +
                            '</button>' +
                            '<canvas class="chat-msg-audio-waveform" width="200" height="36" data-audio-id="' + audioId + '"></canvas>' +
                            '<span class="chat-msg-audio-time">0:00</span>' +
                            '<audio id="' + audioId + '" src="' + u + '" preload="metadata"></audio>' +
                            '</div>';
                    } else if (msg.media_type === 'text') {
                        const fileName = u.split('/').pop();
                        const ext = fileName.split('.').pop().toLowerCase();
                        content = '<div class="chat-msg-text-file" onclick="openTextFileEditor(\'' + u + '\', \'' + escAttrChat(fileName) + '\')">' +
                            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>' +
                            '<span style="font-size:13px;font-weight:600;">' + escChat(fileName) + '</span>' +
                            '<span style="font-size:11px;opacity:0.7;text-transform:uppercase;">' + ext + '</span>' +
                            '</div>';
                    }
                }
                const cloudAttachment = parseCloudAttachmentMessage(msg.message);
                if (cloudAttachment) {
                    content += renderCloudAttachmentCard(cloudAttachment, isOwn);
                } else {
                    const floorPlanMsg = parseFloorPlanMessage(msg.message);
                    if (floorPlanMsg) {
                        content += renderFloorPlanShareCard(floorPlanMsg, isOwn, msg.id);
                    } else if (msg.message) {
                        content += '<div style="word-wrap:break-word;line-height:1.5;">' + formatChatMessage(msg.message) + '</div>';
                    }
                }
            }

            const readStatus = isOwn && !msg.is_deleted ? (msg.is_read ? '✓✓' : '✓') : '';
            const editedLabel = msg.is_edited ? '<span style="font-size:10px;opacity:0.5;margin-left:6px;">edited</span>' : '';

            return `<div style="display:flex;justify-content:${isOwn ? 'flex-end' : 'flex-start'};margin-bottom:4px;">
                <div class="chat-msg-bubble ${isOwn ? 'chat-msg-own' : 'chat-msg-other'}" data-msg-id="${msg.id}" style="padding:12px 16px;border-radius:${isOwn ? '20px 20px 4px 20px' : '20px 20px 20px 4px'};position:relative;box-shadow:0 2px 8px rgba(0,0,0,0.1);cursor:context-menu;" oncontextmenu="showContextMenu(event, ${msg.id}, ${isOwn}, ${msg.is_deleted})">
                    ${content}
                    <div style="font-size:11px;opacity:0.7;margin-top:6px;display:flex;align-items:center;justify-content:space-between;">
                        <span>${timeStr}${editedLabel}</span>
                        ${readStatus ? `<span style="margin-left:8px;">${readStatus}</span>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');

        requestAnimationFrame(function() {
            document.querySelectorAll('.chat-msg-audio-wrap').forEach(function(wrap) {
                const canvas = wrap.querySelector('.chat-msg-audio-waveform');
                const src = wrap.dataset.audioSrc;
                if (canvas && src) loadAudioWaveform(canvas, src);
            });
        });

        if (preserveScroll && prevHeight != null && prevTop != null) {
            requestAnimationFrame(function() {
                chatMessages.scrollTop = chatMessages.scrollHeight - prevHeight + prevTop;
            });
        } else if (forceScrollBottom || wasAtBottom) {
            scrollChatToBottom(!!forceScrollBottom);
        }
    }

    window.showContextMenu = function(e, msgId, isOwn, isDeleted) {
        e.preventDefault();
        if (isDeleted) return;
        if (!isOwn && !isAdmin) return;

        contextMessageId = msgId;
        contextMenu.style.display = 'block';

        const menuWidth = contextMenu.offsetWidth || 160;
        const menuHeight = contextMenu.offsetHeight || 96;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        let left = e.pageX;
        let top = e.pageY;

        if (left + menuWidth > viewportWidth) {
            left = viewportWidth - menuWidth - 8;
        }

        if (top + menuHeight > viewportHeight) {
            top = viewportHeight - menuHeight - 8;
        }

        contextMenu.style.left = left + 'px';
        contextMenu.style.top = top + 'px';

        contextEdit.style.display = isOwn ? 'block' : 'none';
        contextDelete.style.display = 'block';
    };

    function startEdit(messageId) {
        var oldPv = document.getElementById('edit-media-preview');
        if (oldPv) {
            oldPv.remove();
        }
        editingMessageId = messageId;

        // Find message data
        const msgData = messagesData.find(m => m.id === messageId);
        if (!msgData) return;

        // Set text
        chatInput.value = msgData.message || '';
        chatInput.focus();

        // Show current media preview if exists
        if (msgData.media_path) {
            const preview = document.createElement('div');
            preview.id = 'edit-media-preview';
            preview.style.cssText = 'padding:10px;background:rgba(99,102,241,0.1);border-radius:12px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;';

            if (msgData.media_type === 'image') {
                preview.innerHTML = `<img src="${msgData.media_path}" style="max-width:100px;max-height:100px;border-radius:8px;"><span style="color:var(--text-color);font-size:13px;">Current image (upload new to replace)</span>`;
            } else {
                preview.innerHTML = `<video src="${msgData.media_path}" style="max-width:100px;max-height:100px;border-radius:8px;"></video><span style="color:var(--text-color);font-size:13px;">Current video (upload new to replace)</span>`;
            }

            chatInput.parentElement.insertBefore(preview, chatInput.parentElement.firstChild);
        }

        if (chatCancelEditBtn) {
            chatCancelEditBtn.style.display = 'block';
        }
        if (chatSendBtn) {
            chatSendBtn.textContent = 'Save';
            chatSendBtn.style.width = 'auto';
            chatSendBtn.style.minWidth = '72px';
            chatSendBtn.style.height = '48px';
            chatSendBtn.style.padding = '0 16px';
            chatSendBtn.style.borderRadius = '24px';
            chatSendBtn.style.fontSize = '14px';
            chatSendBtn.style.fontWeight = '600';
            chatSendBtn.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
            chatSendBtn.style.boxShadow = '0 4px 12px rgba(245, 158, 11, 0.4)';
        }
        if (chatUploadBtn) {
            chatUploadBtn.style.display = 'flex';
        }
    }

    function saveEdit() {
        if (!editingMessageId) return;

        const newMessage = chatInput.value.trim();
        const newFile = chatFileInput.files[0];
        const msgRow = messagesData.find(m => m.id === editingMessageId);
        const hadKeepableMedia = !!(msgRow && msgRow.media_path && !msgRow.is_deleted);

        if (!newMessage && !newFile && !hadKeepableMedia) {
            if (typeof showTempNotification === 'function') {
                showTempNotification(T.support_chat_msg_empty || 'Message cannot be empty', 'error');
            } else {
                chatAlert(T.support_chat_msg_empty || 'Message cannot be empty');
            }
            return;
        }

        const formData = new FormData();
        formData.append('action', 'edit_message');
        formData.append('message_id', editingMessageId);
        formData.append('message', newMessage);
        if (newFile) {
            if (newFile.size > 20 * 1024 * 1024) {
                if (typeof showTempNotification === 'function') {
                    showTempNotification(T.support_chat_file_too_large || 'File size must not exceed 20MB', 'error');
                } else {
                    chatAlert(T.support_chat_file_too_large || 'File size must not exceed 20MB');
                }
                return;
            }
            formData.append('media', newFile);
        }

        fetch('?api=support_chat', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                clearAdminEditDraftForCurrentChat();
                cancelEditUI();
                loadMessages(false, { forceScrollBottom: true, full: true });
            } else {
                chatAlert('Error: ' + (data.error || T.unknown_error));
            }
        })
        .catch(err => {
            console.error('Error editing message:', err);
            if (typeof showTempNotification === 'function') {
                showTempNotification(T.network_error || 'Network error', 'error');
            } else {
                chatAlert(T.network_error || 'Network error');
            }
        });
    }

    function sendMessage(file, preText) {
        const typed = preText != null ? String(preText).trim() : (chatInput ? chatInput.value.trim() : '');
        const withPendingPlan = !!chatPendingPlan && preText == null;
        let message;
        if (withPendingPlan) {
            const note = typed || undefined;
            message = JSON.stringify({ type: 'floor_plan_share', plan: chatPendingPlan, note: note, ts: Date.now() });
        } else {
            message = typed;
        }

        if (!message && !file) return;
        if (!currentChatUser) return;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('message', message);
        formData.append('chat_type', currentChatType);
        if (currentChatType === 'peer') {
            formData.append('peer_email', currentPeerEmail);
        } else if (isAdmin) {
            formData.append('user_email', currentChatUser);
            formData.append('send_as', 'support');
        }
        if (file) {
            if (file.size > 20 * 1024 * 1024) {
                if (typeof showTempNotification === 'function') {
                    showTempNotification(T.support_chat_file_too_large || 'File size must not exceed 20MB', 'error');
                } else {
                    chatAlert(T.support_chat_file_too_large || 'File size must not exceed 20MB');
                }
                return;
            }
            formData.append('media', file);
        }

        fetch('?api=support_chat', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                if (chatPendingPlan) chatClearPendingPlan();
                if (chatInput) chatInput.value = '';
                if (chatFileInput) chatFileInput.value = '';
                if (chatMaxId > 0) {
                    loadMessages(true, { forceScrollBottom: true });
                } else {
                    loadMessages(false, { forceScrollBottom: true });
                }
            } else {
                chatAlert('Error: ' + (data.error || T.unknown_error));
            }
        })
        .catch(err => {
            console.error('Error sending message:', err);
            if (typeof showTempNotification === 'function') {
                showTempNotification(T.network_error || 'Network error', 'error');
            } else {
                chatAlert(T.network_error || 'Network error');
            }
        });
    }

    function deleteMessage(messageId) {
        const formData = new FormData();
        formData.append('action', 'delete_message');
        formData.append('message_id', messageId);

        fetch('?api=support_chat', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                loadMessages(false, { full: true });
            } else {
                chatAlert('Error: ' + (data.error || T.unknown_error));
            }
        })
        .catch(err => console.error('Error deleting message:', err));
    }

    // Format chat message with markdown-like syntax
    function formatChatMessage(text) {
        if (!text) return '';

        // Escape HTML first
        text = escChat(text);

        // Code blocks with language (```lang\ncode\n```)
        text = text.replace(/```(\w+)?\n([\s\S]*?)```/g, function(match, lang, code) {
            lang = lang || 'text';
            const codeId = 'code-' + Math.random().toString(36).substr(2, 9);
            return '<div class="chat-msg-code-block">' +
                '<div class="chat-msg-code-header">' +
                '<span>' + escChat(lang) + '</span>' +
                '<button class="chat-msg-code-copy" onclick="copyChatCode(\'' + codeId + '\')">Copy</button>' +
                '</div>' +
                '<div class="chat-msg-code-content"><code id="' + codeId + '">' + code.trim() + '</code></div>' +
                '</div>';
        });

        // Inline code (`code`)
        text = text.replace(/`([^`]+)`/g, '<span class="chat-msg-inline-code">$1</span>');

        // Bold (**text** or __text__)
        text = text.replace(/\*\*([^\*]+)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');

        // Italic (*text* or _text_)
        text = text.replace(/\*([^\*]+)\*/g, '<em>$1</em>');
        text = text.replace(/_([^_]+)_/g, '<em>$1</em>');

        // Strikethrough (~~text~~)
        text = text.replace(/~~([^~]+)~~/g, '<del>$1</del>');

        // Quote (> text)
        text = text.replace(/^&gt;\s*(.+)$/gm, '<div class="chat-msg-quote">$1</div>');

        // Line breaks
        text = text.replace(/\n/g, '<br>');

        return text;
    }

    // Copy code block content
    window.copyChatCode = function(codeId) {
        const codeEl = document.getElementById(codeId);
        if (!codeEl) return;

        const text = codeEl.textContent;
        navigator.clipboard.writeText(text).then(function() {
            const btn = codeEl.closest('.chat-msg-code-block').querySelector('.chat-msg-code-copy');
            if (btn) {
                const orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = orig; }, 2000);
            }
        }).catch(function(err) {
            console.error('Failed to copy:', err);
        });
    };

    // Audio playback
    const audioPlayers = {};
    window.toggleAudioPlay = function(audioId, btn) {
        const audio = document.getElementById(audioId);
        if (!audio) return;

        if (audio.paused) {
            // Pause all other audio
            Object.keys(audioPlayers).forEach(function(id) {
                if (id !== audioId && audioPlayers[id]) {
                    audioPlayers[id].pause();
                    audioPlayers[id].currentTime = 0;
                }
            });

            audio.play();
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>';
            audioPlayers[audioId] = audio;

            audio.ontimeupdate = function() {
                updateAudioProgress(audioId);
            };
            audio.onended = function() {
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
                audio.currentTime = 0;
                const _wrap = audio.closest('.chat-msg-audio-wrap');
                const _timeEl = _wrap ? _wrap.querySelector('.chat-msg-audio-time') : null;
                const _canvas = _wrap ? _wrap.querySelector('.chat-msg-audio-waveform') : null;
                const fmtT = function(s) { s=Math.floor(s)||0; return Math.floor(s/60)+':'+(s%60<10?'0':'')+(s%60); };
                if (_timeEl && audio.duration) _timeEl.textContent = fmtT(audio.duration);
                if (_canvas) {
                    const peaks = _canvas.dataset.peaks ? JSON.parse(_canvas.dataset.peaks) : null;
                    drawAudioWaveform(_canvas, 0, peaks);
                }
            };
        } else {
            audio.pause();
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
        }
    };

    function updateAudioProgress(audioId) {
        const audio = document.getElementById(audioId);
        if (!audio) return;
        const wrap = audio.closest('.chat-msg-audio-wrap');
        if (!wrap) return;
        const timeEl = wrap.querySelector('.chat-msg-audio-time');
        const canvas = wrap.querySelector('.chat-msg-audio-waveform');
        const fmtT = function(s) { s = Math.floor(s)||0; return Math.floor(s/60)+':'+(s%60<10?'0':'')+(s%60); };
        if (timeEl && audio.duration) timeEl.textContent = fmtT(audio.currentTime);
        if (canvas && audio.duration) {
            const peaks = canvas.dataset.peaks ? JSON.parse(canvas.dataset.peaks) : null;
            drawAudioWaveform(canvas, audio.currentTime / audio.duration, peaks);
        }
    }

    function loadAudioWaveform(canvas, src) {
        const audioId = canvas.dataset.audioId;
        const audio = document.getElementById(audioId);
        const wrap = canvas.closest('.chat-msg-audio-wrap');
        const timeEl = wrap ? wrap.querySelector('.chat-msg-audio-time') : null;
        const fmtT = function(s) { s=Math.floor(s)||0; return Math.floor(s/60)+':'+(s%60<10?'0':'')+(s%60); };
        if (audio) {
            const applyDur = function() { if (audio.duration && isFinite(audio.duration) && timeEl) timeEl.textContent = fmtT(audio.duration); };
            if (audio.readyState >= 1) applyDur(); else audio.addEventListener('loadedmetadata', applyDur);
        }
        canvas.addEventListener('click', function(e) {
            if (!audio || !audio.duration) return;
            audio.currentTime = ((e.clientX - canvas.getBoundingClientRect().left) / canvas.getBoundingClientRect().width) * audio.duration;
            updateAudioProgress(audioId);
        });
        if (canvas.dataset.peaks) { drawAudioWaveform(canvas, 0, JSON.parse(canvas.dataset.peaks)); return; }
        drawAudioWaveform(canvas, 0, null);
        fetch(src)
            .then(function(r) { return r.arrayBuffer(); })
            .then(function(buf) {
                const ac = new (window.AudioContext || window.webkitAudioContext)();
                return ac.decodeAudioData(buf).then(function(decoded) { ac.close(); return decoded; });
            })
            .then(function(decoded) {
                const raw = decoded.getChannelData(0);
                const bars = 50;
                const block = Math.floor(raw.length / bars);
                const peaks = [];
                for (let i = 0; i < bars; i++) {
                    let max = 0;
                    for (let j = 0; j < block; j++) { const v = Math.abs(raw[i*block+j]); if (v > max) max = v; }
                    peaks.push(max);
                }
                const maxP = Math.max.apply(null, peaks.concat([0.01]));
                const norm = peaks.map(function(p) { return p / maxP; });
                canvas.dataset.peaks = JSON.stringify(norm);
                drawAudioWaveform(canvas, 0, norm);
            })
            .catch(function() {});
    }
    function drawAudioWaveform(canvas, progress, peaks) {
        if (!canvas) return;
        progress = progress || 0;
        const ctx = canvas.getContext('2d');
        const w = canvas.clientWidth || canvas.offsetWidth || 200;
        const h = canvas.clientHeight || canvas.offsetHeight || 36;
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        ctx.scale(dpr, dpr);
        const wrap = canvas.closest('.chat-msg-audio-wrap');
        const isOwn = !!(wrap && wrap.dataset.own === '1');
        const chatColor = getComputedStyle(document.documentElement).getPropertyValue('--chat-color').trim() || '#6366f1';
        const bars = peaks ? peaks.length : 48;
        const gap = 2;
        const barW = Math.max(2, (w - gap * (bars - 1)) / bars);
        ctx.clearRect(0, 0, w, h);
        for (let i = 0; i < bars; i++) {
            let amp;
            if (peaks) {
                amp = 0.1 + peaks[i] * 0.88;
            } else {
                const seed = canvas.dataset.audioId || '0';
                const x = Math.sin(i * 12.9898 + seed.length) * 43758.5453;
                amp = 0.15 + (x - Math.floor(x)) * 0.55;
            }
            const barH = Math.max(3, amp * h);
            const x = i * (barW + gap);
            const y = (h - barH) / 2;
            const r = Math.min(barW / 2, 2);
            const played = i / bars < progress;
            ctx.fillStyle = played
                ? (isOwn ? 'rgba(255,255,255,0.95)' : chatColor)
                : (isOwn ? 'rgba(255,255,255,0.28)' : 'rgba(140,145,155,0.38)');
            ctx.beginPath();
            if (ctx.roundRect) {
                ctx.roundRect(x, y, barW, barH, r);
            } else {
                ctx.moveTo(x + r, y);
                ctx.arcTo(x+barW, y, x+barW, y+barH, r);
                ctx.arcTo(x+barW, y+barH, x, y+barH, r);
                ctx.arcTo(x, y+barH, x, y, r);
                ctx.arcTo(x, y, x+barW, y, r);
                ctx.closePath();
            }
            ctx.fill();
        }
    }

    // Open text file in Monaco editor
    window.openTextFileEditor = function(filePath, fileName) {
        const modal = document.getElementById('chat-text-editor-modal');
        const title = document.getElementById('chat-text-editor-title');
        const container = document.getElementById('chat-text-editor-container');
        const saveBtn = document.getElementById('chat-text-editor-save');
        const readonlyBadge = document.getElementById('chat-text-editor-readonly-badge');

        if (!modal || !container) return;

        title.textContent = fileName;
        modal.style.display = 'flex';

        // Fetch file content via API
        fetch('?api=support_chat&action=read_file&file=' + encodeURIComponent(filePath))
            .then(r => r.json())
            .then(data => {
                if (!data.ok) {
                    throw new Error(data.error || 'Failed to load file');
                }

                const content = data.content;

                // Detect language from extension
                const ext = fileName.split('.').pop().toLowerCase();
                const langMap = {
                    'js': 'javascript', 'ts': 'typescript', 'py': 'python',
                    'java': 'java', 'c': 'c', 'cpp': 'cpp', 'cs': 'csharp',
                    'php': 'php', 'rb': 'ruby', 'go': 'go', 'rs': 'rust',
                    'sql': 'sql', 'html': 'html', 'css': 'css', 'json': 'json',
                    'xml': 'xml', 'yaml': 'yaml', 'yml': 'yaml', 'md': 'markdown',
                    'sh': 'shell', 'bat': 'bat', 'txt': 'plaintext'
                };
                const language = langMap[ext] || 'plaintext';

                // Load Monaco Editor if not loaded
                if (!window.monaco) {
                    loadMonacoEditor(function() {
                        createMonacoEditor(container, content, language, filePath, fileName);
                    });
                } else {
                    createMonacoEditor(container, content, language, filePath, fileName);
                }

                // Show as read-only for now
                readonlyBadge.style.display = 'inline-block';
                saveBtn.style.display = 'none';
            })
            .catch(err => {
                console.error('Failed to load file:', err);
                if (typeof showTempNotification === 'function') {
                    showTempNotification(T.failed_to_load_file + ' ' + err.message, 'error');
                } else {
                    chatAlert(T.failed_to_load_file + ' ' + err.message);
                }
                modal.style.display = 'none';
            });
    };

    let monacoEditor = null;
    function createMonacoEditor(container, content, language, filePath, fileName) {
        container.innerHTML = '';

        const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';

        monacoEditor = monaco.editor.create(container, {
            value: content,
            language: language,
            theme: theme,
            readOnly: true,
            automaticLayout: true,
            minimap: { enabled: true },
            fontSize: 14,
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            wordWrap: 'on'
        });
    }

    function loadMonacoEditor(callback) {
        window.apLoadMonaco().then(callback).catch(function(e){console.error('Failed to load Monaco:', e);});
    }

    function closeSupportChatDeleteModal() {
        if (!supportChatDeleteModal) {
            return;
        }
        supportChatDeleteModal.style.display = 'none';
        if (supportChatDeleteConfirm) {
            supportChatDeleteConfirm.disabled = false;
        }
        if (supportChatDeleteCancel) {
            supportChatDeleteCancel.disabled = false;
        }
    }
    function executeSupportChatDelete() {
        if (!currentChatUser) {
            return;
        }
        var ue = currentChatUser;
        if (supportChatDeleteConfirm) {
            supportChatDeleteConfirm.disabled = true;
        }
        if (supportChatDeleteCancel) {
            supportChatDeleteCancel.disabled = true;
        }
        const formData = new FormData();
        formData.append('action', 'delete_chat');
        formData.append('chat_type', currentChatType);
        if (currentChatType === 'peer') {
            formData.append('peer_email', currentPeerEmail);
        } else {
            formData.append('user_email', ue);
        }
        fetch('?api=support_chat', {
            method: 'POST',
            body: formData
        })
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (data.ok) {
                    closeSupportChatDeleteModal();
                    currentChatUser = null;
                    currentPeerEmail = '';
                    currentChatType = 'support';
                    currentPeerBlocked = false;
                    chatMaxId = 0;
                    chatOldestId = null;
                    lastMessagesHash = '';
                    if (chatMessagesArea) {
                        chatMessagesArea.style.display = 'none';
                    }
                    if (deleteChatBtn) {
                        deleteChatBtn.style.display = 'none';
                    }
                    if (chatModalTitle) chatModalTitle.textContent = 'Messenger';
                    adminMobilePhase = 'list';
                    syncAdminMobileLayout();
                    loadChatList();
                    stopPolling();
                } else {
                    chatAlert('Error: ' + (data.error || 'Unknown error'));
                    if (supportChatDeleteConfirm) {
                        supportChatDeleteConfirm.disabled = false;
                    }
                    if (supportChatDeleteCancel) {
                        supportChatDeleteCancel.disabled = false;
                    }
                }
            })
            .catch(function(err) {
                console.error('Error deleting chat:', err);
                chatAlert(T.network_error || 'Network error');
                if (supportChatDeleteConfirm) {
                    supportChatDeleteConfirm.disabled = false;
                }
                if (supportChatDeleteCancel) {
                    supportChatDeleteCancel.disabled = false;
                }
            });
    }
    function openSupportChatDeleteModal() {
        if (!currentChatUser) {
            return;
        }
        if (!supportChatDeleteModal) {
            var fb = T.support_chat_delete_confirm || 'Delete this entire chat? This cannot be undone.';
            if (typeof fmConfirm === 'function') {
                fmConfirm(fb, executeSupportChatDelete);
            } else if (window.confirm(fb)) {
                executeSupportChatDelete();
            }
            return;
        }
        if (supportChatDeleteEmail) {
            supportChatDeleteEmail.textContent = currentChatUser;
        }
        supportChatDeleteModal.style.display = 'flex';
        requestAnimationFrame(function() {
            supportChatDeleteModal.focus();
        });
    }
    function wireSupportChatDeleteModal() {
        if (!supportChatDeleteModal || supportChatDeleteModal._scDelWired) {
            return;
        }
        supportChatDeleteModal._scDelWired = true;
        supportChatDeleteModal.addEventListener('click', function(e) {
            if (e.target === supportChatDeleteModal) {
                closeSupportChatDeleteModal();
            }
        });
        if (supportChatDeleteCard) {
            supportChatDeleteCard.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
        if (supportChatDeleteCancel) {
            supportChatDeleteCancel.addEventListener('click', closeSupportChatDeleteModal);
        }
        if (supportChatDeleteConfirm) {
            supportChatDeleteConfirm.addEventListener('click', executeSupportChatDelete);
        }
        window.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') {
                return;
            }
            if (!supportChatDeleteModal || supportChatDeleteModal.style.display !== 'flex') {
                return;
            }
            closeSupportChatDeleteModal();
            e.preventDefault();
            e.stopPropagation();
        }, true);
    }
    function deleteChat() {
        if (!currentChatUser) {
            return;
        }
        wireSupportChatDeleteModal();
        openSupportChatDeleteModal();
    }

    if (deleteChatBtn) {
        deleteChatBtn.addEventListener('click', deleteChat);
    }
    if (blockChatBtn) {
        blockChatBtn.addEventListener('click', function() {
            if (!currentPeerEmail || currentChatType !== 'peer') return;
            if (!currentPeerBlocked) {
                stopPolling();
            }
            const formData = new FormData();
            formData.append('action', currentPeerBlocked ? 'unblock_user' : 'block_user');
            formData.append('peer_email', currentPeerEmail);
            fetch('?api=support_chat', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) {
                        chatAlert('Error: ' + (data.error || 'Unknown error'));
                        return;
                    }
                    currentPeerBlocked = !currentPeerBlocked;
                    setHeaderActions();
                    if (currentPeerBlocked) {
                        stopPolling();
                    } else {
                        startPolling();
                    }
                    loadChatList();
                })
                .catch(() => chatAlert(T.network_error || 'Network error'));
        });
    }

    function markAsRead() {
        if (!currentChatUser) return;

        const url = '?api=support_chat&action=mark_read' + currentChatQuery();

        fetch(url).catch(err => console.error('Error marking as read:', err));
    }

    function startPolling() {
        stopPolling();
        chatPollInterval = setInterval(() => {
            loadMessages(true);
            checkUnreadMessages();
        }, 3000);
    }

    function stopPolling() {
        if (chatPollInterval) {
            clearInterval(chatPollInterval);
            chatPollInterval = null;
        }
    }

    var unreadRequestInFlight = false;
    var lastUnreadWarnAt = 0;
    function checkUnreadMessages() {
        if (unreadRequestInFlight) return;
        unreadRequestInFlight = true;
        fetch('?api=support_chat&action=get_unread_count')
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (!data.ok) {
                    return;
                }
                updateChatButton(data.count || 0);
            })
            .catch(function(err) {
                var now = Date.now();
                if (now - lastUnreadWarnAt > 30000) {
                    lastUnreadWarnAt = now;
                    console.warn('Error checking unread:', err && err.message ? err.message : err);
                }
            })
            .finally(function() {
                unreadRequestInFlight = false;
            });
    }

    function updateChatButton(count) {
        const btn = document.getElementById('support-chat-btn');
        const badge = document.getElementById('chat-unread-badge');
        if (!btn) {
            return;
        }
        if (count > 0) {
            btn.classList.add('has-unread');
            if (badge) {
                badge.style.display = 'block';
                badge.textContent = count > 99 ? '99+' : count;
            }
        } else {
            btn.classList.remove('has-unread');
            if (badge) badge.style.display = 'none';
        }
    }

    // Check unread on page load
    if (chatBtn) {
        setTimeout(checkUnreadMessages, 1000);
        setInterval(checkUnreadMessages, 30000); // Check every 30 seconds
    }
})();
</script>
