@php
    $allChangeLogEntries = $allChangeLogEntries ?? [];
    $currentChangeLogEntries = $currentChangeLogEntries ?? [];
    $previousChangeLogEntries = $previousChangeLogEntries ?? [];
    $shouldShowChangeLogModal = $shouldShowChangeLogModal ?? false;
    $currentAppVersion = $currentAppVersion ?? \App\Support\ChangeLog::currentVersion();
@endphp

@if (!empty($allChangeLogEntries))
    <div class="modal fade" id="changeLogModal" tabindex="-1" aria-labelledby="changeLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="changeLogModalLabel">Registro de cambios</h5>
                        @if ($currentAppVersion)
                            <div class="small text-muted">Versión actual: {{ $currentAppVersion }}</div>
                        @endif
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if (!empty($currentChangeLogEntries))
                        <div class="alert alert-primary d-flex align-items-start gap-2" role="alert">
                            <i class="bi bi-megaphone"></i>
                            <div>
                                <div class="fw-semibold">Novedades visibles para tus roles en la versión {{ $currentAppVersion }}</div>
                                <div class="small mb-0">Estas actualizaciones se muestran solo cuando el cambio impacta alguno de los roles asignados a tu usuario.</div>
                            </div>
                        </div>
                    @endif

                    <div class="d-flex flex-column gap-3">
                        @foreach ($currentChangeLogEntries as $entry)
                            @php
                                $entryRoles = collect(data_get($entry, 'roles', []))
                                    ->map(fn($role) => str_replace('_', ' ', (string) $role))
                                    ->map(fn($role) => ucwords($role))
                                    ->values();
                            @endphp
                            <div class="card shadow-sm border-start border-4 border-primary">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <h6 class="mb-0">{{ data_get($entry, 'title', 'Actualización') }}</h6>
                                                <span class="badge text-bg-secondary">v{{ data_get($entry, 'version') }}</span>
                                                <span class="badge text-bg-primary">Actual</span>
                                            </div>
                                            @if (data_get($entry, 'summary'))
                                                <p class="mb-0 text-muted small mt-1">{{ data_get($entry, 'summary') }}</p>
                                            @endif
                                        </div>
                                        <div class="text-end small text-muted">
                                            @if (data_get($entry, 'published_at'))
                                                <div>{{ \Carbon\Carbon::parse(data_get($entry, 'published_at'))->format('d-m-Y H:i') }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    @if (!empty(data_get($entry, 'items', [])))
                                        <ul class="mb-2 ps-3">
                                            @foreach ((array) data_get($entry, 'items', []) as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if ($entryRoles->isNotEmpty())
                                        <div class="small text-muted">
                                            <span class="fw-semibold">Roles impactados:</span>
                                            {{ $entryRoles->implode(', ') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if (!empty($previousChangeLogEntries))
                        <div class="mt-4">
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#changeLogHistory" aria-expanded="false" aria-controls="changeLogHistory" id="btnToggleChangeLogHistory">
                                <i class="bi bi-clock-history"></i> Ver historial de cambios anteriores
                            </button>

                            <div class="collapse mt-3" id="changeLogHistory">
                                <div class="card card-body bg-light-subtle border-0">
                                    <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
                                        <div>
                                            <h6 class="mb-1">Historial de cambios anteriores</h6>
                                            <div class="small text-muted">Solo ves versiones históricas cuyos cambios aplican a alguno de tus roles.</div>
                                        </div>
                                        <button class="btn btn-sm btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#changeLogHistory" aria-expanded="true" aria-controls="changeLogHistory">
                                            Ocultar historial
                                        </button>
                                    </div>

                                    <div class="accordion" id="changeLogHistoryAccordion">
                                        @foreach ($previousChangeLogEntries as $entry)
                                            @php
                                                $historyId = 'historyVersion' . $loop->index;
                                                $entryRoles = collect(data_get($entry, 'roles', []))
                                                    ->map(fn($role) => str_replace('_', ' ', (string) $role))
                                                    ->map(fn($role) => ucwords($role))
                                                    ->values();
                                            @endphp
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="heading-{{ $historyId }}">
                                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $historyId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse-{{ $historyId }}">
                                                        <span class="fw-semibold me-2">v{{ data_get($entry, 'version') }}</span>
                                                        <span>{{ data_get($entry, 'title', 'Actualización') }}</span>
                                                    </button>
                                                </h2>
                                                <div id="collapse-{{ $historyId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading-{{ $historyId }}" data-bs-parent="#changeLogHistoryAccordion">
                                                    <div class="accordion-body">
                                                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                                                            @if (data_get($entry, 'summary'))
                                                                <p class="mb-0 text-muted small">{{ data_get($entry, 'summary') }}</p>
                                                            @endif
                                                            @if (data_get($entry, 'published_at'))
                                                                <div class="small text-muted">{{ \Carbon\Carbon::parse(data_get($entry, 'published_at'))->format('d-m-Y H:i') }}</div>
                                                            @endif
                                                        </div>

                                                        @if (!empty(data_get($entry, 'items', [])))
                                                            <ul class="mb-2 ps-3">
                                                                @foreach ((array) data_get($entry, 'items', []) as $item)
                                                                    <li>{{ $item }}</li>
                                                                @endforeach
                                                            </ul>
                                                        @endif

                                                        @if ($entryRoles->isNotEmpty())
                                                            <div class="small text-muted">
                                                                <span class="fw-semibold">Roles impactados:</span>
                                                                {{ $entryRoles->implode(', ') }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer justify-content-between">
                    <div class="small text-muted">v{{ $currentAppVersion }}</div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalEl = document.getElementById('changeLogModal');
                const historyEl = document.getElementById('changeLogHistory');
                const bootstrapModal = window.bootstrap?.Modal;
                const bootstrapCollapse = window.bootstrap?.Collapse;
                if (!modalEl || !bootstrapModal) return;

                const modal = bootstrapModal.getOrCreateInstance(modalEl);
                const shouldAutoShow = @json((bool) $shouldShowChangeLogModal);
                let acknowledged = false;

                function acknowledge() {
                    if (acknowledged) return;
                    acknowledged = true;

                    fetch(@json(route('changelog.ack')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ acknowledged: true })
                    }).catch(() => {});
                }

                function setHistoryVisibility(show) {
                    if (!historyEl || !bootstrapCollapse) return;
                    const history = bootstrapCollapse.getOrCreateInstance(historyEl, { toggle: false });
                    if (show) history.show();
                    else history.hide();
                }

                modalEl.addEventListener('show.bs.modal', function(event) {
                    const trigger = event.relatedTarget;
                    const openHistory = trigger?.dataset?.changelogOpenHistory === '1';
                    setHistoryVisibility(openHistory);
                });

                modalEl.addEventListener('hidden.bs.modal', acknowledge);

                if (shouldAutoShow) {
                    setHistoryVisibility(false);
                    modal.show();
                }
            });
        </script>
    @endpush
@endif
