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
    <script src="js/theme-init.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/crear_liga.css"> 
</head>
<body>
    <div id="notificacion"></div>
    <div class="header">
        <div class="header-logo">
            <img src="../img/logovaltasy_rojo.png" alt="VALTASY" class="header-logo-img"><span class="header-logo-text">VALT<span>ASY</span></span> <span style="font-size:0.45em; color:#6b6b7a; font-family:'Barlow Condensed', sans-serif; font-weight:400; letter-spacing:0.1em;">DASHBOARD</span>
        </div>
        <div class="header-right">
        <p>AGENTE: <span><?php echo htmlspecialchars($nombre_usuario_actual); ?></span></p>
            <span id="header-badge-premium"></span>
            <a href="../sesion/cerrar.php">[ DESCONECTAR ]</a>
        </div>
    </div>

    <nav class="nav-tabs">
        <a href="cliente.php"  class="nav-tab activo">Dashboard</a>
        <a href="noticias.php" class="nav-tab">Noticias</a>
        <a href="estadisticas.php" class="nav-tab">Estadísticas</a>
    </nav>

    <div class="seccion-header">
        <h2>// Operaciones Activas</h2>
        <div class="seccion-header-btns">
            <a href="crear_liga.php" class="btn-crear">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Crear Liga
            </a>
            <button class="btn-unirse" id="btnAbrirModalUnirse">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="4" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M1 12c0-2.21 1.79-4 4-4s4 1.79 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M11 6v4M13 8h-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                Unirse a Liga
            </button>
        </div>
    </div>

    <div id="contenedorLigas" class="grid-ligas">
        <div class="estado-mensaje">INICIALIZANDO CONEXIÓN...<span>CARGANDO DATOS DE MISIONES</span></div>
    </div>

    <!-- Modal Unirse a Liga -->
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

    <!-- Modal Premium -->
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
            </div>
        </div>
    </div>

    <!-- Modal Recompensa Diaria -->
    <div id="modalRecompensa" class="modal-overlay hidden">
        <div class="modal-recompensa-box">
            <h2 class="modal-header-title titulo-amarillo">🎁 SUMINISTROS: <span id="nombreLigaRecompensa" style="color:white;"></span></h2>
            <p style="color: #a1a1aa; font-size: 0.9rem;">Mejora el presupuesto de esta operación diaria.</p>
            
            <div class="recompensa-stats">
                <div><span>Suministro Base:</span> <span id="recBase">3.000 €</span></div>
                <div><span>Racha (<span id="recDiasRacha">0</span> días):</span> <span style="color:#4ade80;">+<span id="recBonoRacha">0</span>%</span></div>
                <div><span>Bono Premium:</span> <span id="recBonoPremium" style="color:#c084fc;">+0%</span></div>
                <div class="recompensa-total"><span>TOTAL:</span> <span id="recTotal">0 €</span></div>
            </div>
            
            <!-- Mensaje de Progreso de Hito -->
            <p id="recMensajeProgreso" style="color: #86efac; font-size: 0.85rem; margin-bottom: 20px; font-style: italic;"></p>
            
            <button id="btnReclamarRecompensa" class="btn-reclamar">RECLAMAR AHORA</button>
            <button class="btn-modal-cerrar" id="btnCerrarRecompensa">CERRAR</button>
        </div>
    </div>

    <!-- Modal de Tienda -->
    <div id="modalTienda" class="modal-overlay hidden">
        <div class="modal-tienda-box">
            <h2 class="modal-header-title titulo-cyan">💳 FONDOS BLACK MARKET</h2>
            <p style="color: #a1a1aa; font-size: 0.9rem;">Inyecta presupuesto directamente a: <span id="nombreLigaTienda" style="color:white; font-weight:bold;"></span></p>
            
            <div class="tienda-grid" id="contenedorPacksTienda">
                <div class="tienda-pack" data-pack="pack1" data-precio="1.99">
                    <div class="pack-monto">1.000.000 €</div>
                    <div class="pack-precio">1,99 €</div>
                </div>
                <div class="tienda-pack activo" data-pack="pack2" data-precio="4.99">
                    <div class="pack-monto">3.500.000 €</div>
                    <div class="pack-precio">4,99 €</div>
                </div>
                <div class="tienda-pack" data-pack="pack3" data-precio="9.99">
                    <div class="pack-monto">8.000.000 €</div>
                    <div class="pack-precio">9,99 €</div>
                </div>
                <div class="tienda-pack" data-pack="pack4" data-precio="19.99">
                    <div class="pack-monto">20.000.000 €</div>
                    <div class="pack-precio">19,99 €</div>
                </div>
            </div>
            
            <div id="msgTiendaError" style="color:#ff7a8a; font-size:0.9rem; margin-bottom:10px; display:none;"></div>
            <button id="btnComprarTienda" class="btn-comprar-pack">AUTORIZAR TRANSFERENCIA — 4,99 €</button>
            <button class="btn-modal-cerrar" id="btnCerrarTienda">CANCELAR</button>
        </div>
    </div>

    <script>
        const USUARIO_ACTUAL = "<?php echo htmlspecialchars($nombre_usuario_actual, ENT_QUOTES); ?>";
    </script>
    <script src="js/theme-manager.js"></script>
    <script src="js/cliente.js"></script>
</body>
</html>