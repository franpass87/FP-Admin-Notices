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

        if (anchor) {
            var ariaLabel = count > 0 ? label + ' (' + count + ')' : label;
            anchor.setAttribute('aria-label', ariaLabel);
            anchor.setAttribute('aria-expanded', anchor.classList.contains('is-active') ? 'true' : 'false');
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

    function openPanel(panel, toggle, previouslyFocused) {
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
            }
        }

        var focusTarget = qs('.fp-admin-notices-panel__body', panel);
        if (focusTarget) {
            focusTarget.focus({ preventScroll: true });
        }

        panel.dataset.fpAdminNoticesLastFocus = previouslyFocused ? previouslyFocused.id || 'custom-focus' : '';
    }

    function closePanel(panel, toggle) {
        if (!panel) {
            return;
        }

        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');

        if (toggle) {
            var anchor = qs('a', toggle);
            if (anchor) {
                anchor.classList.remove('is-active');
                anchor.setAttribute('aria-expanded', 'false');
                anchor.focus({ preventScroll: true });
            }
        }
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

        function togglePanel(event) {
            event.preventDefault();
            var isOpen = panel.classList.contains('is-open');
            if (isOpen) {
                closePanel(panel, toggle);
            } else {
                var activeElement = document.activeElement;
                openPanel(panel, toggle, activeElement);
            }
        }

        if (toggle) {
            toggle.addEventListener('click', togglePanel);
            toggle.addEventListener('keydown', function (event) {
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
