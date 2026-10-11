'use strict';

(function () {

    // ── Budget range slider + live estimator (create page) ──��───────────────

    const range      = document.getElementById('budgetRange');
    const hidden     = document.getElementById('budgetHidden');
    const display    = document.getElementById('budgetDisplay');
    const estClicks  = document.getElementById('estClicks');
    const estReach   = document.getElementById('estReach');
    const warning    = document.getElementById('balanceWarning');

    function fmtNum(n) {
        if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
        return Math.round(n).toLocaleString();
    }

    function updateSlider(value) {
        if (!range) return;

        const min           = parseFloat(range.min) || 1;
        const max           = parseFloat(range.max) || 5000;
        const cpc           = parseFloat(range.dataset.cpc) || 0;
        const wallet        = parseFloat(range.dataset.wallet) || 0;
        const symbol        = range.dataset.symbol || '';
        const multiplierMin = parseInt(range.dataset.multiplierMin) || 12;
        const multiplierMax = parseInt(range.dataset.multiplierMax) || 20;

        const pct = ((value - min) / (max - min)) * 100;
        range.style.setProperty('--pct', pct + '%');

        if (display)  display.textContent = symbol + Number(value).toLocaleString(undefined, { minimumFractionDigits: 0 });
        if (hidden)   hidden.value = value;

        if (cpc > 0 && estClicks) {
            const clicks    = Math.floor(value / cpc);
            const reachLow  = clicks * multiplierMin;
            const reachHigh = clicks * multiplierMax;
            estClicks.textContent = fmtNum(clicks);
            if (estReach) estReach.textContent = fmtNum(reachLow) + '–' + fmtNum(reachHigh);
        } else {
            if (estClicks) estClicks.textContent = '—';
            if (estReach)  estReach.textContent  = '—';
        }

        if (warning) {
            warning.style.display = (wallet > 0 && value > wallet) ? 'block' : 'none';
        }

        document.querySelectorAll('.budget-chip').forEach(chip => {
            chip.classList.toggle('active', parseFloat(chip.dataset.value) === parseFloat(value));
        });
    }

    if (range) {
        updateSlider(range.value);
        range.addEventListener('input', function () {
            updateSlider(this.value);
        });
    }

    document.querySelectorAll('.budget-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            const val = parseFloat(this.dataset.value);
            if (!range) return;
            range.value = val;
            updateSlider(val);
        });
    });

    // ── Product selector → preview chip (create page) ───────────────────────

    const productSelect  = document.getElementById('select-product') || document.getElementById('product_id');
    const previewWrap    = document.getElementById('adPreviewWrap');
    const previewName    = document.getElementById('previewName');
    const previewThumb   = document.getElementById('previewThumb');

    function applyProductPreview(value, labelText, imageUrl) {
        if (value) {
            if (previewName)  previewName.textContent = labelText;
            if (previewThumb) {
                if (imageUrl) {
                    previewThumb.style.backgroundImage    = 'url(' + imageUrl + ')';
                    previewThumb.style.backgroundSize     = 'cover';
                    previewThumb.style.backgroundPosition = 'center';
                    previewThumb.style.backgroundColor    = 'transparent';
                } else {
                    previewThumb.style.backgroundImage  = '';
                    previewThumb.style.backgroundColor  = '#f1f3f5';
                }
            }
            if (previewWrap) previewWrap.style.display = 'block';
        } else {
            if (previewWrap) previewWrap.style.display = 'none';
        }
    }

    function bindProductSelect() {
        if (!productSelect) return;

        const ts = productSelect.tomselect;
        if (ts) {
            ts.on('change', function (value) {
                const opt = ts.options[value];
                applyProductPreview(value, opt ? opt.text : '', opt ? opt.image_url : '');
            });
        } else {
            productSelect.addEventListener('change', function () {
                const sel = this.options[this.selectedIndex];
                applyProductPreview(sel && sel.value, sel ? sel.text : '', '');
            });
        }
    }

    setTimeout(bindProductSelect, 0);

    // ── DataTable filter + actions (listing page) ───────────────────────────

    $(document).ready(function () {
        var tableEl = $('#ad-campaigns-table');
        if (!tableEl.length) return;

        var table = tableEl.DataTable();

        $('#statusFilter').on('change', function () {
            table.ajax.reload(null, false);
        });

        table.on('preXhr.dt', function (e, settings, data) {
            data.status = $('#statusFilter').val();
        });

        // Pause
        $(document).on('click', '.btn-pause-campaign', function () {
            var url = this.dataset.url;
            Swal.fire({
                title: 'Pause Campaign?',
                text: 'You can resume it at any time from your dashboard.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Pause',
                confirmButtonColor: '#f76707',
            }).then(function (result) {
                if (result.isConfirmed) {
                    campaignAction(url, function () {
                        table.ajax.reload(null, false);
                    });
                }
            });
        });

        // Resume
        $(document).on('click', '.btn-resume-campaign', function () {
            var url = this.dataset.url;
            Swal.fire({
                title: 'Resume Campaign?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Resume',
                confirmButtonColor: '#2fb344',
            }).then(function (result) {
                if (result.isConfirmed) {
                    campaignAction(url, function () {
                        table.ajax.reload(null, false);
                    });
                }
            });
        });
    });

    function campaignAction(url, onSuccess) {
        axios.post(url, {}, {
            headers: { 'X-CSRF-TOKEN': csrfToken },
        }).then(function (response) {
            var data = response.data;
            if (data.success) {
                Toast.fire({ icon: 'success', title: data.message });
                if (onSuccess) onSuccess();
            } else {
                Toast.fire({ icon: 'error', title: data.message });
            }
        }).catch(function () {
            Toast.fire({ icon: 'error', title: 'Something went wrong.' });
        });
    }

})();
