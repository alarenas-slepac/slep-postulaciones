import '../css/dotacion-docente-asignaciones.css';

function initialize() {
    const modalElement = document.getElementById('confirmar-eliminar-horas-docente');
    let pending = null;
    let opener = null;
    const submitConfirmed = (form, submitter) => {
        form.dataset.horasConfirmed = '1';
        form.requestSubmit(submitter);
    };
    modalElement?.querySelector('[data-horas-confirm-delete]').addEventListener('click', () => {
        if (!pending) return;
        const { form, submitter } = pending;
        pending = null;
        submitConfirmed(form, submitter);
        window.bootstrap.Modal.getInstance(modalElement)?.hide();
    });
    modalElement?.addEventListener('hidden.bs.modal', () => {
        pending = null;
        if (opener?.isConnected && !opener.disabled) opener.focus();
        opener = null;
    });

    document.querySelectorAll('[data-docente-horas]').forEach(root => {
        const checkboxes = [...root.querySelectorAll('[data-horas-selection]')];
        const all = root.querySelector('[data-horas-select-all]');
        const bulk = root.querySelector('[data-horas-delete-selected]');
        const status = root.querySelector('[data-horas-selection-status]');
        const forms = [...root.querySelectorAll('[data-horas-delete]')];
        const selected = () => checkboxes.filter(input => input.checked);
        const sync = () => {
            const count = selected().length;
            if (all) { all.checked = count > 0 && count === checkboxes.length; all.indeterminate = count > 0 && count < checkboxes.length; }
            if (bulk) {
                bulk.disabled = count === 0;
                bulk.querySelector('[data-horas-bulk-label]').textContent = `Eliminar seleccionadas (${count})`;
            }
            if (status) status.textContent = count ? `${count} asignación(es) seleccionada(s) de este docente.` : 'Marque las asignaciones que desea eliminar de este docente.';
        };
        if (all) {
            root.querySelector('[data-horas-select-all-wrap]').classList.remove('d-none');
            all.addEventListener('change', () => { checkboxes.forEach(input => { input.checked = all.checked; }); sync(); });
        }
        checkboxes.forEach(input => input.addEventListener('change', sync));
        forms.forEach(form => {
            const button = form.querySelector('button[type="submit"]');
            const originalLabel = button.innerHTML;
            form.addEventListener('submit', event => {
                if (form.dataset.horasSubmitted === '1') { event.preventDefault(); return; }
                if (form.dataset.horasConfirmed === '1') {
                    delete form.dataset.horasConfirmed;
                    form.dataset.horasSubmitted = '1';
                    form.setAttribute('aria-busy', 'true');
                    button.disabled = true;
                    button.textContent = 'Eliminando…';
                    return;
                }
                event.preventDefault();
                const ids = form.dataset.horasDelete === 'bulk' ? selected().map(input => input.value) : [form.querySelector('[name="asignaciones[]"]').value];
                if (!ids.length) { sync(); return; }
                const summary = `Se eliminarán ${ids.length} asignación(es) de ${root.dataset.docenteNombre} en ${root.dataset.docenteAnio}.`;
                if (!modalElement || !window.bootstrap?.Modal) {
                    if (window.confirm(`${summary} Se recalcularán sus horas y saldo. Esta acción no se puede deshacer.`)) submitConfirmed(form, event.submitter || button);
                    return;
                }
                modalElement.querySelector('[data-horas-confirm-summary]').textContent = summary;
                const list = modalElement.querySelector('[data-horas-confirm-list]');
                list.replaceChildren();
                ids.forEach(id => {
                    const row = [...root.querySelectorAll('[data-horas-row]')].find(row => row.dataset.horasRow === id);
                    const item = document.createElement('li');
                    item.textContent = row?.dataset.horasDescription || 'Asignación seleccionada';
                    list.appendChild(item);
                });
                pending = { form, submitter: event.submitter || button };
                opener = event.submitter || button;
                window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
            });
            window.addEventListener('pageshow', () => {
                delete form.dataset.horasSubmitted;
                delete form.dataset.horasConfirmed;
                form.removeAttribute('aria-busy');
                button.disabled = false;
                button.innerHTML = originalLabel;
                sync();
            });
        });
        sync();
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
else initialize();
