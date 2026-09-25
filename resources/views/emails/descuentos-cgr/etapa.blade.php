@extends('emails.layouts.institutional')

@section('title', $evento === 'finanzas' ? 'Descuento CGR para Finanzas' : 'Descuento CGR para Auditoría')
@section('preheader', 'Nuevo registro CGR pendiente de gestión')

@section('content')
    <p style="margin:0 0 14px;color:#334155;line-height:1.6;">Se ha enviado un descuento CGR a {{ $evento === 'finanzas' ? 'Finanzas' : 'Auditoría Interna' }}.</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #dbe7f3;border-radius:12px;background:#f8fafc;">
        <tr><td style="padding:12px 16px;color:#475569;width:35%;">Resolución</td><td style="padding:12px 16px;color:#0f172a;font-weight:700;">{{ $descuento->numero_resolucion }}</td></tr>
        <tr><td style="padding:12px 16px;color:#475569;">Persona</td><td style="padding:12px 16px;color:#0f172a;">{{ $descuento->nombre }}</td></tr>
        <tr><td style="padding:12px 16px;color:#475569;">Cuotas</td><td style="padding:12px 16px;color:#0f172a;">{{ $descuento->numero_cuotas }}</td></tr>
        <tr><td style="padding:12px 16px;color:#475569;">Estado</td><td style="padding:12px 16px;color:#0f172a;">{{ $descuento->etiquetaEstado() }}</td></tr>
    </table>
    @include('emails.partials.cta', ['url' => route('descuentos-cgr.show', $descuento), 'text' => 'Ver descuento CGR'])
    <p style="margin:18px 0 0;color:#475569;">{{ config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}</p>
@endsection
