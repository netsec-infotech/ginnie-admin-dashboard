document.addEventListener('DOMContentLoaded', function () {
    var labels = window.productBadgeLabels || {};

    if (!window.TomSelect || !labels.badgeSearchUrl) {
        return;
    }

    function buildBadgeSearchUrl(params) {
        return labels.badgeSearchUrl + '?' + new URLSearchParams(params).toString();
    }

    function initBadgeSelect(id, placeholder, allowEmpty) {
        var el = document.getElementById(id);

        if (!el) {
            return null;
        }

        if (el.tomselect) {
            return el.tomselect;
        }

        el.innerHTML = allowEmpty ? '<option value=""></option>' : '';

        return new TomSelect(el, {
            copyClassesToDropdown: false,
            controlInput: '<input>',
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            maxOptions: 20,
            placeholder: placeholder || '',
            load: function (query, callback) {
                if (!query.length) {
                    callback();
                    return;
                }

                fetch(buildBadgeSearchUrl({ q: query }))
                    .then(function (response) { return response.json(); })
                    .then(function (json) { callback(json); })
                    .catch(function () { callback(); });
            }
        });
    }

    function setBadgeSelectValue(tomSelect, badgeId) {
        if (!tomSelect) {
            return;
        }

        if (!badgeId) {
            tomSelect.clear(true);
            return;
        }

        fetch(buildBadgeSearchUrl({ find_id: badgeId }))
            .then(function (response) { return response.json(); })
            .then(function (json) {
                var option = Array.isArray(json) ? json[0] : null;

                if (!option) {
                    tomSelect.clear(true);
                    return;
                }

                tomSelect.clearOptions();
                tomSelect.addOption(option);
                tomSelect.setValue(String(option.value), true);
            })
            .catch(function () {
                tomSelect.clear(true);
            });
    }

    var filterSelect = initBadgeSelect('productBadgeFilter', labels.badgePlaceholder || 'Badge', false);
    var bulkSelect = initBadgeSelect('bulk-badge-select', labels.selectBadgePlaceholder || 'Select a badge', false);
    var assignSelect = initBadgeSelect('assign-badge-select', labels.noBadgePlaceholder || 'No Badge', true);

    var bulkModal = document.getElementById('bulk-badge-modal');
    if (bulkModal) {
        bulkModal.addEventListener('show.bs.modal', function () {
            if (bulkSelect) {
                bulkSelect.clear(true);
            }
        });
    }

    var assignModal = document.getElementById('assign-badge-modal');
    if (assignModal) {
        assignModal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            var badgeId = trigger ? trigger.getAttribute('data-badge-id') : '';

            setTimeout(function () {
                setBadgeSelectValue(assignSelect, badgeId);
            }, 0);
        });
    }

    window.productBadgeTomSelect = {
        filter: filterSelect,
        bulk: bulkSelect,
        assign: assignSelect,
        setValue: setBadgeSelectValue
    };
});
