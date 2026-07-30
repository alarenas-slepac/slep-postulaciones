<?php

namespace App\Console\Commands;

use App\Models\PostulanteProvisorio;
use App\Support\RutChile;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPostulantesProvisorios extends Command
{
    protected $signature = 'postulantes:import-provisorios {path}';
    protected $description = 'Importa postulantes provisorios desde un XLSX (rut,nombres,apellidos,email)';

    public function handle(): int
    {
        $pathArg = (string) $this->argument('path');
        $fullPath = $this->resolvePath($pathArg);

        if (!$fullPath) {
            $this->error("No encuentro el archivo: {$pathArg} (probé base_path y storage/app).");
            return self::FAILURE;
        }

        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($fullPath);

        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);

        if (!$rows || count($rows) === 0) {
            $this->error('Archivo sin filas.');
            return self::FAILURE;
        }

        // header mapping
        $header = array_map(fn($v) => $this->normHeader($v), $rows[0]);
        $map = $this->buildHeaderMap($header);

        $start = 0;
        if ($map) {
            $start = 1;
        } else {
            // fallback: [rut, apellidos, nombres, email]
            $map = ['rut' => 0, 'apellidos' => 1, 'nombres' => 2, 'email' => 3];
        }

        $byRut = [];
        $invalid = 0;
        $computedDv = 0;
        $invalidBodyTooLong = 0;

        for ($i = $start; $i < count($rows); $i++) {
            $r = $rows[$i];

            // 1) leer y sanitizar rut (Excel puede traer números/decimales)
            $rawRut = $this->sanitizeRutCell($r[$map['rut']] ?? '');

            // 2) normalizar
            $norm = RutChile::normalize($rawRut);

            // 3) si no normaliza, intenta calcular DV cuando viene solo cuerpo
            if (!$norm) {
                $digits = preg_replace('/\D+/', '', (string)$rawRut);

                // tolerante: 5 a 8 dígitos (ajusta si quieres)
                if (strlen($digits) >= 5 && strlen($digits) <= 8) {
                    $dv = $this->calcDv($digits);
                    if ($dv !== '') {
                        $rawRut2 = $digits . '-' . $dv;
                        $norm = RutChile::normalize($rawRut2);
                        if ($norm) {
                            $computedDv++;
                            $rawRut = $rawRut2;
                        }
                    }
                }
            }

            if (!$norm) {
                $invalid++;
                continue;
            }

            // ✅ GUARD rut_body: solo dígitos y tamaño razonable
            $rutBody = preg_replace('/\D+/', '', (string)($norm['rut_body'] ?? ''));
            $rutBody = ltrim($rutBody, '0');

            if ($rutBody === '' || strlen($rutBody) > 8) {
                $invalid++;
                $invalidBodyTooLong++;
                continue;
            }

            $rut = $norm['rut'];

            $nombres   = trim((string)($r[$map['nombres']] ?? ''));
            $apellidos = trim((string)($r[$map['apellidos']] ?? ''));
            $email     = strtolower(trim((string)($r[$map['email']] ?? '')));

            $keyN = $nombres !== '' ? preg_replace('/\s+/', ' ', $nombres) : null;
            $keyA = $apellidos !== '' ? preg_replace('/\s+/', ' ', $apellidos) : null;

            if (!isset($byRut[$rut])) {
                $byRut[$rut] = [
                    'rut' => $rut,
                    'rut_body' => $rutBody,          // ✅ guardado filtrado
                    'rut_dv' => $norm['rut_dv'],
                    'raw_rut' => $rawRut,
                    'nombres_freq' => [],
                    'apellidos_freq' => [],
                    'emails_freq' => [],
                    'import_status' => $norm['status'],
                ];
            }

            if ($keyN) $byRut[$rut]['nombres_freq'][$keyN] = ($byRut[$rut]['nombres_freq'][$keyN] ?? 0) + 1;
            if ($keyA) $byRut[$rut]['apellidos_freq'][$keyA] = ($byRut[$rut]['apellidos_freq'][$keyA] ?? 0) + 1;
            if ($email !== '') $byRut[$rut]['emails_freq'][$email] = ($byRut[$rut]['emails_freq'][$email] ?? 0) + 1;
        }

        $pickBest = function (array $freq): ?string {
            if (!$freq) return null;
            uksort($freq, function ($a, $b) use ($freq) {
                $fa = $freq[$a];
                $fb = $freq[$b];
                if ($fa !== $fb) return $fb <=> $fa;
                return strlen($b) <=> strlen($a);
            });
            return array_key_first($freq);
        };

        $payload = [];
        $now = now();

        foreach ($byRut as $data) {
            $emails = array_keys($data['emails_freq']);
            $emailPrincipal = $pickBest($data['emails_freq']);

            $payload[] = [
                'rut' => $data['rut'],
                'rut_body' => $data['rut_body'],
                'rut_dv' => $data['rut_dv'],
                'raw_rut' => $data['raw_rut'],
                'nombres' => $pickBest($data['nombres_freq']),
                'apellidos' => $pickBest($data['apellidos_freq']),
                'email' => $emailPrincipal,
                'emails' => $emails ? json_encode($emails, JSON_UNESCAPED_UNICODE) : null,
                'source_filename' => basename($fullPath),
                'import_status' => $data['import_status'],
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        PostulanteProvisorio::upsert(
            $payload,
            ['rut'],
            ['rut_body', 'rut_dv', 'raw_rut', 'nombres', 'apellidos', 'email', 'emails', 'source_filename', 'import_status', 'updated_at']
        );

        $this->info('Importación lista');
        $this->line('RUT únicos cargados: ' . count($payload));
        $this->line('Filas inválidas (no importadas): ' . $invalid);
        $this->line('DV calculados (rut venía sin dv): ' . $computedDv);
        $this->line('Inválidos por rut_body demasiado largo: ' . $invalidBodyTooLong);

        return self::SUCCESS;
    }

    private function resolvePath(string $path): ?string
    {
        if (is_file($path)) return realpath($path) ?: $path;

        $try = base_path($path);
        if (is_file($try)) return realpath($try) ?: $try;

        $try = storage_path('app/' . ltrim($path, '/\\'));
        if (is_file($try)) return realpath($try) ?: $try;

        return null;
    }

    private function normHeader($v): string
    {
        $s = Str::of((string)$v)->trim()->lower()->ascii()->toString();
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return $s;
    }

    private function buildHeaderMap(array $header): ?array
    {
        $aliases = [
            'rut' => ['rut', 'r.u.t', 'run'],
            'nombres' => ['nombres', 'nombre', 'names'],
            'apellidos' => ['apellidos', 'apellido', 'surnames'],
            'email' => ['email', 'correo', 'correo electronico', 'correo electrónico', 'e-mail', 'mail'],
        ];

        $findIdx = function (array $needles) use ($header): ?int {
            foreach ($header as $i => $h) {
                foreach ($needles as $n) {
                    if ($h === $n) return $i;
                }
            }
            return null;
        };

        $rut = $findIdx($aliases['rut']);
        $nom = $findIdx($aliases['nombres']);
        $ape = $findIdx($aliases['apellidos']);
        $ema = $findIdx($aliases['email']);

        if ($rut === null || $nom === null || $ape === null || $ema === null) {
            return null;
        }

        return ['rut' => $rut, 'nombres' => $nom, 'apellidos' => $ape, 'email' => $ema];
    }

    /**
     * Limpia el valor leído desde Excel para evitar casos tipo:
     * - 110707053.0
     * - números (int/float)
     * - espacios raros
     */
    private function sanitizeRutCell($value): string
    {
        if (is_int($value)) {
            return (string)$value;
        }

        if (is_float($value)) {
            // Para rut 7-8 dígitos es seguro
            return (string)(int)round($value);
        }

        $s = trim((string)$value);
        $s = preg_replace('/\.0$/', '', $s) ?? $s;
        return $s;
    }

    /**
     * Calcula DV para un cuerpo numérico de rut.
     */
    private function calcDv(string $body): string
    {
        $body = preg_replace('/\D+/', '', $body);
        $body = ltrim($body, '0');
        if ($body === '') return '';

        $sum = 0;
        $mul = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += ((int)$body[$i]) * $mul;
            $mul = ($mul === 7) ? 2 : ($mul + 1);
        }
        $res = 11 - ($sum % 11);

        if ($res === 11) return '0';
        if ($res === 10) return 'K';
        return (string)$res;
    }
}
