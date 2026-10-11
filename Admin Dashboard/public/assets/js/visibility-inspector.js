'use strict';

/**
 * Hydrates the visibility-inspector partial from an inspector payload.
 *
 *   payload = {
 *     status: 'live' | 'partial' | 'hidden',
 *     checks: [{key, state: 'pass'|'warn'|'fail', label, message, fix?}, ...],
 *     zone_summary: { reachable_count, total_count, problem_zones: [{id,name,reason}], problem_truncated },
 *   }
 *
 * Usage:
 *   const el = document.querySelector('[data-visibility-inspector]');
 *   renderVisibilityInspector(el, payload);
 *
 * Or (auto-fetch from data-endpoint):
 *   loadVisibilityInspector(el);
 */
window.renderVisibilityInspector = function renderVisibilityInspector(rootEl, payload) {
    if (!rootEl || !payload) return;

    rootEl.hidden = false;

    const statusEl = rootEl.querySelector('[data-visibility-status]');
    if (statusEl) {
        statusEl.className = 'badge ms-2 ' + statusBadgeClass(payload.status);
        statusEl.textContent = statusLabel(payload.status);
    }

    const checksEl = rootEl.querySelector('[data-visibility-checks]');
    if (checksEl) {
        checksEl.innerHTML = (payload.checks || []).map(renderCheckRow).join('');
    }

    const summaryEl = rootEl.querySelector('[data-visibility-zone-summary]');
    const problemsEl = rootEl.querySelector('[data-visibility-problem-zones]');
    if (summaryEl && problemsEl) {
        renderZoneSummary(summaryEl, problemsEl, payload.zone_summary || {});
    }
};

window.loadVisibilityInspector = async function loadVisibilityInspector(rootEl) {
    if (!rootEl) return;
    const endpoint = rootEl.dataset.endpoint;
    if (!endpoint) return;

    try {
        const res = await axios.get(endpoint, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = res.data && res.data.data;
        if (payload) window.renderVisibilityInspector(rootEl, payload);
    } catch (e) {
        console.error('Visibility inspector load failed', e);
    }
};

function statusBadgeClass(status) {
    if (status === 'live') return 'bg-green-lt';
    if (status === 'hidden') return 'bg-red-lt';
    return 'bg-yellow-lt';
}

function statusLabel(status) {
    const labels = window.visibilityInspectorLabels || {};
    if (status === 'live') return labels.live || 'Live';
    if (status === 'hidden') return labels.hidden || 'Hidden';
    return labels.partial || 'Partial';
}

function checkIcon(state) {
    if (state === 'pass') return '<span class="text-success me-2">✓</span>';
    if (state === 'fail') return '<span class="text-danger me-2">✗</span>';
    return '<span class="text-warning me-2">!</span>';
}

function renderCheckRow(check) {
    const fix = check.fix
        ? `<div class="text-muted small ms-3">${escapeHtml(check.fix)}</div>`
        : '';
    return `
        <li class="mb-1">
            <div>${checkIcon(check.state)}<strong>${escapeHtml(check.label)}:</strong> ${escapeHtml(check.message)}</div>
            ${fix}
        </li>`;
}

function renderZoneSummary(summaryEl, problemsEl, summary) {
    const labels = window.visibilityInspectorLabels || {};
    const total = summary.total_count || 0;
    const reachable = summary.reachable_count || 0;

    if (total === 0) {
        summaryEl.textContent = labels.no_active_zones || 'No active zones configured.';
        problemsEl.hidden = true;
        return;
    }

    const tone = reachable === total ? 'text-success' : (reachable === 0 ? 'text-danger' : 'text-warning');
    const dot = reachable === total ? '🟢' : (reachable === 0 ? '🔴' : '🟡');
    const phrase = (labels.reachable_in_zones || 'Reachable in {reachable} of {total} zones')
        .replace('{reachable}', reachable)
        .replace('{total}', total);

    summaryEl.innerHTML = `<span class="${tone}">${dot} ${escapeHtml(phrase)}</span>`;

    const problems = summary.problem_zones || [];
    if (problems.length === 0) {
        problemsEl.hidden = true;
        problemsEl.innerHTML = '';
        return;
    }

    const truncatedNote = summary.problem_truncated
        ? `<div class="text-muted small mt-1">${escapeHtml(labels.problem_truncated || 'More zones omitted.')}</div>`
        : '';

    problemsEl.hidden = false;
    problemsEl.innerHTML = `
        <details>
            <summary class="text-primary" style="cursor:pointer;">${escapeHtml(labels.show_problem_zones || 'Show problem zones')}</summary>
            <ul class="mt-2 mb-0 ps-3">
                ${problems.map(z => `<li><strong>${escapeHtml(z.name)}</strong> — ${escapeHtml(z.reason)}</li>`).join('')}
            </ul>
            ${truncatedNote}
        </details>`;
}

function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
