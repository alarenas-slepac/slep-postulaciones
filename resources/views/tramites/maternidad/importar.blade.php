@extends('layouts.app')
@section('title', 'Importar casos de maternidad')
@include('tramites.maternidad.partials._assets')
@section('content')
<div class="slep-listing mat-workspace" data-slep-listing>
    <header class="slep-listing__header"><div class="slep-listing__heading"><span class="slep-listing__icon"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i></span><div><p class="slep-listing__eyebrow">Trámites y operación · Maternidad</p><h1>Ingreso masivo</h1><p class="slep-listing__muted mb-0">Registre casos desde Excel y complete sus documentos después.</p></div></div><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('tramites.maternidad.index') }}">Volver al listado</a></header>
    @include('tramites.maternidad.partials._mensajes')
    <section class="slep-listing__panel"><div class="slep-listing__panel-header"><h2>1. Preparar el Excel</h2><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('tramites.maternidad.plantilla') }}">Descargar plantilla</a></div><div class="slep-listing__filters">
        <ol class="mb-0"><li>Complete la hoja <strong>Casos</strong>, una fila por embarazo o proceso. RUT y referencia del caso son obligatorios. Puede repetir un RUT con referencias diferentes.</li><li>Los datos laborales y nombres se buscan por RUT. Revise la separación de apellidos; puede completarlos o corregirlos en el Excel. Si hay varios establecimientos, informe el RBD. Para elegir otro vínculo específico use «fuente» (por ejemplo, padron:123), disponible al buscar el RUT en el ingreso individual.</li><li>Complete la hoja <strong>Hijos</strong> si corresponde. Cada fila se vincula mediante RUT de funcionaria y referencia del caso.</li><li>Use fechas AAAA-MM-DD, DD-MM-AAAA o fechas de Excel, y Sí/No para beneficios. Se admiten hasta {{ config('maternidad.max_casos_importacion') }} casos por archivo; sin fórmulas.</li><li>Los beneficios informados quedan pendientes de respaldo. Los certificados se adjuntan posteriormente en cada expediente.</li></ol>
    </div></section>
    <section class="slep-listing__panel"><div class="slep-listing__panel-header"><h2>2. Validar y revisar</h2></div><form class="slep-listing__filters" action="{{ route('tramites.maternidad.previsualizar') }}" method="POST" enctype="multipart/form-data">@csrf
        <label class="form-label" for="mat-excel">Libro Excel (.xlsx), máximo 10 MB</label><input class="form-control @error('archivo') is-invalid @enderror" id="mat-excel" type="file" name="archivo" accept=".xlsx" required><div class="invalid-feedback">@error('archivo'){{ $message }}@enderror</div>
        <p class="form-text mt-3">Puede informar las fechas de REX de Ley Dominga y lactancia y las horas semanales de lactancia en las columnas finales de la plantilla. Los archivos REX se completan después. Se aceptan las plantillas anteriores sin estas tres columnas.</p>
        <p class="form-text mt-3">Primero verá una previsualización. La confirmación crea todos los casos en una sola operación; si hay errores, no se registra ninguno. Las referencias existentes no se sobrescriben.</p><button type="submit" class="slep-listing__button slep-listing__button--primary">Validar archivo</button>
    </form></section>
</div>
@endsection
