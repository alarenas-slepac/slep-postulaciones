<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('[data-ae-nav-toggle]');
    const nav = document.querySelector('[data-ae-nav]');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            const open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('ae-nav-open', open);
        });
        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('ae-nav-open');
            });
        });
    }

    const dialog = document.querySelector('[data-ae-lightbox]');
    if (dialog && typeof dialog.showModal === 'function') {
        const image = dialog.querySelector('[data-ae-lightbox-image]');
        const caption = dialog.querySelector('[data-ae-lightbox-caption]');
        document.querySelectorAll('[data-ae-gallery-image]').forEach(function (button) {
            button.addEventListener('click', function () {
                image.src = button.dataset.src || '';
                image.alt = button.dataset.alt || '';
                caption.textContent = button.dataset.caption || button.dataset.alt || '';
                dialog.showModal();
            });
        });
        dialog.querySelectorAll('[data-ae-lightbox-close]').forEach(function (button) {
            button.addEventListener('click', function () { dialog.close(); });
        });
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) dialog.close();
        });
    }
});
</script>
