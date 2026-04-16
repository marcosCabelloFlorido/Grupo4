<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../sesion/login.php");
    exit();
}

require_once __DIR__ . '/../conexion.php';

$nombre_usuario_actual = $_SESSION['usuario'];
$error_pagina          = null;
$liga_info             = null;
$plantilla             = [];
$titulares             = [];
$reservas              = [];
$es_primer_acceso      = false;

if (!isset($_GET['id_liga']) || !ctype_digit($_GET['id_liga'])) {
    header("Location: cliente.php");
    exit();
}
$id_liga = (int) $_GET['id_liga'];

try {
    $queryUser = "SELECT id_usuario FROM usuarios WHERE nombre = :nombre";
    $stmtUser  = $conexion->prepare($queryUser);
    $stmtUser->execute([':nombre' => $nombre_usuario_actual]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) throw new Exception("Sesión inválida.");
    $id_usuario = $usuario['id_usuario'];

    $queryLiga = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo, L.codigo_acceso, L.max_participantes,
                         EF.id_equipo_fantasy, EF.nombre_equipo,
                         EF.presupuesto_disponible, EF.puntos_equipo,
                         (SELECT COUNT(*) FROM participaciones P2 WHERE P2.id_liga = L.id_liga) AS total_participantes
                  FROM ligas L
                  INNER JOIN participaciones P ON L.id_liga = P.id_liga
                  INNER JOIN equipos_fantasy EF ON P.id_usuario = EF.id_usuario AND P.id_liga = EF.id_liga
                  WHERE L.id_liga = :id_liga AND P.id_usuario = :id_usuario";

    $stmtLiga = $conexion->prepare($queryLiga);
    $stmtLiga->execute([':id_liga' => $id_liga, ':id_usuario' => $id_usuario]);
    $liga_info = $stmtLiga->fetch(PDO::FETCH_ASSOC);

    if (!$liga_info) throw new Exception("No tienes acceso a esta liga.");
    $id_equipo_fantasy = (int)$liga_info['id_equipo_fantasy'];

    $queryCheck = "SELECT COUNT(*) FROM alineaciones WHERE id_equipo_fantasy = :id_ef";
    $stmtCheck = $conexion->prepare($queryCheck);
    $stmtCheck->execute([':id_ef' => $id_equipo_fantasy]);
    
    if ($stmtCheck->fetchColumn() == 0) {
        $es_primer_acceso = true;
        $conexion->beginTransaction();

        $roles_requeridos = ['Duelista' => 1, 'Iniciador' => 1, 'Centinela' => 2, 'Smoker' => 1];
        $asignados = [];

        $queryRol = "SELECT id_jugador, nickname, rol, precio_mercado, media_punto 
                     FROM jugadores 
                     WHERE rol = :rol 
                     AND id_jugador NOT IN (
                         SELECT A.id_jugador FROM alineaciones A 
                         INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy 
                         WHERE EF.id_liga = :id_liga
                     ) 
                     AND id_jugador NOT IN (
                         SELECT id_jugador FROM mercado_liga WHERE id_liga = :id_liga
                     )
                     ORDER BY RAND() LIMIT :limite";
        
        $stmtRol = $conexion->prepare($queryRol);

        foreach ($roles_requeridos as $rol => $cantidad) {
            $stmtRol->bindValue(':rol', $rol, PDO::PARAM_STR);
            $stmtRol->bindValue(':id_liga', $id_liga, PDO::PARAM_INT);
            $stmtRol->bindValue(':limite', $cantidad, PDO::PARAM_INT);
            $stmtRol->execute();
            $jugadores_rol = $stmtRol->fetchAll(PDO::FETCH_ASSOC);
            if (count($jugadores_rol) < $cantidad) {
                $conexion->rollBack();
                throw new Exception("Mercado inicial agotado: No quedan suficientes '$rol' libres en esta liga para formar tu escuadrón.");
            }
            $asignados = array_merge($asignados, $jugadores_rol);
        }

        $stmtIns = $conexion->prepare("INSERT INTO alineaciones (id_equipo_fantasy, id_jugador, jornada, titular) VALUES (:id_ef, :id_j, 1, 1)");
        foreach ($asignados as $aj) {
            $stmtIns->execute([':id_ef' => $id_equipo_fantasy, ':id_j' => $aj['id_jugador']]);
        }
        $conexion->commit();
        foreach ($asignados as $aj) { $aj['titular'] = 1; $plantilla[] = $aj; }

    } else {
        $queryMios = "SELECT A.id_jugador, J.nickname, J.nombre_real, J.rol, J.precio_mercado, J.media_punto, A.titular,
                             EP.nombre_equipo_profesional, EP.region
                      FROM alineaciones A
                      INNER JOIN jugadores J ON A.id_jugador = J.id_jugador
                      LEFT JOIN equipos_profesionales EP ON J.id_equipo_profesional = EP.id_equipo_profesional
                      WHERE A.id_equipo_fantasy = :id_ef
                      ORDER BY A.titular DESC, FIELD(J.rol, 'Duelista', 'Iniciador', 'Smoker', 'Centinela')";
        $stmtMios = $conexion->prepare($queryMios);
        $stmtMios->execute([':id_ef' => $id_equipo_fantasy]);
        $plantilla = $stmtMios->fetchAll(PDO::FETCH_ASSOC);
    }

    $titulares = array_filter($plantilla, function($j) { return $j['titular'] == 1; });
    $reservas  = array_filter($plantilla, function($j) { return $j['titular'] == 0; });

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack();
    $error_pagina = $e->getMessage();
}

