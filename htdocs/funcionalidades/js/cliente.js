/* ═══════════════════════════════════════════════════════════════════════════
   cliente.js — JavaScript del Dashboard de VALTASY
   Requiere: USUARIO_ACTUAL definido inline en el PHP
   ═══════════════════════════════════════════════════════════════════════════ */

let ligaPublicaSeleccionada = null;
let esPremium = false;
let planMeses  = 1;
const precios    = { 1: '4,99€', 3: '12,99€', 6: '22,99€', 12: '39,99€' };

function mostrarNotificacion(texto, tipo = 'exito', duracion = 3000) {
    const notif = document.getElementById('notificacion');
    notif.textContent = texto;
    notif.className   = tipo;
    notif.style.display = 'block';
    setTimeout(() => { notif.style.display = 'none'; }, duracion);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* ── Renderiza tarjetas de ligas ────────────── */
function renderizarLigas(ligas) {
    const contenedor = document.getElementById('contenedorLigas');
    contenedor.innerHTML = '';

    if (!ligas || ligas.length === 0) {
        contenedor.innerHTML = `<div class="estado-mensaje">SIN OPERACIONES ACTIVAS<span>CREA UNA LIGA O ÚNETE A UNA EXISTENTE</span></div>`;
        return;
    }

    ligas.forEach(liga => {
        const tipoBadge  = liga.tipo === 'Privada' ? 'privada' : 'publica';
        const tipoLabel  = liga.tipo === 'Privada' ? 'CLASIFICADA' : 'NO CLASIFICADA';
        const presupuesto = new Intl.NumberFormat('es-ES').format(liga.presupuesto_disponible);

        const card = document.createElement('div');
        card.className = 'liga-card';
        card.dataset.idLiga = liga.id_liga;

        card.innerHTML = `
            <a href="ver_liga.php?id_liga=${encodeURIComponent(liga.id_liga)}" class="liga-card-link">
                <span class="badge-tipo ${tipoBadge}">${tipoLabel}</span>
                <h3>${escapeHtml(liga.nombre_liga)}</h3>
                <div class="dato"><span>TORNEO</span><span style="color:#c084fc;">${escapeHtml(liga.torneo || 'VCT EMEA')}</span></div>
                <div class="dato"><span>ESCUADRÓN</span><span>${escapeHtml(liga.nombre_equipo)}</span></div>
                <div class="dato"><span>PRESUPUESTO</span><span style="color:#4ade80;">${presupuesto} €</span></div>
            </a>
            <div class="liga-card-footer btn-grid-container">
                <a href="mercado.php?id_liga=${encodeURIComponent(liga.id_liga)}" class="btn-card-action btn-purple">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M1 1h1.5l1 5h5.5l1-3.5H3.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><circle cx="5.5" cy="10" r="1" fill="currentColor"/><circle cx="9" cy="10" r="1" fill="currentColor"/></svg>
                    Mercado
                </a>
                <button class="btn-tienda-card btn-card-action btn-cyan" data-id="${liga.id_liga}" data-nombre="${escapeHtml(liga.nombre_liga)}">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><rect x="1" y="3.5" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M4 3.5V3a2 2 0 0 1 4 0v.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><path d="M1 6.5h10" stroke="currentColor" stroke-width="1.3"/></svg>
                    Fondos
                </button>
                <button class="btn-recompensa-card btn-card-action btn-yellow" data-id="${liga.id_liga}" data-nombre="${escapeHtml(liga.nombre_liga)}">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M6 1l1.2 2.4L10 3.9l-2 1.95.47 2.75L6 7.4l-2.47 1.2L4 5.85 2 3.9l2.8-.5L6 1z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M4 10.5h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><path d="M6 8v2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                    Diaria
                </button>
                <button class="btn-eliminar btn-card-action btn-red" data-id="${liga.id_liga}" data-nombre="${escapeHtml(liga.nombre_liga)}">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 3h8M5 3V2h2v1M4.5 3v6M7.5 3v6M3 3l.5 7h5L9 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Salir
                </button>
            </div>`;

        contenedor.appendChild(card);
    });

    contenedor.querySelectorAll('.btn-eliminar').forEach(btn => btn.addEventListener('click', manejarEliminar));
    contenedor.querySelectorAll('.btn-recompensa-card').forEach(btn => btn.addEventListener('click', abrirModalRecompensaLiga));
    contenedor.querySelectorAll('.btn-tienda-card').forEach(btn => btn.addEventListener('click', abrirModalTienda));
}

async function cargarLigas() {
    const contenedor = document.getElementById('contenedorLigas');
    try {
        const res  = await fetch(`api_ligas.php?usuario=${encodeURIComponent(USUARIO_ACTUAL)}`);
        const json = await res.json();
        if (json.status === 'success') renderizarLigas(json.data);
        else contenedor.innerHTML = `<div class="estado-mensaje">ERROR AL CARGAR DATOS<span>${escapeHtml(json.message || '')}</span></div>`;
    } catch (e) {
        contenedor.innerHTML = `<div class="estado-mensaje">ERROR DE CONEXIÓN<span>COMPRUEBA EL SERVIDOR</span></div>`;
    }
}

async function manejarEliminar(e) {
    const btn = e.currentTarget;
    const idLiga = btn.dataset.id;
    const nombre = btn.dataset.nombre;

    if (!confirm(`⚠ BAJA DE OPERACIÓN\n\n¿Confirmas la eliminación de la liga "${nombre}"?\n\nEsta acción es irreversible y eliminará todos los datos asociados.`)) return;

    btn.disabled = true;
    btn.textContent = '...';

    try {
        const res  = await fetch('api_ligas.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_liga: idLiga, nombre_usuario: USUARIO_ACTUAL })
        });
        const json = await res.json();

        if (json.status === 'success') {
            const card = btn.closest('.liga-card');
            card.style.transition = 'opacity 0.3s, transform 0.3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                card.remove();
                if (!document.querySelectorAll('.liga-card').length) renderizarLigas([]);
            }, 300);
            mostrarNotificacion('OPERACIÓN DADA DE BAJA', 'exito');
        } else {
            mostrarNotificacion('ERROR: ' + (json.message || 'Operación fallida'), 'error', 4000);
            btn.disabled = false;
            btn.innerHTML = '✕ SALIR';
        }
    } catch (err) {
        mostrarNotificacion('ERROR DE CONEXIÓN', 'error');
        btn.disabled = false;
        btn.innerHTML = '✕ SALIR';
    }
}

