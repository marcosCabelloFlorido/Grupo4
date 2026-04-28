<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../conexion.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    if (!isset($_SESSION['usuario'])) { echo json_encode(["status" => "error", "message" => "Sesión caducada."]); exit(); }
    try {
        $id_equipo = (int)$data->id_equipo; $id_jugador = (int)$data->id_jugador; $titular = (int)$data->titular;
        $stmtUser = $conexion->prepare("SELECT EF.id_equipo_fantasy FROM equipos_fantasy EF JOIN usuarios U ON EF.id_usuario = U.id_usuario WHERE U.nombre = :nombre AND EF.id_equipo_fantasy = :id_ef");
        $stmtUser->execute([':nombre' => $_SESSION['usuario'], ':id_ef' => $id_equipo]);
        if (!$stmtUser->fetch()) throw new Exception("Permiso denegado.");
        $conexion->prepare("UPDATE alineaciones SET titular = :titular WHERE id_equipo_fantasy = :id_ef AND id_jugador = :id_j")->execute([':titular' => $titular, ':id_ef' => $id_equipo, ':id_j' => $id_jugador]);
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
}
?>