(function () {
    'use strict';

    const payload = document.getElementById('seller-tour-catalog');
    if (!payload || !window.TourManager || !window.Driver) return;

    const config = JSON.parse(payload.textContent);
    const tours = config.tours;
    const text = config.text;
    const storageKey = `seller_walkthrough:${config.userId}`;
    const aliases = {
        seller_store_setup: 'seller_store_create',
        seller_subscription_plans: 'seller_subscriptions',
        seller_product_attributes: 'seller_attributes',
        seller_products_management: 'seller_product_list',
        seller_wallet_setup: 'seller_wallet',
        seller_ad_campaigns: 'seller_ad_campaigns'
    };
    const legacyStart = window.TourManager.startTour;
    let driver = null;
    let active = null;
    let notice = null;
    let launcher = null;
    let generation = 0;
    let transitioning = false;
    let wizardCleanup = null;
    let ownedModal = null;
    const cleanups = [];

    function components() {
        return window.bootstrap || window.tabler;
    }

    function persist(status) {
        active.status = status;
        try {
            if (status === 'completed' || status === 'cancelled') {
                sessionStorage.removeItem(storageKey);
            } else {
                sessionStorage.setItem(storageKey, JSON.stringify(active));
            }
        } catch (error) {
            // The current page remains usable when browser storage is blocked.
        }
    }

    function clearNotice() {
        if (notice) notice.remove();
        notice = null;
    }

    function restoreUi() {
        if (wizardCleanup) wizardCleanup();
        wizardCleanup = null;
        while (cleanups.length) cleanups.pop()();
        ownedModal = null;
    }

    function stop(status = 'cancelled') {
        generation++;
        transitioning = false;
        if (active) persist(status);
        active = null;
        clearNotice();
        if (driver) {
            const previous = driver;
            driver = null;
            previous.destroy();
        }
        restoreUi();
        if (launcher && launcher.isConnected) launcher.focus();
    }

    function visible(selector) {
        if (!selector) return null;
        return [...document.querySelectorAll(selector)].find(element => {
            const style = getComputedStyle(element);
            return element.getClientRects().length > 0 && style.visibility !== 'hidden' && style.display !== 'none';
        }) || null;
    }

    async function waitFor(selector, attempt) {
        const deadline = Date.now() + 4000;
        do {
            if (attempt !== generation || !active) return null;
            const element = visible(selector);
            if (element) return element;
            await new Promise(resolve => setTimeout(resolve, 100));
        } while (Date.now() < deadline);
        return null;
    }

    function message(body, waiting = false, offer = null) {
        clearNotice();
        const host = document.querySelector('.page-body > .container-xl') || document.querySelector('.page-body') || document.body;
        notice = document.createElement('div');
        notice.className = 'alert alert-info d-flex align-items-center justify-content-between gap-3 flex-wrap';
        notice.setAttribute('role', 'status');
        const content = document.createElement('span');
        content.textContent = body;
        notice.appendChild(content);
        if (offer && tours[offer]) {
            const related = document.createElement('button');
            related.type = 'button';
            related.className = 'btn btn-sm btn-outline-primary';
            related.textContent = tours[offer].title;
            related.addEventListener('click', () => start(offer));
            notice.appendChild(related);
        }
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn btn-sm btn-outline-secondary';
        close.textContent = text.close;
        close.addEventListener('click', () => stop());
        notice.appendChild(close);
        host.prepend(notice);
        if (waiting && active) persist('waiting');
        close.focus();
    }

    function patternMatches(stage) {
        const pattern = new URL(stage.url, window.location.origin).pathname;
        const escaped = pattern.split(':record').map(part => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('[^/]+');
        return new RegExp(`^${escaped}/?$`).test(window.location.pathname);
    }

    function navigate(url) {
        persist('starting');
        window.location.assign(url);
    }

    function selectRecord(stage) {
        persist('waiting');
        const listing = new URL(stage.listingUrl, window.location.origin);
        if (window.location.pathname.replace(/\/$/, '') !== listing.pathname.replace(/\/$/, '')) {
            window.location.assign(stage.listingUrl);
            return;
        }
        message(text[stage.selection] || text.select_record, true);
        const table = document.querySelector('.page-body table');
        const offer = stage.listing === 'seller.stores.index' ? 'seller_store_create' : stage.listing === 'seller.products.index' ? 'seller_product_create' : null;
        if (table) {
            const empty = () => {
                if (active && active.status === 'waiting' && table.querySelector('.dt-empty, .dataTables_empty')) {
                    message(text.empty + ' ' + (text[stage.selection] || text.select_record), true, offer);
                }
            };
            const observer = new MutationObserver(empty);
            observer.observe(table, { childList: true, subtree: true });
            cleanups.push(() => observer.disconnect());
            empty();
        }
    }

    function enterStage() {
        const tour = tours[active.id];
        const stage = tour.stages[active.stage];
        if (!stage) return stop('completed');
        if (!patternMatches(stage)) {
            if (stage.redirect && config.route === stage.redirect) {
                message(text.profile_required, true);
                return;
            }
            if (stage.record) return selectRecord(stage);
            return navigate(stage.url);
        }
        if (stage.requireExisting && !visible(stage.requireExisting)) {
            message(text[stage.selection] || text.select_record, true);
            const observer = new MutationObserver(() => {
                if (active && active.status === 'waiting' && visible(stage.requireExisting)) {
                    observer.disconnect();
                    clearNotice();
                    run();
                }
            });
            observer.observe(document.body, { attributes: true, childList: true, subtree: true });
            cleanups.push(() => observer.disconnect());
            return;
        }
        clearNotice();
        run();
    }

    function prepare(step) {
        const reveal = step.reveal;
        if (!reveal) return;
        const element = document.querySelector(reveal.selector);
        if (!element) return;
        if (reveal.type === 'modal') {
            if (element.classList.contains('show')) {
                const existing = components()?.Modal?.getInstance(element);
                existing?._focustrap?.deactivate();
                cleanups.push(() => { if (element.classList.contains('show')) existing?._focustrap?.activate(); });
                return;
            }
            // Only open a composer when its permitted create control is present.
            const trigger = document.querySelector(`[data-bs-target="${reveal.selector}"]:not([disabled])`);
            if (!trigger || !components()?.Modal) return;
            const fields = [...element.querySelectorAll('input, textarea, select')];
            if (fields.some(field => {
                if (field.type === 'file') return field.files?.length > 0;
                if (['checkbox', 'radio'].includes(field.type)) return field.checked !== field.defaultChecked;
                if (field.tagName === 'SELECT') {
                    const defaults = [...field.options].filter(option => option.defaultSelected);
                    const expected = defaults.length ? defaults.map(option => option.value) : field.multiple ? [] : [field.options[0]?.value];
                    return JSON.stringify([...field.selectedOptions].map(option => option.value)) !== JSON.stringify(expected);
                }
                return field.value !== field.defaultValue;
            })) return;
            if (ownedModal) ownedModal.hide();
            const modal = components().Modal.getOrCreateInstance(element, { focus: false });
            const suspendFocus = () => modal._focustrap?.deactivate();
            element.addEventListener('shown.bs.modal', suspendFocus, { once: true });
            modal.show();
            ownedModal = modal;
            cleanups.push(() => {
                element.removeEventListener('shown.bs.modal', suspendFocus);
                modal.hide();
            });
        } else if (reveal.type === 'tab' && components()?.Tab) {
            const previous = element.closest('.nav')?.querySelector('.active');
            if (previous === element) return;
            components().Tab.getOrCreateInstance(element).show();
            if (previous) cleanups.push(() => components().Tab.getOrCreateInstance(previous).show());
        } else if (reveal.type === 'wizard') {
            if (wizardCleanup) wizardCleanup();
            const panes = [...document.querySelectorAll('#product-form-submit .wizard-step')];
            const states = panes.map(pane => pane.classList.contains('d-none'));
            panes.forEach(pane => pane.classList.toggle('d-none', pane.dataset.step !== reveal.step));
            wizardCleanup = () => panes.forEach((pane, index) => pane.classList.toggle('d-none', states[index]));
        }
    }

    function escapeHtml(value) {
        const element = document.createElement('span');
        element.textContent = value;
        return element.innerHTML;
    }

    async function show(index) {
        if (!active) return;
        const attempt = ++generation;
        transitioning = true;
        const stage = tours[active.id].stages[active.stage];
        const summary = index >= stage.steps.length;
        const step = stage.steps[index] || { title: tours[active.id].title, description: text.unavailable };
        if (ownedModal && ![...document.querySelectorAll(step.selector || '__none__')].some(element => ownedModal._element.contains(element)) && step.reveal?.type !== 'modal') {
            ownedModal.hide();
            ownedModal = null;
        }
        active.step = index;
        persist('running');
        prepare(step);
        const target = summary ? null : await waitFor(step.selector, attempt);
        if (attempt !== generation || !active) return;
        if (!target && step.optional) {
            transitioning = false;
            return show(index + 1);
        }
        if (!target && !summary) {
            stop('cancelled');
            message(text.missing);
            return;
        }
        const last = index >= stage.steps.length - 1;
        const nextPage = active.stage < tours[active.id].stages.length - 1;
        driver.setSteps([{
            element: target,
            popover: {
                title: escapeHtml(step.title),
                description: escapeHtml(step.description),
                side: 'bottom',
                currentStepNum: Math.min(index + 1, stage.steps.length),
                totalSteps: stage.steps.length,
                isFirst: index === 0,
                isLast: last && !nextPage,
                nextBtnText: last ? text.continue : text.next
            }
        }]);
        driver.start(0);
        transitioning = false;
        const popover = driver.popover;
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-label', step.title);
        popover.querySelector('.driver-popover-close-btn')?.setAttribute('aria-label', text.close);
        const progress = popover.querySelector('.driver-popover-progress');
        if (progress) progress.textContent = text.progress.replace(':current', Math.min(index + 1, stage.steps.length)).replace(':total', stage.steps.length);
        if (step.offer && tours[step.offer]) {
            const offer = document.createElement('button');
            offer.type = 'button';
            offer.className = 'driver-popover-btn';
            offer.textContent = tours[step.offer].title;
            offer.addEventListener('click', () => start(step.offer));
            popover.appendChild(offer);
        }
        popover.querySelector('.driver-popover-next-btn')?.focus();
    }

    function nextStage() {
        if (!active) return;
        const next = active.stage + 1;
        if (next >= tours[active.id].stages.length) return stop('completed');
        active.stage = next;
        active.step = 0;
        if (driver) {
            const previous = driver;
            driver = null;
            previous.destroy();
        }
        restoreUi();
        enterStage();
    }

    function run() {
        driver = new window.Driver({
            animate: true,
            allowClose: true,
            nextBtnText: text.next,
            prevBtnText: text.back,
            doneBtnText: text.finish,
            onDestroyStarted: () => { if (driver) stop(); }
        });
        driver.next = () => {
            if (transitioning || !active) return;
            const steps = tours[active.id].stages[active.stage].steps;
            if (active.step >= steps.length - 1) nextStage();
            else show(active.step + 1);
        };
        driver.prev = () => {
            if (transitioning || !active) return;
            const steps = tours[active.id].stages[active.stage].steps;
            let previous = active.step - 1;
            while (previous >= 0 && steps[previous].optional && !steps[previous].reveal && !visible(steps[previous].selector)) previous--;
            if (previous >= 0) show(previous);
        };
        show(active.step);
    }

    function start(id) {
        id = aliases[id] || id;
        const tour = tours[id];
        if (!tour) return;
        stop();
        launcher = document.activeElement;
        const drawer = document.getElementById('offcanvasTourDrawer');
        if (drawer) components()?.Offcanvas?.getInstance(drawer)?.hide();
        const currentStage = tour.stages.findIndex(patternMatches);
        active = { id, stage: currentStage < 0 ? 0 : currentStage, step: 0, status: 'starting' };
        persist('starting');
        enterStage();
    }

    window.TourManager.registerTours(tours);
    window.TourManager.startTour = id => {
        if (id.startsWith('seller_')) start(id);
        else legacyStart(id);
    };
    window.SellerWalkthroughs = { start, cancel: () => stop(), getState: () => active ? { ...active } : null };

    document.addEventListener('keydown', event => {
        if (!active) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            stop();
        } else if (event.key === 'Tab' && driver?.popover) {
            const buttons = [...driver.popover.querySelectorAll('button')];
            const index = buttons.indexOf(document.activeElement);
            event.preventDefault();
            buttons[(index + (event.shiftKey ? -1 : 1) + buttons.length) % buttons.length]?.focus();
        }
    }, true);

    function preventMutation(event) {
        if (!active || active.status !== 'running' || event.target.closest('.driver-popover')) return;
        event.preventDefault();
        event.stopImmediatePropagation();
    }
    ['submit', 'click', 'pointerdown', 'beforeinput', 'keydown'].forEach(type => {
        document.addEventListener(type, event => {
            if (type === 'keydown' && ['Tab', 'Escape'].includes(event.key)) return;
            if (event.target.closest('input, textarea, select, button, a, [contenteditable], form') || type === 'submit') preventMutation(event);
        }, true);
    });

    function resume() {
        try {
            localStorage.removeItem('active_seller_tour');
            if ((localStorage.getItem('active_admin_tour') || '').startsWith('seller_')) {
                localStorage.removeItem('active_admin_tour');
            }
            active = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        } catch (error) { active = null; }
        if (!active) return;
        const tour = tours[active.id];
        const stage = tour?.stages[active.stage];
        if (!stage || !Number.isInteger(active.step) || active.step < 0 || active.step > stage.steps.length) return stop();
        const isListing = stage.listingUrl && new URL(stage.listingUrl).pathname === window.location.pathname;
        const isRedirect = stage.redirect === config.route;
        if (!patternMatches(stage) && !isListing && !isRedirect) return stop();
        enterStage();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', resume);
    else resume();
})();
