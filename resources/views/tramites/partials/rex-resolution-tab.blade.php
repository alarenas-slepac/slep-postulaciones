@php
    $displayTimezone = $displayTimezone ?? config('app.display_timezone', 'America/Santiago');
    $canManageRex = $canManageRex ?? false;
    $summary = $tramite->calculo_periodos_resumen;
    $rexData = (array) ($tramite->rex_data ?? []);
    $documents = collect(data_get($rexData, 'documentos', []));
    $formatDurationText = fn(array $duration) => \App\Models\Tramite::formatDurationText($duration);
@endphp

<div class="d-flex flex-column gap-3">
    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Resolución de Reconocimiento de Bienios</span>
            <span class="badge text-bg-secondary">{{ $tramite->estado_label }}</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Total reconocido</div>
                        <div class="fw-semibold">{{ $formatDurationText((array) ($summary['duracion'] ?? [])) }}</div>
                        <div class="small text-muted">Bienios: {{ (int) ($summary['bienios'] ?? 0) }}@if(!empty($summary['tiene_tope_bienios'])) (tope máximo 15)@endif</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Tiempo para el siguiente bienio</div>
                        <div class="fw-semibold">{{ $formatDurationText((array) ($summary['duracion_para_siguiente_bienio'] ?? [])) }}</div>
                        <div class="small text-muted">Faltan {{ number_format((int) ($summary['dias_para_siguiente_bienio'] ?? 0), 0, ',', '.') }} día(s).</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold">Antecedentes a consignar en la resolución</div>
        <div class="card-body">
            @if($documents->isEmpty())
                <div class="text-muted">Aún no se ha generado la resolución. Al generarla se consolidará el listado de documentos considerados.</div>
            @else
                <ol class="mb-0">
                    @foreach($documents as $document)
                        <li>{{ data_get($document, 'tipo_documento', 'Documento') }}@if(data_get($document, 'documento_nombre')) — {{ data_get($document, 'documento_nombre') }}@endif @if(data_get($document, 'fecha_documento')) (fecha {{ data_get($document, 'fecha_documento') }}) @endif</li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold">Acciones de resolución</div>
        <div class="card-body d-flex flex-column gap-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('tramites.rex.word.download', $tramite) }}" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-word"></i> Descargar Resolución Word
                </a>
                @if($tramite->resolucion_pdf_path)
                    <a href="{{ route('tramites.rex.pdf.download', $tramite) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-file-earmark-pdf"></i> Descargar Resolución PDF
                    </a>
                @endif
                @if($tramite->resultado_enviado_at)
                    <span class="badge text-bg-success">Resultado enviado {{ $tramite->resultado_enviado_at->timezone($displayTimezone)->format('d-m-Y H:i') }}</span>
                @endif
            </div>

            @if($canManageRex)
                <form method="POST" action="{{ route('tramites.rex.pdf.upload', $tramite) }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label for="resolucion_pdf" class="form-label">Cargar resolución en PDF</label>
                        <input type="file" name="resolucion_pdf" id="resolucion_pdf" class="form-control @error('resolucion_pdf') is-invalid @enderror" accept="application/pdf,.pdf" required>
                        @error('resolucion_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Guardar Resolución PDF</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('tramites.rex.resultado.send', $tramite) }}">
                    @csrf
                    <button type="submit" class="btn btn-success" {{ $tramite->resolucion_pdf_path ? '' : 'disabled' }}>
                        <i class="bi bi-send"></i> Enviar Resultado
                    </button>
                    @if(!$tramite->resolucion_pdf_path)
                        <div class="small text-muted mt-2">El botón se habilita cuando se haya cargado la resolución en PDF.</div>
                    @endif
                </form>
            @endif
        </div>
    </div>
</div>
