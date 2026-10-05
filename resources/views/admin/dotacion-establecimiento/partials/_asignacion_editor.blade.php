<details id="{{ $editorId }}" class="dotacion-editor" data-dotacion-editor @if ($abierto) open @endif>
    <summary class="dotacion-editor-toggle">
        <span><i class="bi bi-plus-circle" aria-hidden="true"></i> <span class="dotacion-editor-label-closed">{{ $accion }}</span><span class="dotacion-editor-label-open">Cerrar formulario</span><span class="visually-hidden"> para {{ $contexto }}</span></span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </summary>
    <div class="dotacion-editor-content">
        <div class="small fw-semibold text-muted mb-2">{{ $contexto }}</div>
        {{ $slot }}
    </div>
</details>
