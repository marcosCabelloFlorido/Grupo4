<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../sesion/login.php");
    exit();
}

require_once __DIR__ . '/../conexion.php';

$nombre_usuario_actual = $_SESSION['usuario'];
$mensaje     = null;
$mercado_list = [];
$fecha_fin_js = null;

if (isset($_SESSION['mensaje_mercado'])) {
    $mensaje = $_SESSION['mensaje_mercado'];
    unset($_SESSION['mensaje_mercado']);
}

if (!isset($_GET['id_liga']) || !ctype_digit($_GET['id_liga'])) {
    header("Location: cliente.php");
    exit();
}
$id_liga = (int) $_GET['id_liga'];

try {
    $queryUser = "SELECT EF.id_equipo_fantasy, EF.presupuesto_disponible, L.nombre AS nombre_liga
                  FROM usuarios U
                  INNER JOIN participaciones P ON U.id_usuario = P.id_usuario
                  INNER JOIN equipos_fantasy EF ON P.id_usuario = EF.id_usuario AND P.id_liga = EF.id_liga
                  INNER JOIN ligas L ON P.id_liga = L.id_liga
                  WHERE U.nombre = :nombre AND P.id_liga = :id_liga";
    $stmtUser = $conexion->prepare($queryUser);
    $stmtUser->execute([':nombre' => $nombre_usuario_actual, ':id_liga' => $id_liga]);
    $datos_equipo = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$datos_equipo) throw new Exception("No tienes acceso a esta liga.");
    $id_equipo = $datos_equipo['id_equipo_fantasy'];

    // ── Lógica de puja (POST) ────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_mercado'], $_POST['monto_puja'])) {
        try {
            $id_mercado_puja = (int)$_POST['id_mercado'];
            $monto_puja      = (float)$_POST['monto_puja'];

            $conexion->beginTransaction();

            $stmtCheckM = $conexion->prepare("SELECT J.precio_mercado FROM mercado_liga M INNER JOIN jugadores J ON M.id_jugador = J.id_jugador WHERE M.id_mercado = :id_m");
            $stmtCheckM->execute([':id_m' => $id_mercado_puja]);
            $jugador_info = $stmtCheckM->fetch(PDO::FETCH_ASSOC);

            $stmtCheckPuja = $conexion->prepare("SELECT id_puja FROM pujas WHERE id_mercado = :id_m AND id_equipo_fantasy = :id_ef");
            $stmtCheckPuja->execute([':id_m' => $id_mercado_puja, ':id_ef' => $id_equipo]);

            if (!$jugador_info) {
                throw new Exception("El mercado ha cambiado. Inténtalo de nuevo.");
            } elseif ($stmtCheckPuja->fetch()) {
                throw new Exception("Ya has realizado una puja por este agente.");
            } elseif ($monto_puja < $jugador_info['precio_mercado']) {
                throw new Exception("La puja debe ser igual o superior al valor de mercado.");
            } elseif ($datos_equipo['presupuesto_disponible'] < $monto_puja) {
                throw new Exception("Presupuesto insuficiente para realizar esa oferta.");
            }

            $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible - :monto WHERE id_equipo_fantasy = :id_ef")->execute([':monto' => $monto_puja, ':id_ef' => $id_equipo]);
            $conexion->prepare("INSERT INTO pujas (id_mercado, id_equipo_fantasy, monto) VALUES (:id_m, :id_ef, :monto)")->execute([':id_m' => $id_mercado_puja, ':id_ef' => $id_equipo, ':monto' => $monto_puja]);

            $conexion->commit();
            $_SESSION['mensaje_mercado'] = ["tipo" => "exito", "texto" => "Puja registrada con éxito. Dinero congelado hasta la resolución."];
        } catch (Exception $ex) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $_SESSION['mensaje_mercado'] = ["tipo" => "error", "texto" => $ex->getMessage()];
        }
        header("Location: mercado.php?id_liga=" . $id_liga);
        exit();
    }

    // ── Motor de resolución y renovación (cada 24h) ──────────
    $stmtExp = $conexion->prepare("SELECT id_mercado, id_jugador FROM mercado_liga WHERE id_liga = :id_liga AND fecha_expiracion <= NOW()");
    $stmtExp->execute([':id_liga' => $id_liga]);
    $expirados = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM mercado_liga WHERE id_liga = :id_liga");
    $stmtCount->execute([':id_liga' => $id_liga]);
    $total_mercado = $stmtCount->fetchColumn();

    if (count($expirados) > 0 || $total_mercado == 0) {
        $conexion->beginTransaction();

        if (count($expirados) > 0) {
            foreach ($expirados as $exp) {
                $stmtBids = $conexion->prepare("SELECT id_equipo_fantasy, monto FROM pujas WHERE id_mercado = :id_m ORDER BY monto DESC, fecha_puja ASC");
                $stmtBids->execute([':id_m' => $exp['id_mercado']]);
                $bids = $stmtBids->fetchAll(PDO::FETCH_ASSOC);

                if (count($bids) > 0) {
                    $ganador = $bids[0];
                    $conexion->prepare("INSERT INTO alineaciones (id_equipo_fantasy, id_jugador, jornada, titular, puntos_jornada) VALUES (:id_ef, :id_j, 1, 0, 0)")->execute([':id_ef' => $ganador['id_equipo_fantasy'], ':id_j' => $exp['id_jugador']]);
                    for ($i = 1; $i < count($bids); $i++) {
                        $perdedor = $bids[$i];
                        $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible + :monto WHERE id_equipo_fantasy = :id_ef")->execute([':monto' => $perdedor['monto'], ':id_ef' => $perdedor['id_equipo_fantasy']]);
                    }
                }
            }
            $conexion->prepare("DELETE FROM mercado_liga WHERE id_liga = :id_liga")->execute([':id_liga' => $id_liga]);
        }

        $queryLibres = "SELECT J.id_jugador
                        FROM jugadores J
                        INNER JOIN equipos_profesionales EP ON J.id_equipo_profesional = EP.id_equipo_profesional
                        WHERE EP.region = 'EMEA'
                        AND J.id_jugador NOT IN (SELECT A.id_jugador FROM alineaciones A INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga)
                        ORDER BY RAND() LIMIT 5";
        $stmtLibres = $conexion->prepare($queryLibres);
        $stmtLibres->execute([':id_liga' => $id_liga]);
        $nuevos_mercado = $stmtLibres->fetchAll(PDO::FETCH_COLUMN);

        if (count($nuevos_mercado) > 0) {
            $stmtInsertMercado = $conexion->prepare("INSERT INTO mercado_liga (id_liga, id_jugador, fecha_expiracion) VALUES (:id_liga, :id_jugador, DATE_ADD(NOW(), INTERVAL 1 DAY))");
            foreach ($nuevos_mercado as $id_j) {
                $stmtInsertMercado->execute([':id_liga' => $id_liga, ':id_jugador' => $id_j]);
            }
        }

        $conexion->commit();
        $stmtUser->execute([':nombre' => $nombre_usuario_actual, ':id_liga' => $id_liga]);
        $datos_equipo = $stmtUser->fetch(PDO::FETCH_ASSOC);
    }

    // ── Datos para renderizar la web ─────────────────────────
    $queryPintar = "SELECT M.id_mercado, J.nickname, J.rol, J.precio_mercado, M.fecha_expiracion, EP.nombre_equipo_profesional,
                    (SELECT monto FROM pujas P WHERE P.id_mercado = M.id_mercado AND P.id_equipo_fantasy = :id_ef) as mi_puja
                    FROM mercado_liga M
                    INNER JOIN jugadores J ON M.id_jugador = J.id_jugador
                    LEFT JOIN equipos_profesionales EP ON J.id_equipo_profesional = EP.id_equipo_profesional
                    WHERE M.id_liga = :id_liga ORDER BY J.precio_mercado DESC";
    $stmtPintar = $conexion->prepare($queryPintar);
    $stmtPintar->execute([':id_liga' => $id_liga, ':id_ef' => $id_equipo]);
    $mercado_list = $stmtPintar->fetchAll(PDO::FETCH_ASSOC);

    $fecha_fin_js = !empty($mercado_list) ? $mercado_list[0]['fecha_expiracion'] : null;

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack();
    $mensaje = ["tipo" => "error", "texto" => $e->getMessage()];
}

