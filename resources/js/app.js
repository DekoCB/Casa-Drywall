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

/**
 * Confirmación con diseño propio (reemplaza window.confirm). Reutiliza las
 * clases .modal-overlay/.modal-card ya usadas por el resto del panel.
 */
function construirModalConfirmar() {
    if (document.getElementById('rtModalConfirmar')) return;
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = 'rtModalConfirmar';
    overlay.innerHTML = `
        <div class="modal-card modal-card-sm">
            <div class="modal-header">
                <h3>Confirmar acción</h3>
            </div>
            <div class="modal-body">
                <p class="rt-confirmar-texto" id="rtConfirmarTexto"></p>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                    <button type="button" class="btn btn-secondary" id="rtConfirmarCancelar">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="rtConfirmarAceptar">Sí, continuar</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(overlay);
}

function confirmar(mensaje) {
    construirModalConfirmar();
    return new Promise((resolve) => {
        const overlay = document.getElementById('rtModalConfirmar');
        const texto = document.getElementById('rtConfirmarTexto');
        const btnAceptar = document.getElementById('rtConfirmarAceptar');
        const btnCancelar = document.getElementById('rtConfirmarCancelar');

        texto.textContent = mensaje;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        const cerrar = (resultado) => {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
            btnAceptar.removeEventListener('click', onAceptar);
            btnCancelar.removeEventListener('click', onCancelar);
            overlay.removeEventListener('click', onFondo);
            document.removeEventListener('keydown', onTecla);
            resolve(resultado);
        };
        const onAceptar = () => cerrar(true);
        const onCancelar = () => cerrar(false);
        const onFondo = (e) => { if (e.target === overlay) cerrar(false); };
        const onTecla = (e) => { if (e.key === 'Escape') cerrar(false); };

        btnAceptar.addEventListener('click', onAceptar);
        btnCancelar.addEventListener('click', onCancelar);
        overlay.addEventListener('click', onFondo);
        document.addEventListener('keydown', onTecla);
    });
}

// <form data-confirmar="mensaje"> pausa el envío y muestra el modal propio
document.addEventListener('submit', (e) => {
    const mensaje = e.target.dataset.confirmar;
    if (!mensaje || e.target.dataset.confirmado === '1') return;

    e.preventDefault();
    confirmar(mensaje).then((ok) => {
        if (ok) {
            e.target.dataset.confirmado = '1';
            e.target.requestSubmit();
        }
    });
});

/**
 * Selector de fecha propio (reemplaza el calendario nativo de
 * <input type="date">, que no puede tematizarse por completo en todos los
 * navegadores). El input queda "readonly" y este widget escribe su valor.
 */
const RT_MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const RT_DIAS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

let calendarioActivo = null;

function pad2(n) {
    return String(n).padStart(2, '0');
}

function cerrarCalendario() {
    if (!calendarioActivo) return;
    calendarioActivo.elemento.remove();
    document.removeEventListener('mousedown', onClicFueraCalendario, true);
    document.removeEventListener('keydown', onTeclaCalendario, true);
    window.removeEventListener('resize', cerrarCalendario);
    window.removeEventListener('scroll', cerrarCalendario, true);
    calendarioActivo = null;
}

function onClicFueraCalendario(e) {
    if (!calendarioActivo) return;
    if (calendarioActivo.elemento.contains(e.target) || e.target === calendarioActivo.input) return;
    cerrarCalendario();
}

function onTeclaCalendario(e) {
    if (e.key === 'Escape') cerrarCalendario();
}

function renderCalendario(input, vista) {
    const cal = calendarioActivo.elemento;
    const [anioSel, mesSel, diaSel] = input.value ? input.value.split('-').map(Number) : [null, null, null];
    const hoy = new Date();

    const primerDiaMes = new Date(vista.anio, vista.mes, 1);
    const offset = (primerDiaMes.getDay() + 6) % 7; // semana empieza en lunes
    const diasEnMes = new Date(vista.anio, vista.mes + 1, 0).getDate();
    const diasMesAnterior = new Date(vista.anio, vista.mes, 0).getDate();
    const totalCeldas = Math.ceil((offset + diasEnMes) / 7) * 7;

    const min = input.min || null;
    const max = input.max || null;

    let celdas = '';
    for (let i = 0; i < totalCeldas; i++) {
        const numDia = i - offset + 1;
        let clases = 'rt-cal-dia';
        let diaMostrado, mesCelda, anioCelda;

        if (numDia < 1) {
            diaMostrado = diasMesAnterior + numDia;
            mesCelda = vista.mes - 1;
            anioCelda = vista.anio;
            clases += ' fuera-mes';
        } else if (numDia > diasEnMes) {
            diaMostrado = numDia - diasEnMes;
            mesCelda = vista.mes + 1;
            anioCelda = vista.anio;
            clases += ' fuera-mes';
        } else {
            diaMostrado = numDia;
            mesCelda = vista.mes;
            anioCelda = vista.anio;
        }
        let m = mesCelda, a = anioCelda;
        if (m < 0) { m = 11; a -= 1; }
        if (m > 11) { m = 0; a += 1; }

        const fechaStr = `${a}-${pad2(m + 1)}-${pad2(diaMostrado)}`;

        if (a === hoy.getFullYear() && m === hoy.getMonth() && diaMostrado === hoy.getDate()) clases += ' hoy';
        if (anioSel === a && (mesSel - 1) === m && diaSel === diaMostrado) clases += ' seleccionado';

        const deshabilitado = (min && fechaStr < min) || (max && fechaStr > max);
        if (deshabilitado) clases += ' deshabilitado';

        celdas += `<button type="button" class="${clases}" data-fecha="${fechaStr}" ${deshabilitado ? 'disabled' : ''}>${diaMostrado}</button>`;
    }

    cal.innerHTML = `
        <div class="rt-cal-cab">
            <button type="button" class="rt-cal-nav" data-nav="-1" aria-label="Mes anterior">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <span class="rt-cal-mes">${RT_MESES[vista.mes]} ${vista.anio}</span>
            <button type="button" class="rt-cal-nav" data-nav="1" aria-label="Mes siguiente">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
        <div class="rt-cal-dias-sem">${RT_DIAS.map((d) => `<span>${d}</span>`).join('')}</div>
        <div class="rt-cal-grilla">${celdas}</div>
        <div class="rt-cal-pie">
            <button type="button" class="rt-cal-btn-txt" data-accion="hoy">Hoy</button>
            <button type="button" class="rt-cal-btn-txt" data-accion="limpiar">Limpiar</button>
        </div>`;

    const fijarValor = (valor) => {
        input.value = valor;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        cerrarCalendario();
    };

    cal.querySelector('[data-nav="-1"]').addEventListener('click', () => {
        vista.mes -= 1;
        if (vista.mes < 0) { vista.mes = 11; vista.anio -= 1; }
        renderCalendario(input, vista);
    });
    cal.querySelector('[data-nav="1"]').addEventListener('click', () => {
        vista.mes += 1;
        if (vista.mes > 11) { vista.mes = 0; vista.anio += 1; }
        renderCalendario(input, vista);
    });
    cal.querySelectorAll('.rt-cal-dia:not(.deshabilitado)').forEach((celda) => {
        celda.addEventListener('click', () => fijarValor(celda.dataset.fecha));
    });
    cal.querySelector('[data-accion="hoy"]').addEventListener('click', () => {
        fijarValor(`${hoy.getFullYear()}-${pad2(hoy.getMonth() + 1)}-${pad2(hoy.getDate())}`);
    });
    cal.querySelector('[data-accion="limpiar"]').addEventListener('click', () => fijarValor(''));
}

function abrirCalendario(input) {
    if (calendarioActivo && calendarioActivo.input === input) return;
    cerrarCalendario();

    const elemento = document.createElement('div');
    elemento.className = 'rt-calendario activo';
    document.body.appendChild(elemento);

    const rect = input.getBoundingClientRect();
    const anchoCal = 266;
    let left = rect.left + window.scrollX;
    if (left + anchoCal > window.scrollX + document.documentElement.clientWidth) {
        left = rect.right + window.scrollX - anchoCal;
    }
    elemento.style.top = `${rect.bottom + window.scrollY + 6}px`;
    elemento.style.left = `${left}px`;

    const [anioIni, mesIni] = input.value ? input.value.split('-').map(Number) : [new Date().getFullYear(), new Date().getMonth() + 1];
    const vista = { anio: anioIni, mes: mesIni - 1 };

    calendarioActivo = { input, elemento };
    renderCalendario(input, vista);

    document.addEventListener('mousedown', onClicFueraCalendario, true);
    document.addEventListener('keydown', onTeclaCalendario, true);
    window.addEventListener('resize', cerrarCalendario);
    window.addEventListener('scroll', cerrarCalendario, true);
}

document.querySelectorAll('input[type="date"]').forEach((input) => {
    input.setAttribute('readonly', 'readonly');
    input.addEventListener('click', () => abrirCalendario(input));
    input.addEventListener('focus', () => abrirCalendario(input));
});

window.confirmar = confirmar;
window.abrirModal = abrirModal;
window.cerrarModal = cerrarModal;
