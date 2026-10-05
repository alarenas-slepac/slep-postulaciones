export function normalizeSearch(value) {
    return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
}

export function matchesNeed(need, filters) {
    return (!filters.section || need.section === filters.section)
        && (!filters.course || need.course === filters.course)
        && (!filters.pending || need.pending)
        && matchesSearch(need.search, filters.q);
}

function matchesSearch(search, query) {
    const text = normalizeSearch(search);
    return normalizeSearch(query).split(/\s+/).every(word => {
        if (text.includes(word)) return true;
        const rut = word.replace(/[.\-]/g, '');
        return /^\d{6,9}[\dk]$/.test(rut) && text.replace(/[.\-]/g, '').includes(rut);
    });
}

export function matchesCatalog(item, filters) {
    return (!filters.state || String(item.states || '').split(' ').includes(filters.state))
        && matchesSearch(item.search, filters.q);
}

export function initDotacionCatalog(root) {
    const panel = root.querySelector('[data-catalog-filters]');
    if (!panel || root.dataset.catalogInitialized) return;
    root.dataset.catalogInitialized = 'true';
    const rows = [...root.querySelectorAll('[data-catalog-row]')];
    const controls = Object.fromEntries([...panel.querySelectorAll('[data-catalog-filter]')].map(c => [c.dataset.catalogFilter, c]));
    const groups = [...root.querySelectorAll('[data-catalog-group]')].map(group => ({ group, rows: [...group.querySelectorAll('[data-catalog-row]')] }));
    const url = new URL(window.location.href);
    const param = key => `catalog_${root.dataset.dotacionCatalog}_${key}`;
    Object.entries(controls).forEach(([key, control]) => {
        control.value = url.searchParams.get(param(key)) || '';
        if (control.tagName === 'SELECT' && control.selectedIndex < 0) control.value = '';
    });
    const apply = () => {
        const filters = { q: controls.q.value, state: controls.state.value };
        let count = 0;
        rows.forEach(row => {
            const failed = row.dataset.catalogError === '1';
            row.hidden = !failed && !matchesCatalog({ search: row.dataset.catalogSearch, states: row.dataset.catalogState }, filters);
            if (!row.hidden) {
                count++;
                if (filters.q || filters.state || failed) openAncestors(row, root);
            }
        });
        groups.forEach(({ group, rows }) => { group.hidden = !!(filters.q || filters.state) && !rows.some(row => !row.hidden); });
        panel.querySelector('[data-catalog-count]').textContent = `${count} de ${rows.length} registros visibles.${rows.some(row => row.dataset.catalogError === '1') ? ' Se mantiene visible el formulario con errores.' : ''}`;
        root.querySelector('[data-catalog-empty]').hidden = count > 0;
        Object.entries(filters).forEach(([key, value]) => value ? url.searchParams.set(param(key), value) : url.searchParams.delete(param(key)));
        window.history.replaceState(window.history.state, '', url);
        root.dispatchEvent(new Event('dotacion:visibility', { bubbles: true }));
    };
    panel.hidden = false;
    controls.q.addEventListener('input', apply);
    controls.state.addEventListener('change', apply);
    panel.querySelector('[data-catalog-reset]').addEventListener('click', () => {
        controls.q.value = ''; controls.state.value = ''; apply(); controls.q.focus();
    });
    apply();
    // El contexto de regreso no debe cerrar los niveles que contienen errores.
    window.addEventListener('load', apply, { once: true });
}

function revealHashTarget() {
    let id;
    try { id = decodeURIComponent(window.location.hash.slice(1)); } catch (error) { return; }
    const target = id ? document.getElementById(id) : null;
    const workspace = target?.closest('.dotacion-workspace');
    if (!workspace) return;
    openAncestors(target, workspace);
    workspace.dispatchEvent(new Event('dotacion:visibility', { bubbles: true }));
    window.requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
}

function initSaveFeedback(form) {
    form.querySelectorAll('[data-save-field-error]').forEach(message => {
        const field = form.elements.namedItem(message.dataset.saveFieldError);
        if (!field || field.type === 'hidden') return;
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' '));
    });
    form.addEventListener('submit', event => {
        if (event.defaultPrevented || !form.checkValidity()) return;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.dataset.originalLabel = button.innerHTML;
            button.dataset.originalDisabled = button.disabled ? '1' : '0';
            button.disabled = true;
            button.textContent = 'Guardando…';
        });
        form.setAttribute('aria-busy', 'true');
    });
}

export function matchesRevision(person, filters) {
    return (!filters.block || String(person.blocks || '').split(' ').includes(filters.block))
        && (!filters.state || String(person.states || '').split(' ').includes(filters.state))
        && matchesSearch(person.search, filters.q);
}

