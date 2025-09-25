(function () {
    'use strict';

    var data = window.FPAdminNotices || {};
    var i18n = data.i18n || {};
    var severityLabels = {
        error: i18n.filterError || 'Errori',
        warning: i18n.filterWarning || 'Avvisi',
        success: i18n.filterSuccess || 'Successi',
        info: i18n.filterInfo || 'Informazioni'
    };
    var severityIcons = {
        error: 'dashicons-warning',
        warning: 'dashicons-flag',
        success: 'dashicons-yes',
        info: 'dashicons-info-outline'
    };
    var stateIcons = {
        active: 'dashicons-visibility',
        archived: 'dashicons-archive'
    };
    var settings = data.settings || {};
    var dismissedInitial = Array.isArray(data.dismissed) ? data.dismissed : [];
    var state = {
        notices: [],
        filter: 'all',
        search: '',
        showDismissed: false,
        dismissed: new Set(dismissedInitial),
        includeUpdateNag: !!settings.includeUpdateNag,
        autoOpenCritical: !!settings.autoOpenCritical
    };

    var restConfig = data.rest || {};
    var lastFocusedElement = null;
    var focusTrapHandler = null;
    var previousActiveCount = 0;
    var announcementHideTimeout = null;
    var panelEl = null;
    var panelContentEl = null;
    var panelBodyEl = null;
    var toggleEl = null;
    var bulkActionEls = {
        markRead: null,
        markUnread: null,
        toggleArchived: null
    };

    function emit(eventName, detail) {
        if (typeof window.CustomEvent !== 'function') {
            return;
        }
        document.dispatchEvent(new CustomEvent(eventName, {
            detail: detail || {}
        }));
    }

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        }
    }

    function qs(selector, context) {
        return (context || document).querySelector(selector);
    }

    function qsa(selector, context) {
        return Array.prototype.slice.call((context || document).querySelectorAll(selector));
    }

    function hashString(str) {
        var hash = 0;
        if (!str) {
            return hash;
        }
        for (var i = 0; i < str.length; i++) {
            hash = (hash << 5) - hash + str.charCodeAt(i);
            hash |= 0; // Convert to 32bit integer
        }
        return hash;
    }

    function ensureNoticeId(node) {
        if (!node) {
            return '';
        }
        if (node.dataset.fpAdminNoticeId) {
            return node.dataset.fpAdminNoticeId;
        }
        var base = (node.id || '') + '|' + (node.className || '') + '|' + (node.textContent || '');
        var hash = hashString(base.trim());
        var id = 'fp-notice-' + Math.abs(hash);
        node.dataset.fpAdminNoticeId = id;
        return id;
    }

    function getSeverity(node) {
        if (!node) {
            return 'info';
        }
        if (node.classList.contains('notice-error') || node.classList.contains('error')) {
            return 'error';
        }
        if (node.classList.contains('notice-warning') || node.classList.contains('update-nag') || node.classList.contains('warning')) {
            return 'warning';
        }
        if (node.classList.contains('notice-success') || node.classList.contains('updated') || node.classList.contains('success')) {
            return 'success';
        }
        return 'info';
    }

    function shouldIncludeNotice(node) {
        if (!node) {
            return false;
        }
        if (!state.includeUpdateNag && node.classList.contains('update-nag')) {
            return false;
        }
        return true;
    }

    function collectNoticeElements() {
        var selectors = [
            '#wpbody-content .notice',
            '#wpbody-content .error',
            '#wpbody-content .updated'
        ];

        if (state.includeUpdateNag) {
            selectors.push('#wpbody-content .update-nag');
        }

        var elements = [];

        selectors.forEach(function (selector) {
            qsa(selector).forEach(function (node) {
                if (!node.closest('#fp-admin-notices-panel') && elements.indexOf(node) === -1) {
                    if (shouldIncludeNotice(node)) {
                        elements.push(node);
                    }
                }
            });
        });

        return elements;
    }

    function stripDismissButtons(node) {
        qsa('.notice-dismiss, .dismiss-notice', node).forEach(function (btn) {
            btn.remove();
        });
        return node;
    }

    function cloneNotice(node) {
        var clone = node.cloneNode(true);
        clone.classList.add('fp-admin-notices-panel__item');
        clone.classList.remove('is-dismissible');
        clone.classList.remove('fp-admin-notices-hidden');
        clone.classList.remove('fp-admin-notices-highlight');
        clone.removeAttribute('style');
        stripDismissButtons(clone);
        return clone;
    }

    function hideOriginalNotice(node) {
        if (!node) {
            return;
        }
        node.classList.add('fp-admin-notices-hidden');
        node.setAttribute('data-fp-admin-notices', 'hidden');
    }

    function showOriginalNotice(notice) {
        if (!notice || !notice.node) {
            return;
        }
        var node = notice.node;
        node.classList.remove('fp-admin-notices-hidden');
        node.removeAttribute('data-fp-admin-notices');
        node.classList.add('fp-admin-notices-highlight');
        var hadTabindex = node.hasAttribute('tabindex');
        var previousTabindex = hadTabindex ? node.getAttribute('tabindex') : null;

        if (!hadTabindex) {
            node.setAttribute('tabindex', '-1');
        }

        node.scrollIntoView({ behavior: 'smooth', block: 'center' });
        try {
            node.focus({ preventScroll: true });
        } catch (err) {
            // Some elements cannot receive focus; ignore.
        }

        window.setTimeout(function () {
            node.classList.remove('fp-admin-notices-highlight');
            if (!hadTabindex) {
                node.removeAttribute('tabindex');
            } else if (previousTabindex !== null) {
                node.setAttribute('tabindex', previousTabindex);
            }
        }, 2000);
    }

    function formatAriaLabel(baseLabel, count) {
        if (!baseLabel) {
            return '';
        }

        return count > 0 ? baseLabel + ' (' + count + ')' : baseLabel;
    }

    function getActiveNotices() {
        return state.notices.filter(function (notice) {
            return !notice.dismissed;
        });
    }

    function matchesFilters(notice) {
        if (!notice) {
            return false;
        }
        if (state.filter !== 'all' && notice.severity !== state.filter) {
            return false;
        }
        if (state.search) {
            return notice.text.toLowerCase().indexOf(state.search) !== -1;
        }
        return true;
    }

    function getFilteredNotices(options) {
        options = options || {};
        var includeDismissed = !!options.includeDismissed;

        return state.notices.filter(function (notice) {
            if (!matchesFilters(notice)) {
                return false;
            }
            if (!includeDismissed && notice.dismissed) {
                return false;
            }
            return true;
        });
    }

    function ensureBulkActionElements() {
        if (!panelEl) {
            return;
        }
        if (!bulkActionEls.markRead) {
            bulkActionEls.markRead = qs('.fp-admin-notices-panel__bulk-action--read', panelEl);
        }
        if (!bulkActionEls.markUnread) {
            bulkActionEls.markUnread = qs('.fp-admin-notices-panel__bulk-action--unread', panelEl);
        }
        if (!bulkActionEls.toggleArchived) {
            bulkActionEls.toggleArchived = qs('.fp-admin-notices-panel__bulk-action--toggle-archived', panelEl);
        }
    }

    function updateBulkActionsState() {
        ensureBulkActionElements();

        var available = getFilteredNotices({ includeDismissed: true });
        var hasUnread = available.some(function (notice) {
            return !notice.dismissed;
        });
        var hasArchived = available.some(function (notice) {
            return notice.dismissed;
        });

        if (bulkActionEls.markRead) {
            if (i18n.markAllRead) {
                var markReadLabel = qs('.fp-admin-notices-panel__bulk-action-label', bulkActionEls.markRead);
                if (markReadLabel) {
                    markReadLabel.textContent = i18n.markAllRead;
                } else {
                    bulkActionEls.markRead.textContent = i18n.markAllRead;
                }
                bulkActionEls.markRead.setAttribute('title', i18n.markAllRead);
            }
            bulkActionEls.markRead.disabled = !hasUnread;
        }

        if (bulkActionEls.markUnread) {
            if (i18n.markAllUnread) {
                var markUnreadLabel = qs('.fp-admin-notices-panel__bulk-action-label', bulkActionEls.markUnread);
                if (markUnreadLabel) {
                    markUnreadLabel.textContent = i18n.markAllUnread;
                } else {
                    bulkActionEls.markUnread.textContent = i18n.markAllUnread;
                }
                bulkActionEls.markUnread.setAttribute('title', i18n.markAllUnread);
            }
            bulkActionEls.markUnread.disabled = !hasArchived;
        }

        if (bulkActionEls.toggleArchived) {
            bulkActionEls.toggleArchived.setAttribute('aria-pressed', state.showDismissed ? 'true' : 'false');
            var label = state.showDismissed
                ? (i18n.hideDismissed || 'Nascondi archiviate')
                : (i18n.showDismissed || 'Mostra archiviate');
            var toggleLabel = qs('.fp-admin-notices-panel__bulk-action-label', bulkActionEls.toggleArchived);
            if (toggleLabel) {
                toggleLabel.textContent = label;
            } else {
                bulkActionEls.toggleArchived.textContent = label;
            }
            bulkActionEls.toggleArchived.setAttribute('title', label);
        }
    }

    function updateCount() {
        if (!toggleEl) {
            return;
        }

        var activeNotices = getActiveNotices();
        var count = activeNotices.length;
        var badge = qs('.fp-admin-notices-count', toggleEl);
        var label = i18n.title || 'Notifiche';
        var anchor = qs('a', toggleEl);

        if (badge) {
            badge.textContent = count > 0 ? String(count) : '';
            badge.classList.toggle('has-items', count > 0);
        }

        toggleEl.dataset.fpAdminNoticesCount = String(count);

        if (anchor) {
            var isActive = anchor.classList.contains('is-active');
            var baseLabel = isActive ? (i18n.closePanel || label) : (i18n.openPanel || label);
            anchor.setAttribute('aria-label', formatAriaLabel(baseLabel, count));
            anchor.setAttribute('aria-expanded', isActive ? 'true' : 'false');
            if (i18n.toggleShortcut) {
                anchor.setAttribute('title', i18n.toggleShortcut);
            }
        }

        if (count > previousActiveCount) {
            announce(i18n.newNoticeAnnouncement || '');
        }

        previousActiveCount = count;
    }

    function announce(message) {
        var announcer = panelEl ? qs('.fp-admin-notices-panel__announcement', panelEl) : null;
        var visual = panelEl ? qs('.fp-admin-notices-panel__announcement-visual', panelEl) : null;

        if (!message || !panelEl) {
            if (announcementHideTimeout) {
                window.clearTimeout(announcementHideTimeout);
                announcementHideTimeout = null;
            }
            if (visual) {
                visual.classList.remove('is-visible');
                visual.textContent = '';
            }
            if (announcer) {
                announcer.textContent = '';
            }
            return;
        }

        if (announcer) {
            announcer.textContent = '';
            window.requestAnimationFrame(function () {
                announcer.textContent = message;
            });
        }

        if (visual) {
            visual.dataset.title = i18n.announcementVisualTitle || '';
            visual.textContent = message;
            visual.classList.add('is-visible');
            if (announcementHideTimeout) {
                window.clearTimeout(announcementHideTimeout);
            }
            announcementHideTimeout = window.setTimeout(function () {
                visual.classList.remove('is-visible');
                visual.textContent = '';
                announcementHideTimeout = null;
            }, 4000);
        }
    }

    function createMeta(notice) {
        var meta = document.createElement('div');
        meta.className = 'fp-admin-notices-panel__meta';

        var stateBadge = document.createElement('span');
        stateBadge.className = 'fp-admin-notices-panel__badge fp-admin-notices-panel__badge--state';
        var stateIcon = document.createElement('span');
        stateIcon.className = 'fp-admin-notices-panel__badge-icon dashicons ' + (notice.dismissed ? stateIcons.archived : stateIcons.active);
        stateIcon.setAttribute('aria-hidden', 'true');
        stateBadge.appendChild(stateIcon);
        var stateText = document.createElement('span');
        stateText.className = 'fp-admin-notices-panel__badge-text';
        stateText.textContent = notice.dismissed ? (i18n.badgeArchived || 'Archiviata') : (i18n.badgeActive || 'Attiva');
        stateBadge.appendChild(stateText);
        meta.appendChild(stateBadge);

        var severityBadge = document.createElement('span');
        severityBadge.className = 'fp-admin-notices-panel__badge fp-admin-notices-panel__badge--severity fp-admin-notices-panel__badge--' + notice.severity;
        var severityIcon = document.createElement('span');
        var severityIconClass = severityIcons[notice.severity] || severityIcons.info;
        severityIcon.className = 'fp-admin-notices-panel__badge-icon dashicons ' + severityIconClass;
        severityIcon.setAttribute('aria-hidden', 'true');
        severityBadge.appendChild(severityIcon);
        var severityText = document.createElement('span');
        severityText.className = 'fp-admin-notices-panel__badge-text';
        severityText.textContent = severityLabels[notice.severity] || severityLabels.info;
        severityBadge.appendChild(severityText);
        meta.appendChild(severityBadge);

        return meta;
    }

    function createActions(notice) {
        var actions = document.createElement('div');
        actions.className = 'fp-admin-notices-panel__actions';

        var toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'fp-admin-notices-panel__action button-link fp-admin-notices-panel__action--toggle';
        var toggleIcon = document.createElement('span');
        toggleIcon.className = 'fp-admin-notices-panel__action-icon dashicons ' + (notice.dismissed ? 'dashicons-undo' : 'dashicons-yes');
        toggleIcon.setAttribute('aria-hidden', 'true');
        toggleBtn.appendChild(toggleIcon);
        var toggleText = document.createElement('span');
        toggleText.className = 'fp-admin-notices-panel__action-text';
        toggleText.textContent = notice.dismissed ? (i18n.markUnread || 'Segna come non letta') : (i18n.markRead || 'Segna come letta');
        toggleBtn.appendChild(toggleText);
        toggleBtn.addEventListener('click', function (event) {
            event.preventDefault();
            setDismissed(notice, !notice.dismissed, true);
        });
        actions.appendChild(toggleBtn);

        var showBtn = document.createElement('button');
        showBtn.type = 'button';
        showBtn.className = 'fp-admin-notices-panel__action button-link fp-admin-notices-panel__action--show';
        var showIcon = document.createElement('span');
        showIcon.className = 'fp-admin-notices-panel__action-icon dashicons dashicons-external';
        showIcon.setAttribute('aria-hidden', 'true');
        showBtn.appendChild(showIcon);
        var showText = document.createElement('span');
        showText.className = 'fp-admin-notices-panel__action-text';
        showText.textContent = i18n.showNotice || 'Mostra nella pagina';
        showBtn.appendChild(showText);
        showBtn.addEventListener('click', function (event) {
            event.preventDefault();
            showOriginalNotice(notice);
            closePanel();
        });
        actions.appendChild(showBtn);

        return actions;
    }

    function renderList() {
        if (!panelEl) {
            return;
        }
        var list = qs('.fp-admin-notices-panel__list', panelEl);
        if (!list) {
            return;
        }

        list.innerHTML = '';

        var matches = getFilteredNotices({ includeDismissed: true });
        var filtered = state.showDismissed ? matches : matches.filter(function (notice) {
            return !notice.dismissed;
        });

        if (!filtered.length) {
            var emptyWrapper = document.createElement('div');
            emptyWrapper.className = 'fp-admin-notices-panel__empty';

            var emptyIcon = document.createElement('span');
            emptyIcon.className = 'fp-admin-notices-panel__empty-icon dashicons dashicons-smiley';
            emptyIcon.setAttribute('aria-hidden', 'true');
            emptyWrapper.appendChild(emptyIcon);

            var emptyTitle = document.createElement('p');
            emptyTitle.className = 'fp-admin-notices-panel__empty-title';
            emptyTitle.textContent = i18n.emptyTitle || i18n.noNotices || '';
            emptyWrapper.appendChild(emptyTitle);

            var emptySubtitle = document.createElement('p');
            emptySubtitle.className = 'fp-admin-notices-panel__empty-subtitle';
            if (state.notices.length === 0) {
                emptySubtitle.textContent = i18n.noNotices || '';
            } else if (matches.length === 0) {
                emptySubtitle.textContent = i18n.noMatches || i18n.noNotices || '';
            } else {
                emptySubtitle.textContent = i18n.noUnreadMatches || i18n.noNotices || '';
            }
            emptyWrapper.appendChild(emptySubtitle);

            if (settings.emptyStateHelpUrl && i18n.emptyAction) {
                var emptyAction = document.createElement('a');
                emptyAction.className = 'fp-admin-notices-panel__empty-action';
                emptyAction.href = settings.emptyStateHelpUrl;
                emptyAction.textContent = i18n.emptyAction;
                emptyAction.target = '_blank';
                emptyAction.rel = 'noopener noreferrer';
                emptyWrapper.appendChild(emptyAction);
            }

            list.appendChild(emptyWrapper);
            emit('fpAdminNotices:listUpdated', {
                notices: [],
                filter: state.filter,
                search: state.search,
                showDismissed: state.showDismissed
            });
            updateBulkActionsState();
            return;
        }

        var fragment = document.createDocumentFragment();

        filtered.forEach(function (notice) {
            var item = cloneNotice(notice.node);
            item.dataset.fpAdminNoticeId = notice.id;
            item.classList.add('fp-admin-notices-panel__item');
            item.classList.add('fp-admin-notices-panel__item--' + notice.severity);
            if (notice.dismissed) {
                item.classList.add('is-dismissed');
                item.classList.add('is-archived');
            }
            if (!notice.dismissed) {
                item.classList.add('is-active');
            }
            if (notice.isNew) {
                item.classList.add('is-new');
            }
            if (notice.justToggled) {
                item.classList.add('is-toggling');
            }
            item.dataset.severity = notice.severity;
            item.dataset.state = notice.dismissed ? 'archived' : 'active';
            item.insertBefore(createMeta(notice), item.firstChild);
            item.appendChild(createActions(notice));
            fragment.appendChild(item);

            notice.isNew = false;
            notice.justToggled = false;
        });

        list.appendChild(fragment);

        emit('fpAdminNotices:listUpdated', {
            notices: filtered.map(function (notice) {
                return {
                    id: notice.id,
                    severity: notice.severity,
                    dismissed: notice.dismissed,
                    text: notice.text
                };
            }),
            filter: state.filter,
            search: state.search,
            showDismissed: state.showDismissed
        });

        updateBulkActionsState();
        updatePanelScrollState();
    }

    function persistNoticeState(noticeIds, dismissed) {
        if (!restConfig || !restConfig.url || typeof window.fetch !== 'function') {
            return Promise.resolve();
        }

        if (Array.isArray(noticeIds) && !noticeIds.length) {
            return Promise.resolve();
        }

        if (!Array.isArray(noticeIds) && !noticeIds) {
            return Promise.resolve();
        }

        var payload = {
            dismissed: dismissed
        };

        if (Array.isArray(noticeIds)) {
            payload.notice_ids = noticeIds;
        } else {
            payload.notice_id = noticeIds;
        }

        return window.fetch(restConfig.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': restConfig.nonce || ''
            },
            body: JSON.stringify(payload)
        }).catch(function (error) {
            // eslint-disable-next-line no-console
            console.error('FP Admin Notices: unable to persist notice state', error);
        });
    }

    function setDismissed(notice, dismissed, persist) {
        if (!notice) {
            return;
        }

        notice.dismissed = dismissed;
        if (dismissed) {
            state.dismissed.add(notice.id);
        } else {
            state.dismissed.delete(notice.id);
            hideOriginalNotice(notice.node);
        }

        notice.justToggled = true;

        updateCount();
        renderList();

        if (persist) {
            persistNoticeState(notice.id, dismissed);
        }
    }

    function syncNotices() {
        var nodes = collectNoticeElements();
        var seen = {};
        var newCritical = false;

        nodes.forEach(function (node, index) {
            var id = ensureNoticeId(node);
            seen[id] = true;
            var severity = getSeverity(node);
            var text = (node.textContent || '').replace(/\s+/g, ' ').trim();
            var existing = state.notices.find(function (item) {
                return item.id === id;
            });
            var isDismissed = state.dismissed.has(id);
            hideOriginalNotice(node);

            if (!existing) {
                existing = {
                    id: id,
                    node: node,
                    severity: severity,
                    text: text,
                    dismissed: isDismissed,
                    index: index,
                    isNew: true,
                    justToggled: false
                };
                state.notices.push(existing);
                if (!isDismissed && severity === 'error') {
                    newCritical = true;
                }
            } else {
                existing.node = node;
                existing.severity = severity;
                existing.text = text;
                existing.dismissed = isDismissed;
                existing.index = index;
                existing.isNew = existing.isNew || false;
                existing.justToggled = existing.justToggled || false;
            }
        });

        state.notices = state.notices.filter(function (notice) {
            return seen[notice.id];
        });

        state.notices.sort(function (a, b) {
            return a.index - b.index;
        });

        return {
            newCritical: newCritical
        };
    }

    function openPanel() {
        if (!panelEl) {
            return;
        }

        panelEl.classList.add('is-open');
        panelEl.setAttribute('aria-hidden', 'false');

        if (panelBodyEl) {
            panelBodyEl.scrollTop = 0;
        }

        if (toggleEl) {
            var anchor = qs('a', toggleEl);
            if (anchor) {
                anchor.classList.add('is-active');
                anchor.setAttribute('aria-expanded', 'true');
                var count = Number(toggleEl.dataset.fpAdminNoticesCount || '0');
                var label = i18n.closePanel || i18n.title || 'Notifiche';
                anchor.setAttribute('aria-label', formatAriaLabel(label, count));
            }
        }

        lastFocusedElement = document.activeElement;
        var focusTarget = qs('.fp-admin-notices-panel__body', panelEl);
        if (focusTarget) {
            focusTarget.focus({ preventScroll: true });
        }

        enableFocusTrap();
        updatePanelScrollState();
        emit('fpAdminNotices:open', {
            count: getActiveNotices().length
        });
    }

    function closePanel() {
        if (!panelEl) {
            return;
        }

        panelEl.classList.remove('is-open');
        panelEl.setAttribute('aria-hidden', 'true');

        var anchor = toggleEl ? qs('a', toggleEl) : null;
        if (anchor) {
            anchor.classList.remove('is-active');
            anchor.setAttribute('aria-expanded', 'false');
            var count = Number(toggleEl ? toggleEl.dataset.fpAdminNoticesCount || '0' : '0');
            var label = i18n.openPanel || i18n.title || 'Notifiche';
            anchor.setAttribute('aria-label', formatAriaLabel(label, count));
        }

        var focusTarget = lastFocusedElement && document.body.contains(lastFocusedElement)
            ? lastFocusedElement
            : anchor;

        if (focusTarget) {
            try {
                focusTarget.focus({ preventScroll: true });
            } catch (err) {
                // Ignore focus issues
            }
        }

        lastFocusedElement = null;
        disableFocusTrap();
        emit('fpAdminNotices:close', {
            count: getActiveNotices().length
        });
    }

    function enableFocusTrap() {
        if (!panelEl) {
            return;
        }
        disableFocusTrap();

        focusTrapHandler = function (event) {
            if (event.key !== 'Tab') {
                return;
            }
            var focusable = qsa('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])', panelEl)
                .filter(function (el) {
                    return el.offsetParent !== null;
                });
            if (!focusable.length) {
                event.preventDefault();
                return;
            }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        panelEl.addEventListener('keydown', focusTrapHandler);
    }

    function disableFocusTrap() {
        if (!panelEl || !focusTrapHandler) {
            return;
        }
        panelEl.removeEventListener('keydown', focusTrapHandler);
        focusTrapHandler = null;
    }

    function togglePanel(event) {
        if (event) {
            event.preventDefault();
        }
        if (!panelEl) {
            return;
        }
        var isOpen = panelEl.classList.contains('is-open');
        if (isOpen) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function setupFilterControls() {
        var buttons = qsa('.fp-admin-notices-filter', panelEl);
        buttons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var filter = button.getAttribute('data-filter') || 'all';
                state.filter = filter;
                buttons.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn === button);
                });
                renderList();
            });
        });
    }

    function bulkSetDismissed(notices, dismissed) {
        if (!Array.isArray(notices) || !notices.length) {
            return;
        }

        var changed = [];

        notices.forEach(function (notice) {
            if (!notice || notice.dismissed === dismissed) {
                return;
            }

            notice.dismissed = dismissed;
            if (dismissed) {
                state.dismissed.add(notice.id);
            } else {
                state.dismissed.delete(notice.id);
                hideOriginalNotice(notice.node);
            }
            notice.justToggled = true;
            changed.push(notice.id);
        });

        if (!changed.length) {
            return;
        }

        updateCount();
        renderList();
        persistNoticeState(changed, dismissed);
    }

    function setupBulkActions() {
        ensureBulkActionElements();

        if (bulkActionEls.markRead) {
            bulkActionEls.markRead.addEventListener('click', function (event) {
                event.preventDefault();
                bulkSetDismissed(getFilteredNotices({ includeDismissed: true }).filter(function (notice) {
                    return !notice.dismissed;
                }), true);
            });
        }

        if (bulkActionEls.markUnread) {
            bulkActionEls.markUnread.addEventListener('click', function (event) {
                event.preventDefault();
                bulkSetDismissed(getFilteredNotices({ includeDismissed: true }).filter(function (notice) {
                    return notice.dismissed;
                }), false);
            });
        }

        if (bulkActionEls.toggleArchived) {
            bulkActionEls.toggleArchived.addEventListener('click', function (event) {
                event.preventDefault();
                state.showDismissed = !state.showDismissed;
                renderList();
            });
        }

        updateBulkActionsState();
    }

    function setupSearchControl() {
        var searchInput = qs('#fp-admin-notices-search', panelEl);
        if (!searchInput) {
            return;
        }
        searchInput.addEventListener('input', function () {
            state.search = searchInput.value.trim().toLowerCase();
            renderList();
        });
    }

    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function (event) {
            if (event.altKey && event.shiftKey && (event.key === 'N' || event.key === 'n')) {
                event.preventDefault();
                togglePanel();
            }
        });
    }

    function setupMutationObserver() {
        var target = qs('#wpbody-content');
        if (!target) {
            return;
        }

        var scheduled = false;
        var observer = new MutationObserver(function () {
            if (!scheduled) {
                scheduled = true;
                window.requestAnimationFrame(function () {
                    var result = syncNotices();
                    renderList();
                    updateCount();
                    if (result.newCritical && state.autoOpenCritical && panelEl && !panelEl.classList.contains('is-open')) {
                        openPanel();
                    }
                    scheduled = false;
                });
            }
        });

        observer.observe(target, {
            childList: true,
            subtree: true
        });
    }

    function updatePanelScrollState() {
        if (!panelContentEl || !panelBodyEl) {
            return;
        }
        window.requestAnimationFrame(function () {
            var hasShadow = panelBodyEl.scrollTop > 6;
            panelContentEl.classList.toggle('is-scrolled', hasShadow);
        });
    }

    function setupScrollHandling() {
        if (!panelBodyEl) {
            return;
        }
        panelBodyEl.addEventListener('scroll', updatePanelScrollState);
        updatePanelScrollState();
    }

    ready(function () {
        panelEl = document.getElementById('fp-admin-notices-panel');
        toggleEl = document.getElementById('wp-admin-bar-fp-admin-notices-toggle');

        if (!panelEl || !toggleEl) {
            return;
        }

        panelContentEl = qs('.fp-admin-notices-panel__content', panelEl);
        panelBodyEl = qs('.fp-admin-notices-panel__body', panelEl);

        var toggleAnchor = qs('a', toggleEl);
        if (toggleAnchor) {
            toggleAnchor.setAttribute('role', 'button');
            toggleAnchor.setAttribute('aria-expanded', 'false');
            toggleAnchor.setAttribute('aria-controls', panelEl.id);
            toggleAnchor.setAttribute('aria-label', i18n.openPanel || i18n.title || 'Notifiche');
            toggleAnchor.addEventListener('click', togglePanel);
            toggleAnchor.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    togglePanel(event);
                }
            });
        }

        var overlay = qs('.fp-admin-notices-panel__overlay', panelEl);
        if (overlay) {
            overlay.addEventListener('click', function (event) {
                event.preventDefault();
                closePanel();
            });
        }

        var closeButton = qs('.fp-admin-notices-panel__close', panelEl);
        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                closePanel();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && panelEl.classList.contains('is-open')) {
                closePanel();
            }
        });

        syncNotices();
        renderList();
        previousActiveCount = getActiveNotices().length;
        updateCount();

        setupFilterControls();
        setupBulkActions();
        setupSearchControl();
        setupMutationObserver();
        setupKeyboardShortcuts();
        setupScrollHandling();
    });
})();
