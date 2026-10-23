export const normalizeRutInput = (value) => value.toUpperCase().replace(/[.\s-]/g, '');

const element = (tag, value, className = '') => {
    const node = document.createElement(tag);
    if (value !== undefined) node.textContent = value ?? 'Sin información';
    node.className = className;
    return node;
};

function identity(data, central = false) {
    const section = element('section', undefined, 'fc-data p-3 mb-3');
    const name = central ? [data.nombres, data.apellido_paterno, data.apellido_materno].filter(Boolean).join(' ') : data.nombre;
    section.append(element('h3', name || 'Nombre sin información', 'h5'), element('p', `RUT: ${data.rut_normalizado}`));
    if (central) {
        const list = element('dl', undefined, 'row mb-0');
        [['Unidad', data.unidad_departamento], ['Subdirección', data.subdireccion_dependencia], ['Jefatura', data.jefatura === 1 ? 'Sí' : 'No']].forEach(([label, value]) => {
            list.append(element('dt', label, 'col-sm-4'), element('dd', value, 'col-sm-8'));
        });
        section.append(list);
    } else {
        const wrapper = element('div', undefined, 'table-responsive');
        const table = element('table', undefined, 'table align-middle mb-0');
        const head = element('thead'); const row = element('tr');
        ['Establecimiento', 'RBD', 'Estatuto', 'Escalafón', 'Período'].forEach((label) => { const th = element('th', label); th.scope = 'col'; row.append(th); });
        head.append(row); table.append(head);
        const body = element('tbody');
        data.vinculos.forEach((link) => { const tr = element('tr'); ['establecimiento', 'rbd', 'estatuto', 'escalafon', 'periodo'].forEach((key) => tr.append(element('td', link[key]))); body.append(tr); });
        table.append(body); wrapper.append(table); section.append(wrapper);
    }
    return section;
}

function renderResult(target, data) {
    target.replaceChildren();
    const labels = { padron_actual: 'Funcionario de establecimiento · Padrón actual', autorizacion_activa: 'Administración Central · Autorización activa',
        revision_requerida: 'Información que requiere revisión', sin_antecedentes: 'Sin antecedentes en las fuentes disponibles',
        sin_vinculo_actual_confirmado: 'Antecedentes sin dependencia actual confirmada' };
    target.append(element('p', labels[data.estado] || data.estado, 'fc-state rounded-pill d-inline-block px-3 py-2 mb-3'));
    if (data.funcionario) target.append(identity(data.funcionario, data.clasificacion === 'administracion_central'));
    if (data.coincidencias) {
        if (data.coincidencias.establecimiento) { target.append(element('h3', 'Establecimientos', 'h5'), identity(data.coincidencias.establecimiento)); }
        data.coincidencias.administracion_central.forEach((match) => target.append(element('h3', 'Administración Central', 'h5'), identity(match, true)));
    }
    if (data.advertencias?.length) {
        const list = element('ul', undefined, 'alert alert-warning ps-4');
        data.advertencias.forEach((text) => list.append(element('li', text))); target.append(list);
    }
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-fc-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.fcConfirm)) event.preventDefault();
    }));
    document.querySelector('[data-fc-copy]')?.addEventListener('click', async () => {
        const status = document.querySelector('[data-fc-copy-status]');
        try { await navigator.clipboard.writeText(document.querySelector('[data-fc-token]').textContent.trim()); status.textContent = 'Token copiado.'; }
        catch { status.textContent = 'Selecciona el token y cópialo manualmente.'; }
    });
    const root = document.querySelector('[data-funcionario-consulta]');
    root?.querySelector('[data-fc-form]').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget; const button = form.querySelector('button');
        if (button.disabled) return;
        const panel = root.querySelector('[data-fc-result-panel]'); const result = root.querySelector('[data-fc-result]');
        const feedback = root.querySelector('[data-fc-feedback]'); const input = form.querySelector('[name=rut]');
        input.classList.remove('is-invalid'); result.replaceChildren();
        feedback.className = 'alert alert-info'; feedback.textContent = 'Consultando antecedentes…';
        button.disabled = true; panel.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value },
                body: JSON.stringify({ rut: normalizeRutInput(input.value) }), signal: AbortSignal.timeout(20000) });
            const data = await response.json();
            if (!response.ok) {
                feedback.className = 'alert alert-danger'; feedback.textContent = data.message || 'No fue posible completar la consulta.';
                if (response.status === 422) { input.classList.add('is-invalid'); root.querySelector('#fc-rut-error').textContent = data.message; input.focus(); }
                return;
            }
            feedback.className = 'alert d-none'; renderResult(result, data);
        } catch { feedback.className = 'alert alert-danger'; feedback.textContent = 'No fue posible completar la consulta. Revisa tu conexión e intenta nuevamente.'; }
        finally { button.disabled = false; panel.setAttribute('aria-busy', 'false'); }
    });
    window.addEventListener('pagehide', () => {
        document.querySelector('[data-fc-token]')?.replaceChildren();
        root?.querySelector('[data-fc-result]')?.replaceChildren();
    });
}
