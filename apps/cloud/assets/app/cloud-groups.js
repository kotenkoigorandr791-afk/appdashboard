(function () {
    const state = {
        groups: [],
        activeGroupId: null,
        manageGroupId: null,
        loaded: false
    };
    window.cloudGroupsState = state;
    function t(key, params) {
        return typeof window.t === 'function' ? window.t(window.i18n || {}, key, params) : key;
    }
    function isGroupRoot(root) {
        return String(root || '').startsWith('group:');
    }
    function groupIdFromRoot(root) {
        return isGroupRoot(root) ? String(root).slice(6) : null;
    }
    function groupRootKey(id) {
        return 'group:' + id;
    }
    function registerGroupRoot(rootKey) {
        if (isGroupRoot(rootKey) && window.cloudRootState) {
            window.cloudRootState.allowed.add(rootKey);
        }
    }
    function getGroupById(id) {
        return state.groups.find((g) => g.id === id) || null;
    }
    function getActiveGroup() {
        return state.activeGroupId ? getGroupById(state.activeGroupId) : null;
    }
    function getToolbarGroupRoot() {
        if (!state.groups.length) return null;
        const current = typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main';
        if (isGroupRoot(current)) return current;
        const id = state.activeGroupId || state.groups[0]?.id;
        return id ? groupRootKey(id) : null;
    }
    function updateToolbarButton() {
        const btn = document.getElementById('btn-root-groups');
        if (!btn) return;
        const visible = state.groups.length > 0;
        btn.classList.toggle('hidden', !visible);
        const active = isGroupRoot(typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : '');
        btn.classList.toggle('active', active);
        const toolbarRoot = getToolbarGroupRoot();
        if (toolbarRoot) btn.dataset.root = toolbarRoot;
        else delete btn.dataset.root;
    }
    function updateSectionTitle() {
        const label = document.getElementById('section-title-label');
        if (!label) return;
        const root = typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main';
        const showGroupSwitcher = isGroupRoot(root) && state.groups.length > 1;
        if (showGroupSwitcher) {
            label.classList.add('hidden');
        } else {
            label.classList.remove('hidden');
            if (!label.getAttribute('data-i18n')) label.setAttribute('data-i18n', 'folders');
            label.textContent = t('folders');
        }
        renderGroupSwitcher();
    }
    function renderGroupSwitcher() {
        const host = document.getElementById('group-switcher-bar');
        if (host) {
            host.classList.add('hidden');
            host.innerHTML = '';
        }
        const select = document.getElementById('group-section-switcher');
        if (!select) return;
        const root = typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main';
        if (!isGroupRoot(root) || state.groups.length < 2) {
            select.classList.add('hidden');
            select.innerHTML = '';
            return;
        }
        const currentId = groupIdFromRoot(root);
        select.classList.remove('hidden');
        select.innerHTML = state.groups.map((g) => '<option value="' + g.id + '"' + (g.id === currentId ? ' selected' : '') + '>' +
            escapeHtml(g.name) + (!g.is_admin && g.permission === 'read' ? ' (' + t('permReadOnly') + ')' : '') + '</option>').join('');
        if (!select.dataset.bound) {
            select.dataset.bound = '1';
            select.addEventListener('change', () => switchToGroup(select.value));
        }
    }
    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }
    async function loadGroups() {
        try {
            const resp = await api('groups_list', {}, 'GET');
            if (!resp || resp.ok === false) return;
            const next = Array.isArray(resp.groups) ? resp.groups : [];
            state.groups = next;
            state.groups.forEach((g) => registerGroupRoot(groupRootKey(g.id)));
            state.loaded = true;
            updateToolbarButton();
            renderGroupSwitcher();
            updateSectionTitle();
            if (window.cloudGroupChat && typeof window.cloudGroupChat.onGroupsLoaded === 'function') {
                window.cloudGroupChat.onGroupsLoaded();
            }
        } catch (e) {
            // groups_list can fail transiently (server restart, 502 from the
            // proxy). The empty group list is rendered either way, so keep this
            // out of the application-error channel.
            console.warn('groups_list failed', e && e.message ? e.message : e);
        }
    }
    async function switchToGroup(groupId) {
        if (!groupId) return;
        const root = groupRootKey(groupId);
        registerGroupRoot(root);
        state.activeGroupId = groupId;
        const btn = document.getElementById('btn-root-groups');
        if (btn) btn.dataset.root = root;
        if (typeof switchCloudRoot === 'function') {
            await switchCloudRoot(root);
        }
        updateToolbarButton();
        renderGroupSwitcher();
        updateSectionTitle();
        markGroupNotificationsRead(groupId);
    }
    function markGroupNotificationsRead(groupId) {
        if (!groupId) return;
        api('groups_mark_notifications_read', { group_id: groupId }).catch(() => {});
    }
    function formatTs(ts) {
        if (!ts) return '';
        try { return new Date(ts * 1000).toLocaleString(); } catch (_) { return String(ts); }
    }
    function leaveGroupIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 12h11"/><path d="m17 16 4-4-4-4"/><path d="M21 6.344V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1.344"/></svg>';
    }
    function isFullEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
    }
    async function leaveGroup(gid) {
        const group = getGroupById(gid);
        if (!group || group.is_admin) return;
        const confirmFn = typeof showConfirmModal === 'function' ? showConfirmModal : null;
        if (confirmFn) {
            const { confirmed } = await confirmFn(t('groupLeave'), t('groupLeaveConfirm', { name: group.name }));
            if (!confirmed) return;
        }
        try {
            await api('groups_leave', { group_id: gid });
            state.groups = state.groups.filter((g) => g.id !== gid);
            if (state.activeGroupId === gid) {
                state.activeGroupId = null;
                if (typeof switchCloudRoot === 'function') await switchCloudRoot('main');
            }
            if (state.manageGroupId === gid) state.manageGroupId = state.groups[0]?.id || null;
            await loadGroups();
            if (!state.groups.length) closeManageModal();
            else await renderManagePanel();
            if (window.cloudGroupChat && typeof window.cloudGroupChat.onGroupsLoaded === 'function') window.cloudGroupChat.onGroupsLoaded();
            if (typeof flashStatus === 'function') flashStatus(t('groupLeft'));
        } catch (e) {
            if (typeof flashStatus === 'function') flashStatus(t('errorPrefix') + e.message);
        }
    }
    function historyIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>';
    }
    function historyActionClass(action) {
        const a = String(action || '').toLowerCase();
        if (a === 'delete') return 'groups-history-action--delete';
        if (a === 'edit' || a === 'rename') return 'groups-history-action--edit';
        if (a === 'move') return 'groups-history-action--move';
        if (a === 'create') return 'groups-history-action--create';
        return 'groups-history-action--open';
    }
    function historyPathLang(path) {
        const ext = String(path || '').split('.').pop().toLowerCase();
        const map = { js: 'javascript', mjs: 'javascript', ts: 'typescript', py: 'python', php: 'php', json: 'json', html: 'html', htm: 'html', css: 'css', md: 'markdown', sql: 'sql', sh: 'bash', bash: 'bash', xml: 'xml', yaml: 'yaml', yml: 'yaml', tex: 'latex', svg: 'xml' };
        return map[ext] || 'plaintext';
    }
    function formatHistoryFilePath(path) {
        const lang = historyPathLang(path);
        return '<code class="groups-history-path hljs language-' + lang + '">' + escapeHtml(path) + '</code>';
    }
    function highlightHistoryPaths(host) {
        if (!host || !window.hljs) return;
        host.querySelectorAll('.groups-history-path').forEach((el) => {
            try { window.hljs.highlightElement(el); } catch (_) {}
        });
    }
    function formatDeleteTargetLabel(root, path) {
        const rootLabel = typeof getCloudRootDisplayName === 'function' ? getCloudRootDisplayName(root) : root;
        const folder = path ? path + '/' : '';
        return folder ? rootLabel + '/' + folder : rootLabel + '/';
    }
    function groupRoleLabel(group) {
        if (!group) return '';
        if (group.is_admin) return t('groupAdmin');
        if (group.permission === 'read_edit') return t('permReadEdit');
        return t('permReadOnly');
    }
    function renderGroupsOverviewTable() {
        if (!state.groups.length) {
            return '<p class="groups-list-empty">' + t('groupListEmpty') + '</p>';
        }
        const rows = state.groups.map((g) => {
            const active = g.id === state.manageGroupId ? ' groups-overview-row--active' : '';
            const adminBadge = g.is_admin ? ' groups-role-badge--admin' : '';
            const leaveBtn = !g.is_admin ? '<button type="button" class="icon-btn groups-leave-btn" data-group-id="' + g.id + '" title="' + escapeHtml(t('groupLeave')) + '" aria-label="' + escapeHtml(t('groupLeave')) + '">' + leaveGroupIcon() + '</button>' : '';
            return '<tr class="groups-overview-row' + active + '" data-group-id="' + g.id + '">' +
                '<td class="groups-col-name"><strong>' + escapeHtml(g.name) + '</strong></td>' +
                '<td class="groups-col-admin">' + escapeHtml(g.admin_email) + '</td>' +
                '<td class="groups-col-role"><span class="groups-role-badge' + adminBadge + '">' + escapeHtml(groupRoleLabel(g)) + '</span></td>' +
                '<td class="groups-col-actions"><button type="button" class="btn secondary small group-open-btn" data-group-id="' + g.id + '">' + t('groupOpen') + '</button>' + leaveBtn + '</td>' +
                '</tr>';
        }).join('');
        return '<div class="groups-overview-wrap"><table class="groups-overview-table">' +
            '<thead><tr><th>' + t('groupListName') + '</th><th>' + t('groupAdminLabel') + '</th><th>' + t('groupYourPermission') + '</th><th></th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div>';
    }
    function bindGroupsOverviewEvents(panel) {
        panel.querySelectorAll('.groups-overview-row').forEach((row) => {
            row.addEventListener('click', (event) => {
                if (event.target.closest('.group-open-btn') || event.target.closest('.groups-leave-btn')) return;
                const gid = row.dataset.groupId;
                if (!gid || gid === state.manageGroupId) return;
                state.manageGroupId = gid;
                renderManagePanel();
            });
        });
        panel.querySelectorAll('.group-open-btn').forEach((btn) => {
            btn.addEventListener('click', async (event) => {
                event.stopPropagation();
                const gid = btn.dataset.groupId;
                if (!gid) return;
                closeManageModal();
                await switchToGroup(gid);
            });
        });
        panel.querySelectorAll('.groups-leave-btn').forEach((btn) => {
            btn.addEventListener('click', async (event) => {
                event.stopPropagation();
                const gid = btn.dataset.groupId;
                if (gid) await leaveGroup(gid);
            });
        });
    }
    function openManageModal(groupId) {
        state.manageGroupId = groupId || (getActiveGroup()?.id) || (state.groups[0]?.id);
        const modal = document.getElementById('modal-groups-manage');
        if (!modal) return;
        modal.classList.remove('hidden');
        renderManagePanel();
    }
    function closeManageModal() {
        document.getElementById('modal-groups-manage')?.classList.add('hidden');
    }
    async function renderManagePanel() {
        const panel = document.getElementById('groups-manage-body');
        if (!panel) return;
        if (!state.manageGroupId && state.groups.length) state.manageGroupId = state.groups[0].id;
        const gid = state.manageGroupId;
        const group = getGroupById(gid);
        if (gid) markGroupNotificationsRead(gid);
        let detailHtml = '';
        if (!group) {
            detailHtml = '<div class="groups-detail-panel"><p>' + t('groupNotFound') + '</p></div>';
        } else {
            let membersHtml = '';
            if (group.is_admin) {
                try {
                    const m = await api('groups_members', { group_id: gid }, 'GET');
                    const members = Array.isArray(m.members) ? m.members : [];
                    membersHtml = '<div class="groups-members-section"><h4>' + t('groupMembers') + '</h4>' +
                        '<div class="groups-add-member"><div class="groups-add-email-wrap">' +
                        '<input type="email" id="group-add-email" placeholder="email@example.com" autocomplete="off" />' +
                        '<div id="group-add-suggest" class="groups-add-suggest hidden" role="listbox"></div></div>' +
                        '<select id="group-add-perm"><option value="read_edit">' + t('permReadEdit') + '</option><option value="read">' + t('permReadOnly') + '</option></select>' +
                        '<button type="button" class="btn primary small" id="btn-group-add-member">' + t('groupAddMember') + '</button></div>' +
                        '<div class="groups-history-table-wrap"><table class="groups-members-table"><thead><tr><th>Email</th><th>' + t('groupPermission') + '</th><th></th></tr></thead><tbody>' +
                        members.map((mem) => '<tr><td>' + escapeHtml(mem.email) + '</td><td>' +
                            '<select class="group-member-perm" data-email="' + escapeHtml(mem.email) + '">' +
                            '<option value="read_edit"' + (mem.permission === 'read_edit' ? ' selected' : '') + '>' + t('permReadEdit') + '</option>' +
                            '<option value="read"' + (mem.permission === 'read' ? ' selected' : '') + '>' + t('permReadOnly') + '</option></select></td>' +
                            '<td><button type="button" class="btn danger small group-remove-member" data-email="' + escapeHtml(mem.email) + '">' + t('groupRemoveMember') + '</button></td></tr>').join('') +
                        '</tbody></table></div></div>';
                } catch (e) {
                    membersHtml = '<p class="error-text">' + t('errorPrefix') + e.message + '</p>';
                }
            }
            const historyBtn = group.is_admin ? '<button type="button" class="btn secondary small" id="btn-group-history"><span class="groups-btn-icon" aria-hidden="true">' + historyIcon() + '</span><span>' + escapeHtml(t('groupHistory')) + '</span></button>' : '';
            const deleteBtn = group.is_admin ? '<button type="button" class="btn danger small" id="btn-group-delete">' + t('groupDelete') + '</button>' : '';
            const leaveBtn = !group.is_admin ? '<button type="button" class="btn secondary small groups-leave-action" id="btn-group-leave"><span class="groups-leave-action-icon" aria-hidden="true">' + leaveGroupIcon() + '</span><span>' + escapeHtml(t('groupLeave')) + '</span></button>' : '';
            const permLine = group.is_admin ? '' : '<p class="groups-perm-line">' + t('groupYourPermission') + ': ' + (group.permission === 'read_edit' ? t('permReadEdit') : t('permReadOnly')) + '</p>';
            const detailClass = group.is_admin ? 'groups-detail-panel' : 'groups-detail-panel groups-member-detail';
            detailHtml = '<div class="' + detailClass + '">' +
                '<h4 class="groups-detail-title">' + escapeHtml(group.name) + ' — ' + t('groupDetails') + '</h4>' +
                '<p class="groups-perm-line">' + t('groupAdminLabel') + ': ' + escapeHtml(group.admin_email) + '</p>' +
                permLine + membersHtml +
                '<div class="groups-manage-actions">' + historyBtn + deleteBtn + leaveBtn + '</div>' +
                '<div id="groups-history-panel" class="hidden"></div></div>';
        }
        panel.innerHTML = renderGroupsOverviewTable() + detailHtml;
        bindGroupsOverviewEvents(panel);
        wireGroupAddMemberLookup(panel, gid);
        panel.querySelector('#btn-group-add-member')?.addEventListener('click', async () => {
            const email = panel.querySelector('#group-add-email')?.value?.trim().toLowerCase();
            const perm = panel.querySelector('#group-add-perm')?.value || 'read_edit';
            if (!email) return;
            if (!isFullEmail(email)) {
                if (typeof flashStatus === 'function') flashStatus(t('groupInvalidEmail'));
                return;
            }
            if (!panel._groupAddUserValid || panel._groupAddUserValid !== email) {
                if (typeof flashStatus === 'function') flashStatus(t('groupUserNotFound'));
                return;
            }
            try {
                await api('groups_add_member', { group_id: gid, member_email: email, permission: perm });
                if (typeof flashStatus === 'function') flashStatus(t('groupMemberAdded'));
                panel._groupAddUserValid = null;
                await renderManagePanel();
            } catch (e) {
                if (typeof flashStatus === 'function') flashStatus(t('errorPrefix') + e.message);
            }
        });
        panel.querySelectorAll('.group-remove-member').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const email = btn.dataset.email;
                const confirmFn = typeof showConfirmModal === 'function' ? showConfirmModal : null;
                if (confirmFn) {
                    const { confirmed } = await confirmFn(t('groupRemoveMember'), t('groupRemoveMemberConfirm', { email: email }));
                    if (!confirmed) return;
                }
                try {
                    await api('groups_remove_member', { group_id: gid, member_email: email });
                    await renderManagePanel();
                } catch (e) {
                    if (typeof flashStatus === 'function') flashStatus(t('errorPrefix') + e.message);
                }
            });
        });
        panel.querySelectorAll('.group-member-perm').forEach((sel) => {
            sel.addEventListener('change', async () => {
                try {
                    await api('groups_update_member', { group_id: gid, member_email: sel.dataset.email, permission: sel.value });
                } catch (e) {
                    if (typeof flashStatus === 'function') flashStatus(t('errorPrefix') + e.message);
                }
            });
        });
        panel.querySelector('#btn-group-history')?.addEventListener('click', () => showHistory(gid));
        panel.querySelector('#btn-group-delete')?.addEventListener('click', () => confirmDeleteGroup(gid));
        panel.querySelector('#btn-group-leave')?.addEventListener('click', () => leaveGroup(gid));
    }
    function wireGroupAddMemberLookup(panel, gid) {
        const input = panel.querySelector('#group-add-email');
        const suggest = panel.querySelector('#group-add-suggest');
        if (!input || !suggest || !gid) return;
        let lookupTimer = null;
        let lastQuery = '';
        const hideSuggest = () => {
            suggest.classList.add('hidden');
            suggest.innerHTML = '';
        };
        const showUser = (user) => {
            if (!user) { hideSuggest(); return; }
            suggest.innerHTML = '<button type="button" class="groups-add-suggest-item" role="option">' +
                '<img class="groups-add-suggest-avatar" src="' + escapeHtml(user.photo_url || '/default_avatar.png') + '" alt="">' +
                '<span class="groups-add-suggest-email">' + escapeHtml(user.email) + '</span></button>';
            suggest.classList.remove('hidden');
            const btn = suggest.querySelector('.groups-add-suggest-item');
            btn?.addEventListener('click', () => {
                input.value = user.email;
                panel._groupAddUserValid = user.email.toLowerCase();
                hideSuggest();
            });
        };
        const runLookup = async () => {
            const value = input.value.trim().toLowerCase();
            panel._groupAddUserValid = null;
            if (!isFullEmail(value)) { hideSuggest(); return; }
            if (value === lastQuery && suggest.querySelector('.groups-add-suggest-item')) return;
            lastQuery = value;
            try {
                const resp = await api('groups_lookup_user', { group_id: gid, email: value }, 'GET');
                if (input.value.trim().toLowerCase() !== value) return;
                if (resp && resp.ok && resp.found && resp.user) {
                    panel._groupAddUserValid = resp.user.email.toLowerCase();
                    showUser(resp.user);
                } else hideSuggest();
            } catch (_) { hideSuggest(); }
        };
        input.addEventListener('input', () => {
            panel._groupAddUserValid = null;
            lastQuery = '';
            clearTimeout(lookupTimer);
            if (!isFullEmail(input.value.trim())) { hideSuggest(); return; }
            lookupTimer = setTimeout(runLookup, 280);
        });
        input.addEventListener('blur', () => setTimeout(hideSuggest, 160));
        input.addEventListener('focus', () => {
            if (isFullEmail(input.value.trim())) runLookup();
        });
        document.addEventListener('click', (e) => {
            if (!panel.contains(e.target)) hideSuggest();
        });
    }
    async function showHistory(gid) {
        const host = document.getElementById('groups-history-panel');
        if (!host) return;
        host.classList.remove('hidden');
        host.innerHTML = '<p>' + t('loading') + '...</p>';
        try {
            const resp = await api('groups_history', { group_id: gid }, 'GET');
            const items = Array.isArray(resp.items) ? resp.items : [];
            if (!items.length) {
                host.innerHTML = '<p>' + t('groupHistoryEmpty') + '</p>';
                return;
            }
            host.innerHTML = '<h4>' + escapeHtml(t('groupHistory')) + '</h4><div class="groups-history-table-wrap"><table class="groups-history-table"><thead><tr><th>' + escapeHtml(t('groupHistoryTime')) + '</th><th>' + escapeHtml(t('groupHistoryUser')) + '</th><th>' + escapeHtml(t('groupHistoryAction')) + '</th><th>' + escapeHtml(t('groupHistoryFile')) + '</th></tr></thead><tbody>' +
                items.map((it) => '<tr><td>' + escapeHtml(formatTs(it.created_at)) + '</td><td>' + escapeHtml(it.user_email) + '</td><td><span class="groups-history-action ' + historyActionClass(it.action) + '">' + escapeHtml(it.action) + '</span></td><td>' + formatHistoryFilePath(it.file_path) + '</td></tr>').join('') +
                '</tbody></table></div>';
            highlightHistoryPaths(host);
        } catch (e) {
            host.innerHTML = '<p class="error-text">' + t('errorPrefix') + e.message + '</p>';
        }
    }
    async function pickGroupDeleteTarget(current) {
        if (typeof window.showDestinationFolderDialog !== 'function') return null;
        const picked = await window.showDestinationFolderDialog({
            title: t('selectTargetFolder'),
            confirmLabel: t('select'),
            initialRoot: current?.root || 'main',
            initialPath: current?.path || '',
            excludeGroups: true
        });
        if (!picked || !picked.confirmed) return null;
        return { root: picked.root || 'main', path: picked.path || '' };
    }
    function updateDeleteTargetDisplay(root, path) {
        const el = document.getElementById('group-delete-target-display');
        if (!el) return;
        el.textContent = formatDeleteTargetLabel(root, path);
    }
    function showGroupDeleteModal() {
        return new Promise((resolve) => {
            const modal = document.getElementById('modal-groups-delete');
            if (!modal) {
                resolve({ confirmed: false });
                return;
            }
            const moveCb = document.getElementById('group-delete-move-files');
            const moveFields = document.getElementById('group-delete-move-fields');
            const pickBtn = document.getElementById('btn-group-delete-pick-folder');
            let moveTarget = null;
            if (moveCb) moveCb.checked = false;
            if (moveFields) moveFields.classList.add('hidden');
            updateDeleteTargetDisplay('main', '');
            modal.classList.remove('hidden');
            const close = (result) => {
                modal.classList.add('hidden');
                resolve(result);
            };
            const onPickFolder = async () => {
                const picked = await pickGroupDeleteTarget(moveTarget || { root: 'main', path: '' });
                if (!picked) return;
                moveTarget = picked;
                updateDeleteTargetDisplay(picked.root, picked.path);
            };
            if (moveCb && moveFields) {
                moveCb.onchange = () => {
                    moveFields.classList.toggle('hidden', !moveCb.checked);
                    if (moveCb.checked && !moveTarget) onPickFolder();
                };
            }
            if (pickBtn) pickBtn.onclick = onPickFolder;
            const onCancel = () => close({ confirmed: false });
            document.getElementById('btn-groups-delete-cancel')?.addEventListener('click', onCancel, { once: true });
            document.getElementById('btn-groups-delete-close')?.addEventListener('click', onCancel, { once: true });
            document.getElementById('btn-groups-delete-confirm')?.addEventListener('click', async () => {
                if (moveCb?.checked && !moveTarget) {
                    const picked = await pickGroupDeleteTarget({ root: 'main', path: '' });
                    if (!picked) return;
                    moveTarget = picked;
                    updateDeleteTargetDisplay(picked.root, picked.path);
                }
                close({
                    confirmed: true,
                    moveFiles: !!moveCb?.checked,
                    moveTargetRoot: moveTarget ? moveTarget.root : '',
                    moveTargetPath: moveTarget ? moveTarget.path : ''
                });
            }, { once: true });
            const backdrop = (e) => {
                if (e.target === modal) {
                    modal.removeEventListener('click', backdrop);
                    onCancel();
                }
            };
            modal.addEventListener('click', backdrop);
        });
    }
    async function confirmDeleteGroup(gid) {
        const group = getGroupById(gid);
        if (!group) return;
        const choice = await showGroupDeleteModal();
        if (!choice.confirmed) return;
        let moveTargetRoot = '';
        let moveTargetPath = '';
        if (choice.moveFiles) {
            moveTargetRoot = choice.moveTargetRoot || 'main';
            moveTargetPath = choice.moveTargetPath || '';
        }
        try {
            await api('groups_delete', { group_id: gid, move_target_root: moveTargetRoot, move_target_path: moveTargetPath });
            state.groups = state.groups.filter((g) => g.id !== gid);
            if (state.activeGroupId === gid) {
                state.activeGroupId = null;
                if (typeof switchCloudRoot === 'function') await switchCloudRoot('main');
            }
            closeManageModal();
            await loadGroups();
            if (window.cloudGroupChat && typeof window.cloudGroupChat.onGroupsLoaded === 'function') window.cloudGroupChat.onGroupsLoaded();
            if (typeof flashStatus === 'function') flashStatus(t('groupDeleted'));
        } catch (e) {
            if (typeof flashStatus === 'function') flashStatus(t('errorPrefix') + e.message);
        }
    }
    async function createGroup() {
        const name = document.getElementById('group-create-name')?.value?.trim();
        if (!name) return;
        try {
            const resp = await api('groups_create', { name });
            if (resp.group) {
                state.groups.push(resp.group);
                registerGroupRoot(resp.group.root);
                document.getElementById('group-create-name').value = '';
                updateToolbarButton();
                closeManageModal();
                await switchToGroup(resp.group.id);
                if (window.cloudGroupChat && typeof window.cloudGroupChat.onGroupsLoaded === 'function') window.cloudGroupChat.onGroupsLoaded();
                if (typeof flashStatus === 'function') flashStatus(t('groupCreated'));
            }
        } catch (e) {
            if (typeof flashStatus === 'function') flashStatus(t('errorPrefix') + e.message);
        }
    }
    function logGroupFileOpen(filePath) {
        const root = typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : '';
        const gid = groupIdFromRoot(root);
        if (!gid || !filePath) return;
        api('groups_log_activity', { group_id: gid, file_path: filePath, activity: 'open' }).catch(() => {});
    }
    function init() {
        document.getElementById('btn-root-groups')?.addEventListener('click', async () => {
            if (!state.groups.length) return;
            const root = typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main';
            if (isGroupRoot(root)) return;
            const gid = state.activeGroupId || state.groups[0].id;
            await switchToGroup(gid);
        });
        const groupsBtn = document.getElementById('btn-root-groups');
        if (groupsBtn && typeof getTreeDragPayload === 'function') {
            let dragTimer = null;
            const clearGroupDragState = () => {
                if (dragTimer) { clearTimeout(dragTimer); dragTimer = null; }
                groupsBtn.classList.remove('tree-drop-target');
            };
            groupsBtn.addEventListener('dragover', (event) => {
                const paths = getTreeDragPayload(event);
                const nextRoot = getToolbarGroupRoot();
                if (!paths.length || !nextRoot) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                groupsBtn.classList.add('tree-drop-target');
                if (nextRoot !== getCurrentCloudRoot() && !dragTimer) {
                    dragTimer = setTimeout(async () => {
                        dragTimer = null;
                        if (typeof switchCloudRoot === 'function') await switchCloudRoot(nextRoot, { silent: true, keepSelection: true });
                    }, 550);
                }
            });
            groupsBtn.addEventListener('dragleave', (event) => {
                if (groupsBtn.contains(event.relatedTarget)) return;
                clearGroupDragState();
            });
            groupsBtn.addEventListener('drop', async (event) => {
                const paths = getTreeDragPayload(event);
                const targetRoot = getToolbarGroupRoot();
                if (!paths.length || !targetRoot) return;
                event.preventDefault();
                event.stopPropagation();
                clearGroupDragState();
                if (targetRoot !== getCurrentCloudRoot() && typeof switchCloudRoot === 'function') {
                    await switchCloudRoot(targetRoot, { silent: true, keepSelection: true });
                }
                if (typeof moveTreeDragPaths === 'function') {
                    await moveTreeDragPaths(paths, '', targetRoot, window.currentTreeDragSourceRoot || getCurrentCloudRoot());
                }
            });
            document.addEventListener('dragend', clearGroupDragState);
        }
        document.getElementById('btn-groups-manage')?.addEventListener('click', async () => {
            if (typeof window.closeSettingsMenu === 'function') window.closeSettingsMenu();
            await loadGroups();
            openManageModal();
        });
        document.getElementById('btn-group-create')?.addEventListener('click', createGroup);
        document.getElementById('btn-groups-manage-close')?.addEventListener('click', closeManageModal);
        document.getElementById('modal-groups-manage')?.addEventListener('click', (e) => {
            if (e.target.id === 'modal-groups-manage') closeManageModal();
        });
        const saved = localStorage.getItem('cloudRoot') || '';
        if (isGroupRoot(saved)) state.activeGroupId = groupIdFromRoot(saved);
        loadGroups().then(() => {
            if (state.activeGroupId && getGroupById(state.activeGroupId)) {
                registerGroupRoot(groupRootKey(state.activeGroupId));
                if (isGroupRoot(saved) && typeof switchCloudRoot === 'function') {
                    switchCloudRoot(groupRootKey(state.activeGroupId), { silent: true });
                }
            } else if (isGroupRoot(saved)) {
                setCurrentCloudRoot('main');
            }
        });
        document.getElementById('btn-settings')?.addEventListener('click', () => { loadGroups(); });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) loadGroups();
        });
        window.setInterval(() => { if (!document.hidden) loadGroups(); }, 60000);
    }
    window.cloudGroupsRefreshSection = updateSectionTitle;
    window.cloudGroups = {
        loadGroups,
        switchToGroup,
        openManageModal,
        logGroupFileOpen,
        isGroupRoot,
        groupIdFromRoot,
        getActiveGroup,
        updateSectionTitle,
        updateToolbarButton,
        renderGroupSwitcher
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
