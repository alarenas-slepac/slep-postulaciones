<span class="cv-establishment-logo">
    @if($logo)
        <img src="{{ $logo }}" alt="Logo de {{ $nombre }}" width="64" height="64" loading="lazy" decoding="async" referrerpolicy="no-referrer">
    @else
        <i class="bi bi-building" aria-hidden="true"></i>
    @endif
</span>
