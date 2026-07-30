@php
    $tone = $tone ?? 'info';
    $styles = [
        'success' => 'background:#e8f5ee;color:#0f5132;border-color:#badbcc;',
        'danger' => 'background:#fff1f2;color:#842029;border-color:#f5c2c7;',
        'warning' => 'background:#fff8e1;color:#7a5d00;border-color:#ffecb5;',
        'info' => 'background:#e8f6f8;color:#055160;border-color:#b6effb;',
    ];
@endphp
<span style="display:inline-block;border:1px solid;{{ $styles[$tone] ?? $styles['info'] }}border-radius:999px;padding:5px 10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;">
    {{ $text ?? 'Estado' }}
</span>
