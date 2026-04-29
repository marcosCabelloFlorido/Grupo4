/* ═══════════════════════════════════════════════════════════
   cliente.js — JavaScript del Dashboard de VALTASY
   Requiere: USUARIO_ACTUAL definido inline en el PHP
   ═══════════════════════════════════════════════════════════ */

let ligaPublicaSeleccionada = null;

/* ── Notificación flotante ──────────────────────────────── */
function mostrarNotificacion(texto, tipo = 'exito', duracion = 3000) {
    const notif = document.getElementById('notificacion');
    notif.textContent = texto;
    notif.className   = tipo;
    notif.style.display = 'block';
    setTimeout(() => { notif.style.display = 'none'; }, duracion);
}

/* ── Escapa HTML para evitar XSS ───────────────────────── */
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/* ── Renderiza tarjetas de ligas ────────────────────────── */
function renderizarLigas(ligas) {
    const contenedor = document.getElementById('contenedorLigas');
    contenedor.innerHTML = '';

    if (!ligas || ligas.length === 0) {
        contenedor.innerHTML = `
            <div class="estado-mensaje">
                SIN OPERACIONES ACTIVAS
                <span>CREA UNA LIGA O ÚNETE A UNA EXISTENTE</span>
            </div>`;
        return;
    }

    ligas.forEach(liga => {
        const tipoBadge  = liga.tipo === 'Privada' ? 'privada' : 'publica';
        const tipoLabel  = liga.tipo === 'Privada' ? 'CLASIFICADA' : 'NO CLASIFICADA';
        const presupuesto = new Intl.NumberFormat('es-ES').format(liga.presupuesto_disponible);

        const card = document.createElement('div');
        card.className       = 'liga-card';
        card.dataset.idLiga  = liga.id_liga;

        card.innerHTML = `
            <a href="ver_liga.php?id_liga=${encodeURIComponent(liga.id_liga)}" class="liga-card-link">
                <span class="badge-tipo ${tipoBadge}">${tipoLabel}</span>
                <h3>${escapeHtml(liga.nombre_liga)}</h3>
                <div class="dato"><span>TORNEO</span><span style="color:#c084fc;">${escapeHtml(liga.torneo || 'VCT EMEA - Fase Regular')}</span></div>
                <div class="dato"><span>ESCUADRÓN</span><span>${escapeHtml(liga.nombre_equipo)}</span></div>
                <div class="dato"><span>PUNTOS</span><span>${liga.puntos_equipo}</span></div>
                <div class="dato"><span>PRESUPUESTO</span><span>${presupuesto} €</span></div>
                <div class="dato"><span>PARTICIPANTES</span><span>${liga.total_participantes}</span></div>
            </a>
            <div class="liga-card-footer">
                <a href="mercado.php?id_liga=${encodeURIComponent(liga.id_liga)}" class="btn-mercado">
                    🛒 MERCADO
                </a>
                <button class="btn-eliminar" data-id="${liga.id_liga}" data-nombre="${escapeHtml(liga.nombre_liga)}">
                    ✕ SALIR
                </button>
            </div>`;

        contenedor.appendChild(card);
    });

    contenedor.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', manejarEliminar);
    });
}

/* ── Carga las ligas del usuario ────────────────────────── */
async function cargarLigas() {
    const contenedor = document.getElementById('contenedorLigas');
    try {
        const res  = await fetch(`api_ligas.php?usuario=${encodeURIComponent(USUARIO_ACTUAL)}`);
        const json = await res.json();

        if (json.status === 'success') {
            renderizarLigas(json.data);
        } else {
            contenedor.innerHTML = `<div class="estado-mensaje">ERROR AL CARGAR DATOS<span>${escapeHtml(json.message || '')}</span></div>`;
        }
    } catch (e) {
        contenedor.innerHTML = `<div class="estado-mensaje">ERROR DE CONEXIÓN<span>COMPRUEBA EL SERVIDOR</span></div>`;
    }
}