export function initDotacionRevision(root) {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const panel = root.querySelector('[data-revision-filters]');
    if (!panel) return;
    const rows = Array.from(root.querySelectorAll('[data-revision-row]'));
    const related = new Map();
    root.querySelectorAll('[data-revision-related]').forEach(row => {
        const key = row.dataset.revisionRelated;
        if (!related.has(key)) related.set(key, []);
        related.get(key).push(row);
    });
    const controls = Object.fromEntries(Array.from(panel.querySelectorAll('[data-revision-filter]'), control => [control.dataset.revisionFilter, control]));
    const scope = root.dataset.dotacionRevision;
    const url = new URL(window.location.href);
    const storageKey = `dotacion-revision:${url.pathname}:${url.searchParams.get('anio') || ''}:${scope}`;
    const param = key => `revision_${scope}_${key}`;
    let saved;
    try {
        saved = JSON.parse(window.sessionStorage.getItem(storageKey) || 'null');
        window.sessionStorage.removeItem(storageKey);
    } catch (error) { /* La vista sigue operativa si el navegador no admite almacenamiento. */ }
    const hasExplicitFilters = Object.keys(controls).some(key => url.searchParams.has(param(key)));
    Object.entries(controls).forEach(([key, control]) => {
        const value = hasExplicitFilters ? url.searchParams.get(param(key))
            : saved && Date.now() - saved.at < 1800000 ? saved.filters?.[key] : '';
        control.value = value || '';
        // Un valor antiguo no válido no debe dejar un selector sin opción activa.
        if (control.tagName === 'SELECT' && control.selectedIndex < 0) control.value = '';
    });
    const filters = () => Object.fromEntries(Object.entries(controls).map(([key, control]) => [key, control.value]));
    const openFailed = () => rows.filter(row => row.dataset.revisionError === '1').forEach(row => {
        (related.get(row.dataset.revisionRow) || []).forEach(detail => {
            const collapse = detail.matches('.collapse') ? detail : detail.querySelector('.collapse');
            if (!collapse) return;
            collapse.classList.add('show');
            root.querySelectorAll('[aria-controls]').forEach(button => {
                if (button.getAttribute('aria-controls') === collapse.id) button.setAttribute('aria-expanded', 'true');
            });
        });
    });
    const apply = () => {
        const current = filters();
        let count = 0;
        let failed = 0;
        rows.forEach(row => {
            const error = row.dataset.revisionError === '1';
            const visible = error || matchesRevision({ blocks: row.dataset.revisionBlock, states: row.dataset.revisionState, search: row.dataset.revisionSearch }, current);
            row.hidden = !visible;
            (related.get(row.dataset.revisionRow) || []).forEach(detail => { detail.hidden = !visible; });
            if (visible) count++;
            if (error) failed++;
        });
        root.querySelectorAll('[data-revision-group]').forEach(group => {
            const groupRows = Array.from(group.querySelectorAll('[data-revision-row]'));
            // Sin filtros, mantener visibles los mensajes de bloques vacíos.
            group.hidden = Object.values(current).some(Boolean) && !groupRows.some(row => !row.hidden);
        });
        panel.querySelector('[data-revision-count]').textContent = `${count} de ${rows.length} registros visibles.${failed ? ' Se mantiene visible el formulario con errores.' : ''}`;
        root.querySelector('[data-revision-empty]').hidden = count > 0;
        Object.entries(current).forEach(([key, value]) => {
            if (value) url.searchParams.set(param(key), value);
            else url.searchParams.delete(param(key));
        });
        window.history.replaceState(window.history.state, '', url);
        openFailed();
    };
    panel.hidden = false;
    controls.q.addEventListener('input', apply);
    [controls.block, controls.state].forEach(control => control.addEventListener('change', apply));
    panel.querySelector('[data-revision-reset]').addEventListener('click', () => {
        Object.values(controls).forEach(control => { control.value = ''; });
        apply();
        controls.q.focus();
    });
    const save = () => {
        try { window.sessionStorage.setItem(storageKey, JSON.stringify({ at: Date.now(), filters: filters() })); } catch (error) {}
    };
    root.addEventListener('submit', event => { if (!event.defaultPrevented) save(); });
    root.querySelectorAll('[data-dotacion-contexto-salida]').forEach(link => link.addEventListener('click', event => {
        if (event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) save();
    }));
    apply();
    window.addEventListener('load', openFailed, { once: true });
}

