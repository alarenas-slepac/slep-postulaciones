<?php

namespace App\Services\Certificados;

use App\Models\CertificadoEmitido;
use App\Support\Cometidos\SimpleQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CertificadoVigenciaPdfService
{
    public function generar(CertificadoEmitido $certificado): CertificadoEmitido
    {
        File::ensureDirectoryExists(storage_path('fonts'));

        $urlVerificacion = route(
            'certificados.verificar',
            $certificado->codigo_validacion
        );
        $pdf = Pdf::loadView('pdf.certificados.vigencia', [
            'certificado' => $certificado,
            'institucion' => config('certificados.institucion'),
            'firmante' => config('certificados.firmante'),
            'logoDataUri' => $this->dataUri(config('certificados.recursos.logo')),
            'timbreDataUri' => $this->dataUri(config('certificados.recursos.timbre')),
            'firmaDataUri' => $this->dataUri(config('certificados.recursos.firma')),
            'fuenteRegularDataUri' => $this->dataUri(
                config('certificados.recursos.fuente_regular'),
                'font/ttf'
            ),
            'fuenteBoldDataUri' => $this->dataUri(
                config('certificados.recursos.fuente_bold'),
                'font/ttf'
            ),
            'urlVerificacion' => $urlVerificacion,
            'qrDataUri' => SimpleQrCode::dataUri($urlVerificacion, 3, 3),
        ])->setPaper('letter', 'portrait');

        $ruta = 'certificados/vigencia/'
            .$certificado->emitido_at->format('Y')
            .'/'.$certificado->numero.'.pdf';
        $contenido = $pdf->output();

        if (! Storage::disk('local')->put($ruta, $contenido)) {
            throw new \RuntimeException('No fue posible almacenar el certificado generado.');
        }
        $hash = hash('sha256', $contenido);
        $certificado->update([
            'archivo_pdf_path' => $ruta,
            'documento_hash' => $hash,
        ]);

        return $certificado->fresh();
    }

    private function dataUri(?string $ruta, ?string $mime = null): ?string
    {
        if (! $ruta || ! is_file($ruta)) {
            return null;
        }

        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            return null;
        }

        $mime ??= match (strtolower((string) pathinfo($ruta, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ttf' => 'font/ttf',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($contenido);
    }
}
