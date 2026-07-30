<?php

namespace App\Mail;

use App\Models\CometidoFuncionario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CometidoFuncionarioNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $title,
        public string $messageText,
        public ?CometidoFuncionario $cometido = null,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public ?string $badgeText = null,
        public ?string $footerNote = null,
        public ?string $attachmentPack = null,
    ) {
    }

    public function build(): self
    {
        if ($this->cometido) {
            $this->cometido->load(['pasajeAereo', 'funcionarioAcAutorizado', 'establecimiento', 'documentos', 'documentosGenerados', 'informeCometidoActual']);
        }

        $mail = $this
            ->subject($this->title)
            ->view('emails.cometidos.funcionario-notification', [
                'recipientName' => $this->recipientName,
                'title' => $this->title,
                'messageText' => $this->messageText,
                'messageLines' => preg_split("/\r\n|\n|\r/", trim($this->messageText)) ?: [],
                'cometido' => $this->cometido,
                'actionText' => $this->actionText,
                'actionUrl' => $this->actionUrl,
                'badgeText' => $this->badgeText,
                'footerNote' => $this->footerNote,
                'attachmentPack' => $this->attachmentPack,
            ]);

        foreach ($this->attachmentsForPack() as $attachment) {
            $mail->attach($attachment['path'], ['as' => $attachment['as']]);
        }

        return $mail;
    }

    private function attachmentsForPack(): array
    {
        if (! $this->cometido || ! $this->attachmentPack) {
            return [];
        }

        // Force reload here because some notifications are sent immediately after
        // generating documents inside the same request/transaction.
        $this->cometido->load(['documentos', 'documentosGenerados', 'pasajeAereo', 'informeCometidoActual']);

        $attachments = [];
        $seen = [];
        $pack = $this->attachmentPack;

        $includeSolicitudCometido = true;
        $includeSolicitudPedido = in_array($pack, ['pasaje_autorizado', 'pasaje_reserva', 'pasaje_cdp', 'pasaje_boleto', 'expediente_completo'], true);
        $includeReserva = in_array($pack, ['pasaje_reserva', 'pasaje_cdp', 'pasaje_boleto', 'expediente_completo'], true);
        $includeCdp = in_array($pack, ['pasaje_cdp', 'pasaje_boleto', 'expediente_completo'], true);
        $includeBoleto = in_array($pack, ['pasaje_boleto', 'expediente_completo'], true);
        $includeExpedienteCompleto = in_array($pack, ['expediente_completo', 'informe_cometido', 'rendicion_lista', 'daf_contable', 'pago_registrado'], true);

        if ($includeSolicitudCometido) {
            $this->addGeneratedDocument($attachments, $seen, 'solicitud_cometido', 'Cometido funcionario firmado');
        }

        foreach ($this->cometido->documentos as $documento) {
            $label = match ((string) $documento->tipo) {
                'citacion_invitacion' => 'Citación o invitación',
                'oficio' => 'Documento complementario - oficio',
                'formulario_cometido' => 'Documento complementario - formulario',
                'resolucion_cometido' => 'REX cometido CGR',
                'cdp' => 'CDP',
                'rendicion', 'rendicion_reembolso', 'comprobante_rendicion', 'comprobante_reembolso' => 'Documento rendición',
                'contabilidad_viatico' => 'Documento contable viático',
                'pago_viatico' => 'Comprobante pago viático',
                default => 'Documento complementario - ' . Str::headline(str_replace('_', ' ', (string) $documento->tipo)),
            };
            $this->addStorageAttachment($attachments, $seen, $documento->path, $documento->nombre_original ?: $label, $label);
        }

        if ($includeSolicitudPedido) {
            $this->addGeneratedDocument($attachments, $seen, 'solicitud_pedido_pasaje', 'Solicitud de compra de pasaje firmada');
        }

        $pasaje = $this->cometido->pasajeAereo->sortByDesc('id')->first();
        if ($pasaje) {
            if ($includeSolicitudPedido && $pasaje->solicitud_pedido_pdf_path) {
                $this->addStorageAttachment($attachments, $seen, $pasaje->solicitud_pedido_pdf_path, $pasaje->numero_solicitud_pedido ?: 'solicitud_pedido_pasaje.pdf', 'Solicitud de compra de pasaje firmada');
            }
            if ($includeReserva && $pasaje->reserva_archivo_path) {
                $this->addStorageAttachment($attachments, $seen, $pasaje->reserva_archivo_path, $pasaje->reserva_nombre_original ?: 'reserva_pasaje.pdf', 'Reserva de pasaje realizada');
            }
            if ($includeCdp && $pasaje->cdp_archivo_path) {
                $this->addStorageAttachment($attachments, $seen, $pasaje->cdp_archivo_path, $pasaje->cdp_nombre_original ?: 'cdp_pasaje.pdf', 'CDP de pasaje');
            }
            if ($includeBoleto && $pasaje->compra_archivo_path) {
                $this->addStorageAttachment($attachments, $seen, $pasaje->compra_archivo_path, $pasaje->compra_nombre_original ?: 'boleto_pasaje.pdf', 'Boleto o respaldo de compra');
            }
        }

        if ($includeExpedienteCompleto) {
            foreach ($this->cometido->documentosGenerados->sortBy('id') as $documentoGenerado) {
                if ($documentoGenerado->archivo_pdf_path) {
                    $label = 'Documento generado - ' . Str::headline(str_replace('_', ' ', (string) $documentoGenerado->tipo_documento));
                    $this->addStorageAttachment($attachments, $seen, $documentoGenerado->archivo_pdf_path, ($documentoGenerado->numero_documento ?: $documentoGenerado->tipo_documento) . '.pdf', $label);
                }
            }

            $this->addRendicionAttachments($attachments, $seen);
            $this->addResolucionReembolsoAttachments($attachments, $seen);

            $paths = [
                ['path' => $this->cometido->archivo_resolucion_cometido_path, 'name' => 'rex_cometido_cgr.pdf', 'label' => 'REX cometido CGR'],
                ['path' => $this->cometido->documento_contable_viatico_path, 'name' => 'documento_contable_viatico.pdf', 'label' => 'Documento contable viático'],
                ['path' => $this->cometido->documento_pago_viatico_path, 'name' => 'comprobante_pago_viatico.pdf', 'label' => 'Comprobante pago viático'],
            ];

            foreach ($paths as $item) {
                $this->addStorageAttachment($attachments, $seen, $item['path'], $item['name'], $item['label']);
            }
        }

        return $attachments;
    }

    private function addGeneratedDocument(array &$attachments, array &$seen, string $tipo, string $label): void
    {
        $documento = $this->cometido->documentosGenerados
            ->where('tipo_documento', $tipo)
            ->sortByDesc('id')
            ->first();

        if (! $documento || ! $documento->archivo_pdf_path) {
            return;
        }

        $name = trim((string) ($documento->numero_documento ?: Str::headline(str_replace('_', ' ', $tipo)))) . '.pdf';
        $this->addStorageAttachment($attachments, $seen, $documento->archivo_pdf_path, $name, $label);
    }

    private function addStorageAttachment(array &$attachments, array &$seen, ?string $storagePath, ?string $originalName, string $label): void
    {
        if (! $storagePath || ! Storage::exists($storagePath)) {
            return;
        }

        $absolutePath = Storage::path($storagePath);
        if (isset($seen[$absolutePath])) {
            return;
        }

        $seen[$absolutePath] = true;
        $filename = $this->safeAttachmentName($label, $originalName ?: basename($storagePath));

        $attachments[] = [
            'path' => $absolutePath,
            'as' => $filename,
        ];
    }

    private function safeAttachmentName(string $prefix, string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME) ?: $name;
        $base = Str::limit(Str::slug($prefix . ' ' . $base, '_'), 120, '');

        return $extension ? $base . '.' . strtolower($extension) : $base;
    }
}
