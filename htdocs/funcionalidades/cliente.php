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

    <nav class="nav-tabs">
        <a href="cliente.php"    class="nav-tab activo">Dashboard</a>
        <a href="crear_liga.php" class="nav-tab">Nueva Liga</a>
        <a href="noticias.php"   class="nav-tab">Noticias</a>
    </nav>

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

    <!-- Modal: unirse a liga -->
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
    </script>
    <script src="js/cliente.js"></script>
</body>
</html>
