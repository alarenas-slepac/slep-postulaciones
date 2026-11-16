@forelse($cupos as $cupo)
    <article class="slep-record-state slep-record-state--{{ $cupo['disponibles'] > 0 ? 'info' : 'neutral' }} mb-3" data-cupo-clave="{{ $cupo['clave'] }}">
        <strong>{{ $bloques[$cupo['bloque']] ?? $cupo['bloque'] }} · {{ $cupo['nombre'] }}</strong>
        <div>{{ $cupo['titulo'] }}</div>
        <div class="small">{{ implode(' / ', $cupo['areas']) ?: 'Sin área informada' }}</div>
        <p class="my-2">Requeridas: <strong>{{ $fmt($cupo['horas']) }} h</strong> · Ya destinadas: <strong>{{ $fmt($cupo['destinadas']) }} h</strong> · Disponibles: <strong>{{ $fmt($cupo['disponibles']) }} h</strong></p>
        <span class="slep-status-pill slep-status-pill--{{ $cupo['disponibles'] > 0 ? 'info' : 'neutral' }}">{{ $cupo['estado'] }}</span>
        @foreach($cupo['destinaciones'] as $vinculo)
            <p class="small mt-2 mb-0">Destinación #{{ $vinculo['id'] }} · {{ $vinculo['nombre'] }} · {{ $vinculo['origen'] }} · {{ $fmt($vinculo['horas']) }} h destinadas.</p>
        @endforeach
    </article>
@empty<p class="mb-0 slep-listing__muted">No hay cupos con asignaciones que coincidan con la selección.</p>@endforelse
