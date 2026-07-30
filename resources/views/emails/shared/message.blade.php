@extends('emails.layouts.institutional')

@section('title', $greeting ?: config('brand.platform_name', 'Plataforma SLEP Andalién Costa'))
@section('preheader', $greeting ?: 'Notificación de la plataforma')

@section('content')
    @if (!empty($greeting))
        <p style="margin:0 0 16px 0;font-size:18px;font-weight:700;color:#0f172a;">{{ $greeting }}</p>
    @endif

    @if (!empty($lines))
        @foreach ($lines as $line)
            <p style="margin:0 0 12px 0;">{{ $line }}</p>
        @endforeach
    @endif

    @include('emails.partials.cta', ['url' => $actionUrl ?? null, 'text' => $actionText ?? 'Ver en la plataforma'])

    @if (!empty($outroLines))
        @foreach ($outroLines as $line)
            <p style="margin:0 0 12px 0;">{{ $line }}</p>
        @endforeach
    @endif

    <p style="margin:24px 0 0 0;color:#475569;">
        @if (!empty($salutation))
            {!! $salutation !!}
        @else
            Saludos cordiales,<br>
            {{ config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}
        @endif
    </p>
@endsection
