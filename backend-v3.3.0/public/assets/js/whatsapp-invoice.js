'use strict';

(function () {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.js-whatsapp-invoice');
        if (!button) return;

        event.preventDefault();

        button.disabled = true;
        try {
            const { data } = await axios.post(button.dataset.url, { id: button.dataset.id }, {
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });

            if (data?.success) {
                Toast.fire({ icon: 'success', title: data.message || 'Invoice WhatsApp message queued.' });
                return;
            }

            Toast.fire({ icon: 'error', title: data?.message || 'Unable to queue WhatsApp invoice.' });
        } catch (error) {
            Toast.fire({
                icon: 'error',
                title: error?.response?.data?.message || error?.message || 'Unable to queue WhatsApp invoice.',
            });
        } finally {
            button.disabled = false;
        }
    });
})();
