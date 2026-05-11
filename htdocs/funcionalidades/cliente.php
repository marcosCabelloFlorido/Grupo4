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
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/cliente.css">
    <link rel="stylesheet" href="css/crear_liga.css"> 
    <style>
        /* =========================================================
           SISTEMA DE BOTONES UNIFICADO (GRID 2x2)
           ========================================================= */
        .btn-grid-container {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 10px !important;
            width: 100% !important;
            margin-top: 15px !important;
            padding-top: 15px !important;
            border-top: 1px solid rgba(255,255,255,0.05) !important;
        }

        .btn-card-action {
            font-family: 'Orbitron', sans-serif !important;
            font-weight: 900 !important;
            font-size: 0.8rem !important;
            text-transform: uppercase !important;
            text-decoration: none !important;
            text-align: center !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 12px 5px !important;
            border-radius: 6px !important;
            border: none !important;
            color: #fff !important;
            cursor: pointer !important;
            transition: transform 0.2s, box-shadow 0.2s !important;
            width: 100% !important;
            box-sizing: border-box !important;
            letter-spacing: 0.05em !important;
            position: relative !important;
            overflow: hidden !important;
        }

        .btn-card-action::before, .btn-card-action::after {
            display: none !important;
        }

        .btn-card-action:hover {
            transform: translateY(-2px) !important;
        }

        .btn-purple {
            background: linear-gradient(45deg, #8b5cf6, #6d28d9) !important; 
            box-shadow: 0 0 10px rgba(139, 92, 246, 0.2) !important;
        }
        .btn-purple:hover { box-shadow: 0 0 15px rgba(139, 92, 246, 0.5) !important; }

        .btn-cyan {
            background: linear-gradient(45deg, #22d3ee, #0891b2) !important; 
            box-shadow: 0 0 10px rgba(34, 211, 238, 0.2) !important;
        }
        .btn-cyan:hover { box-shadow: 0 0 15px rgba(34, 211, 238, 0.5) !important; }

        .btn-yellow {
            background: linear-gradient(45deg, #eab308, #ca8a04) !important; 
            color: #1e1e28 !important;
            box-shadow: 0 0 10px rgba(234, 179, 8, 0.2) !important;
        }
        .btn-yellow:hover { box-shadow: 0 0 15px rgba(234, 179, 8, 0.5) !important; }

        .btn-red {
            background: linear-gradient(45deg, #ef4444, #b91c1c) !important; 
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.2) !important;
        }
        .btn-red:hover { box-shadow: 0 0 15px rgba(239, 68, 68, 0.5) !important; }

        /* Estilos de Modales */
        .modal-recompensa-box, .modal-tienda-box {
            background: #1e1e28; padding: 30px; border-radius: 8px; width: 90%; max-width: 450px;
            text-align: center; color: #fff; position: relative;
        }
        .modal-recompensa-box { border: 2px solid #eab308; }
        .modal-tienda-box { border: 2px solid #22d3ee; }
        .modal-header-title { font-family: 'Orbitron', sans-serif; margin-bottom: 5px; font-size: 1.2rem; }
        .titulo-amarillo { color: #eab308; }
        .titulo-cyan { color: #22d3ee; }
        .recompensa-stats { background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; margin: 25px 0 10px; text-align: left; }
        .recompensa-stats div { display: flex; justify-content: space-between; margin-bottom: 12px; font-family: 'Barlow Condensed', sans-serif; font-size: 1.1rem; }
        .recompensa-total { font-size: 1.5rem !important; color: #eab308; font-weight: bold; padding-top: 15px; border-top: 1px dashed rgba(234, 179, 8, 0.3); }
        .tienda-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 25px 0; }
        .tienda-pack { background: rgba(255,255,255,0.03); border: 1px solid rgba(34, 211, 238, 0.2); border-radius: 8px; padding: 15px; cursor: pointer; transition: all 0.3s; }
        .tienda-pack:hover, .tienda-pack.activo { background: rgba(34, 211, 238, 0.1); border-color: #22d3ee; transform: scale(1.05); }
        .pack-monto { font-family: 'Orbitron', sans-serif; font-size: 1.2rem; color: #22d3ee; font-weight: bold; margin-bottom: 5px; }
        .pack-precio { font-size: 0.9rem; color: #a1a1aa; background: rgba(0,0,0,0.3); padding: 3px 8px; border-radius: 10px; display: inline-block; }
        .btn-reclamar, .btn-comprar-pack { width: 100%; padding: 15px; font-size: 1.1rem; font-weight: 900; border: none; border-radius: 4px; cursor: pointer; font-family: 'Orbitron', sans-serif; margin-bottom: 15px; }
        .btn-reclamar { background: #eab308; color: #1e1e28; }
        .btn-comprar-pack { background: #22d3ee; color: #1e1e28; }
        .btn-reclamar:disabled, .btn-comprar-pack:disabled { background: #3f3f46; color: #a1a1aa; cursor: not-allowed; }
        #temporizadorRecompensa { color: #f87171; font-family: monospace; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div id="notificacion"></div>
    <div class="header">
        <h1>VALTASY <span style="font-size:0.45em; color:#6b6b7a; font-family:'Barlow Condensed', sans-serif; font-weight:400; letter-spacing:0.1em;">DASHBOARD</span></h1>
        <div class="header-right">
            <p>AGENTE: <span><?php echo htmlspecialchars($nombre_usuario_actual); ?></span> <span id="premiumHeaderBadge"></span></p>
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
            <a href="crear_liga.php" class="btn-crear">+ Crear Liga</a>
            <button class="btn-unirse" id="btnAbrirModalUnirse">⊕ Unirse a Liga</button>
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