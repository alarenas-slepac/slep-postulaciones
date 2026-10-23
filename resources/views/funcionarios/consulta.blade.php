@extends('layouts.app')
@push('styles')
    @vite('resources/css/funcionario-consulta.css')
@endpush
@section('content')
<div class="fc-page" data-funcionario-consulta>
    <header class="fc-header p-4 mb-4">
        <div class="fc-context mb-2"><i class="bi bi-person-vcard me-2" aria-hidden="true"></i>Consulta institucional</div>
        <h1 class="mb-2">Consulta de funcionarios</h1>
        <p class="text-secondary mb-0">Consulta por RUT la dependencia registrada en establecimientos o Administración Central del SLEP.</p>
    </header>
    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <section class="fc-panel p-4" aria-labelledby="fc-search-title">
                <h2 class="h4 mb-3" id="fc-search-title">Buscar funcionario</h2>
                <form action="{{ route('funcionarios.consulta.consultar') }}" method="post" data-fc-form autocomplete="off">
                    @csrf
                    <label for="fc-rut" class="form-label">RUT con dígito verificador <span aria-hidden="true">*</span></label>
                    <input class="form-control" id="fc-rut" name="rut" type="text" required maxlength="15" spellcheck="false" autocapitalize="characters" aria-describedby="fc-rut-help fc-rut-error">
                    <div id="fc-rut-help" class="form-text">Puedes pegarlo con puntos y guion. Se comprobará el dígito verificador.</div>
                    <div id="fc-rut-error" class="invalid-feedback"></div>
                    <button class="btn btn-primary mt-3 w-100" type="submit"><i class="bi bi-search me-2" aria-hidden="true"></i><span data-fc-button-label>Consultar</span></button>
                </form>
            </section>
            <aside class="fc-data p-4 mt-4">
                <h2 class="h6">Información de la consulta</h2>
                <p class="small text-secondary mb-0">La información depende de los padrones disponibles. Una autorización del SGA no constituye una certificación de vigencia laboral.</p>
            </aside>
        </div>
        <div class="col-12 col-lg-8">
            <div class="alert d-none" data-fc-feedback role="status" aria-live="polite"></div>
            <section class="fc-panel p-4" aria-labelledby="fc-result-title" aria-busy="false" data-fc-result-panel>
                <h2 class="h4 mb-3" id="fc-result-title">Resultado de la consulta</h2>
                <div data-fc-result aria-live="polite">
                    <div class="text-center py-5 text-secondary"><i class="bi bi-person-bounding-box display-5 d-block mb-3" aria-hidden="true"></i>Ingresa un RUT para consultar sus antecedentes.</div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
@push('scripts')
    @vite('resources/js/funcionario-consulta.js')
@endpush
