@if(!empty($url))
    <p style="margin:24px 0;">
        <a href="{{ $url }}" style="display:inline-block;background:#0b5ed7;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700;box-shadow:0 8px 18px rgba(11,94,215,.22);">
            {{ $text ?? 'Ver en la plataforma' }}
        </a>
    </p>
@endif
