const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { execFileSync } = require('node:child_process');
const { createServer } = require('node:http');
const { chromium } = require('playwright');

const root = resolve(__dirname, '../..');
const catalog = JSON.parse(execFileSync('php', ['-r', "echo json_encode(require 'config/seller_tours.php');"], { cwd: root, encoding: 'utf8' }));
const translations = JSON.parse(execFileSync('php', ['-r', "$labels = require 'resources/lang/en/labels.php'; echo json_encode($labels['seller_tours']);"], { cwd: root, encoding: 'utf8' }));
const translated = value => value.split('.').slice(2).reduce((current, part) => current[part], translations);
const driverSource = readFileSync(resolve(root, 'public/assets/vendor/driverjs/driver.min.js'), 'utf8');
const driverCss = readFileSync(resolve(root, 'public/assets/vendor/driverjs/driver.css'), 'utf8');
const engineSource = readFileSync(resolve(root, 'public/assets/js/tours/seller/walkthroughs.js'), 'utf8');
let browser;
let server;
let origin;

before(async () => {
    server = createServer((request, response) => {
        response.setHeader('Content-Type', 'text/html');
        response.end('<!doctype html><html><body><div class="page-body"><div class="container-xl"></div></div></body></html>');
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    origin = `http://127.0.0.1:${server.address().port}`;
    browser = await chromium.launch(process.env.WALKTHROUGH_BROWSER ? { executablePath: process.env.WALKTHROUGH_BROWSER } : { channel: 'chrome' });
});

after(async () => {
    if (browser) await browser.close();
    if (server) await new Promise(resolve => server.close(resolve));
});

function tour(stages) {
    return { title: 'Example', description: 'Example', stages };
}

function stage(path = '/panel/seller/example', extra = {}) {
    return {
        url: origin + path,
        steps: [
            { selector: '#first', title: 'First', description: 'First explanation' },
            { selector: '#second', title: 'Second', description: 'Second explanation' }
        ],
        ...extra
    };
}

async function fixture(tours, options = {}) {
    const page = await browser.newPage({ viewport: options.mobile ? { width: 390, height: 844 } : { width: 1280, height: 800 } });
    await page.goto(origin + (options.path || '/panel/seller/example'));
    await page.evaluate(({ tours, options, text }) => {
        document.querySelector('.container-xl').innerHTML = options.html || '<button id="launcher">Start</button><div id="first">First</div><form id="second"><input id="value" value="Existing value"><button id="save" type="submit">Save</button></form>';
        const payload = document.createElement('script');
        payload.type = 'application/json';
        payload.id = 'seller-tour-catalog';
        payload.textContent = JSON.stringify({ tours, userId: options.userId || '123', route: options.route || 'seller.example', text });
        document.body.appendChild(payload);
        window.adminStarted = null;
        window.TourManager = { registerTours: () => {}, startTour: id => { window.adminStarted = id; } };
        if (options.state) sessionStorage.setItem(`seller_walkthrough:${options.userId || '123'}`, JSON.stringify(options.state));
        document.getElementById('launcher')?.focus();
    }, { tours, options, text: translations });
    await page.addScriptTag({ content: driverSource });
    await page.addStyleTag({ path: resolve(root, 'public/assets/theme/css/tabler.css') });
    await page.addStyleTag({ content: driverCss });
    if (options.bootstrap) {
        await page.addScriptTag({ path: resolve(root, 'public/assets/theme/js/tabler.min.js') });
    }
    await page.addScriptTag({ content: engineSource });
    return page;
}

test('ordinary page visits do not start a tour; existing admin start remains available', async () => {
    const page = await fixture({ seller_example: tour([stage()]) });
    assert.equal(await page.locator('.driver-popover').count(), 0);
    await page.evaluate(() => TourManager.startTour('create_system_user'));
    assert.equal(await page.evaluate(() => window.adminStarted), 'create_system_user');
    await page.close();
});

test('walkthrough drawer stays above tables and keeps only one section expanded', async () => {
    const groups = [1, 2].map(index => `<div class="accordion-item border rounded-3 mb-3 tour-group-item" data-group-name="section ${index}"><h3 class="accordion-header"><button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#group${index}" aria-expanded="false"><span class="badge bg-primary-lt me-2 tour-group-number">${index}</span><span class="tour-group-title">Section ${index}</span><span class="badge bg-secondary-lt ms-2 tour-group-count">1</span></button></h3><div class="accordion-collapse collapse" id="group${index}" data-bs-parent="#tourAccordion"><div class="accordion-body"><article class="tour-card" data-tour-title="shared guide" data-tour-description="guide">Guide ${index}</article></div></div></div>`).join('');
    const html = `<div style="position:relative;z-index:1;transform:translateZ(0)"><div class="offcanvas offcanvas-end m-0 end-0 rounded-0" id="offcanvasTourDrawer" tabindex="-1"><div class="offcanvas-header">Walkthroughs</div><div class="offcanvas-body p-3"><input id="tourSearchInput" class="form-control"><div class="accordion" id="tourAccordion">${groups}</div><div id="tourNoResults" class="d-none">No results</div></div></div></div><div id="table-header" style="position:fixed;right:0;top:140px;z-index:1070;background:white">Table header</div>`;
    const page = await fixture({}, { html, bootstrap: true });
    await page.addStyleTag({ path: resolve(root, 'public/assets/css/custom.css') });
    const source = readFileSync(resolve(root, 'resources/views/layouts/partials/_tour_drawer.blade.php'), 'utf8');
    await page.evaluate(() => Object.defineProperty(document, 'readyState', { configurable: true, get: () => 'loading' }));
    await page.addScriptTag({ content: source.slice(source.lastIndexOf('<script>') + 8, source.lastIndexOf('</script>')) });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    for (const theme of ['light', 'dark']) {
        await page.evaluate(theme => {
            document.documentElement.dataset.bsTheme = theme;
            window.tabler.Offcanvas.getOrCreateInstance(document.getElementById('offcanvasTourDrawer')).show();
        }, theme);
        await page.waitForFunction(() => document.getElementById('offcanvasTourDrawer').classList.contains('show'));
        assert.equal(await page.evaluate(() => document.getElementById('offcanvasTourDrawer').parentElement === document.body), true);
        assert.equal(await page.evaluate(() => {
            const drawer = document.getElementById('offcanvasTourDrawer');
            const box = drawer.getBoundingClientRect();
            return drawer.contains(document.elementFromPoint(box.right - 30, 145));
        }), true);
        assert.notEqual(await page.locator('#offcanvasTourDrawer').evaluate(element => getComputedStyle(element).backgroundColor), 'rgba(0, 0, 0, 0)');
    }
    await page.locator('[data-bs-target="#group1"]').click();
    await page.waitForSelector('#group1.show');
    await page.locator('[data-bs-target="#group2"]').click();
    await page.waitForSelector('#group2.show');
    await page.waitForFunction(() => !document.getElementById('group1').classList.contains('show'));
    await page.locator('#tourSearchInput').fill('shared');
    await page.waitForFunction(() => document.querySelectorAll('#tourAccordion .collapse.show').length === 1);
    await page.locator('#tourSearchInput').fill('no match');
    assert.equal(await page.locator('#tourNoResults').isVisible(), true);
    await page.locator('#tourSearchInput').fill('');
    assert.deepEqual(await page.locator('.tour-group-count').allTextContents(), ['1', '1']);
    await page.close();
});

test('Close and Escape cancel without navigation and restore launcher focus', async () => {
    for (const closeWithEscape of [false, true]) {
        const page = await fixture({ seller_example: tour([stage(), stage('/panel/seller/other')]) });
        await page.evaluate(() => SellerWalkthroughs.start('seller_example'));
        await page.waitForSelector('.driver-popover');
        if (closeWithEscape) await page.keyboard.press('Escape');
        else await page.locator('.driver-popover-close-btn').click();
        assert.equal(new URL(page.url()).pathname, '/panel/seller/example');
        assert.equal(await page.evaluate(() => sessionStorage.getItem('seller_walkthrough:123')), null);
        assert.equal(await page.evaluate(() => document.activeElement.id), 'launcher');
        await page.close();
    }
});

test('Next and Back explain controls; submissions and field edits are blocked', async () => {
    const page = await fixture({ seller_example: tour([stage()]) });
    await page.evaluate(() => {
        window.submitted = false;
        document.querySelector('form').addEventListener('submit', event => { event.preventDefault(); window.submitted = true; });
        SellerWalkthroughs.start('seller_example');
    });
    await page.waitForSelector('.driver-popover');
    await page.locator('.driver-popover-next-btn').click();
    await page.waitForFunction(() => SellerWalkthroughs.getState()?.step === 1 && document.querySelector('.driver-popover-title')?.textContent === 'Second');
    await page.evaluate(() => document.querySelector('form').requestSubmit());
    assert.equal(await page.evaluate(() => window.submitted), false);
    await page.locator('#value').press('a');
    assert.equal(await page.locator('#value').inputValue(), 'Existing value');
    await page.locator('.driver-popover-prev-btn').click();
    await page.waitForFunction(() => SellerWalkthroughs.getState()?.step === 0);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#value').inputValue(), 'Existing value');
    await page.close();
});

test('Finish completes and removes session state', async () => {
    const page = await fixture({ seller_example: tour([stage()]) });
    await page.evaluate(() => SellerWalkthroughs.start('seller_example'));
    await page.waitForSelector('.driver-popover');
    await page.locator('.driver-popover-next-btn').click();
    await page.waitForFunction(() => document.querySelector('.driver-popover-title')?.textContent === 'Second');
    await page.locator('.driver-popover-next-btn').click();
    assert.equal(await page.evaluate(() => SellerWalkthroughs.getState()), null);
    assert.equal(await page.locator('.driver-popover').count(), 0);
    await page.close();
});

test('only explicit Continue navigates to a continuation page', async () => {
    const page = await fixture({ seller_example: tour([stage(), stage('/panel/seller/other')]) });
    await page.evaluate(() => SellerWalkthroughs.start('seller_example'));
    await page.waitForSelector('.driver-popover');
    await page.locator('.driver-popover-next-btn').click();
    await page.waitForFunction(() => document.querySelector('.driver-popover-title')?.textContent === 'Second');
    assert.equal(new URL(page.url()).pathname, '/panel/seller/example');
    await page.locator('.driver-popover-next-btn').click();
    await page.waitForURL('**/panel/seller/other');
    assert.equal(await page.evaluate(() => JSON.parse(sessionStorage.getItem('seller_walkthrough:123')).stage), 1);
    await page.close();
});

test('detail tours wait for seller-selected records and handle empty tables', async () => {
    const record = stage('/panel/seller/orders/:record', { record: true, listing: 'seller.orders.index', listingUrl: origin + '/panel/seller/orders' });
    const page = await fixture({ seller_example: tour([record]) }, { path: '/panel/seller/orders', html: '<table><tbody><tr><td class="dt-empty">No orders</td></tr></tbody></table>' });
    await page.evaluate(() => SellerWalkthroughs.start('seller_example'));
    await page.waitForSelector('[role="status"]');
    assert.equal(await page.evaluate(() => SellerWalkthroughs.getState().status), 'waiting');
    assert.equal(await page.locator('.driver-popover').count(), 0);
    assert.match(await page.locator('[role="status"]').textContent(), /no records/i);
    await page.locator('[role="status"] button').click();
    assert.equal(await page.evaluate(() => SellerWalkthroughs.getState()), null);
    await page.close();
    const selected = await fixture({ seller_example: tour([record]) }, { path: '/panel/seller/orders/abc-123', state: { id: 'seller_example', stage: 0, step: 0, status: 'waiting' } });
    await selected.waitForSelector('.driver-popover');
    assert.equal(await selected.evaluate(() => SellerWalkthroughs.getState().status), 'running');
    await selected.close();
});

test('session state cannot resume a tour for a different seller', async () => {
    const page = await fixture({ seller_example: tour([stage()]) }, { userId: '456' });
    await page.evaluate(() => sessionStorage.setItem('seller_walkthrough:123', JSON.stringify({ id: 'seller_example', stage: 0, step: 0, status: 'running' })));
    assert.equal(await page.locator('.driver-popover').count(), 0);
    await page.close();
});

test('wizard panels are revealed and original visibility and values restored', async () => {
    const definition = stage();
    definition.steps = [{ selector: '.wizard-step[data-step="2"]', title: 'Details', description: 'Details', reveal: { type: 'wizard', selector: '#tab2', step: '2' } }];
    const page = await fixture({ seller_example: tour([definition]) }, { html: '<style>.d-none{display:none}</style><form id="product-form-submit"><button id="tab2" type="button">Details</button><div class="wizard-step" data-step="1">Category</div><div class="wizard-step d-none" data-step="2"><input value="Unsaved title"></div></form>' });
    await page.evaluate(() => SellerWalkthroughs.start('seller_example'));
    await page.waitForSelector('.driver-popover');
    assert.equal(await page.locator('.wizard-step[data-step="2"]').isVisible(), true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.wizard-step[data-step="1"]').isVisible(), true);
    assert.equal(await page.locator('.wizard-step[data-step="2"]').isVisible(), false);
    assert.equal(await page.locator('input').inputValue(), 'Unsaved title');
    await page.close();
});

test('required missing controls cancel cleanly with an explanation', async () => {
    const definition = stage();
    definition.steps = [{ selector: '#missing', title: 'Missing', description: 'Missing' }];
    const page = await fixture({ seller_example: tour([definition]) });
    await page.evaluate(() => SellerWalkthroughs.start('seller_example'));
    await page.waitForSelector('[role="status"]', { timeout: 7000 });
    assert.match(await page.locator('[role="status"]').textContent(), /required control/i);
    assert.equal(await page.locator('.driver-overlay').count(), 0);
    await page.close();
});

test('owned composer modals stay open across their steps and close on cancellation', async () => {
    const definition = stage();
    definition.steps = [
        { selector: '#composer input', title: 'Composer', description: 'Explain', reveal: { type: 'modal', selector: '#composer' } },
        { selector: '#composer select', title: 'Options', description: 'Explain options' }
    ];
    const html = '<style>.modal{display:none}.modal.show{display:block}</style><button type="button" data-bs-target="#composer">Create</button><div class="modal" tabindex="-1" id="composer"><div class="modal-dialog"><div class="modal-content"><input value=""><select><option value="">Choose</option><option value="one">One</option></select></div></div></div>';
    const page = await fixture({ seller_example: tour([definition]) }, { html, bootstrap: true });
    await page.evaluate(() => SellerWalkthroughs.start('seller_example'));
    await page.waitForSelector('.driver-popover');
    assert.equal(await page.locator('#composer').isVisible(), true);
    await page.locator('.driver-popover-next-btn').click();
    await page.waitForFunction(() => document.querySelector('.driver-popover-title')?.textContent === 'Options');
    assert.equal(await page.locator('#composer').isVisible(), true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#composer').isVisible(), false);
    await page.close();
});

test('preexisting open modals and unsaved values survive cancellation', async () => {
    const definition = stage();
    definition.steps = [{ selector: '#composer input', title: 'Composer', description: 'Explain', reveal: { type: 'modal', selector: '#composer' } }];
    const html = '<style>.modal{display:none}.modal.show{display:block}</style><div class="modal show" id="composer"><input value="Original"></div>';
    const page = await fixture({ seller_example: tour([definition]) }, { html });
    await page.evaluate(() => { document.querySelector('input').value = 'Unsaved'; SellerWalkthroughs.start('seller_example'); });
    await page.waitForSelector('.driver-popover');
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#composer').isVisible(), true);
    assert.equal(await page.locator('#composer input').inputValue(), 'Unsaved');
    await page.close();
});

test('a hidden composer with an unsaved draft is not opened or reset', async () => {
    const definition = stage();
    definition.steps = [
        { selector: '#composer input', title: 'Composer', description: 'Explain', optional: true, reveal: { type: 'modal', selector: '#composer' } },
        { selector: '#first', title: 'Overview', description: 'Explain' }
    ];
    const html = '<style>.modal{display:none}.modal.show{display:block}</style><div id="first">Overview</div><button type="button" data-bs-target="#composer">Create</button><div class="modal" tabindex="-1" id="composer"><div class="modal-dialog"><div class="modal-content"><input value="Original"></div></div></div>';
    const page = await fixture({ seller_example: tour([definition]) }, { html, bootstrap: true });
    await page.evaluate(() => { document.querySelector('input').value = 'Unsaved'; SellerWalkthroughs.start('seller_example'); });
    await page.waitForSelector('.driver-popover', { timeout: 7000 });
    assert.equal(await page.locator('.driver-popover-title').textContent(), 'Overview');
    assert.equal(await page.locator('#composer').isVisible(), false);
    assert.equal(await page.locator('#composer input').inputValue(), 'Unsaved');
    await page.keyboard.press('Escape');
    await page.close();
});

test('late asynchronous controls become tour targets; optional missing controls do not auto-navigate', async () => {
    const definition = stage();
    definition.steps = [{ selector: '#late', title: 'Late', description: 'Late' }, { selector: '#missing', title: 'Optional', description: 'Optional', optional: true }];
    const page = await fixture({ seller_example: tour([definition, stage('/panel/seller/other')]) });
    await page.evaluate(() => {
        SellerWalkthroughs.start('seller_example');
        setTimeout(() => { const element = document.createElement('div'); element.id = 'late'; element.textContent = 'Loaded'; document.body.appendChild(element); }, 200);
    });
    await page.waitForSelector('.driver-popover');
    assert.equal(await page.locator('#late').getAttribute('class'), 'driver-active-element');
    await page.locator('.driver-popover-next-btn').click();
    await page.waitForFunction(() => document.querySelector('.driver-popover-description')?.textContent.includes('unavailable'), { timeout: 7000 });
    assert.equal(new URL(page.url()).pathname, '/panel/seller/example');
    await page.keyboard.press('Escape');
    await page.close();
});

for (const [id, definition] of Object.entries(catalog)) {
    test(`card ${id} is registered, replayable and responsive`, async () => {
        const first = definition.stages[0];
        const staged = { ...first, url: origin + '/panel/seller/example', record: false, requireExisting: undefined, steps: [{ selector: '#first', title: translated(first.steps[0].title), description: translated(first.steps[0].description) }] };
        const page = await fixture({ [id]: { title: translated(definition.title), stages: [staged] } }, { mobile: true });
        for (let replay = 0; replay < 2; replay++) {
            await page.evaluate(id => TourManager.startTour(id), id);
            await page.waitForSelector('.driver-popover');
            assert.equal(await page.locator('.driver-popover-title').textContent(), translated(first.steps[0].title));
            const bounds = await page.locator('.driver-popover').boundingBox();
            assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= 390);
            await page.keyboard.press('Escape');
        }
        await page.close();
    });
}
