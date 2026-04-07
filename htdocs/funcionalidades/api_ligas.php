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

// conexion.php está en la raíz (un nivel arriba de funcionalidades/)
require_once __DIR__ . '/../conexion.php';

// ══════════════════════════════════════════════════════════════════════════════
// POST — Crear una nueva liga
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->nombre_usuario) && !empty($data->nombre_liga) && !empty($data->nombre_equipo)) {
        try {
            $conexion->beginTransaction();

            // 1. Obtener el id_usuario real a partir del nombre
            $queryUser = "SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario";
            $stmtUser  = $conexion->prepare($queryUser);
            $stmtUser->bindParam(':nombre_usuario', $data->nombre_usuario);
            $stmtUser->execute();
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) throw new Exception("El agente ingresado no existe.");
            $id_usuario_real = $usuario['id_usuario'];

            // 2. Insertar la liga
            $queryLiga = "INSERT INTO ligas (nombre, tipo) VALUES (:nombre, :tipo)";
            $stmtLiga  = $conexion->prepare($queryLiga);
            $stmtLiga->bindParam(':nombre', $data->nombre_liga);
            $stmtLiga->bindParam(':tipo',   $data->tipo);
            $stmtLiga->execute();
            $id_liga_generado = $conexion->lastInsertId();

            // 3. Registrar la participación
            $queryPart = "INSERT INTO participaciones (id_usuario, id_liga, posicion_actual)
                          VALUES (:id_usuario, :id_liga, 1)";
            $stmtPart  = $conexion->prepare($queryPart);
            $stmtPart->bindParam(':id_usuario', $id_usuario_real);
            $stmtPart->bindParam(':id_liga',    $id_liga_generado);
            $stmtPart->execute();

            // 4. Crear el equipo fantasy
            $queryEquipo = "INSERT INTO equipos_fantasy (id_usuario, id_liga, nombre_equipo)
                            VALUES (:id_usuario, :id_liga, :nombre_equipo)";
            $stmtEquipo  = $conexion->prepare($queryEquipo);
            $stmtEquipo->bindParam(':id_usuario',    $id_usuario_real);
            $stmtEquipo->bindParam(':id_liga',       $id_liga_generado);
            $stmtEquipo->bindParam(':nombre_equipo', $data->nombre_equipo);
            $stmtEquipo->execute();

            $conexion->commit();
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Operación creada con éxito."]);

        } catch (Exception $e) {
            $conexion->rollBack();
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }

    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Faltan datos obligatorios."]);
    }

// ══════════════════════════════════════════════════════════════════════════════
// GET — Obtener las ligas de un usuario
// ══════════════════════════════════════════════════════════════════════════════
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (isset($_GET['usuario'])) {
        try {
            // Resolver el id_usuario
            $queryUser = "SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario";
            $stmtUser  = $conexion->prepare($queryUser);
            $stmtUser->bindParam(':nombre_usuario', $_GET['usuario']);
            $stmtUser->execute();
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) throw new Exception("Usuario no encontrado.");
            $id_usuario = $usuario['id_usuario'];

            // Obtener todas sus ligas con datos del equipo
            $query = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo,
                             P.posicion_actual,
                             EF.nombre_equipo, EF.presupuesto_disponible, EF.puntos_equipo
                      FROM ligas L
                      INNER JOIN participaciones  P  ON L.id_liga    = P.id_liga
                      INNER JOIN equipos_fantasy  EF ON P.id_usuario = EF.id_usuario
                                                     AND P.id_liga   = EF.id_liga
                      WHERE P.id_usuario = :id_usuario";

            $stmt = $conexion->prepare($query);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $ligas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "status"     => "success",
                "total_ligas" => count($ligas),
                "data"       => $ligas
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
// DELETE — Eliminar una liga (solo el creador/participante puede eliminarla)
// ══════════════════════════════════════════════════════════════════════════════
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->id_liga) || empty($data->nombre_usuario)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Faltan datos: id_liga y nombre_usuario son obligatorios."]);
        exit();
    }

    $id_liga       = (int) $data->id_liga; // cast a entero para mayor seguridad
    $nombre_usuario = $data->nombre_usuario;

    try {
        $conexion->beginTransaction();

        // 1. Verificar que el usuario existe
        $queryUser = "SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario";
        $stmtUser  = $conexion->prepare($queryUser);
        $stmtUser->bindParam(':nombre_usuario', $nombre_usuario);
        $stmtUser->execute();
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) throw new Exception("El agente no existe.");
        $id_usuario = $usuario['id_usuario'];

        // 2. Verificar que el usuario tiene participación en esa liga
        //    (solo puede eliminar ligas en las que esté inscrito)
        $queryCheck = "SELECT id_liga FROM participaciones
                       WHERE id_liga = :id_liga AND id_usuario = :id_usuario";
        $stmtCheck  = $conexion->prepare($queryCheck);
        $stmtCheck->bindParam(':id_liga',    $id_liga,    PDO::PARAM_INT);
        $stmtCheck->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmtCheck->execute();
        $participacion = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$participacion) {
            throw new Exception("No tienes permisos para eliminar esta liga.");
        }

        // 3. Eliminar la liga — ON DELETE CASCADE borrará en cascada:
        //    participaciones, equipos_fantasy, alineaciones y cualquier
        //    tabla relacionada configurada con CASCADE en el SQL.
        $queryDelete = "DELETE FROM ligas WHERE id_liga = :id_liga";
        $stmtDelete  = $conexion->prepare($queryDelete);
        $stmtDelete->bindParam(':id_liga', $id_liga, PDO::PARAM_INT);
        $stmtDelete->execute();

        if ($stmtDelete->rowCount() === 0) {
            throw new Exception("La liga no existe o ya fue eliminada.");
        }

        $conexion->commit();
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Liga eliminada con éxito."]);

    } catch (Exception $e) {
        $conexion->rollBack();
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
}
?>
