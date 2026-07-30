@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0">Editar usuario</h1>
                    <div class="text-muted small">{{ $user->nombre_completo ?: $user->email }}</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-primary">Ver ficha</a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Volver</a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="js-validate" novalidate>
                        @csrf
                        @method('PUT')

                        @include('admin.users._form', ['user' => $user])

                        <hr class="my-4">

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
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
