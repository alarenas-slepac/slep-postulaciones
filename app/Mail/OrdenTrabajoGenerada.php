<?php

namespace App\Mail;

use App\Models\SolicitudReemplazo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class OrdenTrabajoGenerada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudReemplazo $solicitud)
    {
        $this->solicitud->loadMissing([
            'establecimiento',
            'areaDesempeno',
            'postulante.user',
            'funcionarioTitular',
            'jornadas',
        ]);
    }

    public function build()
    {
        $s = $this->solicitud;

        $subjectNum = $s->numero_solicitud ?? $s->correlativo ?? $s->id;
        $subject = "Orden de Trabajo generada - Solicitud #{$subjectNum}";

        $m = $this->subject($subject)
            ->view('emails.orden_trabajo_generada', ['s' => $s]);

        if (!empty($s->orden_trabajo_pdf_path)) {
            $m->attachFromStorageDisk('local', $s->orden_trabajo_pdf_path, 'ORDEN_TRABAJO.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        if (!empty($s->horario_titular_pdf_path)) {
            $m->attachFromStorageDisk('local', $s->horario_titular_pdf_path, 'HORARIO_TITULAR.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        if (!empty($s->contrato_trabajo_docx_path)) {
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
        }

        return $m;
    }
}
