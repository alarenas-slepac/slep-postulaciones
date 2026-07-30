<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Perfil del Postulante</title>
    <style>
        @page {
            margin: 18px 18px;
        }

        /* margen más chico */
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            /* más compacto */
            line-height: 1.25;
            color: #222;
        }

        /* Header como tabla (mejor soporte en dompdf) */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border-bottom: 1px solid {{ $brand['primary'] }};
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .brand-logo-slep {
            max-height: 34px;
            max-width: 150px;
        }

        .brand-logo-sga {
            max-height: 34px;
            max-width: 145px;
        }

        .title {
            font-size: 14px;
            font-weight: 700;
            color: {{ $brand['primary'] }};
        }

        .muted {
            color: {{ $brand['muted'] }};
            font-size: 10px;
        }

        .avatar {
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        /* Secciones */
        .section-title {
            font-size: 12px;
            color: {{ $brand['primary'] }};
            margin: 8px 0 4px;
            border-left: 3px solid {{ $brand['primary'] }};
            padding-left: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 4px 6px;
            vertical-align: top;
        }

        /* celdas compactas */
        .grid td {
            border: 1px solid #e5e7eb;
        }

        .kv {
            width: 42%;
        }

        /* un poco más ancho para etiquetas largas */
        .val {
            width: 58%;
            word-break: break-word;
        }

        /* Layout de dos columnas */
        .layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .layout .col {
            width: 50%;
            vertical-align: top;
            padding-left: 6px;
        }

        .layout .col:first-child {
            padding-left: 0;
            padding-right: 6px;
        }

        .chip {
            display: inline-block;
            border: 1px solid {{ $brand['primary'] }};
            color: {{ $brand['primary'] }};
            border-radius: 12px;
            padding: 1px 6px;
            margin: 0 3px 3px 0;
            font-size: 10px;
            /* chips más chicos */
            line-height: 1.2;
        }

        .footer {
            margin-top: 8px;
            font-size: 9.5px;
            color: #6c757d;
            text-align: right;
        }

        /* Evitar cortes feos */
        .avoid-break {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>
    {{-- HEADER --}}
    <table class="header-table avoid-break">
        <tr>
            <td style="width: 30%;">
                @if (!empty($brand['slep_logo_src']))
                    <img src="{{ $brand['slep_logo_src'] }}" class="brand-logo-slep" alt="SLEP Andalién Costa">
                @else
                    <span>SLEP Andalién Costa</span>
                @endif
            </td>
            <td style="width: 38%; text-align:center;">
                <div class="title">Perfil del Postulante</div>
                <div class="muted">Generado: {{ cl_datetime($generatedAt) }}</div>
            </td>
            <td style="width: 22%; text-align:right;">
                @if (!empty($brand['sga_logo_src']))
                    <img src="{{ $brand['sga_logo_src'] }}" class="brand-logo-sga" alt="{{ config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}">
                @else
                    <span>{{ config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}</span>
                @endif
            </td>
            <td style="text-align:right; width: 10%;">
                @if ($fotoThumbAbs && is_file($fotoThumbAbs))
                    <img src="{{ $fotoThumbAbs }}" alt="Foto" class="avatar" width="50" height="50">
                @endif
            </td>
        </tr>
    </table>

    {{-- IDENTIDAD --}}
    <table class="avoid-break" style="margin-bottom: 4px;">
        <tr>
            <td class="kv"><strong>Postulante</strong></td>
            <td class="val">
                {{ trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->display_name ?? $user->email }}
            </td>
        </tr>
        <tr>
            <td class="kv"><strong>RUT</strong></td>
            <td class="val">{{ $rutFmt }}</td>
        </tr>
        <tr>
            <td class="kv"><strong>Email de contacto</strong></td>
            <td class="val">{{ $profile->email_contacto ?? $user->email }}</td>
        </tr>
        <tr>
            <td class="kv"><strong>Teléfono(s)</strong></td>
            <td class="val">
                {{ $profile->telefono1 }}@if (!empty($profile->telefono2))
                    — {{ $profile->telefono2 }}
                @endif
            </td>
        </tr>
    </table>

    {{-- DOS COLUMNAS: izquierda (Personales + Previsional/Banco) | derecha (Académicos + Comunas) --}}
    <table class="layout">
        <tr>
            <td class="col">
                <div class="section-title">Datos personales</div>
                <table class="grid avoid-break">
                    <tr>
                        <td class="kv"><strong>Fecha de nacimiento</strong></td>
                        <td class="val">
                            @if (!empty($profile->fecha_nacimiento))
                                {{ \Illuminate\Support\Carbon::parse($profile->fecha_nacimiento)->format('d-m-Y') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Dirección</strong></td>
                        <td class="val">{{ $profile->direccion }}</td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Región</strong></td>
                        <td class="val">{{ $regionName }}</td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Comuna</strong></td>
                        <td class="val">{{ $profile->comuna?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Nacionalidad</strong></td>
                        <td class="val">
                            @if (!empty($flagDataUrl))
                                <img src="{{ $flagDataUrl }}"
                                    style="height:12px;vertical-align:-2px;margin-right:6px;">
                            @endif
                            {{ $nacName ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Género</strong></td>
                        <td class="val">{{ $profile->genero }}</td>
                    </tr>
                    @if (!empty($profile->pronombres))
                        <tr>
                            <td class="kv"><strong>Pronombres</strong></td>
                            <td class="val">{{ $profile->pronombres }}</td>
                        </tr>
                    @endif
                </table>

                <div class="section-title" style="margin-top: 6px;">Datos previsionales y bancarios</div>
                <table class="grid avoid-break">
                    <tr>
                        <td class="kv"><strong>Institución de Previsión (AFP)</strong></td>
                        <td class="val">{{ $profile->prevision_afp ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Institución de Salud</strong></td>
                        <td class="val">{{ $profile->salud_institucion ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Banco</strong></td>
                        <td class="val">{{ $profile->banco ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Tipo de cuenta</strong></td>
                        <td class="val">{{ $profile->tipo_cuenta ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="kv"><strong>Nº de cuenta</strong></td>
                        <td class="val">{{ $profile->numero_cuenta ?? '—' }}</td>
                    </tr>
                </table>
            </td>

            <td class="col">
                <div class="section-title">Antecedentes académicos</div>
                <table class="grid avoid-break">
                    <tr>
                        <td class="kv"><strong>Estamento</strong></td>
                        <td class="val">{{ $profile->estamento ?? '—' }}</td>
                    </tr>
                    @if (!empty($profile->area_desempeno_id) || !empty($profile->area_desempeno_nombre))
                        <tr>
                            <td class="kv"><strong>Área de desempeño</strong></td>
                            <td class="val">{{ $profile->areaDesempeno?->nombre ?? ($profile->area_desempeno_nombre ?? '—') }}</td>
                        </tr>
                    @endif
                    @if (!empty($profile->mencion))
                        <tr>
                            <td class="kv"><strong>Mención</strong></td>
                            <td class="val">{{ $profile->mencion }}</td>
                        </tr>
                    @endif
                    @if (!empty($profile->especialidad_tp))
                        <tr>
                            <td class="kv"><strong>Especialidad TP</strong></td>
                            <td class="val">{{ $profile->especialidad_tp }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="kv"><strong>Nivel de estudios</strong></td>
                        <td class="val">{{ $profile->nivel_estudios ?? '—' }}</td>
                    </tr>
                    @if (in_array($profile->nivel_estudios, ['Técnico Nivel Superior', 'Universitaria']))
                        <tr>
                            <td class="kv"><strong>Institución</strong></td>
                            <td class="val">{{ $profile->institucion_titulo ?? '—' }}</td>
                        </tr>
                        @if (!empty($profile->fecha_titulacion))
                            <tr>
                                <td class="kv"><strong>Fecha de titulación</strong></td>
                                <td class="val">
                                    {{ \Illuminate\Support\Carbon::parse($profile->fecha_titulacion)->format('d-m-Y') }}
                                </td>
                            </tr>
                        @endif
                    @endif
                    @if ($profile->nivel_estudios === 'Universitaria')
                        <tr>
                            <td class="kv"><strong>Semestres</strong></td>
                            <td class="val">{{ $profile->semestres ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="kv"><strong>Horas totales</strong></td>
                            <td class="val">{{ $profile->horas_totales ?? '—' }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="kv"><strong>Años de experiencia</strong></td>
                        <td class="val">{{ $profile->anios_experiencia ?? '—' }}</td>
                    </tr>
                </table>

                <div class="section-title" style="margin-top: 6px;">Lugares de desempeño</div>
                <div class="avoid-break">
                    @if ($communes && $communes->count())
                        <div>
                            @foreach ($communes as $c)
                                <span class="chip">{{ $c->name }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="muted">Sin comunas seleccionadas.</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }} — Perfil generado automáticamente
    </div>
</body>

</html>
