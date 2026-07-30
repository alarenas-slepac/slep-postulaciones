<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe Matriz C DIPRES</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 6px; font-size: 11px; mso-number-format:"\@"; }
        th { background: #f4cccc; text-align: left; font-weight: bold; }
        .meta { margin-bottom: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="meta">
        <strong>Informe Matriz C DIPRES</strong><br>
        Trimestre: {{ $trimestreLabel }}<br>
        Año: {{ $anio }}<br>
        Rango: {{ $fechaInicio }} a {{ $fechaTermino }}<br>
        Día anterior al cierre trimestral: {{ $rangosCese['dia_anterior_termino_trimestre'] ?? '' }}<br>
        Último día trimestre anterior: {{ $rangosCese['ultimo_dia_trimestre_anterior'] ?? '' }}<br>
        Generado: {{ cl_datetime($generatedAt) }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
