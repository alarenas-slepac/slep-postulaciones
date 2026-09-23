@extends('layouts.app')

@push('styles')
    <style>
        .io-card { border: 1px solid #d9e4f3; border-radius: 22px; background: #fff; box-shadow: 0 14px 34px rgba(15,23,42,.06); }
        .io-hero { padding: 1.5rem 1.7rem; background: linear-gradient(135deg,#fff,#eff6ff); }
        .io-eyebrow { color: #1d4ed8; font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .io-title { color: #0f172a; font-size: 1.7rem; font-weight: 800; margin: .45rem 0; }
        .io-help { color: #475569; margin: 0; max-width: 50rem; }
        .io-panel-head { padding: 1.1rem 1.4rem; border-bottom: 1px solid #e8eef5; }
        .io-panel-body { padding: 1.35rem 1.4rem; }
        .io-section { border: 1px solid #dbe6f2; border-radius: 16px; padding: 1rem; background: #fbfdff; }
        .io-section-title { font-weight: 800; color: #0f172a; margin-bottom: .2rem; }
        .io-label { color: #334155; font-size: .86rem; font-weight: 800; margin-bottom: .35rem; }
        .io-primary,.io-secondary { min-height: 45px; border-radius: 13px; padding: .7rem 1rem; display: inline-flex; align-items: center; justify-content: center; gap: .5rem; font-weight: 800; text-decoration: none; }
        .io-primary { color: #fff; background: #1d4ed8; border: 1px solid #1d4ed8; box-shadow: 0 10px 20px rgba(37,99,235,.22); }
        .io-primary:hover { color: #fff; background: #1e40af; }
        .io-secondary { color: #1d4ed8; background: #fff; border: 1px solid #bfdbfe; }
        .io-secondary:hover { color: #1e40af; background: #eff6ff; }
        .io-note { border: 1px solid #bae6fd; background: #f0f9ff; color: #0c4a6e; border-radius: 14px; padding: .85rem 1rem; }
    </style>
@endpush

@section('content')
    <div class="container py-4">
        <section class="io-card io-hero mb-4">
            <div class="io-eyebrow"><i class="bi bi-file-earmark-richtext"></i> Oficio de idoneidad psicológica</div>
            <h1 class="io-title">Preparar oficio de la solicitud #{{ $solicitud->id }}</h1>
            <p class="io-help">Confirma las autoridades y el contacto institucional. Primero podrás previsualizar el PDF; sólo después podrás descargarlo.</p>
        </section>

        <form method="POST" action="{{ route('tramites.idoneidad-psicologica.oficio.previsualizar', $solicitud) }}" target="_blank" class="io-card">
            @csrf
            <div class="io-panel-head"><strong>Datos para el oficio</strong><div class="small text-muted mt-1">Los nombres y cargos se solicitan para esta emisión; revisa la previsualización antes de descargar el archivo definitivo.</div></div>
            <div class="io-panel-body">
                <div class="io-note mb-4"><i class="bi bi-info-circle me-1"></i>El oficio incorpora el período de la solicitud, la nómina histórica y la fecha de emisión automática.</div>

                <div class="io-section mb-3">
                    <div class="io-section-title">Director Regional del Servicio de Salud Concepción</div>
                    <div class="small text-muted mb-3">Destinatario del oficio.</div>
                    <div class="row g-3"><div class="col-md-6"><label class="io-label" for="director_regional_nombre">Nombre completo</label><input class="form-control @error('director_regional_nombre') is-invalid @enderror" id="director_regional_nombre" name="director_regional_nombre" maxlength="180" required value="{{ old('director_regional_nombre', $datos['director_regional_nombre']) }}">@error('director_regional_nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label class="io-label" for="director_regional_cargo">Cargo</label><input class="form-control @error('director_regional_cargo') is-invalid @enderror" id="director_regional_cargo" name="director_regional_cargo" maxlength="220" required value="{{ old('director_regional_cargo', $datos['director_regional_cargo']) }}">@error('director_regional_cargo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                </div>

                <div class="io-section mb-3">
                    <div class="io-section-title">Director Ejecutivo del Servicio Local</div>
                    <div class="small text-muted mb-3">Firma el oficio.</div>
                    <div class="row g-3"><div class="col-md-6"><label class="io-label" for="director_ejecutivo_nombre">Nombre completo</label><input class="form-control @error('director_ejecutivo_nombre') is-invalid @enderror" id="director_ejecutivo_nombre" name="director_ejecutivo_nombre" maxlength="180" required value="{{ old('director_ejecutivo_nombre', $datos['director_ejecutivo_nombre']) }}">@error('director_ejecutivo_nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label class="io-label" for="director_ejecutivo_cargo">Cargo</label><input class="form-control @error('director_ejecutivo_cargo') is-invalid @enderror" id="director_ejecutivo_cargo" name="director_ejecutivo_cargo" maxlength="220" required value="{{ old('director_ejecutivo_cargo', $datos['director_ejecutivo_cargo']) }}">@error('director_ejecutivo_cargo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                </div>

                <div class="io-section">
                    <div class="io-section-title">Contacto de Subdirección de Gestión de Personas</div>
                    <div class="small text-muted mb-3">Se prellena desde tu usuario y puedes corregirlo para esta emisión.</div>
                    <div class="row g-3"><div class="col-md-6"><label class="io-label" for="contacto_nombre">Nombre completo</label><input class="form-control @error('contacto_nombre') is-invalid @enderror" id="contacto_nombre" name="contacto_nombre" maxlength="180" required value="{{ old('contacto_nombre', $datos['contacto_nombre']) }}">@error('contacto_nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label class="io-label" for="contacto_email">Correo institucional</label><input class="form-control @error('contacto_email') is-invalid @enderror" type="email" id="contacto_email" name="contacto_email" maxlength="190" required value="{{ old('contacto_email', $datos['contacto_email']) }}">@error('contacto_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                </div>

                <div class="d-flex flex-wrap justify-content-between gap-2 mt-4"><a class="io-secondary" href="{{ route('tramites.idoneidad-psicologica.show', $solicitud) }}"><i class="bi bi-arrow-left"></i> Volver</a><div class="d-flex flex-wrap gap-2"><button class="io-secondary" type="submit"><i class="bi bi-eye"></i> Previsualizar PDF</button><button class="io-primary" type="submit" formtarget="_self" formaction="{{ route('tramites.idoneidad-psicologica.oficio.descargar', $solicitud) }}"><i class="bi bi-download"></i> Descargar oficio</button></div></div>
            </div>
        </form>
    </div>
@endsection
