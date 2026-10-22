const form = document.querySelector('[data-vr-form]');
if (form) {
    const formatter = new Intl.NumberFormat('es-CL');
    function recalcular() {
        form.querySelectorAll('[data-vr-ballot]').forEach((ballot) => {
            let validos = 0, emitidos = 0, completo = true;
            ballot.querySelectorAll('[data-vr-count]').forEach((input) => {
                const value = input.value.trim();
                const cantidad = Number(value);
                if (!/^\d+$/.test(value) || !Number.isSafeInteger(cantidad) || cantidad > 1000000) {
                    completo = false;
                    return;
                }
                emitidos += cantidad;
                if (input.dataset.validVote === '1') validos += cantidad;
            });
            ballot.querySelector('[data-vr-total="validos"]').textContent = completo ? formatter.format(validos) : '—';
            ballot.querySelector('[data-vr-total="emitidos"]').textContent = completo ? formatter.format(emitidos) : '—';
        });
    }
    form.addEventListener('input', () => {
        recalcular();
        document.querySelectorAll('[data-vr-publish]').forEach(button => { button.disabled = true; });
        const aviso = form.querySelector('[data-vr-dirty]');
        if (aviso) aviso.hidden = false;
    });
    form.addEventListener('submit', () => {
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
    });
    recalcular();
}
