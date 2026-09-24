@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <style>
        .ur-page .select2-container--bootstrap-5 .select2-selection--single {
            min-height: 2.65rem;
            padding: .58rem 2.2rem .58rem .85rem;
            border: 1px solid #dbe4f0;
            border-radius: .75rem;
            background: #fff;
            font-family: "Gilmer", system-ui, sans-serif;
            box-shadow: none;
        }
        .ur-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding: 0;
            color: #0f172a;
            line-height: 1.4;
        }
        .ur-page .select2-container--bootstrap-5.select2-container--focus .select2-selection--single,
        .ur-page .select2-container--bootstrap-5.select2-container--open .select2-selection--single {
            border-color: #8ec1ff;
            box-shadow: 0 0 0 .18rem rgba(13, 110, 253, .12);
        }
        .ur-page .form-select.is-invalid + .select2-container .select2-selection--single { border-color: #dc3545; }
        .ur-select-dropdown.select2-dropdown {
            z-index: 1080;
            overflow: hidden;
            border: 1px solid #dbe4f0;
            border-radius: .75rem;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .12);
            font-family: "Gilmer", system-ui, sans-serif;
        }
        .ur-select-dropdown .select2-search--dropdown { padding: .65rem; background: #f8fbff; }
        .ur-select-dropdown .select2-search__field { min-height: 2.3rem; border-color: #dbe4f0; border-radius: .65rem; }
        .ur-select-dropdown .select2-results__option { padding: .6rem .8rem; }
        .select2-container--bootstrap-5 .ur-select-dropdown .select2-results__options .select2-results__option.select2-results__option--highlighted { background: #eef6ff; color: #0d47a1; }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roles = Array.from(document.querySelectorAll('.js-role-checkbox'));
            const wrapper = document.getElementById('establecimiento-wrapper');
            const verification = document.getElementById('verification-wrapper');
            const establishment = document.getElementById('user-establecimiento');

            if (roles.length && wrapper && establishment) {
                const updateEstablishment = () => {
                    const needsEstablishment = roles.some((role) => role.checked && [
                        'funcionario', 'funcionario_estab', 'funcionario_directivo_estab',
                    ].includes(role.value));

                    wrapper.hidden = !needsEstablishment;
                    verification?.classList.toggle('col-lg-4', needsEstablishment);
                    establishment.required = needsEstablishment;

                    if (!needsEstablishment && establishment.value !== '') {
                        establishment.value = '';
                        if (window.jQuery?.fn?.select2) window.jQuery(establishment).trigger('change');
                    }
                    if (!needsEstablishment) {
                        establishment.classList.remove('is-invalid');
                        establishment.removeAttribute('aria-invalid');
                        document.getElementById('user-establecimiento-error')?.classList.remove('d-block');
                    }
                };

                roles.forEach((role) => role.addEventListener('change', updateEstablishment));
                updateEstablishment();
            }

            if (establishment) {
                const feedback = document.getElementById('user-establecimiento-error');
                const clearEstablishmentError = () => {
                    if (!establishment.value) return;
                    establishment.classList.remove('is-invalid');
                    establishment.removeAttribute('aria-invalid');
                    feedback?.classList.remove('d-block');
                };

                establishment.addEventListener('change', clearEstablishmentError);
                establishment.form?.addEventListener('submit', (event) => {
                    if (wrapper.hidden || establishment.value) return;

                    event.preventDefault();
                    event.stopImmediatePropagation();
                    establishment.classList.add('is-invalid');
                    establishment.setAttribute('aria-invalid', 'true');
                    if (feedback) {
                        feedback.textContent = 'Selecciona un establecimiento.';
                        feedback.classList.add('d-block');
                    }
                    if (window.jQuery?.fn?.select2 && window.jQuery(establishment).data('select2')) {
                        window.jQuery(establishment).select2('open');
                    } else {
                        establishment.focus();
                    }
                }, true);
            }

            if (!window.jQuery?.fn?.select2) return;

            window.jQuery('.js-ur-searchable-select').each(function () {
                window.jQuery(this).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    allowClear: true,
                    placeholder: this.dataset.placeholder || 'Seleccionar',
                    dropdownCssClass: 'ur-select-dropdown',
                    language: {
                        noResults: () => 'Sin resultados',
                        searching: () => 'Buscando…',
                        removeAllItems: () => 'Quitar selección',
                    },
                });
            });
        });
    </script>
@endpush
