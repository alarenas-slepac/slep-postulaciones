@php
    $assignedIds = old('modules', $assigned ?? []);
@endphp

<div class="card mb-3">
    <div class="card-body">
        <label class="form-label">Nombre del rol</label>
        <input class="form-control @error('name') is-invalid @enderror" name="name"
            value="{{ old('name', $role->name ?? '') }}" placeholder="ej: coordinador_gdp">
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Usa el mismo slug que manejas en Spatie (sin espacios).</div>
    </div>
</div>

@if (isset($modules) && $modules->count())
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <strong>Módulos visibles</strong>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSelectAll">
                    Seleccionar todo
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearAll">
                    Limpiar
                </button>
            </div>
        </div>

        <div class="card-body">
            @foreach ($modules as $section => $items)
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="text-uppercase text-muted small fw-semibold">{{ $section }}</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary btnSectionToggle"
                            data-section="{{ \Illuminate\Support\Str::slug($section) }}">
                            Alternar sección
                        </button>
                    </div>

                    <div class="row g-2" data-section-wrap="{{ \Illuminate\Support\Str::slug($section) }}">
                        @foreach ($items as $m)
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input module-check" type="checkbox" name="modules[]"
                                        value="{{ $m->id }}" id="mod_{{ $m->id }}"
                                        @checked(in_array($m->id, $assignedIds))>
                                    <label class="form-check-label" for="mod_{{ $m->id }}">
                                        {{ $m->name }}
                                        <span class="text-muted small">({{ $m->key }})</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        (function() {
            const all = () => Array.from(document.querySelectorAll('.module-check'));
            const setAll = (checked) => all().forEach(cb => cb.checked = checked);

            document.getElementById('btnSelectAll')?.addEventListener('click', () => setAll(true));
            document.getElementById('btnClearAll')?.addEventListener('click', () => setAll(false));

            document.querySelectorAll('.btnSectionToggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const slug = btn.dataset.section;
                    const wrap = document.querySelector(`[data-section-wrap="${slug}"]`);
                    if (!wrap) return;
                    const checks = Array.from(wrap.querySelectorAll('.module-check'));
                    const anyUnchecked = checks.some(cb => !cb.checked);
                    checks.forEach(cb => cb.checked = anyUnchecked);
                });
            });
        })();
    </script>
@endif
