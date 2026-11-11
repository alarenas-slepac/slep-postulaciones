<dl class="small mt-2 mb-0 text-break">
    <dt>Nivel de enseñanza</dt><dd>{{ $registro?->nivelEnsenanzaNombre() ?? 'No informado' }}</dd>
    <dt>Área de enseñanza</dt><dd class="mb-0">{{ implode(', ', $registro?->areasEnsenanzaNombres() ?? []) ?: 'No informada' }}</dd>
</dl>
