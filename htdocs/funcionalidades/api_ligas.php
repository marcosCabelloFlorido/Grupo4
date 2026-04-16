<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

// Responder preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once __DIR__ . '/../conexion.php';

// ══════════════════════════════════════════════════════════════════════════════
// FUNCIÓN AUXILIAR: Unirse a una liga (reutilizada por POST unirse y POST crear)
// ══════════════════════════════════════════════════════════════════════════════
function unirseALiga($conexion, $id_usuario, $id_liga, $nombre_equipo) {
    // Verificar que no esté ya en la liga
    $stmtCheck = $conexion->prepare("SELECT id_liga FROM participaciones WHERE id_usuario = :id_u AND id_liga = :id_l");
    $stmtCheck->execute([':id_u' => $id_usuario, ':id_l' => $id_liga]);
    if ($stmtCheck->fetch()) throw new Exception("Ya eres participante de esta liga.");

    // Verificar que no se ha superado el máximo
    $stmtMax = $conexion->prepare("SELECT L.max_participantes, COUNT(P.id_usuario) AS total FROM ligas L LEFT JOIN participaciones P ON L.id_liga = P.id_liga WHERE L.id_liga = :id_l GROUP BY L.id_liga");
    $stmtMax->execute([':id_l' => $id_liga]);
    $datos = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($datos && $datos['total'] >= $datos['max_participantes']) {
        throw new Exception("Esta liga ha alcanzado el número máximo de participantes.");
    }

    // Calcular posicion_actual (última posición)
    $stmtPos = $conexion->prepare("SELECT COUNT(*) FROM participaciones WHERE id_liga = :id_l");
    $stmtPos->execute([':id_l' => $id_liga]);
    $posicion = (int)$stmtPos->fetchColumn() + 1;

    $stmtPart = $conexion->prepare("INSERT INTO participaciones (id_usuario, id_liga, posicion_actual) VALUES (:id_u, :id_l, :pos)");
    $stmtPart->execute([':id_u' => $id_usuario, ':id_l' => $id_liga, ':pos' => $posicion]);

    $stmtEquipo = $conexion->prepare("INSERT INTO equipos_fantasy (id_usuario, id_liga, nombre_equipo, presupuesto_disponible) VALUES (:id_u, :id_l, :nombre, 35000000)");
    $stmtEquipo->execute([':id_u' => $id_usuario, ':id_l' => $id_liga, ':nombre' => $nombre_equipo]);
}

