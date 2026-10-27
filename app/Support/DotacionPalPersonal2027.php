<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Services\Padron\PadronConciliador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Referencia histórica solicitada para AE/VTF; no usa el alcance del padrón vigente. */
class DotacionPalPersonal2027
{
    public function fuente(): Builder
    {
        return ReemplazoPersonal::query()->where('anio', 2026)->where('mes', 8)
            ->whereRaw("UPPER(TRIM(COALESCE(estatuto, ''))) IN (?, ?)", ['AAEE', 'JUNJI'])
            ->sinReemplazoSuplencia();
    }

    public function disponible(): bool
    {
        return Schema::hasTable('reemplazos_personal');
    }

    public function construir(Establecimiento $ee): array
    {
        $salida = ['asistentes' => [], 'vtf' => []];
        if (! $this->disponible()) { return $salida; }
        $grupos = []; $vistos = [];
        foreach ($this->fuente()->where('rbd', $ee->rbd)->orderBy('id')->cursor() as $persona) {
            $rut = PadronConciliador::rut($persona->rut);
            if ($rut === '') {
                throw ValidationException::withMessages(['pal' => 'El padrón de agosto de 2026 del RBD '.$ee->rbd.' contiene un funcionario sin RUT. Revise la fuente.']);
            }
            $origen = $persona->toArray();
            $cargo = PadronConciliador::text($persona->escalafon);
            $financiamiento = PadronConciliador::text($persona->financiamiento);
            $tipo = PadronConciliador::text($persona->tipocontrato);
            $esVtf = (bool) $ee->sala_cuna || PadronConciliador::text($persona->estatuto) === 'JUNJI'
                || str_contains($financiamiento, 'JUNJI');
            $hoja = $esVtf ? 'vtf' : 'asistentes';
            // El importador admite representaciones históricas del RUT. No sumar
            // dos veces una misma línea contractual con distinto formato de RUT.
            $identidad = json_encode([$rut, $hoja, $tipo, $cargo, $financiamiento,
                $origen['fecha_ingreso'] ?? null, $origen['fecha_termino'] ?? null,
                $persona->jornada, $persona->jornada_basica, $persona->jornada_media], JSON_THROW_ON_ERROR);
            if (isset($vistos[$identidad])) { continue; }
            $vistos[$identidad] = true;
            $calidad = str_contains($tipo, 'INDEFINID') ? 'Indefinido'
                : ((str_contains($tipo, 'PLAZO FIJO') || $tipo === 'CONTRATA') ? 'Plazo Fijo' : 'Otros');
            $nivel = $this->nivel($ee, $persona);
            $key = json_encode([$rut, $hoja, $cargo, $calidad, $esVtf ? null : $nivel], JSON_THROW_ON_ERROR);
            $horas = $persona->jornada ?? (($persona->jornada_basica ?? 0) + ($persona->jornada_media ?? 0));
            if ($horas < 0) { throw new \RuntimeException('El padrón PAL contiene una jornada negativa.'); }
            $grupo = $grupos[$key] ?? ['hoja' => $hoja, 'rut' => $rut, 'cargo' => $cargo, 'calidad' => $calidad,
                'nivel' => $nivel, 'horas' => 0, 'pie' => 0, 'sep' => 0, 'nombres' => [], 'tipos' => [], 'fondos' => []];
            $grupo['horas'] += $horas;
            if ($financiamiento === 'PIE') { $grupo['pie'] += $horas; }
            if ($financiamiento === 'SEP') { $grupo['sep'] += $horas; }
            $grupo['nombres'][(string) $persona->nombre] = true;
            $grupo['tipos'][$tipo ?: 'Sin tipo informado'] = true;
            $grupo['fondos'][$financiamiento ?: 'Sin financiamiento informado'] = true;
            $grupos[$key] = $grupo;
        }
        foreach ($grupos as $grupo) {
            $nota = 'Origen: padrón 08/2026. Nombre: '.implode(' / ', array_keys($grupo['nombres']))
                .'. Cargo de origen: '.($grupo['cargo'] ?: 'Sin informar')
                .'. Contrato: '.implode(', ', array_keys($grupo['tipos']))
                .'. Financiamiento: '.implode(', ', array_keys($grupo['fondos']))
                .'. Horas lectivas no informadas en el padrón.';
            $rut = substr($grupo['rut'], 0, -1).'-'.substr($grupo['rut'], -1);
            $base = ['A' => $rut, 'B' => (string) $ee->nombre_establecimiento, 'C' => (string) $ee->rbd];
            if ($grupo['hoja'] === 'vtf') {
                [$cargo, $funcion, $estamento] = $this->vtf($grupo['cargo']);
                $salida['vtf'][] = $base + ['D' => $estamento, 'E' => $cargo, 'F' => $funcion,
                    'G' => $grupo['calidad'], 'H' => $grupo['horas'], 'I' => null, 'J' => $nota];
            } else {
                $funcion = match ($grupo['cargo']) {
                    'ADMINISTRATIVO', 'ADMINISTRATIVA' => 'Administrativo',
                    'AUXILIAR', 'AUXILIARES' => 'Auxiliares',
                    'TECNICO', 'TECNICA', 'TECNICOS' => 'Técnicos',
                    'PROFESIONAL', 'PROFESIONALES' => 'Profesionales',
                    default => null,
                };
                if ($funcion === null) { $nota .= ' Función PAL pendiente de clasificación.'; }
                if ($grupo['nivel'] === null) { $nota .= ' Nivel no individualizado en el padrón.'; }
                $salida['asistentes'][] = $base + ['D' => $grupo['nivel'], 'E' => 'Asistente de la Educación',
                    'F' => $grupo['cargo'], 'G' => $funcion, 'H' => $grupo['calidad'], 'I' => $grupo['horas'],
                    'J' => null, 'K' => $grupo['pie'], 'L' => $grupo['sep'], 'M' => $nota];
            }
        }
        return $salida;
    }

