@extends('layouts.app')

@push('styles')
    <style>
        .ip-create-hero,.ip-create-panel { border:1px solid #d9e4f3; border-radius:22px; background:#fff; box-shadow:0 14px 34px rgba(15,23,42,.06); }
        .ip-create-hero { padding:1.45rem 1.65rem; background:linear-gradient(135deg,#fff,#eff6ff); }
        .ip-step { display:inline-flex; width:2rem; height:2rem; align-items:center; justify-content:center; border-radius:50%; background:#1d4ed8; color:#fff; font-weight:800; margin-right:.55rem; }
        .ip-create-title { color:#0f172a; font-size:1.7rem; font-weight:800; margin:.55rem 0 .35rem; }.ip-create-help{color:#475569;margin:0;max-width:58rem}
        .ip-create-panel__head{padding:1.1rem 1.4rem;border-bottom:1px solid #e8eef5}.ip-create-panel__body{padding:1.35rem 1.4rem}.ip-create-label{font-weight:800;color:#334155;margin-bottom:.35rem}
        .ip-create-primary,.ip-create-secondary{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:13px;min-height:45px;padding:.68rem 1rem;font-weight:800;text-decoration:none}.ip-create-primary{border:1px solid #1d4ed8;background:#1d4ed8;color:#fff;box-shadow:0 10px 20px rgba(37,99,235,.2)}.ip-create-primary:hover{color:#fff;background:#1e40af}.ip-create-secondary{border:1px solid #bfdbfe;color:#1d4ed8;background:#fff}.ip-create-secondary:hover{color:#1e40af;background:#eff6ff}
        .ip-notice{border:1px solid #bae6fd;background:#f0f9ff;color:#0c4a6e;border-radius:14px;padding:.9rem 1rem}.ip-candidate-toolbar{padding:.85rem 1rem;background:#f8fafc;border:1px solid #dbe6f2;border-bottom:0;border-radius:15px 15px 0 0}.ip-candidate-table{border:1px solid #dbe6f2;border-radius:0 0 15px 15px;overflow:hidden}.ip-candidate-table th{background:#f8fafc;color:#334155;font-size:.8rem;text-transform:uppercase}.ip-candidate-table th,.ip-candidate-table td{padding:.8rem;vertical-align:middle;border-color:#e8eef5}.ip-person-name{font-weight:800;color:#0f172a}.ip-person-meta{font-size:.85rem;color:#64748b;margin-top:.15rem}.ip-sticky-submit{position:sticky;bottom:1rem;z-index:2;padding:1rem;background:rgba(255,255,255,.95);border:1px solid #bfdbfe;border-radius:16px;box-shadow:0 12px 28px rgba(15,23,42,.1)}
    </style>
@endpush

@section('content')
    <div class="container py-4">
        <section class="ip-create-hero mb-4">
            <div class="small text-uppercase fw-bold text-primary"><span class="ip-step">1</span> Definir período y buscar</div>
            <h1 class="ip-create-title">Nueva solicitud de idoneidad psicológica</h1>
            <p class="ip-create-help">La búsqueda considera el último padrón vigente y las solicitudes de reemplazo aceptadas o cerradas. Incluirá AAEE cuyo ingreso o período laboral se cruce con las fechas indicadas.</p>
        </section>

        <section class="ip-create-panel mb-4">
            <div class="ip-create-panel__head"><strong>Período laboral del personal</strong><div class="text-muted small">Primero define el rango; luego podrás revisar y marcar la nómina.</div></div>
            <div class="ip-create-panel__body"><form method="GET" action="{{ route('tramites.idoneidad-psicologica.create') }}"><input type="hidden" name="buscar" value="1"><div class="row g-3 align-items-end"><div class="col-md-4"><label class="ip-create-label" for="fecha_inicio">Fecha de inicio</label><input class="form-control @error('fecha_inicio') is-invalid @enderror" type="date" id="fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio', $fechas['fecha_inicio']) }}" required>@error('fecha_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-4"><label class="ip-create-label" for="fecha_termino">Fecha de término</label><input class="form-control @error('fecha_termino') is-invalid @enderror" type="date" id="fecha_termino" name="fecha_termino" value="{{ old('fecha_termino', $fechas['fecha_termino']) }}" required>@error('fecha_termino')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-4"><button class="ip-create-primary w-100" type="submit"><i class="bi bi-search"></i> Buscar personal elegible</button></div></div></form></div>
        </section>

        @if($buscando)
            <form method="POST" action="{{ route('tramites.idoneidad-psicologica.store') }}">
                @csrf
                <input type="hidden" name="fecha_inicio" value="{{ $fechas['fecha_inicio'] }}"><input type="hidden" name="fecha_termino" value="{{ $fechas['fecha_termino'] }}">
                <section class="ip-create-panel">
                    <div class="ip-create-panel__head d-flex flex-wrap justify-content-between gap-2 align-items-center"><div><span class="ip-step">2</span><strong>Revisar nómina elegible</strong><div class="text-muted small mt-1">{{ number_format($funcionarios->count(), 0, ',', '.') }} funcionario(s) encontrados. Todos se seleccionan inicialmente; puedes desmarcar los que no correspondan.</div></div><span class="badge text-bg-primary px-3 py-2">Fuentes vigentes</span></div>
                    <div class="ip-create-panel__body">
                        @if($funcionarios->isEmpty())
                            <div class="ip-notice"><i class="bi bi-info-circle me-1"></i>No se encontraron AAEE elegibles en el rango. Revisa las fechas o la información contractual del padrón vigente.</div>
                        @else
                            <div class="ip-candidate-toolbar d-flex flex-wrap gap-2 align-items-center"><button type="button" class="ip-create-secondary py-1" id="seleccionar-todos"><i class="bi bi-check2-square"></i> Seleccionar todos</button><button type="button" class="ip-create-secondary py-1" id="limpiar-seleccion"><i class="bi bi-square"></i> Limpiar</button><div class="ms-md-auto small text-muted"><strong id="contador-seleccion">{{ $funcionarios->count() }}</strong> seleccionados</div></div>
                            <div class="table-responsive ip-candidate-table"><table class="table mb-0"><thead><tr><th class="text-center" style="width:68px">Incluir</th><th>Funcionario</th><th>Establecimiento</th><th>Contrato</th><th>Ingreso</th><th>Término</th></tr></thead><tbody>
                                @foreach($funcionarios as $funcionario)
                                    <tr data-candidato><td class="text-center"><input class="form-check-input candidato-check" type="checkbox" name="funcionarios[]" value="{{ $funcionario->idoneidad_key }}" checked aria-label="Incluir {{ $funcionario->nombre }}"></td><td><div class="ip-person-name">{{ $funcionario->nombre }}</div><div class="ip-person-meta">{{ $funcionario->rut }} · {{ $funcionario->estatuto ?: 'Sin estamento' }} · {{ $funcionario->escalafon ?: 'Sin cargo informado' }}</div></td><td><div class="fw-semibold">{{ $funcionario->establecimiento?->nombre_establecimiento ?: 'Sin establecimiento' }}</div><div class="ip-person-meta">RBD {{ $funcionario->establecimiento?->rbd ?: '—' }}</div></td><td>{{ $funcionario->tipocontrato ?: 'Sin contrato informado' }}</td><td>{{ optional($funcionario->fecha_ingreso)->format('d-m-Y') }}</td><td>{{ optional($funcionario->fecha_termino)->format('d-m-Y') ?: 'Sin fecha' }}</td></tr>
                                @endforeach
                            </tbody></table></div>
                            @error('funcionarios')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            <div class="mt-4"><label class="ip-create-label" for="observacion">Observación de la solicitud <span class="text-muted fw-normal">(opcional)</span></label><textarea class="form-control" rows="3" id="observacion" name="observacion" maxlength="2000" placeholder="Antecedentes generales del proceso; no incorpores información clínica.">{{ old('observacion') }}</textarea></div>
                            <div class="ip-sticky-submit mt-4 d-flex flex-wrap align-items-center gap-3"><div class="me-auto"><strong><span class="ip-step">3</span> Generar solicitud</strong><div class="small text-muted mt-1">Las personas seleccionadas quedarán con estado <strong>Solicitado</strong>.</div></div><a href="{{ route('tramites.idoneidad-psicologica.index') }}" class="ip-create-secondary">Cancelar</a><button class="ip-create-primary" type="submit"><i class="bi bi-send-check"></i> Crear solicitud y nómina</button></div>
                        @endif
                    </div>
                </section>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checks = Array.from(document.querySelectorAll('.candidato-check'));
            const count = document.getElementById('contador-seleccion');
            const refresh = () => { if (count) count.textContent = checks.filter((check) => check.checked).length; };
            document.getElementById('seleccionar-todos')?.addEventListener('click', () => { checks.forEach((check) => check.checked = true); refresh(); });
            document.getElementById('limpiar-seleccion')?.addEventListener('click', () => { checks.forEach((check) => check.checked = false); refresh(); });
            checks.forEach((check) => check.addEventListener('change', refresh));
        });
    </script>
@endpush
