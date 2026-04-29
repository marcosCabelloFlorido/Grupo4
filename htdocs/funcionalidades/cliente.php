<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../sesion/login.php");
    exit();
}
$nombre_usuario_actual = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VALTASY — Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #0d0d0f;
            color: #e8e8ee;
            font-family: 'Barlow Condensed', sans-serif;
            padding: 40px;
            min-height: 100vh;
            background-image:
                radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%),
                linear-gradient(rgba(29, 242, 221, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(29, 242, 221, 0.02) 1px, transparent 1px);
            background-size: cover, 60px 60px, 60px 60px;
        }

        h1, h2 { font-family: 'Orbitron', monospace; text-transform: uppercase; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #A63247;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header-right { text-align: right; }
        .header-right p { font-size: 1rem; color: #6b6b7a; letter-spacing: 0.05em; }
        .header-right span { color: #1DF2DD; font-weight: 700; }
        .header-right a { color: #6b6b7a; text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
        .header-right a:hover { color: #A63247; }

        .btn-crear {
            display: inline-block;
            padding: 12px 28px;
            background: linear-gradient(135deg, #168C77, #1DF2DD);
            color: #000;
            text-decoration: none;
            font-weight: 700;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
            transition: box-shadow 0.2s;
        }
        .btn-crear:hover { box-shadow: 0 6px 24px rgba(29, 242, 221, 0.4); }

        .btn-unirse {
            display: inline-block;
            padding: 12px 28px;
            background: transparent;
            border: 1px solid #A63247;
            color: #A63247;
            font-weight: 700;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
            margin-left: 12px;
        }
        .btn-unirse:hover { background: rgba(140, 8, 19, 0.3); box-shadow: 0 6px 24px rgba(140, 8, 19, 0.3); }

        .seccion-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .seccion-header h2 {
            font-size: 1.1rem;
            color: #6b6b7a;
            letter-spacing: 0.12em;
        }

        .seccion-header-btns { display: flex; gap: 0; }

        .grid-ligas {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .liga-card {
            background: #141418;
            border: 1px solid rgba(29, 242, 221, 0.12);
            border-top: 2px solid #1DF2DD;
            padding: 0;
            position: relative;
            transition: border-color 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        .liga-card:hover {
            border-color: rgba(29, 242, 221, 0.4);
            box-shadow: 0 4px 24px rgba(29, 242, 221, 0.08);
        }

        .liga-card-link {
            display: block;
            padding: 22px 22px 16px 22px;
            text-decoration: none;
            color: inherit;
            flex: 1;
        }

        .liga-card-link h3 {
            font-family: 'Orbitron', monospace;
            font-size: 0.95rem;
            color: #1DF2DD;
            margin-bottom: 14px;
            letter-spacing: 0.06em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .liga-card-link .dato {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #6b6b7a;
            margin-bottom: 6px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .liga-card-link .dato span:last-child {
            color: #e8e8ee;
            font-weight: 700;
        }

        .liga-card-footer {
            padding: 12px 22px;
            border-top: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .btn-mercado {
            display: inline-block;
            background: none;
            border: 1px solid rgba(29, 242, 221, 0.4);
            color: #1DF2DD;
            padding: 6px 16px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }
        .btn-mercado:hover {
            background: rgba(29, 242, 221, 0.1);
            border-color: #1DF2DD;
            color: #fff;
        }

        .btn-eliminar {
            background: none;
            border: 1px solid rgba(166, 50, 71, 0.4);
            color: #A63247;
            padding: 6px 16px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            cursor: pointer;
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
            transition: background 0.2s, color 0.2s;
        }
        .btn-eliminar:hover {
            background: rgba(140, 8, 19, 0.3);
            border-color: #A63247;
            color: #ff6b80;
        }
        .btn-eliminar:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .badge-tipo {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 2px 8px;
            margin-bottom: 12px;
        }
        .badge-tipo.privada { background: rgba(140, 8, 19, 0.3); color: #A63247; border: 1px solid #A63247; }
        .badge-tipo.publica { background: rgba(29, 242, 221, 0.1); color: #1DF2DD; border: 1px solid #1DF2DD; }

        /* ── Badge Premium en header ── */
        .badge-premium {
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, #b45309, #fbbf24);
            color: #000;
            font-family: 'Orbitron', monospace;
            font-size: 0.6rem;
            font-weight: 900;
            letter-spacing: 0.1em;
            padding: 3px 9px;
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
            margin-left: 10px;
        }
        .badge-activar-premium {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,190,0,0.35);
            color: #fbbf24;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            padding: 3px 10px;
            cursor: pointer;
            text-decoration: none;
            margin-left: 10px;
            transition: background 0.2s;
        }
        .badge-activar-premium:hover { background: rgba(255,190,0,0.1); }

        .estado-mensaje {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #6b6b7a;
            font-size: 1.1rem;
            letter-spacing: 0.06em;
        }
        .estado-mensaje span {
            display: block;
            font-family: 'Orbitron', monospace;
            font-size: 0.8rem;
            color: #1DF2DD;
            margin-top: 8px;
        }

        #notificacion {
            position: fixed;
            top: 24px;
            right: 24px;
            padding: 14px 24px;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.06em;
            display: none;
            z-index: 1000;
            clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
        }
        #notificacion.exito {
            background: rgba(22, 140, 119, 0.95);
            color: #1DF2DD;
            border-left: 3px solid #1DF2DD;
        }
        #notificacion.error {
            background: rgba(140, 8, 19, 0.95);
            color: #ff6b80;
            border-left: 3px solid #A63247;
        }

        /* ── MODAL UNIRSE A LIGA ── */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .modal-overlay.hidden { display: none; }
        .modal-box {
            background: #141418;
            border: 1px solid rgba(29,242,221,0.2);
            border-top: 3px solid #A63247;
            padding: 36px;
            max-width: 520px;
            width: 92%;
            max-height: 85vh;
            overflow-y: auto;
        }
        .modal-box h2 { font-family: 'Orbitron'; color: #e8e8ee; font-size: 0.9rem; letter-spacing: 0.1em; margin-bottom: 24px; }
        .modal-tabs { display: flex; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 24px; }
        .modal-tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #6b6b7a;
            border-bottom: 2px solid transparent;
            transition: color 0.2s, border-color 0.2s;
        }
        .modal-tab.activo { color: #1DF2DD; border-bottom-color: #1DF2DD; }
        .tab-panel { display: none; }
        .tab-panel.activo { display: block; }

        .modal-input-label { font-size: 0.75rem; letter-spacing: 0.1em; color: #6b6b7a; text-transform: uppercase; margin-bottom: 6px; display: block; }
        .modal-input {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            color: #e8e8ee;
            margin-bottom: 16px;
            font-family: 'Barlow Condensed';
            font-size: 1rem;
        }
        .modal-input:focus { outline: none; border-color: rgba(29,242,221,0.4); }
        .input-codigo { font-family: 'Orbitron'; letter-spacing: 0.2em; text-transform: uppercase; font-size: 1.1rem; text-align: center; }

        .btn-modal-accion {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #8C0813, #A63247);
            color: #fff;
            font-weight: 700;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            font-family: 'Barlow Condensed';
            font-size: 1rem;
            letter-spacing: 0.08em;
            clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
            transition: box-shadow 0.2s;
        }
        .btn-modal-accion:hover { box-shadow: 0 6px 24px rgba(140,8,19,0.5); }
        .btn-modal-accion:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-modal-cerrar {
            width: 100%;
            padding: 10px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.08);
            color: #6b6b7a;
            cursor: pointer;
            margin-top: 10px;
            font-family: 'Barlow Condensed';
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .btn-modal-cerrar:hover { border-color: rgba(255,255,255,0.2); color: #e8e8ee; }

        .modal-error { color: #ff6b80; font-size: 0.85rem; margin-bottom: 14px; padding: 10px; background: rgba(140,8,19,0.15); border: 1px solid rgba(166,50,71,0.4); display: none; }
        .modal-error.visible { display: block; }

        /* Lista de ligas públicas en el modal */
        .lista-ligas-publicas { margin-top: 4px; }
        .liga-publica-item {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(29,242,221,0.1);
            padding: 14px 16px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .liga-publica-item:hover { border-color: rgba(29,242,221,0.4); background: rgba(29,242,221,0.04); }
        .liga-publica-item.seleccionada { border-color: #1DF2DD; background: rgba(29,242,221,0.08); }
        .liga-publica-nombre { font-family: 'Orbitron'; font-size: 0.8rem; color: #1DF2DD; margin-bottom: 4px; }
        .liga-publica-meta { font-size: 0.82rem; color: #6b6b7a; }
        .liga-publica-plazas { font-size: 0.8rem; color: #e8e8ee; font-weight: 700; }
        .cargando-ligas { color: #6b6b7a; text-align: center; padding: 20px; font-size: 0.9rem; }
        .sin-ligas { color: #6b6b7a; text-align: center; padding: 20px; font-size: 0.9rem; }
    </style>
</head>
<body>

    <div id="notificacion"></div>

    <div class="header">
        <h1>VALTASY <span style="font-size:0.45em; color:#6b6b7a; font-family:'Barlow Condensed', sans-serif; font-weight:400; letter-spacing:0.1em;">DASHBOARD</span></h1>
        <div class="header-right">
            <p>AGENTE: <span><?php echo htmlspecialchars($nombre_usuario_actual); ?></span><span id="premiumHeaderBadge"></span></p>
            <a href="../sesion/cerrar.php">[ DESCONECTAR ]</a>
        </div>
    </div>

    <div class="seccion-header">
        <h2>// Operaciones Activas</h2>
        <div class="seccion-header-btns">
            <a href="crear_liga.php" class="btn-crear">+ Crear Liga</a>
            <button class="btn-unirse" id="btnAbrirModalUnirse">⊕ Unirse a Liga</button>
        </div>
    </div>

    <div id="contenedorLigas" class="grid-ligas">
        <div class="estado-mensaje">
            INICIALIZANDO CONEXIÓN...
            <span>CARGANDO DATOS DE MISIONES</span>
        </div>
    </div>

    <div id="modalUnirse" class="modal-overlay hidden">
        <div class="modal-box">
            <h2>// ACCESO A OPERACIÓN EXISTENTE</h2>

            <div class="modal-tabs">
                <div class="modal-tab activo" data-tab="privada">🔒 Liga Privada</div>
                <div class="modal-tab" data-tab="publica">🌐 Liga Pública</div>
            </div>

            <div class="tab-panel activo" id="tab-privada">
                <p style="color:#6b6b7a; font-size:0.9rem; margin-bottom:20px;">Introduce el código de acceso que te ha compartido el creador de la liga.</p>
                <div id="errorPrivada" class="modal-error"></div>
                <label class="modal-input-label">Código de Acceso</label>
                <input type="text" id="inputCodigo" class="modal-input input-codigo" maxlength="10" placeholder="XXXXXXX" autocomplete="off">
                <label class="modal-input-label">Nombre de tu Escuadrón</label>
                <input type="text" id="nombreEquipoPrivada" class="modal-input" placeholder="Tu nombre de equipo" maxlength="100">
                <button class="btn-modal-accion" id="btnUnirsePrivada">CONFIRMAR ACCESO</button>
            </div>

            <div class="tab-panel" id="tab-publica">
                <p style="color:#6b6b7a; font-size:0.9rem; margin-bottom:16px;">Selecciona una operación pública disponible.</p>
                <div id="errorPublica" class="modal-error"></div>
                <div id="listaLigasPublicas" class="lista-ligas-publicas">
                    <div class="cargando-ligas">Cargando operaciones disponibles...</div>
                </div>
                <label class="modal-input-label" style="margin-top:16px;">Nombre de tu Escuadrón</label>
                <input type="text" id="nombreEquipoPublica" class="modal-input" placeholder="Tu nombre de equipo" maxlength="100">
                <button class="btn-modal-accion" id="btnUnirsePublica" disabled>UNIRSE A LA OPERACIÓN</button>
            </div>

            <button class="btn-modal-cerrar" id="btnCerrarModal">CANCELAR</button>
        </div>
    </div>

    <script>
        const USUARIO_ACTUAL = "<?php echo htmlspecialchars($nombre_usuario_actual, ENT_QUOTES); ?>";
        let ligaPublicaSeleccionada = null;

        // ── Notificación flotante ──────────────────────────────────────────────
        function mostrarNotificacion(texto, tipo = 'exito', duracion = 3000) {
            const notif = document.getElementById('notificacion');
            notif.textContent = texto;
            notif.className   = tipo;
            notif.style.display = 'block';
            setTimeout(() => { notif.style.display = 'none'; }, duracion);
        }

        // ── Escapa HTML para evitar XSS ────────────────────────────────────
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // ── Renderiza tarjetas de ligas ───────────────────────────────────────
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
                const tipoBadge = liga.tipo === 'Privada' ? 'privada' : 'publica';
                const tipoLabel = liga.tipo === 'Privada' ? 'CLASIFICADA' : 'NO CLASIFICADA';
                const presupuesto = new Intl.NumberFormat('es-ES').format(liga.presupuesto_disponible);

                const card = document.createElement('div');
                card.className  = 'liga-card';
                card.dataset.idLiga = liga.id_liga;

                // AQUÍ AÑADIMOS EL TORNEO
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

        // ── Carga las ligas del usuario ───────────────────────────────────────
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

        // ── Maneja la eliminación / salida de una liga ────────────────────────
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

        // ════════════════════════════════════════════════════════════════════
        // MODAL: UNIRSE A LIGA
        // ════════════════════════════════════════════════════════════════════

        const modal = document.getElementById('modalUnirse');

        // Abrir modal
        document.getElementById('btnAbrirModalUnirse').addEventListener('click', () => {
            modal.classList.remove('hidden');
            document.getElementById('inputCodigo').focus();
            cargarLigasPublicasModal();
        });

        // Cerrar modal
        document.getElementById('btnCerrarModal').addEventListener('click', cerrarModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModal(); });

        function cerrarModal() {
            modal.classList.add('hidden');
            document.getElementById('inputCodigo').value = '';
            document.getElementById('nombreEquipoPrivada').value = '';
            document.getElementById('nombreEquipoPublica').value = '';
            document.getElementById('errorPrivada').classList.remove('visible');
            document.getElementById('errorPublica').classList.remove('visible');
            ligaPublicaSeleccionada = null;
            document.getElementById('btnUnirsePublica').disabled = true;
        }

        // Tabs
        document.querySelectorAll('.modal-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('activo'));
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
                tab.classList.add('activo');
                document.getElementById('tab-' + tab.dataset.tab).classList.add('activo');
            });
        });

        // Cargar ligas públicas disponibles
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
                        item.innerHTML = `
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

        // ── Acción: unirse por código ──────────────────────────────────────
        document.getElementById('btnUnirsePrivada').addEventListener('click', async () => {
            const codigo      = document.getElementById('inputCodigo').value.trim().toUpperCase();
            const nombreEquipo = document.getElementById('nombreEquipoPrivada').value.trim();
            const errorDiv    = document.getElementById('errorPrivada');
            const btn         = document.getElementById('btnUnirsePrivada');

            errorDiv.classList.remove('visible');

            if (!codigo) { errorDiv.textContent = 'Introduce el código de acceso.'; errorDiv.classList.add('visible'); return; }
            if (!nombreEquipo) { errorDiv.textContent = 'Introduce el nombre de tu escuadrón.'; errorDiv.classList.add('visible'); return; }

            btn.disabled    = true;
            btn.textContent = 'VERIFICANDO...';

            try {
                const res  = await fetch('api_ligas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion:         'unirse',
                        nombre_usuario: USUARIO_ACTUAL,
                        codigo_acceso:  codigo,
                        nombre_equipo:  nombreEquipo
                    })
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

        // ── Acción: unirse a liga pública ──────────────────────────────────
        document.getElementById('btnUnirsePublica').addEventListener('click', async () => {
            const nombreEquipo = document.getElementById('nombreEquipoPublica').value.trim();
            const errorDiv    = document.getElementById('errorPublica');
            const btn         = document.getElementById('btnUnirsePublica');

            errorDiv.classList.remove('visible');

            if (!ligaPublicaSeleccionada) { errorDiv.textContent = 'Selecciona una liga de la lista.'; errorDiv.classList.add('visible'); return; }
            if (!nombreEquipo) { errorDiv.textContent = 'Introduce el nombre de tu escuadrón.'; errorDiv.classList.add('visible'); return; }

            btn.disabled    = true;
            btn.textContent = 'PROCESANDO...';

            try {
                const res  = await fetch('api_ligas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion:         'unirse',
                        nombre_usuario: USUARIO_ACTUAL,
                        id_liga:        ligaPublicaSeleccionada,
                        nombre_equipo:  nombreEquipo
                    })
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

        // ── Inicio ────────────────────────────────────────────────────────
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

        document.addEventListener('DOMContentLoaded', () => {
            cargarLigas();
            verificarPremiumDashboard();
        });
    </script>
</body>
</html>