// ══════════════════════════════════════════════════════════════════════════════
// POST — Crear liga O unirse a liga
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = json_decode(file_get_contents("php://input"));

    // ── SUB-ACCIÓN: Unirse a una liga ──────────────────────────────────────
    if (!empty($data->accion) && $data->accion === 'unirse') {
        if (empty($data->nombre_usuario) || empty($data->nombre_equipo)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Faltan datos: nombre_usuario y nombre_equipo son obligatorios."]);
            exit();
        }

        try {
            // Obtener id_usuario
            $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre");
            $stmtUser->execute([':nombre' => $data->nombre_usuario]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) throw new Exception("El agente no existe.");

            $conexion->beginTransaction();

            // ── Unirse por CÓDIGO (liga privada) ──
            if (!empty($data->codigo_acceso)) {
                $stmtLiga = $conexion->prepare("SELECT id_liga, tipo FROM ligas WHERE codigo_acceso = :codigo");
                $stmtLiga->execute([':codigo' => strtoupper(trim($data->codigo_acceso))]);
                $liga = $stmtLiga->fetch(PDO::FETCH_ASSOC);
                if (!$liga) throw new Exception("Código de acceso inválido. Comprueba que lo has introducido correctamente.");
                $id_liga = $liga['id_liga'];

            // ── Unirse a liga PÚBLICA por id ──
            } elseif (!empty($data->id_liga)) {
                $stmtLiga = $conexion->prepare("SELECT id_liga, tipo FROM ligas WHERE id_liga = :id_l");
                $stmtLiga->execute([':id_l' => (int)$data->id_liga]);
                $liga = $stmtLiga->fetch(PDO::FETCH_ASSOC);
                if (!$liga) throw new Exception("La liga no existe.");
                if ($liga['tipo'] === 'Privada') throw new Exception("Esta liga es privada. Necesitas un código de acceso para unirte.");
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

    // ── SUB-ACCIÓN: Crear liga ─────────────────────────────────────────────
    if (!empty($data->nombre_usuario) && !empty($data->nombre_liga) && !empty($data->nombre_equipo)) {
        try {
            $conexion->beginTransaction();

            // 1. Obtener id_usuario
            $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario");
            $stmtUser->execute([':nombre_usuario' => $data->nombre_usuario]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) throw new Exception("El agente ingresado no existe.");
            $id_usuario_real = $usuario['id_usuario'];

            // 2. Generar código único para ligas privadas
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

            // 3. Insertar la liga
            $stmtLiga = $conexion->prepare("INSERT INTO ligas (nombre, tipo, codigo_acceso) VALUES (:nombre, :tipo, :codigo)");
            $stmtLiga->execute([
                ':nombre' => $data->nombre_liga,
                ':tipo'   => $data->tipo ?? 'Publica',
                ':codigo' => $codigo_acceso
            ]);
            $id_liga_generado = $conexion->lastInsertId();

            // 4. Registrar participación y equipo fantasy del creador
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

// ══════════════════════════════════════════════════════════════════════════════
// GET — Obtener ligas de un usuario o listar ligas públicas disponibles
// ══════════════════════════════════════════════════════════════════════════════
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ── GET: Ligas públicas disponibles para unirse ────────────────────────
    if (isset($_GET['publicas']) && isset($_GET['usuario'])) {
        try {
            $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre");
            $stmtUser->execute([':nombre' => $_GET['usuario']]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) throw new Exception("Usuario no encontrado.");

            // Ligas públicas en las que el usuario NO participa y que no están llenas
            $query = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo, L.max_participantes,
                             COUNT(P.id_usuario) AS participantes_actuales
                      FROM ligas L
                      LEFT JOIN participaciones P ON L.id_liga = P.id_liga
                      WHERE L.tipo = 'Publica'
                        AND L.id_liga NOT IN (
                            SELECT id_liga FROM participaciones WHERE id_usuario = :id_usuario
                        )
                      GROUP BY L.id_liga
                      HAVING participantes_actuales < L.max_participantes
                      ORDER BY participantes_actuales DESC
                      LIMIT 20";
            $stmt = $conexion->prepare($query);
            $stmt->execute([':id_usuario' => $usuario['id_usuario']]);
            $ligas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(["status" => "success", "data" => $ligas]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    // ── GET: Ligas del usuario ─────────────────────────────────────────────
    if (isset($_GET['usuario'])) {
        try {
            $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario");
            $stmtUser->execute([':nombre_usuario' => $_GET['usuario']]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) throw new Exception("Usuario no encontrado.");
            $id_usuario = $usuario['id_usuario'];

            $query = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo, L.codigo_acceso,
                             P.posicion_actual,
                             EF.nombre_equipo, EF.presupuesto_disponible, EF.puntos_equipo,
                             (SELECT COUNT(*) FROM participaciones P2 WHERE P2.id_liga = L.id_liga) AS total_participantes
                      FROM ligas L
                      INNER JOIN participaciones  P  ON L.id_liga    = P.id_liga
                      INNER JOIN equipos_fantasy  EF ON P.id_usuario = EF.id_usuario
                                                     AND P.id_liga   = EF.id_liga
                      WHERE P.id_usuario = :id_usuario";

            $stmt = $conexion->prepare($query);
            $stmt->execute([':id_usuario' => $id_usuario]);
            $ligas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status"      => "success",
                "total_ligas" => count($ligas),
                "data"        => $ligas
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }

    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Parámetro 'usuario' requerido."]);
    }

// ══════════════════════════════════════════════════════════════════════════════
// DELETE — Eliminar una liga
// ══════════════════════════════════════════════════════════════════════════════
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->id_liga) || empty($data->nombre_usuario)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Faltan datos: id_liga y nombre_usuario son obligatorios."]);
        exit();
    }

    $id_liga        = (int) $data->id_liga;
    $nombre_usuario = $data->nombre_usuario;

    try {
        $conexion->beginTransaction();

        $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario");
        $stmtUser->execute([':nombre_usuario' => $nombre_usuario]);
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) throw new Exception("El agente no existe.");
        $id_usuario = $usuario['id_usuario'];

        $stmtCheck = $conexion->prepare("SELECT id_liga FROM participaciones WHERE id_liga = :id_liga AND id_usuario = :id_usuario");
        $stmtCheck->execute([':id_liga' => $id_liga, ':id_usuario' => $id_usuario]);
        if (!$stmtCheck->fetch()) throw new Exception("No tienes permisos para eliminar esta liga.");

        $stmtDelete = $conexion->prepare("DELETE FROM ligas WHERE id_liga = :id_liga");
        $stmtDelete->execute([':id_liga' => $id_liga]);
        if ($stmtDelete->rowCount() === 0) throw new Exception("La liga no existe o ya fue eliminada.");

        $conexion->commit();
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Liga eliminada con éxito."]);

    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
}
?>