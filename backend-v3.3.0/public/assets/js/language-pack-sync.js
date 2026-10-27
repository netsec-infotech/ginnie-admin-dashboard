document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('language-pack-sync');
    if (!root) return;
    const form = root.querySelector('form');
    const button = form.querySelector('button[type="submit"]');
    const texts = JSON.parse(root.dataset.texts);
    let pending = false;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending) return;
        pending = true;
        button.disabled = true;
        try {
            const confirmation = await Swal.fire({
                text: texts.confirm, icon: 'question', showCancelButton: true,
                confirmButtonText: texts.submit, cancelButtonText: texts.cancel,
            });
            if (!confirmation.isConfirmed) return;
            button.textContent = texts.running;
            root.querySelector('#language-pack-sync-message').textContent = texts.running;
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json();
            const message = payload.message || texts.failed;
            root.querySelector('#language-pack-sync-message').textContent = message;
            const body = root.querySelector('tbody');
            body.replaceChildren();
            root.querySelector('#language-pack-sync-totals').textContent = '';
            root.querySelector('#language-pack-sync-results').hidden = !payload.data?.results;
            if (payload.data?.results) {
                for (const result of payload.data.results) {
                    const row = document.createElement('tr');
                    const values = [result.surface, result.locale, texts.statuses[result.status],
                        ...Object.keys(texts.counts).map(key => result[key]), result.message];
                    for (const value of values) {
                        const cell = document.createElement('td');
                        cell.textContent = value ?? '';
                        row.appendChild(cell);
                    }
                    body.appendChild(row);
                }
                root.querySelector('#language-pack-sync-totals').textContent = Object.entries(payload.data.totals)
                    .map(([key, value]) => `${texts.counts[key]}: ${value}`).join(' · ');
            }
            Toast.fire({ icon: payload.success ? 'success' : 'error', title: message });
        } catch (error) {
            root.querySelector('#language-pack-sync-message').textContent = texts.failed;
            Toast.fire({ icon: 'error', title: texts.failed });
        } finally {
            pending = false;
            button.disabled = false;
            button.textContent = texts.submit;
        }
    });
});
