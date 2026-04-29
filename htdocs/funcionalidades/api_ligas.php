<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once __DIR__ . '/../conexion.php';

/**
 * Verifica si un usuario (por nombre) tiene Premium activo.
 * También hace auto-caducidad si la fecha ya pasó.
 */
function usuarioEsPremium(PDO $db, string $nombre): bool {
    $stmt = $db->prepare(
        "SELECT es_premium, premium_hasta FROM usuarios WHERE nombre = :nombre"
    );
    $stmt->execute([':nombre' => $nombre]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) return false;

    if ($u['es_premium'] && $u['premium_hasta'] && strtotime($u['premium_hasta']) < time()) {
        // Caducó: revocar
        $db->prepare("UPDATE usuarios SET es_premium = 0 WHERE nombre = :nombre")
           ->execute([':nombre' => $nombre]);
        return false;
    }
    return (bool)$u['es_premium'];
}

function unirseALiga($conexion, $id_usuario, $id_liga, $nombre_equipo) {
    $stmtCheck = $conexion->prepare("SELECT id_liga FROM participaciones WHERE id_usuario = :id_u AND id_liga = :id_l");
    $stmtCheck->execute([':id_u' => $id_usuario, ':id_l' => $id_liga]);
    if ($stmtCheck->fetch()) throw new Exception("Ya eres participante de esta liga.");

    $stmtMax = $conexion->prepare("SELECT L.max_participantes, COUNT(P.id_usuario) AS total FROM ligas L LEFT JOIN participaciones P ON L.id_liga = P.id_liga WHERE L.id_liga = :id_l GROUP BY L.id_liga");
    $stmtMax->execute([':id_l' => $id_liga]);
    $datos = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($datos && $datos['total'] >= $datos['max_participantes']) {
        throw new Exception("Esta liga ha alcanzado el número máximo de participantes.");
    }

    $stmtPos = $conexion->prepare("SELECT COUNT(*) FROM participaciones WHERE id_liga = :id_l");
    $stmtPos->execute([':id_l' => $id_liga]);
    $posicion = (int)$stmtPos->fetchColumn() + 1;

    $stmtPart = $conexion->prepare("INSERT INTO participaciones (id_usuario, id_liga, posicion_actual) VALUES (:id_u, :id_l, :pos)");
    $stmtPart->execute([':id_u' => $id_usuario, ':id_l' => $id_liga, ':pos' => $posicion]);

    $stmtEquipo = $conexion->prepare("INSERT INTO equipos_fantasy (id_usuario, id_liga, nombre_equipo, presupuesto_disponible) VALUES (:id_u, :id_l, :nombre, 35000000)");
    $stmtEquipo->execute([':id_u' => $id_usuario, ':id_l' => $id_liga, ':nombre' => $nombre_equipo]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->accion) && $data->accion === 'unirse') {
        if (empty($data->nombre_usuario) || empty($data->nombre_equipo)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Faltan datos obligatorios."]);
            exit();
        }

        try {
            $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre");
            $stmtUser->execute([':nombre' => $data->nombre_usuario]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) throw new Exception("El agente no existe.");

            $conexion->beginTransaction();

            if (!empty($data->codigo_acceso)) {
                $stmtLiga = $conexion->prepare("SELECT id_liga, tipo FROM ligas WHERE codigo_acceso = :codigo");
                $stmtLiga->execute([':codigo' => strtoupper(trim($data->codigo_acceso))]);
                $liga = $stmtLiga->fetch(PDO::FETCH_ASSOC);
                if (!$liga) throw new Exception("Código de acceso inválido.");
                $id_liga = $liga['id_liga'];
            } elseif (!empty($data->id_liga)) {
                $stmtLiga = $conexion->prepare("SELECT id_liga, tipo FROM ligas WHERE id_liga = :id_l");
                $stmtLiga->execute([':id_l' => (int)$data->id_liga]);
                $liga = $stmtLiga->fetch(PDO::FETCH_ASSOC);
                if (!$liga) throw new Exception("La liga no existe.");
                if ($liga['tipo'] === 'Privada') throw new Exception("Esta liga es privada. Necesitas código.");
                $id_liga = $liga['id_liga'];
            } else {
                throw new Exception("Debes indicar un código de acceso o una liga pública.");
            }

            unirseALiga($conexion, $usuario['id_usuario'], $id_liga, $data->nombre_equipo);
            $conexion->commit();
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Te has unido a la liga con éxito.", "id_liga" => $id_liga]);

        } catch (Exception $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    if (!empty($data->nombre_usuario) && !empty($data->nombre_liga) && !empty($data->nombre_equipo)) {
        try {
            $conexion->beginTransaction();

            $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario");
            $stmtUser->execute([':nombre_usuario' => $data->nombre_usuario]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) throw new Exception("El agente ingresado no existe.");
            $id_usuario_real = $usuario['id_usuario'];

            // ── COMPROBACIÓN PREMIUM EN SERVIDOR ────────────────────────────
            // Nunca confiar solo en el cliente: verificamos en BD que el usuario
            // tiene Premium activo antes de permitir crear una liga privada.
            if (!empty($data->tipo) && $data->tipo === 'Privada') {
                if (!usuarioEsPremium($conexion, $data->nombre_usuario)) {
                    if ($conexion->inTransaction()) $conexion->rollBack();
                    http_response_code(403);
                    echo json_encode([
                        "status"           => "error",
                        "message"          => "Necesitas una suscripción Premium activa para crear ligas privadas.",
                        "requiere_premium" => true
                    ]);
                    exit();
                }
            }
            // ────────────────────────────────────────────────────────────────

            $codigo_acceso = null;
            if (!empty($data->tipo) && $data->tipo === 'Privada') {
                $intentos = 0;
                do {
                    $codigo_candidato = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 7));
                    $stmtCodigo = $conexion->prepare("SELECT id_liga FROM ligas WHERE codigo_acceso = :codigo");
                    $stmtCodigo->execute([':codigo' => $codigo_candidato]);
                    $intentos++;
                } while ($stmtCodigo->fetch() && $intentos < 10);
                $codigo_acceso = $codigo_candidato;
            }

            $torneo = !empty($data->torneo) ? $data->torneo : 'VCT EMEA - Fase Regular';

            $stmtLiga = $conexion->prepare("INSERT INTO ligas (id_creador, nombre, tipo, torneo, codigo_acceso) VALUES (:creador, :nombre, :tipo, :torneo, :codigo)");
            $stmtLiga->execute([
                ':creador' => $id_usuario_real,
                ':nombre'  => $data->nombre_liga,
                ':tipo'    => $data->tipo ?? 'Publica',
                ':torneo'  => $torneo,
                ':codigo'  => $codigo_acceso
            ]);
            $id_liga_generado = $conexion->lastInsertId();

            unirseALiga($conexion, $id_usuario_real, $id_liga_generado, $data->nombre_equipo);
            $conexion->commit();
            http_response_code(201);
            echo json_encode([
                "status"        => "success",
                "message"       => "Operación creada con éxito.",
                "id_liga"       => $id_liga_generado,
                "codigo_acceso" => $codigo_acceso
            ]);

        } catch (Exception $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }

    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Faltan datos obligatorios."]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (isset($_GET['publicas']) && isset($_GET['usuario'])) {
        try {
            $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre");
            $stmtUser->execute([':nombre' => $_GET['usuario']]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) throw new Exception("Usuario no encontrado.");

            $query = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo, L.max_participantes, COUNT(P.id_usuario) AS participantes_actuales FROM ligas L LEFT JOIN participaciones P ON L.id_liga = P.id_liga WHERE L.tipo = 'Publica' AND L.id_liga NOT IN (SELECT id_liga FROM participaciones WHERE id_usuario = :id_usuario) GROUP BY L.id_liga HAVING participantes_actuales < L.max_participantes ORDER BY participantes_actuales DESC LIMIT 20";
            $stmt = $conexion->prepare($query); $stmt->execute([':id_usuario' => $usuario['id_usuario']]);
            
            $resultadosPublicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["status" => "success", "data" => $resultadosPublicas]);

        } catch (Exception $e) { http_response_code(400); echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        exit();
    }

    if (isset($_GET['usuario'])) {
        try {
            $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario");
            $stmtUser->execute([':nombre_usuario' => $_GET['usuario']]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) throw new Exception("Usuario no encontrado.");
            $id_usuario = $usuario['id_usuario'];

            $query = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo, L.torneo, L.codigo_acceso, P.posicion_actual, EF.nombre_equipo, EF.presupuesto_disponible, EF.puntos_equipo, (SELECT COUNT(*) FROM participaciones P2 WHERE P2.id_liga = L.id_liga) AS total_participantes FROM ligas L INNER JOIN participaciones P ON L.id_liga = P.id_liga INNER JOIN equipos_fantasy EF ON P.id_usuario = EF.id_usuario AND P.id_liga = EF.id_liga WHERE P.id_usuario = :id_usuario";
            $stmt = $conexion->prepare($query); 
            $stmt->execute([':id_usuario' => $id_usuario]);
            
            // LA CORRECCIÓN CLAVE: Guardamos en variable antes de enviarlo
            $resultadosMisLigas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "status" => "success", 
                "total_ligas" => count($resultadosMisLigas), 
                "data" => $resultadosMisLigas
            ]);

        } catch (Exception $e) { http_response_code(400); echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
    } else {
        http_response_code(400); echo json_encode(["status" => "error", "message" => "Parámetro 'usuario' requerido."]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->id_liga) || empty($data->nombre_usuario)) { http_response_code(400); echo json_encode(["status" => "error", "message" => "Faltan datos obligatorios."]); exit(); }
    $id_liga = (int) $data->id_liga; $nombre_usuario = $data->nombre_usuario;

    try {
        $conexion->beginTransaction();
        $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario");
        $stmtUser->execute([':nombre_usuario' => $nombre_usuario]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) throw new Exception("El agente no existe.");
        $id_usuario = $usuario['id_usuario'];

        $stmtLiga = $conexion->prepare("SELECT id_creador FROM ligas WHERE id_liga = :id_liga");
        $stmtLiga->execute([':id_liga' => $id_liga]);
        $liga = $stmtLiga->fetch(PDO::FETCH_ASSOC);
        if (!$liga) throw new Exception("La liga no existe o ya fue eliminada.");

        if ($liga['id_creador'] == $id_usuario) {
            $stmtDelete = $conexion->prepare("DELETE FROM ligas WHERE id_liga = :id_liga");
            $stmtDelete->execute([':id_liga' => $id_liga]);
            $mensaje = "Liga eliminada por completo.";
        } else {
            $stmtCheck = $conexion->prepare("SELECT id_liga FROM participaciones WHERE id_liga = :id_liga AND id_usuario = :id_usuario");
            $stmtCheck->execute([':id_liga' => $id_liga, ':id_usuario' => $id_usuario]);
            if (!$stmtCheck->fetch()) throw new Exception("No perteneces a esta liga.");
            $stmtLeave = $conexion->prepare("DELETE FROM participaciones WHERE id_liga = :id_liga AND id_usuario = :id_usuario");
            $stmtLeave->execute([':id_liga' => $id_liga, ':id_usuario' => $id_usuario]);
            $mensaje = "Has abandonado la liga con éxito.";
        }

        $conexion->commit();
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => $mensaje]);

    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        http_response_code(400); echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else { http_response_code(405); echo json_encode(["status" => "error", "message" => "Método no permitido."]); }
?>