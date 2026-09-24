@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;
    $selectedRoles = collect(old('roles', $user?->roles?->pluck('name')->all() ?? []))
        ->map(fn($role) => (string) $role)
        ->filter()
        ->values()
        ->all();
    $selectedEstablecimiento = old('establecimiento_id', $user?->establecimiento_id);
    $isEdit = isset($user) && $user?->exists;
@endphp

<div class="row g-3">
    @if ($isEdit)
        <div class="col-md-4">
            <label class="form-label" for="user-rut-readonly">RUT</label>
            <input id="user-rut-readonly" type="text" class="form-control" value="{{ $user->rut }}" disabled>
            <div class="form-text">El RUT queda fijo una vez creado el usuario.</div>
        </div>
    @else
        <div class="col-md-4">
            <label class="form-label" for="user-rut">RUT <span class="text-danger">*</span></label>
            <input id="user-rut" type="text" name="rut" value="{{ old('rut') }}"
                class="form-control @error('rut') is-invalid @enderror" placeholder="12.345.678-K" required
                data-validate="rut" autocomplete="off">
            <div class="form-text">Usa el formato con guion y dígito verificador (ej.: 12.345.678-K).</div>
            @error('rut')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            @unless ($errors->has('rut'))
                <div class="invalid-feedback"></div>
            @endunless
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label" for="user-nombres">Nombres <span class="text-danger">*</span></label>
        <input id="user-nombres" type="text" name="nombres" value="{{ old('nombres', $user->nombres ?? '') }}"
            class="form-control @error('nombres') is-invalid @enderror" placeholder="Nombres del usuario" required
            autocomplete="off">
        <div class="form-text">Escribe los nombres tal como figuran en el documento.</div>
        @error('nombres')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @unless ($errors->has('nombres'))
            <div class="invalid-feedback"></div>
        @endunless
    </div>

    <div class="col-md-4">
        <label class="form-label" for="user-apellido-paterno">Apellido paterno <span class="text-danger">*</span></label>
        <input id="user-apellido-paterno" type="text" name="apellido_paterno"
            value="{{ old('apellido_paterno', $user->apellido_paterno ?? '') }}"
            class="form-control @error('apellido_paterno') is-invalid @enderror" placeholder="Apellido paterno"
            required autocomplete="off">
        <div class="form-text">Sin abreviaturas.</div>
        @error('apellido_paterno')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @unless ($errors->has('apellido_paterno'))
            <div class="invalid-feedback"></div>
        @endunless
    </div>

    <div class="col-md-4">
        <label class="form-label" for="user-apellido-materno">Apellido materno <span class="text-danger">*</span></label>
        <input id="user-apellido-materno" type="text" name="apellido_materno"
            value="{{ old('apellido_materno', $user->apellido_materno ?? '') }}"
            class="form-control @error('apellido_materno') is-invalid @enderror" placeholder="Apellido materno"
            required autocomplete="off">
        <div class="form-text">Sin abreviaturas.</div>
        @error('apellido_materno')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @unless ($errors->has('apellido_materno'))
            <div class="invalid-feedback"></div>
        @endunless
    </div>

    <div class="col-md-5">
        <label class="form-label" for="user-email">Email <span class="text-danger">*</span></label>
        <input id="user-email" type="email" name="email" value="{{ old('email', $user->email ?? '') }}"
            class="form-control @error('email') is-invalid @enderror" placeholder="usuario@ejemplo.cl" required
            autocomplete="off">
        <div class="form-text">Será usado para acceso y notificaciones.</div>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @unless ($errors->has('email'))
            <div class="invalid-feedback"></div>
        @endunless
    </div>

    <div class="col-12">
        <fieldset class="ur-section ur-role-section @if ($errors->has('roles') || $errors->has('roles.*')) border-danger @endif">
            <legend class="form-label">Roles <span class="text-danger">*</span></legend>
            <div class="row g-2">
                @foreach ($roles as $value => $label)
                    <div class="col-sm-6 col-lg-4 col-xxl-3">
                        <div class="form-check ur-option">
                            <input class="form-check-input js-role-checkbox" type="checkbox" name="roles[]"
                                value="{{ $value }}" id="role_{{ $value }}" @checked(in_array($value, $selectedRoles, true))>
                            <label class="form-check-label" for="role_{{ $value }}">{{ $label }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="form-text mb-0 mt-3">Puedes asignar uno o varios roles. Si el usuario mantiene el rol postulante, seguirá disponible en selectores de propuesta y reasignación.</p>
        </fieldset>
        @error('roles')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('roles.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    @if ($isEdit)
        <div class="col-12" id="verification-wrapper">
            <div class="ur-section ur-account-section">
                <div class="ur-section-title">Estado de la cuenta</div>
                <div class="form-check form-switch">
                    <input type="hidden" name="email_verified" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="email_verified"
                        name="email_verified" value="1" @checked(old('email_verified', !empty($user?->email_verified_at) ? 1 : 0))>
                    <label class="form-check-label" for="email_verified">Email verificado</label>
                </div>
                <div class="form-text mt-2">Controla si la cuenta aparece como verificada.</div>
            </div>
        </div>
    @endif

    <div class="col-12 {{ $isEdit ? 'col-lg-8' : '' }}" id="establecimiento-wrapper" hidden>
        <div class="ur-section ur-account-section">
            <label class="form-label" for="user-establecimiento">Establecimiento <span class="text-danger">*</span></label>
            <select id="user-establecimiento" name="establecimiento_id" class="form-select js-ur-searchable-select @error('establecimiento_id') is-invalid @enderror" data-placeholder="Buscar establecimiento por RBD o nombre" aria-describedby="user-establecimiento-help user-establecimiento-error">
                <option value="">Seleccione un establecimiento...</option>
                @foreach ($establecimientos as $comuna => $items)
                    <optgroup label="{{ $comuna }}">
                        @foreach ($items as $e)
                            <option value="{{ $e->id }}" @selected((string) $selectedEstablecimiento === (string) $e->id)>
                                {{ $e->rbd }} — {{ $e->nombre_establecimiento }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <div class="form-text" id="user-establecimiento-help">Obligatorio cuando alguno de los roles seleccionados sea Funcionario, Funcionario establecimiento o Funcionario Directivo Establecimiento.</div>

            @error('establecimiento_id')
                <div class="invalid-feedback d-block" id="user-establecimiento-error">{{ $message }}</div>
            @enderror
            @unless ($errors->has('establecimiento_id'))
                <div class="invalid-feedback" id="user-establecimiento-error"></div>
            @endunless
        </div>
    </div>
</div>
