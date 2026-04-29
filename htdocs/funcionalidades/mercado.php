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

// Mostrar mensaje si venimos de una redirección (Evita reenvío de formularios)
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
    // LÓGICA DE PUJA (POST)
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
                throw new Exception("Ya has realizado una puja por este agente.");
            } elseif ($monto_puja < $jugador_info['precio_mercado']) {
                throw new Exception("La puja debe ser igual o superior al valor de mercado.");
            } elseif ($datos_equipo['presupuesto_disponible'] < $monto_puja) {
                throw new Exception("Presupuesto insuficiente para realizar esa oferta.");
            }

            // Congelar dinero y registrar puja
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

    // ────────────────────────────────────────────────────────────────────────
    // MOTOR DE RESOLUCIÓN DE PUJAS Y RENOVACIÓN (CADA 24H)
    // ────────────────────────────────────────────────────────────────────────
    $stmtExp = $conexion->prepare("SELECT id_mercado, id_jugador FROM mercado_liga WHERE id_liga = :id_liga AND fecha_expiracion <= NOW()");
    $stmtExp->execute([':id_liga' => $id_liga]);
    $expirados = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM mercado_liga WHERE id_liga = :id_liga");
    $stmtCount->execute([':id_liga' => $id_liga]);
    $total_mercado = $stmtCount->fetchColumn();

    // Si hay jugadores caducados o el mercado está vacío (primera vez)
    if (count($expirados) > 0 || $total_mercado == 0) {
        $conexion->beginTransaction();
        
        // 1. Resolver pujas de los jugadores expirados
        if (count($expirados) > 0) {
            foreach ($expirados as $exp) {
                $stmtBids = $conexion->prepare("SELECT id_equipo_fantasy, monto FROM pujas WHERE id_mercado = :id_m ORDER BY monto DESC, fecha_puja ASC");
                $stmtBids->execute([':id_m' => $exp['id_mercado']]);
                $bids = $stmtBids->fetchAll(PDO::FETCH_ASSOC);

                if (count($bids) > 0) {
                    $ganador = $bids[0]; 
                    // Asignar al ganador (al banquillo directamente)
                    $conexion->prepare("INSERT INTO alineaciones (id_equipo_fantasy, id_jugador, jornada, titular, puntos_jornada) VALUES (:id_ef, :id_j, 1, 0, 0)")->execute([':id_ef' => $ganador['id_equipo_fantasy'], ':id_j' => $exp['id_jugador']]);

                    // Devolver dinero a los perdedores
                    for ($i = 1; $i < count($bids); $i++) {
                        $perdedor = $bids[$i];
                        $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible + :monto WHERE id_equipo_fantasy = :id_ef")->execute([':monto' => $perdedor['monto'], ':id_ef' => $perdedor['id_equipo_fantasy']]);
                    }
                }
            }
            // Borrar el mercado viejo
            $conexion->prepare("DELETE FROM mercado_liga WHERE id_liga = :id_liga")->execute([':id_liga' => $id_liga]);
        }

        // 2. Generar 5 NUEVOS JUGADORES (SOLO EUROPA - EMEA)
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
            // AÑADIDO: INTERVAL 1 DAY (24 Horas)
            $stmtInsertMercado = $conexion->prepare("INSERT INTO mercado_liga (id_liga, id_jugador, fecha_expiracion) VALUES (:id_liga, :id_jugador, DATE_ADD(NOW(), INTERVAL 1 DAY))");
            foreach ($nuevos_mercado as $id_j) {
                $stmtInsertMercado->execute([':id_liga' => $id_liga, ':id_jugador' => $id_j]);
            }
        }
        
        $conexion->commit();
        // Recargar datos por si el usuario actual recibió dinero de una puja perdida
        $stmtUser->execute([':nombre' => $nombre_usuario_actual, ':id_liga' => $id_liga]);
        $datos_equipo = $stmtUser->fetch(PDO::FETCH_ASSOC);
    }

    // ────────────────────────────────────────────────────────────────────────
    // OBTENER DATOS PARA PINTAR LA WEB
    // ────────────────────────────────────────────────────────────────────────
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
    <title>Mercado de Fichajes — VALTASY</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Barlow+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --cyan: #1DF2DD; --red-soft: #A63247; --bg: #0d0d0f; --bg-card: #141418; --text: #e8e8ee; --muted: #6b6b7a; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Barlow Condensed', sans-serif; padding: 40px; min-height: 100vh; background-image: radial-gradient(ellipse 50% 50% at 50% 50%, rgba(29,242,221,0.05) 0%, transparent 70%); }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--cyan); padding-bottom: 20px; margin-bottom: 16px; }
        .nav-tabs { display: flex; gap: 4px; margin-bottom: 28px; border-bottom: 1px solid rgba(29, 242, 221, 0.12); }
        .nav-tab { font-family: 'Barlow Condensed', sans-serif; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #6b6b7a; text-decoration: none; padding: 10px 22px; border: 1px solid transparent; border-bottom: none; clip-path: polygon(6px 0%, 100% 0%, calc(100% - 6px) 100%, 0% 100%); transition: color 0.2s, background 0.2s; position: relative; top: 1px; }
        .nav-tab:hover { color: #1DF2DD; }
        .nav-tab.activo { color: #1DF2DD; background: #141418; border-color: rgba(29, 242, 221, 0.25); border-bottom-color: #141418; }
        .header h1 { font-family: 'Orbitron'; font-size: 2rem; color: var(--cyan); }
        .header-info { text-align: right; }
        .fondos { color: var(--cyan); font-size: 1.4rem; font-weight: bold; font-family: 'Orbitron'; }
        .btn-volver { color: var(--muted); text-decoration: none; font-weight: bold; font-size: 0.9rem; text-transform: uppercase; transition: 0.2s; display: inline-block; margin-top: 5px;}
        .btn-volver:hover { color: #fff; }

        .alert { padding: 15px; margin-bottom: 25px; font-weight: bold; border-left: 4px solid; }
        .alert.exito { background: rgba(29,242,221,0.1); border-color: var(--cyan); color: var(--cyan); }
        .alert.error { background: rgba(140,8,19,0.1); border-color: var(--red-soft); color: #ff6b80; }

        .timer-container { text-align: center; background: var(--bg-card); padding: 15px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; }
        .timer-label { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 5px; }
        .timer-val { font-family: 'Orbitron'; font-size: 1.8rem; color: var(--cyan); }

        .grid-agentes { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 25px; }
        .card-agente { background: var(--bg-card); border: 1px solid rgba(255,255,255,0.05); padding: 25px 20px; text-align: center; position: relative; transition: transform 0.2s; border-top: 4px solid var(--border-color); }
        .card-agente:hover { transform: translateY(-5px); }
        .card-agente.pujado { background: rgba(140,8,19,0.05); border-color: rgba(166,50,71,0.3); border-top-color: var(--red-soft); }
        
        .tag-pujado { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--red-soft); color: #fff; padding: 4px 15px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        
        .agente-rol { font-size: 0.75rem; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .agente-nick { font-family: 'Orbitron'; font-size: 1.4rem; color: #fff; margin: 10px 0 5px; }
        .agente-equipo { font-size: 0.85rem; color: var(--muted); margin-bottom: 15px; }
        
        .agente-precio-label { font-size: 0.8rem; color: var(--muted); }
        .agente-precio { font-size: 1.5rem; font-weight: bold; color: var(--cyan); font-family: 'Orbitron'; margin-bottom: 20px; }

        .input-puja { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid var(--cyan); color: var(--cyan); font-family: 'Orbitron'; font-size: 1.1rem; text-align: center; margin-bottom: 12px; outline: none; transition: 0.2s;}
        .input-puja:focus { background: rgba(29,242,221,0.1); }
        
        .btn-comprar { width: 100%; padding: 14px; background: var(--cyan); color: #000; font-weight: bold; font-family: 'Barlow Condensed'; font-size: 1.1rem; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s; clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%); }
        .btn-comprar:hover { background: var(--cyan-dark); color: #fff; }
        
        .btn-cancelar { width: 100%; padding: 12px; background: transparent; border: 1px solid var(--red-soft); color: var(--red-soft); font-weight: bold; font-family: 'Barlow Condensed'; font-size: 1rem; cursor: pointer; text-transform: uppercase; margin-top: 15px; transition: 0.2s; }
        .btn-cancelar:hover { background: rgba(140,8,19,0.2); color: #ff6b80; }
        
        .puja-actual-label { font-size: 0.9rem; color: var(--muted); margin-top: 10px; }
        .puja-actual-val { font-family: 'Orbitron'; font-size: 1.3rem; color: var(--red-soft); font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MERCADO DE FICHAJES</h1>
        <div class="header-info">
            <div style="font-size:0.8rem; color:var(--muted); letter-spacing:1px;">FONDOS DISPONIBLES</div>
            <div class="fondos"><?php echo number_format($datos_equipo['presupuesto_disponible'] ?? 0, 0, ',', '.'); ?> €</div>
            <a href="ver_liga.php?id_liga=<?php echo $id_liga; ?>" class="btn-volver">← Volver al Escuadrón</a>
        </div>
    </div>

    <nav class="nav-tabs">
        <a href="cliente.php"    class="nav-tab">Dashboard</a>
        <a href="crear_liga.php" class="nav-tab">Nueva Liga</a>
        <a href="noticias.php"   class="nav-tab">Noticias</a>
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
                    <?php if($j['mi_puja']): ?>
                        <div class="tag-pujado">Oferta Activa</div>
                    <?php endif; ?>
                    
                    <div class="agente-rol" style="color: <?php echo getColor($j['rol']); ?>">
                        <?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?>
                    </div>
                    <div class="agente-nick"><?php echo htmlspecialchars($j['nickname']); ?></div>
                    <div class="agente-equipo">🏢 <?php echo htmlspecialchars($j['nombre_equipo_profesional'] ?? 'Libre'); ?></div>
                    
                    <div class="agente-precio-label">Valor Base:</div>
                    <div class="agente-precio"><?php echo number_format($j['precio_mercado'], 0, ',', '.'); ?> €</div>
                    
                    <?php if(!$j['mi_puja']): ?>
                        <form method="POST">
                            <input type="hidden" name="id_mercado" value="<?php echo $j['id_mercado']; ?>">
                            <input type="number" name="monto_puja" class="input-puja" min="<?php echo $j['precio_mercado']; ?>" value="<?php echo $j['precio_mercado']; ?>" required>
                            <button type="submit" class="btn-comprar">Lanzar Oferta</button>
                        </form>
                    <?php else: ?>
                        <div class="puja-actual-label">Tu oferta secreta:</div>
                        <div class="puja-actual-val"><?php echo number_format($j['mi_puja'], 0, ',', '.'); ?> €</div>
                        <button class="btn-cancelar" onclick="cancelarPuja(<?php echo $j['id_mercado']; ?>)">Retirar Oferta</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            // Lógica para cancelar puja
            async function cancelarPuja(idMercado) {
                if (!confirm('¿Retirar tu oferta? Recuperarás el dinero congelado al instante.')) return;
                try {
                    const res = await fetch('api_cancelar_puja.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id_equipo: <?php echo $id_equipo; ?>, id_mercado: idMercado })
                    });
                    const json = await res.json();
                    if (json.status === 'success') {
                        window.location.href = window.location.href; // Recargar limpiando POST
                    } else {
                        alert("Error: " + json.message);
                    }
                } catch(e) { alert("Error de conexión al servidor."); }
            }

            // Lógica del contador (Adaptada para formato de 24 horas: HH:MM:SS)
            const fechaFin = new Date("<?php echo $fecha_fin_js; ?>").getTime();
            
            const x = setInterval(function() {
                const ahora = new Date().getTime();
                const distancia = fechaFin - ahora;

                if (distancia < 0) {
                    clearInterval(x);
                    document.getElementById("countdown").innerHTML = "RESOLVIENDO...";
                    setTimeout(() => location.reload(), 2500); // Recarga para ejecutar el motor PHP
                    return;
                }

                // Cálculo de horas, minutos y segundos (El total de horas puede ser > 24 en este formato)
                const horas = Math.floor(distancia / (1000 * 60 * 60));
                const minutos = Math.floor((distancia % (1000 * 60 * 60)) / (1000 * 60));
                const segundos = Math.floor((distancia % (1000 * 60)) / 1000);

                document.getElementById("countdown").innerHTML = 
                    String(horas).padStart(2, '0') + ":" + 
                    String(minutos).padStart(2, '0') + ":" + 
                    String(segundos).padStart(2, '0');
            }, 1000);
        </script>
    <?php else: ?>
        <div style="text-align:center; padding: 50px; background: #141418; border: 1px solid rgba(255,255,255,0.1);">
            <p style="color:var(--muted); font-size:1.2rem;">El mercado se está actualizando. Vuelve en unos instantes.</p>
        </div>
    <?php endif; ?>
</body>
</html>