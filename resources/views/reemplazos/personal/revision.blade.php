@extends('layouts.app')

@section('content')
    @php
        $etiquetas = [
            'sin_cambios' => 'Sin cambios', 'actualizacion_propuesta' => 'Actualización propuesta',
            'traslado_propuesto' => 'Traslado propuesto', 'reactivacion_propuesta' => 'Reactivación propuesta',
            'nueva_incorporacion' => 'Nueva incorporación propuesta', 'baja_propuesta' => 'Baja propuesta',
            'revision_manual' => 'Revisión manual', 'ausencia_por_revisar' => 'Ausencia por revisar', 'error' => 'Error',
            'reemplazo_anterior_omitido' => 'Reemplazo anterior omitido',
        ];
        $estadosRevision = [
            'pendiente' => 'Pendiente de decisión', 'resuelta' => 'Decisión registrada',
            'ausencia_vinculada' => 'Ausencia vinculada a una fila', 'error' => 'Error de archivo',
            'propuesta_automatica' => 'Propuesta automática',
            'omitida_por_vigencia' => 'Omitida por vigencia (REEMPLAZO)',
            'conservada' => 'Conservar en el período de carga',
            'nueva_linea_reemplazo' => 'REEMPLAZO: nueva línea automática',
        ];
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3>Revisión del padrón #{{ $revision->id }}</h3>
            <div class="text-muted">{{ $revision->archivo }} · Período: {{ $revision->anio ?? 'Mixto/inválido' }} / {{ $revision->mes ?? '—' }}</div>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('reemplazos.personal.import') }}">Analizar otro archivo</a>
    </div>
    <div class="alert alert-info">
        @if ($revision->aplicada_at)
            <strong>Carga aplicada el {{ $revision->aplicada_at }} por usuario #{{ $revision->aplicada_por }}.</strong>
            {{ $cambiosAplicados }} cambios auditados. Esta revisión está cerrada y conserva las propuestas originales.
            {{ $asignacionesLiberadas ?? 0 }} asignaciones inactivadas por bajas con liberación confirmada, conservadas en la auditoría.
        @else
            <strong>Solo previsualización.</strong> No se modifican contratos, vigencias ni asignaciones.
            Las decisiones manuales y las autorizaciones de jornada tampoco aplican registros al padrón.
            Las confirmaciones de baja con liberación también quedan diferidas hasta la aplicación definitiva.
            <div class="mt-2">Puede continuar esta misma revisión en distintas sesiones: no vence por el paso del tiempo ni por actividad en los documentos auditados.
                Las decisiones registradas se conservan. Los conflictos de Dotación y las referencias históricas se consultan con los datos actuales al recargar;
                antes de aplicar se revalidan. Si cambia el padrón contractual base, los establecimientos o las versiones de períodos, se exige un nuevo análisis.</div>
        @endif
        La Declaración de Sostenedores mantiene su prioridad.
    </div>
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    @if ($obsoleta)
        <div class="alert alert-warning">El padrón contractual base, los establecimientos, las versiones de períodos o la versión del análisis cambiaron. Analice nuevamente el archivo; no es posible registrar decisiones ni autorizar excepciones sobre esta revisión. Los cambios en documentos y cobertura no causan este bloqueo.</div>
    @endif
    @foreach ($revision->errores as $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endforeach
    @if (! $revision->aplicada_at)
        <div class="card mb-3"><div class="card-body">
            <h5>Aplicación definitiva del padrón completo</h5>
            @if (! $aplicacionDisponible)
                <div class="alert alert-warning">No habilitada en esta etapa. La actualización, incorporación y desactivación de personal quedan pendientes de validar la compatibilidad histórica y la escritura transaccional. Instalar las migraciones no habilita esta acción.</div>
            @endif
            @if ($bloqueos)
                <details class="alert alert-warning" id="bloqueos-padron"><summary><strong>{{ count($bloqueos) }} bloqueos por resolver</strong> · Ver primeros 10</summary>
                    <ul class="mb-0">
                        @foreach (array_slice($bloqueos, 0, 10) as $indice => $bloqueo)
                            <li>{{ $bloqueo }}
                                @if (isset($enlacesBloqueos[$indice]))
                                    <a href="{{ $enlacesBloqueos[$indice] }}" data-padron-filas-link>Ver registro y resolver</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if (count($bloqueos) > 10)<div>Se muestran los primeros 10; los demás bloqueos siguen vigentes.</div>@endif
                    @if ($pendientesCorrespondencia->total())
                        <a class="btn btn-outline-primary btn-sm mt-2" href="#pendientes-correspondencia">Ver correspondencias pendientes ({{ $pendientesCorrespondencia->total() }})</a>
                    @endif
                </details>
            @elseif ($aplicacionDisponible && ! $obsoleta)
                <form method="POST" action="{{ route('reemplazos.personal.import.store') }}">
                    @csrf
                    <input type="hidden" name="accion" value="aplicar">
                    <input type="hidden" name="confirmacion_hash" value="{{ $confirmacionHash }}">
                    <input type="hidden" name="revision" value="{{ $revision->id }}">
                    <div class="form-check mb-2"><input id="confirmar-aplicacion" class="form-check-input" type="checkbox" name="confirmar_aplicacion" value="1" required><label class="form-check-label" for="confirmar-aplicacion">Confirmo el padrón completo y autorizo estas actualizaciones, incorporaciones y desactivaciones.</label></div>
                    <button class="btn btn-danger">Aplicar padrón completo</button>
                </form>
            @endif
        </div></div>
    @endif

    @if ($conflictos)
        @include('reemplazos.personal.partials.conflictos-asignaciones')
    @endif

    <div class="row g-2 mb-3">
        @foreach ($revision->resumen as $accion => $cantidad)
            <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-2">
                <div class="small">{{ $etiquetas[$accion] ?? $accion }}</div><strong class="fs-4">{{ $cantidad }}</strong>
            </div></div></div>
        @endforeach
    </div>

    <div class="card mb-3" id="pendientes-correspondencia"><div class="card-body">
        <h5>Resolución de coincidencias</h5>
        <p>Seleccione el ID que corresponde a cada línea ambigua o confirme una nueva línea contractual. Cada decisión requiere justificación y conserva su historial. Un ID no puede ser seleccionado en dos filas.</p>
        <p>Si un funcionario fue omitido del Excel y debe continuar, abra sus filas y seleccione «Conservar este ID en el período de carga» en cada ausencia. Puede registrar juntas las selecciones del mismo RUT. Se mantienen los datos contractuales y se actualiza el mes solo al aplicar, sin duplicar IDs ni liberar sus asignaciones.</p>
        <p>Las filas pendientes de tipo REEMPLAZO se proponen como nuevas líneas sin vincular contratos anteriores. Se respetan las correspondencias ya identificadas y las decisiones registradas. No se levantan errores del archivo, controles de jornada ni bajas pendientes; las exclusiones de los módulos no cambian con esta regla.</p>
        @if (! $resolucionDisponible)
            <div class="alert alert-warning">Ejecute las migraciones de revisión del padrón con PHP 8.3 para habilitar las decisiones manuales.</div>
        @endif
        <div class="d-flex flex-wrap gap-3">
            @foreach ($estadosRevision as $estado => $etiqueta)
                <div>{{ $etiqueta }}: <strong>{{ $resumenResolucion['totales'][$estado] ?? 0 }}</strong></div>
            @endforeach
        </div>
        <p class="small text-muted mt-2 mb-0">Estos conteos abarcan toda la revisión, no solo esta página. Resolver todas las coincidencias no habilita la aplicación definitiva. Los vínculos históricos se informan para revisión; no se consideran protegidos solo por conservar el ID.</p>
        @if ($pendientesCorrespondencia->total())
            <h6 class="mt-3">Correspondencias pendientes por resolver</h6>
            <p class="small">Abra un registro para ver sus filas y registrar las decisiones. Esta lista incluye pendientes aunque no tengan conflictos de Dotación. Se muestran hasta 10 por página; al guardar se actualiza la lista.</p>
            <ul class="list-group mb-2">
                @foreach ($pendientesCorrespondencia as $pendiente)
                    <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>{{ $pendiente->fila_excel ? 'Fila Excel '.$pendiente->fila_excel : 'Ausente · ID '.$pendiente->personal_id }} · {{ $pendiente->rut }} · {{ $pendiente->nombre }}</span>
                        <a class="btn btn-outline-primary btn-sm" data-padron-filas-link href="{{ route('reemplazos.personal.import', ['revision' => $revision->id, 'fila_revision' => $pendiente->id]) }}#filas-padron">Ver registro y resolver</a>
                    </li>
                @endforeach
            </ul>
            {{ $pendientesCorrespondencia->links() }}
        @endif
    </div></div>

    <div class="card mb-3">
        <div class="card-header">Revisión de jornada docente: más de 44 horas</div>
        <div class="card-body">
            <p class="small text-muted">Se suma Jornada de las líneas seleccionadas del RUT, en todos los RBD y financiamientos, si tiene al menos un contrato docente. Jornada Básica y Media no se vuelven a sumar. La autorización es exclusiva de esta carga y de este total; no es una excepción permanente ni aplica cambios al padrón.</p>
            <p class="small text-muted">Primero se identifican transiciones por RUT: si un REEMPLAZO terminó antes del ingreso a un contrato regular posterior, se conserva como antecedente y no suma jornada simultánea, incluso si cambia de RBD. Un término e ingreso el mismo día, fechas incompletas o contratos superpuestos no acreditan esta transición.</p>
            <p class="small text-muted">Después, solo para tipo REEMPLAZO, si el total restante supera 44 h, se conservan los registros más recientes por Fecha_Ingreso y luego Fecha_Termino, hasta el margen disponible después de los demás contratos. Las fechas empatadas se consideran juntas; no se fraccionan jornadas ni se saltan a registros más antiguos para llenar cupo. Fechas incompletas o un conjunto más reciente que no cabe requieren corregir el archivo. SUPLENCIA y otros contratos no se recortan. Las filas omitidas siguen visibles y filtrables en esta revisión.</p>
            @forelse ($revision->excesos as $rut => $exceso)
                <div class="border rounded p-3 mb-2">
                    <strong>{{ $rut }}: {{ $exceso['total'] }} h</strong> · Exceso: {{ $exceso['exceso'] }} h · Filas Excel: {{ implode(', ', $exceso['filas']) }}
                    @if (! empty($exceso['ids_conservados']))<div>Incluye IDs conservados: {{ implode(', ', $exceso['ids_conservados']) }}</div>@endif
                    @if ($autorizaciones->has($rut))
                        @php
                            $autorizacion = $autorizaciones->get($rut);
                        @endphp
                        <div class="text-success mt-2">Autorizada por usuario #{{ $autorizacion->autorizado_por }} · {{ $autorizacion->created_at }}</div>
                        <div>{{ $autorizacion->justificacion }}</div>
                    @elseif (! $obsoleta && ! $revision->errores && ! $revision->aplicada_at)
                        <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="accion" value="autorizar_exceso">
                            <input type="hidden" name="revision" value="{{ $revision->id }}">
                            <input type="hidden" name="rut" value="{{ $rut }}">
                            <label for="justificacion-{{ $loop->index }}" class="form-label">Justificación de la excepción</label>
                            <textarea id="justificacion-{{ $loop->index }}" name="justificacion" class="form-control mb-2" minlength="10" maxlength="2000" rows="2" required></textarea>
                            <button class="btn btn-outline-danger btn-sm">Registrar autorización de {{ $exceso['total'] }} horas</button>
                        </form>
                    @else
                        <div class="text-danger mt-2">Corrija los errores y genere una nueva revisión antes de autorizar.</div>
                    @endif
                </div>
            @empty
                <div>No se detectaron jornadas docentes superiores a 44 horas.</div>
            @endforelse
        </div>
    </div>

    <div id="filas-padron" aria-live="polite">
        @include('reemplazos.personal.partials.filas-revision')
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const panel = document.getElementById('filas-padron');
    if (!panel) return;
    let pending;
    let savingBatch = false;
    panel.addEventListener('click', event => {
        const button = event.target.closest('[data-padron-resolver-varias]');
        if (!button || savingBatch) return;
        const error = panel.querySelector('[data-padron-lote-error]');
        error.textContent = '';
        const forms = Array.from(panel.querySelectorAll('form[data-padron-decision-form]'))
            .filter(form => form.elements.namedItem('personal_id').value !== '');
        if (!forms.length) {
            error.textContent = 'Seleccione explícitamente un registro a conservar, una nueva línea o una baja antes de guardar.';
            return;
        }
        const ruts = new Set(forms.map(form => form.dataset.rut.replace(/[^0-9k]/gi, '').toUpperCase()));
        if (ruts.size !== 1 || ruts.has('')) {
            error.textContent = 'Las selecciones deben corresponder al mismo RUT. Busque un funcionario y vuelva a seleccionar.';
            return;
        }
        const selectedIds = forms.map(form => form.elements.namedItem('personal_id').value).filter(id => id !== '0');
        if (new Set(selectedIds).size !== selectedIds.length) {
            error.textContent = 'No puede conservar el mismo ID en dos filas. Revise las selecciones.';
            return;
        }
        for (const form of forms) {
            form.closest('details').open = true;
            if (!form.reportValidity()) return;
        }
        const batch = document.createElement('form');
        batch.method = 'POST';
        batch.action = forms[0].action;
        const field = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = name; input.value = value;
            batch.appendChild(input);
        };
        for (const name of ['_token', 'revision', 'q', 'accion_filtro', 'page', 'conflictos_page', 'caso_rut', 'caso_establecimiento']) {
            const input = forms[0].elements.namedItem(name);
            if (input) field(name, input.value);
        }
        field('accion', 'resolver_varias');
        field('rut', Array.from(ruts)[0]);
        forms.forEach((form, index) => {
            for (const name of ['fila', 'personal_id', 'justificacion', 'decision_anterior']) {
                field(`decisiones[${index}][${name}]`, form.elements.namedItem(name).value);
            }
        });
        document.body.appendChild(batch);
        savingBatch = true;
        button.disabled = true;
        button.textContent = 'Registrando decisiones…';
        batch.submit();
    });
    async function loadRows(href) {
        if (pending) pending.abort();
        const controller = new AbortController();
        pending = controller;
        const url = new URL(href, window.location.href);
        url.hash = '';
        url.searchParams.set('solo_filas', '1');
        panel.setAttribute('aria-busy', 'true');
        panel.replaceChildren(Object.assign(document.createElement('p'), {textContent: 'Cargando filas del RUT…'}));
        panel.scrollIntoView({block: 'start'});
        const timeout = setTimeout(() => controller.abort(), 60000);
        try {
            const response = await fetch(url, {signal: controller.signal, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok || response.redirected || response.headers.get('X-Padron-Filas') !== '1') throw new Error('response');
            const html = await response.text();
            if (pending !== controller) return;
            panel.innerHTML = html;
            url.searchParams.delete('solo_filas');
            url.searchParams.delete('avanzar_caso');
            url.hash = 'filas-padron';
            // Conservar el RUT también al recargar o volver tras una validación.
            window.history.replaceState(null, '', url.href);
        } catch (error) {
            if (pending !== controller) return;
            const message = Object.assign(document.createElement('p'), {textContent: 'No fue posible cargar las filas. Intente nuevamente o abra la revisión completa.'});
            const retry = Object.assign(document.createElement('a'), {href, textContent: 'Reintentar'});
            retry.dataset.padronFilasLink = '';
            const fallback = Object.assign(document.createElement('a'), {href, textContent: 'Abrir revisión completa', className: 'ms-3'});
            panel.replaceChildren(message, retry, fallback);
        } finally {
            clearTimeout(timeout);
            if (pending === controller) panel.removeAttribute('aria-busy');
        }
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-padron-filas-link], #filas-padron nav a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        loadRows(link.href);
    });
    panel.addEventListener('submit', event => {
        const form = event.target;
        if (form.method.toLowerCase() !== 'get') return;
        event.preventDefault();
        const url = new URL(form.action, window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        loadRows(url.href);
    });
})();
</script>
@endpush
