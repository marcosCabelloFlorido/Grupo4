<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: ../sesion/login.php"); exit(); }
require_once __DIR__ . '/../conexion.php';
$nombre_usuario_actual = $_SESSION['usuario'];
$error_pagina = null; $liga_info = null; $plantilla = []; $titulares = []; $reservas = []; $es_primer_acceso = false;

if (!isset($_GET['id_liga']) || !ctype_digit($_GET['id_liga'])) { header("Location: cliente.php"); exit(); }
$id_liga = (int) $_GET['id_liga'];

try {
    $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre");
    $stmtUser->execute([':nombre' => $nombre_usuario_actual]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) throw new Exception("Sesión inválida.");
    $id_usuario = $usuario['id_usuario'];

    $queryLiga = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo, L.torneo, L.codigo_acceso, EF.id_equipo_fantasy, EF.nombre_equipo, EF.presupuesto_disponible, EF.puntos_equipo FROM ligas L INNER JOIN participaciones P ON L.id_liga = P.id_liga INNER JOIN equipos_fantasy EF ON P.id_usuario = EF.id_usuario AND P.id_liga = EF.id_liga WHERE L.id_liga = :id_liga AND P.id_usuario = :id_usuario";
    $stmtLiga = $conexion->prepare($queryLiga); $stmtLiga->execute([':id_liga' => $id_liga, ':id_usuario' => $id_usuario]);
    $liga_info = $stmtLiga->fetch(PDO::FETCH_ASSOC);
    if (!$liga_info) throw new Exception("No tienes acceso a esta liga.");
    $id_equipo_fantasy = (int)$liga_info['id_equipo_fantasy'];

    $stmtJornada = $conexion->prepare("SELECT MAX(jornada) FROM alineaciones WHERE id_equipo_fantasy = :id_ef");
    $stmtJornada->execute([':id_ef' => $id_equipo_fantasy]);
    $jornada_actual = $stmtJornada->fetchColumn();
    $jornada_actual = $jornada_actual ? $jornada_actual : 1;

    $stmtCheck = $conexion->prepare("SELECT COUNT(*) FROM alineaciones WHERE id_equipo_fantasy = :id_ef");
    $stmtCheck->execute([':id_ef' => $id_equipo_fantasy]);

    if ($stmtCheck->fetchColumn() == 0) {
        $es_primer_acceso = true; $conexion->beginTransaction();
        $roles_requeridos = ['Duelista' => 1, 'Iniciador' => 1, 'Centinela' => 2, 'Smoker' => 1]; $asignados = [];
        $queryRol = "SELECT J.id_jugador, J.nickname, J.rol, J.precio_mercado, J.media_punto FROM jugadores J INNER JOIN equipos_profesionales EP ON J.id_equipo_profesional = EP.id_equipo_profesional WHERE J.rol = :rol AND EP.region = 'EMEA' AND J.id_jugador NOT IN (SELECT A.id_jugador FROM alineaciones A INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga) AND J.id_jugador NOT IN (SELECT id_jugador FROM mercado_liga WHERE id_liga = :id_liga) ORDER BY RAND() LIMIT :limite";
        $stmtRol = $conexion->prepare($queryRol);

        foreach ($roles_requeridos as $rol => $cantidad) {
            $stmtRol->bindValue(':rol', $rol, PDO::PARAM_STR);
            $stmtRol->bindValue(':id_liga', $id_liga, PDO::PARAM_INT);
            $stmtRol->bindValue(':limite', $cantidad, PDO::PARAM_INT);
            $stmtRol->execute();
            $jugadores_rol = $stmtRol->fetchAll(PDO::FETCH_ASSOC);
            if (count($jugadores_rol) < $cantidad) { $conexion->rollBack(); throw new Exception("Mercado inicial agotado para el rol '$rol' en Europa."); }
            $asignados = array_merge($asignados, $jugadores_rol);
        }

        $stmtIns = $conexion->prepare("INSERT INTO alineaciones (id_equipo_fantasy, id_jugador, jornada, titular, puntos_jornada) VALUES (:id_ef, :id_j, 1, 1, 0)");
        foreach ($asignados as $aj) { $stmtIns->execute([':id_ef' => $id_equipo_fantasy, ':id_j' => $aj['id_jugador']]); }
        $conexion->commit();

        foreach ($asignados as $aj) {
            $aj['titular'] = 1; $aj['puntos_jornada'] = 0; $aj['nombre_equipo_profesional'] = 'Libre';
            $aj['total_kills'] = 0; $aj['total_deaths'] = 0; $aj['total_assists'] = 0; $aj['total_aces'] = 0; $aj['total_clutches'] = 0;
            $plantilla[] = $aj;
        }
    } else {
        $queryMios = "SELECT A.id_jugador, J.nickname, J.rol, J.precio_mercado, A.titular, A.puntos_jornada, EP.nombre_equipo_profesional,
                      COALESCE(SUM(E.kills), 0) as total_kills, COALESCE(SUM(E.deaths), 0) as total_deaths,
                      COALESCE(SUM(E.assist), 0) as total_assists, COALESCE(SUM(E.ace), 0) as total_aces, COALESCE(SUM(E.clutch), 0) as total_clutches
                      FROM alineaciones A
                      INNER JOIN jugadores J ON A.id_jugador = J.id_jugador
                      LEFT JOIN equipos_profesionales EP ON J.id_equipo_profesional = EP.id_equipo_profesional
                      LEFT JOIN estadisticas E ON J.id_jugador = E.id_jugador
                      WHERE A.id_equipo_fantasy = :id_ef
                      GROUP BY A.id_jugador, J.nickname, J.rol, J.precio_mercado, A.titular, A.puntos_jornada, EP.nombre_equipo_profesional
                      ORDER BY A.titular DESC, FIELD(J.rol, 'Duelista', 'Iniciador', 'Smoker', 'Centinela')";
        $stmtMios = $conexion->prepare($queryMios); $stmtMios->execute([':id_ef' => $id_equipo_fantasy]);
        $plantilla = $stmtMios->fetchAll(PDO::FETCH_ASSOC);
    }

    $titulares = array_filter($plantilla, function($j) { return $j['titular'] == 1; });
    $reservas  = array_filter($plantilla, function($j) { return $j['titular'] == 0; });
    $puntos_ultima_jornada = array_sum(array_column($titulares, 'puntos_jornada'));

    $queryRankJornada = "SELECT EF.id_equipo_fantasy, EF.nombre_equipo, U.nombre AS manager, COALESCE(SUM(A.puntos_jornada), 0) AS puntos_jornada_total
                         FROM equipos_fantasy EF
                         INNER JOIN usuarios U ON EF.id_usuario = U.id_usuario
                         LEFT JOIN alineaciones A ON EF.id_equipo_fantasy = A.id_equipo_fantasy AND A.titular = 1
                         WHERE EF.id_liga = :id_liga
                         GROUP BY EF.id_equipo_fantasy, EF.nombre_equipo, U.nombre
                         ORDER BY puntos_jornada_total DESC";
    $stmtRankJ = $conexion->prepare($queryRankJornada); $stmtRankJ->execute([':id_liga' => $id_liga]);
    $clasificacion_jornada = $stmtRankJ->fetchAll(PDO::FETCH_ASSOC);

    $stmtRanking = $conexion->prepare("SELECT EF.id_equipo_fantasy, U.nombre AS manager, EF.nombre_equipo, EF.puntos_equipo FROM equipos_fantasy EF INNER JOIN usuarios U ON EF.id_usuario = U.id_usuario WHERE EF.id_liga = :id_liga ORDER BY EF.puntos_equipo DESC");
    $stmtRanking->execute([':id_liga' => $id_liga]);
    $clasificacion_global = $stmtRanking->fetchAll(PDO::FETCH_ASSOC);

    $stmtRosters = $conexion->prepare("SELECT A.id_equipo_fantasy, J.nickname, A.puntos_jornada, J.rol FROM alineaciones A INNER JOIN jugadores J ON A.id_jugador = J.id_jugador INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga AND A.titular = 1");
    $stmtRosters->execute([':id_liga' => $id_liga]); $rosters_raw = $stmtRosters->fetchAll(PDO::FETCH_ASSOC);
    $rosters = []; foreach ($rosters_raw as $r) { $rosters[$r['id_equipo_fantasy']][] = $r; }

} catch (Exception $e) { if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack(); $error_pagina = $e->getMessage(); }

