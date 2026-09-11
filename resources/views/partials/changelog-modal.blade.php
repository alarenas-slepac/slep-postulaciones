@if ($hasVisibleChangeLogEntries ?? false)
    <div class="modal fade" id="changeLogModal" tabindex="-1" aria-labelledby="changeLogModalLabel" aria-hidden="true"
         data-auto-show="{{ ($shouldShowChangeLogModal ?? false) ? '1' : '0' }}"
         data-current-url="{{ route('changelog.entries', ['scope' => 'current']) }}"
         data-history-url="{{ route('changelog.entries', ['scope' => 'history']) }}"
         data-ack-url="{{ route('changelog.ack') }}" data-csrf="{{ csrf_token() }}">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title" id="changeLogModalLabel">Registro de cambios</h5>
                        <div class="small text-muted">Versión actual: {{ $currentAppVersion }}</div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Secciones del registro de cambios">
                        <a class="btn btn-outline-primary btn-sm" data-changelog-page href="{{ route('changelog.entries', ['scope' => 'current']) }}">Novedades actuales</a>
                        @if ($hasPreviousChangeLogEntries ?? false)
                            <a class="btn btn-outline-secondary btn-sm" data-changelog-page href="{{ route('changelog.entries', ['scope' => 'history']) }}">Historial de cambios anteriores</a>
                        @endif
                    </nav>
                    <div id="changeLogContent" aria-live="polite"><p>Los cambios se cargan al abrir esta ventana, hasta diez por página.</p></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('changeLogModal');
                const content = document.getElementById('changeLogContent');
                const Modal = window.bootstrap?.Modal;
                if (!modalEl || !content || !Modal) return;
                let pending;
                let loaded = false;
                let acknowledged = false;

                async function load(href) {
                    const url = new URL(href, window.location.href);
                    if (url.origin !== window.location.origin) return;
                    if (pending) pending.abort();
                    const controller = new AbortController();
                    pending = controller;
                    loaded = false;
                    content.setAttribute('aria-busy', 'true');
                    content.replaceChildren(Object.assign(document.createElement('p'), {textContent: 'Cargando cambios…'}));
                    const timeout = setTimeout(() => controller.abort(), 20000);
                    try {
                        const response = await fetch(url.href, {credentials: 'same-origin', signal: controller.signal,
                            headers: {'X-Requested-With': 'XMLHttpRequest'}});
                        if (!response.ok || response.redirected || response.headers.get('X-ChangeLog-Entries') !== '1') throw new Error('response');
                        const html = await response.text();
                        if (pending !== controller) return;
                        content.innerHTML = html;
                        loaded = true;
                    } catch (error) {
                        if (pending !== controller) return;
                        const message = Object.assign(document.createElement('p'), {textContent: 'No fue posible cargar los cambios. Intente nuevamente.'});
                        const retry = Object.assign(document.createElement('a'), {href: url.href, textContent: 'Reintentar', className: 'btn btn-outline-primary btn-sm'});
                        retry.dataset.changelogPage = '';
                        content.replaceChildren(message, retry);
                    } finally {
                        clearTimeout(timeout);
                        if (pending === controller) content.removeAttribute('aria-busy');
                    }
                }

                modalEl.addEventListener('show.bs.modal', function (event) {
                    load(event.relatedTarget?.dataset?.changelogOpenHistory === '1' ? modalEl.dataset.historyUrl : modalEl.dataset.currentUrl);
                });
                modalEl.addEventListener('click', function (event) {
                    const link = event.target.closest('a[data-changelog-page]');
                    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                    event.preventDefault();
                    load(link.href);
                });
                modalEl.addEventListener('hidden.bs.modal', async function () {
                    if (pending) pending.abort();
                    pending = null;
                    content.removeAttribute('aria-busy');
                    if (!loaded || acknowledged) return;
                    acknowledged = true;
                    try {
                        const response = await fetch(modalEl.dataset.ackUrl, {method: 'POST', credentials: 'same-origin',
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': modalEl.dataset.csrf},
                            body: JSON.stringify({acknowledged: true})});
                        if (!response.ok || response.redirected) acknowledged = false;
                    } catch (error) { acknowledged = false; }
                });
                if (modalEl.dataset.autoShow === '1') Modal.getOrCreateInstance(modalEl).show();
            });
        </script>
    @endpush
@endif