    private function nivel(Establecimiento $ee, ReemplazoPersonal $persona): ?string
    {
        $niveles = array_filter(['Educación Parvularia' => (bool) $ee->pre_escolar,
            'Educación Básica' => (bool) $ee->basica, 'Educación Media' => (bool) $ee->media]);
        if (count($niveles) === 1) { return array_key_first($niveles); }
        if ($persona->jornada_media > 0 && ! $persona->jornada_basica) { return 'Educación Media'; }
        // En un EE con Parvularia y Básica, jornada_basica no distingue ambos niveles.
        if ($persona->jornada_basica > 0 && ! $persona->jornada_media && ! $ee->pre_escolar) { return 'Educación Básica'; }
        return null;
    }

    private function vtf(string $cargo): array
    {
        $educadora = str_contains($cargo, 'EDUCADOR') || str_contains($cargo, 'DIRECTOR');
        $estamento = $educadora ? 'Educadora de Parvulos' : 'Asistente de la Educación';
        if (str_contains($cargo, 'DIRECTOR') || str_contains($cargo, 'ENCARGADA Y EDUCADORA')) {
            return ['DIRECTOR/A', 'Función Directiva', $estamento];
        }
        if (str_contains($cargo, 'EDUCADOR')) { return ['EDUCADOR/A', 'Educadora de Párvulos', $estamento]; }
        if (str_contains($cargo, 'TECNIC')) { return ['TÉCNICO', 'Técnico de Educación Parvularia', $estamento]; }
        if (str_contains($cargo, 'PROFESIONAL') || str_contains($cargo, 'FONOAUDIOLOG') || str_contains($cargo, 'TERAPEUTA OCUPACIONAL')) {
            return ['PROFESIONAL', 'Otros asistentes de la educación', $estamento];
        }
        $categoria = match ($cargo) { 'ADMINISTRATIVO', 'ADMINISTRATIVA' => 'ADMINISTRATIVO', 'AUXILIAR' => 'AUXILIAR', default => null };
        return [$categoria, 'Otros asistentes de la educación', $estamento];
    }
}