function getIcon($rol) {
    $iconos = ['Duelista'=>'⚔', 'Iniciador'=>'🔍', 'Centinela'=>'🛡', 'Smoker'=>'🌀'];
    return $iconos[$rol] ?? '•';
}
function getColor($rol) {
    $colores = ['Duelista'=>'#ff4d6d', 'Iniciador'=>'#4dffb8', 'Centinela'=>'#4d9fff', 'Smoker'=>'#c084fc'];
    return $colores[$rol] ?? '#6b6b7a';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VALTASY — <?php echo htmlspecialchars($liga_info['nombre_liga'] ?? 'Error'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cyan: #1DF2DD; --cyan-dark: #168C77;
            --red: #8C0813; --red-soft: #A63247;
            --bg: #0d0d0f; --bg-card: #141418;
            --border: rgba(29,242,221,0.12);
            --text: #e8e8ee; --muted: #6b6b7a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'Barlow Condensed', sans-serif;
            min-height: 100vh; padding: 0;
            background-image:
                radial-gradient(ellipse 60% 50% at 50% 0%, rgba(140,8,19,0.1) 0%, transparent 60%),
                linear-gradient(rgba(29,242,221,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(29,242,221,0.02) 1px, transparent 1px);
            background-size: cover, 60px 60px, 60px 60px;
        }

        /* ── TOPBAR ── */
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 36px; border-bottom: 1px solid rgba(255,255,255,0.06);
            background: rgba(13,13,15,0.9); backdrop-filter: blur(8px);
            position: sticky; top: 0; z-index: 100;
        }
        .topbar-left { display: flex; align-items: center; gap: 20px; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--muted); text-decoration: none; font-size: 0.78rem;
            font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase;
            transition: color 0.2s;
        }
        .btn-back:hover { color: var(--cyan); }
        .topbar-title { font-family: 'Orbitron'; font-size: 0.85rem; color: var(--text); letter-spacing: 0.1em; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .agente-tag { font-size: 0.8rem; color: var(--muted); }
        .agente-tag span { color: var(--cyan); font-weight: 700; }
        .btn-mercado-top {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 20px; background: linear-gradient(135deg, var(--cyan-dark), var(--cyan));
            color: #000; font-weight: 700; font-size: 0.82rem; letter-spacing: 0.1em;
            text-transform: uppercase; text-decoration: none;
            clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
            transition: box-shadow 0.2s;
        }
        .btn-mercado-top:hover { box-shadow: 0 4px 20px rgba(29,242,221,0.4); }

        /* ── LIGA HERO ── */
        .liga-hero {
            padding: 32px 36px 0;
        }
        .liga-hero-top {
            display: flex; justify-content: space-between; align-items: flex-start;
            flex-wrap: wrap; gap: 20px; margin-bottom: 28px;
        }
        .liga-nombre { font-family: 'Orbitron'; font-size: 1.8rem; font-weight: 900; letter-spacing: 0.06em; line-height: 1; }
        .liga-meta { display: flex; align-items: center; gap: 12px; margin-top: 10px; flex-wrap: wrap; }
        .badge {
            display: inline-block; font-size: 0.68rem; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase; padding: 3px 10px;
        }
        .badge-privada { background: rgba(140,8,19,0.3); color: var(--red-soft); border: 1px solid var(--red-soft); }
        .badge-publica { background: rgba(29,242,221,0.1); color: var(--cyan); border: 1px solid var(--cyan); }
        .liga-codigo-box {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(140,8,19,0.12); border: 1px solid rgba(166,50,71,0.4);
            padding: 5px 12px; cursor: pointer; transition: background 0.2s; user-select: all;
        }
        .liga-codigo-box:hover { background: rgba(140,8,19,0.25); }
        .liga-codigo-label { font-size: 0.68rem; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; }
        .liga-codigo-val { font-family: 'Orbitron'; font-size: 0.85rem; color: var(--red-soft); letter-spacing: 0.2em; }
        .copiado-inline { font-size: 0.72rem; color: var(--cyan); min-width: 60px; }

        /* Stats row */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1px; background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.06); margin-bottom: 28px;
        }
        .stat-box {
            background: var(--bg-card); padding: 18px 20px; text-align: center;
        }
        .stat-label { font-size: 0.68rem; letter-spacing: 0.12em; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; }
        .stat-val { font-family: 'Orbitron'; font-size: 1.3rem; color: var(--cyan); }
        .stat-val.red { color: var(--red-soft); }
        .stat-val.white { color: var(--text); }

        /* ── TABS ── */
        .tabs-bar {
            display: flex; border-bottom: 2px solid rgba(255,255,255,0.06);
            padding: 0 36px; gap: 0;
        }
        .tab-btn {
            padding: 14px 24px; font-size: 0.82rem; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted);
            border: none; background: none; cursor: pointer;
            border-bottom: 2px solid transparent; margin-bottom: -2px;
            transition: color 0.2s, border-color 0.2s;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.activo { color: var(--cyan); border-bottom-color: var(--cyan); }

        /* ── TAB CONTENT ── */
        .tab-content { display: none; padding: 32px 36px; }
        .tab-content.activo { display: block; }

        /* ── ALERTA PRIMER ACCESO ── */
        .alert-recluta {
            background: rgba(29,242,221,0.06); border: 1px solid rgba(29,242,221,0.3);
            border-left: 4px solid var(--cyan); padding: 14px 18px;
            font-size: 0.9rem; color: var(--cyan); margin-bottom: 28px;
            font-weight: 600; letter-spacing: 0.04em;
        }

        /* ── GRID JUGADORES ── */
        .seccion-titulo {
            font-family: 'Orbitron'; font-size: 0.8rem; letter-spacing: 0.15em;
            color: var(--muted); text-transform: uppercase; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .seccion-titulo::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.06); }
        .seccion-titulo .count { color: var(--cyan); }

        .grid-jugadores { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px; margin-bottom: 36px; }

        .card-jugador {
            background: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.06);
            border-top: 3px solid var(--border-color, #333);
            padding: 18px; position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card-jugador:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.4); }
        .card-jugador.titular { --border-color: var(--cyan); }
        .card-jugador.reserva { --border-color: var(--red-soft); }

        .jugador-rol { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 6px; }
        .jugador-nick { font-family: 'Orbitron'; font-size: 1rem; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .jugador-real { font-size: 0.8rem; color: var(--muted); margin-bottom: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .jugador-equipo-pro { font-size: 0.78rem; color: var(--muted); margin-bottom: 12px; }
        .jugador-stats { display: flex; justify-content: space-between; margin-bottom: 14px; }
        .jugador-stat { text-align: center; }
        .jugador-stat-label { font-size: 0.62rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
        .jugador-stat-val { font-family: 'Orbitron'; font-size: 0.85rem; color: var(--text); }

        .btn-accion {
            width: 100%; padding: 8px; background: transparent; cursor: pointer;
            font-family: 'Barlow Condensed'; font-size: 0.82rem; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase; transition: background 0.2s, color 0.2s;
            margin-top: 6px;
        }
        .btn-mover-reservas { border: 1px solid rgba(29,242,221,0.4); color: var(--cyan); }
        .btn-mover-reservas:hover { background: rgba(29,242,221,0.08); }
        .btn-mover-titular { border: 1px solid rgba(166,50,71,0.4); color: var(--red-soft); }
        .btn-mover-titular:hover { background: rgba(140,8,19,0.12); }
        .btn-vender { border: 1px solid rgba(255,255,255,0.08); color: var(--muted); }
        .btn-vender:hover { border-color: var(--red-soft); color: #ff6b80; background: rgba(140,8,19,0.08); }

        .sin-reservas { grid-column: 1 / -1; text-align: center; padding: 30px; color: var(--muted); font-size: 0.9rem; border: 1px dashed rgba(255,255,255,0.06); }

        /* ── TABLA CLASIFICACIÓN ── */
        .tabla-wrap { overflow-x: auto; }
        .tabla-clasificacion { width: 100%; border-collapse: collapse; }
        .tabla-clasificacion th {
            font-size: 0.68rem; letter-spacing: 0.12em; color: var(--muted);
            text-transform: uppercase; padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-family: 'Orbitron'; text-align: left; white-space: nowrap;
        }
        .tabla-clasificacion td {
            padding: 14px 16px; font-size: 0.9rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            vertical-align: middle;
        }
        .tabla-clasificacion tr:hover td { background: rgba(29,242,221,0.02); }
        .tabla-clasificacion tr.fila-yo td { background: rgba(29,242,221,0.04); }
        .tabla-clasificacion tr.fila-yo td:first-child { border-left: 3px solid var(--cyan); padding-left: 13px; }

        .rank { font-family: 'Orbitron'; font-size: 0.95rem; }
        .rank.r1 { color: #FFD700; } .rank.r2 { color: #C0C0C0; } .rank.r3 { color: #CD7F32; }
        .rank.rn { color: var(--muted); }

        .part-nombre { font-family: 'Orbitron'; font-size: 0.8rem; }
        .part-escuadron { font-size: 0.82rem; color: var(--muted); margin-top: 3px; }
        .part-puntos { font-family: 'Orbitron'; font-size: 1.05rem; color: var(--cyan); }
        .part-presupuesto { color: var(--text); }
        .part-plantilla { color: var(--muted); font-size: 0.88rem; }
        .yo-badge { font-size: 0.65rem; color: var(--cyan); border: 1px solid var(--cyan); padding: 1px 5px; margin-left: 6px; vertical-align: middle; }

        .cargando { text-align: center; padding: 40px; color: var(--muted); }

        /* ── NOTIFICACIÓN ── */
        #notif {
            position: fixed; top: 20px; right: 24px; padding: 14px 22px;
            font-weight: 700; font-size: 0.9rem; letter-spacing: 0.06em;
            display: none; z-index: 999;
            clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
        }
        #notif.ok { background: rgba(22,140,119,0.95); color: var(--cyan); border-left: 3px solid var(--cyan); }
        #notif.err { background: rgba(140,8,19,0.95); color: #ff6b80; border-left: 3px solid var(--red-soft); }

        /* ── ERROR PAGE ── */
        .error-page { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; gap: 16px; }
        .error-page h2 { font-family: 'Orbitron'; color: var(--red-soft); font-size: 1.2rem; }

        @media (max-width: 600px) {
            .topbar { padding: 12px 16px; }
            .liga-hero, .tab-content { padding-left: 16px; padding-right: 16px; }
            .tabs-bar { padding: 0 16px; overflow-x: auto; }
            .tab-btn { padding: 12px 16px; font-size: 0.75rem; }
            .liga-nombre { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<div id="notif"></div>

<?php if ($error_pagina): ?>
    <div class="error-page">
        <h2>SISTEMA BLOQUEADO</h2>
        <p style="color:var(--muted)"><?php echo htmlspecialchars($error_pagina); ?></p>
        <a href="cliente.php" style="color:var(--cyan); text-decoration:none; font-weight:700;">← VOLVER AL DASHBOARD</a>
    </div>
<?php else: ?>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <a href="cliente.php" class="btn-back">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Dashboard
        </a>
        <span class="topbar-title"><?php echo htmlspecialchars($liga_info['nombre_liga']); ?></span>
    </div>
    <div class="topbar-right">
        <span class="agente-tag">AGENTE: <span><?php echo htmlspecialchars($nombre_usuario_actual); ?></span></span>
        <a href="mercado.php?id_liga=<?php echo $id_liga; ?>" class="btn-mercado-top">🛒 MERCADO</a>
    </div>
</div>

<!-- LIGA HERO -->
<div class="liga-hero">
    <div class="liga-hero-top">
        <div>
            <h1 class="liga-nombre"><?php echo htmlspecialchars($liga_info['nombre_liga']); ?></h1>
            <div class="liga-meta">
                <span class="badge <?php echo $liga_info['tipo'] === 'Privada' ? 'badge-privada' : 'badge-publica'; ?>">
                    <?php echo $liga_info['tipo'] === 'Privada' ? '🔒 CLASIFICADA' : '🌐 NO CLASIFICADA'; ?>
                </span>
                <?php if ($liga_info['tipo'] === 'Privada' && !empty($liga_info['codigo_acceso'])): ?>
                <div class="liga-codigo-box" onclick="copiarCodigo()" title="Clic para copiar">
                    <div>
                        <div class="liga-codigo-label">Código de acceso</div>
                        <div class="liga-codigo-val" id="codigoValor"><?php echo htmlspecialchars($liga_info['codigo_acceso']); ?></div>
                    </div>
                    <span class="copiado-inline" id="copiadoMsg"></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- STATS ROW -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">Mi Escuadrón</div>
            <div class="stat-val white" style="font-size:1rem; font-family:'Orbitron'"><?php echo htmlspecialchars($liga_info['nombre_equipo']); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Presupuesto</div>
            <div class="stat-val"><?php echo number_format($liga_info['presupuesto_disponible'], 0, ',', '.'); ?> €</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Mis Puntos</div>
            <div class="stat-val red"><?php echo $liga_info['puntos_equipo']; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Participantes</div>
            <div class="stat-val white"><?php echo $liga_info['total_participantes']; ?> / <?php echo $liga_info['max_participantes']; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Titulares</div>
            <div class="stat-val"><?php echo count($titulares); ?> / 5</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Reservas</div>
            <div class="stat-val white"><?php echo count($reservas); ?></div>
        </div>
    </div>
</div>

<!-- TABS -->
<div class="tabs-bar">
    <button class="tab-btn activo" data-tab="plantilla">⚔ Mi Plantilla</button>
    <button class="tab-btn" data-tab="clasificacion">🏆 Clasificación</button>
</div>

<!-- TAB: PLANTILLA -->
<div class="tab-content activo" id="tab-plantilla">
    <?php if ($es_primer_acceso): ?>
    <div class="alert-recluta">⚡ PROTOCOLO DE REPARTO TÁCTICO COMPLETADO — Equipo inicial asignado automáticamente.</div>
    <?php endif; ?>

    <div class="seccion-titulo">
        Titulares <span class="count"><?php echo count($titulares); ?>/5</span>
    </div>
    <div class="grid-jugadores">
        <?php foreach ($titulares as $j):
            $color = getColor($j['rol']); ?>
        <div class="card-jugador titular">
            <div class="jugador-rol" style="color:<?php echo $color; ?>"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></div>
            <div class="jugador-nick" style="color:<?php echo $color; ?>"><?php echo htmlspecialchars($j['nickname']); ?></div>
            <?php if (!empty($j['nombre_real'])): ?>
            <div class="jugador-real"><?php echo htmlspecialchars($j['nombre_real']); ?></div>
            <?php endif; ?>
            <?php if (!empty($j['nombre_equipo_profesional'])): ?>
            <div class="jugador-equipo-pro">🏢 <?php echo htmlspecialchars($j['nombre_equipo_profesional']); ?><?php echo !empty($j['region']) ? ' · '.$j['region'] : ''; ?></div>
            <?php endif; ?>
            <div class="jugador-stats">
                <div class="jugador-stat">
                    <div class="jugador-stat-label">Valor</div>
                    <div class="jugador-stat-val"><?php echo number_format($j['precio_mercado']/1000,0)?>K</div>
                </div>
                <div class="jugador-stat">
                    <div class="jugador-stat-label">Media</div>
                    <div class="jugador-stat-val"><?php echo number_format($j['media_punto'],1); ?></div>
                </div>
            </div>
            <button class="btn-accion btn-mover-reservas" onclick="cambiarEstado(<?php echo $j['id_jugador']; ?>, 0, <?php echo count($titulares); ?>)">↓ Mover a Reservas</button>
            <button class="btn-accion btn-vender" onclick="venderJugador(<?php echo $j['id_jugador']; ?>, '<?php echo htmlspecialchars($j['nickname'], ENT_QUOTES); ?>')">Vender · 80% valor</button>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="seccion-titulo">
        Reservas <span class="count"><?php echo count($reservas); ?></span>
    </div>
    <div class="grid-jugadores">
        <?php if (empty($reservas)): ?>
        <div class="sin-reservas">Sin reservas. Participa en el mercado de pujas para ampliar tu plantilla.</div>
        <?php endif; ?>
        <?php foreach ($reservas as $j):
            $color = getColor($j['rol']); ?>
        <div class="card-jugador reserva">
            <div class="jugador-rol" style="color:<?php echo $color; ?>"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></div>
            <div class="jugador-nick" style="color:var(--text)"><?php echo htmlspecialchars($j['nickname']); ?></div>
            <?php if (!empty($j['nombre_real'])): ?>
            <div class="jugador-real"><?php echo htmlspecialchars($j['nombre_real']); ?></div>
            <?php endif; ?>
            <?php if (!empty($j['nombre_equipo_profesional'])): ?>
            <div class="jugador-equipo-pro">🏢 <?php echo htmlspecialchars($j['nombre_equipo_profesional']); ?><?php echo !empty($j['region']) ? ' · '.$j['region'] : ''; ?></div>
            <?php endif; ?>
            <div class="jugador-stats">
                <div class="jugador-stat">
                    <div class="jugador-stat-label">Valor</div>
                    <div class="jugador-stat-val"><?php echo number_format($j['precio_mercado']/1000,0)?>K</div>
                </div>
                <div class="jugador-stat">
                    <div class="jugador-stat-label">Media</div>
                    <div class="jugador-stat-val"><?php echo number_format($j['media_punto'],1); ?></div>
                </div>
            </div>
            <button class="btn-accion btn-mover-titular" onclick="cambiarEstado(<?php echo $j['id_jugador']; ?>, 1, <?php echo count($titulares); ?>)">↑ Subir a Titular</button>
            <button class="btn-accion btn-vender" onclick="venderJugador(<?php echo $j['id_jugador']; ?>, '<?php echo htmlspecialchars($j['nickname'], ENT_QUOTES); ?>')">Vender · 80% valor</button>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- TAB: CLASIFICACIÓN -->
<div class="tab-content" id="tab-clasificacion">
    <div id="contenedor-clasificacion" class="cargando">Cargando clasificación...</div>
</div>

<script>
    const USUARIO_ACTUAL    = "<?php echo htmlspecialchars($nombre_usuario_actual, ENT_QUOTES); ?>";
    const ID_EQUIPO_FANTASY = <?php echo $id_equipo_fantasy; ?>;
    const ID_LIGA           = <?php echo $id_liga; ?>;

    // ── Tabs ──────────────────────────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('activo'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('activo'));
            btn.classList.add('activo');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('activo');
            if (btn.dataset.tab === 'clasificacion') cargarClasificacion();
        });
    });

    // ── Notificación ──────────────────────────────────────────────────────
    function notif(msg, tipo = 'ok', ms = 3000) {
        const el = document.getElementById('notif');
        el.textContent = msg; el.className = tipo; el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, ms);
    }

    // ── Copiar código ─────────────────────────────────────────────────────
    async function copiarCodigo() {
        const codigo = document.getElementById('codigoValor').textContent;
        const msg    = document.getElementById('copiadoMsg');
        try {
            await navigator.clipboard.writeText(codigo);
            msg.textContent = '✓ Copiado';
            setTimeout(() => { msg.textContent = ''; }, 2000);
        } catch (_) {}
    }

    // ── Clasificación ─────────────────────────────────────────────────────
    async function cargarClasificacion() {
        const contenedor = document.getElementById('contenedor-clasificacion');
        contenedor.innerHTML = '<div class="cargando">Cargando clasificación...</div>';
        try {
            const res  = await fetch(`api_participantes.php?id_liga=${ID_LIGA}`);
            const json = await res.json();
            if (json.status !== 'success' || !json.data.length) {
                contenedor.innerHTML = '<div class="cargando" style="color:var(--muted)">Sin participantes registrados.</div>';
                return;
            }
            const fmt = n => new Intl.NumberFormat('es-ES').format(n);
            const rankCls = i => ['r1','r2','r3'][i] ?? 'rn';
            const medal   = i => ['🥇','🥈','🥉'][i] ?? (i+1);

            let html = `<div class="tabla-wrap"><table class="tabla-clasificacion">
                <thead><tr>
                    <th>#</th>
                    <th>Agente / Escuadrón</th>
                    <th>Puntos</th>
                    <th>Presupuesto</th>
                    <th>Plantilla</th>
                </tr></thead><tbody>`;

            json.data.forEach((p, i) => {
                const esYo = p.usuario === USUARIO_ACTUAL;
                html += `<tr class="${esYo ? 'fila-yo' : ''}">
                    <td><span class="rank ${rankCls(i)}">${medal(i)}</span></td>
                    <td>
                        <div class="part-nombre">${esc(p.usuario)}${esYo ? '<span class="yo-badge">TÚ</span>' : ''}</div>
                        <div class="part-escuadron">${esc(p.nombre_equipo)}</div>
                    </td>
                    <td><span class="part-puntos">${p.puntos_equipo}</span></td>
                    <td><span class="part-presupuesto">${fmt(p.presupuesto_disponible)} €</span></td>
                    <td><span class="part-plantilla">${p.jugadores_en_plantilla} jugadores</span></td>
                </tr>`;
            });

            html += '</tbody></table></div>';
            contenedor.innerHTML = html;
        } catch (_) {
            contenedor.innerHTML = '<div class="cargando" style="color:var(--red-soft)">Error al cargar la clasificación.</div>';
        }
    }

    // ── Cambiar titular / reserva ─────────────────────────────────────────
    async function cambiarEstado(idJugador, nuevoEstado, numTitulares) {
        if (nuevoEstado === 1 && numTitulares >= 5) {
            notif('El equipo titular está lleno (5/5). Baja a alguien antes.', 'err', 4000);
            return;
        }
        try {
            const res  = await fetch('api_plantilla.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id_equipo: ID_EQUIPO_FANTASY, id_jugador: idJugador, titular: nuevoEstado })
            });
            const json = await res.json();
            if (json.status === 'success') { location.reload(); }
            else notif('Error: ' + json.message, 'err', 4000);
        } catch (_) { notif('Error de conexión', 'err'); }
    }

    // ── Vender jugador ────────────────────────────────────────────────────
    async function venderJugador(idJugador, nombre) {
        if (!confirm(`¿Vender a ${nombre}?\n\nRecuperarás el 80% de su valor de mercado. Esta acción no se puede deshacer.`)) return;
        try {
            const res  = await fetch('api_venta.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id_equipo: ID_EQUIPO_FANTASY, id_jugador: idJugador })
            });
            const json = await res.json();
            if (json.status === 'success') { location.reload(); }
            else notif('Error: ' + json.message, 'err', 4000);
        } catch (_) { notif('Error de conexión', 'err'); }
    }

    function esc(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
</script>

<?php endif; ?>
</body>
</html>