<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla BRP</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 6px; font-size: 12px; }
        th { background: #d9eaf7; text-align: left; }
        .meta { margin-bottom: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="meta">
        <strong>Planilla BRP</strong><br>
        Rango: {{ $fechaInicio }} a {{ $fechaTermino }}<br>
        Generado: {{ cl_datetime($generatedAt) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>rut_reemplazo</th>
                <th>nombre_completo_reemplazo</th>
                <th>rbd_establecimiento</th>
                <th>nombre_establecimiento</th>
                <th>rut_funcionario_a_reemplazar</th>
                <th>nombre_funcionario_a_reemplazar</th>
                <th>fecha_inicio</th>
                <th>fecha_termino</th>
                <th>horas_efectivamente_reemplazadas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['rut_reemplazo'] }}</td>
                    <td>{{ $row['nombre_completo_reemplazo'] }}</td>
                    <td>{{ $row['rbd_establecimiento'] }}</td>
                    <td>{{ $row['nombre_establecimiento'] }}</td>
                    <td>{{ $row['rut_funcionario_a_reemplazar'] }}</td>
                    <td>{{ $row['nombre_funcionario_a_reemplazar'] }}</td>
                    <td>{{ $row['fecha_inicio_trabajo'] }}</td>
                    <td>{{ $row['fecha_termino'] }}</td>
                    <td>{{ $row['horas_efectivamente_reemplazadas'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
