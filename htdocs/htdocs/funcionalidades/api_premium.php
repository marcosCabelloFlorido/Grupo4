<?php
/**
 * api_premium.php
 * Gestiona la compra, verificación y estado del plan Premium.
 *
 * GET  ?usuario=X           → devuelve si el usuario es premium y hasta cuándo
 * POST { accion:'comprar', nombre_usuario, metodo_pago, meses }
 *                           → simula la compra y activa el premium
 */
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../conexion.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Comprueba (y sincroniza) si un usuario sigue siendo premium.
 * Si la fecha premium_hasta ya pasó, desactiva el flag.
 */
function obtenerEstadoPremium(PDO $db, string $nombre): array {
    $stmt = $db->prepare(
        "SELECT id_usuario, es_premium, premium_desde, premium_hasta
         FROM usuarios WHERE nombre = :nombre"
    );
    $stmt->execute([':nombre' => $nombre]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) throw new Exception("Usuario no encontrado.");

    // Auto-caducidad: si la fecha ya pasó, revocar premium
    if ($u['es_premium'] && $u['premium_hasta'] && strtotime($u['premium_hasta']) < time()) {
        $db->prepare(
            "UPDATE usuarios SET es_premium = 0 WHERE id_usuario = :id"
        )->execute([':id' => $u['id_usuario']]);
        $u['es_premium']    = 0;
        $u['premium_hasta'] = null;
    }

    return $u;
}

// ── GET: estado premium ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $nombre = $_GET['usuario'] ?? '';
    if (!$nombre) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Parámetro 'usuario' requerido."]);
        exit();
    }

    try {
        $u = obtenerEstadoPremium($conexion, $nombre);
        echo json_encode([
            "status"          => "success",
            "es_premium"      => (bool)$u['es_premium'],
            "premium_desde"   => $u['premium_desde'],
            "premium_hasta"   => $u['premium_hasta'],
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit();
}

// ── POST: comprar premium ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    // Validación básica de sesión
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Sesión caducada."]);
        exit();
    }

    $accion = $data->accion ?? '';

    if ($accion === 'comprar') {
        $nombre     = $_SESSION['usuario'];
        $metodo     = $data->metodo_pago ?? 'tarjeta';
        $meses      = max(1, min(12, (int)($data->meses ?? 1)));
        $monto      = round(4.99 * $meses, 2);

        // Precios con descuento
        $precios = [1 => 4.99, 3 => 12.99, 6 => 22.99, 12 => 39.99];
        if (isset($precios[$meses])) $monto = $precios[$meses];

        try {
            $conexion->beginTransaction();

            $u = obtenerEstadoPremium($conexion, $nombre);

            // Calcular nueva fecha de fin
            // Si ya tiene premium activo, extender desde premium_hasta
            $base = ($u['es_premium'] && $u['premium_hasta'] && strtotime($u['premium_hasta']) > time())
                ? strtotime($u['premium_hasta'])
                : time();

            $nueva_hasta = date('Y-m-d H:i:s', strtotime("+{$meses} months", $base));
            $nueva_desde = $u['premium_desde'] ?? date('Y-m-d H:i:s');

            // Actualizar usuario
            $conexion->prepare(
                "UPDATE usuarios
                 SET es_premium = 1, premium_desde = :desde, premium_hasta = :hasta
                 WHERE id_usuario = :id"
            )->execute([
                ':desde' => $nueva_desde,
                ':hasta' => $nueva_hasta,
                ':id'    => $u['id_usuario'],
            ]);

            // Registrar pago
            // En producción: aquí iría la llamada al gateway de pago (Stripe, PayPal…)
            // y se guardaría la referencia externa real.
            $referencia = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));
            $conexion->prepare(
                "INSERT INTO pagos_premium
                    (id_usuario, monto, metodo_pago, referencia, estado, meses)
                 VALUES (:id_u, :monto, :metodo, :ref, 'completado', :meses)"
            )->execute([
                ':id_u'   => $u['id_usuario'],
                ':monto'  => $monto,
                ':metodo' => $metodo,
                ':ref'    => $referencia,
                ':meses'  => $meses,
            ]);

            $conexion->commit();

            echo json_encode([
                "status"        => "success",
                "message"       => "¡Premium activado con éxito!",
                "premium_hasta" => $nueva_hasta,
                "referencia"    => $referencia,
                "monto"         => $monto,
            ]);

        } catch (Exception $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    // Acción desconocida
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Acción no reconocida."]);
    exit();
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Método no permitido."]);