function getIcon($rol) {
    $iconos = ['Duelista' => '⚔', 'Iniciador' => '🔍', 'Centinela' => '🛡', 'Smoker' => '🌀'];
    return $iconos[$rol] ?? '•';
}
function getColor($rol) {
    $colores = ['Duelista' => '#ff4d6d', 'Iniciador' => '#4dffb8', 'Centinela' => '#4d9fff', 'Smoker' => '#c084fc'];
    return $colores[$rol] ?? '#6b6b7a';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mercado de Fichajes — VALTASY</title>
    <script src="js/theme-init.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Barlow+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/global.css">
    <!-- El ?v=3.0 forzará a tu navegador a recargar el diseño de mercado -->
    <link rel="stylesheet" href="css/mercado.css?v=3.0">
</head>
<body>

    <div class="header">
        <div class="header-logo">
            <img src="../img/logovaltasy_rojo.png" alt="VALTASY" class="header-logo-img"><span class="header-logo-text">MERCADO <span>DE FICHAJES</span></span>
        </div>
        <div class="header-info">
            <div class="fondos-label">FONDOS DISPONIBLES</div>
            <div class="fondos"><?php echo number_format($datos_equipo['presupuesto_disponible'] ?? 0, 0, ',', '.'); ?> €</div>
            <a href="ver_liga.php?id_liga=<?php echo $id_liga; ?>" class="btn-volver">← Volver al Escuadrón</a>
        </div>
    </div>

    <nav class="nav-tabs">
        <a href="cliente.php"    class="nav-tab">Dashboard</a>
        <a href="noticias.php"   class="nav-tab">Noticias</a>
        <a href="estadisticas.php?id_liga=<?php echo $id_liga; ?>" class="nav-tab">Estadísticas</a>
    </nav>

    <?php if ($mensaje): ?>
        <div class="alert <?php echo $mensaje['tipo']; ?>"><?php echo htmlspecialchars($mensaje['texto']); ?></div>
    <?php endif; ?>

    <?php if (!empty($mercado_list)): ?>
        <div class="timer-container">
            <div class="timer-label">El mercado se renueva en</div>
            <div class="timer-val" id="countdown">--:--:--</div>
        </div>

        <div class="grid-agentes">
            <?php foreach ($mercado_list as $j): ?>
                <div class="card-agente <?php echo $j['mi_puja'] ? 'pujado' : ''; ?>" style="--border-color: <?php echo getColor($j['rol']); ?>">
                    <?php if ($j['mi_puja']): ?>
                        <div class="tag-pujado">Oferta Activa</div>
                    <?php endif; ?>
                    
                    <div class="agente-rol" style="color: <?php echo getColor($j['rol']); ?>">
                        <?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?>
                    </div>
                    <div class="agente-nick"><?php echo htmlspecialchars($j['nickname']); ?></div>
                    <div class="agente-equipo">🏢 <?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?></div>
                    
                    <div class="agente-valor-box">
                        <span class="agente-precio-label">Valor Base:</span>
                        <span class="agente-precio"><?php echo number_format($j['precio_mercado'], 0, ',', '.'); ?> €</span>
                    </div>

                    <?php if (!$j['mi_puja']): ?>
                        <form method="POST" class="form-puja">
                            <input type="hidden" name="id_mercado" value="<?php echo $j['id_mercado']; ?>">
                            <input type="number" name="monto_puja" class="input-puja" min="<?php echo $j['precio_mercado']; ?>" value="<?php echo $j['precio_mercado']; ?>" required>
                            <button type="submit" class="btn-comprar">Lanzar Oferta</button>
                        </form>
                    <?php else: ?>
                        <div class="puja-actual-box">
                            <span class="puja-actual-label">Tu oferta secreta:</span>
                            <span class="puja-actual-val"><?php echo number_format($j['mi_puja'], 0, ',', '.'); ?> €</span>
                        </div>
                        <button class="btn-cancelar" onclick="cancelarPuja(<?php echo $j['id_mercado']; ?>)">Retirar Oferta</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div style="text-align:center; padding:50px; background:#141418; border:1px solid rgba(255,255,255,0.1); border-radius: 8px;">
            <p style="color:var(--muted); font-size:1.2rem;">El mercado se está actualizando. Vuelve en unos instantes.</p>
        </div>
    <?php endif; ?>

    <script>
        const ID_EQUIPO = <?php echo $id_equipo; ?>;
        const FECHA_FIN = "<?php echo $fecha_fin_js; ?>";
    </script>
    <script src="js/theme-manager.js"></script>
    <script src="js/mercado.js"></script>
</body>
</html>