function getIcon($rol) { $iconos = ['Duelista'=>'⚔', 'Iniciador'=>'🔍', 'Centinela'=>'🛡', 'Smoker'=>'🌀']; return $iconos[$rol] ?? '•'; }
function getColor($rol) { $colores = ['Duelista'=>'#ff4d6d', 'Iniciador'=>'#4dffb8', 'Centinela'=>'#4d9fff', 'Smoker'=>'#c084fc']; return $colores[$rol] ?? '#6b6b7a'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VALTASY — <?php echo htmlspecialchars($liga_info['nombre_liga'] ?? 'Error'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/ver_liga.css">
</head>
<body>
<div id="copiadoMsg">¡CÓDIGO COPIADO!</div>

<?php if ($error_pagina): ?>
    <div style="text-align:center; padding:100px; color:#A63247"><?php echo $error_pagina; ?></div>
<?php else: ?>

<!-- Modal de estadísticas del jugador -->
<div class="stats-overlay" id="playerStatsModal" onclick="closePlayerStats(event)">
    <div class="stats-panel" onclick="event.stopPropagation()">
        <div class="sp-close" onclick="closePlayerStats(event)">✕</div>
        <div class="sp-header">
            <div class="sp-rol"  id="spRol">ROL</div>
            <div class="sp-name" id="spName">NICKNAME</div>
            <div class="sp-team" id="spTeam">EQUIPO</div>
        </div>
        <div class="sp-grid">
            <div class="sp-data-box"><div class="sp-data-label">Kills Totales</div><div class="sp-data-val" id="spKills">0</div></div>
            <div class="sp-data-box"><div class="sp-data-label">Muertes Totales</div><div class="sp-data-val" id="spDeaths" style="color:var(--red-soft);">0</div></div>
            <div class="sp-data-box"><div class="sp-data-label">Asistencias</div><div class="sp-data-val" id="spAssists">0</div></div>
            <div class="sp-data-box"><div class="sp-data-label">Aces logrados</div><div class="sp-data-val" id="spAces" style="color:#FFD700;">0</div></div>
            <div class="sp-data-box" style="grid-column:span 2;"><div class="sp-data-label">Clutches ganados</div><div class="sp-data-val" id="spClutches" style="color:var(--purple);">0</div></div>
        </div>
    </div>
</div>

<div class="topbar">
    <div class="topbar-left">
        <a href="cliente.php" class="btn-back">Dashboard</a>
        <span class="topbar-title"><?php echo htmlspecialchars($liga_info['nombre_liga']); ?></span>
    </div>
    <div class="topbar-right">
        <span class="torneo-label">🏆 <?php echo htmlspecialchars($liga_info['torneo']); ?></span>
        <select id="jornadaSelect" class="select-jornada">
            <option value="1">Jornada 1</option>
            <option value="2">Jornada 2</option>
            <option value="3">Jornada 3</option>
            <option value="4">Jornada 4</option>
            <option value="5">Jornada 5</option>
        </select>
        <button class="btn-simular" onclick="simularPartido()" id="btnSim">▶ SIMULAR</button>
        <a href="mercado.php?id_liga=<?php echo $id_liga; ?>" class="btn-mercado-top">🛒 MERCADO</a>
    </div>
</div>

<nav class="nav-sub">
    <a href="cliente.php"    class="nav-tab">Dashboard</a>
    <a href="crear_liga.php" class="nav-tab">Nueva Liga</a>
    <a href="noticias.php"   class="nav-tab">Noticias</a>
</nav>

<div class="liga-hero">
    <h1 class="liga-nombre"><?php echo htmlspecialchars($liga_info['nombre_liga']); ?></h1>
    <?php if ($liga_info['tipo'] === 'Privada' && !empty($liga_info['codigo_acceso'])): ?>
    <div class="liga-codigo-container" onclick="copiarCodigo('<?php echo htmlspecialchars($liga_info['codigo_acceso'], ENT_QUOTES); ?>')" title="Haz clic para copiar">
        <span class="codigo-label">INVITAR AGENTES:</span>
        <span class="codigo-val"><?php echo htmlspecialchars($liga_info['codigo_acceso']); ?> 📋</span>
    </div>
    <?php endif; ?>
    <div class="stats-row">
        <div class="stat-box"><div class="stat-label">Mi Escuadrón</div><div class="stat-val" style="color:#fff"><?php echo htmlspecialchars($liga_info['nombre_equipo']); ?></div></div>
        <div class="stat-box"><div class="stat-label">Presupuesto</div><div class="stat-val"><?php echo number_format($liga_info['presupuesto_disponible'], 0, ',', '.'); ?> €</div></div>
        <div class="stat-box"><div class="stat-label">Mis Puntos (Jornada <?php echo $jornada_actual; ?>)</div><div class="stat-val" style="color:var(--purple)"><?php echo $puntos_ultima_jornada; ?> PTS</div></div>
    </div>
</div>

<div class="seccion-titulo" style="color:var(--purple); border-color:rgba(192,132,252,0.2);">Líderes de la Jornada <?php echo $jornada_actual; ?></div>
<div class="ranking-jornada-container">
    <?php $posJ = 1; foreach ($clasificacion_jornada as $cj): $claseTop = ($posJ == 1) ? 'top-1' : ''; ?>
    <div class="card-jornada <?php echo $claseTop; ?>">
        <span class="cj-pos">RANK #<?php echo $posJ; ?></span>
        <div class="cj-nombre"><?php echo htmlspecialchars($cj['nombre_equipo']); ?></div>
        <div class="cj-pts"><?php echo $cj['puntos_jornada_total']; ?> <span>PTS</span></div>
    </div>
    <?php $posJ++; endforeach; ?>
</div>

<div class="panel-total">
    <div>
        <div class="panel-total-titulo">Clasificación General</div>
        <div class="panel-total-sub">PUNTOS TOTALES DE LA COMPETICIÓN</div>
    </div>
    <div class="panel-total-puntos"><?php echo $liga_info['puntos_equipo']; ?> <span style="font-size:1.2rem; color:#6b6b7a;">PTS</span></div>
</div>

<div class="seccion-titulo">Mis Titulares (<?php echo count($titulares); ?>/5)</div>
<div class="grid-jugadores">
    <?php foreach ($titulares as $j): $colorRol = getColor($j['rol']); ?>
    <div class="card-jugador titular" style="--border-color:<?php echo $colorRol; ?>;" onclick="openPlayerStats('<?php echo htmlspecialchars($j['nickname']); ?>','<?php echo $j['rol']; ?>','<?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?>','<?php echo $colorRol; ?>',<?php echo $j['total_kills']; ?>,<?php echo $j['total_deaths']; ?>,<?php echo $j['total_assists']; ?>,<?php echo $j['total_aces']; ?>,<?php echo $j['total_clutches']; ?>)">
        <div class="jugador-rol" style="color:<?php echo $colorRol; ?>"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></div>
        <div class="jugador-nick"><?php echo htmlspecialchars($j['nickname']); ?></div>
        <div class="jugador-equipo-pro">🏢 <?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?></div>
        <div class="jugador-puntos-badge <?php echo ($j['puntos_jornada'] == 0) ? 'cero' : ''; ?>"><?php echo $j['puntos_jornada']; ?> PTS</div>
        <button class="btn-accion btn-mover" style="color:var(--red-soft);border-color:rgba(166,50,71,0.4);" onclick="event.stopPropagation(); cambiarEstado(<?php echo $j['id_jugador']; ?>,0,<?php echo count($titulares); ?>)">↓ Mover a Reservas</button>
        <button class="btn-accion btn-vender" onclick="event.stopPropagation(); venderJugador(<?php echo $j['id_jugador']; ?>,'<?php echo htmlspecialchars($j['nickname'], ENT_QUOTES); ?>')">Vender (-20%)</button>
    </div>
    <?php endforeach; ?>
</div>

<div class="seccion-titulo" style="color:var(--red-soft); border-color:rgba(166,50,71,0.2);">Banquillo (Reservas)</div>
<div class="grid-jugadores">
    <?php if (empty($reservas)): ?><p style="color:var(--muted); font-size:0.9rem; margin-left:5px;">Tu banquillo está vacío.</p><?php endif; ?>
    <?php foreach ($reservas as $j): $colorRol = getColor($j['rol']); ?>
    <div class="card-jugador reserva" style="--border-color:var(--red-soft);" onclick="openPlayerStats('<?php echo htmlspecialchars($j['nickname']); ?>','<?php echo $j['rol']; ?>','<?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?>','<?php echo $colorRol; ?>',<?php echo $j['total_kills']; ?>,<?php echo $j['total_deaths']; ?>,<?php echo $j['total_assists']; ?>,<?php echo $j['total_aces']; ?>,<?php echo $j['total_clutches']; ?>)">
        <div class="jugador-rol" style="color:<?php echo $colorRol; ?>"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></div>
        <div class="jugador-nick"><?php echo htmlspecialchars($j['nickname']); ?></div>
        <div class="jugador-equipo-pro">🏢 <?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?></div>
        <div class="jugador-puntos-badge cero">BANQUILLO</div>
        <button class="btn-accion btn-mover" onclick="event.stopPropagation(); cambiarEstado(<?php echo $j['id_jugador']; ?>,1,<?php echo count($titulares); ?>)">↑ Subir a Titular</button>
        <button class="btn-accion btn-vender" onclick="event.stopPropagation(); venderJugador(<?php echo $j['id_jugador']; ?>,'<?php echo htmlspecialchars($j['nickname'], ENT_QUOTES); ?>')">Vender (-20%)</button>
    </div>
    <?php endforeach; ?>
</div>

<div class="seccion-titulo" style="color:#FFD700; border-color:rgba(255,215,0,0.2); margin-top:50px;">Clasificación Global de la Liga</div>
<div class="ranking-container">
    <?php $posicion = 1; foreach ($clasificacion_global as $c):
        $clase_pos = ($posicion == 1) ? 'gold' : (($posicion == 2) ? 'silver' : (($posicion == 3) ? 'bronze' : ''));
        $is_me     = ($c['id_equipo_fantasy'] == $id_equipo_fantasy) ? 'is-me' : '';
    ?>
    <div class="rank-row <?php echo $is_me; ?>">
        <div class="rank-pos <?php echo $clase_pos; ?>">#<?php echo $posicion; ?></div>
        <div class="rank-info">
            <div class="rank-equipo"><?php echo htmlspecialchars($c['nombre_equipo']); ?> <?php if ($is_me) echo '<span style="font-size:0.7rem;background:var(--cyan);color:#000;padding:2px 5px;border-radius:3px;vertical-align:middle;margin-left:5px;">TÚ</span>'; ?></div>
            <div class="rank-manager">Mánager: <?php echo htmlspecialchars($c['manager']); ?></div>
            <div class="rank-roster">
                <?php if (isset($rosters[$c['id_equipo_fantasy']])) {
                    foreach ($rosters[$c['id_equipo_fantasy']] as $rj) { echo "<div class='roster-pill'>" . getIcon($rj['rol']) . " " . htmlspecialchars($rj['nickname']) . " <span style='color:#1DF2DD'>" . $rj['puntos_jornada'] . " pts</span></div>"; }
                } else echo "<div class='roster-pill' style='color:gray'>Sin jugadores titulares</div>"; ?>
            </div>
        </div>
        <div class="rank-pts"><?php echo $c['puntos_equipo']; ?></div>
    </div>
    <?php $posicion++; endforeach; ?>
</div>

<!-- Modal de simulación -->
<div class="modal-sim" id="modalSimulacion">
    <div class="sim-box">
        <div class="sim-title">⚡ REPORTE DE LA JORNADA</div>
        <div style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;margin-bottom:5px;text-align:center;">Resultados de los partidos</div>
        <div class="sim-matchup" id="simMatchup"></div>
        <div style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;margin-top:20px;margin-bottom:10px;text-align:center;">Top Jugadores de la Jornada</div>
        <div id="simPlayerList"></div>
        <button class="btn-accion btn-mover" style="margin-top:20px;width:100%;border:1px solid #c084fc;color:#c084fc;" onclick="location.reload()">CERRAR Y ACTUALIZAR TABLA</button>
    </div>
</div>

<script>
    // Variables de PHP necesarias para el JS externo
    const ID_EQUIPO_FANTASY = <?php echo $id_equipo_fantasy; ?>;
    const TORNEO_LIGA       = "<?php echo addslashes($liga_info['torneo'] ?? 'VCT EMEA - Fase Regular'); ?>";
</script>
<script src="js/ver_liga.js"></script>

<?php endif; ?>
</body>
</html>
