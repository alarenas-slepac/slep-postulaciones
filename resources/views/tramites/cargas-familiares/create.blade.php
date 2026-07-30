@extends('layouts.app')

@section('content')
@php
    $beneficiario = (array) $beneficiario;
    $oldCausantes = old('causantes', [[
        'carga_familiar_id' => '',
        'run' => '',
        'dv' => '',
        'apellido_paterno' => '',
        'apellido_materno' => '',
        'nombres' => '',
        'sexo' => '',
        'parentesco' => 'hijo_hija',
        'codigo_tipo_beneficio' => '01',
        'codigo_tipo_causante' => '04',
        'fecha_nacimiento' => '',
        'fecha_inicio_beneficio' => now()->toDateString(),
        'observaciones' => '',
    ]]);
    $incomeColumns = [
        'mismo_empleador' => 'Mismo empleador',
        'otros_empleadores' => 'Otros empleadores',
        'trabajador_independiente' => 'Independiente',
        'subsidios' => 'Subsidios',
        'pensiones_misma_entidad' => 'Pensiones misma entidad',
        'otras_pensiones' => 'Otras pensiones',
    ];
    $parentescoOptions = $parentescoOptions ?? [
        'conyuge' => 'Cónyuge',
        'hijo_hija' => 'Hijo/a',
        'hijastro_hijastra' => 'Hijastro/a',
        'nieto_nieta' => 'Nieto/a',
        'bisnieto_bisnieta' => 'Bisnieto/a',
        'madre_viuda' => 'Madre viuda',
        'ascendiente' => 'Ascendiente',
        'madre_filiacion_no_matrimonial' => 'Madre de hijos de filiación no matrimonial',
        'trabajadora_embarazada' => 'Trabajadora embarazada',
        'conyuge_embarazada' => 'Cónyuge embarazada',
        'menor_a_cargo' => 'Menor a cargo por medida de protección',
        'extranjero' => 'Extranjero',
        'otro' => 'Otro según respaldo',
    ];
    $beneficioOptions = $beneficioOptions ?? [
        '01' => '01 · Asignación familiar',
        '02' => '02 · Asignación maternal',
    ];
    $causanteOptions = $causanteOptions ?? [
        '01' => '01 · Cónyuge mujer o varón',
        '02' => '02 · Cónyuge en situación de discapacidad',
        '04' => '04 · Hijo/a, hijo/a adoptado/a o hijastro/a menor o igual a 18 años',
        '05' => '05 · Hijo/a, hijo/a adoptado/a o hijastro/a en situación de discapacidad sin límite de edad',
        '06' => '06 · Hijo/a, hijo/a adoptado/a o hijastro/a estudiante entre 18 y 24 años',
        '07' => '07 · Nieto/a o bisnieto/a huérfano/a o abandonado/a, menor o igual a 18 años',
        '08' => '08 · Nieto/a o bisnieto/a en situación de discapacidad sin límite de edad',
        '09' => '09 · Madre viuda',
        '10' => '10 · Ascendiente mayor de 65 años',
        '11' => '11 · Ascendiente en situación de discapacidad sin límite de edad',
        '17' => '17 · Nieto/a o bisnieto/a estudiante entre 18 y 24 años',
        '18' => '18 · Niño/a huérfano/a o abandonado/a menor de 18 años al cuidado de institución',
        '19' => '19 · Nieto/a huérfano/a o abandonado/a estudiante al cuidado de institución',
        '20' => '20 · Niño/a huérfano/a o abandonado/a en situación de discapacidad al cuidado de institución',
        '21' => '21 · Trabajadora embarazada',
        '22' => '22 · Cónyuge embarazada',
        '26' => '26 · Menor a cargo por medida de protección, menor o igual a 18 años',
        '27' => '27 · Menor a cargo en situación de discapacidad',
        '28' => '28 · Menor a cargo estudiante entre 18 y 24 años',
        '29' => '29 · Acuerdo de unión civil: hijo/a del otro conviviente civil',
        '30' => '30 · Extranjero',
    ];
    $documentacionCausantes = $documentacionCausantes ?? [];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Nueva solicitud · Mis Cargas Familiares</h1>
        <div class="text-muted small">Completa el formulario de solicitud, la declaración jurada de ingresos y adjunta los documentos obligatorios.</div>
    </div>
    <a href="{{ route('tramites.cargas-familiares.index') }}" class="btn btn-outline-secondary">Volver</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Revisa los datos ingresados:</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('tramites.cargas-familiares.store') }}" enctype="multipart/form-data" class="js-validate" novalidate>
    @csrf

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">1. Identificación del beneficiario</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">RUN</label>
                    <input type="text" class="form-control" value="{{ $beneficiario['rut'] ?? $user->rut }}" readonly>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" class="form-control" value="{{ $beneficiario['nombre_completo'] ?? $user->nombre_completo }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Domicilio</label>
                    <input type="text" name="beneficiario[domicilio]" class="form-control @error('beneficiario.domicilio') is-invalid @enderror" value="{{ old('beneficiario.domicilio', $beneficiario['domicilio'] ?? '') }}">
                    @error('beneficiario.domicilio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Comuna</label>
                    <input type="text" name="beneficiario[comuna]" class="form-control @error('beneficiario.comuna') is-invalid @enderror" value="{{ old('beneficiario.comuna', $beneficiario['comuna'] ?? '') }}">
                    @error('beneficiario.comuna')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ciudad</label>
                    <input type="text" name="beneficiario[ciudad]" class="form-control @error('beneficiario.ciudad') is-invalid @enderror" value="{{ old('beneficiario.ciudad', $beneficiario['ciudad'] ?? '') }}">
                    @error('beneficiario.ciudad')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-1">
                    <label class="form-label">Reg.</label>
                    <input type="text" name="beneficiario[region]" class="form-control @error('beneficiario.region') is-invalid @enderror" value="{{ old('beneficiario.region', $beneficiario['region'] ?? '') }}">
                    @error('beneficiario.region')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Correo</label>
                    <input type="email" name="beneficiario[correo]" class="form-control @error('beneficiario.correo') is-invalid @enderror" value="{{ old('beneficiario.correo', $beneficiario['correo'] ?? $user->email) }}">
                    @error('beneficiario.correo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">2. Datos de la solicitud oficial</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo de solicitud <span class="text-danger">*</span></label>
                    <select name="tipo_solicitud" id="tipo_solicitud" class="form-select @error('tipo_solicitud') is-invalid @enderror" required>
                        <option value="nueva_carga" @selected(old('tipo_solicitud', 'nueva_carga') === 'nueva_carga')>Inscripción de nuevo causante</option>
                        <option value="actualizacion" @selected(old('tipo_solicitud') === 'actualizacion')>Actualización de carga existente</option>
                    </select>
                    @error('tipo_solicitud')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">¿Solicita pago directo del beneficio? <span class="text-danger">*</span></label>
                    <select name="solicita_pago_directo" class="form-select @error('solicita_pago_directo') is-invalid @enderror" required>
                        <option value="0" @selected(old('solicita_pago_directo', '0') === '0')>No</option>
                        <option value="1" @selected(old('solicita_pago_directo') === '1')>Sí</option>
                    </select>
                    @error('solicita_pago_directo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="solicitante_distinto" value="0">
                        <input type="checkbox" name="solicitante_distinto" id="solicitante_distinto" value="1" class="form-check-input" @checked(old('solicitante_distinto'))>
                        <label for="solicitante_distinto" class="form-check-label">El solicitante es distinto del beneficiario</label>
                    </div>
                </div>
            </div>

            <div class="border rounded p-3 bg-light mt-3 {{ old('solicitante_distinto') ? '' : 'd-none' }}" id="solicitante_box">
                <div class="fw-semibold mb-2">Identificación del solicitante distinto del beneficiario</div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Nombre o razón social</label><input type="text" name="solicitante[nombre]" class="form-control" value="{{ old('solicitante.nombre') }}"></div>
                    <div class="col-md-2"><label class="form-label">RUN/RUT</label><input type="text" name="solicitante[rut]" class="form-control" value="{{ old('solicitante.rut') }}"></div>
                    <div class="col-md-6"><label class="form-label">Domicilio</label><input type="text" name="solicitante[domicilio]" class="form-control" value="{{ old('solicitante.domicilio') }}"></div>
                    <div class="col-md-3"><label class="form-label">Comuna</label><input type="text" name="solicitante[comuna]" class="form-control" value="{{ old('solicitante.comuna') }}"></div>
                    <div class="col-md-3"><label class="form-label">Ciudad</label><input type="text" name="solicitante[ciudad]" class="form-control" value="{{ old('solicitante.ciudad') }}"></div>
                    <div class="col-md-2"><label class="form-label">Región</label><input type="text" name="solicitante[region]" class="form-control" value="{{ old('solicitante.region') }}"></div>
                    <div class="col-md-4"><label class="form-label">Correo</label><input type="email" name="solicitante[correo]" class="form-control" value="{{ old('solicitante.correo') }}"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">3. Declaración Jurada de Ingresos para actualización del valor de la asignación familiar</div>
        <div class="card-body">
            <div class="alert alert-info small">
                Este formulario web reproduce la información base de la declaración jurada: condición del beneficiario, alternativa declarada e ingresos mensuales por categoría. Además debes adjuntar el PDF firmado en la sección de documentos generales.
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Condición <span class="text-danger">*</span></label>
                    <select name="declaracion_ingresos[condicion]" class="form-select" required>
                        <option value="trabajador" @selected(old('declaracion_ingresos.condicion', 'trabajador') === 'trabajador')>Trabajador</option>
                        <option value="pensionado" @selected(old('declaracion_ingresos.condicion') === 'pensionado')>Pensionado</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Alternativa declarada <span class="text-danger">*</span></label>
                    <select name="declaracion_ingresos[alternativa]" id="declaracion_alternativa" class="form-select" required>
                        <option value="sin_otros_ingresos" @selected(old('declaracion_ingresos.alternativa', 'sin_otros_ingresos') === 'sin_otros_ingresos')>a) No haber percibido otros ingresos</option>
                        <option value="mas_de_un_ingreso" @selected(old('declaracion_ingresos.alternativa') === 'mas_de_un_ingreso')>b) Haber percibido más de un ingreso</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Año enero-junio</label>
                    <input type="number" name="declaracion_ingresos[anio_primer_semestre]" min="2024" max="2100" class="form-control" value="{{ old('declaracion_ingresos.anio_primer_semestre', now()->year) }}" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="declaracion_ingresos[declara_segundo_semestre]" value="0">
                        <input type="checkbox" name="declaracion_ingresos[declara_segundo_semestre]" id="declara_segundo_semestre" value="1" class="form-check-input" @checked(old('declaracion_ingresos.declara_segundo_semestre'))>
                        <label class="form-check-label" for="declara_segundo_semestre">Incluye jul-dic anterior</label>
                    </div>
                </div>
            </div>

            <div id="ingresos_detalle" class="{{ old('declaracion_ingresos.alternativa', 'sin_otros_ingresos') === 'mas_de_un_ingreso' ? '' : 'd-none' }}">
                <div class="fw-semibold mb-2">Detalle enero a junio</div>
                @include('tramites.cargas-familiares.partials.ingresos-table', [
                    'fieldPrefix' => 'declaracion_ingresos[ingresos_primer_semestre]',
                    'oldPrefix' => 'declaracion_ingresos.ingresos_primer_semestre',
                    'meses' => $mesesPrimerSemestre,
                    'incomeColumns' => $incomeColumns,
                ])

                <div id="segundo_semestre_box" class="mt-3 {{ old('declaracion_ingresos.declara_segundo_semestre') ? '' : 'd-none' }}">
                    <div class="row g-3 align-items-end mb-2">
                        <div class="col-md-4">
                            <div class="fw-semibold">Detalle julio a diciembre del año anterior</div>
                            <div class="small text-muted">Aplica para contratos por obra, faena o plazo fijo no superior a seis meses.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Año</label>
                            <input type="number" name="declaracion_ingresos[anio_segundo_semestre]" min="2023" max="2100" class="form-control" value="{{ old('declaracion_ingresos.anio_segundo_semestre', now()->subYear()->year) }}">
                        </div>
                    </div>
                    @include('tramites.cargas-familiares.partials.ingresos-table', [
                        'fieldPrefix' => 'declaracion_ingresos[ingresos_segundo_semestre]',
                        'oldPrefix' => 'declaracion_ingresos.ingresos_segundo_semestre',
                        'meses' => $mesesSegundoSemestre,
                        'incomeColumns' => $incomeColumns,
                    ])
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>4. Identificación de causantes</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add_causante"><i class="bi bi-plus-circle"></i> Agregar causante</button>
        </div>
        <div class="card-body">
            <div class="alert alert-info small mb-3">
                <div class="fw-semibold mb-2">Ayuda para completar códigos del formulario oficial</div>
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="fw-semibold">Código sexo</div>
                        <ul class="mb-0 ps-3">
                            <li>01 Masculino</li>
                            <li>02 Femenino</li>
                        </ul>
                    </div>
                    <div class="col-lg-4">
                        <div class="fw-semibold">Código beneficio</div>
                        <ul class="mb-0 ps-3">
                            @foreach ($beneficioOptions as $beneficioHelp)
                                <li>{{ $beneficioHelp }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-lg-4">
                        <div class="fw-semibold">Causantes más frecuentes</div>
                        <ul class="mb-0 ps-3">
                            <li>04 Hijo/a menor o igual a 18 años.</li>
                            <li>06 Hijo/a estudiante entre 18 y 24 años.</li>
                            <li>21 Trabajadora embarazada.</li>
                            <li>22 Cónyuge embarazada.</li>
                        </ul>
                    </div>
                </div>
                <details class="mt-2">
                    <summary class="fw-semibold">Ver todos los códigos de causante</summary>
                    <div class="row row-cols-1 row-cols-md-2 g-1 mt-2">
                        @foreach ($causanteOptions as $causanteHelp)
                            <div class="col">{{ $causanteHelp }}</div>
                        @endforeach
                    </div>
                </details>
            </div>
            <div id="causantes_wrapper">
                @foreach ($oldCausantes as $index => $causante)
                    @include('tramites.cargas-familiares.partials.causante-row', [
                        'index' => $index,
                        'causante' => $causante,
                        'cargasVigentes' => $cargasVigentes,
                        'parentescoOptions' => $parentescoOptions,
                        'beneficioOptions' => $beneficioOptions,
                        'causanteOptions' => $causanteOptions,
                        'documentacionCausantes' => $documentacionCausantes,
                    ])
                @endforeach
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">5. Documentos generales de la solicitud</div>
        <div class="card-body">
            <div class="alert alert-light border small mb-3">
                Descarga las plantillas oficiales, complétalas y súbelas firmadas en PDF. El sistema mantiene el formulario web para registro estructurado, pero estos archivos siguen siendo respaldo obligatorio de la solicitud.
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                            <div>
                                <label class="form-label mb-1">Formulario de Solicitud de Asignación Familiar y Maternal firmado <span class="text-danger">*</span></label>
                                <div class="form-text mt-0">Plantilla oficial adjunta al submódulo.</div>
                            </div>
                            <a href="{{ route('tramites.cargas-familiares.document-template', 'formulario_solicitud_asignacion') }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-file-earmark-pdf"></i> Plantilla
                            </a>
                        </div>
                        <input type="file" name="documentos_solicitud[formulario_solicitud_asignacion]" class="form-control @error('documentos_solicitud.formulario_solicitud_asignacion') is-invalid @enderror" accept="application/pdf,.pdf" required>
                        @error('documentos_solicitud.formulario_solicitud_asignacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                            <div>
                                <label class="form-label mb-1">Declaración Jurada de Ingresos firmada <span class="text-danger">*</span></label>
                                <div class="form-text mt-0">Plantilla oficial adjunta al submódulo.</div>
                            </div>
                            <a href="{{ route('tramites.cargas-familiares.document-template', 'declaracion_jurada_ingresos') }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-file-earmark-pdf"></i> Plantilla
                            </a>
                        </div>
                        <input type="file" name="documentos_solicitud[declaracion_jurada_ingresos_pdf]" class="form-control @error('documentos_solicitud.declaracion_jurada_ingresos_pdf') is-invalid @enderror" accept="application/pdf,.pdf" required>
                        @error('documentos_solicitud.declaracion_jurada_ingresos_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">6. Declaración del beneficiario solicitante</div>
        <div class="card-body">
            <div class="small text-muted mb-3">
                Declaro que las personas invocadas como causantes viven a mis expensas, no perciben rentas iguales o superiores al 50% del ingreso mínimo mensual, no han sido invocadas ante otra entidad pagadora del beneficio y que los mayores de 18 años cumplen la acreditación de estudios regulares cuando corresponda.
            </div>
            <div class="form-check">
                <input type="checkbox" name="declaracion_aceptada" id="declaracion_aceptada" value="1" class="form-check-input @error('declaracion_aceptada') is-invalid @enderror" @checked(old('declaracion_aceptada')) required>
                <label for="declaracion_aceptada" class="form-check-label">Acepto y declaro bajo juramento que la información ingresada es verdadera.</label>
                @error('declaracion_aceptada')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" id="submit_solicitud" class="btn btn-primary" disabled><i class="bi bi-send"></i> Enviar solicitud</button>
        <a href="{{ route('tramites.cargas-familiares.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <div class="small text-muted align-self-center" id="submit_help">El botón se habilitará al completar todos los campos obligatorios, cargar los documentos requeridos y aceptar la declaración.</div>
    </div>
</form>

<template id="causante_template">
    @include('tramites.cargas-familiares.partials.causante-row', [
        'index' => '__INDEX__',
        'causante' => [
            'carga_familiar_id' => '', 'run' => '', 'dv' => '', 'apellido_paterno' => '', 'apellido_materno' => '', 'nombres' => '', 'sexo' => '', 'parentesco' => 'hijo_hija', 'codigo_tipo_beneficio' => '01', 'codigo_tipo_causante' => '04', 'fecha_nacimiento' => '', 'fecha_inicio_beneficio' => now()->toDateString(), 'observaciones' => '',
        ],
        'cargasVigentes' => $cargasVigentes,
        'parentescoOptions' => $parentescoOptions,
        'beneficioOptions' => $beneficioOptions,
        'causanteOptions' => $causanteOptions,
        'documentacionCausantes' => $documentacionCausantes,
    ])
</template>
@endsection

@push('scripts')
@include('partials.form-validation')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const solicitanteCheck = document.getElementById('solicitante_distinto');
    const solicitanteBox = document.getElementById('solicitante_box');
    solicitanteCheck?.addEventListener('change', () => solicitanteBox?.classList.toggle('d-none', !solicitanteCheck.checked));

    const alternativa = document.getElementById('declaracion_alternativa');
    const ingresosDetalle = document.getElementById('ingresos_detalle');
    alternativa?.addEventListener('change', () => ingresosDetalle?.classList.toggle('d-none', alternativa.value !== 'mas_de_un_ingreso'));

    const declaraSegundo = document.getElementById('declara_segundo_semestre');
    const segundoBox = document.getElementById('segundo_semestre_box');
    declaraSegundo?.addEventListener('change', () => segundoBox?.classList.toggle('d-none', !declaraSegundo.checked));

    const wrapper = document.getElementById('causantes_wrapper');
    const template = document.getElementById('causante_template');
    let nextIndex = {{ count($oldCausantes) }};

    function setSelectByValueOrText(select, incomingValue) {
        if (!select) return;
        const value = String(incomingValue || '').trim();
        if (!value) {
            select.value = '';
            return;
        }
        select.value = value;
        if (select.value === value) return;

        const normalized = value.toLocaleLowerCase('es-CL');
        for (const option of select.options) {
            const optionText = option.textContent.trim().toLocaleLowerCase('es-CL');
            if (optionText.includes(normalized) || normalized.includes(optionText.replace(/^\d+\s*·\s*/, ''))) {
                select.value = option.value;
                return;
            }
        }
    }

    const documentacionCausantes = @json($documentacionCausantes);

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getCausanteIndex(row) {
        const input = row.querySelector('[name^="causantes["]');
        const match = input?.name?.match(/^causantes\[([^\]]+)\]/);
        return match ? match[1] : row.dataset.causanteIndex;
    }

    function renderDocumentacion(row) {
        const codigoSelect = row.querySelector('.js-codigo-causante');
        const parentescoSelect = row.querySelector('.js-parentesco-causante');
        const codigo = codigoSelect?.value || '';
        const config = documentacionCausantes[codigo] || null;
        const info = row.querySelector('.js-documentacion-causante-info');
        const container = row.querySelector('.js-documentos-causante');
        const index = getCausanteIndex(row);

        if (!container || !info) return;

        if (!config) {
            info.textContent = 'Selecciona un Codigo Tipo de Causante para ver los documentos exigidos por el formulario oficial.';
            container.innerHTML = '';
            return;
        }

        if (parentescoSelect && config.parentesco) {
            parentescoSelect.value = config.parentesco;
        }

        const required = Array.isArray(config.required) ? config.required : [];
        const conditional = Array.isArray(config.conditional) ? config.conditional : [];
        const requiredList = required.length
            ? '<ul class="mb-0 ps-3">' + required.map(doc => '<li>' + escapeHtml(doc.label) + '</li>').join('') + '</ul>'
            : '<div>No registra documentos obligatorios especificos.</div>';
        const conditionalList = conditional.length
            ? '<div class="mt-2"><span class="fw-semibold">Condicionales:</span><ul class="mb-0 ps-3">' + conditional.map(item => '<li>' + escapeHtml(item.question) + ': ' + escapeHtml(item.label) + '</li>').join('') + '</ul></div>'
            : '';

        info.innerHTML = '<div class="fw-semibold mb-1">Codigo ' + escapeHtml(codigo) + ': ' + escapeHtml(config.name) + '</div>'
            + '<div><span class="fw-semibold">Documentos obligatorios:</span>' + requiredList + '</div>'
            + conditionalList;

        let html = '';
        required.forEach(function(doc) {
            html += '<div class="col-md-4">'
                + '<label class="form-label">' + escapeHtml(doc.label) + ' <span class="text-danger">*</span></label>'
                + '<input type="file" name="documentos_causantes[' + escapeHtml(index) + '][' + escapeHtml(doc.key) + ']" class="form-control" accept="application/pdf,.pdf" required>'
                + '</div>';
        });

        conditional.forEach(function(item) {
            html += '<div class="col-md-4">'
                + '<div class="border rounded p-3 h-100 bg-white">'
                + '<div class="form-check mb-2">'
                + '<input class="form-check-input js-condicion-documento" type="checkbox" value="1" id="cond_' + escapeHtml(index) + '_' + escapeHtml(item.key) + '" name="documentos_causantes_condiciones[' + escapeHtml(index) + '][' + escapeHtml(item.key) + ']" data-target="doc_' + escapeHtml(index) + '_' + escapeHtml(item.document) + '">'
                + '<label class="form-check-label" for="cond_' + escapeHtml(index) + '_' + escapeHtml(item.key) + '">' + escapeHtml(item.question) + '</label>'
                + '</div>'
                + '<label class="form-label">' + escapeHtml(item.label) + '</label>'
                + '<input type="file" id="doc_' + escapeHtml(index) + '_' + escapeHtml(item.document) + '" name="documentos_causantes[' + escapeHtml(index) + '][' + escapeHtml(item.document) + ']" class="form-control" accept="application/pdf,.pdf" disabled>'
                + (item.help ? '<div class="form-text">' + escapeHtml(item.help) + '</div>' : '')
                + '</div>'
                + '</div>';
        });

        container.innerHTML = html;
        container.querySelectorAll('.js-condicion-documento').forEach(function(check) {
            function toggle() {
                const target = document.getElementById(check.dataset.target);
                if (!target) return;
                target.disabled = !check.checked;
                target.required = check.checked;
                if (!check.checked) target.value = '';
                updateSubmitState();
            }
            check.addEventListener('change', toggle);
            toggle();
        });

        updateSubmitState();
    }

    function bindCausante(row) {
        const fecha = row.querySelector('.js-fecha-nacimiento');
        const edadLabel = row.querySelector('.js-edad-label');
        const selectCarga = row.querySelector('.js-carga-vigente');
        const codigoCausante = row.querySelector('.js-codigo-causante');

        function updateEdad() {
            if (!fecha || !fecha.value) {
                if (edadLabel) edadLabel.textContent = 'Edad: -';
                return;
            }
            const born = new Date(fecha.value + 'T00:00:00');
            const today = new Date();
            let age = today.getFullYear() - born.getFullYear();
            const m = today.getMonth() - born.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < born.getDate())) age--;
            if (edadLabel) edadLabel.textContent = 'Edad: ' + age + ' anos';
        }

        fecha?.addEventListener('change', function() {
            updateEdad();
            updateSubmitState();
        });
        updateEdad();

        codigoCausante?.addEventListener('change', function() {
            renderDocumentacion(row);
        });

        selectCarga?.addEventListener('change', function() {
            const opt = selectCarga.selectedOptions[0];
            if (!opt || !opt.dataset.payload) return;
            const data = JSON.parse(opt.dataset.payload);
            row.querySelector('[name$="[run]"]').value = data.run || '';
            row.querySelector('[name$="[dv]"]').value = data.dv || '';
            row.querySelector('[name$="[apellido_paterno]"]').value = data.apellido_paterno || '';
            row.querySelector('[name$="[apellido_materno]"]').value = data.apellido_materno || '';
            row.querySelector('[name$="[nombres]"]').value = data.nombres || '';
            row.querySelector('[name$="[sexo]"]').value = data.sexo || '';
            setSelectByValueOrText(row.querySelector('[name$="[parentesco]"]'), data.parentesco || '');
            if (data.codigo_tipo_beneficio) setSelectByValueOrText(row.querySelector('[name$="[codigo_tipo_beneficio]"]'), data.codigo_tipo_beneficio || '');
            if (data.codigo_tipo_causante) setSelectByValueOrText(row.querySelector('[name$="[codigo_tipo_causante]"]'), data.codigo_tipo_causante || '');
            row.querySelector('[name$="[fecha_nacimiento]"]').value = data.fecha_nacimiento || '';
            row.querySelector('[name$="[fecha_inicio_beneficio]"]').value = data.fecha_inicio || '';
            updateEdad();
            renderDocumentacion(row);
        });

        row.querySelector('.js-remove-causante')?.addEventListener('click', function() {
            if (wrapper.querySelectorAll('.causante-card').length > 1) {
                row.remove();
                if (typeof updateSubmitState === 'function') updateSubmitState();
            }
        });

        renderDocumentacion(row);
    }

    const form = document.querySelector('form.js-validate');
    const submitButton = document.getElementById('submit_solicitud');
    const submitHelp = document.getElementById('submit_help');
    const declaracionAceptada = document.getElementById('declaracion_aceptada');

    function updateSubmitState() {
        if (!form || !submitButton) return;
        const complete = form.checkValidity() && Boolean(declaracionAceptada?.checked);
        submitButton.disabled = !complete;
        if (submitHelp) {
            submitHelp.classList.toggle('text-success', complete);
            submitHelp.classList.toggle('text-muted', !complete);
            submitHelp.textContent = complete
                ? 'Solicitud completa: ya puedes enviarla.'
                : 'El botón se habilitará al completar todos los campos obligatorios, cargar los documentos requeridos y aceptar la declaración.';
        }
    }

    document.querySelectorAll('.causante-card').forEach(bindCausante);
    document.getElementById('add_causante')?.addEventListener('click', function() {
        const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        const div = document.createElement('div');
        div.innerHTML = html.trim();
        const row = div.firstElementChild;
        wrapper.appendChild(row);
        bindCausante(row);
        nextIndex++;
        updateSubmitState();
    });

    form?.addEventListener('input', updateSubmitState);
    form?.addEventListener('change', updateSubmitState);
    updateSubmitState();
});
</script>
@endpush
