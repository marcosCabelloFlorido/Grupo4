<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../sesion/login.php");
    exit();
}

require_once __DIR__ . '/../conexion.php';

$nombre_usuario_actual = $_SESSION['usuario'];
$mensaje = null;

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
    // MOTOR DE RESOLUCIÓN DE PUJAS (Lazy Evaluation)
    // ────────────────────────────────────────────────────────────────────────
    $stmtExp = $conexion->prepare("SELECT id_mercado, id_jugador FROM mercado_liga WHERE id_liga = :id_liga AND fecha_expiracion <= NOW()");
    $stmtExp->execute([':id_liga' => $id_liga]);
    $expirados = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    if (count($expirados) > 0) {
        $conexion->beginTransaction();
        
        foreach ($expirados as $exp) {
            // Obtener todas las pujas de este jugador ordenadas de mayor a menor
            $stmtBids = $conexion->prepare("SELECT id_equipo_fantasy, monto FROM pujas WHERE id_mercado = :id_m ORDER BY monto DESC, fecha_puja ASC");
            $stmtBids->execute([':id_m' => $exp['id_mercado']]);
            $bids = $stmtBids->fetchAll(PDO::FETCH_ASSOC);

            if (count($bids) > 0) {
                $ganador = $bids[0]; // El que más pagó
                
                // Asignar jugador al ganador (como reserva: titular = 0)
                $stmtIns = $conexion->prepare("INSERT INTO alineaciones (id_equipo_fantasy, id_jugador, jornada, titular) VALUES (:id_ef, :id_j, 1, 0)");
                $stmtIns->execute([':id_ef' => $ganador['id_equipo_fantasy'], ':id_j' => $exp['id_jugador']]);

                // Devolver el dinero congelado a los perdedores
                for ($i = 1; $i < count($bids); $i++) {
                    $perdedor = $bids[$i];
                    $stmtRefund = $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible + :monto WHERE id_equipo_fantasy = :id_ef");
                    $stmtRefund->execute([':monto' => $perdedor['monto'], ':id_ef' => $perdedor['id_equipo_fantasy']]);
                }
            }
        }

        // Borrar el mercado viejo (las pujas se borran solas por CASCADE)
        $conexion->prepare("DELETE FROM mercado_liga WHERE id_liga = :id_liga AND fecha_expiracion <= NOW()")->execute([':id_liga' => $id_liga]);

        // Generar 5 nuevos jugadores libres para el mercado
        $queryLibres = "SELECT id_jugador FROM jugadores WHERE id_jugador NOT IN (SELECT A.id_jugador FROM alineaciones A INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga) ORDER BY RAND() LIMIT 5";
        $stmtLibres = $conexion->prepare($queryLibres);
        $stmtLibres->execute([':id_liga' => $id_liga]);
        $nuevos_mercado = $stmtLibres->fetchAll(PDO::FETCH_COLUMN);

        $stmtInsertMercado = $conexion->prepare("INSERT INTO mercado_liga (id_liga, id_jugador, fecha_expiracion) VALUES (:id_liga, :id_jugador, DATE_ADD(NOW(), INTERVAL 1 DAY))");
        foreach ($nuevos_mercado as $id_j) {
            $stmtInsertMercado->execute([':id_liga' => $id_liga, ':id_jugador' => $id_j]);
        }
        
        $conexion->commit();
        // Recargar el presupuesto por si el usuario actual recibió devoluciones
        $stmtUser->execute([':nombre' => $nombre_usuario_actual, ':id_liga' => $id_liga]);
        $datos_equipo = $stmtUser->fetch(PDO::FETCH_ASSOC);
    } else {
        // Si no hay expirados, verificamos si el mercado está vacío (primera vez que se entra)
        $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM mercado_liga WHERE id_liga = :id_liga");
        $stmtCount->execute([':id_liga' => $id_liga]);
        if ($stmtCount->fetchColumn() == 0) {
            $conexion->beginTransaction();
            $queryLibres = "SELECT id_jugador FROM jugadores WHERE id_jugador NOT IN (SELECT A.id_jugador FROM alineaciones A INNER JOIN equipos_fantasy EF ON A.id_equipo_fantasy = EF.id_equipo_fantasy WHERE EF.id_liga = :id_liga) ORDER BY RAND() LIMIT 5";
            $stmtLibres = $conexion->prepare($queryLibres);
            $stmtLibres->execute([':id_liga' => $id_liga]);
            $nuevos_mercado = $stmtLibres->fetchAll(PDO::FETCH_COLUMN);
            $stmtInsertMercado = $conexion->prepare("INSERT INTO mercado_liga (id_liga, id_jugador, fecha_expiracion) VALUES (:id_liga, :id_jugador, DATE_ADD(NOW(), INTERVAL 1 DAY))");
            foreach ($nuevos_mercado as $id_j) {
                $stmtInsertMercado->execute([':id_liga' => $id_liga, ':id_jugador' => $id_j]);
            }
            $conexion->commit();
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // LÓGICA DE PUJA (POST)
    // ────────────────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_mercado'], $_POST['monto_puja'])) {
        $id_mercado_puja = (int)$_POST['id_mercado'];
        $monto_puja = (float)$_POST['monto_puja'];
        
        $conexion->beginTransaction();
        
        // 1. Verificar que el jugador sigue en el mercado y no ha pujado ya
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
            throw new Exception("Presupuesto insuficiente para esa puja.");
        }

        // 2. Congelar el dinero y registrar la puja
        $conexion->prepare("UPDATE equipos_fantasy SET presupuesto_disponible = presupuesto_disponible - :monto WHERE id_equipo_fantasy = :id_ef")->execute([':monto' => $monto_puja, ':id_ef' => $id_equipo]);
        $conexion->prepare("INSERT INTO pujas (id_mercado, id_equipo_fantasy, monto) VALUES (:id_m, :id_ef, :monto)")->execute([':id_m' => $id_mercado_puja, ':id_ef' => $id_equipo, ':monto' => $monto_puja]);

        $conexion->commit();
        $datos_equipo['presupuesto_disponible'] -= $monto_puja;
        $mensaje = ["tipo" => "exito", "texto" => "Puja registrada. El dinero ha sido congelado hasta la resolución."];
    }

    // 4. Obtener datos para pintar la web (AHORA CONTIENE EL MONTO DE MI PUJA)
    $queryPintar = "SELECT M.id_mercado, J.nickname, J.rol, J.precio_mercado, J.media_punto, M.fecha_expiracion, 
                    (SELECT monto FROM pujas P WHERE P.id_mercado = M.id_mercado AND P.id_equipo_fantasy = :id_ef) as mi_puja
                    FROM mercado_liga M 
                    INNER JOIN jugadores J ON M.id_jugador = J.id_jugador 
                    WHERE M.id_liga = :id_liga ORDER BY J.precio_mercado DESC";
    $stmtPintar = $conexion->prepare($queryPintar);
    $stmtPintar->execute([':id_liga' => $id_liga, ':id_ef' => $id_equipo]);
    $mercado_list = $stmtPintar->fetchAll(PDO::FETCH_ASSOC);

    // Obtener la fecha de expiración para el contador JS
    $fecha_fin_js = !empty($mercado_list) ? $mercado_list[0]['fecha_expiracion'] : null;

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack();
    $mensaje = ["tipo" => "error", "texto" => $e->getMessage()];
}

