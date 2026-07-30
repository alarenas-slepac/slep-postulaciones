@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card sga-auth-card">
                <div class="card-body">
                    <h1 class="h4 mb-3">Crear cuenta</h1>
                    <p class="text-muted mb-4">
                        Ingresa tu RUT y presiona <strong>Validar RUT</strong>. Si el RUT aparece en el padrón vigente de
                        personal, podrás completar el auto-registro como funcionario confirmando tu fecha de nacimiento.
                        Si no aparece, continuarás como postulante.
                    </p>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    @if ($errors->has('register'))
                        <div class="alert alert-danger">{{ $errors->first('register') }}</div>
                    @endif

                    <form method="POST" action="{{ route('register.store') }}" class="js-validate" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">RUT <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input id="rut" type="text" name="rut" value="{{ old('rut') }}"
                                    class="form-control @error('rut') is-invalid @enderror" placeholder="12.345.678-K" required
                                    data-validate="rut" autocomplete="off">
                                <button class="btn btn-outline-primary" type="button" id="btnBuscarRut">
                                    <i class="bi bi-search"></i> Validar RUT
                                </button>
                                @error('rut')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @unless ($errors->has('rut'))
                                    <div class="invalid-feedback"></div>
                                @endunless
                            </div>
                            <div class="form-text">Formato: 12.345.678-K (dígito verificador obligatorio).</div>
                            <div id="rutLookupStatus" class="small mt-1" aria-live="polite"></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha de nacimiento</label>
                                <input id="fecha_nacimiento_funcionario" type="date" name="fecha_nacimiento_funcionario"
                                    value="{{ old('fecha_nacimiento_funcionario') }}"
                                    class="form-control @error('fecha_nacimiento_funcionario') is-invalid @enderror"
                                    autocomplete="bday">
                                <div class="form-text">Sólo es obligatoria si el RUT figura como funcionario.</div>
                                @error('fecha_nacimiento_funcionario')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @unless ($errors->has('fecha_nacimiento_funcionario'))
                                    <div class="invalid-feedback"></div>
                                @endunless
                            </div>

                            <div class="col-md-6 d-none" id="establecimiento_wrapper">
                                <label class="form-label">Establecimiento asignado</label>
                                <input id="establecimiento_label" type="text" class="form-control" value="" readonly>
                                <div class="form-text">Este establecimiento se obtiene desde el padrón vigente y no es editable.</div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Nombres <span class="text-danger">*</span></label>
                                <input id="nombres" type="text" name="nombres" value="{{ old('nombres') }}"
                                    class="form-control @error('nombres') is-invalid @enderror" placeholder="Tus nombres"
                                    required autocomplete="off">
                                <div class="form-text">Tal como aparecen en tu documento.</div>
                                @error('nombres')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @unless ($errors->has('nombres'))
                                    <div class="invalid-feedback"></div>
                                @endunless
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Apellido paterno <span class="text-danger">*</span></label>
                                <input id="apellido_paterno" type="text" name="apellido_paterno"
                                    value="{{ old('apellido_paterno') }}"
                                    class="form-control @error('apellido_paterno') is-invalid @enderror"
                                    placeholder="Tu apellido paterno" required autocomplete="off">
                                @error('apellido_paterno')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @unless ($errors->has('apellido_paterno'))
                                    <div class="invalid-feedback"></div>
                                @endunless
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Apellido materno <span class="text-danger">*</span></label>
                                <input id="apellido_materno" type="text" name="apellido_materno"
                                    value="{{ old('apellido_materno') }}"
                                    class="form-control @error('apellido_materno') is-invalid @enderror"
                                    placeholder="Tu apellido materno" required autocomplete="off">
                                @error('apellido_materno')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @unless ($errors->has('apellido_materno'))
                                    <div class="invalid-feedback"></div>
                                @endunless
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}"
                                    class="form-control @error('email') is-invalid @enderror"
                                    placeholder="tu.correo@ejemplo.cl" required autocomplete="off">
                                <div class="form-text">Usaremos este correo para notificaciones y recuperar tu contraseña.</div>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @unless ($errors->has('email'))
                                    <div class="invalid-feedback"></div>
                                @endunless
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
                                <div class="form-text">Usa mayúsculas, minúsculas, números y símbolos.</div>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @unless ($errors->has('password'))
                                    <div class="invalid-feedback"></div>
                                @endunless
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" class="form-control"
                                    placeholder="Repite la contraseña" required data-match='[name="password"]'
                                    autocomplete="new-password">
                                <div class="form-text">Debe coincidir con la contraseña.</div>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button class="btn btn-primary" type="submit">Crear cuenta</button>
                            <a class="btn btn-outline-secondary" href="{{ route('login') }}">Ya tengo cuenta</a>
                        </div>

                        <p class="text-muted mt-3 mb-0">* Campos obligatorios</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.form-validation')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rut = document.getElementById('rut');
            const btnBuscarRut = document.getElementById('btnBuscarRut');
            const status = document.getElementById('rutLookupStatus');
            const birthDate = document.getElementById('fecha_nacimiento_funcionario');
            const nombres = document.getElementById('nombres');
            const apPat = document.getElementById('apellido_paterno');
            const apMat = document.getElementById('apellido_materno');
            const establecimientoWrapper = document.getElementById('establecimiento_wrapper');
            const establecimientoLabel = document.getElementById('establecimiento_label');
            let birthDateLookupTimer = null;
            let waitingForFuncionarioBirthDate = false;
            let lastLookupKey = '';

            if (!rut || !btnBuscarRut || !status) return;

            const setStatus = (message = '', cls = '') => {
                status.className = 'small mt-1' + (cls ? ` ${cls}` : '');
                status.textContent = message;
            };

            const setFuncionarioFieldsLocked = (locked) => {
                [nombres, apPat, apMat].forEach((field) => {
                    if (!field) return;
                    field.readOnly = locked;
                    field.classList.toggle('bg-light', locked);
                });
            };

            const setFuncionarioPrefill = (data) => {
                if (nombres) nombres.value = data.nombres || '';
                if (apPat) apPat.value = data.apellido_paterno || '';
                if (apMat) apMat.value = data.apellido_materno || '';
                if (establecimientoLabel) establecimientoLabel.value = data.establecimiento_label || '';
                establecimientoWrapper?.classList.remove('d-none');
                setFuncionarioFieldsLocked(true);
            };

            const resetFuncionarioPrefill = () => {
                waitingForFuncionarioBirthDate = false;
                establecimientoWrapper?.classList.add('d-none');
                if (establecimientoLabel) establecimientoLabel.value = '';
                setFuncionarioFieldsLocked(false);
            };

            const scheduleBirthDateLookup = () => {
                const rutValue = (rut.value || '').trim();
                const birthDateValue = (birthDate?.value || '').trim();

                if (!waitingForFuncionarioBirthDate || !rutValue || !/^\d{4}-\d{2}-\d{2}$/.test(birthDateValue)) {
                    return;
                }

                window.clearTimeout(birthDateLookupTimer);
                birthDateLookupTimer = window.setTimeout(() => {
                    void doLookup();
                }, 500);
            };

            const doLookup = async () => {
                const rutValue = (rut.value || '').trim();
                const birthDateValue = (birthDate?.value || '').trim();
                const lookupKey = `${rutValue}|${birthDateValue}`;

                if (!rutValue) {
                    setStatus('Ingresa un RUT antes de validar.', 'text-warning');
                    resetFuncionarioPrefill();
                    return;
                }

                if (lookupKey === lastLookupKey) {
                    return;
                }

                lastLookupKey = lookupKey;
                btnBuscarRut.disabled = true;
                setStatus('Validando RUT...', 'text-muted');

                try {
                    const url = new URL(`{{ route('register.lookup-rut') }}`, window.location.origin);
                    url.searchParams.set('rut', rutValue);
                    if (birthDateValue) {
                        url.searchParams.set('fecha_nacimiento', birthDateValue);
                    }

                    const res = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    const data = await res.json();

                    if (!res.ok && !data) {
                        throw new Error('lookup_failed');
                    }

                    if (data.status === 'invalid') {
                        resetFuncionarioPrefill();
                        setStatus(data.message || 'Ingresa un RUT válido antes de continuar.', 'text-danger');
                        return;
                    }

                    if (data.status === 'error') {
                        resetFuncionarioPrefill();
                        setStatus(data.message || 'No fue posible validar tu RUT en este momento.', 'text-danger');
                        return;
                    }

                    if (data.status === 'funcionario_requires_birth_date') {
                        waitingForFuncionarioBirthDate = true;
                        establecimientoWrapper?.classList.add('d-none');
                        if (establecimientoLabel) establecimientoLabel.value = '';
                        setFuncionarioFieldsLocked(false);
                        setStatus(
                            data.message ||
                                'RUT encontrado en el padrón vigente de personal. Ingresa tu fecha de nacimiento para continuar.',
                            'text-success'
                        );
                        birthDate?.focus();
                        return;
                    }

                    if (data.status === 'funcionario_birth_date_mismatch') {
                        waitingForFuncionarioBirthDate = true;
                        establecimientoWrapper?.classList.add('d-none');
                        if (establecimientoLabel) establecimientoLabel.value = '';
                        setFuncionarioFieldsLocked(false);
                        setStatus(data.message || 'La fecha de nacimiento no coincide.', 'text-danger');
                        birthDate?.focus();
                        return;
                    }

                    if (data.status === 'funcionario_prefill') {
                        waitingForFuncionarioBirthDate = true;
                        setFuncionarioPrefill(data);
                        setStatus(
                            data.message ||
                                'Identidad confirmada. Completamos los datos desde el padrón vigente para registrar al funcionario.',
                            'text-success'
                        );
                        return;
                    }

                    resetFuncionarioPrefill();
                    setStatus(data.message || 'RUT válido. Puedes continuar con el registro como postulante.', 'text-success');
                } catch (error) {
                    lastLookupKey = '';
                    resetFuncionarioPrefill();
                    setStatus('No se pudo validar el RUT en este momento.', 'text-danger');
                } finally {
                    btnBuscarRut.disabled = false;
                }
            };

            btnBuscarRut.addEventListener('click', doLookup);

            rut.addEventListener('input', () => {
                window.clearTimeout(birthDateLookupTimer);
                lastLookupKey = '';
                resetFuncionarioPrefill();
                setStatus();
            });

            birthDate?.addEventListener('input', () => {
                window.clearTimeout(birthDateLookupTimer);
                lastLookupKey = '';

                if (waitingForFuncionarioBirthDate) {
                    establecimientoWrapper?.classList.add('d-none');
                    if (establecimientoLabel) establecimientoLabel.value = '';
                    setFuncionarioFieldsLocked(false);
                    setStatus();
                    scheduleBirthDateLookup();
                    return;
                }

                setStatus();
            });

            birthDate?.addEventListener('change', () => {
                scheduleBirthDateLookup();
            });

            rut.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    doLookup();
                }
            });
        });
    </script>
@endpush
