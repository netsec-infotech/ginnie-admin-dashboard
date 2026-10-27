(function () {
    document.querySelectorAll('[data-weekly-schedule]').forEach(component => {
        const days = [...component.querySelectorAll('[data-schedule-day]')];
        const rows = [...component.querySelectorAll('[data-schedule-row]')];
        const buttons = [...component.querySelectorAll('[data-schedule-edit]')];
        const fields = component.querySelector('[data-schedule-fields]');
        const error = component.querySelector('[data-schedule-error]');
        const reveal = (row, expanded) => {
            const day = row.querySelector('[data-schedule-day]').value;
            const button = buttons.find(button => button.dataset.scheduleEdit === day);
            row.classList.toggle('d-none', !expanded);
            button.classList.toggle('active', expanded);
            button.classList.toggle('btn-primary', expanded);
            button.classList.toggle('btn-outline-primary', !expanded);
            button.setAttribute('aria-expanded', String(expanded));
        };
        buttons.forEach(button => button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') !== 'true';
            rows.forEach(row => reveal(row, expanded && row.querySelector('[data-schedule-day]').value === button.dataset.scheduleEdit));
        }));
        const update = () => {
            const enabled = component.querySelector('[data-schedule-mode]:checked')?.value === '1';
            const missingDay = enabled && !days.some(day => day.checked);
            fields.disabled = !enabled;
            fields.classList.toggle('d-none', !enabled);
            component.querySelector('[data-schedule-always]').classList.toggle('d-none', enabled);
            error.classList.toggle('d-none', !missingDay);
            days[0]?.setCustomValidity(missingDay ? error.textContent.trim() : '');
            rows.forEach(row => {
                const day = row.querySelector('[data-schedule-day]');
                const selected = day.checked;
                const indicator = buttons.find(button => button.dataset.scheduleEdit === day.value).querySelector('[data-schedule-indicator]');
                indicator.textContent = selected ? indicator.dataset.availableLabel : indicator.dataset.closedLabel;
                row.querySelectorAll('[data-schedule-input]').forEach(input => input.classList.toggle('d-none', !selected));
                row.querySelector('[data-schedule-closed]').classList.toggle('d-none', selected);
                row.querySelectorAll('[data-schedule-time]').forEach(input => {
                    input.disabled = !enabled || !selected;
                    input.required = enabled && selected;
                });
                const start = row.querySelector('[data-schedule-time]');
                const end = row.querySelector('[data-schedule-end]');
                end.setCustomValidity(enabled && selected && start.value && end.value && end.value <= start.value ? end.dataset.timeError : '');
            });
            component.dispatchEvent(new CustomEvent('schedule:change', {bubbles: true, detail: {enabled}}));
        };
        component.querySelectorAll('[data-schedule-mode], [data-schedule-day]').forEach(input => input.addEventListener('change', update));
        component.querySelectorAll('[data-schedule-time]').forEach(input => input.addEventListener('input', update));
        rows.forEach(row => row.addEventListener('invalid', () => reveal(row, true), true));
        update();
    });
}());