/* ════════════════════════════════════════════════════════
   MODAL UNIRSE A LIGA 
   ════════════════════════════════════════════════════════ */
const modalUnirse = document.getElementById('modalUnirse');
document.getElementById('btnAbrirModalUnirse').addEventListener('click', () => {
    modalUnirse.classList.remove('hidden');
    document.getElementById('inputCodigo').focus();
    cargarLigasPublicasModal();
});
document.getElementById('btnCerrarModal').addEventListener('click', cerrarModalUnirse);
modalUnirse.addEventListener('click', (e) => { if (e.target === modalUnirse) cerrarModalUnirse(); });

function cerrarModalUnirse() {
    modalUnirse.classList.add('hidden');
    document.getElementById('inputCodigo').value = '';
    document.getElementById('nombreEquipoPrivada').value = '';
    document.getElementById('nombreEquipoPublica').value = '';
    document.getElementById('errorPrivada').classList.remove('visible');
    document.getElementById('errorPublica').classList.remove('visible');
    ligaPublicaSeleccionada = null;
    document.getElementById('btnUnirsePublica').disabled = true;
}

document.querySelectorAll('.modal-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('activo'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
        tab.classList.add('activo');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('activo');
    });
});

async function cargarLigasPublicasModal() {
    const contenedor = document.getElementById('listaLigasPublicas');
    contenedor.innerHTML = '<div class="cargando-ligas">Cargando operaciones disponibles...</div>';
    ligaPublicaSeleccionada = null;
    document.getElementById('btnUnirsePublica').disabled = true;
    try {
        const res  = await fetch(`api_ligas.php?publicas=1&usuario=${encodeURIComponent(USUARIO_ACTUAL)}`);
        const json = await res.json();
        if (json.status === 'success' && json.data.length > 0) {
            contenedor.innerHTML = '';
            json.data.forEach(liga => {
                const plazasLibres = liga.max_participantes - liga.participantes_actuales;
                const item = document.createElement('div');
                item.className = 'liga-publica-item';
                item.dataset.id = liga.id_liga;
                item.innerHTML = `<div><div class="liga-publica-nombre">${escapeHtml(liga.nombre_liga)}</div><div class="liga-publica-meta">${liga.participantes_actuales} / ${liga.max_participantes} agentes</div></div><div class="liga-publica-plazas">${plazasLibres} plaza${plazasLibres !== 1 ? 's' : ''} libre${plazasLibres !== 1 ? 's' : ''}</div>`;
                item.addEventListener('click', () => {
                    document.querySelectorAll('.liga-publica-item').forEach(i => i.classList.remove('seleccionada'));
                    item.classList.add('seleccionada');
                    ligaPublicaSeleccionada = liga.id_liga;
                    document.getElementById('btnUnirsePublica').disabled = false;
                });
                contenedor.appendChild(item);
            });
        } else { contenedor.innerHTML = '<div class="sin-ligas">No hay operaciones públicas disponibles.</div>'; }
    } catch (_) { contenedor.innerHTML = '<div class="sin-ligas">Error al cargar las operaciones.</div>'; }
}

