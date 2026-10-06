@if (auth()->user()?->hasRole('admin') && auth()->user()->activeRoleName() === 'admin')
    <a class="btn btn-primary rounded-pill px-4 fw-semibold" href="{{ route('gestion.solicitudes-reemplazo.documentos-academicos.index') }}">
        <i class="bi bi-file-earmark-zip me-1" aria-hidden="true"></i> Documentos académicos de reemplazantes
    </a>
@endif
