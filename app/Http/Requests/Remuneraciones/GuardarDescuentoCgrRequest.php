<?php

namespace App\Http\Requests\Remuneraciones;

use App\Models\DescuentoCgr;
use App\Support\RutChile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarDescuentoCgrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'funcionario_slep']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rut = RutChile::normalize((string) $this->input('rut'));
        $periodo = trim((string) $this->input('fecha_primer_descuento'));

        $this->merge([
            'rut' => $rut['rut'] ?? $this->input('rut'),
            'numero_resolucion' => DescuentoCgr::normalizarNumeroResolucion((string) $this->input('numero_resolucion')),
            'fecha_primer_descuento' => preg_match('/^\d{4}-\d{2}$/', $periodo) ? $periodo.'-01' : $periodo,
        ]);
    }

    public function rules(): array
    {
        $descuentoCgr = $this->route('descuentoCgr');
        $pdf = $descuentoCgr ? 'nullable' : 'required';
        $reglasResolucion = ['required', 'string', 'max:100'];
        $resolucionSinCambios = $descuentoCgr instanceof DescuentoCgr
            && DescuentoCgr::normalizarNumeroResolucion((string) $this->input('numero_resolucion'))
                === DescuentoCgr::normalizarNumeroResolucion((string) $descuentoCgr->numero_resolucion);

        if (! $resolucionSinCambios) {
            $resolucionUnica = Rule::unique('descuentos_cgr', 'numero_resolucion_clave');
            if ($descuentoCgr instanceof DescuentoCgr) {
                $resolucionUnica->ignore($descuentoCgr);
            }
            $reglasResolucion[] = $resolucionUnica;
        }

        return [
            'rut' => ['required', 'string', 'max:12'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'numero_resolucion' => $reglasResolucion,
            'fecha_resolucion' => ['nullable', 'date'],
            'deuda_definitiva_pesos' => ['required', 'integer', 'min:1'],
            'deuda_equivalente_utm' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'cuota_utm' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'numero_cuotas' => ['required', 'integer', 'min:1', 'max:600'],
            'tasa_interes_anual' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'tasa_interes_mensual' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'fecha_primer_descuento' => ['required', 'date_format:Y-m-d'],
            'resolucion_pdf' => [$pdf, 'file', 'mimes:pdf', 'max:20480'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rut = RutChile::normalize((string) $this->input('rut'));
                if (! $rut || $rut['status'] !== 'ok') {
                    $validator->errors()->add('rut', 'El RUT ingresado no es válido. Incluye su dígito verificador.');
                }

                $fecha = (string) $this->input('fecha_primer_descuento');
                if ($fecha !== '' && ! str_ends_with($fecha, '-01')) {
                    $validator->errors()->add('fecha_primer_descuento', 'La fecha debe corresponder a un mes y año.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'deuda_definitiva_pesos' => 'deuda definitiva',
            'deuda_equivalente_utm' => 'deuda equivalente en UTM',
            'cuota_utm' => 'cuota en UTM',
            'numero_cuotas' => 'número de cuotas',
            'tasa_interes_anual' => 'tasa de interés anual',
            'tasa_interes_mensual' => 'tasa de interés mensual',
            'fecha_primer_descuento' => 'fecha del primer descuento',
            'resolucion_pdf' => 'resolución PDF',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_resolucion.unique' => 'La resolución ingresada ya está registrada en Descuentos CGR.',
        ];
    }
}
