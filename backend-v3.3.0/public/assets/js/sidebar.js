(function () {
    'use strict';

    const storageKey = 'sidebar_collapsed';
    const root = document.documentElement;
    const sidebar = document.getElementById('panel-sidebar');
    const toggle = document.getElementById('sidebar-toggle-btn');
    const toggleIcon = document.getElementById('sidebar-toggle-icon');

    function updateViewportLeftInset() {
        const leftInset = Math.max(0, root.getBoundingClientRect().left);
        root.style.setProperty('--viewport-left-inset', `${leftInset}px`);
    }

    updateViewportLeftInset();

    if (!sidebar || !toggle) return;

    function isDesktop() {
        return window.matchMedia('(min-width: 992px)').matches;
    }

    function isCollapsed() {
        return root.classList.contains('sidebar-collapsed');
    }

    function usesCompactSidebar() {
        return isDesktop() && isCollapsed();
    }

    function updateToggle() {
        const collapsed = isCollapsed();
        const label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';

        document.body.classList.toggle('sidebar-collapsed', collapsed);
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        toggle.setAttribute('title', label);

        if (toggleIcon) {
            toggleIcon.className = collapsed ? 'ti ti-chevron-right' : 'ti ti-chevron-left';
        }
    }

    function closeFlyouts() {
        sidebar.querySelectorAll('.nav-item.is-hover-open').forEach(function (item) {
            item.classList.remove('is-hover-open');
        });
    }

    function positionFlyout(item) {
        if (!usesCompactSidebar()) return;

        const rect = item.getBoundingClientRect();
        const menu = item.querySelector(':scope > .dropdown-menu');

        if (!menu) {
            item.style.setProperty('--sidebar-item-top', `${rect.top + Math.max(0, (rect.height - 34) / 2)}px`);
            return;
        }

        const menuHeight = Math.min(menu.scrollHeight || 224, 448, window.innerHeight - 16);
        const top = Math.max(8, Math.min(rect.top, window.innerHeight - menuHeight - 8));
        menu.style.setProperty('--sidebar-flyout-top', `${top}px`);
    }

    updateToggle();

    toggle.addEventListener('click', function () {
        if (!isDesktop()) return;

        const collapsed = !isCollapsed();
        root.classList.toggle('sidebar-collapsed', collapsed);
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        closeFlyouts();
        updateToggle();

        try {
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (e) {}
    });

    sidebar.querySelectorAll('.nav-item[data-sidebar-label]').forEach(function (item) {
        let closeTimer;
        const menu = item.querySelector(':scope > .dropdown-menu');

        function openFromHover() {
            window.clearTimeout(closeTimer);
            positionFlyout(item);

            if (menu && usesCompactSidebar()) {
                item.classList.add('is-hover-open');
            }
        }

        function closeAfterPointerLeaves() {
            window.clearTimeout(closeTimer);
            closeTimer = window.setTimeout(function () {
                item.classList.remove('is-hover-open');
            }, 280);
        }

        item.addEventListener('mouseenter', openFromHover);
        item.addEventListener('mouseleave', closeAfterPointerLeaves);
        item.addEventListener('focusin', function () {
            positionFlyout(item);
        });

        if (menu) {
            menu.addEventListener('mouseenter', openFromHover);
            menu.addEventListener('mouseleave', closeAfterPointerLeaves);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeFlyouts();
        }
    });

    window.addEventListener('resize', function () {
        updateViewportLeftInset();
        closeFlyouts();
        updateToggle();
    }, { passive: true });
})();
