@push('styles')
    <style>
        .dpa-header {
            background: linear-gradient(180deg, #fff 0%, #fff7f7 100%);
            border: 1px solid #f1d4d8;
            border-radius: 24px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, .08);
            overflow: hidden;
        }
        .dpa-header__top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem 1.75rem 1.25rem;
        }
        .dpa-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            color: #64748b;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: .45rem;
        }
        .dpa-eyebrow__icon {
            width: 2.75rem;
            height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            color: #fff;
            background: linear-gradient(135deg, #be123c 0%, #e11d48 100%);
            box-shadow: 0 10px 24px rgba(190, 18, 60, .25);
            font-size: 1.2rem;
        }
        .dpa-title {
            color: #0f172a;
            font-size: clamp(1.7rem, 2vw, 2.2rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: .4rem;
        }
        .dpa-subtitle { color: #475569; margin: 0; max-width: 60rem; }
        .dpa-role-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .65rem 1rem;
            border: 1px solid #fecdd3;
            border-radius: 999px;
            background: #fff1f2;
            color: #9f1239;
            font-weight: 700;
            white-space: nowrap;
        }
        .dpa-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            padding: 1.2rem 1.75rem 1.75rem;
            border-top: 1px solid #f3e4e7;
        }
        .dpa-summary__item {
            padding: 1rem 1.1rem;
            border: 1px solid #e4eaf1;
            border-radius: 18px;
            background: #fff;
        }
        .dpa-summary__label {
            color: #64748b;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .dpa-summary__value { color: #0f172a; font-size: 1.4rem; font-weight: 800; margin-top: .35rem; }
        .dpa-panel {
            border: 1px solid #d9e4f3;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
            overflow: hidden;
        }
        .dpa-panel__header { padding: 1.2rem 1.5rem 1rem; border-bottom: 1px solid #e8eef5; }
        .dpa-panel__title { color: #0f172a; font-size: 1.05rem; font-weight: 800; }
        .dpa-panel__subtitle { color: #64748b; font-size: .9rem; margin: .25rem 0 0; }
        .dpa-panel__body { padding: 1.35rem 1.5rem; }
        .dpa-status {
            display: inline-flex;
            align-items: center;
            padding: .38rem .7rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 800;
        }
        .dpa-status--danger { color: #991b1b; background: #fee2e2; }
        .dpa-status--warning { color: #92400e; background: #fef3c7; }
        .dpa-status--info { color: #075985; background: #e0f2fe; }
        .dpa-status--success { color: #166534; background: #dcfce7; }
        .dpa-document {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #f8fafc;
        }
        .dpa-document + .dpa-document { margin-top: .75rem; }
        .dpa-document__title { color: #0f172a; font-weight: 800; }
        .dpa-document__meta { color: #64748b; font-size: .86rem; margin-top: .2rem; }
        .dpa-table thead th {
            color: #64748b;
            font-size: .76rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            border-bottom-width: 1px;
        }
        .dpa-empty { padding: 3rem 1.5rem; color: #64748b; text-align: center; }
        @media (max-width: 767.98px) {
            .dpa-header__top { flex-direction: column; padding: 1.25rem; }
            .dpa-summary { grid-template-columns: 1fr; padding: 1rem 1.25rem 1.25rem; }
            .dpa-document { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush
