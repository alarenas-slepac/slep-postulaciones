<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationEventSetting;
use App\Models\User;
use App\Services\Notifications\NotificationEventCatalog;
use App\Services\Notifications\NotificationEventPolicy;
use App\Services\WhatsApp\ReemplazoWhatsAppRecipients;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotificationEventController extends Controller
{
    public function __construct(private NotificationEventCatalog $catalog, private NotificationEventPolicy $policy) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'module' => ['nullable', 'string', 'max:80'], 'channel' => ['nullable', Rule::in(['mail', 'whatsapp'])]]);
        $all = collect($this->catalog->all());
        $ready = Schema::hasTable('notification_event_settings');
        $settings = $ready ? NotificationEventSetting::all()->keyBy(fn ($s) => $s->event_key.'|'.$s->channel) : collect();
        $filtered = $all->filter(fn ($e, $key) => (!$request->filled('module') || $e['module'] === $filters['module'])
            && (!$request->filled('channel') || in_array($filters['channel'], $e['channels'], true))
            && (!$request->filled('q') || mb_stripos($e['name'].' '.$e['module'].' '.$key, $filters['q']) !== false));
        $page = max(1, min(100000, (int) $request->query('page', 1)));
        $events = new LengthAwarePaginator($filtered->slice(($page - 1) * 20, 20), $filtered->count(), 20, $page,
            ['path' => $request->url(), 'query' => $request->query()]);
        return response()->view('admin.notification-events.index', compact('events', 'settings', 'ready') + [
            'modules' => $all->pluck('module')->unique()->values(), 'total' => $all->count(),
            'whatsappCount' => $all->filter(fn ($e) => in_array('whatsapp', $e['channels'], true))->count(),
            'protectedCount' => $all->where('protected', true)->count(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function edit(string $event)
    {
        $definition = $this->definition($event);
        $ready = Schema::hasTable('notification_event_settings');
        $settings = $ready ? NotificationEventSetting::where('event_key', $event)->get()->keyBy('channel') : collect();
        $changes = Schema::hasTable('notification_event_changes') ? DB::table('notification_event_changes')->where('event_key', $event)
            ->latest('id')->limit(5)->get(['channel', 'actor_id', 'created_at']) : collect();
        return response()->view('admin.notification-events.edit', compact('event', 'definition', 'settings', 'changes', 'ready')
            + ['recipients' => $this->eligible($definition)])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, string $event, string $channel)
    {
        $definition = $this->definition($event, $channel);
        abort_if($definition['protected'] ?? false, 403, 'Este evento protege el acceso a la cuenta.');
        $data = $this->validated($request, $definition, $channel);
        $this->save($request, $event, $channel, $data);
        return redirect()->route('admin.notification-events.edit', $event)->with('success', 'Configuración guardada. Se aplicará a los próximos envíos.');
    }

    public function preview(Request $request, string $event, string $channel)
    {
        $definition = $this->definition($event, $channel);
        abort_if($definition['protected'] ?? false, 403);
        $data = $this->validated($request, $definition, $channel);
        $original = "SGA · Ejemplo ficticio de {$definition['name']}\nSolicitud de ejemplo N° 00001-2026.\nDatos y documentos del evento conservados.\nIngresa al SGA: https://sga.example.test/";
        if ($channel === 'whatsapp' && ($definition['whatsapp_from_mail'] ?? false)) {
            $original = "SGA SLEP Andalién Costa\n{$definition['name']}\nTienes una actualización. Consulta el detalle y los documentos disponibles en el SGA:\nhttps://sga.example.test/";
        }
        $originalSubject = $channel === 'mail' ? 'Asunto de ejemplo del evento' : '';
        $subject = $channel === 'mail' ? $this->policy->text($data['subject_template'] ?: '{{asunto_original}}', '', $originalSubject, $definition['name']) : $definition['name'];
        $body = $this->policy->text($data['body_template'] ?: NotificationEventPolicy::ORIGINAL, $original, $originalSubject, $definition['name']);
        if ($channel === 'whatsapp' && ($event === 'solicitud_reemplazo.aceptada_reemplazante' || ($definition['whatsapp_from_mail'] ?? false))) $body .= "\n\nEsta es una notificación automática del SGA.\nPor favor, no respondas a este mensaje.";
        return response()->view('admin.notification-events.preview', compact('event', 'channel', 'definition', 'subject', 'body'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function reset(Request $request, string $event, string $channel)
    {
        $definition = $this->definition($event, $channel);
        abort_if($definition['protected'] ?? false, 403);
        $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $this->save($request, $event, $channel, ['enabled' => null, 'subject_template' => null, 'body_template' => null,
            'additional_emails' => [], 'recipient_mode' => 'all', 'recipient_ids' => []]);
        return redirect()->route('admin.notification-events.edit', $event)->with('success', 'Se restableció la configuración original de este canal.');
    }

    private function definition(string $event, ?string $channel = null): array
    {
        $definition = $this->catalog->find($event);
        abort_unless($definition && (!$channel || in_array($channel, $definition['channels'], true)), 404);
        return $definition;
    }

    private function validated(Request $request, array $definition, string $channel): array
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:0'], 'enabled' => ['required', 'boolean'],
            'subject_template' => ['nullable', 'string', 'max:200'], 'body_template' => ['nullable', 'string', 'max:1500'],
            'additional_emails' => ['nullable', 'string', 'max:4000'], 'recipient_mode' => ['required', Rule::in(['all', 'selected'])],
            'recipient_ids' => ['nullable', 'array', 'max:100'], 'recipient_ids.*' => ['integer', 'distinct'],
        ]);
        $this->policy->validateTemplate($data['subject_template'] ?? null, 'subject_template', false);
        $this->policy->validateTemplate($data['body_template'] ?? null, 'body_template', true);
        $emails = collect(preg_split('/[,;\s]+/', trim($data['additional_emails'] ?? '')) ?: [])->filter()->map(fn ($s) => strtolower($s))->unique()->values();
        if ($emails->count() > 20 || $emails->contains(fn ($s) => !filter_var($s, FILTER_VALIDATE_EMAIL))) throw ValidationException::withMessages(['additional_emails' => 'Ingrese hasta 20 correos válidos, separados por coma o línea.']);
        $ids = array_map('intval', $data['recipient_ids'] ?? []);
        if ($channel === 'whatsapp') {
            if ($emails->isNotEmpty() || !empty($data['subject_template'])) throw ValidationException::withMessages(['additional_emails' => 'WhatsApp utiliza exclusivamente teléfonos de sus destinatarios autorizados.']);
            $allowed = $this->eligible($definition)->pluck('user_id')->all();
            if (array_diff($ids, $allowed) || (!($definition['whatsapp_roles'] ?? []) && !($definition['whatsapp_from_mail'] ?? false) && $data['recipient_mode'] !== 'all')
                || ($data['recipient_mode'] === 'selected' && !$ids)) throw ValidationException::withMessages(['recipient_ids' => 'Seleccione usuarios vigentes de la etapa o conserve todos sus destinatarios.']);
            $sample = $this->policy->text($data['body_template'] ?: NotificationEventPolicy::ORIGINAL, str_repeat('x', 750), '', $definition['name']);
            if (mb_strlen($sample) > 1000) throw ValidationException::withMessages(['body_template' => 'La presentación es demasiado extensa para WhatsApp; conserve margen para los datos del evento.']);
        } elseif ($data['recipient_mode'] !== 'all' || $ids) {
            throw ValidationException::withMessages(['recipient_mode' => 'El correo conserva destinatarios calculados por el proceso y permite copias adicionales.']);
        }
        return ['enabled' => (bool) $data['enabled'], 'subject_template' => trim($data['subject_template'] ?? '') ?: null,
            'body_template' => trim($data['body_template'] ?? '') ?: null, 'additional_emails' => $emails->all(),
            'recipient_mode' => $data['recipient_mode'], 'recipient_ids' => $data['recipient_mode'] === 'selected' ? $ids : []];
    }

    private function eligible(array $definition)
    {
        if ($definition['whatsapp_from_mail'] ?? false) return app(\App\Services\Notifications\NotificationWhatsAppRecipients::class)->eligible();
        $roles = $definition['whatsapp_roles'] ?? [];
        if (!$roles || !Schema::hasTable('funcionarios_ac_autorizados')) return collect();
        return app(ReemplazoWhatsAppRecipients::class)->forStage($roles)->map(function ($recipient) {
            $user = User::find($recipient['user_id']);
            return ['user_id' => $recipient['user_id'], 'label' => ($user?->display_name ?: 'Usuario autorizado').' · '.$recipient['role']];
        })->unique('user_id')->values();
    }

    private function save(Request $request, string $event, string $channel, array $data): void
    {
        abort_unless(Schema::hasTable('notification_event_settings') && Schema::hasTable('notification_event_changes'), 503, 'Complete la migración de eventos antes de guardar.');
        DB::transaction(function () use ($request, $event, $channel, $data) {
            $setting = NotificationEventSetting::where('event_key', $event)->where('channel', $channel)->lockForUpdate()->first();
            if ((int) ($setting?->revision ?? 0) !== (int) $request->input('revision')) throw ValidationException::withMessages(['revision' => 'Otro administrador cambió este evento. Recargue la página antes de guardar.']);
            $previous = $setting?->only(array_keys($data)) ?? [];
            if (!$setting) {
                // createOrFirst recupera la fila ante una inserción concurrente de la misma clave única.
                $setting = NotificationEventSetting::firstOrCreate(['event_key' => $event, 'channel' => $channel],
                    $data + ['updated_by' => $request->user()->id, 'revision' => 1]);
                if (!$setting->wasRecentlyCreated) throw ValidationException::withMessages(['revision' => 'Otro administrador creó esta configuración. Recargue la página antes de guardar.']);
            } else {
                $setting->fill($data + ['updated_by' => $request->user()->id, 'revision' => $setting->revision + 1])->save();
            }
            DB::table('notification_event_changes')->insert(['event_key' => $event, 'channel' => $channel, 'actor_id' => $request->user()->id,
                'previous' => Crypt::encryptString(json_encode($previous, JSON_THROW_ON_ERROR)),
                'current' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }
}
