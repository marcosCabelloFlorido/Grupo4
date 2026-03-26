<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

// conexion.php está en la raíz (un nivel arriba de funcionalidades/)
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->nombre_usuario) && !empty($data->nombre_liga) && !empty($data->nombre_equipo)) {
        try {
            $conexion->beginTransaction();

            $queryUser = "SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario";
            $stmtUser  = $conexion->prepare($queryUser);
            $stmtUser->bindParam(':nombre_usuario', $data->nombre_usuario);
            $stmtUser->execute();
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) throw new Exception("El agente ingresado no existe.");
            $id_usuario_real = $usuario['id_usuario'];

            $queryLiga = "INSERT INTO ligas (nombre, tipo) VALUES (:nombre, :tipo)";
            $stmtLiga  = $conexion->prepare($queryLiga);
            $stmtLiga->bindParam(':nombre', $data->nombre_liga);
            $stmtLiga->bindParam(':tipo',   $data->tipo);
            $stmtLiga->execute();
            $id_liga_generado = $conexion->lastInsertId();

            $queryPart = "INSERT INTO participaciones (id_usuario, id_liga, posicion_actual) VALUES (:id_usuario, :id_liga, 1)";
            $stmtPart  = $conexion->prepare($queryPart);
            $stmtPart->bindParam(':id_usuario', $id_usuario_real);
            $stmtPart->bindParam(':id_liga',    $id_liga_generado);
            $stmtPart->execute();

            $queryEquipo = "INSERT INTO equipos_fantasy (id_usuario, id_liga, nombre_equipo) VALUES (:id_usuario, :id_liga, :nombre_equipo)";
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

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['usuario'])) {
        try {
            $queryUser = "SELECT id_usuario FROM usuarios WHERE nombre = :nombre_usuario";
            $stmtUser  = $conexion->prepare($queryUser);
            $stmtUser->bindParam(':nombre_usuario', $_GET['usuario']);
            $stmtUser->execute();
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) throw new Exception("Usuario no encontrado.");
            $id_usuario = $usuario['id_usuario'];

            $query = "SELECT L.id_liga, L.nombre AS nombre_liga, L.tipo,
                             P.posicion_actual, EF.nombre_equipo, EF.presupuesto_disponible, EF.puntos_equipo
                      FROM ligas L
                      INNER JOIN participaciones P  ON L.id_liga    = P.id_liga
                      INNER JOIN equipos_fantasy EF ON P.id_usuario = EF.id_usuario AND P.id_liga = EF.id_liga
                      WHERE P.id_usuario = :id_usuario";

            $stmt = $conexion->prepare($query);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();
            $ligas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(["status" => "success", "total_ligas" => count($ligas), "data" => $ligas]);

        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
}
?>
