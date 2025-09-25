(function () {
    'use strict';

    var data = window.FPAdminNotices || {};
    var i18n = data.i18n || {};

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

    function collectNoticeElements() {
        var selectors = [
            '#wpbody-content .notice',
            '#wpbody-content .update-nag',
            '#wpbody-content .error',
            '#wpbody-content .updated'
        ];
        var elements = [];

        selectors.forEach(function (selector) {
            qsa(selector).forEach(function (node) {
                if (!node.closest('#fp-admin-notices-panel') && elements.indexOf(node) === -1) {
                    elements.push(node);
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
        return stripDismissButtons(clone);
    }

    function hideOriginalNotice(node) {
        node.classList.add('fp-admin-notices-hidden');
        node.setAttribute('data-fp-admin-notices', 'hidden');
    }

    function formatAriaLabel(baseLabel, count) {
        if (!baseLabel) {
            return '';
        }

        return count > 0 ? baseLabel + ' (' + count + ')' : baseLabel;
    }

    function updateCount(toggle, count) {
        if (!toggle) {
            return;
        }

        var badge = qs('.fp-admin-notices-count', toggle);
        var label = i18n.title || 'Notifiche';
        var anchor = qs('a', toggle);

        if (badge) {
            badge.textContent = count > 0 ? String(count) : '';
            badge.classList.toggle('has-items', count > 0);
        }

        toggle.dataset.fpAdminNoticesCount = String(count);

        if (anchor) {
            var isActive = anchor.classList.contains('is-active');
            var baseLabel = isActive
                ? (i18n.closePanel || label)
                : (i18n.openPanel || label);
            anchor.setAttribute('aria-label', formatAriaLabel(baseLabel, count));
            anchor.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        }
    }

    function renderNotices(panel, toggle) {
        var list = qs('.fp-admin-notices-panel__list', panel);
        if (!list) {
            return;
        }

        list.innerHTML = '';
        var notices = collectNoticeElements();

        if (notices.length === 0) {
            var emptyMessage = document.createElement('p');
            emptyMessage.className = 'fp-admin-notices-panel__empty';
            emptyMessage.textContent = i18n.noNotices || '';
            list.appendChild(emptyMessage);
            updateCount(toggle, 0);
            return;
        }

        notices.forEach(function (notice) {
            hideOriginalNotice(notice);
            list.appendChild(cloneNotice(notice));
        });

        updateCount(toggle, notices.length);
    }

    var lastFocusedElement = null;

    function openPanel(panel, toggle) {
        if (!panel) {
            return;
        }

        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');

        if (toggle) {
            var anchor = qs('a', toggle);
            if (anchor) {
                anchor.classList.add('is-active');
                anchor.setAttribute('aria-expanded', 'true');
                var count = Number(toggle.dataset.fpAdminNoticesCount || '0');
                var label = i18n.closePanel || i18n.title || 'Notifiche';
                anchor.setAttribute('aria-label', formatAriaLabel(label, count));
            }
        }

        lastFocusedElement = document.activeElement;
        var focusTarget = qs('.fp-admin-notices-panel__body', panel);
        if (focusTarget) {
            focusTarget.focus({ preventScroll: true });
        }
    }

    function closePanel(panel, toggle) {
        if (!panel) {
            return;
        }

        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');

        var anchor = toggle ? qs('a', toggle) : null;
        if (anchor) {
            anchor.classList.remove('is-active');
            anchor.setAttribute('aria-expanded', 'false');
            var count = Number(toggle ? toggle.dataset.fpAdminNoticesCount || '0' : '0');
            var label = i18n.openPanel || i18n.title || 'Notifiche';
            anchor.setAttribute('aria-label', formatAriaLabel(label, count));
        }

        var focusTarget = lastFocusedElement && document.body.contains(lastFocusedElement)
            ? lastFocusedElement
            : anchor;

        if (focusTarget) {
            focusTarget.focus({ preventScroll: true });
        }

        lastFocusedElement = null;
    }

    function setupMutationObserver(panel, toggle) {
        var target = qs('#wpbody-content');
        if (!target) {
            return;
        }

        var scheduled = false;
        var observer = new MutationObserver(function () {
            if (!scheduled) {
                scheduled = true;
                window.requestAnimationFrame(function () {
                    renderNotices(panel, toggle);
                    scheduled = false;
                });
            }
        });

        observer.observe(target, {
            childList: true,
            subtree: true
        });
    }

    ready(function () {
        var panel = document.getElementById('fp-admin-notices-panel');
        var toggle = document.getElementById('wp-admin-bar-fp-admin-notices-toggle');

        if (!panel || !toggle) {
            return;
        }

        renderNotices(panel, toggle);
        setupMutationObserver(panel, toggle);

        var overlay = qs('.fp-admin-notices-panel__overlay', panel);
        var closeButton = qs('.fp-admin-notices-panel__close', panel);
        var toggleAnchor = qs('a', toggle);

        if (toggleAnchor) {
            toggleAnchor.setAttribute('role', 'button');
            toggleAnchor.setAttribute('aria-expanded', 'false');
            toggleAnchor.setAttribute('aria-controls', panel.id);
            toggleAnchor.setAttribute('aria-label', i18n.openPanel || i18n.title || 'Notifiche');
        }

        function togglePanel(event) {
            event.preventDefault();
            var isOpen = panel.classList.contains('is-open');
            if (isOpen) {
                closePanel(panel, toggle);
            } else {
                openPanel(panel, toggle);
            }
        }

        if (toggleAnchor) {
            toggleAnchor.addEventListener('click', togglePanel);
            toggleAnchor.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    togglePanel(event);
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function (event) {
                event.preventDefault();
                closePanel(panel, toggle);
            });
        }

        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                closePanel(panel, toggle);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && panel.classList.contains('is-open')) {
                closePanel(panel, toggle);
            }
        });
    });
})();
