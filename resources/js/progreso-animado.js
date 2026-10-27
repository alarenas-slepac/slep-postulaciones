// Mejora progresiva: el HTML conserva siempre los valores finales accesibles.
const preparados = new WeakSet();

export function iniciarProgresoAnimado(root = document, browser = window) {
    const grupos = [...root.querySelectorAll('[data-slep-reveal]')].filter(grupo => !preparados.has(grupo));
    if (!grupos.length) return () => {};
    grupos.forEach(grupo => preparados.add(grupo));
    const movimiento = browser.matchMedia?.('(prefers-reduced-motion: reduce)');
    if (movimiento?.matches || !browser.IntersectionObserver || !browser.requestAnimationFrame) return () => {};

    const estados = new Map();
    let observador;
    const mostrar = (estado, avance) => {
        estado.barras.forEach(({ elemento, valor }) => { elemento.style.width = `${valor * avance}%`; });
        estado.cifras.forEach(({ elemento, valor, formato, sufijo, texto }) => {
            elemento.textContent = avance === 1 ? texto : formato.format(valor * avance) + sufijo;
        });
    };
    const terminar = estado => {
        if (estado.frame !== null) browser.cancelAnimationFrame?.(estado.frame);
        estado.frame = null;
        estado.terminado = true;
        mostrar(estado, 1);
    };
    const animar = estado => {
        if (estado.iniciado || estado.terminado) return;
        estado.iniciado = true;
        let inicio;
        const paso = tiempo => {
            if (estado.terminado) return;
            inicio ??= tiempo;
            const progreso = Math.min(1, Math.max(0, (tiempo - inicio) / 1000));
            mostrar(estado, 1 - (1 - progreso) ** 3);
            if (progreso < 1) estado.frame = browser.requestAnimationFrame(paso);
            else terminar(estado);
        };
        estado.frame = browser.requestAnimationFrame(paso);
    };

    try {
        observador = new browser.IntersectionObserver(entradas => {
            entradas.forEach(entrada => {
                if (!entrada.isIntersecting || (entrada.intersectionRatio ?? 1) < 0.2) return;
                const estado = estados.get(entrada.target);
                if (estado) animar(estado);
                observador.unobserve(entrada.target);
            });
        }, { threshold: 0.2 });
        grupos.forEach(grupo => {
            const cifras = [...grupo.querySelectorAll('[data-slep-count]')].flatMap(elemento => {
                const valor = Number(elemento.dataset.slepCount);
                if (!Number.isFinite(valor) || valor < 0) return [];
                const decimales = Math.max(0, Math.min(2, Number(elemento.dataset.slepDecimals) || 0));
                return [{ elemento, valor, texto: elemento.textContent, sufijo: elemento.dataset.slepSuffix || '',
                    formato: new Intl.NumberFormat('es-CL', { minimumFractionDigits: decimales, maximumFractionDigits: decimales }) }];
            });
            const barras = [...grupo.querySelectorAll('[data-slep-fill]')].flatMap(elemento => {
                const valor = Number(elemento.dataset.slepFill);
                return Number.isFinite(valor) ? [{ elemento, valor: Math.min(100, Math.max(0, valor)) }] : [];
            });
            const estado = { cifras, barras, frame: null, iniciado: false, terminado: false };
            estados.set(grupo, estado);
            mostrar(estado, 0);
            observador.observe(grupo);
        });
    } catch {
        // Si el navegador no puede observar, conserva el resultado completo.
        estados.forEach(terminar);
        observador?.disconnect();
    }

    const reducir = evento => {
        if (!evento.matches) return;
        estados.forEach(terminar);
        observador?.disconnect();
    };
    movimiento?.addEventListener?.('change', reducir);
    const imprimir = () => estados.forEach(terminar);
    browser.addEventListener?.('beforeprint', imprimir);
    return () => {
        estados.forEach(terminar);
        observador?.disconnect();
        movimiento?.removeEventListener?.('change', reducir);
        browser.removeEventListener?.('beforeprint', imprimir);
    };
}

if (typeof document !== 'undefined' && typeof window !== 'undefined') iniciarProgresoAnimado();
