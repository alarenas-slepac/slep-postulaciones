<?php

namespace App\Services\LicenciasMedicas;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class LicenciaPdfExtractor
{
    public function extract(string $path): array
    {
        $text = $this->extractText($path);
        $normalized = $this->normalizeText($text);
        $structured = $this->parseStructured($normalized);
        $data = $this->toFormData($structured, $normalized);

        $filled = collect($data)->filter(fn ($value) => !is_null($value) && $value !== '')->count();

        return [
            'estado' => $filled >= 8 ? 'procesado' : ($filled > 0 ? 'incompleto' : 'sin_texto'),
            'confianza' => $filled >= 11 ? 'alta' : ($filled >= 6 ? 'media' : 'baja'),
            'texto' => Str::limit($normalized, 12000, ''),
            'datos' => $data,
            'estructura' => $structured,
            'advertencias' => $this->advertencias($data, $normalized),
        ];
    }

    private function extractText(string $path): string
    {
        if (class_exists(Parser::class)) {
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($path);
                $text = (string) $pdf->getText();
                if (trim($text) !== '') {
                    return $text;
                }
            } catch (\Throwable $e) {
                // Continua con respaldo PHP para no romper el flujo si el PDF no puede ser interpretado por la libreria.
            }
        }

        return $this->extractTextFallback($path);
    }

    private function extractTextFallback(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) return '';

        $chunks = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = $this->decodePdfStream($stream);
                if ($decoded === null) continue;
                $text = $this->decodePdfTextOperators($decoded);
                if (trim($text) !== '') $chunks[] = $text;
            }
        }

        return implode("\n", array_filter($chunks));
    }

    private function decodePdfStream(string $stream): ?string
    {
        $stream = trim($stream, "\r\n");
        foreach ([
            fn ($value) => @gzuncompress($value),
            fn ($value) => @gzdecode($value),
            fn ($value) => strlen($value) > 6 ? @gzinflate(substr($value, 2, -4)) : false,
            fn ($value) => @gzinflate($value),
        ] as $decoder) {
            $decoded = $decoder($stream);
            if ($decoded !== false && is_string($decoded)) return $decoded;
        }

        if (str_contains($stream, ' Tj') || str_contains($stream, ' TJ')) return $stream;

        return null;
    }

    private function decodePdfTextOperators(string $stream): string
    {
        $out = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $stream, $m)) {
            foreach ($m[0] as $token) $out[] = $this->unescapePdfString($token);
        }
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $m)) {
            foreach ($m[1] as $array) {
                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $array, $parts)) {
                    $line = '';
                    foreach ($parts[0] as $part) $line .= $this->unescapePdfString($part);
                    $out[] = $line;
                }
            }
        }
        return implode("\n", $out);
    }

    private function unescapePdfString(string $token): string
    {
        $token = preg_replace('/^\(|\)\s*(Tj)?$/', '', trim($token)) ?: '';
        $token = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $token);
        $token = preg_replace('/\\\\([nrtbf])/', ' ', $token) ?: $token;
        $token = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($m) => chr(octdec($m[1])), $token) ?: $token;
        return $token;
    }

    private function normalizeText(string $text): string
    {
        $text = $this->ensureUtf8($text);
        $text = str_replace(["\r", "\t", "\xc2\xa0", chr(160)], ["\n", ' ', ' ', ' '], $text);
        $text = str_replace(['º', '°'], ['°', '°'], $text);
        $text = preg_replace('/[ ]{2,}/u', ' ', $text) ?: $text;
        $text = preg_replace('/\n[ ]+/u', "\n", $text) ?: $text;
        $text = preg_replace('/\n{2,}/u', "\n", $text) ?: $text;
        return trim($text);
    }

    private function ensureUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $isUtf8 = false;
        if (function_exists('mb_check_encoding')) {
            $isUtf8 = mb_check_encoding($text, 'UTF-8');
        } else {
            $isUtf8 = (bool) @preg_match('//u', $text);
        }

        if (!$isUtf8) {
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }

            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }

            $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        return is_string($clean) && $clean !== '' ? $clean : $text;
    }

    private function parseStructured(string $text): array
    {
        $receipt = $this->receiptSection($text) ?: $text;
        $professional = $this->section($receipt, '1\.\s*Datos Profesional', '2\.\s*Datos Trabajador') ?: '';
        $worker = $this->section($receipt, '2\.\s*Datos Trabajador', '3\.\s*Datos Reposo') ?: '';
        $rest = $this->section($receipt, '3\.\s*Datos Reposo', '4\.\s*Estado de la licencia') ?: '';
        $status = $this->section($receipt, '4\.\s*Estado de la licencia', '5\.\s*Datos del Empleador') ?: '';
        $employer = $this->section($receipt, '5\.\s*Datos del Empleador', '6\.\s*Datos de pronunciamiento|Puede revisar|El que incurra|$') ?: '';

        $folio = $this->folioParts($text);
        $workerRut = $this->formatRut($this->field($worker, 'Rut') ?: $this->match('/2\.\s*Datos Trabajador.*?Rut\s*:\s*([0-9kK.\-]+)/isu', $receipt));
        $professionalRut = $this->formatRut($this->field($professional, 'Rut'));
        $employerRut = $this->formatRut($this->field($employer, 'Rut del Empleador'));

        $tipoLicenciaRaw = $this->field($worker, 'Tipo Licencia') ?: $this->match('/Tipo Licencia\s*:\s*([^\n]+)/iu', $receipt);
        [$tipoCodigo, $tipoGlosa] = $this->parseTipoLicencia($tipoLicenciaRaw);

        $fechaOtorgamientoRaw = $this->field($receipt, 'Fecha Otorgamiento') ?: $this->match('/Fecha Otorgamiento\s*:\s*([^\n]+)/iu', $receipt);
        [$fechaOtorgamiento, $fechaOtorgamientoHora] = $this->parseDateTime($fechaOtorgamientoRaw);
        $entidadPronunciamiento = $this->field($receipt, 'Entidad que se pronuncia') ?: $this->match('/Entidad que se pronuncia\s*:\s*([^\n]+)/iu', $receipt);
        [$sistemaSalud, $institucionSalud] = $this->parseSistemaSalud($entidadPronunciamiento, $text);

        $nombreCompleto = $this->field($worker, 'Nombre') ?: $this->match('/Nombre\s*:\s*(.*?)\s*Rut\s*:/isu', $worker);
        $nombrePartes = $this->splitNombre((string) $nombreCompleto);

        $data = [
            'folio' => $folio['folio'] ?? null,
            'numero_licencia' => $folio['numero_licencia'] ?? null,
            'tipo_ingreso_licencia' => $folio['tipo_ingreso_licencia'] ?? null,
            'cuerpo_licencia' => $folio['cuerpo_licencia'] ?? null,
            'dv_licencia' => $folio['dv_licencia'] ?? null,
            'fecha_otorgamiento' => $fechaOtorgamiento,
            'fecha_otorgamiento_hora' => $fechaOtorgamientoHora,
            'entidad_pronunciamiento' => $entidadPronunciamiento,
            'sistema_salud' => $sistemaSalud,
            'institucion_salud' => $institucionSalud,
            'estado' => $this->field($status, 'Estado') ?: $this->match('/Estado\s*:\s*([^\n]+)/iu', $status),
            'trabajador' => [
                'nombre' => $nombrePartes['nombres'],
                'apellido_paterno' => $nombrePartes['apellido_paterno'],
                'apellido_materno' => $nombrePartes['apellido_materno'],
                'nombre_completo' => $this->cleanLine($nombreCompleto),
                'rut' => $workerRut,
                'edad' => $this->integer($this->field($worker, 'Edad')),
                'sexo' => $this->field($worker, 'Sexo'),
            ],
            'licencia' => [
                'tipo_codigo' => $tipoCodigo,
                'tipo_glosa' => $tipoGlosa,
                'fecha_inicio_reposo' => $this->date($this->field($rest, 'Fecha Inicio')),
                'dias' => $this->integer($this->field($rest, 'N° Días') ?: $this->field($rest, 'Nº Días') ?: $this->field($rest, 'N Dias')),
                'fecha_termino' => $this->date($this->field($rest, 'Fecha término') ?: $this->field($rest, 'Fecha termino') ?: $this->field($rest, 'término') ?: $this->field($rest, 'termino')),
                'tipo_reposo' => $this->field($rest, 'Tipo'),
                'lugar_reposo' => $this->field($rest, 'Lugar'),
                'direccion_reposo' => $this->field($rest, 'Dirección') ?: $this->field($rest, 'Direccion'),
                'telefono' => $this->field($rest, 'Teléfono') ?: $this->field($rest, 'Telefono'),
            ],
            'profesional' => [
                'nombre_completo' => $this->field($professional, 'Profesional'),
                'rut' => $professionalRut,
                'especialidad' => $this->field($professional, 'Especialidad'),
                'direccion' => $this->field($professional, 'Dirección') ?: $this->field($professional, 'Direccion'),
                'registro_colegio' => $this->field($professional, 'Reg. Colegio Profesional') ?: $this->field($professional, 'REG. COLEGIO PROFESIONAL'),
                'correo' => $this->field($professional, 'Correo Electrónico') ?: $this->field($professional, 'Correo Electronico'),
            ],
            'empleador' => [
                'nombre' => $this->field($receipt, 'Empleador') ?: $this->match('/Empleador\s*:\s*([^\n]+)/iu', $receipt),
                'rut' => $employerRut,
                'fecha_recepcion' => $this->date($this->field($employer, 'Fecha de Recepción') ?: $this->field($employer, 'Fecha de Recepcion')),
                'fecha_envio_pronunciamiento' => $this->date($this->field($employer, 'Fecha de envío a pronunciamiento') ?: $this->field($employer, 'Fecha de envio a pronunciamiento')),
            ],
            'verificacion' => [
                'codigo' => $this->match('/c[oó]digo de verificaci[oó]n\s*:\s*([A-Za-z0-9\-]+)/iu', $receipt),
                'url' => $this->match('/(www\.licencia\.cl)/iu', $receipt),
            ],
        ];

        return $this->mergeMissing($data, $this->parseFormularioPrimeraHoja($text));
    }

    private function parseFormularioPrimeraHoja(string $text): array
    {
        $folio = $this->folioParts($text);
        $trabajadorLinea = $this->match('/A\.1\s+IDENTIFICACI[OÓ]N\s+DEL\s+TRABAJADOR\s*\n(.+?\d{6,8}\s*-\s*[0-9K]\s+\d{1,3}\s+[MF])/isu', $text);
        $trabajador = $this->parseTrabajadorLinea($trabajadorLinea);

        $fechaEmision = $this->dateFromBoxes($text, 'FECHA\s+EMISI[OÓ]N\s+LICENCIA');
        $fechaInicio = $this->dateFromBoxes($text, 'FECHA\s+INICIO\s+DE\s+REPOSO');
        $dias = $this->integer($this->match('/N\s*(?:DE|º|°)?\s*DE?\s*DIAS\s*\n\s*(\d{1,3})/iu', $text) ?: $this->match('/N\s*(?:DE|º|°)?\s*DIAS\s+(\d{1,3})/iu', $text));
        $fechaTermino = null;
        if ($fechaInicio && $dias) {
            try {
                $fechaTermino = Carbon::parse($fechaInicio)->addDays(((int) $dias) - 1)->format('Y-m-d');
            } catch (\Throwable $e) {
                $fechaTermino = null;
            }
        }

        $tipoCodigo = $this->integer($this->match('/A\.3\s+TIPO\s+DE\s+LICENCIA\s*\n\s*([1-7])/isu', $text));
        $tipoGlosa = $tipoCodigo ? $this->tipoLicenciaGlosa($tipoCodigo) : null;
        $reposoCodigo = $this->integer($this->match('/A\.4\s+CARACTER[ÍI]STICAS\s+DEL\s+REPOSO\s*\n\s*([12])/isu', $text));
        $lugarCodigo = $this->integer($this->match('/LUGAR\s+DE\s+REPOSO\s+([123])/iu', $text));
        $direccion = $this->match('/DIRECCI[OÓ]N\s*:\s*CALLE;?N[º°]?;?DEPTO;?COMUNA\s*\n(.+?)(?=\n\s*TELEFONO|\n\s*TEL[EÉ]FONO)/isu', $text);
        $telefono = $this->match('/TEL[EÉ]FONO\s*\(PERSONAL\s+O\s+DE\s+CONTACTO\)\s*\n\s*([0-9+\-\s]+)/iu', $text);

        $profesionalLinea = $this->match('/A\.5\s+IDENTIFICACI[OÓ]N\s+DEL\s+PROFESIONAL\s*\n(.+?\d{6,8}\s*-\s*[0-9K])/isu', $text);
        $profesional = $this->parseNombreRutLinea($profesionalLinea);
        $correos = $this->emails($text);
        $correoProfesional = $correos[0] ?? null;
        $correoTrabajador = $correos[count($correos) - 1] ?? null;

        $entidad = $this->match('/CODIGO\s+ENTIDAD\s+RUT\s+PRESTADOR.*?\n.*?:\s*([^\n]+?)\s+\d{6,8}-[0-9K]/isu', $text)
            ?: $this->match('/\d{7,8}-[0-9K]\s*:\s*([A-ZÁÉÍÓÚÜÑ0-9 .\-]+?)\s+\d{6,8}-[0-9K]/iu', $text);
        [$sistemaSalud, $institucionSalud] = $this->parseSistemaSalud($entidad, $text);

        $estado = $this->match('/ESTADO\s+LICENCIA.*?\n\s*([0-9]+\s*-\s*[A-ZÁÉÍÓÚÜÑ ]+)/isu', $text);
        $rutEmpleador = $this->formatRut($this->match('/CODIGO\s+TRAMITACION\s+RUT\s+EMPLEADOR.*?\n.*?\s(\d{7,8}-[0-9K])(?:\s|$)/isu', $text));

        $simple = [
            'folio' => $folio['folio'] ?? null,
            'numero_licencia' => $folio['numero_licencia'] ?? null,
            'tipo_ingreso_licencia' => $folio['tipo_ingreso_licencia'] ?? null,
            'cuerpo_licencia' => $folio['cuerpo_licencia'] ?? null,
            'dv_licencia' => $folio['dv_licencia'] ?? null,
            'fecha_otorgamiento' => $fechaEmision,
            'fecha_otorgamiento_hora' => null,
            'entidad_pronunciamiento' => $entidad,
            'sistema_salud' => $sistemaSalud,
            'institucion_salud' => $institucionSalud,
            'estado' => $estado ?: null,
            'trabajador' => [
                'nombre' => $trabajador['nombres'] ?? null,
                'apellido_paterno' => $trabajador['apellido_paterno'] ?? null,
                'apellido_materno' => $trabajador['apellido_materno'] ?? null,
                'nombre_completo' => $trabajador['nombre_completo'] ?? null,
                'rut' => $trabajador['rut'] ?? null,
                'edad' => $trabajador['edad'] ?? null,
                'sexo' => $trabajador['sexo'] ?? null,
            ],
            'licencia' => [
                'tipo_codigo' => $tipoCodigo,
                'tipo_glosa' => $tipoGlosa,
                'fecha_inicio_reposo' => $fechaInicio,
                'dias' => $dias,
                'fecha_termino' => $fechaTermino,
                'tipo_reposo' => $this->reposoGlosa($reposoCodigo),
                'lugar_reposo' => $this->lugarReposoGlosa($lugarCodigo),
                'direccion_reposo' => $this->cleanLine($direccion),
                'telefono' => $this->cleanLine($telefono),
            ],
            'profesional' => [
                'nombre_completo' => $profesional['nombre_completo'] ?? null,
                'rut' => $profesional['rut'] ?? null,
                'especialidad' => $this->match('/APELLIDO\s+PATERNO.*?RUN\s*\n\s*([^\n]+?)\s+1\s*\n\s*1=Medico/isu', $text),
                'direccion' => $this->match('/CORREO\s+ELECTR[OÓ]NICO\s*\n(.+?)(?=\n\s*DIRECCION|\n\s*DIRECCI[OÓ]N)/isu', $text),
                'registro_colegio' => $this->match('/REGISTRO\s+COLEGIO\s+PROFESIONAL.*?\n.*?\s(\d{3,}-?[0-9K]?)/isu', $text),
                'correo' => $correoProfesional,
            ],
            'empleador' => [
                'nombre' => null,
                'rut' => $rutEmpleador,
                'fecha_recepcion' => null,
                'fecha_envio_pronunciamiento' => null,
            ],
            'verificacion' => [
                'codigo' => null,
                'url' => $this->match('/(www\.licencia\.cl)/iu', $text),
            ],
            '_correo_trabajador_primera_hoja' => $correoTrabajador,
        ];

        return $this->mergeMissing(
            $this->mergeMissing($this->parseFormularioPorLineas($text), $simple),
            $this->parseFormularioPrimeraHojaColumnar($text)
        );
    }

    private function parseFormularioPrimeraHojaColumnar(string $text): array
    {
        $folio = $this->folioParts($text);
        $trabajador = $this->parseTrabajadorColumnar($text);
        $fechas = $this->parseFechasColumnar($text);
        $dias = $this->integer(
            $this->match('/N\s+DE\s+DIAS\s+EN\s+PALABRAS\s+(\d{1,3})/isu', $text)
            ?: $this->match('/N\s+(?:DE\s+)?DIAS\b.*?\n\s*(\d{1,3})(?=\s*\n\s*(?:A\.3|DIEZ|ONCE|DOCE|TRECE|CATORCE|QUINCE|VEINTE|TREINTA|[A-ZÁÉÍÓÚÜÑ]+))/isu', $text)
        );

        $tipoCodigo = $this->integer(
            $this->match('/\n\s*([1-7])\s*\n\s*1\s*=\s*Enfermedad\s+o\s+Accidente\s+Comun/isu', $text)
            ?: $this->match('/A\.3\s+TIPO\s+DE\s+LICENCIA.*?\n\s*([1-7])\s*(?=\n|\s+1\s*=\s*Enfermedad)/isu', $text)
        );

        $reposoCodigo = $this->integer(
            $this->match('/\n\s*([12])\s*\n\s*1\s*=\s*Reposo\s+Laboral\s+Total/isu', $text)
            ?: $this->match('/A\.4\s+CARACTER[ÍI]STICAS\s+DEL\s+REPOSO.*?\n\s*([12])\s*(?=\n|\s+1\s*=\s*Reposo)/isu', $text)
        );

        $lugarCodigo = $this->integer(
            $this->match('/LUGAR\s+DE\s+REPOSO.*?\n\s*([123])\s*(?:\n|\s+)(?:RA|TR|PA|1\s*=\s*Reposo|JUSTIFICAR)/isu', $text)
            ?: $this->match('/LUGAR\s+DE\s+REPOSO\s+([123])/iu', $text)
        );

        $direccion = $this->match('/DIRECCI[OÓ]N\s*:\s*CALLE;?N[º°]?;?DEPTO;?COMUNA\s*\n\s*(.+?)(?=\n\s*(?:RECUPERABILIDAD|TELEFONO|TEL[EÉ]FONO|FIRMADO))/isu', $text);
        $telefono = $this->match('/TEL[EÉ]FONO\s*\(PERSONAL\s+O\s+DE\s+CONTACTO\).*?\n\s*([0-9+\-\s]{6,})/isu', $text);

        $entidad = $this->match('/CODIGO\s+ENTIDAD.*?(\d{7,8}\s*-\s*[0-9K]\s*:\s*[A-ZÁÉÍÓÚÜÑ0-9 .\-]+)/isu', $text)
            ?: $this->match('/\d{7,8}\s*-\s*[0-9K]\s*:\s*([A-ZÁÉÍÓÚÜÑ0-9 .\-]+?)(?=\s+ESTADO|\s+RUT\s+PRESTADOR|\s+\d{6,8}\s*-\s*[0-9K]|\n)/isu', $text);
        [$sistemaSalud, $institucionSalud] = $this->parseSistemaSalud($entidad, $text);

        $estado = $this->match('/ESTADO\s+LICENCIA.*?\n\s*([0-9]+\s*-\s*[A-ZÁÉÍÓÚÜÑ ]+)/isu', $text);
        $rutEmpleador = $this->formatRut($this->match('/CODIGO\s+TRAMITACION\s+RUT\s+EMPLEADOR.*?\n.*?\s(\d{7,8}\s*-\s*[0-9K])(?:\s|$)/isu', $text));
        $profesional = $this->parseProfesionalColumnar($text);
        $correos = $this->emails($text);

        $fechaTermino = null;
        if (($fechas['fecha_inicio'] ?? null) && $dias) {
            try {
                $fechaTermino = Carbon::parse($fechas['fecha_inicio'])->addDays(((int) $dias) - 1)->format('Y-m-d');
            } catch (\Throwable $e) {
                $fechaTermino = null;
            }
        }

        return [
            'folio' => $folio['folio'] ?? null,
            'numero_licencia' => $folio['numero_licencia'] ?? null,
            'tipo_ingreso_licencia' => $folio['tipo_ingreso_licencia'] ?? null,
            'cuerpo_licencia' => $folio['cuerpo_licencia'] ?? null,
            'dv_licencia' => $folio['dv_licencia'] ?? null,
            'fecha_otorgamiento' => $fechas['fecha_emision'] ?? null,
            'fecha_otorgamiento_hora' => null,
            'entidad_pronunciamiento' => $entidad,
            'sistema_salud' => $sistemaSalud,
            'institucion_salud' => $institucionSalud,
            'estado' => $estado ?: null,
            'trabajador' => [
                'nombre' => $trabajador['nombres'] ?? null,
                'apellido_paterno' => $trabajador['apellido_paterno'] ?? null,
                'apellido_materno' => $trabajador['apellido_materno'] ?? null,
                'nombre_completo' => $trabajador['nombre_completo'] ?? null,
                'rut' => $trabajador['rut'] ?? null,
                'edad' => $trabajador['edad'] ?? null,
                'sexo' => $trabajador['sexo'] ?? null,
            ],
            'licencia' => [
                'tipo_codigo' => $tipoCodigo,
                'tipo_glosa' => $tipoCodigo ? $this->tipoLicenciaGlosa($tipoCodigo) : null,
                'fecha_inicio_reposo' => $fechas['fecha_inicio'] ?? null,
                'dias' => $dias,
                'fecha_termino' => $fechaTermino,
                'tipo_reposo' => $this->reposoGlosa($reposoCodigo),
                'lugar_reposo' => $this->lugarReposoGlosa($lugarCodigo),
                'direccion_reposo' => $this->cleanLine($direccion),
                'telefono' => $this->cleanLine($telefono),
            ],
            'profesional' => [
                'nombre_completo' => $profesional['nombre_completo'] ?? null,
                'rut' => $profesional['rut'] ?? null,
                'especialidad' => $profesional['especialidad'] ?? null,
                'direccion' => $profesional['direccion'] ?? null,
                'registro_colegio' => $profesional['registro_colegio'] ?? null,
                'correo' => $profesional['correo'] ?? ($correos[0] ?? null),
            ],
            'empleador' => [
                'nombre' => null,
                'rut' => $rutEmpleador,
                'fecha_recepcion' => null,
                'fecha_envio_pronunciamiento' => null,
            ],
            'verificacion' => [
                'codigo' => null,
                'url' => $this->match('/(www\.licencia\.cl)/iu', $text),
            ],
            '_correo_trabajador_primera_hoja' => count($correos) > 1 ? end($correos) : null,
        ];
    }

    private function parseTrabajadorColumnar(string $text): array
    {
        if (preg_match('/A\.1\s+IDENTIFICACI[OÓ]N\s+DEL\s+TRABAJADOR\s*\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n+\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n+\s*(\d{6,8}\s*-\s*[0-9K])\s*\n+\s*(\d{1,3})\s*\n+\s*([MF])\b/isu', $text, $m)) {
            $apellidoPaterno = $this->match('/\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n\s*APELLIDO\s+PATERNO\b/isu', $text);
            $apellidoMaterno = $this->cleanLine($m[1]);
            $nombres = $this->cleanLine($m[2]);

            if (!$apellidoPaterno && preg_match('/A\.1\s+IDENTIFICACI[OÓ]N\s+DEL\s+TRABAJADOR\s*\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s+([A-ZÁÉÍÓÚÜÑ ]{2,})\s+([A-ZÁÉÍÓÚÜÑ ]{2,})\s+(\d{6,8}\s*-\s*[0-9K])\s+(\d{1,3})\s+([MF])/isu', $text, $mm)) {
                $apellidoPaterno = $this->cleanLine($mm[1]);
                $apellidoMaterno = $this->cleanLine($mm[2]);
                $nombres = $this->cleanLine($mm[3]);
                $m[3] = $mm[4];
                $m[4] = $mm[5];
                $m[5] = $mm[6];
            }

            $nombreCompleto = $this->cleanLine(implode(' ', array_filter([$apellidoPaterno, $apellidoMaterno, $nombres])));

            return [
                'apellido_paterno' => $apellidoPaterno,
                'apellido_materno' => $apellidoMaterno,
                'nombres' => $nombres,
                'nombre_completo' => $nombreCompleto,
                'rut' => $this->formatRut($m[3]),
                'edad' => (int) $m[4],
                'sexo' => strtoupper($m[5]) === 'F' ? 'Femenino' : 'Masculino',
            ];
        }

        $line = $this->match('/A\.1\s+IDENTIFICACI[OÓ]N\s+DEL\s+TRABAJADOR\s*\n(.+?\d{6,8}\s*-\s*[0-9K]\s+\d{1,3}\s+[MF])/isu', $text);
        return $this->parseTrabajadorLinea($line);
    }

    private function parseFechasColumnar(string $text): array
    {
        $fechaEmision = $this->dateFromBoxes($text, 'FECHA\s+EMISI[OÓ]N\s+LICENCIA');
        $fechaInicio = $this->dateFromBoxes($text, 'FECHA\s+INICIO\s+DE\s+REPOSO');

        if ((!$fechaEmision || !$fechaInicio) && preg_match('/FECHA\s+INICIO\s+DE\s+REPOSO\s+(\d{1,2})\s+(\d{1,2})\s+(\d{2,4})\s+(\d{1,2})\s+(\d{1,2})\s+(\d{2,4})/isu', $text, $m)) {
            $fechaEmision = $fechaEmision ?: $this->date($m[1] . '-' . $m[2] . '-' . (strlen($m[3]) === 2 ? '20' . $m[3] : $m[3]));
            $fechaInicio = $fechaInicio ?: $this->date($m[4] . '-' . $m[5] . '-' . (strlen($m[6]) === 2 ? '20' . $m[6] : $m[6]));
        }

        return ['fecha_emision' => $fechaEmision, 'fecha_inicio' => $fechaInicio];
    }

    private function parseProfesionalColumnar(string $text): array
    {
        if (!preg_match('/A\.5\s+IDENTIFICACI[OÓ]N\s+DEL\s+PROFESIONAL(.*?)(?:A\.6\s+DIAGNOSTICO|$)/isu', $text, $m)) {
            return [];
        }

        $section = $m[1];
        $paterno = $this->match('/^\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n\s*APELLIDO\s+PATERNO/isu', $section);
        $materno = $this->match('/APELLIDO\s+PATERNO\s*\n\s*APELLIDO\s+MATERNO\s*\n\s*-?\s*\n.*?\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n\s*(\d{6,8}\s*-\s*[0-9K])/isu', $section);
        $nombres = $this->match('/APELLIDO\s+MATERNO.*?\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n\s*(\d{6,8}\s*-\s*[0-9K])/isu', $section);
        $rut = $this->formatRut($this->match('/([0-9]{6,8}\s*-\s*[0-9K])\s*\n\s*NOMBRES\s*\n\s*RUN/isu', $section) ?: $this->match('/\b(\d{6,8}\s*-\s*[0-9K])\b/iu', $section));
        $correo = $this->match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/iu', $section);
        $direccion = $this->match('/CORREO\s+ELECTRONICO\s*\n\s*(.+?)(?=\n\s*0\s*\n|\n\s*TELEFONO|\n\s*Firmado)/isu', $section)
            ?: $this->match('/DIRECCION\s*\n\s*(.+?)(?=\n\s*FAX|\n\s*TELEFONO|\n\s*Firmado)/isu', $section);

        return [
            'nombre_completo' => $this->cleanLine(implode(' ', array_filter([$paterno, $materno, $nombres]))),
            'rut' => $rut,
            'especialidad' => $this->match('/\n\s*([^\n]+?)\s*\n\s*1\s*\n\s*TIPO\s+PROFESIONAL/isu', $section),
            'direccion' => $this->cleanLine($direccion),
            'registro_colegio' => $this->match('/REGISTRO\s+COLEGIO\s+PROFESIONAL.*?\n\s*(\d{3,}-?[0-9K]?)/isu', $section),
            'correo' => $correo,
        ];
    }



    private function parseFormularioPorLineas(string $text): array
    {
        $lines = $this->meaningfulLines($text);
        $folio = $this->folioParts($text);
        $trabajador = $this->parseTrabajadorPorLineas($lines, $text);
        $fechas = $this->parseFechasPorLineas($lines);
        $dias = $this->parseDiasPorLineas($lines, $text);
        $tipoCodigo = $this->parseCodigoPrevioAGlosa($lines, '1=Enfermedad o Accidente Comun', 1, 7)
            ?: $this->parseCodigoPrevioAGlosa($lines, '1 = ENFERMEDAD O ACCIDENTE COMUN', 1, 7);
        $reposoCodigo = $this->parseCodigoPrevioAGlosa($lines, '1=Reposo Laboral Total', 1, 2)
            ?: $this->parseCodigoPrevioAGlosa($lines, '1 = REPOSO LABORAL TOTAL', 1, 2);
        $lugarCodigo = $this->parseLugarReposoPorLineas($lines)
            ?: $this->parseCodigoPrevioAGlosa($lines, '1=Su Domicilio', 1, 3)
            ?: $this->parseCodigoPrevioAGlosa($lines, '1 = SU DOMICILIO', 1, 3);

        $direccion = $this->lineAfterLabel($lines, 'DIRECCION: CALLE;N;DEPTO;COMUNA', 8)
            ?: $this->lineAfterLabel($lines, 'DIRECCIÓN: CALLE;Nº;DEPTO;COMUNA', 8)
            ?: $this->match('/DIRECCI[OÓ]N\s*:\s*CALLE;?N[º°]?;?DEPTO;?COMUNA\s*\n\s*(.+?)(?=\n\s*(?:RECUPERABILIDAD|TELEFONO|TEL[EÉ]FONO|FIRMADO))/isu', $text);
        $telefono = $this->phoneAfterLabel($lines, 'TELEFONO (PERSONAL O DE CONTACTO)')
            ?: $this->phoneAfterLabel($lines, 'TELÉFONO (PERSONAL O DE CONTACTO)')
            ?: $this->match('/TEL[EÉ]FONO\s*\(PERSONAL\s+O\s+DE\s+CONTACTO\).*?\n\s*([0-9+\-\s]{6,})/isu', $text);

        $entidad = $this->match('/\d{7,8}\s*-\s*[0-9K]\s*:\s*([A-ZÁÉÍÓÚÜÑ0-9 .\-]+?)(?=\s+\d{6,8}\s*-\s*[0-9K]|\n|$)/isu', $text)
            ?: $this->match('/CODIGO\s+ENTIDAD.*?\n.*?:\s*([^\n]+?)\s+\d{6,8}-[0-9K]/isu', $text);
        [$sistemaSalud, $institucionSalud] = $this->parseSistemaSalud($entidad, $text);

        $estado = $this->match('/\b([0-9]+\s*-\s*[A-ZÁÉÍÓÚÜÑ ]+)\s+\d{2}-\d{2}-\d{2}\s+\d{1,2}:\d{2}\s+\d{4,}/isu', $text)
            ?: $this->match('/ESTADO\s+LICENCIA.*?\n\s*([0-9]+\s*-\s*[A-ZÁÉÍÓÚÜÑ ]+)/isu', $text);
        $rutEmpleador = $this->formatRut($this->match('/\b(61\s*981\s*100\s*-\s*3|61981100\s*-\s*3)\b/iu', $text)
            ?: $this->match('/CODIGO\s+TRAMITACION\s+RUT\s+EMPLEADOR.*?\n.*?\s(\d{7,8}\s*-\s*[0-9K])(?:\s|$)/isu', $text));
        $profesional = $this->parseProfesionalPorLineas($lines, $text);
        $correos = $this->emails($text);
        $fechaTermino = null;
        if (($fechas['fecha_inicio'] ?? null) && $dias) {
            try {
                $fechaTermino = Carbon::parse($fechas['fecha_inicio'])->addDays(((int) $dias) - 1)->format('Y-m-d');
            } catch (\Throwable $e) {
                $fechaTermino = null;
            }
        }

        return [
            'folio' => $folio['folio'] ?? null,
            'numero_licencia' => $folio['numero_licencia'] ?? null,
            'tipo_ingreso_licencia' => $folio['tipo_ingreso_licencia'] ?? null,
            'cuerpo_licencia' => $folio['cuerpo_licencia'] ?? null,
            'dv_licencia' => $folio['dv_licencia'] ?? null,
            'fecha_otorgamiento' => $fechas['fecha_emision'] ?? null,
            'fecha_otorgamiento_hora' => null,
            'entidad_pronunciamiento' => $entidad,
            'sistema_salud' => $sistemaSalud,
            'institucion_salud' => $institucionSalud,
            'estado' => $estado,
            'trabajador' => [
                'nombre' => $trabajador['nombres'] ?? null,
                'apellido_paterno' => $trabajador['apellido_paterno'] ?? null,
                'apellido_materno' => $trabajador['apellido_materno'] ?? null,
                'nombre_completo' => $trabajador['nombre_completo'] ?? null,
                'rut' => $trabajador['rut'] ?? null,
                'edad' => $trabajador['edad'] ?? null,
                'sexo' => $trabajador['sexo'] ?? null,
            ],
            'licencia' => [
                'tipo_codigo' => $tipoCodigo,
                'tipo_glosa' => $tipoCodigo ? $this->tipoLicenciaGlosa($tipoCodigo) : null,
                'fecha_inicio_reposo' => $fechas['fecha_inicio'] ?? null,
                'dias' => $dias,
                'fecha_termino' => $fechaTermino,
                'tipo_reposo' => $this->reposoGlosa($reposoCodigo),
                'lugar_reposo' => $this->lugarReposoGlosa($lugarCodigo),
                'direccion_reposo' => $this->cleanLine($direccion),
                'telefono' => $this->cleanLine($telefono),
            ],
            'profesional' => [
                'nombre_completo' => $profesional['nombre_completo'] ?? null,
                'rut' => $profesional['rut'] ?? null,
                'especialidad' => $profesional['especialidad'] ?? null,
                'direccion' => $profesional['direccion'] ?? null,
                'registro_colegio' => $profesional['registro_colegio'] ?? null,
                'correo' => $profesional['correo'] ?? ($correos[0] ?? null),
            ],
            'empleador' => [
                'nombre' => null,
                'rut' => $rutEmpleador,
                'fecha_recepcion' => null,
                'fecha_envio_pronunciamiento' => null,
            ],
            'verificacion' => [
                'codigo' => null,
                'url' => $this->match('/(www\.licencia\.cl)/iu', $text),
            ],
            '_correo_trabajador_primera_hoja' => count($correos) > 1 ? end($correos) : ($correos[0] ?? null),
        ];
    }

    private function meaningfulLines(string $text): array
    {
        $raw = preg_split('/\R+/u', $text) ?: [];
        $lines = [];
        foreach ($raw as $line) {
            $line = $this->cleanLine($line);
            if (!$line || $this->isNoiseLine($line)) {
                continue;
            }
            $lines[] = $line;
        }
        return array_values($lines);
    }

    private function isNoiseLine(string $line): bool
    {
        $line = trim($line);
        if ($line === '') return true;
        return (bool) preg_match('/^(DO|CU|ME|NT|O|NO|V[ÁA]|LID|RA|TR|[ÁA]M|ITE)$/iu', $line);
    }

    private function parseTrabajadorPorLineas(array $lines, string $text): array
    {
        if (preg_match('/A\.1\s+IDENTIFICACI[OÓ]N\s+DEL\s+TRABAJADOR\s*\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n\s*([A-ZÁÉÍÓÚÜÑ ]{2,})\s*\n\s*(\d{6,8}\s*-\s*[0-9K])\s*\n\s*(\d{1,3})\s*\n\s*([MF])\s*\n\s*APELLIDO\s+PATERNO/isu', $text, $m)) {
            $apellidoPaterno = $this->cleanLine($m[1]);
            $apellidoMaterno = $this->cleanLine($m[2]);
            $nombres = $this->cleanLine($m[3]);

            return [
                'apellido_paterno' => $apellidoPaterno,
                'apellido_materno' => $apellidoMaterno,
                'nombres' => $nombres,
                'nombre_completo' => $this->cleanLine($apellidoPaterno . ' ' . $apellidoMaterno . ' ' . $nombres),
                'rut' => $this->formatRut($m[4]),
                'edad' => (int) $m[5],
                'sexo' => strtoupper($m[6]) === 'F' ? 'Femenino' : 'Masculino',
            ];
        }

        $lineParsed = [];
        if ($line = $this->match('/A\.1\s+IDENTIFICACI[OÓ]N\s+DEL\s+TRABAJADOR\s*\n(.+?\d{6,8}\s*-\s*[0-9K]\s+\d{1,3}\s+[MF])/isu', $text)) {
            $lineParsed = $this->parseTrabajadorLinea($line);
        }

        $start = $this->findLineIndex($lines, 'A.1 IDENTIFICACION DEL TRABAJADOR')
            ?? $this->findLineIndex($lines, 'A.1 IDENTIFICACIÓN DEL TRABAJADOR')
            ?? 0;
        $end = $this->findLineIndex($lines, 'A.5 IDENTIFICACION DEL PROFESIONAL')
            ?? $this->findLineIndex($lines, 'A.5 IDENTIFICACIÓN DEL PROFESIONAL')
            ?? count($lines);

        $idxMaterno = $this->findLineIndex($lines, 'APELLIDO MATERNO', $start, $end);
        $idxPaterno = $this->findLineIndex($lines, 'APELLIDO PATERNO', $start, $end);
        $vals = $idxMaterno !== null ? $this->previousMeaningfulValues($lines, $idxMaterno, 5) : [];
        $apellidoPaterno = $idxPaterno !== null ? $this->previousMeaningful($lines, $idxPaterno) : null;
        $apellidoMaterno = $vals[0] ?? null;
        $nombres = $vals[1] ?? null;
        $rut = $this->formatRut($vals[2] ?? null);
        $edad = isset($vals[3]) && preg_match('/^\d{1,3}$/', $vals[3]) ? (int) $vals[3] : null;
        $sexoRaw = $vals[4] ?? null;

        if (!$rut) {
            for ($i = $start; $i < $end; $i++) {
                if (preg_match('/^(\d{6,8}\s*-\s*[0-9K])$/iu', $lines[$i], $m)) {
                    $rut = $this->formatRut($m[1]);
                    $nombres = $nombres ?: $this->previousMeaningful($lines, $i);
                    $edad = $edad ?: $this->integer($this->nextNumericLine($lines, $i));
                    $sexoRaw = $sexoRaw ?: $this->nextSexLine($lines, $i);
                    break;
                }
            }
        }

        $sexo = null;
        if ($sexoRaw && preg_match('/^[MF]$/iu', $sexoRaw)) {
            $sexo = strtoupper($sexoRaw) === 'F' ? 'Femenino' : 'Masculino';
        }

        if (!$apellidoPaterno && !empty($lineParsed['apellido_paterno'])) $apellidoPaterno = $lineParsed['apellido_paterno'];
        if (!$apellidoMaterno && !empty($lineParsed['apellido_materno'])) $apellidoMaterno = $lineParsed['apellido_materno'];
        if (!$nombres && !empty($lineParsed['nombres'])) $nombres = $lineParsed['nombres'];
        if (!$rut && !empty($lineParsed['rut'])) $rut = $lineParsed['rut'];
        if (!$edad && !empty($lineParsed['edad'])) $edad = $lineParsed['edad'];
        if (!$sexo && !empty($lineParsed['sexo'])) $sexo = $lineParsed['sexo'];

        $nombreCompleto = $this->cleanLine(implode(' ', array_filter([$apellidoPaterno, $apellidoMaterno, $nombres])))
            ?: ($lineParsed['nombre_completo'] ?? null);

        return [
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'nombres' => $nombres,
            'nombre_completo' => $nombreCompleto,
            'rut' => $rut,
            'edad' => $edad,
            'sexo' => $sexo,
        ];
    }

    private function parseLugarReposoPorLineas(array $lines): ?int
    {
        $idx = $this->findLineIndex($lines, 'LUGAR DE REPOSO');
        if ($idx === null) return null;
        for ($i = $idx + 1; $i < min(count($lines), $idx + 18); $i++) {
            if (preg_match('/^[123]$/', $lines[$i])) {
                return (int) $lines[$i];
            }
        }
        return null;
    }

    private function parseFechasPorLineas(array $lines): array
    {
        return [
            'fecha_emision' => $this->dateFromLineLabel($lines, 'FECHA EMISION LICENCIA'),
            'fecha_inicio' => $this->dateFromLineLabel($lines, 'FECHA INICIO DE REPOSO'),
        ];
    }

    private function dateFromLineLabel(array $lines, string $label): ?string
    {
        $idx = $this->findLineIndex($lines, $label);
        if ($idx === null) {
            return null;
        }

        $nums = [];
        for ($i = $idx + 1; $i < min(count($lines), $idx + 12); $i++) {
            if (preg_match('/^\d{1,4}$/', $lines[$i])) {
                $nums[] = $lines[$i];
                if (count($nums) >= 3) {
                    break;
                }
            }
        }

        if (count($nums) < 3) {
            return null;
        }

        $year = strlen($nums[2]) === 2 ? '20' . $nums[2] : $nums[2];
        return $this->date(str_pad($nums[0], 2, '0', STR_PAD_LEFT) . '-' . str_pad($nums[1], 2, '0', STR_PAD_LEFT) . '-' . $year);
    }

    private function parseDiasPorLineas(array $lines, string $text): ?int
    {
        $idx = $this->findLineIndex($lines, 'N DE DIAS');
        if ($idx !== null) {
            for ($i = $idx + 1; $i < min(count($lines), $idx + 12); $i++) {
                if (preg_match('/^\d{1,3}$/', $lines[$i])) return (int) $lines[$i];
            }
        }
        return $this->integer($this->match('/N\s+DE\s+DIAS.*?\n\s*(\d{1,3})/isu', $text));
    }

    private function parseCodigoPrevioAGlosa(array $lines, string $glosa, int $min, int $max): ?int
    {
        $idx = $this->findLineIndex($lines, $glosa);
        if ($idx === null) return null;
        for ($i = $idx - 1; $i >= max(0, $idx - 18); $i--) {
            if (preg_match('/^([0-9])$/', $lines[$i], $m)) {
                $code = (int) $m[1];
                if ($code >= $min && $code <= $max) return $code;
            }
        }
        return null;
    }

    private function lineAfterLabel(array $lines, string $label, int $lookAhead = 8): ?string
    {
        $idx = $this->findLineIndex($lines, $label);
        if ($idx === null) return null;
        for ($i = $idx + 1; $i < min(count($lines), $idx + $lookAhead + 1); $i++) {
            if (!$this->isKnownLabel($lines[$i]) && !preg_match('/^[0-9]$/', $lines[$i])) {
                return $lines[$i];
            }
        }
        return null;
    }

    private function phoneAfterLabel(array $lines, string $label): ?string
    {
        $idx = $this->findLineIndex($lines, $label);
        if ($idx === null) return null;
        for ($i = $idx + 1; $i < min(count($lines), $idx + 20); $i++) {
            if (preg_match('/^[0-9+\- ]{7,}$/', $lines[$i])) {
                return $lines[$i];
            }
        }
        return null;
    }

    private function parseProfesionalPorLineas(array $lines, string $text): array
    {
        $start = $this->findLineIndex($lines, 'A.5 IDENTIFICACION DEL PROFESIONAL')
            ?? $this->findLineIndex($lines, 'A.5 IDENTIFICACIÓN DEL PROFESIONAL');
        $end = $this->findLineIndex($lines, 'A.6 DIAGNOSTICO')
            ?? $this->findLineIndex($lines, 'A.6 DIAGNÓSTICO')
            ?? count($lines);
        if ($start === null) return [];

        $rut = null;
        $rutIdx = null;
        for ($i = $start; $i < $end; $i++) {
            if (preg_match('/^(\d{6,8}\s*-\s*[0-9K])$/iu', $lines[$i], $m)) {
                $rut = $this->formatRut($m[1]);
                $rutIdx = $i;
                break;
            }
        }
        $nombres = $rutIdx !== null ? $this->previousMeaningful($lines, $rutIdx) : null;
        $paterno = null;
        $materno = null;
        $idxPat = $this->findLineIndex($lines, 'APELLIDO PATERNO', $start, $end);
        if ($idxPat !== null) {
            $vals = $this->previousMeaningfulValues($lines, $idxPat, 2);
            $paterno = $vals[0] ?? null;
            $materno = $vals[1] ?? null;
        }
        $correo = null;
        for ($i = $start; $i < $end; $i++) {
            if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $lines[$i], $m)) {
                $correo = $m[0];
                break;
            }
        }
        $direccion = null;
        $idxCorreo = $correo ? $this->findLineIndex($lines, $correo, $start, $end) : null;
        if ($idxCorreo !== null) {
            $vals = [];
            for ($i = $idxCorreo + 1; $i < min($end, $idxCorreo + 8); $i++) {
                if ($this->isKnownLabel($lines[$i]) || preg_match('/^[0-9]$/', $lines[$i])) continue;
                $vals[] = $lines[$i];
            }
            $direccion = $this->cleanLine(implode(' ', $vals));
        }

        return [
            'nombre_completo' => $this->cleanLine(implode(' ', array_filter([$paterno, $materno, $nombres]))),
            'rut' => $rut,
            'especialidad' => null,
            'direccion' => $direccion,
            'registro_colegio' => null,
            'correo' => $correo,
        ];
    }

    private function findLineIndex(array $lines, string $needle, int $start = 0, ?int $end = null): ?int
    {
        $end = $end ?? count($lines);
        $needleNorm = $this->asciiUpper($needle);
        for ($i = max(0, $start); $i < min(count($lines), $end); $i++) {
            if (str_contains($this->asciiUpper($lines[$i]), $needleNorm)) {
                return $i;
            }
        }
        return null;
    }

    private function previousMeaningful(array $lines, int $idx): ?string
    {
        for ($i = $idx - 1; $i >= 0; $i--) {
            if (!$this->isKnownLabel($lines[$i]) && !preg_match('/^[-]$/', $lines[$i])) {
                return $lines[$i];
            }
        }
        return null;
    }

    private function previousMeaningfulValues(array $lines, int $idx, int $count): array
    {
        $values = [];
        for ($i = $idx - 1; $i >= 0 && count($values) < $count; $i--) {
            if ($this->isKnownLabel($lines[$i]) || preg_match('/^[-]$/', $lines[$i])) continue;
            array_unshift($values, $lines[$i]);
        }
        return $values;
    }

    private function nextNumericLine(array $lines, int $idx): ?string
    {
        for ($i = $idx + 1; $i < min(count($lines), $idx + 8); $i++) {
            if (preg_match('/^\d{1,3}$/', $lines[$i])) return $lines[$i];
        }
        return null;
    }

    private function nextSexLine(array $lines, int $idx): ?string
    {
        for ($i = $idx + 1; $i < min(count($lines), $idx + 10); $i++) {
            if (preg_match('/^[MF]$/iu', $lines[$i])) return strtoupper($lines[$i]);
        }
        return null;
    }

    private function isKnownLabel(string $line): bool
    {
        return (bool) preg_match('/^(APELLIDO|NOMBRES|RUN|EDAD|SEXO|DIA|MES|ANO|AÑO|FECHA|N\s+DE|A\.\d|SECCION|SECCIÓN|RUT|CODIGO|CÓDIGO|ESTADO|TELEFONO|TELÉFONO|DIRECCION|DIRECCIÓN|CORREO|ESPECIALIDAD|TIPO|REGISTRO|FIRMA|FIRMADO|DOCUMENTO|MINISTERIO|LICENCIA|OPERADOR|LUGAR|JUSTIFICAR|RECUPERABILIDAD|INICIO|TRAYECTO|TRABAJO|DIAGNOSTICO|DIAGNÓSTICO|INFORMACIÓN|INFORMACION|CÓDIGO|CODIGO)/iu', trim($line));
    }

    private function asciiUpper(string $value): string
    {
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        return strtoupper($value);
    }

    private function toFormData(array $licencia, string $text): array
    {
        $rut = RutNormalizer::normalize($licencia['trabajador']['rut'] ?? null);
        $empleadorRut = $this->formatRut($licencia['empleador']['rut'] ?? null);

        $fechaInicio = $licencia['licencia']['fecha_inicio_reposo'] ?? null;
        $dias = $licencia['licencia']['dias'] ?? null;
        $fechaTermino = $licencia['licencia']['fecha_termino'] ?? null;

        if (!$fechaTermino && $fechaInicio && $dias) {
            try {
                $fechaTermino = Carbon::parse($fechaInicio)->addDays(((int) $dias) - 1)->format('Y-m-d');
            } catch (\Throwable $e) {
                $fechaTermino = null;
            }
        }

        return [
            'tipo_ingreso_licencia' => $licencia['tipo_ingreso_licencia'] ?? null,
            'cuerpo_licencia' => $licencia['cuerpo_licencia'] ?? null,
            'dv_licencia' => $licencia['dv_licencia'] ?? null,
            'folio_licencia' => $licencia['numero_licencia'] ?? null,
            'rut_funcionario' => $rut['rut'] ?? null,
            'dv_funcionario' => $rut['dv'] ?? null,
            'rut_normalizado' => $rut['normalizado'] ?? null,
            'rut_formateado' => $rut['formateado'] ?? ($licencia['trabajador']['rut'] ?? null),
            'nombre_funcionario' => $licencia['trabajador']['nombre_completo'] ?? null,
            'apellido_paterno' => $licencia['trabajador']['apellido_paterno'] ?? null,
            'apellido_materno' => $licencia['trabajador']['apellido_materno'] ?? null,
            'nombres' => $licencia['trabajador']['nombre'] ?? null,
            'sexo' => $licencia['trabajador']['sexo'] ?? null,
            'edad' => $licencia['trabajador']['edad'] ?? null,
            'fecha_emision' => $licencia['fecha_otorgamiento'] ?? null,
            'fecha_inicio' => $fechaInicio,
            'fecha_termino' => $fechaTermino,
            'dias_solicitados' => $dias,
            'tipo_licencia' => $licencia['licencia']['tipo_codigo'] ?? null,
            'tipo_licencia_glosa' => $licencia['licencia']['tipo_glosa'] ?? null,
            'sistema_salud' => $licencia['sistema_salud'] ?? null,
            'institucion_salud' => $licencia['institucion_salud'] ?? null,
            'tipo_reposo' => $licencia['licencia']['tipo_reposo'] ?? null,
            'lugar_reposo' => $licencia['licencia']['lugar_reposo'] ?? null,
            'direccion_reposo' => $licencia['licencia']['direccion_reposo'] ?? null,
            'telefono' => $licencia['licencia']['telefono'] ?? null,
            'correo_trabajador' => $licencia['_correo_trabajador_primera_hoja'] ?? $this->extractCorreoTrabajador($text),
            'rut_empleador' => $empleadorRut,
            'nombre_empleador' => $licencia['empleador']['nombre'] ?? null,
            'fecha_recepcion' => $licencia['empleador']['fecha_recepcion'] ?? null,
            'estado_actual' => $licencia['estado'] ?? 'Ingresada',
            'entidad_pronunciamiento' => $licencia['entidad_pronunciamiento'] ?? null,
            'codigo_verificacion' => $licencia['verificacion']['codigo'] ?? null,
        ];
    }

    private function receiptSection(string $text): ?string
    {
        if (preg_match('/Comprobante de Licencia M[eé]dica Electr[oó]nica(.*)$/isu', $text, $m)) return trim($m[1]);
        return null;
    }

    private function folioParts(string $text): array
    {
        $patterns = [
            '/N[°º]?\s*([1-4])\s+(\d{5,12})\s*-\s*([0-9K])/iu',
            '/N[°º]?\s*([1-4])\s*Folio\s*:\s*(\d{5,12})\s*-\s*([0-9K])/iu',
            '/N[°º]?\s*([1-4])\s+Folio\s*:\s*(\d{5,12})\s*-\s*([0-9K])/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return [
                    'tipo_ingreso_licencia' => $m[1],
                    'cuerpo_licencia' => $m[2],
                    'dv_licencia' => strtoupper($m[3]),
                    'folio' => $m[2] . '-' . strtoupper($m[3]),
                    'numero_licencia' => $m[1] . '-' . $m[2] . '-' . strtoupper($m[3]),
                ];
            }
        }

        if (preg_match('/Folio\s*:?[\s\n]*(\d{5,12})\s*-\s*([0-9K])/iu', $text, $m)) {
            return [
                'tipo_ingreso_licencia' => null,
                'cuerpo_licencia' => $m[1],
                'dv_licencia' => strtoupper($m[2]),
                'folio' => $m[1] . '-' . strtoupper($m[2]),
                'numero_licencia' => null,
            ];
        }

        return [];
    }

    private function field(string $text, string $label): ?string
    {
        $labelPattern = preg_quote($label, '/');
        $pattern = '/(?:^|\n)\s*' . $labelPattern . '\s*(?:\n\s*)?:\s*(?:\n\s*)?(.*?)(?=\n\s*(?:[A-ZÁÉÍÓÚÜÑ][A-Za-zÁÉÍÓÚÜÑáéíóúüñ\. ]{1,45}\s*(?:\n\s*)?:|\d+\.\s|$))/isu';

        if (preg_match($pattern, $text, $m)) {
            $value = $this->cleanLine($m[1]);
            return $value !== '' ? $value : null;
        }

        $patternInline = '/' . $labelPattern . '\s*:\s*([^\n]+)/iu';
        if (preg_match($patternInline, $text, $m)) {
            $value = $this->cleanLine($m[1]);
            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function section(string $text, string $startPattern, string $endPattern): ?string
    {
        if (preg_match('/' . $startPattern . '(.*?)(' . $endPattern . ')/isu', $text, $m)) return trim($m[1]);
        if (preg_match('/' . $startPattern . '(.*)$/isu', $text, $m)) return trim($m[1]);
        return null;
    }


    private function parseSistemaSalud(?string $entidadPronunciamiento, string $text): array
    {
        $entidad = $this->cleanLine($entidadPronunciamiento);
        $isapres = 'ISAPRE|BANMEDICA|BANM[ÉE]DICA|VIDA TRES|CONSALUD|CRUZ BLANCA|COLMENA|NUEVA MASVIDA|MASVIDA|ESENCIAL';
        $hayFonasa = $entidad && preg_match('/FONASA/iu', $entidad);
        $hayIsapre = $entidad && preg_match('/' . $isapres . '/iu', $entidad);

        if ($hayFonasa) {
            return ['FONASA', 'FONASA'];
        }

        if ($hayIsapre) {
            return ['ISAPRE', $entidad];
        }

        if (preg_match('/Otorgada\s+para\s+cotizante\s+FONASA|:\s*FONASA\b|CODIGO\s+ENTIDAD.*?FONASA/isu', $text)) {
            return ['FONASA', 'FONASA'];
        }

        if (preg_match('/(?:Otorgada\s+para\s+cotizante\s+|:\s*)(' . $isapres . '[A-ZÁÉÍÓÚÜÑ0-9 .\-]*)/isu', $text, $m)) {
            return ['ISAPRE', $this->cleanLine($m[1])];
        }

        if ($entidad) {
            return [null, $entidad];
        }

        return [null, null];
    }

    private function mergeMissing(array $base, array $fallback): array
    {
        foreach ($fallback as $key => $value) {
            if (is_array($value)) {
                $base[$key] = $this->mergeMissing($base[$key] ?? [], $value);
                continue;
            }

            if ((!array_key_exists($key, $base) || $base[$key] === null || $base[$key] === '') && $value !== null && $value !== '') {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function parseTrabajadorLinea(?string $line): array
    {
        $line = $this->cleanLine($line) ?: '';
        if (!preg_match('/^(.+?)\s+(\d{6,8}\s*-\s*[0-9K])\s+(\d{1,3})\s+([MF])$/iu', $line, $m)) {
            return [];
        }

        $nombreCompleto = $this->cleanLine($m[1]);
        $partes = $this->splitNombre((string) $nombreCompleto);

        return [
            'nombre_completo' => $nombreCompleto,
            'apellido_paterno' => $partes['apellido_paterno'] ?? null,
            'apellido_materno' => $partes['apellido_materno'] ?? null,
            'nombres' => $partes['nombres'] ?? null,
            'rut' => $this->formatRut($m[2]),
            'edad' => (int) $m[3],
            'sexo' => strtoupper($m[4]) === 'F' ? 'Femenino' : 'Masculino',
        ];
    }

    private function parseNombreRutLinea(?string $line): array
    {
        $line = $this->cleanLine($line) ?: '';
        if (!preg_match('/^(.+?)\s+(\d{6,8}\s*-\s*[0-9K])$/iu', $line, $m)) {
            return [];
        }

        return [
            'nombre_completo' => $this->cleanLine($m[1]),
            'rut' => $this->formatRut($m[2]),
        ];
    }

    private function dateFromBoxes(string $text, string $labelPattern): ?string
    {
        if (preg_match('/' . $labelPattern . '\s*\n\s*(\d{1,2})\s*\n\s*Dia\s*\n\s*(\d{1,2})\s*\n\s*Mes\s*\n\s*(\d{2,4})/isu', $text, $m)) {
            $year = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
            return $this->date(str_pad($m[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . $year);
        }

        if (preg_match('/' . $labelPattern . '\s+(\d{2})(\d{2})(\d{4})/isu', $text, $m)) {
            return $this->date($m[1] . '-' . $m[2] . '-' . $m[3]);
        }

        return null;
    }

    private function tipoLicenciaGlosa(int $codigo): ?string
    {
        return [
            1 => 'Enfermedad o Accidente Común',
            2 => 'Prórroga Medicina Preventiva',
            3 => 'Licencia Maternal Pre y Post Natal',
            4 => 'Enfermedad Grave Hijo Menor de 1 Año',
            5 => 'Accidente del Trabajo o del Trayecto',
            6 => 'Enfermedad Profesional',
            7 => 'Patología del Embarazo',
        ][$codigo] ?? null;
    }

    private function reposoGlosa(?int $codigo): ?string
    {
        return [1 => 'Reposo Laboral Total', 2 => 'Reposo Laboral Parcial'][$codigo] ?? null;
    }

    private function lugarReposoGlosa(?int $codigo): ?string
    {
        return [1 => 'Su Domicilio', 2 => 'Hospital', 3 => 'Otro Domicilio'][$codigo] ?? null;
    }

    private function emails(string $text): array
    {
        if (!preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text, $m)) {
            return [];
        }

        return array_values(array_unique($m[0]));
    }

    private function parseTipoLicencia(?string $value): array
    {
        $value = $this->cleanLine($value);
        if (!$value) return [null, null];
        if (preg_match('/^\s*([1-7])\s*\.?\s*(.+)$/u', $value, $m)) {
            return [(int) $m[1], trim($m[2])];
        }
        return [null, $value];
    }

    private function parseDateTime(?string $value): array
    {
        $value = $this->cleanLine($value);
        if (!$value) return [null, null];

        $date = $this->date($value);
        $hour = null;
        if (preg_match('/(\d{1,2}:\d{2})/', $value, $m)) $hour = $m[1];

        return [$date, $hour];
    }

    private function splitNombre(string $nombreCompleto): array
    {
        $nombreCompleto = $this->cleanLine($nombreCompleto);
        $result = ['apellido_paterno' => null, 'apellido_materno' => null, 'nombres' => null];

        if ($nombreCompleto === '') return $result;

        if (str_contains($nombreCompleto, ',')) {
            [$apellidos, $nombres] = array_map('trim', explode(',', $nombreCompleto, 2));
            $partes = preg_split('/\s+/', $apellidos) ?: [];
            $result['apellido_paterno'] = $partes[0] ?? null;
            $result['apellido_materno'] = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : null;
            $result['nombres'] = $nombres ?: null;
            return $result;
        }

        $partes = preg_split('/\s+/', $nombreCompleto) ?: [];
        $result['apellido_paterno'] = $partes[0] ?? null;
        $result['apellido_materno'] = $partes[1] ?? null;
        $result['nombres'] = count($partes) > 2 ? implode(' ', array_slice($partes, 2)) : null;
        return $result;
    }

    private function integer(?string $value): ?int
    {
        if (!$value) return null;
        return preg_match('/\d+/', $value, $m) ? (int) $m[0] : null;
    }

    private function formatRut(?string $rut): ?string
    {
        if (!$rut) return null;
        $rut = preg_replace('/[^0-9kK]/', '', $rut) ?: '';
        if (strlen($rut) < 2) return null;
        $dv = strtoupper(substr($rut, -1));
        $numero = substr($rut, 0, -1);
        return number_format((int) $numero, 0, '', '.') . '-' . $dv;
    }

    private function cleanLine(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;
        return trim($value, " \t\n\r\0\x0B:");
    }

    private function match(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $m) ? $this->cleanLine($m[1]) : null;
    }

    private function date(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        if (preg_match('/(\d{2})[-\/](\d{2})[-\/](\d{2,4})/', $value, $m)) {
            $value = $m[1] . '-' . $m[2] . '-' . (strlen($m[3]) === 2 ? '20' . $m[3] : $m[3]);
        } elseif (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $m)) {
            $value = $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-m-y', 'd/m/y', 'dmY', 'dmy'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                // next
            }
        }

        return null;
    }

    private function extractCorreoTrabajador(string $text): ?string
    {
        $section = $this->section($text, 'A\.C COMPLEMENTO', '$') ?: $text;
        if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/iu', $section, $m)) return $m[1];
        return null;
    }

    private function advertencias(array $data, string $text): array
    {
        $warnings = [];

        if (!class_exists(Parser::class) && trim($text) === '') {
            $warnings[] = 'No se detecta la librería smalot/pdfparser y el extractor PHP no pudo obtener texto. Ejecute composer require smalot/pdfparser o ingrese manualmente si el PDF es escaneado.';
        }

        foreach ([
            'tipo_ingreso_licencia' => 'tipo de ingreso',
            'cuerpo_licencia' => 'cuerpo de licencia',
            'dv_licencia' => 'DV de licencia',
            'rut_formateado' => 'RUT funcionario',
            'nombre_funcionario' => 'nombre funcionario',
            'fecha_inicio' => 'fecha inicio',
            'dias_solicitados' => 'días solicitados',
        ] as $key => $label) {
            if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') $warnings[] = 'No se pudo extraer ' . $label . '; debe completarse manualmente.';
        }

        if (trim($text) === '') $warnings[] = 'El PDF no contiene texto embebido. Si es escaneado, debe ingresarse manualmente.';

        return $warnings;
    }
}
