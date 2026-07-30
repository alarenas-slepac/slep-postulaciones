@extends('layouts.app')

@section('content')
@php
    $lastId = 0;
    $participants = $conversation->participants ?? collect();
    $participantsCount = $participants->count();
    $messageCount = $messages->count();
@endphp

<div class="container py-4 messages-chat-view">
    <style>
        .messages-chat-view .cometido-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border-radius: .85rem; padding: .68rem .95rem; font-weight: 800; border: 1px solid transparent; text-decoration: none; transition: all .18s ease; }
        .messages-chat-view .cometido-btn.is-primary { background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); color: #fff; }
        .messages-chat-view .cometido-btn.is-secondary { background: #fff; color: #334155; border-color: #d7dee8; }
        .messages-chat-view .cometido-btn.is-primary:hover { box-shadow: 0 .45rem 1rem rgba(13,110,253,.2); }
        .messages-chat-view .cometido-btn.is-secondary:hover { background: #f8fafc; color: #0f172a; }
        .messages-chat-view .chat-workspace { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 1rem; align-items: start; }
        .messages-chat-view .chat-shell { border: 1px solid #d7dee8; border-radius: 1.15rem; background: #fff; box-shadow: 0 .55rem 1.6rem rgba(15,23,42,.06); overflow: hidden; }
        .messages-chat-view .chat-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1.15rem 1.25rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .messages-chat-view .chat-header-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .5rem; }
        .messages-chat-view .chat-person { display: flex; align-items: flex-start; gap: .9rem; min-width: 0; }
        .messages-chat-view .chat-kicker { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .12rem; }
        .messages-chat-view .chat-title { color: #0f172a; font-size: clamp(1.35rem, 2vw, 1.75rem); font-weight: 850; line-height: 1.1; margin-bottom: .25rem; }
        .messages-chat-view .chat-subtitle { color: #64748b; font-size: .88rem; margin-bottom: 0; }
        .messages-chat-view .avatar-pill { width: 2.75rem; height: 2.75rem; border-radius: .95rem; display: inline-flex; align-items: center; justify-content: center; font-weight: 900; flex: 0 0 auto; color: #fff; box-shadow: 0 .35rem .9rem rgba(15,23,42,.12); }
        .messages-chat-view .avatar-pill.role-admin { background: #0d6efd; }
        .messages-chat-view .avatar-pill.role-director { background: #1d4ed8; }
        .messages-chat-view .avatar-pill.role-coord { background: #7c3aed; }
        .messages-chat-view .avatar-pill.role-func { background: #0f8f4d; }
        .messages-chat-view .avatar-pill.role-estab { background: #475569; }
        .messages-chat-view .avatar-pill.role-muted { background: #94a3b8; }
        .messages-chat-view .presence-dot { width: .72rem; height: .72rem; border-radius: 999px; display: inline-block; background: #94a3b8; box-shadow: 0 0 0 3px #fff; }
        .messages-chat-view .presence-dot.is-online { background: #16a34a; }
        .messages-chat-view .info-chip { display: inline-flex; align-items: center; gap: .32rem; padding: .25rem .55rem; border-radius: 999px; background: #f1f5f9; border: 1px solid #dbe4f0; color: #475569; font-size: .76rem; font-weight: 800; }
        .messages-chat-view .info-chip.is-success { background: #ecfdf3; border-color: #bcebd0; color: #0f5132; }
        .messages-chat-view .info-chip.is-primary { background: #eef6ff; border-color: #b9d9ff; color: #0d47a1; }
        .messages-chat-view .chat-body { height: min(62vh, 620px); min-height: 430px; overflow: auto; padding: 1.25rem; background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); }
        .messages-chat-view .load-history-card { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .85rem; margin-bottom: 1rem; padding: .9rem 1rem; border: 1px solid #cfe1ff; border-radius: 1rem; background: #f8fbff; color: #334155; box-shadow: 0 .22rem .7rem rgba(15,23,42,.035); }
        .messages-chat-view .load-history-title { display: flex; align-items: center; gap: .45rem; color: #0f172a; font-weight: 850; margin-bottom: .15rem; }
        .messages-chat-view .load-history-help { color: #64748b; font-size: .82rem; line-height: 1.35; margin-bottom: 0; }
        .messages-chat-view .load-history-card .cometido-btn { padding: .55rem .8rem; white-space: nowrap; }
        .messages-chat-view .message-row { display: flex; margin-bottom: .85rem; }
        .messages-chat-view .message-row.is-mine { justify-content: flex-end; }
        .messages-chat-view .message-bubble { max-width: min(76%, 780px); border: 1px solid #e3eaf3; border-radius: 1rem 1rem 1rem .3rem; background: #fff; color: #0f172a; padding: .8rem .95rem; box-shadow: 0 .22rem .7rem rgba(15,23,42,.035); }
        .messages-chat-view .message-row.is-mine .message-bubble { border-color: #0d6efd; background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); color: #fff; border-radius: 1rem 1rem .3rem 1rem; }
        .messages-chat-view .message-author { font-size: .78rem; font-weight: 850; margin-bottom: .35rem; color: #334155; }
        .messages-chat-view .message-row.is-mine .message-author { color: rgba(255,255,255,.88); }
        .messages-chat-view .message-text { word-break: break-word; line-height: 1.45; }
        .messages-chat-view .message-text p { margin: 0 0 .45rem; }
        .messages-chat-view .message-text p:last-child { margin-bottom: 0; }
        .messages-chat-view .message-text ul, .messages-chat-view .message-text ol { margin: .35rem 0 .35rem 1.25rem; padding-left: .85rem; }
        .messages-chat-view .message-text a { color: inherit; text-decoration: underline; font-weight: 800; }
        .messages-chat-view .message-time { font-size: .72rem; opacity: .75; margin-top: .45rem; }
        .messages-chat-view .message-read-note { font-size: .7rem; opacity: .78; margin-top: .22rem; display: inline-flex; align-items: center; gap: .25rem; }
        .messages-chat-view .message-attachments { display: grid; gap: .55rem; margin-top: .65rem; }
        .messages-chat-view .attachment-card { display: flex; align-items: center; gap: .65rem; padding: .62rem .7rem; border: 1px solid #dbe4f0; border-radius: .8rem; background: rgba(255,255,255,.92); color: #0f172a; text-decoration: none; }
        .messages-chat-view .message-row.is-mine .attachment-card { color: #0f172a; background: rgba(255,255,255,.94); }
        .messages-chat-view .attachment-icon { width: 2.15rem; height: 2.15rem; border-radius: .65rem; display: inline-flex; align-items: center; justify-content: center; background: #eef6ff; color: #0d47a1; flex: 0 0 auto; }
        .messages-chat-view .attachment-name { font-weight: 850; line-height: 1.2; word-break: break-word; }
        .messages-chat-view .attachment-meta { color: #64748b; font-size: .74rem; }
        .messages-chat-view .attachment-preview { display: block; max-width: min(100%, 520px); border-radius: .9rem; border: 1px solid #dbe4f0; background: #fff; overflow: hidden; text-decoration: none; }
        .messages-chat-view .attachment-preview img { display: block; max-width: 100%; height: auto; }
        .messages-chat-view .empty-state { text-align: center; padding: 2.5rem 1rem; color: #64748b; border: 1px dashed #b8c5d6; border-radius: 1rem; background: #fff; }
        .messages-chat-view .empty-icon { width: 3rem; height: 3rem; border-radius: 999px; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; color: #475569; font-size: 1.35rem; margin-bottom: .8rem; }
        .messages-chat-view .chat-footer { padding: 1rem 1.15rem; background: #fff; border-top: 1px solid #e5edf6; }
        .messages-chat-view .composer-shell { border: 1px solid #d7dee8; border-radius: 1rem; overflow: hidden; background: #fff; }
        .messages-chat-view .editor-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; padding: .55rem; border-bottom: 1px solid #e5edf6; background: #f8fbff; }
        .messages-chat-view .editor-toolbar .btn-tool { width: 2.15rem; height: 2.15rem; border-radius: .65rem; border: 1px solid #d7dee8; background: #fff; color: #334155; display: inline-flex; align-items: center; justify-content: center; font-weight: 850; }
        .messages-chat-view .editor-toolbar .btn-tool:hover { background: #eef6ff; color: #0d47a1; border-color: #b9d9ff; }
        .messages-chat-view .emoji-toolbar-wrapper { position: relative; display: inline-flex; }
        .emoji-picker-panel { position: fixed; left: 1rem; top: 1rem; z-index: 1085; width: min(340px, calc(100vw - 1.5rem)); border: 1px solid #d7dee8; border-radius: 1rem; background: #fff; box-shadow: 0 .85rem 2rem rgba(15,23,42,.18); padding: .75rem; display: none; }
        .emoji-picker-panel.is-open { display: block; }
        .emoji-picker-header { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding-bottom: .55rem; margin-bottom: .55rem; border-bottom: 1px solid #e5edf6; }
        .emoji-picker-title { color: #0f172a; font-weight: 850; font-size: .82rem; }
        .emoji-picker-close { border: 0; background: transparent; color: #64748b; font-weight: 900; line-height: 1; }
        .emoji-grid { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: .25rem; max-height: 220px; overflow: auto; }
        .emoji-button { border: 1px solid transparent; background: #fff; border-radius: .55rem; min-width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; cursor: pointer; }
        .emoji-button:hover, .emoji-button:focus { background: #eef6ff; border-color: #b9d9ff; outline: none; }
        .messages-chat-view .editor-toolbar select { max-width: 150px; border-radius: .65rem; border-color: #d7dee8; height: 2.15rem; font-size: .8rem; }
        .messages-chat-view .editor-toolbar input[type="color"] { width: 2.15rem; height: 2.15rem; padding: .18rem; border-radius: .65rem; border: 1px solid #d7dee8; background: #fff; }
        .messages-chat-view .rich-editor { min-height: 112px; max-height: 260px; overflow: auto; padding: .85rem .95rem; outline: none; line-height: 1.45; }
        .messages-chat-view .rich-editor:empty::before { content: attr(data-placeholder); color: #94a3b8; }
        .messages-chat-view .composer-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem .8rem; border-top: 1px solid #e5edf6; background: #fbfdff; }
        .messages-chat-view .attachment-picker { display: inline-flex; align-items: center; gap: .45rem; border: 1px solid #d7dee8; border-radius: .8rem; padding: .55rem .75rem; background: #fff; color: #334155; font-weight: 800; cursor: pointer; }
        .messages-chat-view .attachment-picker:hover { background: #f8fbff; border-color: #b9d9ff; color: #0d47a1; }
        .messages-chat-view .attachment-preview-list { display: grid; gap: .5rem; margin-top: .75rem; }
        .messages-chat-view .pending-attachment { display: flex; align-items: center; gap: .65rem; border: 1px solid #dbe4f0; border-radius: .85rem; padding: .55rem .65rem; background: #f8fafc; }
        .messages-chat-view .pending-attachment img { width: 3rem; height: 3rem; object-fit: cover; border-radius: .55rem; border: 1px solid #dbe4f0; background: #fff; }
        .messages-chat-view .pending-attachment .remove-file { margin-left: auto; border: 0; background: transparent; color: #dc3545; font-weight: 900; }
        .messages-chat-view .status-line { min-height: 1.2rem; color: #64748b; font-size: .78rem; margin-top: .45rem; }
        .messages-chat-view .side-panel-card { border: 1px solid #d7dee8; border-radius: 1.15rem; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15,23,42,.045); overflow: hidden; margin-bottom: 1rem; }
        .messages-chat-view .side-panel-header { display: flex; align-items: flex-start; gap: .75rem; padding: 1rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .messages-chat-view .side-panel-icon { width: 2.35rem; height: 2.35rem; border-radius: .85rem; display: inline-flex; align-items: center; justify-content: center; background: #eef6ff; color: #0d47a1; flex: 0 0 auto; }
        .messages-chat-view .side-panel-kicker { color: #64748b; font-size: .72rem; font-weight: 850; text-transform: uppercase; letter-spacing: .035em; }
        .messages-chat-view .side-panel-title { color: #0f172a; font-weight: 850; line-height: 1.25; margin-bottom: .12rem; }
        .messages-chat-view .side-panel-body { padding: 1rem; }
        .messages-chat-view .detail-list { display: grid; gap: .65rem; }
        .messages-chat-view .detail-item { border: 1px solid #e3eaf3; border-radius: .85rem; padding: .75rem .85rem; background: #f8fafc; }
        .messages-chat-view .detail-label { color: #64748b; font-size: .7rem; font-weight: 850; text-transform: uppercase; letter-spacing: .025em; margin-bottom: .2rem; }
        .messages-chat-view .detail-value { color: #0f172a; font-weight: 750; line-height: 1.35; word-break: break-word; }
        .messages-chat-view .participant-list { display: grid; gap: .65rem; }
        .messages-chat-view .participant-item { display: flex; align-items: flex-start; gap: .65rem; border: 1px solid #e3eaf3; border-radius: .9rem; padding: .75rem; background: #fff; }
        .messages-chat-view .participant-name { color: #0f172a; font-weight: 800; line-height: 1.25; }
        .messages-chat-view .participant-meta { color: #64748b; font-size: .78rem; line-height: 1.35; }
        .messages-chat-view .use-box { border: 1px dashed #b8c5d6; border-radius: .95rem; background: #f8fafc; padding: .9rem; color: #334155; font-size: .84rem; line-height: 1.45; }
        @media (max-width: 1199.98px) { .messages-chat-view .chat-workspace { grid-template-columns: 1fr; } }
        @media (max-width: 767.98px) { .messages-chat-view .message-bubble { max-width: 90%; } .messages-chat-view .chat-body { height: 58vh; } .messages-chat-view .chat-header-actions { width: 100%; justify-content: flex-start; } .messages-chat-view .composer-actions { align-items: stretch; } .messages-chat-view .composer-actions .cometido-btn, .messages-chat-view .attachment-picker { width: 100%; justify-content: center; } }
    </style>

    <div class="mb-3">
        <a href="{{ route('messages.index') }}" class="cometido-btn is-secondary"><i class="bi bi-arrow-left"></i> Volver a mensajes</a>
    </div>

    <div class="chat-workspace">
        <div class="chat-shell">
            <div class="chat-header">
                <div class="chat-person">
                    <span class="avatar-pill {{ $avatarClass }}">{{ $other->initial() }}</span>
                    <div class="min-w-0">
                        <div class="chat-kicker">Conversación institucional</div>
                        <h1 class="chat-title">{{ $other->displayName() }}</h1>
                        <p class="chat-subtitle">{{ $otherRoleLabel ?? 'Usuario del sistema' }}</p>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="info-chip {{ $isOnline ? 'is-success' : '' }}"><span class="presence-dot {{ $isOnline ? 'is-online' : '' }}"></span> {{ $isOnline ? 'En línea' : 'Sin conexión reciente' }}</span>
                            <span class="info-chip is-primary"><i class="bi bi-envelope"></i> {{ $other->email }}</span>
                        </div>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <span class="info-chip"><i class="bi bi-shield-check"></i> Sólo participantes</span>
                    <span class="info-chip is-success"><i class="bi bi-check2-all"></i> Lectura registrada</span>
                    <button type="button" class="cometido-btn is-secondary" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise"></i> Actualizar</button>
                </div>
            </div>

            <div id="chatBody" class="chat-body">
                @if(!empty($hasOlderMessages))
                    <div class="load-history-card" id="loadHistoryCard">
                        <div>
                            <div class="load-history-title"><i class="bi bi-clock-history"></i> Historial disponible</div>
                            <p class="load-history-help">
                                Se muestran los últimos {{ $loadedMessagesCount ?? $messages->count() }} de {{ $messageTotal ?? $messages->count() }} mensajes.
                                Puede cargar el chat completo si necesita revisar mensajes anteriores.
                            </p>
                        </div>
                        <a href="{{ route('messages.show', ['conversation' => $conversation, 'full_chat' => 1]) }}" class="cometido-btn is-secondary">
                            <i class="bi bi-chat-left-text"></i> Cargar chat completo
                        </a>
                    </div>
                @elseif(!empty($isFullChatLoaded) && ($messageTotal ?? 0) > ($messageLoadLimit ?? 20))
                    <div class="load-history-card" id="loadHistoryCard">
                        <div>
                            <div class="load-history-title"><i class="bi bi-check2-all"></i> Chat completo cargado</div>
                            <p class="load-history-help">
                                Se cargaron {{ $messageTotal ?? $messages->count() }} mensajes de esta conversación.
                            </p>
                        </div>
                        <a href="{{ route('messages.show', $conversation) }}" class="cometido-btn is-secondary">
                            <i class="bi bi-lightning-charge"></i> Volver a carga rápida
                        </a>
                    </div>
                @endif

                @forelse($messages as $message)
                    @php
                        $mine = $message->user_id === auth()->id();
                        $lastId = $message->id;
                        $cleanBody = \App\Support\Messaging\MessageContentSanitizer::clean($message->body);
                        $attachments = $message->attachments ?? collect();
                    @endphp
                    <div class="message-row {{ $mine ? 'is-mine' : '' }}" data-message-id="{{ $message->id }}">
                        <div class="message-bubble">
                            @unless($mine)
                                <div class="message-author">{{ $message->user?->full_name ?: 'Usuario' }}</div>
                            @endunless
                            @if($cleanBody !== '')
                                <div class="message-text">{!! $cleanBody !!}</div>
                            @endif
                            @if($attachments->isNotEmpty())
                                <div class="message-attachments">
                                    @foreach($attachments as $attachment)
                                        @php $attachmentUrl = route('messages.attachments.show', [$conversation, $attachment]); @endphp
                                        @if($attachment->is_image)
                                            <a class="attachment-preview" href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                                                <img src="{{ $attachmentUrl }}" alt="{{ $attachment->original_name }}">
                                            </a>
                                        @endif
                                        <a class="attachment-card" href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                                            <span class="attachment-icon"><i class="bi {{ $attachment->is_image ? 'bi-image' : 'bi-paperclip' }}"></i></span>
                                            <span class="min-w-0 flex-grow-1">
                                                <span class="attachment-name d-block">{{ $attachment->original_name }}</span>
                                                <span class="attachment-meta d-block">{{ number_format(($attachment->size ?? 0) / 1024, 0, ',', '.') }} KB</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            <div class="message-time">{{ cl_datetime($message->created_at) }}</div>
                            @if($mine)
                                <div class="message-read-note"><i class="bi bi-check2"></i> Enviado</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state" id="emptyChatState">
                        <div class="empty-icon"><i class="bi bi-chat-square-dots"></i></div>
                        <div class="fw-semibold">Aún no hay mensajes</div>
                        <div class="small">Escriba el primer mensaje o adjunte una captura para iniciar la conversación.</div>
                    </div>
                @endforelse
            </div>

            <div class="chat-footer">
                <form id="sendForm" autocomplete="off" enctype="multipart/form-data" data-poll-url="{{ route('messages.poll', $conversation) }}" data-send-url="{{ route('messages.send', $conversation) }}" data-last-id="{{ $lastId }}">
                    <div class="composer-shell">
                        <div class="editor-toolbar" aria-label="Herramientas de formato">
                            <select class="form-select form-select-sm" data-command-select="fontName" title="Tipo de letra">
                                <option value="Arial">Arial</option>
                                <option value="Calibri">Calibri</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Tahoma">Tahoma</option>
                                <option value="Times New Roman">Times</option>
                                <option value="Verdana">Verdana</option>
                            </select>
                            <select class="form-select form-select-sm" data-command-select="fontSize" title="Tamaño">
                                <option value="2">Normal</option>
                                <option value="3">Mediano</option>
                                <option value="4">Grande</option>
                                <option value="5">Muy grande</option>
                            </select>
                            <button type="button" class="btn-tool" data-command="bold" title="Negrilla"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="btn-tool" data-command="italic" title="Cursiva"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="btn-tool" data-command="underline" title="Subrayado"><i class="bi bi-type-underline"></i></button>
                            <button type="button" class="btn-tool" data-command="insertUnorderedList" title="Lista"><i class="bi bi-list-ul"></i></button>
                            <button type="button" class="btn-tool" data-command="insertOrderedList" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
                            <button type="button" class="btn-tool" data-command="createLink" title="Insertar enlace"><i class="bi bi-link-45deg"></i></button>
                            <span class="emoji-toolbar-wrapper">
                                <button type="button" class="btn-tool" id="emojiToggle" title="Insertar emoji" aria-expanded="false" aria-controls="emojiPickerPanel"><i class="bi bi-emoji-smile"></i></button>
                                <div class="emoji-picker-panel" id="emojiPickerPanel" role="dialog" aria-label="Selector de emojis">
                                    <div class="emoji-picker-header">
                                        <span class="emoji-picker-title"><i class="bi bi-emoji-smile me-1"></i> Emojis rápidos</span>
                                        <button type="button" class="emoji-picker-close" id="emojiPickerClose" aria-label="Cerrar emojis">×</button>
                                    </div>
                                    <div class="emoji-grid" id="emojiGrid"></div>
                                </div>
                            </span>
                            <input type="color" data-command-color="foreColor" title="Color de texto" value="#0f172a">
                            <input type="color" data-command-color="hiliteColor" title="Color de fondo" value="#fff8bf">
                            <button type="button" class="btn-tool" data-command="removeFormat" title="Quitar formato"><i class="bi bi-eraser"></i></button>
                        </div>
                        <div id="richEditor" class="rich-editor" contenteditable="true" data-placeholder="Escriba un mensaje institucional. Puede pegar capturas con Ctrl + V..."></div>
                        <input type="hidden" id="messageHtml" name="text">
                        <div id="attachmentPreviewList" class="attachment-preview-list px-3"></div>
                        <div class="composer-actions">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <label class="attachment-picker" for="attachmentsInput"><i class="bi bi-paperclip"></i> Adjuntar archivo(s)</label>
                                <input id="attachmentsInput" type="file" name="attachments[]" multiple class="d-none" accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.ppt,.pptx,.zip,.rar,.7z">
                                <span class="small text-muted">También puede pegar una captura desde el portapapeles con Ctrl + V.</span>
                            </div>
                            <button id="sendButton" class="cometido-btn is-primary" type="submit"><i class="bi bi-send"></i> Enviar</button>
                        </div>
                    </div>
                    <div id="chatStatus" class="status-line"></div>
                </form>
            </div>
        </div>

        <aside class="chat-side">
            <div class="side-panel-card">
                <div class="side-panel-header">
                    <span class="side-panel-icon"><i class="bi bi-person-vcard"></i></span>
                    <div>
                        <div class="side-panel-kicker">Ficha del contacto</div>
                        <div class="side-panel-title">Información institucional</div>
                        <div class="small text-muted">Datos básicos del interlocutor y estado de la conversación.</div>
                    </div>
                </div>
                <div class="side-panel-body">
                    <div class="detail-list">
                        <div class="detail-item">
                            <div class="detail-label">Contacto principal</div>
                            <div class="detail-value">{{ $other->displayName() }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Rol / perfil</div>
                            <div class="detail-value">{{ $otherRoleLabel ?? 'Usuario del sistema' }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Correo institucional</div>
                            <div class="detail-value">{{ $other->email }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Actividad</div>
                            <div class="detail-value">{{ $isOnline ? 'En línea ahora' : 'Sin conexión reciente' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="side-panel-card">
                <div class="side-panel-header">
                    <span class="side-panel-icon"><i class="bi bi-people"></i></span>
                    <div>
                        <div class="side-panel-kicker">Participantes</div>
                        <div class="side-panel-title">{{ $participantsCount }} integrante(s)</div>
                    </div>
                </div>
                <div class="side-panel-body">
                    <div class="participant-list">
                        @foreach($participants as $participant)
                            @php
                                $participantRole = method_exists($participant, 'getRoleNames')
                                    ? ($participant->getRoleNames()->first() ?? 'Usuario del sistema')
                                    : 'Usuario del sistema';
                            @endphp
                            <div class="participant-item">
                                <span class="avatar-pill {{ $participant->id === auth()->id() ? 'role-admin' : 'role-muted' }}">{{ method_exists($participant, 'initial') ? $participant->initial() : 'U' }}</span>
                                <div class="min-w-0">
                                    <div class="participant-name">{{ method_exists($participant, 'displayName') ? $participant->displayName() : ($participant->email ?? 'Usuario') }}</div>
                                    <div class="participant-meta">{{ \App\Support\SlepUiRegistry::roleLabel($participantRole) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="side-panel-card">
                <div class="side-panel-header">
                    <span class="side-panel-icon"><i class="bi bi-info-circle"></i></span>
                    <div>
                        <div class="side-panel-kicker">Uso institucional</div>
                        <div class="side-panel-title">Buenas prácticas</div>
                    </div>
                </div>
                <div class="side-panel-body">
                    <div class="use-box">
                        Puede usar texto enriquecido, adjuntar uno o varios archivos, o pegar capturas de pantalla directamente con Ctrl + V. Evite compartir datos sensibles que no correspondan a la gestión tratada.
                    </div>
                    <div class="detail-list mt-3">
                        <div class="detail-item">
                            <div class="detail-label">Mensajes cargados</div>
                            <div class="detail-value">{{ $messageCount }} último(s) mensaje(s)</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Límite por mensaje</div>
                            <div class="detail-value">Hasta 10 archivos, máximo 15 MB cada uno.</div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('sendForm');
        const editor = document.getElementById('richEditor');
        const hidden = document.getElementById('messageHtml');
        const fileInput = document.getElementById('attachmentsInput');
        const previewList = document.getElementById('attachmentPreviewList');
        const sendButton = document.getElementById('sendButton');
        const body = document.getElementById('chatBody');
        const status = document.getElementById('chatStatus');
        const emojiToggle = document.getElementById('emojiToggle');
        const emojiPanel = document.getElementById('emojiPickerPanel');
        const emojiGrid = document.getElementById('emojiGrid');
        const emojiClose = document.getElementById('emojiPickerClose');
        if (!form || !editor || !hidden || !body || !fileInput) return;

        let lastId = parseInt(form.dataset.lastId || '0', 10);
        const pollUrl = form.dataset.pollUrl;
        const sendUrl = form.dataset.sendUrl;
        let consecutiveErrors = 0;
        let attachmentsStore = new DataTransfer();

        function setStatus(message) {
            if (status) status.textContent = message || '';
        }

        function scrollBottom() {
            body.scrollTop = body.scrollHeight;
        }

        function appendText(parent, value) {
            parent.appendChild(document.createTextNode(value || ''));
        }

        function stripHtml(value) {
            const tmp = document.createElement('div');
            tmp.innerHTML = value || '';
            return (tmp.textContent || tmp.innerText || '').trim();
        }

        function normalizeEditorSpaces(value) {
            return String(value || '')
                .replace(/&amp;nbsp;/gi, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\u00a0/g, ' ');
        }

        function fileSizeLabel(bytes) {
            bytes = parseInt(bytes || 0, 10);
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
            if (bytes >= 1024) return Math.round(bytes / 1024).toLocaleString('es-CL') + ' KB';
            return bytes + ' B';
        }

        function renderAttachment(parent, attachment) {
            if (!attachment) return;

            if (attachment.is_image && attachment.url) {
                const preview = document.createElement('a');
                preview.className = 'attachment-preview';
                preview.href = attachment.url;
                preview.target = '_blank';
                preview.rel = 'noopener';
                const img = document.createElement('img');
                img.src = attachment.url;
                img.alt = attachment.name || 'Captura adjunta';
                preview.appendChild(img);
                parent.appendChild(preview);
            }

            const card = document.createElement('a');
            card.className = 'attachment-card';
            card.href = attachment.url || '#';
            card.target = '_blank';
            card.rel = 'noopener';

            const icon = document.createElement('span');
            icon.className = 'attachment-icon';
            const iconElement = document.createElement('i');
            iconElement.className = 'bi ' + (attachment.is_image ? 'bi-image' : 'bi-paperclip');
            icon.appendChild(iconElement);

            const info = document.createElement('span');
            info.className = 'min-w-0 flex-grow-1';
            const name = document.createElement('span');
            name.className = 'attachment-name d-block';
            appendText(name, attachment.name || 'Archivo adjunto');
            const meta = document.createElement('span');
            meta.className = 'attachment-meta d-block';
            appendText(meta, attachment.size_label || '');
            info.appendChild(name);
            info.appendChild(meta);

            card.appendChild(icon);
            card.appendChild(info);
            parent.appendChild(card);
        }

        function appendMessage(message) {
            const empty = document.getElementById('emptyChatState');
            if (empty) empty.remove();

            const row = document.createElement('div');
            row.className = 'message-row ' + (message.me ? 'is-mine' : '');
            row.dataset.messageId = message.id;

            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';

            if (!message.me) {
                const author = document.createElement('div');
                author.className = 'message-author';
                appendText(author, message.user || 'Usuario');
                bubble.appendChild(author);
            }

            if (message.body_html) {
                const text = document.createElement('div');
                text.className = 'message-text';
                text.innerHTML = normalizeEditorSpaces(message.body_html);
                bubble.appendChild(text);
            } else if (message.text) {
                const text = document.createElement('div');
                text.className = 'message-text';
                appendText(text, message.text);
                bubble.appendChild(text);
            }

            if (message.attachments && message.attachments.length) {
                const wrap = document.createElement('div');
                wrap.className = 'message-attachments';
                message.attachments.forEach(function (attachment) { renderAttachment(wrap, attachment); });
                bubble.appendChild(wrap);
            }

            const time = document.createElement('div');
            time.className = 'message-time';
            appendText(time, message.created_at || '');
            bubble.appendChild(time);

            if (message.me) {
                const readNote = document.createElement('div');
                readNote.className = 'message-read-note';
                const icon = document.createElement('i');
                icon.className = 'bi bi-check2';
                readNote.appendChild(icon);
                appendText(readNote, ' Enviado');
                bubble.appendChild(readNote);
            }

            row.appendChild(bubble);
            body.appendChild(row);
            lastId = Math.max(lastId, parseInt(message.id || '0', 10));
        }

        function syncHidden() {
            hidden.value = normalizeEditorSpaces(editor.innerHTML).trim();
        }

        let savedEditorRange = null;

        function editorContainsSelection() {
            const selection = window.getSelection();
            return selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode);
        }

        function saveEditorSelection() {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0 || !editor.contains(selection.anchorNode)) return;
            savedEditorRange = selection.getRangeAt(0).cloneRange();
        }

        function restoreEditorSelection() {
            editor.focus();
            const selection = window.getSelection();
            if (!selection) return;

            selection.removeAllRanges();
            if (savedEditorRange) {
                selection.addRange(savedEditorRange);
                return;
            }

            const range = document.createRange();
            range.selectNodeContents(editor);
            range.collapse(false);
            selection.addRange(range);
        }

        function insertEmoji(emoji) {
            restoreEditorSelection();

            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) {
                editor.appendChild(document.createTextNode(emoji));
            } else {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                const node = document.createTextNode(emoji);
                range.insertNode(node);
                range.setStartAfter(node);
                range.setEndAfter(node);
                selection.removeAllRanges();
                selection.addRange(range);
                savedEditorRange = range.cloneRange();
            }

            syncHidden();
            editor.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function setupEmojiPicker() {
            if (!emojiToggle || !emojiPanel || !emojiGrid) return;

            const emojis = [
                '😀','😃','😄','😁','😊','🙂','😉','😍',
                '😂','🤣','😅','😎','🤔','🙌','👏','👍',
                '👎','👌','🙏','💪','✅','☑️','✔️','❌',
                '⚠️','📌','📎','📄','📅','🕒','📣','🔔',
                '💬','📩','📤','📥','💡','🔎','📝','📊',
                '🏫','🏢','🚗','🚌','✈️','🎯','⭐','❤️'
            ];

            if (emojiPanel.parentElement !== document.body) {
                document.body.appendChild(emojiPanel);
            }

            function closeEmojiPicker() {
                emojiPanel.classList.remove('is-open');
                emojiPanel.style.left = '';
                emojiPanel.style.top = '';
                emojiToggle.setAttribute('aria-expanded', 'false');
            }

            function positionEmojiPicker() {
                const rect = emojiToggle.getBoundingClientRect();
                const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                const margin = 12;
                const panelWidth = Math.min(340, Math.max(260, viewportWidth - (margin * 2)));

                emojiPanel.style.width = panelWidth + 'px';
                emojiPanel.style.left = margin + 'px';
                emojiPanel.style.top = margin + 'px';

                const panelHeight = emojiPanel.offsetHeight || 310;
                let left = rect.left;
                let top = rect.top - panelHeight - 8;

                if (left + panelWidth > viewportWidth - margin) {
                    left = viewportWidth - panelWidth - margin;
                }
                if (left < margin) {
                    left = margin;
                }
                if (top < margin) {
                    top = rect.bottom + 8;
                }
                if (top + panelHeight > viewportHeight - margin) {
                    top = Math.max(margin, viewportHeight - panelHeight - margin);
                }

                emojiPanel.style.left = left + 'px';
                emojiPanel.style.top = top + 'px';
            }

            function openEmojiPicker() {
                emojiPanel.classList.add('is-open');
                emojiToggle.setAttribute('aria-expanded', 'true');
                positionEmojiPicker();
            }

            emojiGrid.innerHTML = '';
            emojis.forEach(function (emoji) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'emoji-button';
                button.textContent = emoji;
                button.setAttribute('aria-label', 'Insertar emoji ' + emoji);
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    insertEmoji(emoji);
                    editor.focus();
                    if (emojiPanel.classList.contains('is-open')) {
                        positionEmojiPicker();
                    }
                });
                emojiGrid.appendChild(button);
            });

            emojiToggle.addEventListener('mousedown', function () {
                if (editorContainsSelection()) saveEditorSelection();
            });

            emojiToggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (emojiPanel.classList.contains('is-open')) {
                    closeEmojiPicker();
                } else {
                    openEmojiPicker();
                }
            });

            if (emojiClose) {
                emojiClose.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    closeEmojiPicker();
                    editor.focus();
                });
            }

            document.addEventListener('click', function (event) {
                if (!emojiPanel.classList.contains('is-open')) return;
                if (emojiPanel.contains(event.target) || emojiToggle.contains(event.target)) return;
                closeEmojiPicker();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && emojiPanel.classList.contains('is-open')) {
                    closeEmojiPicker();
                }
            });

            window.addEventListener('resize', function () {
                if (emojiPanel.classList.contains('is-open')) positionEmojiPicker();
            });

            window.addEventListener('scroll', function () {
                if (emojiPanel.classList.contains('is-open')) positionEmojiPicker();
            }, true);
        }

        function refreshFileInput() {
            fileInput.files = attachmentsStore.files;
            renderPendingFiles();
        }

        function addFiles(files) {
            const current = attachmentsStore.files.length;
            const incoming = Array.from(files || []);
            if (current + incoming.length > 10) {
                alert('Puede adjuntar hasta 10 archivos por mensaje.');
                return;
            }

            incoming.forEach(function (file) {
                if (file.size > 15 * 1024 * 1024) {
                    alert('El archivo ' + file.name + ' supera los 15 MB.');
                    return;
                }
                attachmentsStore.items.add(file);
            });

            refreshFileInput();
        }

        function removeFile(index) {
            const next = new DataTransfer();
            Array.from(attachmentsStore.files).forEach(function (file, currentIndex) {
                if (currentIndex !== index) next.items.add(file);
            });
            attachmentsStore = next;
            refreshFileInput();
        }

        function renderPendingFiles() {
            previewList.innerHTML = '';
            Array.from(attachmentsStore.files).forEach(function (file, index) {
                const item = document.createElement('div');
                item.className = 'pending-attachment';

                if (file.type && file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.onload = function () { URL.revokeObjectURL(img.src); };
                    item.appendChild(img);
                } else {
                    const icon = document.createElement('span');
                    icon.className = 'attachment-icon';
                    const iconElement = document.createElement('i');
                    iconElement.className = 'bi bi-paperclip';
                    icon.appendChild(iconElement);
                    item.appendChild(icon);
                }

                const info = document.createElement('span');
                info.className = 'min-w-0';
                const name = document.createElement('span');
                name.className = 'attachment-name d-block';
                appendText(name, file.name || 'Archivo adjunto');
                const meta = document.createElement('span');
                meta.className = 'attachment-meta d-block';
                appendText(meta, fileSizeLabel(file.size));
                info.appendChild(name);
                info.appendChild(meta);
                item.appendChild(info);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'remove-file';
                remove.innerHTML = '<i class="bi bi-x-lg"></i>';
                remove.addEventListener('click', function () { removeFile(index); });
                item.appendChild(remove);

                previewList.appendChild(item);
            });
        }

        editor.addEventListener('keyup', saveEditorSelection);
        editor.addEventListener('mouseup', saveEditorSelection);
        editor.addEventListener('input', saveEditorSelection);
        editor.addEventListener('focus', saveEditorSelection);
        document.addEventListener('selectionchange', function () {
            if (editorContainsSelection()) saveEditorSelection();
        });

        setupEmojiPicker();

        document.querySelectorAll('[data-command]').forEach(function (button) {
            button.addEventListener('click', function () {
                const command = button.dataset.command;
                editor.focus();
                if (command === 'createLink') {
                    const url = prompt('Ingrese URL o correo de enlace:');
                    if (url) document.execCommand('createLink', false, url);
                    return;
                }
                document.execCommand(command, false, null);
                syncHidden();
            });
        });

        document.querySelectorAll('[data-command-select]').forEach(function (select) {
            select.addEventListener('change', function () {
                editor.focus();
                document.execCommand(select.dataset.commandSelect, false, select.value);
                syncHidden();
            });
        });

        document.querySelectorAll('[data-command-color]').forEach(function (input) {
            input.addEventListener('input', function () {
                editor.focus();
                document.execCommand(input.dataset.commandColor, false, input.value);
                syncHidden();
            });
        });

        editor.addEventListener('input', syncHidden);
        editor.addEventListener('paste', function (event) {
            const clipboard = event.clipboardData;
            if (!clipboard || !clipboard.files || clipboard.files.length === 0) return;

            const images = Array.from(clipboard.files).filter(function (file) {
                return file.type && file.type.startsWith('image/');
            });

            if (images.length) {
                event.preventDefault();
                const renamed = images.map(function (file, index) {
                    const extension = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                    return new File([file], 'captura-' + Date.now() + '-' + (index + 1) + '.' + extension, { type: file.type });
                });
                addFiles(renamed);
                setStatus('Captura agregada como archivo adjunto.');
            }
        });

        fileInput.addEventListener('change', function () {
            addFiles(fileInput.files);
        });

        async function poll() {
            if (document.hidden) return;
            try {
                const url = new URL(pollUrl, window.location.origin);
                url.searchParams.set('after_id', lastId);
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) return;
                const data = await response.json();
                const items = data.items || [];
                items.forEach(appendMessage);
                if (items.length) scrollBottom();
                if (typeof data.unread_total !== 'undefined') {
                    document.body.dataset.unreadMessages = data.unread_total;
                }
                consecutiveErrors = 0;
                setStatus('');
            } catch (error) {
                consecutiveErrors++;
                if (consecutiveErrors >= 3) {
                    setStatus('No fue posible actualizar la conversación. Reintentando...');
                }
            }
        }

        scrollBottom();
        setInterval(poll, 3000);

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            syncHidden();

            const text = hidden.value.trim();
            if (!stripHtml(text) && attachmentsStore.files.length === 0) {
                setStatus('Debe escribir un mensaje o adjuntar al menos un archivo.');
                editor.focus();
                return;
            }

            const formData = new FormData();
            formData.append('text', text);
            Array.from(attachmentsStore.files).forEach(function (file) {
                formData.append('attachments[]', file, file.name);
            });

            editor.setAttribute('contenteditable', 'false');
            fileInput.disabled = true;
            sendButton.disabled = true;
            setStatus('Enviando mensaje...');

            try {
                const response = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const data = await response.json().catch(() => ({ ok: false }));
                if (!response.ok || !data.ok) {
                    alert(data.error || 'No se pudo enviar el mensaje.');
                    return;
                }
                editor.innerHTML = '';
                hidden.value = '';
                attachmentsStore = new DataTransfer();
                refreshFileInput();
                if (data.item) appendMessage(data.item);
                scrollBottom();
                setStatus('');
            } catch (error) {
                alert('No se pudo enviar el mensaje.');
            } finally {
                editor.setAttribute('contenteditable', 'true');
                fileInput.disabled = false;
                sendButton.disabled = false;
                editor.focus();
            }
        });
    })();
</script>
@endpush
