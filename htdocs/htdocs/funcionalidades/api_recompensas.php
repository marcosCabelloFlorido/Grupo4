<?php
session_start();
header('Content-Type: application/json');

// 1. Configuración de Base de Datos centralizada
require __DIR__ . '/../conexion.php';

// 2. Verificación de Seguridad
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
    exit();
}

$usuario_nombre = $_SESSION['usuario'];
$accion  = $_POST['accion'] ?? $_GET['accion'] ?? 'estado';
$id_liga = $_POST['id_liga'] ?? $_GET['id_liga'] ?? null;

if (!$id_liga) {
    echo json_encode(['status' => 'error', 'message' => 'Falta identificar la liga.']);
    exit();
}

// 3. Consultar datos del Usuario
$stmtUser = $conexion->prepare("SELECT id_usuario, es_premium FROM usuarios WHERE nombre = :nombre");
$stmtUser->execute(['nombre' => $usuario_nombre]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado en la BD.']);
    exit();
}

$id_usuario = $userData['id_usuario'];
$es_premium = (int)$userData['es_premium'] === 1;

// 4. Sistema de Racha por LIGA
if (!isset($_SESSION['recompensas'][$id_usuario][$id_liga])) {
    $_SESSION['recompensas'][$id_usuario][$id_liga] = [
        'ultima_recompensa' => 0,
        'racha' => 0
    ];
}

$estado = $_SESSION['recompensas'][$id_usuario][$id_liga];
$ahora = time();
$tiempo_pasado = $ahora - $estado['ultima_recompensa'];
$tiempo_espera = 24 * 3600;

$puede_reclamar = $tiempo_pasado >= $tiempo_espera;
$tiempo_restante = $puede_reclamar ? 0 : ($tiempo_espera - $tiempo_pasado);

if ($tiempo_pasado > (48 * 3600) && $estado['ultima_recompensa'] != 0) {
    $estado['racha'] = 0;
    $_SESSION['recompensas'][$id_usuario][$id_liga] = $estado;
}

// 5. Cálculos (Sistema de hitos: 5, 10, 20, 30)
$racha = $estado['racha'];
$bono_racha_pct = 0;

if ($racha >= 30) {
    $bono_racha_pct = 0.02; // 2.0%
} elseif ($racha >= 20) {
    $bono_racha_pct = 0.015; // 1.5%
} elseif ($racha >= 10) {
    $bono_racha_pct = 0.01; // 1.0%
} elseif ($racha >= 5) {
    $bono_racha_pct = 0.005; // 0.5%
}

// Generador del Mensaje Progresivo
$dias_para_siguiente = 0;
$proximo_bono = "";

if ($racha < 5) {
    $dias_para_siguiente = 5 - $racha;
    $proximo_bono = "0.5%";
} elseif ($racha < 10) {
    $dias_para_siguiente = 10 - $racha;
    $proximo_bono = "1.0%";
} elseif ($racha < 20) {
    $dias_para_siguiente = 20 - $racha;
    $proximo_bono = "1.5%";
} elseif ($racha < 30) {
    $dias_para_siguiente = 30 - $racha;
    $proximo_bono = "2.0%";
}

if ($dias_para_siguiente > 0) {
    $mensaje_progreso = "En $dias_para_siguiente día(s) tendrás una suma de bonificación de $proximo_bono.";
} else {
    $mensaje_progreso = "¡Has alcanzado la bonificación máxima por racha diaria!";
}

$recompensa_base = 3000;
$bono_premium_pct = $es_premium ? 0.02 : 0;
$multiplicador = 1 + $bono_racha_pct + $bono_premium_pct;
$recompensa_total = round($recompensa_base * $multiplicador);

// RESPUESTA DE ESTADO
if ($accion === 'estado') {
    echo json_encode([
        'status' => 'success',
        'puede_reclamar' => $puede_reclamar,
        'tiempo_restante' => $tiempo_restante,
        'racha' => $estado['racha'],
        'recompensa_base' => $recompensa_base,
        'bono_racha_pct' => $bono_racha_pct * 100, // Lo convertimos para mostrar (0.5, 1.0, etc)
        'bono_premium_pct' => $es_premium ? 2 : 0,
        'recompensa_total' => $recompensa_total,
        'mensaje_progreso' => $mensaje_progreso
    ]);
    exit();
}

// ACCIÓN DE RECLAMAR
if ($accion === 'reclamar') {
    if (!$puede_reclamar) {
        echo json_encode(['status' => 'error', 'message' => 'Aún no han pasado 24 horas.']);
        exit();
    }

    try {
        $stmtUpdate = $conexion->prepare("
            UPDATE equipos_fantasy 
            SET presupuesto_disponible = presupuesto_disponible + :recompensa 
            WHERE id_usuario = :id_usuario AND id_liga = :id_liga
        ");
        $stmtUpdate->execute([
            'recompensa' => $recompensa_total,
            'id_usuario' => $id_usuario,
            'id_liga'    => $id_liga
        ]);

        if ($stmtUpdate->rowCount() === 0) {
             echo json_encode(['status' => 'error', 'message' => 'No se encontró tu equipo en esta liga.']);
             exit();
        }

        $estado['ultima_recompensa'] = $ahora;
        $estado['racha'] += 1;
        $_SESSION['recompensas'][$id_usuario][$id_liga] = $estado;

        echo json_encode([
            'status' => 'success',
            'message' => "¡Has recibido " . number_format($recompensa_total, 0, ',', '.') . " € en esta liga!"
        ]);
        exit();

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar base de datos.']);
        exit();
    }
}