@php
    $tieneReembolsoCometido = (bool) (($cometido->solicita_reembolso ?? false) || ($cometido->reembolso ?? false) || ($cometido->total_reembolso ?? 0) > 0 || ($cometido->monto_reembolso_autorizado ?? 0) > 0);
@endphp
@if($tieneReembolsoCometido || in_array($cometido->estado ?? '', ['pendiente_rendicion','rendicion_enviada','en_revision_daf_rendicion','rendicion_autorizada_daf','en_juridica_resolucion_reembolso','en_pago_reembolso','reembolso_pagado']))
    <div class="rounded-2xl border border-purple-200 bg-purple-50 p-5 shadow-sm">
        <p class="text-xs uppercase font-semibold text-purple-700">Reembolso</p>
        <h3 class="text-lg font-bold text-slate-900">Rendición, Jurídica y pago</h3>
        <p class="text-sm text-slate-600 mt-1">Gestiona la rendición del establecimiento, autorización DAF, resolución jurídica y pago del reembolso.</p>
        <a href="{{ route('tramites.cometidos-funcionarios.rendicion.panel', $cometido) }}" class="inline-flex mt-3 px-4 py-2 rounded-xl bg-purple-700 text-white font-bold hover:bg-purple-800">Abrir gestión de reembolso</a>
    </div>
@endif
