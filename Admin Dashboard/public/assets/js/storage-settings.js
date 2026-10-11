'use strict';

(function () {
    function bindStorageDiskToggle() {
        const diskSelect = document.getElementById('storageDiskSelect');
        const s3Section = document.getElementById('section-s3-fields');
        const r2Section = document.getElementById('section-r2-fields');
        const localNotice = document.getElementById('section-local-notice');

        if (!diskSelect) return;

        const initialDisk = diskSelect.value;
        const syncWarning = document.getElementById('sync-warning-alert');

        function syncRequiredFields(section, selected) {
            if (!section) return;

            section.querySelectorAll('input[data-required-for]').forEach(el => {
                if (el.dataset.requiredFor === selected) {
                    el.setAttribute('required', 'required');
                } else {
                    el.removeAttribute('required');
                }
            });
        }

        function updateVisibility() {
            const selected = diskSelect.value;

            if (syncWarning) {
                syncWarning.style.display = selected !== initialDisk ? 'block' : 'none';
            }

            if (localNotice) {
                localNotice.style.display = selected === 'local' ? 'block' : 'none';
            }

            if (s3Section) {
                s3Section.style.display = selected === 's3' ? 'block' : 'none';
                syncRequiredFields(s3Section, selected);
            }

            if (r2Section) {
                r2Section.style.display = selected === 'r2' ? 'block' : 'none';
                syncRequiredFields(r2Section, selected);
            }
        }

        diskSelect.addEventListener('change', updateVisibility);
        updateVisibility();
        // Hide warning on initial load even if we called updateVisibility
        if (syncWarning) syncWarning.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', bindStorageDiskToggle);
})();
