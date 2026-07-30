<?php

namespace App\Mail;

use App\Models\SolicitudReemplazo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class OrdenTrabajoCreada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudReemplazo $solicitud) {}

    public function build()
    {
        $s = $this->solicitud->load([
            'establecimiento',
            'funcionarioTitular',
            'areaDesempeno',
            'postulante.user',
            'jornadas',
            'ordenTrabajoCreadaPor',
        ]);

        // Reutilizamos el PDF existente de la solicitud como respaldo
        $pdf = Pdf::loadView('pdf.solicitud-reemplazo', ['s' => $s]);

        $subject = "Orden de trabajo creada — Solicitud {$s->numero_solicitud}";

        return $this->subject($subject)
            ->view('emails.orden-trabajo-creada', ['s' => $s])
            ->attachData($pdf->output(), "Solicitud-{$s->numero_solicitud}.pdf", [
                'mime' => 'application/pdf',
            ])
            ->when(!empty($s->orden_trabajo_pdf_path), function ($m) use ($s) {
                $m->attachFromStorageDisk('local', $s->orden_trabajo_pdf_path, 'ORDEN_TRABAJO.pdf', [
                    'mime' => 'application/pdf',
                ]);
            })
            ->when(!empty($s->contrato_trabajo_docx_path), function ($m) use ($s) {
                $ext = strtolower(pathinfo($s->contrato_trabajo_docx_path, PATHINFO_EXTENSION));
                $mimeDetected = Storage::disk('local')->mimeType($s->contrato_trabajo_docx_path);
                if ($mimeDetected === 'application/pdf') {
                    $ext = 'pdf';
                } elseif ($mimeDetected === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                    $ext = 'docx';
                }

                $ext = in_array($ext, ['docx', 'pdf'], true) ? $ext : 'docx';
                $filename = $ext === 'pdf' ? 'CONTRATO_TRABAJO.pdf' : 'CONTRATO_TRABAJO.docx';
                $mime = $ext === 'pdf'
                    ? 'application/pdf'
                    : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

                $m->attachFromStorageDisk('local', $s->contrato_trabajo_docx_path, $filename, [
                    'mime' => $mime,
                ]);
            });
    }
}
