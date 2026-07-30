@php
    echo "\xEF\xBB\xBF";
    $fmtFecha = fn ($fecha) => $fecha ? \Illuminate\Support\Carbon::parse($fecha)->format('d-m-Y') : '';
    $fmtRut = function ($rut) {
        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
        if ($rut === '') {
            return '';
        }
        $dv = substr($rut, -1);
        $num = substr($rut, 0, -1);
        return number_format((int) $num, 0, '', '.') . '-' . $dv;
    };
    $nombreReemplazante = function ($s) {
        $user = $s->contratoPostulante?->user ?: $s->postulante?->user;
        if (! $user) {
            return trim((string) ($s->rut_reemplazo_normalizado ?: ''));
        }
        return trim(collect([$user->apellido_paterno ?? '', $user->apellido_materno ?? '', $user->nombres ?? ''])->filter()->implode(' '));
    };
    $rutReemplazante = function ($s) use ($fmtRut) {
        $user = $s->contratoPostulante?->user ?: $s->postulante?->user;
        return $fmtRut($user?->rut ?: $s->rut_reemplazo_normalizado ?: '');
    };
    $estadoFiniquitoLabel = fn ($s) => match ((string) ($s->finiquito_estado ?? 'pendiente')) {
        'generado' => 'Generado',
        'completado' => 'Completado',
        default => 'Pendiente',
    };
    $categoriaLabel = fn ($categoria) => match ((string) $categoria) {
        'asistentes' => 'Asistentes',
        'junji' => 'JUNJI / Sala Cuna',
        'docentes' => 'Docentes Matriz C',
        default => 'Todos',
    };
    $jornadaHoras = function ($s) {
        $total = 0.0;
        foreach (($s->jornadas ?? collect()) as $jornada) {
            if ($jornada->getAttribute('total_horas') !== null) {
                $total += (float) $jornada->getAttribute('total_horas');
            } elseif ($jornada->getAttribute('reemplazo_total') !== null) {
                $total += (float) $jornada->getAttribute('reemplazo_total');
            } else {
                $total += (float) $jornada->getAttribute('reemplazo_basica') + (float) $jornada->getAttribute('reemplazo_media');
            }
        }
        if ($total <= 0) {
            $total = (float) ($s->horas_aula_cronologicas_reemplazo ?: $s->horas_aula_pedagogicas_reemplazo ?: 0);
        }
        return $total > 0 ? rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',') : '';
    };
@endphp
<table border="1">
    <thead>
        <tr>
            <th colspan="24">Exportación finiquitos de reemplazos</th>
        </tr>
        <tr>
            <th colspan="24">Pestaña: {{ $categoriaLabel($categoriaGestion ?? 'todos') }} | Estado: {{ $estadoFiniquito ?? 'todos' }} | Fecha de corte: {{ $fmtFecha($cutoff ?? null) }}</th>
        </tr>
        <tr>
            <th>N_SOLICITUD</th>
            <th>ID_SOLICITUD</th>
            <th>ESTADO_SOLICITUD</th>
            <th>CATEGORIA</th>
            <th>COMUNA</th>
            <th>RBD</th>
            <th>ESTABLECIMIENTO</th>
            <th>RUT_TITULAR</th>
            <th>TITULAR</th>
            <th>RUT_REEMPLAZANTE</th>
            <th>REEMPLAZANTE</th>
            <th>INICIO_CONTINUIDAD</th>
            <th>TERMINO_CONTINUIDAD</th>
            <th>JORNADA</th>
            <th>SOLICITUDES_CADENA</th>
            <th>CANTIDAD_SOLICITUDES_CADENA</th>
            <th>ESTADO_FINIQUITO</th>
            <th>MONTO_FINIQUITO</th>
            <th>FECHA_EMISION</th>
            <th>PDF_GENERADO</th>
            <th>PDF_FIRMADO</th>
            <th>FECHA_CARGA_FIRMADO</th>
            <th>USUARIO_CARGA_FIRMADO</th>
            <th>OBSERVACIONES</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($finiquitos as $s)
            <tr>
                <td>{{ $s->numero_solicitud }}</td>
                <td>{{ $s->id }}</td>
                <td>{{ $s->estado }}</td>
                <td>{{ $categoriaLabel($s->categoria_finiquito ?? '') }}</td>
                <td>{{ $s->establecimiento?->comuna }}</td>
                <td>{{ $s->establecimiento?->rbd }}</td>
                <td>{{ $s->establecimiento?->nombre_establecimiento }}</td>
                <td>{{ $fmtRut($s->funcionarioTitular?->rut ?? $s->rut_titular_normalizado ?? '') }}</td>
                <td>{{ $s->funcionarioTitular?->nombre }}</td>
                <td>{{ $rutReemplazante($s) }}</td>
                <td>{{ $nombreReemplazante($s) }}</td>
                <td>{{ $fmtFecha($s->finiquito_periodo_inicio ?? $s->fecha_inicio_trabajo) }}</td>
                <td>{{ $fmtFecha($s->finiquito_periodo_termino ?? $s->fecha_termino) }}</td>
                <td>{{ $jornadaHoras($s) }}</td>
                <td>{{ implode(', ', (array) ($s->finiquito_cadena_numeros ?? [])) }}</td>
                <td>{{ (int) ($s->finiquito_cadena_count ?? 1) }}</td>
                <td>{{ $estadoFiniquitoLabel($s) }}</td>
                <td>{{ (int) ($s->finiquito_monto ?? 0) }}</td>
                <td>{{ $fmtFecha($s->finiquito_fecha_emision ?? null) }}</td>
                <td>{{ $s->finiquito_pdf_path ? 'SI' : 'NO' }}</td>
                <td>{{ $s->finiquito_firmado_pdf_path ? 'SI' : 'NO' }}</td>
                <td>{{ $fmtFecha($s->finiquito_firmado_cargado_at ?? null) }}</td>
                <td>{{ trim(collect([$s->finiquitoFirmadoCargadoPor?->apellido_paterno ?? '', $s->finiquitoFirmadoCargadoPor?->apellido_materno ?? '', $s->finiquitoFirmadoCargadoPor?->nombres ?? ''])->filter()->implode(' ')) }}</td>
                <td>{{ trim(collect([$s->finiquito_observacion ?? '', $s->finiquito_firmado_observacion ?? ''])->filter()->implode(' | ')) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
