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
    <title>Crear Operación — VALTASY</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0d0d0f; color: #e8e8ee;
            font-family: 'Barlow Condensed', sans-serif;
            min-height: 100vh; padding: 40px;
            background-image:
                radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140, 8, 19, 0.12) 0%, transparent 70%),
                linear-gradient(rgba(29, 242, 221, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(29, 242, 221, 0.02) 1px, transparent 1px);
            background-size: cover, 60px 60px, 60px 60px;
        }
        h1, h2 { font-family: 'Orbitron', monospace; text-transform: uppercase; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #A63247; padding-bottom: 20px; }
        .nav-tabs { display: flex; gap: 4px; margin-bottom: 32px; border-bottom: 1px solid rgba(29, 242, 221, 0.12); }
        .nav-tab { font-family: 'Barlow Condensed', sans-serif; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #6b6b7a; text-decoration: none; padding: 10px 22px; border: 1px solid transparent; border-bottom: none; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); transition: color 0.2s, background 0.2s; position: relative; top: 1px; }
        .nav-tab:hover { color: #1DF2DD; }
        .nav-tab.activo { color: #1DF2DD; background: #141418; border-color: rgba(29, 242, 221, 0.25); border-bottom-color: #141418; }
        .card { background: #141418; border: 1px solid rgba(29, 242, 221, 0.12); padding: 40px; max-width: 500px; margin: 0 auto; }
        input[type="text"], select { width: 100%; padding: 12px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: #e8e8ee; margin-bottom: 20px; font-family: 'Barlow Condensed'; font-size: 1rem; }
        input[type="text"]:focus, select:focus { outline: none; border-color: rgba(29,242,221,0.4); }
        label { display: block; font-size: 0.8rem; letter-spacing: 0.1em; color: #6b6b7a; margin-bottom: 8px; text-transform: uppercase; }
        button[type="submit"] { width: 100%; padding: 14px; background: linear-gradient(135deg, #8C0813, #A63247); color: #fff; font-weight: 700; text-transform: uppercase; border: none; cursor: pointer; clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); font-family: 'Barlow Condensed'; font-size: 1rem; letter-spacing: 0.08em; transition: box-shadow 0.2s; }
        button[type="submit"]:hover { box-shadow: 0 6px 24px rgba(140, 8, 19, 0.5); }
        .btn-volver { color: #1DF2DD; text-decoration: none; font-weight: bold; font-size: 1.1rem; }
        #mensaje { margin-top: 20px; padding: 15px; display: none; text-align: center; font-weight: bold; }
        .exito { color: #1DF2DD; border: 1px solid #1DF2DD; background: rgba(29, 242, 221, 0.1); }
        .error { color: #A63247; border: 1px solid #A63247; background: rgba(140, 8, 19, 0.2); }

        /* ── Badge premium en el tipo de liga ── */
        .tipo-option-wrapper { position: relative; }
        .premium-lock-notice {
            display: none; /* se muestra con JS si no es premium */
            margin-top: -12px;
            margin-bottom: 16px;
            padding: 10px 14px;
            background: rgba(255, 190, 0, 0.07);
            border: 1px solid rgba(255, 190, 0, 0.3);
            color: #fbbf24;
            font-size: 0.82rem;
            letter-spacing: 0.04em;
        }
        .premium-lock-notice strong { color: #fde68a; }
        .premium-lock-notice .link-premium {
            color: #fbbf24;
            text-decoration: underline;
            cursor: pointer;
            font-weight: 700;
        }

        select option:disabled { color: #444; }

        /* ── Badge premium junto al header ── */
        .badge-premium {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #b45309, #fbbf24);
            color: #000;
            font-family: 'Orbitron', monospace;
            font-size: 0.65rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            padding: 4px 10px;
            clip-path: polygon(5px 0%, 100% 0%, calc(100% - 5px) 100%, 0% 100%);
        }
        .badge-no-premium {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,190,0,0.3);
            color: #fbbf24;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            padding: 4px 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .badge-no-premium:hover { background: rgba(255,190,0,0.12); }

        /* ── Modal código (liga privada creada) ── */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .modal-overlay.hidden { display: none; }
        .modal-box { background: #141418; border: 2px solid #1DF2DD; padding: 40px; max-width: 440px; width: 90%; text-align: center; }
        .modal-box h2 { font-family: 'Orbitron'; color: #1DF2DD; margin-bottom: 10px; font-size: 1rem; letter-spacing: 0.1em; }
        .modal-box p { color: #6b6b7a; margin-bottom: 20px; font-size: 0.95rem; }
        .codigo-display { font-family: 'Orbitron'; font-size: 2rem; letter-spacing: 0.3em; color: #fff; background: rgba(29, 242, 221, 0.08); border: 2px dashed #1DF2DD; padding: 18px 24px; margin-bottom: 24px; cursor: pointer; user-select: all; transition: background 0.2s; }
        .codigo-display:hover { background: rgba(29, 242, 221, 0.15); }
        .aviso-codigo { font-size: 0.8rem; color: #A63247; margin-bottom: 20px; letter-spacing: 0.05em; }
        .btn-modal { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #168C77, #1DF2DD); color: #000; font-weight: 700; text-decoration: none; text-transform: uppercase; cursor: pointer; border: none; font-family: 'Barlow Condensed'; font-size: 1rem; clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); }
        .copiado-msg { font-size: 0.8rem; color: #1DF2DD; min-height: 1.2em; margin-bottom: 8px; }

        /* ── Modal PREMIUM ── */
        .modal-premium-box {
            background: #141418;
            border: 2px solid #fbbf24;
            padding: 0;
            max-width: 480px;
            width: 92%;
            overflow: hidden;
        }
        .premium-header {
            background: linear-gradient(135deg, #78350f, #b45309);
            padding: 28px 32px 20px;
            text-align: center;
        }
        .premium-header h2 {
            font-family: 'Orbitron';
            font-size: 1.1rem;
            color: #fde68a;
            letter-spacing: 0.12em;
            margin-bottom: 6px;
        }
        .premium-header p { color: #fcd34d; font-size: 0.9rem; }
        .premium-body { padding: 28px 32px; }
        .premium-perks { list-style: none; margin-bottom: 24px; }
        .premium-perks li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.95rem;
            color: #e8e8ee;
            display: flex; align-items: center; gap: 10px;
        }
        .premium-perks li:last-child { border-bottom: none; }
        .premium-perks .icon { color: #fbbf24; font-size: 1rem; }
        .premium-perks .perk-bloqueado { color: #6b6b7a; text-decoration: line-through; }

        .plan-selector { display: grid; grid-template-columns: repeat(4,1fr); gap: 8px; margin-bottom: 22px; }
        .plan-btn {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,190,0,0.2);
            color: #e8e8ee;
            padding: 10px 4px;
            cursor: pointer;
            font-family: 'Barlow Condensed';
            font-size: 0.82rem;
            text-align: center;
            transition: border-color 0.2s, background 0.2s;
        }
        .plan-btn:hover, .plan-btn.activo { border-color: #fbbf24; background: rgba(255,190,0,0.08); color: #fde68a; }
        .plan-btn .plan-precio { font-family: 'Orbitron'; font-size: 0.95rem; color: #fbbf24; display: block; margin-top: 4px; }
        .plan-btn .plan-ahorro { font-size: 0.7rem; color: #86efac; display: block; margin-top: 2px; }

        .metodo-pago { margin-bottom: 20px; }
        .metodo-pago label { margin-bottom: 6px; }
        .metodo-pago select { margin-bottom: 0; }

        .btn-comprar {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #b45309, #fbbf24);
            color: #000;
            font-weight: 900;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
            font-family: 'Orbitron';
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            transition: box-shadow 0.2s;
        }
        .btn-comprar:hover { box-shadow: 0 6px 24px rgba(251,191,36,0.4); }
        .btn-comprar:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-cancelar-premium {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.08);
            color: #6b6b7a;
            cursor: pointer;
            font-family: 'Barlow Condensed';
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .btn-cancelar-premium:hover { border-color: rgba(255,255,255,0.2); color: #e8e8ee; }
        .premium-aviso {
            font-size: 0.75rem; color: #6b6b7a;
            text-align: center; margin-top: 14px;
            letter-spacing: 0.03em; line-height: 1.5;
        }
        #msgPremium {
            margin-top: 12px; padding: 10px; display: none;
            text-align: center; font-size: 0.9rem; font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="header">
        <div style="display:flex; align-items:center; gap:16px;">
            <h1>VALTASY <span style="font-size:0.5em; color:gray;">CREACIÓN</span></h1>
            <span id="badgePremiumHeader"></span>
        </div>
        <div style="text-align:right;">
            <p style="font-size:1rem; color:#6b6b7a; margin-bottom:4px;">AGENTE: <span style="color:#1DF2DD; font-weight:700;"><?php echo htmlspecialchars($nombre_usuario_actual); ?></span></p>
            <a href="../sesion/cerrar.php" style="color:#6b6b7a; text-decoration:none; font-size:0.85rem;">[ DESCONECTAR ]</a>
        </div>
    </div>

    <nav class="nav-tabs">
        <a href="cliente.php"    class="nav-tab">Dashboard</a>
        <a href="crear_liga.php" class="nav-tab activo">Nueva Liga</a>
        <a href="noticias.php"   class="nav-tab">Noticias</a>
    </nav>

    <div class="card">
        <h2>Crear Liga</h2><br>
        <form id="formCrearLiga">
            <input type="hidden" id="nombreUsuario" value="<?php echo htmlspecialchars($nombre_usuario_actual); ?>">

            <label>Nombre de la Liga (Operación):</label>
            <input type="text" id="nombreLiga" required>

            <label>Nombre de tu Escuadrón (Equipo):</label>
            <input type="text" id="nombreEquipo" required>

            <label>Nivel de Acceso (Tipo):</label>
            <div class="tipo-option-wrapper">
                <select id="tipoLiga">
                    <option value="Publica">No Clasificada (Pública)</option>
                    <option value="Privada" id="optionPrivada">🔒 Clasificada (Privada) — Premium</option>
                </select>
                <!-- Aviso que aparece cuando se elige Privada sin premium -->
                <div class="premium-lock-notice" id="lockNotice">
                    <strong>⚡ Función Premium</strong> — Las ligas privadas requieren una suscripción activa.
                    <br><span class="link-premium" id="linkAbrirPremium">Activar Premium por 4,99€/mes →</span>
                </div>
            </div>

            <label>Torneo a simular:</label>
            <select id="torneoLiga">
                <option value="VCT EMEA - Kickoff">VCT EMEA - Kickoff</option>
                <option value="VCT EMEA - Fase Regular">VCT EMEA - Fase Regular</option>
                <option value="VCT EMEA - Playoffs">VCT EMEA - Playoffs</option>
                <option value="VCT EMEA - Last Chance">VCT EMEA - LCQ</option>
            </select>

            <button type="submit" id="btnCrear">Iniciar Operación</button>
        </form>
        <div id="mensaje"></div>
    </div>

    <!-- ── MODAL: código de acceso para liga privada ── -->
    <div id="modalCodigo" class="modal-overlay hidden">
        <div class="modal-box">
            <h2>⚡ OPERACIÓN AUTORIZADA</h2>
            <p>Tu liga privada ha sido creada. Comparte este <strong style="color:#fff;">código de acceso</strong> con los agentes que quieras reclutar.</p>
            <div class="codigo-display" id="codigoTexto" title="Haz clic para copiar"></div>
            <p class="copiado-msg" id="copiadoMsg"></p>
            <p class="aviso-codigo">⚠ Guarda este código. Solo podrás verlo aquí.</p>
            <button class="btn-modal" onclick="irAlDashboard()">IR AL DASHBOARD</button>
        </div>
    </div>

    <!-- ── MODAL: comprar Premium ── -->
    <div id="modalPremium" class="modal-overlay hidden">
        <div class="modal-premium-box">
            <div class="premium-header">
                <h2>⚡ VALTASY PREMIUM</h2>
                <p>Desbloquea las ligas clasificadas y más funciones exclusivas</p>
            </div>
            <div class="premium-body">
                <ul class="premium-perks">
                    <li><span class="icon">🔒</span> Crear ligas privadas con código de acceso</li>
                    <li><span class="icon">👥</span> Invitar agentes de confianza a tu operación</li>
                    <li><span class="icon">📊</span> Estadísticas avanzadas de tu equipo <span style="color:#86efac; font-size:0.75rem;">(próximamente)</span></li>
                    <li><span class="icon">⭐</span> Insignia premium en el ranking</li>
                    <li><span class="icon perk-bloqueado">🌐</span> <span class="perk-bloqueado">Ligas públicas — disponible sin premium</span></li>
                </ul>

                <div class="plan-selector" id="planSelector">
                    <button class="plan-btn activo" data-meses="1">
                        1 mes<span class="plan-precio">4,99€</span>
                    </button>
                    <button class="plan-btn" data-meses="3">
                        3 meses<span class="plan-precio">12,99€</span><span class="plan-ahorro">Ahorra 2€</span>
                    </button>
                    <button class="plan-btn" data-meses="6">
                        6 meses<span class="plan-precio">22,99€</span><span class="plan-ahorro">Ahorra 7€</span>
                    </button>
                    <button class="plan-btn" data-meses="12">
                        12 meses<span class="plan-precio">39,99€</span><span class="plan-ahorro">Ahorra 20€</span>
                    </button>
                </div>

                <div class="metodo-pago">
                    <label>Método de pago:</label>
                    <select id="metodoPago">
                        <option value="tarjeta">💳 Tarjeta de crédito / débito</option>
                        <option value="paypal">🅿 PayPal</option>
                        <option value="bizum">📱 Bizum</option>
                    </select>
                </div>

                <div id="msgPremium"></div>

                <button class="btn-comprar" id="btnComprarPremium">
                    ⚡ ACTIVAR PREMIUM — 4,99€
                </button>
                <button class="btn-cancelar-premium" id="btnCancelarPremium">CANCELAR</button>

                <p class="premium-aviso">
                    Pago simulado — ningún cargo real se realizará.<br>
                    En producción se integraría con Stripe / PayPal.
                </p>
            </div>
        </div>
    </div>

    <script>
        const USUARIO = "<?php echo htmlspecialchars($nombre_usuario_actual, ENT_QUOTES); ?>";
        let esPremium  = false;
        let planMeses  = 1;
        const precios  = { 1: '4,99€', 3: '12,99€', 6: '22,99€', 12: '39,99€' };
        const preciosNum = { 1: 4.99, 3: 12.99, 6: 22.99, 12: 39.99 };

        // ── Verificar estado premium al cargar ────────────────────────────────
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
                badge.innerHTML = '<span class="badge-premium">⚡ PREMIUM</span>';
                notice.style.display = 'none';
                optPriv.disabled = false;
                optPriv.textContent = '🔒 Clasificada (Privada)';
            } else {
                badge.innerHTML = '<span class="badge-no-premium" id="badgeAbrirPremium">⚡ Activar Premium</span>';
                document.getElementById('badgeAbrirPremium').addEventListener('click', abrirModalPremium);
                optPriv.disabled = false; // permitimos seleccionarla para mostrar el aviso
            }
        }

        // Detectar cambio en el tipo de liga
        document.getElementById('tipoLiga').addEventListener('change', function () {
            const notice = document.getElementById('lockNotice');
            if (this.value === 'Privada' && !esPremium) {
                notice.style.display = 'block';
            } else {
                notice.style.display = 'none';
            }
        });

        document.getElementById('linkAbrirPremium').addEventListener('click', abrirModalPremium);

        // ── Modal Premium ─────────────────────────────────────────────────────
        function abrirModalPremium() {
            document.getElementById('modalPremium').classList.remove('hidden');
        }

        document.getElementById('btnCancelarPremium').addEventListener('click', () => {
            document.getElementById('modalPremium').classList.add('hidden');
            document.getElementById('msgPremium').style.display = 'none';
        });

        // Selector de plan
        document.getElementById('planSelector').querySelectorAll('.plan-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.plan-btn').forEach(b => b.classList.remove('activo'));
                btn.classList.add('activo');
                planMeses = parseInt(btn.dataset.meses);
                document.getElementById('btnComprarPremium').textContent =
                    `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
            });
        });

        // Comprar premium
        document.getElementById('btnComprarPremium').addEventListener('click', async () => {
            const btn    = document.getElementById('btnComprarPremium');
            const msg    = document.getElementById('msgPremium');
            const metodo = document.getElementById('metodoPago').value;

            btn.disabled    = true;
            btn.textContent = 'PROCESANDO PAGO...';
            msg.style.display = 'none';

            try {
                const res  = await fetch('api_premium.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion:      'comprar',
                        nombre_usuario: USUARIO,
                        metodo_pago: metodo,
                        meses:       planMeses
                    })
                });
                const json = await res.json();

                if (json.status === 'success') {
                    esPremium = true;
                    actualizarUI();

                    msg.style.display = 'block';
                    msg.style.color   = '#1DF2DD';
                    msg.style.border  = '1px solid #1DF2DD';
                    msg.style.background = 'rgba(29,242,221,0.08)';
                    msg.textContent   = `✓ ¡Premium activo hasta ${new Date(json.premium_hasta).toLocaleDateString('es-ES')}!`;

                    // Cerrar modal automáticamente tras 2 s
                    setTimeout(() => {
                        document.getElementById('modalPremium').classList.add('hidden');
                        msg.style.display = 'none';
                        // Si el usuario tenía Privada seleccionada, quitar aviso
                        document.getElementById('lockNotice').style.display = 'none';
                    }, 2200);

                } else {
                    msg.style.display = 'block';
                    msg.style.color   = '#ff6b80';
                    msg.style.border  = '1px solid #A63247';
                    msg.style.background = 'rgba(140,8,19,0.15)';
                    msg.textContent   = 'Error: ' + (json.message || 'Inténtalo de nuevo.');
                    btn.disabled      = false;
                    btn.textContent   = `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
                }
            } catch (_) {
                msg.style.display = 'block';
                msg.style.color   = '#ff6b80';
                msg.style.border  = '1px solid #A63247';
                msg.style.background = 'rgba(140,8,19,0.15)';
                msg.textContent   = 'Error de conexión con el servidor.';
                btn.disabled      = false;
                btn.textContent   = `⚡ ACTIVAR PREMIUM — ${precios[planMeses]}`;
            }
        });

        // ── Formulario de creación de liga ────────────────────────────────────
        document.getElementById("formCrearLiga").addEventListener("submit", async (e) => {
            e.preventDefault();
            const divMensaje = document.getElementById("mensaje");
            const tipo       = document.getElementById("tipoLiga").value;

            // Bloquear ligas privadas si no es premium
            if (tipo === 'Privada' && !esPremium) {
                divMensaje.style.display = 'block';
                divMensaje.className     = 'error';
                divMensaje.textContent   = '⚡ Necesitas Premium para crear ligas privadas.';
                abrirModalPremium();
                return;
            }

            const datos = {
                nombre_usuario: document.getElementById("nombreUsuario").value,
                nombre_liga:    document.getElementById("nombreLiga").value,
                nombre_equipo:  document.getElementById("nombreEquipo").value,
                tipo:           tipo,
                torneo:         document.getElementById("torneoLiga").value
            };

            const btn = document.getElementById("btnCrear");
            btn.disabled    = true;
            btn.textContent = 'INICIANDO OPERACIÓN...';

            try {
                const res  = await fetch("api_ligas.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(datos)
                });
                const json = await res.json();

                if (res.status === 201 || json.status === "success") {
                    document.getElementById("formCrearLiga").reset();
                    document.getElementById('lockNotice').style.display = 'none';

                    if (json.codigo_acceso) {
                        document.getElementById("codigoTexto").textContent = json.codigo_acceso;
                        document.getElementById("modalCodigo").classList.remove("hidden");
                    } else {
                        divMensaje.style.display = "block";
                        divMensaje.className     = "exito";
                        divMensaje.textContent   = "¡Operación autorizada! Liga pública creada con éxito.";
                        setTimeout(() => { window.location.href = "cliente.php"; }, 1500);
                    }
                } else {
                    divMensaje.style.display = "block";
                    divMensaje.className     = "error";
                    divMensaje.textContent   = "Error: " + json.message;
                }
            } catch (_) {
                divMensaje.style.display = "block";
                divMensaje.className     = "error";
                divMensaje.textContent   = "Error de conexión con los servidores.";
            } finally {
                btn.disabled    = false;
                btn.textContent = 'Iniciar Operación';
            }
        });

        // ── Modal código privado ──────────────────────────────────────────────
        document.getElementById("codigoTexto").addEventListener("click", async () => {
            const codigo = document.getElementById("codigoTexto").textContent;
            try {
                await navigator.clipboard.writeText(codigo);
                const msg = document.getElementById("copiadoMsg");
                msg.textContent = "✓ Código copiado al portapapeles";
                setTimeout(() => { msg.textContent = ""; }, 2500);
            } catch (_) {}
        });

        function irAlDashboard() { window.location.href = "cliente.php"; }

        // ── Inicio ────────────────────────────────────────────────────────────
        verificarPremium();
    </script>
</body>
</html>