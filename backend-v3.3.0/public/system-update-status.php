<?php

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'nonce-{$nonce}'; connect-src 'self'; frame-ancestors 'none'");
$token = $_SERVER['HTTP_X_SYSTEM_UPDATE_TOKEN'] ?? '';
if ($token !== '') {
    header('Content-Type: application/json');
    $path = preg_match('/^[a-f0-9]{64}$/D', $token) ? __DIR__ . '/../storage/app/private/updates/recovery/' . $token . '/state.json' : '';
    if (!$path || !is_file($path)) {
        http_response_code(404);
        echo json_encode(['message' => 'Update log not found.']);
        exit;
    }
    $state = json_decode(file_get_contents($path), true);
    if (!is_array($state)) { http_response_code(503); echo '{}'; exit; }
    if (in_array($state['status'], ['applied', 'failed'], true) && empty($state['finished'])) $state['status'] = 'pending';
    $state['is_stale'] = $state['status'] === 'pending' && strtotime($state['heartbeat_at'] ?? '') < time() - 120;
    echo json_encode($state, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System update progress</title>
<style nonce="<?= $nonce ?>">
body { margin: 0; background: #f6f8fb; color: #182433; font: 16px/1.6 system-ui, sans-serif; }
main { max-width: 900px; margin: 6vh auto; padding: 24px; }
h1 { font-size: 24px; }
pre { padding: 20px; background: #182433; color: #fff; border-radius: 8px; white-space: pre-wrap; overflow-wrap: anywhere; }
p { overflow-wrap: anywhere; }
</style>

</head>
<body>
<main>
    <h1>System update progress</h1>
    <p id="recovery-status" role="status">Checking the update…</p>
    <p>This private link gives access to the update log. Share it only with your administrator or hosting support.</p>
    <p id="recovery-help" hidden>Keep the website in maintenance if recovery is required. Ask your hosting support to inspect the backup path below. If database changes started, restore the database and matching application files together.</p>
    <pre id="recovery-log"></pre>
    <p id="recovery-backup"></p>
</main>
<script nonce="<?= $nonce ?>">
'use strict';
(async function poll() {
    const status = document.getElementById('recovery-status');
    const token = location.hash.slice(1);
    if (!/^[a-f0-9]{64}$/.test(token)) {
        status.textContent = 'Open the private update log link provided after confirming your update.';
        return;
    }
    try {
        const response = await fetch(location.pathname, {headers: {'X-System-Update-Token': token}});
        if (!response.ok) throw new Error('The update log is unavailable. Check your private link or contact hosting support.');
        const state = await response.json();
        status.textContent = state.is_stale ? 'Progress has stopped. Check the log and contact hosting support before retrying.' : `Update ${state.status} — ${state.progress || 0}%`;
        document.getElementById('recovery-log').textContent = state.log || '';
        document.getElementById('recovery-help').hidden = state.status !== 'failed' && !state.is_stale;
        document.getElementById('recovery-backup').textContent = state.backup ? `Backup location: ${state.backup}` : '';
        if (state.status !== 'pending') return;
    } catch (error) {
        status.textContent = error.message;
    }
    setTimeout(poll, 3000);
})();
</script>
</body>
</html>
