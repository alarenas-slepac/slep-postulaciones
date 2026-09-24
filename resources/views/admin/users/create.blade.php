@extends('layouts.app')

@section('content')
<div class="ur-page">
    <div class="ur-hero">
        <div class="ur-hero-main">
            <span class="ur-hero-icon" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
            <div>
                <div class="ur-eyebrow">Administración · Usuarios</div>
                <h1 class="ur-hero-title">Crear usuario</h1>
                <p class="ur-hero-subtitle">Registra la cuenta y asigna sus roles y establecimiento cuando corresponda.</p>
            </div>
        </div>
        <div class="ur-hero-actions"><a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver</a></div>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card ur-panel">
                <div class="card-header"><div class="ur-panel-kicker">Datos de la cuenta</div><h2 class="ur-panel-title">Información y accesos</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.store') }}" class="js-validate" novalidate>
                        @csrf

                        @include('admin.users._form')

                        <p class="ur-info-help mt-4 mb-0">
                            * Campos obligatorios. El usuario podrá definir su contraseña usando “Olvidé mi contraseña”.
                        </p>
                        <div class="ur-actionbar mt-4">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Crear usuario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('partials.form-validation')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleChecks = Array.from(document.querySelectorAll('.js-role-checkbox'));
            const wrapper = document.getElementById('establecimiento-wrapper');
            const estabSelect = document.querySelector('select[name="establecimiento_id"]');

            function toggleEstablecimiento() {
                const selectedRoles = roleChecks.filter((el) => el.checked).map((el) => el.value);
                const needsEstablecimiento = selectedRoles.includes('funcionario') || selectedRoles.includes('funcionario_estab') || selectedRoles.includes('funcionario_directivo_estab');
                wrapper.style.display = needsEstablecimiento ? '' : 'none';

                if (!needsEstablecimiento) {
                    estabSelect.value = '';
                }
            }

            roleChecks.forEach((check) => check.addEventListener('change', toggleEstablecimiento));
            toggleEstablecimiento();
        });
    </script>
@endpush
