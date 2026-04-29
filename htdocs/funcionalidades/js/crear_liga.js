/* ═══════════════════════════════════════════════════════════
   crear_liga.js — JavaScript de la página Crear Liga
   Requiere: USUARIO definido inline en el PHP
   ═══════════════════════════════════════════════════════════ */

let esPremium = false;
let planMeses  = 1;
const precios    = { 1: '4,99€', 3: '12,99€', 6: '22,99€', 12: '39,99€' };
const preciosNum = { 1: 4.99,   3: 12.99,    6: 22.99,    12: 39.99 };

/* ── Verificar estado premium al cargar ─────────────────── */
async function verificarPremium() {
    try {
        const res  = await fetch(`api_premium.php?usuario=${encodeURIComponent(USUARIO)}`);
        const json = await res.json();
        if (json.status === 'success') {
            esPremium = json.es_premium;
            actualizarUI();
        }
    } catch (_) { /* si falla, asumimos no premium */ }
}

function actualizarUI() {
    const badge   = document.getElementById('badgePremiumHeader');
    const notice  = document.getElementById('lockNotice');
    const optPriv = document.getElementById('optionPrivada');

    if (esPremium) {
        badge.innerHTML      = '<span class="badge-premium">⚡ PREMIUM</span>';
        notice.style.display = 'none';
        optPriv.disabled     = false;
        optPriv.textContent  = '🔒 Clasificada (Privada)';
    } else {
        badge.innerHTML = '<span class="badge-no-premium" id="badgeAbrirPremium">⚡ Activar Premium</span>';
        document.getElementById('badgeAbrirPremium').addEventListener('click', abrirModalPremium);
        optPriv.disabled = false; // permitimos seleccionarla para mostrar el aviso
    }
}

/* ── Detectar cambio en el tipo de liga ─────────────────── */
document.getElementById('tipoLiga').addEventListener('change', function () {
    const notice = document.getElementById('lockNotice');
    notice.style.display = (this.value === 'Privada' && !esPremium) ? 'block' : 'none';
});

document.getElementById('linkAbrirPremium').addEventListener('click', abrirModalPremium);

/* ── Modal Premium ──────────────────────────────────────── */
function abrirModalPremium() {
    document.getElementById('modalPremium').classList.remove('hidden');
}

document.getElementById('btnCancelarPremium').addEventListener('click', () => {
    document.getElementById('modalPremium').classList.add('hidden');
    document.getElementById('msgPremium').style.display = 'none';
});

/* ── Selector de plan ───────────────────────────────────── */
document.getElementById('planSelector').querySelectorAll('.plan-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.plan-btn').forEach(b => b.classList.remove('activo'));
        btn.classList.add('activo');
        planMeses = parseInt(btn.dataset.meses);
        document.getElementById('btnComprarPremium').textContent = `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
    });
});

/* ── Comprar premium ────────────────────────────────────── */
document.getElementById('btnComprarPremium').addEventListener('click', async () => {
    const btn    = document.getElementById('btnComprarPremium');
    const msg    = document.getElementById('msgPremium');
    const metodo = document.getElementById('metodoPago').value;

    btn.disabled      = true;
    btn.textContent   = 'PROCESANDO PAGO...';
    msg.style.display = 'none';

    try {
        const res  = await fetch('api_premium.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'comprar', nombre_usuario: USUARIO, metodo_pago: metodo, meses: planMeses })
        });
        const json = await res.json();

        if (json.status === 'success') {
            esPremium = true;
            actualizarUI();

            msg.style.display    = 'block';
            msg.style.color      = '#1DF2DD';
            msg.style.border     = '1px solid #1DF2DD';
            msg.style.background = 'rgba(29,242,221,0.08)';
            msg.textContent      = `✓ ¡Premium activo hasta ${new Date(json.premium_hasta).toLocaleDateString('es-ES')}!`;

            setTimeout(() => {
                document.getElementById('modalPremium').classList.add('hidden');
                msg.style.display = 'none';
                document.getElementById('lockNotice').style.display = 'none';
            }, 2200);

        } else {
            msg.style.display    = 'block';
            msg.style.color      = '#ff6b80';
            msg.style.border     = '1px solid #A63247';
            msg.style.background = 'rgba(140,8,19,0.15)';
            msg.textContent      = 'Error: ' + (json.message || 'Inténtalo de nuevo.');
            btn.disabled         = false;
            btn.textContent      = `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
        }
    } catch (_) {
        msg.style.display    = 'block';
        msg.style.color      = '#ff6b80';
        msg.style.border     = '1px solid #A63247';
        msg.style.background = 'rgba(140,8,19,0.15)';
        msg.textContent      = 'Error de conexión con el servidor.';
        btn.disabled         = false;
        btn.textContent      = `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
    }
});

/* ── Formulario de creación de liga ─────────────────────── */
document.getElementById('formCrearLiga').addEventListener('submit', async (e) => {
    e.preventDefault();
    const divMensaje = document.getElementById('mensaje');
    const tipo       = document.getElementById('tipoLiga').value;

    if (tipo === 'Privada' && !esPremium) {
        divMensaje.style.display = 'block';
        divMensaje.className     = 'error';
        divMensaje.textContent   = '⚡ Necesitas Premium para crear ligas privadas.';
        abrirModalPremium();
        return;
    }

    const datos = {
        nombre_usuario: document.getElementById('nombreUsuario').value,
        nombre_liga:    document.getElementById('nombreLiga').value,
        nombre_equipo:  document.getElementById('nombreEquipo').value,
        tipo:           tipo,
        torneo:         document.getElementById('torneoLiga').value
    };

    const btn    = document.getElementById('btnCrear');
    btn.disabled    = true;
    btn.textContent = 'INICIANDO OPERACIÓN...';

    try {
        const res  = await fetch('api_ligas.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(datos)
        });
        const json = await res.json();

        if (res.status === 201 || json.status === 'success') {
            document.getElementById('formCrearLiga').reset();
            document.getElementById('lockNotice').style.display = 'none';

            if (json.codigo_acceso) {
                document.getElementById('codigoTexto').textContent = json.codigo_acceso;
                document.getElementById('modalCodigo').classList.remove('hidden');
            } else {
                divMensaje.style.display = 'block';
                divMensaje.className     = 'exito';
                divMensaje.textContent   = '¡Operación autorizada! Liga pública creada con éxito.';
                setTimeout(() => { window.location.href = 'cliente.php'; }, 1500);
            }
        } else {
            divMensaje.style.display = 'block';
            divMensaje.className     = 'error';
            divMensaje.textContent   = 'Error: ' + json.message;
        }
    } catch (_) {
        divMensaje.style.display = 'block';
        divMensaje.className     = 'error';
        divMensaje.textContent   = 'Error de conexión con los servidores.';
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Iniciar Operación';
    }
});

/* ── Modal código privado: copiar ───────────────────────── */
document.getElementById('codigoTexto').addEventListener('click', async () => {
    const codigo = document.getElementById('codigoTexto').textContent;
    try {
        await navigator.clipboard.writeText(codigo);
        const msg = document.getElementById('copiadoMsg');
        msg.textContent = '✓ Código copiado al portapapeles';
        setTimeout(() => { msg.textContent = ''; }, 2500);
    } catch (_) {}
});

function irAlDashboard() { window.location.href = 'cliente.php'; }

/* ── Inicio ─────────────────────────────────────────────── */
verificarPremium();
