<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../sesion/login.php");
    exit();
}

require_once '../conexion.php';
$nombre_usuario_actual = $_SESSION['usuario'];

// Verificar si el usuario es premium
$stmt = $conexion->prepare("SELECT es_premium FROM usuarios WHERE nombre = ?");
$stmt->execute([$nombre_usuario_actual]);
$usuario = $stmt->fetch();
$es_premium = $usuario['es_premium'] ?? 0;

// Verificar si viene desde una liga específica
$id_liga = isset($_GET['id_liga']) && ctype_digit($_GET['id_liga']) ? (int)$_GET['id_liga'] : null;
$liga_info = null;

if ($id_liga) {
    // Obtener información de la liga
    $stmt = $conexion->prepare("SELECT nombre FROM ligas WHERE id_liga = ?");
    $stmt->execute([$id_liga]);
    $liga_info = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VALTASY — Estadísticas Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/estadisticas.css">
</head>
<body>

<div class="header">
    <div class="header-logo">VALT<span>ASY</span></div>
    <div class="header-right">
        <p>AGENTE: <span><?= htmlspecialchars($nombre_usuario_actual) ?></span></p>
        <?php if ($es_premium): ?>
            <div class="badge-premium">⚡ PREMIUM</div>
        <?php endif; ?>
        <?php if ($id_liga && $liga_info): ?>
            <a href="ver_liga.php?id_liga=<?= $id_liga ?>" class="btn-volver-liga">← Volver a <?= htmlspecialchars($liga_info['nombre']) ?></a>
        <?php endif; ?>
        <a href="../sesion/cerrar.php" style="margin-top:6px; display:inline-block;">[ DESCONECTAR ]</a>
    </div>
</div>

<nav class="nav-tabs">
    <a href="cliente.php"  class="nav-tab">Dashboard</a>
    <a href="noticias.php" class="nav-tab">Noticias</a>
    <a href="estadisticas.php" class="nav-tab activo">Estadísticas</a>
</nav>

<?php if (!$es_premium): ?>
    <!-- Mensaje para usuarios no premium -->
    <div class="premium-lock-container">
        <div class="premium-lock-box">
            <div class="lock-icon">🔒</div>
            <h2>CONTENIDO PREMIUM EXCLUSIVO</h2>
            <p>Las estadísticas avanzadas de jugadores están disponibles únicamente para agentes con suscripción <strong>VALTASY PREMIUM</strong>.</p>
            
            <div class="premium-features">
                <h3>Con Premium tendrás acceso a:</h3>
                <ul>
                    <li><span class="icon">📊</span> Estadísticas detalladas de todos los jugadores</li>
                    <li><span class="icon">🎯</span> Métricas avanzadas (K/D, Aces, Clutches)</li>
                    <li><span class="icon">📈</span> Media de puntos fantasy por jugador</li>
                    <li><span class="icon">🔍</span> Filtros por equipo, rol y precio</li>
                    <li><span class="icon">⚡</span> Ordenamiento por cualquier métrica</li>
                    <li><span class="icon">🔒</span> Ligas privadas con código de acceso</li>
                </ul>
            </div>

            <a href="cliente.php" class="btn-activar-premium">⚡ ACTIVAR PREMIUM AHORA</a>
            <a href="cliente.php" class="btn-volver">← Volver al Dashboard</a>
        </div>
    </div>
<?php else: ?>
    <!-- Contenido premium: Estadísticas -->
    <div class="page-header">
        <div class="page-title-block">
            <h2>Estadísticas de Agentes</h2>
            <div class="premium-indicator">
                <div class="premium-dot"></div>
                DATOS PREMIUM — ANÁLISIS COMPLETO
            </div>
        </div>
        <div class="controls">
            <button class="btn-refresh" id="btnRefresh">
                <span id="refreshIcon">↻</span> ACTUALIZAR
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-container">
        <div class="filter-group">
            <label>Equipo:</label>
            <select id="filtroEquipo" class="filter-select">
                <option value="">Todos los equipos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Rol:</label>
            <select id="filtroRol" class="filter-select">
                <option value="">Todos los roles</option>
                <option value="Duelist">Duelist</option>
                <option value="Initiator">Initiator</option>
                <option value="Controller">Controller</option>
                <option value="Sentinel">Sentinel</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Ordenar por:</label>
            <select id="ordenarPor" class="filter-select">
                <option value="nickname">Nickname</option>
                <option value="kills">Kills (Total)</option>
                <option value="deaths">Deaths (Total)</option>
                <option value="assists">Assists (Total)</option>
                <option value="aces">Aces (Total)</option>
                <option value="clutches">Clutches (Total)</option>
                <option value="media_punto" selected>Media Puntos Fantasy</option>
                <option value="precio_mercado">Precio de Mercado</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Orden:</label>
            <select id="ordenDireccion" class="filter-select">
                <option value="desc" selected>Descendente</option>
                <option value="asc">Ascendente</option>
            </select>
        </div>
        <button class="btn-reset-filters" id="btnResetFiltros">Limpiar Filtros</button>
    </div>

    <!-- Tabla de estadísticas -->
    <div class="stats-container">
        <div class="stats-table-wrapper">
            <table class="stats-table" id="statsTable">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="nickname">Jugador</th>
                        <th class="sortable" data-sort="equipo">Equipo</th>
                        <th class="sortable" data-sort="rol">Rol</th>
                        <th class="sortable" data-sort="kills">Kills</th>
                        <th class="sortable" data-sort="deaths">Deaths</th>
                        <th class="sortable" data-sort="assists">Assists</th>
                        <th class="sortable" data-sort="aces">Aces</th>
                        <th class="sortable" data-sort="clutches">Clutches</th>
                        <th class="sortable" data-sort="media_punto">Media Pts</th>
                        <th class="sortable" data-sort="precio_mercado">Precio</th>
                    </tr>
                </thead>
                <tbody id="statsTableBody">
                    <tr>
                        <td colspan="10" class="loading-cell">
                            <div class="loading-spinner"></div>
                            <span>Cargando estadísticas...</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Resumen de estadísticas -->
    <div class="stats-summary" id="statsSummary">
        <div class="summary-card">
            <div class="summary-label">Total Jugadores</div>
            <div class="summary-value" id="totalJugadores">-</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Kills Totales</div>
            <div class="summary-value" id="totalKills">-</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Aces Totales</div>
            <div class="summary-value" id="totalAces">-</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Clutches Totales</div>
            <div class="summary-value" id="totalClutches">-</div>
        </div>
    </div>
<?php endif; ?>

<div id="notificacion"></div>

<!-- Botón volver arriba -->
<button id="btnVolverArriba" class="btn-volver-arriba" title="Volver arriba">
    <span>↑</span>
</button>

<script>
    const ES_PREMIUM = <?= json_encode($es_premium) ?>;
    const USUARIO = <?= json_encode($nombre_usuario_actual) ?>;
</script>
<script src="js/theme-manager.js"></script>
<?php if ($es_premium): ?>
<script src="js/estadisticas.js"></script>
<?php endif; ?>
</body>
</html>
