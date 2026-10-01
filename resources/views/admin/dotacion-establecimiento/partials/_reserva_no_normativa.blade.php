@php
    $docentesReserva = \App\Support\DotacionReservaNoNormativa::elegibles($proceso2027Asignacion);
    $faseReserva = \App\Support\DotacionReservaNoNormativa::fase($docentesReserva);
    $opcionesReserva = \App\Support\DotacionReservaNoNormativa::opciones($docentesReserva);
    $capacidadReserva = (float) ($proceso2027Asignacion['capacidad_reserva_no_normativa'] ?? 0);
    $reservasActivas = collect($asignaciones)->where('tipo_asignacion', 'reserva_no_normativa')->values();
    $funcionesVinculables = collect($necesidades['funciones'] ?? [])
        ->filter(fn (array $item) => (int) ($item['dotacion_funcion_id'] ?? 0) > 0
            && (float) ($item['horas_contrato_pendientes'] ?? 0) > 0.01)
        ->values();
@endphp

<div class="card dotacion-section mb-4 overflow-visible">
    <div class="dotacion-section-header rounded-top-4 d-flex align-items-start gap-3">
        <span class="dotacion-icon" style="width:38px;height:38px;background:#0d6efd;"><i class="bi bi-arrow-left-right"></i></span>
        <div>
            <div class="dotacion-eyebrow">Antes de asignar el plan de estudios</div>
            <h2 class="h5 fw-bold mb-1">Traspaso de horas a otras funciones</h2>
            <div class="text-muted small">Reserve horas de contrato por docente antes de crear la función. Estas horas dejan de estar disponibles para plan y PIE; al crear la función podrá vincular la reserva sin duplicarlas.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Saldo no normativo por traspasar</div><strong class="h4 mb-0">{{ $fmt($capacidadReserva) }} h</strong></div></div>
            <div class="col-md-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Horas reservadas sin función</div><strong class="h4 mb-0">{{ $fmt($reservasActivas->sum(fn ($row) => (float) $row->horas_contrato)) }} h</strong></div></div>
            <div class="col-md-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Origen permitido</div><strong>Horas titulares</strong><div class="small text-muted">No se traspasan horas a contrata ni saldos titulares menores a 1 h.</div></div></div>
        </div>

        @if ($capacidadReserva >= 1 && $opcionesReserva->isNotEmpty())
        <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.reservas.store', $establecimiento) }}" class="border rounded-4 p-3 bg-light">
            @csrf
            <input type="hidden" name="anio" value="2027">
            <div class="row g-3 align-items-end">
                <div class="col-lg-7">
                    <label class="form-label fw-semibold" for="docente-reserva-no-normativa">Docente con saldo titular <span class="text-danger">*</span></label>
                    <div class="dotacion-reserva-picker" data-reserva-picker data-fase="{{ $faseReserva }}" data-maximo-global="{{ $capacidadReserva }}">
                        <select id="docente-reserva-no-normativa" name="docente_rut" class="form-select" required>
                            <option value="">Seleccione docente...</option>
                            @foreach ($opcionesReserva as $docente)
                                @php
                                    $maximoDocente = \App\Support\DotacionReservaNoNormativa::maximoParaDocente($proceso2027Asignacion, $docente, $faseReserva);
                                    $saldoDocente = \App\Support\DotacionPlanTitularPrimero::disponibles($docente, $faseReserva);
                                @endphp
                                @continue($maximoDocente < 1)
                                <option value="{{ $docente['rut'] }}" data-nombre="{{ $docente['nombre'] }}" data-rut="{{ $docente['rut'] }}" data-titulo="{{ $docente['titulo'] ?? 'Sin título declarado' }}" data-prioridad-label="{{ $docente['prioridad_2027_label'] ?? '' }}" data-saldo="{{ $fmt($saldoDocente) }}" data-maximo="{{ $fmt($maximoDocente) }}" data-maximo-numero="{{ $maximoDocente }}">{{ $docente['nombre'] }} · {{ $docente['rut'] }} · {{ ucfirst($faseReserva) }}: {{ $fmt($saldoDocente) }} h · Máximo: {{ $fmt($maximoDocente) }} h</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold" for="horas-reserva-no-normativa">Horas contrato <span class="text-danger">*</span></label>
                    <input id="horas-reserva-no-normativa" type="number" name="horas_contrato" class="form-control" min="1" max="{{ $capacidadReserva }}" step="0.01" required>
                </div>
                <div class="col-lg-3">
                    <button class="btn btn-primary rounded-pill w-100" type="submit" @disabled(!$asignacion2027Habilitada || $capacidadReserva < 1 || $opcionesReserva->isEmpty())><i class="bi bi-arrow-left-right"></i> Traspasar horas</button>
                </div>
            </div>
            <div class="form-text">Sólo se pueden traspasar horas titulares, hasta el saldo disponible del docente, su bloque y el establecimiento.</div>
        </form>
        @else
            <div class="alert alert-info border-0 rounded-4 small mb-0"><i class="bi bi-info-circle me-1"></i>
                @if ($capacidadReserva < 1)
                    No queda saldo no normativo para nuevos traspasos.
                @else
                    No hay docentes con al menos 1 h titular disponible para traspasar.
                @endif
                Las horas a contrata no se pueden reservar en este bloque.
            </div>
        @endif

        @if ($reservasActivas->isNotEmpty())
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Docente</th><th>Origen</th><th class="text-end">Horas reservadas</th><th>Vincular a función creada</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($reservasActivas as $reserva)
                            <tr>
                                <td class="fw-semibold">{{ $reserva->docente_nombre }}</td>
                                <td>{{ $reserva->subtipo_asignacion === 'titular' ? 'Titular' : 'Contrata' }}</td>
                                <td class="text-end">{{ $fmt($reserva->horas_contrato) }}</td>
                                <td>
                                    @if (($proceso2027Asignacion['funciones_no_normativas_habilitadas'] ?? false) && $funcionesVinculables->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.reservas.vincular', [$establecimiento, $reserva]) }}" class="d-flex gap-2 flex-wrap align-items-end">
                                            @csrf
                                            <div class="flex-grow-1">
                                                <label class="form-label small mb-1" for="funcion-reserva-{{ $reserva->id }}">Función</label>
                                                <select id="funcion-reserva-{{ $reserva->id }}" name="necesidad_key" class="form-select form-select-sm" required>
                                                    <option value="">Seleccione función...</option>
                                                    @foreach ($funcionesVinculables as $funcion)
                                                        <option value="{{ $funcion['key'] }}">{{ $funcion['titulo'] }} · {{ $fmt($funcion['horas_contrato_pendientes']) }} h pendientes</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1" for="horas-vincular-{{ $reserva->id }}">Horas</label>
                                                <input id="horas-vincular-{{ $reserva->id }}" type="number" name="horas_contrato" class="form-control form-control-sm" min="0.01" max="{{ $reserva->horas_contrato }}" step="0.01" value="{{ $reserva->horas_contrato }}" required style="width:6.5rem">
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit">Vincular</button>
                                        </form>
                                    @else
                                        <span class="small text-muted">Disponible al crear funciones y completar la cobertura obligatoria.</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.destroy', [$establecimiento, $reserva]) }}" onsubmit="return confirm('¿Liberar las horas reservadas de este docente?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">Liberar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@once
    @push('styles')
        <style>
            .dotacion-reserva-picker { position: relative; }
            .dotacion-reserva-picker__trigger { display: flex; align-items: center; gap: .65rem; width: 100%; min-height: 2.65rem; padding: .5rem .75rem; border: 1px solid #dbe4f0; border-radius: .75rem; background: #fff; color: #0f172a; text-align: left; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
            .dotacion-reserva-picker__trigger:hover { border-color: #9db7dc; }
            .dotacion-reserva-picker__trigger:focus-visible, .dotacion-reserva-picker__trigger[aria-expanded="true"] { outline: none; border-color: #0d6efd; box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15); }
            .dotacion-reserva-picker__trigger.is-invalid { border-color: #dc3545; }
            .dotacion-reserva-picker__selected { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
            .dotacion-reserva-picker__selected.is-placeholder { color: #64748b; font-weight: 400; }
            .dotacion-reserva-picker__summary { margin-left: auto; flex: none; color: #0b4aa2; font-size: .78rem; font-weight: 700; white-space: nowrap; }
            .dotacion-reserva-picker__trigger .bi { margin-left: .3rem; color: #64748b; }
            .dotacion-reserva-picker__panel { position: absolute; z-index: 1080; top: calc(100% + .35rem); left: 0; width: min(100%, 48rem); min-width: 100%; overflow: hidden; border: 1px solid #dbe4f0; border-radius: .85rem; background: #fff; box-shadow: 0 14px 30px rgba(15, 23, 42, .16); }
            .dotacion-reserva-picker__panel[hidden] { display: none; }
            .dotacion-reserva-picker__search-wrap { padding: .65rem; border-bottom: 1px solid #dbe4f0; background: #f8fbff; }
            .dotacion-reserva-picker__search { min-height: 2.3rem; border-radius: .65rem; }
            .dotacion-reserva-picker__options { max-height: 19rem; overflow-y: auto; padding: .3rem; }
            .dotacion-reserva-picker__option { display: block; width: 100%; padding: .65rem .75rem; border: 0; border-radius: .6rem; background: #fff; color: #0f172a; text-align: left; }
            .dotacion-reserva-picker__option[hidden] { display: none; }
            .dotacion-reserva-picker__option:hover, .dotacion-reserva-picker__option:focus-visible, .dotacion-reserva-picker__option[aria-selected="true"] { outline: none; background: #eaf2ff; }
            .dotacion-reserva-picker__name { display: block; font-weight: 700; color: #172554; }
            .dotacion-reserva-picker__detail { display: block; margin-top: .15rem; color: #475569; font-size: .78rem; }
            .dotacion-reserva-picker__balance { display: flex; gap: 1rem; align-items: center; margin-top: .35rem; color: #0f766e; font-size: .82rem; font-weight: 700; }
            .dotacion-reserva-picker__maximum { color: #0b4aa2; }
            .dotacion-reserva-picker__empty { padding: .8rem; color: #64748b; font-size: .875rem; }
            @media (max-width: 575.98px) {
                .dotacion-reserva-picker__trigger { flex-wrap: wrap; }
                .dotacion-reserva-picker__summary { margin-left: 0; }
                .dotacion-reserva-picker__balance { flex-wrap: wrap; gap: .25rem .75rem; }
            }
        </style>
    @endpush
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const picker = document.querySelector('[data-reserva-picker]');
                if (!picker) return;
                const select = picker.querySelector('select');
                const hours = document.getElementById('horas-reserva-no-normativa');
                const phase = picker.dataset.fase === 'contrata' ? 'Contrata' : 'Titular';
                const options = Array.from(select.options).filter(option => option.value);
                const trigger = document.createElement('button');
                trigger.type = 'button';
                trigger.className = 'dotacion-reserva-picker__trigger';
                trigger.setAttribute('aria-haspopup', 'listbox');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.setAttribute('aria-label', 'Seleccionar docente para traspasar horas');
                const selected = document.createElement('span');
                selected.className = 'dotacion-reserva-picker__selected';
                const summary = document.createElement('span');
                summary.className = 'dotacion-reserva-picker__summary';
                const arrow = document.createElement('i');
                arrow.className = 'bi bi-chevron-down';
                arrow.setAttribute('aria-hidden', 'true');
                trigger.append(selected, summary, arrow);

                const panel = document.createElement('div');
                panel.className = 'dotacion-reserva-picker__panel';
                panel.hidden = true;
                const searchWrap = document.createElement('div');
                searchWrap.className = 'dotacion-reserva-picker__search-wrap';
                const search = document.createElement('input');
                search.type = 'search';
                search.className = 'form-control dotacion-reserva-picker__search';
                search.placeholder = 'Buscar por nombre, RUT o título...';
                search.setAttribute('aria-label', search.placeholder);
                searchWrap.append(search);
                const list = document.createElement('div');
                list.className = 'dotacion-reserva-picker__options';
                list.id = 'opciones-reserva-no-normativa';
                list.setAttribute('role', 'listbox');
                trigger.setAttribute('aria-controls', list.id);
                panel.append(searchWrap, list);

                const normalize = value => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
                const rows = options.map(option => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'dotacion-reserva-picker__option';
                    row.setAttribute('role', 'option');
                    row.setAttribute('aria-selected', 'false');
                    const name = document.createElement('span');
                    name.className = 'dotacion-reserva-picker__name';
                    name.textContent = `${option.dataset.nombre} · ${option.dataset.rut}`;
                    const detail = document.createElement('span');
                    detail.className = 'dotacion-reserva-picker__detail';
                    detail.textContent = [option.dataset.titulo, option.dataset.prioridadLabel].filter(Boolean).join(' · ');
                    const balance = document.createElement('span');
                    balance.className = 'dotacion-reserva-picker__balance';
                    const available = document.createElement('span');
                    available.textContent = `Saldo ${phase.toLowerCase()}: ${option.dataset.saldo} h`;
                    const maximum = document.createElement('span');
                    maximum.className = 'dotacion-reserva-picker__maximum';
                    maximum.textContent = `Máximo a traspasar: ${option.dataset.maximo} h`;
                    balance.append(available, maximum);
                    row.append(name, detail, balance);
                    row.dataset.search = normalize(option.textContent + ' ' + option.dataset.titulo);
                    row.addEventListener('click', () => {
                        select.value = option.value;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        trigger.classList.remove('is-invalid');
                        close();
                        trigger.focus();
                    });
                    list.append(row);
                    return row;
                });
                const empty = document.createElement('div');
                empty.className = 'dotacion-reserva-picker__empty';
                empty.textContent = 'No se encontraron docentes con ese nombre, RUT o título.';
                empty.hidden = true;
                list.append(empty);

                function update() {
                    const option = select.options[select.selectedIndex];
                    selected.textContent = option?.value ? `${option.dataset.nombre} · ${option.dataset.rut}` : 'Buscar y seleccionar docente...';
                    selected.classList.toggle('is-placeholder', !option?.value);
                    summary.textContent = option?.value ? `${phase}: ${option.dataset.saldo} h · Máx.: ${option.dataset.maximo} h` : '';
                    trigger.setAttribute('aria-label', option?.value
                        ? `${option.dataset.nombre}. Saldo ${phase.toLowerCase()}: ${option.dataset.saldo} horas. Máximo a traspasar: ${option.dataset.maximo} horas.`
                        : 'Seleccionar docente para traspasar horas');
                    rows.forEach((row, index) => row.setAttribute('aria-selected', options[index].value === select.value ? 'true' : 'false'));
                    if (hours) {
                        hours.max = option?.value ? option.dataset.maximoNumero : picker.dataset.maximoGlobal;
                        if (hours.value && Number(hours.value) > Number(hours.max)) hours.value = '';
                    }
                }
                function close() {
                    panel.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                }
                function open() {
                    panel.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    search.value = '';
                    rows.forEach(row => row.hidden = false);
                    empty.hidden = rows.length > 0;
                    search.focus();
                }
                trigger.addEventListener('click', () => panel.hidden ? open() : close());
                document.querySelector('label[for="docente-reserva-no-normativa"]')?.addEventListener('click', event => {
                    event.preventDefault();
                    if (panel.hidden) open(); else search.focus();
                });
                search.addEventListener('input', () => {
                    const query = normalize(search.value.trim());
                    rows.forEach(row => row.hidden = !row.dataset.search.includes(query));
                    empty.hidden = rows.some(row => !row.hidden);
                });
                search.addEventListener('keydown', event => {
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        rows.find(row => !row.hidden)?.focus();
                    }
                    if (event.key === 'Escape') { close(); trigger.focus(); }
                });
                rows.forEach(row => row.addEventListener('keydown', event => {
                    if (event.key === 'Escape') { close(); trigger.focus(); }
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        const visible = rows.filter(item => !item.hidden);
                        visible[(visible.indexOf(row) + (event.key === 'ArrowDown' ? 1 : visible.length - 1)) % visible.length]?.focus();
                    }
                }));
                picker.addEventListener('focusout', () => {
                    requestAnimationFrame(() => { if (!picker.contains(document.activeElement)) close(); });
                });
                document.addEventListener('pointerdown', event => { if (!picker.contains(event.target)) close(); });
                select.addEventListener('change', update);
                select.addEventListener('invalid', event => {
                    event.preventDefault();
                    trigger.classList.add('is-invalid');
                    open();
                });
                picker.append(trigger, panel);
                select.classList.add('visually-hidden');
                select.tabIndex = -1;
                update();
            });
        </script>
    @endpush
@endonce
