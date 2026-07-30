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
            <label class="form-label">RUT</label>
            <input type="text" class="form-control" value="{{ $user->rut }}" disabled>
            <div class="form-text">El RUT queda fijo una vez creado el usuario.</div>
        </div>
    @else
        <div class="col-md-4">
            <label class="form-label">RUT <span class="text-danger">*</span></label>
            <input type="text" name="rut" value="{{ old('rut') }}"
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
        <label class="form-label">Nombres <span class="text-danger">*</span></label>
        <input type="text" name="nombres" value="{{ old('nombres', $user->nombres ?? '') }}"
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
        <label class="form-label">Apellido paterno <span class="text-danger">*</span></label>
        <input type="text" name="apellido_paterno"
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
        <label class="form-label">Apellido materno <span class="text-danger">*</span></label>
        <input type="text" name="apellido_materno"
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
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}"
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

    <div class="col-md-7">
        <label class="form-label d-block">Roles <span class="text-danger">*</span></label>
        <div class="border rounded-3 p-3 @if ($errors->has('roles') || $errors->has('roles.*')) border-danger @endif">
            <div class="row g-2">
                @foreach ($roles as $value => $label)
                    <div class="col-md-6 col-xl-4">
                        <div class="form-check">
                            <input class="form-check-input js-role-checkbox" type="checkbox" name="roles[]"
                                value="{{ $value }}" id="role_{{ $value }}" @checked(in_array($value, $selectedRoles, true))>
                            <label class="form-check-label" for="role_{{ $value }}">{{ $label }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="form-text">Puedes asignar uno o varios roles. Si el usuario mantiene el rol postulante, seguirá disponible en selectores de propuesta y reasignación.</div>
        @error('roles')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('roles.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    @if ($isEdit)
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check form-switch">
                <input type="hidden" name="email_verified" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="email_verified"
                    name="email_verified" value="1" @checked(old('email_verified', !empty($user?->email_verified_at) ? 1 : 0))>
                <label class="form-check-label" for="email_verified">Email verificado</label>
                <div class="form-text">Controla si la cuenta aparece como verificada.</div>
            </div>
        </div>
    @endif

    <div class="col-md-9" id="establecimiento-wrapper" style="display:none;">
        <label class="form-label">Establecimiento <span class="text-danger">*</span></label>
        <select name="establecimiento_id" class="form-select @error('establecimiento_id') is-invalid @enderror">
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
        <div class="form-text">Obligatorio cuando alguno de los roles seleccionados sea Funcionario, Funcionario establecimiento o Funcionario Directivo Establecimiento.</div>

        @error('establecimiento_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @unless ($errors->has('establecimiento_id'))
            <div class="invalid-feedback"></div>
        @endunless
    </div>
</div>
