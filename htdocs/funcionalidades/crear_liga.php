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
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/crear_liga.css">
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

    <!-- Modal: código de acceso para liga privada -->
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

    <!-- Modal: comprar Premium -->
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
                    <button class="plan-btn activo" data-meses="1">1 mes<span class="plan-precio">4,99€</span></button>
                    <button class="plan-btn" data-meses="3">3 meses<span class="plan-precio">12,99€</span><span class="plan-ahorro">Ahorra 2€</span></button>
                    <button class="plan-btn" data-meses="6">6 meses<span class="plan-precio">22,99€</span><span class="plan-ahorro">Ahorra 7€</span></button>
                    <button class="plan-btn" data-meses="12">12 meses<span class="plan-precio">39,99€</span><span class="plan-ahorro">Ahorra 20€</span></button>
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
                <button class="btn-comprar" id="btnComprarPremium">⚡ ACTIVAR PREMIUM — 4,99€</button>
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
    </script>
    <script src="js/crear_liga.js"></script>
</body>
</html>
