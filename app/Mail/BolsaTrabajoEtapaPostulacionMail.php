<?php

namespace App\Mail;

use App\Models\BolsaTrabajoOferta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BolsaTrabajoEtapaPostulacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BolsaTrabajoOferta $oferta,
        public string $tipo,
        public ?string $destinatarioNombre = null,
        public array $context = []
    ) {
        $this->oferta->loadMissing(['establecimiento', 'areaDesempeno', 'selectedPostulacion.user']);
    }

    public function build(): self
    {
        $portalUrl = 'https://postulaciones.slepandaliencosta.cl';
        $greeting = 'Estimado/a ' . trim((string) ($this->destinatarioNombre ?: 'postulante')) . ':';
        $actionText = 'Ver portal de postulaciones';
        $actionUrl = $portalUrl;
        $salutation = 'Saludos cordiales,<br>' . config('brand.platform_name', 'Plataforma SLEP Andalién Costa');

        $subject = 'Actualización de postulación — Oferta laboral #' . $this->oferta->id;
        $lines = [];

        $ofertaLabel = trim(collect([
            $this->oferta->establecimientos_display,
            optional($this->oferta->areaDesempeno)->nombre,
        ])->filter()->implode(' · '));

        $detailLines = array_filter([
            $ofertaLabel !== '' ? 'Oferta: ' . $ofertaLabel : null,
            $this->oferta->rbds_display ? 'RBD: ' . $this->oferta->rbds_display : null,
            $this->oferta->comuna ? 'Comuna: ' . $this->oferta->comuna : null,
            $this->oferta->estamento_label ? 'Estamento: ' . $this->oferta->estamento_label : null,
        ]);

        switch ($this->tipo) {
            case 'avance':
                $stageLabel = (string) ($this->context['target_stage_label'] ?? $this->oferta->etapa_label);
                $subject = 'Resultado de postulación — Avance a ' . $stageLabel;
                $lines = [
                    'Junto con saludar, informamos que su postulación ha avanzado a la etapa **' . $stageLabel . '** del proceso de selección.',
                    ...$detailLines,
                    'En caso de requerirse antecedentes adicionales, citación o coordinación, el equipo a cargo del proceso se contactará mediante los datos registrados en su perfil.',
                    'Agradecemos su interés y le solicitamos mantenerse atento/a a próximas comunicaciones.',
                ];
                break;

            case 'no_avanza':
                $stageLabel = (string) ($this->context['target_stage_label'] ?? $this->oferta->etapa_label);
                $subject = 'Resultado de postulación — No continúa en el proceso';
                $lines = [
                    'Junto con saludar, agradecemos sinceramente su participación en el proceso de selección asociado a la siguiente oferta laboral.',
                    ...$detailLines,
                    'Luego de la revisión correspondiente para la etapa **' . $stageLabel . '**, informamos que en esta oportunidad su postulación no continuará avanzando.',
                    'Le invitamos cordialmente a seguir postulando en futuras convocatorias y a mantenerse atento/a a nuevas publicaciones en el portal institucional.',
                ];
                break;

            case 'desierto':
                $subject = 'Cierre de proceso — Oferta laboral declarada desierta';
                $lines = [
                    'Junto con saludar, informamos que el proceso de selección asociado a la siguiente oferta laboral ha sido cerrado en estado **Desierto**.',
                    ...$detailLines,
                    'Esta determinación se adopta debido a que el proceso no contó con una cantidad suficiente de candidaturas para continuar.',
                    'Agradecemos su interés y participación, e invitamos a mantenerse atento/a a nuevas ofertas laborales publicadas por el Servicio.',
                ];
                break;

            case 'cerrado':
                $selectedName = trim((string) ($this->context['selected_name'] ?? ''));
                $subject = 'Cierre de proceso — Oferta laboral finalizada';
                $lines = [
                    'Junto con saludar, informamos que el proceso de selección asociado a la siguiente oferta laboral ha finalizado.',
                    ...$detailLines,
                    $selectedName !== ''
                        ? 'La persona seleccionada para continuar en el cargo es: **' . $selectedName . '**.'
                        : 'El proceso ha concluido con una persona seleccionada.',
                    'Agradecemos su participación y el interés demostrado durante el proceso.',
                ];
                break;
        }

        return $this->subject($subject)
            ->view('emails.shared.message', compact('greeting', 'lines', 'actionText', 'actionUrl', 'salutation'));
    }
}
