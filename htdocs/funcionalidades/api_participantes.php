<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../conexion.php';

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Sesión caducada."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id_liga']) || !ctype_digit($_GET['id_liga'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parámetro id_liga inválido."]);
    exit();
}

$id_liga = (int) $_GET['id_liga'];

try {
    // Verificar que el usuario que consulta pertenece a esta liga
    $stmtAcceso = $conexion->prepare(
        "SELECT U.id_usuario FROM usuarios U
         INNER JOIN participaciones P ON U.id_usuario = P.id_usuario
         WHERE U.nombre = :nombre AND P.id_liga = :id_liga"
    );
    $stmtAcceso->execute([':nombre' => $_SESSION['usuario'], ':id_liga' => $id_liga]);
    if (!$stmtAcceso->fetch()) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "No tienes acceso a esta liga."]);
        exit();
    }

    // Obtener todos los participantes con sus equipos y puntos
    $query = "SELECT U.nombre AS usuario,
                     EF.nombre_equipo,
                     EF.puntos_equipo,
                     EF.presupuesto_disponible,
                     P.posicion_actual,
                     (SELECT COUNT(*) FROM alineaciones A WHERE A.id_equipo_fantasy = EF.id_equipo_fantasy) AS jugadores_en_plantilla
              FROM participaciones P
              INNER JOIN usuarios U ON P.id_usuario = U.id_usuario
              INNER JOIN equipos_fantasy EF ON P.id_usuario = EF.id_usuario AND P.id_liga = EF.id_liga
              WHERE P.id_liga = :id_liga
              ORDER BY EF.puntos_equipo DESC, P.posicion_actual ASC";

    $stmt = $conexion->prepare($query);
    $stmt->execute([':id_liga' => $id_liga]);
    $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "total"  => count($participantes),
        "data"   => $participantes
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>