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

        .grid-ligas {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        /* ── Tarjeta de liga ── */
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

        /* Zona clickable que lleva a ver_liga.php */
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

        /* Pie de tarjeta con botón eliminar */
        .liga-card-footer {
            padding: 12px 22px;
            border-top: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: flex-end;
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

        /* Badge tipo de liga */
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

        /* Estados vacíos y de carga */
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

        /* Notificación de operación */
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
    </style>
</head>
<body>

    <div id="notificacion"></div>

    <div class="header">
        <h1>VALTASY <span style="font-size:0.45em; color:#6b6b7a; font-family:'Barlow Condensed', sans-serif; font-weight:400; letter-spacing:0.1em;">DASHBOARD</span></h1>
        <div class="header-right">
            <p>AGENTE: <span><?php echo htmlspecialchars($nombre_usuario_actual); ?></span></p>
            <a href="../sesion/cerrar.php">[ DESCONECTAR ]</a>
        </div>
    </div>

    <div class="seccion-header">
        <h2>// Operaciones Activas</h2>
        <a href="crear_liga.php" class="btn-crear">+ Crear Liga</a>
    </div>

    <div id="contenedorLigas" class="grid-ligas">
        <div class="estado-mensaje">
            INICIALIZANDO CONEXIÓN...
            <span>CARGANDO DATOS DE MISIONES</span>
        </div>
    </div>

    <script>
        const USUARIO_ACTUAL = "<?php echo htmlspecialchars($nombre_usuario_actual, ENT_QUOTES); ?>";

        // ── Notificación flotante ──────────────────────────────────────────────
        function mostrarNotificacion(texto, tipo = 'exito', duracion = 3000) {
            const notif = document.getElementById('notificacion');
            notif.textContent = texto;
            notif.className   = tipo;
            notif.style.display = 'block';
            setTimeout(() => { notif.style.display = 'none'; }, duracion);
        }

        // ── Renderiza tarjetas de ligas ───────────────────────────────────────
        function renderizarLigas(ligas) {
            const contenedor = document.getElementById('contenedorLigas');
            contenedor.innerHTML = '';

            if (!ligas || ligas.length === 0) {
                contenedor.innerHTML = `
                    <div class="estado-mensaje">
                        SIN OPERACIONES ACTIVAS
                        <span>CREA UNA LIGA PARA COMENZAR</span>
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

                card.innerHTML = `
                    <a href="ver_liga.php?id_liga=${encodeURIComponent(liga.id_liga)}" class="liga-card-link">
                        <span class="badge-tipo ${tipoBadge}">${tipoLabel}</span>
                        <h3>${escapeHtml(liga.nombre_liga)}</h3>
                        <div class="dato"><span>ESCUADRÓN</span><span>${escapeHtml(liga.nombre_equipo)}</span></div>
                        <div class="dato"><span>PUNTOS</span><span>${liga.puntos_equipo}</span></div>
                        <div class="dato"><span>PRESUPUESTO</span><span>$${presupuesto}</span></div>
                        <div class="dato"><span>POSICIÓN</span><span>#${liga.posicion_actual}</span></div>
                    </a>
                    <div class="liga-card-footer">
                        <button class="btn-eliminar" data-id="${liga.id_liga}" data-nombre="${escapeHtml(liga.nombre_liga)}">
                            ✕ ELIMINAR LIGA
                        </button>
                    </div>`;

                contenedor.appendChild(card);
            });

            // Delegación de eventos para los botones de eliminar
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

        // ── Maneja la eliminación de una liga ─────────────────────────────────
        async function manejarEliminar(e) {
            const btn      = e.currentTarget;
            const idLiga   = btn.dataset.id;
            const nombre   = btn.dataset.nombre;

            if (!confirm(`⚠ OPERACIÓN DE BAJA\n\n¿Confirmas la eliminación de la liga "${nombre}"?\n\nEsta acción es irreversible y eliminará todos los datos asociados.`)) {
                return;
            }

            btn.disabled    = true;
            btn.textContent = 'ELIMINANDO...';

            try {
                const res  = await fetch('api_ligas.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_liga:        idLiga,
                        nombre_usuario: USUARIO_ACTUAL
                    })
                });
                const json = await res.json();

                if (json.status === 'success') {
                    // Animar salida de la tarjeta
                    const card = btn.closest('.liga-card');
                    card.style.transition  = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity     = '0';
                    card.style.transform   = 'scale(0.95)';
                    setTimeout(() => card.remove(), 300);

                    // Si no quedan ligas, mostrar estado vacío
                    setTimeout(() => {
                        const cards = document.querySelectorAll('.liga-card');
                        if (cards.length === 0) {
                            renderizarLigas([]);
                        }
                    }, 350);

                    mostrarNotificacion('LIGA ELIMINADA CON ÉXITO', 'exito');
                } else {
                    mostrarNotificacion('ERROR: ' + (json.message || 'Operación fallida'), 'error', 4000);
                    btn.disabled    = false;
                    btn.textContent = '✕ ELIMINAR LIGA';
                }
            } catch (err) {
                mostrarNotificacion('ERROR DE CONEXIÓN CON EL SERVIDOR', 'error', 4000);
                btn.disabled    = false;
                btn.textContent = '✕ ELIMINAR LIGA';
            }
        }

        // ── Escapa HTML para evitar XSS al insertar datos en el DOM ──────────
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // ── Inicio ────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', cargarLigas);
    </script>
</body>
</html>
