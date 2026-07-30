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
                    ->orWhereNull('impuesto');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $raw = json_decode((string) ($row->raw_row_json ?? ''), true);
                    if (!is_array($raw) || $raw === []) {
                        continue;
                    }

                    $updates = [];

                    if ($row->imposiciones === null) {
                        $value = $this->decimalValue(MaeColumnNormalizer::findLegalDiscountValueInRawRow($raw, 'imposiciones'));
                        if ($value !== null) {
                            $updates['imposiciones'] = $value;
                        }
                    }

                    if ($row->salud === null) {
                        $value = $this->decimalValue(MaeColumnNormalizer::findLegalDiscountValueInRawRow($raw, 'salud'));
                        if ($value !== null) {
                            $updates['salud'] = $value;
                        }
                    }

                    if ($row->impuesto === null) {
                        $value = $this->decimalValue(MaeColumnNormalizer::findLegalDiscountValueInRawRow($raw, 'impuesto'));
                        if ($value !== null) {
                            $updates['impuesto'] = $value;
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
        // No reversible: el backfill sólo completa valores faltantes usando raw_row_json existente.
    }

    private function decimalValue(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $sanitized = preg_replace('/[^0-9,.-]/', '', $value) ?? '';
        if ($sanitized === '') {
            return null;
        }

        if (str_contains($sanitized, ',') && str_contains($sanitized, '.')) {
            $sanitized = str_replace('.', '', $sanitized);
            $sanitized = str_replace(',', '.', $sanitized);
        } elseif (str_contains($sanitized, ',')) {
            $sanitized = str_replace('.', '', $sanitized);
            $sanitized = str_replace(',', '.', $sanitized);
        }

        return is_numeric($sanitized) ? round((float) $sanitized, 2) : null;
    }
};
