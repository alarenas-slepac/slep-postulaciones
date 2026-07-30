<?php

namespace App\Http\Controllers;

use App\Jobs\SendMessageNotifications;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageRead;
use App\Models\User;
use App\Support\Messaging\FuncionarioAcDirectory;
use App\Support\Messaging\MessageContentSanitizer;
use App\Support\SlepUiRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessagingController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $meId = $me->id;

        $canStart = $this->canStartConversation($me);
        $canUseDirectory = FuncionarioAcDirectory::canUseDirectory($me);
        $canStartGeneral = FuncionarioAcDirectory::canStartGeneralConversation($me);
        $isEstablishmentUser = FuncionarioAcDirectory::isEstablishmentUser($me);

        $directoryFilters = [
            'q' => $request->query('q'),
            'subdireccion' => $request->query('subdireccion'),
            'unidad' => $request->query('unidad'),
            'recent_only' => $request->boolean('recent_only'),
        ];

        $directoryItems = $canUseDirectory
            ? FuncionarioAcDirectory::items($directoryFilters)
            : collect();

        $directoryGrouped = $directoryItems
            ->groupBy(fn ($item) => $item['subdireccion'] ?: 'Sin subdirección registrada')
            ->map(fn ($items) => $items->groupBy(fn ($item) => $item['unidad'] ?: 'Sin unidad registrada'));

        $directoryCatalog = $canUseDirectory ? FuncionarioAcDirectory::items() : collect();
        $directoryFilterOptions = $canUseDirectory ? FuncionarioAcDirectory::filters() : ['subdirecciones' => collect(), 'unidades' => collect()];

        $readStates = $this->messageReadsAvailable()
            ? MessageRead::query()
                ->where('user_id', $meId)
                ->get()
                ->keyBy('conversation_id')
            : collect();

        $conversations = Conversation::whereHas(
            'participants',
            fn ($q) => $q->where('users.id', $meId)
        )
            ->with([
                'participants:id,nombres,apellido_paterno,apellido_materno,email,last_seen_at',
                'participants.roles:id,name',
                'messages' => fn ($q) => $q->latest()->limit(1)
                    ->with([
                        'user:id,nombres,apellido_paterno,apellido_materno,email',
                        'attachments',
                    ]),
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Conversation $conversation) use ($meId, $readStates) {
                $other = $conversation->participants->firstWhere('id', '!=', $meId) ?? auth()->user();
                $last = $conversation->messages->first();
                $lastText = MessageContentSanitizer::plain($last?->body, 160);
                $lastAttachmentsCount = (int) ($last?->attachments?->count() ?? 0);
                if ($lastText === '' && $lastAttachmentsCount > 0) {
                    $lastText = $lastAttachmentsCount === 1 ? 'Archivo adjunto' : $lastAttachmentsCount . ' archivos adjuntos';
                }
                $readState = $readStates->get($conversation->id);
                $lastReadId = (int) ($readState?->last_read_message_id ?? 0);
                $unreadCount = $this->unreadCount($conversation, $meId, $lastReadId);

                return (object) [
                    'id' => $conversation->id,
                    'name' => $this->displayName($other),
                    'initial' => $this->initials($other),
                    'avatarClass' => $this->avatarClass($other),
                    'roleLabel' => $this->primaryRoleLabel($other),
                    'online' => $this->isOnline($other),
                    'last' => $lastText ?: null,
                    'last_at' => cl_datetime($last?->created_at),
                    'updated_at' => cl_datetime($conversation->updated_at),
                    'unread_count' => $unreadCount,
                    'has_unread' => $unreadCount > 0,
                    'last_read_at' => cl_datetime($readState?->read_at),
                ];
            });

        $unreadTotal = $conversations->sum('unread_count');

        $metrics = [
            'conversations' => $conversations->count(),
            'unread' => $unreadTotal,
            'unread_conversations' => $conversations->where('has_unread', true)->count(),
            'directory' => $directoryCatalog->count(),
            'subdirecciones' => $directoryCatalog->pluck('subdireccion')->filter()->unique()->count(),
            'unidades' => $directoryCatalog->pluck('unidad')->filter()->unique()->count(),
        ];

        return view('messages.index', compact(
            'canStart',
            'canUseDirectory',
            'canStartGeneral',
            'isEstablishmentUser',
            'conversations',
            'directoryGrouped',
            'directoryItems',
            'directoryFilterOptions',
            'directoryFilters',
            'metrics'
        ));
    }


    public function unreadSummary(Request $request)
    {
        return response()->json([
            'ok' => true,
            'unread_total' => $this->totalUnreadForUser((int) $request->user()->id),
        ]);
    }

    public function searchUsers(Request $request)
    {
        $this->authorize('start', Conversation::class);

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $authUser = $request->user();
        $authId = $authUser->id;

        if (! FuncionarioAcDirectory::canStartGeneralConversation($authUser)) {
            $items = FuncionarioAcDirectory::items(['q' => $q])
                ->where('user_id', '!=', $authId)
                ->take(12)
                ->map(fn ($item) => [
                    'id' => $item['user_id'],
                    'name' => $item['name'],
                    'initial' => $item['initials'],
                    'role' => 'ac',
                    'role_label' => trim(($item['cargo'] ?: 'Funcionario AC') . ' · ' . ($item['unidad'] ?: 'SLEP')),
                    'online' => (bool) $item['online'],
                    'subdireccion' => $item['subdireccion'],
                    'unidad' => $item['unidad'],
                ])
                ->values();

            return response()->json(['items' => $items]);
        }

        $users = User::query()
            ->select(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'email', 'last_seen_at'])
            ->with('roles:id,name')
            ->where('id', '!=', $authId)
            ->where(function ($query) use ($q) {
                $like = '%' . str_replace(' ', '%', $q) . '%';
                $query->where('nombres', 'like', $like)
                    ->orWhere('apellido_paterno', 'like', $like)
                    ->orWhere('apellido_materno', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('nombres')
            ->limit(15)
            ->get();

        $items = $users->map(function (User $user) {
            $firstRole = method_exists($user, 'getRoleNames') ? ($user->getRoleNames()->first() ?? '') : '';

            return [
                'id' => $user->id,
                'name' => $this->displayName($user),
                'initial' => $this->initials($user),
                'role' => $this->roleKey($firstRole),
                'role_label' => $firstRole ? SlepUiRegistry::roleLabel($firstRole) : 'Usuario del sistema',
                'online' => $this->isOnline($user),
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function start(Request $request)
    {
        $this->authorize('start', Conversation::class);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $me = $request->user();
        $meId = $me->id;
        $other = (int) $data['user_id'];

        if ($other === $meId) {
            return response()->json(['ok' => false, 'error' => 'No puedes iniciar una conversación contigo mismo.'], 422);
        }

        if (! $this->canStartWith($me, $other)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tienes permiso para iniciar conversación con este usuario. Los establecimientos deben contactar personal SLEP desde la libreta institucional.',
            ], 403);
        }

        $conversationId = DB::table('conversation_participants as cp1')
            ->join('conversation_participants as cp2', 'cp2.conversation_id', '=', 'cp1.conversation_id')
            ->where('cp1.user_id', $meId)
            ->where('cp2.user_id', $other)
            ->value('cp1.conversation_id');

        if (! $conversationId) {
            $conversation = Conversation::create(['created_by' => $meId]);
            $conversation->participants()->attach([$meId, $other]);
            $conversationId = $conversation->id;
        }

        return response()->json(['ok' => true, 'id' => $conversationId]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $conversation->load([
            'participants:id,nombres,apellido_paterno,apellido_materno,email,last_seen_at',
            'participants.roles:id,name',
        ]);

        $initialMessageLimit = 20;
        $loadFullChat = $request->boolean('full_chat') || $request->boolean('full');
        $messageTotal = (int) $conversation->messages()->count();

        $messagesQuery = $conversation->messages()
            ->with([
                'user:id,nombres,apellido_paterno,apellido_materno,email',
                'attachments',
            ])
            ->latest('id');

        if (! $loadFullChat) {
            $messagesQuery->limit($initialMessageLimit);
        }

        $messages = $messagesQuery
            ->get()
            ->sortBy('id')
            ->values();

        $loadedMessagesCount = $messages->count();
        $hasOlderMessages = ! $loadFullChat && $messageTotal > $loadedMessagesCount;

        $meId = auth()->id();
        $other = $conversation->participants->firstWhere('id', '!=', $meId) ?? auth()->user();
        $this->markConversationRead($conversation, $meId);

        return view('messages.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'other' => $other,
            'avatarClass' => $this->avatarClass($other),
            'isOnline' => $this->isOnline($other),
            'otherRoleLabel' => $this->primaryRoleLabel($other),
            'messageLoadLimit' => $initialMessageLimit,
            'messageTotal' => $messageTotal,
            'loadedMessagesCount' => $loadedMessagesCount,
            'hasOlderMessages' => $hasOlderMessages,
            'isFullChatLoaded' => $loadFullChat,
        ]);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        $this->authorize('poll', $conversation);

        $after = (int) $request->query('after_id', 0);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->when($after > 0, fn ($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->with([
                'user:id,nombres,apellido_paterno,apellido_materno,email',
                'attachments',
            ])
            ->get();

        $items = $messages->map(fn (Message $message) => $this->messagePayload($message, (int) $request->user()->id, $conversation));

        if ($messages->isNotEmpty()) {
            $this->markConversationRead($conversation, $request->user()->id, (int) $messages->max('id'));
        }

        return response()->json([
            'items' => $items,
            'last_read_message_id' => $this->lastReadMessageId($conversation->id, $request->user()->id),
            'unread_total' => $this->totalUnreadForUser($request->user()->id),
        ]);
    }

    public function attachment(Conversation $conversation, MessageAttachment $attachment)
    {
        $this->authorize('view', $conversation);

        $attachment->loadMissing('message');

        if (! $attachment->message || (int) $attachment->message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }

        $disk = $attachment->disk ?: 'local';
        if (! Storage::disk($disk)->exists($attachment->path)) {
            abort(404);
        }

        $filename = $attachment->original_name ?: basename($attachment->path);

        if ($attachment->is_image) {
            return response()->file(Storage::disk($disk)->path($attachment->path), [
                'Content-Type' => $attachment->mime_type ?: 'image/png',
                'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            ]);
        }

        return Storage::disk($disk)->download($attachment->path, $filename);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->authorize('send', $conversation);

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:50000'],
        ]);

        $files = collect($request->file('attachments', []))
            ->flatten()
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        $this->validateAttachments($files);

        $body = MessageContentSanitizer::clean((string) ($data['text'] ?? ''));
        $plain = MessageContentSanitizer::plain($body);

        if ($plain === '' && $files->isEmpty()) {
            return response()->json([
                'ok' => false,
                'error' => 'Debe escribir un mensaje o adjuntar al menos un archivo.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $message = $conversation->messages()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
                'body' => $body,
            ]);

            $this->storeAttachments($message, $files);

            DB::commit();
            $message->load(['user:id,nombres,apellido_paterno,apellido_materno,email', 'attachments']);
            $this->markConversationRead($conversation, $request->user()->id, (int) $message->id);

            try {
                if (class_exists(SendMessageNotifications::class)) {
                    SendMessageNotifications::dispatch($message->id);
                }
            } catch (\Throwable $notifyEx) {
                Log::warning('Fallo al disparar notificaciones del mensaje', [
                    'message_id' => $message->id,
                    'error' => $notifyEx->getMessage(),
                ]);
            }

            return response()->json([
                'ok' => true,
                'id' => $message->id,
                'item' => $this->messagePayload($message, (int) $request->user()->id, $conversation),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('messages.send failed', [
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Error interno al enviar el mensaje.',
            ], 500);
        }
    }

    private function validateAttachments($files): void
    {
        if ($files->count() > 10) {
            throw ValidationException::withMessages([
                'attachments' => 'Puede adjuntar hasta 10 archivos por mensaje.',
            ]);
        }

        $allowed = $this->allowedAttachmentExtensions();
        foreach ($files as $file) {
            if (! $file->isValid()) {
                throw ValidationException::withMessages([
                    'attachments' => 'Uno de los archivos no pudo ser leído correctamente.',
                ]);
            }

            if ($file->getSize() > 15 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'attachments' => 'Cada archivo debe pesar máximo 15 MB.',
                ]);
            }

            $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
            if (! in_array($extension, $allowed, true)) {
                throw ValidationException::withMessages([
                    'attachments' => 'Formato no permitido: ' . ($file->getClientOriginalName() ?: 'archivo') . '.',
                ]);
            }
        }
    }

    private function storeAttachments(Message $message, $files): void
    {
        foreach ($files as $file) {
            $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
            $directory = 'message_attachments/' . now()->format('Y/m');
            $storedName = (string) Str::uuid() . '.' . $extension;
            $path = $file->storeAs($directory, $storedName, 'local');
            $mime = $file->getClientMimeType() ?: 'application/octet-stream';

            $message->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName() ?: ('archivo.' . $extension), 240, ''),
                'mime_type' => $mime,
                'size' => (int) $file->getSize(),
                'is_image' => str_starts_with((string) $mime, 'image/') || in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true),
            ]);
        }
    }

    private function messagePayload(Message $message, int $currentUserId, Conversation $conversation): array
    {
        $message->loadMissing([
            'user:id,nombres,apellido_paterno,apellido_materno,email',
            'attachments',
        ]);

        $bodyHtml = MessageContentSanitizer::clean($message->body);

        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user' => $this->displayName($message->user),
            'me' => (int) $message->user_id === $currentUserId,
            'text' => MessageContentSanitizer::plain($bodyHtml),
            'body_html' => $bodyHtml,
            'created_at' => cl_datetime($message->created_at),
            'attachments' => $message->attachments->map(fn (MessageAttachment $attachment) => $this->attachmentPayload($attachment, $conversation))->values(),
        ];
    }

    private function attachmentPayload(MessageAttachment $attachment, Conversation $conversation): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => (int) $attachment->size,
            'size_label' => $this->formatBytes((int) $attachment->size),
            'is_image' => (bool) $attachment->is_image,
            'url' => route('messages.attachments.show', [$conversation, $attachment]),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.') . ' KB';
        }

        return $bytes . ' B';
    }

    private function allowedAttachmentExtensions(): array
    {
        return [
            'png', 'jpg', 'jpeg', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
            'ppt', 'pptx', 'zip', 'rar', '7z',
        ];
    }

    private function markConversationRead(Conversation $conversation, int $userId, ?int $messageId = null): void
    {
        $messageId = $messageId ?: (int) $conversation->messages()->max('id');

        if ($messageId <= 0) {
            return;
        }

        if (! $this->messageReadsAvailable()) {
            return;
        }

        $values = [
            'last_read_message_id' => $messageId,
            'read_at' => now(),
        ];

        if (Schema::hasColumn('message_reads', 'message_id')) {
            $values['message_id'] = $messageId;
        }

        MessageRead::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
            ],
            $values
        );
    }

    private function lastReadMessageId(int $conversationId, int $userId): int
    {
        if (! $this->messageReadsAvailable()) {
            return 0;
        }

        return (int) MessageRead::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->value('last_read_message_id');
    }

    private function unreadCount(Conversation $conversation, int $userId, int $lastReadId = 0): int
    {
        if (! $this->messageReadsAvailable()) {
            return 0;
        }

        return (int) Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $userId)
            ->where('id', '>', $lastReadId)
            ->count();
    }

    private function totalUnreadForUser(int $userId): int
    {
        if (! $this->messageReadsAvailable()) {
            return 0;
        }

        $conversationIds = DB::table('conversation_participants')
            ->where('user_id', $userId)
            ->pluck('conversation_id');

        if ($conversationIds->isEmpty()) {
            return 0;
        }

        return (int) Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('user_id', '!=', $userId)
            ->where(function ($query) use ($userId) {
                $query->whereNotExists(function ($subquery) use ($userId) {
                    $subquery->selectRaw(1)
                        ->from('message_reads')
                        ->whereColumn('message_reads.conversation_id', 'messages.conversation_id')
                        ->where('message_reads.user_id', $userId)
                        ->whereColumn('message_reads.last_read_message_id', '>=', 'messages.id');
                });
            })
            ->count();
    }

    private function messageReadsAvailable(): bool
    {
        try {
            return Schema::hasTable('message_reads')
                && Schema::hasColumn('message_reads', 'conversation_id')
                && Schema::hasColumn('message_reads', 'last_read_message_id');
        } catch (\Throwable) {
            return false;
        }
    }

    private function canStartConversation(User $user): bool
    {
        return FuncionarioAcDirectory::canUseDirectory($user)
            || FuncionarioAcDirectory::canStartGeneralConversation($user);
    }

    private function canStartWith(User $user, int $targetUserId): bool
    {
        if (FuncionarioAcDirectory::canStartGeneralConversation($user)) {
            return User::query()->whereKey($targetUserId)->exists();
        }

        if (FuncionarioAcDirectory::isEstablishmentUser($user)) {
            return FuncionarioAcDirectory::isActiveDirectoryUser($targetUserId);
        }

        return false;
    }

    private function displayName(?User $user): string
    {
        if (! $user) {
            return 'Usuario';
        }

        if (method_exists($user, 'displayName')) {
            return $user->displayName();
        }

        return trim(($user->nombres ?? '') . ' ' . ($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '')) ?: ($user->email ?? 'Usuario');
    }

    private function initials(?User $user): string
    {
        $name = $this->displayName($user);
        $parts = collect(preg_split('/\s+/', $name) ?: [])->filter()->take(2);

        return $parts->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: 'U';
    }

    private function isOnline(?User $user): bool
    {
        return (bool) ($user?->last_seen_at && $user->last_seen_at->gte(now()->subMinutes(5)));
    }

    private function avatarClass(?User $user): string
    {
        if (! $user || ! method_exists($user, 'hasRole')) {
            return 'role-muted';
        }

        if ($user->hasRole('admin')) {
            return 'role-admin';
        }

        if ($user->hasRole('director_ejecutivo')) {
            return 'role-director';
        }

        if ($user->hasAnyRole(['coordinador_gdp', 'coordinador_uatp'])) {
            return 'role-coord';
        }

        if ($user->hasAnyRole(['funcionario_slep', 'funcionario_ac'])) {
            return 'role-func';
        }

        if ($user->hasAnyRole(['funcionario_estab', 'funcionario_directivo_estab', 'funcionario_establecimiento', 'funcionario_directivo_establecimiento'])) {
            return 'role-estab';
        }

        return 'role-muted';
    }

    private function primaryRoleLabel(?User $user): string
    {
        if (! $user || ! method_exists($user, 'getRoleNames')) {
            return 'Usuario';
        }

        $role = $user->getRoleNames()->first();

        return $role ? SlepUiRegistry::roleLabel($role) : 'Usuario';
    }

    private function roleKey(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return match (true) {
            $role === 'admin' => 'admin',
            $role === 'director_ejecutivo' => 'director',
            in_array($role, ['coordinador_uatp', 'coordinador_gdp'], true) => 'coord',
            in_array($role, ['funcionario_slep', 'funcionario_ac'], true) => 'slep',
            in_array($role, ['funcionario_estab', 'funcionario_directivo_estab'], true) => 'estab',
            default => 'usuario',
        };
    }
}
