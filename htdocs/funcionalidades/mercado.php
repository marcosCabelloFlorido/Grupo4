<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../sesion/login.php");
    exit();
}

require_once __DIR__ . '/../conexion.php';

$nombre_usuario_actual = $_SESSION['usuario'];
$mensaje = null;
$mercado_list = [];
$fecha_fin_js = null;

// Mostrar mensaje si venimos de una redirección (Solución al Resubmission y a los errores)
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
    // 1. Datos del usuario y equipo
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

    // ────────────────────────────────────────────────────────────────────────
    // LÓGICA DE PUJA (POST) CON MANEJO DE ERRORES INDEPENDIENTE
    // ────────────────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_mercado'], $_POST['monto_puja'])) {
        try {
            $id_mercado_puja = (int)$_POST['id_mercado'];
            $monto_puja = (float)$_POST['monto_puja'];
            
            $conexion->beginTransaction();
            
            $stmtCheckM = $conexion->prepare("SELECT J.precio_mercado FROM mercado_liga M INNER JOIN jugadores J ON M.id_jugador = J.id_jugador WHERE M.id_mercado = :id_m");
            $stmtCheckM->execute([':id_m' => $id_mercado_puja]);
            $jugador_info = $stmtCheckM->fetch(PDO::FETCH_ASSOC);

            $stmtCheckPuja = $conexion->prepare("SELECT id_puja FROM pujas WHERE id_mercado = :id_m AND id_equipo_fantasy = :id_ef");
            $stmtCheckPuja->execute([':id_m' => $id_mercado_puja, ':id_ef' => $id_equipo]);
            
            if (!$jugador_info) {
                throw new Exception("El mercado ha cambiado. Inténtalo de nuevo.");
            } elseif ($stmtCheckPuja->fetch()) {
                throw new Exception("Ya has realizado una puja por este agente. Espera a la resolución.");
            } elseif ($monto_puja < $jugador_info['precio_mercado']) {
                throw new Exception("La puja debe ser igual o superior al valor de mercado.");
            } elseif ($datos_equipo['presupuesto_disponible'] < $monto_puja) {
                // AQUÍ ES DONDE SALTA EL ERROR DE DINERO INSUFICIENTE
                throw new Exception("Presupuesto insuficiente para realizar esa puja.");
            }

            $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible - :monto WHERE id_equipo_fantasy = :id_ef")->execute([':monto' => $monto_puja, ':id_ef' => $id_equipo]);
            $conexion->prepare("INSERT INTO pujas (id_mercado, id_equipo_fantasy, monto) VALUES (:id_m, :id_ef, :monto)")->execute([':id_m' => $id_mercado_puja, ':id_ef' => $id_equipo, ':monto' => $monto_puja]);

            $conexion->commit();
            $_SESSION['mensaje_mercado'] = ["tipo" => "exito", "texto" => "Puja registrada. El dinero ha sido congelado hasta la resolución."];
        } catch (Exception $ex) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            // Guardamos el error en rojo en la sesión para mostrarlo al recargar
            $_SESSION['mensaje_mercado'] = ["tipo" => "error", "texto" => $ex->getMessage()];
        }
        
        // Redirigimos siempre para limpiar el formulario y pintar todo correctamente
        header("Location: mercado.php?id_liga=" . $id_liga);
        exit();
    }

    // ────────────────────────────────────────────────────────────────────────
    // MOTOR DE RESOLUCIÓN DE PUJAS (Lazy Evaluation)
    // ────────────────────────────────────────────────────────────────────────
    $stmtExp = $conexion->prepare("SELECT id_mercado, id_jugador FROM mercado_liga WHERE id_liga = :id_liga AND fecha_expiracion <= NOW()");
    $stmtExp->execute([':id_liga' => $id_liga]);
    $expirados = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    if (count($expirados) > 0) {
        $conexion->beginTransaction();
        
        foreach ($expirados as $exp) {
            $stmtBids = $conexion->prepare("SELECT id_equipo_fantasy, monto FROM pujas WHERE id_mercado = :id_m ORDER BY monto DESC, fecha_puja ASC");
            $stmtBids->execute([':id_m' => $exp['id_mercado']]);
            $bids = $stmtBids->fetchAll(PDO::FETCH_ASSOC);

            if (count($bids) > 0) {
                $ganador = $bids[0]; 
                
                $stmtIns = $conexion->prepare("INSERT INTO alineaciones (id_equipo_fantasy, id_jugador, jornada, titular) VALUES (:id_ef, :id_j, 1, 0)");
                $stmtIns->execute([':id_ef' => $ganador['id_equipo_fantasy'], ':id_j' => $exp['id_jugador']]);

                for ($i = 1; $i < count($bids); $i++) {
                    $perdedor = $bids[$i];
                    $stmtRefund = $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible + :monto WHERE id_equipo_fantasy = :id_ef");
                    $stmtRefund->execute([':monto' => $perdedor['monto'], ':id_ef' => $perdedor['id_equipo_fantasy']]);
                }
            }
        }

        $conexion->prepare("DELETE FROM mercado_liga WHERE id_liga = :id_liga")->execute([':id_liga' => $id_liga]);

        $queryLibres = "SELECT id_jugador FROM jugadores WHERE id_jugador NOT IN (SELECT A.id_jugador FROM alineaciones A INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga) ORDER BY RAND() LIMIT 5";
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
    } else {
        $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM mercado_liga WHERE id_liga = :id_liga");
        $stmtCount->execute([':id_liga' => $id_liga]);
        if ($stmtCount->fetchColumn() == 0) {
            $conexion->beginTransaction();
            $queryLibres = "SELECT id_jugador FROM jugadores WHERE id_jugador NOT IN (SELECT A.id_jugador FROM alineaciones A INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga) ORDER BY RAND() LIMIT 5";
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
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // OBTENER DATOS PARA PINTAR LA WEB
    // ────────────────────────────────────────────────────────────────────────
    $queryPintar = "SELECT M.id_mercado, J.nickname, J.rol, J.precio_mercado, J.media_punto, M.fecha_expiracion, 
                    (SELECT monto FROM pujas P WHERE P.id_mercado = M.id_mercado AND P.id_equipo_fantasy = :id_ef) as mi_puja
                    FROM mercado_liga M 
                    INNER JOIN jugadores J ON M.id_jugador = J.id_jugador 
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
    <title>Mercado — VALTASY</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Barlow+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cyan: #1DF2DD; --cyan-dark: #168C77;
            --red: #8C0813; --red-soft: #A63247;
            --bg: #0d0d0f; --bg-card: #141418;
            --text: #e8e8ee; --muted: #6b6b7a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'Barlow Condensed', sans-serif;
            min-height: 100vh;
            background-image:
                radial-gradient(ellipse 50% 50% at 50% 50%, rgba(140,8,19,0.10) 0%, transparent 70%),
                linear-gradient(rgba(29,242,221,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(29,242,221,0.02) 1px, transparent 1px);
            background-size: cover, 60px 60px, 60px 60px;
        }

        /* ── TOPBAR ── */
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 36px; border-bottom: 1px solid rgba(255,255,255,0.06);
            background: rgba(13,13,15,0.92); backdrop-filter: blur(8px);
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
        .topbar-title { font-family: 'Orbitron'; font-size: 0.82rem; color: var(--text); letter-spacing: 0.08em; }
        .topbar-right { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
        .budget-display {
            display: flex; flex-direction: column; align-items: flex-end;
        }
        .budget-label { font-size: 0.65rem; letter-spacing: 0.12em; color: var(--muted); text-transform: uppercase; }
        .budget-val { font-family: 'Orbitron'; font-size: 1.1rem; color: var(--cyan); font-weight: 700; }
        .btn-plantilla {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 20px; background: transparent;
            border: 1px solid rgba(29,242,221,0.4);
            color: var(--cyan); font-weight: 700; font-size: 0.82rem; letter-spacing: 0.1em;
            text-transform: uppercase; text-decoration: none;
            clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-plantilla:hover { background: rgba(29,242,221,0.08); box-shadow: 0 4px 20px rgba(29,242,221,0.2); }

        /* ── MAIN ── */
        .main { padding: 32px 36px; }

        /* ── ALERT ── */
        .alert {
            padding: 14px 20px; margin-bottom: 28px;
            font-weight: 700; font-size: 0.95rem; letter-spacing: 0.04em;
            border-left: 4px solid;
        }
        .alert.exito { background: rgba(29,242,221,0.08); border-color: var(--cyan); color: var(--cyan); }
        .alert.error { background: rgba(140,8,19,0.15); border-color: var(--red-soft); color: #ff6b80; }

        /* ── TIMER ── */
        .timer-bar {
            display: flex; align-items: center; justify-content: center; gap: 14px;
            background: var(--bg-card); border: 1px solid rgba(29,242,221,0.15);
            border-left: 4px solid var(--cyan);
            padding: 16px 24px; margin-bottom: 30px;
        }
        .timer-label { font-size: 0.75rem; letter-spacing: 0.12em; color: var(--muted); text-transform: uppercase; }
        .timer-val { font-family: 'Orbitron'; font-size: 1.3rem; color: var(--cyan); min-width: 90px; }
        .timer-hint { font-size: 0.78rem; color: var(--muted); }

        /* ── GRID ── */
        .seccion-titulo {
            font-family: 'Orbitron'; font-size: 0.75rem; letter-spacing: 0.15em;
            color: var(--muted); text-transform: uppercase; margin-bottom: 18px;
            display: flex; align-items: center; gap: 10px;
        }
        .seccion-titulo::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.06); }
        .seccion-titulo .cnt { color: var(--cyan); }

        .grid-agentes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        /* ── CARD AGENTE ── */
        .card-agente {
            background: var(--bg-card);
            border: 1px solid rgba(29,242,221,0.12);
            border-top: 3px solid rgba(29,242,221,0.25);
            padding: 22px 20px 18px;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }
        .card-agente:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.35); border-color: rgba(29,242,221,0.3); }
        .card-agente.pujado { border-top-color: var(--red-soft); border-color: rgba(166,50,71,0.35); background: rgba(140,8,19,0.04); }
        .card-agente.pujado:hover { border-color: rgba(166,50,71,0.55); }

        .tag-pujado {
            position: absolute; top: -1px; right: 16px;
            background: var(--red-soft); color: #fff;
            padding: 3px 10px; font-size: 0.68rem; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase;
        }

        .rol-tag { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 6px; }
        .jugador-nick { font-family: 'Orbitron'; font-size: 1rem; color: var(--text); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .jugador-media { font-size: 0.8rem; color: var(--muted); margin-bottom: 14px; }

        .precio-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px; }
        .precio-label { font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
        .precio-val { font-family: 'Orbitron'; font-size: 1.25rem; color: var(--cyan); font-weight: 700; }
        .media-mini { text-align: right; }
        .media-mini .precio-label { display: block; }
        .media-mini .precio-val { font-size: 1rem; color: var(--text); }

        .divider { height: 1px; background: rgba(255,255,255,0.06); margin-bottom: 16px; }

        .input-puja {
            width: 100%; padding: 11px 12px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--cyan); font-family: 'Orbitron'; font-size: 1rem;
            text-align: center; margin-bottom: 10px;
            transition: border-color 0.2s;
        }
        .input-puja:focus { outline: none; border-color: rgba(29,242,221,0.5); background: rgba(29,242,221,0.04); }
        .input-hint { font-size: 0.72rem; color: var(--muted); text-align: center; margin-bottom: 12px; letter-spacing: 0.04em; }

        .btn-comprar {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, var(--cyan-dark), var(--cyan));
            color: #000; font-weight: 700; font-family: 'Barlow Condensed';
            border: none; cursor: pointer; text-transform: uppercase;
            font-size: 1rem; letter-spacing: 0.08em;
            clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
            transition: box-shadow 0.2s;
        }
        .btn-comprar:hover { box-shadow: 0 4px 20px rgba(29,242,221,0.4); }

        .mi-oferta-box { text-align: center; margin-bottom: 4px; }
        .mi-oferta-label { font-size: 0.72rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
        .mi-oferta-val { font-family: 'Orbitron'; font-size: 1.1rem; color: #ff6b80; font-weight: 700; }

        .btn-cancelar {
            width: 100%; padding: 11px; margin-top: 10px;
            background: transparent; border: 1px solid rgba(166,50,71,0.5);
            color: var(--red-soft); font-weight: 700; font-family: 'Barlow Condensed';
            cursor: pointer; text-transform: uppercase; font-size: 0.95rem;
            letter-spacing: 0.08em; transition: background 0.2s, color 0.2s;
            clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%);
        }
        .btn-cancelar:hover { background: rgba(140,8,19,0.25); color: #ff6b80; }

        /* ── SIN MERCADO ── */
        .empty-state {
            text-align: center; padding: 80px 20px; color: var(--muted);
        }
        .empty-state .icon { font-size: 3rem; margin-bottom: 16px; opacity: 0.4; }
        .empty-state p { font-size: 1rem; letter-spacing: 0.06em; }

        @media (max-width: 600px) {
            .topbar { padding: 12px 16px; flex-wrap: wrap; gap: 10px; }
            .main { padding: 20px 16px; }
            .grid-agentes { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <a href="cliente.php" class="btn-back">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                Dashboard
            </a>
            <span class="topbar-title">🛒 MERCADO DE PUJAS — <?php echo htmlspecialchars($datos_equipo['nombre_liga'] ?? ''); ?></span>
        </div>
        <div class="topbar-right">
            <div class="budget-display">
                <span class="budget-label">Fondos disponibles</span>
                <span class="budget-val"><?php echo number_format($datos_equipo['presupuesto_disponible'] ?? 0, 0, ',', '.'); ?> €</span>
            </div>
            <a href="ver_liga.php?id_liga=<?php echo $id_liga; ?>" class="btn-plantilla">⚔ Mi Plantilla</a>
        </div>
    </div>

    <div class="main">
        <?php if ($mensaje): ?>
            <div class="alert <?php echo $mensaje['tipo']; ?>"><?php echo htmlspecialchars($mensaje['texto']); ?></div>
        <?php endif; ?>

        <?php if (!empty($mercado_list)): ?>
            <div class="timer-bar">
                <span class="timer-label">⏳ Resolución de pujas en:</span>
                <span class="timer-val" id="countdown">--:--:--</span>
                <span class="timer-hint">· La puja más alta de cada agente gana</span>
            </div>

            <div class="seccion-titulo">
                Agentes disponibles <span class="cnt"><?php echo count($mercado_list); ?></span>
            </div>

            <div class="grid-agentes">
                <?php foreach ($mercado_list as $j): ?>
                <div class="card-agente <?php echo $j['mi_puja'] ? 'pujado' : ''; ?>">
                    <?php if($j['mi_puja']): ?><div class="tag-pujado">✓ Oferta enviada</div><?php endif; ?>

                    <div class="rol-tag" style="color:<?php echo getColor($j['rol']); ?>"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></div>
                    <div class="jugador-nick"><?php echo htmlspecialchars($j['nickname']); ?></div>
                    <div class="jugador-media">Media: <?php echo number_format($j['media_punto'], 1); ?> pts/jornada</div>

                    <div class="precio-row">
                        <div>
                            <div class="precio-label">Valor de mercado</div>
                            <div class="precio-val"><?php echo number_format($j['precio_mercado'], 0, ',', '.'); ?> €</div>
                        </div>
                        <div class="media-mini">
                            <span class="precio-label">Puja mín.</span>
                            <div class="precio-val" style="font-size:0.9rem; color:var(--muted);"><?php echo number_format($j['precio_mercado'], 0, ',', '.'); ?> €</div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <?php if(!$j['mi_puja']): ?>
                        <form method="POST">
                            <input type="hidden" name="id_mercado" value="<?php echo $j['id_mercado']; ?>">
                            <input type="number" name="monto_puja" class="input-puja"
                                   min="<?php echo $j['precio_mercado']; ?>"
                                   value="<?php echo $j['precio_mercado']; ?>" required>
                            <div class="input-hint">Puja ciega — otros no ven tu oferta</div>
                            <button type="submit" class="btn-comprar">Lanzar Oferta</button>
                        </form>
                    <?php else: ?>
                        <div class="mi-oferta-box">
                            <div class="mi-oferta-label">Tu oferta actual</div>
                            <div class="mi-oferta-val"><?php echo number_format($j['mi_puja'], 0, ',', '.'); ?> €</div>
                        </div>
                        <button class="btn-cancelar" onclick="cancelarPuja(<?php echo $j['id_mercado']; ?>)">↩ Retirar Oferta</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <script>
                async function cancelarPuja(idMercado) {
                    if (!confirm('¿Retirar tu puja?\n\nRecuperarás el dinero inmediatamente.')) return;
                    try {
                        const res = await fetch('api_cancelar_puja.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ id_equipo: <?php echo $id_equipo; ?>, id_mercado: idMercado })
                        });
                        const json = await res.json();
                        if (json.status === 'success') location.reload();
                        else alert("Error: " + json.message);
                    } catch(e) { alert("Error de conexión."); }
                }

                const fechaFin = new Date("<?php echo $fecha_fin_js; ?>").getTime();
                const x = setInterval(function() {
                    const dist = fechaFin - new Date().getTime();
                    if (dist < 0) {
                        clearInterval(x);
                        document.getElementById("countdown").innerHTML = "RESOLVIENDO...";
                        setTimeout(() => location.reload(), 2500);
                        return;
                    }
                    const h = Math.floor((dist % (1000*60*60*24)) / (1000*60*60));
                    const m = Math.floor((dist % (1000*60*60)) / (1000*60));
                    const s = Math.floor((dist % (1000*60)) / 1000);
                    document.getElementById("countdown").innerHTML =
                        String(h).padStart(2,'0') + ":" + String(m).padStart(2,'0') + ":" + String(s).padStart(2,'0');
                }, 1000);
            </script>

        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🔍</div>
                <p>No hay agentes en el mercado en este momento.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>