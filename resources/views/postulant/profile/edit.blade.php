@extends('layouts.app')

@section('content')
    @php
        $requiredMessage = $requiredMessage ?? 'Este campo es obligatorio.';
    @endphp

    <div class="dashboard dashboard--postulante">
        <div class="dashboard-hero mb-4 d-flex align-items-center gap-3">
            <div class="hero-icon display-6"><i class="bi bi-person-badge"></i></div>
            <div>
                <h2 class="hero-title h4 m-0">Mi Perfil</h2>
                <p class="hero-subtitle m-0 text-muted">Completa tus datos personales</p>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>No se pudo guardar.</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="profileForm" class="needs-validation" novalidate method="POST"
            action="{{ route('postulant.profile.update', auth()->id()) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-lg-8">

                    {{-- ======= Datos personales ======= --}}
                    <div class="card mb-4" id="card-datos-personales">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Datos personales</h5>

                            {{-- Email de contacto --}}
                            <div class="mb-3" id="anchor-email_contacto">
                                <label class="form-label required" for="email_contacto">Email de contacto</label>
                                <input type="email" class="form-control @error('email_contacto') is-invalid @enderror"
                                    id="email_contacto" name="email_contacto"
                                    value="{{ old('email_contacto', $profile->email_contacto ?? $user->email) }}"
                                    autocomplete="email" required>
                                @error('email_contacto')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                                <div class="form-text">Usaremos este correo para notificaciones (recuperación, mensajes,
                                    etc.).</div>
                            </div>

                            {{-- Fecha de nacimiento --}}
                            <div class="mb-3" id="anchor-fecha_nacimiento">
                                <label class="form-label required" for="fecha_nacimiento">Fecha de nacimiento</label>
                                <input type="date" class="form-control @error('fecha_nacimiento') is-invalid @enderror"
                                    id="fecha_nacimiento" name="fecha_nacimiento"
                                    value="{{ old('fecha_nacimiento', isset($profile->fecha_nacimiento) ? \Illuminate\Support\Carbon::parse($profile->fecha_nacimiento)->format('Y-m-d') : '') }}"
                                    required>
                                @error('fecha_nacimiento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Dirección --}}
                            <div class="mb-3" id="anchor-direccion">
                                <label class="form-label required" for="direccion">Dirección</label>
                                <input type="text" class="form-control @error('direccion') is-invalid @enderror"
                                    id="direccion" name="direccion" placeholder="Calle, número, depto..."
                                    value="{{ old('direccion', $profile->direccion ?? '') }}" required>
                                @error('direccion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Región --}}
                            <div class="mb-3" id="anchor-region_code">
                                <label class="form-label required" for="region_code">Región</label>
                                <select class="form-select @error('region_code') is-invalid @enderror" id="region_code"
                                    name="region_code" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($regiones as $code => $nombre)
                                        <option value="{{ $code }}" @selected(old('region_code', $profile->region_code ?? '') == $code)>
                                            {{ $nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('region_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Comuna --}}
                            <div class="mb-3" id="anchor-comuna_id">
                                <label class="form-label required" for="comuna_id">Comuna</label>
                                @php
                                    $regionSel = old('region_code', $profile->region_code ?? '');
                                    $comunas =
                                        $regionSel && isset($communesByRegion[$regionSel])
                                            ? $communesByRegion[$regionSel]
                                            : [];
                                    $comunaSel = old('comuna_id', $profile->comuna_id ?? '');
                                @endphp
                                <select class="form-select @error('comuna_id') is-invalid @enderror" id="comuna_id"
                                    name="comuna_id" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($comunas as $c)
                                        <option value="{{ $c['id'] }}" @selected((string) $comunaSel === (string) $c['id'])>
                                            {{ $c['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('comuna_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Nacionalidad (con ISO + flag-icons si está instalado) --}}
                            <div class="mb-3" id="anchor-nacionalidad">
                                <label for="nacionalidad" class="form-label">Nacionalidad</label>

                                <div id="nacPreview" class="d-flex align-items-center mb-2">
                                    <span class="me-2">
                                        <span class="fi fi-{{ $initial['iso'] ?? 'cl' }}" aria-hidden="true"
                                            style="margin-right:.25rem;"></span>
                                        <span class="nac-emoji-fallback">{{ $initial['emoji'] ?? '🇨🇱' }}</span>
                                    </span>
                                    <strong class="nac-abbr">{{ $initial['abbr'] ?? 'CHL' }}</strong>
                                    <span class="mx-1">—</span>
                                    <span class="nac-name">{{ $initial['name'] ?? 'Chilena' }}</span>
                                </div>

                                <select class="form-select @error('nacionalidad') is-invalid @enderror" id="nacionalidad"
                                    name="nacionalidad" required>
                                    @foreach ($nacionalidades as $item)
                                        <option value="{{ $item['value'] }}" data-iso="{{ $item['iso'] }}"
                                            data-abbr="{{ $item['abbr'] }}" data-emoji="{{ $item['emoji'] }}"
                                            data-name="{{ $item['name'] }}" @selected($selNac === $item['value'])>
                                            {{ $item['emoji'] }} {{ $item['abbr'] }} — {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('nacionalidad')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                                <div class="form-text">Si no ves banderas, es normal: se mostrará el emoji. Para íconos SVG
                                    instala <code>flag-icons</code>.</div>
                            </div>

                            {{-- Género --}}
                            <div class="mb-3" id="anchor-genero">
                                <label class="form-label required" for="genero">Género</label>
                                @php $gSel = old('genero', $profile->genero ?? ''); @endphp
                                <select class="form-select @error('genero') is-invalid @enderror" id="genero"
                                    name="genero" required>
                                    <option value="">Seleccione…</option>
                                    <option value="masculino" @selected($gSel === 'masculino')>Masculino</option>
                                    <option value="femenino" @selected($gSel === 'femenino')>Femenino</option>
                                    <option value="otro" @selected($gSel === 'otro')>Otro</option>
                                </select>
                                @error('genero')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Pronombres (opcional) --}}
                            <div class="mb-3" id="anchor-pronombres">
                                <label class="form-label" for="pronombres">Pronombres (opcional)</label>
                                <input type="text" class="form-control @error('pronombres') is-invalid @enderror"
                                    id="pronombres" name="pronombres"
                                    value="{{ old('pronombres', $profile->pronombres ?? '') }}">
                                @error('pronombres')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Teléfono 1 --}}
                            <div class="mb-3" id="anchor-telefono1">
                                <label class="form-label required" for="telefono1">Teléfono 1</label>
                                <input type="text" class="form-control @error('telefono1') is-invalid @enderror"
                                    id="telefono1" name="telefono1" placeholder="+56 9 1234 5678"
                                    value="{{ old('telefono1', $profile->telefono1 ?? '') }}" required>
                                @error('telefono1')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                                <div class="form-text">Debe ser un móvil chileno válido (+56 9 ...).</div>
                            </div>

                            {{-- Teléfono 2 (opcional) --}}
                            <div class="mb-3" id="anchor-telefono2">
                                <label class="form-label" for="telefono2">Teléfono 2 (opcional)</label>
                                <input type="text" class="form-control @error('telefono2') is-invalid @enderror"
                                    id="telefono2" name="telefono2" placeholder="+56 9 9876 5432"
                                    value="{{ old('telefono2', $profile->telefono2 ?? '') }}">
                                @error('telefono2')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Foto (opcional) --}}
                            <div class="mb-3" id="anchor-foto">
                                <label class="form-label" for="foto">Foto (opcional)</label>
                                <input type="file" class="form-control @error('foto') is-invalid @enderror"
                                    id="foto" name="foto" accept="image/*">
                                @error('foto')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if (!empty($profile?->foto_thumb_path))
                                    <div class="mt-2">
                                        <img id="fotoPreview" src="{{ asset('storage/' . $profile->foto_thumb_path) }}"
                                            class="rounded" alt="Foto actual" width="80" height="80">
                                    </div>
                                @else
                                    <div class="mt-2">
                                        <img id="fotoPreview" class="rounded d-none" alt="Foto previa" width="80"
                                            height="80">
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>

                    {{-- ======= Académicos ======= --}}
                    <div class="card mb-4" id="card-academicos">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Antecedentes académicos</h5>

                            {{-- Estamento --}}
                            <div class="mb-3" id="anchor-estamento">
                                <label class="form-label required" for="estamento">Estamento</label>
                                @php $estSel = old('estamento', $profile->estamento ?? ''); @endphp
                                <select class="form-select @error('estamento') is-invalid @enderror" id="estamento"
                                    name="estamento" required>
                                    <option value="">Seleccione…</option>
                                    <option value="docente" @selected($estSel === 'docente')>Docente</option>
                                    <option value="asistente" @selected($estSel === 'asistente')>Asistente de la educación
                                    </option>
                                </select>
                                @error('estamento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Área de desempeño (Typeahead) --}}
                            <label class="form-label" for="area_desempeno_id">Área de desempeño</label>

                            <select id="area_desempeno_id" name="area_desempeno_id" class="form-select"
                                data-selected="{{ old('area_desempeno_id', $profile->area_desempeno_id ?? '') }}">
                            </select>

                            <div class="form-text">Al cambiar estamento se cargan las áreas disponibles.</div>


                            {{-- (Docente) Mención --}}
                            <div class="mb-3 d-none" id="wrap-mencion">
                                <label class="form-label" for="mencion">Mención</label>

                                {{-- Buscador --}}
                                <input type="text" id="mencion_search" class="form-control form-control-sm mb-2"
                                    placeholder="Buscar mención (nombre, universidad, año)…" autocomplete="off">

                                {{-- Select (llenado por JS con optgroups por subsector) --}}
                                <select class="form-select @error('mencion') is-invalid @enderror" id="mencion"
                                    name="mencion">
                                    @php $mencionSel = old('mencion', $profile->mencion ?? ''); @endphp
                                    @if ($mencionSel)
                                        <option value="{{ $mencionSel }}" selected>{{ $mencionSel }}</option>
                                    @else
                                        <option value="">Seleccione…</option>
                                    @endif
                                </select>

                                @error('mencion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage ?? 'Este campo es obligatorio.' }}</div>
                                @enderror
                                <div class="form-text">Formato: <code>mención - Universidad - año</code>.</div>
                            </div>

                            {{-- (Docente TP) Especialidad TP --}}
                            <div class="mb-3 d-none" id="wrap-especialidad_tp">
                                <label class="form-label" for="especialidad_tp">Especialidad TP</label>
                                <input type="text" id="especialidad_tp_search"
                                    class="form-control form-control-sm mb-2" placeholder="Buscar especialidad…"
                                    autocomplete="off">
                                <select class="form-select @error('especialidad_tp') is-invalid @enderror"
                                    id="especialidad_tp" name="especialidad_tp">
                                    <option value="">Seleccione…</option>

                                    @php $espSel = old('especialidad_tp', $profile->especialidad_tp ?? ''); @endphp
                                    <optgroup label="PROGRAMAS EDUCACIÓN MEDIA TÉCNICO-PROFESIONAL ">
                                        <option value="Acuicultura" @selected($espSel === 'Acuicultura')>Acuicultura</option>
                                        <option value="Administración con menciones en Recursos Humanos y Logística"
                                            @selected($espSel === 'Administración con menciones en Recursos Humanos y Logística')>Administración con menciones en Recursos Humanos y
                                            Logística</option>
                                        <option value="Agropecuaria con menciones en Agricultura, Pecuaria y Vitivinícola"
                                            @selected($espSel === 'Agropecuaria con menciones en Agricultura, Pecuaria y Vitivinícola')>Agropecuaria con menciones en Agricultura,
                                            Pecuaria y
                                            Vitivinícola</option>
                                        <option value="Asistencia en Geología" @selected($espSel === 'Asistencia en Geología')>Asistencia en
                                            Geología</option>
                                        <option value="Conectividad y Redes" @selected($espSel === 'Conectividad y Redes')>Conectividad y
                                            Redes
                                        </option>
                                        <option
                                            value="Construcción con menciones en Edificación, Terminaciones de la Construcción y Obras Viales y de Infraestructura"
                                            @selected($espSel === 'Construcción con menciones en Edificación, Terminaciones de la Construcción y Obras Viales y de Infraestructura')>Construcción con menciones en Edificación,
                                            Terminaciones de la Construcción y Obras Viales y de Infraestructura</option>
                                        <option value="Construcciones Metálicas" @selected($espSel === 'Construcciones Metálicas')>
                                            Construcciones
                                            Metálicas</option>
                                        <option value="Contabilidad" @selected($espSel === 'Contabilidad')>Contabilidad</option>
                                        <option value="Dibujo Técnico" @selected($espSel === 'Dibujo Técnico')>Dibujo Técnico
                                        </option>
                                        <option value="Elaboración Industrial de Alimentos" @selected($espSel === 'Elaboración Industrial de Alimentos')>
                                            Elaboración Industrial de Alimentos</option>
                                        <option value="Electricidad" @selected($espSel === 'Electricidad')>Electricidad</option>
                                        <option value="Electrónica" @selected($espSel === 'Electrónica')>Electrónica</option>
                                        <option value="Explotación Minera" @selected($espSel === 'Explotación Minera')>Explotación Minera
                                        </option>
                                        <option value="Forestal" @selected($espSel === 'Forestal')>Forestal</option>
                                        <option value="Gastronomía con menciones en Cocina y Pastelería y Repostería"
                                            @selected($espSel === 'Gastronomía con menciones en Cocina y Pastelería y Repostería')>Gastronomía con menciones en Cocina y Pastelería y
                                            Repostería</option>
                                        <option value="Gráfica" @selected($espSel === 'Gráfica')>Gráfica</option>
                                        <option value="Instalaciones Sanitarias" @selected($espSel === 'Instalaciones Sanitarias')>
                                            Instalaciones
                                            Sanitarias</option>
                                        <option value="Mecánica Automotriz" @selected($espSel === 'Mecánica Automotriz')>Mecánica
                                            Automotriz
                                        </option>
                                        <option
                                            value="Mecánica Industrial con menciones en Mantenimiento Electromecánico, Máquinas-Herramientas y Matricería"
                                            @selected($espSel === 'Mecánica Industrial con menciones en Mantenimiento Electromecánico, Máquinas-Herramientas y Matricería')>Mecánica Industrial con menciones en Mantenimiento
                                            Electromecánico, Máquinas-Herramientas y Matricería</option>
                                        <option value="Metalurgia Extractiva" @selected($espSel === 'Metalurgia Extractiva')>Metalurgia
                                            Extractiva</option>
                                        <option value="Montaje Industrial" @selected($espSel === 'Montaje Industrial')>Montaje Industrial
                                        </option>
                                        <option value="Muebles y Terminaciones en Madera" @selected($espSel === 'Muebles y Terminaciones en Madera')>
                                            Muebles
                                            y Terminaciones en Madera</option>
                                        <option value="Operaciones Portuarias" @selected($espSel === 'Operaciones Portuarias')>Operaciones
                                            Portuarias</option>
                                        <option value="Pesquería" @selected($espSel === 'Pesquería')>Pesquería</option>
                                        <option value="Programación" @selected($espSel === 'Programación')>Programación</option>
                                        <option
                                            value="Química Industrial con menciones en Laboratorio Químico y Planta Química"
                                            @selected($espSel === 'Química Industrial con menciones en Laboratorio Químico y Planta Química')>Química Industrial con menciones en Laboratorio
                                            Químico y Planta Química</option>
                                        <option value="Refrigeración y Climatización" @selected($espSel === 'Refrigeración y Climatización')>
                                            Refrigeración y Climatización</option>
                                        <option value="Servicios de Hotelería" @selected($espSel === 'Servicios de Hotelería')>Servicios de
                                            Hotelería</option>
                                        <option value="Servicios de Turismo" @selected($espSel === 'Servicios de Turismo')>Servicios de
                                            Turismo
                                        </option>
                                        <option value="Telecomunicaciones" @selected($espSel === 'Telecomunicaciones')>Telecomunicaciones
                                        </option>
                                        <option value="Tripulación de Naves Mercantes y Especiales"
                                            @selected($espSel === 'Tripulación de Naves Mercantes y Especiales')>Tripulación de Naves Mercantes y Especiales
                                        </option>
                                        <option value="Vestuario y Confección Textil" @selected($espSel === 'Vestuario y Confección Textil')>
                                            Vestuario y
                                            Confección Textil</option>
                                        <option value="Atención de Párvulos" @selected($espSel === 'Atención de Párvulos')>Atención de
                                            Párvulos
                                        </option>
                                        <option value="Atención de Enfermería" @selected($espSel === 'Atención de Enfermería')>Atención de
                                            Enfermería</option>
                                        <option value="Mecánica de Mantenimiento de Aeronaves"
                                            @selected($espSel === 'Mecánica de Mantenimiento de Aeronaves')>
                                            Mecánica de Mantenimiento de Aeronaves</option>
                                    </optgroup>
                                    <optgroup label="PROGRAMAS EDUCACIÓN MEDIA TÉCNICO-PROFESIONAL - ADULTOS">
                                        <option value="Acuicultura – Sector Marítimo" @selected($espSel === 'Acuicultura – Sector Marítimo')>
                                            Acuicultura – Sector Marítimo
                                        </option>
                                        <option value="Agropecuaria – Sector Agropecuario" @selected($espSel === 'Agropecuaria – Sector Agropecuario')>
                                            Agropecuaria – Sector Agropecuario
                                        </option>
                                        <option value="Atención de Adultos Mayores – Sector Programas y Proyectos Sociales"
                                            @selected($espSel === 'Atención de Adultos Mayores – Sector Programas y Proyectos Sociales')>
                                            Atención de Adultos Mayores – Sector Programas y Proyectos Sociales
                                        </option>
                                        <option value="Construcciones Metálicas – Sector Metalmecánico"
                                            @selected($espSel === 'Construcciones Metálicas – Sector Metalmecánico')>
                                            Construcciones Metálicas – Sector Metalmecánico
                                        </option>
                                        <option value="Elaboración Industrial de Alimentos – Sector Alimentación"
                                            @selected($espSel === 'Elaboración Industrial de Alimentos – Sector Alimentación')>
                                            Elaboración Industrial de Alimentos – Sector Alimentación
                                        </option>
                                        <option value="Electrónica – Sector Electricidad" @selected($espSel === 'Electrónica – Sector Electricidad')>
                                            Electrónica – Sector Electricidad
                                        </option>
                                        <option value="Electricidad – Sector Electricidad" @selected($espSel === 'Electricidad – Sector Electricidad')>
                                            Electricidad – Sector Electricidad
                                        </option>
                                        <option value="Forestal – Sector Maderero" @selected($espSel === 'Forestal – Sector Maderero')>
                                            Forestal – Sector Maderero
                                        </option>
                                        <option value="Instalaciones Sanitarias – Sector Construcción"
                                            @selected($espSel === 'Instalaciones Sanitarias – Sector Construcción')>
                                            Instalaciones Sanitarias – Sector Construcción
                                        </option>
                                        <option value="Mecánica Automotriz – Sector Metalmecánico"
                                            @selected($espSel === 'Mecánica Automotriz – Sector Metalmecánico')>
                                            Mecánica Automotriz – Sector Metalmecánico
                                        </option>
                                        <option value="Mecánica Industrial – Sector Metalmecánico"
                                            @selected($espSel === 'Mecánica Industrial – Sector Metalmecánico')>
                                            Mecánica Industrial – Sector Metalmecánico
                                        </option>
                                        <option value="Productos de la Madera – Sector Maderero"
                                            @selected($espSel === 'Productos de la Madera – Sector Maderero')>
                                            Productos de la Madera – Sector Maderero
                                        </option>
                                        <option value="Servicios de Alimentación Colectiva – Sector Alimentación"
                                            @selected($espSel === 'Servicios de Alimentación Colectiva – Sector Alimentación')>
                                            Servicios de Alimentación Colectiva – Sector Alimentación
                                        </option>
                                        <option value="Servicios Hoteleros – Sector Hotelería y Turismo"
                                            @selected($espSel === 'Servicios Hoteleros – Sector Hotelería y Turismo')>
                                            Servicios Hoteleros – Sector Hotelería y Turismo
                                        </option>
                                        <option value="Telecomunicaciones – Sector Electricidad"
                                            @selected($espSel === 'Telecomunicaciones – Sector Electricidad')>
                                            Telecomunicaciones – Sector Electricidad
                                        </option>
                                        <option value="Administración y Comercio – Contabilidad"
                                            @selected($espSel === 'Administración y Comercio – Contabilidad')>
                                            Administración y Comercio – Contabilidad
                                        </option>
                                        <option value="Administración y Comercio – Administración"
                                            @selected($espSel === 'Administración y Comercio – Administración')>
                                            Administración y Comercio – Administración
                                        </option>
                                        <option value="Administración y Comercio – Ventas" @selected($espSel === 'Administración y Comercio – Ventas')>
                                            Administración y Comercio – Ventas
                                        </option>
                                        <option value="Administración y Comercio – Secretariado"
                                            @selected($espSel === 'Administración y Comercio – Secretariado')>
                                            Administración y Comercio – Secretariado
                                        </option>
                                    </optgroup>
                                    <optgroup label="FORMACIÓN EN OFICIOS (Educación Básica)">
                                        @php $espSel = old('especialidad_tp', $profile->especialidad_tp ?? ''); @endphp
                                        <option value="Asistente de Adulto Mayor Autovalente"
                                            @selected($espSel === 'Asistente de Adulto Mayor Autovalente')>Asistente de Adulto Mayor Autovalente</option>
                                        <option value="Ayudante de Cocina" @selected($espSel === 'Ayudante de Cocina')>Ayudante de Cocina
                                        </option>
                                        <option value="Ayudante de Mecánico" @selected($espSel === 'Ayudante de Mecánico')>Ayudante de
                                            Mecánico</option>
                                        <option value="Ayudante de Panadería" @selected($espSel === 'Ayudante de Panadería')>Ayudante de
                                            Panadería</option>
                                        <option value="Ayudante de Repostería y Pastelería" @selected($espSel === 'Ayudante de Repostería y Pastelería')>
                                            Ayudante de Repostería y Pastelería</option>
                                        <option value="Barman" @selected($espSel === 'Barman')>Barman</option>
                                        <option value="Garzón" @selected($espSel === 'Garzón')>Garzón</option>
                                        <option value="Instalador/a Eléctrico en Baja Tensión hasta 200 Volt"
                                            @selected($espSel === 'Instalador/a Eléctrico en Baja Tensión hasta 200 Volt')>Instalador/a Eléctrico en Baja Tensión hasta 200
                                            Volt</option>
                                        <option value="Instalador/a Sanitario" @selected($espSel === 'Instalador/a Sanitario')>Instalador/a
                                            Sanitario</option>
                                        <option value="Jardinero" @selected($espSel === 'Jardinero')>Jardinero</option>
                                        <option value="Motosierrista Forestal" @selected($espSel === 'Motosierrista Forestal')>Motosierrista
                                            Forestal</option>
                                        <option value="Mucama" @selected($espSel === 'Mucama')>Mucama</option>
                                        <option value="Mueblista" @selected($espSel === 'Mueblista')>Mueblista</option>
                                        <option value="Soldador/a al Arco" @selected($espSel === 'Soldador/a al Arco')>Soldador/a al Arco
                                        </option>
                                        <option value="Vacunador/a de Peces de Cultivo" @selected($espSel === 'Vacunador/a de Peces de Cultivo')>
                                            Vacunador/a de Peces de Cultivo</option>
                                    </optgroup>
                                </select>
                                @error('especialidad_tp')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                                <div class="form-text">Visible y obligatoria solo para Docente Técnico Profesional.</div>
                            </div>


                            {{-- Nivel de estudios --}}
                            <div class="mb-3 d-none" id="wrap-nivel_estudios">
                                <label class="form-label" for="nivel_estudios">Nivel de estudios</label>
                                <select class="form-select @error('nivel_estudios') is-invalid @enderror"
                                    id="nivel_estudios" name="nivel_estudios">
                                    <option value="">Seleccione…</option>
                                </select>
                                @error('nivel_estudios')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Institución del título --}}
                            <div class="mb-3 d-none" id="wrap-institucion_titulo">
                                <label class="form-label" for="institucion_titulo">Institución</label>
                                <input type="text"
                                    class="form-control @error('institucion_titulo') is-invalid @enderror"
                                    id="institucion_titulo" name="institucion_titulo"
                                    value="{{ old('institucion_titulo', $profile->institucion_titulo ?? '') }}">
                                @error('institucion_titulo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                                <div class="form-text">Obligatoria si el nivel es TNS o Universitaria (salvo Religión).
                                </div>
                            </div>

                            {{-- Fecha de titulación (visible si nivel = TNS o Universitaria) --}}
                            @php
                                $fechaTit = old('fecha_titulacion');
                                if (is_null($fechaTit)) {
                                    $fechaTit = optional($profile->fecha_titulacion)->format('Y-m-d'); // si está casteado a date
                                    // o si NO está casteado:
                                    // $fechaTit = $profile->fecha_titulacion ? \Carbon\Carbon::parse($profile->fecha_titulacion)->format('Y-m-d') : '';
                                }
                            @endphp
                            <div class="mb-3 d-none" id="wrap-fecha_titulacion">
                                <label class="form-label" for="fecha_titulacion">Fecha de titulación</label>
                                <input type="date" id="fecha_titulacion" name="fecha_titulacion"
                                    value="{{ $fechaTit }}"
                                    class="form-control @error('fecha_titulacion') is-invalid @enderror">
                                @error('fecha_titulacion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Semestres / Horas --}}
                            <div class="row g-3">
                                <div class="col-md-6 d-none" id="wrap-semestres">
                                    <label class="form-label" for="semestres">Semestres cursados</label>
                                    <input type="number" min="1" max="40"
                                        class="form-control @error('semestres') is-invalid @enderror" id="semestres"
                                        name="semestres" value="{{ old('semestres', $profile->semestres ?? '') }}">
                                    @error('semestres')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 d-none" id="wrap-horas_totales">
                                    <label class="form-label" for="horas_totales">Horas totales</label>
                                    <input type="number" min="1" max="20000"
                                        class="form-control @error('horas_totales') is-invalid @enderror"
                                        id="horas_totales" name="horas_totales"
                                        value="{{ old('horas_totales', $profile->horas_totales ?? '') }}">
                                    @error('horas_totales')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- Años de experiencia --}}
                            <div class="mb-3 d-none" id="wrap-anios_experiencia">
                                <label class="form-label" for="anios_experiencia">Años de experiencia</label>
                                <input type="number" min="0" max="60"
                                    class="form-control @error('anios_experiencia') is-invalid @enderror"
                                    id="anios_experiencia" name="anios_experiencia"
                                    value="{{ old('anios_experiencia', $profile->anios_experiencia ?? '') }}">
                                @error('anios_experiencia')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- (Asistente) Cargo/Función --}}
                            <div hidden class="mb-3 d-none" id="wrap-cargos_funcion">
                                <label class="form-label" for="cargos_funcion">Cargo / función</label>

                                @php
                                    $cargoSel = old('cargos_funcion', $profile->cargos_funcion ?? '');
                                    $cargosAsistente = [
                                        'Asistente de aula',
                                        'Inspector(a) Educacional o Ex-Paradocente',
                                        'Encargado(a) de Convivencia Escolar',
                                        'Encargado(a) CRA/Biblioteca',
                                        'Asistente administrativo(a)',
                                        'Auxiliar de servicios',
                                        'Celador',
                                        'Técnico en párvulos',
                                        'Técnico en educación especial',
                                        'Informático(a) / Soporte TIC',
                                        'Psicólogo(a)',
                                        'Trabajador(a) Social',
                                        'Psicopedagogo(a)',
                                        'Fonoaudiólogo(a)',
                                        'Kinesiólogo(a)',
                                        'Encargado(a) de laboratorio de ciencias',
                                        'Conductor',
                                        'Técnico en Efermería',
                                        'Prevencionista de Riesgos',
                                    ];
                                @endphp

                                <select class="form-select @error('cargos_funcion') is-invalid @enderror"
                                    id="cargos_funcion" name="cargos_funcion">
                                    <option value="">Seleccione…</option>
                                    @foreach ($cargosAsistente as $c)
                                        <option value="{{ $c }}" @selected($cargoSel === $c)>
                                            {{ $c }}</option>
                                    @endforeach
                                </select>

                                @error('cargos_funcion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>


                        </div>
                    </div>

                    {{-- ======= Lugares de desempeño ======= --}}
                    <div class="card mb-4" id="card-lugares">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Lugares de desempeño</h5>
                            <p class="text-muted mb-3">Selecciona al menos una comuna donde estás disponible para
                                desempeñarte.</p>

                            @php
                                // Primero prioriza 'old', si no hay, carga de BD con la relación
                                $sel = old('lugares');

                                if (is_null($sel)) {
                                    $sel = $user->communes()->pluck('communes.id')->all();
                                }

                                $sel = array_map('intval', (array) $sel);
                                $permitidas = config('chile.comunas_postulacion_permitidas', ['Coronel', 'Lota', 'San Pedro de la Paz', 'Santa Juana', 'Isla Santa María']);
                                $permitidasIds = collect($allCommunes)
                                    ->filter(fn($c) => in_array($c->name, $permitidas, true))
                                    ->pluck('id')
                                    ->map(fn($id) => (int) $id)
                                    ->all();
                                $visibleCommunes = collect($allCommunes)
                                    ->filter(fn($c) => in_array((int) $c->id, $permitidasIds, true) || in_array((int) $c->id, $sel, true))
                                    ->values();
                            @endphp


                            <div class="row row-cols-1 row-cols-md-2 g-2" id="anchor-lugares">
                                @foreach ($visibleCommunes as $c)
                                    @php $legacySelection = !in_array((int) $c->id, $permitidasIds, true); @endphp
                                    <div class="col">
                                        <div class="form-check">
                                            <input class="form-check-input @error('lugares') is-invalid @enderror"
                                                type="checkbox" name="lugares[]" id="com_{{ $c->id }}"
                                                value="{{ $c->id }}" @checked(in_array($c->id, $sel, true))>
                                            <label class="form-check-label" for="com_{{ $c->id }}">
                                                {{ $c->name }}
                                                @if ($legacySelection)
                                                    <small class="text-muted">(guardada previamente)</small>
                                                @endif
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @error('lugares.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @else
                                <div id="lugaresFeedback" class="invalid-feedback d-none">
                                    {{ $requiredMessage }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    {{-- ======= Datos previsionales y bancarios ======= --}}
                    <div class="card mb-4" id="card-previsional-bancario">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Datos previsionales y bancarios</h5>

                            {{-- AFP --}}
                            <div class="mb-3" id="anchor-prevision_afp">
                                <label class="form-label required" for="prevision_afp">Institución de Previsión
                                    (AFP)</label>
                                @php $afpSel = old('prevision_afp', $profile->prevision_afp ?? ''); @endphp
                                <select class="form-select @error('prevision_afp') is-invalid @enderror"
                                    id="prevision_afp" name="prevision_afp" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($afps as $x)
                                        <option value="{{ $x }}" @selected($afpSel === $x)>
                                            {{ $x }}</option>
                                    @endforeach
                                </select>
                                @error('prevision_afp')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Salud --}}
                            <div class="mb-3" id="anchor-salud_institucion">
                                <label class="form-label required" for="salud_institucion">Institución de Salud</label>
                                @php $salSel = old('salud_institucion', $profile->salud_institucion ?? ''); @endphp
                                <select class="form-select @error('salud_institucion') is-invalid @enderror"
                                    id="salud_institucion" name="salud_institucion" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($salud as $x)
                                        <option value="{{ $x }}" @selected($salSel === $x)>
                                            {{ $x }}</option>
                                    @endforeach
                                </select>
                                @error('salud_institucion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Banco --}}
                            <div class="mb-3" id="anchor-banco">
                                <label class="form-label required" for="banco">Banco</label>
                                @php $banSel = old('banco', $profile->banco ?? ''); @endphp
                                <select class="form-select @error('banco') is-invalid @enderror" id="banco"
                                    name="banco" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($bancos as $x)
                                        <option value="{{ $x }}" @selected($banSel === $x)>
                                            {{ $x }}</option>
                                    @endforeach
                                </select>
                                @error('banco')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Tipo Cuenta --}}
                            <div class="mb-3" id="anchor-tipo_cuenta">
                                <label class="form-label required" for="tipo_cuenta">Tipo de cuenta</label>
                                @php $tipoSel = old('tipo_cuenta', $profile->tipo_cuenta ?? ''); @endphp
                                <select class="form-select @error('tipo_cuenta') is-invalid @enderror" id="tipo_cuenta"
                                    name="tipo_cuenta" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($tiposCuenta as $x)
                                        <option value="{{ $x }}" @selected($tipoSel === $x)>
                                            {{ $x }}</option>
                                    @endforeach
                                </select>
                                @error('tipo_cuenta')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>

                            {{-- Nº Cuenta --}}
                            <div class="mb-3" id="anchor-numero_cuenta">
                                <label class="form-label required" for="numero_cuenta">Nº de cuenta</label>
                                <input type="text" class="form-control @error('numero_cuenta') is-invalid @enderror"
                                    id="numero_cuenta" name="numero_cuenta"
                                    value="{{ old('numero_cuenta', $profile->numero_cuenta ?? '') }}" required>
                                @error('numero_cuenta')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="invalid-feedback">{{ $requiredMessage }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>


                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-back">Volver</a>
                    </div>
                </div>

                {{-- ======= Checklist lateral ======= --}}
                <div class="col-lg-4">
                    <div class="card profile-checklist">
                        <div class="card-body">
                            <h5 class="card-title">Checklist de Perfil</h5>

                            {{-- Barra de progreso dinámica --}}
                            <div class="progress mb-2" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                                <div id="profileProgressBar" class="progress-bar" style="width:0%">0%</div>
                            </div>

                            {{-- Lista dinámica de campos faltantes --}}
                            <ul class="list-unstyled small mb-0" id="checklistMissing"></ul>

                            {{-- Mensaje de completo --}}
                            <p id="checklistDone" class="small text-success mb-0 d-none">¡Tu perfil está completo!</p>
                        </div>
                    </div>
                </div>
            </div>
    </div>
    </form>
    </div>

    @push('scripts')
        <script>
            (function() {

                const form = document.getElementById('profileForm');

                // ----------------------------
                // 0) Flag-icons: preview para nacionalidad (ISO o emoji)
                // ----------------------------
                const selNac = document.getElementById('nacionalidad');
                const nacPreview = document.getElementById('nacPreview');

                function hasFlagIconsSupport() {
                    // Probamos si la clase .fi aplica un background-image (flag-icons)
                    const test = document.createElement('span');
                    test.className = 'fi fi-cl';
                    test.style.position = 'absolute';
                    test.style.visibility = 'hidden';
                    document.body.appendChild(test);
                    const bg = window.getComputedStyle(test).backgroundImage;
                    document.body.removeChild(test);
                    return bg && bg !== 'none';
                }

                const flagIconsOk = hasFlagIconsSupport();

                function updateNacPreview() {
                    validateLugares();
                    if (!selNac || !nacPreview) return;
                    const opt = selNac.options[selNac.selectedIndex];
                    if (!opt) return;

                    const iso = (opt.getAttribute('data-iso') || 'cl').toLowerCase();
                    const abbr = opt.getAttribute('data-abbr') || 'CL';
                    const emoji = opt.getAttribute('data-emoji') || '🇨🇱';
                    const name = opt.textContent.replace(/^[^\s]+\s+/, ''); // quita el emoji inicial para el title aprox.

                    nacPreview.innerHTML = '';
                    const icon = document.createElement('span');
                    icon.style.marginRight = '.25rem';

                    if (flagIconsOk) {
                        icon.className = 'fi fi-' + iso;
                        // ocultamos el emoji fallback si hay flag-icons
                        const em = document.createElement('span');
                        em.className = 'd-none';
                        em.textContent = emoji;
                        nacPreview.appendChild(icon);
                        nacPreview.appendChild(em);
                    } else {
                        icon.textContent = emoji + ' ';
                        nacPreview.appendChild(icon);
                    }

                    nacPreview.title = abbr + ' — ' + name.trim();
                }

                selNac?.addEventListener('change', updateNacPreview);

                document.addEventListener('DOMContentLoaded', function() {
                    const sel = document.getElementById('nacionalidad');
                    const abbr = document.querySelector('.nac-abbr');
                    const name = document.querySelector('.nac-name');
                    const emojiFallback = document.querySelector('.nac-emoji-fallback');
                    const fiSpan = document.querySelector('#nacPreview .fi');

                    function updatePreview(opt) {
                        if (!opt) return;
                        const iso = opt.getAttribute('data-iso') || 'cl';
                        const abbrT = opt.getAttribute('data-abbr') || 'CHL';
                        const emoji = opt.getAttribute('data-emoji') || '🇨🇱';
                        const nameT = opt.getAttribute('data-name') || 'Chilena';

                        // actualizar flag-icon (clase fi fi-xx)
                        if (fiSpan) {
                            // quita clases previas fi fi-??
                            fiSpan.className = 'fi fi-' + iso;
                        }
                        if (abbr) abbr.textContent = abbrT;
                        if (name) name.textContent = nameT;
                        if (emojiFallback) emojiFallback.textContent = emoji;
                    }

                    updatePreview(sel.options[sel.selectedIndex]);
                    sel.addEventListener('change', () => updatePreview(sel.options[sel.selectedIndex]));
                });
                // --------------------------------

                // 1) Validación en vivo (BS5)
                const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
                requiredFields.forEach(el => {
                    el.addEventListener('blur', () => {
                        if (!el.checkValidity() || !String(el.value).trim()) {
                            el.classList.add('is-invalid');
                        } else {
                            el.classList.remove('is-invalid');
                        }
                    });
                    el.addEventListener('input', () => {
                        if (el.checkValidity() && String(el.value).trim()) {
                            el.classList.remove('is-invalid');
                        }
                    });
                });

                // Grupo "lugares" (checkboxes): al menos uno
                const lugaresChecks = form.querySelectorAll('input[name="lugares[]"]');
                const lugaresFeedback = document.getElementById('lugaresFeedback');

                function validateLugares() {
                    const anyChecked = Array.from(lugaresChecks).some(c => c.checked);
                    if (!anyChecked) {
                        lugaresChecks[0]?.classList.add('is-invalid');
                        lugaresFeedback?.classList.remove('d-none'); // mostrar
                        return false;
                    } else {
                        lugaresChecks[0]?.classList.remove('is-invalid');
                        lugaresFeedback?.classList.add('d-none'); // ocultar
                        return true;
                    }
                }
                lugaresChecks.forEach(chk => chk.addEventListener('change', validateLugares));

                // 2) Región → Comuna (dependiente)
                const selectRegion = document.getElementById('region_code');
                const selectComuna = document.getElementById('comuna_id');
                const baseComunas = @json($communesByRegion);
                const oldComuna = "{{ old('comuna_id', $profile->comuna_id ?? '') }}";

                function rebuildComunas() {
                    if (!selectRegion || !selectComuna) return;
                    const code = selectRegion.value;
                    const comunas = baseComunas[code] || [];
                    const current = selectComuna.value;

                    selectComuna.innerHTML = '<option value="">Seleccione…</option>';
                    comunas.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        if (String(oldComuna) === String(c.id) || String(current) === String(c.id)) {
                            opt.selected = true;
                        }
                        selectComuna.appendChild(opt);
                    });
                    selectComuna.classList.remove('is-invalid');
                }
                selectRegion?.addEventListener('change', rebuildComunas);

                // 3) Lógica académica dinámica
                const estamento = document.getElementById('estamento') ?? document.querySelector('[name="estamento"]');

                const area =
                    document.getElementById('area_desempeno') ??
                    document.getElementById('area_desempeno_id') ??
                    document.querySelector('[name="area_desempeno_id"]');

                // Si esta vista no trae esos campos, no ejecutes la lógica (evita el crash)
                if (!estamento || !area) {
                    return;
                }


                const wrapArea = document.getElementById('wrap-area_desempeno');
                const wrapMencion = document.getElementById('wrap-mencion');
                const wrapEspTP = document.getElementById('wrap-especialidad_tp');
                const wrapNivel = document.getElementById('wrap-nivel_estudios');
                const wrapInst = document.getElementById('wrap-institucion_titulo');
                const wrapFechaTit = document.getElementById('wrap-fecha_titulacion');
                const inputFechaTit = document.getElementById('fecha_titulacion');
                const wrapSem = document.getElementById('wrap-semestres');
                const wrapHoras = document.getElementById('wrap-horas_totales');
                const wrapExp = document.getElementById('wrap-anios_experiencia');
                const wrapCargo = document.getElementById('wrap-cargos_funcion');

                const inputMencion = document.getElementById('mencion');
                const inputEspTP = document.getElementById('especialidad_tp');
                const selectNivel = document.getElementById('nivel_estudios');
                const inputInst = document.getElementById('institucion_titulo');
                const inputSem = document.getElementById('semestres');
                const inputHoras = document.getElementById('horas_totales');
                const inputExp = document.getElementById('anios_experiencia');
                const inputCargo = document.getElementById('cargos_funcion');

                const nivelesTodos = ['Enseñanza Media', 'Enseñanza Media Laboral', 'Enseñanza Media TP',
                    'Técnico Nivel Superior', 'Universitaria'
                ];
                const nivelesTP = ['Enseñanza Media', 'Enseñanza Media Laboral', 'Enseñanza Media TP',
                    'Técnico Nivel Superior', 'Universitaria'
                ];
                const nivelesUniv = ['Universitaria'];

                function setRequired(el, wrap, required) {
                    if (!el) return;

                    if (required) {
                        el.removeAttribute('disabled'); // ✅ se envía
                        el.setAttribute('required', 'required');
                        wrap?.classList.remove('d-none');
                    } else {
                        el.removeAttribute('required');
                        el.classList.remove('is-invalid');
                        el.value = ''; // ✅ limpia
                        el.setAttribute('disabled', 'disabled'); // ✅ NO se envía
                        wrap?.classList.add('d-none');
                    }
                }


                function showOnly(wrap, show) {
                    if (!wrap) return;
                    wrap.classList.toggle('d-none', !show);
                }

                function populateOptions(select, options, selectedValue) {
                    if (!select) return;
                    const current = select.value || selectedValue || '';
                    select.innerHTML = '<option value="">Seleccione…</option>';
                    options.forEach(op => {
                        const o = document.createElement('option');
                        o.value = op;
                        o.textContent = op;
                        if (String(current) === String(op)) {
                            o.selected = true;
                        }
                        select.appendChild(o);
                    });
                }

                // ==============================
                // Área desempeño: Select2 AJAX (BD) por estamento
                // ==============================
                const areasAjaxUrl = @json(route('postulant.profile.ajax.areas-desempeno'));
                const estamentoEl = document.getElementById('estamento');
                const areaEl = document.getElementById('area_desempeno_id');

                if (!estamentoEl || !areaEl) return;

                function setEmpty(msg) {
                    areaEl.innerHTML = `<option value="">${msg}</option>`;
                    areaEl.disabled = true;
                }

                function setLoading() {
                    areaEl.innerHTML = `<option value="">Cargando áreas…</option>`;
                    areaEl.disabled = true;
                }

                async function loadAreas({
                    resetSelected = false
                } = {}) {
                    const estamento = estamentoEl.value || '';

                    // Si aún no hay estamento, reintenta un poquito (por orden de scripts)
                    if (!estamento) {
                        setEmpty('Seleccione un estamento…');
                        return false;
                    }

                    if (resetSelected) areaEl.dataset.selected = '';

                    const selected = areaEl.dataset.selected || ''; // 👈 se lee aquí (no afuera)
                    setLoading();

                    const params = new URLSearchParams({
                        estamento
                    });
                    const res = await fetch(`${areasAjaxUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!res.ok) {
                        console.error('Error cargando áreas', res.status);
                        setEmpty('Error al cargar áreas');
                        return true;
                    }

                    const data = await res.json();
                    const items = data.results || [];

                    areaEl.disabled = false;
                    areaEl.innerHTML = `<option value="">Seleccione un área…</option>`;

                    for (const it of items) {
                        const opt = document.createElement('option');
                        opt.value = String(it.id);
                        opt.textContent = it.text;
                        areaEl.appendChild(opt);
                    }

                    // 👇 Aplicar selección guardada (si existe)
                    if (selected) {
                        areaEl.value = String(selected);

                    }
                    // ✅ Forzar recálculo: updateAcademicosUI depende del texto del área (TP / Religión)
                    areaEl.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));

                    // Si el selected no venía en la lista (por filtro/limit), te avisa
                    if (selected && areaEl.value !== String(selected)) {
                        console.warn('Área guardada no está en la lista del estamento actual:', selected);
                    }

                    if (!items.length) {
                        setEmpty('No hay áreas para este estamento');
                    }
                    updateAcademicosUI();
                    return true;
                }

                // Cargar al inicio (y reintentar si el estamento se setea después)
                document.addEventListener('DOMContentLoaded', async () => {
                    let tries = 0;
                    while (tries++ < 20) {
                        const ok = await loadAreas();
                        if (ok) break;
                        await new Promise(r => setTimeout(r, 50));
                    }
                });

                // Cambia estamento => recarga lista y limpia selección
                estamentoEl.addEventListener('change', () => {
                    areaEl.value = '';
                    loadAreas({
                        resetSelected: true
                    });
                });


                function updateAcademicosUI() {
                    validateLugares?.();

                    const est = estamento?.value ?? '';
                    const ar = (function() {
                        // value es id numérico; necesitamos el texto para tus reglas (Religión / TP)
                        const el = area;
                        if (!el) return '';
                        const opt = el.options && el.selectedIndex >= 0 ? el.options[el.selectedIndex] : null;
                        return (opt?.textContent || '').trim();
                    })();


                    const esDocenteReligion = (est === 'docente' && (ar === 'Religión Católica' || ar ===
                        'Religión Evangélica'));
                    const esTP = (est === 'docente' && ar === 'Docente Técnico Profesional');

                    // Reset base (ocultar y quitar required)
                    [wrapArea, wrapMencion, wrapEspTP, wrapNivel, wrapInst, wrapSem, wrapHoras, wrapExp, wrapCargo,
                        wrapFechaTit
                    ]
                    .forEach(w => w?.classList.add('d-none'));

                    [inputMencion, inputEspTP, selectNivel, inputInst, inputSem, inputHoras, inputExp, inputCargo,
                        inputFechaTit
                    ]
                    .forEach(el => el?.removeAttribute('required'));

                    // === SIEMPRE visibles/obligatorios (nuevas reglas) ===
                    setRequired(inputExp, wrapExp, true); // Años de experiencia

                    // Nivel de estudios siempre visible + obligatorio
                    let niveles = nivelesTodos; // default
                    if (est === 'docente') {
                        if (esDocenteReligion) niveles = nivelesTodos;
                        else if (esTP) niveles = nivelesTP;
                        else niveles = nivelesUniv;
                    } else {
                        niveles = nivelesTodos;
                    }

                    const nivelPrevio = "{{ old('nivel_estudios', $profile->nivel_estudios ?? '') }}";
                    populateOptions(selectNivel, niveles, nivelPrevio);
                    setRequired(selectNivel, wrapNivel, true);

                    const nivelActual = selectNivel.value || nivelPrevio;
                    const esUniv = (nivelActual === 'Universitaria');

                    // Semestres/Horas: SOLO si Universitaria
                    setRequired(inputSem, wrapSem, esUniv);
                    setRequired(inputHoras, wrapHoras, esUniv);

                    // Institución: visible SOLO si TNS o Universitaria (no obligatoria aquí)
                    const instVisible = (nivelActual === 'Técnico Nivel Superior' || esUniv);
                    if (instVisible) {
                        wrapInst.classList.remove('d-none');
                    } else {
                        wrapInst.classList.add('d-none');
                        inputInst.removeAttribute('required');
                        inputInst.classList.remove('is-invalid');
                    }

                    // Fecha de titulación: requerida si TNS o Universitaria
                    const reqFechaTit = (nivelActual === 'Técnico Nivel Superior' ||
                        nivelActual ===
                        'Universitaria');
                    setRequired(inputFechaTit, wrapFechaTit, reqFechaTit);

                    // === Reglas por estamento ===
                    if (est === 'asistente') {

                        // ✅ YA NO USAR cargo/función
                        wrapCargo?.classList.add('d-none');
                        inputCargo?.removeAttribute('required');
                        inputCargo?.classList.remove('is-invalid');

                        // ✅ USAR área de desempeño (cargada desde BD por estamento)
                        setRequired(area, wrapArea, true);

                        return; // nada más para asistente
                    }

                    if (est === 'docente') {
                        setRequired(area, wrapArea, true);

                        // Mención: oculta en TP; visible en resto; requerida SOLO si Educadora/Educador(a) Diferencial
                        const ast = document.getElementById('mencionAsterisk');
                        if (esTP) {
                            wrapMencion.classList.add('d-none');
                            inputMencion.removeAttribute('required');
                            inputMencion.classList.remove('is-invalid');
                            ast && ast.classList.add('d-none');
                        } else {
                            wrapMencion.classList.remove('d-none');
                            const reqMencion = (ar === 'Educadora Diferencial' || ar === 'Educador(a) Diferencial');
                            if (reqMencion) {
                                inputMencion.setAttribute('required', 'required');
                                ast && ast.classList.remove('d-none');
                            } else {
                                inputMencion.removeAttribute('required');
                                inputMencion.classList.remove('is-invalid');
                                ast && ast.classList.add('d-none');
                            }
                        }

                        // Especialidad TP: visible + obligatoria SOLO en TP
                        setRequired(inputEspTP, wrapEspTP, esTP);
                    }
                }



                // --- Buscador para Mención (sin dependencias) ---
                (function initMencionPicker() {
                    const sel = document.getElementById('mencion');
                    const input = document.getElementById('mencion_search');
                    if (!sel || !input) return;

                    const initialSelected = @json($mencionSel ?? '');
                    let lastQ = '';
                    let pending = 0;
                    const endpoint = "{{ route('api.menciones.search') }}";

                    function rebuild(groups, selectedValue) {
                        const hasSelected = !!selectedValue && String(selectedValue).trim() !== '';
                        const selected = hasSelected ? String(selectedValue) : String(sel.value || '');
                        let matched = false;

                        sel.innerHTML = '<option value="">Seleccione…</option>';

                        groups.forEach(g => {
                            const og = document.createElement('optgroup');
                            og.label = g.subsector || 'Sin subsector';
                            g.items.forEach(it => {
                                const opt = document.createElement('option');
                                opt.value = it.value; // guardaremos el texto completo en el perfil
                                opt.textContent = it.label;
                                if (String(it.value) === selected) {
                                    opt.selected = true;
                                    matched = true;
                                }
                                og.appendChild(opt);
                            });
                            sel.appendChild(og);
                        });

                        if (hasSelected && !matched) {
                            const opt = document.createElement('option');
                            opt.value = selected;
                            opt.textContent = selected;
                            opt.selected = true;
                            sel.insertBefore(opt, sel.children[1] ?? null);
                        }
                    }

                    async function search(q, selectedOnInit = false) {
                        const query = (q || '').trim();
                        lastQ = query;
                        pending++;
                        try {
                            const url = endpoint + (query ? ('?q=' + encodeURIComponent(query)) : '');
                            const resp = await fetch(url, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });
                            if (!resp.ok) throw new Error('HTTP ' + resp.status);
                            const data = await resp.json();
                            // evita condición de carrera
                            if (query !== lastQ) return;
                            rebuild(data, selectedOnInit ? initialSelected : undefined);
                        } catch (e) {
                            console.error('Búsqueda de menciones falló:', e);
                        } finally {
                            pending--;
                        }
                    }

                    // Debounce del input
                    let t;
                    input.addEventListener('input', function() {
                        clearTimeout(t);
                        const val = this.value;
                        t = setTimeout(() => search(val), 200);
                    });

                    // Carga inicial (sin filtro) para mostrar grupos
                    search('', true);
                })();

                // --- Buscador para Especialidad TP (sin dependencias) ---
                const espSearch = document.getElementById('especialidad_tp_search');
                (function initEspecialidadSearch() {
                    const sel = inputEspTP; // reutilizamos tu select existente
                    if (!sel || !espSearch) return;

                    // Capturar placeholder (ej. "Seleccione…") y estructura original (optgroups + options)
                    const placeholderHTML = sel.querySelector('option[value=""]')?.outerHTML ||
                        '<option value="">Seleccione…</option>';
                    const original = []; // [{ label: string|null, options: [{value,text}] }]

                    // Tomar snapshot de grupos y opciones tal cual están renderizadas
                    Array.from(sel.children).forEach(node => {
                        if (node.tagName === 'OPTGROUP') {
                            const group = {
                                label: node.label || '',
                                options: []
                            };
                            Array.from(node.children).forEach(opt => {
                                if (opt.tagName === 'OPTION') {
                                    group.options.push({
                                        value: opt.value,
                                        text: opt.textContent
                                    });
                                }
                            });
                            original.push(group);
                        } else if (node.tagName === 'OPTION') {
                            // opciones sueltas (fuera de optgroup)
                            let root = original.find(g => g.label === '');
                            if (!root) {
                                root = {
                                    label: '',
                                    options: []
                                };
                                original.unshift(root);
                            }
                            root.options.push({
                                value: node.value,
                                text: node.textContent
                            });
                        }
                    });

                    // Normalizador: minúsculas y sin tildes
                    const norm = s => (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

                    function rebuild(query) {
                        const q = norm(query);
                        const selected = sel.value; // preservar selección actual
                        sel.innerHTML = placeholderHTML;

                        original.forEach(group => {
                            const filtered = group.options.filter(o => {
                                // permite búsqueda por múltiples palabras en cualquier orden
                                const haystack = norm(o.text);
                                return q.split(/\s+/).every(tok => haystack.includes(tok));
                            });

                            if (filtered.length === 0) return;

                            let container = sel;
                            if (group.label) {
                                const og = document.createElement('optgroup');
                                og.label = group.label;
                                sel.appendChild(og);
                                container = og;
                            }

                            filtered.forEach(o => {
                                const opt = document.createElement('option');
                                opt.value = o.value;
                                opt.textContent = o.text;
                                if (o.value === selected) opt.selected = true;
                                container.appendChild(opt);
                            });
                        });
                    }

                    // Eventos
                    espSearch.addEventListener('input', e => rebuild(e.target.value));

                    // Inicializar (sin filtro) para asegurar snapshot consistente
                    rebuild('');
                })();

                const nivelSelectEl = document.getElementById('nivel_estudios');
                if (nivelSelectEl) {
                    nivelSelectEl.addEventListener('change', () => {
                        const est = estamento.value || '';
                        const ar = getSelectedAreaText();
                        const esDocenteReligion = (est === 'docente' && (ar === 'Religión Católica' || ar ===
                            'Religión Evangélica'));
                        const nivelActual = nivelSelectEl.value;

                        // Institución obligatoria si TNS o Universitaria (excepto religión en docente)
                        const instOblig = !esDocenteReligion && (nivelActual === 'Técnico Nivel Superior' ||
                            nivelActual === 'Universitaria');
                        setRequired(
                            document.getElementById('institucion_titulo'),
                            document.getElementById('wrap-institucion_titulo'),
                            instOblig
                        );

                        // Semestres/Horas: sólo Universitaria (excepto religión en docente)
                        const reqSemHoras = !esDocenteReligion && (nivelActual === 'Universitaria');
                        setRequired(document.getElementById('semestres'), document.getElementById(
                                'wrap-semestres'),
                            reqSemHoras);
                        setRequired(document.getElementById('horas_totales'), document.getElementById(
                            'wrap-horas_totales'), reqSemHoras);

                        // Fecha de titulación: requerida si TNS o Universitaria
                        const reqFechaTit = (nivelActual === 'Técnico Nivel Superior' ||
                            nivelActual === 'Universitaria');
                        setRequired(document.getElementById('fecha_titulacion'), document.getElementById(
                            'wrap-fecha_titulacion'), reqFechaTit);
                    });

                    // (opcional) recalcular layout completo cuando cambie el nivel
                    nivelSelectEl.addEventListener('change', updateAcademicosUI);
                }

                estamento?.addEventListener('change', () => {
                    if (window.jQuery) $('#area_desempeno_id').val(null).trigger('change');
                    updateAcademicosUI();
                });

                area?.addEventListener('change', updateAcademicosUI);


                // 4) Preview de foto (opcional)
                const fotoInput = document.getElementById('foto');
                const fotoPreview = document.getElementById('fotoPreview');
                if (fotoInput && fotoPreview) {
                    fotoInput.addEventListener('change', () => {
                        const f = fotoInput.files && fotoInput.files[0];
                        if (!f) return;
                        const reader = new FileReader();
                        reader.onload = e => {
                            fotoPreview.src = e.target.result;
                            fotoPreview.classList.remove('d-none');
                        };
                        reader.readAsDataURL(f);
                    });
                }
                // === CHECKLIST DINÁMICO ===
                // Helpers mínimos
                function isVisible(el) {
                    if (!el) return false;
                    if (el.closest('.d-none')) return false;
                    const s = window.getComputedStyle(el);
                    return s.display !== 'none' && s.visibility !== 'hidden';
                }

                function findAnchorContainer(el) {
                    let n = el;
                    while (n && n !== document) {
                        if (n.id && (n.id.startsWith('anchor-') || n.id.startsWith('wrap-'))) return n;
                        n = n.parentElement;
                    }
                    return null;
                }

                function fieldLabelFor(el) {
                    if (el.id) {
                        const lab = form.querySelector(`label[for="${CSS.escape(el.id)}"]`);
                        if (lab) return lab.textContent.trim();
                    }
                    const lab2 = el.closest('.mb-3, .col-md-6, .form-group')?.querySelector('label');
                    if (lab2) return lab2.textContent.trim();
                    return (el.name || el.id || 'Campo');
                }

                function scrollToAnchor(anchor) {
                    const el = document.querySelector(anchor);
                    if (!el) return;
                    el.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    const input = el.querySelector('input,select,textarea');
                    if (input) {
                        input.classList.add('is-invalid');
                        setTimeout(() => input.classList.remove('is-invalid'), 1500);
                        // foco (sin romper selects dependientes)
                        try {
                            input.focus({
                                preventScroll: true
                            });
                        } catch (e) {}
                    }
                }

                function updateChecklist() {
                    const progressBar = document.getElementById('profileProgressBar');
                    const ulMissing = document.getElementById('checklistMissing');
                    const doneMsg = document.getElementById('checklistDone');
                    if (!progressBar || !ulMissing) return;

                    // 1) Campos requeridos visibles actualmente
                    const reqEls = Array.from(form.querySelectorAll(
                            'input[required], select[required], textarea[required]'))
                        .filter(el => !el.disabled && isVisible(el));

                    // 2) Validación por elemento
                    const missing = [];
                    let completed = 0;
                    reqEls.forEach(el => {
                        let ok = false;
                        const type = (el.getAttribute('type') || '').toLowerCase();

                        if (type === 'checkbox' || type === 'radio') {
                            const group = form.querySelectorAll(`input[name="${el.name}"]`);
                            ok = Array.from(group).some(i => i.checked);
                        } else {
                            ok = String(el.value).trim().length > 0;
                        }

                        if (ok) {
                            completed++;
                        } else {
                            const wrap = findAnchorContainer(el);
                            const anchor = wrap ? `#${wrap.id}` : '#';
                            const label = fieldLabelFor(el);
                            // Evita duplicados por campos dentro del mismo contenedor
                            if (!missing.some(m => m.anchor === anchor && m.label === label)) {
                                missing.push({
                                    anchor,
                                    label
                                });
                            }
                        }
                    });

                    // 3) Validación especial de "Lugares" (grupo de checkboxes)
                    (function validateChecklistLugares() {
                        const group = form.querySelectorAll('input[name="lugares[]"]');
                        if (!group.length) return; // por si no está en esta vista
                        const any = Array.from(group).some(i => i.checked);
                        // Cuenta como un requisito adicional
                        if (any) {
                            completed++;
                        } else {
                            const anchor = '#anchor-lugares';
                            const label = 'Lugares de desempeño';
                            if (!missing.some(m => m.anchor === anchor)) {
                                missing.push({
                                    anchor,
                                    label
                                });
                            }
                        }
                        // Ajusta el total para incluir este requisito "virtual"
                    })();

                    // 4) Total de requisitos = campos requeridos visibles + 1 (lugares)
                    const total = reqEls.length + (form.querySelectorAll('input[name="lugares[]"]').length ? 1 : 0);
                    const percent = total > 0 ? Math.round((completed / total) * 100) : 100;

                    // 5) Render: barra de progreso
                    progressBar.style.width = `${percent}%`;
                    progressBar.textContent = `${percent}%`;
                    progressBar.setAttribute('aria-valuenow', String(percent));

                    // 6) Render: lista de faltantes
                    ulMissing.innerHTML = '';
                    if (missing.length === 0) {
                        doneMsg?.classList.remove('d-none');
                    } else {
                        doneMsg?.classList.add('d-none');
                        missing.forEach(m => {
                            const li = document.createElement('li');
                            li.className = 'mb-1';
                            const a = document.createElement('a');
                            a.href = m.anchor;
                            a.textContent = m.label || 'Campo faltante';
                            a.className = 'text-decoration-underline checklist-link';
                            a.addEventListener('click', function(evt) {
                                if (this.hash) {
                                    evt.preventDefault();
                                    scrollToAnchor(this.hash);
                                }
                            });
                            li.appendChild(a);
                            ulMissing.appendChild(li);
                        });
                    }
                }

                // Debounce simple para no recalcular en exceso
                let checklistTimer = null;

                function scheduleChecklistUpdate() {
                    clearTimeout(checklistTimer);
                    checklistTimer = setTimeout(updateChecklist, 120);
                }

                // 1) Recalcular ante cambios de inputs y selects
                form.addEventListener('input', scheduleChecklistUpdate);
                form.addEventListener('change', scheduleChecklistUpdate);
                form.addEventListener('blur', scheduleChecklistUpdate, true);

                // 2) Observar cambios de visibilidad/required dinámicos
                const checklistObserver = new MutationObserver((mutations) => {
                    // Si cambia la clase (p.ej. toggles de .d-none) o el atributo required, recalculamos
                    if (mutations.some(m => (m.type === 'attributes' && (m.attributeName === 'class' || m
                            .attributeName === 'required' || m.attributeName === 'style')))) {
                        scheduleChecklistUpdate();
                    }
                });
                checklistObserver.observe(form, {
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class', 'required', 'style']
                });

                // 3) Recalcular cuando tu lógica académica cambie dependencias
                estamento?.addEventListener('change', scheduleChecklistUpdate);
                area?.addEventListener('change', scheduleChecklistUpdate);
                document.getElementById('nivel_estudios')?.addEventListener('change', scheduleChecklistUpdate);
                document.getElementById('region_code')?.addEventListener('change', scheduleChecklistUpdate);
                document.getElementById('comuna_id')?.addEventListener('change', scheduleChecklistUpdate);

                // 4) Primera carga
                document.addEventListener('DOMContentLoaded', () => {
                    scheduleChecklistUpdate();
                });
                // 5) Scroll suave desde checklist
                document.querySelectorAll('.checklist-link').forEach(a => {
                    a.addEventListener('click', function(evt) {
                        const href = this.getAttribute('href');
                        if (href && href.startsWith('#')) {
                            const el = document.querySelector(href);
                            if (el) {
                                evt.preventDefault();
                                el.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                const input = el.querySelector('input,select,textarea') || el;
                                if (input) {
                                    input.classList.add('is-invalid');
                                    setTimeout(() => input.classList.remove('is-invalid'), 2000);
                                }
                            }
                        }
                    });
                });

                // 6) Submit: marcar errores y validar grupos
                form.addEventListener('submit', function(e) {
                    const marcados = Array.from(document.querySelectorAll('input[name="lugares[]"]:checked'))
                        .map(el => el.value);
                    console.log('[DEBUG] lugares marcados antes de enviar:', marcados);

                    // 1) Marca inválidos
                    const requiredFields = form.querySelectorAll(
                        'input[required], select[required], textarea[required]');
                    requiredFields.forEach(el => {
                        if (!el.checkValidity() || !String(el.value).trim()) {
                            el.classList.add('is-invalid');
                        }
                    });

                    // 2) Validación grupo lugares (y BLOQUEAR envío si falla)
                    const lugaresChecks = form.querySelectorAll('input[name="lugares[]"]');
                    if (lugaresChecks.length) {
                        const anyChecked = Array.from(lugaresChecks).some(c => c.checked);
                        if (!anyChecked) {
                            e.preventDefault();
                            e.stopPropagation();

                            lugaresChecks[0].classList.add('is-invalid');
                            const lugaresFeedback = document.getElementById('lugaresFeedback');
                            if (lugaresFeedback) {
                                lugaresFeedback.classList.remove('d-none');
                                lugaresFeedback.style.display = 'block';
                            }

                            // opcional: llevar al usuario a la sección
                            document.getElementById('card-lugares')?.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                            return;
                        }
                    }
                });

                // 7) Inicialización al cargar
                updateNacPreview();
                updateAcademicosUI();
                // --- 8) Autofill Nº de cuenta para Cuenta RUT (BancoEstado) ---
                const selectBanco = document.getElementById('banco');
                const selectTipo = document.getElementById('tipo_cuenta');
                const inputNro = document.getElementById('numero_cuenta');

                // RUT del postulante (ajusta si tu RUT está en otro campo/relación)
                const RUT_ORIG = @json($user->rut ?? '');

                // Normaliza texto (minusculas, sin acentos, sin espacios)
                const norm = (s) => (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                // Quita puntos/espacios; si trae guion, toma la parte antes del guion.
                // Si no trae guion, y el último char parece DV (digito o 'k'), corta el último.
                function rutSinDV(r) {
                    if (!r) return '';
                    let s = String(r).trim();
                    s = s.replace(/\./g, '').replace(/\s+/g, '');
                    const hasDash = s.includes('-');
                    if (hasDash) return s.split('-')[0];
                    // sin guion: si largo >= 8 y termina en [0-9kK], corta el ultimo como DV
                    if (s.length >= 8 && /[0-9kK]$/.test(s)) {
                        const cuerpo = s.slice(0, -1);
                        if (/^\d+$/.test(cuerpo)) return cuerpo;
                    }
                    return s.replace(/[^0-9]/g, ''); // fallback: solo dígitos
                }

                function isBancoEstado(value) {
                    // Acepta "BancoEstado" y "Banco Estado"
                    const v = norm((value || '').replace(/\s+/g, ''));
                    return v === 'bancoestado';
                }

                function isCuentaRUT(value) {
                    return norm(value) === norm('Cuenta RUT');
                }

                function maybeAutofill() {
                    if (!selectBanco || !selectTipo || !inputNro) return;

                    const bancoOk = isBancoEstado(selectBanco.value);
                    const tipoOk = isCuentaRUT(selectTipo.value);

                    if (bancoOk && tipoOk) {
                        const base = rutSinDV(RUT_ORIG);
                        // Autocompletar sólo si está vacío o si coincide con el mismo formato
                        if (!inputNro.value || norm(inputNro.value) === norm(rutSinDV(inputNro.value))) {
                            inputNro.value = base;
                            // dispara input para limpiar 'is-invalid' si aplica
                            inputNro.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }
                    }
                }

                // Cuando cambia TIPO: si corresponde, autocompleta sin borrar el valor ya cargado
                selectTipo?.addEventListener('change', () => {
                    maybeAutofill();
                });

                // Cuando cambia BANCO: sólo autocompleta si corresponde; nunca borra automáticamente
                selectBanco?.addEventListener('change', () => {
                    maybeAutofill();
                });

                // Al cargar
                document.addEventListener('DOMContentLoaded', () => {
                    maybeAutofill();
                });

            })();
        </script>
    @endpush
@endsection
