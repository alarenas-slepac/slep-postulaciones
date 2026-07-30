<?php

namespace App\Services;

use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Spatie\Permission\Models\Role;

class FuncionarioAcImportService
{
    public function import(UploadedFile $file, int $importedBy): array
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages(['excel' => 'No se pudo subir el archivo. Intenta nuevamente.']);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw ValidationException::withMessages(['excel' => 'El archivo debe ser Excel (.xlsx o .xls).']);
        }

        $dir = 'imports/funcionarios-ac/' . now()->format('Y/m');
        Storage::disk('local')->makeDirectory($dir);
        $storedPath = $file->storeAs($dir, now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName()), 'local');
        $fullPath = Storage::disk('local')->path($storedPath);

        $reader = IOFactory::createReaderForFile($fullPath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($fullPath);
        $sheet = $spreadsheet->getSheetByName('Carga masiva') ?: $spreadsheet->getSheet(0);
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestColumn = (string) $sheet->getHighestDataColumn();

        if ($highestRow < 2) {
            throw ValidationException::withMessages(['excel' => 'El archivo no contiene registros para importar.']);
        }

        $headers = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0] ?? [];
        $headerMap = $this->headerMap($headers);

        foreach (['run', 'dv'] as $required) {
            if (!array_key_exists($required, $headerMap)) {
                throw ValidationException::withMessages(['excel' => 'La plantilla debe incluir las columnas run y dv.']);
            }
        }

        $summary = [
            'archivo' => $file->getClientOriginalName(),
            'total_filas' => 0,
            'autorizaciones_creadas' => 0,
            'autorizaciones_actualizadas' => 0,
            'roles_asignados_a_usuarios_existentes' => 0,
            'usuarios_existentes' => 0,
            'pendientes_registro' => 0,
            'omitidas' => 0,
            'mensajes' => [],
        ];

        Role::findOrCreate('funcionario_ac', 'web');

        DB::transaction(function () use ($sheet, $highestRow, $highestColumn, $headerMap, $importedBy, &$summary) {
            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $row = $sheet->rangeToArray('A' . $rowNumber . ':' . $highestColumn . $rowNumber, null, true, false)[0] ?? [];
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $summary['total_filas']++;

                $run = $this->onlyRun($this->get($row, $headerMap, 'run'));
                $dv = $this->dv($this->get($row, $headerMap, 'dv'));
                $rutNormalizado = $run . $dv;

                if ($run === '' || $dv === '') {
                    $summary['omitidas']++;
                    $summary['mensajes'][] = "Fila {$rowNumber}: omitida por RUN/DV incompleto.";
                    continue;
                }

                $user = $this->findUserByRut($rutNormalizado);
                $message = $user
                    ? 'Usuario existente: se asigna rol funcionario_ac. Debe ingresar con sus credenciales ya creadas.'
                    : 'RUN autorizado: podra registrarse desde el acceso especial de Funcionario Administracion Central.';

                if ($user) {
                    if (!$user->hasRole('funcionario_ac')) {
                        $user->assignRole('funcionario_ac');
                        $summary['roles_asignados_a_usuarios_existentes']++;
                    }
                    $summary['usuarios_existentes']++;
                } else {
                    $summary['pendientes_registro']++;
                }

                $payload = [
                    'periodo_nomina' => $this->string($this->get($row, $headerMap, 'periodo_nomina')) ?: now()->format('Y-m'),
                    'accion_sistema' => $this->string($this->get($row, $headerMap, 'accion_sistema')) ?: 'autorizar_y_asignar_si_existe',
                    'run' => $run,
                    'dv' => $dv,
                    'rut_normalizado' => $rutNormalizado,
                    'apellido_paterno' => $this->string($this->get($row, $headerMap, 'apellido_paterno')),
                    'apellido_materno' => $this->string($this->get($row, $headerMap, 'apellido_materno')),
                    'nombres' => $this->string($this->get($row, $headerMap, 'nombres')),
                    'email' => strtolower($this->string($this->get($row, $headerMap, 'email'))),
                    'telefono' => $this->string($this->get($row, $headerMap, 'telefono')),
                    'unidad_departamento' => $this->string($this->get($row, $headerMap, 'unidad_departamento')),
                    'cargo_funcion' => $this->string($this->get($row, $headerMap, 'cargo_funcion')),
                    'comuna' => $this->string($this->get($row, $headerMap, 'comuna')),
                    'calidad_juridica' => $this->string($this->get($row, $headerMap, 'calidad_juridica')),
                    'estado_autorizacion' => $this->normalizeEstado($this->get($row, $headerMap, 'estado_autorizacion')),
                    'fecha_inicio_autorizacion' => $this->date($this->get($row, $headerMap, 'fecha_inicio_autorizacion')),
                    'fecha_fin_autorizacion' => $this->date($this->get($row, $headerMap, 'fecha_fin_autorizacion')),
                    'enviar_notificacion' => $this->boolean($this->get($row, $headerMap, 'enviar_notificacion')),
                    'observaciones' => $this->string($this->get($row, $headerMap, 'observaciones')),
                    'registered_user_id' => $user?->id,
                    'registered_at' => $user ? now() : null,
                    'imported_by' => $importedBy,
                    'imported_at' => now(),
                    'last_import_message' => $message,
                ];

                $existing = FuncionarioAcAutorizado::query()->where('rut_normalizado', $rutNormalizado)->first();
                if ($existing) {
                    $existing->fill($payload)->save();
                    $summary['autorizaciones_actualizadas']++;
                } else {
                    FuncionarioAcAutorizado::query()->create($payload);
                    $summary['autorizaciones_creadas']++;
                }
            }
        });

        if ($summary['autorizaciones_creadas'] + $summary['autorizaciones_actualizadas'] === 0) {
            throw ValidationException::withMessages(['excel' => 'No se importo ningun funcionario AC. Revisa la plantilla.']);
        }

        return $summary;
    }

    private function findUserByRut(string $rutNormalizado): ?User
    {
        return User::query()
            ->whereRaw("REPLACE(REPLACE(UPPER(rut), '.', ''), '-', '') = ?", [$rutNormalizado])
            ->first();
    }

    private function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $key = Str::of((string) $header)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            if ($key !== '') {
                $map[$key] = $index;
            }
        }
        return $map;
    }

    private function get(array $row, array $headerMap, string $key): mixed
    {
        if (!array_key_exists($key, $headerMap)) {
            return null;
        }
        return $row[$headerMap[$key]] ?? null;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function string(mixed $value): string
    {
        return trim((string) $value);
    }

    private function onlyRun(mixed $value): string
    {
        return preg_replace('/[^0-9]/', '', (string) $value) ?? '';
    }

    private function dv(mixed $value): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $value) ?? '');
    }

    private function normalizeEstado(mixed $value): string
    {
        $estado = Str::of((string) $value)->lower()->ascii()->trim()->toString();
        return in_array($estado, ['activo', 'inactivo'], true) ? $estado : 'activo';
    }

    private function boolean(mixed $value): bool
    {
        $value = Str::of((string) $value)->lower()->ascii()->trim()->toString();
        return in_array($value, ['si', 's', 'yes', 'y', '1', 'true'], true);
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