function openAncestors(element, root) {
    for (let parent = element.parentElement; parent && parent !== root; parent = parent.parentElement) {
        if (parent.matches('details[data-dotacion-editor]')) parent.open = true;
        if (!parent.classList.contains('collapse')) continue;
        parent.classList.add('show');
        root.querySelectorAll('[aria-controls]').forEach(button => {
            if (button.getAttribute('aria-controls') !== parent.id) return;
            button.setAttribute('aria-expanded', 'true');
            button.classList.remove('collapsed');
        });
    }
}

export function initDotacionAsignacion(root) {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const filterPanel = root.querySelector('[data-dotacion-filters]');
    const needs = Array.from(root.querySelectorAll('[data-dotacion-need]'));
    if (filterPanel) {
        // Asociar las filas una sola vez evita recorrer toda la tabla por cada necesidad al escribir.
        const relatedByNeed = new Map();
        root.querySelectorAll('[data-dotacion-related]').forEach(row => {
            const key = row.dataset.dotacionRelated;
            if (!relatedByNeed.has(key)) relatedByNeed.set(key, []);
            relatedByNeed.get(key).push(row);
        });
        const failedNeeds = new Set(needs.filter(row => row.querySelector('[data-dotacion-form-errors]')
            || (relatedByNeed.get(row.dataset.dotacionNeed) || []).some(detail => detail.querySelector('[data-dotacion-form-errors]'))));
        const controls = Object.fromEntries(Array.from(filterPanel.querySelectorAll('[data-filter]'), input => [input.dataset.filter, input]));
        const url = new URL(window.location.href);
        const names = { q: 'asig_buscar', section: 'asig_seccion', course: 'asig_curso', pending: 'asig_pendientes' };
        const courses = [...new Set(needs.map(row => row.dataset.course))].filter(Boolean).sort((a, b) => a.localeCompare(b, 'es', { numeric: true }));
        courses.forEach(course => controls.course.add(new Option(course, course)));
        Object.entries(controls).forEach(([key, input]) => {
            if (input.type === 'checkbox') input.checked = url.searchParams.get(names[key]) === '1';
            else input.value = url.searchParams.get(names[key]) || '';
        });
        const apply = () => {
            const filters = { q: controls.q.value, section: controls.section.value, course: controls.course.value, pending: controls.pending.checked };
            let count = 0;
            needs.forEach(row => {
                const failed = failedNeeds.has(row);
                const visible = failed || matchesNeed({ section: row.dataset.section, course: row.dataset.course, pending: row.dataset.pending === '1', search: row.dataset.search }, filters);
                row.hidden = !visible;
                (relatedByNeed.get(row.dataset.dotacionNeed) || []).forEach(detail => { detail.hidden = !visible; });
                if (visible) {
                    count++;
                    if (filters.q.trim() || filters.course || failed) openAncestors(row, root);
                }
            });
            root.querySelectorAll('[data-dotacion-block-heading]').forEach(heading => {
                let row = heading.nextElementSibling;
                let visible = false;
                while (row && !row.hasAttribute('data-dotacion-block-heading')) {
                    if (row.hasAttribute('data-dotacion-need') && !row.hidden) visible = true;
                    row = row.nextElementSibling;
                }
                heading.hidden = !visible;
            });
            root.querySelectorAll('[data-dotacion-course], [data-dotacion-group]').forEach(container => {
                container.hidden = !Array.from(container.querySelectorAll('[data-dotacion-need]')).some(row => !row.hidden);
            });
            filterPanel.querySelector('[data-filter-count]').textContent = `${count} de ${needs.length} necesidades visibles${failedNeeds.size ? ' · Se mantiene visible la asignación con errores.' : '.'}`;
            root.querySelector('[data-filter-empty]').hidden = count > 0;
            Object.entries(filters).forEach(([key, value]) => {
                if (!value) url.searchParams.delete(names[key]);
                else url.searchParams.set(names[key], key === 'pending' ? '1' : value);
            });
            window.history.replaceState(window.history.state, '', url);
            root.dispatchEvent(new Event('dotacion:visibility', { bubbles: true }));
        };
        filterPanel.hidden = false;
        controls.q.addEventListener('input', apply);
        [controls.section, controls.course, controls.pending].forEach(input => input.addEventListener('change', apply));
        filterPanel.querySelector('[data-filter-reset]').addEventListener('click', () => {
            Object.values(controls).forEach(input => { if (input.type === 'checkbox') input.checked = false; else input.value = ''; });
            apply();
            controls.q.focus();
        });
        apply();
    }

    root.querySelectorAll('.dotacion-assignment-form').forEach((form, index) => {
        const personal = form.querySelector('select[name="docente_rut"]');
        const summary = document.createElement('div');
        summary.className = 'dotacion-personal-summary small';
        summary.setAttribute('aria-live', 'polite');
        if (personal) {
            const control = personal.nextElementSibling?.classList.contains('select2-container') ? personal.nextElementSibling : personal;
            control.insertAdjacentElement('afterend', summary);
        }
        const button = form.querySelector('button[type="submit"]');
        const reason = document.createElement('div');
        reason.className = 'small text-muted';
        reason.id = `dotacion-submit-reason-${index}`;
        reason.setAttribute('role', 'status');
        if (button) {
            button.insertAdjacentElement('beforebegin', reason);
            button.setAttribute('aria-describedby', reason.id);
        }
        const refresh = () => {
            const option = personal?.selectedOptions[0];
            summary.replaceChildren();
            if (option?.value) {
                const name = document.createElement('strong');
                name.textContent = option.dataset.nombre || option.textContent;
                const detail = document.createElement('div');
                detail.textContent = option.dataset.estamento === 'asistente' ? `Asistente de la Educación · ${option.dataset.funcion || 'Sin función informada'}` : option.dataset.titulo || 'Sin título declarado';
                const balance = document.createElement('div');
                balance.textContent = `Saldo titular: ${option.dataset.titularDisponible || '0'} h · Saldo contrata: ${option.dataset.contrataDisponible || '0'} h`;
                summary.append(name, detail, balance);
            }
            const aaee = form.querySelector('.js-horas-contrato-aaee');
            if (aaee) aaee.closest('[data-aaee-field]').hidden = aaee.disabled;
            if (button?.disabled && root.dataset.processBlocked === '1') reason.textContent = 'Complete la configuración del proceso para habilitar la asignación. Abra «Configuración y avance». ';
            else if (button?.disabled) reason.textContent = 'Esta necesidad no tiene horas disponibles para una nueva asignación.';
            else if (personal && personal.options.length <= 1) reason.textContent = 'No hay personas disponibles para esta cobertura. Revise los docentes asociados y sus saldos, o cambie el tipo de cobertura si corresponde.';
            else if (personal && !personal.value) reason.textContent = 'Seleccione una persona para asignar las horas.';
            else reason.textContent = '';
        };
        form.addEventListener('change', refresh);
        if (personal && window.jQuery) window.jQuery(personal).on('change.dotacionUsabilidad', refresh);
        refresh();

        const errors = form.querySelector('[data-dotacion-form-errors]');
        errors?.querySelectorAll('[data-dotacion-field-error]').forEach(message => {
            const field = form.elements.namedItem(message.dataset.dotacionFieldError);
            if (!field || field.type === 'hidden') return;
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            message.id = `dotacion-field-error-${index}-${message.dataset.dotacionFieldError}`;
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' '));
            message.className = 'invalid-feedback d-block';
            const select2 = field.nextElementSibling?.classList.contains('select2-container') ? field.nextElementSibling : field;
            select2.insertAdjacentElement('afterend', message);
        });
        if (errors) {
            openAncestors(form, root);
            const focusError = () => window.requestAnimationFrame(() => {
                openAncestors(form, root);
                const field = form.querySelector('[aria-invalid="true"]');
                const target = field?.nextElementSibling?.classList.contains('select2-container') ? field.nextElementSibling.querySelector('.select2-selection') : field;
                target?.focus({ preventScroll: true });
                form.scrollIntoView({ block: 'center' });
            });
            if (document.readyState === 'complete') focusError();
            else window.addEventListener('load', focusError, { once: true });
        }
        form.addEventListener('submit', event => {
            if (event.defaultPrevented || !form.checkValidity()) return;
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = '1';
            if (button) {
                button.dataset.originalLabel = button.innerHTML;
                button.dataset.originalDisabled = button.disabled ? '1' : '0';
                button.disabled = true;
                button.textContent = 'Guardando…';
            }
        });
    });
    root.dispatchEvent(new Event('dotacion:visibility', { bubbles: true }));
}

if (typeof document !== 'undefined') {
    const start = () => {
        initDotacionAsignacion(document.querySelector('[data-dotacion-asignacion]'));
        document.querySelectorAll('[data-dotacion-revision]').forEach(initDotacionRevision);
        document.querySelectorAll('[data-dotacion-catalog]').forEach(initDotacionCatalog);
        document.querySelectorAll('form[data-dotacion-save]').forEach(initSaveFeedback);
        revealHashTarget();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
    window.addEventListener('hashchange', revealHashTarget);
    window.addEventListener('load', revealHashTarget, { once: true });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-dotacion-asignacion] form[data-submitting], form[data-dotacion-save][data-submitting]').forEach(form => {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-original-label]').forEach(button => {
                button.innerHTML = button.dataset.originalLabel;
                button.disabled = button.dataset.originalDisabled === '1';
            });
        });
    });
}
