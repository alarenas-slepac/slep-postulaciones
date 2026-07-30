@extends('layouts.app')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1">Solicitud de Reemplazo Funcionarios Establecimientos ({{ now()->year }})</h1>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Revisa los campos:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @php
        $isEdit = isset($solicitud);
        $tipoVal = old('tipo_reemplazo', $isEdit ? $solicitud->tipo_reemplazo : '');
        $propVal = old('propone_reemplazo', $isEdit ? (string) (int) $solicitud->propone_reemplazo : '0');
        $contVal = old(
            'continuidad',
            $isEdit && !is_null($solicitud->continuidad) ? (string) (int) $solicitud->continuidad : '',
        );

        $fiVal = old('fecha_inicio', $isEdit ? \Carbon\Carbon::parse($solicitud->fecha_inicio)->format('d/m/Y') : '');
        $ftVal = old('fecha_termino', $isEdit ? \Carbon\Carbon::parse($solicitud->fecha_termino)->format('d/m/Y') : '');

        $v = fn($key, $default = '') => old($key, $isEdit ? data_get($solicitud, $key, $default) : $default);
        $tiposReemplazoDeshabilitados = [
            'Permiso Horas de Lactancia',
            'Permiso especial para deportistas (Art 74, Ley 19.712)',
            'Otras',
        ];
        $tiposReemplazoOpciones = [
            'Licencia Médica (General)',
            'Licencia Médica (Pre y/o Post Natal y/o Parental)',
            'Permiso Postnatal Parental',
            'Permiso sin goce de sueldo',
            'Permiso Horas de Lactancia',
            'Permiso especial para deportistas (Art 74, Ley 19.712)',
            'Sumario Administrativo',
            'Otras',
        ];
    @endphp

    @if ($isEdit && $solicitud->estado === 'rechazada_uatp' && !empty($solicitud->motivo_rechazo))
        <div class="alert alert-danger">
            <div class="fw-semibold">Solicitud rechazada por UATP</div>
            <div style="white-space: pre-line;">{{ $solicitud->motivo_rechazo }}</div>
        </div>
    @endif

    @if ($isEdit && $solicitud->estado === 'rechazada_plani' && !empty($solicitud->plani_motivo_rechazo))
        <div class="alert alert-danger">
            <div class="fw-semibold">Solicitud rechazada por la Subdirección de Planificación y Control de Gestión</div>
            <div style="white-space: pre-line;">{{ $solicitud->plani_motivo_rechazo }}</div>
            <div class="small mt-2">Al reenviar, la solicitud volverá a Pendiente UATP para un nuevo ciclo de revisión.</div>
        </div>
    @endif

    <form id="solicitudReemplazoForm" method="POST"
        action="{{ $isEdit ? route('funcionario.solicitudes-reemplazo.update', $solicitud) : route('funcionario.solicitudes-reemplazo.store') }}"
        enctype="multipart/form-data">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif


        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha de solicitud</label>
                        <input class="form-control" value="{{ $fechaSolicitud }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hora</label>
                        <input class="form-control" value="{{ $horaSolicitud }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Datos del establecimiento</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Establecimiento</label>
                        <input class="form-control"
                            value="{{ ($establecimiento->rbd ?? '') . ' - ' . ($establecimiento->nombre_establecimiento ?? ($establecimiento->nombre ?? '')) }}"
                            readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Comuna</label>
                        <input class="form-control" value="{{ $establecimiento->comuna ?? '—' }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Datos del contacto del establecimiento</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nombre contacto solicitante</label>
                        <input class="form-control" value="{{ $contactoNombre }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fono del contacto <span class="text-danger">*</span></label>
                        <input name="contacto_fono" class="form-control @error('contacto_fono') is-invalid @enderror"
                            value="{{ $v('contacto_fono') }}" placeholder="+56 9 ...." required>
                        @error('contacto_fono')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Correo de contacto</label>
                        <input class="form-control" value="{{ $contactoEmail }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Datos del funcionario a reemplazar</div>
            <div class="card-body">
                <div class="mb-1">
                    <label class="form-label">Funcionario a reemplazar <span class="text-danger">*</span></label>
                    <select id="funcionarioSelect" name="reemplazo_personal_id" class="form-select" required>
                        @if ($isEdit && $solicitud->funcionarioTitular)
                            <option value="{{ $solicitud->funcionarioTitular->id }}" selected>
                                {{ $solicitud->funcionarioTitular->rut }} - {{ $solicitud->funcionarioTitular->nombre }}
                            </option>
                        @endif
                    </select>
                    <div class="form-text">Busca por RUT o nombre. Los docentes titulares bloqueados no pueden seleccionarse.</div>
                    <div id="funcionarioBloqueadoAlert" class="alert alert-danger mt-2 d-none" role="alert">
                        <strong>Docente titular bloqueado.</strong><br>
                        Este funcionario no puede ser usado en nuevas solicitudes de reemplazo mientras el bloqueo esté activo.
                        <div id="funcionarioBloqueadoMotivo" class="small mt-1"></div>
                    </div>
                </div>
                <div class="row g-3" id="wrap-area-desempeno">
                    <div class="col-md-6">
                        <label class="form-label">Área de desempeño <span class="text-danger">*</span></label>
                        <select id="area_desempeno_id" name="area_desempeno_id"
                            class="form-select @error('area_desempeno_id') is-invalid @enderror"
                            data-selected="{{ old('area_desempeno_id', $isEdit ? $solicitud->area_desempeno_id : '') }}"
                            required disabled>
                            <option value="">Seleccione un funcionario primero…</option>
                        </select>
                        <div id="areaBloqueadaAlert" class="alert alert-warning mt-2 d-none" role="alert">
                            <strong>El área de desempeño se encuentra bloqueada, por sobredotación.</strong><br>
                            Si desea solicitar desbloqueo de área de desempeño, favor enviar correo electrónico a
                            <a href="mailto:alonso.larenas@slepandaliencosta.gob.cl">alonso.larenas@slepandaliencosta.gob.cl</a>
                            o a
                            <a href="mailto:jocelyn.bobadila@slepandaliencosta.gob.cl">jocelyn.bobadila@slepandaliencosta.gob.cl</a>.
                        </div>
                        <div class="form-text">Se carga según el estatuto del funcionario (AAEE/Docente).</div>
                        @error('area_desempeno_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div id="funcionarioCard" class="d-none">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="fw-semibold mb-2">Datos del funcionario</div>
                                    <div><span class="text-muted">RUT:</span> <span id="f_rut"></span></div>
                                    <div><span class="text-muted">Nombres:</span> <span id="f_nombres"></span></div>
                                    <div><span class="text-muted">Estatuto:</span> <span id="f_estatuto"></span></div>
                                    <div><span class="text-muted">Escalafón:</span> <span id="f_escalafon"></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="fw-semibold mb-2">Distribución de jornada (titular)</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Financiamiento</th>
                                                    <th class="text-end">HRS BÁSICA</th>
                                                    <th class="text-end">HRS MEDIA</th>
                                                    <th class="text-end">TOTAL</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tablaTitularBody"></tbody>
                                            <tfoot>
                                                <tr class="fw-semibold">
                                                    <td class="text-end">TOTAL</td>
                                                    <td class="text-end" id="t_basica">0</td>
                                                    <td class="text-end" id="t_media">0</td>
                                                    <td class="text-end" id="t_total">0</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="form-text">Estos valores se usan como máximo para la propuesta.</div>

                                    <div id="horasAulaTitularWrap" class="d-none">
                                        <div id="horasAulaDocenteAlert" class="alert alert-info mt-3 mb-3" role="alert">
                                            <div class="fw-semibold mb-1">Funcionario titular con Estatuto DOCENTE</div>
                                            Debe ingresar obligatoriamente las Horas Aula del titular y del reemplazo para continuar con la solicitud.
                                        </div>

                                        <div class="row g-3 mt-1">
                                            <div class="col-md-6">
                                                <label class="form-label">Horas Aula Cronológicas <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" min="0" id="horas_aula_cronologicas_titular"
                                                    name="horas_aula_cronologicas_titular"
                                                    class="form-control @error('horas_aula_cronologicas_titular') is-invalid @enderror"
                                                    value="{{ $v('horas_aula_cronologicas_titular') }}">
                                                <div class="form-text">No puede exceder el total de horas del titular: <span id="titularHorasMaxLabel">0</span>.</div>
                                                @error('horas_aula_cronologicas_titular')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Horas Aula Pedagógicas <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" min="0" id="horas_aula_pedagogicas_titular"
                                                    name="horas_aula_pedagogicas_titular"
                                                    class="form-control @error('horas_aula_pedagogicas_titular') is-invalid @enderror"
                                                    value="{{ $v('horas_aula_pedagogicas_titular') }}">
                                                @error('horas_aula_pedagogicas_titular')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo de reemplazo <span class="text-danger">*</span></label>
                        <select name="tipo_reemplazo" id="tipoReemplazo" class="form-select" required>
                            <option value="">Seleccione…</option>
                            @foreach ($tiposReemplazoOpciones as $tipoOpcion)
                                @php
                                    $tipoDeshabilitado = in_array($tipoOpcion, $tiposReemplazoDeshabilitados, true) && $tipoVal !== $tipoOpcion;
                                    $tipoTexto = $tipoOpcion;
                                    if (in_array($tipoOpcion, $tiposReemplazoDeshabilitados, true) && $tipoVal === $tipoOpcion) {
                                        $tipoTexto .= ' (no disponible para nuevas solicitudes)';
                                    }
                                @endphp
                                <option value="{{ $tipoOpcion }}" @selected($tipoVal === $tipoOpcion) @disabled($tipoDeshabilitado)>
                                    {{ $tipoTexto }}
                                </option>
                            @endforeach
                        </select>
                        <div id="permisoSinGoceHelp" class="form-text text-muted">
                            Permiso sin goce de sueldo sólo se permite para titulares docentes autorizados por GDP.
                        </div>
                        <div id="permisoSinGoceAlert" class="alert alert-warning mt-2 mb-0 d-none">
                            El titular seleccionado no está autorizado para solicitar Permiso sin goce de sueldo.
                        </div>

                    </div>
                    <div class="col-md-6 d-none" id="tipoOtroWrap">
                        <label class="form-label">Detalle (Otras) <span class="text-danger">*</span></label>
						<input type="text" name="tipo_reemplazo_otro" id="tipoReemplazoOtro" class="form-control"
                            value="{{ $v('tipo_reemplazo_otro') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Fecha inicio reemplazo <span class="text-danger">*</span></label>
                        <input type="text" name="fecha_inicio" id="fechaInicio" class="form-control" required
                            placeholder="dd/mm/yyyy" value="{{ $fiVal }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha término reemplazo <span class="text-danger">*</span></label>
                        <input type="text" name="fecha_termino" id="fechaTermino" class="form-control" required
                            placeholder="dd/mm/yyyy" value="{{ $ftVal }}">
                    </div>
                </div>

                <div id="reglaMinimaAlert" class="alert mt-3 mb-0 d-none" role="alert"></div>

                <hr class="my-3">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">¿Proponer reemplazo?</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="propone_reemplazo" id="propNo"
                                    value="0" @checked($propVal === '0')>
                                <label class="form-check-label" for="propNo">No</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="propone_reemplazo" id="propSi"
                                    value="1" @checked($propVal === '1')>
                                <label class="form-check-label" for="propSi">Sí</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="propuestaWrap" class="d-none mt-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Continuidad de reemplazo</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="continuidad" id="contNo"
                                        value="0" @checked($contVal === '0')>
                                    <label class="form-check-label" for="contNo">No</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="continuidad" id="contSi"
                                        value="1" @checked($contVal === '1')>
                                    <label class="form-check-label" for="contSi">Sí</label>
                                </div>
                            </div>
                            <div class="form-text">"Sí", si la propuesta continúa reemplazando al mismo funcionario.</div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">
                                Postulante / funcionario propuesto
                                <span class="text-danger" id="req-postulante" style="display:none">*</span>
                            </label>
                            <div class="d-flex gap-2">
                                <select id="postulant_profile_id" name="postulant_profile_id"
                                    class="form-select @error('postulant_profile_id') is-invalid @enderror">
                                    @if ($isEdit && $solicitud->postulante && $solicitud->postulante->user)
                                        @php
                                            $pu = $solicitud->postulante->user;
                                            $rn = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($pu->rut ?? '')));
                                            $rutFmt = $rn;
                                            if ($rn !== '' && strlen($rn) >= 2) {
                                                $rutFmt = substr($rn, 0, -1) . '-' . substr($rn, -1);
                                            }
                                            $full = trim(($pu->nombres ?? '') . ' ' . ($pu->apellido_paterno ?? '') . ' ' . ($pu->apellido_materno ?? ''));
                                        @endphp
                                        <option value="{{ $solicitud->postulante->id }}" selected>
                                            {{ trim($full . ' ' . $rutFmt) }}
                                        </option>
                                    @endif

                                </select>

                                @error('postulant_profile_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <a id="btnVerPdf" class="btn btn-outline-primary disabled" target="_blank"
                                    href="#">Ver</a>
                                            <a id="btnVerCv" class="btn btn-outline-success-dark disabled" target="_blank"
                                    href="#">Ver CV</a>
                                <a id="btnDescPdf" class="btn btn-outline-secondary disabled d-none"
                                    href="#">Descargar</a>
                            </div>

                        </div>
                    </div>


                </div>
                {{-- ✅ Siempre se definen horas de reemplazo (independiente de si propone postulante) --}}
                <div id="reemplazoCard" class="d-none mt-3">
                    <div class="card border">
                        <div class="card-body">
                            <div class="fw-semibold mb-2">Distribución de jornada (reemplazo) — editable</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Financiamiento</th>
                                            <th class="text-end">HRS BÁSICA</th>
                                            <th class="text-end">HRS MEDIA</th>
                                            <th class="text-end">TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaReemplazoBody"></tbody>
                                    <tfoot>
                                        <tr class="fw-semibold">
                                            <td class="text-end">TOTAL</td>
                                            <td class="text-end" id="r_basica">0</td>
                                            <td class="text-end" id="r_media">0</td>
                                            <td class="text-end" id="r_total">0</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="form-text">No puede exceder las horas del titular; sí puede disminuir.</div>

                            <div id="horasAulaReemplazoWrap" class="row g-3 mt-1 d-none">
                                <div class="col-md-6">
                                    <label class="form-label">Horas Aula Cronológicas <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" id="horas_aula_cronologicas_reemplazo"
                                        name="horas_aula_cronologicas_reemplazo"
                                        class="form-control @error('horas_aula_cronologicas_reemplazo') is-invalid @enderror"
                                        value="{{ $v('horas_aula_cronologicas_reemplazo') }}">
                                    <div class="form-text">No puede exceder el total de horas del reemplazo: <span id="reemplazoHorasMaxLabel">0</span>.</div>
                                    @error('horas_aula_cronologicas_reemplazo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Horas Aula Pedagógicas <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" id="horas_aula_pedagogicas_reemplazo"
                                        name="horas_aula_pedagogicas_reemplazo"
                                        class="form-control @error('horas_aula_pedagogicas_reemplazo') is-invalid @enderror"
                                        value="{{ $v('horas_aula_pedagogicas_reemplazo') }}">
                                    @error('horas_aula_pedagogicas_reemplazo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>


        <div class="card mb-3">
            <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
                <span>Documentos</span>
                @if ($isEdit)
                    <span class="badge text-bg-info">Corrección: archivos opcionales</span>
                @endif
            </div>
            <div class="card-body">
                @if ($isEdit)
                    <div class="alert alert-info small mb-3">
                        Puedes reemplazar solo los documentos solicitados en la observación. Si no cargas un nuevo archivo, el sistema conservará el documento anterior.
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Oficio Solicitud de Reemplazo (PDF)
                            @if (!$isEdit)<span class="text-danger">*</span>@endif
                        </label>
                        <input type="file" id="oficio_pdf" name="oficio_pdf" class="form-control @error('oficio_pdf') is-invalid @enderror" accept="application/pdf"
                            @if (!$isEdit) required @endif>
                        <div class="form-text">Solo PDF. Tamaño máximo permitido: 10 MB.</div>
                        @if ($isEdit)
                            <div class="form-text">Opcional: carga un nuevo PDF solo si necesitas reemplazar el oficio actual.</div>
                            @if (!empty($solicitud->oficio_pdf_path))
                                <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                                    <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ route('gestion.solicitudes-reemplazo.oficio', $solicitud) }}">
                                        Ver Oficio actual
                                    </a>
                                    <span class="small text-muted">{{ basename($solicitud->oficio_pdf_path) }}</span>
                                </div>
                            @endif
                        @endif
                        @error('oficio_pdf')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Respaldo (Licencia/Permiso/etc.) (PDF)
                            @if (!$isEdit)<span class="text-danger">*</span>@endif
                        </label>
                        <input type="file" id="respaldo_pdf" name="respaldo_pdf" class="form-control @error('respaldo_pdf') is-invalid @enderror" accept="application/pdf"
                            @if (!$isEdit) required @endif>
                        <div class="form-text">Solo PDF. Tamaño máximo permitido: 10 MB.</div>
                        @if ($isEdit)
                            <div class="form-text">Opcional: carga un nuevo PDF solo si necesitas reemplazar el respaldo actual.</div>
                            @if (!empty($solicitud->respaldo_pdf_path))
                                <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                                    <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ route('gestion.solicitudes-reemplazo.respaldo', $solicitud) }}">
                                        Ver Respaldo actual
                                    </a>
                                    <span class="small text-muted">{{ basename($solicitud->respaldo_pdf_path) }}</span>
                                </div>
                            @endif
                        @endif
                        @error('respaldo_pdf')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 d-none" id="horarioTitularWrap">
                        <label class="form-label">Horario Titular (PDF)
                            @if (!$isEdit || empty($solicitud->horario_titular_pdf_path))<span class="text-danger">*</span>@endif
                        </label>
                        <input type="file" id="horario_titular_pdf" name="horario_titular_pdf" class="form-control @error('horario_titular_pdf') is-invalid @enderror" accept="application/pdf">
                        <div class="form-text">Debe subir el Horario de Clases del titular. Este documento se solicita solo cuando el titular tiene Estatuto DOCENTE.</div>
                        <div class="form-text">Solo PDF. Tamaño máximo permitido: 10 MB.</div>
                        @if ($isEdit && !empty($solicitud->horario_titular_pdf_path))
                            <div class="form-text">Opcional: carga un nuevo PDF solo si necesitas reemplazar el horario actual.</div>
                            <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                                <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ route('gestion.solicitudes-reemplazo.horario-titular', $solicitud) }}">
                                    Ver Horario Titular actual
                                </a>
                                <span class="small text-muted">{{ basename($solicitud->horario_titular_pdf_path) }}</span>
                            </div>
                        @elseif ($isEdit)
                            <div class="form-text">Debes adjuntar este PDF si el titular es docente y aún no existe un archivo cargado.</div>
                        @endif
                        @error('horario_titular_pdf')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Observaciones</div>
            <div class="card-body">
                <textarea name="observaciones" class="form-control" rows="4">{{ $v('observaciones') }}</textarea>
            </div>
        </div>

        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input @error('declaracion_responsabilidad_aceptada') is-invalid @enderror"
                    type="checkbox" value="1" id="declaracion_responsabilidad_aceptada"
                    name="declaracion_responsabilidad_aceptada"
                    @checked(old('declaracion_responsabilidad_aceptada', $isEdit ? (bool) ($solicitud->declaracion_responsabilidad_aceptada ?? false) : false)) required>
                <label class="form-check-label" for="declaracion_responsabilidad_aceptada">
                    Declaro, en nombre del Director(a) del establecimiento, que la información contenida en esta solicitud es veraz, completa y verificable, y que, cuando corresponda por Estatuto DOCENTE, las Horas Aula Cronológicas y Horas Aula Pedagógicas informadas para el titular y el reemplazo corresponden a la necesidad real del servicio. Asimismo, asumo la responsabilidad por la correcta entrega de estos antecedentes.
                </label>
                @error('declaracion_responsabilidad_aceptada')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-flex gap-2">
            @if (!$isEdit)
                <button class="btn btn-primary js-submit-solicitud" type="submit">Enviar</button>
            @else
                <button class="btn btn-primary js-submit-solicitud" type="submit" name="action" value="guardar">
                    Guardar cambios
                </button>

                @if (in_array($solicitud->estado, ['rechazada_uatp', 'rechazada_plani'], true))
                    <button class="btn btn-success js-submit-solicitud" type="submit" name="action" value="reenviar">
                        Guardar y reenviar a UATP
                    </button>
                @endif
            @endif

            <a class="btn btn-outline-secondary" href="{{ route('funcionario.solicitudes-reemplazo.index') }}">Volver</a>
        </div>

    </form>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet" />
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        (async function() {
            const isEdit = @json($isEdit ?? false);
            const initial = @json($initial ?? null);
            console.log(initial);
            const funcionarioUrl = @json($urls['funcionarios']);
            const funcionarioDetalleTpl = @json($urls['funcionarioDetalleTpl']);
            const areasAjaxUrl = @json($urls['areasDesempeno']);
            const postulantesUrl = @json($urls['postulantes']);
            const reglaMinimaUrl = @json($urls['reglaMinima'] ?? null);
            const solicitudId = @json($isEdit && isset($solicitud) ? $solicitud->id : null);
            const postulanteViewTpl = @json($urls['postulantePerfilViewTpl']);
            const postulanteDownloadTpl = @json($urls['postulantePerfilPdfTpl']);
            const postulanteCvViewTpl = @json($urls['postulanteCvViewTpl']);

            let bootingEdit = !!isEdit;
            let currentEstamento = '';
            let permisoSinGoceAutorizado = false;
            let reglaMinimaBloqueada = false;
            let reglaMinimaTimer = null;
            const permisoSinGoceTipo = 'Permiso sin goce de sueldo';

            const $func = $('#funcionarioSelect');
            const $post = $('#postulant_profile_id');

            const areaEl = document.getElementById('area_desempeno_id');
            const blockedAreas = new Set((@json($areasBloqueadasIds ?? []) || []).map(v => parseInt(v, 10)));
            const $submitBtns = $('.js-submit-solicitud');
            const $areaAlert = $('#areaBloqueadaAlert');
            const titularCronoEl = document.getElementById('horas_aula_cronologicas_titular');
            const titularPedaEl = document.getElementById('horas_aula_pedagogicas_titular');
            const reemplazoCronoEl = document.getElementById('horas_aula_cronologicas_reemplazo');
            const reemplazoPedaEl = document.getElementById('horas_aula_pedagogicas_reemplazo');
            const titularHorasWrap = document.getElementById('horasAulaTitularWrap');
            const reemplazoHorasWrap = document.getElementById('horasAulaReemplazoWrap');
            const titularHorasMaxLabel = document.getElementById('titularHorasMaxLabel');
            const reemplazoHorasMaxLabel = document.getElementById('reemplazoHorasMaxLabel');
            const horarioTitularWrap = document.getElementById('horarioTitularWrap');
            const horarioTitularInput = document.getElementById('horario_titular_pdf');
            const horarioTitularExisting = @json($isEdit ? !empty($solicitud->horario_titular_pdf_path) : false);
            const formEl = document.getElementById('solicitudReemplazoForm');
            const maxUploadBytes = 10 * 1024 * 1024;

            function updateAreaBlockedUI() {
                const val = parseInt(areaEl?.value || '0', 10);
                const isBlocked = !!val && blockedAreas.has(val);
                if (isBlocked) {
                    $areaAlert.removeClass('d-none');
                    $submitBtns.prop('disabled', true);
                } else {
                    $areaAlert.addClass('d-none');
                    $submitBtns.prop('disabled', reglaMinimaBloqueada);
                }
            }

            function getContinuidadValue() {
                const checked = document.querySelector('[name="continuidad"]:checked');
                return checked ? String(checked.value) : '';
            }

            function setReglaMinimaUI(result) {
                const alertEl = document.getElementById('reglaMinimaAlert');
                if (!alertEl) return;

                if (!result || result.evaluable === false) {
                    reglaMinimaBloqueada = false;
                    alertEl.className = 'alert mt-3 mb-0 d-none';
                    alertEl.textContent = '';
                    updateAreaBlockedUI();
                    return;
                }

                const duracion = Number(result.duracion_dias || 0);
                const continuidad = !!result.es_continuidad;
                const mensaje = result.mensaje || '';

                if (result.permitido) {
                    reglaMinimaBloqueada = false;
                    alertEl.className = 'alert alert-success mt-3 mb-0';
                    alertEl.innerHTML = continuidad
                        ? `<strong>Continuidad detectada.</strong><br>Existe una solicitud anterior correlativa para el mismo titular y reemplazante. Duración del nuevo tramo: ${duracion} día(s).`
                        : `<strong>Regla de reemplazo validada.</strong><br>Duración calculada: ${duracion} día(s).`;
                } else {
                    reglaMinimaBloqueada = true;
                    alertEl.className = 'alert alert-danger mt-3 mb-0';
                    alertEl.innerHTML = `<strong>No se puede enviar esta solicitud.</strong><br>${String(mensaje).replace(/\n/g, '<br>')}`;
                }

                updateAreaBlockedUI();
            }

            async function refreshReglaMinima() {
                if (!reglaMinimaUrl) return;

                const funcionarioId = $func.val() || '';
                const postulanteId = $post.val() || '';
                const fechaInicio = document.getElementById('fechaInicio')?.value || '';
                const fechaTermino = document.getElementById('fechaTermino')?.value || '';

                if (!funcionarioId || !fechaInicio || !fechaTermino) {
                    setReglaMinimaUI({ evaluable: false, permitido: true });
                    return;
                }

                const params = new URLSearchParams({
                    reemplazo_personal_id: funcionarioId,
                    postulant_profile_id: postulanteId,
                    area_desempeno_id: areaEl?.value || '',
                    fecha_inicio: fechaInicio,
                    fecha_termino: fechaTermino,
                    continuidad: getContinuidadValue() || '0',
                });
                if (solicitudId) params.set('solicitud_id', String(solicitudId));

                try {
                    const res = await fetch(`${reglaMinimaUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    setReglaMinimaUI(data);
                } catch (error) {
                    console.error('Error validando regla mínima de reemplazo', error);
                }
            }

            function refreshReglaMinimaDebounced() {
                clearTimeout(reglaMinimaTimer);
                reglaMinimaTimer = setTimeout(refreshReglaMinima, 250);
            }

            const propuestaWrap = document.getElementById('propuestaWrap');
            const reemplazoCard = document.getElementById('reemplazoCard');

            const reqSpan = document.getElementById('req-postulante');
            const postulanteEl = document.getElementById('postulant_profile_id');

            // Captura lo que viene desde Blade (en edit ya traes <option selected>):
            const bladePostId = $post.find('option:selected').val() || '';
            const bladePostText = ($post.find('option:selected').text() || '').trim();

            // -----------------------------
            // Datepickers (ya vienen d/m/Y desde Blade)
            // -----------------------------
            flatpickr('#fechaInicio', {
                dateFormat: 'd/m/Y',
                locale: 'es',
                defaultDate: document.getElementById('fechaInicio')?.value || null,
                onChange: refreshReglaMinimaDebounced
            });
            flatpickr('#fechaTermino', {
                dateFormat: 'd/m/Y',
                locale: 'es',
                defaultDate: document.getElementById('fechaTermino')?.value || null,
                onChange: refreshReglaMinimaDebounced
            });

            // -----------------------------
            // Helpers UI
            // -----------------------------
            function setPostulanteButtons(viewEnabled, viewHref = '#', cvEnabled = false, cvHref = '#', downHref = '#') {
                const btnV = document.getElementById('btnVerPdf');
                const btnCV = document.getElementById('btnVerCv');
                const btnD = document.getElementById('btnDescPdf');

                if (btnV) {
                    btnV.href = viewHref;
                    btnV.classList.toggle('disabled', !viewEnabled);
                    btnV.setAttribute('aria-disabled', viewEnabled ? 'false' : 'true');
                }

                if (btnCV) {
                    btnCV.href = cvHref;
                    btnCV.classList.toggle('disabled', !cvEnabled);
                    btnCV.setAttribute('aria-disabled', cvEnabled ? 'false' : 'true');
                }

                // El botón Descargar se mantiene (ruta no se elimina), pero se oculta en esta vista.
                if (btnD) {
                    btnD.href = downHref;
                    btnD.classList.toggle('disabled', !viewEnabled);
                    btnD.setAttribute('aria-disabled', viewEnabled ? 'false' : 'true');
                }
            }

            function isProponeActive() {
                const checked = document.querySelector('[name="propone_reemplazo"]:checked');
                return checked && String(checked.value) === '1';
            }

            function applyProponeState() {
                const active = isProponeActive();

                if (propuestaWrap) propuestaWrap.classList.toggle('d-none', !active);
                if (reqSpan) reqSpan.style.display = active ? '' : 'none';

                // Habilitar/deshabilitar select2 correctamente
                $post.prop('disabled', !active);

                if (!postulanteEl) return;

                if (active) {
                    postulanteEl.setAttribute('required', 'required');
                } else {
                    postulanteEl.removeAttribute('required');

                    // ⚠️ SOLO limpiar si NO estamos booteando edición
                    if (!bootingEdit) {
                        $post.val(null).trigger('change.select2');
                        setPostulanteButtons(false);
                    }
                }
                refreshReglaMinimaDebounced();
            }

            // -----------------------------
            // Tipo de reemplazo: activar Detalle (Otras)
            // -----------------------------
            const tipoReemplazoEl = document.getElementById('tipoReemplazo');
            const tipoOtroWrapEl = document.getElementById('tipoOtroWrap');
            const tipoOtroInputEl = document.getElementById('tipoReemplazoOtro');

            function applyTipoReemplazoState() {
                const isOtras = (tipoReemplazoEl?.value || '') === 'Otras';
                if (!tipoOtroWrapEl || !tipoOtroInputEl) return;

                tipoOtroWrapEl.classList.toggle('d-none', !isOtras);
                tipoOtroInputEl.required = isOtras;

                // Si NO es "Otras", limpiar el valor para evitar datos residuales
                if (!isOtras) {
                    tipoOtroInputEl.value = '';
                }
            }


            function applyPermisoSinGoceState() {
                if (!tipoReemplazoEl) return;

                const option = Array.from(tipoReemplazoEl.options).find(opt => opt.value === permisoSinGoceTipo);
                const alertEl = document.getElementById('permisoSinGoceAlert');
                const currentValue = tipoReemplazoEl.value;
                const shouldDisable = !permisoSinGoceAutorizado && currentValue !== permisoSinGoceTipo;

                if (option) {
                    option.disabled = shouldDisable;
                    option.textContent = shouldDisable
                        ? `${permisoSinGoceTipo} (sólo titulares autorizados)`
                        : permisoSinGoceTipo;
                }

                if (alertEl) {
                    alertEl.classList.toggle('d-none', currentValue !== permisoSinGoceTipo || permisoSinGoceAutorizado);
                }
            }

            function setPostulanteSelected(id, text) {
                if (!id) return;

                // evita duplicados
                if ($post.find(`option[value="${id}"]`).length === 0) {
                    const opt = new Option(text || String(id), id, true, true);
                    $post.append(opt);
                }
                $post.val(String(id)).trigger('change.select2');
            }

            // -----------------------------
            // Área desempeño (AJAX)
            // -----------------------------
            function setAreaEmpty(msg) {
                if (!areaEl) return;
                areaEl.innerHTML = `<option value="">${msg}</option>`;
                areaEl.disabled = true;
            }

            function setAreaLoading() {
                if (!areaEl) return;
                areaEl.innerHTML = `<option value="">Cargando áreas…</option>`;
                areaEl.disabled = true;
            }

            async function loadAreasForEstamento(estamento) {
                if (!areaEl) return;
                if (!estamento) {
                    setAreaEmpty('No hay estamento para cargar áreas');
                    return;
                }

                const selected = areaEl.dataset.selected || '';
                setAreaLoading();

                const params = new URLSearchParams({
                    estamento
                });
                const res = await fetch(`${areasAjaxUrl}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!res.ok) {
                    console.error('Error cargando áreas', res.status);
                    setAreaEmpty('Error al cargar áreas');
                    return;
                }

                const data = await res.json();

                const bloqueoAlert = document.getElementById('funcionarioBloqueadoAlert');
                const bloqueoMotivo = document.getElementById('funcionarioBloqueadoMotivo');
                if (data.funcionario?.bloqueado) {
                    if (bloqueoMotivo) bloqueoMotivo.textContent = data.funcionario?.bloqueo_motivo ? `Motivo: ${data.funcionario.bloqueo_motivo}` : '';
                    bloqueoAlert?.classList.remove('d-none');
                    $func.val(null).trigger('change.select2');
                    return;
                }
                bloqueoAlert?.classList.add('d-none');
                if (bloqueoMotivo) bloqueoMotivo.textContent = '';

                // ✅ estamento actual (docente|asistente) para filtrar postulantes
                currentEstamento = (data.funcionario?.estamento || '').toString();
                const items = data.results || [];

                areaEl.disabled = false;
                areaEl.innerHTML = `<option value="">Seleccione un área…</option>`;

                for (const it of items) {
                    const opt = document.createElement('option');
                    opt.value = String(it.id);
                    opt.textContent = it.text;
                    areaEl.appendChild(opt);
                }

                if (selected) areaEl.value = String(selected);
                if (!items.length) setAreaEmpty('No hay áreas para este estamento');
            }

            // Al cambiar el área manualmente, limpiar postulante (pero NO en boot edit)
            if (areaEl) {
                areaEl.addEventListener('change', () => {
                    if (bootingEdit) return;
                    $post.val(null).trigger('change.select2');
                    setPostulanteButtons(false);
                    updateAreaBlockedUI();
                });
            }

            // -----------------------------
            // Render tablas
            // -----------------------------
            function fmt(n) {
                return (Number(n) || 0).toFixed(2).replace(/\.00$/, '');
            }

            function escapeHtml(s) {
                return String(s || '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));
            }

            function escapeAttr(s) {
                return String(s || '').replace(/[^a-zA-Z0-9_\-]/g, '_');
            }

            function syncChronologicalInput(inputEl, labelEl, maxValue) {
                if (!labelEl) return;
                const safeMax = Math.max(Number(maxValue || 0), 0);
                labelEl.textContent = fmt(safeMax);

                if (!inputEl) return;
                inputEl.setAttribute('max', String(safeMax));

                let current = Number(inputEl.value || 0);
                if (current > safeMax) {
                    inputEl.value = safeMax;
                }
                if (current < 0) {
                    inputEl.value = 0;
                }
            }

            function bindChronologicalLimit(inputEl, getMax) {
                if (!inputEl) return;
                inputEl.addEventListener('input', () => {
                    const max = Math.max(Number(getMax() || 0), 0);
                    let value = Number(inputEl.value || 0);
                    if (value > max) value = max;
                    if (value < 0) value = 0;
                    inputEl.value = value;
                });
            }

            function clearHorasAulaValues() {
                [titularCronoEl, titularPedaEl, reemplazoCronoEl, reemplazoPedaEl].forEach(el => {
                    if (!el) return;
                    el.value = '0';
                });
            }

            function setHorasAulaRequired(required) {
                [titularCronoEl, titularPedaEl, reemplazoCronoEl, reemplazoPedaEl].forEach(el => {
                    if (!el) return;
                    el.required = required;
                });
            }

            function setHorarioTitularRequired(required) {
                if (!horarioTitularInput) return;
                horarioTitularInput.required = required;
            }

            function validateFileSize(inputEl, label) {
                if (!inputEl) return true;
                inputEl.setCustomValidity('');
                inputEl.classList.remove('is-invalid');

                const file = inputEl.files && inputEl.files[0] ? inputEl.files[0] : null;
                if (!file) return true;

                if (file.size > maxUploadBytes) {
                    const message = `El archivo ${label} no puede superar 10 MB.`;
                    inputEl.value = '';
                    inputEl.setCustomValidity(message);
                    inputEl.classList.add('is-invalid');
                    inputEl.reportValidity();
                    inputEl.setCustomValidity('');
                    return false;
                }

                return true;
            }

            function bindFileSizeValidation(inputEl, label) {
                if (!inputEl) return;
                inputEl.addEventListener('change', () => {
                    validateFileSize(inputEl, label);
                });
            }

            function applyHorarioTitularState(isDocente) {
                if (horarioTitularWrap) horarioTitularWrap.classList.toggle('d-none', !isDocente);
                setHorarioTitularRequired(isDocente && !horarioTitularExisting);

                if (!isDocente && horarioTitularInput) {
                    horarioTitularInput.value = '';
                }
            }

            function applyHorasAulaState(funcionario = null) {
                const hasFuncionario = !!funcionario;
                const estamento = (funcionario?.estamento || '').toString().toLowerCase();
                const estatuto = (funcionario?.estatuto || '').toString().trim().toUpperCase();
                const isDocente = hasFuncionario && (estamento === 'docente' || estatuto === 'DOCENTE' || estatuto === 'PROFESOR' || estatuto === 'PROFESORA' || estatuto.includes('DOC'));

                if (titularHorasWrap) titularHorasWrap.classList.toggle('d-none', !isDocente);
                if (reemplazoHorasWrap) reemplazoHorasWrap.classList.toggle('d-none', !isDocente);
                applyHorarioTitularState(isDocente);
                setHorasAulaRequired(isDocente);

                if (hasFuncionario && !isDocente) {
                    clearHorasAulaValues();
                }
            }

            function renderTitular(rows, totals) {
                const body = document.getElementById('tablaTitularBody');
                if (!body) return;
                body.innerHTML = '';

                (rows || []).forEach(r => {
                    body.insertAdjacentHTML('beforeend', `
                <tr>
                    <td>${escapeHtml(r.financiamiento)}</td>
                    <td class="text-end">${fmt(r.basica)}</td>
                    <td class="text-end">${fmt(r.media)}</td>
                    <td class="text-end">${fmt(r.total)}</td>
                </tr>
            `);
                });

                document.getElementById('t_basica').textContent = fmt(totals?.basica ?? 0);
                document.getElementById('t_media').textContent = fmt(totals?.media ?? 0);
                document.getElementById('t_total').textContent = fmt(totals?.total ?? 0);
                syncChronologicalInput(titularCronoEl, titularHorasMaxLabel, Number(totals?.total ?? 0));
            }

            function renderReemplazo(rows) {
                const body = document.getElementById('tablaReemplazoBody');
                if (!body) return;
                body.innerHTML = '';

                (rows || []).forEach(r => {
                    const fin = r.financiamiento;
                    const maxB = Number(r.basica || 0);
                    const maxM = Number(r.media || 0);

                    body.insertAdjacentHTML('beforeend', `
                <tr data-fin="${escapeHtml(fin)}">
                    <td>${escapeHtml(fin)}</td>
                    <td class="text-end">
                        <input type="number" step="0.01" min="0" max="${maxB}"
                            name="jornadas[${escapeAttr(fin)}][basica]"
                            class="form-control form-control-sm text-end js-h js-basica"
                            value="${maxB}">
                    </td>
                    <td class="text-end">
                        <input type="number" step="0.01" min="0" max="${maxM}"
                            name="jornadas[${escapeAttr(fin)}][media]"
                            class="form-control form-control-sm text-end js-h js-media"
                            value="${maxM}">
                    </td>
                    <td class="text-end fw-semibold js-total">${fmt(maxB + maxM)}</td>
                </tr>
            `);
                });

                body.querySelectorAll('.js-h').forEach(inp => {
                    inp.addEventListener('input', () => {
                        const max = Number(inp.getAttribute('max') || 0);
                        let v = Number(inp.value || 0);
                        if (v > max) v = max;
                        if (v < 0) v = 0;
                        inp.value = v;

                        const tr = inp.closest('tr');
                        const b = Number(tr.querySelector('.js-basica')?.value || 0);
                        const m = Number(tr.querySelector('.js-media')?.value || 0);
                        tr.querySelector('.js-total').textContent = fmt(b + m);
                        refreshReemplazoTotals();
                    });
                });

                refreshReemplazoTotals();
            }

            function refreshReemplazoTotals() {
                let b = 0,
                    m = 0,
                    t = 0;
                document.querySelectorAll('#tablaReemplazoBody tr').forEach(tr => {
                    const bb = Number(tr.querySelector('.js-basica')?.value || 0);
                    const mm = Number(tr.querySelector('.js-media')?.value || 0);
                    b += bb;
                    m += mm;
                    t += (bb + mm);
                });
                document.getElementById('r_basica').textContent = fmt(b);
                document.getElementById('r_media').textContent = fmt(m);
                document.getElementById('r_total').textContent = fmt(t);
                syncChronologicalInput(reemplazoCronoEl, reemplazoHorasMaxLabel, t);
            }

            // -----------------------------
            // Cargar funcionario + áreas + tablas
            // -----------------------------
            async function loadFuncionarioById(id, {
                resetPostulante = true
            } = {}) {
                const url = funcionarioDetalleTpl.replace('___ID___', encodeURIComponent(id));
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!res.ok) return;

                const data = await res.json();

                const bloqueoAlert = document.getElementById('funcionarioBloqueadoAlert');
                const bloqueoMotivo = document.getElementById('funcionarioBloqueadoMotivo');
                if (data.funcionario?.bloqueado) {
                    if (bloqueoMotivo) bloqueoMotivo.textContent = data.funcionario?.bloqueo_motivo ? `Motivo: ${data.funcionario.bloqueo_motivo}` : '';
                    bloqueoAlert?.classList.remove('d-none');
                    $func.val(null).trigger('change.select2');
                    return;
                }
                bloqueoAlert?.classList.add('d-none');
                if (bloqueoMotivo) bloqueoMotivo.textContent = '';

                // ✅ estamento actual (docente|asistente) para filtrar postulantes
                currentEstamento = (data.funcionario?.estamento || '').toString();
                permisoSinGoceAutorizado = !!data.funcionario?.permiso_sin_goce_autorizado;
                applyPermisoSinGoceState();

                // set área seleccionada (edit) ANTES de cargar opciones
                if (isEdit && initial?.area_desempeno_id && areaEl) {
                    areaEl.dataset.selected = String(initial.area_desempeno_id);
                }

                await loadAreasForEstamento(data.funcionario?.estamento || data.funcionario?.estatuto || '');

                document.getElementById('funcionarioCard')?.classList.remove('d-none');
                document.getElementById('f_rut').textContent = data.funcionario?.rut || '—';
                document.getElementById('f_nombres').textContent = data.funcionario?.nombre_full || '—';
                document.getElementById('f_estatuto').textContent = data.funcionario?.estatuto || '—';
                document.getElementById('f_escalafon').textContent = data.funcionario?.escalafon || '—';
                applyHorasAulaState(data.funcionario || null);

                const distribucion = data.distribucion || [];
                renderTitular(distribucion, data.totales);
                renderReemplazo(distribucion);

                // aplicar jornadas guardadas
                if (isEdit && initial?.jornadas) {
                    document.querySelectorAll('#tablaReemplazoBody tr').forEach(tr => {
                        const fin = tr.getAttribute('data-fin');
                        const saved = initial.jornadas?.[fin];
                        if (!saved) return;

                        const bInp = tr.querySelector('.js-basica');
                        const mInp = tr.querySelector('.js-media');

                        if (bInp) bInp.value = saved.basica ?? bInp.value;
                        if (mInp) mInp.value = saved.media ?? mInp.value;

                        const b = Number(bInp?.value || 0);
                        const m = Number(mInp?.value || 0);
                        tr.querySelector('.js-total').textContent = fmt(b + m);
                    });
                    refreshReemplazoTotals();
                }

                reemplazoCard?.classList.remove('d-none');

                // ⚠️ limpiar postulante SOLO si el usuario cambió funcionario (no en boot)
                if (resetPostulante && !bootingEdit) {
                    $post.val(null).trigger('change.select2');
                    setPostulanteButtons(false);
                    updateAreaBlockedUI();
                }
            }

            // -----------------------------
            // Select2 init
            // -----------------------------
            function formatFuncionarioResult(item) {
                if (!item || item.loading) return item?.text || '';

                const label = item.label || item.text || '';
                const bloqueado = !!item.bloqueado || !!item.disabled;
                const motivo = (item.bloqueo_motivo || '').toString().trim();
                const $wrap = $('<div class="d-flex flex-column gap-1"></div>');
                const $top = $('<div class="d-flex justify-content-between align-items-center gap-2"></div>');
                $top.append($('<span></span>').text(label));

                if (bloqueado) {
                    $wrap.addClass('opacity-75');
                    $top.append($('<span class="badge text-bg-danger"></span>').text('Bloqueado'));
                    $wrap.append($top);
                    $wrap.append($('<div class="small text-danger"></div>').text(motivo ? `Motivo: ${motivo}` : 'No seleccionable para solicitudes de reemplazo.'));
                } else {
                    $wrap.append($top);
                }

                return $wrap;
            }

            function formatFuncionarioSelection(item) {
                return item?.label || item?.text || '';
            }

            $func.select2({
                placeholder: 'Buscar funcionario...',
                ajax: {
                    url: funcionarioUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        term: params.term || ''
                    }),
                    processResults: data => data
                },
                templateResult: formatFuncionarioResult,
                templateSelection: formatFuncionarioSelection,
                escapeMarkup: markup => markup,
                width: '100%'
            });

            $func.on('select2:selecting', function(e) {
                const data = e.params.args?.data || e.params.data;
                if (data && (data.disabled || data.bloqueado)) {
                    e.preventDefault();
                    const alertEl = document.getElementById('funcionarioBloqueadoAlert');
                    const motivoEl = document.getElementById('funcionarioBloqueadoMotivo');
                    if (motivoEl) motivoEl.textContent = data.bloqueo_motivo ? `Motivo: ${data.bloqueo_motivo}` : '';
                    alertEl?.classList.remove('d-none');
                }
            });

            
            // -----------------------------
            // Select2 Postulante: render "bonito" (barra % docs) + bloqueo si incompleto
            // -----------------------------
            function clampPercent(p) {
                p = parseInt(p ?? 0, 10);
                if (isNaN(p)) p = 0;
                return Math.max(0, Math.min(100, p));
            }

            function formatPostulanteResult(item) {
                // Headers de grupo (optgroup) o placeholder
                if (!item || item.loading) return item?.text || '';
                if (item.children) return item.text;

                const label = item.label || item.text || '';
                const area = (item.area || '').toString().trim() || '—';
                const uploaded = parseInt(item.uploaded ?? 0, 10) || 0;
                const total = parseInt(item.total_required ?? 0, 10) || 0;
                const percent = clampPercent(item.percent ?? (total > 0 ? Math.round((uploaded * 100) / total) : 0));
                const disabled = !!item.disabled;
                const manualRestriction = !!item.manual_restriction;

                const $wrap = $('<div class="d-flex flex-column gap-1"></div>');
                if (disabled) $wrap.addClass('opacity-50');
                if (manualRestriction) {
                    $wrap.addClass('p-1 rounded');
                    $wrap.css({
                        backgroundColor: 'rgba(220,53,69,.10)',
                        border: '1px solid rgba(220,53,69,.25)'
                    });
                }

                const $top = $('<div class="d-flex justify-content-between align-items-center gap-2"></div>');
                const $name = $('<div class="fw-semibold"></div>').text(label);
                const $right = $('<div class="d-flex align-items-center gap-2"></div>');
                const $badge = $('<span class="badge"></span>')
                    .addClass(percent >= 100 ? 'bg-success' : 'bg-secondary')
                    .text(percent + '%');

                $right.append($badge);
                if (manualRestriction) {
                    $right.append($('<span class="badge text-bg-danger"></span>').text('Restricción manual'));
                }

                $top.append($name).append($right);

                const $meta = $('<div class="small text-muted"></div>').text(`Docs ${uploaded}/${total}`);
                const $area = $('<div class="small text-muted"></div>').text(`Área: ${area}`);

                const $progress = $('<div class="progress" style="height:6px;"></div>');
                const $bar = $('<div class="progress-bar" role="progressbar"></div>').css('width', percent + '%');
                $progress.append($bar);

                $wrap.append($top).append($meta).append($area).append($progress);
                if (manualRestriction) {
                    const reason = (item.manual_restriction_comment || '').toString().trim();
                    if (reason) {
                        $wrap.append($('<div class="small text-danger fw-semibold"></div>').text('Motivo: ' + reason));
                    }
                }
                return $wrap;
            }

            function formatPostulanteSelection(item) {
                if (!item) return '';
                if (item.children) return item.text || '';
                const label = item.label || item.text || '';
                const area = (item.area || '').toString().trim();
                const uploaded = item.uploaded;
                const total = item.total_required;
                const percent = item.percent;
                const restrictionTag = item.manual_restriction ? ' — Restricción manual' : '';

                if (uploaded !== undefined && total !== undefined && percent !== undefined) {
                    return `${label}${area ? ' — ' + area : ''} — Docs ${uploaded}/${total} (${percent}%)${restrictionTag}`;
                }
                return `${label}${area ? ' — ' + area : ''}${restrictionTag}`;
            }

$post.select2({
                placeholder: 'Buscar por nombres/apellidos o RUT...',
                minimumInputLength: 2,
                templateResult: formatPostulanteResult,
                templateSelection: formatPostulanteSelection,
                escapeMarkup: m => m,
                ajax: {
                    url: postulantesUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        term: params.term || '',
                        area_desempeno_id: areaEl ? areaEl.value : '',
                        estamento: currentEstamento || ''
                    }),
                    processResults: data => data
                },
                width: '100%'
            });

            // Bloqueo extra (defensivo): evita seleccionar postulantes incompletos
            $post.on('select2:selecting', function(e) {
                const data = e?.params?.args?.data;
                if (data && data.disabled) {
                    e.preventDefault();
                }
            });


            // -----------------------------
            // Eventos
            // -----------------------------
            document.querySelectorAll('[name="propone_reemplazo"]').forEach(el => {
                el.addEventListener('change', applyProponeState);
            });

            document.querySelectorAll('[name="continuidad"]').forEach(el => {
                el.addEventListener('change', refreshReglaMinimaDebounced);
            });

            ['fechaInicio', 'fechaTermino'].forEach(id => {
                document.getElementById(id)?.addEventListener('input', refreshReglaMinimaDebounced);
                document.getElementById(id)?.addEventListener('change', refreshReglaMinimaDebounced);
            });

            if (tipoReemplazoEl) {
                tipoReemplazoEl.addEventListener('change', () => {
                    applyTipoReemplazoState();
                    applyPermisoSinGoceState();
                });
            }

            $func.on('select2:select', function(e) {
                loadFuncionarioById(e.params.data.id, {
                    resetPostulante: true
                }).then(refreshReglaMinimaDebounced);
            });

            $post.on('select2:select', function(e) {
                const id = e.params.data.id;
                // ⚠️ Importante: como el selector puede listar postulantes de distintas áreas,
                // los botones (Ver / Ver CV) deben usar el área DEL POSTULANTE seleccionado
                // para evitar 403 por mismatch de parámetro.
                const selectedAreaId = (e?.params?.data?.area_desempeno_id ?? '') || (areaEl?.value || '');
                if (!id || !selectedAreaId) return setPostulanteButtons(false);

                const hasCv = !!e?.params?.data?.has_cv;

                const qs = `?area_desempeno_id=${encodeURIComponent(selectedAreaId)}`;
                setPostulanteButtons(
                    true,
                    postulanteViewTpl.replace('___ID___', id) + qs,
                    hasCv,
                    postulanteCvViewTpl.replace('___ID___', id) + qs,
                    postulanteDownloadTpl.replace('___ID___', id) + qs
                );
                refreshReglaMinimaDebounced();
            });

            $post.on('change', function() {
                if (!this.value) setPostulanteButtons(false);
                refreshReglaMinimaDebounced();
            });

            // -----------------------------
            // Diagnóstico: click en postulante deshabilitado → mostrar documentos faltantes en consola
            // -----------------------------
            function bindMissingDocsLogger() {
                const s2 = $post.data('select2');
                if (!s2 || !s2.$results) return;

                s2.$results.off('click.missingDocs');
                s2.$results.on('click.missingDocs', '.select2-results__option[aria-disabled="true"]', function() {
                    const data = $(this).data('data');
                    const missing = Array.isArray(data?.missing_docs) ? data.missing_docs : [];
                    if (missing.length) {
                        console.warn('Postulante NO seleccionable. Documentos faltantes:', missing.join(', '));
                    } else {
                        console.warn('Postulante NO seleccionable (sin detalle de faltantes en payload).', data);
                    }
                });
            }

            $post.on('select2:open', function() {
                bindMissingDocsLogger();
            });

            bindChronologicalLimit(titularCronoEl, () => Number(document.getElementById('t_total')?.textContent || 0));
            bindChronologicalLimit(reemplazoCronoEl, () => Number(document.getElementById('r_total')?.textContent || 0));
            applyHorasAulaState(null);
            bindFileSizeValidation(document.getElementById('oficio_pdf'), 'Oficio Solicitud de Reemplazo');
            bindFileSizeValidation(document.getElementById('respaldo_pdf'), 'Respaldo');
            bindFileSizeValidation(horarioTitularInput, 'Horario Titular');

            if (formEl) {
                formEl.addEventListener('submit', (event) => {
                    const ok = [
                        validateFileSize(document.getElementById('oficio_pdf'), 'Oficio Solicitud de Reemplazo'),
                        validateFileSize(document.getElementById('respaldo_pdf'), 'Respaldo'),
                        validateFileSize(horarioTitularInput, 'Horario Titular'),
                    ].every(Boolean);

                    if (!ok || reglaMinimaBloqueada) {
                        event.preventDefault();
                        event.stopPropagation();
                        window.scrollTo({ top: formEl.offsetTop - 20, behavior: 'smooth' });
                    }
                });
            }


            // -----------------------------
            // BOOT EDIT
            // -----------------------------
            applyProponeState(); // estado inicial
            applyTipoReemplazoState(); // estado inicial tipo
            applyPermisoSinGoceState();

            if (isEdit && initial?.funcionario?.id) {
                // asegurar opción del funcionario seleccionada en select2
                if ($func.find(`option[value="${initial.funcionario.id}"]`).length === 0) {
                    $func.append(new Option(initial.funcionario.text, initial.funcionario.id, true, true));
                }
                $func.val(String(initial.funcionario.id)).trigger('change.select2');

                await loadFuncionarioById(initial.funcionario.id, {
                    resetPostulante: false
                });

                // si propone y hay postulante, setearlo DESPUÉS de cargar funcionario/área
                if (isProponeActive() && bladePostId) {
                    $post.prop('disabled', false);

                    // usa el texto real de Blade (no el "-" del JSON)
                    setPostulanteSelected(bladePostId, bladePostText);

                    const areaId = areaEl?.value || (initial?.area_desempeno_id ? String(initial
                        .area_desempeno_id) : '');
                    if (areaId) {
                        // En edición, si el JSON trae el área del postulante, preferirla.
                        const selectedAreaId = (initial?.postulante?.area_desempeno_id ? String(initial.postulante.area_desempeno_id) : areaId);
                        const qs = `?area_desempeno_id=${encodeURIComponent(selectedAreaId)}`;
                        const hasCv = !!(initial?.postulante?.has_cv);
                        setPostulanteButtons(
                            true,
                            postulanteViewTpl.replace('___ID___', bladePostId) + qs,
                            hasCv,
                            postulanteCvViewTpl.replace('___ID___', bladePostId) + qs,
                            postulanteDownloadTpl.replace('___ID___', bladePostId) + qs
                        );
                    }
                }

                bootingEdit = false;
            updateAreaBlockedUI();
            refreshReglaMinimaDebounced();
            } else {
                bootingEdit = false;
                refreshReglaMinimaDebounced();
            }

        })();
    </script>
@endpush
