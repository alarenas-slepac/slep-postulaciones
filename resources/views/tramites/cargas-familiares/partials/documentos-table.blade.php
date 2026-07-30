@if ($documentos->count())
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Documento</th>
                    <th>Archivo</th>
                    <th>Estado</th>
                    <th>Observación</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documentos as $documento)
                    <tr>
                        <td>{{ $documento->tipo_documento_label }}</td>
                        <td><span class="small text-muted">{{ $documento->original_name }}</span></td>
                        <td><span class="badge {{ $documento->estado_revision_badge_class }}">{{ $documento->estado_revision_label }}</span></td>
                        <td class="small text-muted">{{ $documento->revision_observacion ?: '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                <a href="{{ route('tramites.cargas-familiares.documentos.view', [$solicitud, $documento]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Ver</a>
                                <a href="{{ route('tramites.cargas-familiares.documentos.download', [$solicitud, $documento]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Descargar</a>
                                @if (!empty($canReview))
                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#review-doc-{{ $documento->id }}">Revisar</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if (!empty($canReview))
                        <tr class="collapse" id="review-doc-{{ $documento->id }}">
                            <td colspan="5" class="bg-light">
                                <form method="POST" action="{{ route('tramites.cargas-familiares.documentos.review', [$solicitud, $documento]) }}" class="row g-2 align-items-end">
                                    @csrf
                                    @method('PATCH')
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Estado documento</label>
                                        <select name="estado_revision" class="form-select form-select-sm" required>
                                            @foreach (['pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'observado' => 'Observado', 'rechazado' => 'Rechazado'] as $key => $label)
                                                <option value="{{ $key }}" @selected($documento->estado_revision === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label small mb-1">Observación</label>
                                        <input type="text" name="revision_observacion" class="form-control form-control-sm" value="{{ $documento->revision_observacion }}" maxlength="1500">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-primary w-100">Guardar</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="p-3 text-muted small">No hay documentos registrados.</div>
@endif
