@push('styles')
<style>
    .cgr-page { color: var(--slep-text, #0f172a); }
    .cgr-page-header { background: linear-gradient(180deg, #fff 0%, #f8fbff 100%); border: 1px solid #d9e4f3; border-radius: 1.5rem; box-shadow: 0 18px 44px rgba(15, 23, 42, .08); padding: 1.5rem 1.75rem; }
    .cgr-page-header__eyebrow { display: flex; align-items: center; gap: .6rem; color: #64748b; font-size: .78rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; margin-bottom: .5rem; }
    .cgr-page-header__icon { display: inline-flex; align-items: center; justify-content: center; width: 2.6rem; height: 2.6rem; border-radius: 50%; background: linear-gradient(135deg, #0b3d91, #0d6efd); color: #fff; font-size: 1.1rem; box-shadow: 0 .5rem 1.25rem rgba(13, 110, 253, .2); }
    .cgr-page-header h1 { color: #0f172a; font-size: clamp(1.7rem, 2vw, 2.2rem); line-height: 1.15; font-weight: 800; }
    .cgr-page-header p { color: #475569; }
    .cgr-page .card { border: 1px solid #dbe4f0; border-radius: 1.15rem; box-shadow: 0 .65rem 1.75rem rgba(15, 23, 42, .055); overflow: hidden; }
    .cgr-page .card-header { background: linear-gradient(135deg, #f8fbff, #fff); border-bottom: 1px solid #e5edf6; color: #0f172a; font-size: 1.05rem; font-weight: 800; padding: 1rem 1.25rem; }
    .cgr-page .card-body { padding: 1.25rem; }
    .cgr-page .card-footer { background: #fff; border-top: 1px solid #e5edf6; }
    .cgr-page .form-label { color: #334155; font-size: .85rem; font-weight: 800; margin-bottom: .4rem; }
    .cgr-page .form-control, .cgr-page .form-select { min-height: 2.65rem; border-color: #dbe4f0; border-radius: .75rem; color: #0f172a; }
    .cgr-page .form-control:focus, .cgr-page .form-select:focus { border-color: #8ec1ff; box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .12); }
    .cgr-page .form-control[readonly] { background: #f8fafc; color: #334155; }
    .cgr-page .input-group-text { border-color: #dbe4f0; background: #f8fafc; color: #334155; }
    .cgr-page .btn { border-radius: 999px; font-weight: 700; padding: .55rem 1rem; }
    .cgr-page .input-group > :not(:first-child) { border-top-left-radius: 0; border-bottom-left-radius: 0; }
    .cgr-page .input-group > :not(:last-child) { border-top-right-radius: 0; border-bottom-right-radius: 0; }
    .cgr-page .btn-sm { padding: .4rem .75rem; }
    .cgr-page .badge { border-radius: 999px; padding: .4em .7em; }
    .cgr-page .alert { border-radius: 1rem; border-width: 1px; }
    .cgr-page .table { --bs-table-bg: transparent; }
    .cgr-page .table thead th { background: #f8fafc; color: #334155; font-size: .8rem; font-weight: 800; white-space: nowrap; }
    .cgr-page .table th, .cgr-page .table td { border-color: #e5edf6; padding: .75rem; }
    .cgr-page .table tbody tr:hover { background: #f8fbff; }
    .cgr-page .cgr-kpi .card-body { padding: 1.1rem 1.25rem; }
    .cgr-page .cgr-kpi__label { color: #64748b; font-size: .82rem; font-weight: 800; }
    .cgr-page .cgr-kpi__value { color: #0b3d91; font-size: 1.45rem; font-weight: 800; line-height: 1.2; margin: .35rem 0; }
    .cgr-page .cgr-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; }
    .cgr-page .cgr-empty i { display: block; color: #0d6efd; font-size: 1.6rem; margin-bottom: .5rem; }
    .cgr-page .cgr-tabs .nav-link { border: 1px solid #dbe4f0; border-radius: 999px; background: #fff; color: #334155; font-weight: 700; }
    .cgr-page .cgr-tabs .nav-link.active { border-color: #0d6efd; background: #eaf3ff; color: #0b3d91; }
    .cgr-page .cgr-tabs .nav-link.active .badge { background: #0d6efd !important; color: #fff !important; }
    .cgr-page .cgr-respaldos { min-width: 16rem; max-width: 22rem; overflow-wrap: anywhere; }
    .cgr-page .cgr-document-modal .modal-content { border: 1px solid #dbe4f0; border-radius: 1.15rem; }
    .cgr-page .cgr-document-modal .modal-header { background: #f8fbff; border-bottom-color: #e5edf6; }
    .cgr-page .cgr-document-modal .modal-footer { border-top-color: #e5edf6; }
    .cgr-page .cgr-quota-choice { border: 1px solid #dbe4f0; border-radius: .75rem; padding: .75rem .75rem .75rem 2.25rem; margin: 0; min-height: 3.5rem; }
    .cgr-page .cgr-quota-choice:has(input:checked) { border-color: #9ec5fe; background: #eff6ff; }
    .cgr-page .cgr-data-list dt { color: #64748b; font-size: .85rem; font-weight: 800; }
    .cgr-page .cgr-data-list dd { color: #0f172a; overflow-wrap: anywhere; }
    .cgr-page .cgr-data-list dt, .cgr-page .cgr-data-list dd { border-bottom: 1px solid #e5edf6; padding: .65rem 0; margin-bottom: 0; }
    .cgr-page .cgr-verification-card { max-width: 900px; }
    .cgr-page .cgr-verification-header h1 { color: #0f172a; font-size: clamp(1.7rem, 2vw, 2.1rem); font-weight: 800; }
    @media (max-width: 767.98px) {
        .cgr-page-header { padding: 1.25rem; }
        .cgr-page .card-body { padding: 1rem; }
        .cgr-page .cgr-page-actions { width: 100%; }
        .cgr-page .cgr-page-actions .btn, .cgr-page .cgr-page-actions form { flex: 1 1 auto; }
    }
</style>
@endpush
