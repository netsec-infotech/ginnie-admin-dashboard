'use strict';

document.addEventListener('DOMContentLoaded', function () {
    initMap();
    initStarRatings();
    initTabDatatableAdjust();
    initVerificationStatusForm();
});

function initVerificationStatusForm() {
    var modal = document.getElementById('verificationStatusModal');
    if (!modal) return;

    var form = modal.querySelector('form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var submitBtn = form.querySelector('button[type="submit"]');
        var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
        }

        var formData = new FormData(form);
        var action = form.getAttribute('action');

        window.axios.post(action, formData)
            .then(function (response) {
                var data = response.data || {};
                if (data.success) {
                    var deliveryBoy = data.data || {};
                    var rawStatus = deliveryBoy.verification_status?.value || deliveryBoy.verification_status || '';
                    var remark = deliveryBoy.verification_remark || '';

                    // Update all verification status badges on page
                    var badgeClass = 'bg-warning-lt border-warning-subtle';
                    if (rawStatus === 'verified') {
                        badgeClass = 'bg-success-lt border-success-subtle';
                    } else if (rawStatus === 'rejected') {
                        badgeClass = 'bg-danger-lt border-danger-subtle';
                    }

                    var statusFormatted = rawStatus.replace(/_/g, ' ');
                    statusFormatted = statusFormatted.charAt(0).toUpperCase() + statusFormatted.slice(1);

                    document.querySelectorAll('[data-role="verification-badge"]').forEach(function (badge) {
                        badge.className = 'badge border p-2 ' + badgeClass;
                        badge.textContent = statusFormatted;
                    });

                    // Update verification remark
                    var remarkEl = document.querySelector('[data-role="verification-remark"]');
                    var remarkRow = document.getElementById('verificationRemarkRow');
                    if (remarkEl) {
                        remarkEl.textContent = remark;
                    }
                    if (remarkRow) {
                        if (remark && remark.trim() !== '') {
                            remarkRow.classList.remove('d-none');
                        } else {
                            remarkRow.classList.add('d-none');
                        }
                    }

                    // Hide modal
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        } else {
                            $(modal).modal('hide');
                        }
                    } else {
                        $(modal).modal('hide');
                    }

                    if (typeof Toast !== 'undefined') {
                        Toast.fire({
                            icon: 'success',
                            title: data.message || 'Verification status updated successfully'
                        });
                    }
                } else {
                    if (typeof Toast !== 'undefined') {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'Failed to update verification status'
                        });
                    }
                }
            })
            .catch(function (error) {
                var message = error.response?.data?.message || 'Something went wrong';
                if (typeof Toast !== 'undefined') {
                    Toast.fire({
                        icon: 'error',
                        title: message
                    });
                }
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });
    });
}

function initMap() {
    var config = window.__DB_DETAIL_CONFIG__;
    if (!config || !config.hasLocation) {
        var mapEl = document.getElementById('delivery-boy-map');
        if (mapEl) {
            mapEl.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted"><i class="ti ti-map-pin-off me-2"></i>No location data available</div>';
            mapEl.style.background = '#f8f9fa';
        }
        return;
    }

    var lat = config.latitude;
    var lng = config.longitude;

    var map = L.map('delivery-boy-map').setView([lat, lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    L.marker([lat, lng])
        .addTo(map)
        .bindPopup('<strong>' + escapeHtml(config.name) + '</strong><br>Last known location')
        .openPopup();

    // Fix tile rendering when map is inside a hidden tab
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            map.invalidateSize();
        });
    });
}

function initStarRatings() {
    document.querySelectorAll('.rating-stars').forEach(function (element) {
        if (typeof StarRating === 'undefined') return;
        new StarRating(element, {
            tooltip: false,
            clearable: false,
            readOnly: true,
            initialRating: element.dataset.rating,
            stars: function (el) {
                el.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01L12 2z"/></svg>';
            },
        });
    });
}

/**
 * DataTables inside hidden tabs need a columns.adjust() call once the tab becomes visible.
 */
function initTabDatatableAdjust() {
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                var dtTables = $.fn.dataTable.tables({ visible: true, api: true });
                if (dtTables) {
                    if (dtTables.columns) {
                        dtTables.columns.adjust();
                    }
                    if (dtTables.responsive && typeof dtTables.responsive.recalc === 'function') {
                        dtTables.responsive.recalc();
                    }
                }
            }
        });
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