/* ── Eliminar / salir de una liga ───────────────────────── */
async function manejarEliminar(e) {
    const btn    = e.currentTarget;
    const idLiga = btn.dataset.id;
    const nombre = btn.dataset.nombre;

    if (!confirm(`⚠ BAJA DE OPERACIÓN\n\n¿Confirmas la eliminación de la liga "${nombre}"?\n\nEsta acción es irreversible y eliminará todos los datos asociados.`)) return;

    btn.disabled    = true;
    btn.textContent = 'PROCESANDO...';

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
            card.style.opacity    = '0';
            card.style.transform  = 'scale(0.95)';
            setTimeout(() => {
                card.remove();
                if (!document.querySelectorAll('.liga-card').length) renderizarLigas([]);
            }, 300);
            mostrarNotificacion('OPERACIÓN DADA DE BAJA', 'exito');
        } else {
            mostrarNotificacion('ERROR: ' + (json.message || 'Operación fallida'), 'error', 4000);
            btn.disabled    = false;
            btn.textContent = '✕ SALIR';
        }
    } catch (err) {
        mostrarNotificacion('ERROR DE CONEXIÓN CON EL SERVIDOR', 'error', 4000);
        btn.disabled    = false;
        btn.textContent = '✕ SALIR';
    }
}

/* ════════════════════════════════════════════════════════
   MODAL: UNIRSE A LIGA
   ════════════════════════════════════════════════════════ */

const modal = document.getElementById('modalUnirse');

document.getElementById('btnAbrirModalUnirse').addEventListener('click', () => {
    modal.classList.remove('hidden');
    document.getElementById('inputCodigo').focus();
    cargarLigasPublicasModal();
});

document.getElementById('btnCerrarModal').addEventListener('click', cerrarModal);
modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModal(); });

function cerrarModal() {
    modal.classList.add('hidden');
    document.getElementById('inputCodigo').value         = '';
    document.getElementById('nombreEquipoPrivada').value = '';
    document.getElementById('nombreEquipoPublica').value = '';
    document.getElementById('errorPrivada').classList.remove('visible');
    document.getElementById('errorPublica').classList.remove('visible');
    ligaPublicaSeleccionada = null;
    document.getElementById('btnUnirsePublica').disabled = true;
}

/* ── Tabs del modal ─────────────────────────────────────── */
document.querySelectorAll('.modal-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('activo'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
        tab.classList.add('activo');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('activo');
    });
});

/* ── Ligas públicas disponibles ─────────────────────────── */
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
                item.className  = 'liga-publica-item';
                item.dataset.id = liga.id_liga;
                item.innerHTML  = `
                    <div>
                        <div class="liga-publica-nombre">${escapeHtml(liga.nombre_liga)}</div>
                        <div class="liga-publica-meta">${liga.participantes_actuales} / ${liga.max_participantes} agentes</div>
                    </div>
                    <div class="liga-publica-plazas">${plazasLibres} plaza${plazasLibres !== 1 ? 's' : ''} libre${plazasLibres !== 1 ? 's' : ''}</div>`;
                item.addEventListener('click', () => {
                    document.querySelectorAll('.liga-publica-item').forEach(i => i.classList.remove('seleccionada'));
                    item.classList.add('seleccionada');
                    ligaPublicaSeleccionada = liga.id_liga;
                    document.getElementById('btnUnirsePublica').disabled = false;
                });
                contenedor.appendChild(item);
            });
        } else {
            contenedor.innerHTML = '<div class="sin-ligas">No hay operaciones públicas disponibles en este momento.</div>';
        }
    } catch (_) {
        contenedor.innerHTML = '<div class="sin-ligas">Error al cargar las operaciones.</div>';
    }
}