function getIcon($rol) {
    $iconos = ['Duelista'=>'⚔', 'Iniciador'=>'🔍', 'Centinela'=>'🛡', 'Smoker'=>'🌀'];
    return $iconos[$rol] ?? '•';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mercado — VALTASY</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Barlow+Condensed:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0d0d0f; color: #e8e8ee; font-family: 'Barlow Condensed'; padding: 40px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1DF2DD; padding-bottom: 20px; margin-bottom: 30px; }
        .grid-agentes { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
        .card-agente { background: #141418; border: 1px solid rgba(29,242,221,0.2); padding: 25px 20px; text-align: center; position: relative;}
        .card-agente.pujado { border-color: #A63247; background: rgba(140, 8, 19, 0.05); }
        .input-puja { width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #1DF2DD; font-family: 'Orbitron'; font-size: 1rem; text-align: center; margin-bottom: 10px; }
        .btn-comprar { width: 100%; padding: 12px; background: #1DF2DD; color: #000; font-weight: bold; font-family:'Barlow Condensed'; border: none; cursor: pointer; text-transform: uppercase; font-size: 1.1rem; transition: 0.2s;}
        .btn-comprar:hover { background: #168C77; color: #fff; }
        .btn-cancelar { width: 100%; padding: 12px; background: transparent; border: 1px solid #A63247; color: #A63247; font-weight: bold; font-family:'Barlow Condensed'; cursor: pointer; text-transform: uppercase; font-size: 1.1rem; margin-top: 10px; transition: 0.2s;}
        .btn-cancelar:hover { background: rgba(140, 8, 19, 0.2); color: #ff6b80; }
        .alert { padding: 15px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .alert.exito { background: rgba(29,242,221,0.2); border: 1px solid #1DF2DD; color: #1DF2DD; }
        .alert.error { background: rgba(140,8,19,0.2); border: 1px solid #A63247; color: #ff6b80; }
        .timer { font-family: 'Orbitron'; font-size: 1.2rem; color: #1DF2DD; margin-bottom: 30px; text-align: center; background: #141418; padding: 15px; border: 1px solid rgba(29,242,221,0.2);}
        .tag-pujado { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #A63247; color: #fff; padding: 4px 12px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; white-space: nowrap;}
    </style>
</head>
<body>
    <div class="header">
        <h1 style="font-family:'Orbitron'">MERCADO DE PUJAS</h1>
        <div style="text-align:right">
            <p>FONDOS DISPONIBLES: <span style="color:#1DF2DD; font-weight:bold; font-size:1.4rem;"><?php echo number_format($datos_equipo['presupuesto_disponible'] ?? 0, 0, ',', '.'); ?> €</span></p>
            <br><a href="cliente.php" style="color:#6b6b7a; text-decoration:none;">← DASHBOARD</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert <?php echo $mensaje['tipo']; ?>"><?php echo $mensaje['texto']; ?></div>
    <?php endif; ?>

    <?php if (!empty($mercado_list)): ?>
        <div class="timer">⏳ RESOLUCIÓN DE PUJAS EN: <span id="countdown">--:--:--</span></div>
        <div class="grid-agentes">
            <?php foreach ($mercado_list as $j): ?>
                <div class="card-agente <?php echo $j['mi_puja'] ? 'pujado' : ''; ?>">
                    <?php if($j['mi_puja']): ?>
                        <div class="tag-pujado">Oferta Lanzada</div>
                    <?php endif; ?>
                    <p style="color:#6b6b7a; font-size:0.8rem;"><?php echo getIcon($j['rol']); ?> <?php echo strtoupper($j['rol']); ?></p>
                    <h2 style="font-family:'Orbitron'; color:#fff; margin:10px 0;"><?php echo htmlspecialchars($j['nickname']); ?></h2>
                    <p style="color:#6b6b7a; font-size:0.9rem;">Valor de mercado:</p>
                    <p style="color:#1DF2DD; font-weight:bold; font-size:1.4rem; margin-bottom: 10px;"><?php echo number_format($j['precio_mercado'], 0, ',', '.'); ?> €</p>
                    
                    <?php if(!$j['mi_puja']): ?>
                        <form method="POST">
                            <input type="hidden" name="id_mercado" value="<?php echo $j['id_mercado']; ?>">
                            <input type="number" name="monto_puja" class="input-puja" min="<?php echo $j['precio_mercado']; ?>" value="<?php echo $j['precio_mercado']; ?>" required>
                            <button type="submit" class="btn-comprar">OFRECER PUJA CIEGA</button>
                        </form>
                    <?php else: ?>
                        <p style="color:#e8e8ee; font-size:0.9rem; margin-top: 15px;">Tu oferta actual:</p>
                        <p style="color:#ff6b80; font-weight:bold; font-size:1.2rem; font-family:'Orbitron';"><?php echo number_format($j['mi_puja'], 0, ',', '.'); ?> €</p>
                        <button class="btn-cancelar" onclick="cancelarPuja(<?php echo $j['id_mercado']; ?>)">Retirar Oferta</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            // Lógica para cancelar la puja
            async function cancelarPuja(idMercado) {
                if (!confirm('¿Estás seguro de que quieres retirar tu puja? Recuperarás tu dinero inmediatamente para poder fichar a otro agente.')) return;
                
                try {
                    const res = await fetch('api_cancelar_puja.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            id_equipo: <?php echo $id_equipo; ?>,
                            id_mercado: idMercado
                        })
                    });
                    const json = await res.json();
                    if (json.status === 'success') {
                        location.reload(); // Recargar para reflejar que el dinero volvió
                    } else {
                        alert("Error: " + json.message);
                    }
                } catch (e) { 
                    alert("Error de conexión al intentar cancelar la puja."); 
                }
            }

            // Lógica del contador regresivo en JavaScript
            const fechaFin = new Date("<?php echo $fecha_fin_js; ?>").getTime();
            
            const x = setInterval(function() {
                const ahora = new Date().getTime();
                const distancia = fechaFin - ahora;

                if (distancia < 0) {
                    clearInterval(x);
                    document.getElementById("countdown").innerHTML = "RESOLVIENDO...";
                    setTimeout(() => location.reload(), 2000); // Recarga para activar el motor PHP
                    return;
                }

                const horas = Math.floor((distancia % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutos = Math.floor((distancia % (1000 * 60 * 60)) / (1000 * 60));
                const segundos = Math.floor((distancia % (1000 * 60)) / 1000);

                document.getElementById("countdown").innerHTML = 
                    horas.toString().padStart(2, '0') + ":" + 
                    minutos.toString().padStart(2, '0') + ":" + 
                    segundos.toString().padStart(2, '0');
            }, 1000);
        </script>
    <?php else: ?>
        <p style="text-align:center; color:#6b6b7a;">No hay jugadores en el mercado actualmente.</p>
    <?php endif; ?>
</body>
</html>