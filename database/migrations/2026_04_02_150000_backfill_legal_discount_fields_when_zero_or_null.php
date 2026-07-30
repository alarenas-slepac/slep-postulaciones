<?php

use App\Support\MaeColumnNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mae_registros')
            ->select(['id', 'raw_row_json', 'imposiciones', 'salud', 'impuesto'])
            ->where(function ($query) {
                $query->whereNull('imposiciones')
                    ->orWhereNull('salud')
                    ->orWhereNull('impuesto')
                    ->orWhere('imposiciones', 0)
                    ->orWhere('salud', 0)
                    ->orWhere('impuesto', 0);
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $raw = json_decode((string) ($row->raw_row_json ?? ''), true);
                    if (!is_array($raw) || $raw === []) {
                        continue;
                    }

                    $updates = [];

                    foreach (['imposiciones', 'salud', 'impuesto'] as $field) {
                        $current = $row->{$field};
                        $needsBackfill = $current === null || abs((float) $current) <= 0.000001;
                        if (!$needsBackfill) {
                            continue;
                        }

                        $value = $this->decimalValue(MaeColumnNormalizer::findLegalDiscountValueInRawRow($raw, $field));
                        if ($value === null) {
                            continue;
                        }

                        if ($current === null || abs((float) $current - $value) > 0.000001) {
                            $updates[$field] = $value;
                        }
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('mae_registros')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // No reversible: el backfill corrige columnas persistidas usando raw_row_json existente.
    }

    private function decimalValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/[^0-9,.-]/', '', $text) ?? '';
        if ($text === '' || $text === '-' || $text === ',') {
            return null;
        }

        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (substr_count($text, '.') > 1) {
            $text = str_replace('.', '', $text);
        } elseif (str_contains($text, ',') && !str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? round((float) $text, 2) : null;
    }
};