document.getElementById('btnUnirsePrivada').addEventListener('click', async () => {
    const codigo = document.getElementById('inputCodigo').value.trim().toUpperCase();
    const nombreEquipo = document.getElementById('nombreEquipoPrivada').value.trim();
    const errorDiv = document.getElementById('errorPrivada');
    const btn = document.getElementById('btnUnirsePrivada');

    errorDiv.classList.remove('visible');
    if (!codigo) { errorDiv.textContent = 'Introduce código.'; errorDiv.classList.add('visible'); return; }
    if (!nombreEquipo) { errorDiv.textContent = 'Introduce escuadrón.'; errorDiv.classList.add('visible'); return; }

    btn.disabled = true; btn.textContent = 'VERIFICANDO...';
    try {
        const res = await fetch('api_ligas.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'unirse', nombre_usuario: USUARIO_ACTUAL, codigo_acceso: codigo, nombre_equipo: nombreEquipo }) });
        const json = await res.json();
        if (json.status === 'success') { cerrarModalUnirse(); mostrarNotificacion('ACCESO CONCEDIDO', 'exito'); await cargarLigas(); }
        else { errorDiv.textContent = json.message || 'Error desconocido.'; errorDiv.classList.add('visible'); }
    } catch (_) { errorDiv.textContent = 'Error de conexión.'; errorDiv.classList.add('visible'); } 
    finally { btn.disabled = false; btn.textContent = 'CONFIRMAR ACCESO'; }
});

document.getElementById('btnUnirsePublica').addEventListener('click', async () => {
    const nombreEquipo = document.getElementById('nombreEquipoPublica').value.trim();
    const errorDiv = document.getElementById('errorPublica');
    const btn = document.getElementById('btnUnirsePublica');

    errorDiv.classList.remove('visible');
    if (!ligaPublicaSeleccionada) return;
    if (!nombreEquipo) { errorDiv.textContent = 'Introduce escuadrón.'; errorDiv.classList.add('visible'); return; }

    btn.disabled = true; btn.textContent = 'PROCESANDO...';
    try {
        const res = await fetch('api_ligas.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'unirse', nombre_usuario: USUARIO_ACTUAL, id_liga: ligaPublicaSeleccionada, nombre_equipo: nombreEquipo }) });
        const json = await res.json();
        if (json.status === 'success') { cerrarModalUnirse(); mostrarNotificacion('ACCESO CONCEDIDO', 'exito'); await cargarLigas(); }
        else { errorDiv.textContent = json.message || 'Error desconocido.'; errorDiv.classList.add('visible'); btn.disabled = false; btn.textContent = 'UNIRSE A LA OPERACIÓN'; }
    } catch (_) { errorDiv.textContent = 'Error de conexión.'; errorDiv.classList.add('visible'); btn.disabled = false; btn.textContent = 'UNIRSE A LA OPERACIÓN'; }
});

/* ════════════════════════════════════════════════════════
   LÓGICA PREMIUM 
   ════════════════════════════════════════════════════════ */
async function verificarPremiumDashboard() {
    try {
        const res  = await fetch(`api_premium.php?usuario=${encodeURIComponent(USUARIO_ACTUAL)}`);
        const json = await res.json();
        const badge = document.getElementById('header-badge-premium');
        if (!badge) return;
        if (json.status === 'success' && json.es_premium) {
            esPremium = true;
            badge.innerHTML = '<span class="badge-premium">⚡ PREMIUM</span>';
        } else {
            esPremium = false;
            badge.innerHTML = '<span class="badge-activar-premium" id="badgeAbrirPremium">⚡ Activar Premium</span>';
            document.getElementById('badgeAbrirPremium').addEventListener('click', abrirModalPremium);
        }
    } catch (_) {}
}

function abrirModalPremium() { document.getElementById('modalPremium').classList.remove('hidden'); }
document.getElementById('btnCancelarPremium').addEventListener('click', () => { document.getElementById('modalPremium').classList.add('hidden'); document.getElementById('msgPremium').style.display = 'none'; });

document.getElementById('planSelector').querySelectorAll('.plan-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.plan-btn').forEach(b => b.classList.remove('activo')); btn.classList.add('activo');
        planMeses = parseInt(btn.dataset.meses); document.getElementById('btnComprarPremium').textContent = `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
    });
});

document.getElementById('btnComprarPremium').addEventListener('click', async () => {
    const btn = document.getElementById('btnComprarPremium'); const msg = document.getElementById('msgPremium'); const metodo = document.getElementById('metodoPago').value;
    btn.disabled = true; btn.textContent = 'PROCESANDO PAGO...'; msg.style.display = 'none';
    try {
        const res = await fetch('api_premium.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'comprar', nombre_usuario: USUARIO_ACTUAL, metodo_pago: metodo, meses: planMeses }) });
        const json = await res.json();
        if (json.status === 'success') {
            esPremium = true;
            const badge = document.getElementById('header-badge-premium');
            if (badge) badge.innerHTML = '<span class="badge-premium">⚡ PREMIUM</span>';
            msg.style.display = 'block'; msg.style.color = '#1DF2DD'; msg.style.border = '1px solid #1DF2DD'; msg.style.background = 'rgba(29,242,221,0.08)'; msg.textContent = `✓ ¡Premium activo hasta ${new Date(json.premium_hasta).toLocaleDateString('es-ES')}!`;
            setTimeout(() => { document.getElementById('modalPremium').classList.add('hidden'); msg.style.display = 'none'; }, 2200);
        } else {
            msg.style.display = 'block'; msg.style.color = '#ff6b80'; msg.style.border = '1px solid #A63247'; msg.style.background = 'rgba(140,8,19,0.15)'; msg.textContent = 'Error: ' + (json.message || 'Inténtalo de nuevo.');
            btn.disabled = false; btn.textContent = `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
        }
    } catch (_) {
        msg.style.display = 'block'; msg.style.color = '#ff6b80'; msg.style.border = '1px solid #A63247'; msg.style.background = 'rgba(140,8,19,0.15)'; msg.textContent = 'Error de conexión.';
        btn.disabled = false; btn.textContent = `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
    }
});

/* ════════════════════════════════════════════════════════
   LÓGICA RECOMPENSAS DIARIAS 
   ════════════════════════════════════════════════════════ */
let intervaloRecompensa = null;
let ligaActualRecompensa = null; 

const modalRec = document.getElementById('modalRecompensa');
document.getElementById('btnCerrarRecompensa').addEventListener('click', cerrarModalRecompensa);
document.getElementById('btnReclamarRecompensa').addEventListener('click', reclamarRecompensaLiga);
modalRec.addEventListener('click', (e) => { if (e.target === modalRec) cerrarModalRecompensa(); });

async function abrirModalRecompensaLiga(e) {
    const btnSource = e.currentTarget;
    ligaActualRecompensa = btnSource.dataset.id;
    document.getElementById('nombreLigaRecompensa').textContent = btnSource.dataset.nombre.toUpperCase();
    modalRec.classList.remove('hidden');
    
    const btnReclamar = document.getElementById('btnReclamarRecompensa');
    btnReclamar.disabled = true; btnReclamar.textContent = 'CONECTANDO...';

    try {
        const res = await fetch(`api_recompensas.php?accion=estado&id_liga=${ligaActualRecompensa}`);
        const json = await res.json();
        if (json.status === 'success') {
            document.getElementById('recBase').textContent = new Intl.NumberFormat('es-ES').format(json.recompensa_base) + ' €';
            document.getElementById('recDiasRacha').textContent = json.racha;
            document.getElementById('recBonoRacha').textContent = json.bono_racha_pct;
            
            // Inyectamos el mensaje del backend
            document.getElementById('recMensajeProgreso').textContent = json.mensaje_progreso;

            const txtPremium = document.getElementById('recBonoPremium');
            if (json.bono_premium_pct > 0) { txtPremium.textContent = `+${json.bono_premium_pct}%`; txtPremium.style.color = '#c084fc'; } 
            else { txtPremium.textContent = '0% (Falta Premium)'; txtPremium.style.color = '#6b6b7a'; }
            document.getElementById('recTotal').textContent = new Intl.NumberFormat('es-ES').format(json.recompensa_total) + ' €';

            if (json.puede_reclamar) { btnReclamar.disabled = false; btnReclamar.textContent = 'RECLAMAR SUMINISTROS'; if (intervaloRecompensa) clearInterval(intervaloRecompensa); } 
            else { iniciarTemporizador(json.tiempo_restante, btnReclamar); }
        } else { btnReclamar.textContent = json.message || 'ERROR'; }
    } catch (err) { btnReclamar.textContent = 'ERROR DE RED'; }
}

function cerrarModalRecompensa() { modalRec.classList.add('hidden'); ligaActualRecompensa = null; if (intervaloRecompensa) clearInterval(intervaloRecompensa); }

function iniciarTemporizador(segundosRestantes, boton) {
    boton.disabled = true;
    if (intervaloRecompensa) clearInterval(intervaloRecompensa);
    intervaloRecompensa = setInterval(() => {
        if (segundosRestantes <= 0) { clearInterval(intervaloRecompensa); boton.disabled = false; boton.textContent = 'RECLAMAR SUMINISTROS'; return; }
        const h = Math.floor(segundosRestantes / 3600); const m = Math.floor((segundosRestantes % 3600) / 60); const s = segundosRestantes % 60;
        boton.innerHTML = `ESPERA: <span id="temporizadorRecompensa">${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}</span>`;
        segundosRestantes--;
    }, 1000);
}

async function reclamarRecompensaLiga() {
    if (!ligaActualRecompensa) return;
    const btnReclamar = document.getElementById('btnReclamarRecompensa');
    btnReclamar.disabled = true; btnReclamar.textContent = 'TRANSFIRIENDO FONDOS...';

    const formData = new FormData(); formData.append('accion', 'reclamar'); formData.append('id_liga', ligaActualRecompensa);
    try {
        const res = await fetch('api_recompensas.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.status === 'success') { mostrarNotificacion(json.message, 'exito', 4000); cerrarModalRecompensa(); cargarLigas(); } 
        else { mostrarNotificacion(json.message || 'Error', 'error', 3000); btnReclamar.textContent = 'REINTENTAR'; btnReclamar.disabled = false; }
    } catch (e) { mostrarNotificacion('Error de conexión', 'error', 3000); btnReclamar.textContent = 'REINTENTAR'; btnReclamar.disabled = false; }
}

/* ════════════════════════════════════════════════════════
   LÓGICA TIENDA DE FONDOS POR LIGA 
   ════════════════════════════════════════════════════════ */
let ligaActualTienda = null;
let packSeleccionado = 'pack2';
let precioSeleccionado = 4.99;

const modalTienda = document.getElementById('modalTienda');
document.getElementById('btnCerrarTienda').addEventListener('click', cerrarModalTienda);
modalTienda.addEventListener('click', (e) => { if (e.target === modalTienda) cerrarModalTienda(); });

document.querySelectorAll('.tienda-pack').forEach(pack => {
    pack.addEventListener('click', function() {
        document.querySelectorAll('.tienda-pack').forEach(p => p.classList.remove('activo'));
        this.classList.add('activo');
        packSeleccionado = this.dataset.pack;
        precioSeleccionado = this.dataset.precio;
        document.getElementById('btnComprarTienda').textContent = `AUTORIZAR TRANSFERENCIA — ${precioSeleccionado} €`;
    });
});

function abrirModalTienda(e) {
    const btnSource = e.currentTarget;
    ligaActualTienda = btnSource.dataset.id;
    document.getElementById('nombreLigaTienda').textContent = btnSource.dataset.nombre.toUpperCase();
    document.getElementById('msgTiendaError').style.display = 'none';
    modalTienda.classList.remove('hidden');
}

function cerrarModalTienda() {
    modalTienda.classList.add('hidden');
    ligaActualTienda = null;
    document.getElementById('msgTiendaError').style.display = 'none';
    const btn = document.getElementById('btnComprarTienda');
    btn.disabled = false;
    btn.textContent = `AUTORIZAR TRANSFERENCIA — ${precioSeleccionado} €`;
}

document.getElementById('btnComprarTienda').addEventListener('click', async () => {
    if (!ligaActualTienda || !packSeleccionado) return;
    
    const btn = document.getElementById('btnComprarTienda');
    const msgError = document.getElementById('msgTiendaError');
    btn.disabled = true;
    btn.textContent = 'PROCESANDO PAGO SEGURO...';
    msgError.style.display = 'none';

    const formData = new FormData();
    formData.append('id_liga', ligaActualTienda);
    formData.append('pack_id', packSeleccionado);

    try {
        const res = await fetch('api_tienda.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.status === 'success') {
            mostrarNotificacion(json.message, 'exito', 4000);
            cerrarModalTienda();
            cargarLigas();
        } else {
            msgError.textContent = 'Error: ' + (json.message || 'Operación denegada.');
            msgError.style.display = 'block';
            btn.disabled = false;
            btn.textContent = `REINTENTAR — ${precioSeleccionado} €`;
        }
    } catch (e) {
        msgError.textContent = 'Error de conexión con la pasarela de pagos.';
        msgError.style.display = 'block';
        btn.disabled = false;
        btn.textContent = `REINTENTAR — ${precioSeleccionado} €`;
    }
});

/* ── Inicio ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    cargarLigas();
    verificarPremiumDashboard();
});