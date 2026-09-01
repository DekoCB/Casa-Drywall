import './bootstrap';

/**
 * Modales del panel: apertura por [data-modal], cierre por [data-cerrar],
 * clic en el fondo o tecla Escape.
 */
function abrirModal(id) {
    document.getElementById(id)?.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarModal(id) {
    document.getElementById(id)?.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('click', (e) => {
    const abrir = e.target.closest('[data-modal]');
    if (abrir) {
        e.preventDefault();
        abrirModal(abrir.dataset.modal);

        // Precarga los campos del formulario desde los data-campo-* del disparador.
        const modal = document.getElementById(abrir.dataset.modal);
        if (modal) {
            for (const [clave, valor] of Object.entries(abrir.dataset)) {
                if (!clave.startsWith('campo')) continue;
                const nombre = clave.slice(5).replace(/^./, (c) => c.toLowerCase());
                const input = modal.querySelector(`[name="${nombre}"]`);
                if (input) input.value = valor;
            }
        }
        return;
    }

    const cerrar = e.target.closest('[data-cerrar]');
    if (cerrar) {
        e.preventDefault();
        cerrarModal(cerrar.dataset.cerrar);
        return;
    }

    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal-overlay.active').forEach((m) => m.classList.remove('active'));
    document.body.style.overflow = '';
});

// Confirmación para acciones destructivas: <form data-confirmar="mensaje">
document.addEventListener('submit', (e) => {
    const mensaje = e.target.dataset.confirmar;
    if (mensaje && !window.confirm(mensaje)) {
        e.preventDefault();
    }
});

window.abrirModal = abrirModal;
window.cerrarModal = cerrarModal;
