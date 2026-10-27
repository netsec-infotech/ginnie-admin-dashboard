/**
 * Seller Scheduled Delivery & Dynamic Overrides Calendar JS
 */
function initSellerScheduledDelivery() {
    const cardContainer = document.getElementById('pills-scheduled-delivery');
    if (!cardContainer) return;

    const storeId = cardContainer.getAttribute('data-store-id');
    const closureCheckbox = document.getElementById('is_temporarily_closed');
    const closureContainer = document.getElementById('temporary_closure_reason_container');
    const toggleSwitch = document.getElementById('is_scheduled_delivery_enabled');
    const scheduledBody = document.getElementById('scheduled_delivery_body');

    // 1. Setup UI Toggles
    if (toggleSwitch && scheduledBody) {
        toggleSwitch.addEventListener('change', function () {
            scheduledBody.style.display = this.checked ? '' : 'none';
        });
    }

    if (closureCheckbox && closureContainer) {
        closureCheckbox.addEventListener('change', function () {
            closureContainer.style.display = this.checked ? '' : 'none';
        });
    }

    // 2. Setup Calendar Variables & DOM Elements
    const monthYearTitle = document.getElementById('sd-calendar-month-year');
    const daysGrid = document.getElementById('sd-calendar-days-grid');
    const prevMonthBtn = document.getElementById('sd-prev-month');
    const nextMonthBtn = document.getElementById('sd-next-month');
    const selectedDateTitle = document.getElementById('sd-selected-date-title');
    const slotsContainer = document.getElementById('sd-slots-list-container');
    const loadingEl = document.getElementById('sd-slots-list-loading');
    const emptyEl = document.getElementById('sd-slots-list-empty');

    const overrideModalEl = document.getElementById('sdOverrideModal');
    let overrideModalInstance = null;
    if (overrideModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        overrideModalInstance = bootstrap.Modal.getInstance(overrideModalEl) || new bootstrap.Modal(overrideModalEl);
    }

    // Custom Modal Display Helpers (Bootstrap 5 & jQuery & manual fallback)
    function showOverrideModal() {
        if (overrideModalInstance) {
            overrideModalInstance.show();
        } else if (overrideModalEl && typeof $ !== 'undefined' && $.fn.modal) {
            $(overrideModalEl).modal('show');
        } else if (overrideModalEl) {
            overrideModalEl.classList.add('show');
            overrideModalEl.style.display = 'block';
            overrideModalEl.removeAttribute('aria-hidden');
            overrideModalEl.setAttribute('aria-modal', 'true');
            // Backdrop helper
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
            document.body.classList.add('modal-open');
        }
    }

    function hideOverrideModal() {
        if (overrideModalInstance) {
            overrideModalInstance.hide();
        }
        if (overrideModalEl && typeof $ !== 'undefined' && $.fn.modal) {
            $(overrideModalEl).modal('hide');
        }
        if (overrideModalEl) {
            overrideModalEl.classList.remove('show');
            overrideModalEl.style.display = 'none';
            overrideModalEl.setAttribute('aria-hidden', 'true');
            overrideModalEl.removeAttribute('aria-modal');
        }
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    let currentDate = new Date();
    let selectedDateStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(currentDate.getDate()).padStart(2, '0')}`;
    let storeOverrides = [];

    // Set selected date title to today's date on initial render
    if (selectedDateTitle) {
        selectedDateTitle.textContent = currentDate.toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }

    // 3. Helper to determine if a date is selectable in the calendar overrides tab
    function isDaySelectable(dateStr) {
        const parts = dateStr.split('-');
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        
        const cellDate = new Date(year, month, day);
        cellDate.setHours(0, 0, 0, 0);
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Get max advance booking days from inputs
        const maxDaysInp = document.querySelector('input[name="max_advance_booking_days"]');
        const maxDays = maxDaysInp ? parseInt(maxDaysInp.value, 10) : 7;
        
        const maxDate = new Date(today);
        maxDate.setDate(maxDate.getDate() + maxDays);

        // Date must be between today and today + max_advance_booking_days
        if (cellDate < today || cellDate > maxDate) {
            return false;
        }

        // Date must not fall on a day of the week that is toggled off (closed) in timings
        const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const dayName = dayNames[cellDate.getDay()];
        const daySwitch = document.getElementById(`${dayName}-switch`);
        if (daySwitch && !daySwitch.checked) {
            return false;
        }

        return true;
    }

    // 4. Load Store Overrides list to show indicator dots on the calendar
    async function loadStoreOverrides(refreshSlots = true) {
        try {
            const res = await fetch(`/seller/stores/${storeId}/slot-overrides`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.success && data.data) {
                storeOverrides = data.data;
                renderCalendar();
                if (refreshSlots && selectedDateStr && isDaySelectable(selectedDateStr)) {
                    loadSlotsForDate(selectedDateStr);
                }
            }
        } catch (err) {
            console.error('Error fetching slot overrides:', err);
        }
    }

    // 5. Render Calendar Grid
    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        // Month Names
        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];
        monthYearTitle.textContent = `${monthNames[month]} ${year}`;

        // Clear existing days (keep headers)
        const headerLabels = daysGrid.querySelectorAll('.sd-calendar-day-label');
        daysGrid.innerHTML = '';
        headerLabels.forEach(label => daysGrid.appendChild(label));

        // Get first day of month and total days
        const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7; // Align Mon=0, Sun=6
        const totalDays = new Date(year, month + 1, 0).getDate();

        // Render empty day paddings
        for (let i = 0; i < firstDayIndex; i++) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'sd-calendar-day empty-day';
            daysGrid.appendChild(emptyDiv);
        }

        // Render day cells
        for (let dayNum = 1; dayNum <= totalDays; dayNum++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'sd-calendar-day';
            
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
            dayCell.setAttribute('data-date', dateStr);

            // Day Number
            const daySpan = document.createElement('span');
            daySpan.className = 'sd-day-number';
            daySpan.textContent = dayNum;
            dayCell.appendChild(daySpan);

            // Check if day is selectable (within booking window & open day of week)
            const selectable = isDaySelectable(dateStr);
            if (!selectable) {
                dayCell.classList.add('disabled-day');
                if (selectedDateStr === dateStr) {
                    selectedDateStr = null;
                    selectedDateTitle.textContent = 'Select a Date';
                    slotsContainer.style.display = 'none';
                    emptyEl.style.display = '';
                    emptyEl.innerHTML = `
                        <i class="ti ti-calendar-event fs-1 mb-2 d-block text-secondary"></i>
                        Select a date on the calendar to manage its slots.
                    `;
                }
            } else {
                if (selectedDateStr === dateStr) {
                    dayCell.classList.add('active-day');
                }

                // Indicators (only for selectable days)
                const indicatorsDiv = document.createElement('div');
                indicatorsDiv.className = 'sd-day-indicators';
                
                const dayOverrides = storeOverrides.filter(ov => ov.override_date === dateStr);
                const isBlocked = dayOverrides.some(ov => ov.is_blocked);
                const hasCapacityOverride = dayOverrides.some(ov => !ov.is_blocked && ov.capacity !== null);

                if (isBlocked) {
                    const dot = document.createElement('span');
                    dot.className = 'sd-indicator-dot sd-dot-blocked';
                    indicatorsDiv.appendChild(dot);
                }
                if (hasCapacityOverride) {
                    const dot = document.createElement('span');
                    dot.className = 'sd-indicator-dot sd-dot-capacity';
                    indicatorsDiv.appendChild(dot);
                }
                dayCell.appendChild(indicatorsDiv);

                // Click listener
                dayCell.addEventListener('click', function () {
                    daysGrid.querySelectorAll('.sd-calendar-day').forEach(cell => cell.classList.remove('active-day'));
                    this.classList.add('active-day');
                    selectedDateStr = dateStr;
                    selectedDateTitle.textContent = new Date(dateStr).toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                    });
                    loadSlotsForDate(dateStr);
                });
            }

            daysGrid.appendChild(dayCell);
        }
    }

    // Prev / Next month navigation
    if (prevMonthBtn && nextMonthBtn) {
        prevMonthBtn.addEventListener('click', function () {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });
        nextMonthBtn.addEventListener('click', function () {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });
    }

    // 6. Fetch and Render Virtual Slots for Selected Date
    async function loadSlotsForDate(dateStr) {
        loadingEl.style.display = '';
        emptyEl.style.display = 'none';
        slotsContainer.style.display = 'none';
        slotsContainer.innerHTML = '';

        try {
            // Read duration selection dynamically
            const durationInp = document.querySelector('select[name="scheduled_delivery_slot_duration"]');
            const duration = durationInp ? durationInp.value : '';

            // Load slots using seller resolved-slots endpoint
            const res = await fetch(`/seller/stores/${storeId}/resolved-slots?date=${dateStr}&duration=${duration}`, {
                headers: { 
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const resBody = await res.json();
            loadingEl.style.display = 'none';

            const data = resBody.data;

            if (res.ok && data && data.is_open && data.slots && data.slots.length > 0) {
                slotsContainer.style.display = '';

                data.slots.forEach(slot => {
                    const isBlocked = slot.is_blocked;
                    const customCapacity = slot.override_capacity;

                    const card = document.createElement('div');
                    card.className = `sd-slot-item-card ${isBlocked ? 'blocked-slot' : (customCapacity !== null ? 'override-slot' : '')}`;
                    
                    const formatTime = (timeStr) => {
                        const parts = timeStr.split(':');
                        const h = parseInt(parts[0], 10);
                        const m = parts[1] || '00';
                        return `${h > 12 ? h - 12 : (h === 0 ? 12 : h)}:${m} ${h >= 12 ? 'PM' : 'AM'}`;
                    };

                    card.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="sd-slot-time-title">
                                <i class="ti ti-clock"></i>
                                <span>${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}</span>
                            </div>
                            <div>
                                ${isBlocked 
                                    ? '<span class="badge bg-danger-lt">Blocked</span>' 
                                    : (customCapacity !== null 
                                        ? `<span class="badge bg-warning-lt">Override Capacity: ${customCapacity}</span>` 
                                        : `<span class="badge bg-success-lt">Default Capacity: ${slot.default_capacity}</span>`
                                      )
                                }
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            ${isBlocked 
                                ? `<button type="button" class="btn btn-sm btn-outline-success btn-unblock" data-start="${slot.start_time}" data-end="${slot.end_time}" data-override-id="${slot.override_id}">
                                    <i class="ti ti-check"></i> Unblock Slot
                                   </button>`
                                : `<button type="button" class="btn btn-sm btn-outline-danger btn-block" data-start="${slot.start_time}" data-end="${slot.end_time}">
                                    <i class="ti ti-ban"></i> Block Slot
                                   </button>
                                   <button type="button" class="btn btn-sm btn-outline-primary btn-capacity" data-start="${slot.start_time}" data-end="${slot.end_time}" data-capacity="${customCapacity !== null ? customCapacity : ''}" data-default-capacity="${slot.default_capacity}">
                                    <i class="ti ti-edit"></i> Edit Capacity
                                   </button>`
                            }
                        </div>
                    `;

                    // Unblock action
                    const unblockBtn = card.querySelector('.btn-unblock');
                    if (unblockBtn) {
                        unblockBtn.addEventListener('click', () => {
                            const overrideId = unblockBtn.getAttribute('data-override-id');
                            deleteOverride(overrideId);
                        });
                    }

                    // Block action
                    const blockBtn = card.querySelector('.btn-block');
                    if (blockBtn) {
                        blockBtn.addEventListener('click', () => {
                            const start = blockBtn.getAttribute('data-start');
                            const end = blockBtn.getAttribute('data-end');
                            saveOverride(dateStr, start, end, true, null);
                        });
                    }

                    // Edit capacity action
                    const capBtn = card.querySelector('.btn-capacity');
                    if (capBtn) {
                        capBtn.addEventListener('click', () => {
                            const start = capBtn.getAttribute('data-start');
                            const end = capBtn.getAttribute('data-end');
                            const currentVal = capBtn.getAttribute('data-capacity');
                            const defaultVal = capBtn.getAttribute('data-default-capacity');

                            document.getElementById('override_slot_start').value = start;
                            document.getElementById('override_slot_end').value = end;
                            document.getElementById('override_slot_time_display').value = `${formatTime(start)} - ${formatTime(end)}`;
                            document.getElementById('override_slot_capacity').value = currentVal || defaultVal || '10';

                            showOverrideModal();
                        });
                    }

                    slotsContainer.appendChild(card);
                });
            } else {
                slotsContainer.style.display = 'none';
                emptyEl.style.display = '';
                emptyEl.innerHTML = `
                    <i class="ti ti-calendar-off fs-1 mb-2 d-block text-secondary"></i>
                    ${resBody.message || 'No slots available for this day.'}
                `;
            }
        } catch (err) {
            loadingEl.style.display = 'none';
            emptyEl.style.display = '';
            emptyEl.innerHTML = '<i class="ti ti-alert-triangle fs-1 d-block text-danger"></i> Failed to load slots.';
            console.error('Error loading slots for date:', err);
        }
    }

    // 7. Save Override Action (with confirmation warning count handling)
    async function saveOverride(dateStr, startTime, endTime, isBlocked, capacity, confirmed = false) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        try {
            const response = await fetch(`/seller/stores/${storeId}/slot-overrides`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    override_date: dateStr,
                    start_time: startTime,
                    end_time: endTime,
                    is_blocked: isBlocked ? 1 : 0,
                    capacity: capacity,
                    confirmed: confirmed ? 1 : 0
                })
            });

            const resData = await response.json();

            // Handle active orders warning preview
            if (response.status === 202 && resData.data?.warning) {
                const count = resData.data.affected_orders_count;
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Confirm Override',
                        text: `Warning: there ${count === 1 ? 'is 1 active order' : `are ${count} active orders`} scheduled in this time slot on ${dateStr}. Blocking or reducing capacity will immediately flag them as requiring rescheduling. Proceed anyway?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed and flag orders'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            saveOverride(dateStr, startTime, endTime, isBlocked, capacity, true);
                        }
                    });
                } else {
                    if (confirm(`Warning: there are ${count} active orders in this slot. Save anyway?`)) {
                        saveOverride(dateStr, startTime, endTime, isBlocked, capacity, true);
                    }
                }
                return;
            }

            if (response.ok && resData.success) {
                hideOverrideModal();
                if (typeof Toast !== 'undefined') {
                    Toast.fire({ icon: 'success', title: 'Slot override saved successfully.' });
                }
                await loadStoreOverrides(false);
                if (selectedDateStr) {
                    loadSlotsForDate(selectedDateStr);
                }
            } else {
                const errorText = resData.message || 'Failed to save override.';
                if (typeof Toast !== 'undefined') {
                    Toast.fire({ icon: 'error', title: errorText });
                } else {
                    alert(errorText);
                }
            }
        } catch (err) {
            console.error('Error saving override:', err);
        }
    }

    // 8. Delete Override Action
    async function deleteOverride(overrideId) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            const response = await fetch(`/seller/stores/${storeId}/slot-overrides/${overrideId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const resData = await response.json();
            if (response.ok && resData.success) {
                if (typeof Toast !== 'undefined') {
                    Toast.fire({ icon: 'success', title: 'Slot override deleted successfully.' });
                }
                await loadStoreOverrides(false);
                if (selectedDateStr) {
                    loadSlotsForDate(selectedDateStr);
                }
            } else {
                if (typeof Toast !== 'undefined') {
                    Toast.fire({ icon: 'error', title: resData.message || 'Failed to delete override.' });
                }
            }
        } catch (err) {
            console.error('Error deleting override:', err);
        }
    }

    // Save Capacity Button Listener in modal
    const saveCapacityBtn = document.getElementById('btn-save-sd-override');
    if (saveCapacityBtn) {
        saveCapacityBtn.addEventListener('click', function () {
            const start = document.getElementById('override_slot_start').value;
            const end = document.getElementById('override_slot_end').value;
            const capacity = document.getElementById('override_slot_capacity').value;

            if (selectedDateStr && start && end) {
                saveOverride(selectedDateStr, start, end, false, parseInt(capacity, 10) || 0);
            }
        });
    }

    // 9. Attach change listeners to settings controls to refresh calendar or slot generation immediately
    const durationSelect = document.querySelector('select[name="scheduled_delivery_slot_duration"]');
    if (durationSelect) {
        durationSelect.addEventListener('change', () => {
            if (selectedDateStr) {
                loadSlotsForDate(selectedDateStr);
            }
        });
    }

    const maxDaysInput = document.querySelector('input[name="max_advance_booking_days"]');
    if (maxDaysInput) {
        maxDaysInput.addEventListener('input', () => {
            renderCalendar();
        });
    }

    document.querySelectorAll('.day-switch').forEach(sw => {
        sw.addEventListener('change', () => {
            renderCalendar();
        });
    });

    // Initialize overrides & calendar
    loadStoreOverrides();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSellerScheduledDelivery);
} else {
    initSellerScheduledDelivery();
}
