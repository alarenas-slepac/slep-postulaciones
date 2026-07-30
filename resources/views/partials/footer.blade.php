<footer class="app-footer border-top mt-auto py-3">
    <div class="container d-flex flex-column flex-md-row align-items-center gap-2 justify-content-between">
        <div class="app-footer__brand d-flex align-items-center gap-2">
            <i class="bi bi-mortarboard"></i>
            <span class="small">
                © {{ now()->year }} SLEP Postulaciones
                @php $v = $currentAppVersion ?? \App\Support\ChangeLog::currentVersion(); @endphp
                @if ($v)
                    — v{{ $v }}
                @endif
                @env('local')
                    <span class="text-muted">({{ app()->environment() }})</span>
                @endenv
            </span>
            @auth
                @if (!empty($hasVisibleChangeLogEntries ?? false))
                    <button type="button" class="btn btn-link btn-sm px-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#changeLogModal">
                        <i class="bi bi-journal-text"></i> Ver cambios
                    </button>
                    @if (!empty($hasPreviousChangeLogEntries ?? false))
                        <button type="button" class="btn btn-link btn-sm px-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#changeLogModal" data-changelog-open-history="1">
                            <i class="bi bi-clock-history"></i> Historial
                        </button>
                    @endif
                @endif
            @endauth
        </div>

        <nav class="app-footer__links">
            <ul class="nav small">
                {{-- Ajusta estas rutas si ya tienes páginas creadas --}}
                <li class="nav-item">
                    <a class="nav-link px-2 text-muted" href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>

            </ul>
        </nav>

        <button type="button" class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex" id="btnBackToTop"
            aria-label="Volver arriba">
            <i class="bi bi-arrow-up"></i>
        </button>
    </div>
</footer>

@push('scripts')
    <script>
        (function() {
            const btn = document.getElementById('btnBackToTop');
            if (!btn) return;

            function toggle() {
                if (window.scrollY > 240) btn.classList.remove('d-none');
                else btn.classList.add('d-none');
            }
            window.addEventListener('scroll', toggle, {
                passive: true
            });
            toggle();

            btn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        })();
    </script>
@endpush
