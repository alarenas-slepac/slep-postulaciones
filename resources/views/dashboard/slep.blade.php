@extends('layouts.app')

@push('styles')
    <style>
        .slep-dashboard-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1.5rem;
            align-items: center;
            padding: 1.6rem;
            border-radius: 24px;
            border: 1px solid #d9e4f3;
            background: linear-gradient(135deg, #ffffff 0%, #f7fbff 100%);
            box-shadow: 0 18px 44px rgba(15, 23, 42, .07);
        }
        .slep-dashboard-kicker { display:inline-flex; align-items:center; gap:.5rem; color:#64748b; font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; margin-bottom:.45rem; }
        .slep-dashboard-title { font-size: clamp(1.7rem, 2vw, 2.25rem); font-weight: 900; line-height:1.1; color:#0f172a; margin-bottom:.45rem; }
        .slep-dashboard-subtitle { color:#475569; margin:0; max-width:64rem; }
        .slep-dashboard-date { display:inline-flex; align-items:center; gap:.5rem; border:1px solid #dbe4f0; background:#fff; border-radius:999px; padding:.65rem .9rem; color:#334155; font-weight:700; white-space:nowrap; }
        .slep-kpi-grid { display:grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap:1rem; }
        .slep-kpi { border:1px solid #dbe4f0; border-radius:22px; background:#fff; box-shadow:0 14px 34px rgba(15,23,42,.055); padding:1.1rem; display:flex; gap:1rem; align-items:center; min-height:126px; }
        .slep-kpi-icon { width:3.25rem; height:3.25rem; border-radius:1rem; display:flex; align-items:center; justify-content:center; font-size:1.35rem; color:#fff; flex:0 0 auto; }
        .slep-kpi-icon.primary { background:linear-gradient(135deg,#1d4ed8,#0d6efd); }
        .slep-kpi-icon.warning { background:linear-gradient(135deg,#f59e0b,#f97316); }
        .slep-kpi-icon.success { background:linear-gradient(135deg,#0f8f4d,#16a34a); }
        .slep-kpi-icon.info { background:linear-gradient(135deg,#0891b2,#0ea5e9); }
        .slep-kpi-icon.purple { background:linear-gradient(135deg,#7c3aed,#9333ea); }
        .slep-kpi-label { color:#64748b; font-weight:800; font-size:.82rem; margin-bottom:.25rem; }
        .slep-kpi-value { font-weight:900; font-size:1.65rem; color:#0f172a; line-height:1; }
        .slep-kpi-help { color:#64748b; font-size:.82rem; margin-top:.45rem; }
        .slep-dashboard-grid { display:grid; grid-template-columns: 1.15fr .85fr; gap:1.2rem; }
        .slep-panel { border:1px solid #dbe4f0; border-radius:22px; background:#fff; box-shadow:0 14px 34px rgba(15,23,42,.055); overflow:hidden; }
        .slep-panel-header { padding:1.25rem 1.35rem 1rem; border-bottom:1px solid #e8eef5; display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; }
        .slep-panel-kicker { color:#64748b; font-size:.73rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; margin-bottom:.25rem; }
        .slep-panel-title { font-size:1.2rem; font-weight:900; color:#0f172a; margin:0; }
        .slep-module-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:1rem; padding:1.25rem; }
        .slep-module { border:1px solid #dbe4f0; border-radius:18px; padding:1rem; color:#0f172a; text-decoration:none; transition:.18s ease; display:flex; flex-direction:column; min-height:160px; background:linear-gradient(180deg,#fff,#fbfdff); }
        .slep-module:hover { transform:translateY(-2px); box-shadow:0 14px 28px rgba(15,23,42,.08); color:#0f172a; }
        .slep-module-icon { width:2.8rem; height:2.8rem; border-radius:.95rem; display:flex; align-items:center; justify-content:center; background:#eff6ff; color:#0d47a1; font-size:1.25rem; margin-bottom:.85rem; }
        .slep-module-title { font-weight:900; margin-bottom:.35rem; }
        .slep-module-text { color:#64748b; font-size:.9rem; margin-bottom:1rem; }
        .slep-module-cta { margin-top:auto; color:#0d6efd; font-weight:800; font-size:.88rem; }
        .slep-task-list { padding: .3rem 1.25rem 1.25rem; }
        .slep-task { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1rem 0; border-bottom:1px solid #e8eef5; }
        .slep-task:last-child { border-bottom:0; }
        .slep-task-title { font-weight:900; color:#0f172a; margin-bottom:.2rem; }
        .slep-task-meta { color:#64748b; font-size:.85rem; line-height:1.45; }
        .slep-status { display:inline-flex; align-items:center; border-radius:999px; padding:.35rem .65rem; background:#f1f5f9; color:#334155; font-size:.78rem; font-weight:800; white-space:nowrap; }
        .slep-empty { padding:2rem 1.25rem; color:#64748b; text-align:center; }
        @media (max-width: 1199.98px) { .slep-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .slep-dashboard-grid { grid-template-columns: 1fr; } .slep-module-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .slep-dashboard-hero { grid-template-columns: 1fr; } .slep-kpi-grid, .slep-module-grid { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    @php
        $roleLabel = \App\Support\SlepUiRegistry::roleLabel($activeRole);
        $roleTone = \App\Support\SlepUiRegistry::roleTone($activeRole);
        $context = $dashboardContext ?? \App\Support\SlepUiRegistry::dashboardTitle($activeRole);
        $metrics = $cometidoMetrics ?? ['total' => 0, 'pendientes' => 0, 'observados' => 0, 'viatico' => 0, 'reembolso' => 0, 'monto_cdp' => 0, 'monto_viaticos' => 0, 'monto_reembolsos' => 0, 'monto_total_cometidos' => 0];
        $estadoLabels = \App\Models\CometidoFuncionario::ESTADOS;
    @endphp

    <div class="slep-dashboard">
        <section class="slep-dashboard-hero mb-4">
            <div>
                <div class="slep-dashboard-kicker">
                    <i class="bi bi-stars"></i>
                    {{ $roleLabel }}
                </div>
                <h1 class="slep-dashboard-title">Bienvenido/a, {{ $user->nombre_completo ?? $user->name ?? 'Usuario' }}</h1>
                <p class="slep-dashboard-subtitle">{{ $context['subtitle'] ?? 'Resumen general del sistema.' }}</p>
            </div>
            <div class="d-flex flex-column gap-2 align-items-start align-items-md-end">
                <span class="slep-dashboard-date"><i class="bi bi-calendar3"></i>{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</span>
                <span class="slep-role-chip slep-role-{{ $roleTone }}"><i class="bi bi-person-badge"></i>Rol activo: {{ $roleLabel }}</span>
            </div>
        </section>

        <section class="slep-kpi-grid mb-4">
            <article class="slep-kpi">
                <span class="slep-kpi-icon primary"><i class="bi bi-briefcase"></i></span>
                <div><div class="slep-kpi-label">Cometidos visibles</div><div class="slep-kpi-value">{{ number_format($metrics['total'], 0, ',', '.') }}</div><div class="slep-kpi-help">Según rol activo.</div></div>
            </article>
            <article class="slep-kpi">
                <span class="slep-kpi-icon warning"><i class="bi bi-hourglass-split"></i></span>
                <div><div class="slep-kpi-label">Pendientes</div><div class="slep-kpi-value">{{ number_format($metrics['pendientes'], 0, ',', '.') }}</div><div class="slep-kpi-help">Requieren acción o seguimiento.</div></div>
            </article>
            <article class="slep-kpi">
                <span class="slep-kpi-icon info"><i class="bi bi-airplane"></i></span>
                <div><div class="slep-kpi-label">Total viáticos</div><div class="slep-kpi-value">${{ number_format($metrics['monto_viaticos'] ?? 0, 0, ',', '.') }}</div><div class="slep-kpi-help">Monto de viáticos autorizados/pagados.</div></div>
            </article>
            <article class="slep-kpi">
                <span class="slep-kpi-icon success"><i class="bi bi-receipt-cutoff"></i></span>
                <div><div class="slep-kpi-label">Total reembolsos</div><div class="slep-kpi-value">${{ number_format($metrics['monto_reembolsos'] ?? 0, 0, ',', '.') }}</div><div class="slep-kpi-help">Monto real aprobado/pagado.</div></div>
            </article>
            <article class="slep-kpi">
                <span class="slep-kpi-icon purple"><i class="bi bi-cash-stack"></i></span>
                <div><div class="slep-kpi-label">Total cometidos</div><div class="slep-kpi-value">${{ number_format($metrics['monto_total_cometidos'] ?? 0, 0, ',', '.') }}</div><div class="slep-kpi-help">Viáticos más reembolsos.</div></div>
            </article>
        </section>

        <section class="slep-dashboard-grid">
            <div class="slep-panel">
                <div class="slep-panel-header">
                    <div>
                        <div class="slep-panel-kicker">Accesos por rol</div>
                        <h2 class="slep-panel-title">Módulos rápidos</h2>
                    </div>
                </div>
                <div class="slep-module-grid">
                    @forelse ($quickModules as $module)
                        <a href="{{ route($module['route']) }}" class="slep-module">
                            <span class="slep-module-icon"><i class="bi {{ $module['icon'] }}"></i></span>
                            <span class="slep-module-title">{{ $module['label'] }}</span>
                            <span class="slep-module-text">Acceso disponible para el rol activo {{ $roleLabel }}.</span>
                            <span class="slep-module-cta">Ir al módulo <i class="bi bi-arrow-right ms-1"></i></span>
                        </a>
                    @empty
                        <div class="slep-empty">No hay módulos rápidos configurados para el rol activo.</div>
                    @endforelse
                </div>
            </div>

            <div class="slep-panel">
                <div class="slep-panel-header">
                    <div>
                        <div class="slep-panel-kicker">Tareas recientes</div>
                        <h2 class="slep-panel-title">Cometidos funcionarios</h2>
                    </div>
                    @if (Route::has('tramites.cometidos-funcionarios.index'))
                        <a href="{{ route('tramites.cometidos-funcionarios.index') }}" class="btn btn-sm btn-outline-primary">Ver bandeja</a>
                    @endif
                </div>
                <div class="slep-task-list">
                    @forelse ($recentCometidos as $cometido)
                        <div class="slep-task">
                            <div>
                                <div class="slep-task-title">{{ $cometido->funcionario_nombre ?: 'Funcionario sin nombre' }}</div>
                                <div class="slep-task-meta">
                                    {{ $cometido->establecimiento->nombre_establecimiento ?? 'Sin establecimiento' }}<br>
                                    {{ optional($cometido->fecha_desde)->format('d-m-Y') ?: '—' }} a {{ optional($cometido->fecha_hasta)->format('d-m-Y') ?: '—' }} · {{ $cometido->comuna_destino_nombre ?: 'Sin destino' }}
                                </div>
                            </div>
                            <div class="text-end d-flex flex-column gap-2 align-items-end">
                                <span class="slep-status">{{ $estadoLabels[$cometido->estado] ?? $cometido->estado }}</span>
                                @if (Route::has('tramites.cometidos-funcionarios.show'))
                                    <a href="{{ route('tramites.cometidos-funcionarios.show', $cometido) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="slep-empty">No hay cometidos recientes para el rol activo.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
