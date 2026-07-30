@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0">Crear usuario</h1>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Volver</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.store') }}" class="js-validate" novalidate>
                        @csrf

                        @include('admin.users._form')

                        <hr class="my-4">

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Crear usuario</button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>

                        <p class="text-muted mt-3 mb-0">
                            * Campos obligatorios. El usuario podrá definir su contraseña usando “Olvidé mi contraseña”.
                        </p>
                    </form>
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
