@extends('layouts.app')

@push('styles')
<style>
    .fac-page-header {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid #d9e4f3;
        border-radius: 24px;
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
        padding: 1.5rem;
    }
    .fac-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
        color: #fff;
        box-shadow: 0 10px 24px rgba(37, 99, 235, .28);
        font-size: 1.25rem;
    }
    .fac-title { font-size: clamp(1.55rem, 2vw, 2.1rem); font-weight: 800; color: #0f172a; margin: 0; }
    .fac-panel { border: 1px solid #d9e4f3; border-radius: 22px; background: #fff; box-shadow: 0 14px 34px rgba(15,23,42,.06); overflow: hidden; }
    .fac-panel__header { padding: 1rem 1.25rem .8rem; border-bottom: 1px solid #e8eef5; background: #fff; }
    .fac-panel__eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: .3rem; }
    .fac-panel__title { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; }
    .fac-panel__body { padding: 1rem 1.25rem 1.25rem; }
    .fac-form-label { font-weight: 700; color: #0f172a; font-size: .86rem; }
    .fac-help { color: #64748b; font-size: .84rem; }
    .fac-badge { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; padding: .3rem .55rem; font-size: .75rem; font-weight: 800; border: 1px solid transparent; }
    .fac-badge--success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .fac-badge--warning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
    .fac-badge--muted { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
    .fac-badge--primary { background: #eff6ff; color: #1d4ed8; border-color: #cfe0ff; }
    .fac-btn-primary, .fac-btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; min-height: 40px; border-radius: 12px; font-weight: 800; padding: .6rem 1rem; text-decoration: none; }
    .fac-btn-primary { border: 1px solid #2563eb; background: #2563eb; color: #fff; }
    .fac-btn-primary:hover { color: #fff; background: #1d4ed8; border-color: #1d4ed8; }
    .fac-btn-secondary { border: 1px solid #d9e4f3; background: #fff; color: #0f172a; }
    .fac-btn-secondary:hover { color: #0f172a; background: #f8fafc; }
</style>
@endpush

@section('content')
@php
    $funcionarioOptions = $funcionarios ?? collect();
    $nombreFuncionario = function ($id) use ($funcionarioOptions) {
        $f = $funcionarioOptions->firstWhere('id', $id);
        if (! $f) {
            return 'No definido';
        }
        return trim(($f->nombre_selector ?? ('Funcionario AC #' . $f->id)) . (($f->run_selector ?? '') ? ' · ' . $f->run_selector : ''));
    };
@endphp

<div class="container-fluid py-3 px-3">
    <div class="fac-page-header mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
            <div class="d-flex gap-3 align-items-start">
                <span class="fac-icon"><i class="bi bi-diagram-3"></i></span>
                <div>
                    <div class="text-uppercase fw-bold text-muted small mb-1">Administración Central · Matriz de autorización</div>
                    <h1 class="fac-title">Jefaturas y subrogancias AC</h1>
                    <p class="mb-0 text-muted">
                        Define la jefatura titular y hasta tres subrogantes por Subdirección o dependencia. La activación de subrogancia queda bajo administración de plataforma.
                    </p>
                </div>
            </div>
            <a href="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}" class="fac-btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Volver a funcionarios AC
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Revisa los datos ingresados.</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-info-circle me-2"></i>Regla funcional preparada</div>
        <div>
            Esta configuración todavía no modifica el flujo de Cometidos funcionarios. Queda preparada para que, en una siguiente etapa, los cometidos de Administración Central sean autorizados por la jefatura de la dependencia correspondiente, o por el subrogante activo cuando el administrador lo habilite.
        </div>
    </div>

    <div class="row g-4">
        @foreach($jefaturas as $jefatura)
            @php
                $subroganciaActiva = (bool) ($jefatura->subrogancia_activa ?? false);
                $nivelActivo = $jefatura->subrogante_activo_nivel ?? null;
                $subroganteActivoId = $nivelActivo ? data_get($jefatura, 'subrogante_' . $nivelActivo . '_funcionario_ac_id') : null;
            @endphp
            <div class="col-xl-6">
                <form method="POST" action="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.jefaturas.update', $jefatura->id) }}" class="fac-panel h-100">
                    @csrf
                    @method('PATCH')
                    <div class="fac-panel__header">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                            <div>
                                <div class="fac-panel__eyebrow">Dependencia</div>
                                <h2 class="fac-panel__title">{{ $jefatura->subdireccion_dependencia }}</h2>
                            </div>
                            <div>
                                @if($subroganciaActiva)
                                    <span class="fac-badge fac-badge--warning"><i class="bi bi-person-check"></i>Subrogancia activa</span>
                                @else
                                    <span class="fac-badge fac-badge--success"><i class="bi bi-person-badge"></i>Titular activo</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="fac-panel__body">
                        <input type="hidden" name="subdireccion_dependencia" value="{{ $jefatura->subdireccion_dependencia }}">

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fac-form-label">Jefatura titular</label>
                                <select name="jefatura_funcionario_ac_id" class="form-select">
                                    <option value="">Seleccione jefatura...</option>
                                    @foreach($funcionarioOptions as $funcionario)
                                        <option value="{{ $funcionario->id }}" @selected((int) ($jefatura->jefatura_funcionario_ac_id ?? 0) === (int) $funcionario->id)>
                                            {{ $funcionario->nombre_selector }}{{ $funcionario->run_selector ? ' · '.$funcionario->run_selector : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Sólo se listan funcionarios marcados como jefatura en Funcionarios AC autorizados.</div>
                            </div>

                            @for($i = 1; $i <= 3; $i++)
                                <div class="col-md-4">
                                    <label class="form-label fac-form-label">Subrogante {{ $i }}</label>
                                    <select name="subrogante_{{ $i }}_funcionario_ac_id" class="form-select">
                                        <option value="">Sin subrogante</option>
                                        @foreach($funcionarioOptions as $funcionario)
                                            <option value="{{ $funcionario->id }}" @selected((int) data_get($jefatura, 'subrogante_'.$i.'_funcionario_ac_id', 0) === (int) $funcionario->id)>
                                                {{ $funcionario->nombre_selector }}{{ $funcionario->run_selector ? ' · '.$funcionario->run_selector : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endfor

                            <div class="col-md-4">
                                <label class="form-label fac-form-label">Estado matriz</label>
                                <select name="activo" class="form-select">
                                    <option value="1" @selected((string) ($jefatura->activo ?? 1) === '1')>Activa</option>
                                    <option value="0" @selected((string) ($jefatura->activo ?? 1) === '0')>Inactiva</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fac-form-label">Activar subrogancia</label>
                                <select name="subrogancia_activa" class="form-select">
                                    <option value="0" @selected(! $subroganciaActiva)>No</option>
                                    <option value="1" @selected($subroganciaActiva)>Sí</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fac-form-label">Subrogante activo</label>
                                <select name="subrogante_activo_nivel" class="form-select">
                                    <option value="">Titular</option>
                                    <option value="1" @selected((string) $nivelActivo === '1')>Subrogante 1</option>
                                    <option value="2" @selected((string) $nivelActivo === '2')>Subrogante 2</option>
                                    <option value="3" @selected((string) $nivelActivo === '3')>Subrogante 3</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fac-form-label">Subrogancia desde</label>
                                <input type="date" name="subrogancia_desde" class="form-control" value="{{ old('subrogancia_desde', $jefatura->subrogancia_desde ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fac-form-label">Subrogancia hasta</label>
                                <input type="date" name="subrogancia_hasta" class="form-control" value="{{ old('subrogancia_hasta', $jefatura->subrogancia_hasta ?? '') }}">
                            </div>

                            <div class="col-12">
                                <label class="form-label fac-form-label">Motivo subrogancia</label>
                                <textarea name="motivo_subrogancia" rows="2" class="form-control" placeholder="Ej.: feriado legal, comisión de servicio, licencia médica u otro motivo administrativo">{{ old('motivo_subrogancia', $jefatura->motivo_subrogancia ?? '') }}</textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fac-form-label">Observaciones</label>
                                <textarea name="observaciones" rows="2" class="form-control">{{ old('observaciones', $jefatura->observaciones ?? '') }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mt-3 pt-3 border-top">
                            <div class="fac-help">
                                @if($subroganciaActiva && $subroganteActivoId)
                                    Autoriza actualmente: <strong>{{ $nombreFuncionario($subroganteActivoId) }}</strong>
                                @elseif($jefatura->jefatura_funcionario_ac_id)
                                    Autoriza actualmente: <strong>{{ $nombreFuncionario($jefatura->jefatura_funcionario_ac_id) }}</strong>
                                @else
                                    Sin jefatura titular definida.
                                @endif
                            </div>
                            <button type="submit" class="fac-btn-primary">
                                <i class="bi bi-save"></i>
                                Guardar matriz
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection
