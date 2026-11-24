@props(['title', 'icon' => 'bi-person-vcard', 'description' => null])
@once
    @vite('resources/js/slep-listados.js')
@endonce
<div class="slep-listing" data-slep-listing>
    <header class="slep-listing__header">
        <div class="slep-listing__heading">
            <span class="slep-listing__icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
            <div>
                <h1>{{ $title }}</h1>
                @if ($description)<p class="slep-listing__muted mb-0">{{ $description }}</p>@endif
            </div>
        </div>
        @isset($actions)<div class="slep-listing__actions">{{ $actions }}</div>@endisset
    </header>
    @include('postulant.partials.feedback')
    {{ $slot }}
</div>
