<nav class="slep-nav-scroll slep-sidebar-sections" aria-label="Secciones del menú">
    @if ($navigation['direct'])
        <ul class="list-unstyled slep-sidebar-shortcuts">
            @foreach ($navigation['direct'] as $link)
                <li>
                    @include('partials.sidebar-section-link', ['link' => $link])
                </li>
            @endforeach
        </ul>
    @endif
    @foreach ($navigation['sections'] as $section)
        <details class="slep-sidebar-section @if ($section['active']) is-current @endif"
            data-sidebar-section="{{ $section['key'] }}" @if ($section['active']) open @endif>
            <summary class="slep-sidebar-section-heading">
                <i class="bi {{ $section['icon'] }} slep-sidebar-section-icon" aria-hidden="true"></i>
                <span>{{ $section['label'] }}</span>
                <i class="bi bi-chevron-down slep-sidebar-section-chevron" aria-hidden="true"></i>
            </summary>
            <ul class="list-unstyled slep-sidebar-section-links">
                @foreach ($section['links'] as $link)
                    <li>
                        @include('partials.sidebar-section-link', ['link' => $link])
                    </li>
                @endforeach
            </ul>
        </details>
    @endforeach
</nav>
