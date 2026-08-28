const root = document.querySelector('[data-votaciones-admin]');

if (root) {
    root.querySelectorAll('[data-auto-submit]').forEach((control) => {
        control.addEventListener('change', () => control.form?.requestSubmit());
    });

    root.querySelectorAll('[data-toggle-public-message]').forEach((checkbox) => {
        const target = root.querySelector(checkbox.dataset.togglePublicMessage);
        const refresh = () => {
            if (! target) return;
            target.hidden = ! checkbox.checked;
            target.querySelector('textarea')?.toggleAttribute('required', checkbox.checked);
        };
        checkbox.addEventListener('change', refresh);
        refresh();
    });

    root.querySelectorAll('form[data-disable-on-submit]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });
        }, { once: true });
    });
}