/* ── Unirse por código privado ──────────────────────────── */
document.getElementById('btnUnirsePrivada').addEventListener('click', async () => {
    const codigo       = document.getElementById('inputCodigo').value.trim().toUpperCase();
    const nombreEquipo = document.getElementById('nombreEquipoPrivada').value.trim();
    const errorDiv     = document.getElementById('errorPrivada');
    const btn          = document.getElementById('btnUnirsePrivada');

    errorDiv.classList.remove('visible');

    if (!codigo)       { errorDiv.textContent = 'Introduce el código de acceso.';       errorDiv.classList.add('visible'); return; }
    if (!nombreEquipo) { errorDiv.textContent = 'Introduce el nombre de tu escuadrón.'; errorDiv.classList.add('visible'); return; }

    btn.disabled    = true;
    btn.textContent = 'VERIFICANDO...';

    try {
        const res  = await fetch('api_ligas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'unirse', nombre_usuario: USUARIO_ACTUAL, codigo_acceso: codigo, nombre_equipo: nombreEquipo })
        });
        const json = await res.json();

        if (json.status === 'success') {
            cerrarModal();
            mostrarNotificacion('ACCESO CONCEDIDO — BIENVENIDO A LA LIGA', 'exito', 3500);
            await cargarLigas();
        } else {
            errorDiv.textContent = json.message || 'Error desconocido.';
            errorDiv.classList.add('visible');
        }
    } catch (_) {
        errorDiv.textContent = 'Error de conexión con el servidor.';
        errorDiv.classList.add('visible');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'CONFIRMAR ACCESO';
    }
});

/* ── Unirse a liga pública ──────────────────────────────── */
document.getElementById('btnUnirsePublica').addEventListener('click', async () => {
    const nombreEquipo = document.getElementById('nombreEquipoPublica').value.trim();
    const errorDiv     = document.getElementById('errorPublica');
    const btn          = document.getElementById('btnUnirsePublica');

    errorDiv.classList.remove('visible');

    if (!ligaPublicaSeleccionada) { errorDiv.textContent = 'Selecciona una liga de la lista.';       errorDiv.classList.add('visible'); return; }
    if (!nombreEquipo)            { errorDiv.textContent = 'Introduce el nombre de tu escuadrón.'; errorDiv.classList.add('visible'); return; }

    btn.disabled    = true;
    btn.textContent = 'PROCESANDO...';

    try {
        const res  = await fetch('api_ligas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'unirse', nombre_usuario: USUARIO_ACTUAL, id_liga: ligaPublicaSeleccionada, nombre_equipo: nombreEquipo })
        });
        const json = await res.json();

        if (json.status === 'success') {
            cerrarModal();
            mostrarNotificacion('ACCESO CONCEDIDO — BIENVENIDO A LA LIGA', 'exito', 3500);
            await cargarLigas();
        } else {
            errorDiv.textContent = json.message || 'Error desconocido.';
            errorDiv.classList.add('visible');
            btn.disabled    = false;
            btn.textContent = 'UNIRSE A LA OPERACIÓN';
        }
    } catch (_) {
        errorDiv.textContent = 'Error de conexión con el servidor.';
        errorDiv.classList.add('visible');
        btn.disabled    = false;
        btn.textContent = 'UNIRSE A LA OPERACIÓN';
    }
});

/* ── Badge de Premium en el dashboard ──────────────────── */
async function verificarPremiumDashboard() {
    try {
        const res  = await fetch(`api_premium.php?usuario=${encodeURIComponent(USUARIO_ACTUAL)}`);
        const json = await res.json();
        const badge = document.getElementById('premiumHeaderBadge');
        if (!badge) return;
        if (json.status === 'success' && json.es_premium) {
            badge.innerHTML = '<span class="badge-premium">⚡ PREMIUM</span>';
        } else {
            badge.innerHTML = '<a href="crear_liga.php" class="badge-activar-premium">⚡ Activar Premium</a>';
        }
    } catch (_) {}
}

/* ── Inicio ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    cargarLigas();
    verificarPremiumDashboard();
});
