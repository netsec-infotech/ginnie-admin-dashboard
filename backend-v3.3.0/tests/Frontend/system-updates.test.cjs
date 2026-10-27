const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/system-updates.js'), 'utf8');
const selectorSource = source.slice(source.indexOf('    function selectNewestState'), source.indexOf('    async function pollOnce'));
const selectNewestState = vm.runInNewContext(`${selectorSource}\nselectNewestState`, {Date});
const urlGuardSource = source.slice(source.indexOf('    function requireRequestUrl'), source.indexOf('    function renderErrors'));
const requireRequestUrl = vm.runInNewContext(`${urlGuardSource}\nrequireRequestUrl`);

test('completed admin state replaces the initial recovery message', () => {
    const recovery = {status: 'pending', heartbeat_at: '2026-09-07T10:00:00Z', log: 'Update confirmed. Waiting for the queue worker.'};
    const admin = {status: 'applied', heartbeat_at: '2026-09-07T10:01:00Z', log: 'Full update log'};
    assert.equal(selectNewestState([recovery, admin]).log, 'Full update log');
});

test('fresh recovery progress wins while the admin row is still stale', () => {
    const recovery = {status: 'pending', heartbeat_at: '2026-09-07T10:01:00Z', log: 'Applying files'};
    const admin = {status: 'pending', heartbeat_at: '2026-09-07T10:00:00Z', log: ''};
    assert.equal(selectNewestState([admin, recovery]).log, 'Applying files');
});

test('confirmation is restricted to vendor updates', () => {
    assert.match(source, /if \(preview\.vendor_update\) \{\s+const modal = updateModal\(\);\s+if \(!await confirmUpdate/);
});

test('missing updater URLs fail before Axios receives an undefined value', () => {
    assert.throws(() => requireRequestUrl(undefined), /request URL is unavailable/);
    assert.equal(requireRequestUrl('/admin/system-updates/preview'), '/admin/system-updates/preview');
    assert.match(source, /form\.getAttribute\('data-preview-url'\)/);
    assert.match(source, /form\.getAttribute\('action'\)/);
});
