{{-- Validador genérico para formularios Bootstrap 5 (en vivo + al enviar) --}}
<script>
    (function() {
        'use strict';

        // === Utilidades ===
        const msg = {
            required: 'Este campo es obligatorio.',
            email: 'Ingresa un correo válido.',
            rut: 'RUT inválido. Usa el formato 12.345.678-K.',
            login: 'Ingresa un RUT válido o un email válido.',
            min: (n) => `Debe tener al menos ${n} caracteres.`,
            match: 'Los campos no coinciden.',
        };

        function onlyDigits(s) {
            return (s || '').replace(/\D+/g, '');
        }

        function cleanRut(s) {
            return (s || '').toUpperCase().replace(/[^0-9K]/g, '');
        }

        function rutIsValid(value) {
            if (!value) return false;
            let v = value.toUpperCase().replace(/\./g, '').replace(/-/g, '').trim();
            if (!/^[0-9]+[0-9K]$/.test(v)) return false;
            let body = v.slice(0, -1),
                dv = v.slice(-1);
            let sum = 0,
                mul = 2;
            for (let i = body.length - 1; i >= 0; i--) {
                sum += parseInt(body[i], 10) * mul;
                mul = (mul === 7) ? 2 : mul + 1;
            }
            let res = 11 - (sum % 11);
            let expected = (res === 11) ? '0' : (res === 10 ? 'K' : String(res));
            return dv === expected;
        }

        function isEmail(s) {
            // dejamos al navegador validar email, pero esta ayuda rápida evita casos obvios
            return /^\S+@\S+\.\S+$/.test((s || '').trim());
        }

        function setInvalid(input, message) {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            let fb = input.closest('.form-group,.mb-3,.col,.col-md-4,.col-md-5,.col-md-3')?.querySelector(
                '.invalid-feedback');
            if (fb) {
                fb.textContent = message || msg.required;
            }
        }

        function clearInvalid(input) {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        }

        function validateField(input) {
            const val = (input.value || '').trim();

            // Requerido
            if (input.hasAttribute('required')) {
                if (!val) {
                    setInvalid(input, msg.required);
                    return false;
                }
            }

            // Tipo email
            if (input.type === 'email' && val) {
                if (!isEmail(val) || !input.checkValidity()) {
                    setInvalid(input, msg.email);
                    return false;
                }
            }

            // Password confirmation (si aplica)
            if (input.dataset.match) {
                const other = document.querySelector(input.dataset.match);
                if (other && other.value !== input.value) {
                    setInvalid(input, msg.match);
                    return false;
                }
            }

            // Validaciones personalizadas
            const vtype = input.dataset.validate;
            if (val && vtype) {
                if (vtype === 'rut' && !rutIsValid(val)) {
                    setInvalid(input, msg.rut);
                    return false;
                }
                if (vtype === 'login-email-or-rut') {
                    const ok = isEmail(val) || rutIsValid(val);
                    if (!ok) {
                        setInvalid(input, msg.login);
                        return false;
                    }
                }
            }

            clearInvalid(input);
            return true;
        }

        function attach(form) {
            const inputs = form.querySelectorAll('input,select,textarea');

            // En vivo (input + blur + change)
            inputs.forEach(el => {
                const h = () => validateField(el);
                el.addEventListener('input', h);
                el.addEventListener('blur', h);
                el.addEventListener('change', h);
            });

            // Al enviar
            form.addEventListener('submit', function(e) {
                let firstBad = null;
                let ok = true;
                inputs.forEach(el => {
                    // Validamos solo los visibles/habilitados (evita interferir con campos ocultos)
                    const visible = !!(el.offsetWidth || el.offsetHeight || el.getClientRects()
                        .length);
                    const enabled = !el.disabled;
                    if (visible && enabled) {
                        const good = validateField(el);
                        if (!good && !firstBad) firstBad = el;
                        ok = ok && good;
                    }
                });
                if (!ok) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Foco al primer campo inválido
                    if (firstBad) {
                        firstBad.focus();
                    }
                    // Alerta simple (una sola vez por form)
                    let alert = form.querySelector('.js-form-alert');
                    if (!alert) {
                        alert = document.createElement('div');
                        alert.className = 'js-form-alert alert alert-danger';
                        alert.textContent = 'Por favor corrige los campos marcados en rojo.';
                        form.prepend(alert);
                    }
                }
            }, {
                passive: false
            });
        }

        window.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form.js-validate').forEach(attach);
        });
    })();
</script>
