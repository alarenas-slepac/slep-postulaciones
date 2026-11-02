@once
    @push('styles')
        @vite('resources/js/reemplazo-modificacion-termino.js')
    @endpush
@endonce
@php
    $fechaMaximaModificacion = $s->fecha_termino?->copy()->subDay()->toDateString();
    $esReposoMutualidad = \App\Support\TipoReemplazo::esReposoMutualidad($s->tipo_reemplazo);
    $causalSeleccionada = old('causal_modificacion_termino', $esReposoMutualidad ? 'reduccion_reposo_mutualidad' : 'renuncia_voluntaria');
    $camposReapertura = ['causal_modificacion_termino', 'fecha_termino_nueva', 'motivo_modificacion_termino', 'carta_renuncia_pdf', 'resolucion_renuncia_pdf', 'respaldo_licencia_pdf'];
    $erroresReapertura = collect($errors->getMessages())->only($camposReapertura)->flatten();
@endphp
<div class="slep-listing" data-slep-listing data-reapertura-termino data-causal-licencia="{{ \App\Models\SolicitudReemplazoModificacionTermino::REDUCCION_LICENCIA_REPOSO }}">
    <section class="slep-listing__panel" aria-labelledby="reapertura-termino-titulo">
        <div class="slep-listing__panel-header"><div><p class="slep-listing__eyebrow">Gestión de reemplazos</p><h2 id="reapertura-termino-titulo">Reapertura para modificar término</h2></div></div>
        <div class="slep-listing__filters">
            <div class="slep-record-state slep-record-state--warning mb-3">Esta acción conserva la fecha y los documentos vigentes en el historial. La nueva fecha de término debe ser anterior a la actual.</div>
            @if ($erroresReapertura->isNotEmpty())
                <div class="slep-listing__notice slep-listing__notice--danger mb-3" role="alert" tabindex="-1" data-reapertura-errores><strong>Revise los datos de la reapertura</strong><ul class="mb-0 mt-2">@foreach ($erroresReapertura as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" enctype="multipart/form-data" action="{{ route('gestion.solicitudes-reemplazo.reabrir-modificar-termino', $s) }}" data-reapertura-form>
                @csrf
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label for="reapertura-causal" class="form-label">Causal <span class="text-danger" aria-hidden="true">*</span></label>
                        <select id="reapertura-causal" name="causal_modificacion_termino" class="form-select @error('causal_modificacion_termino') is-invalid @enderror" required data-slep-select="search" aria-describedby="reapertura-causal-error">
                            @foreach (\App\Models\SolicitudReemplazoModificacionTermino::CAUSALES as $valor => $etiqueta)
                                @if ($valor !== 'reduccion_reposo_mutualidad' || $esReposoMutualidad)<option value="{{ $valor }}" @selected($causalSeleccionada === $valor)>{{ $etiqueta }}</option>@endif
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="reapertura-causal-error">@error('causal_modificacion_termino'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-lg-6">
                        <label for="reapertura-fecha" class="form-label">Nueva fecha de término <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="reapertura-fecha" type="date" name="fecha_termino_nueva" class="form-control @error('fecha_termino_nueva') is-invalid @enderror" required value="{{ old('fecha_termino_nueva') }}" min="{{ $s->fecha_inicio?->toDateString() }}" max="{{ $fechaMaximaModificacion }}" aria-describedby="reapertura-fecha-error">
                        <div class="invalid-feedback" id="reapertura-fecha-error">@error('fecha_termino_nueva'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-12">
                        <label for="reapertura-motivo" class="form-label">Motivo de la modificación <span class="text-danger" aria-hidden="true">*</span></label>
                        <textarea id="reapertura-motivo" name="motivo_modificacion_termino" class="form-control @error('motivo_modificacion_termino') is-invalid @enderror" maxlength="5000" rows="3" required aria-describedby="reapertura-motivo-error">{{ old('motivo_modificacion_termino') }}</textarea>
                        <div class="invalid-feedback" id="reapertura-motivo-error">@error('motivo_modificacion_termino'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-6" data-reapertura-renuncia>
                        <label for="reapertura-carta" class="form-label">Carta de renuncia (PDF) <span class="text-danger" aria-hidden="true" data-reapertura-obligatorio>*</span></label>
                        <input id="reapertura-carta" type="file" name="carta_renuncia_pdf" class="form-control @error('carta_renuncia_pdf') is-invalid @enderror" accept="application/pdf,.pdf" aria-describedby="reapertura-carta-ayuda reapertura-carta-error">
                        <div class="form-text" id="reapertura-carta-ayuda">Obligatoria para renuncia voluntaria. No se solicita para la nueva causal de reducción de licencia o reposo. Máximo 10 MB.</div>
                        <div class="invalid-feedback" id="reapertura-carta-error">@error('carta_renuncia_pdf'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-md-6" data-reapertura-renuncia>
                        <label for="reapertura-resolucion" class="form-label">Resolución de renuncia (PDF) <span class="text-danger" aria-hidden="true" data-reapertura-obligatorio>*</span></label>
                        <input id="reapertura-resolucion" type="file" name="resolucion_renuncia_pdf" class="form-control @error('resolucion_renuncia_pdf') is-invalid @enderror" accept="application/pdf,.pdf" aria-describedby="reapertura-resolucion-ayuda reapertura-resolucion-error">
                        <div class="form-text" id="reapertura-resolucion-ayuda">Obligatoria para renuncia voluntaria. No se solicita para la nueva causal de reducción de licencia o reposo. Máximo 10 MB.</div>
                        <div class="invalid-feedback" id="reapertura-resolucion-error">@error('resolucion_renuncia_pdf'){{ $message }}@enderror</div>
                    </div>
                    <div class="col-12" data-reapertura-licencia>
                        <label for="reapertura-respaldo" class="form-label">Respaldo Licencia (PDF) <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="reapertura-respaldo" type="file" name="respaldo_licencia_pdf" class="form-control @error('respaldo_licencia_pdf') is-invalid @enderror" accept="application/pdf,.pdf" aria-describedby="reapertura-respaldo-ayuda reapertura-respaldo-error">
                        <div class="form-text" id="reapertura-respaldo-ayuda">Obligatorio para «Término anticipado por reducción de licencia médica o reposo mutualidad». Máximo 10 MB.</div>
                        <div class="invalid-feedback" id="reapertura-respaldo-error">@error('respaldo_licencia_pdf'){{ $message }}@enderror</div>
                    </div>
                </div>
                <noscript><p class="form-text mt-3">Adjunte los documentos correspondientes a la causal seleccionada: Respaldo Licencia para la nueva causal de reducción, o carta y resolución para renuncia voluntaria.</p></noscript>
                <div class="slep-listing__actions mt-3"><button class="slep-listing__button slep-listing__button--primary" type="submit" data-reapertura-submit>Reabrir y registrar modificación</button></div>
            </form>
        </div>
    </section>
</div>
