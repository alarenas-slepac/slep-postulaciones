<div class="card p-3 mb-3">
    <form method="GET" action="{{ route('declaracion.index') }}">
        <input type="hidden" name="tab" value="{{ $tab ?? request('tab', 'docentes') }}">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label mb-1">RUT</label>
                <input
                    type="text"
                    name="rut"
                    class="form-control form-control-sm"
                    value="{{ request('rut') }}"
                    placeholder="Buscar por RUT"
                >
            </div>

            <div class="col-md-4">
                <label class="form-label mb-1">Nombre</label>
                <input
                    type="text"
                    name="nombre"
                    class="form-control form-control-sm"
                    value="{{ request('nombre') }}"
                    placeholder="Buscar por nombre"
                >
            </div>

            @if(($isDeclaracionAdmin ?? false))
            <div class="col-md-3">
                <label class="form-label mb-1">Establecimiento</label>
                <select name="establecimiento" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach(($establecimientos ?? []) as $e)
                        <option
                            value="{{ $e->cod_estab }}"
                            {{ (string) request('establecimiento') === (string) $e->cod_estab ? 'selected' : '' }}
                        >
                            {{ $e->cod_estab }} - {{ $e->nombre_establecimiento }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Buscar</button>
                <a href="{{ route('declaracion.index', ['tab' => ($tab ?? request('tab', 'docentes'))]) }}" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
            </div>
        </div>
    </form>
</div>
