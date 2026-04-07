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
$jugadores             = [];
$es_primer_acceso      = false;

if (!isset($_GET['id_liga']) || !ctype_digit($_GET['id_liga'])) {
    header("Location: cliente.php");
    exit();
}
$id_liga = (int) $_GET['id_liga'];

try {
    // 1. Obtener ID del usuario
    $queryUser = "SELECT id_usuario FROM usuarios WHERE nombre = :nombre";
    $stmtUser  = $conexion->prepare($queryUser);
    $stmtUser->execute([':nombre' => $nombre_usuario_actual]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) throw new Exception("Sesión inválida.");
    $id_usuario = $usuario['id_usuario'];

    // 2. Obtener info de la liga y el equipo fantasy
    $queryLiga = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo,
                         EF.id_equipo_fantasy, EF.nombre_equipo,
                         EF.presupuesto_disponible, EF.puntos_equipo
                  FROM ligas L
                  INNER JOIN participaciones P ON L.id_liga = P.id_liga
                  INNER JOIN equipos_fantasy EF ON P.id_usuario = EF.id_usuario AND P.id_liga = EF.id_liga
                  WHERE L.id_liga = :id_liga AND P.id_usuario = :id_usuario";

    $stmtLiga = $conexion->prepare($queryLiga);
    $stmtLiga->execute([':id_liga' => $id_liga, ':id_usuario' => $id_usuario]);
    $liga_info = $stmtLiga->fetch(PDO::FETCH_ASSOC);

    if (!$liga_info) throw new Exception("No tienes acceso a esta liga.");
    $id_equipo_fantasy = (int)$liga_info['id_equipo_fantasy'];

    // 3. Comprobar si ya tiene jugadores asignados
    $queryCheck = "SELECT COUNT(*) FROM alineaciones WHERE id_equipo_fantasy = :id_ef";
    $stmtCheck = $conexion->prepare($queryCheck);
    $stmtCheck->execute([':id_ef' => $id_equipo_fantasy]);
    
    if ($stmtCheck->fetchColumn() == 0) {
        // ── ESCENARIO: REPARTO TÁCTICO (PRIMER ACCESO) ──
        $es_primer_acceso = true;
        $conexion->beginTransaction();

        // Definición de la composición obligatoria
        $roles_requeridos = [
            'Duelista'  => 1,
            'Iniciador' => 1,
            'Centinela' => 2,
            'Smoker'    => 1
        ];

        $asignados = [];

        // Consulta para buscar jugadores libres por ROL en ESTA LIGA
        $queryRol = "SELECT id_jugador, nickname, rol, precio_mercado, media_punto 
                     FROM jugadores 
                     WHERE rol = :rol AND id_jugador NOT IN (
                         SELECT A.id_jugador 
                         FROM alineaciones A 
                         INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy 
                         WHERE EF.id_liga = :id_liga
                     ) 
                     ORDER BY RAND() 
                     LIMIT :limite";
        
        $stmtRol = $conexion->prepare($queryRol);

        foreach ($roles_requeridos as $rol => $cantidad) {
            $stmtRol->bindValue(':rol', $rol, PDO::PARAM_STR);
            $stmtRol->bindValue(':id_liga', $id_liga, PDO::PARAM_INT);
            $stmtRol->bindValue(':limite', $cantidad, PDO::PARAM_INT);
            $stmtRol->execute();
            
            $jugadores_rol = $stmtRol->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($jugadores_rol) < $cantidad) {
                $conexion->rollBack();
                throw new Exception("Mercado agotado: No quedan suficientes '$rol' libres en esta liga.");
            }
            $asignados = array_merge($asignados, $jugadores_rol);
        }

        // Insertar los 5 seleccionados en la tabla alineaciones
        $stmtIns = $conexion->prepare("INSERT INTO alineaciones (id_equipo_fantasy, id_jugador, jornada, titular) 
                                       VALUES (:id_ef, :id_j, 1, 1)");
        
        foreach ($asignados as $aj) {
            $stmtIns->execute([':id_ef' => $id_equipo_fantasy, ':id_j' => $aj['id_jugador']]);
        }

        $conexion->commit();
        $jugadores = $asignados;

    } else {
        // ── ESCENARIO: CARGAR EQUIPO EXISTENTE ──
        $queryMios = "SELECT J.nickname, J.rol, J.precio_mercado, J.media_punto 
                      FROM jugadores J
                      INNER JOIN alineaciones A ON J.id_jugador = A.id_jugador
                      WHERE A.id_equipo_fantasy = :id_ef
                      ORDER BY FIELD(J.rol, 'Duelista', 'Iniciador', 'Smoker', 'Centinela')";
        $stmtMios = $conexion->prepare($queryMios);
        $stmtMios->execute([':id_ef' => $id_equipo_fantasy]);
        $jugadores = $stmtMios->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack();
    $error_pagina = $e->getMessage();
}

// Iconos tácticos
function getIcon($rol) {
    $iconos = ['Duelista'=>'⚔', 'Iniciador'=>'🔍', 'Centinela'=>'🛡', 'Smoker'=>'🌀'];
    return $iconos[$rol] ?? '•';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VALTASY — <?php echo htmlspecialchars($liga_info['nombre_liga'] ?? 'Error'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Barlow+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d0d0f; color: #e8e8ee; font-family: 'Barlow Condensed'; padding: 40px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #A63247; padding-bottom: 20px; margin-bottom: 30px; }
        .liga-banner { background: #141418; border-top: 3px solid #1DF2DD; padding: 25px; margin-bottom: 25px; display: flex; justify-content: space-between; }
        .grid-agentes { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .card-agente { background: #1a1a1f; border-left: 4px solid #A63247; padding: 20px; transition: 0.3s; }
        .card-agente:hover { transform: translateY(-5px); border-left-color: #1DF2DD; }
        .rol-tag { font-family: 'Orbitron'; color: #6b6b7a; font-size: 0.7rem; letter-spacing: 1px; }
        .nickname { font-family: 'Orbitron'; color: #1DF2DD; font-size: 1.2rem; margin: 5px 0; }
        .stat { font-size: 0.9rem; color: #e8e8ee; }
        .btn-volver { color: #1DF2DD; text-decoration: none; font-weight: bold; }
        .alert { background: rgba(29, 242, 221, 0.1); border: 1px solid #1DF2DD; padding: 15px; margin-bottom: 20px; font-family: 'Orbitron'; font-size: 0.8rem; color: #1DF2DD; }
    </style>
</head>
<body>
    <?php if ($error_pagina): ?>
        <div style="text-align:center; padding-top: 100px;">
            <h2 style="color:#A63247; font-family:'Orbitron'">SISTEMA BLOQUEADO</h2>
            <p><?php echo $error_pagina; ?></p>
            <br><a href="cliente.php" class="btn-volver">VOLVER</a>
        </div>
    <?php else: ?>
        <div class="header">
            <h1 style="font-family:'Orbitron'"><?php echo htmlspecialchars($liga_info['nombre_liga']); ?></h1>
            <div style="text-align:right">
                <p>AGENTE: <span style="color:#1DF2DD"><?php echo htmlspecialchars($nombre_usuario_actual); ?></span></p>
                <a href="cliente.php" class="btn-volver">← PANEL PRINCIPAL</a>
            </div>
        </div>

        <div class="liga-banner">
            <div>
                <p style="color:#6b6b7a">ESCUADRÓN ASIGNADO</p>
                <h2 style="font-family:'Orbitron'"><?php echo htmlspecialchars($liga_info['nombre_equipo']); ?></h2>
            </div>
            <div style="text-align:right">
                <p>PRESUPUESTO: <span style="color:#1DF2DD"><?php echo number_format($liga_info['presupuesto_disponible'], 0, ',', '.'); ?> €</span></p>
                <p>PUNTOS TOTALES: <span style="color:#1DF2DD"><?php echo $liga_info['puntos_equipo']; ?></span></p>
            </div>
        </div>

        <?php if ($es_primer_acceso): ?>
            <div class="alert">> PROTOCOLO DE REPARTO TÁCTICO COMPLETADO: EQUIPO EQUILIBRADO ASIGNADO.</div>
        <?php endif; ?>

        <div class="grid-agentes">
            <?php foreach ($jugadores as $j): ?>
                <div class="card-agente">
                    <p class="rol-tag"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></p>
                    <p class="nickname"><?php echo htmlspecialchars($j['nickname']); ?></p>
                    <p class="stat">VALOR: <?php echo number_format($j['precio_mercado'], 0, ',', '.'); ?> €</p>
                    <p class="stat">MEDIA: <?php echo number_format($j['media_punto'], 1); ?> PTS</p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>